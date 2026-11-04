<?php
/**
 * AVC_AIS_Schema クラス
 *
 * JSON-LD スキーマを生成するクラスで、共通 @graph やページ別のスキーマを出力します。
 *
 * NOTE:
 * AVC 優先モード時は、主要SEOプラグイン向けのフィルタで JSON-LD 出力を抑止する。
 * 出力バッファによる強制除去は行わない。
 *
 */
defined( 'ABSPATH' ) || exit;
class AVC_AIS_Schema {
	/**
	 * @var AVC_AIS_Schema|null
	 */
	private static $instance = null;

	/**
	 * 初期化
	 *
	 * @return AVC_AIS_Schema
	 */
	public static function init() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * plugins_loaded で競合スキーマ抑止を初期化
	 *
	 * @return void
	 */
	public static function bootstrap_competing_schema_control() {
		$instance = self::init();
		$instance->maybe_toggle_competing_schema_plugins();
	}

	/**
	 * 正規化されたオプションを取得
	 *
	 * @return array
	 */
	private function get_normalized_options() {
		return AVC_AIS_Settings::init()->get_options();
	}

	/**
	 * @var array
	 */
	private $runtime_validation_errors = array();
	/**
	 * @var array
	 */
	private $runtime_validation_warnings = array();

	/**
	 * @var bool
	 */
	private $suppressing_external_schema = false;

	/**
	 * @var bool
	 */
	private $local_business_suppressed = false;

	/**
	 * @var array
	 */
	private $local_business_missing_fields = array();

	/**
	 * @var array<int, array{
	 *     hook:string,
	 *     callback:callable,
	 *     priority:int,
	 *     accepted_args:int
	 * }>
	 */
	private $external_schema_filters = array();

	/**
	 * コンストラクタ
	 */
	public function __construct() {
		add_action( 'wp_head', array( $this, 'output_json_ld' ) );
		add_action( 'wp', array( $this, 'apply_schema_priority_for_current_context' ), 0 );
		add_action( 'admin_notices', array( $this, 'display_admin_validation_notice' ) );
		add_action( 'wp_footer', array( $this, 'display_frontend_validation_notice' ) );
		add_action( 'init', array( $this, 'maybe_toggle_competing_schema_plugins' ), 5, 0 );
	}

	/**
	 * JSON-LD スキーマを出力する
 */
	public function output_json_ld() {
		$context = $this->resolve_page_context();
		$options = $this->get_normalized_options();

		if ( 'external' === $this->get_schema_priority_value( $options, $context ) ) {
			return;
		}

		if ( ! $this->is_schema_output_enabled( $options ) ) {
			return;
		}

		if ( is_404() ) {
			return;
		}

		$schema_data = $this->generate_schema();
		if ( empty( $schema_data ) ) {
			return;
		}

		$validator  = new AVC_AIS_Validator();
		$validation = $validator->validate( $schema_data );

		if ( $this->local_business_suppressed ) {
			$message = __(
				'LocalBusiness: Skipped output because required fields were missing.',
				'aivec-ai-search-schema'
			);
			if ( ! empty( $this->local_business_missing_fields ) ) {
				$message .= ' ' . sprintf(
					/* translators: %s: comma separated list of missing fields */
					__( 'Missing fields: %s.', 'aivec-ai-search-schema' ),
					implode( ', ', $this->local_business_missing_fields )
				);
			}
			$validation['warnings'][] = $message;
		}

		$this->runtime_validation_warnings = $validation['warnings'];

		if ( $validation['is_valid'] ) {
			$this->clear_validation_errors();
			$clean_schema = $this->remove_null_values( $validation['schema'] );
			if ( ! empty( $clean_schema ) ) {
				$this->print_json_ld( $clean_schema );
			}
			return;
		}

		$this->handle_validation_failure( $validation['errors'], $validation['schema'] );
	}

	/**
	 * スキーマデータを生成する
	 *
	 * @return array スキーマデータ
	 */
	private function generate_schema() {
		$options  = $this->get_normalized_options();
		$resolver = new AVC_AIS_ContentResolver();
		$builder  = new AVC_AIS_GraphBuilder( $resolver );

		$schema                              = $builder->build( $options );
		$this->local_business_suppressed     = $builder->was_local_business_suppressed();
		$this->local_business_missing_fields = $builder->get_local_business_missing_fields();

		return $schema;
	}

	/**
	 * スキーマ出力が有効かどうかを判定
	 *
	 * @return bool
	 */
	private function is_schema_output_enabled( $options = null ) {
		if ( null === $options ) {
			$options = $this->get_normalized_options();
		}

		$default_enabled = ( 'external' !== $this->get_schema_priority_value( $options ) );

		/**
		 * フィルター: avc_ais_enabled
		 * スキーマ出力を有効 / 無効にする。
		 *
		 * @param bool $enabled 現在の有効状態
		 */
		$is_enabled = (bool) apply_filters( 'avc_ais_enabled', $default_enabled );

		return $is_enabled;
	}

	/**
	 * スキーマ優先設定を取得
	 *
	 * @param array|null $options 設定オプション
	 * @return string avc|external
	 */
	private function get_schema_priority_value( $options = null, ?array $context = null ) {
		if ( null === $options ) {
			$options = $this->get_normalized_options();
		}

		if ( ! is_array( $options ) ) {
			$options = array();
		}

		if ( isset( $options['avc_ais_priority'] ) ) {
			$priority = $options['avc_ais_priority'];
		} elseif ( isset( $options['schema_priority'] ) ) {
			$priority = $options['schema_priority'];
		} else {
			$priority = 'ais';
		}

		if ( ! empty( $context ) ) {
			$override = $this->get_context_override( $options, $context );
			if ( isset( $override['schema_priority'] ) ) {
				$priority = $override['schema_priority'];
			}
		}

		return in_array( $priority, array( 'ais', 'external' ), true ) ? $priority : 'ais';
	}

	/**
	 * ページ文脈ごとのオーバーライド値
	 *
	 * @param array $options
	 * @param array $context
	 * @return array
	 */
	private function get_context_override( array $options, array $context ) {
		$global_breadcrumbs = true;
		if ( array_key_exists( 'avc_ais_breadcrumbs_schema_enabled', $options ) ) {
			$global_breadcrumbs = (bool) $options['avc_ais_breadcrumbs_schema_enabled'];
		} elseif ( array_key_exists( 'enable_breadcrumbs', $options ) ) {
			$global_breadcrumbs = (bool) $options['enable_breadcrumbs'];
		}

		$defaults = array(
			'schema_type'         => 'auto',
			'breadcrumbs_enabled' => $global_breadcrumbs,
			'faq_enabled'         => true,
			'schema_priority'     => $options['avc_ais_priority'] ?? 'ais',
		);

		$settings = isset( $options['content_type_settings'] ) && is_array( $options['content_type_settings'] )
			? $options['content_type_settings']
			: array();

		if ( isset( $context['post_type'], $settings['post_types'][ $context['post_type'] ] ) ) {
			return array_merge( $defaults, $settings['post_types'][ $context['post_type'] ] );
		}

		if ( isset( $context['taxonomy'], $settings['taxonomies'][ $context['taxonomy'] ] ) ) {
			return array_merge( $defaults, $settings['taxonomies'][ $context['taxonomy'] ] );
		}

		return $defaults;
	}

	/**
	 * 現在ページの情報を取得
	 */
	private function resolve_page_context() {
		$context = array(
			'post_id' => 0,
			'url'     => $this->get_current_url(),
			'title'   => get_bloginfo( 'name' ),
			'type'    => 'general',
		);

		if ( is_front_page() ) {
			$context['type'] = 'front_page';
			$front_page_id   = (int) get_option( 'page_on_front' );
			if ( $front_page_id ) {
				$context['post_id'] = $front_page_id;
				$context['url']     = get_permalink( $front_page_id );
				$context['title']   = get_the_title( $front_page_id );
			}
			return $this->normalize_context_title( $context );
		}

		if ( is_home() && ! is_front_page() ) {
			$context['type'] = 'blog_home';
			$posts_page_id   = (int) get_option( 'page_for_posts' );
			if ( $posts_page_id ) {
				$context['post_id'] = $posts_page_id;
				$context['url']     = get_permalink( $posts_page_id );
				$context['title']   = get_the_title( $posts_page_id );
			} else {
				$context['title'] = get_the_archive_title();
			}
			return $this->normalize_context_title( $context );
		}

		if ( is_singular() ) {
			$post_id = get_queried_object_id();
			if ( $post_id ) {
				$context['post_id']   = $post_id;
				$context['url']       = get_permalink( $post_id );
				$context['title']     = get_the_title( $post_id );
				$context['type']      = is_page( $post_id ) ? 'page' : 'singular';
				$context['post_type'] = get_post_type( $post_id );
			}
			return $this->normalize_context_title( $context );
		}

		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term instanceof WP_Term ) {
				$context['term']     = $term;
				$term_link           = get_term_link( $term );
				$context['url']      = is_wp_error( $term_link ) ? $this->get_current_url() : $term_link;
				$context['title']    = $term->name;
				$context['type']     = is_category() ? 'category' : ( is_tag() ? 'tag' : 'taxonomy' );
				$context['taxonomy'] = $term->taxonomy;
			} else {
				$context['type']  = 'taxonomy';
				$context['title'] = get_the_archive_title();
			}
			return $this->normalize_context_title( $context );
		}

		if ( is_author() ) {
			$author = get_queried_object();
			if ( $author instanceof WP_User ) {
				$context['type']   = 'author';
				$context['author'] = $author;
				$context['title']  = $author->display_name;
				$context['url']    = get_author_posts_url( $author->ID );
			} else {
				$context['type']  = 'author';
				$context['title'] = get_the_archive_title();
			}
			return $this->normalize_context_title( $context );
		}

		if ( is_search() ) {
			$query           = get_search_query();
			$context['type'] = 'search';
			if ( $query ) {
				$context['title'] = sprintf(
					/* translators: %s: search query string. */
					__( 'Search results for "%s"', 'aivec-ai-search-schema' ),
					$query
				);
			} else {
				$context['title'] = __( 'Search results', 'aivec-ai-search-schema' );
			}
			$search_link       = get_search_link( $query );
			$context['url']    = is_wp_error( $search_link ) ? $this->get_current_url() : $search_link;
			$context['search'] = $query;
			return $this->normalize_context_title( $context );
		}

		if ( is_archive() ) {
			$context['type']  = 'archive';
			$context['title'] = get_the_archive_title();
		}

		return $this->normalize_context_title( $context );
	}

	/**
	 * ページ文脈のタイトルとURLを正規化
	 *
	 * @param array $context コンテキスト
	 * @return array
	 */
	private function normalize_context_title( array $context ) {
		if ( empty( $context['title'] ) ) {
			$context['title'] = get_bloginfo( 'name' );
		}

		if ( empty( $context['url'] ) ) {
			$context['url'] = $this->get_current_url();
		}

		return $context;
	}

	/**
	 * 現在のURLを推測
	 */
	private function get_current_url() {
		global $wp;

		if ( isset( $wp->request ) && $wp->request ) {
			return home_url( trailingslashit( $wp->request ) );
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
			: '/';
		if ( '' === $request_uri || '/' !== $request_uri[0] ) {
			$request_uri = '/' . ltrim( $request_uri, '/' );
		}

		return home_url( $request_uri );
	}

	/**
	 * 他SEOプラグインのスキーマ出力を制御
	 *
	 * @return void
	 */
	public function maybe_toggle_competing_schema_plugins( ?array $context = null ) {
		$options         = $this->get_normalized_options();
		$should_suppress = ( 'ais' === $this->get_schema_priority_value( $options, $context ) );

		if ( $should_suppress ) {
			$this->suppress_external_schema_plugins();
			return;
		}

		$this->restore_external_schema_plugins();
	}

	/**
	 * 現在のページ文脈に応じて優先度を再評価
	 *
	 * @return void
	 */
	public function apply_schema_priority_for_current_context() {
		$context = $this->resolve_page_context();
		$this->maybe_toggle_competing_schema_plugins( $context );
	}

	/**
	 * 競合プラグインのスキーマを抑止
	 */
	private function suppress_external_schema_plugins() {
		if ( $this->suppressing_external_schema ) {
			return;
		}

			$priority = 99;

		$this->external_schema_filters = array(
			array(
				'hook'          => 'wpseo_json_ld_output',
				'callback'      => '__return_false',
				'priority'      => $priority,
				'accepted_args' => 1,
			),
			array(
				'hook'          => 'wpseo_schema_graph_pieces',
				'callback'      => '__return_empty_array',
				'priority'      => $priority,
				'accepted_args' => 2,
			),
			array(
				'hook'          => 'rank_math/json_ld',
				'callback'      => array( $this, 'filter_return_empty_array' ),
				'priority'      => $priority,
				'accepted_args' => 1,
			),
			array(
				'hook'          => 'rank_math/frontend/json_ld',
				'callback'      => array( $this, 'filter_return_empty_array' ),
				'priority'      => $priority,
				'accepted_args' => 1,
			),
			array(
				'hook'          => 'rank_math/json_ld/disable',
				'callback'      => array( $this, 'filter_return_true' ),
				'priority'      => $priority,
				'accepted_args' => 1,
			),
			array(
				'hook'          => 'aioseo_schema_disable_output',
				'callback'      => '__return_true',
				'priority'      => $priority,
				'accepted_args' => 1,
			),
			array(
				'hook'          => 'aioseo_schema_disable',
				'callback'      => '__return_true',
				'priority'      => $priority,
				'accepted_args' => 1,
			),
			array(
				'hook'          => 'aioseo_schema_enabled',
				'callback'      => array( $this, 'filter_return_false' ),
				'priority'      => $priority,
				'accepted_args' => 1,
			),
			array(
				'hook'          => 'aioseo_schema_graph',
				'callback'      => array( $this, 'filter_return_empty_array' ),
				'priority'      => $priority,
				'accepted_args' => 1,
			),
			array(
				'hook'          => 'aioseo_schema_graph_data',
				'callback'      => array( $this, 'filter_return_empty_array' ),
				'priority'      => $priority,
				'accepted_args' => 1,
			),
			array(
				'hook'          => 'aioseo_schema_graph_output',
				'callback'      => array( $this, 'filter_return_empty_string' ),
				'priority'      => $priority,
				'accepted_args' => 1,
			),
			array(
				'hook'          => 'aioseo_schema_output',
				'callback'      => array( $this, 'filter_return_empty_string' ),
				'priority'      => $priority,
				'accepted_args' => 1,
			),
			array(
				'hook'          => 'aioseo_schema_markup',
				'callback'      => array( $this, 'filter_return_empty_string' ),
				'priority'      => $priority,
				'accepted_args' => 1,
			),
			array(
				'hook'          => 'aioseo_schema_script_output',
				'callback'      => array( $this, 'filter_return_empty_string' ),
				'priority'      => $priority,
				'accepted_args' => 1,
			),
		);

		foreach ( $this->external_schema_filters as $filter ) {
			add_filter( $filter['hook'], $filter['callback'], $filter['priority'], $filter['accepted_args'] );
		}

			$this->suppressing_external_schema = true;
	}

	/**
	 * 抑止していたフィルタを解除
	 */
	private function restore_external_schema_plugins() {
		if ( ! $this->suppressing_external_schema ) {
			return;
		}

		foreach ( $this->external_schema_filters as $filter ) {
			remove_filter( $filter['hook'], $filter['callback'], $filter['priority'] );
		}

		$this->external_schema_filters     = array();
		$this->suppressing_external_schema = false;
	}

	/**
	 * bool(false) を返す汎用コールバック
	 *
	 * @return bool
	 */
	public function filter_return_false( $_value = null ) {
		unset( $_value );
		return false;
	}

	/**
	 * bool(true) を返す汎用コールバック
	 *
	 * @return bool
	 */
	public function filter_return_true( $_value = null ) {
		unset( $_value );
		return true;
	}

	/**
	 * 空配列を返す汎用コールバック
	 *
	 * @return array
	 */
	public function filter_return_empty_array( $_value = null ) {
		unset( $_value );
		return array();
	}

	/**
	 * 空文字列を返す汎用コールバック
	 *
	 * @return string
	 */
	public function filter_return_empty_string( $_value = null ) {
		unset( $_value );
		return '';
	}

	/**
	 * バリデーション失敗時の処理
	 *
	 * @param array $errors
	 * @param array $schema
	 * @return void
	 */
	private function handle_validation_failure( array $errors, array $schema ) {
		if ( empty( $errors ) ) {
			return;
		}

		$this->runtime_validation_errors = $errors;
		$this->set_admin_notice_errors( $errors );
		$this->log_validation_errors( $errors, $schema );

		$clean_schema = $this->remove_null_values( $schema );
		if ( ! empty( $clean_schema ) ) {
			$this->print_json_ld( $clean_schema );
			return;
		}

		$minimal_schema = $this->remove_null_values( $this->get_minimal_schema() );
		if ( ! empty( $minimal_schema ) ) {
			$this->print_json_ld( $minimal_schema );
		}
	}

	/**
	 * バリデーションエラーをログに記録
	 *
	 * @param array $errors
	 * @param array $schema
	 * @return void
	 */
	private function log_validation_errors( array $errors, array $schema ) {
		if ( empty( $errors ) ) {
			return;
		}

		$should_log = defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG;

		if ( $should_log ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Logged only when WP_DEBUG_LOG is enabled.
			error_log( '[AI Search Schema] Validation errors: ' . implode( ', ', $errors ) );
		}

		if ( $this->is_debug_mode() && $should_log ) {
			$schema_json = wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			if ( $schema_json ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Logged only when WP_DEBUG_LOG is enabled.
				error_log( '[AI Search Schema] Schema payload: ' . $schema_json );
			}
		}
	}

	/**
	 * バリデーションエラーを管理画面に通知
	 *
	 * @param array $errors
	 * @return void
	 */
	private function set_admin_notice_errors( array $errors ) {
		if ( ! $this->is_debug_mode() ) {
			return;
		}

		set_transient( 'avc_ais_last_validation_errors', $errors, 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * バリデーションエラーをクリア
	 */
	private function clear_validation_errors() {
		$this->runtime_validation_errors   = array();
		$this->runtime_validation_warnings = array();
		if ( $this->is_debug_mode() ) {
			delete_transient( 'avc_ais_last_validation_errors' );
		}
	}

	/**
	 * JSON-LD を出力
	 *
	 * @param array $schema
	 */
	private function print_json_ld( array $schema ) {
		( new AVC_AIS_Output() )->print( $schema, true );
	}

	/**
	 * null 値を削除
	 *
	 * @param mixed $data
	 * @return mixed
	 */
	private function remove_null_values( $data ) {
		if ( is_array( $data ) ) {
			$is_indexed = array_keys( $data ) === range( 0, count( $data ) - 1 );
			$clean      = array();

			foreach ( $data as $key => $value ) {
				$cleaned = $this->remove_null_values( $value );

				if ( null === $cleaned ) {
					continue;
				}

				if ( is_array( $cleaned ) && empty( $cleaned ) ) {
					continue;
				}

				if ( $is_indexed ) {
					$clean[] = $cleaned;
				} else {
					$clean[ $key ] = $cleaned;
				}
			}

			return $clean;
		}

			return null === $data ? null : $data;
	}

	/**
	 * 最小限のスキーマ
	 *
	 * @return array
	 */
	private function get_minimal_schema() {
		return array(
			'@context' => 'https://schema.org',
			'@graph'   => array(
				array(
					'@type' => 'WebSite',
					'url'   => get_site_url(),
					'name'  => get_bloginfo( 'name' ),
				),
			),
		);
	}

	/**
	 * デバッグモード判定
	 *
	 * @return bool
	 */
	private function is_debug_mode() {
		return defined( 'WP_DEBUG' ) && WP_DEBUG;
	}

	/**
	 * 管理画面でのバリデーションエラー表示
	 */
	public function display_admin_validation_notice() {
		if ( ! is_admin() || ! $this->is_debug_mode() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$errors   = get_transient( 'avc_ais_last_validation_errors' );
		$warnings = $this->runtime_validation_warnings;
		if ( empty( $errors ) && empty( $warnings ) ) {
			return;
		}

		delete_transient( 'avc_ais_last_validation_errors' );

		echo '<div class="notice notice-info is-dismissible ai-search-schema-notice">';
		echo '<p><strong>'
			. esc_html__( 'AI Search Schema – Development Mode Warning', 'aivec-ai-search-schema' )
			. '</strong></p>';
		if ( ! empty( $errors ) ) {
			echo '<p>'
				. esc_html__( 'The following validation errors were detected:', 'aivec-ai-search-schema' )
				. '</p>';
			echo '<ul>';
			foreach ( (array) $errors as $error ) {
				echo '<li>' . esc_html( $error ) . '</li>';
			}
			echo '</ul>';
		}
		if ( ! empty( $warnings ) ) {
			echo '<p>'
				. esc_html__( 'Warnings:', 'aivec-ai-search-schema' )
				. '</p>';
			echo '<ul>';
			foreach ( (array) $warnings as $warning ) {
				echo '<li>' . esc_html( $warning ) . '</li>';
			}
			echo '</ul>';
		}
		echo '<p><small>'
			. esc_html__( 'This warning only appears while WP_DEBUG is true.', 'aivec-ai-search-schema' )
			. '</small></p>';
		echo '</div>';
	}

	/**
	 * フロントエンドでのバリデーションエラー表示
	 */
	public function display_frontend_validation_notice() {
		if (
			$this->is_debug_mode()
			&& ! empty( $this->runtime_validation_errors )
			&& ! is_admin()
			&& current_user_can( 'manage_options' )
		) {
			echo '<div class="notice notice-info ai-search-schema-notice"'
				. ' style="margin:20px;">';
			echo '<p><strong>'
				. esc_html__( 'AI Search Schema – Development Mode Warning', 'aivec-ai-search-schema' )
				. '</strong></p>';
			echo '<p>'
				. esc_html__( 'The following validation errors were detected:', 'aivec-ai-search-schema' )
				. '</p>';
			echo '<ul>';
			foreach ( $this->runtime_validation_errors as $error ) {
				echo '<li>' . esc_html( $error ) . '</li>';
			}
			echo '</ul>';
			if ( ! empty( $this->runtime_validation_warnings ) ) {
				echo '<p>'
					. esc_html__( 'Warnings:', 'aivec-ai-search-schema' )
					. '</p>';
				echo '<ul>';
				foreach ( $this->runtime_validation_warnings as $warning ) {
					echo '<li>' . esc_html( $warning ) . '</li>';
				}
				echo '</ul>';
			}
			echo '<p><small>'
				. esc_html__( 'This warning only appears while WP_DEBUG is true.', 'aivec-ai-search-schema' )
				. '</small></p>';
			echo '</div>';
		}

		$this->runtime_validation_errors = array();
	}
}

add_action( 'plugins_loaded', array( 'AVC_AIS_Schema', 'bootstrap_competing_schema_control' ), 20 );

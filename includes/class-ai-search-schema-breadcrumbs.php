<?php
/**
 * AI_Search_Schema_Breadcrumbs クラス
 *
 * このクラスは、パンくずリストを生成し、サイト内のナビゲーションを提供します。
 */
class AI_Search_Schema_Breadcrumbs {
	/**
	 * @var AI_Search_Schema_Breadcrumbs|null
	 */
	private static $instance = null;
	/**
	 * @var array|null キャッシュされたパンくず配列
	 */
	private $cached_items = null;

	/**
	 * 初期化
	 *
	 * @return AI_Search_Schema_Breadcrumbs
	 */
	public static function init() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * 即時にパンくずを出力
	 *
	 * @return void
	 */
	public static function render() {
		$instance = self::init();
		$instance->output_breadcrumbs();
	}

	/**
	 * コンストラクタ
	 */
	public function __construct() {
		add_action( 'wp', array( $this, 'maybe_hook_breadcrumbs' ) );
	}

	/**
	 * 条件を満たす場合にヘッダーへフック
	 */
	public function maybe_hook_breadcrumbs() {
		if ( ! $this->should_render() ) {
			return;
		}

		add_action( 'wp_body_open', array( $this, 'render_block' ) );
		add_action( 'tha_breadcrumb_top', array( $this, 'output_breadcrumbs' ) );
		add_action( 'tha_breadcrumb_after', array( $this, 'close_block' ) );
		add_action( 'tha_breadcrumb_bottom', array( $this, 'render_block' ) ); // fallback for theme structure
		add_action( 'get_footer', array( $this, 'buffer_output' ) ); // fallback if no hook
	}

	/**
	 * パンくず有効判定
	 *
	 * @return bool
	 */
	private function should_render() {
		if ( ! apply_filters( 'ai_search_schema_show_breadcrumbs', true ) ) {
			return false;
		}

		if ( ! is_singular() && ! is_archive() && ! is_home() ) {
			return false;
		}

		$options = get_option( 'ai_search_schema_options', array() );
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		return $this->is_html_output_enabled( $options );
	}

	/**
	 * HTMLパンくず出力の有効判定
	 *
	 * @param array $options
	 * @return bool
	 */
	private function is_html_output_enabled( array $options ) {
		if ( array_key_exists( 'ai_search_schema_breadcrumbs_html_enabled', $options ) ) {
			return (bool) $options['ai_search_schema_breadcrumbs_html_enabled'];
		}

		if ( array_key_exists( 'enable_breadcrumbs', $options ) ) {
			return (bool) $options['enable_breadcrumbs'];
		}

		return false;
	}

	/**
	 * フックのないテーマ向けの出力
	 */
	public function buffer_output() {
		add_action(
			'wp_footer',
			function () {
				if ( ! $this->should_render() ) {
					return;
				}
				echo '<div class="ais-breadcrumbs ais-breadcrumbs--footer">';
				$this->output_breadcrumbs();
				echo '</div>';
			},
			5
		);
	}

	/**
	 * コンテナを開始
	 */
	public function render_block() {
		echo '<div class="ais-breadcrumbs">';
	}

	/**
	 * コンテナを閉じる
	 */
	public function close_block() {
		echo '</div>';
	}

	/**
	 * パンくずリストを出力する
	 *
	 * @return void
	 */
	public function output_breadcrumbs() {
		if ( ! $this->should_render() ) {
			return;
		}

		$items = $this->get_items();
		if ( empty( $items ) ) {
			return;
		}

		echo '<nav class="breadcrumb" aria-label="Breadcrumbs">';
		echo '<ol>';

		foreach ( $items as $index => $item ) {
			$label = isset( $item['label'] ) ? $item['label'] : ( $item['name'] ?? '' );
			$url   = isset( $item['url'] ) ? $item['url'] : ( $item['item'] ?? '' );

			if ( '' === $label ) {
				continue;
			}

			$is_last = ( count( $items ) - 1 ) === $index;

			echo '<li>';
			if ( ! $is_last && $url ) {
				echo '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
			} else {
				echo '<span aria-current="page">' . esc_html( $label ) . '</span>';
			}
			echo '</li>';
		}

		echo '</ol>';
		echo '</nav>';
	}

	/**
	 * 表示用／スキーマ用のパンくず配列を取得
	 *
	 * @param array $context コンテキスト（省略時は ContentResolver から取得）
	 * @return array
	 */
	public function get_items( array $context = array() ) {
		$should_cache = empty( $context );

		if ( null !== $this->cached_items && $should_cache ) {
			return $this->cached_items;
		}

		if ( $should_cache ) {
			$resolver = new AI_Search_Schema_ContentResolver();
			$context  = $resolver->resolve_page_context();
		}

		$items = $this->build_items( $context );
		$items = apply_filters( 'ai_search_schema_breadcrumb_items', $items, $context );

		if ( $should_cache ) {
			$this->cached_items = $items;
		}

		return $items;
	}

	/**
	 * パンくず配列を構築
	 *
	 * @param array $context
	 * @return array
	 */
	private function build_items( array $context ) {
		if ( isset( $context['type'] ) && 'front_page' === $context['type'] ) {
			return array();
		}

		$items      = array();
		$home_label = apply_filters( 'ai_search_schema_breadcrumb_home_label', __( 'Home', 'ai-search-schema' ) );

		$items[] = array(
			'label' => $home_label,
			'url'   => home_url( '/' ),
		);

		if ( ! empty( $context['post_id'] ) ) {
			$ancestors = array_reverse( get_post_ancestors( $context['post_id'] ) );
			foreach ( $ancestors as $ancestor_id ) {
				$items[] = array(
					'label' => get_the_title( $ancestor_id ),
					'url'   => get_permalink( $ancestor_id ),
				);
			}

			$items[] = array(
				'label' => $context['title'],
				'url'   => $context['url'],
			);
		} elseif (
			isset( $context['type'] )
			&& in_array( $context['type'], array( 'category', 'tag', 'taxonomy' ), true )
			&& ! empty( $context['term'] )
			&& $context['term'] instanceof WP_Term
		) {
			$term = $context['term'];
			if ( is_taxonomy_hierarchical( $term->taxonomy ) ) {
				$ancestors = array_reverse( get_ancestors( $term->term_id, $term->taxonomy ) );
				foreach ( $ancestors as $ancestor_id ) {
					$ancestor = get_term( $ancestor_id, $term->taxonomy );
					if ( $ancestor && ! is_wp_error( $ancestor ) ) {
						$items[] = array(
							'label' => $ancestor->name,
							'url'   => get_term_link( $ancestor ),
						);
					}
				}
			}

			$items[] = array(
				'label' => $context['title'],
				'url'   => $context['url'],
			);
		} elseif (
			isset( $context['type'] )
			&& in_array( $context['type'], array( 'search', 'author', 'archive', 'blog_home' ), true )
		) {
			$items[] = array(
				'label' => $context['title'],
				'url'   => $context['url'],
			);
		} elseif ( ! is_front_page() ) {
			$items[] = array(
				'label' => $context['title'],
				'url'   => $context['url'],
			);
		}

		return ( count( $items ) > 1 ) ? $items : array();
	}
}

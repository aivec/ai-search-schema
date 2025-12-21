<?php
/* phpcs:disable WordPress.Files.LineLength.TooLong */
/**
 * AI_Search_Schema_MetaBox クラス
 *
 * 投稿や固定ページにメタボックスを追加し、AEOスキーマに関連する設定を行うクラスです。
 */
class AI_Search_Schema_MetaBox {
	/**
	 * @var AI_Search_Schema_MetaBox|null
	 */
	private static $instance = null;

	/**
	 * 初期化
	 *
	 * @return AI_Search_Schema_MetaBox
	 */
	public static function init() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

		/**
		 * コンストラクタ
		 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_meta_box' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * メタボックスを追加する
	 */
	public function add_meta_box() {
		$allowed_post_types = apply_filters(
			'ai_search_schema_meta_allowed_post_types',
			array( 'post', 'page' )
		);

		if ( ! is_array( $allowed_post_types ) ) {
			$allowed_post_types = array( 'post', 'page' );
		}

		add_meta_box(
			'ai_search_schema_meta',
			__( 'AEO Schema Settings', 'ai-search-schema' ),
			array( $this, 'render_meta_box' ),
			$allowed_post_types,
			'normal',
			'high'
		);
	}

	/**
	 * メタボックスの内容を表示する
	 *
	 * @param WP_Post $post 投稿オブジェクト
	 */
	public function render_meta_box( $post ) {
		// メタデータを取得.
		$meta               = get_post_meta( $post->ID, '_ai_search_schema_meta', true );
		$page_type          = isset( $meta['page_type'] ) ? esc_attr( $meta['page_type'] ) : 'auto';
		$faq_question_class = isset( $meta['faq_question_class'] ) ? esc_attr( $meta['faq_question_class'] ) : '';
		$faq_answer_class   = isset( $meta['faq_answer_class'] ) ? esc_attr( $meta['faq_answer_class'] ) : '';

		// メタボックスのフォーム.
		?>
		<?php wp_nonce_field( 'ai_search_schema_meta_nonce', 'ai_search_schema_meta_nonce' ); ?>
		<div class="ais-metabox">
			<div class="ais-metabox__card">
				<div class="ais-metabox__header">
					<p class="ais-metabox__eyebrow"><?php esc_html_e( 'AEO Schema', 'ai-search-schema' ); ?></p>
					<h3 class="ais-metabox__title">
						<?php esc_html_e( 'Page schema settings', 'ai-search-schema' ); ?>
					</h3>
						<p class="ais-metabox__description">
							<?php
							esc_html_e(
								'Choose the schema type and optionally set selectors for FAQ extraction.',
								'ai-search-schema'
							);
							?>
						</p>
				</div>

				<div class="ais-metabox__fields">
									<?php
									ais_render_field(
										'ai_search_schema_page_type',
										__( 'Page type:', 'ai-search-schema' ),
										'select',
										$page_type,
										'',
										'ai_search_schema_meta[page_type]',
										array(
											'auto'    => __( 'Automatic', 'ai-search-schema' ),
											'Article' => __( 'Article', 'ai-search-schema' ),
											'FAQPage' => __( 'FAQPage', 'ai-search-schema' ),
											'QAPage'  => __( 'QAPage', 'ai-search-schema' ),
											'WebPage' => __( 'WebPage', 'ai-search-schema' ),
										)
									);

										ais_render_field(
											'ai_search_schema_faq_question_class',
											__( 'FAQ question class:', 'ai-search-schema' ),
											'text',
											$faq_question_class,
											__( 'Example: faq-question, accordion-title', 'ai-search-schema' ),
											'ai_search_schema_meta[faq_question_class]'
										);

										ais_render_field(
											'ai_search_schema_faq_answer_class',
											__( 'FAQ answer class:', 'ai-search-schema' ),
											'text',
											$faq_answer_class,
											__( 'Example: faq-answer, accordion-content', 'ai-search-schema' ),
											'ai_search_schema_meta[faq_answer_class]'
										);
									?>
								</div>
						</div>
				</div>
				<?php
	}

		/**
		 * 投稿・固定ページ編集画面用のスタイルを読み込み
		 *
		 * @param string $hook 現在の管理画面フック名
		 */
	public function enqueue_assets( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
				return;
		}

			$screen = get_current_screen();
		if ( ! $screen ) {
				return;
		}

			$allowed_post_types = apply_filters(
				'ai_search_schema_meta_allowed_post_types',
				array( 'post', 'page' )
			);

		if ( ! is_array( $allowed_post_types ) ) {
				$allowed_post_types = array( 'post', 'page' );
		}

		if ( ! in_array( $screen->post_type, $allowed_post_types, true ) ) {
				return;
		}

			$style_path    = AI_SEARCH_SCHEMA_DIR . 'assets/dist/css/admin.min.css';
			$style_version = file_exists( $style_path ) ? filemtime( $style_path ) : AI_SEARCH_SCHEMA_VERSION;

			wp_enqueue_style(
				'ais-admin',
				AI_SEARCH_SCHEMA_URL . 'assets/dist/css/admin.min.css',
				array(),
				$style_version
			);
	}

	/**
	 * メタボックスのデータを保存する
	 *
	 * @param int $post_id 投稿ID
	 */
	public function save_meta_box( $post_id ) {
			// Autosave やリビジョンでは処理しない。
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
				return;
		}

			// 対象外の投稿タイプでは処理しない。
		$allowed_post_types = apply_filters(
			'ai_search_schema_meta_allowed_post_types',
			array( 'post', 'page' )
		);
		if ( ! is_array( $allowed_post_types ) ) {
			$allowed_post_types = array( 'post', 'page' );
		}

		if ( ! in_array( get_post_type( $post_id ), $allowed_post_types, true ) ) {
			return;
		}

			// nonce チェック
		if (
					! isset( $_POST['ai_search_schema_meta_nonce'] )
					|| ! wp_verify_nonce(
						wp_unslash( $_POST['ai_search_schema_meta_nonce'] ),
						'ai_search_schema_meta_nonce'
					)
			) {
				return;
		}

			// 権限チェック
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return;
		}

				// データを保存
				$raw_meta = isset( $_POST['ai_search_schema_meta'] )
					? (array) wp_unslash( $_POST['ai_search_schema_meta'] )
					: array();

				$allowed_page_types = array( 'auto', 'Article', 'FAQPage', 'QAPage', 'WebPage' );

			$meta = array();

		if ( isset( $raw_meta['page_type'] ) ) {
				$page_type = sanitize_text_field( $raw_meta['page_type'] );

				// 想定外の値は保存せず、デフォルトにフォールバック。
				$meta['page_type'] = in_array( $page_type, $allowed_page_types, true ) ? $page_type : 'auto';
		}

		if ( isset( $raw_meta['faq_question_class'] ) ) {
				$faq_question_class = sanitize_text_field( $raw_meta['faq_question_class'] );

			if ( '' === $faq_question_class || preg_match( '/^[A-Za-z0-9\-_]+$/', $faq_question_class ) ) {
					$meta['faq_question_class'] = $faq_question_class;
			}
		}

		if ( isset( $raw_meta['faq_answer_class'] ) ) {
				$faq_answer_class = sanitize_text_field( $raw_meta['faq_answer_class'] );

			if ( '' === $faq_answer_class || preg_match( '/^[A-Za-z0-9\-_]+$/', $faq_answer_class ) ) {
					$meta['faq_answer_class'] = $faq_answer_class;
			}
		}

			update_post_meta( $post_id, '_ai_search_schema_meta', $meta );
	}
}
/* phpcs:enable WordPress.Files.LineLength.TooLong */

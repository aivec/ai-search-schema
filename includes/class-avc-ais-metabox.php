<?php
/* phpcs:disable WordPress.Files.LineLength.TooLong */
/**
 * AVC_AIS_MetaBox クラス
 *
 * 投稿や固定ページにメタボックスを追加し、AEOスキーマに関連する設定を行うクラスです。
 */
defined( 'ABSPATH' ) || exit;
class AVC_AIS_MetaBox {
	/**
	 * @var AVC_AIS_MetaBox|null
	 */
	private static $instance = null;

	/**
	 * 初期化
	 *
	 * @return AVC_AIS_MetaBox
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
			'avc_ais_meta_allowed_post_types',
			array( 'post', 'page' )
		);

		if ( ! is_array( $allowed_post_types ) ) {
			$allowed_post_types = array( 'post', 'page' );
		}

		add_meta_box(
			'avc_ais_meta',
			__( 'AI Search Schema', 'aivec-ai-search-schema' ),
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
		$meta               = get_post_meta( $post->ID, '_avc_ais_meta', true );
		$page_type          = isset( $meta['page_type'] ) ? esc_attr( $meta['page_type'] ) : 'auto';
		$faq_question_class = isset( $meta['faq_question_class'] ) ? esc_attr( $meta['faq_question_class'] ) : '';
		$faq_answer_class   = isset( $meta['faq_answer_class'] ) ? esc_attr( $meta['faq_answer_class'] ) : '';

		// メタボックスのフォーム.
		?>
		<?php wp_nonce_field( 'avc_ais_meta_nonce', 'avc_ais_meta_nonce' ); ?>
		<div class="ais-metabox">
			<div class="ais-metabox__card">
				<div class="ais-metabox__header">
					<p class="ais-metabox__eyebrow">
						<?php esc_html_e( 'AI Search Schema', 'aivec-ai-search-schema' ); ?>
					</p>
					<h3 class="ais-metabox__title">
						<?php esc_html_e( 'Page schema settings', 'aivec-ai-search-schema' ); ?>
					</h3>
						<p class="ais-metabox__description">
							<?php
							esc_html_e(
								'Choose the schema type and optionally set selectors for FAQ extraction.',
								'aivec-ai-search-schema'
							);
							?>
						</p>
				</div>

				<div class="ais-metabox__fields">
									<?php
									avc_ais_render_field(
										'avc_ais_page_type',
										__( 'Page type:', 'aivec-ai-search-schema' ),
										'select',
										$page_type,
										'',
										'avc_ais_meta[page_type]',
										array(
											'auto'    => __( 'Automatic', 'aivec-ai-search-schema' ),
											'Article' => __( 'Article', 'aivec-ai-search-schema' ),
											'FAQPage' => __( 'FAQPage', 'aivec-ai-search-schema' ),
											'QAPage'  => __( 'QAPage', 'aivec-ai-search-schema' ),
											'WebPage' => __( 'WebPage', 'aivec-ai-search-schema' ),
										)
									);

										avc_ais_render_field(
											'avc_ais_faq_question_class',
											__( 'FAQ question class:', 'aivec-ai-search-schema' ),
											'text',
											$faq_question_class,
											__( 'Example: faq-question, accordion-title', 'aivec-ai-search-schema' ),
											'avc_ais_meta[faq_question_class]'
										);

										avc_ais_render_field(
											'avc_ais_faq_answer_class',
											__( 'FAQ answer class:', 'aivec-ai-search-schema' ),
											'text',
											$faq_answer_class,
											__( 'Example: faq-answer, accordion-content', 'aivec-ai-search-schema' ),
											'avc_ais_meta[faq_answer_class]'
										);

									/**
									 * Pro版用フック: メタボックスのフィールド群の後にコンテンツを追加
									 *
									 * @param WP_Post $post 投稿オブジェクト.
									 */
									do_action( 'avc_ais_metabox_after_fields', $post );
									?>
								</div><!-- /.ais-metabox__fields -->

								<?php
								/**
								 * Pro版用フック: フィールドコンテナの外側にコンテンツを追加
								 *
								 * @param WP_Post $post 投稿オブジェクト.
								 */
								do_action( 'avc_ais_metabox_card_footer', $post );
								?>
						</div><!-- /.ais-metabox__card -->
				</div><!-- /.ais-metabox -->
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
				'avc_ais_meta_allowed_post_types',
				array( 'post', 'page' )
			);

		if ( ! is_array( $allowed_post_types ) ) {
				$allowed_post_types = array( 'post', 'page' );
		}

		if ( ! in_array( $screen->post_type, $allowed_post_types, true ) ) {
				return;
		}

			$style_path    = AVC_AIS_DIR . 'assets/dist/css/admin.min.css';
			$style_version = file_exists( $style_path ) ? filemtime( $style_path ) : AVC_AIS_VERSION;

			wp_enqueue_style(
				'ais-admin',
				AVC_AIS_URL . 'assets/dist/css/admin.min.css',
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
			'avc_ais_meta_allowed_post_types',
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
					! isset( $_POST['avc_ais_meta_nonce'] )
					|| ! wp_verify_nonce(
						sanitize_text_field( wp_unslash( $_POST['avc_ais_meta_nonce'] ) ),
						'avc_ais_meta_nonce'
					)
			) {
				return;
		}

			// 権限チェック
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return;
		}

				// データを保存
				$raw_meta = isset( $_POST['avc_ais_meta'] )
					? (array) wp_unslash( $_POST['avc_ais_meta'] )
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

			update_post_meta( $post_id, '_avc_ais_meta', $meta );
	}
}
/* phpcs:enable WordPress.Files.LineLength.TooLong */

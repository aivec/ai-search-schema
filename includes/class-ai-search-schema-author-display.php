<?php
/**
 * AI_Search_Schema_Author_Display クラス
 *
 * 著者情報をフロントエンドに表示するクラス。
 *
 * @package Aivec\AiSearchSchema
 */

/**
 * 著者情報表示クラス
 */
class AI_Search_Schema_Author_Display {
	/**
	 * シングルトンインスタンス
	 *
	 * @var AI_Search_Schema_Author_Display|null
	 */
	private static $instance = null;

	/**
	 * CSSが出力済みかどうか
	 *
	 * @var bool
	 */
	private $css_printed = false;

	/**
	 * 初期化
	 *
	 * @return AI_Search_Schema_Author_Display
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
		add_filter( 'the_content', array( $this, 'maybe_append_author_box' ), 20 );
	}

	/**
	 * 著者ボックスを表示すべきか判定
	 *
	 * @return bool
	 */
	private function should_display() {
		if ( ! is_singular( array( 'post', 'page' ) ) ) {
			return false;
		}

		if ( ! in_the_loop() || ! is_main_query() ) {
			return false;
		}

		$options = get_option( 'ai_search_schema_options', array() );
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		return ! empty( $options['author_box_enabled'] );
	}

	/**
	 * コンテンツに著者ボックスを追加
	 *
	 * @param string $content コンテンツ。
	 * @return string
	 */
	public function maybe_append_author_box( $content ) {
		if ( ! $this->should_display() ) {
			return $content;
		}

		$author_box = $this->get_author_box_html();
		if ( empty( $author_box ) ) {
			return $content;
		}

		return $content . $author_box;
	}

	/**
	 * 著者ボックスのHTMLを取得
	 *
	 * @param int|null $post_id 投稿ID（省略時は現在の投稿）。
	 * @return string
	 */
	public function get_author_box_html( $post_id = null ) {
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}

		$author_id = $post->post_author;
		$author    = get_user_by( 'id', $author_id );

		if ( ! $author ) {
			return '';
		}

		$author_name = $author->display_name;
		$author_bio  = get_the_author_meta( 'description', $author_id );
		$author_url  = get_author_posts_url( $author_id );
		$avatar      = get_avatar( $author_id, 80, '', $author_name, array( 'class' => 'ais-author-box__avatar-img' ) );

		$css = $this->get_inline_css();

		ob_start();
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS is hardcoded, no user input.
		echo $css;
		?>
		<aside class="ais-author-box" itemscope itemtype="https://schema.org/Person">
			<div class="ais-author-box__header">
				<span class="ais-author-box__label"><?php esc_html_e( 'Written by', 'ai-search-schema' ); ?></span>
			</div>
			<div class="ais-author-box__content">
				<?php if ( $avatar ) : ?>
					<div class="ais-author-box__avatar">
						<a href="<?php echo esc_url( $author_url ); ?>" rel="author">
							<?php echo $avatar; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</a>
					</div>
				<?php endif; ?>
				<div class="ais-author-box__info">
					<h4 class="ais-author-box__name" itemprop="name">
						<a href="<?php echo esc_url( $author_url ); ?>" rel="author" itemprop="url">
							<?php echo esc_html( $author_name ); ?>
						</a>
					</h4>
					<?php if ( $author_bio ) : ?>
						<p class="ais-author-box__bio" itemprop="description">
							<?php echo esc_html( $author_bio ); ?>
						</p>
					<?php endif; ?>
				</div>
			</div>
		</aside>
		<?php
		return ob_get_clean();
	}

	/**
	 * インラインCSSを取得
	 *
	 * @return string
	 */
	private function get_inline_css() {
		if ( $this->css_printed ) {
			return '';
		}

		$this->css_printed = true;

		$css = '
<style>
.ais-author-box {
	margin: 2em 0;
	padding: 1.5em;
	background: #f8fafc;
	border-radius: 12px;
	border: 1px solid #e4e8f0;
	font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}
.ais-author-box__header {
	margin-bottom: 1em;
	padding-bottom: 0.75em;
	border-bottom: 1px solid #e4e8f0;
}
.ais-author-box__label {
	font-size: 0.75em;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.05em;
	color: #5f6b7c;
}
.ais-author-box__content {
	display: flex;
	gap: 1.25em;
	align-items: flex-start;
}
.ais-author-box__avatar {
	flex-shrink: 0;
}
.ais-author-box__avatar a {
	display: block;
}
.ais-author-box__avatar-img {
	width: 80px;
	height: 80px;
	border-radius: 50%;
	object-fit: cover;
	border: 3px solid #fff;
	box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.ais-author-box__info {
	flex: 1;
	min-width: 0;
}
.ais-author-box__name {
	margin: 0 0 0.5em;
	font-size: 1.125em;
	font-weight: 600;
	line-height: 1.3;
}
.ais-author-box__name a {
	color: #1a1a1a;
	text-decoration: none;
}
.ais-author-box__name a:hover {
	color: #3858e9;
	text-decoration: underline;
}
.ais-author-box__bio {
	margin: 0;
	font-size: 0.9375em;
	line-height: 1.6;
	color: #4a5568;
}
@media (max-width: 480px) {
	.ais-author-box__content {
		flex-direction: column;
		align-items: center;
		text-align: center;
	}
	.ais-author-box__avatar-img {
		width: 64px;
		height: 64px;
	}
}
</style>';

		return $css;
	}

	/**
	 * 静的メソッドで著者ボックスを出力
	 *
	 * @param int|null $post_id 投稿ID。
	 * @return void
	 */
	public static function render( $post_id = null ) {
		$instance = self::init();
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $instance->get_author_box_html( $post_id );
	}
}

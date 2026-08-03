<?php
/**
 * llms.txt Generator
 *
 * Generates and serves llms.txt file to help AI systems understand site structure.
 *
 * @package AVC_AIS_Schema
 * @see https://llmstxt.org/
 */

/**
 * AVC_AIS_Llms_Txt class
 */
defined( 'ABSPATH' ) || exit;
class AVC_AIS_Llms_Txt {

	/**
	 * Option name for stored content.
	 */
	const OPTION_NAME = 'avc_ais_llms_txt';

	/**
	 * Option name for enabled flag.
	 */
	const OPTION_ENABLED = 'avc_ais_llms_txt_enabled';

	/**
	 * Singleton instance.
	 *
	 * @var AVC_AIS_Llms_Txt|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return AVC_AIS_Llms_Txt
	 */
	public static function init() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor - initialize hooks.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_action( 'template_redirect', array( $this, 'serve_llms_txt' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_filter( 'redirect_canonical', array( $this, 'disable_canonical_redirect' ) );
	}

	/**
	 * llms.txt へのリクエストで正規化リダイレクトを抑止する。
	 *
	 * WordPress の redirect_canonical() は /llms.txt を「末尾スラッシュ付き」へ
	 * 301 リダイレクトしてしまう。llms.txt は仕様上ルート直下の単一ファイルとして
	 * 参照されるため、リダイレクトを追わない取得クライアントに届かなくなる。
	 *
	 * @param string|false $redirect_url リダイレクト先 URL.
	 * @return string|false llms.txt リクエストの場合は false（リダイレクトしない）.
	 */
	public function disable_canonical_redirect( $redirect_url ) {
		if ( get_query_var( 'llms_txt' ) ) {
			return false;
		}
		return $redirect_url;
	}

	/**
	 * Check if llms.txt is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return '1' === get_option( self::OPTION_ENABLED, '1' );
	}

	/**
	 * Add rewrite rule for /llms.txt
	 */
	public function add_rewrite_rules() {
		if ( ! $this->is_enabled() ) {
			return;
		}
		add_rewrite_rule( '^llms\.txt$', 'index.php?llms_txt=1', 'top' );
	}

	/**
	 * Register query var.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'llms_txt';
		return $vars;
	}

	/**
	 * Serve llms.txt content.
	 */
	public function serve_llms_txt() {
		if ( ! get_query_var( 'llms_txt' ) ) {
			return;
		}

		if ( ! $this->is_enabled() ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			return;
		}

		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		// ブラウザによる MIME スニッフィングを禁止し、text/plain が HTML として
		// 解釈されることを防ぐ（下の非エスケープ出力を安全にするための前提条件）.
		header( 'X-Content-Type-Options: nosniff' );

		// llms.txt は HTML ではなく Markdown（text/plain）として配信する。
		// esc_html() を通すと '>' が '&gt;' に変換され、llms.txt 仕様の
		// 引用ブロック（> サイト説明）や本文が壊れるため、ここではエスケープしない。
		// 安全性は以下で担保している:
		//   - Content-Type が text/plain かつ X-Content-Type-Options: nosniff
		//   - 手動入力は save_content() の sanitize_textarea_field() で無害化済み
		//   - 自動生成分は制御文字を除去する sanitize_output() を通す
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->sanitize_output( $this->get_content() );
		exit;
	}

	/**
	 * HTML エスケープ済みの文字列をプレーンテキストへ戻す。
	 *
	 * llms.txt は text/plain の Markdown として配信するため、WordPress が
	 * 保存時に付与した HTML エンティティ（&amp; / &#039; など）は復号する必要がある。
	 * 復号後の値は sanitize_output() で制御文字を除去したうえで出力される。
	 *
	 * @param string $text 対象の文字列.
	 * @return string 復号済みの文字列.
	 */
	private static function to_plain_text( $text ) {
		return html_entity_decode( (string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	/**
	 * text/plain として配信する前に出力を無害化する。
	 *
	 * HTML エスケープは行わない（Markdown が壊れるため）。代わりに、
	 * レスポンス分割やヘッダー汚染に悪用されうる制御文字と NUL バイトを除去し、
	 * 改行コードを LF に統一する。
	 *
	 * @param string $content 出力予定の内容.
	 * @return string 無害化済みの内容.
	 */
	private function sanitize_output( $content ) {
		$content = (string) $content;

		// 改行コードを LF に正規化.
		$content = str_replace( array( "\r\n", "\r" ), "\n", $content );

		// タブ・改行以外の制御文字（C0 制御文字と DEL）を除去.
		$content = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $content );

		return null === $content ? '' : $content;
	}

	/**
	 * Get llms.txt content.
	 *
	 * @return string
	 */
	public function get_content() {
		$content = get_option( self::OPTION_NAME, '' );
		if ( empty( $content ) ) {
			$content = $this->generate_default_content();
		}
		return $content;
	}

	/**
	 * Generate default content from site data.
	 *
	 * @return string
	 */
	public function generate_default_content() {
		// WordPress は blogname / blogdescription を sanitize_option() で HTML
		// エスケープした状態で保存する。llms.txt は text/plain として配信するため、
		// エンティティのまま出すと「&amp;」等がそのまま見えてしまう。ここで復号する。
		$site_name = self::to_plain_text( get_bloginfo( 'name' ) );
		$site_desc = self::to_plain_text( get_bloginfo( 'description' ) );

		$content  = "# {$site_name}\n\n";
		$content .= "> {$site_desc}\n\n";

		// Main pages.
		$content .= "## メインコンテンツ / Main Content\n\n";
		$pages    = get_pages(
			array(
				'number'      => 20,
				'sort_column' => 'menu_order',
			)
		);
		if ( ! empty( $pages ) ) {
			foreach ( $pages as $page ) {
				$url     = get_permalink( $page );
				$title   = self::to_plain_text( $page->post_title );
				$excerpt = self::to_plain_text(
					wp_trim_words( wp_strip_all_tags( $page->post_content ), 20, '...' )
				);
				if ( empty( $excerpt ) ) {
					$excerpt = $title;
				}
				$content .= "- [{$title}]({$url}): {$excerpt}\n";
			}
		}

		// Recent posts.
		$content .= "\n## 最新記事 / Recent Posts\n\n";
		$posts    = get_posts( array( 'numberposts' => 10 ) );
		if ( ! empty( $posts ) ) {
			foreach ( $posts as $post ) {
				$url     = get_permalink( $post );
				$title   = self::to_plain_text( $post->post_title );
				$excerpt = self::to_plain_text(
					wp_trim_words( wp_strip_all_tags( $post->post_content ), 20, '...' )
				);
				if ( empty( $excerpt ) ) {
					$excerpt = $title;
				}
				$content .= "- [{$title}]({$url}): {$excerpt}\n";
			}
		}

		/**
		 * Filter the default llms.txt content.
		 *
		 * @param string $content Default generated content.
		 */
		return apply_filters( 'avc_ais_llms_txt_default_content', $content );
	}

	/**
	 * Save content.
	 *
	 * @param string $content Content to save.
	 * @return bool
	 */
	public function save_content( $content ) {
		return update_option( self::OPTION_NAME, sanitize_textarea_field( $content ) );
	}

	/**
	 * Set enabled status.
	 *
	 * @param bool $enabled Whether to enable llms.txt.
	 * @return bool
	 */
	public function set_enabled( $enabled ) {
		return update_option( self::OPTION_ENABLED, $enabled ? '1' : '0' );
	}

	/**
	 * Reset to auto-generated content.
	 *
	 * @return string New content.
	 */
	public function reset_content() {
		delete_option( self::OPTION_NAME );
		return $this->generate_default_content();
	}

	/**
	 * Flush rewrite rules.
	 * Called on plugin activation.
	 */
	public static function activate() {
		$instance = self::init();
		$instance->add_rewrite_rules();
		flush_rewrite_rules();
	}

	/**
	 * Flush rewrite rules on deactivation.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}

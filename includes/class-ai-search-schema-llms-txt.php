<?php
/**
 * llms.txt Generator
 *
 * Generates and serves llms.txt file to help AI systems understand site structure.
 *
 * @package AI_Search_Schema
 * @see https://llmstxt.org/
 */

/**
 * AI_Search_Schema_Llms_Txt class
 */
class AI_Search_Schema_Llms_Txt {

	/**
	 * Option name for stored content.
	 */
	const OPTION_NAME = 'ai_search_schema_llms_txt';

	/**
	 * Option name for enabled flag.
	 */
	const OPTION_ENABLED = 'ai_search_schema_llms_txt_enabled';

	/**
	 * Singleton instance.
	 *
	 * @var AI_Search_Schema_Llms_Txt|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return AI_Search_Schema_Llms_Txt
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

		header( 'Content-Type: text/markdown; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markdown content.
		echo $this->get_content();
		exit;
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
		$site_name = get_bloginfo( 'name' );
		$site_desc = get_bloginfo( 'description' );

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
				$title   = $page->post_title;
				$excerpt = wp_trim_words( wp_strip_all_tags( $page->post_content ), 20, '...' );
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
				$title   = $post->post_title;
				$excerpt = wp_trim_words( wp_strip_all_tags( $post->post_content ), 20, '...' );
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
		return apply_filters( 'ai_search_schema_llms_txt_default_content', $content );
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

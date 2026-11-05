<?php
/**
 * llms.txt の配信出力に関する回帰テスト。
 *
 * llms.txt は text/plain の Markdown として配信されるため、
 * HTML エスケープを掛けると仕様上の引用ブロック（`> 説明`）が壊れる。
 * また WordPress の正規化リダイレクトで `/llms.txt/` へ 301 されると
 * リダイレクトを追わない取得クライアントに届かなくなる。
 * その両方の再発を防ぐ。
 */

require_once __DIR__ . '/bootstrap.php';

class AVC_AIS_LlmsTxtOutput_Test extends WP_UnitTestCase {

	/**
	 * 対象インスタンス。
	 *
	 * @var AVC_AIS_Llms_Txt
	 */
	private $llms;

	public function set_up() {
		parent::set_up();
		require_once AVC_AIS_DIR . 'includes/class-avc-ais-llms-txt.php';

		// シングルトンをリセットして毎テストをクリーンな状態にする.
		$reflection = new ReflectionClass( 'AVC_AIS_Llms_Txt' );
		$instance   = $reflection->getProperty( 'instance' );
		avc_ais_test_make_accessible( $instance );
		$instance->setValue( null, null );

		$this->llms = AVC_AIS_Llms_Txt::init();

		delete_option( AVC_AIS_Llms_Txt::OPTION_NAME );
		delete_option( AVC_AIS_Llms_Txt::OPTION_ENABLED );
	}

	public function tear_down() {
		delete_option( AVC_AIS_Llms_Txt::OPTION_NAME );
		delete_option( AVC_AIS_Llms_Txt::OPTION_ENABLED );
		parent::tear_down();
	}

	/**
	 * private な sanitize_output() を呼び出す。
	 *
	 * @param string $content 入力.
	 * @return string 出力.
	 */
	private function sanitize_output( $content ) {
		$method = new ReflectionMethod( 'AVC_AIS_Llms_Txt', 'sanitize_output' );
		avc_ais_test_make_accessible( $method );
		return $method->invoke( $this->llms, $content );
	}

	/**
	 * Markdown の引用記号が HTML エンティティへ変換されないこと。
	 * これが本来の再現ケース（esc_html() による破壊）。
	 */
	public function test_output_keeps_markdown_blockquote() {
		$content = "# Site\n\n> Site description\n";

		$output = $this->sanitize_output( $content );

		$this->assertStringContainsString( '> Site description', $output );
		$this->assertStringNotContainsString( '&gt;', $output );
	}

	/**
	 * アポストロフィや引用符がエンティティ化されないこと。
	 */
	public function test_output_keeps_quotes_unescaped() {
		$content = "It's a \"quoted\" word & an ampersand";

		$output = $this->sanitize_output( $content );

		$this->assertSame( $content, $output );
		$this->assertStringNotContainsString( '&#039;', $output );
		$this->assertStringNotContainsString( '&quot;', $output );
		$this->assertStringNotContainsString( '&amp;', $output );
	}

	/**
	 * Markdown のリンク記法がそのまま保持されること。
	 */
	public function test_output_keeps_markdown_links() {
		$content = '- [Page Title](http://example.com/page/): Summary text';

		$this->assertSame( $content, $this->sanitize_output( $content ) );
	}

	/**
	 * 制御文字と NUL バイトが除去されること（ヘッダー汚染対策）。
	 */
	public function test_output_strips_control_characters() {
		$content = "# Site\x00\x07 Title\x1F";

		$output = $this->sanitize_output( $content );

		$this->assertSame( '# Site Title', $output );
	}

	/**
	 * 改行コードが LF に正規化され、タブと改行は保持されること。
	 */
	public function test_output_normalizes_newlines_and_keeps_tabs() {
		$content = "line1\r\nline2\rline3\n\tindented";

		$output = $this->sanitize_output( $content );

		$this->assertSame( "line1\nline2\nline3\n\tindented", $output );
	}

	/**
	 * 自動生成された既定コンテンツでも引用ブロックが壊れないこと。
	 */
	public function test_generated_content_blockquote_survives_output() {
		update_option( 'blogdescription', "Tokyo's finest & freshest" );

		$output = $this->sanitize_output( $this->llms->generate_default_content() );

		$this->assertStringContainsString( "> Tokyo's finest & freshest", $output );
		$this->assertStringNotContainsString( '&gt;', $output );
		$this->assertStringNotContainsString( '&amp;', $output );
	}

	/**
	 * llms.txt リクエストでは正規化リダイレクトが抑止されること。
	 */
	public function test_canonical_redirect_is_disabled_for_llms_txt() {
		set_query_var( 'llms_txt', 1 );

		$this->assertFalse(
			$this->llms->disable_canonical_redirect( 'http://example.com/llms.txt/' )
		);
	}

	/**
	 * llms.txt 以外のリクエストでは正規化リダイレクトを妨げないこと。
	 */
	public function test_canonical_redirect_is_untouched_for_other_requests() {
		set_query_var( 'llms_txt', '' );

		$this->assertSame(
			'http://example.com/some-page/',
			$this->llms->disable_canonical_redirect( 'http://example.com/some-page/' )
		);
	}

	/**
	 * redirect_canonical フィルターが実際に登録されていること。
	 */
	public function test_canonical_redirect_filter_is_registered() {
		$this->assertNotFalse(
			has_filter( 'redirect_canonical', array( $this->llms, 'disable_canonical_redirect' ) )
		);
	}
}

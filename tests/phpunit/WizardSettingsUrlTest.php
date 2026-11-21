<?php
/**
 * ウィザードから設定画面へのリンクのテスト。
 *
 * @package Aivec\AiSearchSchema\Tests
 */

require_once __DIR__ . '/bootstrap.php';

use Aivec\AiSearchSchema\Wizard\Wizard;

/**
 * 設定画面はトップレベルのメニューなので、options-general.php の URL ではページの題が見つからない。
 * 題が空になり、admin-header.php で strip_tags( null ) の非推奨の警告が出る。
 */
class WizardSettingsUrlTest extends WP_UnitTestCase {
	/**
	 * メニューから開く設定画面の URL.
	 */
	const SETTINGS_URL = 'admin.php?page=aivec-ai-search-schema';

	/**
	 * @var Wizard
	 */
	private $wizard;

	protected function setUp(): void {
		parent::setUp();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->wizard = new Wizard();
	}

	protected function tearDown(): void {
		unset( $_GET['step'] );
		$_POST    = array();
		$_REQUEST = array();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * 最初と最後のステップの、設定画面へのリンクがメニューと同じ URL であること。
	 *
	 * @dataProvider provide_steps_with_settings_link
	 *
	 * @param string $step ステップ.
	 */
	public function test_step_links_to_the_menu_url( $step ) {
		$_GET['step'] = $step;

		ob_start();
		$this->wizard->render_wizard_page();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( esc_url( admin_url( self::SETTINGS_URL ) ), $html );
		$this->assertStringNotContainsString( 'options-general.php', $html );
	}

	/**
	 * 設定画面へのリンクがあるステップを返す。
	 *
	 * @return array<string,array<int,string>>
	 */
	public function provide_steps_with_settings_link() {
		return array(
			'welcome'  => array( 'welcome' ),
			'complete' => array( 'complete' ),
		);
	}

	/**
	 * スクリプトに渡す設定画面の URL がメニューと同じであること。
	 */
	public function test_script_data_uses_the_menu_url() {
		$this->wizard->enqueue_scripts( 'admin_page_' . Wizard::PAGE_SLUG );

		$data = (string) wp_scripts()->get_data( 'avc-ais-wizard', 'data' );
		$this->assertSame( 1, preg_match( '/var avcAisWizardData = (\{.*\});/s', $data, $matches ) );
		$this->assertSame( admin_url( self::SETTINGS_URL ), json_decode( $matches[1], true )['settingsUrl'] ?? null );
	}

	/**
	 * 完了の AJAX の移動先がメニューと同じ URL であること。
	 */
	public function test_completion_redirects_to_the_menu_url() {
		$_POST    = array( 'nonce' => wp_create_nonce( 'avc_ais_wizard_nonce' ) );
		$_REQUEST = $_POST;
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () {
				return static function () {
					throw new RuntimeException( 'ajax-finished' );
				};
			}
		);

		ob_start();
		try {
			$this->wizard->ajax_complete();
			$this->fail( 'AJAX ハンドラーは wp_die() で終了するはず' );
		} catch ( RuntimeException $e ) {
			$this->assertSame( 'ajax-finished', $e->getMessage() );
		} finally {
			$response = json_decode( (string) ob_get_clean(), true );
		}

		$this->assertSame( admin_url( self::SETTINGS_URL ), $response['data']['redirectUrl'] ?? null );
	}
}

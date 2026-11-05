<?php
/**
 * Google Maps API キーがブラウザや進捗データへ出ないことのテスト。
 *
 * @package Aivec\AiSearchSchema\Tests
 */

require_once __DIR__ . '/bootstrap.php';

use Aivec\AiSearchSchema\Wizard\Wizard;

/**
 * API キー露出のテストクラス。
 */
class ApiKeyExposureTest extends WP_UnitTestCase {

	/**
	 * テストに使う API キー。
	 */
	const API_KEY = 'AIzaSyTestSecretKey';

	/**
	 * ウィザードのインスタンス。
	 *
	 * @var Wizard
	 */
	private $wizard;

	/**
	 * テスト前のセットアップ。
	 */
	protected function setUp(): void {
		parent::setUp();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->wizard = new Wizard();
		delete_option( 'avc_ais_gmaps_api_key' );
		delete_option( Wizard::OPTION_API_KEY_PURGED );
	}

	/**
	 * テスト後のクリーンアップ。
	 */
	protected function tearDown(): void {
		delete_option( 'avc_ais_gmaps_api_key' );
		delete_option( Wizard::OPTION_API_KEY_PURGED );
		$_POST = array();
		parent::tearDown();
	}

	/**
	 * ウィザードの API キー入力欄に保存済みのキーを出力しないこと。
	 */
	public function test_template_does_not_print_stored_key() {
		update_option( 'avc_ais_gmaps_api_key', self::API_KEY, false );

		ob_start();
		include AVC_AIS_DIR . 'templates/wizard/steps/step-api-key.php';
		$html = (string) ob_get_clean();

		$this->assertStringNotContainsString( self::API_KEY, $html );
		$this->assertStringContainsString( 'name="gmaps_api_key"', $html );
		$this->assertStringContainsString( 'value=""', $html );
	}

	/**
	 * ブラウザの自動入力でキーが上書きされないよう、保存後は入力欄を無効化すること。
	 */
	public function test_template_guards_against_browser_autofill() {
		ob_start();
		include AVC_AIS_DIR . 'templates/wizard/steps/step-api-key.php';
		$without_key = (string) ob_get_clean();

		update_option( 'avc_ais_gmaps_api_key', self::API_KEY, false );

		ob_start();
		include AVC_AIS_DIR . 'templates/wizard/steps/step-api-key.php';
		$with_key = (string) ob_get_clean();

		$this->assertStringContainsString( 'autocomplete="new-password"', $without_key );
		$this->assertStringNotContainsString( "disabled='disabled'", $without_key );
		$this->assertStringContainsString( "disabled='disabled'", $with_key );
	}

	/**
	 * save_progress() が API キーを user meta に保存しないこと。
	 */
	public function test_save_progress_drops_api_key() {
		$this->wizard->save_progress(
			array(
				'current_step' => 'api-key',
				'data'         => array(
					'api-key' => array( 'gmaps_api_key' => self::API_KEY ),
					'basics'  => array( 'site_name' => 'Kept' ),
				),
			)
		);

		$stored = get_user_meta( get_current_user_id(), Wizard::META_PROGRESS, true );

		$this->assertStringNotContainsString( self::API_KEY, wp_json_encode( $stored ) );
		$this->assertSame( 'Kept', $stored['data']['basics']['site_name'] );
	}

	/**
	 * 以前のバージョンが保存した API キーを、ログインしないユーザーの分も消し込むこと。
	 */
	public function test_purge_covers_keys_of_all_users() {
		$other_user = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$legacy     = array(
			'current_step' => 'hours',
			'data'         => array(
				'api-key' => array( 'gmaps_api_key' => self::API_KEY ),
				'basics'  => array( 'site_name' => 'Kept' ),
			),
		);
		update_user_meta( get_current_user_id(), Wizard::META_PROGRESS, $legacy );
		update_user_meta( $other_user, Wizard::META_PROGRESS, $legacy );

		$this->wizard->maybe_purge_api_keys_from_progress();

		foreach ( array( get_current_user_id(), $other_user ) as $user_id ) {
			$stored = get_user_meta( $user_id, Wizard::META_PROGRESS, true );
			$this->assertStringNotContainsString( self::API_KEY, wp_json_encode( $stored ) );
			$this->assertSame( 'Kept', $stored['data']['basics']['site_name'] );
		}
		$this->assertNotEmpty( get_option( Wizard::OPTION_API_KEY_PURGED ) );
	}

	/**
	 * バックアップの復元などで古いデータが残っていても、読み出しでは API キーを返さないこと。
	 */
	public function test_get_progress_never_returns_api_key() {
		update_user_meta(
			get_current_user_id(),
			Wizard::META_PROGRESS,
			array( 'data' => array( 'api-key' => array( 'gmaps_api_key' => self::API_KEY ) ) )
		);

		$this->assertStringNotContainsString( self::API_KEY, wp_json_encode( $this->wizard->get_progress() ) );
	}

	/**
	 * 消し込みは管理者のアクセスを契機とし、一度だけ実行すること。
	 */
	public function test_purge_requires_admin_and_runs_once() {
		$legacy = array( 'data' => array( 'api-key' => array( 'gmaps_api_key' => self::API_KEY ) ) );
		$admin  = get_current_user_id();
		update_user_meta( $admin, Wizard::META_PROGRESS, $legacy );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->wizard->maybe_purge_api_keys_from_progress();
		$this->assertFalse( get_option( Wizard::OPTION_API_KEY_PURGED ), '権限がなければ実行しない' );

		wp_set_current_user( $admin );
		$this->wizard->maybe_purge_api_keys_from_progress();
		$this->assertNotEmpty( get_option( Wizard::OPTION_API_KEY_PURGED ) );

		update_user_meta( $admin, Wizard::META_PROGRESS, $legacy );
		$this->wizard->maybe_purge_api_keys_from_progress();
		$this->assertSame( $legacy, get_user_meta( $admin, Wizard::META_PROGRESS, true ), '2 回目は実行しない' );
	}

	/**
	 * 以前のバージョンが自動読み込みで保存したキーを読み込まなくすること。
	 */
	public function test_purge_stops_autoloading_stored_key() {
		add_option( 'avc_ais_gmaps_api_key', self::API_KEY, '', true );
		$this->assertArrayHasKey( 'avc_ais_gmaps_api_key', wp_load_alloptions( true ), '前提: 自動読み込みされている' );

		$this->wizard->maybe_purge_api_keys_from_progress();

		$this->assertArrayNotHasKey( 'avc_ais_gmaps_api_key', wp_load_alloptions( true ) );
		$this->assertSame( self::API_KEY, get_option( 'avc_ais_gmaps_api_key' ) );
	}

	/**
	 * 管理画面の読み込み時に消し込みが走るようフックされていること。
	 */
	public function test_purge_runs_on_admin_init() {
		$this->wizard->register();

		$this->assertNotFalse( has_action( 'admin_init', array( $this->wizard, 'maybe_purge_api_keys_from_progress' ) ) );
	}

	/**
	 * ステップ保存の AJAX が API キーを保存し、応答と進捗には含めないこと。
	 */
	public function test_ajax_save_step_stores_key_only_in_dedicated_option() {
		$_POST    = array(
			'nonce' => wp_create_nonce( 'avc_ais_wizard_nonce' ),
			'step'  => 'api-key',
			'data'  => array( 'gmaps_api_key' => self::API_KEY ),
		);
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
			$this->wizard->ajax_save_step();
			$this->fail( 'AJAX ハンドラーは wp_die() で終了するはず' );
		} catch ( RuntimeException $e ) {
			$this->assertSame( 'ajax-finished', $e->getMessage() );
		} finally {
			$response = (string) ob_get_clean();
			$_REQUEST = array();
		}

		$this->assertStringContainsString( '"success":true', $response );
		$this->assertStringNotContainsString( self::API_KEY, $response );
		$this->assertSame( self::API_KEY, get_option( 'avc_ais_gmaps_api_key' ) );
		$this->assertStringNotContainsString(
			self::API_KEY,
			wp_json_encode( get_user_meta( get_current_user_id(), Wizard::META_PROGRESS, true ) )
		);
	}

	/**
	 * 入力欄を空のまま保存しても、保存済みのキーが消えないこと。
	 */
	public function test_blank_submission_keeps_stored_key() {
		update_option( 'avc_ais_gmaps_api_key', self::API_KEY, false );

		$method = new ReflectionMethod( Wizard::class, 'save_to_settings' );
		avc_ais_test_make_accessible( $method );
		$method->invoke( $this->wizard, 'api-key', array( 'gmaps_api_key' => '' ) );

		$this->assertSame( self::API_KEY, get_option( 'avc_ais_gmaps_api_key' ) );
	}
}

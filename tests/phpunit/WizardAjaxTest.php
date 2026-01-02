<?php
/**
 * Wizard AJAX ハンドラーテスト。
 *
 * ajax_save_step, ajax_skip_step, ajax_complete, ajax_reset,
 * ajax_import, ajax_get_schema のテストを行います。
 *
 * @package Aivec\AiSearchSchema\Tests
 */

require_once __DIR__ . '/bootstrap.php';

use Aivec\AiSearchSchema\Wizard\Wizard;
use Aivec\AiSearchSchema\Wizard\Wizard_Importer;

/**
 * Wizard AJAX テストクラス。
 */
class WizardAjaxTest extends WP_UnitTestCase {

	/**
	 * Wizard インスタンス。
	 *
	 * @var Wizard
	 */
	private $wizard;

	/**
	 * テスト用ユーザーID。
	 *
	 * @var int
	 */
	private $admin_user_id;

	/**
	 * テスト用一般ユーザーID。
	 *
	 * @var int
	 */
	private $subscriber_user_id;

	/**
	 * AJAX レスポンスを格納。
	 *
	 * @var array
	 */
	private $ajax_response = array();

	/**
	 * テスト前のセットアップ。
	 */
	protected function setUp(): void {
		parent::setUp();
		AI_Search_Schema_TEST_Env::$options = array();

		// 管理者ユーザーを作成.
		$this->admin_user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		// 一般ユーザーを作成.
		$this->subscriber_user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		// 管理者でログイン.
		wp_set_current_user( $this->admin_user_id );

		$this->wizard = new Wizard();
	}

	/**
	 * テスト後のクリーンアップ。
	 */
	protected function tearDown(): void {
		// ユーザーメタをクリア.
		delete_user_meta( $this->admin_user_id, 'ai_search_schema_wizard_progress' );
		delete_user_meta( $this->subscriber_user_id, 'ai_search_schema_wizard_progress' );

		// オプションをクリア.
		delete_option( 'ai_search_schema_wizard_completed' );
		delete_option( 'ai_search_schema_wizard_completed_at' );
		delete_option( 'ai_search_schema_options' );

		// POSTデータをクリア.
		$_POST = array();

		wp_set_current_user( 0 );
		parent::tearDown();
	}

	// ===== Helper Methods =====

	/**
	 * AJAX nonce を設定。
	 *
	 * @param string $action Nonce action.
	 */
	private function set_ajax_nonce( $action = 'ai_search_schema_wizard_nonce' ) {
		$_POST['nonce']  = wp_create_nonce( $action );
		$_REQUEST['_wpnonce'] = $_POST['nonce'];
	}

	/**
	 * AJAX レスポンスをキャプチャするためのフック。
	 *
	 * @param array $data Response data.
	 */
	public function capture_json_response( $data ) {
		$this->ajax_response = $data;
	}

	// ===== Progress Methods Tests =====

	/**
	 * get_progress がデフォルト構造を返すことをテスト。
	 */
	public function test_get_progress_returns_default_structure() {
		$progress = $this->wizard->get_progress();

		$this->assertIsArray( $progress );
		$this->assertArrayHasKey( 'current_step', $progress );
		$this->assertArrayHasKey( 'completed_steps', $progress );
		$this->assertArrayHasKey( 'skipped_steps', $progress );
		$this->assertArrayHasKey( 'data', $progress );
		$this->assertEquals( 'welcome', $progress['current_step'] );
		$this->assertIsArray( $progress['completed_steps'] );
		$this->assertIsArray( $progress['skipped_steps'] );
		$this->assertIsArray( $progress['data'] );
	}

	/**
	 * save_progress と get_progress が正しく動作することをテスト。
	 */
	public function test_save_and_get_progress() {
		$test_progress = array(
			'current_step'    => 'basics',
			'completed_steps' => array( 'welcome' ),
			'skipped_steps'   => array(),
			'started_at'      => gmdate( 'Y-m-d H:i:s' ),
			'data'            => array(
				'basics' => array(
					'company_name' => 'Test Company',
				),
			),
		);

		$this->wizard->save_progress( $test_progress );
		$retrieved = $this->wizard->get_progress();

		$this->assertEquals( 'basics', $retrieved['current_step'] );
		$this->assertContains( 'welcome', $retrieved['completed_steps'] );
		$this->assertEquals( 'Test Company', $retrieved['data']['basics']['company_name'] );
	}

	/**
	 * 複数のステップを完了した進捗を保存できることをテスト。
	 */
	public function test_save_progress_with_multiple_completed_steps() {
		$test_progress = array(
			'current_step'    => 'location',
			'completed_steps' => array( 'welcome', 'basics', 'type' ),
			'skipped_steps'   => array( 'api-key' ),
			'started_at'      => gmdate( 'Y-m-d H:i:s' ),
			'data'            => array(
				'basics' => array( 'company_name' => 'Test' ),
				'type'   => array( 'site_type' => 'local_business' ),
			),
		);

		$this->wizard->save_progress( $test_progress );
		$retrieved = $this->wizard->get_progress();

		$this->assertEquals( 'location', $retrieved['current_step'] );
		$this->assertCount( 3, $retrieved['completed_steps'] );
		$this->assertContains( 'api-key', $retrieved['skipped_steps'] );
	}

	// ===== Completion Status Tests =====

	/**
	 * is_completed がデフォルトで false を返すことをテスト。
	 */
	public function test_is_completed_returns_false_by_default() {
		$this->assertFalse( $this->wizard->is_completed() );
	}

	/**
	 * mark_completed が完了状態を設定することをテスト。
	 */
	public function test_mark_completed_sets_completion_status() {
		$this->wizard->mark_completed();

		$this->assertTrue( $this->wizard->is_completed() );
		$this->assertNotEmpty( get_option( 'ai_search_schema_wizard_completed_at' ) );
	}

	/**
	 * 完了フラグがユーザー間で共有されることをテスト。
	 */
	public function test_completion_status_is_shared() {
		// 管理者1で完了.
		$this->wizard->mark_completed();

		// 別の管理者でログイン.
		$admin2_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin2_id );

		$wizard2 = new Wizard();

		// 完了状態は共有される.
		$this->assertTrue( $wizard2->is_completed() );

		// クリーンアップ.
		delete_user_meta( $admin2_id, 'ai_search_schema_wizard_progress' );
	}

	// ===== Wizard URL Tests =====

	/**
	 * get_wizard_url が正しいURLを生成することをテスト。
	 */
	public function test_get_wizard_url_returns_correct_url() {
		$url = $this->wizard->get_wizard_url();

		$this->assertStringContainsString( 'page=ais-wizard', $url );
		$this->assertStringContainsString( 'admin.php', $url );
	}

	/**
	 * get_wizard_url がステップパラメータを含むことをテスト。
	 */
	public function test_get_wizard_url_with_step_parameter() {
		$url = $this->wizard->get_wizard_url( 'basics' );

		$this->assertStringContainsString( 'page=ais-wizard', $url );
		$this->assertStringContainsString( 'step=basics', $url );
	}

	// ===== sanitize_step_data Tests (via Reflection) =====

	/**
	 * basics ステップのサニタイズをテスト（UI入力名）。
	 */
	public function test_sanitize_basics_step() {
		$reflection = new ReflectionClass( $this->wizard );
		$method     = $reflection->getMethod( 'sanitize_step_data' );
		$method->setAccessible( true );

		$input = array(
			'site_name'        => '<b>Site Name</b>',
			'site_description' => 'Test description',
			'logo_url'         => 'https://example.com/logo.png',
		);

		$result = $method->invoke( $this->wizard, 'basics', $input );

		$this->assertEquals( 'Site Name', $result['site_name'] );
		$this->assertEquals( 'Test description', $result['site_description'] );
		$this->assertEquals( 'https://example.com/logo.png', $result['logo_url'] );
		// company_name should fallback to site_name.
		$this->assertEquals( 'Site Name', $result['company_name'] );
	}

	/**
	 * type ステップのサニタイズをテスト（entity_type）。
	 */
	public function test_sanitize_type_step() {
		$reflection = new ReflectionClass( $this->wizard );
		$method     = $reflection->getMethod( 'sanitize_step_data' );
		$method->setAccessible( true );

		$input = array(
			'entity_type'   => 'LocalBusiness',
			'business_type' => 'Restaurant<script>',
		);

		$result = $method->invoke( $this->wizard, 'type', $input );

		$this->assertEquals( 'LocalBusiness', $result['entity_type'] );
		$this->assertEquals( 'Restaurant', $result['business_type'] );
	}

	/**
	 * location ステップのサニタイズをテスト（UI入力名）。
	 */
	public function test_sanitize_location_step() {
		$reflection = new ReflectionClass( $this->wizard );
		$method     = $reflection->getMethod( 'sanitize_step_data' );
		$method->setAccessible( true );

		$input = array(
			'local_business_name' => 'Test Business',
			'local_business_type' => 'Restaurant',
			'postal_code'         => '100-0001',
			'address_region'      => '東京都',
			'address_locality'    => '千代田区',
			'street_address'      => '霞が関1-1',
			'telephone'           => '03-1234-5678',
			'geo_latitude'        => '35.6762',
			'geo_longitude'       => '139.6503',
		);

		$result = $method->invoke( $this->wizard, 'location', $input );

		$this->assertEquals( 'Test Business', $result['local_business_name'] );
		$this->assertEquals( 'Restaurant', $result['local_business_type'] );
		$this->assertEquals( '03-1234-5678', $result['telephone'] );

		// Check address array.
		$this->assertEquals( '100-0001', $result['address']['postal_code'] );
		$this->assertEquals( '東京都', $result['address']['region'] );
		$this->assertEquals( '千代田区', $result['address']['locality'] );
		$this->assertEquals( '霞が関1-1', $result['address']['street_address'] );

		// Check geo array.
		$this->assertEquals( 35.6762, $result['geo']['latitude'] );
		$this->assertEquals( 139.6503, $result['geo']['longitude'] );
	}

	/**
	 * hours ステップのサニタイズをテスト（UI入力形式）。
	 */
	public function test_sanitize_hours_step() {
		$reflection = new ReflectionClass( $this->wizard );
		$method     = $reflection->getMethod( 'sanitize_step_data' );
		$method->setAccessible( true );

		// UI sends hours_{day}_opens/closes format.
		$input = array(
			'hours_monday_opens'   => '09:00',
			'hours_monday_closes'  => '18:00',
			'hours_tuesday_opens'  => '10:00',
			'hours_tuesday_closes' => '17:00',
			'price_range'          => '$$',
			'accepts_reservations' => '1',
		);

		$result = $method->invoke( $this->wizard, 'hours', $input );

		$this->assertCount( 2, $result['opening_hours'] );
		$this->assertEquals( 'monday', $result['opening_hours'][0]['day'] );
		$this->assertEquals( '09:00', $result['opening_hours'][0]['open'] );
		$this->assertEquals( '18:00', $result['opening_hours'][0]['close'] );
		$this->assertEquals( 'tuesday', $result['opening_hours'][1]['day'] );
		$this->assertEquals( '$$', $result['price_range'] );
		$this->assertTrue( $result['accepts_reservations'] );
	}

	/**
	 * hours ステップで無効な曜日がフィルタリングされることをテスト。
	 */
	public function test_sanitize_hours_filters_invalid_days() {
		$reflection = new ReflectionClass( $this->wizard );
		$method     = $reflection->getMethod( 'sanitize_step_data' );
		$method->setAccessible( true );

		$input = array(
			'opening_hours' => array(
				array(
					'day'   => 'monday',
					'open'  => '09:00',
					'close' => '18:00',
				),
				array(
					'day'   => 'invalid_day',
					'open'  => '09:00',
					'close' => '18:00',
				),
			),
		);

		$result = $method->invoke( $this->wizard, 'hours', $input );

		$this->assertCount( 1, $result['opening_hours'] );
		$this->assertEquals( 'monday', $result['opening_hours'][0]['day'] );
	}

	/**
	 * api-key ステップのサニタイズをテスト。
	 */
	public function test_sanitize_api_key_step() {
		$reflection = new ReflectionClass( $this->wizard );
		$method     = $reflection->getMethod( 'sanitize_step_data' );
		$method->setAccessible( true );

		$input = array(
			'gmaps_api_key' => '  AIzaSyTest<script>Key  ',
		);

		$result = $method->invoke( $this->wizard, 'api-key', $input );

		$this->assertEquals( 'AIzaSyTestKey', $result['gmaps_api_key'] );
	}

	// ===== save_to_settings Tests (via Reflection) =====

	/**
	 * save_to_settings が basics ステップを保存することをテスト。
	 */
	public function test_save_to_settings_basics() {
		$reflection = new ReflectionClass( $this->wizard );
		$method     = $reflection->getMethod( 'save_to_settings' );
		$method->setAccessible( true );

		$data = array(
			'company_name'     => 'Test Company',
			'site_name'        => 'Test Site',
			'site_description' => 'Test description',
			'logo_url'         => 'https://example.com/logo.png',
		);

		$method->invoke( $this->wizard, 'basics', $data );

		$settings = get_option( 'ai_search_schema_options', array() );

		$this->assertEquals( 'Test Company', $settings['company_name'] );
		$this->assertEquals( 'Test Site', $settings['site_name'] );
		$this->assertEquals( 'https://example.com/logo.png', $settings['logo'] );
	}

	/**
	 * save_to_settings が type ステップを保存することをテスト。
	 */
	public function test_save_to_settings_type() {
		$reflection = new ReflectionClass( $this->wizard );
		$method     = $reflection->getMethod( 'save_to_settings' );
		$method->setAccessible( true );

		$data = array(
			'entity_type'   => 'LocalBusiness',
			'business_type' => 'Restaurant',
		);

		$method->invoke( $this->wizard, 'type', $data );

		$settings = get_option( 'ai_search_schema_options', array() );

		$this->assertEquals( 'LocalBusiness', $settings['entity_type'] );
		$this->assertEquals( 'WebPage', $settings['content_model'] );
		$this->assertEquals( 'Restaurant', $settings['local_business_type'] );
	}

	/**
	 * save_to_settings が Organization タイプを正しく保存することをテスト。
	 */
	public function test_save_to_settings_type_organization() {
		$reflection = new ReflectionClass( $this->wizard );
		$method     = $reflection->getMethod( 'save_to_settings' );
		$method->setAccessible( true );

		$data = array(
			'entity_type' => 'Organization',
		);

		$method->invoke( $this->wizard, 'type', $data );

		$settings = get_option( 'ai_search_schema_options', array() );

		$this->assertEquals( 'Organization', $settings['entity_type'] );
		$this->assertEquals( 'WebPage', $settings['content_model'] );
	}

	/**
	 * save_to_settings が location ステップを保存することをテスト。
	 */
	public function test_save_to_settings_location() {
		$reflection = new ReflectionClass( $this->wizard );
		$method     = $reflection->getMethod( 'save_to_settings' );
		$method->setAccessible( true );

		$data = array(
			'local_business_name' => 'Test Business',
			'local_business_type' => 'Restaurant',
			'address'             => array(
				'postal_code'    => '100-0001',
				'region'         => '東京都',
				'locality'       => '千代田区',
				'street_address' => '霞が関1-1',
				'country'        => 'JP',
			),
			'geo'                 => array(
				'latitude'  => 35.6762,
				'longitude' => 139.6503,
			),
			'telephone'           => '03-1234-5678',
			'email'               => 'test@example.com',
			'price_range'         => '$$',
		);

		$method->invoke( $this->wizard, 'location', $data );

		$settings = get_option( 'ai_search_schema_options', array() );

		// company_name should be set from local_business_name.
		$this->assertEquals( 'Test Business', $settings['company_name'] );
		$this->assertEquals( 'Restaurant', $settings['local_business_type'] );
		$this->assertEquals( '03-1234-5678', $settings['phone'] );
		$this->assertEquals( 'test@example.com', $settings['email'] );
		$this->assertEquals( '$$', $settings['price_range'] );

		// Check address array.
		$this->assertIsArray( $settings['address'] );
		$this->assertEquals( '100-0001', $settings['address']['postal_code'] );
		$this->assertEquals( '東京都', $settings['address']['region'] );

		// Check geo array.
		$this->assertIsArray( $settings['geo'] );
		$this->assertEquals( 35.6762, $settings['geo']['latitude'] );
	}

	/**
	 * save_to_settings が hours ステップを保存することをテスト。
	 */
	public function test_save_to_settings_hours() {
		$reflection = new ReflectionClass( $this->wizard );
		$method     = $reflection->getMethod( 'save_to_settings' );
		$method->setAccessible( true );

		$data = array(
			'opening_hours'        => array(
				array(
					'day'   => 'monday',
					'open'  => '09:00',
					'close' => '18:00',
				),
			),
			'price_range'          => '$$',
			'accepts_reservations' => true,
		);

		$method->invoke( $this->wizard, 'hours', $data );

		$settings = get_option( 'ai_search_schema_options', array() );

		$this->assertNotEmpty( $settings['opening_hours'] );
		$this->assertEquals( '$$', $settings['price_range'] );
		$this->assertEquals( '1', $settings['accepts_reservations'] );
	}

	/**
	 * save_to_settings が api-key ステップを別オプションに保存することをテスト。
	 */
	public function test_save_to_settings_api_key_saves_to_separate_option() {
		$reflection = new ReflectionClass( $this->wizard );
		$method     = $reflection->getMethod( 'save_to_settings' );
		$method->setAccessible( true );

		$data = array(
			'gmaps_api_key' => 'AIzaSyTestKey',
		);

		$method->invoke( $this->wizard, 'api-key', $data );

		$api_key  = get_option( 'ai_search_schema_gmaps_api_key' );
		$settings = get_option( 'ai_search_schema_options', array() );

		$this->assertEquals( 'AIzaSyTestKey', $api_key );
		// メイン設定には保存されない.
		$this->assertArrayNotHasKey( 'gmaps_api_key', $settings );
	}

	// ===== get_steps Tests =====

	/**
	 * get_steps が全ステップを返すことをテスト。
	 */
	public function test_get_steps_returns_all_steps() {
		$steps = $this->wizard->get_steps();

		$this->assertIsArray( $steps );
		$this->assertArrayHasKey( 'welcome', $steps );
		$this->assertArrayHasKey( 'basics', $steps );
		$this->assertArrayHasKey( 'type', $steps );
		$this->assertArrayHasKey( 'api-key', $steps );
		$this->assertArrayHasKey( 'location', $steps );
		$this->assertArrayHasKey( 'hours', $steps );
		$this->assertArrayHasKey( 'complete', $steps );
	}

	/**
	 * 各ステップが必須プロパティを持つことをテスト。
	 */
	public function test_each_step_has_required_properties() {
		$steps = $this->wizard->get_steps();

		foreach ( $steps as $key => $step ) {
			$this->assertArrayHasKey( 'name', $step, "Step '{$key}' should have 'name'" );
			$this->assertArrayHasKey( 'view', $step, "Step '{$key}' should have 'view'" );
			$this->assertArrayHasKey( 'required', $step, "Step '{$key}' should have 'required'" );
		}
	}

	/**
	 * basics と type ステップが必須であることをテスト。
	 */
	public function test_required_steps() {
		$steps = $this->wizard->get_steps();

		$this->assertTrue( $steps['basics']['required'] );
		$this->assertTrue( $steps['type']['required'] );
		$this->assertFalse( $steps['welcome']['required'] );
		$this->assertFalse( $steps['location']['required'] );
		$this->assertFalse( $steps['hours']['required'] );
	}

	// ===== Edge Cases =====

	/**
	 * 空のデータでサニタイズが動作することをテスト。
	 */
	public function test_sanitize_handles_empty_data() {
		$reflection = new ReflectionClass( $this->wizard );
		$method     = $reflection->getMethod( 'sanitize_step_data' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->wizard, 'basics', array() );

		$this->assertArrayHasKey( 'site_name', $result );
		$this->assertArrayHasKey( 'site_description', $result );
		$this->assertArrayHasKey( 'logo_url', $result );
		$this->assertArrayHasKey( 'company_name', $result );
	}

	/**
	 * 未知のステップがデフォルトサニタイズを使用することをテスト。
	 */
	public function test_sanitize_unknown_step_uses_default() {
		$reflection = new ReflectionClass( $this->wizard );
		$method     = $reflection->getMethod( 'sanitize_step_data' );
		$method->setAccessible( true );

		$input = array(
			'field1' => '  value1  ',
			'field2' => 'value2',
		);

		$result = $method->invoke( $this->wizard, 'unknown_step', $input );

		// sanitize_text_field はトリムする.
		$this->assertEquals( 'value1', $result['field1'] );
		$this->assertEquals( 'value2', $result['field2'] );
	}

	/**
	 * 進捗がユーザーごとに分離されることをテスト。
	 */
	public function test_progress_is_user_specific() {
		// 管理者1で進捗を保存.
		$this->wizard->save_progress(
			array(
				'current_step'    => 'basics',
				'completed_steps' => array( 'welcome' ),
				'skipped_steps'   => array(),
				'data'            => array(),
			)
		);

		// 管理者2を作成してログイン.
		$admin2_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin2_id );

		$wizard2  = new Wizard();
		$progress = $wizard2->get_progress();

		// 新しいユーザーはデフォルト進捗を持つ.
		$this->assertEquals( 'welcome', $progress['current_step'] );
		$this->assertEmpty( $progress['completed_steps'] );

		// クリーンアップ.
		delete_user_meta( $admin2_id, 'ai_search_schema_wizard_progress' );
	}

	/**
	 * XSS 攻撃がサニタイズされることをテスト。
	 */
	public function test_xss_prevention() {
		$reflection = new ReflectionClass( $this->wizard );
		$method     = $reflection->getMethod( 'sanitize_step_data' );
		$method->setAccessible( true );

		$malicious_input = array(
			'site_name'        => '<script>alert("XSS")</script>Site Name',
			'site_description' => '<img src=x onerror=alert(1)>Description',
			'logo_url'         => 'javascript:alert(1)',
		);

		$result = $method->invoke( $this->wizard, 'basics', $malicious_input );

		$this->assertStringNotContainsString( '<script>', $result['site_name'] );
		$this->assertStringNotContainsString( 'onerror', $result['site_description'] );
		$this->assertStringNotContainsString( 'javascript:', $result['logo_url'] );
	}

	/**
	 * 営業時間に holiday が含まれることをテスト。
	 */
	public function test_opening_hours_includes_holiday() {
		$reflection = new ReflectionClass( $this->wizard );
		$method     = $reflection->getMethod( 'sanitize_step_data' );
		$method->setAccessible( true );

		$input = array(
			'opening_hours' => array(
				array(
					'day'   => 'holiday',
					'open'  => '10:00',
					'close' => '17:00',
				),
			),
		);

		$result = $method->invoke( $this->wizard, 'hours', $input );

		$this->assertCount( 1, $result['opening_hours'] );
		$this->assertEquals( 'holiday', $result['opening_hours'][0]['day'] );
	}

	/**
	 * location ステップで緯度経度が浮動小数点として処理されることをテスト。
	 */
	public function test_location_coordinates_as_float() {
		$reflection = new ReflectionClass( $this->wizard );
		$method     = $reflection->getMethod( 'sanitize_step_data' );
		$method->setAccessible( true );

		$input = array(
			'geo_latitude'  => '35.6762',
			'geo_longitude' => '139.6503',
		);

		$result = $method->invoke( $this->wizard, 'location', $input );

		$this->assertIsFloat( $result['geo']['latitude'] );
		$this->assertIsFloat( $result['geo']['longitude'] );
		$this->assertEquals( 35.6762, $result['geo']['latitude'] );
		$this->assertEquals( 139.6503, $result['geo']['longitude'] );
	}

	/**
	 * 無効な緯度経度が0になることをテスト。
	 */
	public function test_invalid_coordinates_become_zero() {
		$reflection = new ReflectionClass( $this->wizard );
		$method     = $reflection->getMethod( 'sanitize_step_data' );
		$method->setAccessible( true );

		$input = array(
			'geo_latitude'  => 'not_a_number',
			'geo_longitude' => '',
		);

		$result = $method->invoke( $this->wizard, 'location', $input );

		$this->assertEquals( 0.0, $result['geo']['latitude'] );
		$this->assertEquals( 0.0, $result['geo']['longitude'] );
	}
}

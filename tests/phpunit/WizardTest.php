<?php
/**
 * Wizard テスト。
 *
 * セットアップウィザードの主要機能をテストします。
 *
 * @package Aivec\AiSearchSchema\Tests
 */

require_once __DIR__ . '/bootstrap.php';

use Aivec\AiSearchSchema\Wizard\Wizard;

/**
 * Wizard クラスのテスト。
 */
class WizardTest extends WP_UnitTestCase {

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
	private $user_id;

	/**
	 * テスト前のセットアップ。
	 */
	protected function setUp(): void {
		parent::setUp();
		AI_Search_Schema_TEST_Env::$options = array();

		// テスト用ユーザーを作成してログイン状態にする.
		$this->user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->user_id );

		$this->wizard = new Wizard();
	}

	/**
	 * テスト後のクリーンアップ。
	 */
	protected function tearDown(): void {
		// ユーザーメタをクリア.
		delete_user_meta( $this->user_id, 'ai_search_schema_wizard_progress' );
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * ステップ定義が正しく取得できることをテスト。
	 */
	public function test_get_steps_returns_all_steps() {
		$steps = $this->wizard->get_steps();

		$this->assertIsArray( $steps );
		$this->assertArrayHasKey( 'welcome', $steps );
		$this->assertArrayHasKey( 'basics', $steps );
		$this->assertArrayHasKey( 'type', $steps );
		$this->assertArrayHasKey( 'location', $steps );
		$this->assertArrayHasKey( 'hours', $steps );
		$this->assertArrayHasKey( 'complete', $steps );
	}

	/**
	 * 各ステップに必要なプロパティがあることをテスト。
	 */
	public function test_each_step_has_required_properties() {
		$steps = $this->wizard->get_steps();

		foreach ( $steps as $key => $step ) {
			$this->assertArrayHasKey( 'name', $step, "Step '{$key}' should have 'name' property" );
			$this->assertArrayHasKey( 'view', $step, "Step '{$key}' should have 'view' property" );
			$this->assertArrayHasKey( 'required', $step, "Step '{$key}' should have 'required' property" );
		}
	}

	/**
	 * ウィザード完了状態の判定をテスト。
	 */
	public function test_is_completed_returns_false_by_default() {
		$this->assertFalse( $this->wizard->is_completed() );
	}

	/**
	 * ウィザード完了後の状態をテスト。
	 */
	public function test_mark_completed_sets_option() {
		$this->wizard->mark_completed();

		$this->assertTrue( $this->wizard->is_completed() );
		$this->assertNotEmpty( get_option( 'ai_search_schema_wizard_completed_at' ) );
	}

	/**
	 * 進捗データの初期状態をテスト。
	 */
	public function test_get_progress_returns_default_structure() {
		$progress = $this->wizard->get_progress();

		$this->assertIsArray( $progress );
		$this->assertArrayHasKey( 'current_step', $progress );
		$this->assertArrayHasKey( 'completed_steps', $progress );
		$this->assertArrayHasKey( 'skipped_steps', $progress );
		$this->assertArrayHasKey( 'data', $progress );
		$this->assertEquals( 'welcome', $progress['current_step'] );
	}

	/**
	 * 進捗データの保存と取得をテスト。
	 */
	public function test_save_and_get_progress() {
		$progress = array(
			'current_step'    => 'type',
			'completed_steps' => array( 'welcome', 'basics' ),
			'skipped_steps'   => array(),
			'started_at'      => '2025-12-08 00:00:00',
			'data'            => array(
				'basics' => array( 'site_name' => 'Test Site' ),
			),
		);

		$this->wizard->save_progress( $progress );
		$retrieved = $this->wizard->get_progress();

		$this->assertEquals( 'type', $retrieved['current_step'] );
		$this->assertContains( 'basics', $retrieved['completed_steps'] );
		$this->assertEquals( 'Test Site', $retrieved['data']['basics']['site_name'] );
	}

	/**
	 * ウィザードURLの生成をテスト。
	 */
	public function test_get_wizard_url_without_step() {
		$url = $this->wizard->get_wizard_url();

		$this->assertStringContainsString( 'page=ais-wizard', $url );
	}

	/**
	 * ステップ指定付きウィザードURLの生成をテスト。
	 */
	public function test_get_wizard_url_with_step() {
		$url = $this->wizard->get_wizard_url( 'type' );

		$this->assertStringContainsString( 'page=ais-wizard', $url );
		$this->assertStringContainsString( 'step=type', $url );
	}

	/**
	 * basicsステップのサニタイズをテスト。
	 */
	public function test_sanitize_basics_step_data() {
		$wizard = new Wizard();

		// リフレクションを使ってプライベートメソッドにアクセス.
		$reflection = new ReflectionClass( $wizard );
		$method     = $reflection->getMethod( 'sanitize_step_data' );
		$method->setAccessible( true );

		// Test with UI input names.
		$input = array(
			'site_name'        => '<script>alert("xss")</script>My Site',
			'site_description' => '  Test description  ',
			'logo_url'         => 'https://example.com/logo.png',
		);

		$result = $method->invoke( $wizard, 'basics', $input );

		$this->assertEquals( 'My Site', $result['site_name'] );
		$this->assertEquals( 'Test description', $result['site_description'] );
		$this->assertEquals( 'https://example.com/logo.png', $result['logo_url'] );
		// company_name should fallback to site_name.
		$this->assertEquals( 'My Site', $result['company_name'] );
	}

	/**
	 * basicsステップで company_name が明示的に指定された場合のテスト。
	 */
	public function test_sanitize_basics_step_with_explicit_company_name() {
		$wizard = new Wizard();

		$reflection = new ReflectionClass( $wizard );
		$method     = $reflection->getMethod( 'sanitize_step_data' );
		$method->setAccessible( true );

		$input = array(
			'company_name' => '  Test Company  ',
			'site_name'    => 'My Site',
			'logo_id'      => '123',
		);

		$result = $method->invoke( $wizard, 'basics', $input );

		$this->assertEquals( 'Test Company', $result['company_name'] );
		$this->assertEquals( 'My Site', $result['site_name'] );
		$this->assertEquals( 123, $result['logo_id'] );
	}

	/**
	 * typeステップのサニタイズをテスト（entity_type）。
	 */
	public function test_sanitize_type_step_data() {
		$wizard = new Wizard();

		$reflection = new ReflectionClass( $wizard );
		$method     = $reflection->getMethod( 'sanitize_step_data' );
		$method->setAccessible( true );

		// UI sends entity_type directly.
		$input = array(
			'entity_type'   => 'LocalBusiness',
			'business_type' => 'Restaurant',
		);

		$result = $method->invoke( $wizard, 'type', $input );

		$this->assertEquals( 'LocalBusiness', $result['entity_type'] );
		$this->assertEquals( 'Restaurant', $result['business_type'] );
	}

	/**
	 * typeステップでPerson/WebSiteがOrganizationにマップされることをテスト。
	 */
	public function test_sanitize_type_maps_person_to_organization() {
		$wizard = new Wizard();

		$reflection = new ReflectionClass( $wizard );
		$method     = $reflection->getMethod( 'sanitize_step_data' );
		$method->setAccessible( true );

		$input = array( 'entity_type' => 'Person' );
		$result = $method->invoke( $wizard, 'type', $input );
		$this->assertEquals( 'Organization', $result['entity_type'] );

		$input = array( 'entity_type' => 'WebSite' );
		$result = $method->invoke( $wizard, 'type', $input );
		$this->assertEquals( 'Organization', $result['entity_type'] );
	}

	/**
	 * typeステップのレガシーsite_typeサポートをテスト。
	 */
	public function test_sanitize_type_legacy_site_type() {
		$wizard = new Wizard();

		$reflection = new ReflectionClass( $wizard );
		$method     = $reflection->getMethod( 'sanitize_step_data' );
		$method->setAccessible( true );

		$input = array( 'site_type' => 'local_business' );
		$result = $method->invoke( $wizard, 'type', $input );
		$this->assertEquals( 'LocalBusiness', $result['entity_type'] );
	}

	/**
	 * locationステップのサニタイズをテスト（UI入力名）。
	 */
	public function test_sanitize_location_step_data() {
		$wizard = new Wizard();

		$reflection = new ReflectionClass( $wizard );
		$method     = $reflection->getMethod( 'sanitize_step_data' );
		$method->setAccessible( true );

		// UI input names.
		$input = array(
			'local_business_name' => 'Test Business',
			'local_business_type' => 'Restaurant',
			'postal_code'         => '100-0001',
			'address_region'      => '東京都',
			'address_locality'    => '千代田区',
			'street_address'      => '霞が関1-1',
			'address_country'     => 'JP',
			'telephone'           => '03-1234-5678',
			'email'               => 'test@example.com',
			'geo_latitude'        => '35.6762',
			'geo_longitude'       => '139.6503',
			'price_range'         => '$$',
		);

		$result = $method->invoke( $wizard, 'location', $input );

		$this->assertEquals( 'Test Business', $result['local_business_name'] );
		$this->assertEquals( 'Restaurant', $result['local_business_type'] );
		$this->assertEquals( '03-1234-5678', $result['telephone'] );
		$this->assertEquals( 'test@example.com', $result['email'] );
		$this->assertEquals( '$$', $result['price_range'] );

		// Check address array.
		$this->assertIsArray( $result['address'] );
		$this->assertEquals( '100-0001', $result['address']['postal_code'] );
		$this->assertEquals( '東京都', $result['address']['region'] );
		$this->assertEquals( '千代田区', $result['address']['locality'] );
		$this->assertEquals( '霞が関1-1', $result['address']['street_address'] );
		$this->assertEquals( 'JP', $result['address']['country'] );

		// Check geo array.
		$this->assertIsArray( $result['geo'] );
		$this->assertEquals( 35.6762, $result['geo']['latitude'] );
		$this->assertEquals( 139.6503, $result['geo']['longitude'] );
	}

	/**
	 * locationステップのレガシー入力名サポートをテスト。
	 */
	public function test_sanitize_location_legacy_input() {
		$wizard = new Wizard();

		$reflection = new ReflectionClass( $wizard );
		$method     = $reflection->getMethod( 'sanitize_step_data' );
		$method->setAccessible( true );

		// Legacy input names.
		$input = array(
			'postal_code' => '100-0001',
			'prefecture'  => '東京都',
			'city'        => '千代田区',
			'street'      => '霞が関1-1',
			'phone'       => '03-1234-5678',
			'latitude'    => '35.6762',
			'longitude'   => '139.6503',
		);

		$result = $method->invoke( $wizard, 'location', $input );

		// Legacy values should be mapped to new structure.
		$this->assertEquals( '東京都', $result['address']['region'] );
		$this->assertEquals( '千代田区', $result['address']['locality'] );
		$this->assertEquals( '霞が関1-1', $result['address']['street_address'] );
		$this->assertEquals( '03-1234-5678', $result['telephone'] );
		$this->assertEquals( 35.6762, $result['geo']['latitude'] );
		$this->assertEquals( 139.6503, $result['geo']['longitude'] );
	}

	/**
	 * hoursステップのサニタイズをテスト（UI入力形式）。
	 */
	public function test_sanitize_hours_step_data() {
		$wizard = new Wizard();

		$reflection = new ReflectionClass( $wizard );
		$method     = $reflection->getMethod( 'sanitize_step_data' );
		$method->setAccessible( true );

		// UI sends hours_{day}_opens/closes format.
		$input = array(
			'hours_monday_opens'    => '09:00',
			'hours_monday_closes'   => '18:00',
			'hours_tuesday_opens'   => '10:00',
			'hours_tuesday_closes'  => '17:00',
			'price_range'           => '$$',
			'accepts_reservations'  => '1',
		);

		$result = $method->invoke( $wizard, 'hours', $input );

		$this->assertCount( 2, $result['opening_hours'] );
		$this->assertEquals( 'monday', $result['opening_hours'][0]['day'] );
		$this->assertEquals( '09:00', $result['opening_hours'][0]['open'] );
		$this->assertEquals( '18:00', $result['opening_hours'][0]['close'] );
		$this->assertEquals( 'tuesday', $result['opening_hours'][1]['day'] );
		$this->assertEquals( '$$', $result['price_range'] );
		$this->assertTrue( $result['accepts_reservations'] );
	}

	/**
	 * hoursステップのレガシー opening_hours 配列形式をテスト。
	 */
	public function test_sanitize_hours_legacy_array_format() {
		$wizard = new Wizard();

		$reflection = new ReflectionClass( $wizard );
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
					'open'  => '10:00',
					'close' => '17:00',
				),
			),
		);

		$result = $method->invoke( $wizard, 'hours', $input );

		$this->assertCount( 1, $result['opening_hours'], 'Invalid day should be filtered out' );
		$this->assertEquals( 'monday', $result['opening_hours'][0]['day'] );
	}

	/**
	 * 無効な座標値のサニタイズをテスト。
	 */
	public function test_sanitize_location_with_invalid_coordinates() {
		$wizard = new Wizard();

		$reflection = new ReflectionClass( $wizard );
		$method     = $reflection->getMethod( 'sanitize_step_data' );
		$method->setAccessible( true );

		$input = array(
			'geo_latitude'  => 'not a number',
			'geo_longitude' => '',
		);

		$result = $method->invoke( $wizard, 'location', $input );

		$this->assertEquals( 0.0, $result['geo']['latitude'] );
		$this->assertEquals( 0.0, $result['geo']['longitude'] );
	}

	/**
	 * 複数の曜日を含む営業時間のサニタイズをテスト。
	 */
	public function test_sanitize_all_valid_days() {
		$wizard = new Wizard();

		$reflection = new ReflectionClass( $wizard );
		$method     = $reflection->getMethod( 'sanitize_step_data' );
		$method->setAccessible( true );

		$valid_days = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday', 'holiday' );
		$hours      = array();

		foreach ( $valid_days as $day ) {
			$hours[] = array(
				'day'   => $day,
				'open'  => '09:00',
				'close' => '18:00',
			);
		}

		$input  = array( 'opening_hours' => $hours );
		$result = $method->invoke( $wizard, 'hours', $input );

		$this->assertCount( 8, $result['opening_hours'], 'All valid days should be preserved' );
	}
}

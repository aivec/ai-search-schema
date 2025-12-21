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

		$input = array(
			'company_name' => '  Test Company  ',
			'site_name'    => '<script>alert("xss")</script>My Site',
			'site_url'     => 'https://example.com',
			'logo_id'      => '123',
		);

		$result = $method->invoke( $wizard, 'basics', $input );

		$this->assertEquals( 'Test Company', $result['company_name'] );
		$this->assertEquals( 'My Site', $result['site_name'] );
		$this->assertEquals( 'https://example.com', $result['site_url'] );
		$this->assertEquals( 123, $result['logo_id'] );
	}

	/**
	 * typeステップのサニタイズをテスト。
	 */
	public function test_sanitize_type_step_data() {
		$wizard = new Wizard();

		$reflection = new ReflectionClass( $wizard );
		$method     = $reflection->getMethod( 'sanitize_step_data' );
		$method->setAccessible( true );

		$input = array(
			'site_type'     => 'LOCAL_BUSINESS',
			'business_type' => 'Restaurant',
		);

		$result = $method->invoke( $wizard, 'type', $input );

		$this->assertEquals( 'local_business', $result['site_type'] );
		$this->assertEquals( 'Restaurant', $result['business_type'] );
	}

	/**
	 * locationステップのサニタイズをテスト。
	 */
	public function test_sanitize_location_step_data() {
		$wizard = new Wizard();

		$reflection = new ReflectionClass( $wizard );
		$method     = $reflection->getMethod( 'sanitize_step_data' );
		$method->setAccessible( true );

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

		$this->assertEquals( '100-0001', $result['postal_code'] );
		$this->assertEquals( '東京都', $result['prefecture'] );
		$this->assertEquals( '千代田区', $result['city'] );
		$this->assertEquals( '霞が関1-1', $result['street'] );
		$this->assertEquals( '03-1234-5678', $result['phone'] );
		$this->assertEquals( 35.6762, $result['latitude'] );
		$this->assertEquals( 139.6503, $result['longitude'] );
	}

	/**
	 * hoursステップのサニタイズをテスト。
	 */
	public function test_sanitize_hours_step_data() {
		$wizard = new Wizard();

		$reflection = new ReflectionClass( $wizard );
		$method     = $reflection->getMethod( 'sanitize_step_data' );
		$method->setAccessible( true );

		$input = array(
			'opening_hours'        => array(
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
			'price_range'          => '$$',
			'accepts_reservations' => '1',
		);

		$result = $method->invoke( $wizard, 'hours', $input );

		$this->assertCount( 1, $result['opening_hours'], 'Invalid day should be filtered out' );
		$this->assertEquals( 'monday', $result['opening_hours'][0]['day'] );
		$this->assertEquals( '09:00', $result['opening_hours'][0]['open'] );
		$this->assertEquals( '18:00', $result['opening_hours'][0]['close'] );
		$this->assertEquals( '$$', $result['price_range'] );
		$this->assertTrue( $result['accepts_reservations'] );
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
			'latitude'  => 'not a number',
			'longitude' => '',
		);

		$result = $method->invoke( $wizard, 'location', $input );

		$this->assertEquals( 0.0, $result['latitude'] );
		$this->assertEquals( 0.0, $result['longitude'] );
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

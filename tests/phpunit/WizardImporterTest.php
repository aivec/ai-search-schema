<?php
/**
 * Wizard Importer テスト。
 *
 * 他のSEOプラグインからのインポート機能をテストします。
 *
 * @package Aivec\AiSearchSchema\Tests
 */

require_once __DIR__ . '/bootstrap.php';

use Aivec\AiSearchSchema\Wizard\Wizard_Importer;

/**
 * Wizard_Importer クラスのテスト。
 */
class WizardImporterTest extends WP_UnitTestCase {

	/**
	 * Wizard_Importer インスタンス。
	 *
	 * @var Wizard_Importer
	 */
	private $importer;

	/**
	 * テスト前のセットアップ。
	 */
	protected function setUp(): void {
		parent::setUp();
		AVC_AIS_TEST_Env::$options = array();
		$this->importer          = new Wizard_Importer();
	}

	/**
	 * テスト後のクリーンアップ。
	 */
	protected function tearDown(): void {
		// テスト用オプションをクリア.
		delete_option( 'wpseo_titles' );
		delete_option( 'wpseo_social' );
		delete_option( 'rank_math_general' );
		delete_option( 'rank_math_titles' );
		delete_option( 'rank_math_local' );
		delete_option( 'aioseo_options' );

		// 定数のクリーンアップは不可能だが、オプションベースの検出をテスト.
		parent::tearDown();
	}

	// =========================================================================
	// 基本機能テスト
	// =========================================================================

	/**
	 * インスタンス作成をテスト。
	 */
	public function test_constructor_initializes_sources() {
		$importer = new Wizard_Importer();
		$sources  = $importer->get_available_sources();

		$this->assertIsArray( $sources );
		$this->assertArrayHasKey( 'yoast', $sources );
		$this->assertArrayHasKey( 'rankmath', $sources );
		$this->assertArrayHasKey( 'aioseo', $sources );
	}

	/**
	 * 各ソースに必要なプロパティがあることをテスト。
	 */
	public function test_sources_have_required_properties() {
		$sources = $this->importer->get_available_sources();

		foreach ( $sources as $key => $source ) {
			$this->assertArrayHasKey( 'name', $source, "Source '{$key}' should have 'name' property" );
			$this->assertArrayHasKey( 'detected', $source, "Source '{$key}' should have 'detected' property" );
			$this->assertArrayHasKey( 'preview', $source, "Source '{$key}' should have 'preview' property" );
		}
	}

	/**
	 * プラグインが検出されない場合をテスト。
	 */
	public function test_no_plugins_detected_by_default() {
		$sources = $this->importer->get_available_sources();

		foreach ( $sources as $source ) {
			$this->assertFalse( $source['detected'], 'No plugin should be detected by default' );
			$this->assertEmpty( $source['preview'], 'Preview should be empty when not detected' );
		}
	}

	/**
	 * 無効なソースからのインポートをテスト。
	 */
	public function test_import_invalid_source_returns_error() {
		$result = $this->importer->import( 'invalid_source' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'invalid_source', $result->get_error_code() );
	}

	/**
	 * 無効なソースのプレビューをテスト。
	 */
	public function test_preview_invalid_source_returns_empty() {
		$result = $this->importer->preview_import( 'invalid_source' );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	// =========================================================================
	// Yoast SEO テスト
	// =========================================================================

	/**
	 * Yoast SEO 検出（オプションベース）をテスト。
	 */
	public function test_detect_yoast_by_option() {
		update_option( 'wpseo_titles', array( 'company_name' => 'Test Company' ) );

		$importer = new Wizard_Importer();
		$sources  = $importer->get_available_sources();

		$this->assertTrue( $sources['yoast']['detected'] );
	}

	/**
	 * Yoast SEO プレビューをテスト。
	 */
	public function test_preview_yoast_with_data() {
		update_option(
			'wpseo_titles',
			array(
				'company_name' => 'My Company',
				'company_logo' => 'https://example.com/logo.png',
			)
		);
		update_option(
			'wpseo_social',
			array(
				'facebook_site'  => 'https://facebook.com/mycompany',
				'twitter_site'   => 'mycompany',
				'instagram_url'  => 'https://instagram.com/mycompany',
				'youtube_url'    => 'https://youtube.com/mycompany',
			)
		);

		$importer = new Wizard_Importer();
		$sources  = $importer->get_available_sources();

		$this->assertTrue( $sources['yoast']['detected'] );
		$this->assertEquals( 'My Company', $sources['yoast']['preview']['company_name'] );
		$this->assertStringContainsString( 'Facebook', $sources['yoast']['preview']['social'] );
		$this->assertStringContainsString( 'X (Twitter)', $sources['yoast']['preview']['social'] );
		$this->assertStringContainsString( 'Instagram', $sources['yoast']['preview']['social'] );
		$this->assertStringContainsString( 'YouTube', $sources['yoast']['preview']['social'] );
	}

	/**
	 * Yoast SEO インポートをテスト。
	 */
	public function test_import_yoast_basic_data() {
		update_option(
			'wpseo_titles',
			array(
				'company_name'      => 'Test Company',
				'website_name'      => 'Test Site',
				'company_logo_id'   => 123,
				'company_or_person' => 'company',
			)
		);

		$result = $this->importer->import( 'yoast' );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'basics', $result );
		$this->assertEquals( 'Test Company', $result['basics']['company_name'] );
		$this->assertEquals( 'Test Site', $result['basics']['site_name'] );
		$this->assertEquals( 123, $result['basics']['logo_id'] );
		$this->assertEquals( 'organization', $result['type']['site_type'] );
	}

	/**
	 * Yoast SEO インポート（会社名をサイト名にフォールバック）をテスト。
	 */
	public function test_import_yoast_fallback_site_name() {
		update_option(
			'wpseo_titles',
			array(
				'company_name' => 'Fallback Company',
				// website_name は設定しない.
			)
		);

		$result = $this->importer->import( 'yoast' );

		$this->assertEquals( 'Fallback Company', $result['basics']['site_name'] );
	}

	// =========================================================================
	// Rank Math テスト
	// =========================================================================

	/**
	 * Rank Math 検出（オプションベース）をテスト。
	 */
	public function test_detect_rankmath_by_option() {
		update_option( 'rank_math_general', array( 'some_setting' => true ) );

		$importer = new Wizard_Importer();
		$sources  = $importer->get_available_sources();

		$this->assertTrue( $sources['rankmath']['detected'] );
	}

	/**
	 * Rank Math プレビューをテスト。
	 */
	public function test_preview_rankmath_with_data() {
		// detect_rankmath() は rank_math_general を確認する.
		update_option( 'rank_math_general', array( 'modules' => array() ) );
		update_option(
			'rank_math_titles',
			array(
				'knowledgegraph_name' => 'My Business',
				'knowledgegraph_logo' => 'https://example.com/logo.png',
			)
		);
		update_option(
			'rank_math_local',
			array(
				'local_business_type' => 'Restaurant',
				'local_address'       => array(
					'streetAddress'   => '123 Main St',
					'addressLocality' => 'Tokyo',
				),
			)
		);

		$importer = new Wizard_Importer();
		$sources  = $importer->get_available_sources();

		$this->assertTrue( $sources['rankmath']['detected'] );
		$this->assertEquals( 'My Business', $sources['rankmath']['preview']['company_name'] );
		$this->assertEquals( 'Restaurant', $sources['rankmath']['preview']['business_type'] );
	}

	/**
	 * Rank Math インポートをテスト。
	 */
	public function test_import_rankmath_full_data() {
		update_option(
			'rank_math_titles',
			array(
				'knowledgegraph_name'    => 'Test Business',
				'knowledgegraph_logo_id' => 456,
				'knowledgegraph_type'    => 'company',
			)
		);
		update_option(
			'rank_math_local',
			array(
				'local_business_type' => 'Restaurant',
				'local_address'       => array(
					'postalCode'      => '100-0001',
					'addressRegion'   => '東京都',
					'addressLocality' => '千代田区',
					'streetAddress'   => '霞が関1-1',
				),
				'local_phone'         => '03-1234-5678',
				'local_geo'           => array(
					'latitude'  => '35.6762',
					'longitude' => '139.6503',
				),
			)
		);

		$result = $this->importer->import( 'rankmath' );

		// 基本情報.
		$this->assertEquals( 'Test Business', $result['basics']['company_name'] );
		$this->assertEquals( 456, $result['basics']['logo_id'] );

		// タイプ.
		$this->assertEquals( 'local_business', $result['type']['site_type'] );
		$this->assertEquals( 'Restaurant', $result['type']['business_type'] );

		// 住所.
		$this->assertEquals( '100-0001', $result['location']['postal_code'] );
		$this->assertEquals( '東京都', $result['location']['prefecture'] );
		$this->assertEquals( '千代田区', $result['location']['city'] );
		$this->assertEquals( '霞が関1-1', $result['location']['street'] );
		$this->assertEquals( '03-1234-5678', $result['location']['phone'] );

		// 座標.
		$this->assertEquals( 35.6762, $result['location']['latitude'] );
		$this->assertEquals( 139.6503, $result['location']['longitude'] );
	}

	/**
	 * Rank Math インポート（Organization タイプ）をテスト。
	 */
	public function test_import_rankmath_organization_type() {
		update_option(
			'rank_math_titles',
			array(
				'knowledgegraph_name' => 'Org Name',
				'knowledgegraph_type' => 'company',
			)
		);

		$result = $this->importer->import( 'rankmath' );

		$this->assertEquals( 'organization', $result['type']['site_type'] );
	}

	/**
	 * Rank Math インポート（Person タイプ）をテスト。
	 */
	public function test_import_rankmath_person_type() {
		update_option(
			'rank_math_titles',
			array(
				'knowledgegraph_name' => 'Person Name',
				'knowledgegraph_type' => 'person',
			)
		);

		$result = $this->importer->import( 'rankmath' );

		// Person も organization として扱う.
		$this->assertEquals( 'organization', $result['type']['site_type'] );
	}

	// =========================================================================
	// All in One SEO テスト
	// =========================================================================

	/**
	 * AIOSEO 検出（オプションベース）をテスト。
	 */
	public function test_detect_aioseo_by_option() {
		update_option( 'aioseo_options', array( 'some_setting' => true ) );

		$importer = new Wizard_Importer();
		$sources  = $importer->get_available_sources();

		$this->assertTrue( $sources['aioseo']['detected'] );
	}

	/**
	 * AIOSEO プレビュー（配列オプション）をテスト。
	 */
	public function test_preview_aioseo_with_array_option() {
		update_option(
			'aioseo_options',
			array(
				'searchAppearance' => array(
					'global' => array(
						'schema' => array(
							'organizationName' => 'AIOSEO Company',
							'organizationLogo' => 'https://example.com/logo.png',
						),
					),
				),
			)
		);

		$importer = new Wizard_Importer();
		$sources  = $importer->get_available_sources();

		$this->assertTrue( $sources['aioseo']['detected'] );
		$this->assertEquals( 'AIOSEO Company', $sources['aioseo']['preview']['company_name'] );
	}

	/**
	 * AIOSEO プレビュー（JSON文字列オプション）をテスト。
	 */
	public function test_preview_aioseo_with_json_string_option() {
		$options = array(
			'searchAppearance' => array(
				'global' => array(
					'schema' => array(
						'organizationName' => 'JSON Company',
						'organizationLogo' => 'https://example.com/logo.png',
					),
				),
			),
		);
		update_option( 'aioseo_options', wp_json_encode( $options ) );

		$importer = new Wizard_Importer();
		$sources  = $importer->get_available_sources();

		$this->assertTrue( $sources['aioseo']['detected'] );
		$this->assertEquals( 'JSON Company', $sources['aioseo']['preview']['company_name'] );
	}

	/**
	 * AIOSEO インポートをテスト。
	 */
	public function test_import_aioseo_basic_data() {
		update_option(
			'aioseo_options',
			array(
				'searchAppearance' => array(
					'global' => array(
						'schema' => array(
							'organizationName' => 'AIOSEO Org',
						),
					),
				),
			)
		);

		$result = $this->importer->import( 'aioseo' );

		$this->assertIsArray( $result );
		$this->assertEquals( 'AIOSEO Org', $result['basics']['company_name'] );
		$this->assertEquals( 'AIOSEO Org', $result['basics']['site_name'] );
		$this->assertEquals( 'organization', $result['type']['site_type'] );
	}

	/**
	 * AIOSEO インポート（Local Business）をテスト。
	 */
	public function test_import_aioseo_local_business() {
		update_option(
			'aioseo_options',
			array(
				'searchAppearance' => array(
					'global' => array(
						'schema' => array(
							'organizationName' => 'Local Shop',
							'localBusiness'    => array(
								'businessType' => 'Store',
							),
						),
					),
				),
			)
		);

		$result = $this->importer->import( 'aioseo' );

		$this->assertEquals( 'local_business', $result['type']['site_type'] );
		$this->assertEquals( 'Store', $result['type']['business_type'] );
	}

	/**
	 * AIOSEO インポート（JSON文字列）をテスト。
	 */
	public function test_import_aioseo_json_string() {
		$options = array(
			'searchAppearance' => array(
				'global' => array(
					'schema' => array(
						'organizationName' => 'JSON Org',
					),
				),
			),
		);
		update_option( 'aioseo_options', wp_json_encode( $options ) );

		$result = $this->importer->import( 'aioseo' );

		$this->assertEquals( 'JSON Org', $result['basics']['company_name'] );
	}

	// =========================================================================
	// フィルターテスト
	// =========================================================================

	/**
	 * インポートソースのフィルターをテスト。
	 */
	public function test_import_sources_filter() {
		add_filter(
			'avc_ais_wizard_import_sources',
			function ( $sources ) {
				$sources['custom'] = array(
					'name'     => 'Custom Plugin',
					'callback' => '__return_empty_array',
					'detect'   => '__return_true',
				);
				return $sources;
			}
		);

		$importer = new Wizard_Importer();
		$sources  = $importer->get_available_sources();

		$this->assertArrayHasKey( 'custom', $sources );
		$this->assertEquals( 'Custom Plugin', $sources['custom']['name'] );
		$this->assertTrue( $sources['custom']['detected'] );

		// フィルターをクリア.
		remove_all_filters( 'avc_ais_wizard_import_sources' );
	}

	// =========================================================================
	// エッジケーステスト
	// =========================================================================

	/**
	 * 空のオプションでのインポートをテスト。
	 */
	public function test_import_with_empty_options() {
		update_option( 'wpseo_titles', array() );
		update_option( 'wpseo_social', array() );

		$result = $this->importer->import( 'yoast' );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'basics', $result );
		// site_url は常に設定される.
		$this->assertEquals( home_url(), $result['basics']['site_url'] );
	}

	/**
	 * Rank Math の住所が配列でない場合をテスト。
	 */
	public function test_import_rankmath_non_array_address() {
		update_option(
			'rank_math_titles',
			array(
				'knowledgegraph_name' => 'Test',
			)
		);
		update_option(
			'rank_math_local',
			array(
				'local_address' => 'string address', // 配列ではない.
			)
		);

		$result = $this->importer->import( 'rankmath' );

		// エラーなく処理される.
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'location', $result );
	}

	/**
	 * Rank Math の geo が配列でない場合をテスト。
	 */
	public function test_import_rankmath_non_array_geo() {
		update_option(
			'rank_math_titles',
			array(
				'knowledgegraph_name' => 'Test',
			)
		);
		update_option(
			'rank_math_local',
			array(
				'local_geo' => 'invalid', // 配列ではない.
			)
		);

		$result = $this->importer->import( 'rankmath' );

		// エラーなく処理される.
		$this->assertIsArray( $result );
		// 座標は設定されない.
		$this->assertArrayNotHasKey( 'latitude', $result['location'] ?? array() );
	}
}

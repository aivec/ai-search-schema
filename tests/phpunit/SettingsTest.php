<?php
/**
 * Settings クラスの包括的テスト。
 *
 * get_options, filter_option_on_get, 営業時間の解析/正規化、
 * GEO値フォーマット、診断レポートなどをテストします。
 *
 * @package Aivec\AiSearchSchema\Tests
 */

require_once __DIR__ . '/bootstrap.php';

/**
 * AVC_AIS_Settings クラスのテスト。
 */
class SettingsTest extends WP_UnitTestCase {

	/**
	 * Settings インスタンス。
	 *
	 * @var AVC_AIS_Settings
	 */
	private $settings;

	/**
	 * テスト前のセットアップ。
	 */
	protected function setUp(): void {
		parent::setUp();
		AVC_AIS_TEST_Env::$options = array();
		$this->settings          = new AVC_AIS_Settings();
	}

	/**
	 * テスト後のクリーンアップ。
	 */
	protected function tearDown(): void {
		AVC_AIS_TEST_Env::$options = array();
		parent::tearDown();
	}

	// ===== get_options() Tests =====

	/**
	 * get_options がデフォルト値を返すことをテスト。
	 */
	public function test_get_options_returns_default_values() {
		$options = $this->settings->get_options();

		$this->assertIsArray( $options );
		$this->assertArrayHasKey( 'company_name', $options );
		$this->assertArrayHasKey( 'logo_id', $options );
		$this->assertArrayHasKey( 'site_name', $options );
		$this->assertArrayHasKey( 'site_url', $options );
		$this->assertArrayHasKey( 'entity_type', $options );
		$this->assertArrayHasKey( 'address', $options );
		$this->assertArrayHasKey( 'geo', $options );
		$this->assertArrayHasKey( 'opening_hours', $options );
		$this->assertArrayHasKey( 'languages', $options );
	}

	/**
	 * get_options がデフォルト言語を正しく設定することをテスト。
	 */
	public function test_get_options_sets_default_language() {
		$options = $this->settings->get_options();

		$this->assertIsArray( $options['languages'] );
		$this->assertNotEmpty( $options['languages'] );
		$this->assertEquals( $options['languages'][0], $options['language'] );
	}

	/**
	 * get_options が住所のデフォルト構造を返すことをテスト。
	 */
	public function test_get_options_returns_address_defaults() {
		$options = $this->settings->get_options();
		$address = $options['address'];

		$this->assertIsArray( $address );
		$this->assertArrayHasKey( 'postal_code', $address );
		$this->assertArrayHasKey( 'prefecture', $address );
		$this->assertArrayHasKey( 'prefecture_iso', $address );
		$this->assertArrayHasKey( 'locality', $address );
		$this->assertArrayHasKey( 'street_address', $address );
		$this->assertArrayHasKey( 'country', $address );
		$this->assertEquals( 'JP', $address['country'] );
	}

	/**
	 * get_options が geo のデフォルト構造を返すことをテスト。
	 */
	public function test_get_options_returns_geo_defaults() {
		$options = $this->settings->get_options();
		$geo     = $options['geo'];

		$this->assertIsArray( $geo );
		$this->assertArrayHasKey( 'latitude', $geo );
		$this->assertArrayHasKey( 'longitude', $geo );
		$this->assertEquals( '', $geo['latitude'] );
		$this->assertEquals( '', $geo['longitude'] );
	}

	/**
	 * get_options が保存済みオプションとデフォルトをマージすることをテスト。
	 */
	public function test_get_options_merges_saved_with_defaults() {
		update_option(
			'avc_ais_options',
			array(
				'company_name' => 'Test Company',
				'phone'        => '03-1234-5678',
			)
		);

		$options = $this->settings->get_options();

		$this->assertEquals( 'Test Company', $options['company_name'] );
		$this->assertEquals( '03-1234-5678', $options['phone'] );
		// デフォルト値も存在する.
		$this->assertArrayHasKey( 'site_name', $options );
		$this->assertArrayHasKey( 'entity_type', $options );
	}

	/**
	 * get_options が null 値をフィルタリングしてデフォルトを適用することをテスト。
	 */
	public function test_get_options_filters_null_values() {
		update_option(
			'avc_ais_options',
			array(
				'company_name' => null,
				'phone'        => '03-1234-5678',
				'address'      => array(
					'postal_code' => null,
					'prefecture'  => '東京都',
				),
			)
		);

		$options = $this->settings->get_options();

		// null 値はデフォルトに置き換えられる.
		$this->assertEquals( '', $options['company_name'] );
		$this->assertEquals( '03-1234-5678', $options['phone'] );
		$this->assertEquals( '東京都', $options['address']['prefecture'] );
	}

	// ===== filter_option_on_get() Tests =====

	/**
	 * filter_option_on_get が配列から null 値を除去することをテスト。
	 */
	public function test_filter_option_on_get_removes_null_values() {
		$input = array(
			'key1' => 'value1',
			'key2' => null,
			'key3' => array(
				'nested1' => 'value',
				'nested2' => null,
			),
		);

		$result = $this->settings->filter_option_on_get( $input );

		$this->assertArrayHasKey( 'key1', $result );
		$this->assertArrayNotHasKey( 'key2', $result );
		$this->assertArrayHasKey( 'key3', $result );
		$this->assertArrayHasKey( 'nested1', $result['key3'] );
		$this->assertArrayNotHasKey( 'nested2', $result['key3'] );
	}

	/**
	 * filter_option_on_get が非配列値をそのまま返すことをテスト。
	 */
	public function test_filter_option_on_get_returns_non_array_as_is() {
		$this->assertEquals( 'string_value', $this->settings->filter_option_on_get( 'string_value' ) );
		$this->assertEquals( 123, $this->settings->filter_option_on_get( 123 ) );
		$this->assertEquals( true, $this->settings->filter_option_on_get( true ) );
	}

	// ===== sanitize_options() Additional Tests =====

	/**
	 * sanitize_options が entity_type を正しくサニタイズすることをテスト。
	 */
	public function test_sanitize_entity_type() {
		$result1 = $this->settings->sanitize_options( array( 'entity_type' => 'Organization' ) );
		$this->assertEquals( 'Organization', $result1['entity_type'] );

		$result2 = $this->settings->sanitize_options( array( 'entity_type' => 'LocalBusiness' ) );
		$this->assertEquals( 'LocalBusiness', $result2['entity_type'] );

		// 無効な値はデフォルトに.
		$result3 = $this->settings->sanitize_options( array( 'entity_type' => 'InvalidType' ) );
		$this->assertEquals( 'Organization', $result3['entity_type'] );
	}

	/**
	 * sanitize_options が content_model を正しくサニタイズすることをテスト。
	 */
	public function test_sanitize_content_model() {
		$valid_models = array( 'WebPage', 'Article', 'FAQPage', 'QAPage', 'NewsArticle', 'BlogPosting', 'Product' );

		foreach ( $valid_models as $model ) {
			$result = $this->settings->sanitize_options( array( 'content_model' => $model ) );
			$this->assertEquals( $model, $result['content_model'], "Content model '$model' should be accepted" );
		}

		// 無効な値はデフォルトに.
		$result = $this->settings->sanitize_options( array( 'content_model' => 'InvalidModel' ) );
		$this->assertEquals( 'WebPage', $result['content_model'] );
	}

	/**
	 * sanitize_options が opening_hours を正しく正規化することをテスト。
	 */
	public function test_sanitize_opening_hours() {
		$input = array(
			'opening_hours' => array(
				array(
					'day_key' => 'Monday',
					'opens'   => '09:00',
					'closes'  => '18:00',
				),
				array(
					'day_key' => 'Tuesday',
					'opens'   => '10:00',
					'closes'  => '17:00',
				),
			),
		);

		$result = $this->settings->sanitize_options( $input );

		$this->assertIsArray( $result['opening_hours'] );
		$this->assertCount( 2, $result['opening_hours'] );
		$this->assertEquals( 'Monday', $result['opening_hours'][0]['day_key'] );
		$this->assertEquals( '09:00', $result['opening_hours'][0]['slots'][0]['opens'] );
		$this->assertEquals( '18:00', $result['opening_hours'][0]['slots'][0]['closes'] );
	}

	/**
	 * sanitize_options が opening_hours_raw から解析することをテスト。
	 */
	public function test_sanitize_opening_hours_from_raw() {
		$input = array(
			'opening_hours_raw' => "Mon 09:00-18:00\nTue 10:00-17:00",
			'opening_hours'     => array(),
		);

		$result = $this->settings->sanitize_options( $input );

		$this->assertIsArray( $result['opening_hours'] );
		$this->assertCount( 2, $result['opening_hours'] );
	}

	/**
	 * sanitize_options が無効な曜日をフィルタリングすることをテスト。
	 */
	public function test_sanitize_opening_hours_filters_invalid_days() {
		$input = array(
			'opening_hours' => array(
				array(
					'day_key' => 'Monday',
					'opens'   => '09:00',
					'closes'  => '18:00',
				),
				array(
					'day_key' => 'InvalidDay',
					'opens'   => '09:00',
					'closes'  => '18:00',
				),
			),
		);

		$result = $this->settings->sanitize_options( $input );

		$this->assertCount( 1, $result['opening_hours'] );
		$this->assertEquals( 'Monday', $result['opening_hours'][0]['day_key'] );
	}

	/**
	 * sanitize_options が無効な時刻形式をフィルタリングすることをテスト。
	 */
	public function test_sanitize_opening_hours_filters_invalid_times() {
		$input = array(
			'opening_hours' => array(
				array(
					'day_key' => 'Monday',
					'opens'   => '09:00',
					'closes'  => '18:00',
				),
				array(
					'day_key' => 'Tuesday',
					'opens'   => 'invalid',
					'closes'  => '18:00',
				),
				array(
					'day_key' => 'Wednesday',
					'opens'   => '09:00',
					'closes'  => '25:00', // 無効.
				),
			),
		);

		$result = $this->settings->sanitize_options( $input );

		$this->assertCount( 1, $result['opening_hours'] );
	}

	/**
	 * sanitize_options が曜日エイリアスを正規化することをテスト。
	 */
	public function test_sanitize_opening_hours_normalizes_day_aliases() {
		$input = array(
			'opening_hours' => array(
				array(
					'day_key' => 'Mon',
					'opens'   => '09:00',
					'closes'  => '18:00',
				),
				array(
					'day_key' => '月曜日',
					'opens'   => '10:00',
					'closes'  => '17:00',
				),
			),
		);

		$result = $this->settings->sanitize_options( $input );

		// Mon と 月曜日 は両方 Monday に正規化される.
		$this->assertCount( 1, $result['opening_hours'] );
		$this->assertEquals( 'Monday', $result['opening_hours'][0]['day_key'] );
		// スロットは2つマージされる.
		$this->assertCount( 2, $result['opening_hours'][0]['slots'] );
	}

	/**
	 * sanitize_options が avc_ais_priority を正しくサニタイズすることをテスト。
	 */
	public function test_sanitize_schema_priority() {
		$result1 = $this->settings->sanitize_options( array( 'avc_ais_priority' => 'ais' ) );
		$this->assertEquals( 'ais', $result1['avc_ais_priority'] );

		$result2 = $this->settings->sanitize_options( array( 'avc_ais_priority' => 'external' ) );
		$this->assertEquals( 'external', $result2['avc_ais_priority'] );

		// 無効な値はデフォルトに.
		$result3 = $this->settings->sanitize_options( array( 'avc_ais_priority' => 'invalid' ) );
		$this->assertEquals( 'ais', $result3['avc_ais_priority'] );
	}

	/**
	 * sanitize_options が holiday_mode を正しくサニタイズすることをテスト。
	 */
	public function test_sanitize_holiday_mode() {
		$valid_modes = array( 'weekday', 'weekend', 'custom' );

		foreach ( $valid_modes as $mode ) {
			$result = $this->settings->sanitize_options( array( 'holiday_mode' => $mode ) );
			$this->assertEquals( $mode, $result['holiday_mode'] );
		}

		// 無効な値はデフォルトに.
		$result = $this->settings->sanitize_options( array( 'holiday_mode' => 'invalid' ) );
		$this->assertEquals( 'custom', $result['holiday_mode'] );
	}

	/**
	 * sanitize_options が boolean フィールドを正しくサニタイズすることをテスト。
	 */
	public function test_sanitize_boolean_fields() {
		$result1 = $this->settings->sanitize_options( array( 'accepts_reservations' => '1' ) );
		$this->assertTrue( $result1['accepts_reservations'] );

		$result2 = $this->settings->sanitize_options( array( 'accepts_reservations' => '' ) );
		$this->assertFalse( $result2['accepts_reservations'] );

		$result3 = $this->settings->sanitize_options( array( 'skip_local_business_if_incomplete' => true ) );
		$this->assertTrue( $result3['skip_local_business_if_incomplete'] );
	}

	/**
	 * sanitize_options が国コードを正しくサニタイズすることをテスト。
	 */
	public function test_sanitize_country_code() {
		$result1 = $this->settings->sanitize_options( array( 'country_code' => 'JP' ) );
		$this->assertEquals( 'JP', $result1['country_code'] );

		$result2 = $this->settings->sanitize_options( array( 'country_code' => 'US' ) );
		$this->assertEquals( 'US', $result2['country_code'] );

		// 無効な値はデフォルトに.
		$result3 = $this->settings->sanitize_options( array( 'country_code' => 'INVALID' ) );
		$this->assertNotEquals( 'INVALID', $result3['country_code'] );
	}

	// ===== GEO Value Formatting Tests =====

	/**
	 * sanitize_options が geo 値を正しくフォーマットすることをテスト。
	 */
	public function test_sanitize_geo_values() {
		$input = array(
			'geo' => array(
				'latitude'  => ' 35.6762 ',
				'longitude' => '139.6503',
			),
		);

		$result = $this->settings->sanitize_options( $input );

		$this->assertEquals( '35.6762', $result['geo']['latitude'] );
		$this->assertEquals( '139.6503', $result['geo']['longitude'] );
	}

	/**
	 * sanitize_options が geo 値を6桁に丸めることをテスト。
	 */
	public function test_sanitize_geo_rounds_to_six_decimals() {
		$input = array(
			'geo' => array(
				'latitude'  => '35.67621234567',
				'longitude' => '139.65031234567',
			),
		);

		$result = $this->settings->sanitize_options( $input );

		// 入力の小数桁数を保持しつつ最大6桁.
		$this->assertMatchesRegularExpression( '/^\d+\.\d{1,6}$/', $result['geo']['latitude'] );
		$this->assertMatchesRegularExpression( '/^\d+\.\d{1,6}$/', $result['geo']['longitude'] );
	}

	/**
	 * sanitize_options が無効な geo 値を空文字列にすることをテスト。
	 */
	public function test_sanitize_geo_handles_invalid_values() {
		$input = array(
			'geo' => array(
				'latitude'  => 'not a number',
				'longitude' => '',
			),
		);

		$result = $this->settings->sanitize_options( $input );

		$this->assertEquals( '', $result['geo']['latitude'] );
		$this->assertEquals( '', $result['geo']['longitude'] );
	}

	/**
	 * sanitize_options が geo 値から XSS を除去することをテスト。
	 */
	public function test_sanitize_geo_removes_xss() {
		$input = array(
			'geo' => array(
				'latitude'  => '35.6762<script>alert("xss")</script>',
				'longitude' => '139.6503',
			),
		);

		$result = $this->settings->sanitize_options( $input );

		$this->assertEquals( '35.6762', $result['geo']['latitude'] );
		$this->assertStringNotContainsString( '<script>', $result['geo']['latitude'] );
	}

	// ===== Address Sanitization Tests =====

	/**
	 * sanitize_options が address フィールドを正しくサニタイズすることをテスト。
	 */
	public function test_sanitize_address_fields() {
		$input = array(
			'address' => array(
				'postal_code'    => '100-0001',
				'prefecture'     => '東京都',
				'locality'       => '千代田区',
				'street_address' => '霞が関1-1',
				'country'        => 'JP',
			),
		);

		$result = $this->settings->sanitize_options( $input );

		$this->assertEquals( '100-0001', $result['address']['postal_code'] );
		$this->assertEquals( '東京都', $result['address']['prefecture'] );
		$this->assertEquals( '千代田区', $result['address']['locality'] );
		$this->assertEquals( '霞が関1-1', $result['address']['street_address'] );
		$this->assertEquals( 'JP', $result['address']['country'] );
	}

	/**
	 * sanitize_options が prefecture から ISO コードを生成することをテスト。
	 */
	public function test_sanitize_address_generates_prefecture_iso() {
		$input = array(
			'address' => array(
				'prefecture' => '東京都',
			),
		);

		$result = $this->settings->sanitize_options( $input );

		$this->assertEquals( 'JP-13', $result['address']['prefecture_iso'] );
	}

	/**
	 * sanitize_options が空の country をデフォルト JP に設定することをテスト。
	 */
	public function test_sanitize_address_defaults_country_to_jp() {
		$input = array(
			'address' => array(
				'country' => '',
			),
		);

		$result = $this->settings->sanitize_options( $input );

		$this->assertEquals( 'JP', $result['address']['country'] );
	}

	// ===== Social Links Sanitization Tests =====

	/**
	 * sanitize_options が social_links を正しくサニタイズすることをテスト。
	 */
	public function test_sanitize_social_links() {
		$input = array(
			'social_links' => array(
				array(
					'network' => 'facebook',
					'account' => 'test_account',
					'label'   => 'Facebook Page',
				),
				array(
					'network' => 'x',
					'account' => '@twitter_handle',
					'label'   => 'X Profile',
				),
			),
		);

		$result = $this->settings->sanitize_options( $input );

		$this->assertCount( 2, $result['social_links'] );
		$this->assertEquals( 'facebook', $result['social_links'][0]['network'] );
		$this->assertEquals( 'test_account', $result['social_links'][0]['account'] );
		$this->assertEquals( 'x', $result['social_links'][1]['network'] );
	}

	/**
	 * sanitize_options が無効なネットワークを other に変換することをテスト。
	 */
	public function test_sanitize_social_links_converts_invalid_network() {
		$input = array(
			'social_links' => array(
				array(
					'network' => 'unknown_network',
					'account' => 'test_account',
					'label'   => 'Test',
				),
			),
		);

		$result = $this->settings->sanitize_options( $input );

		$this->assertEquals( 'other', $result['social_links'][0]['network'] );
	}

	/**
	 * sanitize_options が account が空の social_links を除外することをテスト。
	 */
	public function test_sanitize_social_links_excludes_empty_account() {
		$input = array(
			'social_links' => array(
				array(
					'network' => 'facebook',
					'account' => '',
					'label'   => 'Empty',
				),
				array(
					'network' => 'x',
					'account' => 'valid_account',
					'label'   => 'Valid',
				),
			),
		);

		$result = $this->settings->sanitize_options( $input );

		$this->assertCount( 1, $result['social_links'] );
		$this->assertEquals( 'valid_account', $result['social_links'][0]['account'] );
	}

	// ===== Content Type Settings Tests =====

	/**
	 * sanitize_options が content_type_settings を正しくサニタイズすることをテスト。
	 */
	public function test_sanitize_content_type_settings() {
		$input = array(
			'content_type_settings' => array(
				'post_types' => array(
					'post' => array(
						'schema_type'         => 'Article',
						'breadcrumbs_enabled' => true,
						'faq_enabled'         => true,
						'schema_priority'     => 'ais',
					),
				),
			),
		);

		$result = $this->settings->sanitize_options( $input );

		$this->assertIsArray( $result['content_type_settings'] );
		$this->assertArrayHasKey( 'post_types', $result['content_type_settings'] );
	}

	// ===== Breadcrumbs Settings Tests =====

	/**
	 * sanitize_options が breadcrumbs 設定を正しくサニタイズすることをテスト。
	 */
	public function test_sanitize_breadcrumbs_settings() {
		$result1 = $this->settings->sanitize_options( array( 'avc_ais_breadcrumbs_schema_enabled' => '1' ) );
		$this->assertTrue( $result1['avc_ais_breadcrumbs_schema_enabled'] );

		$result2 = $this->settings->sanitize_options( array( 'avc_ais_breadcrumbs_html_enabled' => '1' ) );
		$this->assertTrue( $result2['avc_ais_breadcrumbs_html_enabled'] );

		// レガシー enable_breadcrumbs からの移行.
		$result3 = $this->settings->sanitize_options( array( 'enable_breadcrumbs' => '1' ) );
		$this->assertTrue( $result3['avc_ais_breadcrumbs_schema_enabled'] );
	}

	// ===== Logo and Image Sanitization Tests =====

	/**
	 * sanitize_options が logo_id を正しくサニタイズすることをテスト。
	 */
	public function test_sanitize_logo_id() {
		$result1 = $this->settings->sanitize_options( array( 'logo_id' => '123' ) );
		$this->assertEquals( 123, $result1['logo_id'] );

		$result2 = $this->settings->sanitize_options( array( 'logo_id' => 'not_a_number' ) );
		$this->assertEquals( 0, $result2['logo_id'] );

		// absint は負の数の絶対値を返す.
		$result3 = $this->settings->sanitize_options( array( 'logo_id' => -5 ) );
		$this->assertEquals( 5, $result3['logo_id'] );
	}

	/**
	 * sanitize_options が logo_url を正しくサニタイズすることをテスト。
	 */
	public function test_sanitize_logo_url() {
		$result = $this->settings->sanitize_options(
			array(
				'logo_id'  => 0,
				'logo_url' => 'https://example.com/logo.png',
			)
		);

		$this->assertEquals( 'https://example.com/logo.png', $result['logo_url'] );
	}

	// ===== Diagnostics Report Tests =====

	/**
	 * get_diagnostics_report が正しい構造を返すことをテスト。
	 */
	public function test_get_diagnostics_report_structure() {
		$options = $this->settings->get_options();
		$report  = $this->settings->get_diagnostics_report( $options );

		$this->assertIsArray( $report );
		$this->assertArrayHasKey( 'generated_at', $report );
		$this->assertArrayHasKey( 'groups', $report );
		$this->assertIsArray( $report['groups'] );
	}

	/**
	 * get_diagnostics_report が全診断グループを含むことをテスト。
	 */
	public function test_get_diagnostics_report_contains_all_groups() {
		$options = $this->settings->get_options();
		$report  = $this->settings->get_diagnostics_report( $options );

		$group_titles = array_map(
			function ( $group ) {
				return $group['title'];
			},
			$report['groups']
		);

		// 各グループが存在することを確認（翻訳されている可能性があるため、キーで判断しない）.
		$this->assertCount( 7, $report['groups'] );
	}

	/**
	 * get_diagnostics_report の各グループが items を持つことをテスト。
	 */
	public function test_get_diagnostics_report_groups_have_items() {
		$options = $this->settings->get_options();
		$report  = $this->settings->get_diagnostics_report( $options );

		foreach ( $report['groups'] as $group ) {
			$this->assertArrayHasKey( 'title', $group );
			$this->assertArrayHasKey( 'items', $group );
			$this->assertIsArray( $group['items'] );
		}
	}

	/**
	 * get_diagnostics_report の各アイテムが status と message を持つことをテスト。
	 */
	public function test_get_diagnostics_report_items_have_required_fields() {
		$options = $this->settings->get_options();
		$report  = $this->settings->get_diagnostics_report( $options );

		foreach ( $report['groups'] as $group ) {
			foreach ( $group['items'] as $item ) {
				$this->assertArrayHasKey( 'status', $item );
				$this->assertArrayHasKey( 'message', $item );
				$this->assertContains( $item['status'], array( 'ok', 'info', 'warning', 'error' ) );
			}
		}
	}

	/**
	 * get_diagnostics_report が欠損フィールドに対してエラーを報告することをテスト。
	 */
	public function test_get_diagnostics_report_reports_errors_for_missing_fields() {
		$options = array(
			'company_name' => '',
			'site_url'     => '',
			'site_name'    => '',
			'phone'        => '',
			'address'      => array(
				'locality'       => '',
				'street_address' => '',
				'region'         => '',
				'postal_code'    => '',
			),
			'geo'          => array(
				'latitude'  => '',
				'longitude' => '',
			),
		);

		$report = $this->settings->get_diagnostics_report( $options );

		$has_error = false;
		foreach ( $report['groups'] as $group ) {
			foreach ( $group['items'] as $item ) {
				if ( 'error' === $item['status'] ) {
					$has_error = true;
					break 2;
				}
			}
		}

		$this->assertTrue( $has_error, 'Should have at least one error for missing required fields' );
	}

	/**
	 * get_diagnostics_report が完全な設定に対して OK を報告することをテスト。
	 */
	public function test_get_diagnostics_report_reports_ok_for_complete_options() {
		$options = array(
			'company_name'  => 'Test Company',
			'site_url'      => 'https://example.com',
			'site_name'     => 'Test Site',
			'phone'         => '03-1234-5678',
			'logo_url'      => 'https://example.com/logo.png',
			'logo_id'       => 123,
			'social_links'  => array(
				array(
					'network' => 'facebook',
					'account' => 'test',
				),
			),
			'address'       => array(
				'locality'       => '千代田区',
				'street_address' => '霞が関1-1',
				'region'         => '東京都',
				'postal_code'    => '100-0001',
				'country'        => 'JP',
			),
			'geo'           => array(
				'latitude'  => '35.6762',
				'longitude' => '139.6503',
			),
			'opening_hours' => array(
				array(
					'day_key' => 'Monday',
					'slots'   => array(
						array(
							'opens'  => '09:00',
							'closes' => '18:00',
						),
					),
				),
			),
			'languages'     => array( 'ja' ),
			'content_model' => 'Article',
		);

		$report = $this->settings->get_diagnostics_report( $options );

		// Organization グループを探す.
		$org_group = null;
		foreach ( $report['groups'] as $group ) {
			if ( strpos( $group['title'], 'Organization' ) !== false ) {
				$org_group = $group;
				break;
			}
		}

		$this->assertNotNull( $org_group );

		// 少なくとも1つの OK ステータスがあるか.
		$has_ok = false;
		foreach ( $org_group['items'] as $item ) {
			if ( 'ok' === $item['status'] ) {
				$has_ok = true;
				break;
			}
		}

		$this->assertTrue( $has_ok, 'Should have at least one OK status for complete Organization' );
	}

	// ===== Edge Cases =====

	/**
	 * sanitize_options が空の入力を処理できることをテスト。
	 */
	public function test_sanitize_options_handles_empty_input() {
		$result = $this->settings->sanitize_options( array() );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'company_name', $result );
	}

	/**
	 * sanitize_options が非配列入力を処理できることをテスト。
	 */
	public function test_sanitize_options_handles_non_array_input() {
		$result = $this->settings->sanitize_options( null );

		$this->assertIsArray( $result );
	}

	/**
	 * get_options が壊れたオプションデータを処理できることをテスト。
	 */
	public function test_get_options_handles_corrupted_data() {
		AVC_AIS_TEST_Env::$options['avc_ais_options'] = 'not an array';

		$options = $this->settings->get_options();

		$this->assertIsArray( $options );
		$this->assertArrayHasKey( 'company_name', $options );
	}

	/**
	 * sanitize_options が XSS を除去することをテスト。
	 */
	public function test_sanitize_options_removes_xss_from_text_fields() {
		$input = array(
			'company_name' => '<script>alert("xss")</script>Test Company',
			'site_name'    => '<img src=x onerror=alert(1)>Site Name',
			'phone'        => '<a href="javascript:alert(1)">03-1234-5678</a>',
		);

		$result = $this->settings->sanitize_options( $input );

		$this->assertStringNotContainsString( '<script>', $result['company_name'] );
		$this->assertStringNotContainsString( 'onerror', $result['site_name'] );
		$this->assertStringNotContainsString( 'javascript:', $result['phone'] );
	}

	/**
	 * sanitize_options が複数スロットの営業時間を処理できることをテスト。
	 */
	public function test_sanitize_opening_hours_with_multiple_slots() {
		$input = array(
			'opening_hours' => array(
				array(
					'day_key' => 'Monday',
					'slots'   => array(
						array(
							'opens'  => '09:00',
							'closes' => '12:00',
						),
						array(
							'opens'  => '13:00',
							'closes' => '18:00',
						),
					),
				),
			),
		);

		$result = $this->settings->sanitize_options( $input );

		$this->assertCount( 1, $result['opening_hours'] );
		$this->assertCount( 2, $result['opening_hours'][0]['slots'] );
		$this->assertEquals( '09:00', $result['opening_hours'][0]['slots'][0]['opens'] );
		$this->assertEquals( '12:00', $result['opening_hours'][0]['slots'][0]['closes'] );
		$this->assertEquals( '13:00', $result['opening_hours'][0]['slots'][1]['opens'] );
		$this->assertEquals( '18:00', $result['opening_hours'][0]['slots'][1]['closes'] );
	}
}

<?php
/**
 * 競合プラグインスキーマ抑止のテスト。
 *
 * Yoast SEO、Rank Math、AIOSEOなどの競合プラグインの
 * スキーマ出力抑止機能をテストします。
 *
 * @package Aivec\AiSearchSchema\Tests
 */

require_once __DIR__ . '/bootstrap.php';

/**
 * 競合スキーマ抑止テストクラス。
 */
class CompetingSchemaSuppressTest extends WP_UnitTestCase {

	/**
	 * スキーマインスタンス。
	 *
	 * @var AI_Search_Schema
	 */
	private $schema;

	/**
	 * テスト前のセットアップ。
	 */
	protected function setUp(): void {
		parent::setUp();

		// AI_Search_Schema_Settings シングルトンをリセット.
		$settings_ref = new ReflectionProperty( AI_Search_Schema_Settings::class, 'instance' );
		$settings_ref->setAccessible( true );
		$settings_ref->setValue( null, null );

		// AI_Search_Schema シングルトンをリセット.
		$ref = new ReflectionProperty( AI_Search_Schema::class, 'instance' );
		$ref->setAccessible( true );
		$ref->setValue( null, null );

		// フィルターをリセット.
		global $wp_filter;
		$hooks_to_reset = array(
			'wpseo_json_ld_output',
			'wpseo_schema_graph_pieces',
			'rank_math/json_ld',
			'rank_math/frontend/json_ld',
			'aioseo_schema_output',
			'aioseo_schema_output_context',
		);

		foreach ( $hooks_to_reset as $hook ) {
			if ( isset( $wp_filter[ $hook ] ) ) {
				unset( $wp_filter[ $hook ] );
			}
		}

		delete_option( 'avc_ais_options' );
	}

	/**
	 * スキーマ優先度が「avc」の場合、競合プラグインが抑止されることをテスト。
	 */
	public function test_suppress_competing_plugins_when_priority_is_avc() {
		update_option(
			'avc_ais_options',
			array(
				'company_name'         => 'Test Company',
				'entity_type'          => 'Organization',
				'avc_ais_priority'  => 'ais',
			)
		);

		$this->schema = AI_Search_Schema::init();
		$this->schema->maybe_toggle_competing_schema_plugins();

		// Yoast SEO フィルターが登録されていることを確認.
		$this->assertTrue(
			has_filter( 'wpseo_json_ld_output' ) !== false,
			'Yoast SEO schema should be suppressed'
		);

		// Rank Math フィルターが登録されていることを確認.
		$this->assertTrue(
			has_filter( 'rank_math/json_ld' ) !== false,
			'Rank Math schema should be suppressed'
		);
	}

	/**
	 * スキーマ優先度が「external」の場合、競合プラグインが抑止されないことをテスト。
	 */
	public function test_no_suppression_when_priority_is_external() {
		update_option(
			'avc_ais_options',
			array(
				'company_name'         => 'Test Company',
				'entity_type'          => 'Organization',
				'avc_ais_priority'  => 'external',
			)
		);

		$this->schema = AI_Search_Schema::init();
		$this->schema->maybe_toggle_competing_schema_plugins();

		// suppressing_external_schema フラグが false であることを確認.
		$ref = new ReflectionProperty( AI_Search_Schema::class, 'suppressing_external_schema' );
		$ref->setAccessible( true );
		$is_suppressing = $ref->getValue( $this->schema );

		$this->assertFalse( $is_suppressing, 'Should NOT be suppressing when priority is external' );
	}

	/**
	 * 抑止フィルターの構造をテスト。
	 */
	public function test_suppress_filter_structure() {
		update_option(
			'avc_ais_options',
			array(
				'company_name'         => 'Test Company',
				'entity_type'          => 'Organization',
				'avc_ais_priority'  => 'ais',
			)
		);

		$this->schema = AI_Search_Schema::init();
		$this->schema->maybe_toggle_competing_schema_plugins();

		// external_schema_filters プロパティを確認.
		$ref = new ReflectionProperty( AI_Search_Schema::class, 'external_schema_filters' );
		$ref->setAccessible( true );
		$filters = $ref->getValue( $this->schema );

		$this->assertIsArray( $filters );
		$this->assertNotEmpty( $filters );

		// 必要なフックがすべて含まれていることを確認.
		$hooks = array_column( $filters, 'hook' );
		$this->assertContains( 'wpseo_json_ld_output', $hooks );
		$this->assertContains( 'rank_math/json_ld', $hooks );
	}

	/**
	 * 二重抑止が防止されることをテスト。
	 */
	public function test_double_suppression_is_prevented() {
		update_option(
			'avc_ais_options',
			array(
				'company_name'         => 'Test Company',
				'entity_type'          => 'Organization',
				'avc_ais_priority'  => 'ais',
			)
		);

		$this->schema = AI_Search_Schema::init();

		// 2回呼び出し.
		$this->schema->maybe_toggle_competing_schema_plugins();
		$this->schema->maybe_toggle_competing_schema_plugins();

		// suppressing_external_schema フラグを確認.
		$ref = new ReflectionProperty( AI_Search_Schema::class, 'suppressing_external_schema' );
		$ref->setAccessible( true );
		$is_suppressing = $ref->getValue( $this->schema );

		$this->assertTrue( $is_suppressing, 'Suppression flag should be set after first call' );
	}

	/**
	 * デフォルト（schema_priority未設定）の動作をテスト。
	 */
	public function test_default_schema_priority_behavior() {
		update_option(
			'avc_ais_options',
			array(
				'company_name' => 'Test Company',
				'entity_type'  => 'Organization',
				// schema_priority は未設定.
			)
		);

		$this->schema = AI_Search_Schema::init();
		$this->schema->maybe_toggle_competing_schema_plugins();

		// デフォルトは 'ais' なので抑止されるはず.
		$this->assertTrue(
			has_filter( 'wpseo_json_ld_output' ) !== false,
			'Default behavior should suppress competing plugins'
		);
	}

	/**
	 * フィルターによる競合パターン拡張をテスト。
	 */
	public function test_competing_schema_patterns_filter() {
		// カスタムパターンを追加するフィルター.
		add_filter(
			'avc_ais_competing_patterns',
			function ( $patterns ) {
				$patterns[] = 'custom_plugin_schema';
				return $patterns;
			}
		);

		update_option(
			'avc_ais_options',
			array(
				'company_name'         => 'Test Company',
				'entity_type'          => 'Organization',
				'avc_ais_priority'  => 'ais',
			)
		);

		$this->schema = AI_Search_Schema::init();

		// フィルターが適用可能な状態になっていることを確認.
		$patterns = apply_filters( 'avc_ais_competing_patterns', array() );
		$this->assertContains( 'custom_plugin_schema', $patterns );
	}

	/**
	 * Yoast SEOのフィルターが正しく __return_false を返すことをテスト。
	 */
	public function test_yoast_filter_returns_false() {
		update_option(
			'avc_ais_options',
			array(
				'company_name'         => 'Test Company',
				'entity_type'          => 'Organization',
				'avc_ais_priority'  => 'ais',
			)
		);

		$this->schema = AI_Search_Schema::init();
		$this->schema->maybe_toggle_competing_schema_plugins();

		// wpseo_json_ld_output フィルターを適用.
		$result = apply_filters( 'wpseo_json_ld_output', array( 'test' => 'data' ) );

		$this->assertFalse( $result, 'Yoast filter should return false' );
	}

	/**
	 * Rank Mathのフィルターが空配列を返すことをテスト。
	 */
	public function test_rank_math_filter_returns_empty_array() {
		update_option(
			'avc_ais_options',
			array(
				'company_name'         => 'Test Company',
				'entity_type'          => 'Organization',
				'avc_ais_priority'  => 'ais',
			)
		);

		$this->schema = AI_Search_Schema::init();
		$this->schema->maybe_toggle_competing_schema_plugins();

		// rank_math/json_ld フィルターを適用.
		$result = apply_filters( 'rank_math/json_ld', array( 'test' => 'data' ) );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result, 'Rank Math filter should return empty array' );
	}
}

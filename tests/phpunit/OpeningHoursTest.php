<?php
/**
 * 営業時間スキーマのテスト。
 *
 * openingHoursSpecification の生成と検証をテストします。
 *
 * @package Aivec\AiSearchSchema\Tests
 */

require_once __DIR__ . '/bootstrap.php';

/**
 * OpeningHours テストクラス。
 */
class OpeningHoursTest extends WP_UnitTestCase {

	/**
	 * ページID。
	 *
	 * @var int
	 */
	private int $page_id;

	/**
	 * テスト前のセットアップ。
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->page_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'Business Page',
				'post_content' => '<p>Business description.</p>',
				'post_status'  => 'publish',
			)
		);

		update_post_meta(
			$this->page_id,
			'_ai_search_schema_meta',
			array( 'page_type' => 'WebPage' )
		);

		// シングルトンをリセット.
		$ref = new ReflectionProperty( AI_Search_Schema::class, 'instance' );
		$ref->setAccessible( true );
		$ref->setValue( null, null );
	}

	/**
	 * テスト後のクリーンアップ。
	 */
	protected function tearDown(): void {
		delete_option( 'ai_search_schema_options' );
		parent::tearDown();
	}

	/**
	 * 営業時間が正しく出力されることをテスト。
	 */
	public function test_opening_hours_specification_is_generated() {
		update_option(
			'ai_search_schema_options',
			array(
				'company_name'  => 'Test Business',
				'entity_type'   => 'LocalBusiness',
				'lb_subtype'    => 'Restaurant',
				'phone'         => '03-1234-5678',
				'geo'           => array(
					'latitude'  => '35.6762',
					'longitude' => '139.6503',
				),
				'address'       => array(
					'postal_code'    => '100-0001',
					'prefecture'     => '東京都',
					'prefecture_iso' => 'JP-13',
					'locality'       => '千代田区',
					'street_address' => '霞が関1-1',
					'country'        => 'JP',
				),
				'opening_hours' => array(
					array(
						'day'    => 'Monday',
						'opens'  => '09:00',
						'closes' => '18:00',
					),
					array(
						'day'    => 'Tuesday',
						'opens'  => '09:00',
						'closes' => '18:00',
					),
				),
			)
		);

		$this->go_to( get_permalink( $this->page_id ) );
		$schema = AI_Search_Schema::init();

		ob_start();
		$schema->output_json_ld();
		$output = ob_get_clean();

		$json           = $this->extract_json_ld( $output );
		$local_business = $this->find_item_by_type( $json['@graph'], 'Restaurant' );

		$this->assertNotNull( $local_business, 'LocalBusiness node should exist' );
		$this->assertArrayHasKey( 'openingHoursSpecification', $local_business );
		$this->assertCount( 2, $local_business['openingHoursSpecification'] );

		$monday = $local_business['openingHoursSpecification'][0];
		$this->assertEquals( 'OpeningHoursSpecification', $monday['@type'] );
		$this->assertEquals( 'Monday', $monday['dayOfWeek'] );
		$this->assertEquals( '09:00', $monday['opens'] );
		$this->assertEquals( '18:00', $monday['closes'] );
	}

	/**
	 * 不完全な営業時間データがスキップされることをテスト。
	 */
	public function test_incomplete_opening_hours_are_skipped() {
		update_option(
			'ai_search_schema_options',
			array(
				'company_name'  => 'Test Business',
				'entity_type'   => 'LocalBusiness',
				'phone'         => '03-1234-5678',
				'geo'           => array(
					'latitude'  => '35.6762',
					'longitude' => '139.6503',
				),
				'address'       => array(
					'postal_code'    => '100-0001',
					'prefecture'     => '東京都',
					'prefecture_iso' => 'JP-13',
					'locality'       => '千代田区',
					'street_address' => '霞が関1-1',
					'country'        => 'JP',
				),
				'opening_hours' => array(
					array(
						'day'    => 'Monday',
						'opens'  => '09:00',
						'closes' => '18:00',
					),
					array(
						'day'   => 'Tuesday',
						'opens' => '09:00',
						// 'closes' が欠落.
					),
					array(
						'day'    => '',
						'opens'  => '10:00',
						'closes' => '17:00',
					),
				),
			)
		);

		$this->go_to( get_permalink( $this->page_id ) );
		$schema = AI_Search_Schema::init();

		ob_start();
		$schema->output_json_ld();
		$output = ob_get_clean();

		$json           = $this->extract_json_ld( $output );
		$local_business = $this->find_item_by_type( $json['@graph'], 'LocalBusiness' );

		$this->assertNotNull( $local_business, 'LocalBusiness node should exist' );
		$this->assertArrayHasKey( 'openingHoursSpecification', $local_business );
		$this->assertCount( 1, $local_business['openingHoursSpecification'], 'Only valid opening hours should be included' );
	}

	/**
	 * 営業時間が空の場合、openingHoursSpecificationが出力されないことをテスト。
	 */
	public function test_empty_opening_hours_not_output() {
		update_option(
			'ai_search_schema_options',
			array(
				'company_name'  => 'Test Business',
				'entity_type'   => 'LocalBusiness',
				'phone'         => '03-1234-5678',
				'geo'           => array(
					'latitude'  => '35.6762',
					'longitude' => '139.6503',
				),
				'address'       => array(
					'postal_code'    => '100-0001',
					'prefecture'     => '東京都',
					'prefecture_iso' => 'JP-13',
					'locality'       => '千代田区',
					'street_address' => '霞が関1-1',
					'country'        => 'JP',
				),
				'opening_hours' => array(),
			)
		);

		$this->go_to( get_permalink( $this->page_id ) );
		$schema = AI_Search_Schema::init();

		ob_start();
		$schema->output_json_ld();
		$output = ob_get_clean();

		$json           = $this->extract_json_ld( $output );
		$local_business = $this->find_item_by_type( $json['@graph'], 'LocalBusiness' );

		$this->assertNotNull( $local_business, 'LocalBusiness node should exist' );
		$this->assertArrayNotHasKey( 'openingHoursSpecification', $local_business, 'Empty opening hours should not be output' );
	}

	/**
	 * 全曜日の営業時間が正しく出力されることをテスト。
	 */
	public function test_all_days_opening_hours() {
		$days          = array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' );
		$opening_hours = array();

		foreach ( $days as $day ) {
			$opening_hours[] = array(
				'day'    => $day,
				'opens'  => '10:00',
				'closes' => '20:00',
			);
		}

		update_option(
			'ai_search_schema_options',
			array(
				'company_name'  => 'Test Business',
				'entity_type'   => 'LocalBusiness',
				'phone'         => '03-1234-5678',
				'geo'           => array(
					'latitude'  => '35.6762',
					'longitude' => '139.6503',
				),
				'address'       => array(
					'postal_code'    => '100-0001',
					'prefecture'     => '東京都',
					'prefecture_iso' => 'JP-13',
					'locality'       => '千代田区',
					'street_address' => '霞が関1-1',
					'country'        => 'JP',
				),
				'opening_hours' => $opening_hours,
			)
		);

		$this->go_to( get_permalink( $this->page_id ) );
		$schema = AI_Search_Schema::init();

		ob_start();
		$schema->output_json_ld();
		$output = ob_get_clean();

		$json           = $this->extract_json_ld( $output );
		$local_business = $this->find_item_by_type( $json['@graph'], 'LocalBusiness' );

		$this->assertNotNull( $local_business, 'LocalBusiness node should exist' );
		$this->assertArrayHasKey( 'openingHoursSpecification', $local_business );
		$this->assertCount( 7, $local_business['openingHoursSpecification'], 'All 7 days should be present' );

		// 各曜日が正しい順序で含まれていることを確認.
		foreach ( $days as $index => $day ) {
			$this->assertEquals( $day, $local_business['openingHoursSpecification'][ $index ]['dayOfWeek'] );
		}
	}

	/**
	 * JSON-LDを抽出。
	 *
	 * @param string $output HTML出力.
	 * @return array|null JSON-LDデータ.
	 */
	private function extract_json_ld( $output ) {
		if ( ! preg_match( '/<script[^>]+class="ai-search-schema"[^>]*>(.*)<\/script>/s', $output, $matches ) ) {
			return null;
		}
		return json_decode( html_entity_decode( trim( $matches[1] ) ), true );
	}

	/**
	 * グラフからタイプでアイテムを検索。
	 *
	 * @param array  $graph グラフ配列.
	 * @param string $type 検索するタイプ.
	 * @return array|null 見つかったアイテム.
	 */
	private function find_item_by_type( array $graph, $type ) {
		foreach ( $graph as $item ) {
			if ( isset( $item['@type'] ) ) {
				if ( is_array( $item['@type'] ) && in_array( $type, $item['@type'], true ) ) {
					return $item;
				}
				if ( $item['@type'] === $type ) {
					return $item;
				}
			}
		}
		return null;
	}
}

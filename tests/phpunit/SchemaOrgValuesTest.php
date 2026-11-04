<?php
/**
 * JSON-LD に schema.org に存在しない値を出力しないことのテスト。
 *
 * @package Aivec\\AiSearchSchema\\Tests
 */

require_once __DIR__ . '/bootstrap.php';

/**
 * schema.org の値のテストクラス。
 */
class SchemaOrgValuesTest extends WP_UnitTestCase {

	/**
	 * トップページの型を返す。
	 *
	 * @return mixed WebPage ノードの @type.
	 */
	private function front_page_type() {
		update_option( 'show_on_front', 'posts' );
		$this->go_to( home_url( '/' ) );

		$resolver = new AVC_AIS_ContentResolver();
		$types    = $resolver->determine_webpage_types( $resolver->resolve_page_context() );

		return count( $types ) === 1 ? $types[0] : $types;
	}

	/**
	 * トップページは schema.org に存在しない HomePage を出力せず、WebPage になること。
	 *
	 * @return void
	 */
	public function test_front_page_is_web_page() {
		$this->assertSame( 'WebPage', $this->front_page_type() );
	}

	/**
	 * 固定ページをトップページにしても WebPage になること。
	 *
	 * @return void
	 */
	public function test_static_front_page_is_web_page() {
		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $page_id );
		$this->go_to( home_url( '/' ) );

		$resolver = new AVC_AIS_ContentResolver();

		$this->assertSame( array( 'WebPage' ), $resolver->determine_webpage_types( $resolver->resolve_page_context() ) );
	}

	/**
	 * 一覧のページの型は schema.org の型のままであること。
	 *
	 * @dataProvider provide_listing_types
	 *
	 * @param string $context_type コンテキストの種類.
	 * @param string $expected     WebPage に加える型.
	 * @return void
	 */
	public function test_listing_pages_keep_valid_types( $context_type, $expected ) {
		$resolver = new AVC_AIS_ContentResolver();

		$this->assertSame(
			array( 'WebPage', $expected ),
			$resolver->determine_webpage_types( array( 'type' => $context_type ) )
		);
	}

	/**
	 * 一覧のページの種類と型を返す。
	 *
	 * @return array<string,array<int,string>>
	 */
	public function provide_listing_types() {
		return array(
			'category' => array( 'category', 'CollectionPage' ),
			'search'   => array( 'search', 'SearchResultsPage' ),
			'author'   => array( 'author', 'ProfilePage' ),
		);
	}

	/**
	 * 保存済みの古いサブタイプを schema.org の型名へ読み替えること。
	 *
	 * @dataProvider provide_renamed_business_types
	 *
	 * @param string $saved    保存済みの値.
	 * @param string $expected schema.org の型名.
	 * @return void
	 */
	public function test_saved_business_type_is_renamed( $saved, $expected ) {
		$this->assertSame( $expected, avc_ais_normalize_business_type( $saved ) );
	}

	/**
	 * 読み替えるサブタイプを返す。
	 *
	 * @return array<string,array<int,string>>
	 */
	public function provide_renamed_business_types() {
		return array(
			'cafe'         => array( 'Cafe', 'CafeOrCoffeeShop' ),
			'gym'          => array( 'FitnessCenter', 'ExerciseGym' ),
			'spa'          => array( 'HealthAndBeautySpa', 'DaySpa' ),
			'construction' => array( 'HomeAndConstruction', 'HomeAndConstructionBusiness' ),
			'unchanged'    => array( 'Restaurant', 'Restaurant' ),
			'free text'    => array( 'Museum', 'Museum' ),
		);
	}

	/**
	 * 配列などを渡されても致命的エラーにせず、そのまま返すこと。
	 *
	 * @return void
	 */
	public function test_non_string_business_type_is_returned_as_is() {
		$types = array( 'Restaurant', 'BarOrPub' );

		$this->assertSame( $types, avc_ais_normalize_business_type( $types ) );
	}

	/**
	 * 保存済みの古いサブタイプを設定の読み出しで読み替え、JSON-LD に schema.org の型名を出力すること。
	 *
	 * @return void
	 */
	public function test_saved_old_business_type_is_output_with_schema_org_name() {
		AVC_AIS_Settings::init();
		update_option(
			'avc_ais_options',
			array(
				'lb_subtype'    => 'Cafe',
				'business_type' => 'Cafe',
			)
		);

		$options = get_option( 'avc_ais_options' );
		$this->assertSame( 'CafeOrCoffeeShop', $options['lb_subtype'] );
		$this->assertSame( 'CafeOrCoffeeShop', $options['business_type'] );

		$node = AVC_AIS_Type_LocalBusiness::build(
			$options,
			array(
				'local_business' => 'https://example.com/#lb',
				'organization'   => 'https://example.com/#org',
			),
			'https://example.com/',
			'サンプルのカフェ',
			array(),
			array(),
			array(),
			array(),
			'',
			true
		);

		$this->assertSame( 'CafeOrCoffeeShop', $node['@type'] );
	}

	/**
	 * ウィザードは保存済みの業種を選択済みにすること。
	 *
	 * sanitize_options() は local_business_type を保存しないので、lb_subtype を見る必要がある。
	 * 選択済みにならないと、再度送信したときに業種が LocalBusiness に戻ってしまう。
	 *
	 * @dataProvider provide_saved_business_types
	 *
	 * @param string $saved    保存した値.
	 * @param string $expected 選択済みになる値.
	 * @return void
	 */
	public function test_wizard_selects_saved_business_type( $saved, $expected ) {
		AVC_AIS_Settings::init()->register_settings();
		update_option( 'avc_ais_options', array( 'lb_subtype' => $saved ) );

		$this->assertArrayNotHasKey( 'local_business_type', get_option( 'avc_ais_options' ), '前提: 本番と同じく保存されない' );

		ob_start();
		require AVC_AIS_DIR . 'templates/wizard/steps/step-3-location.php';
		$html = (string) ob_get_clean();

		$this->assertMatchesRegularExpression( '/<option value="' . $expected . '"\s+selected/', $html );
	}

	/**
	 * ウィザードで確認する業種を返す。
	 *
	 * @return array<string,array<int,string>>
	 */
	public function provide_saved_business_types() {
		return array(
			'current value' => array( 'Restaurant', 'Restaurant' ),
			'old value'     => array( 'FitnessCenter', 'ExerciseGym' ),
		);
	}

	/**
	 * ウィザードの業種の選択肢に、読み替えの対象の古い値がないこと。
	 *
	 * @return void
	 */
	public function test_wizard_choices_do_not_use_renamed_values() {
		$template = (string) file_get_contents( AVC_AIS_DIR . 'templates/wizard/steps/step-3-location.php' );

		foreach ( array( 'Cafe', 'FitnessCenter', 'HealthAndBeautySpa', 'HomeAndConstruction' ) as $old ) {
			$this->assertSame( 0, preg_match( "/'{$old}'\\s*=>/", $template ), $old );
		}
	}

	/**
	 * 祝日の dayOfWeek は schema.org の PublicHolidays で出力すること。
	 *
	 * @return void
	 */
	public function test_public_holiday_uses_schema_org_value() {
		$hours = AVC_AIS_OpeningHoursBuilder::build(
			array(
				'holiday_enabled' => true,
				'holiday_mode'    => 'custom',
				'opening_hours'   => array(
					array(
						'day_key' => 'Monday',
						'slots'   => array(
							array(
								'opens'  => '09:00',
								'closes' => '18:00',
							),
						),
					),
					array(
						'day_key' => 'PublicHoliday',
						'slots'   => array(
							array(
								'opens'  => '10:00',
								'closes' => '17:00',
							),
						),
					),
				),
			)
		);

		$this->assertSame( array( 'Monday', 'PublicHolidays' ), array_column( $hours, 'dayOfWeek' ) );
	}
}

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
	 * 出力する型は schema.org の LocalBusiness のサブタイプになること。
	 *
	 * @dataProvider provide_business_types
	 *
	 * @param mixed $saved    保存済みの値.
	 * @param mixed $expected 出力する型.
	 * @return void
	 */
	public function test_output_type_is_a_local_business_type( $saved, $expected ) {
		$this->assertSame( $expected, AVC_AIS_LocalBusinessTypes::to_schema_type( $saved ) );
	}

	/**
	 * 保存済みの値と出力する型を返す。
	 *
	 * @return array<string,array<int,mixed>>
	 */
	public function provide_business_types() {
		return array(
			'old cafe'         => array( 'Cafe', 'CafeOrCoffeeShop' ),
			'old gym'          => array( 'FitnessCenter', 'ExerciseGym' ),
			'old spa'          => array( 'HealthAndBeautySpa', 'DaySpa' ),
			'old construction' => array( 'HomeAndConstruction', 'HomeAndConstructionBusiness' ),
			'unchanged'        => array( 'Restaurant', 'Restaurant' ),
			'lower case'       => array( 'hairsalon', 'HairSalon' ),
			'spaces'           => array( ' Hair Salon ', 'HairSalon' ),
			'not schema.org'   => array( 'NotARealType', 'LocalBusiness' ),
			'other schema.org' => array( 'Museum', 'LocalBusiness' ),
			'empty'            => array( '', 'LocalBusiness' ),
			'null'             => array( null, 'LocalBusiness' ),
			'number'           => array( 123, 'LocalBusiness' ),
			'list'             => array( array( 'Restaurant', 'BarOrPub' ), array( 'Restaurant', 'BarOrPub' ) ),
			'list one valid'   => array( array( 'Cafe', 'NotARealType' ), 'CafeOrCoffeeShop' ),
			'list none valid'  => array( array( 'NotARealType' ), 'LocalBusiness' ),
		);
	}

	/**
	 * フィルターで選択できる型を追加できること。
	 *
	 * @return void
	 */
	public function test_types_can_be_added_by_filter() {
		$this->assertFalse( AVC_AIS_LocalBusinessTypes::is_valid( 'Museum' ), '前提: 既定では選択できない' );

		add_filter(
			'avc_ais_local_business_types',
			static function ( $types ) {
				$types[] = 'Museum';
				return $types;
			}
		);

		$this->assertTrue( AVC_AIS_LocalBusinessTypes::is_valid( 'Museum' ) );
		$this->assertSame( 'Museum', AVC_AIS_LocalBusinessTypes::to_schema_type( 'Museum' ) );
	}

	/**
	 * 無効な保存済みの値は読み出しでは変えず、JSON-LD には LocalBusiness を出力すること。
	 *
	 * 設定画面で無効な値だと知らせるために、読み出しでは変えない。
	 *
	 * @return void
	 */
	public function test_invalid_saved_type_is_kept_but_not_output() {
		AVC_AIS_Settings::init();
		update_option( 'avc_ais_options', array( 'lb_subtype' => 'NotARealType' ) );

		$options = get_option( 'avc_ais_options' );
		$this->assertSame( 'NotARealType', $options['lb_subtype'] );

		$node = AVC_AIS_Type_LocalBusiness::build(
			$options,
			array(
				'local_business' => 'https://example.com/#lb',
				'organization'   => 'https://example.com/#org',
			),
			'https://example.com/',
			'サンプル',
			array(),
			array(),
			array(),
			array(),
			'',
			true
		);

		$this->assertSame( 'LocalBusiness', $node['@type'] );
	}

	/**
	 * 配列などを渡されても致命的エラーにせず、そのまま返すこと。
	 *
	 * @return void
	 */
	public function test_rename_keeps_non_strings() {
		$types = array( 'Restaurant', 'BarOrPub' );

		$this->assertSame( $types, AVC_AIS_LocalBusinessTypes::rename( $types ) );
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
			'lower case'    => array( 'hairsalon', 'HairSalon' ),
		);
	}

	/**
	 * 設定を保存すると、値が選択できる型に変わること。
	 *
	 * @dataProvider provide_saved_settings
	 *
	 * @param string $input    入力.
	 * @param string $expected 保存する値.
	 * @return void
	 */
	public function test_saving_settings_stores_a_valid_type( $input, $expected ) {
		$settings = new AVC_AIS_Settings();
		$output   = $settings->sanitize_options( array( 'lb_subtype' => $input ) );

		$this->assertSame( $expected, $output['lb_subtype'] );
		$this->assertSame( $expected, $output['business_type'] );
	}

	/**
	 * 設定の入力と保存する値を返す。
	 *
	 * @return array<string,array<int,string>>
	 */
	public function provide_saved_settings() {
		return array(
			'valid'   => array( 'Dentist', 'Dentist' ),
			'old'     => array( 'FitnessCenter', 'ExerciseGym' ),
			'invalid' => array( 'NotARealType', 'LocalBusiness' ),
			'empty'   => array( '', 'LocalBusiness' ),
		);
	}

	/**
	 * ウィザードのプレビューが LocalBusiness の型を schema.org の型で出力すること。
	 *
	 * @return void
	 */
	public function test_wizard_preview_outputs_valid_type() {
		$method = new ReflectionMethod( \Aivec\AiSearchSchema\Wizard\Wizard::class, 'build_preview_local_business' );
		avc_ais_test_make_accessible( $method );

		$node = $method->invoke(
			new \Aivec\AiSearchSchema\Wizard\Wizard(),
			array(
				'company_name' => 'サンプル',
				'lb_subtype'   => 'FitnessCenter',
			),
			array(
				'local_business' => 'https://example.com/#lb',
				'organization'   => 'https://example.com/#org',
			),
			'https://example.com/'
		);

		$this->assertSame( 'ExerciseGym', $node['@type'] );
	}

	/**
	 * 設定画面で選んだ、ウィザードの選択肢にない型も選択済みになること。
	 *
	 * @return void
	 */
	public function test_wizard_keeps_type_chosen_in_settings() {
		AVC_AIS_Settings::init()->register_settings();
		update_option( 'avc_ais_options', array( 'lb_subtype' => 'Winery' ) );

		ob_start();
		require AVC_AIS_DIR . 'templates/wizard/steps/step-3-location.php';
		$html = (string) ob_get_clean();

		$this->assertMatchesRegularExpression( '/<option value="Winery"\s+selected/', $html );
	}

	/**
	 * フィルターで外した型は、よく使う型の選択肢にも出ないこと。
	 *
	 * @return void
	 */
	public function test_common_choices_follow_filter() {
		add_filter(
			'avc_ais_local_business_types',
			static function ( $types ) {
				return array_values( array_diff( $types, array( 'Restaurant' ) ) );
			}
		);

		$this->assertArrayNotHasKey( 'Restaurant', AVC_AIS_LocalBusinessTypes::common_choices() );
		$this->assertArrayHasKey( 'Bakery', AVC_AIS_LocalBusinessTypes::common_choices() );
	}

	/**
	 * ウィザードの選択肢と読み替え先が、すべて選択できる型であること。
	 *
	 * @return void
	 */
	public function test_choices_are_valid_types() {
		$types = AVC_AIS_LocalBusinessTypes::all();

		$this->assertContains( 'LocalBusiness', $types );
		$this->assertSame( array(), array_values( array_diff( array_keys( AVC_AIS_LocalBusinessTypes::common_choices() ), $types ) ) );
		$this->assertSame( array(), array_values( array_diff( AVC_AIS_LocalBusinessTypes::RENAMED, $types ) ) );
		$this->assertSame( array(), array_values( array_intersect( array_keys( AVC_AIS_LocalBusinessTypes::RENAMED ), $types ) ) );
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

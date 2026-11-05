<?php
/**
 * 管理画面のメニューのアイコンのテスト。
 *
 * @package Aivec\\AiSearchSchema\\Tests
 */

require_once __DIR__ . '/bootstrap.php';

/**
 * 管理画面のメニューのアイコンのテストクラス。
 */
class AdminMenuIconTest extends WP_UnitTestCase {

	/**
	 * add_menu_page() が書き換えるグローバル変数の元の値。
	 *
	 * @var array
	 */
	private $saved_globals = array();

	/**
	 * テスト前のセットアップ。
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		foreach ( array( 'menu', 'submenu', 'admin_page_hooks', '_registered_pages', '_parent_pages', 'l10n', 'wp_styles' ) as $name ) {
			$this->saved_globals[ $name ] = $GLOBALS[ $name ] ?? null;
		}
	}

	/**
	 * テスト後の片付け。
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		foreach ( $this->saved_globals as $name => $value ) {
			$GLOBALS[ $name ] = $value; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		parent::tearDown();
	}

	/**
	 * メニューに AIVEC のシンボルが登録されること。
	 *
	 * @return void
	 */
	public function test_menu_is_registered_with_the_icon() {
		global $menu;

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$menu = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$settings = ( new ReflectionClass( AVC_AIS_Settings::class ) )->newInstanceWithoutConstructor();
		$settings->add_settings_page();

		$item = current( wp_list_filter( $menu, array( 2 => 'aivec-ai-search-schema' ) ) );
		$this->assertSame( AVC_AIS_Settings::MENU_ICON, $item[6] );
	}

	/**
	 * 日本語でもメニュー名は折り返されない短い名前で、画面の題は正式な名前のままであること。
	 *
	 * @return void
	 */
	public function test_menu_label_stays_short_in_japanese() {
		global $menu, $l10n;

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$settings = ( new ReflectionClass( AVC_AIS_Settings::class ) )->newInstanceWithoutConstructor();

		foreach ( array( 'ja', 'ja_JP' ) as $locale ) {
			$mo = new MO();
			$this->assertTrue( $mo->import_from_file( dirname( __DIR__, 2 ) . "/languages/aivec-ai-search-schema-{$locale}.mo" ) );
			$l10n['aivec-ai-search-schema'] = $mo; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$menu                           = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

			$settings->add_settings_page();

			$item = current( wp_list_filter( $menu, array( 2 => 'aivec-ai-search-schema' ) ) );
			$this->assertSame( 'AI Search Schema', $item[0], $locale );
			$this->assertSame( 'Aivec AI Search Schema 設定', $item[3], $locale );
		}
	}

	/**
	 * どの管理画面でも、メニュー名を 1 行に収める余白の指定が読み込まれること。
	 *
	 * @return void
	 */
	public function test_menu_label_style_is_added_to_the_admin_menu_styles() {
		$GLOBALS['wp_styles'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$settings = new AVC_AIS_Settings();
		$this->assertNotFalse( has_action( 'admin_enqueue_scripts', array( $settings, 'add_menu_label_style' ) ) );

		$settings->add_menu_label_style();

		$this->assertStringContainsString(
			'#toplevel_page_aivec-ai-search-schema .wp-menu-name{padding-inline-end:0;letter-spacing:-.2px}',
			implode( '', wp_styles()->get_data( 'admin-menu', 'after' ) )
		);
	}

	/**
	 * アイコンは元の SVG と同じで、WordPress が塗り替えられる形であること。
	 *
	 * @return void
	 */
	public function test_icon_can_be_painted_by_wordpress() {
		$prefix = 'data:image/svg+xml;base64,';
		$this->assertStringStartsWith( $prefix, AVC_AIS_Settings::MENU_ICON );

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$svg = base64_decode( substr( AVC_AIS_Settings::MENU_ICON, strlen( $prefix ) ), true );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$this->assertSame( trim( file_get_contents( dirname( __DIR__, 2 ) . '/docs/images/menu-icon.svg' ) ), $svg );

		// WordPress の svg-painter は fill 属性を置き換える。1 色でなければ塗り分けが残る。
		preg_match_all( '/fill="([^"]+)"/', $svg, $fills );
		$this->assertSame( array( '#a7aaad' ), array_values( array_unique( $fills[1] ) ) );
		$this->assertStringContainsString( 'viewBox="0 -8.5 82 82"', $svg );
	}
}

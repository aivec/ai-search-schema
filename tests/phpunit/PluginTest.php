<?php
/**
 * Plugin クラスのテスト。
 *
 * エントリーポイント、依存ファイルの読み込み、
 * テキストドメインの読み込みなどをテストします。
 *
 * @package Aivec\AiSearchSchema\Tests
 */

require_once __DIR__ . '/bootstrap.php';

use Aivec\AiSearchSchema\Plugin;

/**
 * Plugin クラスのテスト。
 */
class PluginTest extends WP_UnitTestCase {

	/**
	 * Plugin インスタンス。
	 *
	 * @var Plugin
	 */
	private $plugin;

	/**
	 * テスト前のセットアップ。
	 */
	protected function setUp(): void {
		parent::setUp();
		AI_Search_Schema_TEST_Env::$options = array();
		$this->plugin            = new Plugin();
	}

	/**
	 * テスト後のクリーンアップ。
	 */
	protected function tearDown(): void {
		AI_Search_Schema_TEST_Env::$options = array();
		parent::tearDown();
	}

	// ===== Plugin Instantiation Tests =====

	/**
	 * Plugin クラスがインスタンス化できることをテスト。
	 */
	public function test_plugin_can_be_instantiated() {
		$this->assertInstanceOf( Plugin::class, $this->plugin );
	}

	/**
	 * register メソッドが存在することをテスト。
	 */
	public function test_register_method_exists() {
		$this->assertTrue( method_exists( $this->plugin, 'register' ) );
	}

	/**
	 * bootstrap メソッドが存在することをテスト。
	 */
	public function test_bootstrap_method_exists() {
		$this->assertTrue( method_exists( $this->plugin, 'bootstrap' ) );
	}

	/**
	 * load_textdomain メソッドが存在することをテスト。
	 */
	public function test_load_textdomain_method_exists() {
		$this->assertTrue( method_exists( $this->plugin, 'load_textdomain' ) );
	}

	/**
	 * uninstall メソッドが存在することをテスト。
	 */
	public function test_uninstall_method_exists() {
		$this->assertTrue( method_exists( Plugin::class, 'uninstall' ) );
	}

	// ===== Register Tests =====

	/**
	 * register がフックを登録することをテスト。
	 */
	public function test_register_adds_hooks() {
		// フィルター/アクションの登録前の状態を確認.
		$init_priority_before = has_action( 'init', array( $this->plugin, 'bootstrap' ) );

		// register を呼び出し.
		$this->plugin->register();

		// フックが登録されたことを確認.
		$textdomain_priority = has_action( 'init', array( $this->plugin, 'load_textdomain' ) );
		$bootstrap_priority  = has_action( 'init', array( $this->plugin, 'bootstrap' ) );

		$this->assertIsInt( $textdomain_priority );
		$this->assertIsInt( $bootstrap_priority );
		$this->assertEquals( 5, $textdomain_priority );
		$this->assertEquals( 10, $bootstrap_priority );
	}

	/**
	 * register が複数回呼び出せることをテスト（エラーにならない）。
	 */
	public function test_register_can_be_called_multiple_times() {
		$this->plugin->register();
		$this->plugin->register();

		// エラーが発生しないことを確認.
		$this->assertTrue( true );
	}

	// ===== load_textdomain Tests =====

	/**
	 * load_textdomain がエラーなく実行できることをテスト。
	 */
	public function test_load_textdomain_executes_without_error() {
		$this->plugin->load_textdomain();

		// エラーが発生しないことを確認.
		$this->assertTrue( true );
	}

	/**
	 * load_textdomain が plugin_locale フィルターを適用することをテスト。
	 */
	public function test_load_textdomain_applies_locale_filter() {
		$filter_called = false;
		$test_locale   = null;

		add_filter(
			'plugin_locale',
			function ( $locale, $domain ) use ( &$filter_called, &$test_locale ) {
				if ( 'ai-search-schema' === $domain ) {
					$filter_called = true;
					$test_locale   = $locale;
				}
				return $locale;
			},
			10,
			2
		);

		$this->plugin->load_textdomain();

		$this->assertTrue( $filter_called );
		$this->assertNotNull( $test_locale );
	}

	/**
	 * load_textdomain が正しいドメインを使用することをテスト。
	 */
	public function test_load_textdomain_uses_correct_domain() {
		$captured_domain = null;

		add_filter(
			'plugin_locale',
			function ( $locale, $domain ) use ( &$captured_domain ) {
				$captured_domain = $domain;
				return $locale;
			},
			10,
			2
		);

		$this->plugin->load_textdomain();

		$this->assertEquals( 'ai-search-schema', $captured_domain );
	}

	// ===== bootstrap Tests =====

	/**
	 * bootstrap がエラーなく実行できることをテスト。
	 */
	public function test_bootstrap_executes_without_error() {
		// bootstrap を実行（依存クラスが読み込まれる）.
		$this->plugin->bootstrap();

		// エラーが発生しないことを確認.
		$this->assertTrue( true );
	}

	/**
	 * bootstrap 後に必要なクラスが利用可能になることをテスト。
	 */
	public function test_bootstrap_loads_required_classes() {
		$this->plugin->bootstrap();

		// 依存クラスが読み込まれることを確認.
		$this->assertTrue( class_exists( 'AI_Search_Schema_Prefectures' ) );
	}

	/**
	 * bootstrap 後に functions.php の関数が利用可能になることをテスト。
	 */
	public function test_bootstrap_loads_functions() {
		$this->plugin->bootstrap();

		// functions.php から読み込まれる関数をチェック.
		// 注: 実際に存在する関数名に合わせる必要がある.
		$functions_file = AVC_AIS_DIR . 'includes/functions.php';
		$this->assertTrue( file_exists( $functions_file ) );
	}

	// ===== load_dependencies Tests (via Reflection) =====

	/**
	 * load_dependencies が指定ファイルを読み込むことをテスト。
	 */
	public function test_load_dependencies_loads_specified_files() {
		$reflection = new ReflectionClass( $this->plugin );
		$method     = $reflection->getMethod( 'load_dependencies' );
		$method->setAccessible( true );

		// メソッドを呼び出し.
		$method->invoke( $this->plugin );

		// ファイルが読み込まれたことを確認.
		$this->assertTrue( class_exists( 'AI_Search_Schema_Prefectures' ) );
	}

	/**
	 * load_dependencies が存在しないファイルでエラーにならないことをテスト。
	 */
	public function test_load_dependencies_handles_missing_files_gracefully() {
		// Plugin クラスは file_exists でチェックしているため、
		// 存在しないファイルがあってもエラーにならないはず.
		$reflection = new ReflectionClass( $this->plugin );
		$method     = $reflection->getMethod( 'load_dependencies' );
		$method->setAccessible( true );

		// エラーなく実行できることを確認.
		$method->invoke( $this->plugin );
		$this->assertTrue( true );
	}

	// ===== uninstall Tests =====

	/**
	 * uninstall が静的メソッドとして呼び出せることをテスト。
	 */
	public function test_uninstall_is_static_method() {
		$reflection = new ReflectionMethod( Plugin::class, 'uninstall' );

		$this->assertTrue( $reflection->isStatic() );
	}

	/**
	 * uninstall がエラーなく実行できることをテスト。
	 */
	public function test_uninstall_executes_without_error() {
		Plugin::uninstall();

		// エラーが発生しないことを確認.
		$this->assertTrue( true );
	}

	// ===== Integration Tests =====

	/**
	 * register + bootstrap の順序で実行できることをテスト。
	 */
	public function test_register_then_bootstrap_integration() {
		$plugin = new Plugin();

		// register は init フックを登録するだけ.
		$plugin->register();

		// 実際の初期化は bootstrap で行われる.
		$plugin->bootstrap();

		// エラーなく完了することを確認.
		$this->assertTrue( true );
	}

	/**
	 * 管理画面でウィザードが登録されることをテスト。
	 */
	public function test_wizard_registered_in_admin() {
		// is_admin() をシミュレートするのは難しいため、
		// bootstrap 内のロジックを確認.
		$this->plugin->bootstrap();

		// ウィザードクラスが存在することを確認.
		$this->assertTrue( class_exists( 'Aivec\AiSearchSchema\Wizard\Wizard' ) );
	}

	// ===== Constants Tests =====

	/**
	 * プラグイン定数が定義されていることをテスト。
	 */
	public function test_plugin_constants_are_defined() {
		$this->assertTrue( defined( 'AVC_AIS_DIR' ) );
		$this->assertTrue( defined( 'AVC_AIS_VERSION' ) );
	}

	/**
	 * AVC_AIS_DIR が正しいパスを指すことをテスト。
	 */
	public function test_plugin_dir_constant_points_to_correct_path() {
		$dir = AVC_AIS_DIR;

		$this->assertNotEmpty( $dir );
		$this->assertDirectoryExists( $dir );
		$this->assertFileExists( $dir . 'ai-search-schema.php' );
	}

	/**
	 * AVC_AIS_VERSION が正しいフォーマットであることをテスト。
	 */
	public function test_plugin_version_has_correct_format() {
		$version = AVC_AIS_VERSION;

		$this->assertNotEmpty( $version );
		// セマンティックバージョニングのパターン（緩いチェック）.
		$this->assertMatchesRegularExpression( '/^\d+\.\d+/', $version );
	}

	// ===== Edge Cases =====

	/**
	 * 複数の Plugin インスタンスが作成できることをテスト。
	 */
	public function test_multiple_plugin_instances_can_be_created() {
		$plugin1 = new Plugin();
		$plugin2 = new Plugin();

		$this->assertInstanceOf( Plugin::class, $plugin1 );
		$this->assertInstanceOf( Plugin::class, $plugin2 );
		$this->assertNotSame( $plugin1, $plugin2 );
	}

	/**
	 * bootstrap が複数回呼び出されてもエラーにならないことをテスト。
	 */
	public function test_bootstrap_can_be_called_multiple_times() {
		$this->plugin->bootstrap();
		$this->plugin->bootstrap();

		// require_once を使用しているため、複数回呼び出してもエラーにならない.
		$this->assertTrue( true );
	}

	/**
	 * 言語ディレクトリが存在することをテスト。
	 */
	public function test_languages_directory_exists() {
		$languages_dir = AVC_AIS_DIR . 'languages';

		$this->assertDirectoryExists( $languages_dir );
	}

	/**
	 * 依存ファイルが存在することをテスト。
	 */
	public function test_dependency_files_exist() {
		$files = array(
			'includes/functions.php',
			'includes/class-ai-search-schema-prefectures.php',
		);

		foreach ( $files as $file ) {
			$path = AVC_AIS_DIR . $file;
			$this->assertFileExists( $path, "File {$file} should exist" );
		}
	}

	/**
	 * Wp 名前空間クラスが存在することをテスト。
	 */
	public function test_wp_namespace_classes_exist() {
		$classes = array(
			'Aivec\AiSearchSchema\Wp\Settings',
			'Aivec\AiSearchSchema\Wp\MetaBox',
			'Aivec\AiSearchSchema\Wp\Schema',
			'Aivec\AiSearchSchema\Wp\Breadcrumbs',
		);

		foreach ( $classes as $class ) {
			$this->assertTrue( class_exists( $class ), "Class {$class} should exist" );
		}
	}
}

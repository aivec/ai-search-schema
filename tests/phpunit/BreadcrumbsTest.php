<?php
/**
 * Breadcrumbs テスト。
 *
 * パンくずリスト生成機能をテストします。
 *
 * @package Aivec\AiSearchSchema\Tests
 */

require_once __DIR__ . '/bootstrap.php';

/**
 * AVC_AIS_Breadcrumbs クラスのテスト。
 */
class BreadcrumbsTest extends WP_UnitTestCase {

	/**
	 * テスト用投稿ID。
	 *
	 * @var int
	 */
	private $post_id;

	/**
	 * テスト用ページID。
	 *
	 * @var int
	 */
	private $page_id;

	/**
	 * テスト用親ページID。
	 *
	 * @var int
	 */
	private $parent_page_id;

	/**
	 * テスト用カテゴリID。
	 *
	 * @var int
	 */
	private $category_id;

	/**
	 * テスト用親カテゴリID。
	 *
	 * @var int
	 */
	private $parent_category_id;

	/**
	 * テスト前のセットアップ。
	 */
	protected function setUp(): void {
		parent::setUp();
		AVC_AIS_TEST_Env::$options = array();

		// テスト用投稿を作成.
		$this->post_id = self::factory()->post->create(
			array(
				'post_title'  => 'Test Post',
				'post_status' => 'publish',
			)
		);

		// テスト用親ページを作成.
		$this->parent_page_id = self::factory()->post->create(
			array(
				'post_title'  => 'Parent Page',
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);

		// テスト用子ページを作成.
		$this->page_id = self::factory()->post->create(
			array(
				'post_title'  => 'Child Page',
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_parent' => $this->parent_page_id,
			)
		);

		// テスト用親カテゴリを作成.
		$this->parent_category_id = self::factory()->category->create(
			array(
				'name' => 'Parent Category',
				'slug' => 'parent-category',
			)
		);

		// テスト用子カテゴリを作成.
		$this->category_id = self::factory()->category->create(
			array(
				'name'   => 'Child Category',
				'slug'   => 'child-category',
				'parent' => $this->parent_category_id,
			)
		);

		// シングルトンインスタンスをリセット.
		$reflection = new ReflectionClass( 'AVC_AIS_Breadcrumbs' );
		$property   = $reflection->getProperty( 'instance' );
		$property->setAccessible( true );
		$property->setValue( null, null );
	}

	/**
	 * テスト後のクリーンアップ。
	 */
	protected function tearDown(): void {
		// オプションをクリア.
		delete_option( 'avc_ais_options' );

		// フィルターをクリア.
		remove_all_filters( 'avc_ais_show_breadcrumbs' );
		remove_all_filters( 'avc_ais_breadcrumb_items' );
		remove_all_filters( 'avc_ais_breadcrumb_home_label' );

		parent::tearDown();
	}

	// =========================================================================
	// 初期化テスト
	// =========================================================================

	/**
	 * シングルトン初期化をテスト。
	 */
	public function test_init_returns_singleton() {
		$instance1 = AVC_AIS_Breadcrumbs::init();
		$instance2 = AVC_AIS_Breadcrumbs::init();

		$this->assertSame( $instance1, $instance2 );
		$this->assertInstanceOf( AVC_AIS_Breadcrumbs::class, $instance1 );
	}

	// =========================================================================
	// get_items テスト
	// =========================================================================

	/**
	 * 投稿ページのパンくず生成をテスト。
	 */
	public function test_get_items_for_single_post() {
		$breadcrumbs = new AVC_AIS_Breadcrumbs();

		$context = array(
			'type'    => 'post',
			'post_id' => $this->post_id,
			'title'   => 'Test Post',
			'url'     => get_permalink( $this->post_id ),
		);

		$items = $breadcrumbs->get_items( $context );

		$this->assertIsArray( $items );
		$this->assertCount( 2, $items );

		// ホーム.
		$this->assertEquals( 'Home', $items[0]['label'] );
		$this->assertEquals( home_url( '/' ), $items[0]['url'] );

		// 投稿.
		$this->assertEquals( 'Test Post', $items[1]['label'] );
	}

	/**
	 * 階層ページのパンくず生成をテスト。
	 */
	public function test_get_items_for_hierarchical_page() {
		$breadcrumbs = new AVC_AIS_Breadcrumbs();

		$context = array(
			'type'    => 'page',
			'post_id' => $this->page_id,
			'title'   => 'Child Page',
			'url'     => get_permalink( $this->page_id ),
		);

		$items = $breadcrumbs->get_items( $context );

		$this->assertIsArray( $items );
		$this->assertCount( 3, $items );

		// ホーム.
		$this->assertEquals( 'Home', $items[0]['label'] );

		// 親ページ.
		$this->assertEquals( 'Parent Page', $items[1]['label'] );
		$this->assertEquals( get_permalink( $this->parent_page_id ), $items[1]['url'] );

		// 子ページ.
		$this->assertEquals( 'Child Page', $items[2]['label'] );
	}

	/**
	 * カテゴリアーカイブのパンくず生成をテスト。
	 */
	public function test_get_items_for_category_archive() {
		$breadcrumbs = new AVC_AIS_Breadcrumbs();
		$term        = get_term( $this->category_id );

		$context = array(
			'type'  => 'category',
			'term'  => $term,
			'title' => 'Child Category',
			'url'   => get_term_link( $term ),
		);

		$items = $breadcrumbs->get_items( $context );

		$this->assertIsArray( $items );
		$this->assertCount( 3, $items );

		// ホーム.
		$this->assertEquals( 'Home', $items[0]['label'] );

		// 親カテゴリ.
		$this->assertEquals( 'Parent Category', $items[1]['label'] );

		// 子カテゴリ.
		$this->assertEquals( 'Child Category', $items[2]['label'] );
	}

	/**
	 * 検索結果ページのパンくず生成をテスト。
	 */
	public function test_get_items_for_search_page() {
		$breadcrumbs = new AVC_AIS_Breadcrumbs();

		$context = array(
			'type'  => 'search',
			'title' => 'Search Results for: test',
			'url'   => home_url( '/?s=test' ),
		);

		$items = $breadcrumbs->get_items( $context );

		$this->assertIsArray( $items );
		$this->assertCount( 2, $items );
		$this->assertEquals( 'Home', $items[0]['label'] );
		$this->assertEquals( 'Search Results for: test', $items[1]['label'] );
	}

	/**
	 * 著者アーカイブのパンくず生成をテスト。
	 */
	public function test_get_items_for_author_archive() {
		$breadcrumbs = new AVC_AIS_Breadcrumbs();

		$context = array(
			'type'  => 'author',
			'title' => 'Author: John',
			'url'   => home_url( '/author/john/' ),
		);

		$items = $breadcrumbs->get_items( $context );

		$this->assertIsArray( $items );
		$this->assertCount( 2, $items );
		$this->assertEquals( 'Author: John', $items[1]['label'] );
	}

	/**
	 * トップページのパンくず生成をテスト（空配列）。
	 */
	public function test_get_items_for_front_page_returns_empty() {
		$breadcrumbs = new AVC_AIS_Breadcrumbs();

		$context = array(
			'type'  => 'front_page',
			'title' => 'Home',
			'url'   => home_url( '/' ),
		);

		$items = $breadcrumbs->get_items( $context );

		$this->assertIsArray( $items );
		$this->assertEmpty( $items );
	}

	/**
	 * キャッシュ機能をテスト。
	 */
	public function test_get_items_caches_result() {
		$breadcrumbs = new AVC_AIS_Breadcrumbs();

		// 最初の呼び出し（コンテキストなし）.
		$context1 = array(
			'type'    => 'post',
			'post_id' => $this->post_id,
			'title'   => 'Test Post',
			'url'     => get_permalink( $this->post_id ),
		);

		// コンテキストを渡すとキャッシュされない.
		$items1 = $breadcrumbs->get_items( $context1 );
		$items2 = $breadcrumbs->get_items( $context1 );

		// 同じ結果.
		$this->assertEquals( $items1, $items2 );
	}

	// =========================================================================
	// フィルターテスト
	// =========================================================================

	/**
	 * ホームラベルフィルターをテスト。
	 */
	public function test_home_label_filter() {
		add_filter(
			'avc_ais_breadcrumb_home_label',
			function () {
				return 'トップページ';
			}
		);

		$breadcrumbs = new AVC_AIS_Breadcrumbs();

		$context = array(
			'type'    => 'post',
			'post_id' => $this->post_id,
			'title'   => 'Test Post',
			'url'     => get_permalink( $this->post_id ),
		);

		$items = $breadcrumbs->get_items( $context );

		$this->assertEquals( 'トップページ', $items[0]['label'] );
	}

	/**
	 * パンくず項目フィルターをテスト。
	 */
	public function test_breadcrumb_items_filter() {
		add_filter(
			'avc_ais_breadcrumb_items',
			function ( $items ) {
				// カスタム項目を追加.
				array_splice(
					$items,
					1,
					0,
					array(
						array(
							'label' => 'Custom',
							'url'   => 'https://example.com/custom',
						),
					)
				);
				return $items;
			}
		);

		$breadcrumbs = new AVC_AIS_Breadcrumbs();

		$context = array(
			'type'    => 'post',
			'post_id' => $this->post_id,
			'title'   => 'Test Post',
			'url'     => get_permalink( $this->post_id ),
		);

		$items = $breadcrumbs->get_items( $context );

		$this->assertCount( 3, $items );
		$this->assertEquals( 'Custom', $items[1]['label'] );
	}

	// =========================================================================
	// 出力テスト
	// =========================================================================

	/**
	 * output_breadcrumbs のHTML出力をテスト。
	 */
	public function test_output_breadcrumbs_html() {
		// パンくずを有効化.
		update_option(
			'avc_ais_options',
			array(
				'avc_ais_breadcrumbs_html_enabled' => true,
			)
		);

		// シングル投稿ページとしてセットアップ.
		$this->go_to( get_permalink( $this->post_id ) );

		$breadcrumbs = new AVC_AIS_Breadcrumbs();

		ob_start();
		$breadcrumbs->output_breadcrumbs();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<nav class="breadcrumb"', $output );
		$this->assertStringContainsString( 'aria-label="Breadcrumbs"', $output );
		$this->assertStringContainsString( '<ol>', $output );
		$this->assertStringContainsString( '</ol>', $output );
		$this->assertStringContainsString( 'Home', $output );
		$this->assertStringContainsString( 'Test Post', $output );
	}

	/**
	 * 無効時に出力されないことをテスト。
	 */
	public function test_output_breadcrumbs_disabled() {
		// パンくずを無効化.
		update_option(
			'avc_ais_options',
			array(
				'avc_ais_breadcrumbs_html_enabled' => false,
			)
		);

		$this->go_to( get_permalink( $this->post_id ) );

		$breadcrumbs = new AVC_AIS_Breadcrumbs();

		ob_start();
		$breadcrumbs->output_breadcrumbs();
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/**
	 * render_block の出力をテスト。
	 */
	public function test_render_block_outputs_div() {
		$breadcrumbs = new AVC_AIS_Breadcrumbs();

		ob_start();
		$breadcrumbs->render_block();
		$output = ob_get_clean();

		$this->assertEquals( '<div class="ais-breadcrumbs">', $output );
	}

	/**
	 * close_block の出力をテスト。
	 */
	public function test_close_block_outputs_closing_div() {
		$breadcrumbs = new AVC_AIS_Breadcrumbs();

		ob_start();
		$breadcrumbs->close_block();
		$output = ob_get_clean();

		$this->assertEquals( '</div>', $output );
	}

	// =========================================================================
	// should_render テスト
	// =========================================================================

	/**
	 * フィルターで無効化した場合をテスト。
	 */
	public function test_should_render_disabled_by_filter() {
		add_filter( 'avc_ais_show_breadcrumbs', '__return_false' );

		update_option(
			'avc_ais_options',
			array(
				'avc_ais_breadcrumbs_html_enabled' => true,
			)
		);

		$this->go_to( get_permalink( $this->post_id ) );

		$breadcrumbs = new AVC_AIS_Breadcrumbs();

		ob_start();
		$breadcrumbs->output_breadcrumbs();
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/**
	 * レガシーオプション enable_breadcrumbs をテスト。
	 */
	public function test_should_render_with_legacy_option() {
		update_option(
			'avc_ais_options',
			array(
				'enable_breadcrumbs' => true,
			)
		);

		$this->go_to( get_permalink( $this->post_id ) );

		$breadcrumbs = new AVC_AIS_Breadcrumbs();

		ob_start();
		$breadcrumbs->output_breadcrumbs();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<nav class="breadcrumb"', $output );
	}

	// =========================================================================
	// エッジケーステスト
	// =========================================================================

	/**
	 * 空ラベルの項目がスキップされることをテスト。
	 */
	public function test_output_skips_empty_labels() {
		add_filter(
			'avc_ais_breadcrumb_items',
			function ( $items ) {
				// 空ラベルの項目を追加.
				$items[] = array(
					'label' => '',
					'url'   => 'https://example.com',
				);
				return $items;
			}
		);

		update_option(
			'avc_ais_options',
			array(
				'avc_ais_breadcrumbs_html_enabled' => true,
			)
		);

		$this->go_to( get_permalink( $this->post_id ) );

		$breadcrumbs = new AVC_AIS_Breadcrumbs();

		ob_start();
		$breadcrumbs->output_breadcrumbs();
		$output = ob_get_clean();

		// 空ラベルの項目は出力されない.
		$this->assertStringNotContainsString( 'href="https://example.com"', $output );
	}

	/**
	 * 代替キー（name/item）での項目処理をテスト。
	 */
	public function test_output_handles_alternative_keys() {
		add_filter(
			'avc_ais_breadcrumb_items',
			function () {
				return array(
					array(
						'name' => 'Alt Home',
						'item' => home_url( '/' ),
					),
					array(
						'name' => 'Alt Page',
						'item' => 'https://example.com/page',
					),
				);
			}
		);

		update_option(
			'avc_ais_options',
			array(
				'avc_ais_breadcrumbs_html_enabled' => true,
			)
		);

		$this->go_to( get_permalink( $this->post_id ) );

		$breadcrumbs = new AVC_AIS_Breadcrumbs();

		ob_start();
		$breadcrumbs->output_breadcrumbs();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Alt Home', $output );
		$this->assertStringContainsString( 'Alt Page', $output );
	}

	/**
	 * タクソノミーアーカイブ（非カテゴリ）をテスト。
	 */
	public function test_get_items_for_taxonomy_archive() {
		// タグを作成.
		$tag_id = self::factory()->tag->create(
			array(
				'name' => 'Test Tag',
				'slug' => 'test-tag',
			)
		);
		$tag    = get_term( $tag_id, 'post_tag' );

		$breadcrumbs = new AVC_AIS_Breadcrumbs();

		$context = array(
			'type'  => 'tag',
			'term'  => $tag,
			'title' => 'Test Tag',
			'url'   => get_term_link( $tag ),
		);

		$items = $breadcrumbs->get_items( $context );

		$this->assertIsArray( $items );
		$this->assertCount( 2, $items );
		$this->assertEquals( 'Test Tag', $items[1]['label'] );
	}

	/**
	 * ブログホームのパンくず生成をテスト。
	 */
	public function test_get_items_for_blog_home() {
		$breadcrumbs = new AVC_AIS_Breadcrumbs();

		$context = array(
			'type'  => 'blog_home',
			'title' => 'Blog',
			'url'   => home_url( '/blog/' ),
		);

		$items = $breadcrumbs->get_items( $context );

		$this->assertIsArray( $items );
		$this->assertCount( 2, $items );
		$this->assertEquals( 'Blog', $items[1]['label'] );
	}

	/**
	 * 無効なオプション形式をテスト。
	 */
	public function test_should_render_with_invalid_options() {
		// 無効なオプション（文字列）.
		update_option( 'avc_ais_options', 'invalid' );

		$this->go_to( get_permalink( $this->post_id ) );

		$breadcrumbs = new AVC_AIS_Breadcrumbs();

		ob_start();
		$breadcrumbs->output_breadcrumbs();
		$output = ob_get_clean();

		// エラーなく処理される（無効時は出力なし）.
		$this->assertEmpty( $output );
	}
}

<?php
/**
 * llms.txt の配信時に公開していない投稿の行を隠す処理のテスト。
 *
 * @package Aivec\AiSearchSchema\Tests
 */

require_once __DIR__ . '/bootstrap.php';

/**
 * llms.txt の配信内容のテストクラス。
 */
class LlmsTxtServedContentTest extends WP_UnitTestCase {

	/**
	 * 管理者の ID。
	 *
	 * @var int
	 */
	private $admin_id;

	/**
	 * テスト前のセットアップ。
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$ref = new ReflectionProperty( AVC_AIS_Llms_Txt::class, 'instance' );
		avc_ais_test_make_accessible( $ref );
		$ref->setValue( null, null );

		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_id );
		$this->set_permalink_structure( '/%postname%/' );

		// 投稿の更新などで動くフックを登録する。
		AVC_AIS_Llms_Txt::init();

		delete_option( AVC_AIS_Llms_Txt::OPTION_NAME );
		delete_option( AVC_AIS_Llms_Txt::OPTION_URLS_REPAIRED );
		delete_option( AVC_AIS_Llms_Txt::OPTION_SCRUBBED );
		delete_option( AVC_AIS_Llms_Txt::OPTION_RULE_RESTORED );
	}

	/**
	 * テスト後の片付け。
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		delete_option( AVC_AIS_Llms_Txt::OPTION_NAME );
		delete_option( AVC_AIS_Llms_Txt::OPTION_URLS_REPAIRED );
		delete_option( AVC_AIS_Llms_Txt::OPTION_SCRUBBED );
		delete_option( AVC_AIS_Llms_Txt::OPTION_RULE_RESTORED );
		$this->set_permalink_structure( '' );

		parent::tearDown();
	}

	/**
	 * 未ログインの訪問者への配信内容を返す。
	 *
	 * @return string 配信内容.
	 */
	private function served() {
		$user_id = get_current_user_id();
		wp_set_current_user( 0 );
		$served = AVC_AIS_Llms_Txt::init()->get_served_content();
		wp_set_current_user( $user_id );

		return $served;
	}

	/**
	 * 投稿を作成し、その行を保存する。
	 *
	 * @param array  $args  投稿の項目.
	 * @param string $after 行のあとに続ける内容.
	 * @return int 投稿 ID.
	 */
	private function create_post_with_entry( array $args = array(), $after = "\n# 見出し" ) {
		$args    = array_merge(
			array(
				'post_title' => '対象の記事',
				'post_name'  => 'target',
			),
			$args
		);
		$post_id = self::factory()->post->create( $args );
		update_option(
			AVC_AIS_Llms_Txt::OPTION_NAME,
			'- [' . $args['post_title'] . '](' . get_permalink( $post_id ) . '): 手書きの説明' . $after
		);

		return $post_id;
	}

	/**
	 * 投稿を隠す変更を返す。
	 *
	 * @return array<string,array<int,array>>
	 */
	public function provide_hiding_changes() {
		return array(
			'password' => array( array( 'post_password' => 'hunter2' ) ),
			'private'  => array( array( 'post_status' => 'private' ) ),
			'draft'    => array( array( 'post_status' => 'draft' ) ),
			'pending'  => array( array( 'post_status' => 'pending' ) ),
			'trash'    => array( array( 'post_status' => 'trash' ) ),
		);
	}

	/**
	 * 投稿を隠すと行は配信されないが、保存済みの内容には残ること。
	 *
	 * @dataProvider provide_hiding_changes
	 *
	 * @param array $change 投稿に加える変更.
	 * @return void
	 */
	public function test_hiding_a_post_hides_entry_but_keeps_saved_text( array $change ) {
		$post_id = $this->create_post_with_entry();
		$saved   = get_option( AVC_AIS_Llms_Txt::OPTION_NAME );

		wp_update_post( array_merge( array( 'ID' => $post_id ), $change ) );

		$this->assertSame( '# 見出し', $this->served() );
		$this->assertSame( $saved, get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
		$this->assertSame( array( '対象の記事' ), AVC_AIS_Llms_Txt::init()->get_hidden_entry_titles() );
	}

	/**
	 * プラグインが追加した公開していないステータスの投稿も隠すこと。
	 *
	 * @return void
	 */
	public function test_custom_non_public_status_is_hidden() {
		register_post_status( 'archived', array( 'public' => false ) );

		$post_id = $this->create_post_with_entry();
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'archived',
			)
		);

		$this->assertSame( '# 見出し', $this->served() );
	}

	/**
	 * プラグインが追加した公開のステータスの投稿は隠さないこと。
	 *
	 * @return void
	 */
	public function test_custom_public_status_is_served() {
		register_post_status( 'featured', array( 'public' => true ) );

		$post_id = $this->create_post_with_entry();
		$saved   = get_option( AVC_AIS_Llms_Txt::OPTION_NAME );
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'featured',
			)
		);

		$this->assertSame( $saved, $this->served() );
	}

	/**
	 * パーマリンクの設定にかかわらず、?p=ID の URL の行を判定できること。
	 *
	 * @return void
	 */
	public function test_query_form_url_is_matched_on_pretty_permalink_sites() {
		$post_id = self::factory()->post->create( array( 'post_title' => '元のタイトル' ) );
		$saved   = '- [表のタイトル](' . home_url( "/?p={$post_id}" ) . "): 秘密\n- [固定](" . home_url( "/?page_id={$post_id}" ) . '): 秘密2';
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, $saved );
		$this->assertSame( $saved, $this->served(), '前提: 公開中は配信する' );

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'draft',
			)
		);

		$this->assertSame( '', $this->served() );
	}

	/**
	 * 壊れた子ページの URL を親ページと取り違えないこと。
	 *
	 * 以前の保存処理で /news/あ/ は /news// になる。
	 *
	 * @return void
	 */
	public function test_broken_child_url_is_not_matched_to_parent() {
		$parent_id = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'ニュース',
				'post_name'  => 'news',
			)
		);
		self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => '非公開の子ページ',
				'post_name'   => 'あ',
				'post_parent' => $parent_id,
				'post_status' => 'private',
			)
		);
		$parent_line = '- [ニュース](' . get_permalink( $parent_id ) . '): 公開の抜粋';
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, '- [非公開の子ページ](' . home_url( '/news//' ) . "): 秘密\n{$parent_line}" );

		$this->assertSame( $parent_line, $this->served() );

		wp_update_post(
			array(
				'ID'        => $parent_id,
				'post_name' => 'renamed',
			)
		);
		$this->assertStringContainsString( '- [非公開の子ページ](' . home_url( '/news//' ) . '): 秘密', get_option( AVC_AIS_Llms_Txt::OPTION_NAME ), '親の変更で書き換えない' );
	}

	/**
	 * 説明を省いた行や字下げした行も、投稿を隠せば配信しないこと。
	 *
	 * @dataProvider provide_list_forms
	 *
	 * @param string $format 行の書式（%s は URL）.
	 * @return void
	 */
	public function test_other_list_forms_are_hidden( $format ) {
		$post_id = self::factory()->post->create(
			array(
				'post_title' => '元のタイトル',
				'post_name'  => 'hidden',
			)
		);
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, sprintf( $format, get_permalink( $post_id ) ) . "\n# 見出し" );

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'private',
			)
		);

		$this->assertSame( '# 見出し', $this->served() );
		$this->assertSame( array( '表のタイトル' ), AVC_AIS_Llms_Txt::init()->get_hidden_entry_titles() );
	}

	/**
	 * 一覧の行の書式を返す。
	 *
	 * @return array<string,array<int,string>>
	 */
	public function provide_list_forms() {
		return array(
			'no notes'      => array( '- [表のタイトル](%s)' ),
			'indented'      => array( '  - [表のタイトル](%s): 説明' ),
			'asterisk'      => array( '* [表のタイトル](%s): 説明' ),
			'other notes'   => array( '- [表のタイトル](%s) の説明' ),
			'japanese note' => array( '- [表のタイトル](%s)の説明' ),
			'ordered'       => array( '1. [表のタイトル](%s): 説明' ),
			'extra spaces'  => array( '-   [表のタイトル](%s): 説明' ),
			'link title'    => array( '- [表のタイトル](%s "補足"): 説明' ),
		);
	}

	/**
	 * タイトルのない投稿の行も、投稿を隠せば配信しないこと。
	 *
	 * 自動生成では「- [](URL): 抜粋」になる。
	 *
	 * @return void
	 */
	public function test_untitled_post_entry_is_hidden() {
		$post_id = self::factory()->post->create(
			array(
				'post_title' => '',
				'post_name'  => 'untitled',
			)
		);
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, '- [](' . get_permalink( $post_id ) . "): 秘密\n# 見出し" );

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'private',
			)
		);

		$this->assertSame( '# 見出し', $this->served() );
	}

	/**
	 * タイトルのない行は、URL で見つからなければ隠さないこと。
	 *
	 * 空のタイトルで投稿を探すと条件が無視され、すべての投稿が対象になる。
	 *
	 * @return void
	 */
	public function test_untitled_entry_without_a_post_is_kept() {
		self::factory()->post->create_many( AVC_AIS_Llms_Txt::TITLE_QUERY_LIMIT + 1, array( 'post_status' => 'draft' ) );
		$saved = '- [](' . home_url( '/blog/' ) . '): 一覧';
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, $saved );

		$this->assertSame( $saved, $this->served() );
	}

	/**
	 * 同じタイトルの公開中の投稿が多くても、投稿に対応しない行を隠さないこと。
	 *
	 * @return void
	 */
	public function test_many_public_posts_with_same_title_do_not_hide_entry() {
		self::factory()->post->create_many( AVC_AIS_Llms_Txt::TITLE_QUERY_LIMIT + 1, array( 'post_title' => 'お知らせ' ) );
		$saved = '- [お知らせ](' . home_url( '/category/news/' ) . '): 一覧';
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, $saved );

		$this->assertSame( $saved, $this->served() );
	}

	/**
	 * スラッグが長い投稿をゴミ箱に入れても行を隠せること。
	 *
	 * WordPress はスラッグを 191 バイトに切り詰めてから __trashed を付ける。
	 *
	 * @return void
	 */
	public function test_trashed_post_with_long_slug_is_hidden() {
		$post_id = self::factory()->post->create(
			array(
				'post_title' => '元のタイトル',
				'post_name'  => str_repeat( 'あ', 22 ),
			)
		);
		$this->assertGreaterThan( 191, strlen( get_post( $post_id )->post_name ), '前提: スラッグが長い' );
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, '- [表のタイトル](' . get_permalink( $post_id ) . "): 秘密\n# 見出し" );

		wp_trash_post( $post_id );

		$this->assertSame( '# 見出し', $this->served() );
	}

	/**
	 * ドメインに日本語を含むサイトでも、自分のサイトの行として判定すること。
	 *
	 * @return void
	 */
	public function test_non_ascii_domain_is_treated_as_this_site() {
		$home = 'http://日本語.example';
		add_filter(
			'home_url',
			static function ( $url ) use ( $home ) {
				return str_replace( 'http://example.org', $home, $url );
			}
		);

		$post_id = self::factory()->post->create(
			array(
				'post_title'  => '元のタイトル',
				'post_name'   => 'hidden',
				'post_status' => 'private',
			)
		);
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, "- [表のタイトル]({$home}/hidden/): 秘密\n# 見出し" );
		$this->assertNotEmpty( $post_id, '前提: 投稿がある' );

		$this->assertSame( '# 見出し', $this->served() );
	}

	/**
	 * 親ページのスラッグを変えると、子ページの行の URL も書き換えること。
	 *
	 * @return void
	 */
	public function test_changing_parent_slug_updates_child_entry_url() {
		$parent_id = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => '親',
				'post_name'  => 'parent',
			)
		);
		$child_id  = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => '元のタイトル',
				'post_name'   => 'child',
				'post_parent' => $parent_id,
			)
		);
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, '- [表のタイトル](' . get_permalink( $child_id ) . '): 秘密' );

		wp_update_post(
			array(
				'ID'        => $parent_id,
				'post_name' => 'renamed',
			)
		);

		$url = get_permalink( $child_id );
		$this->assertStringContainsString( '/renamed/child', $url, '前提: 子ページの URL が変わる' );
		$this->assertSame( "- [表のタイトル]({$url}): 秘密", get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );

		wp_update_post(
			array(
				'ID'          => $child_id,
				'post_status' => 'private',
			)
		);

		$this->assertSame( '', $this->served() );
	}

	/**
	 * 大文字のパーセントエンコードや別のスキームで書いた子ページの行も書き換えること。
	 *
	 * @return void
	 */
	public function test_changing_parent_slug_updates_child_entry_with_different_url_form() {
		$parent_id = self::factory()->post->create(
			array(
				'post_type' => 'page',
				'post_name' => 'あ',
			)
		);
		$child_id  = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_name'   => 'child',
				'post_parent' => $parent_id,
			)
		);
		$url       = preg_replace_callback(
			'/%[0-9a-f]{2}/',
			static function ( $byte ) {
				return strtoupper( $byte[0] );
			},
			set_url_scheme( get_permalink( $child_id ), 'https' )
		);
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, "- [子]({$url}): 説明" );

		wp_update_post(
			array(
				'ID'        => $parent_id,
				'post_name' => 'renamed',
			)
		);

		$this->assertSame( '- [子](' . get_permalink( $child_id ) . '): 説明', get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
	}

	/**
	 * 日本語のままで書いた子ページの行も、親ページのスラッグの変更で書き換えること。
	 *
	 * @return void
	 */
	public function test_changing_parent_slug_updates_decoded_child_entry() {
		$parent_id = self::factory()->post->create(
			array(
				'post_type' => 'page',
				'post_name' => 'あ',
			)
		);
		$child_id  = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_name'   => 'い',
				'post_parent' => $parent_id,
			)
		);
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, '- [子](' . rawurldecode( get_permalink( $child_id ) ) . '): 説明' );

		wp_update_post(
			array(
				'ID'        => $parent_id,
				'post_name' => 'renamed',
			)
		);

		$this->assertSame( '- [子](' . get_permalink( $child_id ) . '): 説明', get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
	}

	/**
	 * 親ページのスラッグを変えても、子ページの URL の残りの部分の表記を変えないこと。
	 *
	 * @return void
	 */
	public function test_changing_parent_slug_keeps_rest_of_child_url() {
		$parent_id = self::factory()->post->create(
			array(
				'post_type' => 'page',
				'post_name' => 'parent',
			)
		);
		$old       = get_permalink( $parent_id );
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, "- [資料]({$old}Report.PDF?id=AbC%26x): 説明" );

		wp_update_post(
			array(
				'ID'        => $parent_id,
				'post_name' => 'renamed',
			)
		);

		$new = get_permalink( $parent_id );
		$this->assertSame( "- [資料]({$new}Report.PDF?id=AbC%26x): 説明", get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
	}

	/**
	 * 投稿タイプを変えて一覧から外れた投稿でも、判定結果を無効にすること。
	 *
	 * @return void
	 */
	public function test_changing_post_type_clears_cache() {
		register_post_type( 'ais_other' );

		$post_id = self::factory()->post->create();
		update_option( AVC_AIS_Llms_Txt::OPTION_CACHE_GENERATION, 'before' );

		wp_update_post(
			array(
				'ID'        => $post_id,
				'post_type' => 'ais_other',
			)
		);

		$this->assertNotSame( 'before', get_option( AVC_AIS_Llms_Txt::OPTION_CACHE_GENERATION ) );
	}

	/**
	 * 一覧に載らない投稿タイプの更新では、判定結果を無効にしないこと。
	 *
	 * @return void
	 */
	public function test_other_post_types_do_not_clear_cache() {
		register_post_type( 'ais_other' );

		$other = self::factory()->post->create( array( 'post_type' => 'ais_other' ) );
		update_option( AVC_AIS_Llms_Txt::OPTION_CACHE_GENERATION, 'before' );

		wp_update_post(
			array(
				'ID'         => $other,
				'post_title' => '更新',
			)
		);
		$this->assertSame( 'before', get_option( AVC_AIS_Llms_Txt::OPTION_CACHE_GENERATION ) );

		wp_delete_post( $other, true );
		$this->assertSame( 'before', get_option( AVC_AIS_Llms_Txt::OPTION_CACHE_GENERATION ) );

		$post_id = self::factory()->post->create();
		$this->assertNotSame( 'before', get_option( AVC_AIS_Llms_Txt::OPTION_CACHE_GENERATION ), '前提: 投稿では無効にする' );
		$this->assertNotEmpty( $post_id, '前提: 投稿がある' );
	}

	/**
	 * 通常の投稿のスラッグを変えても、下の階層に見える別の行を書き換えないこと。
	 *
	 * @return void
	 */
	public function test_changing_post_slug_keeps_entries_below_its_url() {
		$post_id = self::factory()->post->create( array( 'post_name' => 'news' ) );
		$other   = '- [過去の記事](' . trailingslashit( get_permalink( $post_id ) ) . 'archive/): 一覧';
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, $other );

		wp_update_post(
			array(
				'ID'        => $post_id,
				'post_name' => 'media',
			)
		);

		$this->assertSame( $other, get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
	}

	/**
	 * リンクのタイトルがある行も、スラッグを変えると URL を書き換えること。
	 *
	 * @return void
	 */
	public function test_changing_slug_updates_entry_with_link_title() {
		$post_id = self::factory()->post->create( array( 'post_name' => 'before' ) );
		$old     = get_permalink( $post_id );
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, "- [記事]({$old} \"補足\"): 説明" );

		wp_update_post(
			array(
				'ID'        => $post_id,
				'post_name' => 'after',
			)
		);

		$new = get_permalink( $post_id );
		$this->assertSame( "- [記事]({$new} \"補足\"): 説明", get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
	}

	/**
	 * 判定の途中で投稿が変わると、そのあとに保存された古い結果を使わないこと。
	 *
	 * @return void
	 */
	public function test_stale_cache_written_after_a_change_is_not_used() {
		$post_id = $this->create_post_with_entry();
		$saved   = get_option( AVC_AIS_Llms_Txt::OPTION_NAME );
		$this->assertSame( $saved, $this->served(), '前提: 判定して結果を保存する' );
		$stale = get_transient( AVC_AIS_Llms_Txt::HIDDEN_LINES_CACHE );

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'private',
			)
		);
		set_transient( AVC_AIS_Llms_Txt::HIDDEN_LINES_CACHE, $stale, MINUTE_IN_SECONDS );

		$this->assertSame( '# 見出し', $this->served() );
	}

	/**
	 * スラッグを変えても、説明のない行や字下げした行の書式を変えないこと。
	 *
	 * @return void
	 */
	public function test_changing_slug_keeps_line_format() {
		$post_id = self::factory()->post->create(
			array(
				'post_title' => '記事',
				'post_name'  => 'before',
			)
		);
		$old     = get_permalink( $post_id );
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, "  - [記事]({$old})\n* [記事]({$old}) の説明" );

		wp_update_post(
			array(
				'ID'        => $post_id,
				'post_name' => 'after',
			)
		);

		$new = get_permalink( $post_id );
		$this->assertSame( "  - [記事]({$new})\n* [記事]({$new}) の説明", get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
	}

	/**
	 * 判定結果を再利用してクエリを減らし、投稿が変わったら判定し直すこと。
	 *
	 * @return void
	 */
	public function test_hidden_lines_are_cached_until_a_post_changes() {
		global $wpdb;

		$post_id = $this->create_post_with_entry( array(), "\n- [ブログ](" . home_url( '/blog/' ) . '): 一覧' );
		$saved   = get_option( AVC_AIS_Llms_Txt::OPTION_NAME );

		$this->assertSame( $saved, $this->served(), '前提: 判定して結果を保存する' );

		$before = $wpdb->num_queries;
		$this->assertSame( $saved, $this->served() );
		$this->assertLessThanOrEqual( 1, $wpdb->num_queries - $before, '二度目は投稿を取得しない' );

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'private',
			)
		);

		$this->assertStringNotContainsString( '手書きの説明', $this->served(), '投稿が変わったら判定し直す' );
	}

	/**
	 * パスワードの変更と削除でも判定し直し、内容が変わったら保存した結果を使わないこと。
	 *
	 * @return void
	 */
	public function test_hidden_lines_cache_follows_passwords_and_content() {
		$post_id = $this->create_post_with_entry();
		$this->assertStringContainsString( '手書きの説明', $this->served(), '前提: 公開中は配信する' );

		wp_update_post(
			array(
				'ID'            => $post_id,
				'post_password' => 'hunter2',
			)
		);
		$this->assertSame( '# 見出し', $this->served() );

		// 行の順序が変わると、保存した行番号は使えない。
		$line = '- [対象の記事](' . home_url( '/target/' ) . '): 手書きの説明';
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, "# 見出し\n{$line}" );
		$this->assertSame( '# 見出し', $this->served() );
	}

	/**
	 * 投稿を公開し直すと、手書きの説明がそのまま配信に戻ること。
	 *
	 * @return void
	 */
	public function test_republishing_restores_hand_written_entry() {
		$post_id = $this->create_post_with_entry();
		$saved   = get_option( AVC_AIS_Llms_Txt::OPTION_NAME );

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'draft',
			)
		);
		$this->assertSame( '# 見出し', $this->served(), '前提: 下書きの間は隠れる' );

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			)
		);

		$this->assertSame( $saved, $this->served() );
		$this->assertSame( array(), AVC_AIS_Llms_Txt::init()->get_hidden_entry_titles() );
	}

	/**
	 * ゴミ箱に入れて戻しても行が残り、公開すれば配信に戻ること。
	 *
	 * @return void
	 */
	public function test_trash_and_restore_keeps_entry() {
		$post_id = $this->create_post_with_entry();
		$saved   = get_option( AVC_AIS_Llms_Txt::OPTION_NAME );

		wp_trash_post( $post_id );
		$this->assertSame( '# 見出し', $this->served(), '前提: ゴミ箱の間は隠れる' );
		$this->assertSame( $saved, get_option( AVC_AIS_Llms_Txt::OPTION_NAME ), 'スラッグの変更で書き換えない' );

		wp_untrash_post( $post_id );
		wp_publish_post( $post_id );

		$this->assertSame( $saved, $this->served() );
	}

	/**
	 * 削除した投稿の行は保存済みの内容からも取り除くこと。
	 *
	 * @dataProvider provide_deletions
	 *
	 * @param callable $delete 投稿を削除する処理.
	 * @return void
	 */
	public function test_deleting_a_post_removes_entry( $delete ) {
		$post_id = $this->create_post_with_entry();

		$delete( $post_id );

		$this->assertSame( '# 見出し', get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
	}

	/**
	 * 投稿を削除する処理を返す。
	 *
	 * @return array<string,array<int,callable>>
	 */
	public function provide_deletions() {
		return array(
			'force delete'    => array(
				static function ( $post_id ) {
					wp_delete_post( $post_id, true );
				},
			),
			'trash and empty' => array(
				static function ( $post_id ) {
					wp_trash_post( $post_id );
					wp_delete_post( $post_id, true );
				},
			),
			'draft and empty' => array(
				static function ( $post_id ) {
					wp_update_post(
						array(
							'ID'          => $post_id,
							'post_status' => 'draft',
						)
					);
					wp_delete_post( $post_id, true );
				},
			),
		);
	}

	/**
	 * 公開中の投稿と同じスラッグの下書きを削除しても、公開中の投稿の行は残ること。
	 *
	 * @return void
	 */
	public function test_deleting_a_draft_with_same_slug_keeps_public_entry() {
		$this->create_post_with_entry();
		$saved = get_option( AVC_AIS_Llms_Txt::OPTION_NAME );
		$draft = self::factory()->post->create(
			array(
				'post_title'  => '別の下書き',
				'post_name'   => 'target',
				'post_status' => 'draft',
			)
		);
		$this->assertSame( 'target', get_post( $draft )->post_name, '前提: 下書きはスラッグが重複しうる' );

		wp_delete_post( $draft, true );

		$this->assertSame( $saved, get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
	}

	/**
	 * 非公開の投稿と同じスラッグの下書きを削除しても、非公開の投稿の行は残ること。
	 *
	 * 公開し直したときに手書きの説明が失われないようにする。
	 *
	 * @return void
	 */
	public function test_deleting_a_draft_with_same_slug_keeps_private_entry() {
		$post_id = $this->create_post_with_entry();
		$saved   = get_option( AVC_AIS_Llms_Txt::OPTION_NAME );
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'private',
			)
		);
		$draft = self::factory()->post->create(
			array(
				'post_title'  => '別の下書き',
				'post_name'   => 'target',
				'post_status' => 'draft',
			)
		);

		wp_delete_post( $draft, true );

		$this->assertSame( $saved, get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
	}

	/**
	 * 同じタイトルの投稿が上限を超えると判断できないので、古い形式の行を消さないこと。
	 *
	 * @return void
	 */
	public function test_deleting_a_post_keeps_legacy_entries_at_query_limit() {
		$ids = array();
		for ( $i = 0; $i < AVC_AIS_Llms_Txt::TITLE_QUERY_LIMIT + 1; $i++ ) {
			$ids[] = self::factory()->post->create(
				array(
					'post_title' => '無題',
					'post_name'  => '無題' . $i,
				)
			);
		}
		$saved = '- [無題](' . home_url( '/' ) . '/): 別の記事の抜粋';
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, $saved );

		wp_delete_post( $ids[0], true );

		$this->assertSame( $saved, get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
	}

	/**
	 * 下書きを作成・編集しても、公開中の投稿とトップページの行は隠れないこと。
	 *
	 * スラッグが空の下書きの URL はトップページと、公開中の投稿と同じスラッグの下書きの URL は
	 * その投稿と一致してしまう。
	 *
	 * @return void
	 */
	public function test_drafts_do_not_hide_public_entries() {
		$published = self::factory()->post->create(
			array(
				'post_title' => 'Shared',
				'post_name'  => 'shared-slug',
			)
		);
		$saved     = '- [Home](' . home_url( '/' ) . "): HOMEEXCERPT\n- [Shared](" . get_permalink( $published ) . '): SHAREDEXCERPT';
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, $saved );

		$empty_slug = self::factory()->post->create(
			array(
				'post_title'  => 'Home',
				'post_status' => 'draft',
				'post_name'   => '',
			)
		);
		$same_slug  = self::factory()->post->create(
			array(
				'post_title'  => 'Shared',
				'post_status' => 'draft',
				'post_name'   => 'shared-slug',
			)
		);
		foreach ( array( $empty_slug, $same_slug ) as $draft ) {
			wp_update_post(
				array(
					'ID'           => $draft,
					'post_content' => 'Edited draft',
				)
			);
		}

		$this->assertSame( $saved, $this->served() );
		$this->assertSame( $saved, get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
	}

	/**
	 * 未ログインの訪問者への配信でも、公開時の URL で照合できること。
	 *
	 * 権限のないユーザーには get_permalink() が ?p=ID 形式を返す。
	 *
	 * @return void
	 */
	public function test_private_post_is_matched_by_pretty_url_for_anonymous_visitor() {
		self::factory()->post->create(
			array(
				'post_title'  => '別のタイトル',
				'post_name'   => 'hidden-entry',
				'post_status' => 'private',
			)
		);
		update_option(
			AVC_AIS_Llms_Txt::OPTION_NAME,
			'- [古いタイトル](' . home_url( '/hidden-entry/' ) . "): HIDDENEXCERPT\n- [Other](" . home_url( '/other/' ) . '): Other excerpt'
		);

		$served = $this->served();

		$this->assertStringNotContainsString( 'HIDDENEXCERPT', $served );
		$this->assertStringContainsString( 'Other excerpt', $served );
	}

	/**
	 * パーマリンクが基本設定でも、ID の URL で照合できること。
	 *
	 * @return void
	 */
	public function test_plain_permalink_entries_are_matched_by_id() {
		$this->set_permalink_structure( '' );

		$post_id = self::factory()->post->create( array( 'post_title' => '基本設定の記事' ) );
		$saved   = '- [古いタイトル](' . get_permalink( $post_id ) . '): 秘密';
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, $saved );
		$this->assertSame( $saved, $this->served(), '前提: 公開中は配信する' );

		wp_update_post(
			array(
				'ID'            => $post_id,
				'post_password' => 'hunter2',
			)
		);

		$this->assertSame( '', $this->served() );
	}

	/**
	 * URL の表記の違いがあっても照合できること。
	 *
	 * @dataProvider provide_url_variants
	 *
	 * @param callable $variant 投稿の URL を変える処理.
	 * @return void
	 */
	public function test_entry_url_variants_are_matched( $variant ) {
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => '元のタイトル',
				'post_name'   => 'あい',
				'post_status' => 'publish',
			)
		);
		$url     = get_permalink( $post_id );
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, '- [表のタイトル](' . $variant( $url ) . '): 秘密' );

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'private',
			)
		);

		$this->assertSame( '', $this->served() );
	}

	/**
	 * URL の表記を変える処理を返す。
	 *
	 * @return array<string,array<int,callable>>
	 */
	public function provide_url_variants() {
		return array(
			'upper case percent bytes' => array(
				static function ( $url ) {
					return preg_replace_callback(
						'/%[0-9a-f]{2}/',
						static function ( $byte ) {
							return strtoupper( $byte[0] );
						},
						$url
					);
				},
			),
			'no trailing slash'        => array( 'untrailingslashit' ),
			'decoded'                  => array( 'rawurldecode' ),
			'other scheme'             => array(
				static function ( $url ) {
					return set_url_scheme( $url, 'https' );
				},
			),
		);
	}

	/**
	 * 別の行の抜粋に同じ URL のリンクがあっても、その行は隠れないこと。
	 *
	 * @return void
	 */
	public function test_link_in_excerpt_of_other_entry_does_not_hide_it() {
		$post_id = self::factory()->post->create(
			array(
				'post_title' => '隠す記事',
				'post_name'  => 'hidden',
			)
		);
		$other   = self::factory()->post->create(
			array(
				'post_title' => '残す記事',
				'post_name'  => 'kept',
			)
		);
		$url     = get_permalink( $post_id );
		$kept    = '- [残す記事](' . get_permalink( $other ) . "): 詳しくは [こちら]({$url}): を参照";
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, "- [隠す記事]({$url}): 秘密\n{$kept}\n本文の [リンク]({$url}): はそのまま" );

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'private',
			)
		);

		$this->assertSame( "{$kept}\n本文の [リンク]({$url}): はそのまま", $this->served() );
	}

	/**
	 * 外部サイトへのリンクとメールアドレスの行は、同じタイトルの投稿を隠しても残ること。
	 *
	 * @return void
	 */
	public function test_external_entries_are_never_hidden() {
		self::factory()->post->create(
			array(
				'post_title'  => '同じタイトル',
				'post_status' => 'private',
			)
		);
		$saved = "- [同じタイトル](https://other.example/docs/): 外部\n- [同じタイトル](mailto:a@example.com): メール";
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, $saved );

		$this->assertSame( $saved, $this->served() );
	}

	/**
	 * 投稿に対応しない別のポートの URL は、同じタイトルの投稿があっても残すこと。
	 *
	 * @return void
	 */
	public function test_entries_on_another_port_without_a_post_are_kept() {
		self::factory()->post->create(
			array(
				'post_title'  => '同じタイトル',
				'post_status' => 'private',
			)
		);
		$host = WP_TESTS_DOMAIN;
		foreach ( array( "http://{$host}:8080/docs/", "https://{$host}:80/docs/", "http://{$host}:443/docs/" ) as $url ) {
			$saved = '- [同じタイトル](' . $url . '): 別のポート';
			update_option( AVC_AIS_Llms_Txt::OPTION_NAME, $saved );

			$this->assertSame( $saved, $this->served(), $url );
		}
	}

	/**
	 * 標準のポートを明示した URL は、このサイトの行として判定すること。
	 *
	 * @return void
	 */
	public function test_entries_with_a_default_port_are_site_entries() {
		self::factory()->post->create(
			array(
				'post_title'  => '同じタイトル',
				'post_status' => 'private',
			)
		);
		$host = WP_TESTS_DOMAIN;
		foreach ( array( "http://{$host}:80/docs/", "https://{$host}:443/docs/", "//{$host}:443/docs/", "//{$host}:80/docs/" ) as $url ) {
			update_option( AVC_AIS_Llms_Txt::OPTION_NAME, '- [同じタイトル](' . $url . '): 標準のポート' );

			$this->assertSame( '', $this->served(), $url );
		}
	}

	/**
	 * サブディレクトリのサイトでは、外のパスの行を隠さず、中のパスの行は隠すこと。
	 *
	 * @return void
	 */
	public function test_site_in_a_subdirectory_only_hides_its_own_paths() {
		$host = WP_TESTS_DOMAIN;
		update_option( 'home', "http://{$host}/blog" );
		self::factory()->post->create(
			array(
				'post_title'  => '同じタイトル',
				'post_status' => 'private',
			)
		);

		$outside = array(
			"http://{$host}",
			"http://{$host}/",
			"http://{$host}/shop/docs/",
			"http://{$host}/blogging/docs/",
			"//{$host}/shop/docs/",
			"http://www.{$host}/shop/docs/",
			'/shop/docs/',
			"http://{$host}/?p=1",
		);
		foreach ( $outside as $url ) {
			$saved = '- [同じタイトル](' . $url . '): 外';
			update_option( AVC_AIS_Llms_Txt::OPTION_NAME, $saved );

			$this->assertSame( $saved, $this->served(), $url );
		}

		$inside = array(
			"http://{$host}/blog/docs/",
			"https://{$host}/blog/docs/",
			"http://www.{$host}/blog/docs/",
			"//{$host}/blog/docs/",
			"http://{$host}/BLOG/docs/",
			"http://{$host}/blog//",
			'/blog/docs/',
			'docs/',
		);
		foreach ( $inside as $url ) {
			update_option( AVC_AIS_Llms_Txt::OPTION_NAME, '- [同じタイトル](' . $url . '): 中' );

			$this->assertSame( '', $this->served(), $url );
		}
	}

	/**
	 * エンコードした日本語のサブディレクトリでも、中のパスとして判定すること。
	 *
	 * @return void
	 */
	public function test_site_in_a_non_ascii_subdirectory_matches_either_encoding() {
		$host = WP_TESTS_DOMAIN;
		update_option( 'home', "http://{$host}/%e3%83%96%e3%83%ad%e3%82%b0" );
		self::factory()->post->create(
			array(
				'post_title'  => '同じタイトル',
				'post_status' => 'private',
			)
		);

		foreach ( array( "http://{$host}/ブログ/docs/", "http://{$host}/%E3%83%96%E3%83%AD%E3%82%B0/docs/" ) as $url ) {
			update_option( AVC_AIS_Llms_Txt::OPTION_NAME, '- [同じタイトル](' . $url . '): 中' );

			$this->assertSame( '', $this->served(), $url );
		}
	}

	/**
	 * home_url() に言語のディレクトリが足されていても、他の言語の行をこのサイトとして判定すること。
	 *
	 * @return void
	 */
	public function test_language_directory_in_home_url_keeps_other_languages_inside() {
		$host = WP_TESTS_DOMAIN;
		self::factory()->post->create(
			array(
				'post_title'  => '同じタイトル',
				'post_status' => 'private',
			)
		);
		$add_language = static function ( $url, $path ) {
			return untrailingslashit( get_option( 'home' ) ) . '/ja' . $path;
		};
		add_filter( 'home_url', $add_language, 10, 2 );
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, "- [同じタイトル](http://{$host}/en/docs/): 他の言語" );
		$served = $this->served();
		remove_filter( 'home_url', $add_language, 10 );

		$this->assertSame( '', $served );
	}

	/**
	 * home_url() だけを別のドメインへ変えるプラグインがあっても、そのドメインの行を隠すこと。
	 *
	 * @return void
	 */
	public function test_filtered_home_url_domain_stays_inside() {
		update_option( 'home', 'http://' . WP_TESTS_DOMAIN . '/original' );
		self::factory()->post->create(
			array(
				'post_title'  => '同じタイトル',
				'post_status' => 'private',
			)
		);
		$map_domain = static function ( $url, $path ) {
			return 'http://mapped.example' . $path;
		};
		add_filter( 'home_url', $map_domain, 10, 2 );
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, '- [同じタイトル](http://mapped.example/docs/): 別のドメイン' );
		$served = $this->served();
		remove_filter( 'home_url', $map_domain, 10 );

		$this->assertSame( '', $served );
	}

	/**
	 * パスが公開していない投稿と同じなら、別のポートでも抜粋を出さないよう隠すこと。
	 *
	 * @return void
	 */
	public function test_entries_on_another_port_with_a_hidden_post_path_are_hidden() {
		self::factory()->post->create(
			array(
				'post_title'  => '公開していない投稿',
				'post_name'   => 'docs',
				'post_status' => 'private',
			)
		);
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, '- [別のタイトル](http://' . WP_TESTS_DOMAIN . ':8443/docs/): 移転前のポート' );

		$this->assertSame( '', $this->served() );
	}

	/**
	 * パスが同じでも別のポートの行は、投稿のスラッグの変更や削除で書き換えないこと。
	 *
	 * @return void
	 */
	public function test_entries_on_another_port_survive_post_changes() {
		$host    = WP_TESTS_DOMAIN;
		$post_id = self::factory()->post->create(
			array(
				'post_title' => '同じタイトル',
				'post_name'  => 'docs',
			)
		);
		$other   = "- [同じタイトル](http://{$host}:8080/docs/): 別のポート";
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, "- [同じタイトル](http://{$host}/docs/): このサイト\n{$other}" );

		wp_update_post(
			array(
				'ID'        => $post_id,
				'post_name' => 'docs-moved',
			)
		);
		$this->assertSame(
			"- [同じタイトル](http://{$host}/docs-moved/): このサイト\n{$other}",
			get_option( AVC_AIS_Llms_Txt::OPTION_NAME )
		);

		wp_delete_post( $post_id, true );
		$this->assertSame( $other, get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
	}

	/**
	 * 投稿を削除しても、パスが同じ別のポートの行は消さないこと（保存時の除去も含む）。
	 *
	 * @return void
	 */
	public function test_deleting_a_post_keeps_the_same_path_on_another_port() {
		$host    = WP_TESTS_DOMAIN;
		$post_id = self::factory()->post->create(
			array(
				'post_title' => '同じタイトル',
				'post_name'  => 'docs',
			)
		);
		$other   = "- [同じタイトル](http://{$host}:8080/docs/): 別のポート";
		$own     = "- [同じタイトル](http://{$host}/docs/): このサイト";
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, "{$own}\n{$other}" );

		wp_delete_post( $post_id, true );
		$this->assertSame( $other, get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );

		// 古い画面から保存した内容は、記憶した URL の行だけを取り除く。
		$remove = new ReflectionMethod( AVC_AIS_Llms_Txt::class, 'remove_entries_of_deleted_posts' );
		avc_ais_test_make_accessible( $remove );
		$this->assertSame( $other, $remove->invoke( null, "{$own}\n{$other}" ) );
	}

	/**
	 * home に日本語がそのまま入っていても、中のパスとトップページを判定できること。
	 *
	 * @return void
	 */
	public function test_raw_multibyte_home_matches_its_paths() {
		$host = WP_TESTS_DOMAIN;
		self::factory()->post->create(
			array(
				'post_title'  => '同じタイトル',
				'post_status' => 'private',
			)
		);
		$raw_home = static function () use ( $host ) {
			return "http://{$host}/日本語";
		};
		add_filter( 'option_home', $raw_home );
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, "- [同じタイトル](http://{$host}/日本語/docs/): 中" );
		$inside = $this->served();
		$front  = "- [同じタイトル](http://{$host}/日本語/): トップページ";
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, $front );
		$top = $this->served();
		remove_filter( 'option_home', $raw_home );

		$this->assertSame( '', $inside );
		$this->assertSame( $front, $top );
	}

	/**
	 * home_url() にクエリが足されていても、トップページとサイトの範囲を判定できること。
	 *
	 * @return void
	 */
	public function test_query_in_home_url_is_ignored() {
		$host = WP_TESTS_DOMAIN;
		self::factory()->post->create(
			array(
				'post_title'  => '同じタイトル',
				'post_status' => 'private',
			)
		);
		$add_query = static function ( $url, $path ) {
			return '' === $path || '/' === $path ? untrailingslashit( get_option( 'home' ) ) . '/?lang=en' : $url;
		};
		add_filter( 'home_url', $add_query, 10, 2 );
		$front = "- [同じタイトル](http://{$host}/): トップページ";
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, $front . "\n- [同じタイトル](http://{$host}/docs/): このサイト" );
		$served = $this->served();
		remove_filter( 'home_url', $add_query, 10 );

		$this->assertSame( $front, $served );
	}

	/**
	 * ?p= の URL は、このサイトの範囲の中でだけ投稿と照合すること。
	 *
	 * @return void
	 */
	public function test_query_urls_outside_the_site_are_not_matched_by_id() {
		$host    = WP_TESTS_DOMAIN;
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => '公開していない投稿',
				'post_status' => 'draft',
			)
		);
		update_option( 'home', "http://{$host}/blog" );

		$saved = "- [別のタイトル](http://{$host}/shop/?p={$post_id}): サイトの外";
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, $saved );
		$this->assertSame( $saved, $this->served() );

		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, "- [別のタイトル](http://{$host}/blog/?p={$post_id}): このサイト" );
		$this->assertSame( '', $this->served() );
	}

	/**
	 * 日本語のサブディレクトリで以前の保存処理が壊した URL は、このサイトの行として隠すこと。
	 *
	 * @return void
	 */
	public function test_legacy_broken_urls_under_an_encoded_home_are_site_entries() {
		$host = WP_TESTS_DOMAIN;
		self::factory()->post->create(
			array(
				'post_title'  => '同じタイトル',
				'post_status' => 'private',
			)
		);

		$homes = array(
			"http://{$host}/%e3%83%96%e3%83%ad%e3%82%b0" => "http://{$host}//docs/",
			"http://{$host}/site-%e3%81%82"              => "http://{$host}/site-/docs/",
		);
		foreach ( $homes as $home => $url ) {
			update_option( 'home', $home );
			update_option( AVC_AIS_Llms_Txt::OPTION_NAME, '- [同じタイトル](' . $url . '): 壊れた URL' );
			$this->assertSame( '', $this->served(), $url );

			// 比較の範囲が広がりすぎて、サイトの外の行まで隠さないこと。
			$outside = "- [同じタイトル](http://{$host}/shop/docs/): サイトの外";
			update_option( AVC_AIS_Llms_Txt::OPTION_NAME, $outside );
			$this->assertSame( $outside, $this->served(), $home );
		}
	}

	/**
	 * サブディレクトリのトップページは、大文字やエンコードの違いがあっても隠さないこと。
	 *
	 * @return void
	 */
	public function test_home_url_spellings_are_not_judged_by_title() {
		$host = WP_TESTS_DOMAIN;
		update_option( 'home', "http://{$host}/%e3%83%96%e3%83%ad%e3%82%b0" );
		self::factory()->post->create(
			array(
				'post_title'  => '同じタイトル',
				'post_status' => 'private',
			)
		);

		$urls = array(
			"http://{$host}/ブログ",
			"http://{$host}/ブログ/",
			"http://{$host}/%E3%83%96%E3%83%AD%E3%82%B0/",
		);
		foreach ( $urls as $url ) {
			$saved = '- [同じタイトル](' . $url . '): トップページ';
			update_option( AVC_AIS_Llms_Txt::OPTION_NAME, $saved );

			$this->assertSame( $saved, $this->served(), $url );
		}
	}

	/**
	 * http と https のリクエストで、保存した判定結果を共有すること。
	 *
	 * @return void
	 */
	public function test_cached_result_is_shared_between_schemes() {
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, '- [タイトル](http://' . WP_TESTS_DOMAIN . '/docs/): 説明' );
		$this->served();

		$use_https = static function ( $url ) {
			return set_url_scheme( $url, 'https' );
		};
		add_filter( 'home_url', $use_https );
		$this->served();
		remove_filter( 'home_url', $use_https );

		$this->assertCount( 1, get_transient( AVC_AIS_Llms_Txt::HIDDEN_LINES_CACHE ) );
	}

	/**
	 * home_url() が交互に変わっても判定し直さず、保存する数には上限があること。
	 *
	 * @return void
	 */
	public function test_cached_results_are_kept_per_home_url() {
		global $wpdb;

		$hosts = array( 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h' );
		$saved = implode(
			"\n",
			array_map(
				static function ( $host ) {
					return "- [タイトル](http://{$host}.example/docs/): 説明";
				},
				$hosts
			)
		);
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, $saved );
		$served_on = function ( $host ) {
			$map_domain = static function ( $url, $path ) use ( $host ) {
				return "http://{$host}{$path}";
			};
			add_filter( 'home_url', $map_domain, 10, 2 );
			$this->served();
			remove_filter( 'home_url', $map_domain, 10 );
		};

		$served_on( 'a.example' );
		$served_on( 'b.example' );
		$before = $wpdb->num_queries;
		$served_on( 'a.example' );
		$served_on( 'b.example' );
		$this->assertSame( $before, $wpdb->num_queries, '保存した結果を使えば投稿を検索しない' );

		foreach ( $hosts as $host ) {
			$served_on( "{$host}.example" );
		}
		$this->assertCount( AVC_AIS_Llms_Txt::HIDDEN_LINES_CACHE_HOMES, get_transient( AVC_AIS_Llms_Txt::HIDDEN_LINES_CACHE ) );
	}

	/**
	 * ホストの大文字小文字で判定し直さないこと。ポートが違えば別の結果として保存すること。
	 *
	 * @return void
	 */
	public function test_cached_result_key_normalizes_host_and_includes_port() {
		$saved = '- [タイトル](http://' . WP_TESTS_DOMAIN . '/docs/): 説明';
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, $saved );
		$this->served();

		$upper = static function ( $url, $path ) {
			return 'http://' . strtoupper( WP_TESTS_DOMAIN ) . $path;
		};
		add_filter( 'home_url', $upper, 10, 2 );
		$this->served();
		remove_filter( 'home_url', $upper, 10 );
		$this->assertCount( 1, get_transient( AVC_AIS_Llms_Txt::HIDDEN_LINES_CACHE ) );

		$with_port = static function ( $url, $path ) {
			return 'http://' . WP_TESTS_DOMAIN . ':8080' . $path;
		};
		add_filter( 'home_url', $with_port, 10, 2 );
		$this->served();
		remove_filter( 'home_url', $with_port, 10 );
		$this->assertCount( 2, get_transient( AVC_AIS_Llms_Txt::HIDDEN_LINES_CACHE ) );
	}

	/**
	 * ホストのすぐ後にクエリや空白が続く行も、そのホストの行として別に保存すること。
	 *
	 * @return void
	 */
	public function test_hosts_followed_by_a_query_are_found_in_the_content() {
		$served_on = function ( $host ) {
			$map_domain = static function ( $url, $path ) use ( $host ) {
				return "http://{$host}{$path}";
			};
			add_filter( 'home_url', $map_domain, 10, 2 );
			$this->served();
			remove_filter( 'home_url', $map_domain, 10 );
		};

		foreach ( array( '?p=5', '#top', ' "タイトル"' ) as $rest ) {
			delete_transient( AVC_AIS_Llms_Txt::HIDDEN_LINES_CACHE );
			update_option( AVC_AIS_Llms_Txt::OPTION_NAME, "- [題](https://en.example{$rest}): 抜粋" );
			$served_on( 'en.example' );
			$served_on( 'ja.example' );
			$this->assertCount( 2, get_transient( AVC_AIS_Llms_Txt::HIDDEN_LINES_CACHE ), $rest );
		}
	}

	/**
	 * 別のポートで書かれた行のホストも、そのホストの行として別に保存すること。
	 * 別の言語のドメインの結果を使うと、隠すはずの行が配信される。
	 *
	 * @return void
	 */
	public function test_hosts_with_a_port_in_the_content_are_found() {
		self::factory()->post->create(
			array(
				'post_name'   => 'secret-on-port',
				'post_status' => 'private',
			)
		);
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, '- [別の題](https://en.example:8443/secret-on-port/): 秘密の抜粋' );
		$served_on = function ( $host ) {
			$map_domain = static function ( $url, $path ) use ( $host ) {
				return "http://{$host}{$path}";
			};
			add_filter( 'home_url', $map_domain, 10, 2 );
			$body = $this->served();
			remove_filter( 'home_url', $map_domain, 10 );
			return $body;
		};

		$this->assertStringContainsString( '秘密の抜粋', $served_on( 'ja.example' ) );
		$this->assertStringNotContainsString( '秘密の抜粋', $served_on( 'en.example' ) );
		$this->assertCount( 2, get_transient( AVC_AIS_Llms_Txt::HIDDEN_LINES_CACHE ) );
	}

	/**
	 * home のポートが行にないときも、行のホストの結果を別のドメインと分けること。
	 * ホストが同じなら投稿の状態で判定するので、別のドメインの結果を使うと隠すはずの行が配信される。
	 *
	 * @return void
	 */
	public function test_hosts_in_the_content_are_found_when_the_home_port_differs() {
		self::factory()->post->create(
			array(
				'post_name'   => 'secret-on-port',
				'post_status' => 'private',
			)
		);
		$served_on = function ( $host ) {
			$map_domain = static function ( $url, $path ) use ( $host ) {
				return "http://{$host}{$path}";
			};
			add_filter( 'home_url', $map_domain, 10, 2 );
			$body = $this->served();
			remove_filter( 'home_url', $map_domain, 10 );
			return $body;
		};

		foreach ( array( 'https://en.example/', 'https://en.example:9000/' ) as $base ) {
			update_option( AVC_AIS_Llms_Txt::OPTION_NAME, "- [別の題]({$base}secret-on-port/): 秘密の抜粋" );
			delete_transient( AVC_AIS_Llms_Txt::HIDDEN_LINES_CACHE );

			$this->assertStringContainsString( '秘密の抜粋', $served_on( 'ja.example:8443' ), $base );
			$this->assertStringNotContainsString( '秘密の抜粋', $served_on( 'en.example:8443' ), $base );
		}
	}

	/**
	 * 判定と同じ解析でホストを探すので、書き方が違う行も、そのホストの行として別に保存すること。
	 *
	 * @return void
	 */
	public function test_hosts_in_the_content_are_found_in_every_spelling() {
		self::factory()->post->create(
			array(
				'post_name'   => 'secret-spelling',
				'post_status' => 'private',
			)
		);
		$served_on = function ( $host ) {
			$map_domain = static function ( $url, $path ) use ( $host ) {
				return "http://{$host}{$path}";
			};
			add_filter( 'home_url', $map_domain, 10, 2 );
			$body = $this->served();
			remove_filter( 'home_url', $map_domain, 10 );
			return $body;
		};

		$spellings = array(
			'日本語のドメイン' => array( 'https://例.example/secret-spelling/', '例.example' ),
			'ユーザー名付き'   => array( 'https://user@en.example/secret-spelling/', 'en.example' ),
		);
		foreach ( $spellings as $name => list( $url, $host ) ) {
			update_option( AVC_AIS_Llms_Txt::OPTION_NAME, "- [別の題]({$url}): 秘密の抜粋" );
			delete_transient( AVC_AIS_Llms_Txt::HIDDEN_LINES_CACHE );

			$this->assertStringContainsString( '秘密の抜粋', $served_on( 'ja.example' ), $name );
			$this->assertStringNotContainsString( '秘密の抜粋', $served_on( $host ), $name );
		}
	}

	/**
	 * 固定ページをトップページにしたサイトでは、トップページの行もそのページの状態で判定すること。
	 * トップページの URL からは投稿を探せないので、設定から探す。
	 *
	 * @return void
	 */
	public function test_home_entry_follows_the_static_front_page() {
		$front_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'ようこそ',
				'post_status' => 'publish',
			)
		);
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $front_id );
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, '- [別の題](' . home_url( '/' ) . '): 秘密の抜粋' );

		$this->assertStringContainsString( '秘密の抜粋', $this->served(), '公開中は配信する' );

		wp_update_post(
			array(
				'ID'          => $front_id,
				'post_status' => 'private',
			)
		);
		$this->assertStringNotContainsString( '秘密の抜粋', $this->served(), '非公開' );

		wp_update_post(
			array(
				'ID'            => $front_id,
				'post_status'   => 'publish',
				'post_password' => 'secret',
			)
		);
		$this->assertStringNotContainsString( '秘密の抜粋', $this->served(), 'パスワード付き' );

		// 投稿一覧をトップページに戻すと、残っている設定のページはトップページではない。
		update_option( 'show_on_front', 'posts' );
		delete_transient( AVC_AIS_Llms_Txt::HIDDEN_LINES_CACHE );
		$this->assertStringContainsString( '秘密の抜粋', $this->served(), '投稿一覧のトップページ' );
	}

	/**
	 * URL で判定できない投稿を削除しても、?p= の行は取り除くこと。
	 * ?p= はその投稿だけを指し、投稿がなくなると判定できず配信され続ける。
	 *
	 * @return void
	 */
	public function test_deleting_a_post_removes_its_query_entry_when_the_url_is_not_its_own() {
		$cases = array(
			'スラッグのない下書き' => self::factory()->post->create(
				array(
					'post_status' => 'draft',
					'post_name'   => '',
				)
			),
			'同じ URL の別の投稿'  => self::factory()->post->create(
				array(
					'post_status' => 'publish',
					'post_name'   => 'shared-url',
				)
			),
		);
		self::factory()->post->create(
			array(
				'post_status' => 'draft',
				'post_name'   => 'shared-url',
			)
		);

		foreach ( $cases as $name => $post_id ) {
			$line = '- [別の題](' . home_url( "/?p={$post_id}" ) . '): 秘密の抜粋';
			update_option( AVC_AIS_Llms_Txt::OPTION_NAME, "{$line}\n- [他](" . home_url( '/docs/' ) . '): 説明' );

			wp_delete_post( $post_id, true );

			$saved = get_option( AVC_AIS_Llms_Txt::OPTION_NAME );
			$this->assertStringNotContainsString( '秘密の抜粋', $saved, $name );
			$this->assertStringContainsString( '- [他]', $saved, $name );
		}
	}

	/**
	 * 行にあるホストでも、行にないポートは、ポートを変えても判定し直さないこと。
	 *
	 * @return void
	 */
	public function test_ports_not_in_the_content_share_the_cached_result() {
		global $wpdb;

		update_option(
			AVC_AIS_Llms_Txt::OPTION_NAME,
			'- [タイトル](http://' . WP_TESTS_DOMAIN . '/docs/): 説明' . "\n" . '- [別](http://' . WP_TESTS_DOMAIN . ':8443/docs/): 説明'
		);
		$served_on = function ( $host ) {
			$from_host = static function ( $url, $path ) use ( $host ) {
				return "http://{$host}{$path}";
			};
			add_filter( 'home_url', $from_host, 10, 2 );
			$this->served();
			remove_filter( 'home_url', $from_host, 10 );
		};

		$served_on( WP_TESTS_DOMAIN . ':1' );
		$before = $wpdb->num_queries;
		$served_on( WP_TESTS_DOMAIN . ':2' );
		$served_on( WP_TESTS_DOMAIN . ':3' );
		$this->assertSame( $before, $wpdb->num_queries );
		$this->assertCount( 1, get_transient( AVC_AIS_Llms_Txt::HIDDEN_LINES_CACHE ) );

		// 行にあるポートと標準のポートは、範囲の判定が変わるので分ける。
		$served_on( WP_TESTS_DOMAIN . ':8443' );
		$this->assertCount( 2, get_transient( AVC_AIS_Llms_Txt::HIDDEN_LINES_CACHE ) );
		$served_on( WP_TESTS_DOMAIN );
		$this->assertCount( 3, get_transient( AVC_AIS_Llms_Txt::HIDDEN_LINES_CACHE ) );
	}

	/**
	 * 行にないホストは、Host を変えても判定し直さないこと。
	 *
	 * @return void
	 */
	public function test_hosts_not_in_the_content_share_the_cached_result() {
		global $wpdb;

		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, '- [タイトル](http://' . WP_TESTS_DOMAIN . '/docs/): 説明' );
		$served_on = function ( $host ) {
			$from_host = static function ( $url, $path ) use ( $host ) {
				return "http://{$host}{$path}";
			};
			add_filter( 'home_url', $from_host, 10, 2 );
			$this->served();
			remove_filter( 'home_url', $from_host, 10 );
		};

		$served_on( 'attacker-1.example' );
		$before = $wpdb->num_queries;
		$served_on( 'attacker-2.example' );
		$served_on( 'attacker-3.example' );
		// 行の URL の一部だけに一致する Host も、行にあるホストとしない。
		$served_on( 'e' );
		$served_on( 'example' );
		// ポートだけを変えても判定し直さない。
		$served_on( 'attacker-2.example:1' );
		$served_on( 'attacker-2.example:2' );

		$this->assertSame( $before, $wpdb->num_queries );
		$this->assertCount( 1, get_transient( AVC_AIS_Llms_Txt::HIDDEN_LINES_CACHE ) );
	}

	/**
	 * 結果ごとの期限を過ぎた判定結果は、全体の期限が残っていても使わないこと。
	 *
	 * @return void
	 */
	public function test_cached_result_expires_on_its_own() {
		$post_id = self::factory()->post->create(
			array(
				'post_title' => '記事',
				'post_name'  => 'cached-post',
			)
		);
		$saved   = '- [記事](http://' . WP_TESTS_DOMAIN . '/cached-post/): 抜粋';
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, $saved );
		$this->assertSame( $saved, $this->served() );

		// 投稿の変更のフックを通らない変更（データベースの直接の編集など）を想定している。
		global $wpdb;
		$wpdb->update( $wpdb->posts, array( 'post_status' => 'private' ), array( 'ID' => $post_id ) );
		clean_post_cache( $post_id );
		$this->assertSame( $saved, $this->served(), '前提: 期限までは保存した結果を使う' );

		$cached = get_transient( AVC_AIS_Llms_Txt::HIDDEN_LINES_CACHE );
		foreach ( array_keys( $cached ) as $home ) {
			$cached[ $home ]['time'] = time() - 5 * MINUTE_IN_SECONDS;
		}
		set_transient( AVC_AIS_Llms_Txt::HIDDEN_LINES_CACHE, $cached, MINUTE_IN_SECONDS );

		$this->assertSame( '', $this->served() );
	}

	/**
	 * home_url() が変わったら、保存した判定結果を使わないこと。
	 *
	 * @return void
	 */
	public function test_cached_result_depends_on_home_url() {
		self::factory()->post->create(
			array(
				'post_title'  => '同じタイトル',
				'post_status' => 'private',
			)
		);
		$saved = '- [同じタイトル](http://mapped.example/docs/): 別のドメイン';
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, $saved );
		$this->assertSame( $saved, $this->served() );

		$map_domain = static function ( $url, $path ) {
			return 'http://mapped.example' . $path;
		};
		add_filter( 'home_url', $map_domain, 10, 2 );
		$served = $this->served();
		remove_filter( 'home_url', $map_domain, 10 );

		$this->assertSame( '', $served );
	}

	/**
	 * タイトルとスラッグがともに公開していない投稿と一致する行は、リンク先を問わず隠すこと。
	 *
	 * サイトを移転した前の URL や、home_url() がリクエストで変わるサイトの行を想定している。
	 *
	 * @return void
	 */
	public function test_entries_matching_title_and_slug_are_hidden_anywhere() {
		$host = WP_TESTS_DOMAIN;
		self::factory()->post->create(
			array(
				'post_title'  => '同じタイトル',
				'post_name'   => 'secret-post',
				'post_status' => 'private',
			)
		);
		update_option( 'home', "http://{$host}/new" );

		$urls = array(
			'https://old-domain.example/secret-post/',
			"http://{$host}/old/secret-post/",
			"http://{$host}:8443/old/secret-post/",
			"http://{$host}/shop/../new/secret-post/",
			"http://{$host}/old/secret-post.html",
		);
		foreach ( $urls as $url ) {
			update_option( AVC_AIS_Llms_Txt::OPTION_NAME, '- [同じタイトル](' . $url . '): 移転前' );
			$this->assertSame( '', $this->served(), $url );
		}

		// タイトルが違えば別のページとして残す。
		$saved = '- [別のタイトル](https://old-domain.example/secret-post/): 別のページ';
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, $saved );
		$this->assertSame( $saved, $this->served() );

		// スラッグが違えば別のページとして残す。
		$saved = '- [同じタイトル](https://old-domain.example/other-page/): 別のページ';
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, $saved );
		$this->assertSame( $saved, $this->served() );
	}

	/**
	 * 別のホストの行は、タイトルとスラッグが同じでも投稿が公開中なら残し、パスが同じだけでも残すこと。
	 *
	 * @return void
	 */
	public function test_entries_on_another_host_need_a_hidden_post_with_the_same_title_and_slug() {
		self::factory()->post->create(
			array(
				'post_title' => '公開中の記事',
				'post_name'  => 'public-post',
			)
		);
		self::factory()->post->create(
			array(
				'post_title'  => '公開していない記事',
				'post_name'   => 'secret-post',
				'post_status' => 'private',
			)
		);
		$saved = "- [公開中の記事](https://other.example/public-post/): 公開中\n- [別のタイトル](https://other.example/secret-post/): パスだけ同じ";
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, $saved );

		$this->assertSame( $saved, $this->served() );
	}

	/**
	 * タイトルと ID が公開していない投稿と一致する行は、リンク先を問わず隠すこと。
	 *
	 * @return void
	 */
	public function test_entries_matching_title_and_id_are_hidden_anywhere() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => '同じタイトル',
				'post_status' => 'private',
			)
		);

		$urls = array(
			"https://old-domain.example/?p={$post_id}",
			"https://old-domain.example/?page_id={$post_id}",
			"https://old-domain.example/archives/{$post_id}",
		);
		foreach ( $urls as $url ) {
			update_option( AVC_AIS_Llms_Txt::OPTION_NAME, '- [同じタイトル](' . $url . '): 移転前' );
			$this->assertSame( '', $this->served(), $url );

			$saved = '- [別のタイトル](' . $url . '): 別のページ';
			update_option( AVC_AIS_Llms_Txt::OPTION_NAME, $saved );
			$this->assertSame( $saved, $this->served(), $url );
		}
	}

	/**
	 * タイトルとスラッグが同じ公開中の投稿もあれば、その投稿の行かもしれないので隠さないこと。
	 *
	 * @return void
	 */
	public function test_public_twin_keeps_entries_matching_title_and_slug() {
		$trashed = self::factory()->post->create(
			array(
				'post_title' => '作り直した記事',
				'post_name'  => 'rebuilt',
			)
		);
		wp_trash_post( $trashed );
		self::factory()->post->create(
			array(
				'post_title' => '作り直した記事',
				'post_name'  => 'rebuilt',
			)
		);
		$saved = '- [作り直した記事](https://old-domain.example/rebuilt/): 公開中';
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, $saved );

		$this->assertSame( $saved, $this->served() );
	}

	/**
	 * 計測用のクエリが付いた URL も、タイトルが違っても投稿の状態で判定すること。
	 *
	 * @return void
	 */
	public function test_tracking_query_does_not_reveal_hidden_posts() {
		self::factory()->post->create(
			array(
				'post_title'  => '公開していない投稿',
				'post_name'   => 'secret-post',
				'post_status' => 'private',
			)
		);
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, '- [別の題](http://' . WP_TESTS_DOMAIN . '/secret-post/?utm_source=llms): 抜粋' );

		$this->assertSame( '', $this->served() );
	}

	/**
	 * 投稿を完全に削除したら、計測用のクエリが付いた行も消すこと。
	 *
	 * @return void
	 */
	public function test_deleting_a_post_removes_its_entry_with_a_tracking_query() {
		$host    = WP_TESTS_DOMAIN;
		$post_id = self::factory()->post->create(
			array(
				'post_title' => '記事',
				'post_name'  => 'gone',
			)
		);
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, "- [別の題](http://{$host}/gone/?x=1): 抜粋" );

		wp_delete_post( $post_id, true );
		$this->assertSame( '', (string) get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );

		// 古い画面から保存した内容からも取り除く。
		$remove = new ReflectionMethod( AVC_AIS_Llms_Txt::class, 'remove_entries_of_deleted_posts' );
		avc_ais_test_make_accessible( $remove );
		$this->assertSame( '', $remove->invoke( null, "- [別の題](http://{$host}/gone/?x=1): 抜粋" ) );
	}

	/**
	 * 投稿を完全に削除したら、?p=ID の行も消して、隠れていた抜粋を配信しないこと。
	 *
	 * @return void
	 */
	public function test_deleting_a_post_removes_its_entry_with_a_query_id() {
		$host    = WP_TESTS_DOMAIN;
		$post_id = self::factory()->post->create( array( 'post_status' => 'private' ) );
		$other   = "- [別の題](http://{$host}:8080/?p={$post_id}): 別のポート";
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, "- [別の題](http://{$host}/?p={$post_id}&utm=1): 抜粋\n{$other}" );

		wp_delete_post( $post_id, true );

		$this->assertSame( $other, get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
	}

	/**
	 * ID が文字列でも、?p=ID の行を消すこと。WordPress 6.8 までの wp_delete_post() は、受け取った値のままフックへ渡す。
	 *
	 * @return void
	 */
	public function test_deleting_a_post_by_a_string_id_removes_its_entry_with_a_query_id() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'private' ) );
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, '- [別の題](' . home_url( "/?p={$post_id}&utm=1" ) . '): 抜粋' );

		AVC_AIS_Llms_Txt::init()->remove_entry_of_deleted_post( (string) $post_id, get_post( $post_id ) );

		$this->assertSame( '', get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
	}

	/**
	 * 投稿の URL が変わったら、クエリとフラグメントを残して行を書き換えること。
	 *
	 * @return void
	 */
	public function test_changing_slug_keeps_query_and_fragment_of_the_entry() {
		$host = WP_TESTS_DOMAIN;
		foreach ( array( 'post', 'page' ) as $post_type ) {
			$post_id = self::factory()->post->create(
				array(
					'post_type' => $post_type,
					'post_name' => "before-{$post_type}",
				)
			);
			update_option(
				AVC_AIS_Llms_Txt::OPTION_NAME,
				"- [A](http://{$host}/before-{$post_type}/?x=1): クエリ\n- [B](http://{$host}/before-{$post_type}/#sec): フラグメント"
			);

			wp_update_post(
				array(
					'ID'        => $post_id,
					'post_name' => "after-{$post_type}",
				)
			);

			$this->assertSame(
				"- [A](http://{$host}/after-{$post_type}/?x=1): クエリ\n- [B](http://{$host}/after-{$post_type}/#sec): フラグメント",
				get_option( AVC_AIS_Llms_Txt::OPTION_NAME )
			);
		}
	}

	/**
	 * 投稿の URL そのものにクエリがある形は、スラッグの変更でクエリを二重にしないこと。
	 *
	 * @return void
	 */
	public function test_changing_slug_does_not_double_the_query_of_the_permalink() {
		$add_language = static function ( $url ) {
			return add_query_arg( 'lang', 'en', $url );
		};
		add_filter( 'post_link', $add_language );
		$post_id = self::factory()->post->create( array( 'post_name' => 'before' ) );
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, '- [A](http://' . WP_TESTS_DOMAIN . '/before/?lang=en): 言語' );

		wp_update_post(
			array(
				'ID'        => $post_id,
				'post_name' => 'after',
			)
		);
		$updated = get_option( AVC_AIS_Llms_Txt::OPTION_NAME );
		remove_filter( 'post_link', $add_language );

		$this->assertSame( '- [A](http://' . WP_TESTS_DOMAIN . '/after/?lang=en): 言語', $updated );
	}

	/**
	 * パスの %3F はクエリではないので、別のページの行として扱うこと。
	 *
	 * @return void
	 */
	public function test_encoded_question_mark_in_the_path_is_not_a_query() {
		$post_id = self::factory()->post->create(
			array(
				'post_title' => '記事',
				'post_name'  => 'old',
			)
		);
		$saved   = '- [別のページ](http://' . WP_TESTS_DOMAIN . '/old%3Fv2/): 手書き';
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, $saved );

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'private',
			)
		);
		$this->assertSame( $saved, $this->served() );

		// 投稿の行が一緒にあり、投稿を取得していても別のページとして扱う。
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, '- [記事](http://' . WP_TESTS_DOMAIN . "/old/): 投稿の行\n{$saved}" );
		$this->assertSame( $saved, $this->served() );
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, $saved );

		wp_update_post(
			array(
				'ID'        => $post_id,
				'post_name' => 'new',
			)
		);
		$this->assertSame( $saved, get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );

		wp_delete_post( $post_id, true );
		$this->assertSame( $saved, get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
	}

	/**
	 * 作り直した投稿の行は、計測用のクエリが付いていても古い画面の保存で消さないこと。
	 *
	 * @return void
	 */
	public function test_recreated_post_keeps_its_entry_with_a_tracking_query() {
		$post_id = self::factory()->post->create( array( 'post_name' => 'gone' ) );
		wp_delete_post( $post_id, true );
		self::factory()->post->create( array( 'post_name' => 'gone' ) );

		$remove = new ReflectionMethod( AVC_AIS_Llms_Txt::class, 'remove_entries_of_deleted_posts' );
		avc_ais_test_make_accessible( $remove );
		$saved = '- [題](http://' . WP_TESTS_DOMAIN . '/gone/?utm=1): 手書き';

		$this->assertSame( $saved, $remove->invoke( null, $saved ) );
	}

	/**
	 * URL が ID から作られるパーマリンクでは、スラッグのない下書きも URL で判定すること。
	 *
	 * @return void
	 */
	public function test_slugless_draft_is_matched_by_its_id_permalink() {
		$this->set_permalink_structure( '/archives/%post_id%/' );
		$post_id = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, '- [別の題](http://' . WP_TESTS_DOMAIN . "/archives/{$post_id}/): 抜粋" );

		$this->assertSame( '', $this->served() );
	}

	/**
	 * 投稿の URL にクエリがある形でも、同じ URL の別の投稿があれば行を消さないこと。
	 *
	 * @return void
	 */
	public function test_other_post_at_a_permalink_with_a_query_keeps_the_entry() {
		$add_language = static function ( $url ) {
			return add_query_arg( 'lang', 'en', $url );
		};
		add_filter( 'post_link', $add_language );
		self::factory()->post->create( array( 'post_name' => 'dup' ) );
		$draft = self::factory()->post->create(
			array(
				'post_status' => 'draft',
				'post_name'   => 'dup',
			)
		);
		$saved = '- [題](http://' . WP_TESTS_DOMAIN . '/dup/?lang=en): 手書き';
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, $saved );

		wp_delete_post( $draft, true );
		$after = get_option( AVC_AIS_Llms_Txt::OPTION_NAME );
		remove_filter( 'post_link', $add_language );

		$this->assertSame( $saved, $after );
	}

	/**
	 * タグのあるタイトルの行は、保存時に取り除いた形でもタイトルとスラッグで照合すること。
	 *
	 * @return void
	 */
	public function test_title_with_tags_is_matched_in_its_saved_form() {
		self::factory()->post->create(
			array(
				'post_title'  => '<em>強調</em> タイトル',
				'post_name'   => 'with-tags',
				'post_status' => 'private',
			)
		);
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, '- [強調 タイトル](https://old-domain.example/with-tags/): 抜粋' );

		$this->assertSame( '', $this->served() );
	}

	/**
	 * home_url( '/' ) にクエリがあるサイトでも、スラッグのない下書きをトップページと見なさないこと。
	 *
	 * @return void
	 */
	public function test_slugless_draft_does_not_claim_a_language_front_page() {
		$host    = WP_TESTS_DOMAIN;
		$post_id = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		$add     = static function ( $url ) {
			return add_query_arg( 'lang', 'en', $url );
		};
		add_filter( 'home_url', $add );
		$front = "- [トップ](http://{$host}/?lang=en): トップページ";
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, "{$front}\n- [別の題](http://{$host}/?p={$post_id}): 下書き" );
		$served = $this->served();
		remove_filter( 'home_url', $add );

		$this->assertSame( $front, $served );
	}

	/**
	 * http でないスキームの行は、パスが同じでも投稿の変更や削除で書き換えないこと。
	 *
	 * @return void
	 */
	public function test_other_schemes_survive_post_changes() {
		$host    = WP_TESTS_DOMAIN;
		$post_id = self::factory()->post->create( array( 'post_name' => 'docs' ) );
		$saved   = "- [FTP](ftp://{$host}/docs/): 別のスキーム";
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, $saved );

		wp_update_post(
			array(
				'ID'        => $post_id,
				'post_name' => 'docs-moved',
			)
		);
		$this->assertSame( $saved, get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );

		$other = self::factory()->post->create( array( 'post_name' => 'docs' ) );
		wp_delete_post( $other, true );
		$this->assertSame( $saved, get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );

		$remove = new ReflectionMethod( AVC_AIS_Llms_Txt::class, 'remove_entries_of_deleted_posts' );
		avc_ais_test_make_accessible( $remove );
		$this->assertSame( $saved, $remove->invoke( null, $saved ) );
	}

	/**
	 * home_url() だけが別のドメインでも、option のドメインの行は URL で判定して隠すこと。
	 *
	 * @return void
	 */
	public function test_entries_on_the_option_domain_are_matched_by_url() {
		self::factory()->post->create(
			array(
				'post_title'  => '公開していない投稿',
				'post_name'   => 'secret-post',
				'post_status' => 'private',
			)
		);
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, '- [別の題](http://' . WP_TESTS_DOMAIN . '/secret-post/): 抜粋' );

		$map_domain = static function ( $url, $path ) {
			return 'http://en.example' . $path;
		};
		add_filter( 'home_url', $map_domain, 10, 2 );
		$served = $this->served();
		remove_filter( 'home_url', $map_domain, 10 );

		$this->assertSame( '', $served );
	}

	/**
	 * WP_HOME のドメインの行は、home_url() とデータベースの値が別のドメインでも隠すこと。
	 *
	 * @return void
	 */
	public function test_entries_on_the_wp_home_domain_are_site_entries() {
		self::factory()->post->create(
			array(
				'post_title'  => '同じタイトル',
				'post_status' => 'private',
			)
		);
		$wp_home    = static function () {
			return 'http://constant.example';
		};
		$map_domain = static function ( $url, $path ) {
			return 'http://mapped.example' . $path;
		};
		add_filter( 'option_home', $wp_home );
		add_filter( 'home_url', $map_domain, 10, 2 );
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, '- [同じタイトル](http://constant.example/docs/): このサイト' );
		$served = $this->served();
		remove_filter( 'home_url', $map_domain, 10 );
		remove_filter( 'option_home', $wp_home );

		$this->assertSame( '', $served );
	}

	/**
	 * 別のホストの URL は、パスがサイトのトップページと同じでもトップページとしないこと。
	 *
	 * @return void
	 */
	public function test_same_path_as_home_on_another_host_is_not_the_home_page() {
		update_option( 'home', 'http://' . WP_TESTS_DOMAIN . '/blog' );
		self::factory()->post->create(
			array(
				'post_title'  => '同じタイトル',
				'post_name'   => 'blog',
				'post_status' => 'private',
			)
		);
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, '- [同じタイトル](https://old-domain.example/blog/): 移転前' );

		$this->assertSame( '', $this->served() );
	}

	/**
	 * ホストとパスは同じ home で比べること。別の home のパスで範囲を広げない。
	 *
	 * @return void
	 */
	public function test_site_range_compares_host_and_path_of_the_same_home() {
		$host = WP_TESTS_DOMAIN;
		update_option( 'home', "http://{$host}/original" );
		self::factory()->post->create(
			array(
				'post_title'  => '同じタイトル',
				'post_status' => 'private',
			)
		);
		$map_domain = static function ( $url, $path ) {
			return 'http://mapped.example' . $path;
		};
		add_filter( 'home_url', $map_domain, 10, 2 );
		$saved = "- [同じタイトル](http://{$host}/shop/docs/): サイトの外";
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, $saved );
		$served = $this->served();
		remove_filter( 'home_url', $map_domain, 10 );

		$this->assertSame( $saved, $served );
	}

	/**
	 * Host ヘッダーから home を作るサイトでも、Host を変えて抜粋を取り出せないこと。
	 *
	 * @return void
	 */
	public function test_changed_host_does_not_reveal_hidden_entries() {
		self::factory()->post->create(
			array(
				'post_title'  => '同じタイトル',
				'post_name'   => 'secret-post',
				'post_status' => 'private',
			)
		);
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, '- [同じタイトル](http://' . WP_TESTS_DOMAIN . '/secret-post/): 抜粋' );

		$from_host = static function ( $url, $path ) {
			return 'http://attacker.example' . $path;
		};
		$wp_home   = static function () {
			return 'http://attacker.example';
		};
		add_filter( 'home_url', $from_host, 10, 2 );
		add_filter( 'option_home', $wp_home );
		$served = $this->served();

		// タイトルを書き換えた行も、データベースの home と比べて URL で判定する。
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, '- [別の題](http://' . WP_TESTS_DOMAIN . '/secret-post/): 抜粋' );
		$retitled = $this->served();
		remove_filter( 'option_home', $wp_home );
		remove_filter( 'home_url', $from_host, 10 );

		$this->assertSame( '', $served );
		$this->assertSame( '', $retitled );
	}

	/**
	 * ゴミ箱の投稿と、連続するスペースのタイトルも、タイトルとスラッグで一致すること。
	 *
	 * @return void
	 */
	public function test_title_and_slug_match_trashed_posts_and_collapsed_spaces() {
		$trashed = self::factory()->post->create(
			array(
				'post_title' => '捨てた記事',
				'post_name'  => 'trashed-post',
			)
		);
		wp_trash_post( $trashed );
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, '- [捨てた記事](https://old-domain.example/trashed-post/): 抜粋' );
		$this->assertSame( '', $this->served() );

		self::factory()->post->create(
			array(
				'post_title'  => 'スペース  二つ',
				'post_name'   => 'two-spaces',
				'post_status' => 'draft',
			)
		);
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, '- [スペース 二つ](https://old-domain.example/two-spaces/): 抜粋' );
		$this->assertSame( '', $this->served() );
	}

	/**
	 * ページの中へのリンクと空の URL は、サイトの場所にかかわらず隠さないこと。
	 *
	 * @return void
	 */
	public function test_anchor_and_empty_urls_are_never_hidden() {
		self::factory()->post->create(
			array(
				'post_title'  => '同じタイトル',
				'post_status' => 'draft',
			)
		);
		foreach ( array( 'http://' . WP_TESTS_DOMAIN, 'http://' . WP_TESTS_DOMAIN . '/blog' ) as $home ) {
			update_option( 'home', $home );
			foreach ( array( '#contact', '' ) as $url ) {
				$saved = '- [同じタイトル](' . $url . '): 投稿ではない';
				update_option( AVC_AIS_Llms_Txt::OPTION_NAME, $saved );
				$this->assertSame( $saved, $this->served(), $home . ' ' . $url );
			}
		}
	}

	/**
	 * 別のポートの ?p= の行は、このサイトの同じ ?p= の行があっても隠さないこと。
	 *
	 * @return void
	 */
	public function test_query_url_on_another_port_is_independent_of_other_lines() {
		$host    = WP_TESTS_DOMAIN;
		$post_id = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		$other   = "- [別の題](http://{$host}:8080/?p={$post_id}): 別のポート";
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, "- [別の題](http://{$host}/?p={$post_id}): このサイト\n{$other}" );

		$this->assertSame( $other, $this->served() );
	}

	/**
	 * 多言語プラグインが home_url() を変えても、トップページの行をタイトルで隠さないこと。
	 *
	 * @return void
	 */
	public function test_language_front_pages_are_not_judged_by_title() {
		$host = WP_TESTS_DOMAIN;
		self::factory()->post->create(
			array(
				'post_title'  => '同じタイトル',
				'post_status' => 'draft',
			)
		);
		$filters = array(
			static function ( $url, $path ) {
				return untrailingslashit( get_option( 'home' ) ) . '/ja' . $path;
			},
			static function ( $url, $path ) {
				return '' === $path || '/' === $path ? untrailingslashit( get_option( 'home' ) ) . '/?lang=en' : $url;
			},
		);
		$cases   = array(
			array( "http://{$host}/", "http://{$host}/ja/" ),
			array( "http://{$host}/", "http://{$host}/?lang=en" ),
		);
		foreach ( $filters as $index => $filter ) {
			add_filter( 'home_url', $filter, 10, 2 );
			foreach ( $cases[ $index ] as $url ) {
				$saved = '- [同じタイトル](' . $url . '): トップページ';
				update_option( AVC_AIS_Llms_Txt::OPTION_NAME, $saved );
				$this->assertSame( $saved, $this->served(), $url );
			}
			// ?p= など他のクエリの行はトップページではない。
			update_option( AVC_AIS_Llms_Txt::OPTION_NAME, "- [同じタイトル](http://{$host}/?x=1): トップページではない" );
			$this->assertSame( '', $this->served() );
			remove_filter( 'home_url', $filter, 10 );
		}
	}

	/**
	 * home_url() のドメインと違うドメインの行は、投稿の変更や削除で書き換えないこと。
	 *
	 * @return void
	 */
	public function test_entries_on_the_option_domain_survive_changes_on_a_filtered_domain() {
		$post_id = self::factory()->post->create(
			array(
				'post_title' => '同じタイトル',
				'post_name'  => 'docs',
			)
		);
		$other   = '- [手書き](http://' . WP_TESTS_DOMAIN . '/docs/): 別の言語のドメイン';
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, $other );

		$map_domain = static function ( $url, $path ) {
			return 'http://en.example' . $path;
		};
		add_filter( 'home_url', $map_domain, 10, 2 );
		wp_update_post(
			array(
				'ID'        => $post_id,
				'post_name' => 'docs-moved',
			)
		);
		$renamed = get_option( AVC_AIS_Llms_Txt::OPTION_NAME );
		wp_delete_post( $post_id, true );
		$deleted = get_option( AVC_AIS_Llms_Txt::OPTION_NAME );
		remove_filter( 'home_url', $map_domain, 10 );

		$this->assertSame( $other, $renamed );
		$this->assertSame( $other, $deleted );
	}

	/**
	 * 親ページの URL が変わったら、表記の違う子ページの行も書き換え、別のポートの行は残すこと。
	 *
	 * @return void
	 */
	public function test_child_entries_follow_the_parent_in_every_spelling() {
		$host   = WP_TESTS_DOMAIN;
		$parent = self::factory()->post->create(
			array(
				'post_type' => 'page',
				'post_name' => 'parent-page',
			)
		);
		$lines  = array(
			"- [子A](http://{$host}/parent-page/child/): 同じ表記",
			"- [子B](http://www.{$host}/parent-page/child/): www",
			"- [子C](http://{$host}:80/parent-page/child/): ポートの明示",
			'- [子D](/parent-page/child/): ホストなし',
			"- [子E](http://{$host}/parent-page/Child/?x=1#top): 大文字とクエリ",
			"- [子F](http://{$host}/PARENT-page/%E3%81%82/): エンコードの表記",
			"- [壊れた形](http://{$host}/parent-page//): 判別できない",
			"- [別のポート](http://{$host}:8080/parent-page/child/): 残す",
		);
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, implode( "\n", $lines ) );

		wp_update_post(
			array(
				'ID'        => $parent,
				'post_name' => 'renamed-page',
			)
		);

		$new = "http://{$host}/renamed-page";
		$this->assertSame(
			array(
				"- [子A]({$new}/child/): 同じ表記",
				"- [子B]({$new}/child/): www",
				"- [子C]({$new}/child/): ポートの明示",
				"- [子D]({$new}/child/): ホストなし",
				"- [子E]({$new}/Child/?x=1#top): 大文字とクエリ",
				"- [子F]({$new}/%E3%81%82/): エンコードの表記",
				$lines[6],
				$lines[7],
			),
			explode( "\n", get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) )
		);
	}

	/**
	 * 投稿に対応しないサイト内の URL は、同じタイトルの隠した投稿がなければ残ること。
	 *
	 * @return void
	 */
	public function test_site_entries_without_a_post_are_kept() {
		self::factory()->post->create(
			array(
				'post_title'  => '関係ない記事',
				'post_status' => 'private',
			)
		);
		$saved = '- [ブログ](' . home_url( '/blog/' ) . '): 記事の一覧';
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, $saved );

		$this->assertSame( $saved, $this->served() );
	}

	/**
	 * スラッグを変えると行の URL も新しくなり、そのあと隠しても行が隠れること。
	 *
	 * @return void
	 */
	public function test_changing_slug_updates_entry_url() {
		$post_id = $this->create_post_with_entry();

		wp_update_post(
			array(
				'ID'        => $post_id,
				'post_name' => 'renamed',
			)
		);

		$url = get_permalink( $post_id );
		$this->assertStringContainsString( 'renamed', $url, '前提: URL が変わる' );
		$this->assertSame( "- [対象の記事]({$url}): 手書きの説明\n# 見出し", get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'private',
			)
		);

		$this->assertSame( '# 見出し', $this->served() );
	}

	/**
	 * 下書きに戻している間にスラッグとタイトルを変えても、行が隠れて新しい URL になること。
	 *
	 * @return void
	 */
	public function test_changing_slug_while_unpublished_updates_entry_url() {
		$post_id = $this->create_post_with_entry();

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'draft',
			)
		);
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => '新しいタイトル',
				'post_name'  => 'renamed',
			)
		);
		$this->assertSame( '# 見出し', $this->served(), '下書きの間は隠れる' );

		wp_publish_post( $post_id );

		$url = get_permalink( $post_id );
		$this->assertStringContainsString( 'renamed', $url, '前提: URL が変わる' );
		$this->assertSame( "- [対象の記事]({$url}): 手書きの説明\n# 見出し", $this->served() );
	}

	/**
	 * 下書きのスラッグを変えても、同じ URL の公開中の投稿の行を書き換えないこと。
	 *
	 * @return void
	 */
	public function test_changing_draft_slug_keeps_public_entry() {
		$this->create_post_with_entry();
		$saved = get_option( AVC_AIS_Llms_Txt::OPTION_NAME );
		$draft = self::factory()->post->create(
			array(
				'post_title'  => '下書き',
				'post_name'   => 'target',
				'post_status' => 'draft',
			)
		);

		wp_update_post(
			array(
				'ID'        => $draft,
				'post_name' => 'renamed',
			)
		);

		$this->assertSame( $saved, get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
	}

	/**
	 * パーマリンクの設定を変えて URL が古くなった行も、タイトルで隠せること。
	 *
	 * @return void
	 */
	public function test_entry_with_stale_url_is_hidden_by_title() {
		$post_id = $this->create_post_with_entry();

		$this->set_permalink_structure( '/%year%/%postname%/' );
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'private',
			)
		);
		$this->set_permalink_structure( '/articles/%post_id%/' );

		$this->assertSame( '# 見出し', $this->served() );
	}

	/**
	 * 無効にしている間に隠した投稿の行も隠れること。
	 *
	 * @return void
	 */
	public function test_post_hidden_while_deactivated_is_hidden() {
		global $wpdb;

		$post_id = $this->create_post_with_entry();

		AVC_AIS_Llms_Txt::deactivate();

		// 無効の間はフックが動かないので、データベースを直接更新する。
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update( $wpdb->posts, array( 'post_status' => 'draft' ), array( 'ID' => $post_id ) );
		clean_post_cache( $post_id );

		$this->assertSame( '# 見出し', $this->served() );
	}

	/**
	 * 無効の間に隠した投稿の行が、再有効化の直後に保存した判定結果で配信されないこと。
	 *
	 * @return void
	 */
	public function test_activation_ignores_cached_result_from_before_deactivation() {
		global $wpdb;

		$post_id = $this->create_post_with_entry();
		$this->assertStringContainsString( '手書きの説明', $this->served(), '前提: 判定して結果を保存する' );

		AVC_AIS_Llms_Txt::deactivate();

		// 無効の間はフックが動かないので、データベースを直接更新する。
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update( $wpdb->posts, array( 'post_status' => 'private' ), array( 'ID' => $post_id ) );
		clean_post_cache( $post_id );

		AVC_AIS_Llms_Txt::activate();

		$this->assertSame( '# 見出し', $this->served() );
	}

	/**
	 * 配信は保存済みの内容を変更せず、隠した投稿の数に関係なく行数分の処理で済むこと。
	 *
	 * @return void
	 */
	public function test_serving_is_read_only_and_does_not_walk_hidden_posts() {
		global $wpdb;

		self::factory()->post->create_many(
			210,
			array(
				'post_status'   => 'publish',
				'post_password' => 'hunter2',
			)
		);
		$post_id = $this->create_post_with_entry();
		$this->assertStringContainsString( '手書きの説明', $this->served(), '前提: 公開中は配信する' );
		wp_update_post(
			array(
				'ID'            => $post_id,
				'post_password' => 'hunter2',
			)
		);
		$saved = get_option( AVC_AIS_Llms_Txt::OPTION_NAME );

		$before = $wpdb->num_queries;
		$served = $this->served();
		$count  = $wpdb->num_queries - $before;

		$this->assertSame( '# 見出し', $served );
		$this->assertLessThan( 10, $count, '隠した投稿を全件たどらない' );
		$this->assertSame( $saved, get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
		$this->assertFalse( get_option( AVC_AIS_Llms_Txt::OPTION_URLS_REPAIRED ), '配信では修復しない' );
	}

	/**
	 * 保存していなければ自動生成の内容を配信すること。
	 *
	 * @return void
	 */
	public function test_generated_content_is_served_when_nothing_is_saved() {
		self::factory()->post->create( array( 'post_title' => '公開の記事' ) );
		$llms = AVC_AIS_Llms_Txt::init();

		$this->assertStringContainsString( '公開の記事', $this->served() );
		$this->assertSame( $llms->generate_default_content(), $this->served() );
		$this->assertSame( array(), $llms->get_hidden_entry_titles() );
	}

	/**
	 * 以前の保存処理で URL が壊れた行も、隠した投稿のタイトルで隠せること。
	 *
	 * 壊れた形が同じ公開中の投稿の行は巻き込まない。
	 *
	 * @return void
	 */
	public function test_legacy_entry_is_hidden_by_title_and_keeps_other_entries() {
		$protected_id = self::factory()->post->create(
			array(
				'post_title'    => 'apple',
				'post_name'     => 'あ',
				'post_password' => 'hunter2',
			)
		);
		$public_id    = self::factory()->post->create(
			array(
				'post_title' => 'Apple',
				'post_name'  => 'い',
			)
		);
		$broken       = sanitize_textarea_field( get_permalink( $protected_id ) );
		$this->assertSame( $broken, sanitize_textarea_field( get_permalink( $public_id ) ), '前提: 壊れた URL が衝突する' );

		$public_line = "- [Apple]({$broken}): 公開の抜粋";
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, "- [apple]({$broken}): 秘密\n{$public_line}" );

		$this->assertSame( $public_line, $this->served() );
	}

	/**
	 * 同じタイトルの公開中と隠した投稿が壊れた形でも衝突すると、どちらの抜粋か判断できないので隠すこと。
	 *
	 * 以前は隠した投稿の行を消すと公開中の投稿の行も消えていた。消さずに隠すので、修復もしない。
	 *
	 * @return void
	 */
	public function test_ambiguous_legacy_entries_are_hidden_but_not_repaired_or_removed() {
		$protected_id = self::factory()->post->create(
			array(
				'post_title'    => 'お知らせ',
				'post_name'     => 'あ',
				'post_password' => 'hunter2',
			)
		);
		self::factory()->post->create(
			array(
				'post_title' => 'お知らせ',
				'post_name'  => 'い',
			)
		);
		$saved = '- [お知らせ](' . sanitize_textarea_field( get_permalink( $protected_id ) ) . '): 抜粋';
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, $saved );

		AVC_AIS_Llms_Txt::init()->maybe_repair_saved_urls();

		$this->assertSame( '', $this->served() );
		$this->assertSame( $saved, get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
	}

	/**
	 * URL の修復は隠した投稿の行も正しい URL へ直し、配信はしないこと。
	 *
	 * 公開すれば正しいリンクとして配信に戻る。
	 *
	 * @return void
	 */
	public function test_repair_fixes_hidden_entry_without_serving_it() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => '下書きの記事',
				'post_name'   => 'あい',
				'post_status' => 'draft',
			)
		);
		$llms    = AVC_AIS_Llms_Txt::init();
		update_option(
			AVC_AIS_Llms_Txt::OPTION_NAME,
			'- [下書きの記事](' . home_url( '/' ) . "/): 秘密の本文\n# 見出し"
		);

		$llms->maybe_repair_saved_urls();
		$this->assertSame( '# 見出し', $this->served() );

		wp_publish_post( $post_id );
		$url = get_permalink( $post_id );
		$this->assertStringContainsString( '%e3%81%82', $url, '前提: 日本語スラッグの URL' );
		$this->assertSame( "- [下書きの記事]({$url}): 秘密の本文\n# 見出し", $this->served() );
	}

	/**
	 * 連続するスペースのあるタイトルの保護された投稿も、修復のあとは隠れること。
	 *
	 * 以前の保存処理は連続するスペースをまとめていたので、配信時のタイトルの照合では見つからない。
	 *
	 * @return void
	 */
	public function test_repair_finds_legacy_entry_whose_title_has_double_spaces() {
		$protected_id = self::factory()->post->create(
			array(
				'post_title'    => 'Members  only',
				'post_name'     => 'あ',
				'post_password' => 'hunter2',
			)
		);
		$legacy       = sanitize_textarea_field( '- [Members  only](' . get_permalink( $protected_id ) . "): secret\n# 見出し" );
		$this->assertStringContainsString( '- [Members only]', $legacy, '前提: スペースがまとめられる' );
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, $legacy );

		AVC_AIS_Llms_Txt::init()->maybe_repair_saved_urls();

		$this->assertSame( '# 見出し', $this->served() );
	}

	/**
	 * タイトルの & が &amp; で保存された投稿も、照合と修復ができること。
	 *
	 * @return void
	 */
	public function test_titles_with_entities_are_matched() {
		global $wpdb;

		$post_id = self::factory()->post->create(
			array(
				'post_title' => 'Q and A',
				'post_name'  => 'あい',
			)
		);
		// 権限のないユーザーの投稿は kses で &amp; に変わる。
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update( $wpdb->posts, array( 'post_title' => 'Q &amp; A' ), array( 'ID' => $post_id ) );
		clean_post_cache( $post_id );

		$url    = get_permalink( $post_id );
		$broken = sanitize_textarea_field( $url );
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, "- [Q & A]({$broken}): 抜粋" );

		AVC_AIS_Llms_Txt::init()->maybe_repair_saved_urls();
		$this->assertSame( "- [Q & A]({$url}): 抜粋", get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );

		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, "- [Q & A]({$broken}): 秘密" );
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'private',
			)
		);

		$this->assertSame( '', $this->served() );
	}

	/**
	 * URL の修復が途中で失敗しても、繰り返さないこと。
	 *
	 * @return void
	 */
	public function test_failed_repair_is_not_retried() {
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, '- [記事](' . home_url( '/' ) . '/): 抜粋' );

		$fail = static function ( $query ) {
			if ( $query->get( 'title' ) ) {
				throw new RuntimeException( 'time limit' );
			}
		};
		add_action( 'pre_get_posts', $fail );

		try {
			AVC_AIS_Llms_Txt::init()->maybe_repair_saved_urls();
			$this->fail( '前提: 修復が途中で止まる' );
		} catch ( RuntimeException $e ) {
			$this->assertSame( 'time limit', $e->getMessage() );
		}

		$this->assertNotEmpty( get_option( AVC_AIS_Llms_Txt::OPTION_URLS_REPAIRED ) );

		// 二度目は処理しないので失敗しない。
		AVC_AIS_Llms_Txt::init()->maybe_repair_saved_urls();
		remove_action( 'pre_get_posts', $fail );
	}

	/**
	 * v1.3.2 の掃除の記録は修復のときに消すこと。
	 *
	 * @return void
	 */
	public function test_repair_removes_old_scrub_flag() {
		update_option( AVC_AIS_Llms_Txt::OPTION_SCRUBBED, true );

		AVC_AIS_Llms_Txt::init()->maybe_repair_saved_urls();

		$this->assertFalse( get_option( AVC_AIS_Llms_Txt::OPTION_SCRUBBED ) );
		$this->assertNotEmpty( get_option( AVC_AIS_Llms_Txt::OPTION_URLS_REPAIRED ) );
	}

	/**
	 * 権限のないリクエストでは、一度きりの処理を実行しないこと。
	 *
	 * admin_init は admin-ajax.php への未ログインのリクエストでも動く。
	 *
	 * @dataProvider provide_users_without_permission
	 *
	 * @param string $role 権限グループ（空なら未ログイン）.
	 * @return void
	 */
	public function test_one_time_tasks_need_permission( $role ) {
		$broken = '- [記事](' . home_url( '/' ) . '/): 抜粋';
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, $broken );
		wp_set_current_user( $role ? self::factory()->user->create( array( 'role' => $role ) ) : 0 );

		$llms = AVC_AIS_Llms_Txt::init();
		$llms->restore_missing_rewrite_rule();
		$llms->maybe_repair_saved_urls();

		$this->assertFalse( get_option( AVC_AIS_Llms_Txt::OPTION_RULE_RESTORED ) );
		$this->assertFalse( get_option( AVC_AIS_Llms_Txt::OPTION_URLS_REPAIRED ) );

		wp_set_current_user( $this->admin_id );
		$llms->restore_missing_rewrite_rule();
		$llms->maybe_repair_saved_urls();

		$this->assertNotEmpty( get_option( AVC_AIS_Llms_Txt::OPTION_RULE_RESTORED ) );
		$this->assertNotEmpty( get_option( AVC_AIS_Llms_Txt::OPTION_URLS_REPAIRED ) );
	}

	/**
	 * 一度きりの処理の権限がないユーザーを返す。
	 *
	 * @return array<string,array<int,string>>
	 */
	public function provide_users_without_permission() {
		return array(
			'anonymous' => array( '' ),
			'editor'    => array( 'editor' ),
		);
	}

	/**
	 * 改行の種類によって保存される内容が変わらないこと。
	 *
	 * sanitize_textarea_field() は行末の < の扱いが改行コードで変わる。
	 *
	 * @return void
	 */
	public function test_sanitize_content_does_not_depend_on_newline_style() {
		$lf = "x <\ny\nz";

		$this->assertSame(
			AVC_AIS_Llms_Txt::sanitize_content( $lf ),
			AVC_AIS_Llms_Txt::sanitize_content( str_replace( "\n", "\r\n", $lf ) )
		);
		$this->assertStringNotContainsString( "\r", AVC_AIS_Llms_Txt::sanitize_content( "a\r\nb\rc" ) );
	}

	/**
	 * ネットワーク全体の無効化で他のサイトのルールも消え、再有効化で戻ること。
	 *
	 * @group ms-required
	 *
	 * @return void
	 */
	public function test_network_deactivation_and_activation_update_all_sites() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'マルチサイト環境でのみ実行する。' );
		}

		$other    = array( 'other/?$' => 'index.php?other=1' );
		$llms     = array( AVC_AIS_Llms_Txt::REWRITE_REGEX => 'index.php?llms_txt=1' );
		$site_id  = self::factory()->blog->create();
		$disabled = self::factory()->blog->create();
		foreach ( array( $site_id, $disabled ) as $id ) {
			switch_to_blog( $id );
			update_option( 'rewrite_rules', $llms + $other );
			restore_current_blog();
		}

		AVC_AIS_Llms_Txt::deactivate( true );

		switch_to_blog( $site_id );
		$this->assertSame( $other, get_option( 'rewrite_rules' ) );
		restore_current_blog();

		switch_to_blog( $disabled );
		update_option( AVC_AIS_Llms_Txt::OPTION_ENABLED, '0' );
		restore_current_blog();

		AVC_AIS_Llms_Txt::activate( true );

		switch_to_blog( $site_id );
		$this->assertSame( $llms + $other, get_option( 'rewrite_rules' ) );
		restore_current_blog();

		switch_to_blog( $disabled );
		$this->assertSame( $other, get_option( 'rewrite_rules' ), 'llms.txt を無効にしたサイトには追加しない' );
		restore_current_blog();
	}

	/**
	 * サイト単位で有効にしているサイトは、ネットワーク全体の無効化でもルールを残すこと。
	 *
	 * @group ms-required
	 *
	 * @return void
	 */
	public function test_network_deactivation_keeps_sites_with_plugin_active() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'マルチサイト環境でのみ実行する。' );
		}

		$rules   = array( AVC_AIS_Llms_Txt::REWRITE_REGEX => 'index.php?llms_txt=1' );
		$site_id = self::factory()->blog->create();
		switch_to_blog( $site_id );
		update_option( 'rewrite_rules', $rules );
		update_option( 'active_plugins', array( plugin_basename( AVC_AIS_FILE ) ) );
		restore_current_blog();

		AVC_AIS_Llms_Txt::deactivate( true );

		switch_to_blog( $site_id );
		$this->assertSame( $rules, get_option( 'rewrite_rules' ) );
		restore_current_blog();
	}

	/**
	 * 現在のサイトでサイト単位で有効なら、ネットワーク全体の無効化でルールを残すこと。
	 *
	 * @group ms-required
	 *
	 * @return void
	 */
	public function test_network_deactivation_keeps_current_site_with_plugin_active() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'マルチサイト環境でのみ実行する。' );
		}

		AVC_AIS_Llms_Txt::activate();
		update_option( 'active_plugins', array( plugin_basename( AVC_AIS_FILE ) ) );

		AVC_AIS_Llms_Txt::deactivate( true );

		$this->assertArrayHasKey( AVC_AIS_Llms_Txt::REWRITE_REGEX, get_option( 'rewrite_rules' ) );
	}

	/**
	 * 別のネットワークのサイトのルールに触れないこと。
	 *
	 * @group ms-required
	 *
	 * @return void
	 */
	public function test_network_deactivation_keeps_other_networks() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'マルチサイト環境でのみ実行する。' );
		}

		$rules   = array( AVC_AIS_Llms_Txt::REWRITE_REGEX => 'index.php?llms_txt=1' );
		$site_id = self::factory()->blog->create( array( 'network_id' => 999 ) );
		switch_to_blog( $site_id );
		update_option( 'rewrite_rules', $rules );
		restore_current_blog();

		AVC_AIS_Llms_Txt::deactivate( true );

		switch_to_blog( $site_id );
		$this->assertSame( $rules, get_option( 'rewrite_rules' ) );
		restore_current_blog();
	}

	/**
	 * マルチサイトで、同じホストの別のサイトへの行を隠さないこと（メインサイトと子サイトの両方）。
	 *
	 * @return void
	 */
	public function test_multisite_entries_of_sibling_sites_are_never_hidden() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'マルチサイトでのみ実行する。' );
		}

		$site_a = self::factory()->blog->create( array( 'path' => '/site-a/' ) );
		$site_b = self::factory()->blog->create( array( 'path' => '/site-b/' ) );
		$host   = get_site( $site_a )->domain;

		$hide_on = static function () {
			self::factory()->post->create(
				array(
					'post_title'  => '同じタイトル',
					'post_status' => 'private',
				)
			);
		};

		// 子サイトから見た、別の子サイトとメインサイト。
		switch_to_blog( $site_a );
		$hide_on();
		foreach ( array( "http://{$host}/site-b/docs/", "http://{$host}/docs/", "http://{$host}/" ) as $url ) {
			$saved = '- [同じタイトル](' . $url . '): 別のサイト';
			update_option( AVC_AIS_Llms_Txt::OPTION_NAME, $saved );
			$this->assertSame( $saved, $this->served(), $url );
		}
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, "- [同じタイトル](http://{$host}/site-a/docs/): 自分のサイト" );
		$this->assertSame( '', $this->served() );
		restore_current_blog();

		// メインサイトから見た子サイト。パスはメインサイトの範囲の中にある。
		$hide_on();
		$siblings = array(
			"http://{$host}/site-b/docs/",
			"http://{$host}/SITE-B/docs/",
			"http://{$host}:80/site-b/docs/",
			"https://{$host}:443/site-b/docs/",
			"http://www.{$host}/site-b/docs/",
			'/site-b/docs/',
		);
		foreach ( $siblings as $url ) {
			$saved = '- [同じタイトル](' . $url . '): 別のサイト';
			update_option( AVC_AIS_Llms_Txt::OPTION_NAME, $saved );
			$this->assertSame( $saved, $this->served(), $url );
		}
		// 相対パスと、以前の保存処理で壊れた // は、子サイトと比べられないので隠す。
		foreach ( array( 'site-b/docs/', "http://{$host}//site-b/" ) as $url ) {
			update_option( AVC_AIS_Llms_Txt::OPTION_NAME, '- [同じタイトル](' . $url . '): このサイト' );
			$this->assertSame( '', $this->served(), $url );
		}
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, "- [同じタイトル](http://{$host}/docs/): 自分のサイト" );
		$this->assertSame( '', $this->served() );
		// スラッグの一部が同じだけの URL は子サイトではない。
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, "- [同じタイトル](http://{$host}/site-b-docs/): 自分のサイト" );
		$this->assertSame( '', $this->served() );

		wp_delete_site( $site_a );
		wp_delete_site( $site_b );
	}

	/**
	 * 子サイトの home をサイトのパスの外へ変えていても、別のサイトは別のサイトとして判定すること。
	 *
	 * @return void
	 */
	public function test_multisite_child_site_with_a_moved_home() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'マルチサイトでのみ実行する。' );
		}

		$site_a = self::factory()->blog->create( array( 'path' => '/site-a/' ) );
		$site_b = self::factory()->blog->create( array( 'path' => '/site-b/' ) );
		$site_c = self::factory()->blog->create( array( 'path' => '/c/' ) );
		$host   = get_site( $site_a )->domain;

		switch_to_blog( $site_a );
		self::factory()->post->create(
			array(
				'post_title'  => '同じタイトル',
				'post_status' => 'private',
			)
		);
		$served = array();
		foreach ( array( "http://{$host}/custom", "http://{$host}" ) as $home ) {
			update_option( 'home', $home );
			$served[ $home ] = array();
			foreach ( array( '/custom/docs/', '/site-a/docs/', '/site-b/docs/', '/c/docs/' ) as $path ) {
				update_option( AVC_AIS_Llms_Txt::OPTION_NAME, "- [同じタイトル](http://{$host}{$path}): 確認" );
				$served[ $home ][ $path ] = '' === $this->served();
			}
		}
		restore_current_blog();
		wp_delete_site( $site_a );
		wp_delete_site( $site_b );
		wp_delete_site( $site_c );

		$this->assertSame(
			array(
				'/custom/docs/' => true,
				'/site-a/docs/' => false,
				'/site-b/docs/' => false,
				'/c/docs/'      => false,
			),
			$served[ "http://{$host}/custom" ]
		);
		$this->assertSame(
			array(
				'/custom/docs/' => true,
				'/site-a/docs/' => true,
				'/site-b/docs/' => false,
				'/c/docs/'      => false,
			),
			$served[ "http://{$host}" ]
		);
	}

	/**
	 * ?p= の URL は、別のサイトのものなら ID が同じでも投稿と照合しないこと。
	 *
	 * @return void
	 */
	public function test_multisite_query_urls_of_sibling_sites_are_not_matched_by_id() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'マルチサイトでのみ実行する。' );
		}

		$site_b  = self::factory()->blog->create( array( 'path' => '/site-b/' ) );
		$host    = get_site( $site_b )->domain;
		$post_id = self::factory()->post->create( array( 'post_status' => 'draft' ) );

		$saved = "- [別のタイトル](http://{$host}/site-b/?p={$post_id}): 別のサイト";
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, $saved );
		$sibling = $this->served();
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, "- [別のタイトル](http://{$host}/?p={$post_id}): このサイト" );
		$own = $this->served();
		wp_delete_site( $site_b );

		$this->assertSame( $saved, $sibling );
		$this->assertSame( '', $own );
	}

	/**
	 * ネットワークにないドメインへ home_url() を変えたサイトの行は、子サイトと同じパスでも隠すこと。
	 *
	 * @return void
	 */
	public function test_multisite_mapped_domain_is_not_compared_with_network_sites() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'マルチサイトでのみ実行する。' );
		}

		$site_a = self::factory()->blog->create( array( 'path' => '/site-a/' ) );
		$site_b = self::factory()->blog->create( array( 'path' => '/long-site-name/' ) );

		switch_to_blog( $site_a );
		self::factory()->post->create(
			array(
				'post_title'  => '同じタイトル',
				'post_status' => 'private',
			)
		);
		$map_domain = static function ( $url, $path ) {
			return 'http://mapped.example' . $path;
		};
		add_filter( 'home_url', $map_domain, 10, 2 );
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, '- [同じタイトル](http://mapped.example/long-site-name/docs/): このサイト' );
		$served = $this->served();
		remove_filter( 'home_url', $map_domain, 10 );
		restore_current_blog();
		wp_delete_site( $site_a );
		wp_delete_site( $site_b );

		$this->assertSame( '', $served );
	}

	/**
	 * メインサイトのページのパスが子サイトと重なっても、公開していないページの行は隠すこと。
	 *
	 * WordPress は子サイトと同じスラッグのページを作らせないが、ページの後に作る子サイトは防がない。
	 *
	 * @return void
	 */
	public function test_multisite_private_page_under_a_child_site_path_is_hidden() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'マルチサイトでのみ実行する。' );
		}

		self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'お知らせ',
				'post_name'   => 'news',
				'post_status' => 'private',
			)
		);
		$site_news = self::factory()->blog->create( array( 'path' => '/news/' ) );
		$host      = get_site( $site_news )->domain;
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, "- [お知らせ](http://{$host}/news/): 抜粋" );
		$served = $this->served();
		wp_delete_site( $site_news );

		$this->assertSame( '', $served );
	}

	/**
	 * 子サイトの行は、パスの重なるメインサイトのページが変わっても書き換えないこと。
	 *
	 * @return void
	 */
	public function test_multisite_sibling_entries_survive_page_changes() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'マルチサイトでのみ実行する。' );
		}

		$page_id = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'お知らせ',
				'post_name'  => 'news',
			)
		);
		$site    = self::factory()->blog->create( array( 'path' => '/news/' ) );
		$saved   = '- [子サイトの記事](http://' . get_site( $site )->domain . '/news/foo/): 別のサイト';
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, $saved );

		wp_update_post(
			array(
				'ID'        => $page_id,
				'post_name' => 'press',
			)
		);
		$after = get_option( AVC_AIS_Llms_Txt::OPTION_NAME );
		wp_delete_site( $site );

		$this->assertSame( $saved, $after );
	}

	/**
	 * パスの重なるメインサイトのページの行は、URL が完全に一致するので書き換えて消すこと。
	 *
	 * 別のサイトと判定して残すと、公開していないページや削除したページの抜粋を配信してしまう。
	 *
	 * @return void
	 */
	public function test_multisite_own_page_entry_under_a_child_site_path_follows_the_page() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'マルチサイトでのみ実行する。' );
		}

		$page_id = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'お知らせ',
				'post_name'  => 'news',
			)
		);
		$site    = self::factory()->blog->create( array( 'path' => '/news/' ) );
		$host    = get_site( $site )->domain;
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, "- [お知らせ](http://{$host}/news/): 抜粋" );

		wp_update_post(
			array(
				'ID'        => $page_id,
				'post_name' => 'press',
			)
		);
		$renamed = get_option( AVC_AIS_Llms_Txt::OPTION_NAME );

		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, "- [お知らせ](http://{$host}/press/): 抜粋" );
		wp_delete_post( $page_id, true );
		$deleted = get_option( AVC_AIS_Llms_Txt::OPTION_NAME );
		wp_delete_site( $site );

		$this->assertSame( "- [お知らせ](http://{$host}/press/): 抜粋", $renamed );
		$this->assertSame( '', (string) $deleted );
	}

	/**
	 * 別のサイトの行は、タイトルとスラッグが公開していない投稿と同じでも隠さないこと。
	 *
	 * 同じひな形から作ったサイトでは、タイトルとスラッグが同じになる。
	 *
	 * @return void
	 */
	public function test_multisite_sibling_entries_with_the_same_title_and_slug_are_kept() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'マルチサイトでのみ実行する。' );
		}

		self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => '会社概要',
				'post_name'   => 'about',
				'post_status' => 'draft',
			)
		);
		$site  = self::factory()->blog->create( array( 'path' => '/site-b/' ) );
		$saved = '- [会社概要](http://' . get_site( $site )->domain . '/site-b/about/): 別のサイト';
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, $saved );
		$served = $this->served();
		wp_delete_site( $site );

		$this->assertSame( $saved, $served );
	}

	/**
	 * 子サイトから、同じ長さのパスの子サイトとメインサイトへの行を隠さないこと。
	 *
	 * @return void
	 */
	public function test_multisite_child_site_keeps_entries_of_other_sites_with_the_same_title_and_slug() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'マルチサイトでのみ実行する。' );
		}

		$site_a = self::factory()->blog->create( array( 'path' => '/site-a/' ) );
		$site_b = self::factory()->blog->create( array( 'path' => '/site-b/' ) );
		$host   = get_site( $site_a )->domain;

		switch_to_blog( $site_a );
		self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => '会社概要',
				'post_name'   => 'about',
				'post_status' => 'draft',
			)
		);
		$served = array();
		foreach ( array( "http://{$host}/site-b/about/", "http://{$host}/about/" ) as $url ) {
			update_option( AVC_AIS_Llms_Txt::OPTION_NAME, '- [会社概要](' . $url . '): 別のサイト' );
			$served[ $url ] = '' !== $this->served();
		}
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, "- [会社概要](http://{$host}/site-a/old/about/): このサイト" );
		$own = $this->served();
		// 別のポートはネットワークのサイトではないので、移転前の URL として隠す。
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, "- [会社概要](http://{$host}:8443/site-b/about/): 移転前" );
		$other_port = $this->served();
		restore_current_blog();
		wp_delete_site( $site_a );
		wp_delete_site( $site_b );

		$this->assertSame(
			array(
				"http://{$host}/site-b/about/" => true,
				"http://{$host}/about/"        => true,
			),
			$served
		);
		$this->assertSame( '', $own );
		$this->assertSame( '', $other_port );
	}

	/**
	 * サブドメイン型のように別のホストの別のサイトも、タイトルとスラッグが同じでも隠さないこと。
	 *
	 * @return void
	 */
	public function test_multisite_entries_of_sites_on_other_hosts_are_kept() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'マルチサイトでのみ実行する。' );
		}

		self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => '会社概要',
				'post_name'   => 'about',
				'post_status' => 'draft',
			)
		);
		$site  = self::factory()->blog->create(
			array(
				'domain' => 'b.example.org',
				'path'   => '/',
			)
		);
		$saved = '- [会社概要](http://b.example.org/about/): 別のサイト';
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, $saved );
		$served = $this->served();
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, '- [会社概要](https://not-in-network.example/about/): 移転前' );
		$moved = $this->served();
		wp_delete_site( $site );

		$this->assertSame( $saved, $served );
		$this->assertSame( '', $moved );
	}

	/**
	 * ホストのない URL は、home_url() のドメインがネットワークになければ子サイトと比べないこと。
	 *
	 * @return void
	 */
	public function test_multisite_root_relative_entry_on_a_mapped_domain_is_hidden() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'マルチサイトでのみ実行する。' );
		}

		$site_a = self::factory()->blog->create( array( 'path' => '/site-a/' ) );
		$site_b = self::factory()->blog->create( array( 'path' => '/long-site-name/' ) );

		switch_to_blog( $site_a );
		self::factory()->post->create(
			array(
				'post_title'  => '同じタイトル',
				'post_status' => 'private',
			)
		);
		$map_domain = static function ( $url, $path ) {
			return 'http://mapped.example' . $path;
		};
		add_filter( 'home_url', $map_domain, 10, 2 );
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, '- [同じタイトル](/long-site-name/docs/): このサイト' );
		$served = $this->served();
		remove_filter( 'home_url', $map_domain, 10 );
		restore_current_blog();
		wp_delete_site( $site_a );
		wp_delete_site( $site_b );

		$this->assertSame( '', $served );
	}

	/**
	 * ポートのあるネットワークでも、同じホストの別のサイトへの行を隠さないこと。
	 *
	 * @return void
	 */
	public function test_multisite_with_a_port_keeps_sibling_entries() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'マルチサイトでのみ実行する。' );
		}

		$args   = array( 'domain' => 'ms.example:8888' );
		$main   = self::factory()->blog->create( $args + array( 'path' => '/' ) );
		$sister = self::factory()->blog->create( $args + array( 'path' => '/site-b/' ) );
		$domain = get_site( $main )->domain;
		if ( 'ms.example:8888' !== $domain ) {
			wp_delete_site( $main );
			wp_delete_site( $sister );
			$this->markTestSkipped( 'このバージョンの wp_insert_site() はドメインのポートを保持しない。' );
		}

		switch_to_blog( $main );
		self::factory()->post->create(
			array(
				'post_title'  => '同じタイトル',
				'post_status' => 'private',
			)
		);
		$saved = '- [同じタイトル](http://ms.example:8888/site-b/docs/): 別のサイト';
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, $saved );
		$sibling = $this->served();
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, '- [同じタイトル](http://ms.example:8888/docs/): このサイト' );
		$own = $this->served();
		restore_current_blog();
		wp_delete_site( $main );
		wp_delete_site( $sister );

		$this->assertSame( $saved, $sibling );
		$this->assertSame( '', $own );
	}

	/**
	 * 通常の無効化では他のサイトのルールに触れないこと。
	 *
	 * @group ms-required
	 *
	 * @return void
	 */
	public function test_single_site_deactivation_keeps_other_sites() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'マルチサイト環境でのみ実行する。' );
		}

		$rules   = array( AVC_AIS_Llms_Txt::REWRITE_REGEX => 'index.php?llms_txt=1' );
		$site_id = self::factory()->blog->create();
		switch_to_blog( $site_id );
		update_option( 'rewrite_rules', $rules );
		restore_current_blog();

		AVC_AIS_Llms_Txt::deactivate();

		switch_to_blog( $site_id );
		$this->assertSame( $rules, get_option( 'rewrite_rules' ) );
		restore_current_blog();
	}
}

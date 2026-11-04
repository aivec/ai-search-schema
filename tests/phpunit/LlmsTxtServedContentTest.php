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
	 * ネットワーク全体の無効化で他のサイトのルールも消え、再有効化で戻せること。
	 *
	 * @group ms-required
	 *
	 * @return void
	 */
	public function test_network_deactivation_updates_all_sites() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'マルチサイト環境でのみ実行する。' );
		}

		$rules   = array(
			AVC_AIS_Llms_Txt::REWRITE_REGEX => 'index.php?llms_txt=1',
			'other/?$'                      => 'index.php?other=1',
		);
		$site_id = self::factory()->blog->create();
		switch_to_blog( $site_id );
		update_option( 'rewrite_rules', $rules );
		update_option( AVC_AIS_Llms_Txt::OPTION_RULE_RESTORED, true );
		restore_current_blog();

		AVC_AIS_Llms_Txt::deactivate( true );

		switch_to_blog( $site_id );
		$this->assertSame( array( 'other/?$' => 'index.php?other=1' ), get_option( 'rewrite_rules' ) );
		$this->assertFalse( get_option( AVC_AIS_Llms_Txt::OPTION_RULE_RESTORED ), '再有効化のあとルールを戻せる' );
		restore_current_blog();
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

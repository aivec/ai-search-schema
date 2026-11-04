<?php
/**
 * パスワード保護された投稿の本文が公開の出力へ漏れないことのテスト。
 *
 * @package Aivec\AiSearchSchema\Tests
 */

require_once __DIR__ . '/bootstrap.php';

/**
 * パスワード保護コンテンツのテストクラス。
 */
class PasswordProtectedContentTest extends WP_UnitTestCase {

	/**
	 * 本文に埋め込む目印。
	 */
	const SECRET = 'PROTECTEDBODY';

	/**
	 * テスト前のセットアップ。
	 */
	protected function setUp(): void {
		parent::setUp();
		require_once AVC_AIS_DIR . 'includes/class-avc-ais-llms-txt.php';
		delete_option( AVC_AIS_Llms_Txt::OPTION_NAME );
		delete_option( AVC_AIS_Llms_Txt::OPTION_SCRUBBED );
		update_option(
			'avc_ais_options',
			array(
				'company_name'     => 'Test Company',
				'logo_url'         => 'http://example.com/logo.png',
				'site_url'         => 'http://example.com',
				'languages'        => array( 'en' ),
				'content_model'    => 'WebPage',
				'avc_ais_priority' => 'ais',
			)
		);
		$this->reset_singletons();
		wp_set_current_user( 0 );
	}

	/**
	 * テスト後のクリーンアップ。
	 */
	protected function tearDown(): void {
		delete_option( AVC_AIS_Llms_Txt::OPTION_NAME );
		delete_option( AVC_AIS_Llms_Txt::OPTION_SCRUBBED );
		delete_option( 'avc_ais_options' );
		parent::tearDown();
	}

	/**
	 * オプションを読み直させるためシングルトンをリセットする。
	 *
	 * @return void
	 */
	private function reset_singletons() {
		foreach ( array( AVC_AIS_Settings::class, AVC_AIS_Schema::class ) as $class ) {
			$ref = new ReflectionProperty( $class, 'instance' );
			$ref->setAccessible( true );
			$ref->setValue( null, null );
		}
	}

	/**
	 * パスワード保護された投稿を作成する。
	 *
	 * @param array $overrides 上書きする投稿データ.
	 * @return int 投稿 ID.
	 */
	private function create_protected_post( array $overrides = array() ) {
		return self::factory()->post->create(
			array_merge(
				array(
					'post_title'    => 'Protected entry',
					'post_status'   => 'publish',
					'post_password' => 'hunter2',
					'post_excerpt'  => '',
					'post_content'  => self::SECRET . ' visible only with the password.',
				),
				$overrides
			)
		);
	}

	/**
	 * 現在のリクエストに対する JSON-LD を取得する。
	 *
	 * @return string
	 */
	private function render_json_ld() {
		ob_start();
		AVC_AIS_Schema::init()->output_json_ld();
		return (string) ob_get_clean();
	}

	/**
	 * 自動生成の llms.txt に保護された投稿・固定ページが含まれないこと。
	 */
	public function test_generated_llms_txt_excludes_protected_entries() {
		$this->create_protected_post();
		$this->create_protected_post(
			array(
				'post_type'  => 'page',
				'post_title' => 'Protected page',
			)
		);
		self::factory()->post->create(
			array(
				'post_title'   => 'Public post',
				'post_content' => 'Public post body',
			)
		);
		self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'Public page',
				'post_content' => 'Public page body',
			)
		);

		$content = AVC_AIS_Llms_Txt::init()->generate_default_content();

		$this->assertStringNotContainsString( self::SECRET, $content );
		$this->assertStringNotContainsString( 'Protected entry', $content );
		$this->assertStringNotContainsString( 'Protected page', $content );
		$this->assertStringContainsString( 'Public post body', $content );
		$this->assertStringContainsString( 'Public page body', $content );
	}

	/**
	 * 固定ページは get_pages() の順（menu_order と親子関係）を保つこと。
	 */
	public function test_generated_llms_txt_keeps_page_hierarchy_order() {
		$parent = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Parent page',
				'menu_order' => 1,
			)
		);
		self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'Child page',
				'post_parent' => $parent,
				'menu_order'  => 0,
			)
		);
		self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Last page',
				'menu_order' => 2,
			)
		);

		$content = AVC_AIS_Llms_Txt::init()->generate_default_content();

		$this->assertLessThan( strpos( $content, 'Child page' ), strpos( $content, 'Parent page' ) );
		$this->assertLessThan( strpos( $content, 'Last page' ), strpos( $content, 'Child page' ) );
	}

	/**
	 * 編集していない自動生成の内容を保存しても固定されず、自動生成のままであること。
	 *
	 * 設定画面の保存で固定されると、後から非公開にした投稿の抜粋が残り続ける。
	 */
	public function test_saving_unedited_generated_content_keeps_it_generated() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Soon private',
				'post_content' => 'SOONPRIVATEBODY',
			)
		);
		$llms    = AVC_AIS_Llms_Txt::init();

		// textarea から送信される CRLF でも同じ内容と判定されること。
		$llms->save_content( str_replace( "\n", "\r\n", $llms->get_content() ) );
		$this->assertFalse( get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'private',
			)
		);

		$this->assertStringNotContainsString( 'SOONPRIVATEBODY', $llms->get_content() );
	}

	/**
	 * 管理者が編集した内容は従来どおり保存されること。
	 */
	public function test_saving_edited_content_is_kept() {
		$llms = AVC_AIS_Llms_Txt::init();

		$llms->save_content( $llms->get_content() . "\nEdited by admin" );

		$this->assertStringContainsString( 'Edited by admin', get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
		$this->assertStringContainsString( 'Edited by admin', $llms->get_content() );
	}

	/**
	 * 更新後の最初の処理で、保存済みの内容から非公開の投稿の行だけが除かれること。
	 */
	public function test_saved_llms_txt_is_scrubbed_once_after_update() {
		$protected_id = $this->create_protected_post();
		$private_id   = self::factory()->post->create(
			array(
				'post_title'  => 'Private entry',
				'post_status' => 'private',
			)
		);
		$public_id    = self::factory()->post->create( array( 'post_title' => 'Public post' ) );
		$content      = "# Site\n\n"
			. '- [Protected entry](' . get_permalink( $protected_id ) . '): ' . self::SECRET . "\n"
			. '- [Private entry](' . get_permalink( $private_id ) . "): PRIVATEBODY\n"
			. '- [Public post](' . get_permalink( $public_id ) . "): Public excerpt\n";
		$llms         = AVC_AIS_Llms_Txt::init();

		$llms->save_content( $content );
		delete_option( AVC_AIS_Llms_Txt::OPTION_SCRUBBED );

		// 配信時に読み出すだけで掃除されること。
		$this->assertStringNotContainsString( self::SECRET, $llms->get_content() );

		$stored = get_option( AVC_AIS_Llms_Txt::OPTION_NAME );
		$this->assertStringNotContainsString( self::SECRET, $stored );
		$this->assertStringNotContainsString( 'PRIVATEBODY', $stored );
		$this->assertStringContainsString( '# Site', $stored );
		$this->assertStringContainsString( 'Public excerpt', $stored );
		$this->assertNotEmpty( get_option( AVC_AIS_Llms_Txt::OPTION_SCRUBBED ) );

		// 2 回目以降は読み出しの負荷をかけず、管理者が保存した内容にも触れない。
		$llms->save_content( $content );
		$llms->maybe_scrub_saved_content();
		$this->assertStringContainsString( self::SECRET, get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
	}

	/**
	 * 未ログインの訪問者の処理でも、非公開の投稿の行を公開時の URL で照合できること。
	 *
	 * 権限のないユーザーには get_permalink() が ?p=ID 形式を返すため、そのままでは
	 * 保存済みの行と一致せず、掃除済みの扱いになって抜粋が残り続ける。
	 */
	public function test_scrub_matches_pretty_url_of_private_post_for_anonymous_visitor() {
		global $wp_rewrite;
		$wp_rewrite->init();
		$wp_rewrite->set_permalink_structure( '/%postname%/' );

		self::factory()->post->create(
			array(
				'post_title'  => 'Hidden entry',
				'post_name'   => 'hidden-entry',
				'post_status' => 'private',
			)
		);
		$llms = AVC_AIS_Llms_Txt::init();
		$llms->save_content( '- [Hidden entry](' . home_url( '/hidden-entry/' ) . "): HIDDENEXCERPT\n- [Other](" . home_url( '/other/' ) . "): Other excerpt\n" );
		delete_option( AVC_AIS_Llms_Txt::OPTION_SCRUBBED );
		wp_set_current_user( 0 );

		$content = $llms->get_content();

		$wp_rewrite->set_permalink_structure( '' );

		$this->assertStringNotContainsString( 'HIDDENEXCERPT', $content );
		$this->assertStringContainsString( 'Other excerpt', $content );
	}

	/**
	 * 掃除は投稿数が分割の単位を超えても最後まで処理すること。
	 */
	public function test_scrub_covers_posts_beyond_one_batch() {
		$ids = self::factory()->post->create_many(
			201,
			array(
				'post_status'   => 'publish',
				'post_password' => 'hunter2',
			)
		);
		$llms = AVC_AIS_Llms_Txt::init();
		$llms->save_content( '- [' . get_post( end( $ids ) )->post_title . '](' . get_permalink( end( $ids ) ) . "): LASTEXCERPT\n# Kept\n" );
		delete_option( AVC_AIS_Llms_Txt::OPTION_SCRUBBED );

		$content = $llms->get_content();

		$this->assertStringNotContainsString( 'LASTEXCERPT', $content );
		$this->assertStringContainsString( '# Kept', $content );
	}

	/**
	 * 下書きの作成・保存で、公開済みのページの行が消えないこと。
	 *
	 * スラッグが空の下書きの URL はトップページと、公開済みの投稿と同じスラッグの下書きの
	 * URL はその投稿と一致してしまう。
	 */
	public function test_drafts_do_not_remove_entries_of_published_posts() {
		global $wp_rewrite;
		$wp_rewrite->init();
		$wp_rewrite->set_permalink_structure( '/%postname%/' );

		$published = self::factory()->post->create(
			array(
				'post_title' => 'Shared',
				'post_name'  => 'shared-slug',
			)
		);
		$saved     = '- [Home](' . home_url( '/' ) . "): HOMEEXCERPT\n"
			. '- [Shared](' . get_permalink( $published ) . "): SHAREDEXCERPT\n";
		$llms      = AVC_AIS_Llms_Txt::init();
		$llms->save_content( $saved );

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
		delete_option( AVC_AIS_Llms_Txt::OPTION_SCRUBBED );
		$content = $llms->get_content();

		$wp_rewrite->set_permalink_structure( '' );

		$this->assertStringContainsString( 'HOMEEXCERPT', $content );
		$this->assertStringContainsString( 'SHAREDEXCERPT', $content );
	}

	/**
	 * 管理者が文中に書いたリンクは、リンク先を非公開にしても行ごと消えないこと。
	 */
	public function test_hiding_a_post_keeps_hand_written_prose() {
		$post_id = self::factory()->post->create( array( 'post_title' => 'Soon hidden' ) );
		$url     = get_permalink( $post_id );
		$llms    = AVC_AIS_Llms_Txt::init();
		$llms->save_content( "See [the guide]({$url}) and the rest of the docs.\n- [Soon hidden]({$url}): HIDDENEXCERPT\n" );

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'private',
			)
		);

		$stored = get_option( AVC_AIS_Llms_Txt::OPTION_NAME );
		$this->assertStringContainsString( 'See [the guide]', $stored );
		$this->assertStringNotContainsString( 'HIDDENEXCERPT', $stored );
	}

	/**
	 * ゴミ箱への移動と完全な削除でも、保存済みの内容からその行が除かれること。
	 *
	 * @dataProvider provide_removal_functions
	 *
	 * @param callable $remove 投稿を削除する処理.
	 */
	public function test_removing_a_post_removes_its_saved_entry( $remove ) {
		$post_id = self::factory()->post->create( array( 'post_title' => 'Soon removed' ) );
		$llms    = AVC_AIS_Llms_Txt::init();
		$llms->save_content( '- [Soon removed](' . get_permalink( $post_id ) . "): REMOVEDEXCERPT\n# Kept\n" );

		$remove( $post_id );

		$stored = get_option( AVC_AIS_Llms_Txt::OPTION_NAME );
		$this->assertStringNotContainsString( 'REMOVEDEXCERPT', $stored );
		$this->assertStringContainsString( '# Kept', $stored );
	}

	/**
	 * 投稿を削除する処理を返す。
	 *
	 * @return array<string,array<int,callable>>
	 */
	public function provide_removal_functions() {
		return array(
			'trash'  => array( 'wp_trash_post' ),
			'delete' => array(
				static function ( $post_id ) {
					wp_delete_post( $post_id, true );
				},
			),
		);
	}

	/**
	 * 保存時にパーセントエンコードが除去された URL の行も除かれること。
	 *
	 * 以前の保存処理で日本語スラッグの URL は別の文字列になっているため、
	 * 除去後の URL が同じ別の投稿の行を巻き込まないことも確認する。
	 */
	public function test_scrub_matches_urls_altered_by_sanitization() {
		global $wp_rewrite;
		$wp_rewrite->init();
		$wp_rewrite->set_permalink_structure( '/%postname%/' );

		$protected_id = $this->create_protected_post( array( 'post_name' => '保護された記事' ) );
		$public_id    = self::factory()->post->create(
			array(
				'post_title' => 'Public japanese slug',
				'post_name'  => '公開された記事',
			)
		);
		$llms         = AVC_AIS_Llms_Txt::init();

		update_option(
			AVC_AIS_Llms_Txt::OPTION_NAME,
			sanitize_textarea_field(
				'- [Protected entry](' . get_permalink( $protected_id ) . '): ' . self::SECRET . "\n"
				. '- [Public japanese slug](' . get_permalink( $public_id ) . "): Public excerpt\n"
			)
		);
		$before = get_option( AVC_AIS_Llms_Txt::OPTION_NAME );
		$this->assertStringNotContainsString( '(' . get_permalink( $protected_id ) . ')', $before, '前提: 以前の保存処理で URL が変わる' );

		delete_option( AVC_AIS_Llms_Txt::OPTION_SCRUBBED );
		$llms->maybe_scrub_saved_content();
		$stored = get_option( AVC_AIS_Llms_Txt::OPTION_NAME );

		$wp_rewrite->set_permalink_structure( '' );

		$this->assertStringNotContainsString( self::SECRET, $stored );
		$this->assertStringContainsString( 'Public excerpt', $stored );
	}

	/**
	 * 投稿を非公開にすると、保存済みの内容からその行が除かれること。
	 *
	 * @dataProvider provide_hiding_changes
	 *
	 * @param array $change 投稿へ加える変更.
	 */
	public function test_hiding_a_post_removes_its_saved_entry( array $change ) {
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => 'Soon hidden',
				'post_status' => 'publish',
			)
		);
		$other   = self::factory()->post->create( array( 'post_title' => 'Stays public' ) );
		$llms    = AVC_AIS_Llms_Txt::init();
		$llms->save_content(
			'- [Soon hidden](' . get_permalink( $post_id ) . "): HIDDENEXCERPT\n"
			. '- [Stays public](' . get_permalink( $other ) . "): Public excerpt\n"
		);

		wp_update_post( array_merge( array( 'ID' => $post_id ), $change ) );

		$stored = get_option( AVC_AIS_Llms_Txt::OPTION_NAME );
		$this->assertStringNotContainsString( 'HIDDENEXCERPT', $stored );
		$this->assertStringContainsString( 'Public excerpt', $stored );
	}

	/**
	 * 投稿を非公開にする変更を返す。
	 *
	 * @return array<string,array<int,array>>
	 */
	public function provide_hiding_changes() {
		return array(
			'password' => array( array( 'post_password' => 'hunter2' ) ),
			'private'  => array( array( 'post_status' => 'private' ) ),
			'draft'    => array( array( 'post_status' => 'draft' ) ),
			'trash'    => array( array( 'post_status' => 'trash' ) ),
		);
	}

	/**
	 * 公開のままの更新では保存済みの内容を変更しないこと。
	 */
	public function test_updating_a_public_post_keeps_saved_entry() {
		$post_id = self::factory()->post->create( array( 'post_title' => 'Public post' ) );
		$llms    = AVC_AIS_Llms_Txt::init();
		$llms->save_content( '- [Public post](' . get_permalink( $post_id ) . "): Public excerpt\n" );

		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => 'Updated body',
			)
		);

		$this->assertStringContainsString( 'Public excerpt', get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
	}

	/**
	 * get_pages() が false を返す環境でも致命的エラーにならないこと。
	 */
	public function test_generated_llms_txt_survives_non_hierarchical_pages() {
		global $wp_post_types;
		$hierarchical = $wp_post_types['page']->hierarchical;

		$wp_post_types['page']->hierarchical = false;
		$content                             = AVC_AIS_Llms_Txt::init()->generate_default_content();
		$wp_post_types['page']->hierarchical = $hierarchical;

		$this->assertStringContainsString( 'Recent Posts', $content );
	}

	/**
	 * 保護された投稿のアイキャッチ画像の URL を出力しないこと。
	 */
	public function test_featured_image_of_protected_post_is_not_output() {
		$post_id = $this->create_protected_post();
		update_post_meta( $post_id, '_thumbnail_id', 200 );
		add_filter(
			'wp_get_attachment_image_src',
			static function () {
				return array( 'http://example.com/protected-image.jpg', 800, 600, true );
			}
		);

		$this->go_to( get_permalink( $post_id ) );

		$this->assertStringNotContainsString( 'protected-image.jpg', $this->render_json_ld() );
	}

	/**
	 * QAPage の JSON-LD に保護された本文が含まれないこと。
	 */
	public function test_qapage_excludes_protected_body() {
		$post_id = $this->create_protected_post();
		update_post_meta( $post_id, '_avc_ais_meta', array( 'page_type' => 'QAPage' ) );

		$this->go_to( get_permalink( $post_id ) );
		$html = $this->render_json_ld();

		$this->assertStringContainsString( '"@type":"WebPage"', $html, 'ページ自体のノードは出力する' );
		$this->assertStringNotContainsString( '"@type":"QAPage"', $html );
		$this->assertStringNotContainsString( self::SECRET, $html );
	}

	/**
	 * 保護された投稿では Product のレビューや価格も出力しないこと。
	 */
	public function test_product_meta_of_protected_post_is_not_output() {
		$post_id = $this->create_protected_post();
		update_post_meta(
			$post_id,
			'_ais_product',
			array(
				'price'          => '1280',
				'price_currency' => 'JPY',
				'reviews'        => array(
					array(
						'author' => 'Reviewer',
						'body'   => 'PROTECTEDREVIEW',
						'rating' => 5,
					),
				),
			)
		);
		update_option(
			'avc_ais_options',
			array_merge( get_option( 'avc_ais_options' ), array( 'content_model' => 'Product' ) )
		);
		$this->reset_singletons();

		$this->go_to( get_permalink( $post_id ) );
		$html = $this->render_json_ld();

		$this->assertStringNotContainsString( '"@type":"Product"', $html );
		$this->assertStringNotContainsString( 'PROTECTEDREVIEW', $html );
	}

	/**
	 * パスワードを入力済みの訪問者には本文の表示に合わせてスキーマを出力すること。
	 */
	public function test_qapage_is_output_once_password_is_entered() {
		$post_id = $this->create_protected_post();
		update_post_meta( $post_id, '_avc_ais_meta', array( 'page_type' => 'QAPage' ) );
		add_filter( 'post_password_required', '__return_false' );

		$this->go_to( get_permalink( $post_id ) );
		$html = $this->render_json_ld();

		$this->assertStringContainsString( '"@type":"QAPage"', $html );
		$this->assertStringContainsString( self::SECRET, $html );
	}

	/**
	 * QAPage の JSON-LD に保護された投稿の手動抜粋が含まれないこと。
	 */
	public function test_qapage_excludes_protected_excerpt() {
		$post_id = $this->create_protected_post( array( 'post_excerpt' => 'PROTECTEDEXCERPT' ) );
		update_post_meta( $post_id, '_avc_ais_meta', array( 'page_type' => 'QAPage' ) );

		$this->go_to( get_permalink( $post_id ) );

		$this->assertStringNotContainsString( 'PROTECTEDEXCERPT', $this->render_json_ld() );
	}

	/**
	 * 保護されていない QAPage は従来どおり本文を回答として出力すること。
	 */
	public function test_qapage_keeps_body_of_public_post() {
		$post_id = self::factory()->post->create(
			array(
				'post_excerpt' => '',
				'post_content' => 'Public answer body',
			)
		);
		update_post_meta( $post_id, '_avc_ais_meta', array( 'page_type' => 'QAPage' ) );

		$this->go_to( get_permalink( $post_id ) );

		$this->assertStringContainsString( 'Public answer body', $this->render_json_ld() );
	}

	/**
	 * タイトルが空でも、Article の見出しへ保護された本文が流用されないこと。
	 */
	public function test_article_headline_does_not_fall_back_to_protected_body() {
		$author  = self::factory()->user->create( array( 'role' => 'author' ) );
		$post_id = $this->create_protected_post(
			array(
				'post_title'  => '',
				'post_author' => $author,
			)
		);
		update_post_meta( $post_id, '_avc_ais_meta', array( 'page_type' => 'Article' ) );
		update_post_meta( $post_id, '_thumbnail_id', 200 );
		add_filter(
			'wp_get_attachment_image_src',
			static function () {
				return array( 'http://example.com/featured.jpg', 800, 600, true );
			}
		);
		// タイトルの「保護中:」接頭辞を消し、フォールバックが働く状況を作る。
		add_filter(
			'protected_title_format',
			static function () {
				return '%s';
			}
		);

		$this->go_to( get_permalink( $post_id ) );

		$this->assertStringNotContainsString( self::SECRET, $this->render_json_ld() );
	}

	/**
	 * FAQ 抽出でも保護された本文が出力されないこと。
	 */
	public function test_faq_extraction_excludes_protected_body() {
		$post_id = $this->create_protected_post(
			array(
				'post_content' => '<div class="q">' . self::SECRET . ' question</div><div class="a">' . self::SECRET . ' answer</div>',
			)
		);
		update_post_meta(
			$post_id,
			'_avc_ais_meta',
			array(
				'page_type'          => 'FAQPage',
				'faq_question_class' => 'q',
				'faq_answer_class'   => 'a',
			)
		);

		$this->go_to( get_permalink( $post_id ) );

		$this->assertStringNotContainsString( self::SECRET, $this->render_json_ld() );
	}
}

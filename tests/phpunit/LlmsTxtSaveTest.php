<?php
/**
 * llms.txt の保存処理のテスト。
 *
 * - 設定画面以外からの保存で llms.txt の設定が変わらないこと
 * - 保存してもパーセントエンコードされた URL が壊れないこと
 * - 編集せずに保存しても内容が書き込まれないこと
 *
 * @package AVC_AIS_Schema
 */

require_once __DIR__ . '/bootstrap.php';

use Aivec\AiSearchSchema\Wizard\Wizard;

/**
 * llms.txt の保存処理のテストクラス。
 */
class LlmsTxtSaveTest extends WP_UnitTestCase {

	const CUSTOM_CONTENT = "# Custom\n\n> Hand written";

	const ENCODED_LINE = '- [日本語の記事](https://example.com/%e3%81%82%e3%81%84/): 抜粋';

	/**
	 * テスト用の準備。
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		require_once AVC_AIS_DIR . 'includes/class-avc-ais-llms-txt.php';

		$ref = new ReflectionProperty( AVC_AIS_Llms_Txt::class, 'instance' );
		avc_ais_test_make_accessible( $ref );
		$ref->setValue( null, null );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->set_permalink_structure( '/%postname%/' );

		// sanitize_option_avc_ais_options フィルターは本番では admin_init で登録される。
		AVC_AIS_Settings::init()->register_settings();

		delete_option( 'avc_ais_options' );
		delete_option( AVC_AIS_Llms_Txt::OPTION_NAME );
		delete_option( AVC_AIS_Llms_Txt::OPTION_ENABLED );
		delete_option( 'avc_ais_llms_txt_rule_restored' );
		update_option( AVC_AIS_Llms_Txt::OPTION_URLS_REPAIRED, true );
	}

	/**
	 * テスト後の片付け。
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		delete_option( 'avc_ais_options' );
		delete_option( AVC_AIS_Llms_Txt::OPTION_NAME );
		delete_option( AVC_AIS_Llms_Txt::OPTION_ENABLED );
		delete_option( AVC_AIS_Llms_Txt::OPTION_URLS_REPAIRED );
		delete_option( 'avc_ais_llms_txt_rule_restored' );
		$_POST    = array();
		$_REQUEST = array();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * 設定画面のフォームから保存する。
	 *
	 * options.php は入力をそのまま update_option() へ渡す。
	 *
	 * @param array $fields 送信する項目.
	 * @return void
	 */
	private function submit_settings_form( array $fields ) {
		update_option( 'avc_ais_options', array_merge( array( 'company_name' => 'Aivec' ), $fields ) );
	}

	/**
	 * llms.txt の設定に関係しない保存の経路。
	 *
	 * @return array[]
	 */
	public function provide_saves_outside_the_settings_form() {
		return array(
			'wizard step'                => array(
				static function () {
					$method = new ReflectionMethod( Wizard::class, 'save_to_settings' );
					avc_ais_test_make_accessible( $method );
					$method->invoke( new Wizard(), 'basics', array( 'company_name' => 'Aivec' ) );
				},
			),
			'avc_ais_update_option'      => array(
				static function () {
					avc_ais_update_option( 'company_name', 'Aivec' );
				},
			),
			'legacy API key migration'   => array(
				static function () {
					delete_option( 'avc_ais_gmaps_api_key' );
					update_option( 'avc_ais_options', array( 'gmaps_api_key' => 'legacy-key' ) );
					$method = new ReflectionMethod( AVC_AIS_Settings::class, 'get_google_maps_api_key' );
					avc_ais_test_make_accessible( $method );
					$method->invoke( AVC_AIS_Settings::init() );
				},
			),
			'direct update_option'       => array(
				static function () {
					update_option( 'avc_ais_options', array( 'company_name' => 'Aivec' ) );
				},
			),
			'unchanged direct re-saving' => array(
				static function () {
					update_option( 'avc_ais_options', get_option( 'avc_ais_options', array() ) );
				},
			),
		);
	}

	/**
	 * 設定画面以外からの保存では、llms.txt の有効状態もカスタム内容も変わらないこと。
	 *
	 * @dataProvider provide_saves_outside_the_settings_form
	 *
	 * @param callable $save 保存処理.
	 * @return void
	 */
	public function test_saving_outside_the_form_keeps_llms_txt_settings( callable $save ) {
		$llms = AVC_AIS_Llms_Txt::init();
		$llms->set_enabled( true );
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, self::CUSTOM_CONTENT );

		$save();

		$this->assertTrue( $llms->is_enabled() );
		$this->assertSame( self::CUSTOM_CONTENT, get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
	}

	/**
	 * 設定画面以外からの保存で、無効にしていた llms.txt が有効に戻らないこと。
	 *
	 * @return void
	 */
	public function test_saving_outside_the_form_keeps_llms_txt_disabled() {
		$llms = AVC_AIS_Llms_Txt::init();
		$llms->set_enabled( false );

		avc_ais_update_option( 'company_name', 'Aivec' );

		$this->assertFalse( $llms->is_enabled() );
	}

	/**
	 * 設定画面でチェックを外すと無効になること。未選択のチェックボックスは送信されない。
	 *
	 * @return void
	 */
	public function test_settings_form_can_disable_llms_txt() {
		$llms = AVC_AIS_Llms_Txt::init();
		$llms->set_enabled( true );

		$this->submit_settings_form( array( 'llms_txt_content' => self::CUSTOM_CONTENT ) );

		$this->assertFalse( $llms->is_enabled() );
		$this->assertSame( self::CUSTOM_CONTENT, get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
	}

	/**
	 * 設定画面で有効にしてカスタム内容を保存できること。
	 *
	 * @return void
	 */
	public function test_settings_form_saves_enabled_and_content() {
		$llms = AVC_AIS_Llms_Txt::init();
		$llms->set_enabled( false );

		$this->submit_settings_form(
			array(
				'llms_txt_enabled' => '1',
				'llms_txt_content' => self::CUSTOM_CONTENT,
			)
		);

		$this->assertTrue( $llms->is_enabled() );
		$this->assertSame( self::CUSTOM_CONTENT, get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
	}

	/**
	 * 設定画面で内容を空にして保存すると自動生成に戻ること。
	 *
	 * @return void
	 */
	public function test_settings_form_with_empty_content_resets_to_generated() {
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, self::CUSTOM_CONTENT );

		$this->submit_settings_form(
			array(
				'llms_txt_enabled' => '1',
				'llms_txt_content' => '',
			)
		);

		$this->assertFalse( get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
	}

	/**
	 * 無効の状態から有効に戻したときに /llms.txt のルールが登録されること。
	 *
	 * リクエストの init は無効の状態で動くので、登録しないままルールを再生成すると
	 * /llms.txt へのルールが消え、有効にしても 404 のままになる。
	 *
	 * @return void
	 */
	public function test_reenabling_from_settings_form_registers_rewrite_rule() {
		global $wp_rewrite;

		$llms = AVC_AIS_Llms_Txt::init();
		$llms->set_enabled( false );
		unset( $wp_rewrite->extra_rules_top[ AVC_AIS_Llms_Txt::REWRITE_REGEX ] );
		flush_rewrite_rules();
		$this->assertArrayNotHasKey( AVC_AIS_Llms_Txt::REWRITE_REGEX, get_option( 'rewrite_rules' ), '前提: ルールがない' );

		$this->submit_settings_form(
			array(
				'llms_txt_enabled' => '1',
				'llms_txt_content' => '',
			)
		);

		$this->assertArrayHasKey( AVC_AIS_Llms_Txt::REWRITE_REGEX, get_option( 'rewrite_rules' ) );
	}

	/**
	 * 以前のバージョンでルールが消えたサイトが、管理画面へのアクセスで復旧すること。
	 *
	 * @return void
	 */
	public function test_admin_request_restores_missing_rewrite_rule() {
		$llms = AVC_AIS_Llms_Txt::init();
		$llms->add_rewrite_rules();
		flush_rewrite_rules( false );

		$rules = get_option( 'rewrite_rules' );
		unset( $rules[ AVC_AIS_Llms_Txt::REWRITE_REGEX ] );
		update_option( 'rewrite_rules', $rules );

		$this->assertNotFalse( has_action( 'admin_init', array( $llms, 'restore_missing_rewrite_rule' ) ) );
		$llms->restore_missing_rewrite_rule();

		$this->assertArrayHasKey( AVC_AIS_Llms_Txt::REWRITE_REGEX, get_option( 'rewrite_rules' ) );
	}

	/**
	 * ルールの再生成が必要ない状態では再生成しないこと。
	 *
	 * @dataProvider provide_states_without_rule_regeneration
	 *
	 * @param bool         $enabled 有効かどうか.
	 * @param array|string $rules   保存済みのルール.
	 * @return void
	 */
	public function test_rewrite_rule_is_not_regenerated_when_not_needed( $enabled, $rules ) {
		$llms = AVC_AIS_Llms_Txt::init();
		$llms->set_enabled( $enabled );
		update_option( 'rewrite_rules', $rules );

		$llms->restore_missing_rewrite_rule();

		$this->assertSame( $rules, get_option( 'rewrite_rules' ) );
	}

	/**
	 * ルールの再生成が必要ない状態。
	 *
	 * @return array[]
	 */
	public function provide_states_without_rule_regeneration() {
		return array(
			'disabled'        => array( false, array( 'other' => 'index.php' ) ),
			'plain permalink' => array( true, '' ),
			'rule present'    => array( true, array( AVC_AIS_Llms_Txt::REWRITE_REGEX => 'index.php?llms_txt=1' ) ),
		);
	}

	/**
	 * 再生成してもルールが残らないサイトで、管理画面を開くたびに再生成しないこと。
	 *
	 * @return void
	 */
	public function test_rule_restoration_runs_only_once() {
		$regenerations = 0;
		add_filter(
			'rewrite_rules_array',
			static function ( $rules ) use ( &$regenerations ) {
				++$regenerations;
				unset( $rules[ AVC_AIS_Llms_Txt::REWRITE_REGEX ] );
				return $rules;
			}
		);

		$llms = AVC_AIS_Llms_Txt::init();
		$llms->add_rewrite_rules();
		flush_rewrite_rules( false );

		$regenerations = 0;
		$llms->restore_missing_rewrite_rule();
		$this->assertSame( 1, $regenerations, '1 回目は再生成する' );

		$regenerations = 0;
		$llms->restore_missing_rewrite_rule();
		$this->assertSame( 0, $regenerations, '2 回目は再生成しない' );
	}

	/**
	 * 内容を保存する経路。
	 *
	 * @return array[]
	 */
	public function provide_content_saves() {
		return array(
			'save_content'       => array(
				static function ( $content ) {
					AVC_AIS_Llms_Txt::init()->save_content( $content );
				},
			),
			'settings form'      => array(
				static function ( $content ) {
					update_option(
						'avc_ais_options',
						array(
							'company_name'     => 'Aivec',
							'llms_txt_enabled' => '1',
							'llms_txt_content' => $content,
						)
					);
				},
			),
			'save button (AJAX)' => array(
				static function ( $content ) {
					$responder = static function () {};
					add_action( 'avc_ais_json_responder', $responder );

					$nonce             = wp_create_nonce( 'avc_ais_save_llms_txt' );
					$_REQUEST['nonce'] = $nonce;
					$_POST             = array(
						'nonce'   => $nonce,
						'content' => wp_slash( $content ),
					);
					AVC_AIS_Settings::init()->handle_save_llms_txt();

					remove_action( 'avc_ais_json_responder', $responder );
				},
			),
		);
	}

	/**
	 * どの経路で保存してもパーセントエンコードされた URL が壊れないこと。
	 *
	 * @dataProvider provide_content_saves
	 *
	 * @param callable $save 保存処理.
	 * @return void
	 */
	public function test_saving_keeps_percent_encoded_urls( callable $save ) {
		$save( self::ENCODED_LINE );

		$this->assertSame( self::ENCODED_LINE, get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
	}

	/**
	 * どの経路で保存してもタグは取り除かれること。
	 *
	 * @dataProvider provide_content_saves
	 *
	 * @param callable $save 保存処理.
	 * @return void
	 */
	public function test_saving_still_strips_tags( callable $save ) {
		$save( "<script>alert('%41')</script><b>Bold</b>\n" . self::ENCODED_LINE );

		$saved = get_option( AVC_AIS_Llms_Txt::OPTION_NAME );
		$this->assertStringNotContainsString( '<', $saved );
		$this->assertStringNotContainsString( 'alert', $saved );
		$this->assertStringContainsString( self::ENCODED_LINE, $saved );
	}

	/**
	 * 保存の前後で本文の文字が変わらないこと。
	 *
	 * @dataProvider provide_preserved_text
	 *
	 * @param string $text 保存する文字列.
	 * @return void
	 */
	public function test_saving_preserves_text( $text ) {
		AVC_AIS_Llms_Txt::init()->save_content( $text );

		$this->assertSame( $text, get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
	}

	/**
	 * 保存の前後で変わってはいけない文字列。
	 *
	 * @return array[]
	 */
	public function provide_preserved_text() {
		return array(
			'percent sign in prose'    => array( '割引は 100% です' ),
			'percent followed by hex'  => array( '成長率 50%ab と 10%25' ),
			'encoded tag stays inert'  => array( '%3Cscript%3Ealert(1)%3C/script%3E' ),
			'query string'             => array( '[検索](https://example.com/?q=%E6%97%A5&page=2)' ),
			'upper case percent bytes' => array( '[記事](https://example.com/%E3%81%82/)' ),
			'multiple lines'           => array( "# 見出し\n\n- [a](https://example.com/%e3%81%82/): x\n- [b](https://example.com/%e3%81%84/): y" ),
		);
	}

	/**
	 * パスワード生成に手を加えるプラグインがあっても、保存内容が変わらないこと。
	 *
	 * @return void
	 */
	public function test_saving_is_independent_of_password_filters() {
		add_filter(
			'random_password',
			static function () {
				return '%41<b>';
			}
		);

		AVC_AIS_Llms_Txt::init()->save_content( self::ENCODED_LINE );

		$this->assertSame( self::ENCODED_LINE, get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
	}

	/**
	 * 設定画面を開いて、編集せずに送信するときの項目を返す。
	 *
	 * ブラウザは textarea の改行を CRLF で送信する。
	 *
	 * @return array 送信する項目.
	 */
	private function open_settings_screen() {
		$content = AVC_AIS_Llms_Txt::init()->get_content();

		return array(
			'llms_txt_enabled'      => '1',
			'llms_txt_content'      => str_replace( "\n", "\r\n", $content ),
			'llms_txt_content_hash' => AVC_AIS_Llms_Txt::content_hash( $content ),
		);
	}

	/**
	 * 保存ボタンの AJAX を実行してレスポンスを返す。
	 *
	 * @param string $action  AJAX の処理（avc_ais_save_llms_txt など）.
	 * @param string $handler 呼び出すメソッド.
	 * @param array  $fields  送信する項目.
	 * @return array レスポンスのデータ.
	 */
	private function post_llms_txt_ajax( $action, $handler, array $fields = array() ) {
		$response  = array();
		$responder = static function ( $success, $data ) use ( &$response ) {
			$response = $data;
		};
		add_action( 'avc_ais_json_responder', $responder, 10, 2 );

		$nonce             = wp_create_nonce( $action );
		$_REQUEST['nonce'] = $nonce;
		$_POST             = array_merge( array( 'nonce' => $nonce ), wp_slash( $fields ) );
		AVC_AIS_Settings::init()->$handler();

		remove_action( 'avc_ais_json_responder', $responder );

		return $response;
	}

	/**
	 * 開いている間に投稿が公開されても、編集せずに保存すれば自動生成のままになること。
	 *
	 * @return void
	 */
	public function test_unedited_form_stays_generated_when_a_post_is_published_meanwhile() {
		$fields = $this->open_settings_screen();
		self::factory()->post->create( array( 'post_title' => 'Published meanwhile' ) );

		$this->submit_settings_form( $fields );

		$this->assertFalse( get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
		$this->assertStringContainsString( 'Published meanwhile', AVC_AIS_Llms_Txt::init()->get_content() );
	}

	/**
	 * 開いている間に非公開にした投稿の抜粋が、編集せずに保存しても残らないこと。
	 *
	 * @return void
	 */
	public function test_unedited_form_does_not_keep_excerpt_of_post_hidden_meanwhile() {
		$post_id = self::factory()->post->create( array( 'post_content' => 'TOPSECRETEXCERPT' ) );
		$fields  = $this->open_settings_screen();
		$this->assertStringContainsString( 'TOPSECRETEXCERPT', $fields['llms_txt_content'], '前提: 開いたときは載っている' );

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'private',
			)
		);
		$this->submit_settings_form( $fields );

		$this->assertFalse( get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
		$this->assertStringNotContainsString( 'TOPSECRETEXCERPT', AVC_AIS_Llms_Txt::init()->get_content() );
	}

	/**
	 * 別の画面で保存した内容を、編集していない古い画面の保存で上書きしないこと。
	 *
	 * @return void
	 */
	public function test_unedited_form_does_not_overwrite_content_saved_elsewhere() {
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, self::CUSTOM_CONTENT );
		$fields = $this->open_settings_screen();

		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, '# 別の画面で保存' );
		$this->submit_settings_form( $fields );

		$this->assertSame( '# 別の画面で保存', get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
	}

	/**
	 * 編集していなくても、有効と無効の切り替えは保存されること。
	 *
	 * @return void
	 */
	public function test_unedited_form_still_saves_enabled_status() {
		$llms   = AVC_AIS_Llms_Txt::init();
		$fields = $this->open_settings_screen();
		unset( $fields['llms_txt_enabled'] );

		$this->submit_settings_form( $fields );

		$this->assertFalse( $llms->is_enabled() );
		$this->assertFalse( get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
	}

	/**
	 * 編集した内容は、開いている間に投稿が変わっていても保存されること。
	 *
	 * @return void
	 */
	public function test_edited_form_is_saved() {
		$fields = $this->open_settings_screen();
		self::factory()->post->create( array( 'post_title' => 'Published meanwhile' ) );

		$fields['llms_txt_content'] .= "\r\n# 追記";
		$this->submit_settings_form( $fields );

		$this->assertStringEndsWith( '# 追記', get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
	}

	/**
	 * 以前のバージョンで固定された内容が自動生成と同じなら、編集せずに保存すると解除されること。
	 *
	 * @return void
	 */
	public function test_unedited_form_releases_frozen_content_equal_to_generated() {
		self::factory()->post->create( array( 'post_title' => '固定された記事' ) );
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, AVC_AIS_Llms_Txt::init()->generate_default_content() );

		$this->submit_settings_form( $this->open_settings_screen() );

		$this->assertFalse( get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
	}

	/**
	 * 行末が < の内容でも、編集せずに保存すれば自動生成のままになること。
	 *
	 * sanitize_textarea_field() は LF の前の < と CRLF の前の < を別々に処理する。
	 *
	 * @return void
	 */
	public function test_unedited_form_stays_generated_when_a_line_ends_with_less_than() {
		update_option( 'blogname', 'Site <' );
		$fields = $this->open_settings_screen();
		$this->assertStringContainsString( "# Site <\r\n", $fields['llms_txt_content'], '前提: 行末が <' );

		self::factory()->post->create( array( 'post_title' => 'Published meanwhile' ) );
		$this->submit_settings_form( $fields );

		$this->assertFalse( get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
	}

	/**
	 * 編集せずに保存しても、WP-CLI などで入れた保存済みの内容を書き換えないこと。
	 *
	 * @return void
	 */
	public function test_unedited_form_does_not_rewrite_saved_content() {
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, '<b>太字</b>の見出し' );

		$this->submit_settings_form( $this->open_settings_screen() );

		$this->assertSame( '<b>太字</b>の見出し', get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
	}

	/**
	 * ハッシュと llms.txt の内容を設定へ保存しないこと。
	 *
	 * @return void
	 */
	public function test_form_does_not_store_llms_txt_fields_in_settings() {
		$this->submit_settings_form( $this->open_settings_screen() );

		$options = get_option( 'avc_ais_options' );
		$this->assertArrayNotHasKey( 'llms_txt_content', $options );
		$this->assertArrayNotHasKey( 'llms_txt_content_hash', $options );
	}

	/**
	 * ハッシュが配列でも警告を出さず、編集された内容として保存すること。
	 *
	 * @return void
	 */
	public function test_form_treats_non_string_hash_as_edited() {
		$this->submit_settings_form(
			array(
				'llms_txt_content'      => self::CUSTOM_CONTENT,
				'llms_txt_content_hash' => array( 'x' ),
			)
		);

		$this->assertSame( self::CUSTOM_CONTENT, get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
	}

	/**
	 * 設定画面から保存しても本文のバックスラッシュが消えないこと。
	 *
	 * @return void
	 */
	public function test_settings_form_keeps_backslashes() {
		$this->submit_settings_form( array( 'llms_txt_content' => '保存先は C:\\Users\\aivec' ) );

		$this->assertSame( '保存先は C:\\Users\\aivec', get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
	}

	/**
	 * 本文にバックスラッシュがある投稿が載っていても、編集せずに保存すれば自動生成のままになること。
	 *
	 * @return void
	 */
	public function test_unedited_form_with_backslashes_stays_generated() {
		self::factory()->post->create( array( 'post_content' => wp_slash( '保存先は C:\\Users\\aivec' ) ) );
		$fields = $this->open_settings_screen();
		$this->assertStringContainsString( 'C:\\Users', $fields['llms_txt_content'], '前提: バックスラッシュが載っている' );

		self::factory()->post->create( array( 'post_title' => 'Published meanwhile' ) );
		$this->submit_settings_form( $fields );

		$this->assertFalse( get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
	}

	/**
	 * 保存ボタンでも、編集していなければ書き込まないこと。
	 *
	 * @return void
	 */
	public function test_unedited_save_button_stays_generated() {
		$fields = $this->open_settings_screen();
		self::factory()->post->create( array( 'post_title' => 'Published meanwhile' ) );

		$response = $this->post_llms_txt_ajax(
			'avc_ais_save_llms_txt',
			'handle_save_llms_txt',
			array(
				'content'      => $fields['llms_txt_content'],
				'content_hash' => $fields['llms_txt_content_hash'],
			)
		);

		$this->assertFalse( get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
		$this->assertStringContainsString( 'Published meanwhile', $response['content'], '配信する内容を返す' );
		$this->assertSame( AVC_AIS_Llms_Txt::content_hash( $response['content'] ), $response['hash'] );
	}

	/**
	 * 保存ボタンのあとは、保存した内容を基準に編集の有無を判定すること。
	 *
	 * 保存後に開いたときの内容へ手で戻したら、編集として保存する。
	 *
	 * @return void
	 */
	public function test_save_button_returns_hash_of_saved_content() {
		$fields = $this->open_settings_screen();

		$response = $this->post_llms_txt_ajax(
			'avc_ais_save_llms_txt',
			'handle_save_llms_txt',
			array(
				'content'      => self::CUSTOM_CONTENT,
				'content_hash' => $fields['llms_txt_content_hash'],
			)
		);
		$this->assertSame( self::CUSTOM_CONTENT, get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
		$this->assertSame( AVC_AIS_Llms_Txt::content_hash( self::CUSTOM_CONTENT ), $response['hash'] );

		$fields['llms_txt_content_hash'] = $response['hash'];
		$fields['llms_txt_content']      = '# 手で書き直した内容';
		$this->submit_settings_form( $fields );

		$this->assertSame( '# 手で書き直した内容', get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
	}

	/**
	 * 再生成のあと、編集せずに設定を保存しても自動生成のままになること。
	 *
	 * @return void
	 */
	public function test_unedited_form_after_regenerating_stays_generated() {
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, self::CUSTOM_CONTENT );

		$response = $this->post_llms_txt_ajax( 'avc_ais_regenerate_llms_txt', 'handle_regenerate_llms_txt' );
		self::factory()->post->create( array( 'post_title' => 'Published meanwhile' ) );

		$this->submit_settings_form(
			array(
				'llms_txt_content'      => $response['content'],
				'llms_txt_content_hash' => $response['hash'],
			)
		);

		$this->assertFalse( get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
	}

	/**
	 * 無効化すると /llms.txt のルールが消えること。
	 *
	 * @return void
	 */
	public function test_deactivation_removes_rewrite_rule() {
		$llms = AVC_AIS_Llms_Txt::init();
		$llms->add_rewrite_rules();
		flush_rewrite_rules( false );
		$this->assertArrayHasKey( AVC_AIS_Llms_Txt::REWRITE_REGEX, get_option( 'rewrite_rules' ), '前提: ルールがある' );

		AVC_AIS_Llms_Txt::deactivate();

		$this->assertArrayNotHasKey( AVC_AIS_Llms_Txt::REWRITE_REGEX, get_option( 'rewrite_rules' ) );
	}

	/**
	 * 無効化のあと、もう一度有効化するとルールが戻ること。
	 *
	 * @return void
	 */
	public function test_reactivation_restores_rewrite_rule() {
		AVC_AIS_Llms_Txt::deactivate();

		AVC_AIS_Llms_Txt::activate();

		$this->assertArrayHasKey( AVC_AIS_Llms_Txt::REWRITE_REGEX, get_option( 'rewrite_rules' ) );
	}

	/**
	 * 配列を送られても警告を出さず、空として扱うこと。
	 *
	 * @return void
	 */
	public function test_sanitize_content_treats_arrays_as_empty() {
		$this->assertSame( '', AVC_AIS_Llms_Txt::sanitize_content( array( 'x', array( 'y' ) ) ) );
		$this->assertSame( AVC_AIS_Llms_Txt::content_hash( '' ), AVC_AIS_Llms_Txt::content_hash( array( 'x' ) ) );
	}

	/**
	 * 日本語スラッグの投稿があっても、編集せずに保存すれば自動生成のままになること。
	 *
	 * @return void
	 */
	public function test_saving_unedited_content_with_encoded_url_stays_generated() {
		self::factory()->post->create(
			array(
				'post_title' => '日本語の記事',
				'post_name'  => 'あい',
			)
		);
		$llms = AVC_AIS_Llms_Txt::init();
		$this->assertStringContainsString( '%e3%81%82', $llms->get_content(), '前提: エンコードされた URL が載る' );

		$llms->save_content( $llms->get_content() );

		$this->assertFalse( get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
	}

	/**
	 * 候補の取得数の上限に達したら、一意とみなさず修復しないこと。
	 *
	 * @return void
	 */
	public function test_repair_treats_results_at_the_query_limit_as_ambiguous() {
		$older_id = self::factory()->post->create(
			array(
				'post_title' => 'お知らせ',
				'post_name'  => 'あ',
				'post_date'  => '2020-01-01 00:00:00',
			)
		);
		for ( $i = 1; $i <= 19; $i++ ) {
			self::factory()->post->create(
				array(
					'post_title' => 'お知らせ',
					'post_name'  => "news-{$i}",
					'post_date'  => sprintf( '2021-01-%02d 00:00:00', $i ),
				)
			);
		}
		self::factory()->post->create(
			array(
				'post_title' => 'お知らせ',
				'post_name'  => 'い',
				'post_date'  => '2022-01-01 00:00:00',
			)
		);

		$saved = '- [お知らせ](' . sanitize_textarea_field( get_permalink( $older_id ) ) . '): 抜粋';

		$this->assertSame( $saved, $this->run_once_over_legacy_content( $saved ) );
	}

	/**
	 * 以前のバージョンが保存した内容を用意して、一度きりの処理を走らせる。
	 *
	 * @param string $saved 保存済みの内容.
	 * @return string 処理後の内容.
	 */
	private function run_once_over_legacy_content( $saved ) {
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, $saved );
		delete_option( AVC_AIS_Llms_Txt::OPTION_URLS_REPAIRED );

		AVC_AIS_Llms_Txt::init()->maybe_repair_saved_urls();

		return get_option( AVC_AIS_Llms_Txt::OPTION_NAME );
	}

	/**
	 * 以前のバージョンで壊れた URL が、更新後に一度だけ修復されること。
	 *
	 * @return void
	 */
	public function test_broken_urls_are_repaired_once_after_update() {
		$first_id  = self::factory()->post->create(
			array(
				'post_title' => '最初の記事',
				'post_name'  => 'あい',
			)
		);
		$second_id = self::factory()->post->create(
			array(
				'post_title' => '二番目の記事',
				'post_name'  => 'mixed-かき',
			)
		);
		$first     = get_permalink( $first_id );
		$second    = get_permalink( $second_id );
		$broken    = sanitize_textarea_field( $first );
		$this->assertNotSame( $first, $broken, '前提: 以前の保存処理で URL が変わる' );

		$content = $this->run_once_over_legacy_content(
			"# 手書きの見出し\n"
			. "- [最初の記事]({$broken}): 最初の抜粋\n"
			. '- [二番目の記事](' . sanitize_textarea_field( $second ) . "): 二番目の抜粋\n"
			. '手書きの本文'
		);

		$this->assertSame(
			"# 手書きの見出し\n- [最初の記事]({$first}): 最初の抜粋\n- [二番目の記事]({$second}): 二番目の抜粋\n手書きの本文",
			$content
		);
		$this->assertSame( $content, get_option( AVC_AIS_Llms_Txt::OPTION_NAME ), '修復結果を保存する' );

		// 二度目は走らないので、管理者が同じ形で書き直した内容には触れない。
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, "- [最初の記事]({$broken}): 書き直し" );
		AVC_AIS_Llms_Txt::init()->maybe_repair_saved_urls();
		$this->assertSame( "- [最初の記事]({$broken}): 書き直し", get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
	}

	/**
	 * 改行が CRLF の内容でも修復でき、行末が変わらないこと。
	 *
	 * @return void
	 */
	public function test_repair_keeps_crlf_line_endings() {
		$post_id = self::factory()->post->create(
			array(
				'post_title' => '最初の記事',
				'post_name'  => 'あい',
			)
		);
		$url     = get_permalink( $post_id );

		$content = $this->run_once_over_legacy_content(
			'- [最初の記事](' . sanitize_textarea_field( $url ) . "): 抜粋\r\n# 見出し"
		);

		$this->assertSame( "- [最初の記事]({$url}): 抜粋\r\n# 見出し", $content );
	}

	/**
	 * 確実に対応づけられない行は修復しないこと。
	 *
	 * @return void
	 */
	public function test_repair_leaves_lines_it_cannot_match() {
		$first_id = self::factory()->post->create(
			array(
				'post_title' => '同じタイトル',
				'post_name'  => 'あい',
			)
		);
		self::factory()->post->create(
			array(
				'post_title' => '同じタイトル',
				'post_name'  => 'かき',
			)
		);
		$unique_id = self::factory()->post->create(
			array(
				'post_title' => '固有のタイトル',
				'post_name'  => 'さし',
			)
		);
		$broken    = sanitize_textarea_field( get_permalink( $first_id ) );
		$this->assertSame( $broken, sanitize_textarea_field( get_permalink( $unique_id ) ), '前提: 壊れた URL は衝突する' );

		$saved = implode(
			"\n",
			array(
				"- [同じタイトル]({$broken}): 候補が 2 件ある",
				"- [別のタイトル]({$broken}): タイトルが一致しない",
				'- [固有のタイトル](https://other.example/): URL が一致しない',
				"[固有のタイトル]({$broken}): 箇条書きではない",
				"本文中の [固有のタイトル]({$broken}): リンク",
			)
		);

		$this->assertSame( $saved, $this->run_once_over_legacy_content( $saved ) );
	}

	/**
	 * URL がすでに正しい行を、同じタイトルの別の投稿の URL へ書き換えないこと。
	 *
	 * /newsあ/ の壊れた形は /news/ なので、/news/ の行と同じ形になる。
	 *
	 * @return void
	 */
	public function test_repair_keeps_lines_whose_url_is_already_correct() {
		$correct_id = self::factory()->post->create(
			array(
				'post_title' => '同じタイトル',
				'post_name'  => 'news',
			)
		);
		$other_id   = self::factory()->post->create(
			array(
				'post_title' => '同じタイトル',
				'post_name'  => 'newsあ',
			)
		);
		$correct    = get_permalink( $correct_id );
		$this->assertSame( $correct, sanitize_textarea_field( get_permalink( $other_id ) ), '前提: 壊れた形が衝突する' );

		$saved = "- [同じタイトル]({$correct}): 抜粋";

		$this->assertSame( $saved, $this->run_once_over_legacy_content( $saved ) );
	}

	/**
	 * 照合順序で同じとみなされるだけのタイトルの投稿へ書き換えないこと。
	 *
	 * @return void
	 */
	public function test_repair_requires_exact_title_match() {
		$post_id = self::factory()->post->create(
			array(
				'post_title' => 'ばな',
				'post_name'  => 'あい',
			)
		);

		$saved = '- [はな](' . sanitize_textarea_field( get_permalink( $post_id ) ) . '): 抜粋';

		$this->assertSame( $saved, $this->run_once_over_legacy_content( $saved ) );
	}

	/**
	 * 開いている間に隠した投稿の行が、編集せずに保存しても配信されないこと。
	 *
	 * @return void
	 */
	public function test_unedited_form_does_not_serve_entry_of_post_hidden_meanwhile() {
		$post_id = self::factory()->post->create( array( 'post_title' => '隠す記事' ) );
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, '- [隠す記事](' . get_permalink( $post_id ) . "): 秘密\n# 見出し" );
		$fields = $this->open_settings_screen();

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'private',
			)
		);
		$this->submit_settings_form( $fields );

		$this->assertSame( '# 見出し', AVC_AIS_Llms_Txt::init()->get_served_content() );
	}

	/**
	 * 開いている間に削除した投稿の行が、編集せずに保存しても戻らないこと。
	 *
	 * @return void
	 */
	public function test_unedited_form_does_not_restore_entry_of_post_deleted_meanwhile() {
		$post_id = self::factory()->post->create( array( 'post_title' => '消す記事' ) );
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, '- [消す記事](' . get_permalink( $post_id ) . "): 秘密\n# 見出し" );
		$fields = $this->open_settings_screen();

		wp_delete_post( $post_id, true );
		$this->submit_settings_form( $fields );

		$this->assertSame( '# 見出し', get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
	}

	/**
	 * 編集して保存しても、開いている間に隠した投稿の行が配信されないこと。
	 *
	 * @dataProvider provide_hidden_post_changes
	 *
	 * @param array $change 投稿に加える変更.
	 * @return void
	 */
	public function test_edited_form_does_not_serve_entry_of_post_hidden_meanwhile( array $change ) {
		$post_id = self::factory()->post->create( array( 'post_content' => 'TOPSECRETEXCERPT' ) );
		$fields  = $this->open_settings_screen();
		$this->assertStringContainsString( 'TOPSECRETEXCERPT', $fields['llms_txt_content'], '前提: 開いたときは載っている' );

		wp_update_post( array_merge( array( 'ID' => $post_id ), $change ) );
		$fields['llms_txt_content'] .= "\r\n# 追記";
		$this->submit_settings_form( $fields );

		$served = AVC_AIS_Llms_Txt::init()->get_served_content();
		$this->assertStringNotContainsString( 'TOPSECRETEXCERPT', $served );
		$this->assertStringEndsWith( '# 追記', $served );
	}

	/**
	 * 開いている間に投稿を隠す変更を返す。
	 *
	 * @return array[]
	 */
	public function provide_hidden_post_changes() {
		return array(
			'private'            => array( array( 'post_status' => 'private' ) ),
			'password protected' => array( array( 'post_password' => 'hunter2' ) ),
			'draft'              => array( array( 'post_status' => 'draft' ) ),
			'trash'              => array( array( 'post_status' => 'trash' ) ),
		);
	}

	/**
	 * 編集して保存しても、開いている間に削除した投稿の行が戻らないこと。
	 *
	 * 同じ保存で追記した内容と、外部サイトへの行は残す。
	 *
	 * @return void
	 */
	public function test_edited_form_does_not_restore_entry_of_post_deleted_meanwhile() {
		$post_id = self::factory()->post->create( array( 'post_content' => 'TOPSECRETEXCERPT' ) );
		$fields  = $this->open_settings_screen();
		$this->assertStringContainsString( 'TOPSECRETEXCERPT', $fields['llms_txt_content'], '前提: 開いたときは載っている' );

		wp_delete_post( $post_id, true );
		$fields['llms_txt_content'] .= "\r\n- [外部](https://other.example/): 説明\r\n# 追記";
		$this->submit_settings_form( $fields );

		$saved = get_option( AVC_AIS_Llms_Txt::OPTION_NAME );
		$this->assertStringNotContainsString( 'TOPSECRETEXCERPT', $saved );
		$this->assertStringEndsWith( "- [外部](https://other.example/): 説明\n# 追記", $saved );
	}

	/**
	 * 古い画面でも、投稿に対応しないサイト内の行を追記すればそのまま保存すること。
	 *
	 * 一覧ページやトップページへの手書きの行を、削除した投稿の行と取り違えない。
	 *
	 * @return void
	 */
	public function test_edited_form_keeps_added_site_entries_when_screen_is_stale() {
		$fields  = $this->open_settings_screen();
		$lines   = array(
			'- [ブログ](' . home_url( '/blog/' ) . '): 記事の一覧',
			'- [ホーム](' . home_url( '/' ) . '): トップページ',
		);
		$post_id = self::factory()->post->create( array( 'post_title' => '開いている間の記事' ) );
		$this->assertNotEmpty( $post_id, '前提: 画面が古くなる' );

		$fields['llms_txt_content'] .= "\r\n" . implode( "\r\n", $lines );
		$this->submit_settings_form( $fields );

		$this->assertStringEndsWith( implode( "\n", $lines ), get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
	}

	/**
	 * 削除した投稿と同じ URL の投稿を作り直したら、古い画面の行を取り除かないこと。
	 *
	 * @return void
	 */
	public function test_edited_form_keeps_entry_of_recreated_post() {
		$post_id = self::factory()->post->create(
			array(
				'post_title' => '作り直す記事',
				'post_name'  => 'recreated',
			)
		);
		$url     = get_permalink( $post_id );
		$fields  = $this->open_settings_screen();

		wp_delete_post( $post_id, true );
		$new_id = self::factory()->post->create(
			array(
				'post_title' => '作り直した記事',
				'post_name'  => 'recreated',
			)
		);
		$this->assertSame( $url, get_permalink( $new_id ), '前提: 同じ URL になる' );

		$fields['llms_txt_content'] .= "\r\n# 追記";
		$this->submit_settings_form( $fields );

		$this->assertStringContainsString( "- [作り直す記事]({$url}):", get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
	}

	/**
	 * 削除した投稿の URL は期限を過ぎると忘れ、件数にも上限があること。
	 *
	 * @return void
	 */
	public function test_deleted_urls_are_pruned() {
		update_option(
			AVC_AIS_Llms_Txt::OPTION_DELETED_URLS,
			array(
				'/expired' => time() - AVC_AIS_Llms_Txt::DELETED_URL_TTL - 60,
				'/recent'  => time() - 60,
			),
			false
		);

		$post_id = self::factory()->post->create( array( 'post_name' => 'just-deleted' ) );
		wp_delete_post( $post_id, true );

		$deleted = get_option( AVC_AIS_Llms_Txt::OPTION_DELETED_URLS );
		$this->assertArrayNotHasKey( '/expired', $deleted );
		$this->assertArrayHasKey( '/recent', $deleted );
		$this->assertArrayHasKey( '/just-deleted', $deleted );

		$many = array();
		for ( $i = 0; $i < AVC_AIS_Llms_Txt::DELETED_URL_LIMIT + 5; $i++ ) {
			$many[ "/old-{$i}" ] = time();
		}
		update_option( AVC_AIS_Llms_Txt::OPTION_DELETED_URLS, $many, false );

		$post_id = self::factory()->post->create( array( 'post_name' => 'over-limit' ) );
		wp_delete_post( $post_id, true );

		$deleted = get_option( AVC_AIS_Llms_Txt::OPTION_DELETED_URLS );
		$this->assertCount( AVC_AIS_Llms_Txt::DELETED_URL_LIMIT, $deleted );
		$this->assertArrayHasKey( '/over-limit', $deleted );
	}

	/**
	 * 保存ボタンは実際に保存した内容を返し、隠した投稿の行も画面に残すこと。
	 *
	 * @return void
	 */
	public function test_save_button_returns_content_actually_stored() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => '非公開の記事',
				'post_status' => 'private',
				'post_name'   => 'hidden',
			)
		);
		$url     = home_url( '/hidden/' );
		$this->assertNotEmpty( $post_id, '前提: 投稿がある' );

		$response = $this->post_llms_txt_ajax(
			'avc_ais_save_llms_txt',
			'handle_save_llms_txt',
			array( 'content' => "- [非公開の記事]({$url}): 秘密\n<b>太字</b>の見出し" )
		);

		$expected = "- [非公開の記事]({$url}): 秘密\n太字の見出し";
		$this->assertSame( $expected, get_option( AVC_AIS_Llms_Txt::OPTION_NAME ) );
		$this->assertSame( $expected, $response['content'] );
		$this->assertSame( AVC_AIS_Llms_Txt::content_hash( $expected ), $response['hash'] );
		$this->assertSame( '太字の見出し', AVC_AIS_Llms_Txt::init()->get_served_content() );
	}
}

<?php
/**
 * セットアップ案内通知の表示条件に関する回帰テスト。
 *
 * ウィザードを通さず設定画面から直接入力したサイトでも
 * 「セットアップを開始」の案内が出続けていた不具合の再発を防ぐ。
 */

require_once __DIR__ . '/bootstrap.php';

use Aivec\AiSearchSchema\Wizard\Wizard;

class WizardNoticeTest extends WP_UnitTestCase {

	/**
	 * Wizard インスタンス。
	 *
	 * @var Wizard
	 */
	private $wizard;

	/**
	 * テスト用ユーザーID。
	 *
	 * @var int
	 */
	private $user_id;

	protected function setUp(): void {
		parent::setUp();
		AVC_AIS_TEST_Env::$options = array();

		$this->user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->user_id );

		delete_option( 'avc_ais_options' );
		delete_option( 'avc_ais_wizard_completed' );

		$this->wizard = new Wizard();
	}

	protected function tearDown(): void {
		delete_option( 'avc_ais_options' );
		delete_option( 'avc_ais_wizard_completed' );
		delete_user_meta( $this->user_id, 'avc_ais_wizard_notice_dismissed' );
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * 通知の出力内容を取得する。
	 *
	 * @return string 出力された HTML.
	 */
	private function capture_notice() {
		ob_start();
		$this->wizard->show_setup_notice();
		return (string) ob_get_clean();
	}

	/**
	 * 未設定・ウィザード未完了なら通知が出ること。
	 */
	public function test_notice_is_shown_when_nothing_is_configured() {
		$this->assertStringContainsString( 'ais-wizard-notice', $this->capture_notice() );
	}

	/**
	 * 設定画面から company_name を入力していれば通知が出ないこと。
	 * ウィザードは未完了のまま（これが本来の再現ケース）。
	 */
	public function test_notice_is_hidden_when_settings_are_filled_without_wizard() {
		update_option(
			'avc_ais_options',
			array(
				'company_name' => 'Aivec LLC',
				'entity_type'  => 'Organization',
			)
		);

		$this->assertFalse( $this->wizard->is_completed(), 'ウィザードは未完了のままであること' );
		$this->assertTrue( $this->wizard->has_essential_settings() );
		$this->assertSame( '', $this->capture_notice() );
	}

	/**
	 * ウィザードの 1 ステップ目だけ保存して離脱した場合は通知を出し続けること。
	 * Basic Information は company_name しか書き込まないため、
	 * これだけで「設定済み」と判定してはいけない。
	 */
	public function test_notice_is_shown_when_only_the_first_wizard_step_is_saved() {
		update_option( 'avc_ais_options', array( 'company_name' => 'Aivec LLC' ) );

		$this->assertFalse( $this->wizard->has_essential_settings() );
		$this->assertStringContainsString( 'ais-wizard-notice', $this->capture_notice() );
	}

	/**
	 * entity_type だけでは「設定済み」と判定しないこと。
	 */
	public function test_entity_type_alone_is_not_enough() {
		update_option( 'avc_ais_options', array( 'entity_type' => 'Organization' ) );

		$this->assertFalse( $this->wizard->has_essential_settings() );
	}

	/**
	 * ウィザードを完了していれば通知が出ないこと（従来の挙動）。
	 */
	public function test_notice_is_hidden_when_wizard_is_completed() {
		update_option( 'avc_ais_wizard_completed', true );

		$this->assertSame( '', $this->capture_notice() );
	}

	/**
	 * 空文字や空白のみの company_name は未設定として扱うこと。
	 */
	public function test_blank_company_name_is_treated_as_unconfigured() {
		update_option(
			'avc_ais_options',
			array(
				'company_name' => '   ',
				'entity_type'  => 'Organization',
			)
		);

		$this->assertFalse( $this->wizard->has_essential_settings() );
		$this->assertStringContainsString( 'ais-wizard-notice', $this->capture_notice() );
	}

	/**
	 * オプションが配列でなくても例外にならないこと。
	 */
	public function test_non_array_option_is_handled_safely() {
		update_option( 'avc_ais_options', 'broken' );

		$this->assertFalse( $this->wizard->has_essential_settings() );
	}

	/**
	 * 非表示にしたユーザーには通知が出ないこと（従来の挙動）。
	 */
	public function test_notice_is_hidden_after_dismissal() {
		update_user_meta( $this->user_id, 'avc_ais_wizard_notice_dismissed', 1 );

		$this->assertSame( '', $this->capture_notice() );
	}

	/**
	 * 権限のないユーザーには通知が出ないこと。
	 */
	public function test_notice_is_hidden_for_users_without_capability() {
		$subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber );

		$this->assertSame( '', $this->capture_notice() );
	}
}

<?php
/**
 * アンインストール処理のテスト。
 *
 * @package Aivec\AiSearchSchema\Tests
 */

require_once __DIR__ . '/bootstrap.php';

use Aivec\AiSearchSchema\Plugin;
use Aivec\AiSearchSchema\Wizard\Wizard;

/**
 * アンインストール処理のテストクラス。
 */
class UninstallTest extends WP_UnitTestCase {

	/**
	 * 設定・API キー・ウィザードのデータを削除すること。
	 */
	public function test_uninstall_removes_plugin_data() {
		$options = array(
			'avc_ais_options',
			'avc_ais_gmaps_api_key',
			'avc_ais_llms_txt',
			'avc_ais_llms_txt_enabled',
			'avc_ais_llms_txt_scrubbed',
			'avc_ais_llms_txt_rule_restored',
			Wizard::OPTION_COMPLETED,
			'avc_ais_wizard_completed_at',
			'avc_ais_wizard_version',
			Wizard::OPTION_API_KEY_PURGED,
		);
		foreach ( $options as $option ) {
			update_option( $option, 'value' );
		}
		set_transient( 'avc_ais_wizard_redirect', true, 30 );
		set_transient( 'avc_ais_last_validation_errors', array( 'error' ), 30 );

		$user_id = self::factory()->user->create();
		update_user_meta( $user_id, Wizard::META_PROGRESS, array( 'data' => array() ) );
		update_user_meta( $user_id, 'avc_ais_wizard_notice_dismissed', '1' );

		Plugin::uninstall();

		foreach ( $options as $option ) {
			$this->assertFalse( get_option( $option ), $option );
		}
		$this->assertFalse( get_transient( 'avc_ais_wizard_redirect' ) );
		$this->assertFalse( get_transient( 'avc_ais_last_validation_errors' ) );
		$this->assertSame( '', get_user_meta( $user_id, Wizard::META_PROGRESS, true ) );
		$this->assertSame( '', get_user_meta( $user_id, 'avc_ais_wizard_notice_dismissed', true ) );
	}

	/**
	 * マルチサイトでは全サイトの設定を削除すること。
	 *
	 * @group ms-required
	 */
	public function test_uninstall_covers_all_sites_on_multisite() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'マルチサイト環境でのみ実行する。' );
		}

		$site_id = self::factory()->blog->create();
		update_option( 'avc_ais_gmaps_api_key', 'main-site-key' );
		switch_to_blog( $site_id );
		update_option( 'avc_ais_gmaps_api_key', 'sub-site-key' );
		restore_current_blog();

		Plugin::uninstall();

		$this->assertFalse( get_option( 'avc_ais_gmaps_api_key' ) );
		switch_to_blog( $site_id );
		$this->assertFalse( get_option( 'avc_ais_gmaps_api_key' ) );
		restore_current_blog();
	}

	/**
	 * 他のプラグインのデータと投稿ごとの設定には触れないこと。
	 */
	public function test_uninstall_keeps_unrelated_data() {
		update_option( 'blogname', 'Keep me' );
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_avc_ais_meta', array( 'page_type' => 'Article' ) );

		Plugin::uninstall();

		$this->assertSame( 'Keep me', get_option( 'blogname' ) );
		$this->assertSame( array( 'page_type' => 'Article' ), get_post_meta( $post_id, '_avc_ais_meta', true ) );
	}
}

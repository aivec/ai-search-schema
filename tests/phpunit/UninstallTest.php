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
	 * 処理のための一時的なデータを削除すること。
	 */
	public function test_uninstall_removes_temporary_data() {
		$options = array(
			'avc_ais_llms_txt_scrubbed',
			'avc_ais_llms_txt_urls_repaired',
			'avc_ais_llms_txt_deleted_urls',
			'avc_ais_llms_txt_rule_restored',
			Wizard::OPTION_API_KEY_PURGED,
		);
		foreach ( $options as $option ) {
			update_option( $option, 'value' );
		}
		set_transient( 'avc_ais_wizard_redirect', true, 30 );
		set_transient( 'avc_ais_last_validation_errors', array( 'error' ), 30 );

		$user_id = self::factory()->user->create();
		update_user_meta( $user_id, Wizard::META_PROGRESS, array( 'data' => array() ) );

		Plugin::uninstall();

		foreach ( $options as $option ) {
			$this->assertFalse( get_option( $option ), $option );
		}
		$this->assertFalse( get_transient( 'avc_ais_wizard_redirect' ) );
		$this->assertFalse( get_transient( 'avc_ais_last_validation_errors' ) );
		$this->assertSame( '', get_user_meta( $user_id, Wizard::META_PROGRESS, true ) );
	}

	/**
	 * /llms.txt の rewrite ルールだけを消し、他のルールには触れないこと。
	 */
	public function test_uninstall_removes_only_llms_txt_rewrite_rule() {
		update_option(
			'rewrite_rules',
			array(
				AVC_AIS_Llms_Txt::REWRITE_REGEX => 'index.php?llms_txt=1',
				'other/?$'                      => 'index.php?other=1',
			)
		);

		Plugin::uninstall();

		$this->assertSame( array( 'other/?$' => 'index.php?other=1' ), get_option( 'rewrite_rules' ) );
	}

	/**
	 * パーマリンクが基本設定のサイトでも、ルールの処理で失敗しないこと。
	 */
	public function test_uninstall_keeps_empty_rewrite_rules() {
		update_option( 'rewrite_rules', '' );

		Plugin::uninstall();

		$this->assertSame( '', get_option( 'rewrite_rules' ) );
	}

	/**
	 * 再インストールで入力し直さなくて済むよう、設定と API キーは残すこと。
	 */
	public function test_uninstall_keeps_settings() {
		$kept = array(
			'avc_ais_options'             => array( 'company_name' => 'Aivec' ),
			'avc_ais_gmaps_api_key'       => 'stored-key',
			'avc_ais_llms_txt'            => '# Custom',
			'avc_ais_llms_txt_enabled'    => '0',
			Wizard::OPTION_COMPLETED      => '1',
			'avc_ais_wizard_completed_at' => '2026-11-04 00:00:00',
			'avc_ais_wizard_version'      => '1.3.2',
		);
		foreach ( $kept as $option => $value ) {
			update_option( $option, $value );
		}
		$user_id = self::factory()->user->create();
		update_user_meta( $user_id, 'avc_ais_wizard_notice_dismissed', '1' );

		Plugin::uninstall();

		foreach ( $kept as $option => $value ) {
			$this->assertSame( $value, get_option( $option ), $option );
		}
		$this->assertSame( '1', get_user_meta( $user_id, 'avc_ais_wizard_notice_dismissed', true ) );
	}

	/**
	 * プラグインがない間に非公開にした投稿の行が、再インストール後の配信で隠されること。
	 */
	public function test_kept_llms_txt_content_hides_post_hidden_while_uninstalled() {
		global $wpdb;

		$post_id = self::factory()->post->create( array( 'post_title' => '隠す記事' ) );
		$saved   = '- [隠す記事](' . get_permalink( $post_id ) . "): 秘密\n# 見出し";
		update_option( AVC_AIS_Llms_Txt::OPTION_NAME, $saved );

		Plugin::uninstall();

		// プラグインがない間はフックが動かないので、データベースを直接更新する。
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update( $wpdb->posts, array( 'post_status' => 'private' ), array( 'ID' => $post_id ) );
		clean_post_cache( $post_id );

		$this->assertSame( '# 見出し', AVC_AIS_Llms_Txt::init()->get_served_content() );
		$this->assertSame( $saved, get_option( AVC_AIS_Llms_Txt::OPTION_NAME ), '保存済みの行は残す' );
	}

	/**
	 * マルチサイトでは全サイトの一時的なデータを削除し、設定を残すこと。
	 *
	 * @group ms-required
	 */
	public function test_uninstall_covers_all_sites_on_multisite() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'マルチサイト環境でのみ実行する。' );
		}

		$rules   = array( AVC_AIS_Llms_Txt::REWRITE_REGEX => 'index.php?llms_txt=1' );
		$site_id = self::factory()->blog->create();
		update_option( 'avc_ais_llms_txt_scrubbed', true );
		update_option( 'rewrite_rules', $rules );
		update_option( 'avc_ais_gmaps_api_key', 'main-site-key' );
		switch_to_blog( $site_id );
		update_option( 'avc_ais_llms_txt_scrubbed', true );
		update_option( 'rewrite_rules', $rules );
		update_option( 'avc_ais_gmaps_api_key', 'sub-site-key' );
		restore_current_blog();

		Plugin::uninstall();

		$this->assertFalse( get_option( 'avc_ais_llms_txt_scrubbed' ) );
		$this->assertSame( array(), get_option( 'rewrite_rules' ) );
		$this->assertSame( 'main-site-key', get_option( 'avc_ais_gmaps_api_key' ) );
		switch_to_blog( $site_id );
		$this->assertFalse( get_option( 'avc_ais_llms_txt_scrubbed' ) );
		$this->assertSame( array(), get_option( 'rewrite_rules' ) );
		$this->assertSame( 'sub-site-key', get_option( 'avc_ais_gmaps_api_key' ) );
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

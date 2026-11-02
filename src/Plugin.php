<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName
/**
 * プラグインのエントリーポイントを担うクラス
 */

namespace Aivec\AiSearchSchema;

defined( 'ABSPATH' ) || exit;

use Aivec\AiSearchSchema\Wizard\Wizard;
use Aivec\AiSearchSchema\Wp\Breadcrumbs;
use Aivec\AiSearchSchema\Wp\MetaBox;
use Aivec\AiSearchSchema\Wp\Schema;
use Aivec\AiSearchSchema\Wp\Settings;

/**
 * プラグインの初期化や依存ファイルの読み込みを行うクラス
 */
class Plugin {
	/**
	 * WordPress へのフック登録を行う
	 */
	public function register(): void {
		\add_action( 'init', array( $this, 'load_textdomain' ), 5 );
		\add_action( 'init', array( $this, 'bootstrap' ) );
	}

	/**
	 * 翻訳ファイルの読み込みを行う
	 */
	public function load_textdomain(): void {
		$domain = 'aivec-ai-search-schema';
		$locale = \determine_locale();
		$locale = \apply_filters( 'plugin_locale', $locale, $domain );
		$locale = $locale ?: 'en_US';

		$language_dir = \trailingslashit( AVC_AIS_DIR . 'languages' );
		$mofile       = $language_dir . $domain . '-' . $locale . '.mo';

		\unload_textdomain( $domain );

		if ( \file_exists( $mofile ) ) {
			\load_textdomain( $domain, $mofile );
			return;
		}

		$separator = \strpos( $locale, '_' );
		$short     = false !== $separator ? \substr( $locale, 0, $separator ) : $locale;
		if ( $short && $short !== $locale ) {
			$fallback = $language_dir . $domain . '-' . $short . '.mo';
			if ( \file_exists( $fallback ) ) {
				\load_textdomain( $domain, $fallback );
				return;
			}
		}

	}

	/**
	 * プラグイン本体の初期化を行う
	 */
	public function bootstrap(): void {
		$this->load_dependencies();

		Wp\Settings::init();
		Wp\MetaBox::init();
		Wp\Schema::init();
		Wp\Breadcrumbs::init();

		// Setup Wizard.
		if ( \is_admin() ) {
			$wizard = new Wizard();
			$wizard->register();

		}
	}

	/**
	 * プラグインで使用するファイルを読み込む
	 */
	private function load_dependencies(): void {
		$includes = array(
			// Core utilities.
			'includes/functions.php',
			'includes/class-avc-ais-prefectures.php',
		);

		foreach ( $includes as $relative_path ) {
			$path = AVC_AIS_DIR . $relative_path;
			if ( \file_exists( $path ) ) {
				require_once $path;
			}
		}
	}

	/**
	 * アンインストール時の処理を行う
	 *
	 * 設定と Google Maps API キーを残さないよう削除する。投稿ごとの設定
	 * （_avc_ais_meta / _ais_product）はコンテンツの一部として残す。
	 */
	public static function uninstall(): void {
		if ( \is_multisite() ) {
			$site_ids = \get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			);
			foreach ( $site_ids as $site_id ) {
				\switch_to_blog( $site_id );
				self::delete_site_data();
				\restore_current_blog();
			}
		} else {
			self::delete_site_data();
		}

		// user meta はネットワーク全体で共有されるためサイトごとには消さない。
		\delete_metadata( 'user', 0, Wizard::META_PROGRESS, '', true );
		\delete_metadata( 'user', 0, 'avc_ais_wizard_notice_dismissed', '', true );
	}

	/**
	 * 現在のサイトに保存したプラグインのデータを削除する
	 *
	 * ハッシュ付きの一時データ（avc_ais_diag_* / avc_ais_geocode_*）は短時間で失効し、
	 * WordPress の定期処理で消えるため対象にしない。
	 */
	private static function delete_site_data(): void {
		$options = array(
			'avc_ais_options',
			'avc_ais_gmaps_api_key',
			'avc_ais_llms_txt',
			'avc_ais_llms_txt_enabled',
			'avc_ais_llms_txt_scrubbed',
			Wizard::OPTION_COMPLETED,
			'avc_ais_wizard_completed_at',
			'avc_ais_wizard_version',
			Wizard::OPTION_API_KEY_PURGED,
		);
		foreach ( $options as $option ) {
			\delete_option( $option );
		}

		\delete_transient( 'avc_ais_wizard_redirect' );
		\delete_transient( 'avc_ais_last_validation_errors' );
	}
}

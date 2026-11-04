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
	 * 再インストールのたびに入力し直さなくて済むよう、設定、Google Maps API キー、
	 * llms.txt の内容、ウィザードの完了状態、投稿ごとの設定は残し、処理のための一時的な
	 * データだけを削除する。API キーが不要なら設定画面の削除ボタンで消せる。
	 */
	public static function uninstall(): void {
		// 無効な状態で呼ばれるので、このクラスはまだ読み込まれていない。
		require_once AVC_AIS_DIR . 'includes/class-avc-ais-llms-txt.php';

		if ( \is_multisite() ) {
			$site_ids = \get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			);
			foreach ( $site_ids as $site_id ) {
				\switch_to_blog( $site_id );
				self::delete_temporary_site_data();
				\restore_current_blog();
			}
		} else {
			self::delete_temporary_site_data();
		}

		// ウィザードの入力はステップごとに設定へ保存されるので、進捗を消しても入力は失われない。
		// user meta はネットワーク全体で共有されるためサイトごとには消さない。
		\delete_metadata( 'user', 0, Wizard::META_PROGRESS, '', true );
	}

	/**
	 * 現在のサイトに保存した処理のための一時的なデータを削除する
	 *
	 * 一度きりの処理の記録も消して再インストール後にやり直す。
	 * /llms.txt の rewrite ルールも消す。残るとトップページを返してしまう。
	 * ハッシュ付きの一時データ（avc_ais_diag_* / avc_ais_geocode_*）は短時間で失効し、
	 * WordPress の定期処理で消えるため対象にしない。
	 */
	private static function delete_temporary_site_data(): void {
		$options = array(
			\AVC_AIS_Llms_Txt::OPTION_SCRUBBED,
			\AVC_AIS_Llms_Txt::OPTION_URLS_REPAIRED,
			\AVC_AIS_Llms_Txt::OPTION_RULE_RESTORED,
			Wizard::OPTION_API_KEY_PURGED,
		);
		foreach ( $options as $option ) {
			\delete_option( $option );
		}

		\AVC_AIS_Llms_Txt::remove_rewrite_rule();

		\delete_transient( 'avc_ais_wizard_redirect' );
		\delete_transient( 'avc_ais_last_validation_errors' );
	}
}

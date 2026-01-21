<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName
/**
 * プラグインのエントリーポイントを担うクラス
 */

namespace Aivec\AiSearchSchema;

use Aivec\AiSearchSchema\License\Client;
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
		$domain = 'ai-search-schema';
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

		\load_plugin_textdomain( $domain, false, \dirname( \plugin_basename( AVC_AIS_FILE ) ) . '/languages/' );
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

			// cptm-client ベースの更新チェッカーを初期化（依存パッケージが存在する場合のみ）.
			if ( \class_exists( \Aivec\Welcart\CptmClient\Client::class ) ) {
				$update_client = new Client(
					AVC_AIS_FILE,
					'ai-search-schema',
					AVC_AIS_VERSION
				);
				$update_client->initUpdateChecker();
			}
		}
	}

	/**
	 * プラグインで使用するファイルを読み込む
	 */
	private function load_dependencies(): void {
		$includes = array(
			// Core utilities.
			'includes/functions.php',
			'includes/class-ai-search-schema-prefectures.php',
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
	 */
	public static function uninstall(): void {
		// Reserved for uninstall routines (options, transient cleanup, etc).
	}
}

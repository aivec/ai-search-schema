<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName
/**
 * 商用プラグイン更新クライアント
 *
 * cptm-client を拡張し、Welcart 非依存で動作するように変更。
 *
 * @package Aivec\AiSearchSchema\License
 */

namespace Aivec\AiSearchSchema\License;

defined( 'ABSPATH' ) || exit;

use Aivec\Welcart\CptmClient\Client as CptmClient;

/**
 * AI Search Schema 用の更新チェッカークライアント
 *
 * cptm-client を継承し、Welcart 依存（USCES_VERSION）を除去した実装。
 */
class Client extends CptmClient {

	/**
	 * プラグインファイルパス
	 *
	 * @var string
	 */
	private $plugin_file;

	/**
	 * 製品ユニークID
	 *
	 * @var string
	 */
	private $product_id;

	/**
	 * プラグインバージョン
	 *
	 * @var string
	 */
	private $version;

	/**
	 * コンストラクタ
	 *
	 * @param string $plugin_file プラグインファイルの絶対パス.
	 * @param string $product_id  製品ユニークID.
	 * @param string $version     プラグインバージョン.
	 */
	public function __construct( string $plugin_file, string $product_id, string $version ) {
		parent::__construct( $plugin_file, $product_id, $version );

		// 親クラスのプロパティは private なので、子クラス用に保持.
		$this->plugin_file = $plugin_file;
		$this->product_id  = $product_id;
		$this->version     = $version;
	}

	/**
	 * 更新チェッカーを初期化（Welcart 非依存版）
	 *
	 * 親クラスの initUpdateChecker() をオーバーライドし、
	 * USCES_VERSION（Welcart）への依存を除去。
	 *
	 * @param string|null $endpoint_override エンドポイントURL上書き.
	 * @return void
	 */
	public function initUpdateChecker( $endpoint_override = null ) {
		global $wp_version, $wpdb;

		$url = $this->getUpdateEndpoint();
		if ( is_string( $endpoint_override ) && ! empty( $endpoint_override ) ) {
			$url = $endpoint_override;
		}

		// データベースサーバー情報を取得.
		$server_info = '';
		if ( isset( $wpdb->use_mysqli ) && $wpdb->use_mysqli && isset( $wpdb->dbh ) ) {
			$server_info = mysqli_get_server_info( $wpdb->dbh );
		}

		// phpcs:ignore PHPCompatibility.Classes.NewClasses.puc_v4_factoryFound
		\Puc_v4_Factory::buildUpdateChecker(
			add_query_arg(
				array(
					'wcexcptm_update_action'     => 'get_metadata',
					'wcexcptm_cptitem_unique_id' => $this->product_id,
					'domain'                     => $this->getHost(),
					'productVersion'             => $this->version,
					'wordpressVersion'           => $wp_version,
					'phpVersion'                 => phpversion(),
					'webServer'                  => isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '',
					'databaseInfo'               => $server_info,
					'databaseVersion'            => $wpdb->db_version(),
				),
				$url . '/wp-update-server/'
			),
			$this->plugin_file,
			'',
			1
		);
	}
}

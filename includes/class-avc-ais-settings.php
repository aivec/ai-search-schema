<?php
/**
 * AVC_AIS_Settings クラス
 *
 * プラグインの一般設定を管理するクラスです。
 * 設定項目の保存や表示を行います。
 */
defined( 'ABSPATH' ) || exit;
class AVC_AIS_Settings {
	private const OPTION_NAME                = 'avc_ais_options';
	private const OPTION_GMAPS_KEY           = 'avc_ais_gmaps_api_key';
	private const GEOCODE_RATE_LIMIT_SECONDS = 10;

	/**
	 * @var AVC_AIS_Settings|null
	 */
	private static $instance = null;

	/**
	 * 初期化
	 *
	 * @return AVC_AIS_Settings
	 */
	public static function init() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * コンストラクタ
	 */
	public function __construct() {
		// Filter null values from options at the earliest point to prevent PHP 8 deprecation warnings.
		add_filter( 'option_' . self::OPTION_NAME, array( $this, 'filter_option_on_get' ), 1 );

		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_avc_ais_geocode', array( $this, 'handle_geocode_request' ) );
		add_action( 'wp_ajax_avc_ais_regenerate_llms_txt', array( $this, 'handle_regenerate_llms_txt' ) );
		add_action( 'wp_ajax_avc_ais_save_llms_txt', array( $this, 'handle_save_llms_txt' ) );
	}

	/**
	 * Filter null values when option is retrieved from database.
	 *
	 * This prevents PHP 8 deprecation warnings when WordPress internal
	 * functions process the option values.
	 *
	 * @param mixed $value Option value from database.
	 * @return mixed Filtered value with nulls removed.
	 */
	public function filter_option_on_get( $value ) {
		if ( is_array( $value ) ) {
			return $this->filter_null_values( $value );
		}
		return $value;
	}

	/**
	 * 設定ページを追加
	 */
	public function add_settings_page() {
		// トップレベルメニューを追加.
		add_menu_page(
			__( 'AI Search Schema Settings', 'aivec-ai-search-schema' ),
			__( 'AI Search Schema', 'aivec-ai-search-schema' ),
			'manage_options',
			'aivec-ai-search-schema',
			array( $this, 'render_settings_page' ),
			'dashicons-schema',
			81
		);

		// メイン設定ページをサブメニューとして追加（トップレベルと同じページ）.
		add_submenu_page(
			'aivec-ai-search-schema',
			__( 'AI Search Schema Settings', 'aivec-ai-search-schema' ),
			__( 'Settings', 'aivec-ai-search-schema' ),
			'manage_options',
			'aivec-ai-search-schema',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * 設定を登録
	 */
	public function register_settings() {
		register_setting( self::OPTION_NAME, self::OPTION_NAME, array( $this, 'sanitize_options' ) );
	}

	/**
	 * 管理画面用のスクリプトを読み込み
	 *
	 * @param string $hook 現在の管理画面フック名
	 */
	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_aivec-ai-search-schema' !== $hook ) {
			return;
		}

		wp_enqueue_media();

		$style_path    = AVC_AIS_DIR . 'assets/dist/css/admin.min.css';
		$style_version = file_exists( $style_path ) ? filemtime( $style_path ) : AVC_AIS_VERSION;
		wp_enqueue_style(
			'ais-admin',
			AVC_AIS_URL . 'assets/dist/css/admin.min.css',
			array(),
			$style_version
		);

		// Diagnostic styles (moved from inline to enqueue).
		$diagnostic_css  = '.ais-diagnostic-list{display:grid;gap:12px}';
		$diagnostic_css .= '.ais-diagnostic-group{border:1px solid #e4e7eb;border-radius:12px;padding:12px}';
		$diagnostic_css .= '.ais-diagnostic-title{margin:0 0 8px;font-weight:600;font-size:14px}';
		$diagnostic_css .= '.ais-diagnostic-item{display:flex;align-items:center;gap:8px;margin:6px 0}';
		$diagnostic_css .= '.ais-status-badge{display:inline-flex;align-items:center;';
		$diagnostic_css .= 'padding:2px 8px;border-radius:999px;font-size:12px;font-weight:600}';
		$diagnostic_css .= '.ais-status-ok{background:#e6f4ea;color:#1a7f37}';
		$diagnostic_css .= '.ais-status-info{background:#e8f4fd;color:#0969da}';
		$diagnostic_css .= '.ais-status-warning{background:#fff4e5;color:#b06100}';
		$diagnostic_css .= '.ais-status-error{background:#fdecea;color:#b3261e}';
		wp_add_inline_style( 'ais-admin', $diagnostic_css );

		// Dev mode test manager link styles.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$dev_css  = '.ais-dev-test-manager-link{position:fixed;bottom:20px;right:20px;';
			$dev_css .= 'display:flex;align-items:center;gap:8px;padding:10px 16px;';
			$dev_css .= 'background:#1e1e1e;color:#fff;border-radius:4px;text-decoration:none;';
			$dev_css .= 'font-size:13px;font-weight:500;box-shadow:0 2px 8px rgba(0,0,0,0.2);';
			$dev_css .= 'z-index:9999;transition:background 0.2s,transform 0.2s}';
			$dev_css .= '.ais-dev-test-manager-link:hover{background:#2271b1;color:#fff;';
			$dev_css .= 'transform:translateY(-2px)}';
			$dev_css .= '.ais-dev-test-manager-link .dashicons{font-size:18px;width:18px;height:18px}';
			$dev_css .= '.ais-dev-test-manager-link::before{content:"DEV";position:absolute;';
			$dev_css .= 'top:-8px;left:-8px;background:#d63638;color:#fff;font-size:9px;';
			$dev_css .= 'font-weight:700;padding:2px 5px;border-radius:3px}';
			wp_add_inline_style( 'ais-admin', $dev_css );
		}

		$script_path    = AVC_AIS_DIR . 'assets/js/admin-settings.js';
		$script_version = file_exists( $script_path ) ? filemtime( $script_path ) : AVC_AIS_VERSION;

		wp_enqueue_script(
			'ais-settings',
			AVC_AIS_URL . 'assets/js/admin-settings.js',
			array( 'jquery' ),
			$script_version,
			true
		);

		$gmaps_api_key = $this->get_google_maps_api_key();

		wp_localize_script(
			'ais-settings',
			'aisSettings',
			array(
				'socialOptions'             => $this->get_social_network_choices(),
				'languageOptions'           => $this->get_language_choices(),
				/* translators: %s: The language label shown as a tag. */
				'i18nLanguageRemove'        => __( 'Remove "%s"', 'aivec-ai-search-schema' ),
				'ajaxUrl'                   => admin_url( 'admin-ajax.php' ),
				'geocodeNonce'              => wp_create_nonce( 'avc_ais_geocode' ),
				'i18nGeocodeReady'          => __( 'Fetch coordinates from address', 'aivec-ai-search-schema' ),
				'i18nGeocodeWorking'        => __( 'Fetching...', 'aivec-ai-search-schema' ),
				'i18nGeocodeSuccess'        => __( 'Latitude and longitude updated.', 'aivec-ai-search-schema' ),
				'i18nGeocodeFailure'        => __(
					'Unable to fetch latitude and longitude.',
					'aivec-ai-search-schema'
				),
				'i18nGeocodeMissingAddress' => __(
					'Enter the address information.',
					'aivec-ai-search-schema'
				),
				'i18nGeocodeMissingKey'     => __(
					'Please configure a Google Maps API key.',
					'aivec-ai-search-schema'
				),
				'hasGeocodeApiKey'          => ! empty( $gmaps_api_key ),
				// phpcs:ignore Generic.Files.LineLength.TooLong -- 翻訳文字列は分割できない
				'i18nEntityHelpOrg'         => __( 'The primary role of this site is defined as an "Organization". You can also enter headquarters or branch office information in the "Local details & hours" section below to associate your organization with a physical location and enhance visibility in local search results.', 'aivec-ai-search-schema' ),
				// phpcs:ignore Generic.Files.LineLength.TooLong -- 翻訳文字列は分割できない
				'i18nEntityHelpLB'          => __( 'The primary role of this site is defined as a "Store/Business location". This setting is ideal when the site itself represents a specific physical store or service location.', 'aivec-ai-search-schema' ),
				'llmsTxtNonce'              => wp_create_nonce( 'avc_ais_regenerate_llms_txt' ),
				'llmsTxtSaveNonce'          => wp_create_nonce( 'avc_ais_save_llms_txt' ),
				'i18nLlmsTxtRegenerating'   => __( 'Regenerating...', 'aivec-ai-search-schema' ),
				'i18nLlmsTxtRegenerate'     => __( 'Regenerate from site data', 'aivec-ai-search-schema' ),
				'i18nLlmsTxtSaving'         => __( 'Saving...', 'aivec-ai-search-schema' ),
				'i18nLlmsTxtSave'           => __( 'Save edits', 'aivec-ai-search-schema' ),
				'i18nLlmsTxtSaved'          => __( 'Saved!', 'aivec-ai-search-schema' ),
			)
		);
	}

	/**
	 * Geocode住所から緯度経度を取得するAJAXハンドラ
	 */
	public function handle_geocode_request() {
		// セキュリティ: nonceバイパスはテスト環境（PHPUnit実行中）かつWP_DEBUGが有効な場合のみ許可
		$bypass_nonce = false;
		$is_test_env  = defined( 'WP_TESTS_DIR' ) || defined( 'PHPUNIT_COMPOSER_INSTALL' );
		if ( $is_test_env && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			/**
			 * テスト環境でのみ有効なnonceバイパスフィルター。
			 * 本番環境（WP_DEBUG=false または テスト環境外）では常に無効。
			 *
			 * @since 0.14.4
			 * @since 0.16.4 テスト環境かつWP_DEBUG有効時のみに制限
			 *
			 * @param bool $bypass_nonce nonceチェックをスキップするかどうか。デフォルトfalse。
			 */
			$bypass_nonce = apply_filters( 'avc_ais_bypass_geocode_nonce', false );
		}
		if ( ! $bypass_nonce ) {
			check_ajax_referer( 'avc_ais_geocode', 'nonce' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			$this->respond_json(
				false,
				array(
					'message' => __( 'You do not have permission to perform this action.', 'aivec-ai-search-schema' ),
				),
				403
			);
		}

		$wait_seconds = $this->claim_geocode_slot();
		if ( $wait_seconds > 0 ) {
			/* translators: %d: number of seconds an admin must wait before requesting new coordinates. */
			$message_format = __(
				'Please wait %d seconds before requesting new coordinates.',
				'aivec-ai-search-schema'
			);
			$this->respond_json(
				false,
				array(
					'message' => sprintf(
						$message_format,
						$wait_seconds
					),
				),
				429
			);
		}

		$address_data   = isset( $_POST['address'] ) ? (array) wp_unslash( $_POST['address'] ) : array();
		$postal_code    = sanitize_text_field( $address_data['postal_code'] ?? '' );
		$region         = sanitize_text_field( $address_data['region'] ?? '' );
		$locality       = sanitize_text_field( $address_data['locality'] ?? '' );
		$street_address = sanitize_text_field( $address_data['street_address'] ?? ( $address_data['street'] ?? '' ) );
		$country        = sanitize_text_field( $address_data['country'] ?? '' );
		$parts          = array_filter(
			array(
				$postal_code,
				$region,
				$locality,
				$street_address,
				$country,
			)
		);

		if ( empty( $parts ) ) {
			$this->respond_json(
				false,
				array( 'message' => __( 'Enter the address information.', 'aivec-ai-search-schema' ) )
			);
		}

		$query = implode( ', ', $parts );

		$api_key = $this->get_google_maps_api_key();
		if ( empty( $api_key ) ) {
			$fallback = $this->geocode_with_nominatim( $query );
			if ( is_wp_error( $fallback ) ) {
				$this->respond_json( false, array( 'message' => $fallback->get_error_message() ) );
			}
			$this->respond_json( true, $fallback );
		}

		$response = $this->geocode_with_google_maps( $query, $api_key );

		if ( is_wp_error( $response ) ) {
			$this->respond_json( false, array( 'message' => $response->get_error_message() ), 500 );
		}

		$this->respond_json( true, $response );
	}

	/**
	 * AJAX handler for regenerating llms.txt content.
	 */
	public function handle_regenerate_llms_txt() {
		check_ajax_referer( 'avc_ais_regenerate_llms_txt', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			$this->respond_json(
				false,
				array(
					'message' => __( 'You do not have permission to perform this action.', 'aivec-ai-search-schema' ),
				),
				403
			);
		}

		require_once AVC_AIS_DIR . 'includes/class-avc-ais-llms-txt.php';
		$llms_txt = AVC_AIS_Llms_Txt::init();
		$content  = $llms_txt->reset_content();

		$this->respond_json(
			true,
			array(
				'content' => $content,
				'message' => __( 'llms.txt content has been regenerated from site data.', 'aivec-ai-search-schema' ),
			)
		);
	}

	/**
	 * AJAX handler for saving llms.txt content.
	 */
	public function handle_save_llms_txt() {
		check_ajax_referer( 'avc_ais_save_llms_txt', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			$this->respond_json(
				false,
				array(
					'message' => __( 'You do not have permission to perform this action.', 'aivec-ai-search-schema' ),
				),
				403
			);
		}

		$content = isset( $_POST['content'] ) ? sanitize_textarea_field( wp_unslash( $_POST['content'] ) ) : '';

		require_once AVC_AIS_DIR . 'includes/class-avc-ais-llms-txt.php';
		$llms_txt = AVC_AIS_Llms_Txt::init();

		if ( ! empty( $content ) ) {
			$llms_txt->save_content( $content );
		} else {
			$llms_txt->reset_content();
		}

		$this->respond_json(
			true,
			array(
				'message' => __( 'llms.txt content has been saved.', 'aivec-ai-search-schema' ),
			)
		);
	}

	/**
	 * Google Maps APIキーを取得
	 *
	 * @return string
	 */
	private function get_google_maps_api_key() {
		$key = get_option( self::OPTION_GMAPS_KEY, '' );
		if ( empty( $key ) ) {
			$options = get_option( self::OPTION_NAME, array() );
			if ( is_array( $options ) && ! empty( $options['gmaps_api_key'] ) ) {
				$key = sanitize_text_field( $options['gmaps_api_key'] );
				unset( $options['gmaps_api_key'] );
				update_option( self::OPTION_NAME, $options );
				update_option( self::OPTION_GMAPS_KEY, $key, false );
			}
		}
		/**
		 * フィルター: avc_ais_google_maps_api_key
		 *
		 * @param string $key 現在のAPIキー
		 */
		return (string) apply_filters( 'avc_ais_google_maps_api_key', sanitize_text_field( (string) $key ) );
	}

	/**
	 * Google Maps Geocoding APIで住所を検索
	 *
	 * @param string $query
	 * @param string $api_key
	 * @return array|\WP_Error
	 */
	private function geocode_with_google_maps( $query, $api_key ) {
		$endpoint = add_query_arg(
			array(
				'address'  => $query,
				'key'      => $api_key,
				'language' => get_locale(),
			),
			'https://maps.googleapis.com/maps/api/geocode/json'
		);

		$response = wp_remote_get(
			$endpoint,
			array(
				'timeout'     => 12,
				'redirection' => 2,
				'headers'     => array(
					'User-Agent' => 'AI-Search-Schema/' . AVC_AIS_VERSION . ' (' . home_url( '/' ) . ')',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || empty( $body ) ) {
			return new \WP_Error(
				'avc_ais_geocode_http_error',
				__( 'The geocoding API returned an error.', 'aivec-ai-search-schema' )
			);
		}

		if ( isset( $body['error_message'] ) ) {
			return new \WP_Error( 'avc_ais_geocode_api_error', $body['error_message'] );
		}

		if ( empty( $body['results'][0]['geometry']['location'] ) ) {
			return new \WP_Error(
				'avc_ais_geocode_not_found',
				__( 'Unable to fetch latitude and longitude.', 'aivec-ai-search-schema' )
			);
		}

		$location   = $body['results'][0]['geometry']['location'];
		$lat        = round( (float) $location['lat'], 6 );
		$lng        = round( (float) $location['lng'], 6 );
		$components = $this->extract_google_address_components( $body['results'][0]['address_components'] ?? array() );

		return array(
			'latitude'   => (string) $lat,
			'longitude'  => (string) $lng,
			'query'      => $body['results'][0]['formatted_address'] ?? $query,
			'provider'   => 'google',
			'components' => $components,
		);
	}

	/**
	 * Google Maps の address_components から住所要素を抽出する.
	 *
	 * @param array $components
	 * @return array<string,string>
	 */
	private function extract_google_address_components( $components ) {
		if ( empty( $components ) || ! is_array( $components ) ) {
			return array();
		}

		$route         = '';
		$street_number = '';
		$premise       = '';
		$subpremise    = '';
		$result        = array(
			'address_locality' => '',
			'street_address'   => '',
			'address_region'   => '',
			'postal_code'      => '',
			'country'          => '',
		);

		foreach ( $components as $component ) {
			$types = isset( $component['types'] ) ? (array) $component['types'] : array();
			$long  = isset( $component['long_name'] ) ? (string) $component['long_name'] : '';
			$short = isset( $component['short_name'] ) ? (string) $component['short_name'] : $long;

			if ( in_array( 'locality', $types, true ) ) {
				$result['address_locality'] = $long;
			} elseif (
					in_array( 'administrative_area_level_2', $types, true )
					&& empty( $result['address_locality'] )
				) {
				// 一部の市区町村は administrative_area_level_2 に入るためフォールバック.
				$result['address_locality'] = $long;
			}

			if ( in_array( 'administrative_area_level_1', $types, true ) ) {
				$result['address_region'] = $long;
			}

			if ( in_array( 'postal_code', $types, true ) ) {
				$result['postal_code'] = $long;
			}

			if ( in_array( 'country', $types, true ) ) {
				$result['country'] = $short ? $short : $long;
			}

			if ( in_array( 'route', $types, true ) ) {
				$route = $long;
			}

			if ( in_array( 'street_number', $types, true ) ) {
				$street_number = $long;
			}

			if ( in_array( 'premise', $types, true ) ) {
				$premise = $long;
			}

			if ( in_array( 'subpremise', $types, true ) ) {
				$subpremise = $long;
			}
		}

		$building     = trim( implode( ' ', array_filter( array( $premise, $subpremise ) ) ) );
		$street_parts = array_filter(
			array(
				$route,
				$street_number,
				$building,
			)
		);

		if ( ! empty( $street_parts ) ) {
			$result['street_address'] = trim( implode( ' ', $street_parts ) );
		}

		foreach ( $result as $key => $value ) {
			$result[ $key ] = sanitize_text_field( $value );
		}

		return array_filter(
			$result,
			function ( $value ) {
				return '' !== $value;
			}
		);
	}

	/**
	 * Nominatimによるフォールバック
	 *
	 * @param string $query
	 * @return array|\WP_Error
	 */
	private function geocode_with_nominatim( $query ) {
		$args = array(
			'format'         => 'json',
			'limit'          => 1,
			'addressdetails' => 0,
			'q'              => $query,
		);

		$admin_email = get_bloginfo( 'admin_email' );
		if ( $admin_email ) {
			$args['email'] = sanitize_email( $admin_email );
		}

		$endpoint = add_query_arg( $args, 'https://nominatim.openstreetmap.org/search' );

		$response = wp_remote_get(
			$endpoint,
			array(
				'timeout' => 15,
				'headers' => array(
					'User-Agent' => 'AI-Search-Schema/' . AVC_AIS_VERSION . ' (' . home_url( '/' ) . ')',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new \WP_Error(
				'avc_ais_geocode_http_error',
				__( 'The geocoding API returned an error.', 'aivec-ai-search-schema' )
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( empty( $data ) || empty( $data[0]['lat'] ) || empty( $data[0]['lon'] ) ) {
			return new \WP_Error(
				'avc_ais_geocode_not_found',
				__( 'Unable to fetch latitude and longitude.', 'aivec-ai-search-schema' )
			);
		}

		return array(
			'latitude'  => (string) $data[0]['lat'],
			'longitude' => (string) $data[0]['lon'],
			'query'     => $query,
			'provider'  => 'nominatim',
			'notice'    => __(
				'No Google Maps API key was found, so OpenStreetMap (for development) was used instead.',
				'aivec-ai-search-schema'
			),
		);
	}

	/**
	 * 設定ページの表示
	 */
	public function render_settings_page() {
		$options                 = $this->get_options();
		$option_name             = self::OPTION_NAME;
		$social_choices          = $this->get_social_network_choices();
		$content_models          = $this->get_content_model_choices();
		$language_choices        = $this->get_language_choices();
		$country_choices         = $this->get_country_choices();
		$weekday_choices         = $this->get_weekday_choices();
		$prefecture_choices      = AVC_AIS_Prefectures::get_prefecture_choices();
		$gmaps_api_key_set       = ! empty( $this->get_google_maps_api_key() );
		$content_type_settings   = $options['content_type_settings'] ?? array();
		$content_type_post_types = $this->get_configurable_post_types();
		$content_type_taxonomies = $this->get_configurable_taxonomies();
		$content_type_defaults   = $this->get_content_type_settings_defaults();
		$schema_type_choices     = $this->get_schema_type_choices();
		$schema_priority_choices = $this->get_schema_priority_choices();
		$diagnostics             = $this->get_diagnostics_report( $options );
		$is_dev_mode             = $this->is_dev_mode();
		$test_manager_url        = $is_dev_mode ? AVC_AIS_URL . 'tools/test-manager.php' : '';
		$template                = AVC_AIS_DIR . 'templates/admin-settings.php';

		if ( file_exists( $template ) ) {
			include $template;
		} else {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'Settings template could not be located.', 'aivec-ai-search-schema' )
			);
		}
	}

	/**
	 * Check if the plugin is running in dev mode.
	 *
	 * Dev mode is detected by the presence of the tools directory
	 * which is only included in dev builds.
	 *
	 * @return bool True if dev mode is active.
	 */
	public function is_dev_mode() {
		return file_exists( AVC_AIS_DIR . 'tools/test-manager.php' );
	}

	/**
	 * オプションをサニタイズ
	 *
	 * @param array $input 入力されたオプション
	 * @return array サニタイズされたオプション
	 */
	public function sanitize_options( $input ) {
		if ( ! is_array( $input ) ) {
			$input = array();
		} else {
			// Filter null values before wp_unslash to prevent PHP 8 deprecation warnings.
			$input = $this->filter_null_values( $input );
			$input = wp_unslash( $input );
		}

		$output = $this->get_options();

		$output['company_name'] = sanitize_text_field( $input['company_name'] ?? '' );

		$logo_id            = isset( $input['logo_id'] ) ? absint( $input['logo_id'] ) : 0;
		$logo_url           = $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : '';
		$output['logo_id']  = $logo_id;
		$output['logo_url'] = $logo_url ? $logo_url : esc_url_raw( $input['logo_url'] ?? '' );

		$lb_image_id            = isset( $input['lb_image_id'] ) ? absint( $input['lb_image_id'] ) : 0;
		$lb_image_url           = $lb_image_id ? wp_get_attachment_image_url( $lb_image_id, 'full' ) : '';
		$output['lb_image_id']  = $lb_image_id;
		$output['lb_image_url'] = $lb_image_url ? $lb_image_url : esc_url_raw( $input['lb_image_url'] ?? '' );

			$store_image_id            = isset( $input['store_image_id'] ) ? absint( $input['store_image_id'] ) : 0;
			$store_image_url           = $store_image_id ? wp_get_attachment_image_url( $store_image_id, 'full' ) : '';
			$output['store_image_id']  = $store_image_id;
			$output['store_image_url'] = $store_image_url
				? $store_image_url
				: esc_url_raw( $input['store_image_url'] ?? '' );
		if ( empty( $output['store_image_url'] ) && ! empty( $output['lb_image_url'] ) ) {
			$output['store_image_id']  = $output['lb_image_id'];
			$output['store_image_url'] = $output['lb_image_url'];
		}

		$output['phone'] = sanitize_text_field( $input['phone'] ?? '' );

		$country = sanitize_text_field( $input['country_code'] ?? '' );
		if ( ! array_key_exists( $country, $this->get_country_choices() ) ) {
			$country = $this->get_default_country();
		}
		$output['country_code'] = $country;

		$languages_input = $input['languages'] ?? $input['language'] ?? array();
		if ( ! is_array( $languages_input ) ) {
			$languages_input = array( $languages_input );
		}
		$language_choices    = $this->get_language_choices();
		$languages_sanitized = array();
		foreach ( $languages_input as $lang ) {
			$lang = sanitize_text_field( $lang );
			if ( $lang && array_key_exists( $lang, $language_choices ) ) {
				$languages_sanitized[] = $lang;
			}
		}
		if ( empty( $languages_sanitized ) ) {
			$languages_sanitized[] = $this->get_default_language();
		}
		$languages_sanitized = array_values( array_unique( $languages_sanitized ) );
		$output['languages'] = $languages_sanitized;
		$output['language']  = $languages_sanitized[0];
		$output['site_name'] = sanitize_text_field( $input['site_name'] ?? '' );
		$output['site_url']  = esc_url_raw( $input['site_url'] ?? '' );

		$entity_type           = sanitize_text_field( $input['entity_type'] ?? 'Organization' );
		$output['entity_type'] = in_array( $entity_type, array( 'Organization', 'LocalBusiness' ), true )
			? $entity_type
			: 'Organization';

		$publisher_entity           = sanitize_text_field( $input['publisher_entity'] ?? 'Organization' );
		$output['publisher_entity'] = in_array( $publisher_entity, array( 'Organization', 'LocalBusiness' ), true )
			? $publisher_entity
			: 'Organization';

		$lb_subtype_raw          = $input['lb_subtype'] ?? $input['business_type'] ?? 'LocalBusiness';
		$lb_subtype              = sanitize_text_field( $lb_subtype_raw );
		$lb_subtype              = $lb_subtype ? $lb_subtype : 'LocalBusiness';
		$output['lb_subtype']    = $lb_subtype;
		$output['business_type'] = $lb_subtype;

		$output['local_business_label'] = sanitize_text_field( $input['local_business_label'] ?? '' );

		$address             = $input['address'] ?? array();
		$prefecture_choices  = AVC_AIS_Prefectures::get_prefecture_choices();
		$prefecture          = sanitize_text_field( $address['prefecture'] ?? '' );
		$legacy_region_value = sanitize_text_field( $address['region'] ?? '' );
		if ( '' === $prefecture && isset( $prefecture_choices[ $legacy_region_value ] ) ) {
			$prefecture = $legacy_region_value;
		}
		if ( '' !== $prefecture && ! isset( $prefecture_choices[ $prefecture ] ) ) {
			$prefecture = '';
		}
		$prefecture_iso = AVC_AIS_Prefectures::get_iso_code( $prefecture );
		$country        = strtoupper( sanitize_text_field( $address['country'] ?? 'JP' ) );
		if ( '' === $country ) {
			$country = 'JP';
		}
	// phpcs:disable Generic.Formatting.MultipleStatementAlignment.NotSameWarning
		$address_locality = sanitize_text_field( $address['city'] ?? $address['locality'] ?? '' );
		$street_address   = sanitize_text_field( $address['address_line'] ?? $address['street_address'] ?? '' );
		$output['address'] = array(
			'postal_code'    => sanitize_text_field( $address['postal_code'] ?? '' ),
			'prefecture'     => $prefecture,
			'prefecture_iso' => $prefecture_iso,
			'region'         => ( '' !== $prefecture ) ? $prefecture : $legacy_region_value,
			'locality'       => $address_locality,
			'city'           => $address_locality,
			'street_address' => $street_address,
			'address_line'   => $street_address,
			'country'        => $country,
		);
		// phpcs:enable Generic.Formatting.MultipleStatementAlignment.NotSameWarning
			$output['localbusiness_address_locality'] = sanitize_text_field(
				$input['localbusiness_address_locality'] ?? $address_locality
			);
			$output['localbusiness_street_address']   = sanitize_text_field(
				$input['localbusiness_street_address'] ?? $street_address
			);

		$geo           = $input['geo'] ?? array();
		$output['geo'] = array(
			'latitude'  => $this->format_geo_value( $geo['latitude'] ?? '' ),
			'longitude' => $this->format_geo_value( $geo['longitude'] ?? '' ),
		);

		$output['holiday_enabled'] = ! empty( $input['holiday_enabled'] );
		$holiday_mode              = isset( $input['holiday_mode'] )
			? sanitize_text_field( $input['holiday_mode'] )
			: 'custom';
		if ( ! in_array( $holiday_mode, array( 'weekday', 'weekend', 'custom' ), true ) ) {
			$holiday_mode = 'custom';
		}
		$output['holiday_mode']  = $holiday_mode;
		$output['holiday_slots'] = array();

		$map_enabled_flag = null;
		if ( array_key_exists( 'has_map_enabled', $input ) ) {
			$map_enabled_flag = ! empty( $input['has_map_enabled'] );
		}

		$map_enabled_legacy = null;
		if ( array_key_exists( 'has_map', $input ) ) {
			$legacy_input = trim( sanitize_text_field( $input['has_map'] ) );
			$legacy_bool  = filter_var( $legacy_input, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
			if ( null !== $legacy_bool ) {
				$map_enabled_legacy = $legacy_bool;
			}
		}

		if ( null === $map_enabled_flag && null !== $map_enabled_legacy ) {
			$map_enabled_flag = $map_enabled_legacy;
		}

		$output['has_map_enabled'] = (bool) ( $map_enabled_flag ?? false );
		$gmaps_key_input           = isset( $input['gmaps_api_key'] ) ? trim( (string) $input['gmaps_api_key'] ) : null;
		$gmaps_key_action          = isset( $input['gmaps_api_key_action'] )
			? sanitize_text_field( $input['gmaps_api_key_action'] )
			: '';
		if ( 'clear' === $gmaps_key_action ) {
			delete_option( self::OPTION_GMAPS_KEY );
		} elseif ( '' !== $gmaps_key_input && null !== $gmaps_key_input ) {
			update_option( self::OPTION_GMAPS_KEY, sanitize_text_field( $gmaps_key_input ) );
		}
		$output['price_range']                       = sanitize_text_field( $input['price_range'] ?? '' );
		$output['payment_accepted']                  = sanitize_text_field( $input['payment_accepted'] ?? '' );
		$output['accepts_reservations']              = ! empty( $input['accepts_reservations'] );
		$output['skip_local_business_if_incomplete'] = ! empty( $input['skip_local_business_if_incomplete'] );

			$priority_value = isset( $input['avc_ais_priority'] ) ?
				sanitize_text_field( $input['avc_ais_priority'] ) :
				'ais';
		if ( ! in_array( $priority_value, array( 'ais', 'external' ), true ) ) {
			$priority_value = 'ais';
		}
		$output['avc_ais_priority'] = $priority_value;

		if ( array_key_exists( 'avc_ais_breadcrumbs_schema_enabled', $input ) ) {
			$breadcrumbs_schema_enabled = ! empty( $input['avc_ais_breadcrumbs_schema_enabled'] );
		} else {
			$breadcrumbs_schema_enabled = ! empty( $input['enable_breadcrumbs'] );
		}

		if ( array_key_exists( 'avc_ais_breadcrumbs_html_enabled', $input ) ) {
			$breadcrumbs_html_enabled = ! empty( $input['avc_ais_breadcrumbs_html_enabled'] );
		} else {
			$breadcrumbs_html_enabled = ! empty( $input['enable_breadcrumbs'] );
		}

		$output['avc_ais_breadcrumbs_schema_enabled'] = $breadcrumbs_schema_enabled;
		$output['avc_ais_breadcrumbs_html_enabled']   = $breadcrumbs_html_enabled;
		$output['enable_breadcrumbs']                 = $breadcrumbs_schema_enabled;

		$social_links = array();
		if ( ! empty( $input['social_links'] ) && is_array( $input['social_links'] ) ) {
			$allowed_networks = array_keys( $this->get_social_network_choices() );
			foreach ( $input['social_links'] as $row ) {
				if ( empty( $row['account'] ) ) {
					continue;
				}
				$network = sanitize_text_field( $row['network'] ?? '' );
				if ( ! in_array( $network, $allowed_networks, true ) ) {
					$network = 'other';
				}
				$account        = sanitize_text_field( $row['account'] ?? '' );
				$label          = sanitize_text_field( $row['label'] ?? '' );
				$social_links[] = array(
					'network' => $network,
					'account' => $account,
					'label'   => $label,
				);
			}
		}
		$output['social_links'] = $social_links;

		$content_model           = sanitize_text_field( $input['content_model'] ?? 'WebPage' );
		$output['content_model'] = array_key_exists( $content_model, $this->get_content_model_choices() )
			? $content_model
			: 'WebPage';

		$opening_hours_entries = array();
		if ( ! empty( $input['opening_hours'] ) && is_array( $input['opening_hours'] ) ) {
			$opening_hours_entries = $this->normalize_opening_hours( $input['opening_hours'] );
		}

		$output['opening_hours_raw'] = sanitize_textarea_field( $input['opening_hours_raw'] ?? '' );
		if ( empty( $opening_hours_entries ) && $output['opening_hours_raw'] ) {
			$opening_hours_entries = $this->normalize_opening_hours(
				$this->parse_opening_hours( $output['opening_hours_raw'] )
			);
		}

		$output['opening_hours'] = $opening_hours_entries;

			$content_type_defaults       = $this->get_content_type_settings_defaults();
			$has_content_type_input      = ! empty( $input['content_type_settings'] )
				&& is_array( $input['content_type_settings'] );
		$content_type_input              = $has_content_type_input
			? $input['content_type_settings']
			: array();
		$output['content_type_settings'] = $this->sanitize_content_type_settings(
			$content_type_input,
			$content_type_defaults,
			$this->should_translate_labels()
		);

		// llms.txt settings.
		$llms_txt_enabled = ! empty( $input['llms_txt_enabled'] );
		$llms_txt_content = isset( $input['llms_txt_content'] )
			? sanitize_textarea_field( $input['llms_txt_content'] )
			: '';

		require_once AVC_AIS_DIR . 'includes/class-avc-ais-llms-txt.php';
		$llms_txt         = AVC_AIS_Llms_Txt::init();
		$previous_enabled = $llms_txt->is_enabled();
		$llms_txt->set_enabled( $llms_txt_enabled );
		if ( ! empty( $llms_txt_content ) ) {
			$llms_txt->save_content( $llms_txt_content );
		} else {
			// If content is empty, reset to auto-generate.
			$llms_txt->reset_content();
		}

		// Flush rewrite rules if llms.txt enabled status changed.
		if ( $llms_txt_enabled !== $previous_enabled ) {
			flush_rewrite_rules();
		}

		return $output;
	}

	/**
	 * 翻訳を実行しても早すぎないかを判定する.
	 *
	 * @return bool
	 */
	private function should_translate_labels() {
		return did_action( 'init' ) || doing_action( 'init' );
	}

	/**
	 * 保存済みオプションを取得し不足分を埋める
	 *
	 * @return array
	 */
	public function get_options() {
		$translate_labels = $this->should_translate_labels();

		$defaults = array(
			'company_name'                       => '',
			'logo_id'                            => 0,
			'logo_url'                           => '',
			'lb_image_id'                        => 0,
			'lb_image_url'                       => '',
			'store_image_id'                     => 0,
			'store_image_url'                    => '',
			'phone'                              => '',
			'country_code'                       => $this->get_default_country( $translate_labels ),
			'languages'                          => array( $this->get_default_language( $translate_labels ) ),
			'language'                           => $this->get_default_language( $translate_labels ),
			'site_name'                          => get_bloginfo( 'name' ),
			'site_url'                           => get_site_url(),
			'entity_type'                        => 'Organization',
			'publisher_entity'                   => 'Organization',
			'business_type'                      => 'LocalBusiness',
			'lb_subtype'                         => 'LocalBusiness',
			'avc_ais_priority'                   => 'ais',
			'avc_ais_breadcrumbs_schema_enabled' => true,
			'avc_ais_breadcrumbs_html_enabled'   => false,
			'enable_breadcrumbs'                 => false,
			'local_business_label'               => '',
			'localbusiness_address_locality'     => '',
			'localbusiness_street_address'       => '',
			'address'                            => array(
				'postal_code'    => '',
				'prefecture'     => '',
				'prefecture_iso' => '',
				'region'         => '',
				'locality'       => '',
				'city'           => '',
				'street_address' => '',
				'address_line'   => '',
				'country'        => 'JP',
			),
			'geo'                                => array(
				'latitude'  => '',
				'longitude' => '',
			),
			'has_map_enabled'                    => false,
			'skip_local_business_if_incomplete'  => false,
			'holiday_enabled'                    => true,
			'holiday_mode'                       => 'custom',
			'holiday_slots'                      => array(),
			'price_range'                        => '',
			'payment_accepted'                   => '',
			'accepts_reservations'               => false,
			'social_links'                       => array(),
			'content_model'                      => 'WebPage',
			'opening_hours_raw'                  => '',
			'opening_hours'                      => array(),
			'content_type_settings'              => array(),
		);

		$options = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $options ) ) {
			$options = array();
		}

		// Filter out null values before wp_parse_args to ensure defaults are applied.
		// wp_parse_args does not replace null values with defaults.
		$options = $this->filter_null_values( $options );

		$content_model_choices = $this->get_content_model_choices( $translate_labels );
		if (
			! empty( $options['content_model'] )
			&& ! array_key_exists( $options['content_model'], $content_model_choices )
		) {
			$options['content_model'] = 'WebPage';
		}

		$merged = wp_parse_args( $options, $defaults );
		if ( isset( $merged['gmaps_api_key'] ) ) {
			unset( $merged['gmaps_api_key'] );
		}

		if ( ! is_array( $merged['social_links'] ) ) {
			$merged['social_links'] = array();
		}

		if ( ! array_key_exists( 'avc_ais_priority', $merged ) ) {
			$merged['avc_ais_priority'] = 'ais';
		}

		if ( ! array_key_exists( 'avc_ais_breadcrumbs_schema_enabled', $merged ) ) {
			$merged['avc_ais_breadcrumbs_schema_enabled'] = ! empty( $merged['enable_breadcrumbs'] );
		}

		if ( ! array_key_exists( 'avc_ais_breadcrumbs_html_enabled', $merged ) ) {
			$merged['avc_ais_breadcrumbs_html_enabled'] = ! empty( $merged['enable_breadcrumbs'] );
		}

		$merged['enable_breadcrumbs'] = (bool) $merged['avc_ais_breadcrumbs_schema_enabled'];
		if ( ! is_array( $merged['address'] ) ) {
			$merged['address'] = $defaults['address'];
		}
		$merged['address'] = wp_parse_args( $merged['address'], $defaults['address'] );
		if ( ! is_array( $merged['geo'] ) ) {
			$merged['geo'] = $defaults['geo'];
		}
		$merged['geo'] = wp_parse_args( $merged['geo'], $defaults['geo'] );
		if ( ! is_array( $merged['opening_hours'] ) ) {
			$merged['opening_hours'] = array();
		}
		$merged['opening_hours'] = $this->normalize_opening_hours(
			$merged['opening_hours'],
			$translate_labels
		);
		if (
				empty( $merged['opening_hours'] )
				&& ! empty( $merged['opening_hours_raw'] )
			) {
			$merged['opening_hours'] = $this->normalize_opening_hours(
				$this->parse_opening_hours( $merged['opening_hours_raw'] ),
				$translate_labels
			);
		}
		if ( ! is_array( $merged['languages'] ) ) {
			if ( ! empty( $merged['language'] ) ) {
				$merged['languages'] = array( $merged['language'] );
			} else {
				$merged['languages'] = array( $this->get_default_language( $translate_labels ) );
			}
		}
		$language_choices    = $this->get_language_choices( $translate_labels );
		$merged['languages'] = array_values(
			array_filter(
				array_map(
					function ( $lang ) use ( $language_choices ) {
						return array_key_exists( $lang, $language_choices ) ? $lang : null;
					},
					$merged['languages']
				)
			)
		);
		if ( empty( $merged['languages'] ) ) {
			$merged['languages'][] = $this->get_default_language( $translate_labels );
		}
		$merged['language'] = $merged['languages'][0];

		if (
			! array_key_exists( $merged['country_code'], $this->get_country_choices( $translate_labels ) )
		) {
			$merged['country_code'] = $this->get_default_country( $translate_labels );
		}

		if (
			! empty( $merged['address']['region'] )
			&& empty( $merged['address']['prefecture'] )
		) {
			$choices = AVC_AIS_Prefectures::get_prefecture_choices();
			if ( isset( $choices[ $merged['address']['region'] ] ) ) {
				$merged['address']['prefecture'] = $merged['address']['region'];
			}
		}
		if ( empty( $merged['address']['country'] ) ) {
			$merged['address']['country'] = 'JP';
		}
		if (
			empty( $merged['localbusiness_address_locality'] )
			&& ! empty( $merged['address']['locality'] )
		) {
			$merged['localbusiness_address_locality'] = $merged['address']['locality'];
		}
		if (
			empty( $merged['address']['locality'] )
			&& ! empty( $merged['localbusiness_address_locality'] )
		) {
			$merged['address']['locality'] = $merged['localbusiness_address_locality'];
		}
		if (
			empty( $merged['localbusiness_street_address'] )
			&& ! empty( $merged['address']['street_address'] )
		) {
			$merged['localbusiness_street_address'] = $merged['address']['street_address'];
		}
		if (
			empty( $merged['address']['street_address'] )
			&& ! empty( $merged['localbusiness_street_address'] )
		) {
			$merged['address']['street_address'] = $merged['localbusiness_street_address'];
		}
		if (
				! empty( $merged['address']['prefecture'] )
				&& empty( $merged['address']['prefecture_iso'] )
			) {
			$merged['address']['prefecture_iso'] = AVC_AIS_Prefectures::get_iso_code(
				$merged['address']['prefecture']
			);
		}
		$merged['has_map_enabled'] = ! empty( $merged['has_map_enabled'] );

		if ( ! in_array( $merged['publisher_entity'], array( 'Organization', 'LocalBusiness' ), true ) ) {
			$merged['publisher_entity'] = 'Organization';
		}

		if ( empty( $merged['lb_subtype'] ) ) {
			$merged['lb_subtype'] = $merged['business_type'];
		}
		$merged['business_type'] = $merged['lb_subtype'];

		if ( ! isset( $merged['local_business_label'] ) ) {
			$merged['local_business_label'] = '';
		}

		if ( ! isset( $merged['store_image_id'] ) ) {
			$merged['store_image_id'] = isset( $merged['lb_image_id'] ) ? $merged['lb_image_id'] : 0;
		}
		if ( ! isset( $merged['store_image_url'] ) ) {
			$merged['store_image_url'] = isset( $merged['lb_image_url'] ) ? $merged['lb_image_url'] : '';
		}

		$content_defaults                = $this->get_content_type_settings_defaults();
		$has_content_type_settings       = isset( $merged['content_type_settings'] )
			&& is_array( $merged['content_type_settings'] );
		$stored_content_settings         = $has_content_type_settings
			? $merged['content_type_settings']
			: array();
		$merged['content_type_settings'] = $this->sanitize_content_type_settings(
			$stored_content_settings,
			$content_defaults,
			$translate_labels
		);

		// Replace null values with empty strings to avoid PHP 8 deprecation warnings.
		return $this->replace_null_values( $merged );
	}

	/**
	 * Recursively replace null values with empty strings.
	 *
	 * @param array $data The array to process.
	 * @return array The array with null values replaced.
	 */
	private function replace_null_values( array $data ) {
		foreach ( $data as $key => $value ) {
			if ( is_null( $value ) ) {
				$data[ $key ] = '';
			} elseif ( is_array( $value ) ) {
				$data[ $key ] = $this->replace_null_values( $value );
			}
		}
		return $data;
	}

	/**
	 * Recursively filter out null values from an array.
	 *
	 * This is used before wp_parse_args() to ensure defaults are applied
	 * for keys with null values, since wp_parse_args() does not treat
	 * null as "not set".
	 *
	 * @param array $data The array to process.
	 * @return array The array with null values removed.
	 */
	private function filter_null_values( array $data ) {
		$result = array();
		foreach ( $data as $key => $value ) {
			if ( is_null( $value ) ) {
				continue;
			}
			if ( is_array( $value ) ) {
				$result[ $key ] = $this->filter_null_values( $value );
			} else {
				$result[ $key ] = $value;
			}
		}
		return $result;
	}

	/**
	 * SNS選択肢を取得
	 *
	 * @param bool $translate ラベルを翻訳するかどうか。
	 * @return array
	 */
	private function get_social_network_choices( $translate = true ) {
		if ( ! $translate ) {
			return array(
				'facebook'    => 'Facebook',
				'instagram'   => 'Instagram',
				'x'           => 'X',
				'youtube'     => 'YouTube',
				'google_plus' => 'Google+',
				'line'        => 'LINE',
				'tiktok'      => 'TikTok',
				'threads'     => 'Threads',
				'pinterest'   => 'Pinterest',
				'mixi'        => 'mixi',
				'other'       => 'Other',
			);
		}

		return array(
			'facebook'    => __( 'Facebook', 'aivec-ai-search-schema' ),
			'instagram'   => __( 'Instagram', 'aivec-ai-search-schema' ),
			'x'           => __( 'X', 'aivec-ai-search-schema' ),
			'youtube'     => __( 'YouTube', 'aivec-ai-search-schema' ),
			'google_plus' => __( 'Google+', 'aivec-ai-search-schema' ),
			'line'        => __( 'LINE', 'aivec-ai-search-schema' ),
			'tiktok'      => __( 'TikTok', 'aivec-ai-search-schema' ),
			'threads'     => __( 'Threads', 'aivec-ai-search-schema' ),
			'pinterest'   => __( 'Pinterest', 'aivec-ai-search-schema' ),
			'mixi'        => __( 'mixi', 'aivec-ai-search-schema' ),
			'other'       => __( 'Other', 'aivec-ai-search-schema' ),
		);
	}

	/**
	 * コンテンツモデル選択肢を取得
	 *
	 * @param bool $translate ラベルを翻訳するかどうか。
	 * @return array
	 */
	private function get_content_model_choices( $translate = true ) {
		if ( ! $translate ) {
			return array(
				'WebPage'     => 'WebPage (default)',
				'Article'     => 'Article',
				'FAQPage'     => 'FAQPage',
				'QAPage'      => 'QAPage',
				'NewsArticle' => 'NewsArticle',
				'BlogPosting' => 'BlogPosting',
				'Product'     => 'Product',
			);
		}

		return array(
			'WebPage'     => __( 'WebPage (default)', 'aivec-ai-search-schema' ),
			'Article'     => __( 'Article', 'aivec-ai-search-schema' ),
			'FAQPage'     => __( 'FAQPage', 'aivec-ai-search-schema' ),
			'QAPage'      => __( 'QAPage', 'aivec-ai-search-schema' ),
			'NewsArticle' => __( 'NewsArticle', 'aivec-ai-search-schema' ),
			'BlogPosting' => __( 'BlogPosting', 'aivec-ai-search-schema' ),
			'Product'     => __( 'Product', 'aivec-ai-search-schema' ),
		);
	}

	/**
	 * 言語選択肢を取得
	 *
	 * @param bool $translate ラベルを翻訳するかどうか。
	 * @return array
	 */
	private function get_language_choices( $translate = true ) {
		if ( ! $translate ) {
			return array(
				'ja'    => 'Japanese',
				'en'    => 'English',
				'zh-CN' => 'Chinese (Simplified)',
				'zh-TW' => 'Chinese (Traditional)',
				'ko'    => 'Korean',
				'fr'    => 'French',
				'de'    => 'German',
				'es'    => 'Spanish',
				'pt'    => 'Portuguese',
				'it'    => 'Italian',
				'th'    => 'Thai',
			);
		}

		return array(
			'ja'    => __( 'Japanese', 'aivec-ai-search-schema' ),
			'en'    => __( 'English', 'aivec-ai-search-schema' ),
			'zh-CN' => __( 'Chinese (Simplified)', 'aivec-ai-search-schema' ),
			'zh-TW' => __( 'Chinese (Traditional)', 'aivec-ai-search-schema' ),
			'ko'    => __( 'Korean', 'aivec-ai-search-schema' ),
			'fr'    => __( 'French', 'aivec-ai-search-schema' ),
			'de'    => __( 'German', 'aivec-ai-search-schema' ),
			'es'    => __( 'Spanish', 'aivec-ai-search-schema' ),
			'pt'    => __( 'Portuguese', 'aivec-ai-search-schema' ),
			'it'    => __( 'Italian', 'aivec-ai-search-schema' ),
			'th'    => __( 'Thai', 'aivec-ai-search-schema' ),
		);
	}

	/**
	 * 曜日選択肢を取得
	 *
	 * @param bool $translate ラベルを翻訳するかどうか。
	 * @return array
	 */
	private function get_weekday_choices( $translate = true ) {
		if ( ! $translate ) {
			return array(
				'Monday'        => 'Monday',
				'Tuesday'       => 'Tuesday',
				'Wednesday'     => 'Wednesday',
				'Thursday'      => 'Thursday',
				'Friday'        => 'Friday',
				'Saturday'      => 'Saturday',
				'Sunday'        => 'Sunday',
				'PublicHoliday' => 'Public holiday',
			);
		}

		return array(
			'Monday'        => __( 'Monday', 'aivec-ai-search-schema' ),
			'Tuesday'       => __( 'Tuesday', 'aivec-ai-search-schema' ),
			'Wednesday'     => __( 'Wednesday', 'aivec-ai-search-schema' ),
			'Thursday'      => __( 'Thursday', 'aivec-ai-search-schema' ),
			'Friday'        => __( 'Friday', 'aivec-ai-search-schema' ),
			'Saturday'      => __( 'Saturday', 'aivec-ai-search-schema' ),
			'Sunday'        => __( 'Sunday', 'aivec-ai-search-schema' ),
			'PublicHoliday' => __( 'Public holiday', 'aivec-ai-search-schema' ),
		);
	}

	/**
	 * コンテンツタイプごとのデフォルト設定を取得
	 *
	 * @return array
	 */
	private function get_content_type_settings_defaults() {
		$post_types    = $this->get_configurable_post_types();
		$taxonomies    = $this->get_configurable_taxonomies();
		$post_defaults = array();

		foreach ( $post_types as $slug => $object ) {
			$post_defaults[ $slug ] = array(
				'schema_type'         => 'auto',
				'breadcrumbs_enabled' => true,
				'faq_enabled'         => 'post' === $slug,
				'schema_priority'     => 'ais',
			);
		}

		if ( ! isset( $post_defaults['post'] ) ) {
			$post_defaults['post'] = array(
				'schema_type'         => 'auto',
				'breadcrumbs_enabled' => true,
				'faq_enabled'         => true,
				'schema_priority'     => 'ais',
			);
		}

		if ( ! isset( $post_defaults['page'] ) ) {
			$post_defaults['page'] = array(
				'schema_type'         => 'auto',
				'breadcrumbs_enabled' => true,
				'faq_enabled'         => false,
				'schema_priority'     => 'ais',
			);
		}

		$taxonomy_defaults = array();
		foreach ( $taxonomies as $slug => $object ) {
			$taxonomy_defaults[ $slug ] = array(
				'schema_type'         => 'auto',
				'breadcrumbs_enabled' => true,
				'faq_enabled'         => false,
				'schema_priority'     => 'ais',
			);
		}

		return array(
			'post_types' => $post_defaults,
			'taxonomies' => $taxonomy_defaults,
		);
	}

	/**
	 * コンテンツタイプ設定をサニタイズ
	 *
	 * @param array $input
	 * @param array $defaults
	 * @return array
	 */
	private function sanitize_content_type_settings( $input, $defaults, $translate_labels = true ) {
		$post_types_input = ! empty( $input['post_types'] ) && is_array( $input['post_types'] )
			? $input['post_types']
			: array();
		$taxonomies_input = ! empty( $input['taxonomies'] ) && is_array( $input['taxonomies'] )
			? $input['taxonomies']
			: array();

		return array(
			'post_types' => $this->sanitize_content_type_entries(
				$post_types_input,
				$defaults['post_types'] ?? array(),
				$translate_labels
			),
			'taxonomies' => $this->sanitize_content_type_entries(
				$taxonomies_input,
				$defaults['taxonomies'] ?? array(),
				$translate_labels
			),
		);
	}

	/**
	 * コンテンツタイプのエントリをサニタイズ
	 *
	 * @param array $input
	 * @param array $defaults
	 * @param bool  $translate_labels ラベルを翻訳するかどうか。
	 * @return array
	 */
	private function sanitize_content_type_entries( $input, $defaults, $translate_labels = true ) {
		$choices         = $this->get_schema_type_choices( $translate_labels );
		$priority_values = array_keys( $this->get_schema_priority_choices( $translate_labels ) );
		$result          = array();

		foreach ( $defaults as $key => $values ) {
			$entry       = ! empty( $input[ $key ] ) && is_array( $input[ $key ] ) ? $input[ $key ] : array();
			$schema_type = isset( $entry['schema_type'] )
				? sanitize_text_field( $entry['schema_type'] )
				: $values['schema_type'];
			if ( ! isset( $choices[ $schema_type ] ) ) {
				$schema_type = $values['schema_type'];
			}
			$priority = isset( $entry['schema_priority'] )
				? sanitize_text_field( $entry['schema_priority'] )
				: $values['schema_priority'];
			if ( ! in_array( $priority, $priority_values, true ) ) {
				$priority = $values['schema_priority'];
			}
			$breadcrumbs_enabled = array_key_exists( 'breadcrumbs_enabled', $entry )
				? (bool) $entry['breadcrumbs_enabled']
				: (bool) $values['breadcrumbs_enabled'];
			$faq_enabled         = array_key_exists( 'faq_enabled', $entry )
				? (bool) $entry['faq_enabled']
				: (bool) $values['faq_enabled'];

			$result[ $key ] = array(
				'schema_type'         => $schema_type,
				'breadcrumbs_enabled' => $breadcrumbs_enabled,
				'faq_enabled'         => $faq_enabled,
				'schema_priority'     => $priority,
			);
		}

		return $result;
	}

	/**
	 * 公開 post type を取得
	 *
	 * @return array<string, WP_Post_Type>
	 */
	private function get_configurable_post_types() {
		if ( ! function_exists( 'get_post_types' ) ) {
			return $this->get_default_post_types();
		}

		$types   = get_post_types(
			array(
				'public' => true,
			),
			'objects'
		);
		$exclude = array(
			'attachment',
			'revision',
			'nav_menu_item',
			'custom_css',
			'customize_changeset',
			'oembed_cache',
			'user_request',
			'wp_block',
		);
		$result  = array();
		foreach ( $types as $type ) {
			if ( in_array( $type->name, $exclude, true ) ) {
				continue;
			}
			$result[ $type->name ] = $type;
		}
		return $result;
	}

	/**
	 * 公開 taxonomy を取得
	 *
	 * @return array<string, WP_Taxonomy>
	 */
	private function get_configurable_taxonomies() {
		if ( ! function_exists( 'get_taxonomies' ) ) {
			return $this->get_default_taxonomies();
		}

		$taxonomies = get_taxonomies(
			array(
				'public'  => true,
				'show_ui' => true,
			),
			'objects'
		);
		$exclude    = array(
			'nav_menu',
			'link_category',
			'post_format',
		);
		$result     = array();
		foreach ( $taxonomies as $tax ) {
			if ( in_array( $tax->name, $exclude, true ) ) {
				continue;
			}
			$result[ $tax->name ] = $tax;
		}
		return $result;
	}

	private function get_default_post_types() {
		$default               = new stdClass();
		$default->name         = 'post';
		$default->label        = 'Post';
		$labels                = new stdClass();
		$labels->singular_name = 'Post';
		$default->labels       = $labels;
		return array( 'post' => $default );
	}

	private function get_default_taxonomies() {
		$default               = new stdClass();
		$default->name         = 'category';
		$default->label        = 'Category';
		$labels                = new stdClass();
		$labels->singular_name = 'Category';
		$default->labels       = $labels;
		return array( 'category' => $default );
	}

	/**
	 * カスタム schema type 選択肢
	 *
	 * @param bool $translate ラベルを翻訳するかどうか。
	 * @return array
	 */
	private function get_schema_type_choices( $translate = true ) {
		if ( ! $translate ) {
			return array(
				'auto'           => 'Use global default content model',
				'WebPage'        => 'WebPage',
				'Article'        => 'Article',
				'BlogPosting'    => 'BlogPosting',
				'NewsArticle'    => 'NewsArticle',
				'FAQPage'        => 'FAQPage',
				'QAPage'         => 'QAPage',
				'Product'        => 'Product',
				'CollectionPage' => 'CollectionPage',
				'ItemList'       => 'ItemList',
			);
		}

		return array(
			'auto'           => __( 'Use global default content model', 'aivec-ai-search-schema' ),
			'WebPage'        => __( 'WebPage', 'aivec-ai-search-schema' ),
			'Article'        => __( 'Article', 'aivec-ai-search-schema' ),
			'BlogPosting'    => __( 'BlogPosting', 'aivec-ai-search-schema' ),
			'NewsArticle'    => __( 'NewsArticle', 'aivec-ai-search-schema' ),
			'FAQPage'        => __( 'FAQPage', 'aivec-ai-search-schema' ),
			'QAPage'         => __( 'QAPage', 'aivec-ai-search-schema' ),
			'Product'        => __( 'Product', 'aivec-ai-search-schema' ),
			'CollectionPage' => __( 'CollectionPage', 'aivec-ai-search-schema' ),
			'ItemList'       => __( 'ItemList', 'aivec-ai-search-schema' ),
		);
	}

	/**
	 * スキーマ優先度選択肢
	 *
	 * @param bool $translate ラベルを翻訳するかどうか。
	 * @return array
	 */
	private function get_schema_priority_choices( $translate = true ) {
		if ( ! $translate ) {
			return array(
				'ais'      => 'AVC schema priority (suppress other plugins)',
				'external' => 'Let other SEO plugins output schema',
			);
		}

		return array(
			'ais'      => __( 'AVC schema priority (suppress other plugins)', 'aivec-ai-search-schema' ),
			'external' => __( 'Let other SEO plugins output schema', 'aivec-ai-search-schema' ),
		);
	}

	/**
	 * デフォルト言語コードを取得
	 *
	 * @param bool $translate ラベルを翻訳するかどうか。
	 * @return string
	 */
	private function get_default_language( $translate = true ) {
		$locale = get_locale();
		if ( ! is_string( $locale ) || '' === $locale ) {
			return 'ja';
		}

		$normalized = str_replace( '_', '-', $locale );
		$choices    = $this->get_language_choices( $translate );

		if ( array_key_exists( $normalized, $choices ) ) {
			return $normalized;
		}

		$short = substr( $normalized, 0, 2 );
		if ( $short && array_key_exists( $short, $choices ) ) {
			return $short;
		}

		return 'ja';
	}

	/**
	 * 国コード選択肢を取得
	 *
	 * @param bool $translate ラベルを翻訳するかどうか。
	 * @return array
	 */
	private function get_country_choices( $translate = true ) {
		if ( ! $translate ) {
			return array(
				'JP' => 'Japan',
				'US' => 'United States',
				'CN' => 'China',
				'TW' => 'Taiwan',
				'HK' => 'Hong Kong',
				'KR' => 'South Korea',
				'SG' => 'Singapore',
				'TH' => 'Thailand',
				'VN' => 'Vietnam',
				'PH' => 'Philippines',
				'MY' => 'Malaysia',
				'ID' => 'Indonesia',
				'FR' => 'France',
				'DE' => 'Germany',
				'ES' => 'Spain',
				'IT' => 'Italy',
				'GB' => 'United Kingdom',
				'CA' => 'Canada',
				'AU' => 'Australia',
				'NZ' => 'New Zealand',
			);
		}

		return array(
			'JP' => __( 'Japan', 'aivec-ai-search-schema' ),
			'US' => __( 'United States', 'aivec-ai-search-schema' ),
			'CN' => __( 'China', 'aivec-ai-search-schema' ),
			'TW' => __( 'Taiwan', 'aivec-ai-search-schema' ),
			'HK' => __( 'Hong Kong', 'aivec-ai-search-schema' ),
			'KR' => __( 'South Korea', 'aivec-ai-search-schema' ),
			'SG' => __( 'Singapore', 'aivec-ai-search-schema' ),
			'TH' => __( 'Thailand', 'aivec-ai-search-schema' ),
			'VN' => __( 'Vietnam', 'aivec-ai-search-schema' ),
			'PH' => __( 'Philippines', 'aivec-ai-search-schema' ),
			'MY' => __( 'Malaysia', 'aivec-ai-search-schema' ),
			'ID' => __( 'Indonesia', 'aivec-ai-search-schema' ),
			'FR' => __( 'France', 'aivec-ai-search-schema' ),
			'DE' => __( 'Germany', 'aivec-ai-search-schema' ),
			'ES' => __( 'Spain', 'aivec-ai-search-schema' ),
			'IT' => __( 'Italy', 'aivec-ai-search-schema' ),
			'GB' => __( 'United Kingdom', 'aivec-ai-search-schema' ),
			'CA' => __( 'Canada', 'aivec-ai-search-schema' ),
			'AU' => __( 'Australia', 'aivec-ai-search-schema' ),
			'NZ' => __( 'New Zealand', 'aivec-ai-search-schema' ),
		);
	}

	/**
	 * デフォルトの国コードを取得
	 *
	 * @param bool $translate ラベルを翻訳するかどうか。
	 * @return string
	 */
	private function get_default_country( $translate = true ) {
		$locale = get_locale();
		if ( is_string( $locale ) && strpos( $locale, '_' ) !== false ) {
			$country = substr( strrchr( $locale, '_' ), 1 );
			if ( $country && array_key_exists( $country, $this->get_country_choices( $translate ) ) ) {
				return $country;
			}
		}

		return 'JP';
	}

	/**
	 * AEO/GEO 診断結果を返す（transient でキャッシュ）
	 *
	 * @param array $options
	 * @return array
	 */
	public function get_diagnostics_report( array $options ) {
		$cache_key = 'avc_ais_diag_' . md5( wp_json_encode( $options ) );
		$cached    = get_transient( $cache_key );
		if ( $cached && is_array( $cached ) ) {
			return $cached;
		}

		$report = array(
			'generated_at' => time(),
			'groups'       => array(
				$this->diagnose_organization( $options ),
				$this->diagnose_local_business( $options ),
				$this->diagnose_website( $options ),
				$this->diagnose_webpage( $options ),
				$this->diagnose_article( $options ),
				$this->diagnose_faq( $options ),
				$this->diagnose_breadcrumb( $options ),
			),
		);

		set_transient( $cache_key, $report, MINUTE_IN_SECONDS * 10 );

		return $report;
	}

	private function make_item( $status, $message ) {
		return array(
			'status'  => $status,
			'message' => $message,
		);
	}

	private function diagnose_organization( array $options ) {
		$items = array();

		$has_name = ! empty( $options['company_name'] );
		$has_url  = ! empty( $options['site_url'] );
		$has_logo = ! empty( $options['logo_url'] ) || ! empty( $options['logo_id'] );
		$has_same = ! empty( $options['social_links'] ) && is_array( $options['social_links'] );

		$items[] = $this->make_item(
			$has_name ? 'ok' : 'error',
			$has_name
				? __(
					'The site operator is defined in the structured data.',
					'aivec-ai-search-schema'
				)
				: __(
					'The site operator name is not set. Entity identification may be difficult.',
					'aivec-ai-search-schema'
				)
		);
		$items[] = $this->make_item(
			$has_url ? 'ok' : 'error',
			$has_url
				? __(
					'The organization URL is defined in the structured data.',
					'aivec-ai-search-schema'
				)
				: __(
					'The organization URL is not set. Entity identification may be difficult.',
					'aivec-ai-search-schema'
				)
		);
		$items[] = $this->make_item(
			$has_logo ? 'ok' : 'warning',
			$has_logo
				? __( 'A logo is registered for entity identification.', 'aivec-ai-search-schema' )
				: __( 'No logo is registered. Credibility assessment may be affected.', 'aivec-ai-search-schema' )
		);
		$items[] = $this->make_item(
			$has_same ? 'ok' : 'warning',
			$has_same
				? __( 'Social profiles (sameAs) are linked for entity verification.', 'aivec-ai-search-schema' )
				: __( 'No social profiles are linked. Entity verification may be limited.', 'aivec-ai-search-schema' )
		);

		return array(
			'title' => __( 'Organization', 'aivec-ai-search-schema' ),
			'items' => $items,
		);
	}

	private function diagnose_local_business( array $options ) {
		$items      = array();
		$address    = $options['address'] ?? array();
		$locality   = $options['localbusiness_address_locality'] ?? ( $address['locality'] ?? '' );
		$street     = $options['localbusiness_street_address'] ?? ( $address['street_address'] ?? '' );
		$region     = $address['region'] ?? '';
		$postal     = $address['postal_code'] ?? '';
		$country    = $address['country'] ?? 'JP';
		$has_geo    = ! empty( $options['geo']['latitude'] ) && ! empty( $options['geo']['longitude'] );
		$oh_entries = ! empty( $options['opening_hours'] ) && is_array( $options['opening_hours'] );

		$items[] = $this->make_item(
			! empty( $options['company_name'] ) ? 'ok' : 'error',
			! empty( $options['company_name'] )
				? __(
					'The business name is defined in the structured data.',
					'aivec-ai-search-schema'
				)
				: __(
					'The business name is not set. Local search visibility may be affected.',
					'aivec-ai-search-schema'
				)
		);
		$items[] = $this->make_item(
			! empty( $options['phone'] ) ? 'ok' : 'error',
			! empty( $options['phone'] )
				? __( 'A phone number is registered for contact purposes.', 'aivec-ai-search-schema' )
				: __( 'No phone number is set. This is a required field for LocalBusiness.', 'aivec-ai-search-schema' )
		);

		$addr_status = ( $street && $locality && $region && $postal ) ? 'ok' : 'error';
		$items[]     = $this->make_item(
			$addr_status,
			'ok' === $addr_status
				? __( 'The address is fully defined in the structured data.', 'aivec-ai-search-schema' )
				: __( 'The address is incomplete. Some fields may be missing.', 'aivec-ai-search-schema' )
		);

		$geo_status  = $has_geo ? 'ok' : 'warning';
		$lat_decimal = $this->count_decimals( $options['geo']['latitude'] ?? '' );
		$lng_decimal = $this->count_decimals( $options['geo']['longitude'] ?? '' );
		if ( $has_geo && ( $lat_decimal > 6 || $lng_decimal > 6 ) ) {
			$geo_status = 'warning';
		}
		$items[] = $this->make_item(
			$geo_status,
			$has_geo
				? sprintf(
					/* translators: %s decimal places */
					__( 'Geo coordinates are registered (%s decimal places). Map display is ready.', 'aivec-ai-search-schema' ), // phpcs:ignore Generic.Files.LineLength.TooLong
					max( $lat_decimal, $lng_decimal )
				)
				: __( 'Geo coordinates are not set. Map-based local search may be affected.', 'aivec-ai-search-schema' )
		);

		$items[] = $this->make_item(
			$oh_entries ? 'ok' : 'warning',
			$oh_entries
				? __( 'Business hours are defined in the structured data.', 'aivec-ai-search-schema' )
				: __( 'No business hours are set. Open/closed status cannot be determined.', 'aivec-ai-search-schema' )
		);

		$items[] = $this->make_item(
			! empty( $country ) ? 'ok' : 'warning',
			! empty( $country )
				? __( 'The country code is defined for address context.', 'aivec-ai-search-schema' )
				: __( 'The country code is not set. Regional interpretation may vary.', 'aivec-ai-search-schema' )
		);

		return array(
			'title' => __( 'LocalBusiness', 'aivec-ai-search-schema' ),
			'items' => $items,
		);
	}

	// phpcs:disable Generic.Formatting.MultipleStatementAlignment.NotSameWarning
	private function diagnose_website( array $options ) {
		$items = array();
		$items[] = $this->make_item(
			! empty( $options['site_name'] ) ? 'ok' : 'error',
			! empty( $options['site_name'] )
				? __( 'The site name is defined in the structured data.', 'aivec-ai-search-schema' )
				: __( 'The site name is not set. Site identification may be unclear.', 'aivec-ai-search-schema' )
		);
		$items[] = $this->make_item(
			! empty( $options['site_url'] ) ? 'ok' : 'error',
			! empty( $options['site_url'] )
				? __( 'The site URL is defined as the canonical reference.', 'aivec-ai-search-schema' )
				: __( 'The site URL is not set. Canonical identification may fail.', 'aivec-ai-search-schema' )
		);
		$langs = $options['languages'] ?? array();
		$items[] = $this->make_item(
			! empty( $langs ) ? 'ok' : 'warning',
			! empty( $langs )
				? __( 'The site language is defined for content context.', 'aivec-ai-search-schema' )
				: __( 'No language is set. Content language context may be ambiguous.', 'aivec-ai-search-schema' )
		);
		$items[] = $this->make_item(
			! empty( $options['company_name'] ) ? 'ok' : 'warning',
			! empty( $options['company_name'] )
				? __( 'The publisher is linked to the site entity.', 'aivec-ai-search-schema' )
				: __( 'No publisher is set. Site-entity relationship is undefined.', 'aivec-ai-search-schema' )
		);

		return array(
			'title' => __( 'WebSite', 'aivec-ai-search-schema' ),
			'items' => $items,
		);
	}

	private function diagnose_webpage( array $options ) {
		$items = array();
		$items[] = $this->make_item(
			! empty( $options['site_url'] ) ? 'ok' : 'warning',
			! empty( $options['site_url'] )
				? __( 'The WebPage base URL is defined for page identification.', 'aivec-ai-search-schema' )
				: __( 'The WebPage base URL is not set. Page linking may be affected.', 'aivec-ai-search-schema' )
		);
		$langs = $options['languages'] ?? array();
		$items[] = $this->make_item(
			! empty( $langs ) ? 'ok' : 'warning',
			! empty( $langs )
				? __( 'The page language is defined for content context.', 'aivec-ai-search-schema' )
				: __( 'No language is set. System default will be used.', 'aivec-ai-search-schema' )
		);

		return array(
			'title' => __( 'WebPage', 'aivec-ai-search-schema' ),
			'items' => $items,
		);
	}

	private function diagnose_article( array $options ) {
		$items            = array();
		$post_schema_type = $options['content_type_settings']['post_types']['post']['schema_type'] ?? 'auto';
		$is_article       = 'Article' === $post_schema_type || 'auto' === $post_schema_type;
		$items[]          = $this->make_item(
			$is_article ? 'ok' : 'info',
			$is_article
				? __( 'Posts are output as Article schema for blog content.', 'aivec-ai-search-schema' )
				: __( 'Posts are output as WebPage schema. Article may be more suitable for blogs.', 'aivec-ai-search-schema' ) // phpcs:ignore Generic.Files.LineLength.TooLong
		);
		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		$post     = ! empty( $posts ) ? $posts[0] : null;
		$has_post = (bool) $post;

		if ( ! $has_post ) {
			$items[] = $this->make_item(
				'info',
				__( 'No published posts found. Sample check cannot be performed.', 'aivec-ai-search-schema' )
			);
			return array(
				'title' => __( 'Article (sample post)', 'aivec-ai-search-schema' ),
				'items' => $items,
			);
		}

		$items[] = $this->make_item(
			! empty( $post->post_title ) ? 'ok' : 'error',
			! empty( $post->post_title )
				? __( 'The headline is defined in the sample post.', 'aivec-ai-search-schema' )
				: __( 'The headline is missing from the sample post.', 'aivec-ai-search-schema' )
		);
		$items[] = $this->make_item(
			! empty( $post->post_date ) ? 'ok' : 'error',
			! empty( $post->post_date )
				? __( 'The publication date is defined in the structured data.', 'aivec-ai-search-schema' )
				: __( 'The publication date is missing. Freshness signals may be affected.', 'aivec-ai-search-schema' )
		);
		$items[] = $this->make_item(
			! empty( $post->post_modified ) ? 'ok' : 'warning',
			! empty( $post->post_modified )
				? __( 'The modification date is defined for content freshness.', 'aivec-ai-search-schema' )
				: __( 'The modification date is not available.', 'aivec-ai-search-schema' )
		);

		$author = get_user_by( 'id', $post->post_author );
		$items[] = $this->make_item(
			$author ? 'ok' : 'warning',
			$author
				? __( 'The author is defined for content attribution.', 'aivec-ai-search-schema' )
				: __( 'No author is set. Content attribution may be unclear.', 'aivec-ai-search-schema' )
		);

		$post_id   = $post->ID ?? 0;
		$has_image = ( $post_id && has_post_thumbnail( $post_id ) )
			|| ( $post_id && get_post_thumbnail_id( $post_id ) );
		$items[]   = $this->make_item(
			$has_image ? 'ok' : 'warning',
			$has_image
				? __(
					'A featured image is set for the sample post.',
					'aivec-ai-search-schema'
				)
				: __(
					'No featured image on the sample post. Rich results may be limited.',
					'aivec-ai-search-schema'
				)
		);

		return array(
			'title' => __( 'Article (sample post)', 'aivec-ai-search-schema' ),
			'items' => $items,
		);
	}

	// phpcs:enable Generic.Formatting.MultipleStatementAlignment.NotSameWarning

	private function diagnose_faq( array $options ) {
		$items = array();

		$faq_enabled_any = false;
		$content_types   = $options['content_type_settings']['post_types'] ?? array();
		foreach ( $content_types as $entry ) {
			if ( ! empty( $entry['faq_enabled'] ) ) {
				$faq_enabled_any = true;
				break;
			}
		}

		if ( $faq_enabled_any ) {
			$items[] = $this->make_item(
				'ok',
				__( 'FAQ schema extraction is enabled for at least one post type.', 'aivec-ai-search-schema' )
			);
			$items[] = $this->make_item(
				'info',
				__( 'FAQ content requires both question and answer elements with matching CSS classes.', 'aivec-ai-search-schema' ) // phpcs:ignore Generic.Files.LineLength.TooLong
			);
		} else {
			$items[] = $this->make_item(
				'info',
				__( 'FAQ schema extraction is not enabled. Enable it if your content contains Q&A sections.', 'aivec-ai-search-schema' ) // phpcs:ignore Generic.Files.LineLength.TooLong
			);
		}

		return array(
			'title' => __( 'FAQPage', 'aivec-ai-search-schema' ),
			'items' => $items,
		);
	}

	private function diagnose_breadcrumb( array $options ) {
		$items      = array();
		$sample_url = ! empty( $options['site_url'] ) ? $options['site_url'] : home_url( '/' );
		$posts      = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		$post       = ! empty( $posts ) ? $posts[0] : null;

		$context = $post
			? array(
				'post_id'   => $post->ID,
				'title'     => get_the_title( $post ),
				'url'       => get_permalink( $post ),
				'type'      => 'page',
				'post_type' => get_post_type( $post ),
			)
			: array(
				'title' => get_bloginfo( 'name' ),
				'url'   => $sample_url,
				'type'  => 'page',
			);

		$breadcrumbs = class_exists( 'AVC_AIS_Breadcrumbs' )
			? AVC_AIS_Breadcrumbs::init()->get_items( $context )
			: array();

		$items[] = $this->make_item(
			! empty( $breadcrumbs ) && count( $breadcrumbs ) > 1 ? 'ok' : 'warning',
			! empty( $breadcrumbs ) && count( $breadcrumbs ) > 1
				? __( 'Breadcrumb navigation is defined in the structured data.', 'aivec-ai-search-schema' )
				: __( 'The breadcrumb trail is empty. Site hierarchy may be unclear.', 'aivec-ai-search-schema' )
		);

		return array(
			'title' => __( 'BreadcrumbList', 'aivec-ai-search-schema' ),
			'items' => $items,
		);
	}

	private function count_decimals( $value ) {
		if ( '' === $value || null === $value ) {
			return 0;
		}
		$parts = explode( '.', (string) $value );
		if ( count( $parts ) < 2 ) {
			return 0;
		}
		return strlen( rtrim( $parts[1], '0' ) );
	}

	/**
	 * GEO値を最大6桁に丸め、元の小数桁数を尊重して文字列化する。
	 *
	 * @param mixed $value 入力値
	 * @return string
	 */
	private function format_geo_value( $value ) {
		if ( ! isset( $value ) || '' === $value ) {
			return '';
		}

		$raw = trim( sanitize_text_field( (string) $value ) );
		if ( ! preg_match( '/^-?\d+(?:\.\d+)?/', $raw, $matches ) ) {
			return '';
		}
		$raw = $matches[0];

		$precision = 0;
		if ( strpos( $raw, '.' ) !== false ) {
			$decimals  = substr( $raw, strpos( $raw, '.' ) + 1 );
			$precision = strlen( $decimals );
		}
		$precision = min( max( $precision, 0 ), 6 );

		$rounded = round( (float) $raw, 6 );
		if ( 0 === $precision ) {
			return (string) $rounded;
		}

		return number_format( $rounded, $precision, '.', '' );
	}

	/**
	 * 営業時間を解析して配列化
	 *
	 * @param string $raw 1行1レコードの営業時間テキスト
	 * @return array
	 */
	private function parse_opening_hours( $raw ) {
		$lines       = preg_split( "/\r\n|\r|\n/", (string) $raw );
		$parsed      = array();
		$day_aliases = array(
			'Mon'  => 'Monday',
			'Tue'  => 'Tuesday',
			'Tues' => 'Tuesday',
			'Wed'  => 'Wednesday',
			'Thu'  => 'Thursday',
			'Thur' => 'Thursday',
			'Fri'  => 'Friday',
			'Sat'  => 'Saturday',
			'Sun'  => 'Sunday',
		);

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}

			if ( preg_match( '/^([A-Za-z]{2,9})\s+(\d{2}:\d{2})\s*-\s*(\d{2}:\d{2})$/', $line, $matches ) ) {
				$day_key  = isset( $day_aliases[ $matches[1] ] ) ? $day_aliases[ $matches[1] ] : $matches[1];
				$parsed[] = array(
					'day_key' => $day_key,
					'opens'   => $matches[2],
					'closes'  => $matches[3],
				);
			}
		}

		return $parsed;
	}

	/**
	 * 営業時間エントリを正規化
	 *
	 * @param array $entries 入力エントリ
	 * @param bool  $translate_labels ラベルを翻訳するかどうか。
	 * @return array
	 */
	private function normalize_opening_hours( $entries, $translate_labels = true ) {
		$normalized      = array();
		$weekday_choices = $this->get_weekday_choices( $translate_labels );
		$allowed_days    = array_keys( $weekday_choices );
		$day_aliases     = array(
			'Mon'  => 'Monday',
			'Tue'  => 'Tuesday',
			'Tues' => 'Tuesday',
			'Wed'  => 'Wednesday',
			'Thu'  => 'Thursday',
			'Thur' => 'Thursday',
			'Fri'  => 'Friday',
			'Sat'  => 'Saturday',
			'Sun'  => 'Sunday',
			'月曜日'  => 'Monday',
			'火曜日'  => 'Tuesday',
			'水曜日'  => 'Wednesday',
			'木曜日'  => 'Thursday',
			'金曜日'  => 'Friday',
			'土曜日'  => 'Saturday',
			'日曜日'  => 'Sunday',
			'祝日'   => 'PublicHoliday',
		);
		$results_by_day  = array();

		foreach ( (array) $entries as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$day_key = sanitize_text_field( $entry['day_key'] ?? $entry['day'] ?? '' );
			if ( ! in_array( $day_key, $allowed_days, true ) ) {
				if ( isset( $day_aliases[ $day_key ] ) ) {
					$day_key = $day_aliases[ $day_key ];
				} else {
					continue;
				}
			}

			$slots = array();

			if ( ! empty( $entry['slots'] ) && is_array( $entry['slots'] ) ) {
				foreach ( $entry['slots'] as $slot ) {
					$opens  = sanitize_text_field( $slot['opens'] ?? '' );
					$closes = sanitize_text_field( $slot['closes'] ?? '' );
					if ( $this->is_valid_time( $opens ) && $this->is_valid_time( $closes ) ) {
						$slots[] = array(
							'opens'  => $opens,
							'closes' => $closes,
						);
					}
				}
			} else {
				$opens  = sanitize_text_field( $entry['opens'] ?? '' );
				$closes = sanitize_text_field( $entry['closes'] ?? '' );
				if ( $this->is_valid_time( $opens ) && $this->is_valid_time( $closes ) ) {
					$slots[] = array(
						'opens'  => $opens,
						'closes' => $closes,
					);
				}
			}

			if ( empty( $slots ) ) {
				continue;
			}

			if ( ! isset( $results_by_day[ $day_key ] ) ) {
				$results_by_day[ $day_key ] = array(
					'day'     => $weekday_choices[ $day_key ] ?? $day_key,
					'day_key' => $day_key,
					'slots'   => array(),
				);
			}

			$results_by_day[ $day_key ]['slots'] = array_merge( $results_by_day[ $day_key ]['slots'], $slots );
		}

		return array_values( $results_by_day );
	}

	/**
	 * 時刻フォーマット検証 (HH:MM)
	 *
	 * @param string $time 入力時刻
	 * @return bool
	 */
	private function is_valid_time( $time ) {
		return is_string( $time ) && 1 === preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time );
	}

	/**
	 * Claim a slot for geocoding to enforce basic rate limiting.
	 *
	 * @return int Remaining seconds to wait, or 0 when allowed.
	 */
	private function claim_geocode_slot() {
		$user_id    = get_current_user_id();
		$identifier = $user_id
			? 'user_' . $user_id
			: 'ip_' . (
				isset( $_SERVER['REMOTE_ADDR'] )
					? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
					: 'anon'
			);
		$cache_key  = 'avc_ais_geocode_' . md5( $identifier );
		$now        = time();
		$last       = (int) get_transient( $cache_key );

		if ( $last && ( $now - $last ) < self::GEOCODE_RATE_LIMIT_SECONDS ) {
			return self::GEOCODE_RATE_LIMIT_SECONDS - ( $now - $last );
		}

		set_transient( $cache_key, $now, self::GEOCODE_RATE_LIMIT_SECONDS );
		return 0;
	}

	/**
	 * JSON レスポンスを出力する。テスト用にフックで挙動を差し替え可能。
	 *
	 * @param bool  $success 成否。
	 * @param array $data    レスポンスデータ。
	 * @param int   $status  ステータスコード。
	 * @return void
	 */
	private function respond_json( bool $success, array $data, int $status = 200 ): void {
		if ( has_action( 'avc_ais_json_responder' ) ) {
			/**
			 * テスト等で wp_send_json_* を差し替えたい場合に使用する。
			 *
			 * @param bool  $success 成否。
			 * @param array $data    レスポンスデータ。
			 * @param int   $status  ステータスコード。
			 */
			do_action( 'avc_ais_json_responder', $success, $data, $status );
			return;
		}

		if ( $success ) {
			wp_send_json_success( $data, $status );
		}

		wp_send_json_error( $data, $status );
	}
}

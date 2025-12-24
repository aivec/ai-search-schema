<?php
/**
 * AI_Search_Schema_License クラス
 *
 * Pro版のライセンス管理を行うクラスです。
 * ライセンスキーの検証・保存・ステータス管理を行います。
 *
 * @package AI_Search_Schema
 * @since 0.10.0
 */

/**
 * License management class for AI Search Schema Pro.
 */
class AI_Search_Schema_License {

	/**
	 * Option name for license data.
	 */
	private const OPTION_NAME = 'ai_search_schema_license';

	/**
	 * License API endpoint (future implementation).
	 */
	private const API_ENDPOINT = 'https://api.aivec.co.jp/v1/license/validate';

	/**
	 * Singleton instance.
	 *
	 * @var AI_Search_Schema_License|null
	 */
	private static $instance = null;

	/**
	 * Cached license data.
	 *
	 * @var array|null
	 */
	private $license_data = null;

	/**
	 * Initialize the license manager.
	 *
	 * @return AI_Search_Schema_License
	 */
	public static function init() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'wp_ajax_ai_search_schema_activate_license', array( $this, 'handle_activate_license' ) );
		add_action( 'wp_ajax_ai_search_schema_deactivate_license', array( $this, 'handle_deactivate_license' ) );
	}

	/**
	 * Check if the Pro license is active.
	 *
	 * @return bool True if Pro license is active, false otherwise.
	 */
	public function is_pro() {
		// Stub: Always returns false until license API is implemented.
		return false;
	}

	/**
	 * Get the license key.
	 *
	 * @return string License key or empty string.
	 */
	public function get_license_key() {
		$data = $this->get_license_data();
		return isset( $data['key'] ) ? $data['key'] : '';
	}

	/**
	 * Get the license status.
	 *
	 * @return string License status: 'valid', 'invalid', 'expired', or 'inactive'.
	 */
	public function get_license_status() {
		$data = $this->get_license_data();
		return isset( $data['status'] ) ? $data['status'] : 'inactive';
	}

	/**
	 * Get the license expiration date.
	 *
	 * @return string|null Expiration date (Y-m-d) or null if not set.
	 */
	public function get_expiration_date() {
		$data = $this->get_license_data();
		return isset( $data['expires'] ) ? $data['expires'] : null;
	}

	/**
	 * Get all license data.
	 *
	 * @return array License data array.
	 */
	private function get_license_data() {
		if ( null === $this->license_data ) {
			$this->license_data = get_option(
				self::OPTION_NAME,
				array(
					'key'     => '',
					'status'  => 'inactive',
					'expires' => null,
				)
			);
		}
		return $this->license_data;
	}

	/**
	 * Save license data.
	 *
	 * @param array $data License data to save.
	 * @return bool True on success, false on failure.
	 */
	private function save_license_data( $data ) {
		$this->license_data = $data;
		return update_option( self::OPTION_NAME, $data );
	}

	/**
	 * Validate a license key with the API.
	 *
	 * @param string $key License key to validate.
	 * @return array Validation result with 'success' and 'data' keys.
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Will be used when API is implemented.
	public function validate( $key ) {
		// Stub: Always returns false until license API is implemented.
		// In production, this would make an API call to validate the key.
		return array(
			'success' => false,
			'data'    => array(
				'status'  => 'invalid',
				'message' => __( 'License validation API is not yet available.', 'ai-search-schema' ),
			),
		);
	}

	/**
	 * Activate a license key.
	 *
	 * @param string $key License key to activate.
	 * @return array Activation result.
	 */
	public function activate( $key ) {
		$key = sanitize_text_field( trim( $key ) );

		if ( empty( $key ) ) {
			return array(
				'success' => false,
				'message' => __( 'Please enter a license key.', 'ai-search-schema' ),
			);
		}

		// Validate the key.
		$validation = $this->validate( $key );

		if ( $validation['success'] ) {
			$this->save_license_data(
				array(
					'key'     => $key,
					'status'  => 'valid',
					'expires' => $validation['data']['expires'] ?? null,
				)
			);

			return array(
				'success' => true,
				'message' => __( 'License activated successfully.', 'ai-search-schema' ),
			);
		}

		// Save the key even if validation fails (for future re-validation).
		$this->save_license_data(
			array(
				'key'     => $key,
				'status'  => 'invalid',
				'expires' => null,
			)
		);

		return array(
			'success' => false,
			'message' => $validation['data']['message'] ?? __( 'License validation failed.', 'ai-search-schema' ),
		);
	}

	/**
	 * Deactivate the current license.
	 *
	 * @return array Deactivation result.
	 */
	public function deactivate() {
		$this->save_license_data(
			array(
				'key'     => '',
				'status'  => 'inactive',
				'expires' => null,
			)
		);

		return array(
			'success' => true,
			'message' => __( 'License deactivated.', 'ai-search-schema' ),
		);
	}

	/**
	 * AJAX handler for license activation.
	 */
	public function handle_activate_license() {
		check_ajax_referer( 'ai_search_schema_license', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You do not have permission to perform this action.', 'ai-search-schema' ) ),
				403
			);
		}

		$key    = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';
		$result = $this->activate( $key );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX handler for license deactivation.
	 */
	public function handle_deactivate_license() {
		check_ajax_referer( 'ai_search_schema_license', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You do not have permission to perform this action.', 'ai-search-schema' ) ),
				403
			);
		}

		$result = $this->deactivate();
		wp_send_json_success( $result );
	}

	/**
	 * Get the license status label for display.
	 *
	 * @return string Status label.
	 */
	public function get_status_label() {
		$status = $this->get_license_status();

		switch ( $status ) {
			case 'valid':
				return __( 'Active', 'ai-search-schema' );
			case 'expired':
				return __( 'Expired', 'ai-search-schema' );
			case 'invalid':
				return __( 'Invalid', 'ai-search-schema' );
			default:
				return __( 'Inactive', 'ai-search-schema' );
		}
	}

	/**
	 * Get the license status CSS class.
	 *
	 * @return string CSS class for status display.
	 */
	public function get_status_class() {
		$status = $this->get_license_status();

		switch ( $status ) {
			case 'valid':
				return 'ais-license-status--active';
			case 'expired':
				return 'ais-license-status--expired';
			case 'invalid':
				return 'ais-license-status--invalid';
			default:
				return 'ais-license-status--inactive';
		}
	}
}

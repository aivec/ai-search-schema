<?php
/**
 * AVC_AIS_Pro_Features クラス
 *
 * Pro版の機能を管理するクラスです。
 * ライセンスが有効な場合にのみPro機能を有効化します。
 *
 * @package AVC_AIS_Schema
 * @since 0.10.0
 */

/**
 * Pro features management class for AI Search Schema.
 */
class AVC_AIS_Pro_Features {

	/**
	 * Singleton instance.
	 *
	 * @var AVC_AIS_Pro_Features|null
	 */
	private static $instance = null;

	/**
	 * License manager instance.
	 *
	 * @var AVC_AIS_License|null
	 */
	private $license = null;

	/**
	 * Initialize the Pro features manager.
	 *
	 * @return AVC_AIS_Pro_Features
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
		require_once AVC_AIS_DIR . 'includes/class-avc-ais-license.php';
		$this->license = AVC_AIS_License::init();

		// Register Pro features hooks if license is valid.
		if ( $this->is_pro_active() ) {
			$this->register_pro_features();
		}

		// Always show Pro feature teasers in admin.
		if ( is_admin() ) {
			add_action( 'admin_init', array( $this, 'register_pro_teasers' ) );
		}
	}

	/**
	 * Check if Pro license is active.
	 *
	 * @return bool True if Pro is active.
	 */
	public function is_pro_active() {
		return $this->license->is_pro();
	}

	/**
	 * Get the license manager instance.
	 *
	 * @return AVC_AIS_License License manager.
	 */
	public function get_license() {
		return $this->license;
	}

	/**
	 * Register Pro features when license is valid.
	 */
	private function register_pro_features() {
		// Future Pro features will be registered here.
		// Examples:
		// - Multi-location LocalBusiness support
		// - Custom schema templates
		// - Priority support
		// - Advanced analytics integration
		// - Automatic schema validation scheduling.
	}

	/**
	 * Register Pro feature teasers in admin.
	 */
	public function register_pro_teasers() {
		// Show Pro feature previews with upgrade prompts.
		// This helps users understand what Pro offers.
	}

	/**
	 * Get list of Pro features for display.
	 *
	 * @return array Array of Pro feature descriptions.
	 */
	public function get_pro_features_list() {
		return array(
			array(
				'title'       => __( 'Multi-Location Support', 'aivec-ai-search-schema' ),
				'description' => __(
					'Manage multiple LocalBusiness locations from a single WordPress installation.',
					'aivec-ai-search-schema'
				),
				'icon'        => 'dashicons-location',
			),
			array(
				'title'       => __( 'Custom Schema Templates', 'aivec-ai-search-schema' ),
				// phpcs:ignore Generic.Files.LineLength.TooLong -- Translation string.
				'description' => __( 'Create and save custom schema templates for different content types.', 'aivec-ai-search-schema' ),
				'icon'        => 'dashicons-editor-code',
			),
			array(
				'title'       => __( 'Priority Support', 'aivec-ai-search-schema' ),
				'description' => __(
					'Get priority email support from our development team.',
					'aivec-ai-search-schema'
				),
				'icon'        => 'dashicons-sos',
			),
			array(
				'title'       => __( 'Advanced Validation', 'aivec-ai-search-schema' ),
				'description' => __(
					'Scheduled automatic schema validation with email reports.',
					'aivec-ai-search-schema'
				),
				'icon'        => 'dashicons-yes-alt',
			),
		);
	}

	/**
	 * Check if a specific Pro feature is available.
	 *
	 * @param string $feature Feature slug to check.
	 * @return bool True if feature is available.
	 */
	public function has_feature( $feature ) {
		if ( ! $this->is_pro_active() ) {
			return false;
		}

		// All features are available when Pro is active.
		$available_features = array(
			'multi-location',
			'custom-templates',
			'priority-support',
			'advanced-validation',
		);

		return in_array( $feature, $available_features, true );
	}

	/**
	 * Render the Pro upgrade notice.
	 *
	 * @param string $feature Feature name to highlight.
	 */
	public function render_upgrade_notice( $feature = '' ) {
		if ( $this->is_pro_active() ) {
			return;
		}
		?>
		<div class="ais-pro-notice">
			<span class="ais-pro-badge"><?php esc_html_e( 'Pro', 'aivec-ai-search-schema' ); ?></span>
			<?php if ( $feature ) : ?>
					<p>
						<?php
						printf(
							/* translators: %s: feature name */
							esc_html__(
								'%s is a Pro feature. Upgrade to unlock all Pro features.',
								'aivec-ai-search-schema'
							),
							esc_html( $feature )
						);
						?>
					</p>
				<?php else : ?>
					<p>
						<?php
						esc_html_e(
							'Upgrade to Pro to unlock all advanced features.',
							'aivec-ai-search-schema'
						);
						?>
					</p>
				<?php endif; ?>
			<a
				href="https://aivec.co.jp/apps/ai-search-schema-pro"
				target="_blank"
				rel="noopener noreferrer"
				class="button button-primary"
			>
				<?php esc_html_e( 'Learn More', 'aivec-ai-search-schema' ); ?>
			</a>
		</div>
		<?php
	}
}

<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName
/**
 * Setup Wizard main class.
 *
 * @package Aivec\AiSearchSchema\Wizard
 */

namespace Aivec\AiSearchSchema\Wizard;

/**
 * Wizard class - handles the setup wizard flow.
 */
class Wizard {

	/**
	 * Wizard page slug.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'ais-wizard';

	/**
	 * Option key for wizard completion status.
	 *
	 * @var string
	 */
	const OPTION_COMPLETED = 'ai_search_schema_wizard_completed';

	/**
	 * User meta key for wizard progress.
	 *
	 * @var string
	 */
	const META_PROGRESS = 'ai_search_schema_wizard_progress';

	/**
	 * Available steps.
	 *
	 * @var array
	 */
	private $steps = array();

	/**
	 * Current step.
	 *
	 * @var string
	 */
	private $current_step = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->steps = $this->get_steps();
	}

	/**
	 * Register hooks.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'maybe_redirect_to_wizard' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_notices', array( $this, 'show_setup_notice' ) );
		add_action( 'current_screen', array( $this, 'maybe_set_wizard_title' ) );

		// Ajax handlers.
		add_action( 'wp_ajax_ai_search_schema_wizard_save_step', array( $this, 'ajax_save_step' ) );
		add_action( 'wp_ajax_ai_search_schema_wizard_skip_step', array( $this, 'ajax_skip_step' ) );
		add_action( 'wp_ajax_ai_search_schema_wizard_complete', array( $this, 'ajax_complete' ) );
		add_action( 'wp_ajax_ai_search_schema_wizard_reset', array( $this, 'ajax_reset' ) );
		add_action( 'wp_ajax_ai_search_schema_wizard_import', array( $this, 'ajax_import' ) );
		add_action( 'wp_ajax_ai_search_schema_wizard_get_schema', array( $this, 'ajax_get_schema' ) );
	}

	/**
	 * Get wizard steps.
	 *
	 * @return array
	 */
	public function get_steps() {
		$steps = array(
			'welcome'  => array(
				'name'     => __( 'Welcome', 'ai-search-schema' ),
				'view'     => 'welcome',
				'required' => false,
			),
			'basics'   => array(
				'name'     => __( 'Basic Information', 'ai-search-schema' ),
				'view'     => 'step-1-basics',
				'required' => true,
			),
			'type'     => array(
				'name'     => __( 'Site Type', 'ai-search-schema' ),
				'view'     => 'step-2-type',
				'required' => true,
			),
			'api-key'  => array(
				'name'     => __( 'API Key', 'ai-search-schema' ),
				'view'     => 'step-api-key',
				'required' => false,
			),
			'location' => array(
				'name'     => __( 'Location', 'ai-search-schema' ),
				'view'     => 'step-3-location',
				'required' => false,
			),
			'hours'    => array(
				'name'     => __( 'Business Hours', 'ai-search-schema' ),
				'view'     => 'step-4-hours',
				'required' => false,
			),
			'complete' => array(
				'name'     => __( 'Complete', 'ai-search-schema' ),
				'view'     => 'step-5-complete',
				'required' => false,
			),
		);

		return apply_filters( 'ai_search_schema_wizard_steps', $steps );
	}

	/**
	 * Add admin menu page.
	 */
	public function add_admin_menu() {
		$hook = add_submenu_page(
			'options.php', // Hidden from menu (use options.php instead of null for PHP 8 compatibility).
			__( 'AI Search Schema Setup', 'ai-search-schema' ),
			__( 'Setup Wizard', 'ai-search-schema' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_wizard_page' )
		);

		// Set page title early to prevent PHP 8 deprecation warnings in admin-header.php.
		if ( $hook ) {
			add_action( 'load-' . $hook, array( $this, 'set_page_title' ) );
		}
	}

	/**
	 * Set wizard page title early (before admin-header.php).
	 */
	public function set_page_title() {
		global $title;
		$title = __( 'AI Search Schema Setup', 'ai-search-schema' );
	}

	/**
	 * Set wizard page title on current_screen hook (backup for load-{$hook}).
	 *
	 * @param WP_Screen $screen Current screen object.
	 */
	public function maybe_set_wizard_title( $screen ) {
		if ( ! $screen || ! isset( $screen->id ) ) {
			return;
		}

		$screen_id = (string) $screen->id;
		if ( '' !== $screen_id && strpos( $screen_id, self::PAGE_SLUG ) !== false ) {
			$this->set_page_title();
		}
	}

	/**
	 * Maybe redirect to wizard on first activation.
	 */
	public function maybe_redirect_to_wizard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Check if we should redirect.
		$redirect = get_transient( 'ai_search_schema_wizard_redirect' );
		if ( $redirect ) {
			delete_transient( 'ai_search_schema_wizard_redirect' );

			// Don't redirect if wizard is already completed.
			if ( $this->is_completed() ) {
				return;
			}

			// Don't redirect during bulk activation or AJAX.
			if ( wp_doing_ajax() || isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return;
			}

			wp_safe_redirect( $this->get_wizard_url() );
			exit;
		}
	}

	/**
	 * Show setup notice if wizard not completed.
	 */
	public function show_setup_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Don't show on wizard page.
		$screen = get_current_screen();
		if ( $screen && $screen->id && strpos( $screen->id, self::PAGE_SLUG ) !== false ) {
			return;
		}

		// Don't show if wizard is completed.
		if ( $this->is_completed() ) {
			return;
		}

		// Don't show if dismissed.
		if ( get_user_meta( get_current_user_id(), 'ai_search_schema_wizard_notice_dismissed', true ) ) {
			return;
		}

		?>
		<div class="notice notice-info is-dismissible ais-wizard-notice">
			<p>
				<strong><?php esc_html_e( 'AI Search Schema', 'ai-search-schema' ); ?></strong>
				<?php esc_html_e( 'Complete the setup wizard to optimize your site for Google search.', 'ai-search-schema' ); ?>
				<a href="<?php echo esc_url( $this->get_wizard_url() ); ?>" class="button button-primary" style="margin-left: 10px;">
					<?php esc_html_e( 'Start Setup', 'ai-search-schema' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Enqueue wizard scripts and styles.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		if ( empty( $hook ) || strpos( $hook, self::PAGE_SLUG ) === false ) {
			return;
		}

		// current_step をここで取得（render_wizard_page()より先に呼ばれるため）.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_step = isset( $_GET['step'] ) ? sanitize_key( $_GET['step'] ) : 'welcome';
		if ( ! isset( $this->steps[ $current_step ] ) ) {
			$current_step = 'welcome';
		}
		$this->current_step = $current_step;

		wp_enqueue_media();

		wp_enqueue_style(
			'ais-wizard',
			AI_SEARCH_SCHEMA_URL . 'assets/dist/css/wizard.min.css',
			array(),
			AI_SEARCH_SCHEMA_VERSION
		);

		wp_enqueue_script(
			'ais-wizard',
			AI_SEARCH_SCHEMA_URL . 'assets/js/wizard.js',
			array( 'jquery', 'wp-util' ),
			AI_SEARCH_SCHEMA_VERSION,
			true
		);

		wp_localize_script(
			'ais-wizard',
			'aisWizardData',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'ai_search_schema_wizard_nonce' ),
				'geocodeNonce' => wp_create_nonce( 'ai_search_schema_geocode' ),
				'currentStep'  => $this->current_step,
				'wizardUrl'    => admin_url( 'admin.php?page=' . self::PAGE_SLUG ),
				'settingsUrl'  => admin_url( 'options-general.php?page=ai-search-schema' ),
				'strings'      => array(
					'saving'             => __( 'Saving...', 'ai-search-schema' ),
					'saved'              => __( 'Saved', 'ai-search-schema' ),
					'error'              => __( 'An error occurred', 'ai-search-schema' ),
					'errorSaving'        => __( 'Error saving. Please try again.', 'ai-search-schema' ),
					'required'           => __( 'This field is required', 'ai-search-schema' ),
					'invalidUrl'         => __( 'Please enter a valid URL', 'ai-search-schema' ),
					'selectLogo'         => __( 'Select Logo', 'ai-search-schema' ),
					'useLogo'            => __( 'Use this logo', 'ai-search-schema' ),
					'noLogo'             => __( 'No logo selected', 'ai-search-schema' ),
					'import'             => __( 'Import', 'ai-search-schema' ),
					'importing'          => __( 'Importing...', 'ai-search-schema' ),
					'imported'           => __( 'Imported!', 'ai-search-schema' ),
					'importError'        => __( 'Import failed. Please try again.', 'ai-search-schema' ),
					'enterAddress'       => __( 'Please enter an address first.', 'ai-search-schema' ),
					'fetching'           => __( 'Fetching...', 'ai-search-schema' ),
					'getCoordinates'     => __( 'Get Coordinates', 'ai-search-schema' ),
					'geocodeError'       => __( 'Could not fetch coordinates.', 'ai-search-schema' ),
					'viewSchema'         => __( 'View JSON-LD Schema', 'ai-search-schema' ),
					'hideSchema'         => __( 'Hide JSON-LD Schema', 'ai-search-schema' ),
					'noSchema'           => __( 'No schema generated yet.', 'ai-search-schema' ),
					'schemaError'        => __( 'Could not load schema.', 'ai-search-schema' ),
					'noHoursSet'         => __( 'Set your business hours above to see a preview.', 'ai-search-schema' ),
					'monday'             => __( 'Monday', 'ai-search-schema' ),
					'tuesday'            => __( 'Tuesday', 'ai-search-schema' ),
					'wednesday'          => __( 'Wednesday', 'ai-search-schema' ),
					'thursday'           => __( 'Thursday', 'ai-search-schema' ),
					'friday'             => __( 'Friday', 'ai-search-schema' ),
					'saturday'           => __( 'Saturday', 'ai-search-schema' ),
					'sunday'             => __( 'Sunday', 'ai-search-schema' ),
					'localBusinessTitle' => __( 'Local Business Selected', 'ai-search-schema' ),
					'localBusinessText'  => __( 'You will be able to enter your business address, hours, and contact information.', 'ai-search-schema' ),
					'organizationTitle'  => __( 'Organization Selected', 'ai-search-schema' ),
					'organizationText'   => __( 'Perfect for companies, non-profits, and institutions.', 'ai-search-schema' ),
					'personTitle'        => __( 'Personal Site Selected', 'ai-search-schema' ),
					'personText'         => __( 'Great for blogs, portfolios, and freelancer websites.', 'ai-search-schema' ),
					'websiteTitle'       => __( 'Online Service Selected', 'ai-search-schema' ),
					'websiteText'        => __( 'Ideal for news sites, web apps, and online tools.', 'ai-search-schema' ),
				),
			)
		);
	}

	/**
	 * Render the wizard page.
	 */
	public function render_wizard_page() {
		// enqueue_scripts()で既に設定されていなければ、ここで current_step を取得.
		if ( empty( $this->current_step ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$this->current_step = isset( $_GET['step'] ) ? sanitize_key( $_GET['step'] ) : 'welcome';

			if ( ! isset( $this->steps[ $this->current_step ] ) ) {
				$this->current_step = 'welcome';
			}
		}

		// Get wizard data.
		$progress    = $this->get_progress();
		$wizard_data = $progress['data'] ?? array();
		$options     = get_option( 'ai_search_schema_options', array() );

		// Make current_step available as a local variable for templates.
		$current_step = $this->current_step;

		// Include the wizard template.
		include AI_SEARCH_SCHEMA_DIR . 'templates/wizard/wizard-page.php';
	}

	/**
	 * Get wizard progress for current user.
	 *
	 * @return array
	 */
	public function get_progress() {
		$user_id  = get_current_user_id();
		$progress = get_user_meta( $user_id, self::META_PROGRESS, true );

		if ( ! is_array( $progress ) ) {
			$progress = array(
				'current_step'    => 'welcome',
				'completed_steps' => array(),
				'skipped_steps'   => array(),
				'started_at'      => current_time( 'mysql' ),
				'data'            => array(),
			);
		}

		return $progress;
	}

	/**
	 * Save wizard progress.
	 *
	 * @param array $progress Progress data.
	 */
	public function save_progress( $progress ) {
		$user_id = get_current_user_id();
		update_user_meta( $user_id, self::META_PROGRESS, $progress );
	}

	/**
	 * Check if wizard is completed.
	 *
	 * @return bool
	 */
	public function is_completed() {
		return (bool) get_option( self::OPTION_COMPLETED, false );
	}

	/**
	 * Mark wizard as completed.
	 */
	public function mark_completed() {
		update_option( self::OPTION_COMPLETED, true );
		update_option( 'ai_search_schema_wizard_completed_at', current_time( 'mysql' ) );
		update_option( 'ai_search_schema_wizard_version', AI_SEARCH_SCHEMA_VERSION );

		do_action( 'ai_search_schema_wizard_completed', $this->get_progress() );
	}

	/**
	 * Get wizard URL.
	 *
	 * @param string $step Optional step slug.
	 * @return string
	 */
	public function get_wizard_url( $step = '' ) {
		$url = admin_url( 'admin.php?page=' . self::PAGE_SLUG );

		if ( $step ) {
			$url = add_query_arg( 'step', $step, $url );
		}

		return $url;
	}

	/**
	 * Ajax: Save step data.
	 */
	public function ajax_save_step() {
		check_ajax_referer( 'ai_search_schema_wizard_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'ai-search-schema' ) ) );
		}

		$step = isset( $_POST['step'] ) ? sanitize_key( $_POST['step'] ) : '';
		$data = isset( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( ! $step || ! isset( $this->steps[ $step ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid step', 'ai-search-schema' ) ) );
		}

		// Sanitize data based on step.
		$sanitized_data = $this->sanitize_step_data( $step, $data );

		// Save to progress.
		$progress                      = $this->get_progress();
		$progress['data'][ $step ]     = $sanitized_data;
		$progress['current_step']      = $step;
		$progress['completed_steps'][] = $step;
		$progress['completed_steps']   = array_unique( $progress['completed_steps'] );

		$this->save_progress( $progress );

		// Also save to main settings.
		$this->save_to_settings( $step, $sanitized_data );

		do_action( 'ai_search_schema_wizard_step_completed', $step, $sanitized_data );

		// 営業時間ステップ（最後のデータ入力ステップ）完了時にウィザードを完了としてマーク.
		if ( 'hours' === $step ) {
			$this->mark_completed();
		}

		wp_send_json_success(
			array(
				'message'  => __( 'Step saved', 'ai-search-schema' ),
				'progress' => $progress,
			)
		);
	}

	/**
	 * Ajax: Skip step.
	 */
	public function ajax_skip_step() {
		check_ajax_referer( 'ai_search_schema_wizard_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'ai-search-schema' ) ) );
		}

		$step = isset( $_POST['step'] ) ? sanitize_key( $_POST['step'] ) : '';

		if ( ! $step || ! isset( $this->steps[ $step ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid step', 'ai-search-schema' ) ) );
		}

		$progress                    = $this->get_progress();
		$progress['skipped_steps'][] = $step;
		$progress['skipped_steps']   = array_unique( $progress['skipped_steps'] );

		$this->save_progress( $progress );

		wp_send_json_success(
			array(
				'message'  => __( 'Step skipped', 'ai-search-schema' ),
				'progress' => $progress,
			)
		);
	}

	/**
	 * Ajax: Complete wizard.
	 */
	public function ajax_complete() {
		check_ajax_referer( 'ai_search_schema_wizard_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'ai-search-schema' ) ) );
		}

		$this->mark_completed();

		wp_send_json_success(
			array(
				'message'     => __( 'Setup completed!', 'ai-search-schema' ),
				'redirectUrl' => admin_url( 'options-general.php?page=ai-search-schema' ),
			)
		);
	}

	/**
	 * Ajax: Reset wizard.
	 */
	public function ajax_reset() {
		check_ajax_referer( 'ai_search_schema_wizard_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'ai-search-schema' ) ) );
		}

		$user_id = get_current_user_id();
		delete_user_meta( $user_id, self::META_PROGRESS );
		delete_option( self::OPTION_COMPLETED );

		wp_send_json_success(
			array(
				'message'     => __( 'Wizard reset', 'ai-search-schema' ),
				'redirectUrl' => $this->get_wizard_url(),
			)
		);
	}

	/**
	 * Ajax: Import from other plugins.
	 */
	public function ajax_import() {
		check_ajax_referer( 'ai_search_schema_wizard_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'ai-search-schema' ) ) );
		}

		$source = isset( $_POST['source'] ) ? sanitize_key( $_POST['source'] ) : '';

		$importer      = new Wizard_Importer();
		$imported_data = $importer->import( $source );

		if ( is_wp_error( $imported_data ) ) {
			wp_send_json_error( array( 'message' => $imported_data->get_error_message() ) );
		}

		// Save imported data to progress.
		$progress         = $this->get_progress();
		$progress['data'] = array_merge( $progress['data'], $imported_data );
		$this->save_progress( $progress );

		// Also save to main settings.
		foreach ( $imported_data as $step => $data ) {
			$this->save_to_settings( $step, $data );
		}

		do_action( 'ai_search_schema_wizard_import_completed', $source, $imported_data );

		wp_send_json_success(
			array(
				'message'  => __( 'Settings imported successfully', 'ai-search-schema' ),
				'imported' => $imported_data,
				'progress' => $progress,
			)
		);
	}

	/**
	 * Sanitize step data.
	 *
	 * Accepts input names from UI and normalizes them for internal storage.
	 *
	 * @param string $step Step slug.
	 * @param array  $data Raw data.
	 * @return array Sanitized data.
	 */
	private function sanitize_step_data( $step, $data ) {
		$sanitized = array();

		switch ( $step ) {
			case 'basics':
				// UI sends: site_name, site_description, logo_url.
				// Also accept legacy: company_name, site_url, logo_id for backwards compatibility.
				$sanitized['site_name']        = isset( $data['site_name'] ) ? sanitize_text_field( $data['site_name'] ) : '';
				$sanitized['site_description'] = isset( $data['site_description'] ) ? sanitize_textarea_field( $data['site_description'] ) : '';
				$sanitized['logo_url']         = isset( $data['logo_url'] ) ? esc_url_raw( $data['logo_url'] ) : '';

				// company_name: use provided value, or fallback to site_name.
				$sanitized['company_name'] = isset( $data['company_name'] ) && '' !== $data['company_name']
					? sanitize_text_field( $data['company_name'] )
					: $sanitized['site_name'];

				// Legacy support: logo_id, site_url.
				$sanitized['logo_id']  = isset( $data['logo_id'] ) ? absint( $data['logo_id'] ) : 0;
				$sanitized['site_url'] = isset( $data['site_url'] ) ? esc_url_raw( $data['site_url'] ) : '';
				break;

			case 'type':
				// UI sends: entity_type (LocalBusiness, Organization, Person, WebSite).
				// Normalize to Organization or LocalBusiness only.
				$entity_type = isset( $data['entity_type'] ) ? sanitize_key( $data['entity_type'] ) : '';

				// Map UI values to valid entity types.
				$valid_types = array( 'organization', 'localbusiness' );
				$entity_type = strtolower( $entity_type );

				if ( in_array( $entity_type, $valid_types, true ) ) {
					$sanitized['entity_type'] = 'localbusiness' === $entity_type ? 'LocalBusiness' : 'Organization';
				} else {
					// Person/WebSite -> Organization for schema compatibility.
					$sanitized['entity_type'] = 'Organization';
				}

				// Legacy support: site_type.
				if ( isset( $data['site_type'] ) && ! isset( $data['entity_type'] ) ) {
					$site_type = sanitize_key( $data['site_type'] );
					$sanitized['entity_type'] = 'local_business' === $site_type ? 'LocalBusiness' : 'Organization';
				}

				// business_type is set in location step, but accept here for legacy.
				$sanitized['business_type'] = isset( $data['business_type'] ) ? sanitize_text_field( $data['business_type'] ) : '';
				break;

			case 'api-key':
				$sanitized['gmaps_api_key'] = isset( $data['gmaps_api_key'] ) ? sanitize_text_field( $data['gmaps_api_key'] ) : '';
				break;

			case 'location':
				// UI sends: local_business_name, local_business_type,
				// postal_code, address_region, address_locality, street_address, address_country,
				// telephone, email, geo_latitude, geo_longitude, price_range.
				$sanitized['local_business_name'] = isset( $data['local_business_name'] ) ? sanitize_text_field( $data['local_business_name'] ) : '';
				$sanitized['local_business_type'] = isset( $data['local_business_type'] ) ? sanitize_text_field( $data['local_business_type'] ) : '';

				// Build normalized address array.
				$sanitized['address'] = array(
					'postal_code'    => isset( $data['postal_code'] ) ? sanitize_text_field( $data['postal_code'] ) : '',
					'region'         => isset( $data['address_region'] ) ? sanitize_text_field( $data['address_region'] ) : '',
					'locality'       => isset( $data['address_locality'] ) ? sanitize_text_field( $data['address_locality'] ) : '',
					'street_address' => isset( $data['street_address'] ) ? sanitize_text_field( $data['street_address'] ) : '',
					'country'        => isset( $data['address_country'] ) ? sanitize_text_field( $data['address_country'] ) : 'JP',
				);

				// Build normalized geo array.
				$sanitized['geo'] = array(
					'latitude'  => isset( $data['geo_latitude'] ) ? floatval( $data['geo_latitude'] ) : 0,
					'longitude' => isset( $data['geo_longitude'] ) ? floatval( $data['geo_longitude'] ) : 0,
				);

				// Contact info.
				$sanitized['telephone'] = isset( $data['telephone'] ) ? sanitize_text_field( $data['telephone'] ) : '';
				$sanitized['email']     = isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '';

				// Price range.
				$sanitized['price_range'] = isset( $data['price_range'] ) ? sanitize_text_field( $data['price_range'] ) : '';

				// Legacy support: old field names.
				if ( ! $sanitized['address']['region'] && isset( $data['prefecture'] ) ) {
					$sanitized['address']['region'] = sanitize_text_field( $data['prefecture'] );
				}
				if ( ! $sanitized['address']['locality'] && isset( $data['city'] ) ) {
					$sanitized['address']['locality'] = sanitize_text_field( $data['city'] );
				}
				if ( ! $sanitized['address']['street_address'] && isset( $data['street'] ) ) {
					$sanitized['address']['street_address'] = sanitize_text_field( $data['street'] );
				}
				if ( ! $sanitized['telephone'] && isset( $data['phone'] ) ) {
					$sanitized['telephone'] = sanitize_text_field( $data['phone'] );
				}
				if ( 0 === $sanitized['geo']['latitude'] && isset( $data['latitude'] ) ) {
					$sanitized['geo']['latitude'] = floatval( $data['latitude'] );
				}
				if ( 0 === $sanitized['geo']['longitude'] && isset( $data['longitude'] ) ) {
					$sanitized['geo']['longitude'] = floatval( $data['longitude'] );
				}
				break;

			case 'hours':
				// UI sends: hours_{day}_opens, hours_{day}_closes (individual selects).
				// Convert to opening_hours array format matching settings expectations.
				$opening_hours = array();

				// Map lowercase wizard days to capitalized settings format.
				$day_map = array(
					'monday'    => 'Monday',
					'tuesday'   => 'Tuesday',
					'wednesday' => 'Wednesday',
					'thursday'  => 'Thursday',
					'friday'    => 'Friday',
					'saturday'  => 'Saturday',
					'sunday'    => 'Sunday',
					'holiday'   => 'PublicHoliday',
				);

				foreach ( $day_map as $wizard_day => $settings_day ) {
					$open_key  = 'hours_' . $wizard_day . '_opens';
					$close_key = 'hours_' . $wizard_day . '_closes';

					if ( ! empty( $data[ $open_key ] ) && ! empty( $data[ $close_key ] ) ) {
						$opening_hours[] = array(
							'day_key' => $settings_day,
							'opens'   => sanitize_text_field( $data[ $open_key ] ),
							'closes'  => sanitize_text_field( $data[ $close_key ] ),
						);
					}
				}

				// Also accept pre-formatted opening_hours array (from imports/legacy).
				if ( empty( $opening_hours ) && isset( $data['opening_hours'] ) && is_array( $data['opening_hours'] ) ) {
					$opening_hours = $this->sanitize_opening_hours( $data['opening_hours'] );
				}

				$sanitized['opening_hours']        = $opening_hours;
				$sanitized['price_range']          = isset( $data['price_range'] ) ? sanitize_text_field( $data['price_range'] ) : '';
				$sanitized['accepts_reservations'] = isset( $data['accepts_reservations'] ) ? (bool) $data['accepts_reservations'] : false;
				break;

			default:
				$sanitized = is_array( $data ) ? array_map( 'sanitize_text_field', $data ) : array();
				break;
		}

		return $sanitized;
	}

	/**
	 * Sanitize opening hours array.
	 *
	 * Normalizes both legacy format (day/open/close) and new format (day_key/opens/closes).
	 *
	 * @param array $hours Raw hours data.
	 * @return array Sanitized hours in settings-compatible format.
	 */
	private function sanitize_opening_hours( $hours ) {
		$sanitized = array();

		// Map lowercase wizard days to capitalized settings format.
		$day_map = array(
			'monday'    => 'Monday',
			'tuesday'   => 'Tuesday',
			'wednesday' => 'Wednesday',
			'thursday'  => 'Thursday',
			'friday'    => 'Friday',
			'saturday'  => 'Saturday',
			'sunday'    => 'Sunday',
			'holiday'   => 'PublicHoliday',
		);

		$valid_days = array_merge( array_keys( $day_map ), array_values( $day_map ) );

		foreach ( $hours as $entry ) {
			// Accept both 'day' and 'day_key'.
			$day = $entry['day_key'] ?? $entry['day'] ?? '';
			if ( ! in_array( $day, $valid_days, true ) ) {
				continue;
			}

			// Normalize lowercase to capitalized.
			$day_key = isset( $day_map[ $day ] ) ? $day_map[ $day ] : $day;

			// Accept both 'opens'/'closes' and 'open'/'close'.
			$opens  = $entry['opens'] ?? $entry['open'] ?? '';
			$closes = $entry['closes'] ?? $entry['close'] ?? '';

			if ( empty( $opens ) || empty( $closes ) ) {
				continue;
			}

			$sanitized[] = array(
				'day_key' => $day_key,
				'opens'   => sanitize_text_field( $opens ),
				'closes'  => sanitize_text_field( $closes ),
			);
		}

		return $sanitized;
	}

	/**
	 * Save wizard data to main plugin settings.
	 *
	 * Normalizes data from sanitize_step_data() and saves to ai_search_schema_options.
	 *
	 * @param string $step Step slug.
	 * @param array  $data Sanitized data.
	 */
	private function save_to_settings( $step, $data ) {
		$settings = get_option( 'ai_search_schema_options', array() );

		switch ( $step ) {
			case 'basics':
				// Save site_name.
				if ( ! empty( $data['site_name'] ) ) {
					$settings['site_name'] = $data['site_name'];
				}

				// Save company_name (defaults to site_name in sanitize_step_data).
				if ( ! empty( $data['company_name'] ) ) {
					$settings['company_name'] = $data['company_name'];
				}

				// Save site_url if provided.
				if ( ! empty( $data['site_url'] ) ) {
					$settings['site_url'] = $data['site_url'];
				}

				// Save logo_url: prefer logo_url, fallback to logo_id.
				if ( ! empty( $data['logo_url'] ) ) {
					$settings['logo_url'] = $data['logo_url'];
				} elseif ( ! empty( $data['logo_id'] ) ) {
					$settings['logo_url'] = wp_get_attachment_url( $data['logo_id'] );
				}

				// site_description is stored but not used in schema currently.
				if ( ! empty( $data['site_description'] ) ) {
					$settings['site_description'] = $data['site_description'];
				}
				break;

			case 'type':
				// entity_type is now directly available from sanitize_step_data.
				$settings['entity_type'] = $data['entity_type'] ?? 'Organization';

				// Set content_model based on entity_type.
				$settings['content_model'] = 'WebPage';

				// Save business_type if provided (legacy support).
				if ( ! empty( $data['business_type'] ) ) {
					$settings['local_business_type'] = $data['business_type'];
				}
				break;

			case 'api-key':
				// Save API key to separate option (not in main settings array).
				if ( ! empty( $data['gmaps_api_key'] ) ) {
					update_option( 'ai_search_schema_gmaps_api_key', $data['gmaps_api_key'], false );
				}
				return; // Return early as we don't need to update main settings.

			case 'location':
				// Update company_name from local_business_name if company_name not already set.
				if ( ! empty( $data['local_business_name'] ) ) {
					if ( empty( $settings['company_name'] ) ) {
						$settings['company_name'] = $data['local_business_name'];
					}
					// Also store as local_business_name for reference.
					$settings['local_business_name'] = $data['local_business_name'];
				}

				// Save local_business_type and sync to lb_subtype for schema output compatibility.
				if ( ! empty( $data['local_business_type'] ) ) {
					$settings['local_business_type'] = $data['local_business_type'];
					$settings['lb_subtype']          = $data['local_business_type'];
				}

				// Save normalized address array.
				if ( ! empty( $data['address'] ) && is_array( $data['address'] ) ) {
					$settings['address'] = $data['address'];
				}

				// Save normalized geo array.
				if ( ! empty( $data['geo'] ) && is_array( $data['geo'] ) ) {
					$geo = $data['geo'];
					if ( ! empty( $geo['latitude'] ) || ! empty( $geo['longitude'] ) ) {
						$settings['geo'] = $geo;
					}
				}

				// Save phone (normalize from telephone).
				if ( ! empty( $data['telephone'] ) ) {
					$settings['phone'] = $data['telephone'];
				}

				// Save email.
				if ( ! empty( $data['email'] ) ) {
					$settings['email'] = $data['email'];
				}

				// Save price_range.
				if ( ! empty( $data['price_range'] ) ) {
					$settings['price_range'] = $data['price_range'];
				}
				break;

			case 'hours':
				if ( ! empty( $data['opening_hours'] ) ) {
					$settings['opening_hours'] = $data['opening_hours'];
				}
				if ( ! empty( $data['price_range'] ) ) {
					$settings['price_range'] = $data['price_range'];
				}
				if ( isset( $data['accepts_reservations'] ) ) {
					$settings['accepts_reservations'] = $data['accepts_reservations'] ? '1' : '';
				}
				break;
		}

		update_option( 'ai_search_schema_options', $settings );
	}

	/**
	 * Ajax: Get generated schema preview.
	 *
	 * Note: This builds a simplified preview schema without page-specific context,
	 * as template functions like is_404() don't work in AJAX context.
	 */
	public function ajax_get_schema() {
		check_ajax_referer( 'ai_search_schema_wizard_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'ai-search-schema' ) ) );
		}

		try {
			// 依存クラスを読み込み.
			if ( ! class_exists( '\AI_Search_Schema_Settings' ) ) {
				require_once AI_SEARCH_SCHEMA_DIR . 'includes/class-ai-search-schema-settings.php';
			}
			if ( ! class_exists( '\AI_Search_Schema_OpeningHoursBuilder' ) ) {
				require_once AI_SEARCH_SCHEMA_DIR . 'src/Schema/class-ai-search-schema-openinghoursbuilder.php';
			}

			// 設定を正規化.
			$options = \AI_Search_Schema_Settings::init()->get_options();

			// プレビュー用の簡易スキーマを構築.
			$schema = $this->build_preview_schema( $options );

			if ( empty( $schema ) || empty( $schema['@graph'] ) ) {
				wp_send_json_success(
					array(
						'schema'  => null,
						'message' => __( 'No schema generated yet. Please complete the wizard steps first.', 'ai-search-schema' ),
					)
				);
			}

			wp_send_json_success( array( 'schema' => $schema ) );
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array(
					'message' => __( 'Error generating schema.', 'ai-search-schema' ),
					'error'   => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Build a simplified preview schema for the wizard.
	 *
	 * This only includes Organization/LocalBusiness and WebSite,
	 * without page-specific content that requires template functions.
	 *
	 * @param array $options Normalized plugin options.
	 * @return array JSON-LD schema array.
	 */
	private function build_preview_schema( array $options ) {
		$site_url = ! empty( $options['site_url'] ) ? trailingslashit( $options['site_url'] ) : trailingslashit( home_url() );
		$graph    = array();

		$ids = array(
			'organization'         => $site_url . '#org',
			'logo'                 => $site_url . '#logo',
			'website'              => $site_url . '#website',
			'local_business'       => $site_url . '#lb-main',
			'local_business_image' => $site_url . '#lb-image',
		);

		$entity_type = ! empty( $options['entity_type'] ) ? $options['entity_type'] : 'Organization';
		$language    = get_bloginfo( 'language' ) ?: 'ja-JP';

		// Organization.
		if ( 'Organization' === $entity_type || 'LocalBusiness' === $entity_type ) {
			$org = $this->build_preview_organization( $options, $ids, $site_url );
			if ( ! empty( $org ) ) {
				$graph[] = $org;
			}
		}

		// Logo.
		if ( ! empty( $options['logo_url'] ) ) {
			$logo = $this->build_preview_logo( $options, $ids );
			if ( ! empty( $logo ) ) {
				$graph[] = $logo;
			}
		}

		// LocalBusiness.
		if ( 'LocalBusiness' === $entity_type ) {
			$lb = $this->build_preview_local_business( $options, $ids, $site_url );
			if ( ! empty( $lb ) ) {
				$graph[] = $lb;
			}
		}

		// WebSite.
		$website = $this->build_preview_website( $options, $ids, $site_url, $language );
		if ( ! empty( $website ) ) {
			$graph[] = $website;
		}

		if ( empty( $graph ) ) {
			return array();
		}

		return array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		);
	}

	/**
	 * Build Organization schema for preview.
	 *
	 * @param array  $options  Plugin options.
	 * @param array  $ids      Graph IDs.
	 * @param string $site_url Site URL.
	 * @return array Organization schema.
	 */
	private function build_preview_organization( array $options, array $ids, $site_url ) {
		$name = ! empty( $options['company_name'] ) ? $options['company_name'] : get_bloginfo( 'name' );
		if ( empty( $name ) ) {
			return array();
		}

		$org = array(
			'@type' => 'Organization',
			'@id'   => $ids['organization'],
			'name'  => $name,
			'url'   => $site_url,
		);

		if ( ! empty( $options['logo_url'] ) ) {
			$org['logo'] = array( '@id' => $ids['logo'] );
		}

		return $org;
	}

	/**
	 * Build Logo schema for preview.
	 *
	 * @param array $options Plugin options.
	 * @param array $ids     Graph IDs.
	 * @return array Logo schema.
	 */
	private function build_preview_logo( array $options, array $ids ) {
		if ( empty( $options['logo_url'] ) ) {
			return array();
		}

		return array(
			'@type'      => 'ImageObject',
			'@id'        => $ids['logo'],
			'url'        => $options['logo_url'],
			'contentUrl' => $options['logo_url'],
		);
	}

	/**
	 * Build LocalBusiness schema for preview.
	 *
	 * @param array  $options  Plugin options.
	 * @param array  $ids      Graph IDs.
	 * @param string $site_url Site URL.
	 * @return array LocalBusiness schema.
	 */
	private function build_preview_local_business( array $options, array $ids, $site_url ) {
		$type = ! empty( $options['local_business_type'] ) ? $options['local_business_type'] : 'LocalBusiness';
		$name = ! empty( $options['company_name'] ) ? $options['company_name'] : get_bloginfo( 'name' );

		if ( empty( $name ) ) {
			return array();
		}

		$lb = array(
			'@type'            => $type,
			'@id'              => $ids['local_business'],
			'name'             => $name,
			'url'              => $site_url,
			'parentOrganization' => array( '@id' => $ids['organization'] ),
		);

		// Address.
		$address = $this->build_preview_address( $options );
		if ( ! empty( $address ) ) {
			$lb['address'] = $address;
		}

		// Phone.
		if ( ! empty( $options['phone'] ) ) {
			$lb['telephone'] = $options['phone'];
		}

		// Geo coordinates.
		if ( ! empty( $options['geo']['latitude'] ) && ! empty( $options['geo']['longitude'] ) ) {
			$lb['geo'] = array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => (float) $options['geo']['latitude'],
				'longitude' => (float) $options['geo']['longitude'],
			);
		}

		// Opening hours.
		if ( class_exists( '\AI_Search_Schema_OpeningHoursBuilder' ) ) {
			$hours = \AI_Search_Schema_OpeningHoursBuilder::build( $options );
			if ( ! empty( $hours ) ) {
				$lb['openingHoursSpecification'] = $hours;
			}
		}

		return $lb;
	}

	/**
	 * Build PostalAddress for preview.
	 *
	 * @param array $options Plugin options.
	 * @return array PostalAddress schema or empty array.
	 */
	private function build_preview_address( array $options ) {
		$address = $options['address'] ?? array();

		$postal = array( '@type' => 'PostalAddress' );

		if ( ! empty( $address['street_address'] ) ) {
			$postal['streetAddress'] = $address['street_address'];
		}
		if ( ! empty( $address['locality'] ) ) {
			$postal['addressLocality'] = $address['locality'];
		}
		if ( ! empty( $address['region'] ) ) {
			$postal['addressRegion'] = $address['region'];
		}
		if ( ! empty( $address['postal_code'] ) ) {
			$postal['postalCode'] = $address['postal_code'];
		}

		$postal['addressCountry'] = ! empty( $address['country'] ) ? $address['country'] : 'JP';

		// Check if we have any actual address data.
		if ( count( $postal ) <= 2 ) { // Only @type and addressCountry.
			return array();
		}

		return $postal;
	}

	/**
	 * Build WebSite schema for preview.
	 *
	 * @param array  $options  Plugin options.
	 * @param array  $ids      Graph IDs.
	 * @param string $site_url Site URL.
	 * @param string $language Site language.
	 * @return array WebSite schema.
	 */
	private function build_preview_website( array $options, array $ids, $site_url, $language ) {
		$name = ! empty( $options['site_name'] ) ? $options['site_name'] : get_bloginfo( 'name' );

		$website = array(
			'@type'       => 'WebSite',
			'@id'         => $ids['website'],
			'url'         => $site_url,
			'name'        => $name,
			'inLanguage'  => $language,
		);

		// Publisher reference.
		$entity_type = ! empty( $options['entity_type'] ) ? $options['entity_type'] : 'Organization';
		if ( 'Organization' === $entity_type || 'LocalBusiness' === $entity_type ) {
			$website['publisher'] = array( '@id' => $ids['organization'] );
		}

		return $website;
	}
}

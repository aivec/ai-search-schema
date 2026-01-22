<?php
/**
 * Plugin Name: Aivec AI Search Schema
 * Plugin URI: https://aivec.co.jp/apps
 * Description: Schema markup for AI search optimization, local SEO, breadcrumbs, and FAQ.
 * Version:           1.1.1
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Aivec LLC
 * Author URI:        https://aivec.co.jp/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ai-search-schema
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'AVC_AIS_VERSION', '1.1.1' );
define( 'AVC_AIS_DIR', plugin_dir_path( __FILE__ ) );
define( 'AVC_AIS_URL', plugin_dir_url( __FILE__ ) );
define( 'AVC_AIS_FILE', __FILE__ );

/**
 * Filter null values from plugin options to prevent PHP 8 deprecation warnings.
 *
 * This must be registered early (before any plugin code runs) to ensure
 * WordPress internal functions don't receive null values.
 *
 * @param mixed $value Option value.
 * @return mixed Filtered value.
 */
function avc_ais_filter_null_option_values( $value ) {
	if ( ! is_array( $value ) ) {
		return $value;
	}
	$result = array();
	foreach ( $value as $key => $val ) {
		if ( is_null( $val ) ) {
			continue;
		}
		if ( is_array( $val ) ) {
			$result[ $key ] = avc_ais_filter_null_option_values( $val );
		} else {
			$result[ $key ] = $val;
		}
	}
	return $result;
}

// Register null filters for all plugin options at the earliest point.
add_filter( 'option_avc_ais_options', 'avc_ais_filter_null_option_values', 1 );
add_filter( 'option_avc_ais_settings', 'avc_ais_filter_null_option_values', 1 );
add_filter( 'option_avc_ais_wizard_progress', 'avc_ais_filter_null_option_values', 1 );
add_filter( 'option_avc_ais_license', 'avc_ais_filter_null_option_values', 1 );

// Composer autoloader or PSR-4 fallback.
$ais_autoloader = AVC_AIS_DIR . 'vendor/autoload.php';
if ( file_exists( $ais_autoloader ) ) {
	require_once $ais_autoloader;
} else {
	spl_autoload_register(
		static function ( $class_name ) {
			// PSR-4 (namespaced).
			$prefix   = 'Aivec\\AiSearchSchema\\';
			$base_dir = AVC_AIS_DIR . 'src/';

			$len = strlen( $prefix );
			if ( strncmp( $prefix, $class_name, $len ) === 0 ) {
				$relative_class = substr( $class_name, $len );
				$file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

				if ( file_exists( $file ) ) {
					require $file;
				}
				return;
			}

			// Legacy global classes (AVC_AIS_*).
			$legacy_prefix = 'AVC_AIS_';
			if ( strpos( $class_name, $legacy_prefix ) === 0 ) {
				$relative = substr( $class_name, strlen( $legacy_prefix ) );
				$slug     = strtolower( str_replace( '_', '-', $relative ) );

				if ( 'Schema' === $relative ) {
					$file = AVC_AIS_DIR . 'includes/class-avc-ais-schema.php';
				} elseif ( strpos( $relative, 'Type_' ) === 0 ) {
					$file = AVC_AIS_DIR . 'src/Schema/Type/class-avc-ais-' . $slug . '.php';
				} elseif ( strpos( $relative, 'Adapter_' ) === 0 ) {
					$file = AVC_AIS_DIR . 'src/Schema/Adapter/class-avc-ais-' . $slug . '.php';
				} else {
					$file = AVC_AIS_DIR . 'src/Schema/class-avc-ais-' . $slug . '.php';
				}

				if ( file_exists( $file ) ) {
					require $file;
					return;
				}

				// Fallback to includes/ directory.
				$includes_file = AVC_AIS_DIR . 'includes/class-avc-ais-' . $slug . '.php';
				if ( file_exists( $includes_file ) ) {
					require $includes_file;
				}
			}
		}
	);
}

/**
 * Plugin entrypoint.
 *
 * @return \Aivec\AiSearchSchema\Plugin
 */
if ( ! function_exists( 'avc_ais_init' ) ) {
	function avc_ais_init() {
		static $plugin = null;

		if ( null === $plugin ) {
			$plugin = new \Aivec\AiSearchSchema\Plugin();
			$plugin->register();
		}

		return $plugin;
	}
}

avc_ais_init();

// Initialize llms.txt generator.
add_action(
	'init',
	static function () {
		require_once AVC_AIS_DIR . 'includes/class-avc-ais-llms-txt.php';
		AVC_AIS_Llms_Txt::init();
	},
	5
);

// Initialize Pro features manager (only if Pro add-on hasn't loaded its own).
add_action(
	'init',
	static function () {
		if ( ! class_exists( 'AVC_AIS_Pro_Features' ) ) {
			require_once AVC_AIS_DIR . 'includes/class-avc-ais-pro-features.php';
			AVC_AIS_Pro_Features::init();
		}
	},
	10
);

// Activation hook - set redirect transient for wizard and flush rewrite rules.
register_activation_hook(
	__FILE__,
	static function () {
		// Only set redirect if wizard hasn't been completed.
		if ( ! get_option( 'avc_ais_wizard_completed', false ) ) {
			set_transient( 'avc_ais_wizard_redirect', true, 30 );
		}

		// Flush rewrite rules for llms.txt.
		require_once AVC_AIS_DIR . 'includes/class-avc-ais-llms-txt.php';
		AVC_AIS_Llms_Txt::activate();
	}
);

// Deactivation hook - flush rewrite rules.
register_deactivation_hook(
	__FILE__,
	static function () {
		require_once AVC_AIS_DIR . 'includes/class-avc-ais-llms-txt.php';
		AVC_AIS_Llms_Txt::deactivate();
	}
);

// Cleanup on uninstall.
register_uninstall_hook( __FILE__, array( '\\Aivec\\AiSearchSchema\\Plugin', 'uninstall' ) );

<?php
/**
 * Plugin Name: AI Search Schema
 * Plugin URI: https://aivec.co.jp/apps
 * Description: Schema markup for AI search optimization, local SEO, breadcrumbs, and FAQ.
 * Version:           1.0.7-dev
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Aivec LLC
 * Author URI:        https://aivec.co.jp/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ai-search-schema
 * Domain Path:       /languages
 */

// Plugin constants.
define( 'AI_SEARCH_SCHEMA_VERSION', '1.0.7-dev' );
define( 'AI_SEARCH_SCHEMA_DIR', plugin_dir_path( __FILE__ ) );
define( 'AI_SEARCH_SCHEMA_URL', plugin_dir_url( __FILE__ ) );
define( 'AI_SEARCH_SCHEMA_FILE', __FILE__ );

/**
 * Filter null values from plugin options to prevent PHP 8 deprecation warnings.
 *
 * This must be registered early (before any plugin code runs) to ensure
 * WordPress internal functions don't receive null values.
 *
 * @param mixed $value Option value.
 * @return mixed Filtered value.
 */
function ai_search_schema_filter_null_option_values( $value ) {
	if ( ! is_array( $value ) ) {
		return $value;
	}
	$result = array();
	foreach ( $value as $key => $val ) {
		if ( is_null( $val ) ) {
			continue;
		}
		if ( is_array( $val ) ) {
			$result[ $key ] = ai_search_schema_filter_null_option_values( $val );
		} else {
			$result[ $key ] = $val;
		}
	}
	return $result;
}

// Register null filters for all plugin options at the earliest point.
add_filter( 'option_ai_search_schema_options', 'ai_search_schema_filter_null_option_values', 1 );
add_filter( 'option_ai_search_schema_settings', 'ai_search_schema_filter_null_option_values', 1 );
add_filter( 'option_ai_search_schema_wizard_progress', 'ai_search_schema_filter_null_option_values', 1 );
add_filter( 'option_ai_search_schema_license', 'ai_search_schema_filter_null_option_values', 1 );

// Composer autoloader or PSR-4 fallback.
$ais_autoloader = AI_SEARCH_SCHEMA_DIR . 'vendor/autoload.php';
if ( file_exists( $ais_autoloader ) ) {
	require_once $ais_autoloader;
} else {
	spl_autoload_register(
		static function ( $class_name ) {
			// PSR-4 (namespaced).
			$prefix   = 'Aivec\\AiSearchSchema\\';
			$base_dir = AI_SEARCH_SCHEMA_DIR . 'src/';

			$len = strlen( $prefix );
			if ( strncmp( $prefix, $class_name, $len ) === 0 ) {
				$relative_class = substr( $class_name, $len );
				$file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

				if ( file_exists( $file ) ) {
					require $file;
				}
				return;
			}

			// Legacy global classes (AI_Search_Schema_*).
			$legacy_prefix = 'AI_Search_Schema_';
			if ( strpos( $class_name, $legacy_prefix ) === 0 ) {
				$relative = substr( $class_name, strlen( $legacy_prefix ) );
				$slug     = strtolower( str_replace( '_', '-', $relative ) );

				if ( strpos( $relative, 'Type_' ) === 0 ) {
					$file = AI_SEARCH_SCHEMA_DIR . 'src/Schema/Type/class-ai-search-schema-' . $slug . '.php';
				} elseif ( strpos( $relative, 'Adapter_' ) === 0 ) {
					$file = AI_SEARCH_SCHEMA_DIR . 'src/Schema/Adapter/class-ai-search-schema-' . $slug . '.php';
				} else {
					$file = AI_SEARCH_SCHEMA_DIR . 'src/Schema/class-ai-search-schema-' . $slug . '.php';
				}

				if ( file_exists( $file ) ) {
					require $file;
					return;
				}

				// Fallback to includes/ directory.
				$includes_file = AI_SEARCH_SCHEMA_DIR . 'includes/class-ai-search-schema-' . $slug . '.php';
				if ( file_exists( $includes_file ) ) {
					require $includes_file;
				}
			}
		}
	);
}

$ais_plugin = new \Aivec\AiSearchSchema\Plugin();
$ais_plugin->register();

// Initialize llms.txt generator.
add_action(
	'init',
	static function () {
		require_once AI_SEARCH_SCHEMA_DIR . 'includes/class-ai-search-schema-llms-txt.php';
		AI_Search_Schema_Llms_Txt::init();
	},
	5
);

// Initialize author display for frontend.
add_action(
	'init',
	static function () {
		if ( ! is_admin() ) {
			require_once AI_SEARCH_SCHEMA_DIR . 'includes/class-ai-search-schema-author-display.php';
			AI_Search_Schema_Author_Display::init();
		}
	},
	10
);

// Initialize Pro features manager (only if Pro add-on hasn't loaded its own).
add_action(
	'init',
	static function () {
		if ( ! class_exists( 'AI_Search_Schema_Pro_Features' ) ) {
			require_once AI_SEARCH_SCHEMA_DIR . 'includes/class-ai-search-schema-pro-features.php';
			AI_Search_Schema_Pro_Features::init();
		}
	},
	10
);

// Activation hook - set redirect transient for wizard and flush rewrite rules.
register_activation_hook(
	__FILE__,
	static function () {
		// Only set redirect if wizard hasn't been completed.
		if ( ! get_option( 'ai_search_schema_wizard_completed', false ) ) {
			set_transient( 'ai_search_schema_wizard_redirect', true, 30 );
		}

		// Flush rewrite rules for llms.txt.
		require_once AI_SEARCH_SCHEMA_DIR . 'includes/class-ai-search-schema-llms-txt.php';
		AI_Search_Schema_Llms_Txt::activate();
	}
);

// Deactivation hook - flush rewrite rules.
register_deactivation_hook(
	__FILE__,
	static function () {
		require_once AI_SEARCH_SCHEMA_DIR . 'includes/class-ai-search-schema-llms-txt.php';
		AI_Search_Schema_Llms_Txt::deactivate();
	}
);

// Cleanup on uninstall.
register_uninstall_hook( __FILE__, array( '\\Aivec\\AiSearchSchema\\Plugin', 'uninstall' ) );

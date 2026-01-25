<?php
/**
 * PHPUnit bootstrap for AI Search Schema tests.
 */

$wp_tests_dir = getenv('WP_TESTS_DIR');
$wp_tests_dir = $wp_tests_dir ?: '/tmp/wordpress-tests-lib';

$functions = $wp_tests_dir . '/includes/functions.php';
$bootstrap = $wp_tests_dir . '/includes/bootstrap.php';

if (!file_exists($functions)) {
    fwrite(
        STDERR,
        "Could not find WordPress test functions at {$functions}. Set WP_TESTS_DIR or run bin/install-wp-tests.sh.\n"
    );
    exit(1);
}

if (!file_exists($bootstrap)) {
    fwrite(
        STDERR,
        "Could not find WordPress test bootstrap at {$bootstrap}. Set WP_TESTS_DIR or run bin/install-wp-tests.sh.\n"
    );
    exit(1);
}

require_once $functions;

tests_add_filter('muplugins_loaded', static function () {
    $plugin_dir = dirname(__DIR__, 2);
    $plugin_file = $plugin_dir . '/aivec-ai-search-schema.php';

    if (!file_exists($plugin_file)) {
        fwrite(STDERR, "Plugin loader not found at {$plugin_file}.\n");
        exit(1);
    }

    $autoload = $plugin_dir . '/vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
    }

    require_once $plugin_file;
});

require_once $bootstrap;
require_once __DIR__ . '/test-helpers.php';

<?php
use PHPUnit\Framework\TestCase;

if (!class_exists('WP_UnitTestCase')) {
    abstract class WP_UnitTestCase extends TestCase {}
}

if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}

class AI_Search_Schema_TEST_Env {
    public static $current_post_id = 0;
    public static $posts = [];
    public static $post_meta = [];
    public static $options = [];
    public static $thumbnails = [];
    public static $attachments = [];
    public static $transients = [];
    public static $autosaves = [];
    public static $revisions = [];
    public static $capabilities = [
        'manage_options' => true,
        'edit_post' => true,
    ];
    public static $http_handler = null;
    public static $queried_object = null;
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        // For testing we store callbacks but do not execute automatically.
        global $ai_search_schema_test_actions;
        if (!isset($ai_search_schema_test_actions)) {
            $ai_search_schema_test_actions = [];
        }
        $ai_search_schema_test_actions[$hook][] = $callback;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($hook, $value) {
        return $value;
    }
}

if (!function_exists('__')) {
    function __($text, $domain = null) {
        return $text;
    }
}

if (!function_exists('_e')) {
    function _e($text, $domain = null) {
        echo $text;
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = null) {
        return $text;
    }
}

if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = array()) {
        if (is_object($args)) {
            $args = get_object_vars($args);
        } elseif (!is_array($args)) {
            parse_str($args, $args);
        }
        return array_merge($defaults, $args);
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url) {
        return filter_var($url, FILTER_SANITIZE_URL);
    }
}

if (!function_exists('wp_kses')) {
    function wp_kses($content, $allowed_html = array(), $allowed_protocols = array()) {
        return $content;
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        public $errors = array();
        public $error_data = array();

        public function __construct($code = '', $message = '', $data = '') {
            if ($code) {
                $this->errors[$code] = (array) $message;
                $this->error_data[$code] = $data;
            }
        }
    }
}

if (!class_exists('WP_Term')) {
    class WP_Term {
        public $term_id;
        public $name;
        public $taxonomy;
        public $slug;
    }
}

if (!class_exists('WP_User')) {
    class WP_User {
        public $ID;
        public $display_name;
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $options = 0, $depth = 512) {
        return json_encode($data, $options | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1) {
        return 'nonce-' . $action;
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value) {
        if (is_array($value)) {
            return array_map('wp_unslash', $value);
        }
        return is_string($value) ? stripslashes($value) : $value;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        $filtered = strip_tags((string) $str);
        $filtered = preg_replace('/[\r\n\t ]+/', ' ', $filtered);
        return trim($filtered);
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($str) {
        return trim(strip_tags((string) $str));
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($email) {
        return filter_var($email, FILTER_SANITIZE_EMAIL);
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url) {
        return filter_var($url, FILTER_SANITIZE_URL);
    }
}

if (!function_exists('get_option')) {
    function get_option($name, $default = false) {
        return AI_Search_Schema_TEST_Env::$options[$name] ?? $default;
    }
}

if (!function_exists('get_post_meta')) {
    function get_post_meta($post_id, $key = '', $single = false) {
        $value = AI_Search_Schema_TEST_Env::$post_meta[$post_id][$key] ?? ($single ? '' : []);
        if ($single) {
            return $value;
        }
        return (array) $value;
    }
}

if (!function_exists('update_post_meta')) {
    function update_post_meta($post_id, $key, $value) {
        AI_Search_Schema_TEST_Env::$post_meta[$post_id][$key] = $value;
        return true;
    }
}

if (!function_exists('get_the_ID')) {
    function get_the_ID() {
        return AI_Search_Schema_TEST_Env::$current_post_id;
    }
}

if (!function_exists('get_queried_object_id')) {
    function get_queried_object_id() {
        return AI_Search_Schema_TEST_Env::$current_post_id ?: 0;
    }
}

if (!function_exists('get_queried_object')) {
    function get_queried_object() {
        return AI_Search_Schema_TEST_Env::$queried_object;
    }
}

if (!function_exists('get_post')) {
    function get_post($post_id) {
        return AI_Search_Schema_TEST_Env::$posts[$post_id] ?? null;
    }
}

if (!function_exists('get_post_type')) {
    function get_post_type($post_id = 0) {
        $post_id = $post_id ?: AI_Search_Schema_TEST_Env::$current_post_id;
        $post = AI_Search_Schema_TEST_Env::$posts[$post_id] ?? null;
        return $post->post_type ?? 'post';
    }
}

if (!function_exists('get_post_ancestors')) {
    function get_post_ancestors($post_id = 0) {
        return array();
    }
}

if (!function_exists('get_the_title')) {
    function get_the_title($post_id = 0) {
        $post_id = $post_id ?: AI_Search_Schema_TEST_Env::$current_post_id;
        return AI_Search_Schema_TEST_Env::$posts[$post_id]->post_title ?? '';
    }
}

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($text) {
        return trim(strip_tags((string) $text));
    }
}

if (!function_exists('wp_trim_words')) {
    function wp_trim_words($text, $num_words = 55, $more = '…') {
        $words = preg_split('/\s+/', trim(strip_tags($text)));
        if (count($words) <= $num_words) {
            return implode(' ', $words);
        }
        return implode(' ', array_slice($words, 0, $num_words)) . $more;
    }
}

if (!function_exists('get_author_posts_url')) {
    function get_author_posts_url($author_id) {
        return 'http://example.com/author/' . $author_id;
    }
}

if (!function_exists('get_the_author_meta')) {
    function get_the_author_meta($field, $user_id) {
        return 'Author ' . $user_id;
    }
}

if (!function_exists('get_the_date')) {
    function get_the_date($format = 'c', $post_id = 0) {
        return gmdate($format, strtotime('2024-01-01 00:00:00'));
    }
}

if (!function_exists('get_the_modified_date')) {
    function get_the_modified_date($format = 'c', $post_id = 0) {
        return gmdate($format, strtotime('2024-01-02 00:00:00'));
    }
}

if (!function_exists('has_post_thumbnail')) {
    function has_post_thumbnail($post_id = 0) {
        return isset(AI_Search_Schema_TEST_Env::$thumbnails[$post_id]);
    }
}

if (!function_exists('get_post_thumbnail_id')) {
    function get_post_thumbnail_id($post_id) {
        return AI_Search_Schema_TEST_Env::$thumbnails[$post_id]['id'] ?? null;
    }
}

if (!function_exists('wp_get_attachment_image_src')) {
    function wp_get_attachment_image_src($attachment_id, $size = 'full') {
        if (!isset(AI_Search_Schema_TEST_Env::$attachments[$attachment_id])) {
            return false;
        }

        $data = AI_Search_Schema_TEST_Env::$attachments[$attachment_id];
        return [$data['url'], $data['width'], $data['height'], true];
    }
}

if (!function_exists('wp_get_attachment_url')) {
    function wp_get_attachment_url($attachment_id = 0) {
        return AI_Search_Schema_TEST_Env::$attachments[$attachment_id]['url'] ?? '';
    }
}

if (!function_exists('get_the_excerpt')) {
    function get_the_excerpt($post_id = 0) {
        $post_id = $post_id ?: AI_Search_Schema_TEST_Env::$current_post_id;
        return AI_Search_Schema_TEST_Env::$posts[$post_id]->post_excerpt ?? '';
    }
}

if (!function_exists('get_site_url')) {
    function get_site_url() {
        return 'http://example.com';
    }
}

if (!function_exists('home_url')) {
    function home_url($path = '') {
        $url = 'http://example.com';
        if ($path) {
            $url = rtrim($url, '/') . '/' . ltrim($path, '/');
        }
        return $url;
    }
}

if (!function_exists('admin_url')) {
    function admin_url($path = '') {
        return 'http://example.com/wp-admin/' . ltrim($path, '/');
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg($args, $url) {
        if (is_array($args)) {
            $query = http_build_query($args);
        } else {
            $query = (string) $args;
        }
        $separator = strpos($url, '?') === false ? '?' : '&';
        return $url . $separator . $query;
    }
}

if (!function_exists('get_bloginfo')) {
    function get_bloginfo($key = '') {
        if ('name' === $key) {
            return 'Example Site';
        }
        return '';
    }
}

if (!function_exists('get_permalink')) {
    function get_permalink($post_id = 0) {
        $post_id = $post_id ?: AI_Search_Schema_TEST_Env::$current_post_id;
        return 'http://example.com/post/' . $post_id;
    }
}

if (!function_exists('get_the_archive_title')) {
    function get_the_archive_title() {
        return 'Archive';
    }
}

if (!function_exists('get_search_query')) {
    function get_search_query($escaped = true) {
        return 'sample query';
    }
}

if (!function_exists('get_search_link')) {
    function get_search_link($query = '') {
        $query = $query ?: 'sample query';
        return 'http://example.com/?s=' . rawurlencode($query);
    }
}

if (!function_exists('get_term_link')) {
    function get_term_link($term) {
        $slug = is_object($term) && isset($term->slug) ? $term->slug : 'term';
        return 'http://example.com/term/' . $slug;
    }
}

if (!function_exists('get_term')) {
    function get_term($term_id, $taxonomy) {
        $term = new WP_Term();
        $term->term_id = $term_id;
        $term->taxonomy = $taxonomy;
        $term->name = 'Term ' . $term_id;
        $term->slug = 'term-' . $term_id;
        return $term;
    }
}

if (!function_exists('get_ancestors')) {
    function get_ancestors($object_id = 0, $object_type = '') {
        return array();
    }
}

if (!function_exists('is_taxonomy_hierarchical')) {
    function is_taxonomy_hierarchical($taxonomy) {
        return in_array($taxonomy, array('category', 'product_cat'), true);
    }
}

if (!function_exists('wp_remote_get')) {
    function wp_remote_get($url, $args = array()) {
        if (isset(AI_Search_Schema_TEST_Env::$http_handler) && is_callable(AI_Search_Schema_TEST_Env::$http_handler)) {
            return call_user_func(AI_Search_Schema_TEST_Env::$http_handler, $url, $args);
        }
        return array(
            'response' => array('code' => 200),
            'body' => '{}',
        );
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) {
        return $response['response']['code'] ?? 0;
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) {
        return $response['body'] ?? '';
    }
}

if (!function_exists('get_locale')) {
    function get_locale() {
        return 'en_US';
    }
}

if (!function_exists('set_transient')) {
    function set_transient($key, $value, $expiration) {
        AI_Search_Schema_TEST_Env::$transients[$key] = array(
            'value' => $value,
            'expires' => time() + (int) $expiration,
        );
        return true;
    }
}

if (!function_exists('get_transient')) {
    function get_transient($key) {
        if (!isset(AI_Search_Schema_TEST_Env::$transients[$key])) {
            return false;
        }
        $entry = AI_Search_Schema_TEST_Env::$transients[$key];
        if ($entry['expires'] < time()) {
            unset(AI_Search_Schema_TEST_Env::$transients[$key]);
            return false;
        }
        return $entry['value'];
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($key) {
        unset(AI_Search_Schema_TEST_Env::$transients[$key]);
        return true;
    }
}

if (!function_exists('absint')) {
    function absint($maybeint) {
        return abs((int) $maybeint);
    }
}

if (!function_exists('is_admin')) {
    function is_admin() {
        return false;
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability, ...$args) {
        return !empty(AI_Search_Schema_TEST_Env::$capabilities[$capability]);
    }
}

if (!function_exists('wp_is_post_autosave')) {
    function wp_is_post_autosave($post_id) {
        return !empty(AI_Search_Schema_TEST_Env::$autosaves[$post_id]);
    }
}

if (!function_exists('wp_is_post_revision')) {
    function wp_is_post_revision($post_id) {
        return !empty(AI_Search_Schema_TEST_Env::$revisions[$post_id]);
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id() {
        return 1;
    }
}

if (!function_exists('is_singular')) {
    function is_singular() {
        return true;
    }
}

if (!function_exists('is_front_page')) {
    function is_front_page() {
        return false;
    }
}

if (!function_exists('is_404')) {
    function is_404() {
        return false;
    }
}

if (!function_exists('is_archive')) {
    function is_archive() {
        return false;
    }
}

if (!function_exists('is_home')) {
    function is_home() {
        return false;
    }
}

if (!function_exists('is_category')) {
    function is_category() {
        return false;
    }
}

if (!function_exists('is_tag')) {
    function is_tag() {
        return false;
    }
}

if (!function_exists('is_tax')) {
    function is_tax() {
        return false;
    }
}

if (!function_exists('is_author')) {
    function is_author() {
        return false;
    }
}

if (!function_exists('is_search')) {
    function is_search() {
        return false;
    }
}

if (!function_exists('is_single')) {
    function is_single() {
        return true;
    }
}

if (!function_exists('is_page')) {
    function is_page($post = null) {
        return true;
    }
}

if (!class_exists('AI_Search_Schema_TEST_JSON_Response')) {
    class AI_Search_Schema_TEST_JSON_Response extends Exception {
        public $success;
        public $data;
        public $status;

        public function __construct($success, $data = null, $status = 200) {
            parent::__construct('JSON response');
            $this->success = $success;
            $this->data = $data;
            $this->status = $status;
        }
    }
}

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null, $status_code = null) {
        throw new AI_Search_Schema_TEST_JSON_Response(true, $data, $status_code ?? 200);
    }
}

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null, $status_code = null) {
        throw new AI_Search_Schema_TEST_JSON_Response(false, $data, $status_code ?? 400);
    }
}

if (!function_exists('check_ajax_referer')) {
    function check_ajax_referer($action, $query_arg = false, $die = true) {
        return true;
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action) {
        return true;
    }
}

if (!function_exists('update_option')) {
    function update_option($name, $value, $autoload = null) {
        AI_Search_Schema_TEST_Env::$options[$name] = $value;
        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option($name) {
        unset(AI_Search_Schema_TEST_Env::$options[$name]);
        return true;
    }
}

if (!function_exists('get_user_meta')) {
    function get_user_meta($user_id, $key = '', $single = false) {
        $meta_key = "user_{$user_id}_{$key}";
        $value = AI_Search_Schema_TEST_Env::$options[$meta_key] ?? ($single ? '' : array());
        return $single ? $value : (array) $value;
    }
}

if (!function_exists('update_user_meta')) {
    function update_user_meta($user_id, $key, $value) {
        $meta_key = "user_{$user_id}_{$key}";
        AI_Search_Schema_TEST_Env::$options[$meta_key] = $value;
        return true;
    }
}

if (!function_exists('delete_user_meta')) {
    function delete_user_meta($user_id, $key) {
        $meta_key = "user_{$user_id}_{$key}";
        unset(AI_Search_Schema_TEST_Env::$options[$meta_key]);
        return true;
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        $key = strtolower($key);
        return preg_replace('/[^a-z0-9_\-]/', '', $key);
    }
}

if (!function_exists('do_action')) {
    function do_action($hook, ...$args) {
        // For testing, we don't execute actions.
    }
}

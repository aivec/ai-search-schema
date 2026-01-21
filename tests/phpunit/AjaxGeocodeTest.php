<?php
require_once __DIR__ . '/bootstrap.php';

class AI_Search_Schema_Ajax_Geocode_Test extends WP_UnitTestCase {
    private int $admin_id;
    private int $subscriber_id;
    private string $nonce;

    protected function setUp(): void {
        parent::setUp();
        $this->admin_id = self::factory()->user->create(array('role' => 'administrator'));
        $this->subscriber_id = self::factory()->user->create(array('role' => 'subscriber'));
        wp_set_current_user($this->admin_id);
        wp_set_auth_cookie($this->admin_id);
        $this->nonce = wp_create_nonce('avc_ais_geocode');
        add_filter('avc_ais_bypass_geocode_nonce', '__return_true');
        add_action('avc_ais_json_responder', array($this, 'json_responder'), 10, 3);
        update_option('avc_ais_gmaps_api_key', 'test-key', false);

        AI_Search_Schema_TEST_Env::$options = array(
            'avc_ais_gmaps_api_key' => 'test-key',
        );
        AI_Search_Schema_TEST_Env::$http_handler = null;
        AI_Search_Schema_TEST_Env::$transients = array();
        AI_Search_Schema_TEST_Env::$capabilities = array('manage_options' => true);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    }

    protected function tearDown(): void {
        parent::tearDown();
        wp_set_current_user(0);
        AI_Search_Schema_TEST_Env::$http_handler = null;
        AI_Search_Schema_TEST_Env::$transients = array();
        AI_Search_Schema_TEST_Env::$capabilities = array('manage_options' => true);
        unset($_POST);
        delete_transient('avc_ais_geocode_' . md5('user_' . $this->admin_id));
        remove_filter('avc_ais_bypass_geocode_nonce', '__return_true');
        remove_action('avc_ais_json_responder', array($this, 'json_responder'), 10);
        delete_option('avc_ais_gmaps_api_key');
    }

    /**
     * JSON レスポンスをテスト用に例外へ変換するハンドラ。
     *
     * @param bool  $success 成否。
     * @param array $data    レスポンスデータ。
     * @param int   $status  ステータスコード。
     * @throws AI_Search_Schema_TEST_JSON_Response 常に投げてテストを継続する。
     */
    public function json_responder(bool $success, array $data, int $status): void {
        throw new AI_Search_Schema_TEST_JSON_Response($success, $data, $status);
    }

    private function prepare_request_address() {
        $_POST = array(
            'nonce' => $this->nonce,
            'address' => array(
                'postal_code' => '100-0001',
                'region' => 'Tokyo',
                'locality' => 'Chiyoda',
                'street' => '1-1',
                'country' => 'JP',
            ),
        );
    }

    public function test_geocode_success_via_google_api() {
        $this->prepare_request_address();
        add_filter('pre_http_request', array($this, 'mock_google_success'), 10, 3);

        $settings = new AI_Search_Schema_Settings();

        try {
            $settings->handle_geocode_request();
            $this->fail('Expected JSON response');
        } catch (AI_Search_Schema_TEST_JSON_Response $response) {
            $this->assertTrue($response->success);
            $this->assertSame('35.1', $response->data['latitude']);
            $this->assertSame('139.2', $response->data['longitude']);
            $this->assertSame('Tokyo, Japan', $response->data['query']);
        }
        remove_filter('pre_http_request', array($this, 'mock_google_success'), 10);
    }

    public function test_geocode_requires_address_input() {
        $_POST = array('nonce' => $this->nonce, 'address' => array());
        $settings = new AI_Search_Schema_Settings();

        try {
            $settings->handle_geocode_request();
            $this->fail('Expected validation error');
        } catch (AI_Search_Schema_TEST_JSON_Response $response) {
            $this->assertFalse($response->success);
            $this->assertStringContainsString('Enter the address information', $response->data['message']);
        }
    }

    public function test_geocode_respects_rate_limit() {
        $this->prepare_request_address();
        add_filter('pre_http_request', array($this, 'mock_google_success'), 10, 3);

        $settings = new AI_Search_Schema_Settings();

        // First call should succeed and set the transient.
        try {
            $settings->handle_geocode_request();
            $this->fail('Expected JSON response');
        } catch (AI_Search_Schema_TEST_JSON_Response $response) {
            $this->assertTrue($response->success);
        }
        remove_filter('pre_http_request', array($this, 'mock_google_success'), 10);

        // Second immediate call should be rate-limited.
        try {
            $settings->handle_geocode_request();
            $this->fail('Expected rate-limit error');
        } catch (AI_Search_Schema_TEST_JSON_Response $response) {
            $this->assertFalse($response->success);
            $this->assertSame(429, $response->status);
        }
    }

    public function test_geocode_requires_manage_options_capability() {
        $this->prepare_request_address();
        wp_set_current_user($this->subscriber_id);
        $this->nonce = wp_create_nonce('avc_ais_geocode');
        $_POST['nonce'] = $this->nonce;
        $settings = new AI_Search_Schema_Settings();

        try {
            $settings->handle_geocode_request();
            $this->fail('Expected permission error');
        } catch (AI_Search_Schema_TEST_JSON_Response $response) {
            $this->assertFalse($response->success);
            $this->assertSame(403, $response->status);
        }
    }

    public function mock_google_success($preempt, $r, $url) {
        if (false === stripos($url, 'googleapis.com')) {
            return $preempt;
        }

        $body = json_encode(array(
            'results' => array(
                array(
                    'formatted_address' => 'Tokyo, Japan',
                    'geometry' => array('location' => array('lat' => 35.1, 'lng' => 139.2)),
                ),
            ),
        ));

        return array('response' => array('code' => 200), 'body' => $body);
    }
}

<?php
require_once __DIR__ . '/bootstrap.php';

class AI_Search_Schema_Settings_Sanitize_Test extends WP_UnitTestCase {
    protected function setUp(): void {
        parent::setUp();
        AI_Search_Schema_TEST_Env::$options = array();
    }

    public function test_sanitize_normalizes_languages_and_social_links() {
        $settings = new AI_Search_Schema_Settings();

        $input = array(
            'languages' => array('foo', 'en', 'ja', 'en'),
            'social_links' => array(
                array(
                    'network' => 'unknown',
                    'account' => ' ExampleProfile ',
                    'label' => 'Test <b>Label</b>',
                ),
            ),
            'geo' => array(
                'latitude' => ' 35.0000 ',
                'longitude' => "139.0000<script>",
            ),
            'address' => array(
                'prefecture' => '東京都',
                'prefecture_iso' => '',
                'postal_code' => '1000001',
                'locality' => '千代田区',
                'street_address' => '霞が関1-1',
                'country' => '',
            ),
            'has_map_enabled' => '1',
        );

        $result = $settings->sanitize_options($input);

        $this->assertSame(array('en', 'ja'), $result['languages'], 'Languages should include only valid codes and remove duplicates.');
        $this->assertSame('other', $result['social_links'][0]['network']);
        $this->assertSame('ExampleProfile', $result['social_links'][0]['account']);
        $this->assertSame('Test Label', $result['social_links'][0]['label']);
        $this->assertSame('35.0000', $result['geo']['latitude']);
        $this->assertSame('139.0000', $result['geo']['longitude']);
        $this->assertSame('東京都', $result['address']['prefecture']);
        $this->assertSame('JP-13', $result['address']['prefecture_iso']);
        $this->assertSame('JP', $result['address']['country']);
        $this->assertTrue($result['has_map_enabled']);
    }

    public function test_legacy_has_map_respects_boolean_values() {
        $settings = new AI_Search_Schema_Settings();

        $disabled = $settings->sanitize_options(array('has_map' => '0'));
        $this->assertFalse($disabled['has_map_enabled']);

        $enabled = $settings->sanitize_options(array('has_map' => '1'));
        $this->assertTrue($enabled['has_map_enabled']);
    }

    public function test_legacy_has_map_ignores_unknown_values() {
        $settings = new AI_Search_Schema_Settings();

        $result = $settings->sanitize_options(array('has_map' => 'banana'));

        $this->assertFalse($result['has_map_enabled']);
    }
}

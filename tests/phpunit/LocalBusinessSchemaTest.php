<?php
require_once __DIR__ . '/bootstrap.php';

class AI_Search_Schema_LocalBusiness_Test extends WP_UnitTestCase {
    private int $page_id;

    protected function setUp(): void {
        parent::setUp();

        $this->page_id = self::factory()->post->create(
            [
                'post_type'    => 'page',
                'post_title'   => 'Storefront Landing',
                'post_content' => '<p>Storefront description.</p>',
                'post_excerpt' => 'Storefront excerpt.',
                'post_status'  => 'publish',
            ]
        );

        update_post_meta(
            $this->page_id,
            '_avc_ais_meta',
            [
                'page_type' => 'WebPage',
            ]
        );

        update_option(
            'avc_ais_options',
            [
                'company_name' => 'Sample Store',
                'logo_url' => 'http://example.com/logo.png',
                'site_url' => 'http://example.com',
                'entity_type' => 'Organization',
                'lb_subtype' => 'Store',
                'phone' => '012-345-6789',
                'languages' => ['en'],
                'has_map_enabled' => true,
                'geo' => [
                    'latitude' => '35.123456',
                    'longitude' => '139.654321',
                ],
                'address' => [
                    'postal_code' => '100-0001',
                    'prefecture' => '東京都',
                    'prefecture_iso' => 'JP-13',
                    'region' => '東京都',
                    'locality' => '千代田区',
                    'street_address' => '1-1 Main St',
                    'country' => 'JP',
                ],
            ]
        );

        $ref = new ReflectionProperty(AI_Search_Schema::class, 'instance');
        $ref->setAccessible(true);
        $ref->setValue(null, null);

        $this->go_to( get_permalink( $this->page_id ) );
    }

    public function test_local_business_contains_geo_coordinates() {
        $schema = AI_Search_Schema::init();

        ob_start();
        $schema->output_json_ld();
        $output = ob_get_clean();

        $this->assertNotEmpty($output);
        $json = $this->extract_json_ld($output);
        $local_business = $this->find_item_by_type($json['@graph'], 'Store');
        $this->assertNotNull($local_business, 'LocalBusiness node should exist.');
        $this->assertSame('Store', is_array($local_business['@type']) ? $local_business['@type'][0] : $local_business['@type']);
        $this->assertEquals(35.123456, (float) $local_business['geo']['latitude']);
        $this->assertEquals(139.654321, (float) $local_business['geo']['longitude']);
        $this->assertSame('東京都', $local_business['address']['addressRegion']);
        $this->assertSame(
            'https://www.google.com/maps/search/?api=1&query=35.123456%2C139.654321',
            $local_business['hasMap']
        );
        $this->assertSame(
            [
                [
                    '@type' => 'AdministrativeArea',
                    'name' => '東京都',
                    'identifier' => 'JP-13',
                ],
                [
                    '@type' => 'Country',
                    'name' => 'Japan',
                    'identifier' => 'JP',
                ],
            ],
            $local_business['areaServed']
        );
        $this->assertSame(
            'http://example.com/#org',
            $local_business['branchOf']['@id']
        );
    }

    private function extract_json_ld($output) {
        if (!preg_match('/<script[^>]+class="ai-search-schema"[^>]*>(.*)<\/script>/s', $output, $matches)) {
            return null;
        }
        return json_decode(html_entity_decode(trim($matches[1])), true);
    }

    private function find_item_by_type(array $graph, $type) {
        foreach ($graph as $item) {
            if (isset($item['@type']) && ((is_array($item['@type']) && in_array($type, $item['@type'], true)) || $item['@type'] === $type)) {
                return $item;
            }
        }
        return null;
    }
}

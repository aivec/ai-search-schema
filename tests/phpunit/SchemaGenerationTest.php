<?php
/**
 * Article schema generation tests.
 */

require_once __DIR__ . '/bootstrap.php';

class AI_Search_Schema_Generation_Test extends WP_UnitTestCase {
    /**
     * @var AI_Search_Schema
     */
    private $schema;
    private int $post_id;
    private int $author_id;

    protected function setUp(): void {
        parent::setUp();

        $this->author_id = self::factory()->user->create(
            [
                'display_name' => 'Author 42',
            ]
        );

        $this->post_id = self::factory()->post->create(
            [
                'post_author'  => $this->author_id,
                'post_title'   => 'Sample Article Title',
                'post_content' => '<p>Sample <strong>content</strong> with markup.</p>',
                'post_excerpt' => 'Sample excerpt for article.',
                'post_status'  => 'publish',
            ]
        );

        update_post_meta(
            $this->post_id,
            '_avc_ais_meta',
            [
                'page_type' => 'Article',
            ]
        );
        update_post_meta( $this->post_id, '_thumbnail_id', 200 );

        add_filter(
            'wp_get_attachment_image_src',
            static function ( $image, $attachment_id, $size ) {
                if ( 200 === (int) $attachment_id ) {
                    return [ 'http://example.com/featured.jpg', 800, 600, true ];
                }
                if ( 100 === (int) $attachment_id ) {
                    return [ 'http://example.com/logo.png', 400, 200, true ];
                }
                return $image;
            },
            10,
            3
        );

        update_option(
            'avc_ais_options',
            [
                'company_name'                  => 'Test Company',
                'logo_url'                     => 'http://example.com/logo.png',
                'logo_id'                      => 100,
                'site_url'                     => 'http://example.com',
                'languages'                    => ['ja'],
                'content_model'                => 'WebPage',
                'avc_ais_priority'          => 'ais',
                'avc_ais_breadcrumbs_schema_enabled' => true,
                'avc_ais_breadcrumbs_html_enabled'   => false,
            ]
        );

        $ref = new ReflectionProperty(AI_Search_Schema::class, 'instance');
        $ref->setAccessible(true);
        $ref->setValue(null, null);

        $this->go_to( get_permalink( $this->post_id ) );
        $this->schema = AI_Search_Schema::init();
    }

    public function test_article_schema_is_generated() {
        ob_start();
        $this->schema->output_json_ld();
        $output = ob_get_clean();

        $this->assertNotEmpty($output, 'JSON-LD output should not be empty for valid schema.');

        $json = $this->extract_json_ld($output);
        $this->assertIsArray($json, 'JSON-LD output must decode to array.');
        $this->assertArrayHasKey('@graph', $json);

        $article = $this->find_item_by_type($json['@graph'], 'Article');
        $this->assertNotNull($article, 'Article item should be present in graph.');
        $this->assertSame('Sample Article Title', $article['headline']);
        $this->assertSame('Author 42', $article['author']['name']);
        $this->assertSame(get_author_posts_url($this->author_id), $article['author']['url']);
        $this->assertSame(get_permalink($this->post_id) . '#webpage', $article['mainEntityOfPage']['@id']);
        $this->assertSame('http://example.com/featured.jpg', $article['image']['url']);
        $this->assertSame('ja-JP', $article['inLanguage']);

        $organization = $this->find_item_by_type($json['@graph'], 'Organization');
        $this->assertNotNull($organization, 'Organization should be present for context.');
        $this->assertSame('Test Company', $organization['name']);
        $this->assertSame('http://example.com/', $organization['url']);
    }

    /**
     * Extract JSON-LD from script output.
     *
     * @param string $output
     * @return array|null
     */
    private function extract_json_ld($output) {
        if (!preg_match('/<script[^>]+class=\"ai-search-schema\"[^>]*>(.*)<\\/script>/s', $output, $matches)) {
            return null;
        }
        $json = html_entity_decode(trim($matches[1]));
        return json_decode($json, true);
    }

    /**
     * Find item by @type.
     *
     * @param array  $graph
     * @param string $type
     * @return array|null
     */
    private function find_item_by_type(array $graph, $type) {
        foreach ($graph as $item) {
            if (isset($item['@type']) && $item['@type'] === $type) {
                return $item;
            }
        }
        return null;
    }
}

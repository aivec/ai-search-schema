<?php
/**
 * Article schema generation unit tests.
 */

require_once __DIR__ . '/bootstrap.php';

class AI_Search_Schema_ArticleSchema_Unit_Test extends WP_UnitTestCase {
	private int $post_id;
	private int $author_id;

	protected function setUp(): void {
		parent::setUp();

		$this->author_id = self::factory()->user->create(
			array(
				'display_name' => 'Author 5',
			)
		);

		$this->post_id = self::factory()->post->create(
			array(
				'post_author'  => $this->author_id,
				'post_title'   => 'Unit Article',
				'post_content' => '<p>Article body</p>',
				'post_excerpt' => 'Short excerpt',
			)
		);

		update_post_meta(
			$this->post_id,
			'_ai_search_schema_meta',
			array(
				'page_type' => 'Article',
			)
		);

		update_post_meta( $this->post_id, '_thumbnail_id', 999 );

		add_filter(
			'wp_get_attachment_image_src',
			static function ( $image, $attachment_id, $size ) {
				if ( 999 === (int) $attachment_id ) {
					return array( 'http://example.com/article.jpg', 640, 480, true );
				}
				return $image;
			},
			10,
			3
		);

		update_option(
			'ai_search_schema_options',
			array(
				'company_name'                   => 'Article Co',
				'logo_url'                       => 'http://example.com/logo.png',
				'logo_id'                        => 100,
				'site_url'                       => 'http://example.com',
				'languages'                      => array( 'en' ),
				'ai_search_schema_priority'            => 'ais',
				'ai_search_schema_breadcrumbs_schema_enabled' => true,
			)
		);

		$this->go_to( get_permalink( $this->post_id ) );
	}

	public function test_article_schema_fields_present() {
		$resolver = new AI_Search_Schema_ContentResolver();
		$builder  = new AI_Search_Schema_GraphBuilder( $resolver );
		$schema   = $builder->build( get_option( 'ai_search_schema_options' ) );

		$article = $this->find_by_type( $schema['@graph'], 'Article' );

		$this->assertNotEmpty( $article );
		$this->assertSame( 'Unit Article', $article['headline'] );
		$this->assertSame( 'Author 5', $article['author']['name'] );
		$this->assertSame( get_permalink( $this->post_id ) . '#webpage', $article['mainEntityOfPage']['@id'] );
		$this->assertSame( 'http://example.com/article.jpg', $article['image']['url'] );
	}

	private function find_by_type( array $graph, $type ) {
		foreach ( $graph as $node ) {
			if ( isset( $node['@type'] ) && $node['@type'] === $type ) {
				return $node;
			}
		}
		return array();
	}
}

<?php
/**
 * ContentResolver tests.
 */

require_once __DIR__ . '/bootstrap.php';

class AVC_AIS_ContentResolver_Test extends WP_UnitTestCase {
	public function test_normalize_site_url_trims_and_slash() {
		$resolver = new AVC_AIS_ContentResolver();
		$url      = $resolver->normalize_site_url(
			array(
				'site_url' => ' http://example.com/path ',
			)
		);

		$this->assertSame( 'http://example.com/path/', $url );
	}

	public function test_resolve_page_context_for_singular() {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'My Page',
				'post_status' => 'publish',
			)
		);

		$this->go_to( get_permalink( $post_id ) );

		$resolver = new AVC_AIS_ContentResolver();
		$context  = $resolver->resolve_page_context();

		$this->assertSame( $post_id, $context['post_id'] );
		$this->assertSame( 'My Page', $context['title'] );
		$this->assertSame( 'page', $context['type'] );
		$this->assertSame( get_permalink( $post_id ), $context['url'] );
	}
}

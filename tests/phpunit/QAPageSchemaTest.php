<?php
/**
 * QAPage schema generation tests.
 */

class QAPageSchemaTest extends WP_UnitTestCase {
	private int $post_id;

	protected function setUp(): void {
		parent::setUp();

		$this->post_id = self::factory()->post->create(
			[
				'post_title'   => 'How do I configure the plugin?',
				'post_content' => 'You can configure the plugin by going to Settings > AEO Schema.',
				'post_excerpt' => 'Go to Settings > AEO Schema to configure.',
				'post_status'  => 'publish',
			]
		);
	}

	protected function tearDown(): void {
		wp_delete_post( $this->post_id, true );
		parent::tearDown();
	}

	public function test_qapage_schema_with_excerpt() {
		$context = [
			'post_id' => $this->post_id,
			'url'     => get_permalink( $this->post_id ),
			'title'   => 'How do I configure the plugin?',
		];

		$schema = AVC_AIS_Type_QAPage::build(
			$context,
			'en-US',
			'http://example.com/#webpage'
		);

		$this->assertSame( 'QAPage', $schema['@type'] );
		$this->assertSame( get_permalink( $this->post_id ) . '#qapage', $schema['@id'] );
		$this->assertSame( get_permalink( $this->post_id ), $schema['url'] );
		$this->assertSame( 'How do I configure the plugin?', $schema['name'] );
		$this->assertSame( 'en-US', $schema['inLanguage'] );
		$this->assertCount( 1, $schema['mainEntity'] );

		$question = $schema['mainEntity'][0];
		$this->assertSame( 'Question', $question['@type'] );
		$this->assertSame( 'How do I configure the plugin?', $question['name'] );
		$this->assertSame( 'Answer', $question['acceptedAnswer']['@type'] );
		// Should use excerpt when available. Note: > is HTML encoded as &gt;.
		$this->assertStringContainsString( 'AEO Schema to configure', $question['acceptedAnswer']['text'] );
	}

	public function test_qapage_schema_without_excerpt_uses_content() {
		// Create a post without excerpt.
		$post_id = self::factory()->post->create(
			[
				'post_title'   => 'What is AEO?',
				'post_content' => 'AEO stands for Answer Engine Optimization.',
				'post_excerpt' => '',
				'post_status'  => 'publish',
			]
		);

		$context = [
			'post_id' => $post_id,
			'url'     => get_permalink( $post_id ),
			'title'   => 'What is AEO?',
		];

		$schema = AVC_AIS_Type_QAPage::build(
			$context,
			'ja-JP',
			'http://example.com/#webpage'
		);

		$this->assertSame( 'AEO stands for Answer Engine Optimization.', $schema['mainEntity'][0]['acceptedAnswer']['text'] );

		wp_delete_post( $post_id, true );
	}

	public function test_qapage_schema_without_post_id_uses_title() {
		$context = [
			'url'   => 'http://example.com/question/',
			'title' => 'Is this plugin free?',
		];

		$schema = AVC_AIS_Type_QAPage::build(
			$context,
			'en-US',
			'http://example.com/#webpage'
		);

		$this->assertSame( 'Is this plugin free?', $schema['mainEntity'][0]['acceptedAnswer']['text'] );
	}

	public function test_qapage_schema_without_language() {
		$context = [
			'post_id' => $this->post_id,
			'url'     => get_permalink( $this->post_id ),
			'title'   => 'How do I configure the plugin?',
		];

		$schema = AVC_AIS_Type_QAPage::build(
			$context,
			'',
			'http://example.com/#webpage'
		);

		$this->assertArrayNotHasKey( 'inLanguage', $schema );
	}

	public function test_qapage_schema_mainentityofpage() {
		$context = [
			'post_id' => $this->post_id,
			'url'     => get_permalink( $this->post_id ),
			'title'   => 'How do I configure the plugin?',
		];

		$schema = AVC_AIS_Type_QAPage::build(
			$context,
			'en-US',
			'http://example.com/#webpage'
		);

		$this->assertSame( 'http://example.com/#webpage', $schema['mainEntityOfPage']['@id'] );
	}
}

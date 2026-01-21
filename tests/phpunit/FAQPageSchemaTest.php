<?php
/**
 * FAQPage schema generation tests.
 */

class FAQPageSchemaTest extends WP_UnitTestCase {
	private int $post_id;

	protected function setUp(): void {
		parent::setUp();

		// Create a post with FAQ-style content.
		$this->post_id = self::factory()->post->create(
			[
				'post_title'   => 'Frequently Asked Questions',
				'post_content' => '
					<div class="faq-question">What is this plugin?</div>
					<div class="faq-answer">This is an AEO schema generator plugin for WordPress.</div>
					<div class="faq-question">How do I install it?</div>
					<div class="faq-answer">Upload the ZIP file and activate from Plugins menu.</div>
					<div class="faq-question">Is it free?</div>
					<div class="faq-answer">Yes, it is free to use.</div>
				',
				'post_status'  => 'publish',
			]
		);

		// Set up the default options.
		update_option(
			'avc_ais_options',
			[
				'company_name'                   => 'Test Company',
				'site_url'                       => 'http://example.com',
				'languages'                      => ['en'],
				'avc_ais_priority'            => 'ais',
				'avc_ais_breadcrumbs_schema_enabled' => true,
			]
		);
	}

	protected function tearDown(): void {
		wp_delete_post( $this->post_id, true );
		delete_option( 'avc_ais_options' );
		parent::tearDown();
	}

	public function test_faqpage_schema_not_generated_when_disabled() {
		update_post_meta(
			$this->post_id,
			'_avc_ais_meta',
			[
				'page_type'          => 'FAQPage',
				'faq_question_class' => 'faq-question',
				'faq_answer_class'   => 'faq-answer',
			]
		);

		// Disable FAQ via content_type_settings.
		$options = get_option( 'avc_ais_options' );
		$options['content_type_settings'] = [
			'post_types' => [
				'post' => [
					'faq_enabled' => false,
				],
			],
		];
		update_option( 'avc_ais_options', $options );

		$context = [
			'post_id'   => $this->post_id,
			'url'       => get_permalink( $this->post_id ),
			'title'     => 'FAQ Page',
			'post_type' => 'post',
		];

		$schema = AI_Search_Schema_Type_FAQPage::build(
			$context,
			'en-US',
			'http://example.com/#webpage',
			get_option( 'avc_ais_options' )
		);

		$this->assertEmpty( $schema );
	}

	public function test_faqpage_schema_not_generated_without_post_id() {
		$context = [
			'url'   => 'http://example.com/',
			'title' => 'FAQ Page',
		];

		$schema = AI_Search_Schema_Type_FAQPage::build(
			$context,
			'en-US',
			'http://example.com/#webpage',
			get_option( 'avc_ais_options' )
		);

		$this->assertEmpty( $schema );
	}

	public function test_faqpage_schema_not_generated_without_classes() {
		update_post_meta(
			$this->post_id,
			'_avc_ais_meta',
			[
				'page_type' => 'FAQPage',
				// Missing faq_question_class and faq_answer_class.
			]
		);

		$context = [
			'post_id'   => $this->post_id,
			'url'       => get_permalink( $this->post_id ),
			'title'     => 'FAQ Page',
			'post_type' => 'post',
		];

		$schema = AI_Search_Schema_Type_FAQPage::build(
			$context,
			'en-US',
			'http://example.com/#webpage',
			get_option( 'avc_ais_options' )
		);

		$this->assertEmpty( $schema );
	}

	public function test_faqpage_schema_not_generated_with_only_question_class() {
		update_post_meta(
			$this->post_id,
			'_avc_ais_meta',
			[
				'page_type'          => 'FAQPage',
				'faq_question_class' => 'faq-question',
				// Missing faq_answer_class.
			]
		);

		$context = [
			'post_id'   => $this->post_id,
			'url'       => get_permalink( $this->post_id ),
			'title'     => 'FAQ Page',
			'post_type' => 'post',
		];

		$schema = AI_Search_Schema_Type_FAQPage::build(
			$context,
			'en-US',
			'http://example.com/#webpage',
			get_option( 'avc_ais_options' )
		);

		$this->assertEmpty( $schema );
	}

	public function test_get_context_override_returns_defaults() {
		$reflection = new ReflectionClass( AI_Search_Schema_Type_FAQPage::class );
		$method     = $reflection->getMethod( 'get_context_override' );
		$method->setAccessible( true );

		$options = [
			'avc_ais_breadcrumbs_schema_enabled' => true,
			'avc_ais_priority'            => 'ais',
		];
		$context = [
			'post_type' => 'page',
		];

		$result = $method->invoke( null, $options, $context );

		$this->assertSame( 'auto', $result['schema_type'] );
		$this->assertTrue( $result['breadcrumbs_enabled'] );
		$this->assertTrue( $result['faq_enabled'] );
		$this->assertSame( 'ais', $result['schema_priority'] );
	}

	public function test_get_context_override_with_post_type_settings() {
		$reflection = new ReflectionClass( AI_Search_Schema_Type_FAQPage::class );
		$method     = $reflection->getMethod( 'get_context_override' );
		$method->setAccessible( true );

		$options = [
			'avc_ais_breadcrumbs_schema_enabled' => true,
			'content_type_settings'          => [
				'post_types' => [
					'post' => [
						'faq_enabled' => false,
					],
				],
			],
		];
		$context = [
			'post_type' => 'post',
		];

		$result = $method->invoke( null, $options, $context );

		$this->assertFalse( $result['faq_enabled'] );
	}

	public function test_get_context_override_with_taxonomy_settings() {
		$reflection = new ReflectionClass( AI_Search_Schema_Type_FAQPage::class );
		$method     = $reflection->getMethod( 'get_context_override' );
		$method->setAccessible( true );

		$options = [
			'avc_ais_breadcrumbs_schema_enabled' => true,
			'content_type_settings'          => [
				'taxonomies' => [
					'category' => [
						'faq_enabled' => false,
					],
				],
			],
		];
		$context = [
			'taxonomy' => 'category',
		];

		$result = $method->invoke( null, $options, $context );

		$this->assertFalse( $result['faq_enabled'] );
	}

	public function test_get_context_override_with_enable_breadcrumbs_fallback() {
		$reflection = new ReflectionClass( AI_Search_Schema_Type_FAQPage::class );
		$method     = $reflection->getMethod( 'get_context_override' );
		$method->setAccessible( true );

		$options = [
			'enable_breadcrumbs' => false,
		];
		$context = [];

		$result = $method->invoke( null, $options, $context );

		$this->assertFalse( $result['breadcrumbs_enabled'] );
	}
}

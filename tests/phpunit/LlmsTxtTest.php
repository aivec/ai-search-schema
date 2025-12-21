<?php
/**
 * Tests for llms.txt functionality.
 *
 * @package AI_Search_Schema
 */

require_once __DIR__ . '/bootstrap.php';

/**
 * Test class for AI_Search_Schema_Llms_Txt.
 */
class LlmsTxtTest extends WP_UnitTestCase {

	/**
	 * Instance of the llms.txt class.
	 *
	 * @var AI_Search_Schema_Llms_Txt
	 */
	private $llms;

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();
		require_once AI_SEARCH_SCHEMA_DIR . 'includes/class-ai-search-schema-llms-txt.php';

		// Reset singleton for clean tests.
		$reflection = new ReflectionClass( 'AI_Search_Schema_Llms_Txt' );
		$instance   = $reflection->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );

		$this->llms = AI_Search_Schema_Llms_Txt::init();

		// Clean up options.
		delete_option( AI_Search_Schema_Llms_Txt::OPTION_NAME );
		delete_option( AI_Search_Schema_Llms_Txt::OPTION_ENABLED );
	}

	/**
	 * Tear down test fixtures.
	 */
	public function tear_down() {
		delete_option( AI_Search_Schema_Llms_Txt::OPTION_NAME );
		delete_option( AI_Search_Schema_Llms_Txt::OPTION_ENABLED );
		parent::tear_down();
	}

	/**
	 * Test that llms.txt is enabled by default.
	 */
	public function test_is_enabled_by_default() {
		$this->assertTrue( $this->llms->is_enabled() );
	}

	/**
	 * Test setting enabled status.
	 */
	public function test_set_enabled() {
		$this->llms->set_enabled( false );
		$this->assertFalse( $this->llms->is_enabled() );

		$this->llms->set_enabled( true );
		$this->assertTrue( $this->llms->is_enabled() );
	}

	/**
	 * Test generate default content includes site name.
	 */
	public function test_generate_default_content_includes_site_name() {
		$content = $this->llms->generate_default_content();

		$this->assertStringContainsString( get_bloginfo( 'name' ), $content );
		$this->assertStringContainsString( '## ', $content );
	}

	/**
	 * Test generate default content includes site description.
	 */
	public function test_generate_default_content_includes_description() {
		$description = get_bloginfo( 'description' );
		if ( empty( $description ) ) {
			$this->markTestSkipped( 'Site description is empty.' );
		}

		$content = $this->llms->generate_default_content();
		$this->assertStringContainsString( $description, $content );
	}

	/**
	 * Test generate default content includes pages.
	 */
	public function test_generate_default_content_includes_pages() {
		// Create a test page.
		$page_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'Test Page for llms.txt',
				'post_content' => 'This is test content for the llms.txt page.',
				'post_status'  => 'publish',
			)
		);

		$content = $this->llms->generate_default_content();

		$this->assertStringContainsString( 'Test Page for llms.txt', $content );
		$this->assertStringContainsString( get_permalink( $page_id ), $content );

		wp_delete_post( $page_id, true );
	}

	/**
	 * Test generate default content includes posts.
	 */
	public function test_generate_default_content_includes_posts() {
		// Create a test post.
		$post_id = self::factory()->post->create(
			array(
				'post_type'    => 'post',
				'post_title'   => 'Test Post for llms.txt',
				'post_content' => 'This is test content for the llms.txt post.',
				'post_status'  => 'publish',
			)
		);

		$content = $this->llms->generate_default_content();

		$this->assertStringContainsString( 'Test Post for llms.txt', $content );
		$this->assertStringContainsString( get_permalink( $post_id ), $content );

		wp_delete_post( $post_id, true );
	}

	/**
	 * Test save and get content.
	 */
	public function test_save_and_get_content() {
		$test_content = "# Test Site\n\n> Test description\n\n## Test Section";

		$this->llms->save_content( $test_content );
		$this->assertEquals( $test_content, $this->llms->get_content() );
	}

	/**
	 * Test get content returns default when empty.
	 */
	public function test_get_content_returns_default_when_empty() {
		// Ensure option is empty.
		delete_option( AI_Search_Schema_Llms_Txt::OPTION_NAME );

		$content = $this->llms->get_content();

		// Should contain auto-generated content with site name.
		$this->assertStringContainsString( get_bloginfo( 'name' ), $content );
	}

	/**
	 * Test reset content removes saved content.
	 */
	public function test_reset_content() {
		// Save custom content.
		$this->llms->save_content( 'custom content that should be replaced' );

		// Reset.
		$new_content = $this->llms->reset_content();

		// Should return fresh auto-generated content.
		$this->assertStringContainsString( get_bloginfo( 'name' ), $new_content );
		$this->assertStringNotContainsString( 'custom content', $new_content );

		// Verify get_content also returns auto-generated.
		$this->assertStringContainsString( get_bloginfo( 'name' ), $this->llms->get_content() );
	}

	/**
	 * Test query vars are added.
	 */
	public function test_add_query_vars() {
		$vars   = array( 'existing_var' );
		$result = $this->llms->add_query_vars( $vars );

		$this->assertContains( 'llms_txt', $result );
		$this->assertContains( 'existing_var', $result );
	}

	/**
	 * Test filter for default content works.
	 */
	public function test_default_content_filter() {
		add_filter(
			'ai_search_schema_llms_txt_default_content',
			function ( $content ) {
				return $content . "\n\n## Custom Section from Filter";
			}
		);

		$content = $this->llms->generate_default_content();

		$this->assertStringContainsString( '## Custom Section from Filter', $content );

		remove_all_filters( 'ai_search_schema_llms_txt_default_content' );
	}

	/**
	 * Test content is properly sanitized on save.
	 */
	public function test_content_sanitization() {
		$malicious_content = "<script>alert('xss')</script># Safe Content\n\n> Description";

		$this->llms->save_content( $malicious_content );
		$saved_content = get_option( AI_Search_Schema_Llms_Txt::OPTION_NAME );

		// Script tag should be stripped.
		$this->assertStringNotContainsString( '<script>', $saved_content );
		$this->assertStringContainsString( '# Safe Content', $saved_content );
	}
}

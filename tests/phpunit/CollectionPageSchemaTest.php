<?php
/**
 * CollectionPage schema generation tests.
 */

class CollectionPageSchemaTest extends WP_UnitTestCase {

	public function test_collectionpage_schema_generation() {
		$context = [
			'url'   => 'http://example.com/category/tutorials/',
			'title' => 'Tutorials',
		];

		$has_part = [
			[
				'@type' => 'WebPage',
				'@id'   => 'http://example.com/tutorial-1/#webpage',
				'url'   => 'http://example.com/tutorial-1/',
				'name'  => 'Tutorial 1',
			],
			[
				'@type' => 'WebPage',
				'@id'   => 'http://example.com/tutorial-2/#webpage',
				'url'   => 'http://example.com/tutorial-2/',
				'name'  => 'Tutorial 2',
			],
		];

		$schema = AI_Search_Schema_Type_CollectionPage::build( $context, $has_part );

		$this->assertSame( 'CollectionPage', $schema['@type'] );
		$this->assertSame( 'http://example.com/category/tutorials/#collection', $schema['@id'] );
		$this->assertSame( 'http://example.com/category/tutorials/', $schema['url'] );
		$this->assertSame( 'Tutorials', $schema['name'] );
		$this->assertSame( 'Tutorials', $schema['about'] );
		$this->assertCount( 2, $schema['hasPart'] );
	}

	public function test_collectionpage_schema_with_empty_has_part() {
		$context = [
			'url'   => 'http://example.com/category/empty/',
			'title' => 'Empty Category',
		];

		$schema = AI_Search_Schema_Type_CollectionPage::build( $context, [] );

		$this->assertEmpty( $schema );
	}

	public function test_collectionpage_schema_with_single_part() {
		$context = [
			'url'   => 'http://example.com/tag/special/',
			'title' => 'Special Tag',
		];

		$has_part = [
			[
				'@type' => 'WebPage',
				'@id'   => 'http://example.com/special-post/#webpage',
				'url'   => 'http://example.com/special-post/',
				'name'  => 'Special Post',
			],
		];

		$schema = AI_Search_Schema_Type_CollectionPage::build( $context, $has_part );

		$this->assertSame( 'CollectionPage', $schema['@type'] );
		$this->assertCount( 1, $schema['hasPart'] );
		$this->assertSame( 'Special Post', $schema['hasPart'][0]['name'] );
	}
}

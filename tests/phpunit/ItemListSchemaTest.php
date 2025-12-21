<?php
/**
 * ItemList schema generation tests.
 */

class ItemListSchemaTest extends WP_UnitTestCase {

	public function test_itemlist_schema_generation() {
		$items = [
			[
				'position' => 1,
				'name'     => 'First Item',
				'url'      => 'http://example.com/item-1',
			],
			[
				'position' => 2,
				'name'     => 'Second Item',
				'url'      => 'http://example.com/item-2',
			],
			[
				'position' => 3,
				'name'     => 'Third Item',
				'url'      => 'http://example.com/item-3',
			],
		];

		$schema = AI_Search_Schema_Type_ItemList::build( 'http://example.com/#itemlist', $items );

		$this->assertSame( 'ItemList', $schema['@type'] );
		$this->assertSame( 'http://example.com/#itemlist', $schema['@id'] );
		$this->assertSame( 'http://schema.org/ItemListOrderAscending', $schema['itemListOrder'] );
		$this->assertCount( 3, $schema['itemListElement'] );

		// Check first item.
		$this->assertSame( 'ListItem', $schema['itemListElement'][0]['@type'] );
		$this->assertSame( 1, $schema['itemListElement'][0]['position'] );
		$this->assertSame( 'First Item', $schema['itemListElement'][0]['name'] );
		$this->assertSame( 'http://example.com/item-1', $schema['itemListElement'][0]['item'] );

		// Check second item.
		$this->assertSame( 'ListItem', $schema['itemListElement'][1]['@type'] );
		$this->assertSame( 2, $schema['itemListElement'][1]['position'] );
		$this->assertSame( 'Second Item', $schema['itemListElement'][1]['name'] );
	}

	public function test_itemlist_schema_with_empty_items() {
		$schema = AI_Search_Schema_Type_ItemList::build( 'http://example.com/#itemlist', [] );

		$this->assertSame( 'ItemList', $schema['@type'] );
		$this->assertEmpty( $schema['itemListElement'] );
	}

	public function test_itemlist_schema_with_single_item() {
		$items = [
			[
				'position' => 1,
				'name'     => 'Only Item',
				'url'      => 'http://example.com/only',
			],
		];

		$schema = AI_Search_Schema_Type_ItemList::build( 'http://example.com/#itemlist', $items );

		$this->assertCount( 1, $schema['itemListElement'] );
		$this->assertSame( 'Only Item', $schema['itemListElement'][0]['name'] );
	}
}

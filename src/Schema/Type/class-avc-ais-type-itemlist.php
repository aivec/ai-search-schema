<?php
/**
 * ItemList スキーマ生成クラス。
 */
defined( 'ABSPATH' ) || exit;
class AVC_AIS_Type_ItemList {
	public static function build( $id, array $items ) {
		$list_items = array();
		foreach ( $items as $item ) {
			$list_items[] = array(
				'@type'    => 'ListItem',
				'position' => $item['position'],
				'name'     => $item['name'],
				'item'     => $item['url'],
			);
		}

		return array(
			'@type'           => 'ItemList',
			'@id'             => $id,
			'itemListOrder'   => 'http://schema.org/ItemListOrderAscending',
			'itemListElement' => $list_items,
		);
	}
}

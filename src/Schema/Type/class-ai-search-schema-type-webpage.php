<?php
/**
 * WebPage ノードを生成するクラス。
 */
class AI_Search_Schema_Type_WebPage {
	public static function build( array $context, array $ids, $language, array $types, array $primary_image ) {
		if ( empty( $context['url'] ) ) {
			return array();
		}

		$schema = array(
			'@type'    => ( count( $types ) === 1 ) ? $types[0] : $types,
			'@id'      => $context['url'] . '#webpage',
			'url'      => $context['url'],
			'name'     => $context['title'],
			'isPartOf' => array(
				'@id' => $ids['website'],
			),
		);

		if ( $language ) {
			$schema['inLanguage'] = $language;
		}

		if ( ! empty( $primary_image ) ) {
			$schema['primaryImageOfPage'] = $primary_image;
		}

		return $schema;
	}
}

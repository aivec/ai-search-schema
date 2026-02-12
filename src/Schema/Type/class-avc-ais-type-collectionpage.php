<?php
/**
 * CollectionPage スキーマ生成クラス。
 */
defined( 'ABSPATH' ) || exit;
class AVC_AIS_Type_CollectionPage {
	public static function build( array $context, array $has_part ) {
		if ( empty( $has_part ) ) {
			return array();
		}

		return array(
			'@type'   => 'CollectionPage',
			'@id'     => $context['url'] . '#collection',
			'url'     => $context['url'],
			'name'    => $context['title'],
			'hasPart' => $has_part,
			'about'   => $context['title'],
		);
	}
}

<?php
/**
 * WebSite ノードを生成するクラス。
 */
class AVC_AIS_Type_WebSite {
	public static function build( array $options, array $ids, $site_url, $language, $publisher_id ) {
		$site_name = ! empty( $options['site_name'] ) ? $options['site_name'] : get_bloginfo( 'name' );
		$website   = array(
			'@type'      => 'WebSite',
			'@id'        => $ids['website'],
			'url'        => $site_url,
			'name'       => $site_name,
			'inLanguage' => $language,
		);

		$website['potentialAction'] = array(
			'@type'       => 'SearchAction',
			'target'      => home_url( '/?s={search_term_string}' ),
			'query-input' => 'required name=search_term_string',
		);

		if ( $publisher_id ) {
			$website['publisher'] = array(
				'@id' => $publisher_id,
			);
		}

		return $website;
	}
}

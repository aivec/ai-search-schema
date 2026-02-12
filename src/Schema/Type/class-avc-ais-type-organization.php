<?php
/**
 * Organization ノードを生成するクラス。
 */
defined( 'ABSPATH' ) || exit;
class AVC_AIS_Type_Organization {
	/**
	 * Organization ノードを構築
	 *
	 * @param array  $options
	 * @param array  $ids
	 * @param string $site_url
	 * @param array  $social_urls
	 * @return array
	 */
	public static function build( array $options, array $ids, $site_url, array $social_urls ) {
		$name = ! empty( $options['company_name'] ) ? $options['company_name'] : get_bloginfo( 'name' );
		$name = trim( $name );
		if ( '' === $name ) {
			return array();
		}

		$organization = array(
			'@type' => 'Organization',
			'@id'   => $ids['organization'],
			'name'  => $name,
			'url'   => $site_url,
		);

		if ( ! empty( $options['logo_url'] ) ) {
			$logo_ref              = array( '@id' => $ids['logo'] );
			$organization['image'] = $logo_ref;
			$organization['logo']  = $logo_ref;
		}

		if ( ! empty( $social_urls ) ) {
			$organization['sameAs'] = $social_urls;
		}

		return $organization;
	}

	/**
	 * ImageObject としてのロゴを構築
	 *
	 * @param array $options
	 * @param array $ids
	 * @return array
	 */
	public static function build_logo( array $options, array $ids ) {
		if ( empty( $options['logo_url'] ) ) {
			return array();
		}

		$logo = array(
			'@type' => 'ImageObject',
			'@id'   => $ids['logo'],
			'url'   => esc_url( $options['logo_url'] ),
		);

		$width   = 512;
		$height  = 512;
		$logo_id = ! empty( $options['logo_id'] ) ? absint( $options['logo_id'] ) : 0;
		if ( $logo_id ) {
			$logo_data = wp_get_attachment_image_src( $logo_id, 'full' );
			if ( $logo_data ) {
				$width  = (int) $logo_data[1];
				$height = (int) $logo_data[2];
			}
		}

		$logo['width']  = $width;
		$logo['height'] = $height;

		return $logo;
	}
}

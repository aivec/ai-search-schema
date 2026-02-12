<?php
/**
 * LocalBusiness ノードを生成するクラス。
 */
defined( 'ABSPATH' ) || exit;
class AVC_AIS_Type_LocalBusiness {
	/**
	 * LocalBusiness ノードを構築
	 *
	 * @param array       $options
	 * @param array       $ids
	 * @param string      $site_url
	 * @param string      $name
	 * @param array       $postal_address
	 * @param array       $area_served
	 * @param array       $geo_coordinates
	 * @param array       $opening_hours
	 * @param string      $map_url
	 * @param bool        $has_organization
	 * @return array
	 */
	public static function build(
		array $options,
		array $ids,
		$site_url,
		$name,
		array $postal_address,
		array $area_served,
		array $geo_coordinates,
		array $opening_hours,
		$map_url,
		$has_organization
	) {
		$lb_type = ! empty( $options['business_type'] ) ? $options['business_type'] : 'LocalBusiness';
		if ( ! empty( $options['lb_subtype'] ) ) {
			$lb_type = $options['lb_subtype'];
		}

		$local_business = array(
			'@type' => $lb_type,
			'@id'   => $ids['local_business'],
			'name'  => $name,
			'url'   => $site_url,
		);

		if ( $has_organization ) {
			$local_business['parentOrganization'] = array(
				'@id' => $ids['organization'],
			);
			$local_business['branchOf']           = array(
				'@id' => $ids['organization'],
			);
		}

		$store_image_url = ! empty( $options['store_image_url'] )
			? $options['store_image_url']
			: ( $options['lb_image_url'] ?? '' );
		if ( ! empty( $store_image_url ) ) {
			$local_business['image'] = array(
				'@id' => $ids['local_business_image'],
			);
		}

		if ( ! empty( $options['phone'] ) ) {
			$local_business['telephone'] = $options['phone'];
		}

		if ( ! empty( $postal_address ) ) {
			$local_business['address'] = $postal_address;
		}

		if ( ! empty( $area_served ) ) {
			$local_business['areaServed'] = $area_served;
		}

		if ( ! empty( $geo_coordinates ) ) {
			$local_business['geo'] = $geo_coordinates;
		}

		if ( ! empty( $opening_hours ) ) {
			$local_business['openingHoursSpecification'] = $opening_hours;
		}

		if ( $map_url ) {
			$local_business['hasMap'] = $map_url;
		}

		if ( ! empty( $options['price_range'] ) ) {
			$local_business['priceRange'] = $options['price_range'];
		}

		if ( ! empty( $options['payment_accepted'] ) ) {
			$accepted_payment                  = array_values(
				array_filter(
					array_map(
						'trim',
						explode( ',', $options['payment_accepted'] )
					)
				)
			);
			$local_business['paymentAccepted'] = $accepted_payment;
		}

		if ( ! empty( $options['accepts_reservations'] ) ) {
			$local_business['acceptsReservations'] = (bool) $options['accepts_reservations'];
		}

		return $local_business;
	}

	/**
	 * LocalBusiness用のImageObject
	 *
	 * @param array $options
	 * @param array $ids
	 * @return array
	 */
	public static function build_image( array $options, array $ids ) {
		$store_image_url = ! empty( $options['store_image_url'] )
			? $options['store_image_url']
			: ( $options['lb_image_url'] ?? '' );
		if ( empty( $store_image_url ) ) {
			return array();
		}

		$image = array(
			'@type' => 'ImageObject',
			'@id'   => $ids['local_business_image'],
			'url'   => esc_url( $store_image_url ),
		);

		$width    = 512;
		$height   = 512;
		$image_id = ! empty( $options['lb_image_id'] ) ? absint( $options['lb_image_id'] ) : 0;
		if ( $image_id ) {
			$image_data = wp_get_attachment_image_src( $image_id, 'full' );
			if ( $image_data ) {
				$width  = (int) $image_data[1];
				$height = (int) $image_data[2];
			}
		}

		$image['width']  = $width;
		$image['height'] = $height;

		return $image;
	}
}

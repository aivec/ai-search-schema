<?php
/**
 * Product スキーマ生成クラス（最低限のプレースホルダー）。
 */
defined( 'ABSPATH' ) || exit;
class AVC_AIS_Type_Product {
	public static function build(
		array $context,
		$language_value,
		$webpage_id,
		array $image_object = array(),
		$adapter = null
	) {
		if ( empty( $context['url'] ) ) {
			return array();
		}

		$post_id = $context['post_id'] ?? null;

		if ( $adapter && is_object( $adapter ) && method_exists( $adapter, 'build_schema' ) ) {
			$schema = $adapter->build_schema( $post_id, $language_value, $webpage_id, $image_object );
			if ( ! empty( $schema ) ) {
				return $schema;
			}
		}

		$schema    = array(
			'@type'            => 'Product',
			'name'             => $context['title'],
			'url'              => $context['url'],
			'mainEntityOfPage' => array(
				'@id' => $webpage_id,
			),
		);
		$meta_data = $post_id ? get_post_meta( $post_id, '_ais_product', true ) : array();

		if ( $language_value ) {
			$schema['inLanguage'] = $language_value;
		}

		if ( ! empty( $image_object ) ) {
			$schema['image'] = $image_object;
		}

		$schema['sku'] = self::maybe_get_meta_value(
			$meta_data,
			array( 'sku', '_sku', 'product_sku' )
		);

		$schema['brand'] = self::maybe_get_meta_value(
			$meta_data,
			array( 'brand', '_brand', 'product_brand' )
		);

		// GTIN variants.
		foreach ( array( 'gtin8', 'gtin12', 'gtin13', 'gtin14' ) as $gtin_key ) {
			$value = self::maybe_get_meta_value( $meta_data, array( $gtin_key ) );
			if ( $value ) {
				$schema[ $gtin_key ] = $value;
			}
		}

		$offers = self::build_offers( $post_id, $meta_data );
		if ( ! empty( $offers ) ) {
			$schema['offers'] = $offers;
		}

		$aggregate_rating = self::build_aggregate_rating( $meta_data );
		if ( ! empty( $aggregate_rating ) ) {
			$schema['aggregateRating'] = $aggregate_rating;
		}

		$reviews = self::build_reviews( $meta_data );
		if ( ! empty( $reviews ) ) {
			$schema['review'] = $reviews;
		}

		$schema = array_filter(
			$schema,
			function ( $value ) {
				return null !== $value && '' !== $value && array() !== $value;
			}
		);

		return $schema;
	}

	private static function build_offers( $post_id, array $meta_data ) {
		$price    = self::maybe_get_meta_value(
			$meta_data,
			array( 'price', '_price', '_regular_price', 'product_price' )
		);
		$currency = self::maybe_get_meta_value( $meta_data, array( 'price_currency', 'currency' ) );
		if ( empty( $currency ) && function_exists( 'get_woocommerce_currency' ) ) {
			$currency = get_woocommerce_currency();
		}

		if ( '' === (string) $price || '' === (string) $currency ) {
			return array();
		}

		$availability = self::maybe_get_meta_value(
			$meta_data,
			array( 'availability', '_stock_status' )
		);
		$availability = self::normalize_availability( $availability );

		$offers = array(
			'@type'         => 'Offer',
			'price'         => (float) $price,
			'priceCurrency' => $currency,
		);

		if ( $availability ) {
			$offers['availability'] = $availability;
		}

		if ( $post_id ) {
			$offers['url'] = get_permalink( $post_id );
		}

		return $offers;
	}

	private static function normalize_availability( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$map = array(
			'instock'      => 'https://schema.org/InStock',
			'in-stock'     => 'https://schema.org/InStock',
			'onbackorder'  => 'https://schema.org/PreOrder',
			'backorder'    => 'https://schema.org/PreOrder',
			'outofstock'   => 'https://schema.org/OutOfStock',
			'out-of-stock' => 'https://schema.org/OutOfStock',
		);

		$key = strtolower( $value );
		return $map[ $key ] ?? '';
	}

	private static function build_aggregate_rating( array $meta_data ) {
		$rating_value = self::maybe_get_meta_value( $meta_data, array( 'rating_value', 'rating' ) );
		$rating_count = self::maybe_get_meta_value( $meta_data, array( 'rating_count', 'review_count' ) );

		if ( '' === (string) $rating_value || '' === (string) $rating_count ) {
			return array();
		}

		return array(
			'@type'       => 'AggregateRating',
			'ratingValue' => (float) $rating_value,
			'reviewCount' => (int) $rating_count,
		);
	}

	private static function build_reviews( array $meta_data ) {
		if ( empty( $meta_data['reviews'] ) || ! is_array( $meta_data['reviews'] ) ) {
			return array();
		}

		$reviews = array();
		foreach ( $meta_data['reviews'] as $review ) {
			$author = $review['author'] ?? '';
			$body   = $review['body'] ?? '';
			$rating = isset( $review['rating'] ) ? (float) $review['rating'] : null;

			if ( '' === $author || '' === $body || null === $rating ) {
				continue;
			}

			$reviews[] = array(
				'@type'        => 'Review',
				'author'       => array(
					'@type' => 'Person',
					'name'  => $author,
				),
				'reviewBody'   => $body,
				'reviewRating' => array(
					'@type'       => 'Rating',
					'ratingValue' => $rating,
				),
			);
		}

		return $reviews;
	}

	private static function maybe_get_meta_value( $meta_data, array $keys ) {
		foreach ( $keys as $key ) {
			if ( is_array( $meta_data ) && array_key_exists( $key, $meta_data ) ) {
				$value = $meta_data[ $key ];
			} else {
				$value = get_post_meta( get_the_ID(), $key, true );
			}

			if ( '' !== $value && null !== $value ) {
				return is_string( $value ) ? trim( $value ) : $value;
			}
		}

		return '';
	}
}

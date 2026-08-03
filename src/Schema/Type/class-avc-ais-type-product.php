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

		$schema = array(
			'@type'            => 'Product',
			'name'             => $context['title'],
			'url'              => $context['url'],
			'mainEntityOfPage' => array(
				'@id' => $webpage_id,
			),
		);
		// get_post_meta() は第3引数 true でメタキーが存在しない場合に array() ではなく
		// 空文字列を返すため、後続の配列前提の処理へ渡す前に必ず正規化する。
		$meta_data = self::normalize_meta_data(
			$post_id ? get_post_meta( $post_id, '_ais_product', true ) : array()
		);

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

	/**
	 * 投稿メタの値を必ず配列へ正規化する。
	 *
	 * get_post_meta( $id, $key, true ) はメタが存在しないとき空文字列を返す。
	 * 加えて、WordPress 外から直接 DB へ書き込まれた場合はシリアライズ済み文字列や
	 * JSON 文字列のまま残ることがあるため、復元できるものは救済したうえで、
	 * どうしても配列化できない値は空配列へフォールバックさせる。
	 *
	 * @param mixed $value 正規化対象の値.
	 * @return array 正規化済みの配列（復元不能なら空配列）.
	 */
	private static function normalize_meta_data( $value ) {
		if ( is_array( $value ) ) {
			return $value;
		}

		if ( is_object( $value ) ) {
			return get_object_vars( $value );
		}

		if ( ! is_string( $value ) ) {
			return array();
		}

		$value = trim( $value );
		if ( '' === $value ) {
			return array();
		}

		if ( is_serialized( $value ) ) {
			// PHP オブジェクトインジェクション対策として allowed_classes を false に固定する。
			// これによりシリアライズ文字列に仕込まれたオブジェクトは復元されず、
			// 他プラグインの脆弱性を RCE に繋げるガジェットとして悪用されるのを防ぐ。
			// 復元対象は配列のみとし、それ以外は全て空配列へフォールバックさせる。
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
			$unserialized = unserialize( $value, array( 'allowed_classes' => false ) );
			return is_array( $unserialized ) ? $unserialized : array();
		}

		$decoded = json_decode( $value, true );
		if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
			return $decoded;
		}

		return array();
	}

	/**
	 * Offer スキーマを構築する。
	 *
	 * @param int|null $post_id   投稿ID.
	 * @param mixed    $meta_data 商品メタ（配列以外が渡っても内部で正規化する）.
	 * @return array Offer スキーマ。生成できない場合は空配列.
	 */
	private static function build_offers( $post_id, $meta_data ) {
		$meta_data = self::normalize_meta_data( $meta_data );

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

	/**
	 * AggregateRating スキーマを構築する。
	 *
	 * @param mixed $meta_data 商品メタ（配列以外が渡っても内部で正規化する）.
	 * @return array AggregateRating スキーマ。生成できない場合は空配列.
	 */
	private static function build_aggregate_rating( $meta_data ) {
		$meta_data = self::normalize_meta_data( $meta_data );

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

	/**
	 * Review スキーマの配列を構築する。
	 *
	 * @param mixed $meta_data 商品メタ（配列以外が渡っても内部で正規化する）.
	 * @return array Review スキーマの配列。生成できない場合は空配列.
	 */
	private static function build_reviews( $meta_data ) {
		$meta_data = self::normalize_meta_data( $meta_data );

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

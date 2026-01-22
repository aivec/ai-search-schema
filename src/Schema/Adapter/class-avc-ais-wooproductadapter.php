<?php
/**
 * WooCommerce 連携で Product スキーマを生成するアダプタ。
 */
class AVC_AIS_WooProductAdapter {
	/**
	 * WooCommerce が利用可能か。
	 *
	 * @return bool
	 */
	public function is_available() {
		return function_exists( 'wc_get_product' ) && class_exists( 'WC_Product' );
	}

	/**
	 * WC_Product から Product スキーマを構築する。
	 *
	 * @param int         $post_id      投稿ID.
	 * @param string|null $language     言語.
	 * @param string      $webpage_id   WebPage ID.
	 * @param array       $image_object フィーチャー画像.
	 * @return array
	 */
	public function build_schema( $post_id, $language, $webpage_id, array $image_object = array() ) {
		if ( ! $this->is_available() ) {
			return array();
		}

		$product = wc_get_product( $post_id );
		if ( ! $product ) {
			return array();
		}

		$schema = array(
			'@type'            => 'Product',
			'name'             => $product->get_name(),
			'url'              => get_permalink( $post_id ),
			'sku'              => $product->get_sku(),
			'mainEntityOfPage' => array(
				'@id' => $webpage_id,
			),
		);

		if ( $language ) {
			$schema['inLanguage'] = $language;
		}

		$images = $this->collect_images( $product, $image_object );
		if ( ! empty( $images ) ) {
			$schema['image'] = $images;
		}

		$brand = $this->get_brand( $product );
		if ( $brand ) {
			$schema['brand'] = array(
				'@type' => 'Brand',
				'name'  => $brand,
			);
		}

		$offers = $this->build_offers( $product, $post_id );
		if ( ! empty( $offers ) ) {
			$schema['offers'] = $offers;
		}

		$aggregate = $this->build_aggregate_rating( $product );
		if ( ! empty( $aggregate ) ) {
			$schema['aggregateRating'] = $aggregate;
		}

		return array_filter(
			$schema,
			function ( $value ) {
				return null !== $value && '' !== $value && array() !== $value;
			}
		);
	}

	private function collect_images( WC_Product $product, array $featured_image ) {
		$images = array();

		if ( ! empty( $featured_image['url'] ) ) {
			$images[] = $featured_image['url'];
		}

		$gallery_ids = $product->get_gallery_image_ids();
		$main_id     = $product->get_image_id();

		if ( $main_id ) {
			$main_url = wp_get_attachment_url( $main_id );
			if ( $main_url ) {
				$images[] = $main_url;
			}
		}

		foreach ( (array) $gallery_ids as $img_id ) {
			$url = wp_get_attachment_url( $img_id );
			if ( $url ) {
				$images[] = $url;
			}
		}

		$images = array_values( array_unique( $images ) );
		if ( empty( $images ) && ! empty( $featured_image['url'] ) ) {
			$images[] = $featured_image['url'];
		}

		return $images;
	}

	private function get_brand( WC_Product $product ) {
		// 一般的に brand 属性があれば取得。Woo 標準では存在しないため任意。
		$brand = $product->get_attribute( 'pa_brand' );
		if ( $brand ) {
			return wp_strip_all_tags( $brand );
		}

		return '';
	}

	private function build_offers( WC_Product $product, $post_id ) {
		$price    = $product->get_price();
		$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '';

		if ( '' === (string) $price || '' === (string) $currency ) {
			return array();
		}

		$availability = $this->normalize_availability( $product->get_stock_status() );
		$offers       = array(
			'@type'         => 'Offer',
			'price'         => (float) $price,
			'priceCurrency' => $currency,
			'url'           => get_permalink( $post_id ),
		);

		if ( $availability ) {
			$offers['availability'] = $availability;
		}

		$sale_price = $product->get_sale_price();
		if ( $sale_price ) {
			$offers['price'] = (float) $sale_price;
		}

		return $offers;
	}

	private function normalize_availability( $status ) {
		$map = array(
			'instock'     => 'https://schema.org/InStock',
			'onbackorder' => 'https://schema.org/PreOrder',
			'outofstock'  => 'https://schema.org/OutOfStock',
		);

		$key = strtolower( (string) $status );
		return $map[ $key ] ?? '';
	}

	private function build_aggregate_rating( WC_Product $product ) {
		$avg   = $product->get_average_rating();
		$count = $product->get_review_count();

		if ( ! $avg || ! $count ) {
			return array();
		}

		return array(
			'@type'       => 'AggregateRating',
			'ratingValue' => (float) $avg,
			'reviewCount' => (int) $count,
		);
	}
}

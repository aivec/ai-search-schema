<?php
/**
 * Product schema generation with WooCommerce adapter stubs.
 */

require_once __DIR__ . '/bootstrap.php';

// Stub WooCommerce classes/functions if not present.
if ( ! class_exists( 'WC_Product' ) ) {
	class WC_Product {
		private $id;
		public function __construct( $id ) { $this->id = $id; }
		public function get_name() { return 'Woo Product'; }
		public function get_sku() { return 'SKU-123'; }
		public function get_price() { return '50'; }
		public function get_sale_price() { return '40'; }
		public function get_stock_status() { return 'instock'; }
		public function get_gallery_image_ids() { return array( 501 ); }
		public function get_image_id() { return 500; }
		public function get_attribute( $key ) {
			if ( 'pa_brand' === $key ) {
				return 'WooBrand';
			}
			return '';
		}
		public function get_average_rating() { return 4.5; }
		public function get_review_count() { return 3; }
	}
}

if ( ! function_exists( 'wc_get_product' ) ) {
	function wc_get_product( $post_id ) {
		return new WC_Product( $post_id );
	}
}

if ( ! function_exists( 'get_woocommerce_currency' ) ) {
	function get_woocommerce_currency() {
		return 'USD';
	}
}

class AVC_AIS_ProductSchema_Test extends WP_UnitTestCase {
	private int $product_id;

	protected function setUp(): void {
		parent::setUp();

		register_post_type(
			'product',
			array(
				'public' => true,
				'label'  => 'Products',
			)
		);

		$this->product_id = self::factory()->post->create(
			array(
				'post_title'   => 'Woo Product',
				'post_content' => '<p>Product desc</p>',
				'post_status'  => 'publish',
				'post_type'    => 'product',
			)
		);

		update_post_meta(
			$this->product_id,
			'_avc_ais_meta',
			array(
				'page_type' => 'Product',
			)
		);
		update_post_meta( $this->product_id, '_thumbnail_id', 500 );

		add_filter(
			'wp_get_attachment_image_src',
			static function ( $image, $attachment_id, $size ) {
				if ( 500 === (int) $attachment_id ) {
					return array( 'http://example.com/featured-product.jpg', 800, 600, true );
				}
				if ( 501 === (int) $attachment_id ) {
					return array( 'http://example.com/gallery1.jpg', 800, 600, true );
				}
				return $image;
			},
			10,
			3
		);

		update_option(
			'avc_ais_options',
			array(
				'company_name'        => 'Shop Co',
				'logo_url'            => 'http://example.com/logo.png',
				'site_url'            => 'http://example.com',
				'languages'           => array( 'en' ),
				'avc_ais_priority' => 'ais',
			)
		);

		$this->go_to( get_permalink( $this->product_id ) );
	}

	public function test_product_schema_contains_woocommerce_data() {
		$resolver = new AVC_AIS_ContentResolver();
		$builder  = new AVC_AIS_GraphBuilder( $resolver );
		$schema   = $builder->build( get_option( 'avc_ais_options' ) );

		$product = $this->find_by_type( $schema['@graph'], 'Product' );
		$this->assertNotEmpty( $product );
		$this->assertSame( 'Woo Product', $product['name'] );
		$this->assertSame( 'SKU-123', $product['sku'] );
		$this->assertEquals( 3, $product['aggregateRating']['reviewCount'] );
		$this->assertSame( 'WooBrand', $product['brand']['name'] );
		$this->assertContains( 'http://example.com/featured-product.jpg', (array) $product['image'] );

		$this->assertSame( 40.0, $product['offers']['price'] ); // sale price
		$this->assertSame( 'USD', $product['offers']['priceCurrency'] );
		$this->assertSame( 'https://schema.org/InStock', $product['offers']['availability'] );
	}

	private function find_by_type( array $graph, $type ) {
		foreach ( $graph as $node ) {
			if ( isset( $node['@type'] ) && $node['@type'] === $type ) {
				return $node;
			}
		}
		return array();
	}
}

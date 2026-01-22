<?php
/**
 * Enhanced Validator tests for AEO self-check.
 */

require_once __DIR__ . '/bootstrap.php';

class AVC_AIS_ValidatorEnhanced_Test extends WP_UnitTestCase {
	public function test_product_requires_brand_and_offer_fields() {
		$validator = new AVC_AIS_Validator();
		$result    = $validator->validate(
			array(
				'@context' => 'https://schema.org',
				'@graph'   => array(
					array(
						'@type' => 'Product',
						'image' => 'http://example.com/image.jpg',
						'offers' => array(
							'price'         => 10,
							'priceCurrency' => 'USD',
							// availability missing to trigger warning.
						),
					),
				),
			)
		);

		$this->assertFalse( $result['is_valid'] );
		$this->assertContains( 'Product: brand がありません', $result['errors'] );
		$this->assertContains( 'Product: offers.availability がありません', $result['errors'] );
	}

	public function test_localbusiness_requires_phone_and_address() {
		$validator = new AVC_AIS_Validator();
		$result    = $validator->validate(
			array(
				'@graph' => array(
					array(
						'@type'   => 'LocalBusiness',
						'name'    => 'Store',
						'address' => array(
							'@type'          => 'PostalAddress',
							'addressCountry' => 'JP',
						),
					),
				),
			)
		);

		$this->assertFalse( $result['is_valid'] );
		$this->assertContains( 'LocalBusiness: address.streetAddress がありません', $result['errors'] );
		$this->assertContains( 'LocalBusiness: telephone がありません', $result['errors'] );
	}
}

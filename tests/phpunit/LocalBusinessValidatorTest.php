<?php
/**
 * LocalBusiness validator coverage.
 */

require_once __DIR__ . '/bootstrap.php';

class AI_Search_Schema_LocalBusinessValidator_Test extends WP_UnitTestCase {
	public function test_local_business_valid_when_required_fields_present() {
		$validator = new AI_Search_Schema_Validator();
		$result    = $validator->validate(
			array(
				'@graph' => array(
					array(
						'@type' => 'LocalBusiness',
						'name'  => 'Store',
						'telephone' => '0123-456-789',
						'address' => array(
							'@type'          => 'PostalAddress',
							'streetAddress'  => '1-2-3',
							'addressLocality'=> 'Shibuya',
							'addressRegion'  => 'Tokyo',
							'postalCode'     => '150-0000',
							'addressCountry' => 'JP',
						),
					),
				),
			)
		);

		$this->assertTrue( $result['is_valid'] );
		$this->assertEmpty( $result['errors'] );
	}
}

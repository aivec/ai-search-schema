<?php
/**
 * GraphBuilder tests.
 */

require_once __DIR__ . '/bootstrap.php';

class AVC_AIS_GraphBuilder_Test extends WP_UnitTestCase {
	public function test_build_includes_basic_nodes() {
		$options = array(
			'company_name' => 'Graph Co',
			'site_url'     => 'http://example.com',
			'languages'    => array( 'en' ),
			'entity_type'  => 'Organization',
		);

		$resolver = new AVC_AIS_ContentResolver();
		$builder  = new AVC_AIS_GraphBuilder( $resolver );
		$schema   = $builder->build( $options );

		$this->assertArrayHasKey( '@graph', $schema );
		$this->assertNotEmpty( $this->find_by_type( $schema['@graph'], 'Organization' ) );
		$this->assertNotEmpty( $this->find_by_type( $schema['@graph'], 'WebSite' ) );
		$this->assertNotEmpty( $this->find_by_type( $schema['@graph'], 'WebPage' ) );
	}

	public function test_local_business_is_included_when_required_fields_exist() {
		$options = array(
			'company_name' => 'LB Co',
			'site_url'     => 'http://example.com',
			'languages'    => array( 'en' ),
			'phone'        => '000-111-2222',
			'address'      => array(
				'street_address' => '1-2-3',
				'locality'       => 'Shibuya',
				'region'         => 'Tokyo',
				'country'        => 'JP',
				'postal_code'    => '150-0000',
			),
		);

		$resolver = new AVC_AIS_ContentResolver();
		$builder  = new AVC_AIS_GraphBuilder( $resolver );
		$schema   = $builder->build( $options );

		$this->assertNotEmpty( $this->find_by_type( $schema['@graph'], 'LocalBusiness' ) );
	}

	private function find_by_type( array $graph, $type ) {
		foreach ( $graph as $node ) {
			$types = isset( $node['@type'] ) ? (array) $node['@type'] : array();
			if ( in_array( $type, $types, true ) ) {
				return $node;
			}
		}
		return array();
	}
}

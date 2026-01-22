<?php
class AVC_AIS_Validator {
	/**
	 * スキーマを検証し、AEO向けの必須項目を自己診断する。
	 *
	 * @param array $schema 検証対象スキーマ.
	 * @return array{is_valid:bool,errors:array,schema:array}
	 */
	public function validate( array $schema ) {
		$errors   = array();
		$warnings = array();

		if ( ! is_array( $schema ) ) {
			return array(
				'is_valid' => false,
				'errors'   => array( 'スキーマが配列ではありません。' ),
				'schema'   => array(),
				'warnings' => $warnings,
			);
		}

		$graph       = $this->collect_graph_nodes( $schema );
		$nodes_by_id = $this->map_by_id( $graph );

		$local_business_validation = $this->validate_local_business( $graph );
		$errors                    = array_merge(
			$errors,
			$this->validate_website( $graph, $nodes_by_id ),
			$this->validate_organization( $graph ),
			$local_business_validation['errors'],
			$this->validate_article( $graph ),
			$this->validate_product( $graph )
		);
		$warnings                  = array_merge( $warnings, $local_business_validation['warnings'] );

		$is_valid = empty( $errors );

		return array(
			'is_valid' => $is_valid,
			'errors'   => $errors,
			'schema'   => $schema,
			'warnings' => $warnings,
		);
	}

	/**
	 * @param array $schema
	 * @return array
	 */
	private function collect_graph_nodes( array $schema ) {
		if ( isset( $schema['@graph'] ) && is_array( $schema['@graph'] ) ) {
			return $schema['@graph'];
		}

		return is_array( $schema ) ? array( $schema ) : array();
	}

	/**
	 * @param array $graph
	 * @return array<string,array>
	 */
	private function map_by_id( array $graph ) {
		$mapped = array();
		foreach ( $graph as $node ) {
			if ( isset( $node['@id'] ) ) {
				$mapped[ $node['@id'] ] = $node;
			}
		}
		return $mapped;
	}

	private function get_nodes_by_type( array $graph, $type ) {
		$result = array();
		foreach ( $graph as $node ) {
			if ( empty( $node['@type'] ) ) {
				continue;
			}
			$types = is_array( $node['@type'] ) ? $node['@type'] : array( $node['@type'] );
			if ( in_array( $type, $types, true ) ) {
				$result[] = $node;
			}
		}
		return $result;
	}

	private function validate_website( array $graph, array $nodes_by_id ) {
		$errors   = array();
		$websites = $this->get_nodes_by_type( $graph, 'WebSite' );

		foreach ( $websites as $site ) {
			if ( empty( $site['url'] ) ) {
				$errors[] = 'WebSite: url がありません';
			}
			if ( empty( $site['inLanguage'] ) ) {
				$errors[] = 'WebSite: inLanguage がありません';
			}
			if ( empty( $site['publisher'] ) ) {
				$errors[] = 'WebSite: publisher が設定されていません';
			} else {
				$publisher = $this->resolve_publisher_node( $site['publisher'], $nodes_by_id );
				if ( empty( $publisher['name'] ) ) {
					$errors[] = 'WebSite: publisher.name がありません';
				}
			}
		}

		return $errors;
	}

	private function resolve_publisher_node( $publisher_ref, array $nodes_by_id ) {
		if ( isset( $publisher_ref['@id'] ) && isset( $nodes_by_id[ $publisher_ref['@id'] ] ) ) {
			return $nodes_by_id[ $publisher_ref['@id'] ];
		}
		if ( is_array( $publisher_ref ) ) {
			return $publisher_ref;
		}
		return array();
	}

	private function validate_organization( array $graph ) {
		$errors        = array();
		$organizations = $this->get_nodes_by_type( $graph, 'Organization' );

		foreach ( $organizations as $org ) {
			if ( empty( $org['name'] ) ) {
				$errors[] = 'Organization: name がありません';
			}
			if ( empty( $org['url'] ) ) {
				$errors[] = 'Organization: url がありません';
			}
			if ( empty( $org['logo'] ) && empty( $org['image'] ) ) {
				$errors[] = 'Organization: logo がありません';
			}
		}

		return $errors;
	}

	private function validate_local_business( array $graph ) {
		$errors   = array();
		$warnings = array();
		$lbs      = $this->get_nodes_by_type( $graph, 'LocalBusiness' );

		foreach ( $lbs as $lb ) {
			if ( empty( $lb['name'] ) ) {
				$errors[] = 'LocalBusiness: name がありません';
			}
			$missing_street   = empty( $lb['address'] ) || empty( $lb['address']['streetAddress'] );
			$missing_locality = empty( $lb['address']['addressLocality'] );
			if ( $missing_street ) {
				$errors[]   = 'LocalBusiness: address.streetAddress がありません';
				$warnings[] = 'LocalBusiness: address.streetAddress がありません';
			}
			if ( $missing_locality ) {
				$errors[]   = 'LocalBusiness: address.addressLocality がありません';
				$warnings[] = 'LocalBusiness: address.addressLocality がありません';
			}
			if ( empty( $lb['address']['addressRegion'] ) ) {
				$errors[] = 'LocalBusiness: address.addressRegion がありません';
			}
			if ( empty( $lb['address']['postalCode'] ) ) {
				$errors[] = 'LocalBusiness: address.postalCode がありません';
			}
			if ( empty( $lb['address']['addressCountry'] ) ) {
				$errors[] = 'LocalBusiness: address.addressCountry がありません';
			}
			if ( empty( $lb['telephone'] ) ) {
				$errors[] = 'LocalBusiness: telephone がありません';
			}

			if ( empty( $lb['geo'] ) ) {
				$warnings[] = 'LocalBusiness: geo がありません';
			}

			if ( empty( $lb['openingHoursSpecification'] ) ) {
				$warnings[] = 'LocalBusiness: openingHoursSpecification がありません';
			}

			if ( ! empty( $lb['areaServed'] ) && ! empty( $lb['address']['addressCountry'] ) ) {
				$country_code = strtoupper( (string) $lb['address']['addressCountry'] );
				$matches      = array_filter(
					(array) $lb['areaServed'],
					function ( $area ) use ( $country_code ) {
						return isset( $area['identifier'] )
							&& strtoupper( (string) $area['identifier'] ) === $country_code;
					}
				);
				if ( empty( $matches ) ) {
					$errors[] = 'LocalBusiness: areaServed が国コードと一致しません';
				}
			}

			$auto_complete_enabled = ! empty( $lb['geo'] ) || ! empty( $lb['hasMap'] );
			if ( $auto_complete_enabled && ( $missing_street || $missing_locality ) ) {
				$warnings[] = 'LocalBusiness: 住所自動補完が有効ですが市区町村または丁目・番地が空です';
			}
		}

		return array(
			'errors'   => $errors,
			'warnings' => $warnings,
		);
	}

	private function validate_article( array $graph ) {
		$errors   = array();
		$articles = array_merge(
			$this->get_nodes_by_type( $graph, 'Article' ),
			$this->get_nodes_by_type( $graph, 'NewsArticle' ),
			$this->get_nodes_by_type( $graph, 'BlogPosting' )
		);

		foreach ( $articles as $article ) {
			if ( empty( $article['headline'] ) ) {
				$errors[] = 'Article: headline がありません';
			}
			if ( empty( $article['datePublished'] ) ) {
				$errors[] = 'Article: datePublished がありません';
			}
			if ( empty( $article['dateModified'] ) ) {
				$errors[] = 'Article: dateModified がありません';
			}
			if ( empty( $article['author'] ) ) {
				$errors[] = 'Article: author がありません';
			}
			if ( empty( $article['mainEntityOfPage'] ) ) {
				$errors[] = 'Article: mainEntityOfPage がありません';
			}
			if ( empty( $article['publisher'] ) ) {
				$errors[] = 'Article: publisher がありません';
			}
			if ( empty( $article['image'] ) ) {
				$errors[] = 'Article: image がありません';
			}
		}

		return $errors;
	}

	private function validate_product( array $graph ) {
		$errors   = array();
		$products = $this->get_nodes_by_type( $graph, 'Product' );

		foreach ( $products as $product ) {
			if ( empty( $product['brand'] ) ) {
				$errors[] = 'Product: brand がありません';
			}
			if ( empty( $product['image'] ) ) {
				$errors[] = 'Product: image がありません';
			}
			if ( empty( $product['offers'] ) || ! is_array( $product['offers'] ) ) {
				$errors[] = 'Product: offers がありません';
				continue;
			}
			$offer = $product['offers'];
			if ( empty( $offer['price'] ) ) {
				$errors[] = 'Product: offers.price がありません';
			}
			if ( empty( $offer['priceCurrency'] ) ) {
				$errors[] = 'Product: offers.priceCurrency がありません';
			}
			if ( empty( $offer['availability'] ) ) {
				$errors[] = 'Product: offers.availability がありません';
			}
		}

		return $errors;
	}
}

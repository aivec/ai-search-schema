<?php
/**
 * グラフ生成と共通ヘルパーを担当するクラス。
 */
class AI_Search_Schema_GraphBuilder {
	/**
	 * @var AI_Search_Schema_ContentResolver
	 */
	private $resolver;

	/**
	 * @param AI_Search_Schema_ContentResolver $resolver
	 */
	public function __construct( AI_Search_Schema_ContentResolver $resolver ) {
		$this->resolver = $resolver;
	}

	/**
	 * @var bool
	 */
	private $local_business_suppressed = false;

	/**
	 * @var array
	 */
	private $local_business_missing_fields = array();

	/**
	 * @return bool
	 */
	public function was_local_business_suppressed() {
		return $this->local_business_suppressed;
	}

	/**
	 * @return array
	 */
	public function get_local_business_missing_fields() {
		return $this->local_business_missing_fields;
	}

	/**
	 * スキーマ全体を生成
	 *
	 * @param array $options
	 * @return array
	 */
	public function build( array $options ) {
		$this->local_business_suppressed     = false;
		$this->local_business_missing_fields = array();

		$site_url   = $this->resolver->normalize_site_url( $options );
		$ids        = $this->build_graph_ids( $site_url );
		$lang       = $this->resolve_primary_language( $options );
		$common     = $this->build_common_graph( $options, $site_url, $ids, $lang );
		$publisher  = $common['publisher_id'];
		$page_graph = $this->build_page_graph( $options, $ids, $publisher, $lang );

		$graph = array_merge( $common['graph'], $page_graph );

		if ( empty( $graph ) ) {
			return array();
		}

		return array(
			'@context' => 'https://schema.org',
			'@graph'   => $this->remove_null_values( $graph ),
		);
	}

	/**
	 * @param array  $options
	 * @param string $site_url
	 * @param array  $ids
	 * @param string $language
	 * @return array{graph:array,publisher_id:?string}
	 */
	private function build_common_graph( array $options, $site_url, array $ids, $language ) {
		$graph = array();

		$entity_type = ! empty( $options['entity_type'] ) ? $options['entity_type'] : 'Organization';
		$show_org    = ( 'Organization' === $entity_type );

		$social_urls = $this->build_social_urls( $options['social_links'] ?? array() );

		$organization = $show_org
			? AI_Search_Schema_Type_Organization::build( $options, $ids, $site_url, $social_urls )
			: array();

		$local_business     = array();
		$skip_on_incomplete = ! empty( $options['skip_local_business_if_incomplete'] );
		$has_required_lb    = $this->has_required_local_business_data( $options );
		if ( $skip_on_incomplete && ! $has_required_lb ) {
			$this->local_business_suppressed = true;
			$local_business                  = array();
		} else {
			$local_business = AI_Search_Schema_Type_LocalBusiness::build(
				$options,
				$ids,
				$site_url,
				$this->build_local_business_name( $options ),
				$this->build_postal_address( $options['address'] ?? array(), $options ),
				$this->build_area_served( $options['address'] ?? array() ),
				$this->build_geo_coordinates( $options['geo'] ?? array() ),
				$this->get_opening_hours( $options ),
				$this->build_map_url( $options, $this->build_geo_coordinates( $options['geo'] ?? array() ) ),
				! empty( $organization )
			);
		}

		if ( ! empty( $organization ) ) {
			$graph[] = $organization;
		}

		$logo = AI_Search_Schema_Type_Organization::build_logo( $options, $ids );
		if ( ! empty( $logo ) ) {
			$graph[] = $logo;
		}

		if ( ! empty( $local_business ) ) {
			$graph[] = $local_business;

			$lb_image = AI_Search_Schema_Type_LocalBusiness::build_image( $options, $ids );
			if ( ! empty( $lb_image ) ) {
				$graph[] = $lb_image;
			}
		}

		$publisher_id = null;
		if ( ! empty( $organization ) ) {
			$publisher_id = $ids['organization'];
		} elseif ( ! empty( $local_business ) ) {
			$publisher_id = $ids['local_business'];
		}

		$website = AI_Search_Schema_Type_WebSite::build( $options, $ids, $site_url, $language, $publisher_id );
		if ( ! empty( $website ) ) {
			$graph[] = $website;
		}

		return array(
			'graph'        => array_values( array_filter( $graph ) ),
			'publisher_id' => $publisher_id,
		);
	}

	/**
	 * ページ固有のグラフを構築
	 *
	 * @param array       $options
	 * @param array       $ids
	 * @param string|null $publisher_id
	 * @param string      $language
	 * @return array
	 */
	private function build_page_graph( array $options, array $ids, $publisher_id, $language ) {
		if ( is_404() ) {
			return array();
		}

		$context  = $this->resolver->resolve_page_context();
		$web_page = AI_Search_Schema_Type_WebPage::build(
			$context,
			$ids,
			$language,
			$this->resolver->determine_webpage_types( $context ),
			$this->get_featured_image_object( $context['post_id'] ?? 0 )
		);

		if ( empty( $web_page ) ) {
			return array();
		}

		$graph = array( $web_page );

		$breadcrumb = $this->build_breadcrumb_schema( $options, $context );
		if ( ! empty( $breadcrumb ) ) {
			$graph[] = $breadcrumb;
		}

		$item_list = $this->build_archive_item_list( $context );
		if ( ! empty( $item_list ) ) {
			$graph[] = $item_list;
		}

		$schema_type = $this->determine_content_schema_type( $options, $context );
		$content     = $this->build_content_schema(
			$schema_type,
			$context,
			$language,
			$publisher_id,
			$web_page['@id'],
			$options
		);

		if ( ! empty( $content ) ) {
			$graph[] = $content;
		}

		return $graph;
	}

	/**
	 * コンテンツ固有スキーマ
	 */
	private function build_content_schema(
		$schema_type,
		array $context,
		$language,
		$publisher_id,
		$webpage_id,
		array $options
	) {
		if ( 'WebPage' === $schema_type ) {
			return array();
		}

		$article_like = array( 'Article', 'NewsArticle', 'BlogPosting' );
		if ( in_array( $schema_type, $article_like, true ) ) {
			return AI_Search_Schema_Type_Article::build(
				$context['post_id'] ?? null,
				$options,
				$language,
				$publisher_id,
				$schema_type,
				$webpage_id,
				$this->get_featured_image_object( $context['post_id'] ?? 0 )
			);
		}

		if ( 'FAQPage' === $schema_type ) {
			return AI_Search_Schema_Type_FAQPage::build( $context, $language, $webpage_id, $options );
		}

		if ( 'QAPage' === $schema_type ) {
			return AI_Search_Schema_Type_QAPage::build( $context, $language, $webpage_id );
		}

		if ( 'Product' === $schema_type ) {
			$adapter = class_exists( 'AI_Search_Schema_WooProductAdapter' )
				? new AI_Search_Schema_WooProductAdapter()
				: null;
			return AI_Search_Schema_Type_Product::build(
				$context,
				$language,
				$webpage_id,
				$this->get_featured_image_object( $context['post_id'] ?? 0 ),
				$adapter
			);
		}

		return array(
			'@type'            => $schema_type,
			'name'             => $context['title'],
			'url'              => $context['url'],
			'mainEntityOfPage' => array(
				'@id' => $webpage_id,
			),
		);
	}

	/**
	 * IDセットを生成
	 *
	 * @param string $site_url
	 * @return array
	 */
	public function build_graph_ids( $site_url ) {
		return array(
			'organization'         => $site_url . '#org',
			'logo'                 => $site_url . '#logo',
			'website'              => $site_url . '#website',
			'local_business'       => $site_url . '#lb-main',
			'local_business_image' => $site_url . '#lb-image',
		);
	}

	private function build_local_business_name( array $options ) {
		$company_name  = ! empty( $options['company_name'] ) ? $options['company_name'] : get_bloginfo( 'name' );
		$label         = ! empty( $options['local_business_label'] ) ? $options['local_business_label'] : '';
		$composed_name = trim( $company_name . ' ' . $label );
		$name          = '' !== $composed_name ? $composed_name : $company_name;
		if ( '' === trim( $name ) ) {
			$name = get_bloginfo( 'name' );
		}
		return $name;
	}

	private function build_postal_address( $address, array $options = array() ) {
		if ( ! is_array( $address ) ) {
			return array();
		}

		if ( ! empty( $options['localbusiness_address_locality'] ) ) {
			$address['locality'] = $options['localbusiness_address_locality'];
		}
		if ( ! empty( $options['localbusiness_street_address'] ) ) {
			$address['street_address'] = $options['localbusiness_street_address'];
		}

		$postal = array(
			'@type' => 'PostalAddress',
		);

		$mapping = array(
			'streetAddress'   => 'street_address',
			'addressLocality' => 'locality',
			'addressRegion'   => 'region',
			'postalCode'      => 'postal_code',
			'addressCountry'  => 'country',
		);

		foreach ( $mapping as $schema_key => $option_key ) {
			if ( ! empty( $address[ $option_key ] ) ) {
				$postal[ $schema_key ] = $address[ $option_key ];
			}
		}

		$has_core_field = ! empty( $postal['streetAddress'] )
			|| ! empty( $postal['addressLocality'] )
			|| ! empty( $postal['addressRegion'] )
			|| ! empty( $postal['postalCode'] );

		if ( ! $has_core_field ) {
			return array();
		}

		$postal['addressCountry'] = ! empty( $postal['addressCountry'] ) ? $postal['addressCountry'] : 'JP';

		return $postal;
	}

	private function has_required_local_business_data( array $options ) {
		$this->local_business_missing_fields = array();

		if ( empty( $options['phone'] ) ) {
			$this->local_business_missing_fields[] = 'telephone';
		}

		$address  = $options['address'] ?? array();
		$street   = ! empty( $options['localbusiness_street_address'] )
			? $options['localbusiness_street_address']
			: ( $address['street_address'] ?? '' );
		$locality = ! empty( $options['localbusiness_address_locality'] )
			? $options['localbusiness_address_locality']
			: ( $address['locality'] ?? '' );
		$region   = $address['region'] ?? '';
		$country  = ! empty( $address['country'] ) ? $address['country'] : 'JP';

		if ( empty( $street ) ) {
			$this->local_business_missing_fields[] = 'address.streetAddress';
		}
		if ( empty( $locality ) ) {
			$this->local_business_missing_fields[] = 'address.addressLocality';
		}
		if ( empty( $region ) ) {
			$this->local_business_missing_fields[] = 'address.addressRegion';
		}
		if ( empty( $country ) ) {
			$this->local_business_missing_fields[] = 'address.addressCountry';
		}

		return empty( $this->local_business_missing_fields );
	}
	private function build_area_served( $address ) {
		$areas        = array();
		$country_code = strtoupper( (string) ( $address['country'] ?? 'JP' ) );

		if ( ! empty( $address['prefecture'] ) ) {
			$prefecture_iso = ! empty( $address['prefecture_iso'] )
				? $address['prefecture_iso']
				: AI_Search_Schema_Prefectures::get_iso_code( $address['prefecture'] );

			$prefecture = array(
				'@type' => 'AdministrativeArea',
				'name'  => $address['prefecture'],
			);

			if ( $prefecture_iso ) {
				$prefecture['identifier'] = $prefecture_iso;
			}

			$areas[] = $prefecture;
		}

		if ( '' !== $country_code ) {
			$areas[] = array(
				'@type'      => 'Country',
				'name'       => ( 'JP' === $country_code ) ? 'Japan' : $country_code,
				'identifier' => $country_code,
			);
		}

		return $areas;
	}

	private function build_geo_coordinates( $geo ) {
		if ( ! is_array( $geo ) ) {
			return array();
		}

		$lat = isset( $geo['latitude'] ) ? trim( (string) $geo['latitude'] ) : '';
		$lng = isset( $geo['longitude'] ) ? trim( (string) $geo['longitude'] ) : '';

		if ( '' === $lat || '' === $lng ) {
			return array();
		}

		$lat_float = filter_var( $lat, FILTER_VALIDATE_FLOAT );
		$lng_float = filter_var( $lng, FILTER_VALIDATE_FLOAT );

		if ( false === $lat_float || false === $lng_float ) {
			return array();
		}

		if ( $lat_float < -90 || $lat_float > 90 || $lng_float < -180 || $lng_float > 180 ) {
			return array();
		}

		$lat_float = round( $lat_float, 6 );
		$lng_float = round( $lng_float, 6 );

		return array(
			'@type'     => 'GeoCoordinates',
			'latitude'  => $lat_float,
			'longitude' => $lng_float,
		);
	}

	private function build_map_url( $options, $geo_coordinates ) {
		if ( empty( $options['has_map_enabled'] ) || empty( $geo_coordinates ) ) {
			return '';
		}

		if ( ! isset( $geo_coordinates['latitude'], $geo_coordinates['longitude'] ) ) {
			return '';
		}

		$latitude  = (float) $geo_coordinates['latitude'];
		$longitude = (float) $geo_coordinates['longitude'];
		$query     = rawurlencode( $latitude . ',' . $longitude );
		return 'https://www.google.com/maps/search/?api=1&query=' . $query;
	}

	/**
	 * 営業時間スキーマを取得する。
	 *
	 * @param array $options プラグイン設定オプション。
	 * @return array OpeningHoursSpecification スキーマの配列。
	 */
	private function get_opening_hours( $options ) {
		return AI_Search_Schema_OpeningHoursBuilder::build( $options );
	}

	private function determine_content_schema_type( $options, array $context ) {
		$meta         = ! empty( $context['post_id'] )
			? get_post_meta( $context['post_id'], '_ai_search_schema_meta', true )
			: array();
		$page_type    = ( is_array( $meta ) && ! empty( $meta['page_type'] ) )
			? $meta['page_type']
			: 'auto';
		$default_type = ! empty( $options['content_model'] )
			? $options['content_model']
			: 'WebPage';
		$override     = $this->get_context_override( $options, $context );

		if ( 'auto' !== $page_type && ! empty( $page_type ) ) {
			$schema_type = $page_type;
		} elseif ( ! empty( $override['schema_type'] ) && 'auto' !== $override['schema_type'] ) {
			$schema_type = $override['schema_type'];
		} else {
			$schema_type = $default_type;
		}

		if ( 'FAQPage' === $schema_type && empty( $override['faq_enabled'] ) ) {
			$schema_type = $default_type;
		}

		return $schema_type;
	}

	private function get_context_override( array $options, array $context ) {
		$global_breadcrumbs = true;
		if ( array_key_exists( 'ai_search_schema_breadcrumbs_schema_enabled', $options ) ) {
			$global_breadcrumbs = (bool) $options['ai_search_schema_breadcrumbs_schema_enabled'];
		} elseif ( array_key_exists( 'enable_breadcrumbs', $options ) ) {
			$global_breadcrumbs = (bool) $options['enable_breadcrumbs'];
		}

		$defaults = array(
			'schema_type'         => 'auto',
			'breadcrumbs_enabled' => $global_breadcrumbs,
			'faq_enabled'         => true,
			'schema_priority'     => $options['ai_search_schema_priority'] ?? 'avc',
		);

		$settings = isset( $options['content_type_settings'] ) && is_array( $options['content_type_settings'] )
			? $options['content_type_settings']
			: array();

		if ( isset( $context['post_type'], $settings['post_types'][ $context['post_type'] ] ) ) {
			return array_merge( $defaults, $settings['post_types'][ $context['post_type'] ] );
		}

		if ( isset( $context['taxonomy'], $settings['taxonomies'][ $context['taxonomy'] ] ) ) {
			return array_merge( $defaults, $settings['taxonomies'][ $context['taxonomy'] ] );
		}

		return $defaults;
	}

	private function build_breadcrumb_schema( array $options, array $context ) {
		$override = $this->get_context_override( $options, $context );
		if ( empty( $override['breadcrumbs_enabled'] ) ) {
			return array();
		}

		$breadcrumb_items = AI_Search_Schema_Breadcrumbs::init()->get_items( $context );
		if ( empty( $breadcrumb_items ) ) {
			return array();
		}

		$item_list = array();
		$position  = 1;
		foreach ( $breadcrumb_items as $item ) {
			if ( empty( $item['label'] ) ) {
				continue;
			}
			$item_list[] = array(
				'@type'    => 'ListItem',
				'position' => $position++,
				'name'     => $item['label'],
				'item'     => ! empty( $item['url'] ) ? $item['url'] : $context['url'],
			);
		}

		if ( empty( $item_list ) ) {
			return array();
		}

		return array(
			'@type'           => 'BreadcrumbList',
			'@id'             => $context['url'] . '#breadcrumb',
			'itemListElement' => $item_list,
		);
	}

	private function build_archive_item_list( array $context ) {
		$archive_types = array( 'category', 'tag', 'taxonomy', 'blog_home', 'search', 'archive' );
		if ( empty( $context['type'] ) || ! in_array( $context['type'], $archive_types, true ) ) {
			return array();
		}

		global $wp_query;
		if ( ! isset( $wp_query->posts ) || empty( $wp_query->posts ) ) {
			return array();
		}

		$items    = array();
		$position = 1;

		foreach ( $wp_query->posts as $post ) {
			$url   = get_permalink( $post );
			$title = get_the_title( $post );
			if ( empty( $url ) || empty( $title ) ) {
				continue;
			}

			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $position++,
				'url'      => $url,
				'name'     => wp_strip_all_tags( $title ),
			);

			if ( $position > 11 ) {
				break;
			}
		}

		if ( empty( $items ) ) {
			return array();
		}

		return AI_Search_Schema_Type_ItemList::build( $context['url'] . '#itemlist', $items );
	}

	private function get_featured_image_object( $post_id ) {
		if ( ! $post_id || ! has_post_thumbnail( $post_id ) ) {
			return array();
		}

		$image_id   = get_post_thumbnail_id( $post_id );
		$image_data = wp_get_attachment_image_src( $image_id, 'full' );
		if ( ! $image_data ) {
			return array();
		}

		return array(
			'@type'  => 'ImageObject',
			'url'    => $image_data[0],
			'width'  => (int) $image_data[1],
			'height' => (int) $image_data[2],
		);
	}

	private function resolve_primary_language( $options ) {
		$langs = array();

		if ( ! empty( $options['languages'] ) && is_array( $options['languages'] ) ) {
			$langs = array_values( array_filter( $options['languages'] ) );
		} elseif ( ! empty( $options['language'] ) ) {
			$langs = array( $options['language'] );
		} else {
			$langs = array( get_bloginfo( 'language' ) );
		}

		$primary = ! empty( $langs ) ? $langs[0] : 'ja-JP';

		return $this->normalize_language_tag( $primary );
	}

	private function normalize_language_tag( $language_tag ) {
		$language_tag = str_replace( '_', '-', trim( (string) $language_tag ) );
		if ( '' === $language_tag ) {
			return 'ja-JP';
		}

		$parts    = explode( '-', $language_tag );
		$language = strtolower( array_shift( $parts ) );
		$region   = '';

		if ( ! empty( $parts ) ) {
			$region_candidate = strtoupper( array_shift( $parts ) );
			if ( strlen( $region_candidate ) === 2 ) {
				$region = $region_candidate;
			}
		}

		if ( '' === $region ) {
			$defaults = array(
				'ja' => 'JP',
				'en' => 'US',
				'zh' => 'CN',
				'fr' => 'FR',
				'de' => 'DE',
				'es' => 'ES',
				'pt' => 'PT',
				'it' => 'IT',
				'ko' => 'KR',
				'th' => 'TH',
			);
			if ( isset( $defaults[ $language ] ) ) {
				$region = $defaults[ $language ];
			}
		}

		return $region ? $language . '-' . $region : $language;
	}

	private function build_social_urls( $links ) {
		$results = array();
		foreach ( $links as $link ) {
			$network = isset( $link['network'] ) ? $link['network'] : '';
			$account = isset( $link['account'] ) ? trim( $link['account'] ) : '';
			$label   = isset( $link['label'] ) ? $link['label'] : '';

			if ( '' === $account ) {
				continue;
			}

			switch ( $network ) {
				case 'facebook':
					$account   = ltrim( $account, '@/' );
					$results[] = 'https://www.facebook.com/' . $account;
					break;
				case 'instagram':
					$account   = ltrim( $account, '@/' );
					$results[] = 'https://www.instagram.com/' . $account . '/';
					break;
				case 'x':
					$account   = ltrim( $account, '@/' );
					$results[] = 'https://x.com/' . $account;
					break;
				case 'youtube':
					$account = ltrim( $account, '@/' );
					if ( filter_var( $account, FILTER_VALIDATE_URL ) ) {
						$results[] = esc_url_raw( $account );
					} else {
						$results[] = 'https://www.youtube.com/' . $account;
					}
					break;
				case 'google_plus':
					$results[] = esc_url_raw( $account );
					break;
				case 'line':
					if ( filter_var( $account, FILTER_VALIDATE_URL ) ) {
						$results[] = esc_url_raw( $account );
					} else {
						$account   = ltrim( $account, '@/' );
						$results[] = 'https://page.line.me/' . $account;
					}
					break;
				case 'tiktok':
					$account   = ltrim( $account, '@/' );
					$results[] = 'https://www.tiktok.com/@' . $account;
					break;
				case 'threads':
					$account   = ltrim( $account, '@/' );
					$results[] = 'https://www.threads.net/@' . $account;
					break;
				case 'pinterest':
					$account   = ltrim( $account, '@/' );
					$results[] = 'https://www.pinterest.com/' . $account;
					break;
				case 'mixi':
					if ( filter_var( $account, FILTER_VALIDATE_URL ) ) {
						$results[] = esc_url_raw( $account );
					} else {
						$results[] = 'https://mixi.jp/' . ltrim( $account, '/' );
					}
					break;
				default:
					if ( filter_var( $account, FILTER_VALIDATE_URL ) ) {
						$results[] = esc_url_raw( $account );
					}
					break;
			}
		}

		return $results;
	}

	private function remove_null_values( $data ) {
		if ( is_array( $data ) ) {
			$is_indexed = array_keys( $data ) === range( 0, count( $data ) - 1 );
			$clean      = array();

			foreach ( $data as $key => $value ) {
				$cleaned = $this->remove_null_values( $value );

				if ( null === $cleaned ) {
					continue;
				}

				if ( is_array( $cleaned ) && empty( $cleaned ) ) {
					continue;
				}

				if ( $is_indexed ) {
					$clean[] = $cleaned;
				} else {
					$clean[ $key ] = $cleaned;
				}
			}

			return $clean;
		}

		return null === $data ? null : $data;
	}
}

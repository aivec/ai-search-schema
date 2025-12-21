<?php
/**
 * AI_Search_Schema クラス
 *
 * JSON-LD スキーマを生成するクラスで、共通 @graph やページ別のスキーマを出力します。
 *
 * NOTE:
 * AVC 優先モード時は、最終HTMLから
 * 「AVC が出力した JSON-LD 以外の application/ld+json <script> をすべて削除」する。
 * 各SEOプラグインごとの出力仕様変更に影響されないよう、
 * 目印付きスクリプト（class="ai-search-schema-graph" / data-ai-search-schema="1"）だけ残すポリシー。
 *
 */
class AI_Search_Schema {
	/**
	 * @var AI_Search_Schema|null
	 */
	private static $instance = null;

	/**
	 * 初期化
	 *
	 * @return AI_Search_Schema
	 */
	public static function init() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * plugins_loaded で競合スキーマ抑止を初期化
	 *
	 * @return void
	 */
	public static function bootstrap_competing_schema_control() {
		$instance = self::init();
		$instance->maybe_toggle_competing_schema_plugins();
	}

	/**
	 * 正規化されたオプションを取得
	 *
	 * @return array
	 */
	private function get_normalized_options() {
		return AI_Search_Schema_Settings::init()->get_options();
	}

	/**
	 * @var array
	 */
	private $runtime_validation_errors = array();
	/**
	 * @var array
	 */
	private $runtime_validation_warnings = array();

	/**
	 * @var bool
	 */
	private $suppressing_external_schema = false;

	/**
	 * @var bool
	 */
	private $local_business_suppressed = false;

	/**
	 * @var array
	 */
	private $local_business_missing_fields = array();

	/**
	 * @var array<int, array{
	 *     hook:string,
	 *     callback:callable,
	 *     priority:int,
	 *     accepted_args:int
	 * }>
	 */
	private $external_schema_filters = array();

	/**
	 * @var bool
	 */
	private $head_buffer_enabled = false;

	/**
	 * @var bool
	 */
	private $head_buffer_active = false;

	/**
	 * @var bool
	 */
	private $global_buffer_enabled = false;

	/**
	 * @var bool
	 */
	private $global_buffer_active = false;

	/**
	 * コンストラクタ
	 */
	public function __construct() {
		add_action( 'wp_head', array( $this, 'output_json_ld' ) );
		add_action( 'wp', array( $this, 'apply_schema_priority_for_current_context' ), 0 );
		add_action( 'admin_notices', array( $this, 'display_admin_validation_notice' ) );
		add_action( 'wp_footer', array( $this, 'display_frontend_validation_notice' ) );
		add_action( 'init', array( $this, 'maybe_toggle_competing_schema_plugins' ), 5, 0 );
	}

	/**
	 * JSON-LD スキーマを出力する
 */
	public function output_json_ld() {
		$context = $this->resolve_page_context();
		$options = $this->get_normalized_options();

		if ( 'external' === $this->get_schema_priority_value( $options, $context ) ) {
			return;
		}

		if ( ! $this->is_schema_output_enabled( $options ) ) {
			return;
		}

		if ( is_404() ) {
			return;
		}

		$schema_data = $this->generate_schema();
		if ( empty( $schema_data ) ) {
			return;
		}

		$validator  = new AI_Search_Schema_Validator();
		$validation = $validator->validate( $schema_data );

		if ( $this->local_business_suppressed ) {
			$message = __( 'LocalBusiness: Skipped output because required fields were missing.', 'ai-search-schema' );
			if ( ! empty( $this->local_business_missing_fields ) ) {
				$message .= ' ' . sprintf(
					/* translators: %s: comma separated list of missing fields */
					__( 'Missing fields: %s.', 'ai-search-schema' ),
					implode( ', ', $this->local_business_missing_fields )
				);
			}
			$validation['warnings'][] = $message;
		}

		$this->runtime_validation_warnings = $validation['warnings'];

		if ( $validation['is_valid'] ) {
			$this->clear_validation_errors();
			$clean_schema = $this->remove_null_values( $validation['schema'] );
			if ( ! empty( $clean_schema ) ) {
				$output = new AI_Search_Schema_Output( $validator );
				$output->print( $clean_schema, true );
			}
			return;
		}

		$this->handle_validation_failure( $validation['errors'], $validation['schema'] );
	}

	/**
	 * スキーマデータを生成する
	 *
	 * @return array スキーマデータ
	 */
	private function generate_schema() {
		$options  = $this->get_normalized_options();
		$resolver = new AI_Search_Schema_ContentResolver();
		$builder  = new AI_Search_Schema_GraphBuilder( $resolver );

		$schema                              = $builder->build( $options );
		$this->local_business_suppressed     = $builder->was_local_business_suppressed();
		$this->local_business_missing_fields = $builder->get_local_business_missing_fields();

		return $schema;
	}

	/**
	 * スキーマ出力が有効かどうかを判定
	 *
	 * @return bool
	 */
	private function is_schema_output_enabled( $options = null ) {
		if ( null === $options ) {
			$options = $this->get_normalized_options();
		}

		$default_enabled = ( 'external' !== $this->get_schema_priority_value( $options ) );

		/**
		 * フィルター: ai_search_schema_enabled
		 * スキーマ出力を有効 / 無効にする。
		 *
		 * @param bool $enabled 現在の有効状態
		 */
		$is_enabled = (bool) apply_filters( 'ai_search_schema_enabled', $default_enabled );

		return $is_enabled;
	}

	/**
	 * サイトURLを正規化
	 *
	 * @param array $options 設定オプション
	 * @return string
	 */
	private function normalize_site_url( $options ) {
		$site = ! empty( $options['site_url'] ) ? $options['site_url'] : home_url( '/' );
		$site = trim( $site );
		if ( '' === $site ) {
			$site = home_url( '/' );
		}
		return rtrim( $site, '/' ) . '/';
	}

	/**
	 * @param string $site_url
	 * @return array
	 */
	private function build_graph_ids( $site_url ) {
		return array(
			'organization'         => $site_url . '#org',
			'logo'                 => $site_url . '#logo',
			'website'              => $site_url . '#website',
			'local_business'       => $site_url . '#lb-main',
			'local_business_image' => $site_url . '#lb-image',
		);
	}

	/**
	 * 共通グラフを構築
	 *
	 * @param array  $options  設定オプション
	 * @param string $site_url 基準URL
	 * @param array  $ids      @idセット
	 * @return array
	 */
	private function build_common_graph( $options, $site_url, array $ids ) {
		$graph            = array();
		$primary_language = $this->resolve_primary_language( $options );

		$entity_type       = ! empty( $options['entity_type'] ) ? $options['entity_type'] : 'Organization';
		$show_organization = ( 'Organization' === $entity_type );
			$organization  = $show_organization
				? $this->build_organization_schema( $options, $ids, $site_url )
				: array();
		if ( ! empty( $organization ) ) {
			$graph[] = $organization;
		}

		$logo = $this->build_logo_schema( $options, $ids );
		if ( ! empty( $logo ) ) {
			$graph[] = $logo;
		}

		$local_business = $this->build_local_business_schema( $options, $ids, $site_url, ! empty( $organization ) );
		if ( ! empty( $local_business ) ) {
			$graph[] = $local_business;
		}

		if ( ! empty( $local_business ) && ! empty( $options['lb_image_url'] ) ) {
			$lb_image = $this->build_local_business_image_schema( $options, $ids );
			if ( ! empty( $lb_image ) ) {
				$graph[] = $lb_image;
			}
		}

		$publisher_entity = ! empty( $options['publisher_entity'] )
			? $options['publisher_entity']
			: 'Organization';
		if ( 'Organization' !== $publisher_entity ) {
			$publisher_entity = 'Organization';
		}

		$publisher_id = null;
		if ( 'Organization' === $publisher_entity && ! empty( $organization ) ) {
			$publisher_id = $ids['organization'];
		}

		$website = $this->build_website_schema( $options, $ids, $site_url, $primary_language, $publisher_id );
		if ( ! empty( $website ) ) {
			$graph[] = $website;
		}

		return array(
			'graph'        => array_values( array_filter( $graph ) ),
			'publisher_id' => $publisher_id,
		);
	}

	/**
	 * スキーマ優先設定を取得
	 *
	 * @param array|null $options 設定オプション
	 * @return string avc|external
	 */
	private function get_schema_priority_value( $options = null, ?array $context = null ) {
		if ( null === $options ) {
			$options = $this->get_normalized_options();
		}

		if ( ! is_array( $options ) ) {
			$options = array();
		}

		if ( isset( $options['ai_search_schema_priority'] ) ) {
			$priority = $options['ai_search_schema_priority'];
		} elseif ( isset( $options['schema_priority'] ) ) {
			$priority = $options['schema_priority'];
		} else {
			$priority = 'ais';
		}

		if ( ! empty( $context ) ) {
			$override = $this->get_context_override( $options, $context );
			if ( isset( $override['schema_priority'] ) ) {
				$priority = $override['schema_priority'];
			}
		}

		return in_array( $priority, array( 'ais', 'external' ), true ) ? $priority : 'ais';
	}

	/**
	 * ページ文脈ごとのオーバーライド値
	 *
	 * @param array $options
	 * @param array $context
	 * @return array
	 */
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
			'schema_priority'     => $options['ai_search_schema_priority'] ?? 'ais',
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

	/**
	 * Organizationノード
	 */
	private function build_organization_schema( $options, array $ids, $site_url ) {
		$name = ! empty( $options['company_name'] ) ? $options['company_name'] : get_bloginfo( 'name' );
		$name = trim( $name );
		if ( '' === $name ) {
			return array();
		}

		$organization = array(
			'@type' => 'Organization',
			'@id'   => $ids['organization'],
			'name'  => $name,
			'url'   => $site_url,
		);

		if ( ! empty( $options['logo_url'] ) ) {
			$logo_ref              = array( '@id' => $ids['logo'] );
			$organization['image'] = $logo_ref;
			$organization['logo']  = $logo_ref;
		}

		if ( ! empty( $options['social_links'] ) && is_array( $options['social_links'] ) ) {
			$same_as = $this->build_social_urls( $options['social_links'] );
			if ( ! empty( $same_as ) ) {
				$organization['sameAs'] = $same_as;
			}
		}

		return $organization;
	}

	/**
	 * ロゴImageObject
	 */
	private function build_logo_schema( $options, array $ids ) {
		if ( empty( $options['logo_url'] ) ) {
			return array();
		}

		$logo = array(
			'@type' => 'ImageObject',
			'@id'   => $ids['logo'],
			'url'   => esc_url( $options['logo_url'] ),
		);

		$width   = 512;
		$height  = 512;
		$logo_id = ! empty( $options['logo_id'] ) ? absint( $options['logo_id'] ) : 0;
		if ( $logo_id ) {
			$logo_data = wp_get_attachment_image_src( $logo_id, 'full' );
			if ( $logo_data ) {
				$width  = (int) $logo_data[1];
				$height = (int) $logo_data[2];
			}
		}

		$logo['width']  = $width;
		$logo['height'] = $height;

		return $logo;
	}

	/**
	 * LocalBusiness用のImageObject
	 */
	private function build_local_business_image_schema( $options, array $ids ) {
		if ( empty( $options['lb_image_url'] ) ) {
			return array();
		}

		$image = array(
			'@type' => 'ImageObject',
			'@id'   => $ids['local_business_image'],
			'url'   => esc_url( $options['lb_image_url'] ),
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

	/**
	 * WebSiteノード
	 */
	private function build_website_schema( $options, array $ids, $site_url, $language, $publisher_id ) {
		$site_name = ! empty( $options['site_name'] ) ? $options['site_name'] : get_bloginfo( 'name' );
		$website   = array(
			'@type'      => 'WebSite',
			'@id'        => $ids['website'],
			'url'        => $site_url,
			'name'       => $site_name,
			'inLanguage' => $language,
		);

		$website['potentialAction'] = array(
			'@type'       => 'SearchAction',
			'target'      => home_url( '/?s={search_term_string}' ),
			'query-input' => 'required name=search_term_string',
		);

				$publisher = $this->get_publisher_schema( $options, $publisher_id );
		if ( ! empty( $publisher ) ) {
				$website['publisher'] = $publisher;
		}

		return $website;
	}

	/**
	 * LocalBusinessノード
	 */
	private function build_local_business_schema( $options, array $ids, $site_url, $has_organization ) {
		$address         = ! empty( $options['address'] ) && is_array( $options['address'] )
			? $options['address']
			: array();
		$geo             = ! empty( $options['geo'] ) && is_array( $options['geo'] )
			? $options['geo']
			: array();
		$opening_hours   = $this->get_opening_hours( $options );
		$geo_coordinates = $this->build_geo_coordinates( $geo );

		$company_name  = ! empty( $options['company_name'] ) ? $options['company_name'] : get_bloginfo( 'name' );
		$label         = ! empty( $options['local_business_label'] ) ? $options['local_business_label'] : '';
		$composed_name = trim( $company_name . ' ' . $label );
		$name          = '' !== $composed_name ? $composed_name : $company_name;
		if ( '' === trim( $name ) ) {
			$name = get_bloginfo( 'name' );
		}

		if ( ! $this->should_render_local_business( $options, $address, $geo_coordinates, $opening_hours, $name ) ) {
			return array();
		}

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

		if ( ! empty( $options['lb_image_url'] ) ) {
			$local_business['image'] = array(
				'@id' => $ids['local_business_image'],
			);
		}

		if ( ! empty( $options['phone'] ) ) {
			$local_business['telephone'] = $options['phone'];
		}

		$postal_address = $this->build_postal_address( $address );
		if ( ! empty( $postal_address ) ) {
			$local_business['address'] = $postal_address;
		}

		$area_served = $this->build_area_served( $address );
		if ( ! empty( $area_served ) ) {
			$local_business['areaServed'] = $area_served;
		}

		if ( ! empty( $geo_coordinates ) ) {
			$local_business['geo'] = $geo_coordinates;
		}

		if ( ! empty( $opening_hours ) ) {
			$local_business['openingHoursSpecification'] = $opening_hours;
		}

		$map_url = $this->build_map_url( $options, $geo_coordinates );
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
	 * LocalBusiness出力要否
	 */
	private function should_render_local_business( $options, $address, $geo_coordinates, $opening_hours, $name = '' ) {
		if ( '' === trim( $name ) ) {
			$name = ! empty( $options['company_name'] ) ? $options['company_name'] : get_bloginfo( 'name' );
		}

		if ( '' === trim( $name ) ) {
			return false;
		}

		$required_address = array(
			'street_address',
			'locality',
			'region',
			'country',
		);
		foreach ( $required_address as $key ) {
			if ( empty( $address[ $key ] ) ) {
				return false;
			}
		}

		if ( empty( $options['phone'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * 住所入力の有無
	 */
	private function has_address_data( $address ) {
		if ( ! is_array( $address ) ) {
			return false;
		}

		foreach ( $address as $value ) {
			if ( ! empty( $value ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * PostalAddressを生成
	 */
	private function build_postal_address( $address ) {
		if ( ! is_array( $address ) ) {
				return array();
		}

			$required_keys = array(
				'street_address',
				'locality',
				'postal_code',
			);

			foreach ( $required_keys as $required_key ) {
				if ( empty( $address[ $required_key ] ) ) {
						return array();
				}
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

			return $postal;
	}

	/**
	 * GeoCoordinatesを生成
	 */
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

		return array(
			'@type'     => 'GeoCoordinates',
			'latitude'  => $lat_float,
			'longitude' => $lng_float,
		);
	}

	/**
	 * hasMap URL を構築
	 *
	 * @param array $options 設定オプション
	 * @param array $geo_coordinates GeoCoordinates
	 * @return string
	 */
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
	 * areaServed を構築
	 *
	 * @param array $address 住所情報
	 * @return array
	 */
	private function build_area_served( $address ) {
		if ( empty( $address['prefecture'] ) ) {
				return array();
		}

					$prefecture_iso = ! empty( $address['prefecture_iso'] )
							? $address['prefecture_iso']
							: AI_Search_Schema_Prefectures::get_iso_code( $address['prefecture'] );

			$areas = array(
				array(
					'@type' => 'AdministrativeArea',
					'name'  => $address['prefecture'],
				),
			);

			if ( $prefecture_iso ) {
					$areas[0]['identifier'] = $prefecture_iso;
			}

			$country_code = ! empty( $address['country'] ) ? $address['country'] : 'JP';
			$country_name = 'JP' === $country_code ? 'Japan' : 'United States';

			$areas[] = array(
				'@type'      => 'Country',
				'name'       => $country_name,
				'identifier' => $country_code,
			);

			return $areas;
	}

	/**
	 * 営業時間を取得する
	 *
	 * @param array $options 設定オプション
	 * @return array 営業時間スキーマ
	 */
	private function get_opening_hours( $options ) {
		$opening_hours = array();
		if ( ! empty( $options['opening_hours'] ) && is_array( $options['opening_hours'] ) ) {
			foreach ( $options['opening_hours'] as $slot ) {
				if ( empty( $slot['day'] ) || empty( $slot['opens'] ) || empty( $slot['closes'] ) ) {
					continue;
				}
					$opening_hours[] = array(
						'@type'     => 'OpeningHoursSpecification',
						'dayOfWeek' => $slot['day'],
						'opens'     => $slot['opens'],
						'closes'    => $slot['closes'],
					);
			}
		}
		return $opening_hours;
	}

	/**
	 * ページ固有のグラフを構築
	 */
	private function build_page_graph( $options, array $ids, $publisher_id ) {
		if ( is_404() ) {
			return array();
		}

		$language_value   = $this->resolve_primary_language( $options );
		$context          = $this->resolve_page_context();
				$web_page = $this->build_web_page_schema( $context, $ids, $language_value, $options, $publisher_id );

		if ( empty( $web_page ) ) {
			return array();
		}

		$graph = array( $web_page );

		if ( $this->is_breadcrumb_schema_enabled( $options, $context ) ) {
			$breadcrumb = $this->build_breadcrumb_schema( $context );
			if ( ! empty( $breadcrumb ) ) {
				$graph[] = $breadcrumb;
			}
		}

		$archive_item_list = $this->build_archive_item_list( $context );
		if ( ! empty( $archive_item_list ) ) {
			$graph[] = $archive_item_list;
		}

		$schema_type    = $this->determine_content_schema_type( $options, $context );
		$content_schema = $this->build_content_schema(
			$schema_type,
			$context,
			$language_value,
			$publisher_id,
			$web_page['@id'],
			$options
		);
		if ( ! empty( $content_schema ) ) {
			$graph[] = $content_schema;
		}

		return $graph;
	}

	/**
	 * JSON-LD のパンくず出力が有効かどうか
	 *
	 * @param array $options 設定オプション
	 * @return bool
	 */
	private function is_breadcrumb_schema_enabled( array $options, array $context ) {
		$override = $this->get_context_override( $options, $context );
		return (bool) $override['breadcrumbs_enabled'];
	}

	/**
	 * 現在ページの情報を取得
	 */
	private function resolve_page_context() {
		$context = array(
			'post_id' => 0,
			'url'     => $this->get_current_url(),
			'title'   => get_bloginfo( 'name' ),
			'type'    => 'general',
		);

		if ( is_front_page() ) {
			$context['type'] = 'front_page';
			$front_page_id   = (int) get_option( 'page_on_front' );
			if ( $front_page_id ) {
				$context['post_id'] = $front_page_id;
				$context['url']     = get_permalink( $front_page_id );
				$context['title']   = get_the_title( $front_page_id );
			}
			return $this->normalize_context_title( $context );
		}

		if ( is_home() && ! is_front_page() ) {
			$context['type'] = 'blog_home';
			$posts_page_id   = (int) get_option( 'page_for_posts' );
			if ( $posts_page_id ) {
				$context['post_id'] = $posts_page_id;
				$context['url']     = get_permalink( $posts_page_id );
				$context['title']   = get_the_title( $posts_page_id );
			} else {
				$context['title'] = get_the_archive_title();
			}
			return $this->normalize_context_title( $context );
		}

		if ( is_singular() ) {
			$post_id = get_queried_object_id();
			if ( $post_id ) {
				$context['post_id']   = $post_id;
				$context['url']       = get_permalink( $post_id );
				$context['title']     = get_the_title( $post_id );
				$context['type']      = is_page( $post_id ) ? 'page' : 'singular';
				$context['post_type'] = get_post_type( $post_id );
			}
			return $this->normalize_context_title( $context );
		}

		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term instanceof WP_Term ) {
				$context['term']     = $term;
				$term_link           = get_term_link( $term );
				$context['url']      = is_wp_error( $term_link ) ? $this->get_current_url() : $term_link;
				$context['title']    = $term->name;
				$context['type']     = is_category() ? 'category' : ( is_tag() ? 'tag' : 'taxonomy' );
				$context['taxonomy'] = $term->taxonomy;
			} else {
				$context['type']  = 'taxonomy';
				$context['title'] = get_the_archive_title();
			}
			return $this->normalize_context_title( $context );
		}

		if ( is_author() ) {
			$author = get_queried_object();
			if ( $author instanceof WP_User ) {
				$context['type']   = 'author';
				$context['author'] = $author;
				$context['title']  = $author->display_name;
				$context['url']    = get_author_posts_url( $author->ID );
			} else {
				$context['type']  = 'author';
				$context['title'] = get_the_archive_title();
			}
			return $this->normalize_context_title( $context );
		}

		if ( is_search() ) {
			$query           = get_search_query();
			$context['type'] = 'search';
			if ( $query ) {
				$context['title'] = sprintf(
					/* translators: %s: search query string. */
					__( 'Search results for "%s"', 'ai-search-schema' ),
					$query
				);
			} else {
				$context['title'] = __( 'Search results', 'ai-search-schema' );
			}
			$search_link       = get_search_link( $query );
			$context['url']    = is_wp_error( $search_link ) ? $this->get_current_url() : $search_link;
			$context['search'] = $query;
			return $this->normalize_context_title( $context );
		}

		if ( is_archive() ) {
			$context['type']  = 'archive';
			$context['title'] = get_the_archive_title();
		}

		return $this->normalize_context_title( $context );
	}

	/**
	 * ページ文脈のタイトルとURLを正規化
	 *
	 * @param array $context コンテキスト
	 * @return array
	 */
	private function normalize_context_title( array $context ) {
		if ( empty( $context['title'] ) ) {
			$context['title'] = get_bloginfo( 'name' );
		}

		if ( empty( $context['url'] ) ) {
			$context['url'] = $this->get_current_url();
		}

		return $context;
	}

	/**
	 * 現在のURLを推測
	 */
	private function get_current_url() {
		global $wp;

		if ( isset( $wp->request ) && $wp->request ) {
			return home_url( trailingslashit( $wp->request ) );
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		$request_uri = sanitize_text_field( $request_uri );
		if ( '' === $request_uri || '/' !== $request_uri[0] ) {
			$request_uri = '/' . ltrim( $request_uri, '/' );
		}

		return home_url( $request_uri );
	}

	/**
	 * WebPageノード
	 */
	private function build_web_page_schema( array $context, array $ids, $language_value, $options, $publisher_id ) {
		if ( empty( $context['url'] ) ) {
			return array();
		}

		$schema_types = $this->determine_webpage_types( $context );

		$schema = array(
			'@type'    => ( count( $schema_types ) === 1 ) ? $schema_types[0] : $schema_types,
			'@id'      => $context['url'] . '#webpage',
			'url'      => $context['url'],
			'name'     => $context['title'],
			'isPartOf' => array(
				'@id' => $ids['website'],
			),
		);

		if ( $language_value ) {
			$schema['inLanguage'] = $language_value;
		}

			$image = $this->get_featured_image_object( $context['post_id'] );
		if ( ! empty( $image ) ) {
				$schema['primaryImageOfPage'] = $image;
		}

			$publisher = $this->get_publisher_schema( $options, $publisher_id );
		if ( ! empty( $publisher ) ) {
				$schema['publisher'] = $publisher;
		}

			return $schema;
	}

	/**
	 * WebPageタイプを判定
	 *
	 * @param array $context ページ情報
	 * @return array
	 */
	private function determine_webpage_types( array $context ) {
		$types = array( 'WebPage' );
		$type  = isset( $context['type'] ) ? $context['type'] : '';

		switch ( $type ) {
			case 'front_page':
				$types[] = 'HomePage';
				break;
			case 'blog_home':
			case 'category':
			case 'tag':
			case 'taxonomy':
			case 'archive':
				$types[] = 'CollectionPage';
				break;
			case 'search':
				$types[] = 'SearchResultsPage';
				break;
			case 'author':
				$types[] = 'ProfilePage';
				break;
		}

		return array_values( array_unique( $types ) );
	}

	/**
	 * パンくず
	 */
	private function build_breadcrumb_schema( array $context ) {
		$items = $this->build_breadcrumb_items( $context );
		if ( empty( $items ) ) {
			return array();
		}

		return array(
			'@type'           => 'BreadcrumbList',
			'@id'             => $context['url'] . '#breadcrumb',
			'itemListElement' => $items,
		);
	}

	/**
	 * パンくず要素
	 */
	private function build_breadcrumb_items( array $context ) {
		if ( isset( $context['type'] ) && 'front_page' === $context['type'] ) {
			return array();
		}

		$items    = array();
		$position = 1;

		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $position++,
			'name'     => get_bloginfo( 'name' ),
			'item'     => home_url( '/' ),
		);

		if ( ! empty( $context['post_id'] ) ) {
			$ancestors = array_reverse( get_post_ancestors( $context['post_id'] ) );
			foreach ( $ancestors as $ancestor_id ) {
				$items[] = array(
					'@type'    => 'ListItem',
					'position' => $position++,
					'name'     => get_the_title( $ancestor_id ),
					'item'     => get_permalink( $ancestor_id ),
				);
			}

			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $position++,
				'name'     => $context['title'],
				'item'     => $context['url'],
			);
		} elseif (
				isset( $context['type'] )
				&& in_array( $context['type'], array( 'category', 'tag', 'taxonomy' ), true )
				&& ! empty( $context['term'] )
				&& $context['term'] instanceof WP_Term
			) {
			$term = $context['term'];
			if ( is_taxonomy_hierarchical( $term->taxonomy ) ) {
				$ancestors = array_reverse( get_ancestors( $term->term_id, $term->taxonomy ) );
				foreach ( $ancestors as $ancestor_id ) {
					$ancestor = get_term( $ancestor_id, $term->taxonomy );
					if ( $ancestor && ! is_wp_error( $ancestor ) ) {
						$items[] = array(
							'@type'    => 'ListItem',
							'position' => $position++,
							'name'     => $ancestor->name,
							'item'     => get_term_link( $ancestor ),
						);
					}
				}
			}

			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $position++,
				'name'     => $context['title'],
				'item'     => $context['url'],
			);
		} elseif (
				isset( $context['type'] )
				&& in_array( $context['type'], array( 'search', 'author', 'archive', 'blog_home' ), true )
			) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $position++,
				'name'     => $context['title'],
				'item'     => $context['url'],
			);
		} elseif ( ! is_front_page() ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $position++,
				'name'     => $context['title'],
				'item'     => $context['url'],
			);
		}

		return ( count( $items ) > 1 ) ? $items : array();
	}

	/**
	 * アーカイブの ItemList を生成
	 *
	 * @param array $context ページ情報
	 * @return array
	 */
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

			$clean_title = wp_strip_all_tags( $title );

			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $position++,
				'url'      => $url,
				'name'     => $clean_title,
				'item'     => array(
					'name' => $clean_title,
					'url'  => $url,
				),
			);

			if ( $position > 11 ) {
				break;
			}
		}

		if ( empty( $items ) ) {
			return array();
		}

		return array(
			'@type'           => 'ItemList',
			'@id'             => $context['url'] . '#itemlist',
			'itemListOrder'   => 'http://schema.org/ItemListOrderAscending',
			'itemListElement' => $items,
		);
	}

	/**
	 * ページタイプを決定
	 */
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

	/**
	 * コンテンツ固有スキーマ
	 */
	private function build_content_schema(
		$schema_type,
		array $context,
		$language_value,
		$publisher_id,
		$webpage_id,
		array $options
	) {
		if ( 'WebPage' === $schema_type ) {
			return array();
		}

		$article_like = array( 'Article', 'NewsArticle', 'BlogPosting' );
		if ( in_array( $schema_type, $article_like, true ) ) {
			return $this->get_article_schema(
				$context['post_id'],
				$options,
				$language_value,
				$publisher_id,
				$schema_type,
				$webpage_id
			);
		}

		if ( 'FAQPage' === $schema_type ) {
			return $this->build_faq_schema( $context, $language_value, $webpage_id, $options );
		}

		if ( empty( $context['url'] ) ) {
			return array();
		}

		$schema = array(
			'@type'            => $schema_type,
			'name'             => $context['title'],
			'url'              => $context['url'],
			'mainEntityOfPage' => array(
				'@id' => $webpage_id,
			),
		);

		if ( 'Product' === $schema_type ) {
			$price = '';
			if ( ! empty( $context['post_id'] ) ) {
				$price = get_post_meta( $context['post_id'], '_price', true );
			}

			$currency = get_option( 'woocommerce_currency' );
			if ( empty( $currency ) ) {
				$currency = 'USD';
			}

			$stock_status = '';
			if ( ! empty( $context['post_id'] ) ) {
				$stock_status = get_post_meta( $context['post_id'], '_stock_status', true );
			}

			$availability = ( 'outofstock' === $stock_status )
				? 'http://schema.org/OutOfStock'
				: 'http://schema.org/InStock';

			$schema['offers'] = array(
				'@type'         => 'Offer',
				'price'         => (string) $price,
				'priceCurrency' => $currency,
				'availability'  => $availability,
			);
		}

		if ( 'QAPage' === $schema_type ) {
			$answer_text = '';
			if ( ! empty( $context['post_id'] ) ) {
				$answer_text = get_post_field( 'post_content', $context['post_id'] );
			}

			if ( empty( $answer_text ) ) {
				$answer_text = $context['title'];
			}

			$schema['mainEntity'] = array(
				array(
					'@type'          => 'Question',
					'name'           => $context['title'],
					'acceptedAnswer' => array(
						'@type' => 'Answer',
						'text'  => wp_strip_all_tags( $answer_text ),
					),
				),
			);
		}

		if ( 'ItemList' === $schema_type ) {
			$schema['itemListElement'] = array(
				array(
					'@type'    => 'ListItem',
					'position' => 1,
					'name'     => $context['title'],
					'url'      => $context['url'],
				),
			);
		}

		if ( 'CollectionPage' === $schema_type ) {
			$schema['hasPart'] = array(
				array(
					'@type' => 'WebPage',
					'name'  => $context['title'],
					'url'   => $context['url'],
				),
			);
		}

		if ( $language_value ) {
			$schema['inLanguage'] = $language_value;
		}

		$image = $this->get_featured_image_object( $context['post_id'] );
		if ( ! empty( $image ) ) {
			$schema['image'] = $image;
		}

		switch ( $schema_type ) {
			case 'Product':
				$price        = '';
				$currency     = '';
				$availability = '';

				if ( ! empty( $context['post_id'] ) ) {
					$price_meta = get_post_meta( $context['post_id'], 'price', true );
					if ( '' === $price_meta ) {
						$price_meta = get_post_meta( $context['post_id'], '_price', true );
					}
					if ( '' === $price_meta ) {
						$price_meta = get_post_meta( $context['post_id'], '_regular_price', true );
					}
					if ( '' === $price_meta ) {
						$price_meta = get_post_meta( $context['post_id'], '_sale_price', true );
					}
					$price = is_scalar( $price_meta ) ? (string) $price_meta : '';

					$currency_meta = get_post_meta( $context['post_id'], 'price_currency', true );
					if ( '' === $currency_meta ) {
						$currency_meta = get_post_meta( $context['post_id'], '_currency', true );
					}
					$currency = is_scalar( $currency_meta ) ? (string) $currency_meta : '';

					$availability_meta = get_post_meta( $context['post_id'], 'availability', true );
					if ( '' === $availability_meta ) {
						$availability_meta = get_post_meta( $context['post_id'], '_stock_status', true );
					}
					if ( 'instock' === $availability_meta ) {
						$availability_meta = 'https://schema.org/InStock';
					} elseif ( 'outofstock' === $availability_meta ) {
						$availability_meta = 'https://schema.org/OutOfStock';
					}
					$availability = is_scalar( $availability_meta ) ? (string) $availability_meta : '';
				}

				if ( '' === $price ) {
					$price = '0';
				}

				if ( '' === $currency ) {
					$locale   = get_locale();
					$currency = ( 0 === strpos( $locale, 'ja' ) ) ? 'JPY' : 'USD';
				}

				if ( '' === $availability ) {
					$availability = 'https://schema.org/InStock';
				}

				$schema['offers'] = array(
					'@type'         => 'Offer',
					'price'         => $price,
					'priceCurrency' => $currency,
					'availability'  => $availability,
				);
				break;

			case 'QAPage':
				$answer_text = '';
				if ( ! empty( $context['post_id'] ) ) {
					$post = get_post( $context['post_id'] );
					if ( $post instanceof WP_Post ) {
						$answer_text = $post->post_excerpt
							? wp_strip_all_tags( $post->post_excerpt )
							: wp_strip_all_tags( $post->post_content );
					}
				}

				if ( '' === $answer_text ) {
					$answer_text = $context['title'];
				}

				$schema['mainEntity'] = array(
					array(
						'@type'          => 'Question',
						'name'           => $context['title'],
						'acceptedAnswer' => array(
							'@type' => 'Answer',
							'text'  => $answer_text,
						),
					),
				);
				break;

			case 'CollectionPage':
				$archive_item_list = $this->build_archive_item_list( $context );
				if (
					! empty( $archive_item_list['itemListElement'] )
					&& is_array( $archive_item_list['itemListElement'] )
				) {
					$schema['hasPart'] = array();
					foreach ( $archive_item_list['itemListElement'] as $item ) {
						if ( empty( $item['name'] ) ) {
							continue;
						}

						$part_url = '';
						if (
							! empty( $item['item'] )
							&& is_array( $item['item'] )
							&& ! empty( $item['item']['url'] )
						) {
							$part_url = $item['item']['url'];
						} elseif ( ! empty( $item['url'] ) ) {
							$part_url = $item['url'];
						}

						if ( '' === $part_url ) {
							continue;
						}

						$schema['hasPart'][] = array(
							'name' => $item['name'],
							'url'  => $part_url,
						);
					}
					if ( empty( $schema['hasPart'] ) ) {
						unset( $schema['hasPart'] );
					}
				}
				break;
		}

		return $schema;
	}

	/**
	 * FAQPage スキーマ
	 *
	 * @param array  $context        ページ情報
	 * @param string $language_value 言語
	 * @param string $webpage_id     WebPage ID
	 *
	 * @return array
	 */
	private function build_faq_schema( array $context, $language_value, $webpage_id, array $options ) {
		$override = $this->get_context_override( $options, $context );
		if ( empty( $override['faq_enabled'] ) ) {
			return array();
		}

		if ( empty( $context['post_id'] ) ) {
			return array();
		}

		$meta = get_post_meta( $context['post_id'], '_ai_search_schema_meta', true );
		if ( ! is_array( $meta ) ) {
			return array();
		}

			$question_class = isset( $meta['faq_question_class'] )
				? sanitize_text_field( $meta['faq_question_class'] )
				: '';
			$answer_class   = isset( $meta['faq_answer_class'] )
				? sanitize_text_field( $meta['faq_answer_class'] )
				: '';

		if ( '' === $question_class || '' === $answer_class ) {
			return array();
		}

		$extractor = new AI_Search_Schema_Faq_Extractor();
		$faqs      = $extractor->extract_faqs( $question_class, $answer_class );
		if ( empty( $faqs ) ) {
			return array();
		}

		$entities = array();
		foreach ( $faqs as $faq ) {
			$question_text = isset( $faq['question'] ) ? wp_strip_all_tags( $faq['question'] ) : '';
			$answer_text   = isset( $faq['answer'] ) ? wp_kses_post( $faq['answer'] ) : '';

			if ( '' === $question_text || '' === $answer_text ) {
				continue;
			}

			$entities[] = array(
				'@type'          => 'Question',
				'name'           => $question_text,
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $answer_text,
				),
			);
		}

		if ( empty( $entities ) ) {
			return array();
		}

		$schema = array(
			'@type'            => 'FAQPage',
			'@id'              => $context['url'] . '#faq',
			'url'              => $context['url'],
			'name'             => $context['title'],
			'mainEntity'       => $entities,
			'mainEntityOfPage' => array(
				'@id' => $webpage_id,
			),
		);

		if ( $language_value ) {
			$schema['inLanguage'] = $language_value;
		}

		return $schema;
	}

	/**
	 * アイキャッチ ImageObject
	 */
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

	/**
	 * Article型のスキーマを生成
	 *
	 * @param int         $post_id      投稿ID
	 * @param array       $options      設定オプション
	 * @param mixed       $language     言語情報
	 * @param string|null $publisher_id 参照する@id
	 * @param string      $schema_type  Article系のタイプ
	 * @param string|null $webpage_id   mainEntityOfPage参照
	 * @return array
	 */
	private function get_article_schema(
		$post_id = null,
		$options = array(),
		$language = null,
		$publisher_id = null,
		$schema_type = 'Article',
		$webpage_id = null
	) {
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		$post = $post_id ? get_post( $post_id ) : null;
		if ( ! $post ) {
			return array(
				'@type'            => $schema_type,
				'headline'         => get_bloginfo( 'name' ),
				'mainEntityOfPage' => array(
					'@type' => 'WebPage',
					'@id'   => $webpage_id ? $webpage_id : get_site_url(),
				),
			);
		}

		$author_id = $post->post_author;
		$headline  = wp_strip_all_tags( get_the_title( $post_id ) );
		if ( '' === $headline ) {
			$headline = wp_trim_words( wp_strip_all_tags( $post->post_content ), 12, '…' );
		}

		$article_schema = array(
			'@type'            => $schema_type,
			'headline'         => $headline,
			'author'           => array(
				'@type' => 'Person',
				'name'  => get_the_author_meta( 'display_name', $author_id ),
				'url'   => get_author_posts_url( $author_id ),
			),
			'datePublished'    => get_the_date( 'c', $post_id ),
			'dateModified'     => get_the_modified_date( 'c', $post_id ),
			'publisher'        => $this->get_publisher_schema( $options, $publisher_id ),
			'mainEntityOfPage' => array(
				'@type' => 'WebPage',
				'@id'   => $webpage_id ? $webpage_id : get_permalink( $post_id ),
			),
		);

		if ( $language ) {
			$article_schema['inLanguage'] = $language;
		}

		$image = $this->get_featured_image_object( $post_id );
		if ( ! empty( $image ) ) {
			$article_schema['image'] = $image;
		}

		$excerpt = get_the_excerpt( $post_id );
		if ( ! empty( $excerpt ) ) {
			$article_schema['description'] = wp_strip_all_tags( $excerpt );
		}

		return $article_schema;
	}

	/**
	 * Publisher スキーマ
	 *
	 * @param array       $options      設定オプション
	 * @param string|null $publisher_id 参照する@id
	 * @return array
	 */
	private function get_publisher_schema( $options = array(), $publisher_id = null ) {
		if ( $publisher_id ) {
			return array(
				'@id' => $publisher_id,
			);
		}

		$publisher_entity = ! empty( $options['publisher_entity'] ) ? $options['publisher_entity'] : 'Organization';
		if ( 'Organization' !== $publisher_entity ) {
			$publisher_entity = 'Organization';
		}

		$publisher = array(
			'@type' => $publisher_entity,
			'name'  => ! empty( $options['company_name'] ) ? $options['company_name'] : get_bloginfo( 'name' ),
		);

		if ( 'Organization' === $publisher_entity ) {
			$logo_url = ! empty( $options['logo_url'] ) ? esc_url( $options['logo_url'] ) : '';
			if ( $logo_url ) {
				$logo    = array(
					'@type' => 'ImageObject',
					'url'   => $logo_url,
				);
				$logo_id = ! empty( $options['logo_id'] ) ? absint( $options['logo_id'] ) : 0;
				if ( $logo_id ) {
					$logo_data = wp_get_attachment_image_src( $logo_id, 'full' );
					if ( $logo_data ) {
						$logo['width']  = (int) $logo_data[1];
						$logo['height'] = (int) $logo_data[2];
					}
				}
				$publisher['logo'] = $logo;
			}
		} else {
			$publisher['url'] = ! empty( $options['site_url'] ) ? esc_url( $options['site_url'] ) : get_site_url();
		}

		return $publisher;
	}

	/**
	 * SNSアカウントからURL一覧を生成
	 *
	 * @param array $links
	 * @return array
	 */
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

	/**
	 * 言語配列を解決
	 *
	 * @param array $options 設定オプション
	 * @return array
	 */
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

	/**
	 * BCP47 形式に整形
	 *
	 * @param string $language_tag 言語タグ
	 * @return string
	 */
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

	/**
	 * 他SEOプラグインのスキーマ出力を制御
	 *
	 * @return void
	 */
	public function maybe_toggle_competing_schema_plugins( ?array $context = null ) {
		$options         = $this->get_normalized_options();
		$should_suppress = ( 'ais' === $this->get_schema_priority_value( $options, $context ) );

		if ( $should_suppress ) {
			$this->suppress_external_schema_plugins();
			return;
		}

		$this->restore_external_schema_plugins();
	}

	/**
	 * 現在のページ文脈に応じて優先度を再評価
	 *
	 * @return void
	 */
	public function apply_schema_priority_for_current_context() {
		$context = $this->resolve_page_context();
		$this->maybe_toggle_competing_schema_plugins( $context );
	}

	/**
	 * 競合プラグインのスキーマを抑止
	 */
	private function suppress_external_schema_plugins() {
		if ( $this->suppressing_external_schema ) {
			return;
		}

				$priority = 99;

				$options       = $this->get_normalized_options();
				$is_debug_mode = ! empty( $options['debug_mode'] );

		$this->external_schema_filters = array(
			array(
				'hook'          => 'wpseo_json_ld_output',
				'callback'      => '__return_false',
				'priority'      => $priority,
				'accepted_args' => 1,
			),
			array(
				'hook'          => 'wpseo_schema_graph_pieces',
				'callback'      => '__return_empty_array',
				'priority'      => $priority,
				'accepted_args' => 2,
			),
			array(
				'hook'          => 'rank_math/json_ld',
				'callback'      => array( $this, 'filter_return_empty_array' ),
				'priority'      => $priority,
				'accepted_args' => 1,
			),
			array(
				'hook'          => 'rank_math/frontend/json_ld',
				'callback'      => array( $this, 'filter_return_empty_array' ),
				'priority'      => $priority,
				'accepted_args' => 1,
			),
			array(
				'hook'          => 'rank_math/json_ld/disable',
				'callback'      => array( $this, 'filter_return_true' ),
				'priority'      => $priority,
				'accepted_args' => 1,
			),
			array(
				'hook'          => 'aioseo_schema_disable_output',
				'callback'      => '__return_true',
				'priority'      => $priority,
				'accepted_args' => 1,
			),
			array(
				'hook'          => 'aioseo_schema_disable',
				'callback'      => '__return_true',
				'priority'      => $priority,
				'accepted_args' => 1,
			),
			array(
				'hook'          => 'aioseo_schema_enabled',
				'callback'      => array( $this, 'filter_return_false' ),
				'priority'      => $priority,
				'accepted_args' => 1,
			),
			array(
				'hook'          => 'aioseo_schema_graph',
				'callback'      => array( $this, 'filter_return_empty_array' ),
				'priority'      => $priority,
				'accepted_args' => 1,
			),
			array(
				'hook'          => 'aioseo_schema_graph_data',
				'callback'      => array( $this, 'filter_return_empty_array' ),
				'priority'      => $priority,
				'accepted_args' => 1,
			),
			array(
				'hook'          => 'aioseo_schema_graph_output',
				'callback'      => array( $this, 'filter_return_empty_string' ),
				'priority'      => $priority,
				'accepted_args' => 1,
			),
			array(
				'hook'          => 'aioseo_schema_output',
				'callback'      => array( $this, 'filter_return_empty_string' ),
				'priority'      => $priority,
				'accepted_args' => 1,
			),
			array(
				'hook'          => 'aioseo_schema_markup',
				'callback'      => array( $this, 'filter_return_empty_string' ),
				'priority'      => $priority,
				'accepted_args' => 1,
			),
			array(
				'hook'          => 'aioseo_schema_script_output',
				'callback'      => array( $this, 'filter_return_empty_string' ),
				'priority'      => $priority,
				'accepted_args' => 1,
			),
		);

		foreach ( $this->external_schema_filters as $filter ) {
			add_filter( $filter['hook'], $filter['callback'], $filter['priority'], $filter['accepted_args'] );
		}

		if ( $is_debug_mode ) {
				$this->enable_head_schema_buffer();
				$this->enable_global_schema_buffer();
		}
				$this->suppressing_external_schema = true;
	}

	/**
	 * 抑止していたフィルタを解除
	 */
	private function restore_external_schema_plugins() {
		if ( ! $this->suppressing_external_schema ) {
			return;
		}

		foreach ( $this->external_schema_filters as $filter ) {
			remove_filter( $filter['hook'], $filter['callback'], $filter['priority'] );
		}

		$this->disable_head_schema_buffer();
		$this->disable_global_schema_buffer();
		$this->external_schema_filters     = array();
		$this->suppressing_external_schema = false;
	}

	/**
	 * bool(false) を返す汎用コールバック
	 *
	 * @return bool
	 */
	public function filter_return_false( $_value = null ) {
		unset( $_value );
		return false;
	}

	/**
	 * bool(true) を返す汎用コールバック
	 *
	 * @return bool
	 */
	public function filter_return_true( $_value = null ) {
		unset( $_value );
		return true;
	}

	/**
	 * 空配列を返す汎用コールバック
	 *
	 * @return array
	 */
	public function filter_return_empty_array( $_value = null ) {
		unset( $_value );
		return array();
	}

	/**
	 * 空文字列を返す汎用コールバック
	 *
	 * @return string
	 */
	public function filter_return_empty_string( $_value = null ) {
		unset( $_value );
		return '';
	}

	/**
	 * wp_head の出力をバッファリングして競合スキーマを除去
	 *
	 * @return void
	 */
	private function enable_head_schema_buffer() {
		if ( $this->head_buffer_enabled || is_admin() ) {
			return;
		}

		add_action( 'wp_head', array( $this, 'begin_head_schema_buffer' ), 0 );
		add_action( 'wp_head', array( $this, 'flush_head_schema_buffer' ), PHP_INT_MAX );
		$this->head_buffer_enabled = true;
	}

	/**
	 * wp_head バッファリングを解除
	 *
	 * @return void
	 */
	private function disable_head_schema_buffer() {
		if ( ! $this->head_buffer_enabled ) {
			return;
		}

		remove_action( 'wp_head', array( $this, 'begin_head_schema_buffer' ), 0 );
		remove_action( 'wp_head', array( $this, 'flush_head_schema_buffer' ), PHP_INT_MAX );
		$this->head_buffer_enabled = false;
	}

	/**
	 * ページ全体をバッファリングして競合スキーマを除去
	 *
	 * @return void
	 */
	private function enable_global_schema_buffer() {
		if ( $this->global_buffer_enabled || is_admin() ) {
			return;
		}

		add_action( 'template_redirect', array( $this, 'begin_global_schema_buffer' ), PHP_INT_MIN );
		add_action( 'shutdown', array( $this, 'flush_global_schema_buffer' ), 0 );
		$this->global_buffer_enabled = true;
	}

	/**
	 * ページ全体バッファリングを解除
	 *
	 * @return void
	 */
	private function disable_global_schema_buffer() {
		if ( ! $this->global_buffer_enabled ) {
			return;
		}

		remove_action( 'template_redirect', array( $this, 'begin_global_schema_buffer' ), PHP_INT_MIN );
		remove_action( 'shutdown', array( $this, 'flush_global_schema_buffer' ), 0 );

		if ( $this->global_buffer_active ) {
			$this->flush_global_schema_buffer();
		}

		$this->global_buffer_enabled = false;
	}

	/**
	 * wp_head 出力のバッファを開始
	 *
	 * @return void
	 */
	public function begin_head_schema_buffer() {
		if ( $this->head_buffer_active ) {
			return;
		}

		$this->head_buffer_active = true;
		ob_start();
	}

	/**
	 * バッファをフラッシュし、競合スキーマを除去
	 *
	 * @return void
	 */
	public function flush_head_schema_buffer() {
		if ( ! $this->head_buffer_active ) {
			return;
		}

		$buffer = ob_get_clean();

		$this->head_buffer_active = false;

		if ( false === $buffer ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Needed to reinject filtered head markup.
		echo $this->strip_competing_schema_markup( $buffer );
	}

	/**
	 * 全体出力のバッファを開始
	 *
	 * @return void
	 */
	public function begin_global_schema_buffer() {
		if ( $this->global_buffer_active || is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		$this->global_buffer_active = true;
		ob_start();
	}

	/**
	 * 全体出力のバッファをフラッシュ
	 *
	 * @return void
	 */
	public function flush_global_schema_buffer() {
		if ( ! $this->global_buffer_active ) {
			return;
		}

		$output                     = ob_get_clean();
		$this->global_buffer_active = false;

		if ( false === $output ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Filtering entire page output for competing schemas.
		echo $this->strip_competing_schema_markup( $output );
	}

	/**
	 * wp_head 出力から競合スキーマを除去
	 *
	 * @param string $buffer
	 * @return string
	 */
	private function strip_competing_schema_markup( $buffer ) {
		$pattern = '#<script[^>]*type=["\']application/ld\+json["\'][^>]*>.*?</script>#is';

		return preg_replace_callback(
			$pattern,
			function ( $matches ) {
				$script_tag = $matches[0];

				if ( preg_match( '#class=["\'][^"\']*ai-search-schema-graph[^"\']*["\']#i', $script_tag ) ) {
					return $script_tag;
				}

				$custom_patterns = apply_filters( 'ai_search_schema_competing_patterns', array() );

				foreach ( $custom_patterns as $custom_pattern ) {
					if ( is_string( $custom_pattern ) && '' !== $custom_pattern ) {
						$script_tag = preg_replace( $custom_pattern, '', $script_tag );
					}
				}

				if ( preg_match( '#class=["\'][^"\']*ai-search-schema-graph[^"\']*["\']#i', $script_tag ) ) {
					return $script_tag;
				}

				return '';
			},
			$buffer
		);
	}

	/**
	 * バリデーション失敗時の処理
	 *
	 * @param array $errors
	 * @param array $schema
	 * @return void
	 */
	private function handle_validation_failure( array $errors, array $schema ) {
		if ( empty( $errors ) ) {
			return;
		}

		$this->runtime_validation_errors = $errors;
		$this->set_admin_notice_errors( $errors );
		$this->log_validation_errors( $errors, $schema );

		$clean_schema = $this->remove_null_values( $schema );
		if ( ! empty( $clean_schema ) ) {
			$this->print_json_ld( $clean_schema );
			return;
		}

		$minimal_schema = $this->remove_null_values( $this->get_minimal_schema() );
		if ( ! empty( $minimal_schema ) ) {
			$this->print_json_ld( $minimal_schema );
		}
	}

	/**
	 * バリデーションエラーをログに記録
	 *
	 * @param array $errors
	 * @param array $schema
	 * @return void
	 */
	private function log_validation_errors( array $errors, array $schema ) {
		if ( empty( $errors ) ) {
			return;
		}

		$should_log = defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG;

		if ( $should_log ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Logged only when WP_DEBUG_LOG is enabled.
			error_log( '[AVC AEO Schema] Validation errors: ' . implode( ', ', $errors ) );
		}

		if ( $this->is_debug_mode() && $should_log ) {
			$schema_json = wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			if ( $schema_json ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Logged only when WP_DEBUG_LOG is enabled.
				error_log( '[AVC AEO Schema] Schema payload: ' . $schema_json );
			}
		}
	}

	/**
	 * バリデーションエラーを管理画面に通知
	 *
	 * @param array $errors
	 * @return void
	 */
	private function set_admin_notice_errors( array $errors ) {
		if ( ! $this->is_debug_mode() ) {
			return;
		}

		set_transient( 'ai_search_schema_last_validation_errors', $errors, 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * バリデーションエラーをクリア
	 */
	private function clear_validation_errors() {
		$this->runtime_validation_errors   = array();
		$this->runtime_validation_warnings = array();
		if ( $this->is_debug_mode() ) {
			delete_transient( 'ai_search_schema_last_validation_errors' );
		}
	}

	/**
	 * JSON-LD を出力
	 *
	 * @param array $schema
	 */
	private function print_json_ld( array $schema ) {
		if ( empty( $schema ) ) {
			return;
		}

		$json = wp_json_encode(
			$schema,
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);

		if ( false === $json ) {
			return;
		}

		$script_tag = sprintf(
			'<script type="application/ld+json" class="ai-search-schema">%s</script>',
			$json
		);

		printf(
			"<!-- AVC AEO Schema JSON-LD start -->\n%s\n<!-- AVC AEO Schema JSON-LD end -->\n",
			wp_kses(
				$script_tag,
				array(
					'script' => array(
						'type'  => true,
						'class' => true,
					),
				)
			)
		);
	}

	/**
	 * null 値を削除
	 *
	 * @param mixed $data
	 * @return mixed
	 */
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

	/**
	 * 最小限のスキーマ
	 *
	 * @return array
	 */
	private function get_minimal_schema() {
		return array(
			'@context' => 'https://schema.org',
			'@graph'   => array(
				array(
					'@type' => 'WebSite',
					'url'   => get_site_url(),
					'name'  => get_bloginfo( 'name' ),
				),
			),
		);
	}

	/**
	 * デバッグモード判定
	 *
	 * @return bool
	 */
	private function is_debug_mode() {
		return defined( 'WP_DEBUG' ) && WP_DEBUG;
	}

	/**
	 * 管理画面でのバリデーションエラー表示
	 */
	public function display_admin_validation_notice() {
		if ( ! is_admin() || ! $this->is_debug_mode() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$errors   = get_transient( 'ai_search_schema_last_validation_errors' );
		$warnings = $this->runtime_validation_warnings;
		if ( empty( $errors ) && empty( $warnings ) ) {
			return;
		}

		delete_transient( 'ai_search_schema_last_validation_errors' );

		echo '<div class="notice notice-info is-dismissible ai-search-schema-notice">';
		echo '<p><strong>'
			. esc_html__( 'AVC AEO Schema – Development Mode Warning', 'ai-search-schema' )
			. '</strong></p>';
		if ( ! empty( $errors ) ) {
			echo '<p>'
				. esc_html__( 'The following validation errors were detected:', 'ai-search-schema' )
				. '</p>';
			echo '<ul>';
			foreach ( (array) $errors as $error ) {
				echo '<li>' . esc_html( $error ) . '</li>';
			}
			echo '</ul>';
		}
		if ( ! empty( $warnings ) ) {
			echo '<p>'
				. esc_html__( 'Warnings:', 'ai-search-schema' )
				. '</p>';
			echo '<ul>';
			foreach ( (array) $warnings as $warning ) {
				echo '<li>' . esc_html( $warning ) . '</li>';
			}
			echo '</ul>';
		}
		echo '<p><small>'
			. esc_html__( 'This warning only appears while WP_DEBUG is true.', 'ai-search-schema' )
			. '</small></p>';
		echo '</div>';
	}

	/**
	 * フロントエンドでのバリデーションエラー表示
	 */
	public function display_frontend_validation_notice() {
		if (
			$this->is_debug_mode()
			&& ! empty( $this->runtime_validation_errors )
			&& ! is_admin()
			&& current_user_can( 'manage_options' )
		) {
			echo '<div class="notice notice-info ai-search-schema-notice"'
				. ' style="margin:20px;">';
			echo '<p><strong>'
				. esc_html__( 'AVC AEO Schema – Development Mode Warning', 'ai-search-schema' )
				. '</strong></p>';
			echo '<p>'
				. esc_html__( 'The following validation errors were detected:', 'ai-search-schema' )
				. '</p>';
			echo '<ul>';
			foreach ( $this->runtime_validation_errors as $error ) {
				echo '<li>' . esc_html( $error ) . '</li>';
			}
			echo '</ul>';
			if ( ! empty( $this->runtime_validation_warnings ) ) {
				echo '<p>'
					. esc_html__( 'Warnings:', 'ai-search-schema' )
					. '</p>';
				echo '<ul>';
				foreach ( $this->runtime_validation_warnings as $warning ) {
					echo '<li>' . esc_html( $warning ) . '</li>';
				}
				echo '</ul>';
			}
			echo '<p><small>'
				. esc_html__( 'This warning only appears while WP_DEBUG is true.', 'ai-search-schema' )
				. '</small></p>';
			echo '</div>';
		}

		$this->runtime_validation_errors = array();
	}
}

add_action( 'plugins_loaded', array( 'AI_Search_Schema', 'bootstrap_competing_schema_control' ), 20 );

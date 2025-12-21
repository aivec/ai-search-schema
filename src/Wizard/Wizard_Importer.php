<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName
/**
 * Wizard Importer - imports settings from other SEO plugins.
 *
 * @package Aivec\AiSearchSchema\Wizard
 */

namespace Aivec\AiSearchSchema\Wizard;

/**
 * Wizard_Importer class.
 */
class Wizard_Importer {

	/**
	 * Available import sources.
	 *
	 * @var array
	 */
	private $sources = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->sources = array(
			'yoast'    => array(
				'name'     => 'Yoast SEO',
				'callback' => array( $this, 'import_yoast' ),
				'detect'   => array( $this, 'detect_yoast' ),
			),
			'rankmath' => array(
				'name'     => 'Rank Math',
				'callback' => array( $this, 'import_rankmath' ),
				'detect'   => array( $this, 'detect_rankmath' ),
			),
			'aioseo'   => array(
				'name'     => 'All in One SEO',
				'callback' => array( $this, 'import_aioseo' ),
				'detect'   => array( $this, 'detect_aioseo' ),
			),
		);

		$this->sources = apply_filters( 'ai_search_schema_wizard_import_sources', $this->sources );
	}

	/**
	 * Get available import sources with detection status.
	 *
	 * @return array
	 */
	public function get_available_sources() {
		$available = array();

		foreach ( $this->sources as $key => $source ) {
			$detected = false;
			$data     = array();

			if ( is_callable( $source['detect'] ) ) {
				$detected = call_user_func( $source['detect'] );
			}

			if ( $detected ) {
				$data = $this->preview_import( $key );
			}

			$available[ $key ] = array(
				'name'     => $source['name'],
				'detected' => $detected,
				'preview'  => $data,
			);
		}

		return $available;
	}

	/**
	 * Import data from a source.
	 *
	 * @param string $source Source key.
	 * @return array|\WP_Error Imported data or error.
	 */
	public function import( $source ) {
		if ( ! isset( $this->sources[ $source ] ) ) {
			return new \WP_Error( 'invalid_source', __( 'Invalid import source', 'ai-search-schema' ) );
		}

		$callback = $this->sources[ $source ]['callback'];

		if ( ! is_callable( $callback ) ) {
			return new \WP_Error( 'invalid_callback', __( 'Import callback not found', 'ai-search-schema' ) );
		}

		return call_user_func( $callback );
	}

	/**
	 * Preview what would be imported.
	 *
	 * @param string $source Source key.
	 * @return array Preview data.
	 */
	public function preview_import( $source ) {
		if ( ! isset( $this->sources[ $source ] ) ) {
			return array();
		}

		$method = 'preview_' . $source;
		if ( method_exists( $this, $method ) ) {
			return call_user_func( array( $this, $method ) );
		}

		return array();
	}

	// ===== Yoast SEO =====

	/**
	 * Detect Yoast SEO.
	 *
	 * @return bool
	 */
	public function detect_yoast() {
		return defined( 'WPSEO_VERSION' ) || get_option( 'wpseo_titles' );
	}

	/**
	 * Preview Yoast import.
	 *
	 * @return array
	 */
	public function preview_yoast() {
		$titles = get_option( 'wpseo_titles', array() );
		$social = get_option( 'wpseo_social', array() );

		$preview = array();

		if ( ! empty( $titles['company_name'] ) ) {
			$preview['company_name'] = $titles['company_name'];
		}

		if ( ! empty( $titles['company_logo'] ) ) {
			$preview['logo'] = __( 'Set', 'ai-search-schema' );
		}

		$social_profiles = array();
		if ( ! empty( $social['facebook_site'] ) ) {
			$social_profiles[] = 'Facebook';
		}
		if ( ! empty( $social['twitter_site'] ) ) {
			$social_profiles[] = 'X (Twitter)';
		}
		if ( ! empty( $social['instagram_url'] ) ) {
			$social_profiles[] = 'Instagram';
		}
		if ( ! empty( $social['youtube_url'] ) ) {
			$social_profiles[] = 'YouTube';
		}

		if ( ! empty( $social_profiles ) ) {
			$preview['social'] = implode( ', ', $social_profiles );
		}

		return $preview;
	}

	/**
	 * Import from Yoast SEO.
	 *
	 * @return array Imported data mapped to wizard steps.
	 */
	public function import_yoast() {
		$titles = get_option( 'wpseo_titles', array() );
		$social = get_option( 'wpseo_social', array() );

		$imported = array(
			'basics' => array(),
		);

		// Company/Organization name.
		if ( ! empty( $titles['company_name'] ) ) {
			$imported['basics']['company_name'] = sanitize_text_field( $titles['company_name'] );
		}

		// Website name (use company name as fallback).
		if ( ! empty( $titles['website_name'] ) ) {
			$imported['basics']['site_name'] = sanitize_text_field( $titles['website_name'] );
		} elseif ( ! empty( $titles['company_name'] ) ) {
			$imported['basics']['site_name'] = sanitize_text_field( $titles['company_name'] );
		}

		// Site URL.
		$imported['basics']['site_url'] = home_url();

		// Logo.
		if ( ! empty( $titles['company_logo_id'] ) ) {
			$imported['basics']['logo_id'] = absint( $titles['company_logo_id'] );
		} elseif ( ! empty( $titles['company_logo'] ) ) {
			$attachment_id = attachment_url_to_postid( $titles['company_logo'] );
			if ( $attachment_id ) {
				$imported['basics']['logo_id'] = $attachment_id;
			}
		}

		// Organization type.
		if ( ! empty( $titles['company_or_person'] ) ) {
			if ( 'company' === $titles['company_or_person'] ) {
				$imported['type']['site_type'] = 'organization';
			}
		}

		return $imported;
	}

	// ===== Rank Math =====

	/**
	 * Detect Rank Math.
	 *
	 * @return bool
	 */
	public function detect_rankmath() {
		return defined( 'RANK_MATH_VERSION' ) || get_option( 'rank_math_general' );
	}

	/**
	 * Preview Rank Math import.
	 *
	 * @return array
	 */
	public function preview_rankmath() {
		$general = get_option( 'rank_math_general', array() );
		$titles  = get_option( 'rank_math_titles', array() );
		$local   = get_option( 'rank_math_local', array() );

		$preview = array();

		if ( ! empty( $titles['knowledgegraph_name'] ) ) {
			$preview['company_name'] = $titles['knowledgegraph_name'];
		}

		if ( ! empty( $titles['knowledgegraph_logo'] ) ) {
			$preview['logo'] = __( 'Set', 'ai-search-schema' );
		}

		if ( ! empty( $local['local_business_type'] ) ) {
			$preview['business_type'] = $local['local_business_type'];
		}

		if ( ! empty( $local['local_address'] ) ) {
			$preview['address'] = __( 'Set', 'ai-search-schema' );
		}

		return $preview;
	}

	/**
	 * Import from Rank Math.
	 *
	 * @return array Imported data mapped to wizard steps.
	 */
	public function import_rankmath() {
		$titles = get_option( 'rank_math_titles', array() );
		$local  = get_option( 'rank_math_local', array() );

		$imported = array(
			'basics'   => array(),
			'type'     => array(),
			'location' => array(),
		);

		// Organization name.
		if ( ! empty( $titles['knowledgegraph_name'] ) ) {
			$imported['basics']['company_name'] = sanitize_text_field( $titles['knowledgegraph_name'] );
			$imported['basics']['site_name']    = sanitize_text_field( $titles['knowledgegraph_name'] );
		}

		// Site URL.
		$imported['basics']['site_url'] = home_url();

		// Logo.
		if ( ! empty( $titles['knowledgegraph_logo_id'] ) ) {
			$imported['basics']['logo_id'] = absint( $titles['knowledgegraph_logo_id'] );
		}

		// Organization type.
		if ( ! empty( $titles['knowledgegraph_type'] ) ) {
			if ( 'company' === $titles['knowledgegraph_type'] ) {
				$imported['type']['site_type'] = 'organization';
			} elseif ( 'person' === $titles['knowledgegraph_type'] ) {
				$imported['type']['site_type'] = 'organization';
			}
		}

		// Local business.
		if ( ! empty( $local['local_business_type'] ) ) {
			$imported['type']['site_type']     = 'local_business';
			$imported['type']['business_type'] = sanitize_text_field( $local['local_business_type'] );
		}

		// Address.
		if ( ! empty( $local['local_address'] ) && is_array( $local['local_address'] ) ) {
			$address = $local['local_address'];

			if ( ! empty( $address['postalCode'] ) ) {
				$imported['location']['postal_code'] = sanitize_text_field( $address['postalCode'] );
			}
			if ( ! empty( $address['addressRegion'] ) ) {
				$imported['location']['prefecture'] = sanitize_text_field( $address['addressRegion'] );
			}
			if ( ! empty( $address['addressLocality'] ) ) {
				$imported['location']['city'] = sanitize_text_field( $address['addressLocality'] );
			}
			if ( ! empty( $address['streetAddress'] ) ) {
				$imported['location']['street'] = sanitize_text_field( $address['streetAddress'] );
			}
		}

		// Phone.
		if ( ! empty( $local['local_phone'] ) ) {
			$imported['location']['phone'] = sanitize_text_field( $local['local_phone'] );
		}

		// Geo.
		if ( ! empty( $local['local_geo'] ) && is_array( $local['local_geo'] ) ) {
			$geo = $local['local_geo'];

			if ( ! empty( $geo['latitude'] ) ) {
				$imported['location']['latitude'] = floatval( $geo['latitude'] );
			}
			if ( ! empty( $geo['longitude'] ) ) {
				$imported['location']['longitude'] = floatval( $geo['longitude'] );
			}
		}

		return $imported;
	}

	// ===== All in One SEO =====

	/**
	 * Detect All in One SEO.
	 *
	 * @return bool
	 */
	public function detect_aioseo() {
		return defined( 'AIOSEO_VERSION' ) || get_option( 'aioseo_options' );
	}

	/**
	 * Preview AIOSEO import.
	 *
	 * @return array
	 */
	public function preview_aioseo() {
		$options = get_option( 'aioseo_options', array() );

		if ( is_string( $options ) ) {
			$options = json_decode( $options, true );
		}

		$preview = array();

		if ( ! empty( $options['searchAppearance']['global']['schema']['organizationName'] ) ) {
			$preview['company_name'] = $options['searchAppearance']['global']['schema']['organizationName'];
		}

		if ( ! empty( $options['searchAppearance']['global']['schema']['organizationLogo'] ) ) {
			$preview['logo'] = __( 'Set', 'ai-search-schema' );
		}

		return $preview;
	}

	/**
	 * Import from All in One SEO.
	 *
	 * @return array Imported data mapped to wizard steps.
	 */
	public function import_aioseo() {
		$options = get_option( 'aioseo_options', array() );

		if ( is_string( $options ) ) {
			$options = json_decode( $options, true );
		}

		$imported = array(
			'basics' => array(),
			'type'   => array(),
		);

		// Organization settings are nested.
		$schema = $options['searchAppearance']['global']['schema'] ?? array();

		// Organization name.
		if ( ! empty( $schema['organizationName'] ) ) {
			$imported['basics']['company_name'] = sanitize_text_field( $schema['organizationName'] );
			$imported['basics']['site_name']    = sanitize_text_field( $schema['organizationName'] );
		}

		// Site URL.
		$imported['basics']['site_url'] = home_url();

		// Logo.
		if ( ! empty( $schema['organizationLogo'] ) ) {
			$attachment_id = attachment_url_to_postid( $schema['organizationLogo'] );
			if ( $attachment_id ) {
				$imported['basics']['logo_id'] = $attachment_id;
			}
		}

		// Organization type.
		$imported['type']['site_type'] = 'organization';

		// Local business (AIOSEO Pro feature).
		if ( ! empty( $schema['localBusiness'] ) && is_array( $schema['localBusiness'] ) ) {
			$local = $schema['localBusiness'];

			$imported['type']['site_type'] = 'local_business';

			if ( ! empty( $local['businessType'] ) ) {
				$imported['type']['business_type'] = sanitize_text_field( $local['businessType'] );
			}
		}

		return $imported;
	}
}

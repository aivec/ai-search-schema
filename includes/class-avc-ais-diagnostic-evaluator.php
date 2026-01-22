<?php
/**
 * AI Search Schema - Diagnostic Evaluator
 *
 * Evaluates structured data against spec-defined criteria.
 * Follows Presence -> Validity -> Quality hierarchy.
 *
 * ## Judgment Philosophy
 *
 * ### Presence (存在チェック)
 * - Target schema type exists in JSON-LD?
 * - If not detected: ERROR
 *
 * ### Validity (妥当性チェック)
 * - JSON-LD parseable?
 * - Required fields present?
 * - If invalid/missing required: ERROR
 *
 * ### Quality (品質チェック)
 * - Recommended fields present?
 * - Minimum counts/lengths met?
 * - Consistency across fields?
 * - If not met: WARNING (default), ERROR only if errorOnFail=true
 *
 * @package AVC_AIS_Schema
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Diagnostic Evaluator Class
 */
class AVC_AIS_Diagnostic_Evaluator {

	/**
	 * Status constants
	 */
	const STATUS_INFO    = 'info';
	const STATUS_WARNING = 'warning';
	const STATUS_ERROR   = 'error';

	/**
	 * Evaluate a test item against structured data
	 *
	 * @param array  $spec         Test spec with judgment parameters.
	 * @param string $json_ld_raw  Raw JSON-LD string to evaluate.
	 * @param array  $context      Additional context (url, post_id, etc).
	 * @return array Evaluation result with status, message, and details.
	 */
	public static function evaluate( array $spec, $json_ld_raw = '', array $context = array() ) {
		$result = array(
			'status'  => self::STATUS_INFO,
			'message' => '',
			'details' => array(),
			'phase'   => '', // presence, validity, or quality.
		);

		// Phase 1: Presence Check.
		$presence_result = self::check_presence( $spec, $json_ld_raw, $context );
		if ( self::STATUS_ERROR === $presence_result['status'] ) {
			return array_merge( $result, $presence_result, array( 'phase' => 'presence' ) );
		}
		$result['details'] = array_merge( $result['details'], $presence_result['details'] );

		// Phase 2: Validity Check.
		$validity_result = self::check_validity( $spec, $json_ld_raw, $presence_result['parsed_data'] ?? array() );
		if ( self::STATUS_ERROR === $validity_result['status'] ) {
			return array_merge( $result, $validity_result, array( 'phase' => 'validity' ) );
		}
		$result['details'] = array_merge( $result['details'], $validity_result['details'] );

		// Phase 3: Quality Check.
		$quality_result    = self::check_quality( $spec, $presence_result['parsed_data'] ?? array(), $context );
		$result            = array_merge(
			$result,
			array(
				'status'  => $quality_result['status'],
				'message' => $quality_result['message'],
				'phase'   => 'quality',
			)
		);
		$result['details'] = array_merge( $result['details'], $quality_result['details'] );

		// Upgrade to warning if validity had issues but not error.
		if ( self::STATUS_WARNING === $validity_result['status'] && self::STATUS_INFO === $result['status'] ) {
			$result['status'] = self::STATUS_WARNING;
		}

		// Final message construction.
		if ( empty( $result['message'] ) ) {
			$result['message'] = self::STATUS_INFO === $result['status']
				? __( 'All checks passed.', 'ai-search-schema' )
				: __( 'Some issues detected. See details.', 'ai-search-schema' );
		}

		return $result;
	}

	/**
	 * Phase 1: Presence Check
	 *
	 * @param array  $spec        Test spec.
	 * @param string $json_ld_raw Raw JSON-LD.
	 * @param array  $context     Context (reserved for future use).
	 * @return array Result.
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $context reserved for future.
	private static function check_presence( array $spec, $json_ld_raw, array $context ) {
		$result = array(
			'status'      => self::STATUS_INFO,
			'message'     => '',
			'details'     => array(),
			'parsed_data' => array(),
		);

		// Parse JSON-LD.
		if ( empty( $json_ld_raw ) ) {
			return array(
				'status'  => self::STATUS_ERROR,
				'message' => __( 'No JSON-LD structured data found on this page.', 'ai-search-schema' ),
				'details' => array( 'json_ld_missing' => true ),
			);
		}

		$parsed = self::parse_json_ld( $json_ld_raw );
		if ( false === $parsed ) {
			return array(
				'status'  => self::STATUS_ERROR,
				'message' => __( 'JSON-LD could not be parsed. Invalid format.', 'ai-search-schema' ),
				'details' => array( 'json_ld_parse_error' => true ),
			);
		}

		$result['parsed_data'] = $parsed;

		// Check for target type if specified.
		$target_type = $spec['targetType'] ?? '';
		if ( ! empty( $target_type ) ) {
			$found_type = self::find_type_in_graph( $parsed, $target_type );
			if ( ! $found_type ) {
				return array(
					'status'  => self::STATUS_ERROR,
					'message' => sprintf(
						/* translators: %s: schema type name */
						__( 'Target schema type "%s" not found in JSON-LD.', 'ai-search-schema' ),
						$target_type
					),
					'details' => array(
						'target_type'    => $target_type,
						'type_not_found' => true,
					),
				);
			}
			$result['details']['target_type_found'] = $target_type;
		}

		return $result;
	}

	/**
	 * Phase 2: Validity Check
	 *
	 * @param array  $spec        Test spec.
	 * @param string $json_ld_raw Raw JSON-LD (unused but kept for interface consistency).
	 * @param array  $parsed_data Parsed JSON-LD data.
	 * @return array Result.
	 */
	private static function check_validity( array $spec, $json_ld_raw, array $parsed_data ) {
		$result = array(
			'status'  => self::STATUS_INFO,
			'message' => '',
			'details' => array(),
		);

		$target_type = $spec['targetType'] ?? '';
		$required    = $spec['required'] ?? array();

		if ( empty( $required ) || empty( $target_type ) ) {
			return $result;
		}

		// Find the target type data.
		$type_data = self::get_type_data( $parsed_data, $target_type );
		if ( empty( $type_data ) ) {
			return $result; // Already handled in presence check.
		}

		// Check required fields.
		$missing_required = array();
		foreach ( $required as $field ) {
			$value = self::get_nested_value( $type_data, $field );
			if ( empty( $value ) && 0 !== $value && '0' !== $value ) {
				$missing_required[] = $field;
			}
		}

		if ( ! empty( $missing_required ) ) {
			return array(
				'status'  => self::STATUS_ERROR,
				'message' => sprintf(
					/* translators: %s: comma-separated list of field names */
					__( 'Required fields missing: %s', 'ai-search-schema' ),
					implode( ', ', $missing_required )
				),
				'details' => array(
					'missing_required' => $missing_required,
				),
			);
		}

		$result['details']['required_fields_ok'] = true;
		return $result;
	}

	/**
	 * Phase 3: Quality Check
	 *
	 * @param array $spec        Test spec.
	 * @param array $parsed_data Parsed JSON-LD data.
	 * @param array $context     Context (reserved for future use).
	 * @return array Result.
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $context reserved for future.
	private static function check_quality( array $spec, array $parsed_data, array $context ) {
		$result = array(
			'status'  => self::STATUS_INFO,
			'message' => '',
			'details' => array(),
		);

		$target_type     = $spec['targetType'] ?? '';
		$recommended     = $spec['recommended'] ?? array();
		$min_count       = $spec['minCount'] ?? 0;
		$min_count_field = $spec['minCountField'] ?? '';
		$min_len         = $spec['minLen'] ?? 0;
		$min_len_field   = $spec['minLenField'] ?? '';
		$error_on_fail   = ! empty( $spec['errorOnFail'] );

		$issues = array();

		$type_data = self::get_type_data( $parsed_data, $target_type );

		// Check recommended fields.
		if ( ! empty( $recommended ) && ! empty( $type_data ) ) {
			$missing_recommended = array();
			foreach ( $recommended as $field ) {
				$value = self::get_nested_value( $type_data, $field );
				if ( empty( $value ) && 0 !== $value && '0' !== $value ) {
					$missing_recommended[] = $field;
				}
			}
			if ( ! empty( $missing_recommended ) ) {
				$issues[] = array(
					'type'    => 'missing_recommended',
					'fields'  => $missing_recommended,
					'message' => sprintf(
						/* translators: %s: comma-separated list of field names */
						__( 'Recommended fields missing: %s', 'ai-search-schema' ),
						implode( ', ', $missing_recommended )
					),
				);
			}
		}

		// Check minCount.
		if ( $min_count > 0 && ! empty( $min_count_field ) && ! empty( $type_data ) ) {
			$count_value  = self::get_nested_value( $type_data, $min_count_field );
			$actual_count = is_array( $count_value ) ? count( $count_value ) : 0;
			if ( $actual_count < $min_count ) {
				$issues[] = array(
					'type'    => 'min_count_not_met',
					'field'   => $min_count_field,
					'min'     => $min_count,
					'actual'  => $actual_count,
					'message' => sprintf(
						/* translators: 1: field name, 2: minimum count, 3: actual count */
						__( '%1$s has %3$d items (minimum: %2$d).', 'ai-search-schema' ),
						$min_count_field,
						$min_count,
						$actual_count
					),
				);
			}
		}

		// Check minLen.
		if ( $min_len > 0 && ! empty( $min_len_field ) && ! empty( $type_data ) ) {
			$len_value  = self::get_nested_value( $type_data, $min_len_field );
			$actual_len = is_string( $len_value ) ? mb_strlen( $len_value ) : 0;
			if ( $actual_len < $min_len ) {
				$issues[] = array(
					'type'    => 'min_len_not_met',
					'field'   => $min_len_field,
					'min'     => $min_len,
					'actual'  => $actual_len,
					'message' => sprintf(
						/* translators: 1: field name, 2: minimum length, 3: actual length */
						__( '%1$s is %3$d characters (minimum: %2$d).', 'ai-search-schema' ),
						$min_len_field,
						$min_len,
						$actual_len
					),
				);
			}
		}

		// Check consistency group if specified.
		$consistency_group = $spec['consistencyGroup'] ?? array();
		if ( ! empty( $consistency_group ) && ! empty( $type_data ) ) {
			$consistency_result = self::check_consistency( $type_data, $consistency_group );
			if ( ! empty( $consistency_result['issues'] ) ) {
				$issues = array_merge( $issues, $consistency_result['issues'] );
			}
		}

		// Determine status based on issues.
		if ( ! empty( $issues ) ) {
			$result['status']                    = $error_on_fail ? self::STATUS_ERROR : self::STATUS_WARNING;
			$result['message']                   = $issues[0]['message'] ?? __(
				'Quality issues detected.',
				'ai-search-schema'
			);
			$result['details']['quality_issues'] = $issues;
		}

		return $result;
	}

	/**
	 * Check consistency within a group of fields
	 *
	 * @param array $data   Schema data.
	 * @param array $fields Fields to check for consistency.
	 * @return array Result with issues array.
	 */
	private static function check_consistency( array $data, array $fields ) {
		$result = array( 'issues' => array() );

		// Extract values for comparison.
		$values = array();
		foreach ( $fields as $field ) {
			$value = self::get_nested_value( $data, $field );
			if ( ! empty( $value ) ) {
				$values[ $field ] = is_array( $value ) ? wp_json_encode( $value ) : (string) $value;
			}
		}

		// For now, we just report if some fields are missing while others are set.
		// More sophisticated consistency checks can be added.
		$set_count   = count( $values );
		$total_count = count( $fields );

		if ( $set_count > 0 && $set_count < $total_count ) {
			$missing            = array_diff( $fields, array_keys( $values ) );
			$result['issues'][] = array(
				'type'    => 'partial_consistency',
				'fields'  => $fields,
				'missing' => $missing,
				'message' => sprintf(
					/* translators: %s: comma-separated list of field names */
					__( 'Partial data: some fields in group are missing: %s', 'ai-search-schema' ),
					implode( ', ', $missing )
				),
			);
		}

		return $result;
	}

	/**
	 * Parse JSON-LD string
	 *
	 * @param string $json_ld_raw Raw JSON-LD string.
	 * @return array|false Parsed array or false on failure.
	 */
	private static function parse_json_ld( $json_ld_raw ) {
		// Handle script tags.
		if ( strpos( $json_ld_raw, '<script' ) !== false ) {
			$pattern = '/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is';
			preg_match_all( $pattern, $json_ld_raw, $matches );
			if ( ! empty( $matches[1] ) ) {
				$all_data = array();
				foreach ( $matches[1] as $json_str ) {
					$decoded = json_decode( trim( $json_str ), true );
					if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
						$all_data[] = $decoded;
					}
				}
				if ( ! empty( $all_data ) ) {
					return count( $all_data ) === 1 ? $all_data[0] : array( '@graph' => $all_data );
				}
			}
			return false;
		}

		// Direct JSON.
		$decoded = json_decode( $json_ld_raw, true );
		if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
			return $decoded;
		}

		return false;
	}

	/**
	 * Find a type in the JSON-LD graph
	 *
	 * @param array  $data JSON-LD data.
	 * @param string $type Type to find.
	 * @return bool True if found.
	 */
	private static function find_type_in_graph( array $data, $type ) {
		// Check direct type.
		if ( isset( $data['@type'] ) ) {
			$types = is_array( $data['@type'] ) ? $data['@type'] : array( $data['@type'] );
			if ( in_array( $type, $types, true ) ) {
				return true;
			}
		}

		// Check @graph.
		if ( isset( $data['@graph'] ) && is_array( $data['@graph'] ) ) {
			foreach ( $data['@graph'] as $item ) {
				if ( self::find_type_in_graph( $item, $type ) ) {
					return true;
				}
			}
		}

		// Check nested arrays.
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) && '@' !== substr( $key, 0, 1 ) ) {
				if ( self::find_type_in_graph( $value, $type ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Get data for a specific type from JSON-LD
	 *
	 * @param array  $data JSON-LD data.
	 * @param string $type Type to get.
	 * @return array|null Type data or null.
	 */
	private static function get_type_data( array $data, $type ) {
		// Check direct type.
		if ( isset( $data['@type'] ) ) {
			$types = is_array( $data['@type'] ) ? $data['@type'] : array( $data['@type'] );
			if ( in_array( $type, $types, true ) ) {
				return $data;
			}
		}

		// Check @graph.
		if ( isset( $data['@graph'] ) && is_array( $data['@graph'] ) ) {
			foreach ( $data['@graph'] as $item ) {
				$result = self::get_type_data( $item, $type );
				if ( $result ) {
					return $result;
				}
			}
		}

		return null;
	}

	/**
	 * Get nested value from array using dot notation
	 *
	 * @param array  $data Array to search.
	 * @param string $path Dot-notation path (e.g., "address.streetAddress").
	 * @return mixed Value or null.
	 */
	private static function get_nested_value( array $data, $path ) {
		$keys    = explode( '.', $path );
		$current = $data;

		foreach ( $keys as $key ) {
			if ( ! is_array( $current ) || ! array_key_exists( $key, $current ) ) {
				return null;
			}
			$current = $current[ $key ];
		}

		return $current;
	}

	/**
	 * Normalize legacy pass/fail to info/warning/error
	 *
	 * @param string $legacy_status Legacy status (pass, fail, pending).
	 * @return string Normalized status.
	 */
	public static function normalize_status( $legacy_status ) {
		switch ( $legacy_status ) {
			case 'pass':
				return self::STATUS_INFO;
			case 'fail':
				return self::STATUS_ERROR;
			case 'pending':
				return self::STATUS_WARNING;
			default:
				return self::STATUS_INFO;
		}
	}

	/**
	 * Create spec definition for common schema types
	 *
	 * @param string $type Schema type.
	 * @return array Spec definition.
	 */
	public static function get_default_spec( $type ) {
		$specs = array(
			'Organization'   => array(
				'targetType'  => 'Organization',
				'required'    => array( 'name' ),
				'recommended' => array( 'url', 'logo', 'sameAs' ),
			),
			'LocalBusiness'  => array(
				'targetType'  => 'LocalBusiness',
				'required'    => array( 'name', 'address' ),
				'recommended' => array( 'url', 'telephone', 'openingHours', 'geo' ),
			),
			'FAQPage'        => array(
				'targetType'    => 'FAQPage',
				'required'      => array( 'mainEntity' ),
				'minCountField' => 'mainEntity',
				'minCount'      => 1,
				'errorOnFail'   => true, // 0 Q&A is structural failure.
			),
			'Article'        => array(
				'targetType'  => 'Article',
				'required'    => array( 'headline', 'datePublished' ),
				'recommended' => array( 'author', 'image', 'dateModified' ),
			),
			'WebSite'        => array(
				'targetType'  => 'WebSite',
				'required'    => array( 'name', 'url' ),
				'recommended' => array( 'potentialAction' ),
			),
			'BreadcrumbList' => array(
				'targetType'    => 'BreadcrumbList',
				'required'      => array( 'itemListElement' ),
				'minCountField' => 'itemListElement',
				'minCount'      => 2,
			),
		);

		return $specs[ $type ] ?? array();
	}
}

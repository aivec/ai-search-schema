<?php
/**
 * FAQPage スキーマ生成クラス。
 */
defined( 'ABSPATH' ) || exit;
class AVC_AIS_Type_FAQPage {
	public static function build( array $context, $language_value, $webpage_id, array $options ) {
		$override = self::get_context_override( $options, $context );
		if ( empty( $override['faq_enabled'] ) ) {
			return array();
		}

		if ( empty( $context['post_id'] ) ) {
			return array();
		}

		$meta = get_post_meta( $context['post_id'], '_avc_ais_meta', true );
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

		$extractor = new AVC_AIS_Faq_Extractor();
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

	private static function get_context_override( array $options, array $context ) {
		$global_breadcrumbs = true;
		if ( array_key_exists( 'avc_ais_breadcrumbs_schema_enabled', $options ) ) {
			$global_breadcrumbs = (bool) $options['avc_ais_breadcrumbs_schema_enabled'];
		} elseif ( array_key_exists( 'enable_breadcrumbs', $options ) ) {
			$global_breadcrumbs = (bool) $options['enable_breadcrumbs'];
		}

		$defaults = array(
			'schema_type'         => 'auto',
			'breadcrumbs_enabled' => $global_breadcrumbs,
			'faq_enabled'         => true,
			'schema_priority'     => $options['avc_ais_priority'] ?? 'avc',
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
}

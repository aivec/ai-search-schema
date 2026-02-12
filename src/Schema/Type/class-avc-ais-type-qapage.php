<?php
/**
 * QAPage スキーマ生成クラス。
 */
defined( 'ABSPATH' ) || exit;
class AVC_AIS_Type_QAPage {
	public static function build( array $context, $language_value, $webpage_id ) {
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

		$schema = array(
			'@type'            => 'QAPage',
			'@id'              => $context['url'] . '#qapage',
			'url'              => $context['url'],
			'name'             => $context['title'],
			'mainEntity'       => array(
				array(
					'@type'          => 'Question',
					'name'           => $context['title'],
					'acceptedAnswer' => array(
						'@type' => 'Answer',
						'text'  => $answer_text,
					),
				),
			),
			'mainEntityOfPage' => array(
				'@id' => $webpage_id,
			),
		);

		if ( $language_value ) {
			$schema['inLanguage'] = $language_value;
		}

		return $schema;
	}
}

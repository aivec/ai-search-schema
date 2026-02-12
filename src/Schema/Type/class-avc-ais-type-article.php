<?php
/**
 * Article系スキーマを生成するクラス。
 */
defined( 'ABSPATH' ) || exit;
class AVC_AIS_Type_Article {
	public static function build(
		$post_id = null,
		$options = array(),
		$language = null,
		$publisher_id = null,
		$schema_type = 'Article',
		$webpage_id = null,
		$image_object = array()
	) {
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		$post = $post_id ? get_post( $post_id ) : null;
		if ( ! $post ) {
			return array();
		}

			$author         = function_exists( 'get_user_by' ) ? get_user_by( 'id', $post->post_author ) : null;
			$headline       = wp_strip_all_tags( get_the_title( $post_id ) );
			$excerpt        = get_the_excerpt( $post_id );
			$content        = trim( wp_strip_all_tags( $post->post_content ) );
			$has_image      = ! empty( $image_object );
			$has_publisher  = ! empty( $publisher_id )
				|| ! empty( $options['company_name'] )
				|| ! empty( $options['local_business_label'] );
			$date_published = get_the_date( 'c', $post_id );
			$date_modified  = get_the_modified_date( 'c', $post_id );
			$author_name    = function_exists( 'get_the_author_meta' )
				? get_the_author_meta( 'display_name', $post->post_author )
				: '';
		if ( empty( $author_name ) && $author ) {
			$author_name = isset( $author->display_name ) ? $author->display_name : '';
		}

		if ( '' === $headline ) {
			$headline = wp_trim_words( $content, 12, '…' );
		}

		$can_build = (
			! empty( $headline )
			&& ( $author instanceof WP_User || '' !== $author_name )
			&& ! empty( $date_published )
			&& ! empty( $date_modified )
			&& ( ! empty( $excerpt ) || ! empty( $content ) )
			&& $has_publisher
			&& $has_image
		);

		if ( ! $can_build ) {
			return array();
		}

		$article_schema = array(
			'@type'            => $schema_type,
			'headline'         => $headline,
			'author'           => array(
				'@type' => 'Person',
				'name'  => $author_name ? $author_name : ( $author->display_name ?? '' ),
				'url'   => get_author_posts_url( $author->ID ?? $post->post_author ),
			),
			'datePublished'    => $date_published,
			'dateModified'     => $date_modified,
			'publisher'        => self::get_publisher_schema( $options, $publisher_id ),
			'mainEntityOfPage' => array(
				'@type' => 'WebPage',
				'@id'   => $webpage_id ? $webpage_id : get_permalink( $post_id ),
			),
		);

		if ( $language ) {
			$article_schema['inLanguage'] = $language;
		}

		if ( ! empty( $image_object ) ) {
			$article_schema['image'] = $image_object;
		}

		if ( ! empty( $excerpt ) ) {
			$article_schema['description'] = wp_strip_all_tags( $excerpt );
		}

		return $article_schema;
	}

	private static function get_publisher_schema( $options = array(), $publisher_id = null ) {
		if ( $publisher_id ) {
			return array(
				'@id' => $publisher_id,
			);
		}

		$publisher_entity = ! empty( $options['publisher_entity'] ) ? $options['publisher_entity'] : 'Organization';
		if ( ! in_array( $publisher_entity, array( 'Organization', 'LocalBusiness' ), true ) ) {
			$publisher_entity = 'Organization';
		}

		$publisher_name = ! empty( $options['company_name'] ) ? $options['company_name'] : get_bloginfo( 'name' );
		if ( 'LocalBusiness' === $publisher_entity ) {
			$label         = ! empty( $options['local_business_label'] ) ? $options['local_business_label'] : '';
			$composed_name = trim( $publisher_name . ' ' . $label );
			if ( '' !== $composed_name ) {
				$publisher_name = $composed_name;
			}
		}

		$publisher = array(
			'@type' => $publisher_entity,
			'name'  => $publisher_name,
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
}

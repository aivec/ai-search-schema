<?php
/**
 * ページコンテキストやコンテンツ種別の解決を担当するクラス。
 */
defined( 'ABSPATH' ) || exit;
class AVC_AIS_ContentResolver {
	/**
	 * サイトURLを正規化
	 *
	 * @param array $options 設定オプション
	 * @return string
	 */
	public function normalize_site_url( array $options ) {
		$site = ! empty( $options['site_url'] ) ? $options['site_url'] : home_url( '/' );
		$site = trim( $site );
		if ( '' === $site ) {
			$site = home_url( '/' );
		}
		return rtrim( $site, '/' ) . '/';
	}

	/**
	 * 現在ページの情報を取得
	 *
	 * @return array
	 */
	public function resolve_page_context() {
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
			return $this->normalize_context_title(
				$this->apply_pagination_to_context( $context )
			);
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
			return $this->normalize_context_title(
				$this->apply_pagination_to_context( $context )
			);
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
			return $this->normalize_context_title(
				$this->apply_pagination_to_context( $context )
			);
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
			return $this->normalize_context_title(
				$this->apply_pagination_to_context( $context )
			);
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
			return $this->normalize_context_title(
				$this->apply_pagination_to_context( $context )
			);
		}

		if ( is_search() ) {
			$query           = get_search_query();
			$context['type'] = 'search';
			if ( $query ) {
				$context['title'] = sprintf(
					/* translators: %s: search query string. */
					__( 'Search results for "%s"', 'aivec-ai-search-schema' ),
					$query
				);
			} else {
				$context['title'] = __( 'Search results', 'aivec-ai-search-schema' );
			}
			$search_link       = get_search_link( $query );
			$context['url']    = is_wp_error( $search_link ) ? $this->get_current_url() : $search_link;
			$context['search'] = $query;
			return $this->normalize_context_title(
				$this->apply_pagination_to_context( $context )
			);
		}

		if ( is_post_type_archive() ) {
			$post_type = $this->get_query_var_safe( 'post_type' );
			if ( is_array( $post_type ) ) {
				$post_type = reset( $post_type );
			}
			$context['type']      = 'archive';
			$context['post_type'] = $post_type;
				$link             = $post_type && function_exists( 'get_post_type_archive_link' )
					? get_post_type_archive_link( $post_type )
					: '';
			$context['url']       = $link ? $link : $this->get_current_url();
			$context['title']     = get_the_archive_title();
			return $this->normalize_context_title(
				$this->apply_pagination_to_context( $context )
			);
		}

		if ( is_date() ) {
			$context['type']  = 'archive';
			$context['title'] = get_the_archive_title();
			$year             = $this->get_query_var_safe( 'year' );
			$month            = $this->get_query_var_safe( 'monthnum' );
			$day              = $this->get_query_var_safe( 'day' );
			if ( function_exists( 'get_day_link' ) && $year && $month && $day ) {
				$context['url'] = get_day_link( (int) $year, (int) $month, (int) $day );
			} elseif ( function_exists( 'get_month_link' ) && $year && $month ) {
				$context['url'] = get_month_link( (int) $year, (int) $month );
			} elseif ( function_exists( 'get_year_link' ) && $year ) {
				$context['url'] = get_year_link( (int) $year );
			}
			return $this->normalize_context_title(
				$this->apply_pagination_to_context( $context )
			);
		}

		if ( is_archive() ) {
			$context['type']  = 'archive';
			$context['title'] = get_the_archive_title();
			$context['url']   = $this->get_current_url();
		}

		return $this->normalize_context_title(
			$this->apply_pagination_to_context( $context )
		);
	}

	/**
	 * ページ文脈のタイトルとURLを正規化
	 *
	 * @param array $context コンテキスト
	 * @return array
	 */
	public function normalize_context_title( array $context ) {
		if ( empty( $context['title'] ) ) {
			$context['title'] = get_bloginfo( 'name' );
		}

		if ( empty( $context['url'] ) ) {
			$context['url'] = $this->get_current_url();
		}

		return $context;
	}

	/**
	 * ページ送りを考慮したURLに正規化する。
	 *
	 * @param array $context コンテキスト.
	 * @return array
	 */
	private function apply_pagination_to_context( array $context ) {
		$base_url = isset( $context['url'] ) ? $context['url'] : '';
		if ( '' === $base_url ) {
			return $context;
		}

		if ( is_singular() ) {
			$page = max( 1, (int) $this->get_query_var_safe( 'page', 1 ) );
			if ( $page > 1 ) {
				if ( function_exists( 'user_trailingslashit' ) ) {
					$context['url'] = user_trailingslashit(
						trailingslashit( $base_url ) . 'page/' . $page,
						'single_paged'
					);
				} else {
					$context['url'] = trailingslashit( $base_url ) . 'page/' . $page . '/';
				}
			}
			return $context;
		}

		$paged = max( 1, (int) $this->get_query_var_safe( 'paged', 1 ) );
		if ( $paged > 1 ) {
			if ( function_exists( 'get_pagenum_link' ) ) {
				$context['url'] = get_pagenum_link( $paged );
			} else {
				$context['url'] = trailingslashit( $base_url ) . 'page/' . $paged . '/';
			}
		}

		return $context;
	}

	/**
	 * get_query_var のラッパー（テスト環境対策）。
	 *
	 * @param string $key      クエリキー.
	 * @param mixed  $fallback デフォルト値.
	 * @return mixed
	 */
	private function get_query_var_safe( $key, $fallback = null ) {
		if ( function_exists( 'get_query_var' ) ) {
			$value = get_query_var( $key );
			if ( '' !== $value && null !== $value ) {
				return $value;
			}
		}
		return $fallback;
	}

	/**
	 * 現在のURLを推測
	 */
	public function get_current_url() {
		global $wp;

		if ( isset( $wp->request ) && $wp->request ) {
			return home_url( trailingslashit( $wp->request ) );
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
			: '/';
		if ( '' === $request_uri || '/' !== $request_uri[0] ) {
			$request_uri = '/' . ltrim( $request_uri, '/' );
		}

		return home_url( $request_uri );
	}

	/**
	 * WebPageタイプを判定
	 *
	 * @param array $context ページ情報
	 * @return array
	 */
	public function determine_webpage_types( array $context ) {
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
}

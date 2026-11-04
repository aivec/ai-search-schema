<?php
/**
 * llms.txt Generator
 *
 * Generates and serves llms.txt file to help AI systems understand site structure.
 *
 * @package AVC_AIS_Schema
 * @see https://llmstxt.org/
 */

/**
 * AVC_AIS_Llms_Txt class
 */
defined( 'ABSPATH' ) || exit;
class AVC_AIS_Llms_Txt {

	/**
	 * Option name for stored content.
	 */
	const OPTION_NAME = 'avc_ais_llms_txt';

	/**
	 * Option name for enabled flag.
	 */
	const OPTION_ENABLED = 'avc_ais_llms_txt_enabled';

	/**
	 * v1.3.2 が一度きりの掃除の記録に使っていた option 名。今は残っていれば消すだけ。
	 */
	const OPTION_SCRUBBED = 'avc_ais_llms_txt_scrubbed';

	/**
	 * Option name recording that broken URLs in saved content were repaired.
	 */
	const OPTION_URLS_REPAIRED = 'avc_ais_llms_txt_urls_repaired';

	/**
	 * Option name for URLs of recently deleted posts.
	 */
	const OPTION_DELETED_URLS = 'avc_ais_llms_txt_deleted_urls';

	/**
	 * Option name recording that the rewrite rule was checked after updating.
	 */
	const OPTION_RULE_RESTORED = 'avc_ais_llms_txt_rule_restored';

	/**
	 * /llms.txt の rewrite ルールの正規表現。
	 */
	const REWRITE_REGEX = '^llms\.txt$';

	/**
	 * 自動生成の一覧に載せる投稿タイプ。
	 */
	const LISTED_POST_TYPES = array( 'post', 'page' );

	/**
	 * 一覧の行の形。文中のリンクと区別するため、リストの項目の先頭にあるリンクだけを対象にする。
	 *
	 * llms.txt の仕様では「- [タイトル](URL): 説明」の説明は省略でき、字下げや番号付きのリスト、
	 * リンクのタイトルも書ける。タイトルのない投稿は自動生成で「- [](URL): 抜粋」になる。
	 */
	const ENTRY_PATTERN = '/^\s*(?:[-*+]|\d+[.)])\s+\[(.*?)\]\((\S*?)(?:\s+"[^"]*")?\):?/u';

	/**
	 * 削除した投稿の URL を覚えておく秒数。
	 *
	 * 設定画面の nonce は長くても 24 時間で失効するので、それより古い画面からは保存できない。
	 */
	const DELETED_URL_TTL = 2 * DAY_IN_SECONDS;

	/**
	 * 削除した投稿の URL を覚えておく件数の上限。
	 */
	const DELETED_URL_LIMIT = 200;

	/**
	 * タイトルで投稿を探すときの取得数の上限。
	 */
	const TITLE_QUERY_LIMIT = 20;

	/**
	 * 配信しない行の判定結果を保存する transient の名前。
	 */
	const HIDDEN_LINES_CACHE = 'avc_ais_llms_txt_hidden_lines';

	/**
	 * 投稿が変わるたびに更新する、判定結果の世代の option 名。
	 */
	const OPTION_CACHE_GENERATION = 'avc_ais_llms_txt_cache_generation';

	/**
	 * Singleton instance.
	 *
	 * @var AVC_AIS_Llms_Txt|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return AVC_AIS_Llms_Txt
	 */
	public static function init() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor - initialize hooks.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_action( 'admin_init', array( $this, 'restore_missing_rewrite_rule' ) );
		add_action( 'admin_init', array( $this, 'maybe_repair_saved_urls' ) );
		add_action( 'template_redirect', array( $this, 'serve_llms_txt' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_filter( 'redirect_canonical', array( $this, 'disable_canonical_redirect' ) );
		add_action( 'post_updated', array( $this, 'update_entry_of_moved_post' ), 10, 3 );
		add_action( 'before_delete_post', array( $this, 'remove_entry_of_deleted_post' ), 10, 2 );
		foreach ( array( 'post_updated', 'transition_post_status', 'deleted_post' ) as $hook ) {
			add_action( $hook, array( self::class, 'clear_hidden_lines_cache' ) );
		}
	}

	/**
	 * llms.txt へのリクエストで正規化リダイレクトを抑止する。
	 *
	 * WordPress の redirect_canonical() は /llms.txt を「末尾スラッシュ付き」へ
	 * 301 リダイレクトしてしまう。llms.txt は仕様上ルート直下の単一ファイルとして
	 * 参照されるため、リダイレクトを追わない取得クライアントに届かなくなる。
	 *
	 * @param string|false $redirect_url リダイレクト先 URL.
	 * @return string|false llms.txt リクエストの場合は false（リダイレクトしない）.
	 */
	public function disable_canonical_redirect( $redirect_url ) {
		if ( get_query_var( 'llms_txt' ) ) {
			return false;
		}
		return $redirect_url;
	}

	/**
	 * Check if llms.txt is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return '1' === get_option( self::OPTION_ENABLED, '1' );
	}

	/**
	 * Add rewrite rule for /llms.txt
	 */
	public function add_rewrite_rules() {
		if ( ! $this->is_enabled() ) {
			return;
		}
		add_rewrite_rule( self::REWRITE_REGEX, 'index.php?llms_txt=1', 'top' );
	}

	/**
	 * 有効なのに /llms.txt のルールがなければ、更新後に一度だけルールを再生成する。
	 *
	 * 以前のバージョンでは無効の状態から有効へ戻すとルールが消え、404 のままになっていた。
	 * プラグインの更新では有効化の処理が走らないので、管理画面へのアクセス時に直す。
	 * 他のプラグインがルールを書き換えるサイトでは再生成してもルールが残らないことがあるので、
	 * 管理画面を開くたびに再生成しないよう一度だけにする。
	 * admin_init は admin-ajax.php への未ログインのリクエストでも動くので、権限を確かめる。
	 *
	 * @return void
	 */
	public function restore_missing_rewrite_rule() {
		if ( get_option( self::OPTION_RULE_RESTORED ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		update_option( self::OPTION_RULE_RESTORED, true );

		$rules = get_option( 'rewrite_rules' );
		if ( $this->is_enabled() && is_array( $rules ) && ! isset( $rules[ self::REWRITE_REGEX ] ) ) {
			flush_rewrite_rules( false );
		}
	}

	/**
	 * Register query var.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'llms_txt';
		return $vars;
	}

	/**
	 * Serve llms.txt content.
	 */
	public function serve_llms_txt() {
		if ( ! get_query_var( 'llms_txt' ) ) {
			return;
		}

		if ( ! $this->is_enabled() ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			return;
		}

		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		// ブラウザによる MIME スニッフィングを禁止し、text/plain が HTML として
		// 解釈されることを防ぐ（下の非エスケープ出力を安全にするための前提条件）.
		header( 'X-Content-Type-Options: nosniff' );

		// llms.txt は HTML ではなく Markdown（text/plain）として配信する。
		// esc_html() を通すと '>' が '&gt;' に変換され、llms.txt 仕様の
		// 引用ブロック（> サイト説明）や本文が壊れるため、ここではエスケープしない。
		// 安全性は以下で担保している:
		//   - Content-Type が text/plain かつ X-Content-Type-Options: nosniff
		//   - 手動入力は save_content() の sanitize_content() で無害化済み
		//   - 自動生成分は制御文字を除去する sanitize_output() を通す
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->sanitize_output( $this->get_served_content() );
		exit;
	}

	/**
	 * HTML エスケープ済みの文字列をプレーンテキストへ戻す。
	 *
	 * llms.txt は text/plain の Markdown として配信するため、WordPress が
	 * 保存時に付与した HTML エンティティ（&amp; / &#039; など）は復号する必要がある。
	 * 復号後の値は sanitize_output() で制御文字を除去したうえで出力される。
	 *
	 * @param string $text 対象の文字列.
	 * @return string 復号済みの文字列.
	 */
	private static function to_plain_text( $text ) {
		return html_entity_decode( (string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	/**
	 * text/plain として配信する前に出力を無害化する。
	 *
	 * HTML エスケープは行わない（Markdown が壊れるため）。代わりに、
	 * レスポンス分割やヘッダー汚染に悪用されうる制御文字と NUL バイトを除去し、
	 * 改行コードを LF に統一する。
	 *
	 * @param string $content 出力予定の内容.
	 * @return string 無害化済みの内容.
	 */
	private function sanitize_output( $content ) {
		$content = (string) $content;

		// 改行コードを LF に正規化.
		$content = self::normalize_newlines( $content );

		// タブ・改行以外の制御文字（C0 制御文字と DEL）を除去.
		$content = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $content );

		return null === $content ? '' : $content;
	}

	/**
	 * Get llms.txt content.
	 *
	 * 設定画面に表示する内容を返す。公開していない投稿の行も含むので、配信には
	 * get_served_content() を使う。
	 *
	 * @return string
	 */
	public function get_content() {
		$content = get_option( self::OPTION_NAME, '' );
		if ( empty( $content ) ) {
			$content = $this->generate_default_content();
		}
		return $content;
	}

	/**
	 * /llms.txt で配信する内容を返す。
	 *
	 * 保存済みの内容から行を消すと、投稿を公開し直したときに手書きの説明が失われる。投稿の変更を
	 * 追えない間（無効化中、古い画面からの保存）の分も取りこぼさないよう、投稿の現在の状態で
	 * 判定する。判定には行数分のクエリが走り、/llms.txt は未ログインで何度でも呼べるので、
	 * 結果を短時間だけ保存する。判定中に投稿が変わると古い結果を保存してしまうので、消すのでは
	 * なく、判定の前に読んだ世代を一緒に保存して、投稿が変わったら世代を進める。
	 *
	 * @return string
	 */
	public function get_served_content() {
		$saved = get_option( self::OPTION_NAME, '' );
		if ( empty( $saved ) ) {
			return $this->generate_default_content();
		}

		$lines  = explode( "\n", (string) $saved );
		$key    = md5( get_option( self::OPTION_CACHE_GENERATION, '' ) . (string) $saved );
		$cached = get_transient( self::HIDDEN_LINES_CACHE );
		if ( is_array( $cached ) && ( $cached['key'] ?? '' ) === $key && is_array( $cached['lines'] ?? null ) ) {
			$hidden = array_flip( $cached['lines'] );
		} else {
			$hidden = $this->find_hidden_entries( $lines );
			set_transient(
				self::HIDDEN_LINES_CACHE,
				array(
					'key'   => $key,
					'lines' => array_keys( $hidden ),
				),
				5 * MINUTE_IN_SECONDS
			);
		}

		return implode( "\n", array_diff_key( $lines, $hidden ) );
	}

	/**
	 * 配信しない行の判定結果を無効にする。
	 *
	 * @return void
	 */
	public static function clear_hidden_lines_cache() {
		update_option( self::OPTION_CACHE_GENERATION, uniqid( '', true ), false );
	}

	/**
	 * 保存済みの内容のうち、投稿が公開されていないため配信しない行のタイトルを返す。
	 *
	 * @return string[] タイトル.
	 */
	public function get_hidden_entry_titles() {
		$saved = get_option( self::OPTION_NAME, '' );
		if ( empty( $saved ) ) {
			return array();
		}

		return array_values( $this->find_hidden_entries( explode( "\n", (string) $saved ) ) );
	}

	/**
	 * 投稿が公開されていない行を探す。
	 *
	 * 対象は一覧の形式でこのサイトを指す行だけで、外部サイトへのリンクと文中のリンクは残す。
	 *
	 * @param string[] $lines 内容の各行.
	 * @return array<int,string> 行番号をキーとするタイトル.
	 */
	private function find_hidden_entries( array $lines ) {
		$entries = array();
		foreach ( $lines as $index => $line ) {
			$entry = self::parse_entry( $line );
			if ( null !== $entry && self::is_site_url( $entry['url'] ) ) {
				$entries[ $index ] = $entry;
			}
		}
		if ( empty( $entries ) ) {
			return array();
		}

		$posts_by_url = self::find_posts_by_url( array_column( $entries, 'url' ) );

		$hidden = array();
		foreach ( $entries as $index => $entry ) {
			if ( self::is_entry_hidden( $entry, $posts_by_url ) ) {
				$hidden[ $index ] = $entry['title'];
			}
		}

		return $hidden;
	}

	/**
	 * 行の投稿が公開されていないかを判定する。
	 *
	 * 下書きのスラッグは公開中の投稿と重複しうるので、URL が公開中の投稿のものなら配信する。
	 * URL で見つからない行は、スラッグやパーマリンクの変更、以前の保存処理で URL が壊れた行の
	 * 可能性がある。同じタイトルの公開していない投稿があれば、抜粋を出さないよう隠す。
	 * 消すわけではないので、誤って隠しても投稿を公開するか行を直せば配信に戻る。
	 * トップページは投稿の状態にかかわらず公開されているので、タイトルでは判定しない。
	 *
	 * @param array $entry        parse_entry() の結果.
	 * @param array $posts_by_url find_posts_by_url() の結果.
	 * @return bool 隠すなら true.
	 */
	private static function is_entry_hidden( array $entry, array $posts_by_url ) {
		$posts = $posts_by_url[ self::url_key( $entry['url'] ) ] ?? array();
		if ( ! empty( $posts ) ) {
			return empty( array_filter( $posts, array( self::class, 'is_public_post' ) ) );
		}

		if ( self::is_home_url( $entry['url'] ) ) {
			return false;
		}

		return self::has_hidden_post_titled( $entry['title'] );
	}

	/**
	 * タイトルが一致する公開していない投稿があるかを判定する。
	 *
	 * 公開中の投稿は取得しないので、同じタイトルの公開中の投稿が多くても上限に達しない。
	 * 上限に達したら判断できないので、抜粋を出さない側に倒す。
	 *
	 * @param string $title 行のタイトル.
	 * @return bool あれば true.
	 */
	private static function has_hidden_post_titled( $title ) {
		$hidden_statuses = array_diff( get_post_stati( array( 'public' => false ) ), array( 'auto-draft', 'inherit' ) );

		$conditions = array(
			array( 'post_status' => $hidden_statuses ),
			array(
				'post_status'  => get_post_stati( array( 'public' => true ) ),
				'has_password' => true,
			),
		);
		foreach ( $conditions as $condition ) {
			$posts = self::find_posts_titled( $title, $condition );
			if ( null === $posts || ! empty( $posts ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * 投稿が公開されているかを判定する。
	 *
	 * @param WP_Post $post 投稿.
	 * @return bool 公開されていれば true.
	 */
	private static function is_public_post( $post ) {
		$status = get_post_status_object( $post->post_status );

		return null !== $status && $status->public && '' === $post->post_password;
	}

	/**
	 * 一覧の形式の行を分解する。
	 *
	 * @param string $line 行.
	 * @return array{prefix:string,title:string,url:string}|null 一覧の形式でなければ null.
	 */
	private static function parse_entry( $line ) {
		if ( ! preg_match( self::ENTRY_PATTERN, $line, $match ) ) {
			return null;
		}

		return array(
			'prefix' => $match[0],
			'title'  => $match[1],
			'url'    => $match[2],
		);
	}

	/**
	 * 行の URL を分解する。
	 *
	 * ブラウザからコピーした URL は日本語がそのままのことがある。parse_url() はロケールによって
	 * マルチバイト文字の一部のバイトを制御文字とみなして _ に置き換えるので、先にエンコードする。
	 *
	 * @param string $url URL.
	 * @return array|false 分解した URL。形が崩れていれば false.
	 */
	private static function parse_entry_url( $url ) {
		$encoded = preg_replace_callback(
			'/[^\x21-\x7e]/',
			static function ( $byte ) {
				return rawurlencode( $byte[0] );
			},
			(string) $url
		);

		return wp_parse_url( (string) $encoded );
	}

	/**
	 * URL がこのサイトを指すかを判定する。
	 *
	 * 以前の保存処理で壊れた URL は形が崩れていることがあるので、判定できなければこのサイトとする。
	 *
	 * @param string $url URL.
	 * @return bool このサイトなら true.
	 */
	private static function is_site_url( $url ) {
		$parts = self::parse_entry_url( $url );
		if ( ! is_array( $parts ) ) {
			return true;
		}
		$scheme = strtolower( $parts['scheme'] ?? 'http' );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return false;
		}
		if ( empty( $parts['host'] ) ) {
			return true;
		}

		$normalize = static function ( $host ) {
			return preg_replace( '/^www\./', '', strtolower( (string) $host ) );
		};

		$home = self::parse_entry_url( home_url() );

		return is_array( $home ) && $normalize( $parts['host'] ) === $normalize( $home['host'] ?? '' );
	}

	/**
	 * URL がサイトのトップページを指すかを判定する。
	 *
	 * 以前の保存処理で壊れた // はトップページではないので、スラッシュを 1 つだけ許す。
	 *
	 * @param string $url URL.
	 * @return bool トップページなら true.
	 */
	private static function is_home_url( $url ) {
		$parts = self::parse_entry_url( $url );
		if ( ! is_array( $parts ) || isset( $parts['query'] ) ) {
			return false;
		}

		$home = untrailingslashit( (string) wp_parse_url( home_url(), PHP_URL_PATH ) );

		return in_array( $parts['path'] ?? '', array( $home, $home . '/' ), true );
	}

	/**
	 * URL を比較するためのキーを返す。
	 *
	 * パーセントエンコードの大文字小文字、末尾のスラッシュ、http と https の違いは同じ URL とする。
	 *
	 * @param string $url URL.
	 * @return string キー.
	 */
	private static function url_key( $url ) {
		$parts = self::parse_entry_url( $url );
		if ( ! is_array( $parts ) ) {
			return strtolower( rawurldecode( $url ) );
		}

		// 末尾のスラッシュは 1 つだけ除く。以前の保存処理で壊れた /news// を親の /news/ と区別する。
		$key = preg_replace( '#/$#', '', rawurldecode( $parts['path'] ?? '' ) );
		if ( isset( $parts['query'] ) ) {
			$key .= '?' . rawurldecode( $parts['query'] );
		}

		return strtolower( $key );
	}

	/**
	 * 一覧に載る投稿タイプの投稿を、公開状態を問わず取得する。
	 *
	 * @param array $args 取得条件.
	 * @return WP_Post[] 投稿.
	 */
	private static function query_listed_posts( array $args ) {
		return get_posts(
			array_merge(
				array(
					'post_type'              => self::LISTED_POST_TYPES,
					// プラグインが追加したステータスも判定できるよう、すべて取得する。
					'post_status'            => array_diff( get_post_stati(), array( 'auto-draft', 'inherit' ) ),
					'numberposts'            => -1,
					'update_post_meta_cache' => false,
				),
				$args
			)
		);
	}

	/**
	 * URL に対応する投稿を探す。
	 *
	 * url_to_postid() は権限のないユーザーには公開していない投稿を返さず、配信の判定に使えない。
	 * URL からスラッグと ID を取り出して投稿を取得し、公開したときの URL と比較して確かめる。
	 *
	 * @param string[] $urls URL.
	 * @return array<string,WP_Post[]> url_key() をキーとする投稿.
	 */
	private static function find_posts_by_url( array $urls ) {
		$slugs      = array();
		$ids        = array();
		$query_urls = array();
		foreach ( $urls as $url ) {
			$parts = self::parse_entry_url( $url );
			if ( ! is_array( $parts ) ) {
				continue;
			}

			parse_str( $parts['query'] ?? '', $query );
			foreach ( array( 'p', 'page_id' ) as $key ) {
				if ( isset( $query[ $key ] ) && is_string( $query[ $key ] ) ) {
					$id                  = absint( $query[ $key ] );
					$ids[]               = $id;
					$query_urls[ $id ][] = self::url_key( $url );
				}
			}

			$segments = array_values( array_filter( explode( '/', $parts['path'] ?? '' ), 'strlen' ) );
			if ( empty( $segments ) ) {
				continue;
			}

			$last = rawurldecode( end( $segments ) );
			if ( ctype_digit( $last ) ) {
				$ids[] = absint( $last );
			}

			// /%postname%.html のようなパーマリンクでは拡張子がスラッグに含まれない。
			$slug = sanitize_title( preg_replace( '/\.[a-z0-9]+$/i', '', $last ) );
			if ( '' !== $slug ) {
				// ゴミ箱のスラッグは、WordPress が 191 バイトに切り詰めてから __trashed を付ける。
				$slugs[] = $slug;
				$slugs[] = _truncate_post_slug( $slug, 191 ) . '__trashed';
			}
		}

		// 空の配列を渡すと条件が無視されて全件を取得してしまう。
		$posts = array();
		if ( ! empty( $slugs ) ) {
			$posts = self::query_listed_posts( array( 'post_name__in' => array_values( array_unique( $slugs ) ) ) );
		}
		$ids = array_filter( $ids );
		if ( ! empty( $ids ) ) {
			$by_id = self::query_listed_posts( array( 'post__in' => array_values( array_unique( $ids ) ) ) );
			$posts = array_merge( $posts, $by_id );
		}

		$posts_by_url = array();
		foreach ( $posts as $post ) {
			$posts_by_url[ self::url_key( self::published_permalink( $post ) ) ][ $post->ID ] = $post;
			// パーマリンクの設定にかかわらず ?p=ID の URL は投稿へ届く。
			foreach ( $query_urls[ $post->ID ] ?? array() as $key ) {
				$posts_by_url[ $key ][ $post->ID ] = $post;
			}
		}

		return $posts_by_url;
	}

	/**
	 * タイトルが一致する投稿を探す。
	 *
	 * データベースの照合順序では「はな」と「ばな」も一致するので、完全一致を確かめる。
	 * 投稿タイトルの & は &amp; で保存されていることがあるので、どちらの形でも探す。
	 *
	 * @param string $title 行のタイトル.
	 * @param array  $args  追加の取得条件.
	 * @return WP_Post[]|null 投稿。上限に達して判断できないときは null.
	 */
	private static function find_posts_titled( $title, array $args = array() ) {
		// 空のタイトルを渡すと条件が無視され、すべての投稿が対象になる。
		if ( '' === $title ) {
			return array();
		}

		$posts = array();
		foreach ( array_unique( array( $title, str_replace( '&', '&amp;', $title ) ) ) as $stored ) {
			$found = self::query_listed_posts(
				array_merge(
					array(
						'title'       => $stored,
						'numberposts' => self::TITLE_QUERY_LIMIT,
					),
					$args
				)
			);
			if ( count( $found ) >= self::TITLE_QUERY_LIMIT ) {
				return null;
			}
			$posts = array_merge( $posts, $found );
		}

		return array_filter(
			$posts,
			static function ( $post ) use ( $title ) {
				return self::to_plain_text( $post->post_title ) === $title;
			}
		);
	}

	/**
	 * 投稿を公開したときの URL を返す。
	 *
	 * 公開していない投稿は権限のないユーザーには ?p=ID 形式の URL が返り、ゴミ箱の投稿は
	 * スラッグに __trashed が付くので、公開中だったときの URL を求める。
	 *
	 * @param WP_Post $post 投稿.
	 * @return string URL.
	 */
	private static function published_permalink( $post ) {
		$published              = clone $post;
		$published->post_status = 'publish';
		if ( 'trash' === $post->post_status ) {
			$desired              = (string) get_post_meta( $post->ID, '_wp_desired_post_slug', true );
			$published->post_name = '' !== $desired ? $desired : preg_replace( '/__trashed$/', '', $post->post_name );
		}

		return (string) get_permalink( $published );
	}

	/**
	 * 以前の保存処理を通った一覧の行の、抜粋より前の部分を返す。
	 *
	 * 以前の保存処理は内容全体に sanitize_textarea_field() をかけており、パーセントエンコードの
	 * 除去に加えて連続するスペースもまとめていた。同じ処理で比較するので、行全体に適用する。
	 *
	 * @param WP_Post $post 投稿.
	 * @param string  $url  投稿の URL.
	 * @return string 行の先頭部分.
	 */
	private static function legacy_entry_prefix( $post, $url ) {
		return sanitize_textarea_field( '- [' . self::to_plain_text( $post->post_title ) . '](' . $url . '):' );
	}

	/**
	 * 更新後に一度だけ、以前の保存処理で壊れた URL を直す。
	 *
	 * 行数分のクエリが走るので、未ログインのリクエストでは動かさない。直すまでの間も、公開して
	 * いない投稿の行は is_entry_hidden() がタイトルで隠す。
	 * 失敗しても繰り返さないよう、完了扱いにしてから行う。
	 *
	 * @return void
	 */
	public function maybe_repair_saved_urls() {
		if ( get_option( self::OPTION_URLS_REPAIRED ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		update_option( self::OPTION_URLS_REPAIRED, true );
		delete_option( self::OPTION_SCRUBBED );

		$this->repair_saved_urls();
	}

	/**
	 * 以前の保存処理で壊れた URL を、同じタイトルの公開中の投稿の URL へ戻す。
	 *
	 * 日本語スラッグの URL は /%e3%81%82/ が // になるように壊れており、別の投稿の URL と
	 * 同じ形になりうる。タイトルと壊れた形がともに一致し、候補が 1 件に絞れる行だけを直す。
	 * 公開していない投稿も候補に数えるので、その抜粋を公開中の投稿の URL で配信しない。候補が
	 * 公開していない投稿だけなら正しい URL へ直す。配信時に隠され、公開すれば正しいリンクになる。
	 * URL が壊れていない投稿も候補に数えるので、正しい行を別の投稿の URL へ書き換えない。
	 * 取得数の上限に達したときは、他にも候補がありうるので直さない。
	 *
	 * @return void
	 */
	private function repair_saved_urls() {
		$saved = (string) get_option( self::OPTION_NAME, '' );
		$lines = explode( "\n", $saved );

		foreach ( $lines as $index => $line ) {
			$entry = self::parse_entry( $line );
			$posts = null === $entry ? null : self::find_posts_for_repair( $entry['title'] );
			if ( null === $posts ) {
				continue;
			}

			$candidates = array();
			foreach ( $posts as $post ) {
				$url = self::published_permalink( $post );
				if ( self::legacy_entry_prefix( $post, $url ) === $entry['prefix'] ) {
					$candidates[ $post->ID ] = $url;
				}
			}

			if ( 1 === count( $candidates ) ) {
				$lines[ $index ] = self::replace_entry_url( $line, $entry, reset( $candidates ) );
			}
		}

		$updated = implode( "\n", $lines );
		if ( $updated !== $saved ) {
			update_option( self::OPTION_NAME, $updated );
		}
	}

	/**
	 * URL の修復の候補を、行のタイトルから探す。
	 *
	 * 以前の保存処理は連続するスペースをまとめていたので、タイトルが完全一致しない投稿もある。
	 * 全文検索は重いので、完全一致で見つからなかったときの一度きりの修復でだけ行う。
	 *
	 * @param string $title 行のタイトル.
	 * @return WP_Post[]|null 投稿。上限に達して判断できないときは null.
	 */
	private static function find_posts_for_repair( $title ) {
		$posts = self::find_posts_titled( $title );
		if ( array() !== $posts || false === strpos( $title, ' ' ) ) {
			return $posts;
		}

		$found = self::query_listed_posts(
			array(
				's'              => $title,
				'search_columns' => array( 'post_title' ),
				'numberposts'    => self::TITLE_QUERY_LIMIT,
			)
		);
		if ( count( $found ) >= self::TITLE_QUERY_LIMIT ) {
			return null;
		}

		return array_filter(
			$found,
			static function ( $post ) use ( $title ) {
				return preg_replace( '/ +/', ' ', self::to_plain_text( $post->post_title ) ) === $title;
			}
		);
	}

	/**
	 * 行の URL を置き換える。
	 *
	 * @param string $line  行.
	 * @param array  $entry parse_entry() の結果.
	 * @param string $url   新しい URL.
	 * @return string 置き換えた行.
	 */
	private static function replace_entry_url( $line, array $entry, $url ) {
		$prefix = str_replace( '](' . $entry['url'], '](' . $url, $entry['prefix'] );

		return $prefix . substr( $line, strlen( $entry['prefix'] ) );
	}

	/**
	 * 投稿の URL が変わったときに、保存済みの内容の行を新しい URL へ書き換える。
	 *
	 * 書き換えないと行のリンクが古いままになり、投稿を隠すときの判定も URL で行えなくなる。
	 * ゴミ箱への出し入れではスラッグに __trashed が付くので対象にしない。公開していない投稿は
	 * スラッグが他の投稿と重複しうるので、他に同じ URL の投稿があれば書き換えない。
	 *
	 * @param int     $post_id     投稿 ID.
	 * @param WP_Post $post_after  更新後の投稿.
	 * @param WP_Post $post_before 更新前の投稿.
	 * @return void
	 */
	public function update_entry_of_moved_post( $post_id, $post_after, $post_before ) {
		$is_listed_type = in_array( $post_after->post_type, self::LISTED_POST_TYPES, true );
		$has_slugs      = '' !== $post_before->post_name && '' !== $post_after->post_name;
		$in_trash       = 'trash' === $post_before->post_status || 'trash' === $post_after->post_status;
		if ( ! $is_listed_type || ! $has_slugs || $in_trash ) {
			return;
		}

		$old_url = self::published_permalink( $post_before );
		$new_url = self::published_permalink( $post_after );
		$saved   = (string) get_option( self::OPTION_NAME, '' );
		if ( $old_url === $new_url || '' === $saved ) {
			return;
		}
		if ( 'publish' !== $post_before->post_status && self::has_other_post_at_url( $old_url, $post_id ) ) {
			return;
		}

		$old_key    = self::url_key( $old_url );
		$old_parent = is_post_type_hierarchical( $post_after->post_type ) ? trailingslashit( $old_url ) : '';
		$lines      = explode( "\n", $saved );
		foreach ( $lines as $index => $line ) {
			$entry = self::parse_entry( $line );
			if ( null === $entry || ! self::is_site_url( $entry['url'] ) ) {
				continue;
			}
			if ( self::url_key( $entry['url'] ) === $old_key ) {
				$lines[ $index ] = self::replace_entry_url( $line, $entry, $new_url );
				continue;
			}
			// 親ページの URL が変わると子ページの URL も変わるが、子ページの更新は発生しない。
			// 以前の保存処理で壊れた /親// は子ページと判別できないので書き換えない。
			$is_child = '' !== $old_parent && 0 === strpos( $entry['url'], $old_parent );
			$child    = $is_child ? substr( $entry['url'], strlen( $old_parent ) ) : '';
			if ( '' !== $child && '/' !== $child[0] ) {
				$lines[ $index ] = self::replace_entry_url( $line, $entry, trailingslashit( $new_url ) . $child );
			}
		}

		$updated = implode( "\n", $lines );
		if ( $updated !== $saved ) {
			update_option( self::OPTION_NAME, $updated );
		}
	}

	/**
	 * URL が同じ別の投稿があるかを判定する。
	 *
	 * @param string $url     URL.
	 * @param int    $post_id 対象の投稿 ID.
	 * @return bool あれば true.
	 */
	private static function has_other_post_at_url( $url, $post_id ) {
		$posts = self::find_posts_by_url( array( $url ) )[ self::url_key( $url ) ] ?? array();
		unset( $posts[ $post_id ] );

		return ! empty( $posts );
	}

	/**
	 * 投稿を完全に削除するときに、保存済みの内容からその行を取り除く。
	 *
	 * 公開し直すことがないので、隠さずに取り除く。削除すると投稿の状態で判定できなくなる。
	 * 同じ URL や同じ壊れた形の別の投稿があれば、その投稿の行かもしれないので残す。
	 * 開いている画面の内容には残っているので、URL を覚えておいて編集保存のときに取り除く。
	 *
	 * @param int     $post_id 投稿 ID.
	 * @param WP_Post $post    削除する投稿.
	 * @return void
	 */
	public function remove_entry_of_deleted_post( $post_id, $post ) {
		$is_listed_type = in_array( $post->post_type, self::LISTED_POST_TYPES, true );
		if ( ! $is_listed_type || '' === $post->post_name ) {
			return;
		}

		$url = self::published_permalink( $post );
		if ( self::has_other_post_at_url( $url, $post_id ) ) {
			return;
		}

		self::remember_deleted_url( $url );

		$saved = (string) get_option( self::OPTION_NAME, '' );
		if ( '' === $saved ) {
			return;
		}

		$key    = self::url_key( $url );
		$legacy = self::legacy_entry_prefix( $post, $url );
		$posts  = self::find_posts_titled( self::to_plain_text( $post->post_title ) );
		if ( null === $posts ) {
			$legacy = null;
		}
		foreach ( null === $posts ? array() : $posts as $other ) {
			$other_prefix = self::legacy_entry_prefix( $other, self::published_permalink( $other ) );
			if ( $other->ID !== $post->ID && $other_prefix === $legacy ) {
				$legacy = null;
				break;
			}
		}

		$lines = array_filter(
			explode( "\n", $saved ),
			static function ( $line ) use ( $key, $legacy ) {
				$entry = self::parse_entry( $line );
				if ( null === $entry || ! self::is_site_url( $entry['url'] ) ) {
					return true;
				}
				return self::url_key( $entry['url'] ) !== $key && $entry['prefix'] !== $legacy;
			}
		);

		$updated = implode( "\n", $lines );
		if ( $updated !== $saved ) {
			update_option( self::OPTION_NAME, $updated );
		}
	}

	/**
	 * 最近削除した投稿の URL を返す。
	 *
	 * @return array<string,int> url_key() をキーとする削除した時刻.
	 */
	private static function recently_deleted_urls() {
		$deleted = get_option( self::OPTION_DELETED_URLS, array() );
		if ( ! is_array( $deleted ) ) {
			return array();
		}

		$cutoff = time() - self::DELETED_URL_TTL;

		return array_filter(
			$deleted,
			static function ( $deleted_at ) use ( $cutoff ) {
				return $deleted_at >= $cutoff;
			}
		);
	}

	/**
	 * 削除した投稿の URL を覚えておく。
	 *
	 * @param string $url URL.
	 * @return void
	 */
	private static function remember_deleted_url( $url ) {
		$deleted = self::recently_deleted_urls();

		$deleted[ self::url_key( $url ) ] = time();

		$deleted = array_slice( $deleted, -self::DELETED_URL_LIMIT, null, true );

		update_option( self::OPTION_DELETED_URLS, $deleted, false );
	}

	/**
	 * Generate default content from site data.
	 *
	 * @return string
	 */
	public function generate_default_content() {
		// WordPress は blogname / blogdescription を sanitize_option() で HTML
		// エスケープした状態で保存する。llms.txt は text/plain として配信するため、
		// エンティティのまま出すと「&amp;」等がそのまま見えてしまう。ここで復号する。
		$site_name = self::to_plain_text( get_bloginfo( 'name' ) );
		$site_desc = self::to_plain_text( get_bloginfo( 'description' ) );

		$content  = "# {$site_name}\n\n";
		$content .= "> {$site_desc}\n\n";

		// Main pages.
		$content .= "## メインコンテンツ / Main Content\n\n";
		$pages    = get_pages(
			array(
				'number'      => 20,
				'sort_column' => 'menu_order',
			)
		);
		if ( ! empty( $pages ) ) {
			foreach ( $pages as $page ) {
				// パスワード保護された本文を公開の llms.txt へ出さない。
				// get_pages() には has_password に相当する引数がないため、ここで除外する。
				if ( '' !== $page->post_password ) {
					continue;
				}

				$url     = get_permalink( $page );
				$title   = self::to_plain_text( $page->post_title );
				$excerpt = self::to_plain_text(
					wp_trim_words( wp_strip_all_tags( $page->post_content ), 20, '...' )
				);
				if ( empty( $excerpt ) ) {
					$excerpt = $title;
				}
				$content .= "- [{$title}]({$url}): {$excerpt}\n";
			}
		}

		// Recent posts.
		$content .= "\n## 最新記事 / Recent Posts\n\n";
		$posts    = get_posts(
			array(
				'numberposts'  => 10,
				'has_password' => false,
			)
		);
		if ( ! empty( $posts ) ) {
			foreach ( $posts as $post ) {
				$url     = get_permalink( $post );
				$title   = self::to_plain_text( $post->post_title );
				$excerpt = self::to_plain_text(
					wp_trim_words( wp_strip_all_tags( $post->post_content ), 20, '...' )
				);
				if ( empty( $excerpt ) ) {
					$excerpt = $title;
				}
				$content .= "- [{$title}]({$url}): {$excerpt}\n";
			}
		}

		/**
		 * Filter the default llms.txt content.
		 *
		 * @param string $content Default generated content.
		 */
		return apply_filters( 'avc_ais_llms_txt_default_content', $content );
	}

	/**
	 * Save content.
	 *
	 * @param string $content Content to save.
	 * @return bool
	 */
	public function save_content( $content ) {
		// 自動生成と同じ内容なら保存せず、自動生成のままにする。内容を固定すると新しい投稿が
		// 載らなくなり、後から非公開にした投稿の抜粋まで残り続ける。
		if ( $this->is_generated_content( $content ) ) {
			delete_option( self::OPTION_NAME );
			return true;
		}

		return update_option( self::OPTION_NAME, self::sanitize_content( $content ) );
	}

	/**
	 * 内容が現在の自動生成と同じかを判定する。
	 *
	 * @param mixed $content 内容（無害化する前の値）.
	 * @return bool 同じなら true.
	 */
	private function is_generated_content( $content ) {
		return self::content_hash( $content ) === self::content_hash( $this->generate_default_content() );
	}

	/**
	 * 画面から送信された内容を保存する。
	 *
	 * 画面を開いている間に投稿が変わると送信された内容は古くなっているので、そのまま書き込むと
	 * 自動生成の内容が固定されたり、削除した投稿の行が戻ったりする。編集されていなければ
	 * 送信された内容を使わず、編集されていれば削除した投稿の行を取り除く。
	 * 公開していない投稿の行は配信時に隠すので、ここでは取り除かない。
	 *
	 * @param mixed $content        送信された内容（無害化する前の値）.
	 * @param mixed $displayed_hash 画面に表示した内容の content_hash().
	 * @return void
	 */
	public function save_submitted_content( $content, $displayed_hash ) {
		if ( empty( self::sanitize_content( $content ) ) ) {
			$this->reset_content();
			return;
		}

		if ( self::content_hash( $content ) !== $displayed_hash ) {
			if ( self::content_hash( $this->get_content() ) !== $displayed_hash ) {
				$content = self::remove_entries_of_deleted_posts( $content );
			}
			$this->save_content( $content );
			return;
		}

		// 以前のバージョンで固定された内容が自動生成と同じなら、ここで固定を解除する。
		$saved = (string) get_option( self::OPTION_NAME, '' );
		if ( '' !== $saved && $this->is_generated_content( $saved ) ) {
			delete_option( self::OPTION_NAME );
		}
	}

	/**
	 * 古い画面から戻ってきた、削除した投稿の行を取り除く。
	 *
	 * 削除した投稿は状態で判定できないので、削除したときに覚えた URL で判定する。投稿のない
	 * URL の行をすべて取り除くと、一覧ページなどへの手書きの行まで失われる。
	 *
	 * @param string $content 送信された内容.
	 * @return string 取り除いた内容.
	 */
	private static function remove_entries_of_deleted_posts( $content ) {
		$deleted = self::recently_deleted_urls();
		if ( empty( $deleted ) ) {
			return $content;
		}

		$lines = explode( "\n", self::normalize_newlines( $content ) );
		foreach ( $lines as $index => $line ) {
			$entry = self::parse_entry( $line );
			if ( null === $entry || ! self::is_site_url( $entry['url'] ) ) {
				continue;
			}
			// 同じ URL の投稿を作り直していれば、その投稿の行として残す。
			$is_deleted = isset( $deleted[ self::url_key( $entry['url'] ) ] );
			if ( $is_deleted && ! self::has_other_post_at_url( $entry['url'], 0 ) ) {
				unset( $lines[ $index ] );
			}
		}

		return implode( "\n", $lines );
	}

	/**
	 * 画面に表示した内容が編集されたかを判定するためのハッシュを返す。
	 *
	 * @param mixed $content 内容（無害化する前の値）.
	 * @return string ハッシュ.
	 */
	public static function content_hash( $content ) {
		return hash( 'sha256', self::sanitize_content( $content ) );
	}

	/**
	 * 保存する内容を無害化する。
	 *
	 * sanitize_textarea_field() はパーセントエンコードも除去するため、日本語スラッグの
	 * URL が壊れてしまう。タグの除去などはそのまま使い、% だけを除去から守る。
	 * llms.txt は text/plain で配信してデコードもしないので、% が残っても安全である。
	 * sanitize_textarea_field() は行末の < の扱いが改行コードで変わり、ブラウザは textarea の改行を
	 * CRLF で送信するので、無害化の前に改行を統一する。
	 *
	 * @param string $content 無害化する内容.
	 * @return string 無害化した内容.
	 */
	public static function sanitize_content( $content ) {
		if ( is_array( $content ) || is_object( $content ) ) {
			return '';
		}

		$placeholder = bin2hex( random_bytes( 8 ) );

		return str_replace(
			$placeholder,
			'%',
			sanitize_textarea_field( str_replace( '%', $placeholder, self::normalize_newlines( $content ) ) )
		);
	}

	/**
	 * 改行コードを LF に統一する。
	 *
	 * @param string $text 対象の文字列.
	 * @return string 改行コードを統一した文字列.
	 */
	private static function normalize_newlines( $text ) {
		return str_replace( array( "\r\n", "\r" ), "\n", (string) $text );
	}

	/**
	 * Set enabled status.
	 *
	 * @param bool $enabled Whether to enable llms.txt.
	 * @return bool
	 */
	public function set_enabled( $enabled ) {
		return update_option( self::OPTION_ENABLED, $enabled ? '1' : '0' );
	}

	/**
	 * Reset to auto-generated content.
	 *
	 * @return string New content.
	 */
	public function reset_content() {
		delete_option( self::OPTION_NAME );
		return $this->generate_default_content();
	}

	/**
	 * Flush rewrite rules.
	 * Called on plugin activation.
	 *
	 * ネットワーク全体の有効化では他のサイトの有効化の処理が走らないので、ルールも追加する。
	 *
	 * @param bool $network_wide ネットワーク全体の有効化なら true.
	 */
	public static function activate( $network_wide = false ) {
		$instance = self::init();
		$instance->add_rewrite_rules();
		flush_rewrite_rules();

		if ( ! $network_wide ) {
			return;
		}

		self::for_each_other_site(
			static function () use ( $instance ) {
				$rules = get_option( 'rewrite_rules' );
				if ( $instance->is_enabled() && is_array( $rules ) && ! isset( $rules[ self::REWRITE_REGEX ] ) ) {
					update_option( 'rewrite_rules', array( self::REWRITE_REGEX => 'index.php?llms_txt=1' ) + $rules );
				}
			}
		);
	}

	/**
	 * Flush rewrite rules on deactivation.
	 *
	 * このリクエストの init で登録したルールを外してから再生成する。外さないと /llms.txt の
	 * ルールが残り、プラグインを削除する前からトップページを返してしまう。
	 * ネットワーク全体の無効化では、サイト単位で有効にしているサイトを除いて他のサイトのルールも外す。
	 *
	 * @param bool $network_wide ネットワーク全体の無効化なら true.
	 */
	public static function deactivate( $network_wide = false ) {
		global $wp_rewrite;

		$plugin    = plugin_basename( AVC_AIS_FILE );
		$is_active = static function () use ( $plugin ) {
			return in_array( $plugin, (array) get_option( 'active_plugins', array() ), true );
		};

		if ( ! $network_wide || ! $is_active() ) {
			unset( $wp_rewrite->extra_rules_top[ self::REWRITE_REGEX ] );
			flush_rewrite_rules();
		}

		if ( ! $network_wide ) {
			return;
		}

		self::for_each_other_site(
			static function () use ( $is_active ) {
				if ( ! $is_active() ) {
					self::remove_rewrite_rule();
				}
			}
		);
	}

	/**
	 * マルチサイトの現在のサイト以外で処理を実行する。
	 *
	 * @param callable $callback サイトごとの処理.
	 * @return void
	 */
	private static function for_each_other_site( callable $callback ) {
		if ( ! is_multisite() ) {
			return;
		}

		$current_site_id = get_current_blog_id();
		$site_ids        = get_sites(
			array(
				'fields'     => 'ids',
				'number'     => 0,
				'network_id' => get_current_network_id(),
			)
		);
		foreach ( $site_ids as $site_id ) {
			if ( (int) $site_id !== $current_site_id ) {
				switch_to_blog( $site_id );
				$callback();
				restore_current_blog();
			}
		}
	}

	/**
	 * 現在のサイトに保存された rewrite ルールから /llms.txt の行だけを消す。
	 *
	 * 他のサイトのルールを再生成すると、そのサイトで有効なプラグインのルールが読み込まれて
	 * いないため失われる。
	 *
	 * @return void
	 */
	public static function remove_rewrite_rule() {
		$rules = get_option( 'rewrite_rules' );
		if ( is_array( $rules ) && isset( $rules[ self::REWRITE_REGEX ] ) ) {
			unset( $rules[ self::REWRITE_REGEX ] );
			update_option( 'rewrite_rules', $rules );
		}
	}
}

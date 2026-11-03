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
	 * Option name recording that saved content was scrubbed.
	 */
	const OPTION_SCRUBBED = 'avc_ais_llms_txt_scrubbed';

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
		add_action( 'template_redirect', array( $this, 'serve_llms_txt' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_filter( 'redirect_canonical', array( $this, 'disable_canonical_redirect' ) );
		add_action( 'post_updated', array( $this, 'remove_entry_of_hidden_post' ), 10, 3 );
		add_action( 'before_delete_post', array( $this, 'remove_entry_of_deleted_post' ), 10, 2 );
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
	 *
	 * @return void
	 */
	public function restore_missing_rewrite_rule() {
		if ( get_option( self::OPTION_RULE_RESTORED ) ) {
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
		echo $this->sanitize_output( $this->get_content() );
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
	 * @return string
	 */
	public function get_content() {
		$this->maybe_scrub_saved_content();

		$content = get_option( self::OPTION_NAME, '' );
		if ( empty( $content ) ) {
			$content = $this->generate_default_content();
		}
		return $content;
	}

	/**
	 * 保存済みの内容から公開されていない投稿の行を取り除き、壊れた URL を一度だけ直す。
	 *
	 * 以前のバージョンでは設定画面を保存すると自動生成の内容が DB に固定され、保護された
	 * 投稿の抜粋も含まれていた。保存時に URL のパーセントエンコードも除去されていた。
	 * 投稿が多いサイトでは重い処理になるため、一般ページのリクエストでは実行せず、
	 * 内容を読み出す直前（/llms.txt と設定画面）にだけ実行する。
	 * 行の削除が途中で失敗しても完了扱いにしないので、掃除前の内容を配信することはない。
	 * URL の修復は行数分のクエリが走るので、失敗しても繰り返さないよう完了扱いにしてから行う。
	 *
	 * @return void
	 */
	public function maybe_scrub_saved_content() {
		if ( get_option( self::OPTION_SCRUBBED ) ) {
			return;
		}

		if ( '' !== (string) get_option( self::OPTION_NAME, '' ) ) {
			$this->remove_entries_of_posts_matching(
				array(
					'post_status'  => 'publish',
					'has_password' => true,
				)
			);
			$this->remove_entries_of_posts_matching( array( 'post_status' => 'private' ) );
		}

		update_option( self::OPTION_SCRUBBED, true );

		$this->repair_saved_urls();
	}

	/**
	 * 以前の保存処理で壊れた URL を、同じタイトルの公開中の投稿の URL へ戻す。
	 *
	 * 日本語スラッグの URL は /%e3%81%82/ が // になるように壊れており、別の投稿の URL と
	 * 同じ形になりうる。タイトルと壊れた形がともに一致し、候補が 1 件に絞れる行だけを直す。
	 * URL が壊れていない投稿も候補に数えるので、正しい行を別の投稿の URL へ書き換えない。
	 * 取得数の上限に達したときは、他にも候補がありうるので直さない。
	 *
	 * @return void
	 */
	private function repair_saved_urls() {
		$limit = 20;
		$saved = (string) get_option( self::OPTION_NAME, '' );
		$lines = explode( "\n", $saved );

		foreach ( $lines as $index => $line ) {
			if ( ! preg_match( '/^- \[(.+?)\]\(([^)\s]*)\):/u', $line, $match ) ) {
				continue;
			}

			$posts = get_posts(
				array(
					'post_type'     => self::LISTED_POST_TYPES,
					'title'         => $match[1],
					'numberposts'   => $limit,
					'has_password'  => false,
					'cache_results' => false,
				)
			);

			$urls = array();
			foreach ( $posts as $post ) {
				// データベースの照合順序では「はな」と「ばな」も一致するので、完全一致を確かめる。
				$url = get_permalink( $post );
				if ( self::legacy_entry_prefix( $post, $url ) === $match[0] ) {
					$urls[] = $url;
				}
			}

			if ( 1 === count( $urls ) && count( $posts ) < $limit ) {
				$lines[ $index ] = '- [' . $match[1] . '](' . $urls[0] . '):' . substr( $line, strlen( $match[0] ) );
			}
		}

		$updated = implode( "\n", $lines );
		if ( $updated !== $saved ) {
			update_option( self::OPTION_NAME, $updated );
		}
	}

	/**
	 * 条件に合う投稿の行を、保存済みの内容から少しずつ取り除く。
	 *
	 * 対象の投稿が多いサイトでもメモリを使い切らないよう、分割して処理する。
	 *
	 * @param array $query 投稿の取得条件.
	 * @return void
	 */
	private function remove_entries_of_posts_matching( array $query ) {
		$batch_size = 200;
		$paged      = 1;
		do {
			$posts = get_posts(
				array_merge(
					array(
						'post_type'     => self::LISTED_POST_TYPES,
						'numberposts'   => $batch_size,
						'paged'         => $paged,
						'orderby'       => 'ID',
						'order'         => 'ASC',
						'cache_results' => false,
					),
					$query
				)
			);
			$this->remove_saved_entries( $posts );
			$fetched = count( $posts );
			++$paged;
		} while ( $fetched === $batch_size );
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
	 * 投稿を非公開にしたときに、保存済みの内容からその行を取り除く。
	 *
	 * 一覧に載るのは公開中の投稿だけなので、公開中だった投稿が非公開になったときだけ
	 * 処理する。下書きはスラッグが空だったり公開済みの投稿と重複したりして、別の投稿の
	 * URL と一致してしまう。ゴミ箱への移動ではスラッグが変わるため更新前の投稿で照合する。
	 *
	 * @param int     $post_id     投稿 ID.
	 * @param WP_Post $post_after  更新後の投稿.
	 * @param WP_Post $post_before 更新前の投稿.
	 * @return void
	 */
	public function remove_entry_of_hidden_post( $post_id, $post_after, $post_before ) {
		unset( $post_id );

		$was_listed = 'publish' === $post_before->post_status && '' === $post_before->post_password;
		$is_hidden  = 'publish' !== $post_after->post_status || '' !== $post_after->post_password;
		if ( $was_listed && $is_hidden && in_array( $post_after->post_type, self::LISTED_POST_TYPES, true ) ) {
			$this->remove_saved_entries( array( $post_before ) );
		}
	}

	/**
	 * 投稿を完全に削除するときに、保存済みの内容からその行を取り除く。
	 *
	 * @param int     $post_id 投稿 ID.
	 * @param WP_Post $post    削除する投稿.
	 * @return void
	 */
	public function remove_entry_of_deleted_post( $post_id, $post ) {
		unset( $post_id );

		if ( 'publish' === $post->post_status && in_array( $post->post_type, self::LISTED_POST_TYPES, true ) ) {
			$this->remove_saved_entries( array( $post ) );
		}
	}

	/**
	 * 保存済みの内容から指定した投稿の行を取り除いて保存し直す。
	 *
	 * 対象は自動生成の形式の行（- [タイトル](URL): 抜粋）だけとし、管理者が文中に書いた
	 * リンクは巻き込まない。
	 *
	 * @param WP_Post[] $posts 取り除く投稿.
	 * @return void
	 */
	private function remove_saved_entries( array $posts ) {
		$saved = (string) get_option( self::OPTION_NAME, '' );
		if ( '' === $saved || empty( $posts ) ) {
			return;
		}

		$url_needles    = array();
		$legacy_needles = array();
		foreach ( $posts as $post ) {
			if ( '' === $post->post_name ) {
				continue;
			}

			// 非公開の投稿は権限のないユーザーには ?p=ID 形式の URL が返るため、
			// 公開されていたときの URL を求めて照合する。
			$published              = clone $post;
			$published->post_status = 'publish';
			$url                    = get_permalink( $published );
			$url_needles[]          = '](' . $url . '):';
			// 以前の保存処理はパーセントエンコードを除去していたため、日本語スラッグの URL は
			// 別の文字列で残っていることがある。除去後の URL は他の投稿と衝突しうるので、
			// タイトルと組み合わせて照合する。
			$legacy_needles[] = self::legacy_entry_prefix( $post, $url );
		}

		$lines = array_filter(
			explode( "\n", $saved ),
			static function ( $line ) use ( $url_needles, $legacy_needles ) {
				if ( 0 !== strpos( $line, '- [' ) ) {
					return true;
				}
				foreach ( $url_needles as $needle ) {
					// ブラウザからコピーした URL はパーセントエンコードが大文字のことがある。
					if ( false !== stripos( $line, $needle ) ) {
						return false;
					}
				}
				foreach ( $legacy_needles as $needle ) {
					if ( 0 === strpos( $line, $needle ) ) {
						return false;
					}
				}
				return true;
			}
		);

		$updated = implode( "\n", $lines );
		if ( $updated !== $saved ) {
			update_option( self::OPTION_NAME, $updated );
		}
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
		$content = self::sanitize_content( $content );

		// 設定画面の textarea には自動生成の内容が入っているため、編集せず保存しただけで
		// 内容が固定され、後から非公開にした投稿の抜粋まで残り続けていた。自動生成と同じ
		// 内容なら保存せず、自動生成のままにする。
		$generated = self::sanitize_content( $this->generate_default_content() );
		if ( self::normalize_newlines( $content ) === self::normalize_newlines( $generated ) ) {
			delete_option( self::OPTION_NAME );
			return true;
		}

		return update_option( self::OPTION_NAME, $content );
	}

	/**
	 * 保存する内容を無害化する。
	 *
	 * sanitize_textarea_field() はパーセントエンコードも除去するため、日本語スラッグの
	 * URL が壊れてしまう。タグの除去などはそのまま使い、% だけを除去から守る。
	 * llms.txt は text/plain で配信してデコードもしないので、% が残っても安全である。
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
			sanitize_textarea_field( str_replace( '%', $placeholder, (string) $content ) )
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
	 */
	public static function activate() {
		$instance = self::init();
		$instance->add_rewrite_rules();
		flush_rewrite_rules();
	}

	/**
	 * Flush rewrite rules on deactivation.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}

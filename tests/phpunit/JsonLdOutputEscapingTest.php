<?php
/**
 * JSON-LD の出力で script 要素から抜け出せないことのテスト。
 *
 * @package Aivec\AiSearchSchema\Tests
 */

require_once __DIR__ . '/bootstrap.php';

/**
 * JSON-LD 出力エスケープのテストクラス。
 */
class JsonLdOutputEscapingTest extends WP_UnitTestCase {

	/**
	 * script 要素からの抜け出しを狙う値。
	 */
	const PAYLOAD = '</script><script>alert(document.domain)</script>';

	/**
	 * テスト前のセットアップ。
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->set_options( array() );
	}

	/**
	 * テスト後のクリーンアップ。
	 */
	protected function tearDown(): void {
		delete_option( 'avc_ais_options' );
		parent::tearDown();
	}

	/**
	 * プラグイン設定を更新し、シングルトンへ反映させる。
	 *
	 * @param array $overrides 上書きする設定.
	 * @return void
	 */
	private function set_options( array $overrides ) {
		update_option(
			'avc_ais_options',
			array_merge(
				array(
					'company_name'     => 'Test Company',
					'logo_url'         => 'http://example.com/logo.png',
					'site_url'         => 'http://example.com',
					'languages'        => array( 'en' ),
					'content_model'    => 'WebPage',
					'avc_ais_priority' => 'ais',
				),
				$overrides
			)
		);
		foreach ( array( AVC_AIS_Settings::class, AVC_AIS_Schema::class ) as $class ) {
			$ref = new ReflectionProperty( $class, 'instance' );
			avc_ais_test_make_accessible( $ref );
			$ref->setValue( null, null );
		}
	}

	/**
	 * ブラウザと同じ規則で script 要素を列挙する。
	 *
	 * @param string $html HTML.
	 * @return array<int,string> 各 script 要素の中身.
	 */
	private function script_contents( $html ) {
		$contents  = array();
		$processor = new WP_HTML_Tag_Processor( $html );
		while ( $processor->next_tag( 'SCRIPT' ) ) {
			$contents[] = $processor->get_modifiable_text();
		}
		return $contents;
	}

	/**
	 * 指定スキーマを AVC_AIS_Output で出力する。
	 *
	 * @param array $schema スキーマ.
	 * @return string
	 */
	private function print_schema( array $schema ) {
		$output = new AVC_AIS_Output( new AVC_AIS_Validator() );
		ob_start();
		$output->print( $schema, true );
		return (string) ob_get_clean();
	}

	/**
	 * 値に </script> があっても script 要素は 1 つのままで、JSON として読めること。
	 */
	public function test_output_cannot_break_out_of_script_element() {
		$html    = $this->print_schema( array( 'nested' => array( 'name' => self::PAYLOAD ) ) );
		$scripts = $this->script_contents( $html );

		$this->assertCount( 1, $scripts );
		$this->assertIsArray( json_decode( $scripts[0], true ) );
		$this->assertStringNotContainsString( '</script><script>', $html );
	}

	/**
	 * タグの形にならない < や > を含む文が切り捨てられず、JSON の値として保たれること。
	 *
	 * @dataProvider provide_text_with_angle_brackets
	 *
	 * @param string $text 検証する文字列.
	 */
	public function test_output_preserves_angle_brackets_in_text( $text ) {
		$scripts = $this->script_contents( $this->print_schema( array( 'name' => $text ) ) );

		$this->assertCount( 1, $scripts );
		$this->assertStringNotContainsString( '<', $scripts[0] );
		$this->assertSame( $text, json_decode( $scripts[0], true )['name'] );
	}

	/**
	 * タグではない < や > を含む文字列を返す。
	 *
	 * @return array<string,array<int,string>>
	 */
	public function provide_text_with_angle_brackets() {
		return array(
			'spaced comparison' => array( '1 < 2 and 3 > 2' ),
			'no space'          => array( 'Is x<y? Yes always.' ),
			'number after <'    => array( 'price<100 and >50' ),
			'heart'             => array( 'love it <3' ),
			'looks like a tag'  => array( 'if a<b then c>d' ),
		);
	}

	/**
	 * URL の & が &amp; へ書き換えられないこと。
	 */
	public function test_output_does_not_corrupt_ampersands_in_urls() {
		$url     = 'https://example.com/?a=1&b=2';
		$scripts = $this->script_contents( $this->print_schema( array( 'url' => $url ) ) );

		$this->assertSame( $url, json_decode( $scripts[0], true )['url'] );
		$this->assertStringContainsString( $url, $scripts[0], 'ページのソースでも URL をそのまま読めること' );
	}

	/**
	 * タイトルなどに残る HTML タグの形は取り除くこと。
	 *
	 * @dataProvider provide_html_tags
	 *
	 * @param string $input    入力.
	 * @param string $expected 期待する値.
	 */
	public function test_output_strips_html_tags( $input, $expected ) {
		$scripts = $this->script_contents( $this->print_schema( array( 'name' => $input ) ) );

		$this->assertSame( $expected, json_decode( $scripts[0], true )['name'] );
	}

	/**
	 * HTML タグを含む文字列と期待する値を返す。
	 *
	 * @return array<string,array<int,string>>
	 */
	public function provide_html_tags() {
		return array(
			'inline tag'     => array( 'A <em>bold</em> title', 'A bold title' ),
			'line break'     => array( 'First<br>Second<br />Third', 'FirstSecondThird' ),
			'with attribute' => array( 'See <a href="https://example.com/?a=1" title="x">link</a>', 'See link' ),
			'script tags'    => array( '</script><script>alert(1)</script>', 'alert(1)' ),
		);
	}

	/**
	 * 文字列に残った HTML タグを取り除き、数値などの型は変えないこと。
	 */
	public function test_output_strips_tags_and_keeps_scalar_types() {
		$scripts = $this->script_contents(
			$this->print_schema(
				array(
					'name'  => 'A <b>bold</b> title',
					'price' => 1280.5,
					'count' => 3,
				)
			)
		);
		$decoded = json_decode( $scripts[0], true );

		$this->assertSame( 'A bold title', $decoded['name'] );
		$this->assertSame( 1280.5, $decoded['price'] );
		$this->assertSame( 3, $decoded['count'] );
	}

	/**
	 * 出力の属性と開始・終了コメントが従来どおりであること。
	 */
	public function test_output_keeps_script_attributes_and_comments() {
		$html = $this->print_schema( array( 'name' => 'x' ) );

		$this->assertStringContainsString( '<!-- AI Search Schema JSON-LD start -->', $html );
		$this->assertStringContainsString( '<!-- AI Search Schema JSON-LD end -->', $html );
		$this->assertMatchesRegularExpression( '/<script[^>]+type="application\/ld\+json"/', $html );
		$this->assertMatchesRegularExpression( '/<script[^>]+class="avc-ais-schema avc-ais-schema-graph"/', $html );
		$this->assertMatchesRegularExpression( '/<script[^>]+data-avc-ais-schema="1"/', $html );
	}

	/**
	 * 寄稿者が設定したカスタムフィールドから script 要素を増やせないこと。
	 *
	 * 寄稿者の下書きを管理者がプレビューする場面と、公開後の両方を確認する。
	 */
	public function test_contributor_custom_field_cannot_inject_script() {
		$contributor = self::factory()->user->create( array( 'role' => 'contributor' ) );
		$admin       = self::factory()->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( $contributor );
		$post_id = wp_insert_post(
			array(
				'post_title'   => 'Draft by contributor',
				'post_content' => 'Body',
				'post_status'  => 'draft',
				'post_author'  => $contributor,
			)
		);
		$this->assertTrue( current_user_can( 'edit_post_meta', $post_id, 'brand' ), '前提: 寄稿者が編集できる' );
		add_post_meta( $post_id, 'brand', self::PAYLOAD );
		add_post_meta( $post_id, 'sku', 'SKU-1' . self::PAYLOAD );

		$this->set_options( array( 'content_model' => 'Product' ) );

		wp_set_current_user( $admin );
		$this->go_to(
			add_query_arg(
				array(
					'p'       => $post_id,
					'preview' => 'true',
				),
				home_url( '/' )
			)
		);
		ob_start();
		AVC_AIS_Schema::init()->output_json_ld();
		$preview = (string) ob_get_clean();

		$this->assertCount( 1, $this->script_contents( $preview ) );
		$this->assertStringContainsString( '"sku":"SKU-1', $preview, 'カスタムフィールドの経路を通っていること' );

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			)
		);
		wp_set_current_user( 0 );
		$this->go_to( get_permalink( $post_id ) );
		ob_start();
		AVC_AIS_Schema::init()->output_json_ld();

		$this->assertCount( 1, $this->script_contents( (string) ob_get_clean() ) );
	}

	/**
	 * Product のメタ値は正当な文字を壊さず、出力時にタグだけが除かれること。
	 */
	public function test_product_meta_values_are_not_corrupted() {
		// 値は無害化せず AVC_AIS_Output で守るため、正当な文字が壊れないことを確認する。
		$post_id = self::factory()->post->create();
		add_post_meta( $post_id, 'brand', 'Acme <b>Bold</b>' );
		update_post_meta(
			$post_id,
			'_ais_product',
			array(
				'sku'     => ' AB%20C ',
				'reviews' => array(
					array(
						'author' => 'Alice',
						'body'   => '100%beef, love it <3',
						'rating' => 5,
					),
				),
			)
		);
		$this->go_to( get_permalink( $post_id ) );

		$schema  = AVC_AIS_Type_Product::build(
			array(
				'url'     => get_permalink( $post_id ),
				'title'   => 'Product',
				'post_id' => $post_id,
			),
			'en',
			'#webpage'
		);
		$scripts = $this->script_contents( $this->print_schema( $schema ) );
		$decoded = json_decode( $scripts[0], true );

		$this->assertSame( 'Acme Bold', $decoded['brand'] );
		$this->assertSame( 'AB%20C', $decoded['sku'] );
		$this->assertSame( '100%beef, love it <3', $decoded['review'][0]['reviewBody'] );
	}
}

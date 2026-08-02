<?php
/**
 * Product スキーマの投稿メタ正規化に関する回帰テスト。
 *
 * WooCommerce アダプタを経由しない経路（アダプタ未指定 / アダプタが空を返す場合）で
 * `_ais_product` メタが存在しないと get_post_meta() が空文字列を返し、
 * かつて TypeError で致命的エラーになっていた。その再発を防ぐ。
 */

require_once __DIR__ . '/bootstrap.php';

class AVC_AIS_ProductSchemaMeta_Test extends WP_UnitTestCase {

	/**
	 * 対象の投稿ID。
	 *
	 * @var int
	 */
	private int $post_id;

	protected function setUp(): void {
		parent::setUp();

		$this->post_id = self::factory()->post->create(
			array(
				'post_title'  => 'Meta Free Product',
				'post_status' => 'publish',
			)
		);
	}

	/**
	 * `_ais_product` メタの生の戻り値を差し替えるフィルターを登録する。
	 *
	 * get_post_meta() は保存時に自動でシリアライズ解除するため、
	 * 「文字列のまま返ってくる」状況はフィルターで再現する。
	 *
	 * @param mixed $raw_value get_post_meta() に返させたい値.
	 * @return void
	 */
	private function force_product_meta( $raw_value ) {
		// WordPress は $single=true でフィルター値が配列の場合 $check[0] を取り出すため、
		// 配列をそのまま返させたいときは一段ラップして渡す。
		$payload = is_array( $raw_value ) ? array( $raw_value ) : $raw_value;

		add_filter(
			'get_post_metadata',
			static function ( $value, $object_id, $meta_key ) use ( $payload ) {
				if ( '_ais_product' === $meta_key ) {
					return $payload;
				}
				return $value;
			},
			10,
			3
		);
	}

	/**
	 * テスト用のコンテキストを組み立てる。
	 *
	 * @return array
	 */
	private function build_context() {
		return array(
			'post_id' => $this->post_id,
			'title'   => 'Meta Free Product',
			'url'     => get_permalink( $this->post_id ),
		);
	}

	/**
	 * スキーマを生成する（アダプタは使わず素の経路を通す）。
	 *
	 * @return array
	 */
	private function build_schema() {
		return AVC_AIS_Type_Product::build(
			$this->build_context(),
			'en',
			'http://example.com/#webpage',
			array(),
			null
		);
	}

	/**
	 * メタが 1 件も無くても致命的エラーにならず、スキーマ生成が完了すること。
	 * これが本来の再現ケース。
	 */
	public function test_build_succeeds_when_product_meta_is_absent() {
		$schema = $this->build_schema();

		$this->assertIsArray( $schema );
		$this->assertSame( 'Product', $schema['@type'] );
		$this->assertSame( 'Meta Free Product', $schema['name'] );
		// 値が無いので offers 等は出力されない.
		$this->assertArrayNotHasKey( 'offers', $schema );
		$this->assertArrayNotHasKey( 'aggregateRating', $schema );
		$this->assertArrayNotHasKey( 'review', $schema );
	}

	/**
	 * メタが空文字列で返ってきても例外にならないこと。
	 */
	public function test_build_succeeds_when_meta_is_empty_string() {
		$this->force_product_meta( '' );

		$schema = $this->build_schema();

		$this->assertSame( 'Product', $schema['@type'] );
		$this->assertArrayNotHasKey( 'offers', $schema );
	}

	/**
	 * シリアライズ済み文字列で保存されていても復元して利用できること。
	 */
	public function test_build_recovers_serialized_meta() {
		$this->force_product_meta(
			serialize( // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
				array(
					'price'          => '1280',
					'price_currency' => 'JPY',
				)
			)
		);

		$schema = $this->build_schema();

		$this->assertArrayHasKey( 'offers', $schema );
		$this->assertSame( 1280.0, $schema['offers']['price'] );
		$this->assertSame( 'JPY', $schema['offers']['priceCurrency'] );
	}

	/**
	 * JSON 文字列で保存されていても復元して利用できること。
	 */
	public function test_build_recovers_json_meta() {
		$this->force_product_meta( '{"price":"980","price_currency":"JPY"}' );

		$schema = $this->build_schema();

		$this->assertArrayHasKey( 'offers', $schema );
		$this->assertSame( 980.0, $schema['offers']['price'] );
		$this->assertSame( 'JPY', $schema['offers']['priceCurrency'] );
	}

	/**
	 * 復元できない文字列は空配列にフォールバックし、例外にならないこと。
	 */
	public function test_build_falls_back_when_meta_is_unparsable() {
		$this->force_product_meta( 'not-a-serialized-or-json-value' );

		$schema = $this->build_schema();

		$this->assertSame( 'Product', $schema['@type'] );
		$this->assertArrayNotHasKey( 'offers', $schema );
	}

	/**
	 * オブジェクトで返ってきた場合もプロパティを配列として扱えること。
	 */
	public function test_build_accepts_object_meta() {
		$meta                 = new stdClass();
		$meta->price          = '500';
		$meta->price_currency = 'USD';
		$this->force_product_meta( $meta );

		$schema = $this->build_schema();

		$this->assertArrayHasKey( 'offers', $schema );
		$this->assertSame( 500.0, $schema['offers']['price'] );
		$this->assertSame( 'USD', $schema['offers']['priceCurrency'] );
	}

	/**
	 * 配列メタから aggregateRating と review が生成されること。
	 */
	public function test_build_creates_rating_and_reviews_from_array_meta() {
		$this->force_product_meta(
			array(
				'rating_value' => '4.5',
				'rating_count' => '12',
				'reviews'      => array(
					array(
						'author' => 'Taro',
						'body'   => 'Great product.',
						'rating' => '5',
					),
				),
			)
		);

		$schema = $this->build_schema();

		$this->assertSame( 4.5, $schema['aggregateRating']['ratingValue'] );
		$this->assertSame( 12, $schema['aggregateRating']['reviewCount'] );
		$this->assertCount( 1, $schema['review'] );
		$this->assertSame( 'Taro', $schema['review'][0]['author']['name'] );
	}
}

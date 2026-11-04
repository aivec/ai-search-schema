<?php
/**
 * JSON-LD 出力を担当するクラス。
 */
defined( 'ABSPATH' ) || exit;
class AVC_AIS_Output {
	/**
	 * HTML タグとみなす形。属性は = を含むものだけをタグとして扱う。
	 */
	private const HTML_TAG_PATTERN = '/<\/?[a-zA-Z][a-zA-Z0-9:-]*(?:\s+[^<>]*=[^<>]*)?\s*\/?>/';

	/**
	 * @var AVC_AIS_Validator
	 */
	private $validator;

	public function __construct( AVC_AIS_Validator $validator ) {
		$this->validator = $validator;
	}

	/**
	 * 検証して出力
	 *
	 * @param array $schema
	 * @param bool  $validated すでに検証済みならtrue
	 * @return void
	 */
	public function print( array $schema, $validated = false ) {
		if ( empty( $schema ) ) {
			return;
		}

		if ( ! $validated ) {
			$validation = $this->validator->validate( $schema );
			if ( ! $validation['is_valid'] ) {
				return;
			}
			$schema = $validation['schema'];
		}

		// JSON_HEX_TAG で < と > を Unicode エスケープし、値に含まれる </script> で
		// script 要素から抜けられないようにする。wp_kses() は script を許可すると
		// この抜け出しを防げないうえ、URL の & を &amp; へ書き換えてしまうため使わない。
		$json = wp_json_encode(
			$this->strip_tags_from_values( $schema ),
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG
		);

		if ( false === $json ) {
			return;
		}

		echo "<!-- AI Search Schema JSON-LD start -->\n";
		wp_print_inline_script_tag(
			$json,
			array(
				'type'                => 'application/ld+json',
				'class'               => 'avc-ais-schema avc-ais-schema-graph',
				'data-avc-ais-schema' => '1',
			)
		);
		echo "<!-- AI Search Schema JSON-LD end -->\n";
	}

	/**
	 * スキーマ内の文字列から HTML タグを取り除く。
	 *
	 * 投稿タイトルなどに残る <b> のような許可タグを構造化データへ出さない。
	 * 以前は出力全体にかけていた wp_kses() がこの役割も担っていた。
	 * strip_tags() 系は「x<y です」のような文を以降すべて切り捨てるため使わず、
	 * タグの形をしたものだけを除く（安全性は JSON_HEX_TAG で担保している）。
	 * 「a<b then c>d」は文として残す。
	 *
	 * @param array $schema スキーマ.
	 * @return array タグを除いたスキーマ.
	 */
	private function strip_tags_from_values( array $schema ) {
		array_walk_recursive(
			$schema,
			static function ( &$value ) {
				if ( is_string( $value ) ) {
					$value = preg_replace( self::HTML_TAG_PATTERN, '', $value ) ?? $value;
				}
			}
		);

		return $schema;
	}
}

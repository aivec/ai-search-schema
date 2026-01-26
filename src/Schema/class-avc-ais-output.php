<?php
/**
 * JSON-LD 出力を担当するクラス。
 */
class AVC_AIS_Output {
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

		$json = wp_json_encode(
			$schema,
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);

		if ( false === $json ) {
			return;
		}

		printf(
			"<!-- AI Search Schema JSON-LD start -->\n%s\n<!-- AI Search Schema JSON-LD end -->\n",
			wp_kses(
				sprintf(
					'<script type="application/ld+json" class="avc-ais-schema avc-ais-schema-graph" '
					. 'data-avc-ais-schema="1">%s</script>',
					$json
				),
				array(
					'script' => array(
						'type'                => true,
						'class'               => true,
						'data-avc-ais-schema' => true,
					),
				)
			)
		);
	}
}

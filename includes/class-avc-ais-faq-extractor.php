<?php
/**
 * FAQ抽出のためのクラス
 *
 * このクラスは、指定されたクラス名から質問と回答を取得します。
 */
defined( 'ABSPATH' ) || exit;
class AVC_AIS_Faq_Extractor {

	/**
	 * FAQを抽出する
	 *
	 * @param string $faq_question_class 質問のクラス名
	 * @param string $faq_answer_class 回答のクラス名
	 * @return array 抽出されたFAQの配列
	 */
	public function extract_faqs( $faq_question_class, $faq_answer_class ) {
		$faqs = array();

		// 質問と回答を取得
		$questions = $this->get_elements( $faq_question_class );
		$answers   = $this->get_elements( $faq_answer_class );

		// 質問と回答をペアにして配列に追加
		foreach ( $questions as $index => $question ) {
			if ( isset( $answers[ $index ] ) ) {
				$faqs[] = array(
					'question' => sanitize_text_field( $question ),
					'answer'   => wp_kses_post( $answers[ $index ] ),
				);
			}
		}

		return $faqs;
	}

	/**
	 * 指定されたクラス名の要素を取得する
	 *
	 * @param string $class_name クラス名
	 * @return array 要素の配列
	 */
	private function get_elements( $class_name ) {
		$elements = array();

		// DOMDocumentを使用してHTMLから要素を取得
		$dom               = new DOMDocument();
		$internal_errors   = libxml_use_internal_errors( true );
		$content           = get_the_content();
		$converted_content = mb_convert_encoding( $content, 'HTML-ENTITIES', 'UTF-8' );

		$dom->loadHTML( $converted_content );
		libxml_clear_errors();
		libxml_use_internal_errors( $internal_errors );
		$xpath = new DOMXPath( $dom );
		$nodes = $xpath->query( "//*[contains(concat(' ', normalize-space(@class), ' '), ' $class_name ')]" );

		foreach ( $nodes as $node ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API uses camelCase.
			$elements[] = $node->textContent;
		}

		return $elements;
	}
}

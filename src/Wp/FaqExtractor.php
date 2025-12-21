<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName

namespace Aivec\AiSearchSchema\Wp;

/**
 * レガシー FAQ 抽出クラスを PSR-4 名前空間から利用するためのラッパー。
 */
if ( ! class_exists( '\AI_Search_Schema_Faq_Extractor' ) ) {
	require_once \AI_SEARCH_SCHEMA_DIR . 'includes/class-ai-search-schema-faq-extractor.php';
}

class FaqExtractor extends \AI_Search_Schema_Faq_Extractor {
}

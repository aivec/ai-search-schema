<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName

/**
 * FAQPage タイプの PSR-4 ラッパー。
 */

namespace Aivec\AiSearchSchema\Type;

if ( ! class_exists( '\AI_Search_Schema_Type_FAQPage' ) ) {
	require_once AI_SEARCH_SCHEMA_DIR . 'src/Schema/Type/class-ai-search-schema-type-faqpage.php';
}

/**
 * レガシー `AI_Search_Schema_Type_FAQPage` を PSR-4 で扱うためのラッパー。
 */
class FaqPage extends \AI_Search_Schema_Type_FAQPage {
}

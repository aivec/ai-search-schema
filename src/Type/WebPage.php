<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName

/**
 * WebPage タイプの PSR-4 ラッパー。
 */

namespace Aivec\AiSearchSchema\Type;

if ( ! class_exists( '\AI_Search_Schema_Type_WebPage' ) ) {
	require_once AI_SEARCH_SCHEMA_DIR . 'src/Schema/Type/class-ai-search-schema-type-webpage.php';
}

/**
 * レガシー `AI_Search_Schema_Type_WebPage` を PSR-4 で扱うためのラッパー。
 */
class WebPage extends \AI_Search_Schema_Type_WebPage {
}

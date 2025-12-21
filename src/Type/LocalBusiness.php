<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName

/**
 * LocalBusiness タイプの PSR-4 ラッパー。
 */

namespace Aivec\AiSearchSchema\Type;

if ( ! class_exists( '\AI_Search_Schema_Type_LocalBusiness' ) ) {
	require_once AI_SEARCH_SCHEMA_DIR . 'src/Schema/Type/class-ai-search-schema-type-localbusiness.php';
}

/**
 * レガシー `AI_Search_Schema_Type_LocalBusiness` を PSR-4 で扱うためのラッパー。
 */
class LocalBusiness extends \AI_Search_Schema_Type_LocalBusiness {
}

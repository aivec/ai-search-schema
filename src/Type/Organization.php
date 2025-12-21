<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName

/**
 * Organization タイプの PSR-4 ラッパー。
 */

namespace Aivec\AiSearchSchema\Type;

if ( ! class_exists( '\AI_Search_Schema_Type_Organization' ) ) {
	require_once AI_SEARCH_SCHEMA_DIR . 'src/Schema/Type/class-ai-search-schema-type-organization.php';
}

/**
 * レガシー `AI_Search_Schema_Type_Organization` を PSR-4 で扱うためのラッパー。
 */
class Organization extends \AI_Search_Schema_Type_Organization {
}

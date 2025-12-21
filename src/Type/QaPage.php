<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName

/**
 * QAPage タイプの PSR-4 ラッパー。
 */

namespace Aivec\AiSearchSchema\Type;

if ( ! class_exists( '\AI_Search_Schema_Type_QAPage' ) ) {
	require_once AI_SEARCH_SCHEMA_DIR . 'src/Schema/Type/class-ai-search-schema-type-qapage.php';
}

/**
 * レガシー `AI_Search_Schema_Type_QAPage` を PSR-4 で扱うためのラッパー。
 */
class QaPage extends \AI_Search_Schema_Type_QAPage {
}

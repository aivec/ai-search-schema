<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName

/**
 * Article タイプの PSR-4 ラッパー。
 */

namespace Aivec\AiSearchSchema\Type;

if ( ! class_exists( '\AI_Search_Schema_Type_Article' ) ) {
	require_once AVC_AIS_DIR . 'src/Schema/Type/class-ai-search-schema-type-article.php';
}

/**
 * レガシー `AI_Search_Schema_Type_Article` を PSR-4 で扱うためのラッパー。
 */
class Article extends \AI_Search_Schema_Type_Article {
}

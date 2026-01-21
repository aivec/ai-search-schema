<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName

/**
 * WebSite タイプの PSR-4 ラッパー。
 */

namespace Aivec\AiSearchSchema\Type;

if ( ! class_exists( '\AI_Search_Schema_Type_WebSite' ) ) {
	require_once AVC_AIS_DIR . 'src/Schema/Type/class-ai-search-schema-type-website.php';
}

/**
 * レガシー `AI_Search_Schema_Type_WebSite` を PSR-4 で扱うためのラッパー。
 */
class WebSite extends \AI_Search_Schema_Type_WebSite {
}

<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName

/**
 * ItemList タイプの PSR-4 ラッパー。
 */

namespace Aivec\AiSearchSchema\Type;

if ( ! class_exists( '\AI_Search_Schema_Type_ItemList' ) ) {
	require_once AVC_AIS_DIR . 'src/Schema/Type/class-ai-search-schema-type-itemlist.php';
}

/**
 * レガシー `AI_Search_Schema_Type_ItemList` を PSR-4 で扱うためのラッパー。
 */
class ItemList extends \AI_Search_Schema_Type_ItemList {
}

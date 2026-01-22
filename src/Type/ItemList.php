<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName

/**
 * ItemList タイプの PSR-4 ラッパー。
 */

namespace Aivec\AiSearchSchema\Type;

if ( ! class_exists( '\AVC_AIS_Type_ItemList' ) ) {
	require_once AVC_AIS_DIR . 'src/Schema/Type/class-avc-ais-type-itemlist.php';
}

/**
 * レガシー `AVC_AIS_Type_ItemList` を PSR-4 で扱うためのラッパー。
 */
class ItemList extends \AVC_AIS_Type_ItemList {
}

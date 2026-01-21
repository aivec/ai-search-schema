<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName

/**
 * CollectionPage タイプの PSR-4 ラッパー。
 */

namespace Aivec\AiSearchSchema\Type;

if ( ! class_exists( '\AI_Search_Schema_Type_CollectionPage' ) ) {
	require_once AVC_AIS_DIR . 'src/Schema/Type/class-ai-search-schema-type-collectionpage.php';
}

/**
 * レガシー `AI_Search_Schema_Type_CollectionPage` を PSR-4 で扱うためのラッパー。
 */
class CollectionPage extends \AI_Search_Schema_Type_CollectionPage {
}

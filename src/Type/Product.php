<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName

/**
 * Product タイプの PSR-4 ラッパー。
 */

namespace Aivec\AiSearchSchema\Type;

if ( ! class_exists( '\AI_Search_Schema_Type_Product' ) ) {
	require_once AVC_AIS_DIR . 'src/Schema/Type/class-ai-search-schema-type-product.php';
}

/**
 * レガシー `AI_Search_Schema_Type_Product` を PSR-4 で扱うためのラッパー。
 */
class Product extends \AI_Search_Schema_Type_Product {
}

<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName

/**
 * Product タイプの PSR-4 ラッパー。
 */

namespace Aivec\AiSearchSchema\Type;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\AVC_AIS_Type_Product' ) ) {
	require_once AVC_AIS_DIR . 'src/Schema/Type/class-avc-ais-type-product.php';
}

/**
 * レガシー `AVC_AIS_Type_Product` を PSR-4 で扱うためのラッパー。
 */
class Product extends \AVC_AIS_Type_Product {
}

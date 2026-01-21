<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName

/**
 * WooCommerce Product アダプタの PSR-4 ラッパー。
 */

namespace Aivec\AiSearchSchema\Adapter;

if ( ! class_exists( '\AI_Search_Schema_WooProductAdapter' ) ) {
	require_once AVC_AIS_DIR . 'src/Schema/Adapter/class-ai-search-schema-wooproductadapter.php';
}

/**
 * レガシー `AI_Search_Schema_WooProductAdapter` を PSR-4 で扱うためのラッパー。
 */
class WooProductAdapter extends \AI_Search_Schema_WooProductAdapter {
}

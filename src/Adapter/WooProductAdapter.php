<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName

/**
 * WooCommerce Product アダプタの PSR-4 ラッパー。
 */

namespace Aivec\AiSearchSchema\Adapter;

if ( ! class_exists( '\AVC_AIS_WooProductAdapter' ) ) {
	require_once AVC_AIS_DIR . 'src/Schema/Adapter/class-avc-ais-wooproductadapter.php';
}

/**
 * レガシー `AVC_AIS_WooProductAdapter` を PSR-4 で扱うためのラッパー。
 */
class WooProductAdapter extends \AVC_AIS_WooProductAdapter {
}

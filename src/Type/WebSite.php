<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName

/**
 * WebSite タイプの PSR-4 ラッパー。
 */

namespace Aivec\AiSearchSchema\Type;

if ( ! class_exists( '\AVC_AIS_Type_WebSite' ) ) {
	require_once AVC_AIS_DIR . 'src/Schema/Type/class-avc-ais-type-website.php';
}

/**
 * レガシー `AVC_AIS_Type_WebSite` を PSR-4 で扱うためのラッパー。
 */
class WebSite extends \AVC_AIS_Type_WebSite {
}

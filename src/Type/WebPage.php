<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName

/**
 * WebPage タイプの PSR-4 ラッパー。
 */

namespace Aivec\AiSearchSchema\Type;

if ( ! class_exists( '\AVC_AIS_Type_WebPage' ) ) {
	require_once AVC_AIS_DIR . 'src/Schema/Type/class-avc-ais-type-webpage.php';
}

/**
 * レガシー `AVC_AIS_Type_WebPage` を PSR-4 で扱うためのラッパー。
 */
class WebPage extends \AVC_AIS_Type_WebPage {
}

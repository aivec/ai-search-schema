<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName

/**
 * FAQPage タイプの PSR-4 ラッパー。
 */

namespace Aivec\AiSearchSchema\Type;

if ( ! class_exists( '\AVC_AIS_Type_FAQPage' ) ) {
	require_once AVC_AIS_DIR . 'src/Schema/Type/class-avc-ais-type-faqpage.php';
}

/**
 * レガシー `AVC_AIS_Type_FAQPage` を PSR-4 で扱うためのラッパー。
 */
class FaqPage extends \AVC_AIS_Type_FAQPage {
}

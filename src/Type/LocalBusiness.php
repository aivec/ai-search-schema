<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName

/**
 * LocalBusiness タイプの PSR-4 ラッパー。
 */

namespace Aivec\AiSearchSchema\Type;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\AVC_AIS_Type_LocalBusiness' ) ) {
	require_once AVC_AIS_DIR . 'src/Schema/Type/class-avc-ais-type-localbusiness.php';
}

/**
 * レガシー `AVC_AIS_Type_LocalBusiness` を PSR-4 で扱うためのラッパー。
 */
class LocalBusiness extends \AVC_AIS_Type_LocalBusiness {
}

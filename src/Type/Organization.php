<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName

/**
 * Organization タイプの PSR-4 ラッパー。
 */

namespace Aivec\AiSearchSchema\Type;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\AVC_AIS_Type_Organization' ) ) {
	require_once AVC_AIS_DIR . 'src/Schema/Type/class-avc-ais-type-organization.php';
}

/**
 * レガシー `AVC_AIS_Type_Organization` を PSR-4 で扱うためのラッパー。
 */
class Organization extends \AVC_AIS_Type_Organization {
}

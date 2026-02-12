<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName

/**
 * CollectionPage タイプの PSR-4 ラッパー。
 */

namespace Aivec\AiSearchSchema\Type;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\AVC_AIS_Type_CollectionPage' ) ) {
	require_once AVC_AIS_DIR . 'src/Schema/Type/class-avc-ais-type-collectionpage.php';
}

/**
 * レガシー `AVC_AIS_Type_CollectionPage` を PSR-4 で扱うためのラッパー。
 */
class CollectionPage extends \AVC_AIS_Type_CollectionPage {
}

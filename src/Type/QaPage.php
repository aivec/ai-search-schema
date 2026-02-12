<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName

/**
 * QAPage タイプの PSR-4 ラッパー。
 */

namespace Aivec\AiSearchSchema\Type;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\AVC_AIS_Type_QAPage' ) ) {
	require_once AVC_AIS_DIR . 'src/Schema/Type/class-avc-ais-type-qapage.php';
}

/**
 * レガシー `AVC_AIS_Type_QAPage` を PSR-4 で扱うためのラッパー。
 */
class QaPage extends \AVC_AIS_Type_QAPage {
}

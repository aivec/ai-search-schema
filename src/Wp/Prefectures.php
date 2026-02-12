<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName
/**
 * 都道府県ユーティリティの PSR-4 ラッパー。
 */

namespace Aivec\AiSearchSchema\Wp;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\AVC_AIS_Prefectures' ) ) {
	require_once AVC_AIS_DIR . 'includes/class-avc-ais-prefectures.php';
}

/**
 * レガシー `AVC_AIS_Prefectures` を PSR-4 で扱うためのラッパークラス。
 */
class Prefectures extends \AVC_AIS_Prefectures {
}

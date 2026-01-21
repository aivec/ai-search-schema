<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName
/**
 * 都道府県ユーティリティの PSR-4 ラッパー。
 */

namespace Aivec\AiSearchSchema\Wp;

if ( ! class_exists( '\AI_Search_Schema_Prefectures' ) ) {
	require_once AVC_AIS_DIR . 'includes/class-ai-search-schema-prefectures.php';
}

/**
 * レガシー `AI_Search_Schema_Prefectures` を PSR-4 で扱うためのラッパークラス。
 */
class Prefectures extends \AI_Search_Schema_Prefectures {
}

<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName

/**
 * Article タイプの PSR-4 ラッパー。
 */

namespace Aivec\AiSearchSchema\Type;

if ( ! class_exists( '\AVC_AIS_Type_Article' ) ) {
	require_once AVC_AIS_DIR . 'src/Schema/Type/class-avc-ais-type-article.php';
}

/**
 * レガシー `AVC_AIS_Type_Article` を PSR-4 で扱うためのラッパー。
 */
class Article extends \AVC_AIS_Type_Article {
}

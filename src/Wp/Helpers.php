<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName

namespace Aivec\AiSearchSchema\Wp;

/**
 * レガシーヘルパーを PSR-4 名前空間から利用するためのラッパー。
 */
if ( ! class_exists( '\AI_Search_Schema_Helpers' ) ) {
	require_once \AI_SEARCH_SCHEMA_DIR . 'includes/class-ai-search-schema-helpers.php';
}

class Helpers extends \AI_Search_Schema_Helpers {
}

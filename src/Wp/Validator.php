<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName

namespace Aivec\AiSearchSchema\Wp;

/**
 * レガシー Validator (AEO 用) を PSR-4 名前空間から利用するためのラッパー。
 */
if ( ! class_exists( '\AI_Search_Schema_Validator' ) ) {
	require_once \AI_SEARCH_SCHEMA_DIR . 'includes/class-ai-search-schema-validator.php';
}

class Validator extends \AI_Search_Schema_Validator {
}

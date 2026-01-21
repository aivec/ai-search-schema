<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName

namespace Aivec\AiSearchSchema;

/**
 * レガシー Validator を PSR-4 名前空間から利用するためのラッパー。
 */
if ( ! class_exists( '\AI_Search_Schema_Validator' ) ) {
	require_once AVC_AIS_DIR . 'src/Schema/class-ai-search-schema-validator.php';
}

class Validator extends \AI_Search_Schema_Validator {
}

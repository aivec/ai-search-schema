<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName

namespace Aivec\AiSearchSchema;

/**
 * レガシー Output を PSR-4 名前空間から利用するためのラッパー。
 */
if ( ! class_exists( '\AI_Search_Schema_Output' ) ) {
	require_once AVC_AIS_DIR . 'src/Schema/class-ai-search-schema-output.php';
}

class Output extends \AI_Search_Schema_Output {
}

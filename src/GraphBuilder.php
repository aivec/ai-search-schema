<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName

namespace Aivec\AiSearchSchema;

/**
 * レガシー GraphBuilder を PSR-4 名前空間から利用するためのラッパー。
 */
if ( ! class_exists( '\AI_Search_Schema_GraphBuilder' ) ) {
	require_once AVC_AIS_DIR . 'src/Schema/class-ai-search-schema-graphbuilder.php';
}

class GraphBuilder extends \AI_Search_Schema_GraphBuilder {
}

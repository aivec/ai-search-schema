<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName

namespace Aivec\AiSearchSchema;

defined( 'ABSPATH' ) || exit;

/**
 * レガシー GraphBuilder を PSR-4 名前空間から利用するためのラッパー。
 */
if ( ! class_exists( '\AVC_AIS_GraphBuilder' ) ) {
	require_once AVC_AIS_DIR . 'src/Schema/class-avc-ais-graphbuilder.php';
}

class GraphBuilder extends \AVC_AIS_GraphBuilder {
}

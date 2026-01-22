<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName

namespace Aivec\AiSearchSchema\Wp;

/**
 * レガシーヘルパーを PSR-4 名前空間から利用するためのラッパー。
 */
if ( ! class_exists( '\AVC_AIS_Helpers' ) ) {
	require_once \AVC_AIS_DIR . 'includes/class-avc-ais-helpers.php';
}

class Helpers extends \AVC_AIS_Helpers {
}

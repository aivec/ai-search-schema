<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName

namespace Aivec\AiSearchSchema\Wp;

/**
 * レガシースキーマ出力クラスを PSR-4 名前空間から利用するためのラッパー。
 */
if ( ! class_exists( '\AVC_AIS_Schema' ) ) {
	require_once \AVC_AIS_DIR . 'includes/class-avc-ais-schema.php';
}

class Schema extends \AVC_AIS_Schema {
}

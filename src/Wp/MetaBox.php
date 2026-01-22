<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName

namespace Aivec\AiSearchSchema\Wp;

/**
 * レガシーメタボックスクラスを PSR-4 名前空間から利用するためのラッパー。
 */
if ( ! class_exists( '\AVC_AIS_MetaBox' ) ) {
	require_once \AVC_AIS_DIR . 'includes/class-avc-ais-metabox.php';
}

class MetaBox extends \AVC_AIS_MetaBox {
}

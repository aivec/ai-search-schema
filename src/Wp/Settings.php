<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName

namespace Aivec\AiSearchSchema\Wp;

/**
 * レガシー設定クラスを PSR-4 名前空間から利用するためのラッパー。
 */
if ( ! class_exists( '\AVC_AIS_Settings' ) ) {
	require_once AVC_AIS_DIR . 'includes/class-avc-ais-settings.php';
}

class Settings extends \AVC_AIS_Settings {
}

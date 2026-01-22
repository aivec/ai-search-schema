<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName

namespace Aivec\AiSearchSchema\Wp;

/**
 * レガシー Validator (AEO 用) を PSR-4 名前空間から利用するためのラッパー。
 */
if ( ! class_exists( '\AVC_AIS_Validator' ) ) {
	require_once \AVC_AIS_DIR . 'src/Schema/class-avc-ais-validator.php';
}

class Validator extends \AVC_AIS_Validator {
}

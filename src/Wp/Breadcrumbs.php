<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName

namespace Aivec\AiSearchSchema\Wp;

/**
 * レガシーパンくずクラスを PSR-4 名前空間から利用するためのラッパー。
 */
if ( ! class_exists( '\AVC_AIS_Breadcrumbs' ) ) {
	require_once \AVC_AIS_DIR . 'includes/class-avc-ais-breadcrumbs.php';
}

class Breadcrumbs extends \AVC_AIS_Breadcrumbs {
}

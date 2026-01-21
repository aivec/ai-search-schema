<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName

namespace Aivec\AiSearchSchema\Wp;

/**
 * レガシーメタボックスクラスを PSR-4 名前空間から利用するためのラッパー。
 */
if ( ! class_exists( '\AI_Search_Schema_MetaBox' ) ) {
	require_once \AVC_AIS_DIR . 'includes/class-ai-search-schema-metabox.php';
}

class MetaBox extends \AI_Search_Schema_MetaBox {
}

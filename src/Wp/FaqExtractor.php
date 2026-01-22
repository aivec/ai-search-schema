<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName

namespace Aivec\AiSearchSchema\Wp;

/**
 * レガシー FAQ 抽出クラスを PSR-4 名前空間から利用するためのラッパー。
 */
if ( ! class_exists( '\AVC_AIS_Faq_Extractor' ) ) {
	require_once \AVC_AIS_DIR . 'includes/class-avc-ais-faq-extractor.php';
}

class FaqExtractor extends \AVC_AIS_Faq_Extractor {
}

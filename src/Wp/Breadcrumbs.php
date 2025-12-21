<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName

namespace Aivec\AiSearchSchema\Wp;

/**
 * レガシーパンくずクラスを PSR-4 名前空間から利用するためのラッパー。
 */
if ( ! class_exists( '\AI_Search_Schema_Breadcrumbs' ) ) {
	require_once \AI_SEARCH_SCHEMA_DIR . 'includes/class-ai-search-schema-breadcrumbs.php';
}

class Breadcrumbs extends \AI_Search_Schema_Breadcrumbs {
}

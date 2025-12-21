#!/usr/bin/env php
<?php

/**
 * README.md / readme.txt を共通ソースから生成するスクリプト。
 */

declare(strict_types=1);

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * WordPress 未ロード時の簡易 HTML エスケープ。
	 *
	 * @param string $text エスケープ対象の文字列。
	 * @return string エスケープ済み文字列。
	 */
	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}

/**
 * ファイルを読み込み、失敗した場合は例外を投げる。
 *
 * @param string $path ファイルパス。
 * @return string 読み込んだ内容。
 */
function read_file_or_fail( string $path ): string {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$content = file_get_contents( $path );

	if ( false === $content ) {
		throw new RuntimeException( 'ファイルを読み込めません: ' . esc_html( $path ) );
	}

	return $content;
}

/**
 * Markdown の見出しを WordPress readme 形式に変換する。
 *
 * @param string $markdown 変換対象の Markdown。
 * @return string 変換済みテキスト。
 */
function convert_shared_markdown_to_wp_readme( string $markdown ): string {
	$normalized = str_replace( array( "\r\n", "\r" ), "\n", $markdown );
	$lines      = explode( "\n", $normalized );
	$converted  = array();

	foreach ( $lines as $line ) {
		if ( 1 === preg_match( '/^##\s+(.*)$/u', $line, $matches ) ) {
			$converted[] = '== ' . trim( $matches[1] ) . ' ==';
			continue;
		}

		if ( 1 === preg_match( '/^###\s+(.*)$/u', $line, $matches ) ) {
			$converted[] = '= ' . trim( $matches[1] ) . ' =';
			continue;
		}

		$converted[] = $line;
	}

	return implode( "\n", $converted );
}

/**
 * 指定した見出しの本文（次の == セクションまで）を抽出する。
 *
 * @param string $content   readme.txt 全体。
 * @param string $heading   抽出したい見出し行（例: `== Changelog ==`）。
 * @return string 見出しを除いたセクション本文。見つからなければ空文字。
 */
function extract_section_body( string $content, string $heading ): string {
	$lines      = explode( "\n", str_replace( array( "\r\n", "\r" ), "\n", $content ) );
	$start_line = null;

	foreach ( $lines as $index => $line ) {
		if ( trim( $line ) === $heading ) {
			$start_line = $index + 1;
			break;
		}
	}

	if ( null === $start_line ) {
		return '';
	}

	$body = array();

	$lines_count = count( $lines );

	for ( $i = $start_line; $i < $lines_count; $i++ ) {
		$current = $lines[ $i ];
		$trimmed = trim( $current );

		if ( 0 === strpos( $trimmed, '==' ) ) {
			break;
		}

		$body[] = $current;
	}

	return rtrim( implode( "\n", $body ) );
}

$root_dir        = dirname( __DIR__ );
$shared_md_path  = $root_dir . '/docs/readme-shared.md';
$template_path   = $root_dir . '/docs/readme.txt.tpl';
$readme_md_path  = $root_dir . '/README.md';
$readme_txt_path = $root_dir . '/readme.txt';
$shared_markdown = trim( read_file_or_fail( $shared_md_path ) );
$readme_template = read_file_or_fail( $template_path );
$current_readme  = file_exists( $readme_txt_path ) ? read_file_or_fail( $readme_txt_path ) : '';
$wp_body         = convert_shared_markdown_to_wp_readme( $shared_markdown );
$changelog_body  = extract_section_body( $current_readme, '== Changelog ==' );
$upgrade_notice  = extract_section_body( $current_readme, '== Upgrade Notice ==' );

// README.md には公開向けの内容のみ含める
// 開発者向けドキュメントは .ai/readme-dev.md を参照
$readme_md = "# AI Search Schema\n\n" . $shared_markdown . "\n";

// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
file_put_contents( $readme_md_path, $readme_md );

$placeholders = array(
	'{{BODY}}'           => rtrim( $wp_body ) . "\n",
	'{{CHANGELOG}}'      => '' !== $changelog_body ? rtrim( $changelog_body ) . "\n" : '',
	'{{UPGRADE_NOTICE}}' => '' !== $upgrade_notice ? rtrim( $upgrade_notice ) . "\n" : '',
);

$filled_readme = strtr( $readme_template, $placeholders );

// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
file_put_contents( $readme_txt_path, rtrim( $filled_readme ) . "\n" );

echo "README.md と readme.txt を同期しました。\n";

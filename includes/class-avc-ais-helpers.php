<?php
/**
 * AVC_AIS_Helpers クラス
 *
 * プラグイン内で共通して使用されるヘルパー関数を提供します。
 */
defined( 'ABSPATH' ) || exit;
class AVC_AIS_Helpers {

	/**
	 * サニタイズされたテキストを取得します。
	 *
	 * @param string $input 入力テキスト
	 * @return string サニタイズされたテキスト
	 */
	public static function sanitize_text( $input ) {
		return sanitize_text_field( $input );
	}

	/**
	 * URLをサニタイズします。
	 *
	 * @param string $url 入力URL
	 * @return string サニタイズされたURL
	 */
	public static function sanitize_url( $url ) {
		return esc_url( $url );
	}

	/**
	 * 電話番号をサニタイズします。
	 *
	 * @param string $phone 入力電話番号
	 * @return string サニタイズされた電話番号
	 */
	public static function sanitize_phone( $phone ) {
		return preg_replace( '/[^0-9+]/', '', $phone );
	}

	/**
	 * 緯度をバリデーションします。
	 *
	 * @param float $lat 緯度
	 * @return float|null 有効な緯度またはnull
	 */
	public static function validate_latitude( $lat ) {
		return ( $lat >= -90 && $lat <= 90 ) ? $lat : null;
	}

	/**
	 * 経度をバリデーションします。
	 *
	 * @param float $lng 経度
	 * @return float|null 有効な経度またはnull
	 */
	public static function validate_longitude( $lng ) {
		return ( $lng >= -180 && $lng <= 180 ) ? $lng : null;
	}

	/**
	 * 言語コードをサニタイズします。
	 *
	 * @param string $lang 言語コード
	 * @return string サニタイズされた言語コード
	 */
	public static function sanitize_language_code( $lang ) {
		return preg_replace( '/[^a-zA-Z-]/', '', $lang );
	}
}

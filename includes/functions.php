<?php
/**
 * プラグイン全体で使用される関数を定義するファイル
 */

/**
 * プラグインのオプションを取得する関数
 *
 * @param string $key           オプションのキー
 * @param mixed  $default_value デフォルト値（省略時は空文字列）
 * @return mixed オプションの値
 */
function avc_ais_get_option( $key, $default_value = '' ) {
	$options = get_option( 'avc_ais_options', array() );
	return isset( $options[ $key ] ) ? $options[ $key ] : $default_value;
}

/**
 * プラグインのオプションを保存する関数
 *
 * @param string $key オプションのキー
 * @param mixed  $value オプションの値
 */
function avc_ais_update_option( $key, $value ) {
	$options         = get_option( 'avc_ais_options', array() );
	$options[ $key ] = $value;
	update_option( 'avc_ais_options', $options );
}

/**
 * サニタイズされた電話番号を取得する関数
 *
 * @param string|null $phone 電話番号
 * @return string サニタイズされた電話番号
 */
function avc_ais_sanitize_phone( $phone ) {
	return sanitize_text_field( (string) ( $phone ?? '' ) );
}

/**
 * サニタイズされたURLを取得する関数
 *
 * @param string|null $url URL
 * @return string サニタイズされたURL
 */
function avc_ais_sanitize_url( $url ) {
	return esc_url_raw( (string) ( $url ?? '' ) );
}

/**
 * サニタイズされたテキストを取得する関数
 *
 * @param string|null $text テキスト
 * @return string サニタイズされたテキスト
 */
function avc_ais_sanitize_text( $text ) {
	return sanitize_text_field( (string) ( $text ?? '' ) );
}

/**
 * サニタイズされたメールアドレスを取得する関数
 *
 * @param string|null $email メールアドレス
 * @return string サニタイズされたメールアドレス
 */
function avc_ais_sanitize_email( $email ) {
	return sanitize_email( (string) ( $email ?? '' ) );
}

/**
 * Render a labeled form field with matching id/for attributes.
 *
 * @param string $id          Base id for the field.
 * @param string $label       Label text.
 * @param string $type        Field type: text|textarea|select|checkbox|password|number.
 * @param mixed  $value       Current value.
 * @param string $description Optional description shown below the control.
 * @param string $name        Optional name attribute. Defaults to $id.
 * @param array  $options     Options for select fields as value => label.
 */
function avc_ais_render_field(
	$id,
	$label,
	$type = 'text',
	$value = '',
	$description = '',
	$name = '',
	$options = array()
) {
	$field_id = sanitize_key( $id );
	if ( '' === $name ) {
		$name = $field_id;
	}
	// Ensure value is never null to avoid PHP 8 deprecation warnings.
	$value = $value ?? '';

	echo '<div class="ais-render-field">';
	echo '<label for="' . esc_attr( $field_id ) . '">' . esc_html( $label ) . '</label>';

	switch ( $type ) {
		case 'textarea':
			printf(
				'<textarea id="%1$s" name="%2$s" rows="3">%3$s</textarea>',
				esc_attr( $field_id ),
				esc_attr( $name ),
				esc_textarea( (string) $value )
			);
			break;
		case 'select':
			echo '<select id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '">';
			foreach ( (array) $options as $option_value => $option_label ) {
				printf(
					'<option value="%1$s" %2$s>%3$s</option>',
					esc_attr( $option_value ),
					selected( (string) $value, (string) $option_value, false ),
					esc_html( $option_label )
				);
			}
			echo '</select>';
			break;
		case 'checkbox':
			printf(
				'<input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s />',
				esc_attr( $field_id ),
				esc_attr( $name ),
				checked( (bool) $value, true, false )
			);
			break;
		default:
			printf(
				'<input type="%1$s" id="%2$s" name="%3$s" value="%4$s" />',
				esc_attr( $type ),
				esc_attr( $field_id ),
				esc_attr( $name ),
				esc_attr( (string) $value )
			);
			break;
	}

	if ( '' !== $description ) {
		echo '<p class="description">' . esc_html( $description ) . '</p>';
	}
	echo '</div>';
}

/**
 * Render a tooltip help icon with hover text.
 *
 * @param string $text The tooltip text to display on hover.
 * @return string HTML for the tooltip.
 */
function avc_ais_render_tooltip( $text ) {
	if ( empty( $text ) ) {
		return '';
	}

	return sprintf(
		'<span class="ais-field__tooltip-wrap">' .
		'<span class="ais-field__tooltip-icon" aria-label="%1$s">?</span>' .
		'<span class="ais-field__tooltip-text">%2$s</span>' .
		'</span>',
		esc_attr__( 'Help', 'aivec-ai-search-schema' ),
		esc_html( $text )
	);
}

/**
 * Echo a tooltip help icon with hover text.
 *
 * @param string $text The tooltip text to display on hover.
 */
function avc_ais_tooltip( $text ) {
	echo avc_ais_render_tooltip( $text ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

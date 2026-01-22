<?php
/**
 * OpeningHoursSpecification スキーマ生成クラス。
 *
 * GraphBuilder から営業時間ロジックを分離し、単一責任の原則に従う。
 *
 * @package AVC_AIS_Schema
 */

/**
 * 営業時間スキーマを構築するクラス。
 */
class AVC_AIS_OpeningHoursBuilder {

	/**
	 * 営業時間オプションから OpeningHoursSpecification スキーマを生成する。
	 *
	 * @param array $options プラグイン設定オプション。
	 * @return array OpeningHoursSpecification スキーマの配列。
	 */
	public static function build( array $options ) {
		$slots_source = self::get_slots_source( $options );
		$slots_source = self::apply_holiday_settings( $slots_source, $options );

		return self::convert_to_schema( $slots_source );
	}

	/**
	 * オプションから営業時間スロットソースを取得する。
	 *
	 * @param array $options プラグイン設定オプション。
	 * @return array 営業時間スロットの配列。
	 */
	private static function get_slots_source( array $options ) {
		if ( empty( $options['opening_hours'] ) || ! is_array( $options['opening_hours'] ) ) {
			return array();
		}
		return $options['opening_hours'];
	}

	/**
	 * 祝日設定を適用する。
	 *
	 * @param array $slots_source 営業時間スロットの配列。
	 * @param array $options      プラグイン設定オプション。
	 * @return array 祝日設定適用後のスロット配列。
	 */
	private static function apply_holiday_settings( array $slots_source, array $options ) {
		$holiday_enabled = ! empty( $options['holiday_enabled'] );
		$holiday_mode    = ! empty( $options['holiday_mode'] ) ? $options['holiday_mode'] : 'custom';

		// 祝日が無効なら PublicHoliday を除外。
		if ( ! $holiday_enabled ) {
			return self::filter_out_public_holidays( $slots_source );
		}

		// カスタムモードならそのまま返す。
		if ( 'custom' === $holiday_mode ) {
			return $slots_source;
		}

		// 祝日を除外し、モードに応じて再生成。
		$slots_source = self::filter_out_public_holidays( $slots_source );

		if ( 'weekday' === $holiday_mode ) {
			$slots_source = self::add_weekday_based_holidays( $slots_source );
		} elseif ( 'weekend' === $holiday_mode ) {
			$slots_source = self::add_weekend_hours_for_holidays( $slots_source );
		}

		return $slots_source;
	}

	/**
	 * PublicHoliday スロットを除外する。
	 *
	 * @param array $slots_source 営業時間スロットの配列。
	 * @return array 祝日除外後のスロット配列。
	 */
	private static function filter_out_public_holidays( array $slots_source ) {
		return array_filter(
			$slots_source,
			function ( $slot ) {
				$day = $slot['day_key'] ?? $slot['day'] ?? '';
				return 'PublicHoliday' !== $day;
			}
		);
	}

	/**
	 * 平日営業時間から祝日スロットを生成する。
	 *
	 * @param array $slots_source 営業時間スロットの配列。
	 * @return array 祝日スロット追加後の配列。
	 */
	private static function add_weekday_based_holidays( array $slots_source ) {
		$weekdays      = array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday' );
		$weekday_slots = array();

		foreach ( $slots_source as $slot ) {
			$day = $slot['day_key'] ?? $slot['day'] ?? '';
			if ( ! in_array( $day, $weekdays, true ) ) {
				continue;
			}

			$slot_ranges = self::extract_slot_ranges( $slot );
			foreach ( $slot_ranges as $range ) {
				if ( empty( $range['opens'] ) || empty( $range['closes'] ) ) {
					continue;
				}
				$key                   = $range['opens'] . '|' . $range['closes'];
				$weekday_slots[ $key ] = $range;
			}
		}

		// 重複排除された時間帯を祝日スロットとして追加。
		foreach ( $weekday_slots as $range ) {
			$slots_source[] = array(
				'day_key' => 'PublicHoliday',
				'slots'   => array( $range ),
			);
		}

		return $slots_source;
	}

	/**
	 * 週末モード用のデフォルト祝日営業時間を追加する。
	 *
	 * @param array $slots_source 営業時間スロットの配列。
	 * @return array 祝日スロット追加後の配列。
	 */
	private static function add_weekend_hours_for_holidays( array $slots_source ) {
		$slots_source[] = array(
			'day_key' => 'PublicHoliday',
			'slots'   => array(
				array(
					'opens'  => '10:00',
					'closes' => '17:00',
				),
			),
		);
		return $slots_source;
	}

	/**
	 * スロットから時間範囲を抽出する。
	 *
	 * @param array $slot 単一の営業時間スロット。
	 * @return array 時間範囲の配列。
	 */
	private static function extract_slot_ranges( array $slot ) {
		if ( ! empty( $slot['slots'] ) && is_array( $slot['slots'] ) ) {
			return $slot['slots'];
		}

		return array(
			array(
				'opens'  => $slot['opens'] ?? '',
				'closes' => $slot['closes'] ?? '',
			),
		);
	}

	/**
	 * スロットソースを OpeningHoursSpecification スキーマに変換する。
	 *
	 * @param array $slots_source 営業時間スロットの配列。
	 * @return array OpeningHoursSpecification スキーマの配列。
	 */
	private static function convert_to_schema( array $slots_source ) {
		$opening_hours = array();

		foreach ( $slots_source as $slot ) {
			$day_of_week = $slot['day_key'] ?? $slot['day'] ?? '';
			if ( empty( $day_of_week ) ) {
				continue;
			}

			$slot_ranges = self::extract_slot_ranges( $slot );
			foreach ( $slot_ranges as $range ) {
				if ( empty( $range['opens'] ) || empty( $range['closes'] ) ) {
					continue;
				}
				$opening_hours[] = array(
					'@type'     => 'OpeningHoursSpecification',
					'dayOfWeek' => $day_of_week,
					'opens'     => $range['opens'],
					'closes'    => $range['closes'],
				);
			}
		}

		return $opening_hours;
	}
}

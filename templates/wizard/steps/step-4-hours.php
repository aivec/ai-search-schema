<?php
// phpcs:ignoreFile Generic.Files.LineLength.TooLong
/**
 * Step 4: Business Hours template.
 *
 * @package Aivec\AiSearchSchema
 */

defined( 'ABSPATH' ) || exit;

$options = get_option( 'ai_search_schema_options', array() );

// Days of the week.
$days = array(
	'monday'    => __( 'Monday', 'ai-search-schema' ),
	'tuesday'   => __( 'Tuesday', 'ai-search-schema' ),
	'wednesday' => __( 'Wednesday', 'ai-search-schema' ),
	'thursday'  => __( 'Thursday', 'ai-search-schema' ),
	'friday'    => __( 'Friday', 'ai-search-schema' ),
	'saturday'  => __( 'Saturday', 'ai-search-schema' ),
	'sunday'    => __( 'Sunday', 'ai-search-schema' ),
);

// Time options.
$time_options = array();
for ( $h = 0; $h < 24; $h++ ) {
	for ( $minute = 0; $minute < 60; $minute += 30 ) {
		$time                  = sprintf( '%02d:%02d', $h, $minute );
		$time_options[ $time ] = $time;
	}
}
?>
<div class="ais-wizard-step ais-wizard-step--hours">
	<div class="ais-wizard-step__header">
		<h1 class="ais-wizard-step__title">
			<?php esc_html_e( 'Business Hours', 'ai-search-schema' ); ?>
		</h1>
		<p class="ais-wizard-step__description">
			<?php esc_html_e( 'Set your business hours. This helps customers know when you are open.', 'ai-search-schema' ); ?>
		</p>
		<p class="ais-wizard-step__hint">
			<?php
			printf(
				/* translators: %s: example time range */
				esc_html__( 'Example: %s', 'ai-search-schema' ),
				'<code>09:00 - 18:00</code>'
			);
			?>
		</p>
	</div>

	<div class="ais-wizard-step__content">
		<!-- Quick Setup -->
		<div class="ais-wizard-hours-quick">
			<h3 class="ais-wizard-hours-quick__title">
				<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
					<path d="M13 3c-4.97 0-9 4.03-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42C8.27 19.99 10.51 21 13 21c4.97 0 9-4.03 9-9s-4.03-9-9-9zm-1 5v5l4.28 2.54.72-1.21-3.5-2.08V8H12z"/>
				</svg>
				<?php esc_html_e( 'Quick Setup', 'ai-search-schema' ); ?>
			</h3>
			<div class="ais-wizard-hours-quick__buttons">
				<button type="button" class="ais-wizard-btn ais-wizard-btn--outline ais-wizard-btn--small" id="ais-wizard-hours-weekdays">
					<?php esc_html_e( 'Same hours every weekday', 'ai-search-schema' ); ?>
				</button>
				<button type="button" class="ais-wizard-btn ais-wizard-btn--outline ais-wizard-btn--small" id="ais-wizard-hours-everyday">
					<?php esc_html_e( 'Same hours every day', 'ai-search-schema' ); ?>
				</button>
				<button type="button" class="ais-wizard-btn ais-wizard-btn--outline ais-wizard-btn--small" id="ais-wizard-hours-clear">
					<?php esc_html_e( 'Clear all', 'ai-search-schema' ); ?>
				</button>
			</div>
		</div>

		<!-- Hours Table -->
		<div class="ais-wizard-hours-table">
			<div class="ais-wizard-hours-table__header">
				<div class="ais-wizard-hours-table__col ais-wizard-hours-table__col--day">
					<?php esc_html_e( 'Day', 'ai-search-schema' ); ?>
				</div>
				<div class="ais-wizard-hours-table__col ais-wizard-hours-table__col--status">
					<?php esc_html_e( 'Open', 'ai-search-schema' ); ?>
				</div>
				<div class="ais-wizard-hours-table__col ais-wizard-hours-table__col--time">
					<?php esc_html_e( 'Opening Time', 'ai-search-schema' ); ?>
				</div>
				<div class="ais-wizard-hours-table__col ais-wizard-hours-table__col--time">
					<?php esc_html_e( 'Closing Time', 'ai-search-schema' ); ?>
				</div>
			</div>

			<?php foreach ( $days as $day_key => $day_label ) : ?>
				<?php
				$opens   = $options[ 'hours_' . $day_key . '_opens' ] ?? '';
				$closes  = $options[ 'hours_' . $day_key . '_closes' ] ?? '';
				$is_open = ! empty( $opens ) && ! empty( $closes );
				?>
				<div class="ais-wizard-hours-table__row" data-day="<?php echo esc_attr( $day_key ); ?>">
					<div class="ais-wizard-hours-table__col ais-wizard-hours-table__col--day">
						<span class="ais-wizard-hours-table__day-name"><?php echo esc_html( $day_label ); ?></span>
					</div>
					<div class="ais-wizard-hours-table__col ais-wizard-hours-table__col--status">
						<label class="ais-wizard-toggle">
							<input type="checkbox" name="hours_<?php echo esc_attr( $day_key ); ?>_open" class="ais-wizard-hours-toggle" <?php checked( $is_open ); ?>>
							<span class="ais-wizard-toggle__slider"></span>
						</label>
					</div>
					<div class="ais-wizard-hours-table__col ais-wizard-hours-table__col--time">
						<select name="hours_<?php echo esc_attr( $day_key ); ?>_opens" class="ais-wizard-form__select ais-wizard-hours-select" <?php echo $is_open ? '' : 'disabled'; ?>>
							<option value=""><?php esc_html_e( 'Select', 'ai-search-schema' ); ?></option>
							<?php foreach ( $time_options as $time_value => $time_label ) : ?>
								<option value="<?php echo esc_attr( $time_value ); ?>" <?php selected( $opens, $time_value ); ?>>
									<?php echo esc_html( $time_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="ais-wizard-hours-table__col ais-wizard-hours-table__col--time">
						<select name="hours_<?php echo esc_attr( $day_key ); ?>_closes" class="ais-wizard-form__select ais-wizard-hours-select" <?php echo $is_open ? '' : 'disabled'; ?>>
							<option value=""><?php esc_html_e( 'Select', 'ai-search-schema' ); ?></option>
							<?php foreach ( $time_options as $time_value => $time_label ) : ?>
								<option value="<?php echo esc_attr( $time_value ); ?>" <?php selected( $closes, $time_value ); ?>>
									<?php echo esc_html( $time_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<!-- Hours Preview -->
		<div class="ais-wizard-hours-preview">
			<h4 class="ais-wizard-hours-preview__title">
				<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor">
					<path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
				</svg>
				<?php esc_html_e( 'Preview', 'ai-search-schema' ); ?>
			</h4>
			<div class="ais-wizard-hours-preview__content" id="ais-wizard-hours-preview">
				<p class="ais-wizard-hours-preview__empty">
					<?php esc_html_e( 'Set your business hours above to see a preview.', 'ai-search-schema' ); ?>
				</p>
			</div>
		</div>
	</div>

	<div class="ais-wizard-step__footer">
		<a href="<?php echo esc_url( add_query_arg( 'step', 'location' ) ); ?>" class="ais-wizard-btn ais-wizard-btn--text">
			<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
				<path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/>
			</svg>
			<?php esc_html_e( 'Back', 'ai-search-schema' ); ?>
		</a>
		<button type="button" class="ais-wizard-btn ais-wizard-btn--primary ais-wizard-next-btn" data-next="complete">
			<?php esc_html_e( 'Continue', 'ai-search-schema' ); ?>
			<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
				<path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/>
			</svg>
		</button>
	</div>
</div>

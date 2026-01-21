<?php
// phpcs:ignoreFile Generic.Files.LineLength.TooLong
/**
 * Step 3: Location Information template.
 *
 * @package Aivec\AiSearchSchema
 */

defined( 'ABSPATH' ) || exit;

$options = get_option( 'avc_ais_options', array() );

// Business type options.
$business_types = array(
	'LocalBusiness'       => __( 'General Local Business', 'ai-search-schema' ),
	'Restaurant'          => __( 'Restaurant', 'ai-search-schema' ),
	'Cafe'                => __( 'Cafe', 'ai-search-schema' ),
	'BarOrPub'            => __( 'Bar or Pub', 'ai-search-schema' ),
	'Bakery'              => __( 'Bakery', 'ai-search-schema' ),
	'Store'               => __( 'Store / Retail', 'ai-search-schema' ),
	'BeautySalon'         => __( 'Beauty Salon', 'ai-search-schema' ),
	'HairSalon'           => __( 'Hair Salon', 'ai-search-schema' ),
	'HealthAndBeautySpa'  => __( 'Spa', 'ai-search-schema' ),
	'Dentist'             => __( 'Dentist', 'ai-search-schema' ),
	'Physician'           => __( 'Doctor / Physician', 'ai-search-schema' ),
	'Hospital'            => __( 'Hospital', 'ai-search-schema' ),
	'Pharmacy'            => __( 'Pharmacy', 'ai-search-schema' ),
	'LegalService'        => __( 'Legal Service', 'ai-search-schema' ),
	'AccountingService'   => __( 'Accounting Service', 'ai-search-schema' ),
	'RealEstateAgent'     => __( 'Real Estate Agent', 'ai-search-schema' ),
	'AutoRepair'          => __( 'Auto Repair', 'ai-search-schema' ),
	'Hotel'               => __( 'Hotel', 'ai-search-schema' ),
	'FitnessCenter'       => __( 'Fitness Center / Gym', 'ai-search-schema' ),
	'HomeAndConstruction' => __( 'Home & Construction Service', 'ai-search-schema' ),
);

// Price range options.
$price_ranges = array(
	''     => __( 'Not specified', 'ai-search-schema' ),
	'$'    => __( '$ - Budget-friendly', 'ai-search-schema' ),
	'$$'   => __( '$$ - Moderate', 'ai-search-schema' ),
	'$$$'  => __( '$$$ - Upscale', 'ai-search-schema' ),
	'$$$$' => __( '$$$$ - Luxury', 'ai-search-schema' ),
);
?>
<div class="ais-wizard-step ais-wizard-step--location">
	<div class="ais-wizard-step__header">
		<h1 class="ais-wizard-step__title">
			<?php esc_html_e( 'Business Location', 'ai-search-schema' ); ?>
		</h1>
		<p class="ais-wizard-step__description">
			<?php esc_html_e( 'Enter your business details for local SEO. This information helps customers find you.', 'ai-search-schema' ); ?>
		</p>
	</div>

	<div class="ais-wizard-step__content">
		<div class="ais-wizard-form">
			<!-- Business Type -->
			<div class="ais-wizard-form__field">
				<label for="ais-wizard-business-type" class="ais-wizard-form__label">
					<?php esc_html_e( 'Business Type', 'ai-search-schema' ); ?>
					<span class="ais-wizard-form__required">*</span>
				</label>
				<select id="ais-wizard-business-type" name="local_business_type" class="ais-wizard-form__select" required>
					<?php foreach ( $business_types as $type_key => $type_label ) : ?>
						<option value="<?php echo esc_attr( $type_key ); ?>" <?php selected( $options['local_business_type'] ?? '', $type_key ); ?>>
							<?php echo esc_html( $type_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<!-- Business Name -->
			<div class="ais-wizard-form__field">
				<label for="ais-wizard-business-name" class="ais-wizard-form__label">
					<?php esc_html_e( 'Business Name', 'ai-search-schema' ); ?>
					<span class="ais-wizard-form__required">*</span>
				</label>
				<input type="text" id="ais-wizard-business-name" name="local_business_name" class="ais-wizard-form__input" value="<?php echo esc_attr( $options['local_business_name'] ?? '' ); ?>" required>
			</div>

			<!-- Address Section -->
			<div class="ais-wizard-form__section">
				<h3 class="ais-wizard-form__section-title">
					<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
						<path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
					</svg>
					<?php esc_html_e( 'Address', 'ai-search-schema' ); ?>
				</h3>

				<div class="ais-wizard-form__row">
					<div class="ais-wizard-form__field ais-wizard-form__field--half">
						<label for="ais-wizard-postal-code" class="ais-wizard-form__label">
							<?php esc_html_e( 'Postal Code', 'ai-search-schema' ); ?>
						</label>
						<input type="text" id="ais-wizard-postal-code" name="postal_code" class="ais-wizard-form__input" value="<?php echo esc_attr( $options['postal_code'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'e.g., 100-0001', 'ai-search-schema' ); ?>">
					</div>
					<div class="ais-wizard-form__field ais-wizard-form__field--half">
						<label for="ais-wizard-country" class="ais-wizard-form__label">
							<?php esc_html_e( 'Country', 'ai-search-schema' ); ?>
						</label>
						<input type="text" id="ais-wizard-country" name="address_country" class="ais-wizard-form__input" value="<?php echo esc_attr( $options['address_country'] ?? 'JP' ); ?>">
					</div>
				</div>

				<div class="ais-wizard-form__field">
					<label for="ais-wizard-region" class="ais-wizard-form__label">
						<?php esc_html_e( 'Prefecture / State', 'ai-search-schema' ); ?>
					</label>
					<input type="text" id="ais-wizard-region" name="address_region" class="ais-wizard-form__input" value="<?php echo esc_attr( $options['address_region'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'e.g., Tokyo', 'ai-search-schema' ); ?>">
				</div>

				<div class="ais-wizard-form__field">
					<label for="ais-wizard-locality" class="ais-wizard-form__label">
						<?php esc_html_e( 'City / Town', 'ai-search-schema' ); ?>
					</label>
					<input type="text" id="ais-wizard-locality" name="address_locality" class="ais-wizard-form__input" value="<?php echo esc_attr( $options['address_locality'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'e.g., Chiyoda', 'ai-search-schema' ); ?>">
				</div>

				<div class="ais-wizard-form__field">
					<label for="ais-wizard-street" class="ais-wizard-form__label">
						<?php esc_html_e( 'Street Address', 'ai-search-schema' ); ?>
					</label>
					<input type="text" id="ais-wizard-street" name="street_address" class="ais-wizard-form__input" value="<?php echo esc_attr( $options['street_address'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'e.g., 1-1-1 Marunouchi', 'ai-search-schema' ); ?>">
				</div>

				<!-- Geocoding -->
				<div class="ais-wizard-geocoding">
					<div class="ais-wizard-form__row">
						<div class="ais-wizard-form__field ais-wizard-form__field--half">
							<label for="ais-wizard-lat" class="ais-wizard-form__label">
								<?php esc_html_e( 'Latitude', 'ai-search-schema' ); ?>
							</label>
							<input type="text" id="ais-wizard-lat" name="geo_latitude" class="ais-wizard-form__input" value="<?php echo esc_attr( $options['geo_latitude'] ?? '' ); ?>" readonly>
						</div>
						<div class="ais-wizard-form__field ais-wizard-form__field--half">
							<label for="ais-wizard-lng" class="ais-wizard-form__label">
								<?php esc_html_e( 'Longitude', 'ai-search-schema' ); ?>
							</label>
							<input type="text" id="ais-wizard-lng" name="geo_longitude" class="ais-wizard-form__input" value="<?php echo esc_attr( $options['geo_longitude'] ?? '' ); ?>" readonly>
						</div>
					</div>
					<button type="button" class="ais-wizard-btn ais-wizard-btn--secondary ais-wizard-btn--small" id="ais-wizard-geocode-btn">
						<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
							<path d="M12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm8.94 3c-.46-4.17-3.77-7.48-7.94-7.94V1h-2v2.06C6.83 3.52 3.52 6.83 3.06 11H1v2h2.06c.46 4.17 3.77 7.48 7.94 7.94V23h2v-2.06c4.17-.46 7.48-3.77 7.94-7.94H23v-2h-2.06zM12 19c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7 7-3.13 7-7 7z"/>
						</svg>
						<?php esc_html_e( 'Get Coordinates', 'ai-search-schema' ); ?>
					</button>
				</div>
			</div>

			<!-- Contact Section -->
			<div class="ais-wizard-form__section">
				<h3 class="ais-wizard-form__section-title">
					<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
						<path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/>
					</svg>
					<?php esc_html_e( 'Contact Information', 'ai-search-schema' ); ?>
				</h3>

				<div class="ais-wizard-form__row">
					<div class="ais-wizard-form__field ais-wizard-form__field--half">
						<label for="ais-wizard-phone" class="ais-wizard-form__label">
							<?php esc_html_e( 'Phone Number', 'ai-search-schema' ); ?>
						</label>
						<input type="tel" id="ais-wizard-phone" name="telephone" class="ais-wizard-form__input" value="<?php echo esc_attr( $options['telephone'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'e.g., 03-1234-5678', 'ai-search-schema' ); ?>">
					</div>
					<div class="ais-wizard-form__field ais-wizard-form__field--half">
						<label for="ais-wizard-email" class="ais-wizard-form__label">
							<?php esc_html_e( 'Email', 'ai-search-schema' ); ?>
						</label>
						<input type="email" id="ais-wizard-email" name="email" class="ais-wizard-form__input" value="<?php echo esc_attr( $options['email'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'e.g., info@example.com', 'ai-search-schema' ); ?>">
					</div>
				</div>
			</div>

			<!-- Price Range -->
			<div class="ais-wizard-form__field">
				<label for="ais-wizard-price-range" class="ais-wizard-form__label">
					<?php esc_html_e( 'Price Range', 'ai-search-schema' ); ?>
				</label>
				<select id="ais-wizard-price-range" name="price_range" class="ais-wizard-form__select">
					<?php foreach ( $price_ranges as $range_key => $range_label ) : ?>
						<option value="<?php echo esc_attr( $range_key ); ?>" <?php selected( $options['price_range'] ?? '', $range_key ); ?>>
							<?php echo esc_html( $range_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>
	</div>

	<div class="ais-wizard-step__footer">
		<a href="<?php echo esc_url( add_query_arg( 'step', 'api-key' ) ); ?>" class="ais-wizard-btn ais-wizard-btn--text">
			<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
				<path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/>
			</svg>
			<?php esc_html_e( 'Back', 'ai-search-schema' ); ?>
		</a>
		<button type="button" class="ais-wizard-btn ais-wizard-btn--primary ais-wizard-next-btn" data-next="hours">
			<?php esc_html_e( 'Continue', 'ai-search-schema' ); ?>
			<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
				<path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/>
			</svg>
		</button>
	</div>
</div>

<?php
// phpcs:ignoreFile Generic.Files.LineLength.TooLong
/**
 * Step: Google Maps API Key template.
 *
 * @package Avc\Aeo\Schema
 */

defined( 'ABSPATH' ) || exit;

$gmaps_api_key = get_option( 'ai_search_schema_gmaps_api_key', '' );
$has_api_key   = ! empty( $gmaps_api_key );
?>
<div class="ais-wizard-step avc-wizard-step--api-key">
	<div class="ais-wizard-step__header">
		<h1 class="ais-wizard-step__title">
			<?php esc_html_e( 'Google Maps API Key', 'ai-search-schema' ); ?>
		</h1>
		<p class="ais-wizard-step__description">
			<?php esc_html_e( 'Optional: Enter your Google Maps API key to enable automatic coordinate lookup for your business address.', 'ai-search-schema' ); ?>
		</p>
	</div>

	<div class="ais-wizard-step__content">
		<!-- Info Box -->
		<div class="ais-wizard-info-box avc-wizard-info-box--info">
			<div class="ais-wizard-info-box__icon">
				<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor">
					<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>
				</svg>
			</div>
			<div class="ais-wizard-info-box__content">
				<h4 class="ais-wizard-info-box__title">
					<?php esc_html_e( 'Why do I need this?', 'ai-search-schema' ); ?>
				</h4>
				<p class="ais-wizard-info-box__text">
					<?php esc_html_e( 'The Google Maps API key enables the "Get Coordinates" feature in the next step. This automatically converts your business address into latitude and longitude coordinates, which improves your local SEO and helps Google Maps show your location accurately.', 'ai-search-schema' ); ?>
				</p>
				<p class="ais-wizard-info-box__text avc-wizard-info-box__text--secondary">
					<?php esc_html_e( 'You can skip this step and enter coordinates manually, or add the API key later from the settings page.', 'ai-search-schema' ); ?>
				</p>
			</div>
		</div>

		<div class="ais-wizard-form">
			<!-- API Key Input -->
			<div class="ais-wizard-form__field">
				<label for="avc-wizard-gmaps-api-key" class="ais-wizard-form__label">
					<?php esc_html_e( 'Google Maps API Key', 'ai-search-schema' ); ?>
				</label>
				<input type="password" id="ais-wizard-gmaps-api-key" name="gmaps_api_key" class="ais-wizard-form__input" value="<?php echo esc_attr( $gmaps_api_key ); ?>" placeholder="<?php esc_attr_e( 'e.g., AIzaSyB...', 'ai-search-schema' ); ?>" autocomplete="off">
				<?php if ( $has_api_key ) : ?>
					<p class="ais-wizard-form__hint avc-wizard-form__hint--success">
						<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
							<path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
						</svg>
						<?php esc_html_e( 'API key is already configured.', 'ai-search-schema' ); ?>
					</p>
				<?php endif; ?>
			</div>

			<!-- Instructions -->
			<div class="ais-wizard-instructions">
				<h4 class="ais-wizard-instructions__title">
					<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor">
						<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H8c0-2.21 1.79-4 4-4s4 1.79 4 4c0 .88-.36 1.68-.93 2.25z"/>
					</svg>
					<?php esc_html_e( 'How to get an API key', 'ai-search-schema' ); ?>
				</h4>
				<ol class="ais-wizard-instructions__list">
					<li><?php esc_html_e( 'Go to the Google Cloud Console', 'ai-search-schema' ); ?></li>
					<li><?php esc_html_e( 'Create a new project or select an existing one', 'ai-search-schema' ); ?></li>
					<li><?php esc_html_e( 'Enable the "Geocoding API"', 'ai-search-schema' ); ?></li>
					<li><?php esc_html_e( 'Go to "Credentials" and create an API key', 'ai-search-schema' ); ?></li>
					<li><?php esc_html_e( 'Copy the API key and paste it above', 'ai-search-schema' ); ?></li>
				</ol>
				<a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener noreferrer" class="ais-wizard-btn avc-wizard-btn--outline avc-wizard-btn--small">
					<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
						<path d="M19 19H5V5h7V3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z"/>
					</svg>
					<?php esc_html_e( 'Open Google Cloud Console', 'ai-search-schema' ); ?>
				</a>
			</div>
		</div>
	</div>

	<div class="ais-wizard-step__footer">
		<a href="<?php echo esc_url( add_query_arg( 'step', 'type' ) ); ?>" class="ais-wizard-btn avc-wizard-btn--text">
			<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
				<path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/>
			</svg>
			<?php esc_html_e( 'Back', 'ai-search-schema' ); ?>
		</a>
		<div class="ais-wizard-step__footer-actions">
			<a href="<?php echo esc_url( add_query_arg( 'step', 'location' ) ); ?>" class="ais-wizard-btn avc-wizard-btn--text">
				<?php esc_html_e( 'Skip', 'ai-search-schema' ); ?>
			</a>
			<button type="button" class="ais-wizard-btn avc-wizard-btn--primary avc-wizard-next-btn" data-next="location">
				<?php esc_html_e( 'Continue', 'ai-search-schema' ); ?>
				<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
					<path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/>
				</svg>
			</button>
		</div>
	</div>
</div>

<?php
// phpcs:ignoreFile Generic.Files.LineLength.TooLong
/**
 * Step 5: Complete template.
 *
 * @package Aivec\AiSearchSchema
 */

defined( 'ABSPATH' ) || exit;

$options = get_option( 'ai_search_schema_settings', array() );
?>
<div class="ais-wizard-step ais-wizard-step--complete">
	<div class="ais-wizard-complete">
		<div class="ais-wizard-complete__icon">
			<svg viewBox="0 0 80 80" width="80" height="80">
				<circle cx="40" cy="40" r="38" fill="#22c55e" opacity="0.1"/>
				<circle cx="40" cy="40" r="30" fill="#22c55e"/>
				<path d="M35 40l5 5 10-10" stroke="#fff" stroke-width="4" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
			</svg>
		</div>

		<h1 class="ais-wizard-complete__title">
			<?php esc_html_e( 'Setup Complete!', 'ai-search-schema' ); ?>
		</h1>

		<p class="ais-wizard-complete__description">
			<?php esc_html_e( 'Congratulations! Your site is now optimized for search engines. Here is a summary of your settings.', 'ai-search-schema' ); ?>
		</p>

		<!-- Summary Card -->
		<div class="ais-wizard-summary">
			<h3 class="ais-wizard-summary__title">
				<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
					<path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/>
				</svg>
				<?php esc_html_e( 'Settings Summary', 'ai-search-schema' ); ?>
			</h3>

			<div class="ais-wizard-summary__content">
				<!-- Site Info -->
				<div class="ais-wizard-summary__section">
					<h4 class="ais-wizard-summary__section-title"><?php esc_html_e( 'Site Information', 'ai-search-schema' ); ?></h4>
					<dl class="ais-wizard-summary__list">
						<div class="ais-wizard-summary__item">
							<dt><?php esc_html_e( 'Site Name', 'ai-search-schema' ); ?></dt>
							<dd><?php echo esc_html( $options['site_name'] ?? get_bloginfo( 'name' ) ); ?></dd>
						</div>
						<div class="ais-wizard-summary__item">
							<dt><?php esc_html_e( 'Site Type', 'ai-search-schema' ); ?></dt>
							<dd><?php echo esc_html( $options['entity_type'] ?? '-' ); ?></dd>
						</div>
					</dl>
				</div>

				<?php if ( ( $options['entity_type'] ?? '' ) === 'LocalBusiness' ) : ?>
					<!-- Business Info -->
					<div class="ais-wizard-summary__section">
						<h4 class="ais-wizard-summary__section-title"><?php esc_html_e( 'Business Information', 'ai-search-schema' ); ?></h4>
						<dl class="ais-wizard-summary__list">
							<div class="ais-wizard-summary__item">
								<dt><?php esc_html_e( 'Business Name', 'ai-search-schema' ); ?></dt>
								<dd><?php echo esc_html( $options['local_business_name'] ?? '-' ); ?></dd>
							</div>
							<div class="ais-wizard-summary__item">
								<dt><?php esc_html_e( 'Business Type', 'ai-search-schema' ); ?></dt>
								<dd><?php echo esc_html( $options['local_business_type'] ?? '-' ); ?></dd>
							</div>
							<?php if ( ! empty( $options['street_address'] ) || ! empty( $options['address_locality'] ) ) : ?>
								<div class="ais-wizard-summary__item">
									<dt><?php esc_html_e( 'Address', 'ai-search-schema' ); ?></dt>
									<dd>
										<?php
										$address_parts = array_filter(
											array(
												$options['postal_code'] ?? '',
												$options['address_region'] ?? '',
												$options['address_locality'] ?? '',
												$options['street_address'] ?? '',
											)
										);
										echo esc_html( implode( ' ', $address_parts ) );
										?>
									</dd>
								</div>
							<?php endif; ?>
							<?php if ( ! empty( $options['telephone'] ) ) : ?>
								<div class="ais-wizard-summary__item">
									<dt><?php esc_html_e( 'Phone', 'ai-search-schema' ); ?></dt>
									<dd><?php echo esc_html( $options['telephone'] ); ?></dd>
								</div>
							<?php endif; ?>
						</dl>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<!-- Schema Preview -->
		<div class="ais-wizard-schema-preview">
			<h3 class="ais-wizard-schema-preview__title">
				<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
					<path d="M9.4 16.6L4.8 12l4.6-4.6L8 6l-6 6 6 6 1.4-1.4zm5.2 0l4.6-4.6-4.6-4.6L16 6l6 6-6 6-1.4-1.4z"/>
				</svg>
				<?php esc_html_e( 'Generated Schema', 'ai-search-schema' ); ?>
			</h3>
			<div class="ais-wizard-schema-preview__content">
				<button type="button" class="ais-wizard-btn ais-wizard-btn--outline ais-wizard-btn--small" id="ais-wizard-show-schema">
					<?php esc_html_e( 'View JSON-LD Schema', 'ai-search-schema' ); ?>
				</button>
				<pre class="ais-wizard-schema-preview__code" id="ais-wizard-schema-code" style="display: none;"></pre>
			</div>
		</div>

		<!-- Next Steps -->
		<div class="ais-wizard-next-steps">
			<h3 class="ais-wizard-next-steps__title"><?php esc_html_e( 'Next Steps', 'ai-search-schema' ); ?></h3>
			<ul class="ais-wizard-next-steps__list">
				<li>
					<svg viewBox="0 0 24 24" width="20" height="20" fill="#22c55e">
						<path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
					</svg>
					<?php esc_html_e( 'Schema markup is now active on your site', 'ai-search-schema' ); ?>
				</li>
				<li>
					<svg viewBox="0 0 24 24" width="20" height="20" fill="#4f46e5">
						<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
					</svg>
					<?php
					printf(
						/* translators: %s: link to Google Rich Results Test */
						esc_html__( 'Test your schema with %s', 'ai-search-schema' ),
						'<a href="https://search.google.com/test/rich-results" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Google Rich Results Test', 'ai-search-schema' ) . '</a>'
					);
					?>
				</li>
				<li>
					<svg viewBox="0 0 24 24" width="20" height="20" fill="#4f46e5">
						<path d="M19.14 12.94c.04-.31.06-.63.06-.94 0-.31-.02-.63-.06-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.04.31-.06.63-.06.94s.02.63.06.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/>
					</svg>
					<?php esc_html_e( 'Customize additional settings from the plugin settings page', 'ai-search-schema' ); ?>
				</li>
			</ul>
		</div>

		<!-- Actions -->
		<div class="ais-wizard-complete__actions">
			<a href="<?php echo esc_url( admin_url( 'options-general.php?page=ai-search-schema' ) ); ?>" class="ais-wizard-btn ais-wizard-btn--primary ais-wizard-btn--large">
				<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
					<path d="M19.14 12.94c.04-.31.06-.63.06-.94 0-.31-.02-.63-.06-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.04.31-.06.63-.06.94s.02.63.06.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/>
				</svg>
				<?php esc_html_e( 'Go to Settings', 'ai-search-schema' ); ?>
			</a>
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="ais-wizard-btn ais-wizard-btn--secondary ais-wizard-btn--large" target="_blank">
				<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
					<path d="M19 19H5V5h7V3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z"/>
				</svg>
				<?php esc_html_e( 'View Site', 'ai-search-schema' ); ?>
			</a>
		</div>
	</div>
</div>

<?php
// phpcs:ignoreFile Generic.Files.LineLength.TooLong
/**
 * Step 1: Basic Information template.
 *
 * @package Aivec\AiSearchSchema
 */

defined( 'ABSPATH' ) || exit;

$options   = get_option( 'avc_ais_options', array() );
$site_name = $options['site_name'] ?? get_bloginfo( 'name' );
$site_desc = $options['site_description'] ?? get_bloginfo( 'description' );
$site_logo = $options['logo_url'] ?? '';
?>
<div class="ais-wizard-step ais-wizard-step--basics">
	<div class="ais-wizard-step__header">
		<h1 class="ais-wizard-step__title">
			<?php esc_html_e( 'Basic Information', 'ai-search-schema' ); ?>
		</h1>
		<p class="ais-wizard-step__description">
			<?php esc_html_e( 'Tell us about your site. This information will be used for search engine optimization.', 'ai-search-schema' ); ?>
		</p>
	</div>

	<div class="ais-wizard-step__content">
		<div class="ais-wizard-form">
			<!-- Site Name -->
			<div class="ais-wizard-form__field">
				<label for="ais-wizard-site-name" class="ais-wizard-form__label">
					<?php esc_html_e( 'Site Name', 'ai-search-schema' ); ?>
					<span class="ais-wizard-form__required">*</span>
				</label>
				<input type="text" id="ais-wizard-site-name" name="site_name" class="ais-wizard-form__input" value="<?php echo esc_attr( $site_name ); ?>" required>
				<p class="ais-wizard-form__hint">
					<?php esc_html_e( 'The name of your website as it should appear in search results.', 'ai-search-schema' ); ?>
				</p>
			</div>

			<!-- Site Description -->
			<div class="ais-wizard-form__field">
				<label for="ais-wizard-site-desc" class="ais-wizard-form__label">
					<?php esc_html_e( 'Site Description', 'ai-search-schema' ); ?>
				</label>
				<textarea id="ais-wizard-site-desc" name="site_description" class="ais-wizard-form__textarea" rows="3"><?php echo esc_textarea( $site_desc ); ?></textarea>
				<p class="ais-wizard-form__hint">
					<?php esc_html_e( 'A brief description of your website (optional).', 'ai-search-schema' ); ?>
				</p>
			</div>

			<!-- Logo -->
			<div class="ais-wizard-form__field">
				<label class="ais-wizard-form__label">
					<?php esc_html_e( 'Site Logo', 'ai-search-schema' ); ?>
				</label>
				<div class="ais-wizard-logo-upload">
					<div class="ais-wizard-logo-upload__preview" id="ais-wizard-logo-preview">
						<?php if ( $site_logo ) : ?>
							<img src="<?php echo esc_url( $site_logo ); ?>" alt="<?php esc_attr_e( 'Logo preview', 'ai-search-schema' ); ?>">
						<?php else : ?>
							<div class="ais-wizard-logo-upload__placeholder">
								<svg viewBox="0 0 24 24" width="48" height="48" fill="#9ca3af">
									<path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/>
								</svg>
								<span><?php esc_html_e( 'No logo selected', 'ai-search-schema' ); ?></span>
							</div>
						<?php endif; ?>
					</div>
					<div class="ais-wizard-logo-upload__actions">
						<button type="button" class="ais-wizard-btn ais-wizard-btn--secondary" id="ais-wizard-upload-logo">
							<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
								<path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
							</svg>
							<?php esc_html_e( 'Upload Logo', 'ai-search-schema' ); ?>
						</button>
						<button type="button" class="ais-wizard-btn ais-wizard-btn--text" id="ais-wizard-remove-logo" style="<?php echo $site_logo ? '' : 'display: none;'; ?>">
							<?php esc_html_e( 'Remove', 'ai-search-schema' ); ?>
						</button>
					</div>
					<input type="hidden" id="ais-wizard-logo-url" name="logo_url" value="<?php echo esc_attr( $site_logo ); ?>">
				</div>
				<p class="ais-wizard-form__hint">
					<?php esc_html_e( 'Recommended size: 600x60 pixels. PNG or SVG format.', 'ai-search-schema' ); ?>
				</p>
			</div>
		</div>
	</div>

	<div class="ais-wizard-step__footer">
		<a href="<?php echo esc_url( add_query_arg( 'step', 'welcome' ) ); ?>" class="ais-wizard-btn ais-wizard-btn--text">
			<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
				<path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/>
			</svg>
			<?php esc_html_e( 'Back', 'ai-search-schema' ); ?>
		</a>
		<button type="button" class="ais-wizard-btn ais-wizard-btn--primary ais-wizard-next-btn" data-next="type">
			<?php esc_html_e( 'Continue', 'ai-search-schema' ); ?>
			<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
				<path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/>
			</svg>
		</button>
	</div>
</div>

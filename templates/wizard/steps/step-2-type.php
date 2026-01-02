<?php
// phpcs:ignoreFile Generic.Files.LineLength.TooLong
/**
 * Step 2: Site Type Selection template.
 *
 * @package Aivec\AiSearchSchema
 */

defined( 'ABSPATH' ) || exit;

$options     = get_option( 'ai_search_schema_options', array() );
$entity_type = $options['entity_type'] ?? '';

// Site type options with icons (Organization and LocalBusiness only for schema compatibility).
$site_types = array(
	'LocalBusiness' => array(
		'label'       => __( 'Local Business', 'ai-search-schema' ),
		'description' => __( 'Physical store, restaurant, service provider with a location', 'ai-search-schema' ),
		'icon'        => '<path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>',
		'examples'    => __( 'e.g., Café, Beauty Salon, Clinic', 'ai-search-schema' ),
	),
	'Organization'  => array(
		'label'       => __( 'Organization / Company', 'ai-search-schema' ),
		'description' => __( 'Corporate website, non-profit, educational institution, personal blog, online service', 'ai-search-schema' ),
		'icon'        => '<path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10zm-2-8h-2v2h2v-2zm0 4h-2v2h2v-2z"/>',
		'examples'    => __( 'e.g., Corporation, NPO, Blog, News site', 'ai-search-schema' ),
	),
);
?>
<div class="ais-wizard-step ais-wizard-step--type">
	<div class="ais-wizard-step__header">
		<h1 class="ais-wizard-step__title">
			<?php esc_html_e( 'What type of site is this?', 'ai-search-schema' ); ?>
		</h1>
		<p class="ais-wizard-step__description">
			<?php esc_html_e( 'Select the option that best describes your website. This helps search engines understand your content.', 'ai-search-schema' ); ?>
		</p>
	</div>

	<div class="ais-wizard-step__content">
		<div class="ais-wizard-type-grid">
			<?php foreach ( $site_types as $type_key => $type_data ) : ?>
				<label class="ais-wizard-type-card <?php echo $entity_type === $type_key ? 'ais-wizard-type-card--selected' : ''; ?>">
					<input type="radio" name="entity_type" value="<?php echo esc_attr( $type_key ); ?>" <?php checked( $entity_type, $type_key ); ?>>
					<div class="ais-wizard-type-card__icon">
						<svg viewBox="0 0 24 24" width="32" height="32" fill="currentColor">
							<?php echo $type_data['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</svg>
					</div>
					<div class="ais-wizard-type-card__content">
						<div class="ais-wizard-type-card__title"><?php echo esc_html( $type_data['label'] ); ?></div>
						<div class="ais-wizard-type-card__description"><?php echo esc_html( $type_data['description'] ); ?></div>
						<div class="ais-wizard-type-card__examples"><?php echo esc_html( $type_data['examples'] ); ?></div>
					</div>
					<div class="ais-wizard-type-card__check">
						<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor">
							<path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
						</svg>
					</div>
				</label>
			<?php endforeach; ?>
		</div>

		<div class="ais-wizard-info-box" id="ais-wizard-type-info" style="display: none;">
			<div class="ais-wizard-info-box__icon">
				<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
					<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>
				</svg>
			</div>
			<div class="ais-wizard-info-box__content">
				<div class="ais-wizard-info-box__title" id="ais-wizard-type-info-title"></div>
				<div class="ais-wizard-info-box__text" id="ais-wizard-type-info-text"></div>
			</div>
		</div>
	</div>

	<div class="ais-wizard-step__footer">
		<a href="<?php echo esc_url( add_query_arg( 'step', 'basics' ) ); ?>" class="ais-wizard-btn ais-wizard-btn--text">
			<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
				<path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/>
			</svg>
			<?php esc_html_e( 'Back', 'ai-search-schema' ); ?>
		</a>
		<button type="button" class="ais-wizard-btn ais-wizard-btn--primary ais-wizard-next-btn" data-next="api-key" id="ais-wizard-type-next" disabled>
			<?php esc_html_e( 'Continue', 'ai-search-schema' ); ?>
			<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
				<path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/>
			</svg>
		</button>
	</div>
</div>

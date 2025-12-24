<?php
/* phpcs:disable WordPress.Files.LineLength.TooLong */
/**
 * Admin settings page template
 *
 * @var array $options
 * @var string $option_name
 * @var array $social_choices
 * @var array $content_models
 * @var bool $gmaps_api_key_set
 *
 * @package AI_Search_Schema
 */

$logo_url              = ! empty( $options['logo_url'] ) ? esc_url( $options['logo_url'] ) : '';
$logo_id               = ! empty( $options['logo_id'] ) ? absint( $options['logo_id'] ) : 0;
$lb_image_url          = ! empty( $options['lb_image_url'] ) ? esc_url( $options['lb_image_url'] ) : '';
$lb_image_id           = ! empty( $options['lb_image_id'] ) ? absint( $options['lb_image_id'] ) : 0;
$social_links          = ! empty( $options['social_links'] ) && is_array( $options['social_links'] )
	? $options['social_links']
	: array(
		array(
			'network' => 'facebook',
			'account' => '',
			'label'   => '',
		),
	);
$opening_hours_entries = ! empty( $options['opening_hours'] ) && is_array( $options['opening_hours'] )
	? $options['opening_hours']
	: array();
$opening_hours_rows    = array();
foreach ( $opening_hours_entries as $entry ) {
	$day_key = isset( $entry['day_key'] ) ? $entry['day_key'] : ( $entry['day'] ?? 'Monday' );
	$slots   = array();
	if ( ! empty( $entry['slots'] ) && is_array( $entry['slots'] ) ) {
		$slots = $entry['slots'];
	} else {
		$slots[] = array(
			'opens'  => isset( $entry['opens'] ) ? $entry['opens'] : '',
			'closes' => isset( $entry['closes'] ) ? $entry['closes'] : '',
		);
	}
	foreach ( $slots as $slot ) {
		$opening_hours_rows[] = array(
			'day_key' => $day_key,
			'opens'   => isset( $slot['opens'] ) ? $slot['opens'] : '',
			'closes'  => isset( $slot['closes'] ) ? $slot['closes'] : '',
		);
	}
}
if ( empty( $opening_hours_rows ) ) {
	$opening_hours_rows[] = array(
		'day_key' => 'Monday',
		'opens'   => '',
		'closes'  => '',
	);
}
$rich_results_target = ! empty( $options['site_url'] ) ? $options['site_url'] : get_site_url();
$rich_results_url    = 'https://search.google.com/test/rich-results?url=' . rawurlencode( $rich_results_target );
$language_count      = ! empty( $options['languages'] ) && is_array( $options['languages'] ) ? count( $options['languages'] ) : 0;
$language_count      = $language_count ? $language_count : 1;
$hero_country        = ! empty( $options['country_code'] ) ? $options['country_code'] : __( 'Not set', 'ai-search-schema' );
$hero_entity         = ! empty( $options['entity_type'] ) ? $options['entity_type'] : 'Organization';
$settings_notices    = '';
if ( function_exists( 'settings_errors' ) ) {
	ob_start();
	settings_errors( $option_name );
	$settings_notices = trim( ob_get_clean() );
}

// 診断サマリーの集計
$diag_errors   = array();
$diag_warnings = array();
if ( ! empty( $diagnostics['groups'] ) && is_array( $diagnostics['groups'] ) ) {
	foreach ( $diagnostics['groups'] as $group ) {
		if ( empty( $group['items'] ) || ! is_array( $group['items'] ) ) {
			continue;
		}
		$group_title = $group['title'] ?? '';
		foreach ( $group['items'] as $item ) {
			if ( 'error' === ( $item['status'] ?? '' ) ) {
				$diag_errors[] = array(
					'group'   => $group_title,
					'message' => $item['message'] ?? '',
				);
			} elseif ( 'warning' === ( $item['status'] ?? '' ) ) {
				$diag_warnings[] = array(
					'group'   => $group_title,
					'message' => $item['message'] ?? '',
				);
			}
		}
	}
}
$diag_error_count   = count( $diag_errors );
$diag_warning_count = count( $diag_warnings );
$diag_has_issues    = $diag_error_count > 0 || $diag_warning_count > 0;
?>

<div class="wrap ais-modern-settings">
	

	<?php if ( ! empty( $settings_notices ) ) : ?>
		<div class="ais-modern-settings__notices">
			<?php echo $settings_notices; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	<?php endif; ?>

	<div class="ais-modern-settings__content">
		<h1 class="screen-reader-text ais-settings-page-title"><?php esc_html_e( 'AEO Schema Settings', 'ai-search-schema' ); ?></h1>

		<div class="ais-settings-hero">
			<div class="ais-hero__content">
				<span class="ais-badge"><?php esc_html_e( 'Schema Console', 'ai-search-schema' ); ?></span>
				<div class="ais-hero__title" aria-hidden="true"><?php esc_html_e( 'AEO Schema Settings', 'ai-search-schema' ); ?></div>
				<p><?php esc_html_e( 'Manage brand profiles, publishing entities, and local SEO schema from a single console.', 'ai-search-schema' ); ?></p>
			</div>
			<div class="ais-hero__meta">
				<div class="ais-hero__meta-card">
					<strong><?php echo esc_html( $language_count ); ?></strong>
					<span><?php esc_html_e( 'Active Languages', 'ai-search-schema' ); ?></span>
				</div>
				<div class="ais-hero__meta-card">
					<strong><?php echo esc_html( $hero_country ); ?></strong>
					<span><?php esc_html_e( 'Primary Country', 'ai-search-schema' ); ?></span>
				</div>
				<div class="ais-hero__meta-card">
					<strong><?php echo esc_html( $hero_entity ); ?></strong>
					<span><?php esc_html_e( 'Entity Type', 'ai-search-schema' ); ?></span>
				</div>
			</div>
		</div>

		<?php if ( $diag_has_issues ) : ?>
		<div class="ais-validation-summary<?php echo $diag_error_count > 0 ? ' ais-validation-summary--has-errors' : ''; ?>">
			<div class="ais-validation-summary__header">
				<div class="ais-validation-summary__icon">
					<?php if ( $diag_error_count > 0 ) : ?>
						<span class="dashicons dashicons-warning"></span>
					<?php else : ?>
						<span class="dashicons dashicons-info"></span>
					<?php endif; ?>
				</div>
				<div class="ais-validation-summary__title">
					<strong><?php esc_html_e( 'Schema Validation Summary', 'ai-search-schema' ); ?></strong>
					<span class="ais-validation-summary__counts">
						<?php if ( $diag_error_count > 0 ) : ?>
							<span class="ais-validation-summary__count ais-validation-summary__count--error">
								<?php
								/* translators: %d: number of errors */
								printf( esc_html( _n( '%d error', '%d errors', $diag_error_count, 'ai-search-schema' ) ), absint( $diag_error_count ) );
								?>
							</span>
						<?php endif; ?>
						<?php if ( $diag_warning_count > 0 ) : ?>
							<span class="ais-validation-summary__count ais-validation-summary__count--warning">
								<?php
								/* translators: %d: number of warnings */
								printf( esc_html( _n( '%d warning', '%d warnings', $diag_warning_count, 'ai-search-schema' ) ), absint( $diag_warning_count ) );
								?>
							</span>
						<?php endif; ?>
					</span>
				</div>
				<button type="button" class="ais-validation-summary__toggle" aria-expanded="false" aria-controls="ais-validation-details">
					<span class="screen-reader-text"><?php esc_html_e( 'Toggle details', 'ai-search-schema' ); ?></span>
					<span class="dashicons dashicons-arrow-down-alt2"></span>
				</button>
			</div>
			<div id="ais-validation-details" class="ais-validation-summary__details" hidden>
				<?php if ( $diag_error_count > 0 ) : ?>
				<div class="ais-validation-summary__section ais-validation-summary__section--errors">
					<h4><?php esc_html_e( 'Errors', 'ai-search-schema' ); ?></h4>
					<ul>
						<?php foreach ( $diag_errors as $err ) : ?>
						<li>
							<span class="ais-validation-summary__group"><?php echo esc_html( $err['group'] ); ?>:</span>
							<?php echo esc_html( $err['message'] ); ?>
						</li>
						<?php endforeach; ?>
					</ul>
				</div>
				<?php endif; ?>
				<?php if ( $diag_warning_count > 0 ) : ?>
				<div class="ais-validation-summary__section ais-validation-summary__section--warnings">
					<h4><?php esc_html_e( 'Warnings', 'ai-search-schema' ); ?></h4>
					<ul>
						<?php foreach ( $diag_warnings as $warn ) : ?>
						<li>
							<span class="ais-validation-summary__group"><?php echo esc_html( $warn['group'] ); ?>:</span>
							<?php echo esc_html( $warn['message'] ); ?>
						</li>
						<?php endforeach; ?>
					</ul>
				</div>
				<?php endif; ?>
			</div>
		</div>
		<?php endif; ?>

		<form method="post" action="options.php" class="ais-settings-form">
		<?php settings_fields( $option_name ); ?>

		<div class="ais-card-grid ais-card-grid--top">
			<section class="ais-card">
				<div class="ais-card__header">
					<h2><?php esc_html_e( 'Brand & Site', 'ai-search-schema' ); ?></h2>
					<p><?php esc_html_e( 'Register brand, site, and contact information.', 'ai-search-schema' ); ?></p>
				</div>
				<div class="ais-card__body">
					<div class="ais-field-grid two-col">
						<div class="ais-field">
							<label for="ais-company-name"><?php esc_html_e( 'Company or individual name', 'ai-search-schema' ); ?><span class="ais-required">*</span><?php ais_tooltip( __( 'The official name of your organization or personal name. Used in Organization/LocalBusiness schema.', 'ai-search-schema' ) ); ?></label>
							<input id="ais-company-name" type="text" name="<?php echo esc_attr( $option_name ); ?>[company_name]" value="<?php echo esc_attr( $options['company_name'] ); ?>" placeholder="<?php esc_attr_e( 'Example: Acme Inc. / Taro Yamada', 'ai-search-schema' ); ?>" />
						</div>
						<div class="ais-field">
							<label for="ais-site-name"><?php esc_html_e( 'Site name', 'ai-search-schema' ); ?><?php ais_tooltip( __( 'The name of your website. Used in WebSite schema for sitelinks search box.', 'ai-search-schema' ) ); ?></label>
							<input id="ais-site-name" type="text" name="<?php echo esc_attr( $option_name ); ?>[site_name]" value="<?php echo esc_attr( $options['site_name'] ); ?>" placeholder="<?php esc_attr_e( 'Example: My Company Blog', 'ai-search-schema' ); ?>" />
						</div>
						<div class="ais-field">
							<label for="ais-phone"><?php esc_html_e( 'Phone number', 'ai-search-schema' ); ?><span class="ais-required" title="<?php esc_attr_e( 'Required for LocalBusiness', 'ai-search-schema' ); ?>">*</span><?php ais_tooltip( __( 'Contact phone number in international format. Required for LocalBusiness.', 'ai-search-schema' ) ); ?></label>
							<input id="ais-phone" type="text" name="<?php echo esc_attr( $option_name ); ?>[phone]" value="<?php echo esc_attr( $options['phone'] ); ?>" placeholder="<?php esc_attr_e( 'Example: +81-3-1234-5678', 'ai-search-schema' ); ?>" />
						</div>
						<div class="ais-field">
							<label for="ais-country-code"><?php esc_html_e( 'Country code', 'ai-search-schema' ); ?><?php ais_tooltip( __( 'Select the primary country or region.', 'ai-search-schema' ) ); ?></label>
							<select id="ais-country-code" name="<?php echo esc_attr( $option_name ); ?>[country_code]">
								<?php foreach ( $country_choices as $country_code => $country_label ) : ?>
									<option value="<?php echo esc_attr( $country_code ); ?>" <?php selected( $options['country_code'], $country_code ); ?>><?php echo esc_html( $country_label ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
					<div class="ais-field">
						<label for="ais-site-url"><?php esc_html_e( 'Site URL', 'ai-search-schema' ); ?><span class="ais-required">*</span><?php ais_tooltip( __( 'Your website URL. Used as the canonical URL in WebSite schema.', 'ai-search-schema' ) ); ?></label>
						<input id="ais-site-url" type="text" name="<?php echo esc_attr( $option_name ); ?>[site_url]" value="<?php echo esc_attr( $options['site_url'] ); ?>" placeholder="<?php esc_attr_e( 'Example: https://example.com', 'ai-search-schema' ); ?>" />
						<div class="ais-field__description">
							<a class="button button-secondary" href="<?php echo esc_url( $rich_results_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open Google Rich Results Test', 'ai-search-schema' ); ?></a>
							<span><?php esc_html_e( 'Save first, then click again to test the latest URL.', 'ai-search-schema' ); ?></span>
						</div>
					</div>
					<div class="ais-field">
						<label for="ais-language-select"><?php esc_html_e( 'Supported languages', 'ai-search-schema' ); ?></label>
						<div id="ais-language-tags" class="ais-language-tag-list" data-name="<?php echo esc_attr( $option_name ); ?>[languages][]">
							<?php
							foreach ( $options['languages'] as $lang_code ) :
								if ( ! isset( $language_choices[ $lang_code ] ) ) {
									continue;
								}
								$label = $language_choices[ $lang_code ];
								/* translators: %s: The language label shown as a tag. */
								$remove_label = sprintf( __( 'Remove "%s"', 'ai-search-schema' ), $label );
								?>
								<span class="ais-language-tag" data-code="<?php echo esc_attr( $lang_code ); ?>">
									<span class="ais-language-label"><?php echo esc_html( $label ); ?></span>
									<button type="button" class="button-link-delete ais-language-remove" aria-label="<?php echo esc_attr( $remove_label ); ?>">
										<span aria-hidden="true">&times;</span>
										<span class="screen-reader-text"><?php echo esc_html( $remove_label ); ?></span>
									</button>
									<input type="hidden" name="<?php echo esc_attr( $option_name ); ?>[languages][]" value="<?php echo esc_attr( $lang_code ); ?>" />
								</span>
							<?php endforeach; ?>
						</div>
						<select id="ais-language-select">
							<option value=""><?php esc_html_e( 'Select a language...', 'ai-search-schema' ); ?></option>
							<?php
							foreach ( $language_choices as $lang_code => $lang_label ) :
								if ( in_array( $lang_code, $options['languages'], true ) ) {
									continue;
								}
								?>
								<option value="<?php echo esc_attr( $lang_code ); ?>"><?php echo esc_html( $lang_label ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="ais-field__description"><?php esc_html_e( 'Added languages appear as tags. Remove them with the × action.', 'ai-search-schema' ); ?></p>
					</div>
				</div>
			</section>

			<section class="ais-card">
				<div class="ais-card__header">
					<h2><?php esc_html_e( 'Brand assets & social', 'ai-search-schema' ); ?></h2>
					<p><?php esc_html_e( 'Manage logos, storefront imagery, and social profiles.', 'ai-search-schema' ); ?></p>
				</div>
				<div class="ais-card__body">
					<div class="ais-field-grid two-col">
						<div class="ais-field">
							<label><?php esc_html_e( 'Logo', 'ai-search-schema' ); ?><?php ais_tooltip( __( 'Your organization logo. Used in Organization schema and as publisher logo for articles.', 'ai-search-schema' ) ); ?></label>
							<div class="ais-image-field">
								<div id="ais-logo-preview" class="ais-image-preview"></div>
								<div class="ais-image-controls">
									<input type="hidden" id="ais-logo-id" name="<?php echo esc_attr( $option_name ); ?>[logo_id]" value="<?php echo esc_attr( $logo_id ); ?>" />
									<input type="text" id="ais-logo-url" name="<?php echo esc_attr( $option_name ); ?>[logo_url]" value="<?php echo esc_attr( $logo_url ); ?>" readonly />
									<div class="ais-button-row">
										<button type="button" class="button" id="ais-logo-select" data-title="<?php esc_attr_e( 'Select logo', 'ai-search-schema' ); ?>" data-button="<?php esc_attr_e( 'Insert logo', 'ai-search-schema' ); ?>"><?php esc_html_e( 'Select from media library', 'ai-search-schema' ); ?></button>
										<button type="button" class="button-link ais-inline-link <?php echo $logo_url ? '' : 'hidden'; ?>" id="ais-logo-remove"><?php esc_html_e( 'Clear logo', 'ai-search-schema' ); ?></button>
									</div>
								</div>
							</div>
						</div>
						<div class="ais-field">
							<label><?php esc_html_e( 'Storefront image', 'ai-search-schema' ); ?><?php ais_tooltip( __( 'Used for the LocalBusiness image property. Upload a storefront photo that differs from the logo.', 'ai-search-schema' ) ); ?></label>
							<div class="ais-image-field">
								<div id="ais-lb-image-preview" class="ais-image-preview"></div>
								<div class="ais-image-controls">
									<input type="hidden" id="ais-lb-image-id" name="<?php echo esc_attr( $option_name ); ?>[lb_image_id]" value="<?php echo esc_attr( $lb_image_id ); ?>" />
									<input type="text" id="ais-lb-image-url" name="<?php echo esc_attr( $option_name ); ?>[lb_image_url]" value="<?php echo esc_attr( $lb_image_url ); ?>" readonly />
									<div class="ais-button-row">
										<button type="button" class="button" id="ais-lb-image-select" data-title="<?php esc_attr_e( 'Select storefront image', 'ai-search-schema' ); ?>" data-button="<?php esc_attr_e( 'Insert image', 'ai-search-schema' ); ?>"><?php esc_html_e( 'Select from media library', 'ai-search-schema' ); ?></button>
										<button type="button" class="button-link ais-inline-link <?php echo $lb_image_url ? '' : 'hidden'; ?>" id="ais-lb-image-remove"><?php esc_html_e( 'Clear image', 'ai-search-schema' ); ?></button>
									</div>
								</div>
							</div>
						</div>
						<div class="ais-field">
							<label><?php esc_html_e( 'Store photo (LocalBusiness image)', 'ai-search-schema' ); ?><?php ais_tooltip( __( 'Used only for LocalBusiness image. Logo will not be used as a fallback.', 'ai-search-schema' ) ); ?></label>
							<div class="ais-image-field">
								<div id="ais-store-image-preview" class="ais-image-preview"></div>
								<div class="ais-image-controls">
									<input type="hidden" id="ais-store-image-id" name="<?php echo esc_attr( $option_name ); ?>[store_image_id]" value="<?php echo esc_attr( $options['store_image_id'] ?? 0 ); ?>" />
									<input type="text" id="ais-store-image-url" name="<?php echo esc_attr( $option_name ); ?>[store_image_url]" value="<?php echo esc_attr( $options['store_image_url'] ?? '' ); ?>" readonly />
									<div class="ais-button-row">
										<button type="button" class="button" id="ais-store-image-select" data-title="<?php esc_attr_e( 'Select store photo', 'ai-search-schema' ); ?>" data-button="<?php esc_attr_e( 'Insert image', 'ai-search-schema' ); ?>"><?php esc_html_e( 'Select from media library', 'ai-search-schema' ); ?></button>
										<button type="button" class="button-link ais-inline-link <?php echo ! empty( $options['store_image_url'] ) ? '' : 'hidden'; ?>" id="ais-store-image-remove"><?php esc_html_e( 'Clear image', 'ai-search-schema' ); ?></button>
									</div>
								</div>
							</div>
						</div>
					</div>
					<div class="ais-field">
						<label><?php esc_html_e( 'Social profiles', 'ai-search-schema' ); ?><?php ais_tooltip( __( 'Add your social media profiles. Used in Organization/LocalBusiness sameAs property for knowledge panel.', 'ai-search-schema' ) ); ?></label>
						<div id="ais-social-rows">
							<?php
							foreach ( $social_links as $index => $row ) :
								$network = isset( $row['network'] ) ? $row['network'] : 'facebook';
								$account = isset( $row['account'] ) ? $row['account'] : '';
								$label   = isset( $row['label'] ) ? $row['label'] : '';
								?>
								<div class="ais-social-row" data-index="<?php echo esc_attr( $index ); ?>">
									<select class="ais-social-network-select" name="<?php echo esc_attr( $option_name ); ?>[social_links][<?php echo esc_attr( $index ); ?>][network]" data-name="<?php echo esc_attr( $option_name ); ?>[social_links][__index__][network]">
										<?php foreach ( $social_choices as $choice_key => $choice_label ) : ?>
											<option value="<?php echo esc_attr( $choice_key ); ?>" <?php selected( $network, $choice_key ); ?>><?php echo esc_html( $choice_label ); ?></option>
										<?php endforeach; ?>
									</select>
									<input type="text" name="<?php echo esc_attr( $option_name ); ?>[social_links][<?php echo esc_attr( $index ); ?>][account]" data-name="<?php echo esc_attr( $option_name ); ?>[social_links][__index__][account]" value="<?php echo esc_attr( $account ); ?>" placeholder="<?php esc_attr_e( 'Account name or profile URL', 'ai-search-schema' ); ?>" />
									<input type="text" class="ais-social-other-label <?php echo 'other' === $network ? '' : 'hidden'; ?>" name="<?php echo esc_attr( $option_name ); ?>[social_links][<?php echo esc_attr( $index ); ?>][label]" data-name="<?php echo esc_attr( $option_name ); ?>[social_links][__index__][label]" value="<?php echo esc_attr( $label ); ?>" placeholder="<?php esc_attr_e( 'Other service label', 'ai-search-schema' ); ?>" />
									<button type="button" class="button-link-delete ais-remove-social"><?php esc_html_e( 'Remove', 'ai-search-schema' ); ?></button>
								</div>
							<?php endforeach; ?>
						</div>
						<button type="button" class="button" id="ais-add-social"><?php esc_html_e( 'Add social profile', 'ai-search-schema' ); ?></button>
					</div>
				</div>
			</section>

			<section class="ais-card">
				<div class="ais-card__header">
					<h2><?php esc_html_e( 'Schema output', 'ai-search-schema' ); ?></h2>
					<p><?php esc_html_e( 'Control entity types and content models.', 'ai-search-schema' ); ?></p>
				</div>
				<div class="ais-card__body">
					<div class="ais-field-grid two-col">
						<div class="ais-field">
							<label for="ais-entity-type"><?php esc_html_e( 'Primary role of this site', 'ai-search-schema' ); ?><span id="ais-entity-type-tooltip" class="ais-field__tooltip-wrap"><span class="ais-field__tooltip-icon" aria-label="<?php esc_attr_e( 'Help', 'ai-search-schema' ); ?>">?</span><span class="ais-field__tooltip-text"></span></span></label>
							<select id="ais-entity-type" name="<?php echo esc_attr( $option_name ); ?>[entity_type]">
								<option value="Organization" <?php selected( $options['entity_type'], 'Organization' ); ?>><?php esc_html_e( 'Organization (corporate or brand site)', 'ai-search-schema' ); ?></option>
								<option value="LocalBusiness" <?php selected( $options['entity_type'], 'LocalBusiness' ); ?>><?php esc_html_e( 'Store / Business location (storefront or service site)', 'ai-search-schema' ); ?></option>
							</select>
						</div>
						<div class="ais-field">
							<label for="ais-content-model"><?php esc_html_e( 'Content model', 'ai-search-schema' ); ?><?php ais_tooltip( __( 'Blog: Uses Article schema for posts. Corporate: Uses WebPage schema. E-commerce: Uses Product schema when WooCommerce is active.', 'ai-search-schema' ) ); ?></label>
							<select id="ais-content-model" name="<?php echo esc_attr( $option_name ); ?>[content_model]">
								<?php foreach ( $content_models as $model_key => $model_label ) : ?>
									<option value="<?php echo esc_attr( $model_key ); ?>" <?php selected( $options['content_model'], $model_key ); ?>><?php echo esc_html( $model_label ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="ais-field ais-field--radios">
							<span class="ais-field__label"><?php esc_html_e( 'Schema priority', 'ai-search-schema' ); ?><?php ais_tooltip( __( 'When external priority is selected, this plugin will stop printing JSON-LD and will not disable Yoast, Rank Math, or AIOSEO schema.', 'ai-search-schema' ) ); ?></span>
							<label class="ais-radio">
								<input type="radio" name="<?php echo esc_attr( $option_name ); ?>[ai_search_schema_priority]" value="avc" <?php checked( $options['ai_search_schema_priority'], 'avc' ); ?> />
								<span><?php esc_html_e( 'Use AEO Schema output (disable other plugins)', 'ai-search-schema' ); ?></span>
							</label>
							<label class="ais-radio">
								<input type="radio" name="<?php echo esc_attr( $option_name ); ?>[ai_search_schema_priority]" value="external" <?php checked( $options['ai_search_schema_priority'], 'external' ); ?> />
								<span><?php esc_html_e( 'Prefer external SEO plugin schema', 'ai-search-schema' ); ?></span>
							</label>
						</div>
						<div class="ais-field">
							<label for="ais-publisher-entity"><?php esc_html_e( 'Website publisher', 'ai-search-schema' ); ?><?php ais_tooltip( __( 'Sets the publisher reference for Article and WebPage schemas. Independent from "Primary role" — you can use LocalBusiness as publisher when articles are authored by the store.', 'ai-search-schema' ) ); ?></label>
							<select id="ais-publisher-entity" name="<?php echo esc_attr( $option_name ); ?>[publisher_entity]">
								<option value="Organization" <?php selected( $options['publisher_entity'], 'Organization' ); ?>><?php esc_html_e( 'Organization (brand site)', 'ai-search-schema' ); ?></option>
								<option value="LocalBusiness" <?php selected( $options['publisher_entity'], 'LocalBusiness' ); ?>><?php esc_html_e( 'LocalBusiness (storefront site)', 'ai-search-schema' ); ?></option>
							</select>
						</div>
						<div class="ais-field">
							<label for="ais-business-type"><?php esc_html_e( 'LocalBusiness subtype', 'ai-search-schema' ); ?><?php ais_tooltip( __( 'Specify a more specific type. Examples: Restaurant, Dentist, HairSalon, AutoRepair, LegalService', 'ai-search-schema' ) ); ?></label>
							<input id="ais-business-type" type="text" name="<?php echo esc_attr( $option_name ); ?>[lb_subtype]" value="<?php echo esc_attr( $options['lb_subtype'] ); ?>" placeholder="<?php esc_attr_e( 'Example: Store, Restaurant', 'ai-search-schema' ); ?>" />
						</div>
						<div class="ais-field">
							<label for="ais-lb-label"><?php esc_html_e( 'Location label', 'ai-search-schema' ); ?><?php ais_tooltip( __( 'Appends after the Organization name to clarify each location.', 'ai-search-schema' ) ); ?></label>
							<input id="ais-lb-label" type="text" name="<?php echo esc_attr( $option_name ); ?>[local_business_label]" value="<?php echo esc_attr( $options['local_business_label'] ); ?>" placeholder="<?php esc_attr_e( 'Example: HQ / Shinjuku', 'ai-search-schema' ); ?>" />
						</div>
					</div>
					<div class="ais-field ais-field--toggle">
						<span class="ais-field__label"><?php esc_html_e( 'Breadcrumbs', 'ai-search-schema' ); ?></span>
						<label class="ais-toggle" for="ais-breadcrumbs-schema">
							<input id="ais-breadcrumbs-schema" type="checkbox" name="<?php echo esc_attr( $option_name ); ?>[ai_search_schema_breadcrumbs_schema_enabled]" value="1" <?php checked( true, ! empty( $options['ai_search_schema_breadcrumbs_schema_enabled'] ) ); ?> />
							<span><?php esc_html_e( 'Include BreadcrumbList in JSON-LD output', 'ai-search-schema' ); ?></span>
						</label>
						<p class="ais-field__description ais-field__description--inline"><?php esc_html_e( 'ON: Outputs BreadcrumbList schema for Google rich snippets. Recommended for SEO.', 'ai-search-schema' ); ?></p>
						<label class="ais-toggle" for="ais-breadcrumbs-html">
							<input id="ais-breadcrumbs-html" type="checkbox" name="<?php echo esc_attr( $option_name ); ?>[ai_search_schema_breadcrumbs_html_enabled]" value="1" <?php checked( true, ! empty( $options['ai_search_schema_breadcrumbs_html_enabled'] ) ); ?> />
							<span><?php esc_html_e( 'Render HTML breadcrumbs (nav) via AI_Search_Schema_Breadcrumbs::render()', 'ai-search-schema' ); ?></span>
						</label>
						<p class="ais-field__description"><?php esc_html_e( 'ON: Displays visible breadcrumb navigation on pages. Leave OFF if your theme already has breadcrumbs.', 'ai-search-schema' ); ?></p>
					</div>
				</div>
			</section>

		</div>

		<div class="ais-card-grid ais-card-grid--stack">
			<section class="ais-card ais-card--full">
				<div class="ais-card__header">
					<h2><?php esc_html_e( 'Page-type schema controls', 'ai-search-schema' ); ?><?php ais_tooltip( __( 'Column guide: Schema type = Force a specific schema (Article, FAQPage, WebPage) regardless of content model. Breadcrumbs = Include in BreadcrumbList output. FAQ extraction = Auto-detect Q&A content and generate FAQPage schema. Schema priority = Override global priority per content type.', 'ai-search-schema' ) ); ?></h2>
					<p><?php esc_html_e( 'Adjust schema types, breadcrumb output, FAQ extraction, and priority per content area.', 'ai-search-schema' ); ?></p>
				</div>
				<div class="ais-card__body">
					<div class="ais-content-schema">
						<div class="ais-content-schema__section">
							<h3><?php esc_html_e( 'Post type overrides', 'ai-search-schema' ); ?></h3>
							<div class="ais-content-schema-table">
								<div class="ais-content-schema-row ais-content-schema-row--head">
									<span class="ais-content-schema-column"><?php esc_html_e( 'Content', 'ai-search-schema' ); ?></span>
									<span class="ais-content-schema-column"><?php esc_html_e( 'Schema type', 'ai-search-schema' ); ?></span>
									<span class="ais-content-schema-column"><?php esc_html_e( 'Breadcrumbs', 'ai-search-schema' ); ?></span>
									<span class="ais-content-schema-column"><?php esc_html_e( 'FAQ extraction', 'ai-search-schema' ); ?></span>
									<span class="ais-content-schema-column"><?php esc_html_e( 'Schema priority', 'ai-search-schema' ); ?></span>
								</div>
								<?php foreach ( $content_type_post_types as $slug => $type_obj ) : ?>
									<?php
									$entry = isset( $content_type_settings['post_types'][ $slug ] ) ? $content_type_settings['post_types'][ $slug ] : ( $content_type_defaults['post_types'][ $slug ] ?? array() );
									if ( empty( $entry ) ) {
										$entry = array(
											'schema_type' => 'auto',
											'breadcrumbs_enabled' => true,
											'faq_enabled' => false,
											'schema_priority' => 'avc',
										);
									}
									$label = ! empty( $type_obj->labels->singular_name ) ? $type_obj->labels->singular_name : ( ! empty( $type_obj->label ) ? $type_obj->label : $slug );
									?>
									<div class="ais-content-schema-row">
										<span class="ais-content-schema-column ais-content-schema-column--title">
											<strong><?php echo esc_html( $label ); ?></strong>
											<span class="ais-content-schema-column__slug"><?php echo esc_html( $slug ); ?></span>
										</span>
										<span class="ais-content-schema-column">
											<select name="<?php echo esc_attr( $option_name ); ?>[content_type_settings][post_types][<?php echo esc_attr( $slug ); ?>][schema_type]">
												<?php foreach ( $schema_type_choices as $type_key => $type_label ) : ?>
													<option value="<?php echo esc_attr( $type_key ); ?>" <?php selected( $entry['schema_type'], $type_key ); ?>><?php echo esc_html( $type_label ); ?></option>
												<?php endforeach; ?>
											</select>
										</span>
										<span class="ais-content-schema-column">
											<input type="hidden" name="<?php echo esc_attr( $option_name ); ?>[content_type_settings][post_types][<?php echo esc_attr( $slug ); ?>][breadcrumbs_enabled]" value="0" />
											<label class="ais-toggle ais-toggle--inline">
												<input type="checkbox" name="<?php echo esc_attr( $option_name ); ?>[content_type_settings][post_types][<?php echo esc_attr( $slug ); ?>][breadcrumbs_enabled]" value="1" <?php checked( $entry['breadcrumbs_enabled'], true ); ?> />
												<span><?php esc_html_e( 'Enabled', 'ai-search-schema' ); ?></span>
											</label>
										</span>
										<span class="ais-content-schema-column">
											<input type="hidden" name="<?php echo esc_attr( $option_name ); ?>[content_type_settings][post_types][<?php echo esc_attr( $slug ); ?>][faq_enabled]" value="0" />
											<label class="ais-toggle ais-toggle--inline">
												<input type="checkbox" name="<?php echo esc_attr( $option_name ); ?>[content_type_settings][post_types][<?php echo esc_attr( $slug ); ?>][faq_enabled]" value="1" <?php checked( $entry['faq_enabled'], true ); ?> />
												<span><?php esc_html_e( 'Enabled', 'ai-search-schema' ); ?></span>
											</label>
										</span>
										<span class="ais-content-schema-column">
											<select name="<?php echo esc_attr( $option_name ); ?>[content_type_settings][post_types][<?php echo esc_attr( $slug ); ?>][schema_priority]">
												<?php foreach ( $schema_priority_choices as $priority_key => $priority_label ) : ?>
													<option value="<?php echo esc_attr( $priority_key ); ?>" <?php selected( $entry['schema_priority'], $priority_key ); ?>><?php echo esc_html( $priority_label ); ?></option>
												<?php endforeach; ?>
											</select>
										</span>
									</div>
								<?php endforeach; ?>
							</div>
							<p class="ais-field__description"><?php esc_html_e( 'Override schema defaults per post type so you can force Article, FAQPage, or WebPage output without editing individual posts.', 'ai-search-schema' ); ?></p>
						</div>
						<div class="ais-content-schema__section">
							<h3><?php esc_html_e( 'Taxonomy overrides', 'ai-search-schema' ); ?></h3>
							<?php if ( empty( $content_type_taxonomies ) ) : ?>
								<p class="ais-field__description"><?php esc_html_e( 'No public taxonomies were detected on this site.', 'ai-search-schema' ); ?></p>
							<?php else : ?>
								<div class="ais-content-schema-table">
									<div class="ais-content-schema-row ais-content-schema-row--head">
										<span class="ais-content-schema-column"><?php esc_html_e( 'Content', 'ai-search-schema' ); ?></span>
										<span class="ais-content-schema-column"><?php esc_html_e( 'Schema type', 'ai-search-schema' ); ?></span>
										<span class="ais-content-schema-column"><?php esc_html_e( 'Breadcrumbs', 'ai-search-schema' ); ?></span>
										<span class="ais-content-schema-column"><?php esc_html_e( 'FAQ extraction', 'ai-search-schema' ); ?></span>
										<span class="ais-content-schema-column"><?php esc_html_e( 'Schema priority', 'ai-search-schema' ); ?></span>
									</div>
									<?php foreach ( $content_type_taxonomies as $slug => $taxonomy_object ) : ?>
										<?php
										$entry = isset( $content_type_settings['taxonomies'][ $slug ] ) ? $content_type_settings['taxonomies'][ $slug ] : ( $content_type_defaults['taxonomies'][ $slug ] ?? array() );
										if ( empty( $entry ) ) {
											$entry = array(
												'schema_type' => 'auto',
												'breadcrumbs_enabled' => true,
												'faq_enabled' => false,
												'schema_priority' => 'avc',
											);
										}
										$label = ! empty( $taxonomy_object->labels->singular_name ) ? $taxonomy_object->labels->singular_name : ( ! empty( $taxonomy_object->label ) ? $taxonomy_object->label : $slug );
										?>
										<div class="ais-content-schema-row">
											<span class="ais-content-schema-column ais-content-schema-column--title">
												<strong><?php echo esc_html( $label ); ?></strong>
												<span class="ais-content-schema-column__slug"><?php echo esc_html( $slug ); ?></span>
											</span>
											<span class="ais-content-schema-column">
												<select name="<?php echo esc_attr( $option_name ); ?>[content_type_settings][taxonomies][<?php echo esc_attr( $slug ); ?>][schema_type]">
													<?php foreach ( $schema_type_choices as $type_key => $type_label ) : ?>
														<option value="<?php echo esc_attr( $type_key ); ?>" <?php selected( $entry['schema_type'], $type_key ); ?>><?php echo esc_html( $type_label ); ?></option>
													<?php endforeach; ?>
												</select>
											</span>
											<span class="ais-content-schema-column">
												<input type="hidden" name="<?php echo esc_attr( $option_name ); ?>[content_type_settings][taxonomies][<?php echo esc_attr( $slug ); ?>][breadcrumbs_enabled]" value="0" />
												<label class="ais-toggle ais-toggle--inline">
													<input type="checkbox" name="<?php echo esc_attr( $option_name ); ?>[content_type_settings][taxonomies][<?php echo esc_attr( $slug ); ?>][breadcrumbs_enabled]" value="1" <?php checked( $entry['breadcrumbs_enabled'], true ); ?> />
													<span><?php esc_html_e( 'Enabled', 'ai-search-schema' ); ?></span>
												</label>
											</span>
											<span class="ais-content-schema-column">
												<input type="hidden" name="<?php echo esc_attr( $option_name ); ?>[content_type_settings][taxonomies][<?php echo esc_attr( $slug ); ?>][faq_enabled]" value="0" />
												<label class="ais-toggle ais-toggle--inline">
													<input type="checkbox" name="<?php echo esc_attr( $option_name ); ?>[content_type_settings][taxonomies][<?php echo esc_attr( $slug ); ?>][faq_enabled]" value="1" <?php checked( $entry['faq_enabled'], true ); ?> />
													<span><?php esc_html_e( 'Enabled', 'ai-search-schema' ); ?></span>
												</label>
											</span>
											<span class="ais-content-schema-column">
												<select name="<?php echo esc_attr( $option_name ); ?>[content_type_settings][taxonomies][<?php echo esc_attr( $slug ); ?>][schema_priority]">
													<?php foreach ( $schema_priority_choices as $priority_key => $priority_label ) : ?>
														<option value="<?php echo esc_attr( $priority_key ); ?>" <?php selected( $entry['schema_priority'], $priority_key ); ?>><?php echo esc_html( $priority_label ); ?></option>
													<?php endforeach; ?>
												</select>
											</span>
										</div>
									<?php endforeach; ?>
								</div>
								<p class="ais-field__description"><?php esc_html_e( 'Taxonomy archives can inherit the breadcrumbs/FAQ toggles and schema priority settings just like posts.', 'ai-search-schema' ); ?></p>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</section>
			<section class="ais-card ais-card--full" id="ais-local-business-section">
				<div class="ais-card__header">
					<h2><?php esc_html_e( 'Local details & hours', 'ai-search-schema' ); ?></h2>
					<p><?php esc_html_e( 'Capture address, coordinates, and hours to enrich the LocalBusiness schema.', 'ai-search-schema' ); ?></p>
				</div>
				<div class="ais-card__body">
					<!-- LocalBusiness Google 推奨項目ガイド -->
					<div class="ais-lb-guide" id="ais-lb-guide">
						<div class="ais-lb-guide__header">
							<span class="ais-lb-guide__icon" aria-hidden="true">📍</span>
							<div class="ais-lb-guide__title">
								<strong><?php esc_html_e( 'Google Recommended Properties for LocalBusiness', 'ai-search-schema' ); ?></strong>
								<p><?php esc_html_e( 'Complete these fields to maximize your visibility in local search results and Google Maps.', 'ai-search-schema' ); ?></p>
							</div>
						</div>
						<div class="ais-lb-guide__grid">
							<div class="ais-lb-guide__item">
								<span class="ais-lb-guide__check" id="ais-lb-check-name">○</span>
								<div class="ais-lb-guide__label">
									<strong><?php esc_html_e( 'name', 'ai-search-schema' ); ?></strong>
									<span><?php esc_html_e( 'Business name (Company name field)', 'ai-search-schema' ); ?></span>
								</div>
							</div>
							<div class="ais-lb-guide__item">
								<span class="ais-lb-guide__check" id="ais-lb-check-address">○</span>
								<div class="ais-lb-guide__label">
									<strong><?php esc_html_e( 'address', 'ai-search-schema' ); ?></strong>
									<span><?php esc_html_e( 'Full postal address', 'ai-search-schema' ); ?></span>
								</div>
							</div>
							<div class="ais-lb-guide__item">
								<span class="ais-lb-guide__check" id="ais-lb-check-phone">○</span>
								<div class="ais-lb-guide__label">
									<strong><?php esc_html_e( 'telephone', 'ai-search-schema' ); ?></strong>
									<span><?php esc_html_e( 'Contact phone number', 'ai-search-schema' ); ?></span>
								</div>
							</div>
							<div class="ais-lb-guide__item">
								<span class="ais-lb-guide__check" id="ais-lb-check-geo">○</span>
								<div class="ais-lb-guide__label">
									<strong><?php esc_html_e( 'geo', 'ai-search-schema' ); ?></strong>
									<span><?php esc_html_e( 'Latitude and longitude', 'ai-search-schema' ); ?></span>
								</div>
							</div>
							<div class="ais-lb-guide__item">
								<span class="ais-lb-guide__check" id="ais-lb-check-hours">○</span>
								<div class="ais-lb-guide__label">
									<strong><?php esc_html_e( 'openingHours', 'ai-search-schema' ); ?></strong>
									<span><?php esc_html_e( 'Business hours', 'ai-search-schema' ); ?></span>
								</div>
							</div>
							<div class="ais-lb-guide__item">
								<span class="ais-lb-guide__check" id="ais-lb-check-image">○</span>
								<div class="ais-lb-guide__label">
									<strong><?php esc_html_e( 'image', 'ai-search-schema' ); ?></strong>
									<span><?php esc_html_e( 'Storefront photo', 'ai-search-schema' ); ?></span>
								</div>
							</div>
							<div class="ais-lb-guide__item">
								<span class="ais-lb-guide__check" id="ais-lb-check-url">○</span>
								<div class="ais-lb-guide__label">
									<strong><?php esc_html_e( 'url', 'ai-search-schema' ); ?></strong>
									<span><?php esc_html_e( 'Website URL', 'ai-search-schema' ); ?></span>
								</div>
							</div>
							<div class="ais-lb-guide__item">
								<span class="ais-lb-guide__check" id="ais-lb-check-price">○</span>
								<div class="ais-lb-guide__label">
									<strong><?php esc_html_e( 'priceRange', 'ai-search-schema' ); ?></strong>
									<span><?php esc_html_e( 'Price range ($, $$, $$$)', 'ai-search-schema' ); ?></span>
								</div>
							</div>
						</div>
							<p class="ais-lb-guide__note">
								<a href="https://developers.google.com/search/docs/appearance/structured-data/local-business" target="_blank" rel="noopener noreferrer">
									<?php esc_html_e( 'View Google LocalBusiness documentation →', 'ai-search-schema' ); ?>
								</a>
								<br />
								<?php esc_html_e( 'LocalBusiness output relies on these fields. When skip on incomplete is enabled, missing items here will suppress output.', 'ai-search-schema' ); ?>
							</p>
						</div>
					<div class="ais-field-grid two-col">
						<div class="ais-field">
							<label for="ais-price-range"><?php esc_html_e( 'Price range', 'ai-search-schema' ); ?><?php ais_tooltip( __( 'Select the price level for your business. Google displays this in search results.', 'ai-search-schema' ) ); ?></label>
							<select id="ais-price-range" name="<?php echo esc_attr( $option_name ); ?>[price_range]">
								<option value=""><?php esc_html_e( '— Not specified —', 'ai-search-schema' ); ?></option>
								<option value="$" <?php selected( $options['price_range'], '$' ); ?>><?php esc_html_e( '$ (Budget)', 'ai-search-schema' ); ?></option>
								<option value="$$" <?php selected( $options['price_range'], '$$' ); ?>><?php esc_html_e( '$$ (Moderate)', 'ai-search-schema' ); ?></option>
								<option value="$$$" <?php selected( $options['price_range'], '$$$' ); ?>><?php esc_html_e( '$$$ (Expensive)', 'ai-search-schema' ); ?></option>
								<option value="$$$$" <?php selected( $options['price_range'], '$$$$' ); ?>><?php esc_html_e( '$$$$ (Luxury)', 'ai-search-schema' ); ?></option>
							</select>
						</div>
						<div class="ais-field">
							<label for="ais-payment-accepted"><?php esc_html_e( 'Accepted payment methods', 'ai-search-schema' ); ?><?php ais_tooltip( __( 'List payment methods separated by commas. Examples: Cash, Credit Card, PayPay, Suica', 'ai-search-schema' ) ); ?></label>
							<input id="ais-payment-accepted" type="text" name="<?php echo esc_attr( $option_name ); ?>[payment_accepted]" value="<?php echo esc_attr( $options['payment_accepted'] ); ?>" placeholder="<?php esc_attr_e( 'Example: Cash, Credit Card', 'ai-search-schema' ); ?>" />
						</div>
						<div class="ais-field ais-field--toggle">
							<span class="ais-field__label"><?php esc_html_e( 'Reservations', 'ai-search-schema' ); ?></span>
							<label class="ais-toggle" for="ais-reservations">
								<input id="ais-reservations" type="checkbox" name="<?php echo esc_attr( $option_name ); ?>[accepts_reservations]" value="1" <?php checked( true, ! empty( $options['accepts_reservations'] ) ); ?> />
								<span><?php esc_html_e( 'Accept reservations', 'ai-search-schema' ); ?></span>
							</label>
							<p class="ais-field__description"><?php esc_html_e( 'ON: Adds acceptsReservations property to LocalBusiness schema. Enable for restaurants, salons, or any business that takes bookings.', 'ai-search-schema' ); ?></p>
						</div>
					</div>
					<div class="ais-field ais-field--api-key <?php echo $gmaps_api_key_set ? 'is-locked' : ''; ?>">
						<label for="ais-gmaps-api-key"><?php esc_html_e( 'Google Maps API Key', 'ai-search-schema' ); ?></label>
						<div class="ais-api-key-control">
							<input
								id="ais-gmaps-api-key"
								type="password"
								name="<?php echo esc_attr( $option_name ); ?>[gmaps_api_key]"
								value=""
								placeholder="<?php echo esc_attr( $gmaps_api_key_set ? __( 'Stored API key', 'ai-search-schema' ) : __( 'Example: AIzaSyB...', 'ai-search-schema' ) ); ?>"
								autocomplete="off"
								<?php disabled( $gmaps_api_key_set ); ?>
							/>
							<?php if ( ! $gmaps_api_key_set ) : ?>
								<button type="submit" class="button button-secondary ais-api-key-save">
									<?php esc_html_e( 'Save key', 'ai-search-schema' ); ?>
								</button>
							<?php endif; ?>
						</div>
						<p class="ais-field__description">
							<?php if ( ! empty( $gmaps_api_key_set ) ) : ?>
								<?php esc_html_e( 'An API key is already stored. Delete it first if you need to change it.', 'ai-search-schema' ); ?>
								<button
									type="submit"
									class="button-link ais-inline-link ais-api-key-clear"
									name="<?php echo esc_attr( $option_name ); ?>[gmaps_api_key_action]"
									value="clear"
									id="ais-gmaps-api-key-clear"
									data-confirm="<?php esc_attr_e( 'Delete the stored API key?', 'ai-search-schema' ); ?>"
								>
									<?php esc_html_e( 'Delete stored key', 'ai-search-schema' ); ?>
								</button>
							<?php else : ?>
								<?php esc_html_e( 'Enter a Google Maps Geocoding API key to use Google for geocoding. When empty, OpenStreetMap is used for development purposes.', 'ai-search-schema' ); ?>
							<?php endif; ?>
						</p>
					</div>
						<div class="ais-field">
							<label><?php esc_html_e( 'Address', 'ai-search-schema' ); ?><span class="ais-required" title="<?php esc_attr_e( 'Required for LocalBusiness', 'ai-search-schema' ); ?>">*</span><?php ais_tooltip( __( 'Required for LocalBusiness. Enter your full postal address for the PostalAddress schema property.', 'ai-search-schema' ) ); ?></label>
							<div class="ais-address-grid">
								<input type="text" id="ais-address-postal" name="<?php echo esc_attr( $option_name ); ?>[address][postal_code]" value="<?php echo esc_attr( $options['address']['postal_code'] ); ?>" placeholder="<?php esc_attr_e( 'Postal code (e.g. 150-0001)', 'ai-search-schema' ); ?>" />
								<div class="ais-address-prefecture">
									<select
										id="ais-address-prefecture"
										name="<?php echo esc_attr( $option_name ); ?>[address][prefecture]"
									>
										<option value=""><?php esc_html_e( 'Select prefecture', 'ai-search-schema' ); ?></option>
										<?php foreach ( $prefecture_choices as $prefecture_label => $prefecture_iso ) : ?>
											<option
												value="<?php echo esc_attr( $prefecture_label ); ?>"
												data-iso="<?php echo esc_attr( $prefecture_iso ); ?>"
												<?php selected( $options['address']['prefecture'], $prefecture_label ); ?>
											>
												<?php echo esc_html( $prefecture_label ); ?>
											</option>
										<?php endforeach; ?>
									</select>
									<input type="hidden" id="ais-address-region-hidden" name="<?php echo esc_attr( $option_name ); ?>[address][region]" value="<?php echo esc_attr( $options['address']['region'] ); ?>" />
								</div>
								<input
									type="text"
									id="ais-address-city"
									name="<?php echo esc_attr( $option_name ); ?>[address][city]"
									value="<?php echo esc_attr( $options['address']['city'] ?? $options['address']['locality'] ); ?>"
									placeholder="<?php esc_attr_e( '市区町村（例：草津市）', 'ai-search-schema' ); ?>"
									aria-label="<?php esc_attr_e( '市区町村', 'ai-search-schema' ); ?>"
								/>
								<input
									type="text"
									id="ais-address-line"
									name="<?php echo esc_attr( $option_name ); ?>[address][address_line]"
									value="<?php echo esc_attr( $options['address']['address_line'] ?? $options['address']['street_address'] ); ?>"
									placeholder="<?php esc_attr_e( '町名・番地・建物名（例：大路1丁目11-14 フロント草津ビル5F）', 'ai-search-schema' ); ?>"
									aria-label="<?php esc_attr_e( '町名・番地・建物名', 'ai-search-schema' ); ?>"
								/>
								<div class="ais-country-field">
									<div class="ais-country-field__primary">
										<label for="ais-address-country"><?php esc_html_e( 'Country code', 'ai-search-schema' ); ?></label>
										<input type="text" id="ais-address-country" name="<?php echo esc_attr( $option_name ); ?>[address][country]" value="<?php echo esc_attr( $options['address']['country'] ); ?>" placeholder="<?php esc_attr_e( 'Country (e.g. JP)', 'ai-search-schema' ); ?>" />
										<p class="ais-field__description ais-country-field__note">
											<?php esc_html_e( 'ISO 3166-2 updates automatically from the prefecture selection.', 'ai-search-schema' ); ?>
										</p>
									</div>
									<div class="ais-country-field__iso-card" aria-live="polite">
										<span class="ais-country-field__iso-label"><?php esc_html_e( 'ISO 3166-2', 'ai-search-schema' ); ?></span>
										<span class="ais-country-field__iso-value" id="ais-prefecture-iso">
											<?php
											$prefecture_iso = ! empty( $options['address']['prefecture_iso'] ) ? $options['address']['prefecture_iso'] : '—';
											echo esc_html( $prefecture_iso );
											?>
										</span>
									</div>
								</div>
							</div>
							<div class="ais-address-actions">
								<button type="button" class="button" id="ais-zip-autofill"><?php esc_html_e( '郵便番号から住所を自動補完する', 'ai-search-schema' ); ?></button>
							</div>
						</div>
						<div class="ais-field-grid two-col">
							<div class="ais-field">
								<label><?php esc_html_e( 'Latitude & longitude', 'ai-search-schema' ); ?><?php ais_tooltip( __( 'Required for LocalBusiness. Click "Fetch coordinates" to auto-fill from address.', 'ai-search-schema' ) ); ?></label>
								<div class="ais-field-grid two-col">
									<input type="text" id="ais-geo-latitude" name="<?php echo esc_attr( $option_name ); ?>[geo][latitude]" value="<?php echo esc_attr( $options['geo']['latitude'] ); ?>" placeholder="<?php esc_attr_e( 'Latitude 35.123456', 'ai-search-schema' ); ?>" />
								<input type="text" id="ais-geo-longitude" name="<?php echo esc_attr( $option_name ); ?>[geo][longitude]" value="<?php echo esc_attr( $options['geo']['longitude'] ); ?>" placeholder="<?php esc_attr_e( 'Longitude 139.123456', 'ai-search-schema' ); ?>" />
							</div>
							<div class="ais-geocode-actions">
								<button type="button" class="button button-secondary" id="ais-geocode-button"><?php esc_html_e( 'Fetch coordinates from address', 'ai-search-schema' ); ?></button>
								<span class="ais-geocode-status" id="ais-geocode-status"></span>
							</div>
						</div>
						<div class="ais-field ais-field--toggle ais-field--hasmap">
							<span class="ais-field__label"><?php esc_html_e( 'Google Map link', 'ai-search-schema' ); ?></span>
							<label class="ais-toggle" for="ais-has-map-enabled">
								<input
									id="ais-has-map-enabled"
									type="checkbox"
									name="<?php echo esc_attr( $option_name ); ?>[has_map_enabled]"
									value="1"
									<?php checked( true, ! empty( $options['has_map_enabled'] ) ); ?>
								/>
								<span><?php esc_html_e( 'Generate hasMap from saved coordinates', 'ai-search-schema' ); ?></span>
							</label>
							<p class="ais-field__description">
								<?php esc_html_e( 'When enabled, hasMap becomes https://www.google.com/maps/search/?api=1&query=latitude,longitude. Coordinates are required.', 'ai-search-schema' ); ?>
							</p>
							</div>
						</div>
						<div class="ais-field ais-field--toggle">
							<span class="ais-field__label"><?php esc_html_e( 'LocalBusiness output control', 'ai-search-schema' ); ?></span>
							<label class="ais-toggle" for="ais-lb-skip-incomplete">
								<input
									id="ais-lb-skip-incomplete"
									type="checkbox"
									name="<?php echo esc_attr( $option_name ); ?>[skip_local_business_if_incomplete]"
									value="1"
									<?php checked( true, ! empty( $options['skip_local_business_if_incomplete'] ) ); ?>
								/>
								<span><?php esc_html_e( 'Do not output LocalBusiness when required fields are missing', 'ai-search-schema' ); ?></span>
							</label>
							<p class="ais-field__description">
								<?php esc_html_e( 'When enabled, LocalBusiness will be skipped unless name, address, and telephone are complete. A warning will appear in debug mode.', 'ai-search-schema' ); ?>
							</p>
						</div>
						<div class="ais-field">
							<span class="ais-field__label" id="ais-opening-hours-label"><?php esc_html_e( 'Opening hours', 'ai-search-schema' ); ?><?php ais_tooltip( __( 'Add as many day and time combinations as needed. Use templates for quick setup.', 'ai-search-schema' ) ); ?></span>
							<div class="ais-opening-hours-actions" role="group" aria-label="<?php esc_attr_e( 'Opening hour templates', 'ai-search-schema' ); ?>">
								<button type="button" class="button" id="ais-oh-template-weekday"><?php esc_html_e( '平日 10:00–19:00', 'ai-search-schema' ); ?></button>
								<button type="button" class="button" id="ais-oh-template-weekend"><?php esc_html_e( '土日祝 10:00–17:00', 'ai-search-schema' ); ?></button>
								<button type="button" class="button" id="ais-oh-template-24h"><?php esc_html_e( '24時間営業（毎日 00:00–23:59）', 'ai-search-schema' ); ?></button>
								<button type="button" class="button button-link-delete" id="ais-oh-template-clear"><?php esc_html_e( 'クリア', 'ai-search-schema' ); ?></button>
							</div>
							<div class="ais-day-toggle-list ais-opening-hours-toggles">
								<label class="ais-toggle ais-day-toggle" for="ais-include-monday">
									<input type="checkbox" id="ais-include-monday" data-day-key="Monday" checked />
									<span><?php esc_html_e( '月曜を含める', 'ai-search-schema' ); ?></span>
								</label>
								<label class="ais-toggle ais-day-toggle" for="ais-include-tuesday">
									<input type="checkbox" id="ais-include-tuesday" data-day-key="Tuesday" checked />
									<span><?php esc_html_e( '火曜を含める', 'ai-search-schema' ); ?></span>
								</label>
								<label class="ais-toggle ais-day-toggle" for="ais-include-wednesday">
									<input type="checkbox" id="ais-include-wednesday" data-day-key="Wednesday" checked />
									<span><?php esc_html_e( '水曜を含める', 'ai-search-schema' ); ?></span>
								</label>
								<label class="ais-toggle ais-day-toggle" for="ais-include-thursday">
									<input type="checkbox" id="ais-include-thursday" data-day-key="Thursday" checked />
									<span><?php esc_html_e( '木曜を含める', 'ai-search-schema' ); ?></span>
								</label>
								<label class="ais-toggle ais-day-toggle" for="ais-include-friday">
									<input type="checkbox" id="ais-include-friday" data-day-key="Friday" checked />
									<span><?php esc_html_e( '金曜を含める', 'ai-search-schema' ); ?></span>
								</label>
								<label class="ais-toggle ais-day-toggle" for="ais-include-saturday">
									<input type="checkbox" id="ais-include-saturday" data-day-key="Saturday" checked />
									<span><?php esc_html_e( '土曜を含める', 'ai-search-schema' ); ?></span>
								</label>
								<label class="ais-toggle ais-day-toggle" for="ais-include-sunday">
									<input type="checkbox" id="ais-include-sunday" data-day-key="Sunday" checked />
									<span><?php esc_html_e( '日曜を含める', 'ai-search-schema' ); ?></span>
								</label>
								<label class="ais-toggle ais-day-toggle" for="ais-include-holiday">
									<input type="checkbox" id="ais-include-holiday" data-day-key="PublicHoliday" checked />
									<span><?php esc_html_e( '祝日を含める', 'ai-search-schema' ); ?></span>
								</label>
							</div>
							<div class="ais-holiday-controls">
								<label class="ais-toggle" for="ais-holiday-enabled">
									<input type="checkbox" id="ais-holiday-enabled" name="<?php echo esc_attr( $option_name ); ?>[holiday_enabled]" value="1" <?php checked( ! empty( $options['holiday_enabled'] ) ); ?> />
									<span><?php esc_html_e( '祝日を含める', 'ai-search-schema' ); ?></span>
								</label>
								<div class="ais-holiday-modes" aria-labelledby="ais-holiday-mode-label">
									<span class="ais-field__label" id="ais-holiday-mode-label"><?php esc_html_e( '祝日の営業時間', 'ai-search-schema' ); ?></span>
									<label class="ais-radio">
										<input type="radio" name="<?php echo esc_attr( $option_name ); ?>[holiday_mode]" value="weekday" <?php checked( $options['holiday_mode'] ?? 'custom', 'weekday' ); ?> />
										<span><?php esc_html_e( '平日と同じ', 'ai-search-schema' ); ?></span>
									</label>
									<label class="ais-radio">
										<input type="radio" name="<?php echo esc_attr( $option_name ); ?>[holiday_mode]" value="weekend" <?php checked( $options['holiday_mode'] ?? 'custom', 'weekend' ); ?> />
										<span><?php esc_html_e( '土日祝テンプレと同じ', 'ai-search-schema' ); ?></span>
									</label>
									<label class="ais-radio">
										<input type="radio" name="<?php echo esc_attr( $option_name ); ?>[holiday_mode]" value="custom" <?php checked( $options['holiday_mode'] ?? 'custom', 'custom' ); ?> />
										<span><?php esc_html_e( 'カスタム設定', 'ai-search-schema' ); ?></span>
									</label>
								</div>
							</div>
							<div id="ais-opening-hours-rows" aria-labelledby="ais-opening-hours-label">
							<?php
							foreach ( $opening_hours_rows as $index => $slot ) :
								$day_key = isset( $slot['day_key'] ) ? $slot['day_key'] : 'Monday';
								$opens   = isset( $slot['opens'] ) ? $slot['opens'] : '';
								$closes  = isset( $slot['closes'] ) ? $slot['closes'] : '';
								?>
								<div class="ais-opening-hours-row ais-opening-hours-slot" data-index="<?php echo esc_attr( $index ); ?>" draggable="true">
									<span class="ais-slot-handle" aria-hidden="true">☰</span>
									<select class="ais-opening-hours-day" style="min-width: 160px;" name="<?php echo esc_attr( $option_name ); ?>[opening_hours][<?php echo esc_attr( $index ); ?>][day_key]" data-name="<?php echo esc_attr( $option_name ); ?>[opening_hours][__index__][day_key]">
										<?php foreach ( $weekday_choices as $weekday_code => $weekday_label ) : ?>
											<option value="<?php echo esc_attr( $weekday_code ); ?>" <?php selected( $day_key, $weekday_code ); ?>><?php echo esc_html( $weekday_label ); ?></option>
										<?php endforeach; ?>
									</select>
									<input type="time" class="ais-opening-hours-opens" name="<?php echo esc_attr( $option_name ); ?>[opening_hours][<?php echo esc_attr( $index ); ?>][opens]" data-name="<?php echo esc_attr( $option_name ); ?>[opening_hours][__index__][opens]" value="<?php echo esc_attr( $opens ); ?>" />
									<span class="ais-opening-hours-separator"><?php esc_html_e( 'to', 'ai-search-schema' ); ?></span>
									<input type="time" class="ais-opening-hours-closes" name="<?php echo esc_attr( $option_name ); ?>[opening_hours][<?php echo esc_attr( $index ); ?>][closes]" data-name="<?php echo esc_attr( $option_name ); ?>[opening_hours][__index__][closes]" value="<?php echo esc_attr( $closes ); ?>" />
									<button type="button" class="button-link-delete ais-remove-opening-hour"><?php esc_html_e( 'Remove', 'ai-search-schema' ); ?></button>
								</div>
							<?php endforeach; ?>
						</div>
						<button type="button" class="button" id="ais-add-opening-hour"><?php esc_html_e( 'Add time slot', 'ai-search-schema' ); ?></button>
					</div>
				</div>
			</section>

			<section class="ais-card">
				<div class="ais-card__header">
					<h2><?php esc_html_e( 'AEO/GEO チェック', 'ai-search-schema' ); ?></h2>
					<p><?php esc_html_e( '主要スキーマの必須項目が揃っているかを自己診断します。', 'ai-search-schema' ); ?></p>
				</div>
				<div class="ais-card__body">
					<style>
						.ais-diagnostic-list{display:grid;gap:12px}
						.ais-diagnostic-group{border:1px solid #e4e7eb;border-radius:12px;padding:12px}
						.ais-diagnostic-title{margin:0 0 8px;font-weight:600;font-size:14px}
						.ais-diagnostic-item{display:flex;align-items:center;gap:8px;margin:6px 0}
						.ais-status-badge{display:inline-flex;align-items:center;padding:2px 8px;border-radius:999px;font-size:12px;font-weight:600}
						.ais-status-ok{background:#e6f4ea;color:#1a7f37}
						.ais-status-warning{background:#fff4e5;color:#b06100}
						.ais-status-error{background:#fdecea;color:#b3261e}
					</style>
					<div class="ais-diagnostic-list">
						<?php if ( ! empty( $diagnostics['groups'] ) ) : ?>
							<?php foreach ( $diagnostics['groups'] as $group ) : ?>
								<div class="ais-diagnostic-group">
									<div class="ais-diagnostic-title"><?php echo esc_html( $group['title'] ?? '' ); ?></div>
									<?php if ( ! empty( $group['items'] ) && is_array( $group['items'] ) ) : ?>
										<?php foreach ( $group['items'] as $item ) : ?>
											<?php
											$diag_status = isset( $item['status'] ) ? $item['status'] : 'warning';
											$label       = 'ok' === $diag_status ? __( 'OK', 'ai-search-schema' ) : ( 'error' === $diag_status ? __( 'Error', 'ai-search-schema' ) : __( 'Warning', 'ai-search-schema' ) );
											?>
											<div class="ais-diagnostic-item">
												<span class="ais-status-badge ais-status-<?php echo esc_attr( $diag_status ); ?>"><?php echo esc_html( $label ); ?></span>
												<span><?php echo esc_html( $item['message'] ?? '' ); ?></span>
											</div>
										<?php endforeach; ?>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						<?php else : ?>
							<p><?php esc_html_e( 'まだ診断結果がありません。', 'ai-search-schema' ); ?></p>
						<?php endif; ?>
					</div>
					<?php if ( ! empty( $diagnostics['generated_at'] ) ) : ?>
						<p class="ais-field__description">
							<?php
							printf(
								/* translators: %s: datetime string */
								esc_html__( '最終診断: %s（再読み込みで更新）', 'ai-search-schema' ),
								esc_html( date_i18n( 'Y-m-d H:i', $diagnostics['generated_at'] ) )
							);
							?>
						</p>
					<?php endif; ?>
				</div>
			</section>

			<section class="ais-card">
				<div class="ais-card__header">
					<h2><?php esc_html_e( 'llms.txt', 'ai-search-schema' ); ?></h2>
					<p><?php esc_html_e( 'Generate llms.txt to help AI systems understand your site structure.', 'ai-search-schema' ); ?></p>
				</div>
				<div class="ais-card__body">
					<?php
					require_once AI_SEARCH_SCHEMA_DIR . 'includes/class-ai-search-schema-llms-txt.php';
					$llms_txt_instance = AI_Search_Schema_Llms_Txt::init();
					$llms_txt_enabled  = $llms_txt_instance->is_enabled();
					$llms_txt_content  = $llms_txt_instance->get_content();
					$llms_txt_url      = home_url( '/llms.txt' );
					?>

					<div class="ais-field">
						<label for="ais-llms-txt-enabled">
							<input type="checkbox" id="ais-llms-txt-enabled" name="<?php echo esc_attr( $option_name ); ?>[llms_txt_enabled]" value="1" <?php checked( $llms_txt_enabled ); ?> />
							<?php esc_html_e( 'Enable llms.txt', 'ai-search-schema' ); ?>
						</label>
						<p class="ais-field__description">
							<?php esc_html_e( 'When enabled, the file is served at:', 'ai-search-schema' ); ?>
							<a href="<?php echo esc_url( $llms_txt_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_url( $llms_txt_url ); ?></a>
						</p>
					</div>

					<div class="ais-field">
						<label for="ais-llms-txt-content"><?php esc_html_e( 'Content (Markdown)', 'ai-search-schema' ); ?></label>
						<textarea id="ais-llms-txt-content" name="<?php echo esc_attr( $option_name ); ?>[llms_txt_content]" rows="15" class="large-text code" style="font-family: monospace;"><?php echo esc_textarea( $llms_txt_content ); ?></textarea>
						<p class="ais-field__description">
							<?php esc_html_e( 'Edit the llms.txt content. Leave empty to auto-generate from site data.', 'ai-search-schema' ); ?>
						</p>
					</div>

					<p class="ais-button-row">
						<button type="button" class="button button-primary" id="ais-save-llms-txt">
							<?php esc_html_e( 'Save edits', 'ai-search-schema' ); ?>
						</button>
						<button type="button" class="button" id="ais-regenerate-llms-txt">
							<?php esc_html_e( 'Regenerate from site data', 'ai-search-schema' ); ?>
						</button>
						<span class="spinner" style="float: none; margin-top: 0;"></span>
						<span class="ais-llms-txt-status" style="margin-left: 8px;"></span>
					</p>
				</div>
			</section>

			<section class="ais-card">
				<div class="ais-card__header">
					<h2><?php esc_html_e( 'License', 'ai-search-schema' ); ?></h2>
					<p><?php esc_html_e( 'Manage your AI Search Schema Pro license.', 'ai-search-schema' ); ?></p>
				</div>
				<div class="ais-card__body">
					<?php
					require_once AI_SEARCH_SCHEMA_DIR . 'includes/class-ai-search-schema-license.php';
					$license_manager = AI_Search_Schema_License::init();
					$license_key     = $license_manager->get_license_key();
					$license_status  = $license_manager->get_license_status();
					$status_label    = $license_manager->get_status_label();
					$status_class    = $license_manager->get_status_class();
					$is_pro          = $license_manager->is_pro();
					?>

					<div class="ais-license-status-row">
						<span class="ais-license-status-label"><?php esc_html_e( 'Status:', 'ai-search-schema' ); ?></span>
						<span class="ais-license-status <?php echo esc_attr( $status_class ); ?>">
							<?php echo esc_html( $status_label ); ?>
						</span>
					</div>

					<div class="ais-field">
						<label for="ais-license-key"><?php esc_html_e( 'License Key', 'ai-search-schema' ); ?></label>
						<div class="ais-license-key-row">
							<input
								type="text"
								id="ais-license-key"
								class="regular-text"
								value="<?php echo esc_attr( $license_key ); ?>"
								placeholder="<?php esc_attr_e( 'Enter your license key', 'ai-search-schema' ); ?>"
								<?php echo $is_pro ? 'readonly' : ''; ?>
							/>
							<?php if ( $is_pro ) : ?>
								<button type="button" class="button" id="ais-deactivate-license">
									<?php esc_html_e( 'Deactivate', 'ai-search-schema' ); ?>
								</button>
							<?php else : ?>
								<button type="button" class="button button-primary" id="ais-activate-license">
									<?php esc_html_e( 'Activate', 'ai-search-schema' ); ?>
								</button>
							<?php endif; ?>
							<span class="spinner" style="float: none; margin-top: 0;"></span>
						</div>
						<p class="ais-field__description">
							<?php esc_html_e( 'Enter your Pro license key to unlock advanced features.', 'ai-search-schema' ); ?>
							<a href="https://aivec.co.jp/apps/ai-search-schema-pro" target="_blank" rel="noopener noreferrer">
								<?php esc_html_e( 'Get a license', 'ai-search-schema' ); ?>
							</a>
						</p>
						<span class="ais-license-message" id="ais-license-message"></span>
					</div>

					<?php if ( ! $is_pro ) : ?>
					<div class="ais-pro-features-preview">
						<h3><?php esc_html_e( 'Pro Features', 'ai-search-schema' ); ?></h3>
						<ul class="ais-pro-features-list">
							<li>
								<span class="dashicons dashicons-location"></span>
								<strong><?php esc_html_e( 'Multi-Location Support', 'ai-search-schema' ); ?></strong>
								<span><?php esc_html_e( 'Manage multiple LocalBusiness locations.', 'ai-search-schema' ); ?></span>
							</li>
							<li>
								<span class="dashicons dashicons-editor-code"></span>
								<strong><?php esc_html_e( 'Custom Schema Templates', 'ai-search-schema' ); ?></strong>
								<span><?php esc_html_e( 'Create custom schema templates for different content types.', 'ai-search-schema' ); ?></span>
							</li>
							<li>
								<span class="dashicons dashicons-sos"></span>
								<strong><?php esc_html_e( 'Priority Support', 'ai-search-schema' ); ?></strong>
								<span><?php esc_html_e( 'Get priority email support.', 'ai-search-schema' ); ?></span>
							</li>
							<li>
								<span class="dashicons dashicons-yes-alt"></span>
								<strong><?php esc_html_e( 'Advanced Validation', 'ai-search-schema' ); ?></strong>
								<span><?php esc_html_e( 'Scheduled automatic schema validation.', 'ai-search-schema' ); ?></span>
							</li>
						</ul>
					</div>
					<?php endif; ?>
				</div>
			</section>
		</div>

		<div class="ais-settings__actions">
			<?php submit_button( esc_html__( 'Save settings', 'ai-search-schema' ) ); ?>
		</div>
	</form>
	</div>
</div>

<script type="text/html" id="tmpl-ais-social-row">
	<div class="ais-social-row" data-index="__index__">
		<select class="ais-social-network-select" data-name="<?php echo esc_attr( $option_name ); ?>[social_links][__index__][network]">
			<?php foreach ( $social_choices as $choice_key => $choice_label ) : ?>
				<option value="<?php echo esc_attr( $choice_key ); ?>"><?php echo esc_html( $choice_label ); ?></option>
			<?php endforeach; ?>
		</select>
		<input type="text" data-name="<?php echo esc_attr( $option_name ); ?>[social_links][__index__][account]" placeholder="<?php esc_attr_e( 'Account name or profile URL', 'ai-search-schema' ); ?>" />
		<input type="text" class="ais-social-other-label hidden" data-name="<?php echo esc_attr( $option_name ); ?>[social_links][__index__][label]" placeholder="<?php esc_attr_e( 'Other service label', 'ai-search-schema' ); ?>" />
		<button type="button" class="button-link-delete ais-remove-social"><?php esc_html_e( 'Remove', 'ai-search-schema' ); ?></button>
	</div>
	</script>

	<script type="text/html" id="tmpl-ais-opening-hours-row">
		<div class="ais-opening-hours-row ais-opening-hours-slot" data-index="__index__" draggable="true">
			<span class="ais-slot-handle" aria-hidden="true">☰</span>
			<select class="ais-opening-hours-day" style="min-width: 160px;" data-name="<?php echo esc_attr( $option_name ); ?>[opening_hours][__index__][day_key]">
			<?php foreach ( $weekday_choices as $weekday_code => $weekday_label ) : ?>
				<option value="<?php echo esc_attr( $weekday_code ); ?>"><?php echo esc_html( $weekday_label ); ?></option>
			<?php endforeach; ?>
		</select>
		<input type="time" class="ais-opening-hours-opens" data-name="<?php echo esc_attr( $option_name ); ?>[opening_hours][__index__][opens]" />
		<span class="ais-opening-hours-separator"><?php esc_html_e( '〜', 'ai-search-schema' ); ?></span>
			<input type="time" class="ais-opening-hours-closes" data-name="<?php echo esc_attr( $option_name ); ?>[opening_hours][__index__][closes]" />
			<button type="button" class="button-link-delete ais-remove-opening-hour"><?php esc_html_e( 'Remove', 'ai-search-schema' ); ?></button>
		</div>
	</script>

<?php if ( $is_dev_mode && $test_manager_url ) : ?>
<!-- Dev Mode: Test Manager Link -->
<a href="<?php echo esc_url( $test_manager_url ); ?>" target="_blank" class="ais-dev-test-manager-link" title="<?php esc_attr_e( 'Open Test Manager', 'ai-search-schema' ); ?>">
	<span class="dashicons dashicons-clipboard"></span>
	<span class="ais-dev-label"><?php esc_html_e( 'Test Manager', 'ai-search-schema' ); ?></span>
</a>
<style>
.ais-dev-test-manager-link {
	position: fixed;
	bottom: 20px;
	right: 20px;
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 10px 16px;
	background: #1e1e1e;
	color: #fff;
	border-radius: 4px;
	text-decoration: none;
	font-size: 13px;
	font-weight: 500;
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
	z-index: 9999;
	transition: background 0.2s, transform 0.2s;
}
.ais-dev-test-manager-link:hover {
	background: #2271b1;
	color: #fff;
	transform: translateY(-2px);
}
.ais-dev-test-manager-link .dashicons {
	font-size: 18px;
	width: 18px;
	height: 18px;
}
.ais-dev-test-manager-link::before {
	content: 'DEV';
	position: absolute;
	top: -8px;
	left: -8px;
	background: #d63638;
	color: #fff;
	font-size: 9px;
	font-weight: 700;
	padding: 2px 5px;
	border-radius: 3px;
}
</style>
<?php endif; ?>

<?php /* phpcs:enable WordPress.Files.LineLength.TooLong */

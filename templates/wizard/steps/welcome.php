<?php
// phpcs:ignoreFile Generic.Files.LineLength.TooLong
/**
 * Welcome step template.
 *
 * @package Avc\Aeo\Schema
 */

defined( 'ABSPATH' ) || exit;

// Get import sources.
$importer = new \Avc\Aeo\Schema\Wizard\Wizard_Importer();
$sources  = $importer->get_available_sources();

$has_importable = false;
foreach ( $sources as $source ) {
	if ( $source['detected'] ) {
		$has_importable = true;
		break;
	}
}
?>
<div class="ais-wizard-step avc-wizard-step--welcome">
	<div class="ais-wizard-welcome">
		<div class="ais-wizard-welcome__icon">
			<svg viewBox="0 0 80 80" width="80" height="80">
				<circle cx="40" cy="40" r="38" fill="#4f46e5" opacity="0.1"/>
				<path d="M40 15L20 25v15c0 13.3 8.5 25.7 20 30 11.5-4.3 20-16.7 20-30V25L40 15z" fill="#4f46e5"/>
				<path d="M35 40l5 5 10-10" stroke="#fff" stroke-width="3" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
			</svg>
		</div>

		<h1 class="ais-wizard-welcome__title">
			<?php esc_html_e( 'Welcome to AVC AEO Schema', 'ai-search-schema' ); ?>
		</h1>

		<p class="ais-wizard-welcome__description">
			<?php esc_html_e( 'Get your site optimized for Google search in just 5 minutes. This wizard will guide you through the essential settings.', 'ai-search-schema' ); ?>
		</p>

		<div class="ais-wizard-welcome__preview">
			<div class="ais-wizard-preview-card">
				<div class="ais-wizard-preview-card__header">
					<span class="ais-wizard-preview-card__icon">
						<svg viewBox="0 0 24 24" width="20" height="20" fill="#4285f4">
							<path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
							<path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34a853"/>
							<path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#fbbc05"/>
							<path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#ea4335"/>
						</svg>
					</span>
					<span class="ais-wizard-preview-card__title"><?php esc_html_e( 'Google Search Preview', 'ai-search-schema' ); ?></span>
				</div>
				<div class="ais-wizard-preview-card__content">
					<div class="ais-wizard-preview-card__result">
						<div class="ais-wizard-preview-card__result-title">
							<?php echo esc_html( get_bloginfo( 'name' ) ); ?> - <?php esc_html_e( 'Your City', 'ai-search-schema' ); ?>
						</div>
						<div class="ais-wizard-preview-card__result-meta">
							<span class="ais-wizard-preview-card__stars">★★★★☆</span>
							<span>4.2 (123)</span>
							<span>·</span>
							<span><?php esc_html_e( 'Business', 'ai-search-schema' ); ?></span>
						</div>
						<div class="ais-wizard-preview-card__result-status">
							<span class="ais-wizard-preview-card__open"><?php esc_html_e( 'Open', 'ai-search-schema' ); ?></span>
							<span>·</span>
							<span><?php esc_html_e( 'Closes at 6:00 PM', 'ai-search-schema' ); ?></span>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="ais-wizard-welcome__actions">
			<a href="<?php echo esc_url( add_query_arg( 'step', 'basics' ) ); ?>" class="ais-wizard-btn avc-wizard-btn--primary avc-wizard-btn--large">
				<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
					<path d="M19.14 12.94c.04-.31.06-.63.06-.94 0-.31-.02-.63-.06-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.04.31-.06.63-.06.94s.02.63.06.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/>
				</svg>
				<?php esc_html_e( 'Get Started', 'ai-search-schema' ); ?>
			</a>

			<?php if ( $has_importable ) : ?>
				<button type="button" class="ais-wizard-btn avc-wizard-btn--secondary avc-wizard-btn--large" id="ais-wizard-show-import">
					<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
						<path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/>
					</svg>
					<?php esc_html_e( 'Import from another plugin', 'ai-search-schema' ); ?>
				</button>
			<?php endif; ?>
		</div>

		<div class="ais-wizard-welcome__skip">
			<a href="<?php echo esc_url( admin_url( 'options-general.php?page=ai-search-schema' ) ); ?>">
				<?php esc_html_e( 'Set up later', 'ai-search-schema' ); ?> →
			</a>
		</div>
	</div>

	<!-- Import Modal -->
	<?php if ( $has_importable ) : ?>
		<div class="ais-wizard-modal" id="ais-wizard-import-modal" style="display: none;">
			<div class="ais-wizard-modal__backdrop"></div>
			<div class="ais-wizard-modal__content">
				<button type="button" class="ais-wizard-modal__close" aria-label="<?php esc_attr_e( 'Close', 'ai-search-schema' ); ?>">
					<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor">
						<path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
					</svg>
				</button>

				<h2 class="ais-wizard-modal__title">
					<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor">
						<path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/>
					</svg>
					<?php esc_html_e( 'Import Existing Settings', 'ai-search-schema' ); ?>
				</h2>

				<p class="ais-wizard-modal__description">
					<?php esc_html_e( 'We detected these SEO plugins. You can import their settings to get started quickly.', 'ai-search-schema' ); ?>
				</p>

				<div class="ais-wizard-import-sources">
					<?php foreach ( $sources as $source_key => $source ) : ?>
						<div class="ais-wizard-import-source <?php echo $source['detected'] ? 'avc-wizard-import-source--detected' : 'avc-wizard-import-source--not-found'; ?>">
							<div class="ais-wizard-import-source__header">
								<span class="ais-wizard-import-source__icon">
									<?php if ( $source['detected'] ) : ?>
										<svg viewBox="0 0 24 24" width="20" height="20" fill="#22c55e">
											<path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
										</svg>
									<?php else : ?>
										<svg viewBox="0 0 24 24" width="20" height="20" fill="#9ca3af">
											<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
										</svg>
									<?php endif; ?>
								</span>
								<span class="ais-wizard-import-source__name"><?php echo esc_html( $source['name'] ); ?></span>
							</div>

							<?php if ( $source['detected'] && ! empty( $source['preview'] ) ) : ?>
								<div class="ais-wizard-import-source__preview">
									<ul>
										<?php foreach ( $source['preview'] as $key => $value ) : ?>
											<li>
												<span class="ais-wizard-import-source__key"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $key ) ) ); ?>:</span>
												<span class="ais-wizard-import-source__value"><?php echo esc_html( $value ); ?></span>
											</li>
										<?php endforeach; ?>
									</ul>
								</div>
								<div class="ais-wizard-import-source__actions">
									<button type="button" class="ais-wizard-btn avc-wizard-btn--primary avc-wizard-btn--small avc-wizard-import-btn" data-source="<?php echo esc_attr( $source_key ); ?>">
										<?php esc_html_e( 'Import', 'ai-search-schema' ); ?>
									</button>
								</div>
							<?php elseif ( ! $source['detected'] ) : ?>
								<div class="ais-wizard-import-source__not-found">
									<?php esc_html_e( 'Not detected', 'ai-search-schema' ); ?>
								</div>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>

				<div class="ais-wizard-modal__footer">
					<a href="<?php echo esc_url( add_query_arg( 'step', 'basics' ) ); ?>" class="ais-wizard-btn avc-wizard-btn--text">
						<?php esc_html_e( 'Continue without importing', 'ai-search-schema' ); ?> →
					</a>
				</div>
			</div>
		</div>
	<?php endif; ?>
</div>

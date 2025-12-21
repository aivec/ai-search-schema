<?php
// phpcs:ignoreFile Generic.Files.LineLength.TooLong
/**
 * Progress bar component.
 *
 * @package Avc\Aeo\Schema
 * @var array  $step_keys   Array of step keys.
 * @var int    $step_index  Current step index.
 * @var int    $total_steps Total number of steps.
 * @var string $current_step Current step slug.
 * @var array  $progress    Wizard progress data.
 */

defined( 'ABSPATH' ) || exit;

// Steps to show in progress bar (exclude welcome).
$visible_steps = array(
	'basics'   => __( 'Basic Info', 'ai-search-schema' ),
	'type'     => __( 'Site Type', 'ai-search-schema' ),
	'location' => __( 'Location', 'ai-search-schema' ),
	'hours'    => __( 'Hours', 'ai-search-schema' ),
	'complete' => __( 'Complete', 'ai-search-schema' ),
);

$visible_keys    = array_keys( $visible_steps );
$current_visible = array_search( $current_step, $visible_keys, true );
$completed_steps = $progress['completed_steps'] ?? array();
?>
<nav class="ais-wizard__progress" aria-label="<?php esc_attr_e( 'Setup progress', 'ai-search-schema' ); ?>">
	<ol class="ais-wizard__steps">
		<?php foreach ( $visible_steps as $step_key => $step_label ) : ?>
			<?php
			$step_num    = array_search( $step_key, $visible_keys, true ) + 1;
			$is_current  = $step_key === $current_step;
			$is_complete = in_array( $step_key, $completed_steps, true );
			$is_past     = array_search( $step_key, $visible_keys, true ) < $current_visible;

			$classes = array( 'ais-wizard__step' );
			if ( $is_current ) {
				$classes[] = 'ais-wizard__step--current';
			}
			if ( $is_complete || $is_past ) {
				$classes[] = 'ais-wizard__step--complete';
			}
			?>
			<li class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" data-step="<?php echo esc_attr( $step_key ); ?>">
				<span class="ais-wizard__step-num">
					<?php if ( $is_complete || $is_past ) : ?>
						<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
							<path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
						</svg>
					<?php else : ?>
						<?php echo esc_html( $step_num ); ?>
					<?php endif; ?>
				</span>
				<span class="ais-wizard__step-label"><?php echo esc_html( $step_label ); ?></span>
			</li>
		<?php endforeach; ?>
	</ol>
</nav>

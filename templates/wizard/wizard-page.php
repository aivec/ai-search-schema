<?php
// phpcs:ignoreFile Generic.Files.LineLength.TooLong
/**
 * Setup Wizard main page template.
 *
 * @package Aivec\AiSearchSchema
 * @var string $current_step Current step slug.
 * @var array  $progress     Wizard progress data.
 * @var array  $wizard_data  Saved wizard data.
 * @var array  $options      Plugin settings.
 */

defined( 'ABSPATH' ) || exit;

$step_keys   = array_keys( $this->steps );
$step_index  = array_search( $current_step, $step_keys, true );
$total_steps = count( $step_keys );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( get_admin_page_title() ?: __( 'AI Search Schema Setup', 'aivec-ai-search-schema' ) ); ?></title>
	<?php
	wp_enqueue_style( 'ais-wizard' );
	wp_print_styles( 'ais-wizard' );
	wp_print_styles( 'dashicons' );
	?>
</head>
<body class="ais-wizard-body">
	<div class="ais-wizard" data-step="<?php echo esc_attr( $current_step ); ?>">
		<!-- Header -->
		<header class="ais-wizard__header">
			<div class="ais-wizard__logo">
				<img src="<?php echo esc_url( AVC_AIS_URL . 'assets/icon-256x256.png' ); ?>" alt="AI Search Schema" class="ais-wizard__logo-icon" width="40" height="40">
				<span class="ais-wizard__logo-text">AI Search Schema</span>
			</div>
			<div class="ais-wizard__lang">
				<?php
				$current_lang = isset( $_GET['lang'] ) ? sanitize_key( $_GET['lang'] ) : get_user_locale(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$current_lang = $current_lang ?: 'en';
				$is_japanese  = strpos( $current_lang, 'ja' ) === 0;
				?>
				<a href="<?php echo esc_url( add_query_arg( 'lang', 'ja' ) ); ?>" class="<?php echo $is_japanese ? 'active' : ''; ?>">JA</a>
				<span class="ais-wizard__lang-sep">|</span>
				<a href="<?php echo esc_url( add_query_arg( 'lang', 'en' ) ); ?>" class="<?php echo ! $is_japanese ? 'active' : ''; ?>">EN</a>
			</div>
		</header>

		<!-- Progress Bar (hidden on welcome step) -->
		<?php if ( 'welcome' !== $current_step ) : ?>
			<?php include AVC_AIS_DIR . 'templates/wizard/components/progress-bar.php'; ?>
		<?php endif; ?>

		<!-- Main Content -->
		<main class="ais-wizard__main">
			<div class="ais-wizard__content">
				<?php
				$step_view = $this->steps[ $current_step ]['view'] ?? '';
				$step_file = AVC_AIS_DIR . 'templates/wizard/steps/' . $step_view . '.php';

				if ( file_exists( $step_file ) ) {
					include $step_file;
				} else {
					echo '<p>' . esc_html__( 'Step template not found.', 'aivec-ai-search-schema' ) . '</p>';
				}
				?>
			</div>
		</main>

		<!-- Footer -->
		<footer class="ais-wizard__footer">
			<p class="ais-wizard__footer-text">
				<?php
				printf(
					/* translators: %s: plugin version */
					esc_html__( 'AI Search Schema v%s', 'aivec-ai-search-schema' ),
					esc_html( AVC_AIS_VERSION )
				);
				?>
				&bull;
				<a href="https://aivec.co.jp/apps" target="_blank" rel="noopener noreferrer">
					AIVEC LLC.
				</a>
			</p>
		</footer>
	</div>

	<?php
	wp_enqueue_script( 'jquery' );
	wp_enqueue_script( 'wp-util' );
	wp_enqueue_media();
	wp_enqueue_script( 'ais-wizard' );
	wp_print_scripts( array( 'jquery', 'wp-util', 'media-upload', 'ais-wizard' ) );
	?>
</body>
</html>

<?php
/**
 * Corporate error page template.
 *
 * Logo-forward, structured layout with clear hierarchy and a
 * professional footer. Agency-ready styling. Self-contained.
 *
 * Available $context keys:
 *   site_name, logo_url, icon_url, brand_color, bg_color, text_color,
 *   dark_mode, error_title, error_message, home_url, back_url, back_link,
 *   response_code, charset, text_direction, year, css_url,
 *   primary_btn_text, primary_btn_url, secondary_btn_text,
 *   secondary_btn_url, copyright, support_link
 *
 * @package GracefulErrorPages
 * @var array<string, mixed> $context Template context.
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Self-contained error page, wp_head() unavailable.
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( str_replace( '_', '-', function_exists( 'get_locale' ) ? get_locale() : 'en' ) ); ?>" dir="<?php echo esc_attr( (string) ( $context['text_direction'] ?? 'ltr' ) ); ?>">
<head>
	<meta charset="<?php echo esc_attr( (string) ( $context['charset'] ?? 'UTF-8' ) ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( (string) ( $context['error_title'] ?? '' ) ); ?></title>
	<?php if ( ! empty( $context['css_url'] ) ) : ?>
		<link rel="stylesheet" href="<?php echo esc_url( (string) $context['css_url'] ); ?>">
	<?php endif; ?>
	<style>
		body.gcep-error-page[data-dark-mode] {
			--gcep-brand-color: <?php echo esc_attr( (string) ( $context['brand_color'] ?? '#2563eb' ) ); ?>;
			<?php if ( ! empty( $context['bg_color'] ) ) : ?>
			--gcep-bg-color: <?php echo esc_attr( (string) $context['bg_color'] ); ?>;
			<?php endif; ?>
			<?php if ( ! empty( $context['text_color'] ) ) : ?>
			--gcep-text-color: <?php echo esc_attr( (string) $context['text_color'] ); ?>;
			<?php endif; ?>
		}
	</style>
</head>
<body class="gcep-error-page gcep-template-corporate" data-dark-mode="<?php echo esc_attr( (string) ( $context['dark_mode'] ?? 'auto' ) ); ?>">
	<main class="gcep-card">
		<?php if ( ! empty( $context['logo_url'] ) ) : ?>
			<img class="gcep-logo" src="<?php echo esc_url( (string) $context['logo_url'] ); ?>" alt="<?php echo esc_attr( (string) ( $context['site_name'] ?? '' ) ); ?>">
		<?php elseif ( ! empty( $context['icon_url'] ) ) : ?>
			<img class="gcep-icon" src="<?php echo esc_url( (string) $context['icon_url'] ); ?>" alt="<?php echo esc_attr( (string) ( $context['site_name'] ?? '' ) ); ?>">
		<?php elseif ( ! empty( $context['site_name'] ) ) : ?>
			<div class="gcep-logo-text"><?php echo esc_html( (string) $context['site_name'] ); ?></div>
		<?php endif; ?>

		<h1 class="gcep-title"><?php echo esc_html( (string) ( $context['error_title'] ?? '' ) ); ?></h1>
		<p class="gcep-message"><?php echo wp_kses_post( (string) ( $context['error_message'] ?? '' ) ); ?></p>

		<div class="gcep-actions">
			<a href="<?php echo esc_url( (string) ( $context['primary_btn_url'] ?? $context['home_url'] ?? '/' ) ); ?>" class="gcep-btn gcep-btn-primary">
				<?php echo esc_html( (string) ( $context['primary_btn_text'] ?? __( 'Go to Homepage', 'graceful-error-pages' ) ) ); ?>
			</a>
			<?php if ( ! empty( $context['back_link'] ) ) : ?>
				<?php
				$gcep_secondary_url  = ! empty( $context['secondary_btn_url'] ) ? $context['secondary_btn_url'] : ( $context['back_url'] ?? '' );
				$gcep_secondary_text = (string) ( $context['secondary_btn_text'] ?? __( 'Go Back', 'graceful-error-pages' ) );
				?>
				<?php if ( '' !== $gcep_secondary_url ) : ?>
					<a href="<?php echo esc_url( (string) $gcep_secondary_url ); ?>" class="gcep-btn gcep-btn-secondary">
						<?php echo esc_html( $gcep_secondary_text ); ?>
					</a>
				<?php else : ?>
					<button type="button" class="gcep-btn gcep-btn-secondary" onclick="history.back()">
						<?php echo esc_html( $gcep_secondary_text ); ?>
					</button>
				<?php endif; ?>
			<?php endif; ?>
		</div>

		<?php if ( ! empty( $context['support_link'] ) ) : ?>
			<p class="gcep-support">
				<a href="<?php echo esc_url( (string) $context['support_link'] ); ?>">
					<?php echo esc_html__( 'Contact Support', 'graceful-error-pages' ); ?>
				</a>
			</p>
		<?php endif; ?>

		<footer class="gcep-footer">
			<?php if ( ! empty( $context['copyright'] ) ) : ?>
				<?php echo esc_html( (string) $context['copyright'] ); ?>
			<?php else : ?>
				&copy; <?php echo esc_html( (string) ( $context['year'] ?? gmdate( 'Y' ) ) ); ?> <?php echo esc_html( (string) ( $context['site_name'] ?? '' ) ); ?>
			<?php endif; ?>
		</footer>
	</main>
</body>
</html>
<?php
// phpcs:enable

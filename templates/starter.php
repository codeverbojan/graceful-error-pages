<?php
/**
 * Starter error page template.
 *
 * Bare minimum: no card, no decoration, no SVG. Just readable
 * centered text with enough spacing to not look broken.
 * Lightest possible output for performance-conscious sites.
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
		body.gep-error-page[data-dark-mode] {
			--gep-brand-color: <?php echo esc_attr( (string) ( $context['brand_color'] ?? '#2563eb' ) ); ?>;
			<?php if ( ! empty( $context['bg_color'] ) ) : ?>
			--gep-bg-color: <?php echo esc_attr( (string) $context['bg_color'] ); ?>;
			<?php endif; ?>
			<?php if ( ! empty( $context['text_color'] ) ) : ?>
			--gep-text-color: <?php echo esc_attr( (string) $context['text_color'] ); ?>;
			<?php endif; ?>
		}
	</style>
</head>
<body class="gep-error-page gep-template-starter" data-dark-mode="<?php echo esc_attr( (string) ( $context['dark_mode'] ?? 'auto' ) ); ?>">
	<main class="gep-card">
		<?php if ( ! empty( $context['logo_url'] ) ) : ?>
			<img class="gep-logo" src="<?php echo esc_url( (string) $context['logo_url'] ); ?>" alt="<?php echo esc_attr( (string) ( $context['site_name'] ?? '' ) ); ?>">
		<?php elseif ( ! empty( $context['icon_url'] ) ) : ?>
			<img class="gep-icon" src="<?php echo esc_url( (string) $context['icon_url'] ); ?>" alt="<?php echo esc_attr( (string) ( $context['site_name'] ?? '' ) ); ?>">
		<?php endif; ?>

		<h1 class="gep-title"><?php echo esc_html( (string) ( $context['error_title'] ?? '' ) ); ?></h1>
		<p class="gep-message"><?php echo wp_kses_post( (string) ( $context['error_message'] ?? '' ) ); ?></p>

		<div class="gep-actions">
			<a href="<?php echo esc_url( (string) ( $context['primary_btn_url'] ?? $context['home_url'] ?? '/' ) ); ?>" class="gep-btn gep-btn-primary">
				<?php echo esc_html( (string) ( $context['primary_btn_text'] ?? __( 'Go to Homepage', 'graceful-error-pages' ) ) ); ?>
			</a>
			<?php if ( ! empty( $context['back_link'] ) ) : ?>
				<?php
				$gep_secondary_url  = ! empty( $context['secondary_btn_url'] ) ? $context['secondary_btn_url'] : ( $context['back_url'] ?? '' );
				$gep_secondary_text = (string) ( $context['secondary_btn_text'] ?? __( 'Go Back', 'graceful-error-pages' ) );
				?>
				<?php if ( '' !== $gep_secondary_url ) : ?>
					<a href="<?php echo esc_url( (string) $gep_secondary_url ); ?>" class="gep-btn gep-btn-secondary">
						<?php echo esc_html( $gep_secondary_text ); ?>
					</a>
				<?php else : ?>
					<button type="button" class="gep-btn gep-btn-secondary" onclick="history.back()">
						<?php echo esc_html( $gep_secondary_text ); ?>
					</button>
				<?php endif; ?>
			<?php endif; ?>
		</div>

		<?php if ( ! empty( $context['support_link'] ) ) : ?>
			<p class="gep-support">
				<a href="<?php echo esc_url( (string) $context['support_link'] ); ?>">
					<?php echo esc_html__( 'Contact Support', 'graceful-error-pages' ); ?>
				</a>
			</p>
		<?php endif; ?>

		<footer class="gep-footer">
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

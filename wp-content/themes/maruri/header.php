<?php
/**
 * Nombre: Header principal
 * Descripcion: Imprime la estructura inicial del documento y el encabezado global del sitio.
 * Uso: Se incluye desde las plantillas base del theme.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div id="page" class="site">
	<a class="skip-link screen-reader-text" href="#primary"><?php esc_html_e( 'Skip to content', 'maruri' ); ?></a>
	<header class="site-header">
		<div class="maruri-shell maruri-shell--header">
			<div class="site-branding">
				<?php if ( has_custom_logo() ) : ?>
					<div class="site-logo"><?php the_custom_logo(); ?></div>
				<?php endif; ?>
				<div class="site-branding__content">
					<?php
					$maruri_brand_name = maruri_get_brand_name();
					if ( is_front_page() && is_home() ) :
						?>
						<h1 class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php echo esc_html( $maruri_brand_name ); ?></a></h1>
					<?php else : ?>
						<p class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php echo esc_html( $maruri_brand_name ); ?></a></p>
					<?php endif; ?>
					<?php
					$description = get_bloginfo( 'description', 'display' );
					if ( $description ) :
						?>
						<p class="site-description"><?php echo esc_html( $description ); ?></p>
					<?php endif; ?>
				</div>
			</div>
			<nav class="primary-navigation" aria-label="<?php esc_attr_e( 'Primary menu', 'maruri' ); ?>">
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'primary',
						'menu_id'        => 'primary-menu',
						'container'      => false,
						'fallback_cb'    => 'maruri_render_fallback_menu',
					)
				);
				?>
			</nav>
		</div>
	</header>

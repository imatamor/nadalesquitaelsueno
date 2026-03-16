<?php
/**
 * Nombre: Footer principal
 * Descripcion: Cierra la estructura base del sitio y muestra el footer global reutilizable.
 * Uso: Se incluye desde las plantillas base del theme.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
	<footer class="site-footer">
		<div class="maruri-shell maruri-shell--footer">
			<div class="site-info">
				<p>
					<?php echo esc_html( maruri_get_footer_copyright() ); ?>
				</p>
				<?php $maruri_contact_email = maruri_get_contact_email(); ?>
				<?php if ( $maruri_contact_email ) : ?>
					<p class="site-contact">
						<a href="mailto:<?php echo esc_attr( antispambot( $maruri_contact_email ) ); ?>"><?php echo esc_html( antispambot( $maruri_contact_email ) ); ?></a>
					</p>
				<?php endif; ?>
				<?php maruri_render_social_links(); ?>
			</div>
			<?php if ( is_active_sidebar( 'footer-1' ) ) : ?>
				<div class="footer-widgets">
					<?php dynamic_sidebar( 'footer-1' ); ?>
				</div>
			<?php endif; ?>
		</div>
	</footer>
</div>
<?php wp_footer(); ?>
</body>
</html>

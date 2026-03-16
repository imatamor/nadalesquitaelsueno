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
					<?php
					printf(
						/* translators: %s: current year */
						esc_html__( '%s Maruri. Todos los derechos reservados.', 'maruri' ),
						esc_html( gmdate( 'Y' ) )
					);
					?>
				</p>
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

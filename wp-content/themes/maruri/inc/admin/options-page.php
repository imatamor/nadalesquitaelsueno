<?php
/**
 * Nombre: Opciones del theme
 * Descripcion: Registra una pagina simple de administracion para branding y configuracion global reutilizable.
 * Uso: Se carga desde functions.php y usa la Settings API de WordPress.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Nombre: maruri_register_theme_options_page
 * Descripcion: Agrega la pagina de opciones del theme dentro del menu Apariencia.
 * Uso: Hookeada a admin_menu.
 */
function maruri_register_theme_options_page() {
	add_theme_page(
		__( 'Maruri Theme Options', 'maruri' ),
		__( 'Maruri Options', 'maruri' ),
		'manage_options',
		'maruri-theme-options',
		'maruri_render_theme_options_page'
	);
}
add_action( 'admin_menu', 'maruri_register_theme_options_page' );

/**
 * Nombre: maruri_register_theme_settings
 * Descripcion: Registra ajustes y campos del theme para branding, contacto y snippets globales.
 * Uso: Hookeada a admin_init.
 */
function maruri_register_theme_settings() {
	register_setting(
		'maruri_theme_options_group',
		'maruri_theme_options',
		array(
			'sanitize_callback' => 'maruri_sanitize_theme_options',
			'default'           => array(),
		)
	);

	add_settings_section(
		'maruri_branding_section',
		__( 'Branding y layout base', 'maruri' ),
		'__return_false',
		'maruri-theme-options'
	);

	add_settings_field( 'brand_name', __( 'Brand Name', 'maruri' ), 'maruri_render_text_field', 'maruri-theme-options', 'maruri_branding_section', array( 'key' => 'brand_name' ) );
	add_settings_field( 'contact_email', __( 'Contact Email', 'maruri' ), 'maruri_render_text_field', 'maruri-theme-options', 'maruri_branding_section', array( 'key' => 'contact_email', 'type' => 'email' ) );
	add_settings_field( 'footer_copyright', __( 'Footer Copyright', 'maruri' ), 'maruri_render_text_field', 'maruri-theme-options', 'maruri_branding_section', array( 'key' => 'footer_copyright' ) );
	add_settings_field(
		'sidebar_behavior',
		__( 'Sidebar Behavior', 'maruri' ),
		'maruri_render_select_field',
		'maruri-theme-options',
		'maruri_branding_section',
		array(
			'key'     => 'sidebar_behavior',
			'default' => 'auto',
			'options' => array(
				'auto'   => __( 'Automatic', 'maruri' ),
				'always' => __( 'Always show when widgets exist', 'maruri' ),
				'hidden' => __( 'Always hide', 'maruri' ),
			),
		)
	);

	add_settings_section(
		'maruri_channels_section',
		__( 'Canales y redes', 'maruri' ),
		'__return_false',
		'maruri-theme-options'
	);

	add_settings_field(
		'social_links',
		__( 'Social Links', 'maruri' ),
		'maruri_render_textarea_field',
		'maruri-theme-options',
		'maruri_channels_section',
		array(
			'key'         => 'social_links',
			'rows'        => 6,
			'description' => __( 'Una red por linea con el formato red|url|etiqueta. Ejemplo: instagram|https://instagram.com/maruri|Instagram', 'maruri' ),
		)
	);

	add_settings_section(
		'maruri_design_section',
		__( 'Design foundations', 'maruri' ),
		'__return_false',
		'maruri-theme-options'
	);

	add_settings_field( 'accent_color', __( 'Accent Color', 'maruri' ), 'maruri_render_text_field', 'maruri-theme-options', 'maruri_design_section', array( 'key' => 'accent_color', 'type' => 'color' ) );
	add_settings_field( 'background_color', __( 'Background Color', 'maruri' ), 'maruri_render_text_field', 'maruri-theme-options', 'maruri_design_section', array( 'key' => 'background_color', 'type' => 'color' ) );
	add_settings_field( 'surface_color', __( 'Surface Color', 'maruri' ), 'maruri_render_text_field', 'maruri-theme-options', 'maruri_design_section', array( 'key' => 'surface_color', 'type' => 'color' ) );
	add_settings_field( 'text_color', __( 'Text Color', 'maruri' ), 'maruri_render_text_field', 'maruri-theme-options', 'maruri_design_section', array( 'key' => 'text_color', 'type' => 'color' ) );
	add_settings_field( 'shell_width', __( 'Shell Width (px)', 'maruri' ), 'maruri_render_text_field', 'maruri-theme-options', 'maruri_design_section', array( 'key' => 'shell_width', 'type' => 'number' ) );
	add_settings_field( 'reading_width', __( 'Reading Width (px)', 'maruri' ), 'maruri_render_text_field', 'maruri-theme-options', 'maruri_design_section', array( 'key' => 'reading_width', 'type' => 'number' ) );

	add_settings_section(
		'maruri_snippets_section',
		__( 'Custom code snippets', 'maruri' ),
		'__return_false',
		'maruri-theme-options'
	);

	add_settings_field( 'custom_css', __( 'Custom CSS', 'maruri' ), 'maruri_render_textarea_field', 'maruri-theme-options', 'maruri_snippets_section', array( 'key' => 'custom_css', 'rows' => 8 ) );
	add_settings_field( 'custom_js', __( 'Custom JS', 'maruri' ), 'maruri_render_textarea_field', 'maruri-theme-options', 'maruri_snippets_section', array( 'key' => 'custom_js', 'rows' => 8 ) );
	add_settings_field( 'head_scripts', __( 'Head Scripts', 'maruri' ), 'maruri_render_textarea_field', 'maruri-theme-options', 'maruri_snippets_section', array( 'key' => 'head_scripts', 'rows' => 6 ) );
	add_settings_field( 'body_open_scripts', __( 'Body Open Scripts', 'maruri' ), 'maruri_render_textarea_field', 'maruri-theme-options', 'maruri_snippets_section', array( 'key' => 'body_open_scripts', 'rows' => 6 ) );
	add_settings_field( 'footer_scripts', __( 'Footer Scripts', 'maruri' ), 'maruri_render_textarea_field', 'maruri-theme-options', 'maruri_snippets_section', array( 'key' => 'footer_scripts', 'rows' => 6 ) );
}
add_action( 'admin_init', 'maruri_register_theme_settings' );

/**
 * Nombre: maruri_sanitize_theme_options
 * Descripcion: Sanitiza los valores del panel del theme segun el tipo de dato esperado.
 * Uso: Callback de register_setting.
 * Parametros:
 * - $input: Valores enviados desde el formulario.
 * Retorna: Array sanitizado para persistencia.
 */
function maruri_sanitize_theme_options( $input ) {
	$sanitized = array();
	$input     = is_array( $input ) ? $input : array();

	$sanitized['brand_name']        = isset( $input['brand_name'] ) ? sanitize_text_field( $input['brand_name'] ) : '';
	$sanitized['contact_email']     = isset( $input['contact_email'] ) ? sanitize_email( $input['contact_email'] ) : '';
	$sanitized['footer_copyright']  = isset( $input['footer_copyright'] ) ? sanitize_text_field( $input['footer_copyright'] ) : '';
	$sanitized['sidebar_behavior']  = isset( $input['sidebar_behavior'] ) && in_array( $input['sidebar_behavior'], array( 'auto', 'always', 'hidden' ), true ) ? $input['sidebar_behavior'] : 'auto';
	$sanitized['social_links']      = isset( $input['social_links'] ) ? sanitize_textarea_field( $input['social_links'] ) : '';
	$sanitized['accent_color']      = isset( $input['accent_color'] ) ? sanitize_hex_color( $input['accent_color'] ) : '';
	$sanitized['background_color']  = isset( $input['background_color'] ) ? sanitize_hex_color( $input['background_color'] ) : '';
	$sanitized['surface_color']     = isset( $input['surface_color'] ) ? sanitize_hex_color( $input['surface_color'] ) : '';
	$sanitized['text_color']        = isset( $input['text_color'] ) ? sanitize_hex_color( $input['text_color'] ) : '';
	$sanitized['shell_width']       = isset( $input['shell_width'] ) ? absint( $input['shell_width'] ) : 0;
	$sanitized['reading_width']     = isset( $input['reading_width'] ) ? absint( $input['reading_width'] ) : 0;
	$sanitized['custom_css']        = isset( $input['custom_css'] ) ? sanitize_textarea_field( $input['custom_css'] ) : '';
	$sanitized['custom_js']         = isset( $input['custom_js'] ) ? sanitize_textarea_field( $input['custom_js'] ) : '';
	$sanitized['head_scripts']      = isset( $input['head_scripts'] ) ? wp_kses_post( $input['head_scripts'] ) : '';
	$sanitized['body_open_scripts'] = isset( $input['body_open_scripts'] ) ? wp_kses_post( $input['body_open_scripts'] ) : '';
	$sanitized['footer_scripts']    = isset( $input['footer_scripts'] ) ? wp_kses_post( $input['footer_scripts'] ) : '';

	return $sanitized;
}

/**
 * Nombre: maruri_render_text_field
 * Descripcion: Imprime un campo de texto simple conectado al array de opciones del theme.
 * Uso: Callback de add_settings_field.
 * Parametros:
 * - $args: Configuracion del campo.
 */
function maruri_render_text_field( $args ) {
	$key   = isset( $args['key'] ) ? $args['key'] : '';
	$type  = isset( $args['type'] ) ? $args['type'] : 'text';
	$value = maruri_get_theme_option( $key, '' );
	?>
	<input class="regular-text" type="<?php echo esc_attr( $type ); ?>" name="maruri_theme_options[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>">
	<?php if ( ! empty( $args['description'] ) ) : ?>
		<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
	<?php endif; ?>
	<?php
}

/**
 * Nombre: maruri_render_select_field
 * Descripcion: Imprime un select conectado al array de opciones del theme.
 * Uso: Callback de add_settings_field.
 * Parametros:
 * - $args: Configuracion del campo y opciones.
 */
function maruri_render_select_field( $args ) {
	$key     = isset( $args['key'] ) ? $args['key'] : '';
	$default = isset( $args['default'] ) ? $args['default'] : '';
	$value   = maruri_get_theme_option( $key, $default );
	$options = isset( $args['options'] ) && is_array( $args['options'] ) ? $args['options'] : array();
	?>
	<select name="maruri_theme_options[<?php echo esc_attr( $key ); ?>]">
		<?php foreach ( $options as $option_value => $label ) : ?>
			<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $value, $option_value ); ?>><?php echo esc_html( $label ); ?></option>
		<?php endforeach; ?>
	</select>
	<?php
}

/**
 * Nombre: maruri_render_textarea_field
 * Descripcion: Imprime un textarea conectado al array de opciones del theme.
 * Uso: Callback de add_settings_field.
 * Parametros:
 * - $args: Configuracion del campo.
 */
function maruri_render_textarea_field( $args ) {
	$key   = isset( $args['key'] ) ? $args['key'] : '';
	$rows  = isset( $args['rows'] ) ? (int) $args['rows'] : 6;
	$value = maruri_get_theme_option( $key, '' );
	?>
	<textarea class="large-text code" rows="<?php echo esc_attr( $rows ); ?>" name="maruri_theme_options[<?php echo esc_attr( $key ); ?>]"><?php echo esc_textarea( $value ); ?></textarea>
	<?php if ( ! empty( $args['description'] ) ) : ?>
		<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
	<?php endif; ?>
	<?php
}

/**
 * Nombre: maruri_render_theme_options_page
 * Descripcion: Renderiza la interfaz administrativa principal del theme.
 * Uso: Callback de add_theme_page.
 */
function maruri_render_theme_options_page() {
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Maruri Theme Options', 'maruri' ); ?></h1>
		<p><?php esc_html_e( 'Configura branding global y snippets reutilizables sin acoplar el theme a un builder especifico.', 'maruri' ); ?></p>
		<form method="post" action="options.php">
			<?php settings_fields( 'maruri_theme_options_group' ); ?>
			<?php do_settings_sections( 'maruri-theme-options' ); ?>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

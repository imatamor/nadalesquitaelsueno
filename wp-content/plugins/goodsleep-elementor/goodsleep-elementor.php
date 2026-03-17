<?php
/**
 * Plugin Name: Goodsleep Elementor
 * Plugin URI: https://xn--nadalesquitaelsueo-30b.com
 * Description: Widgets, integraciones y logica de campana para la landing Goodsleep.
 * Version: 0.1.0
 * Author: Isaac Matamoros
 * Author URI: https://xn--nadalesquitaelsueo-30b.com
 * Text Domain: goodsleep-elementor
 * Requires at least: 6.5
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GOODSLEEP_ELEMENTOR_VERSION', '0.1.0' );
define( 'GOODSLEEP_ELEMENTOR_FILE', __FILE__ );
define( 'GOODSLEEP_ELEMENTOR_PATH', plugin_dir_path( __FILE__ ) );
define( 'GOODSLEEP_ELEMENTOR_URL', plugin_dir_url( __FILE__ ) );

require_once GOODSLEEP_ELEMENTOR_PATH . 'includes/functions-helpers.php';
require_once GOODSLEEP_ELEMENTOR_PATH . 'includes/class-goodsleep-elementor-plugin.php';

/**
 * Devuelve la instancia principal del plugin.
 *
 * @return Goodsleep_Elementor_Plugin
 */
function goodsleep_elementor() {
	return Goodsleep_Elementor_Plugin::instance();
}

goodsleep_elementor();

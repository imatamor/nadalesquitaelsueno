<?php
/**
 * Integracion del plugin con Elementor.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Goodsleep_Elementor_Elementor {
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
	}

	/**
	 * Registra la categoria de widgets.
	 *
	 * @param \Elementor\Elements_Manager $elements_manager Manager.
	 * @return void
	 */
	public function register_category( $elements_manager ) {
		$elements_manager->add_category(
			'goodsleep',
			array(
				'title' => __( 'Goodsleep', 'goodsleep-elementor' ),
				'icon'  => 'fa fa-moon',
			)
		);
	}

	/**
	 * Registra widgets custom.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Manager.
	 * @return void
	 */
	public function register_widgets( $widgets_manager ) {
		if ( ! did_action( 'elementor/loaded' ) ) {
			return;
		}

		require_once GOODSLEEP_ELEMENTOR_PATH . 'includes/widgets/class-goodsleep-historia-generator-widget.php';
		require_once GOODSLEEP_ELEMENTOR_PATH . 'includes/widgets/class-goodsleep-historias-list-widget.php';

		$widgets_manager->register( new Goodsleep_Historia_Generator_Widget() );
		$widgets_manager->register( new Goodsleep_Historias_List_Widget() );
	}
}

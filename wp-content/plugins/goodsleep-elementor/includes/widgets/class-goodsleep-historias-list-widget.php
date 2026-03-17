<?php
/**
 * Widget Historias List.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Goodsleep_Historias_List_Widget extends \Elementor\Widget_Base {
	/**
	 * @return string
	 */
	public function get_name() {
		return 'goodsleep-historias-list';
	}

	/**
	 * @return string
	 */
	public function get_title() {
		return __( 'Historias List', 'goodsleep-elementor' );
	}

	/**
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-post-list';
	}

	/**
	 * @return array<int,string>
	 */
	public function get_categories() {
		return array( 'goodsleep' );
	}

	/**
	 * @return array<int,string>
	 */
	public function get_style_depends() {
		return array( 'goodsleep-elementor-frontend' );
	}

	/**
	 * @return array<int,string>
	 */
	public function get_script_depends() {
		return array( 'goodsleep-elementor-frontend' );
	}

	/**
	 * Registra controles.
	 *
	 * @return void
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'content_section',
			array(
				'label' => __( 'Contenido', 'goodsleep-elementor' ),
			)
		);

		$this->add_control(
			'empty_state',
			array(
				'label'   => __( 'Mensaje sin resultados', 'goodsleep-elementor' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Todavia no hay historias publicadas.', 'goodsleep-elementor' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Renderiza el widget.
	 *
	 * @return void
	 */
	protected function render() {
		$settings  = $this->get_settings_for_display();
		$widget_id = 'goodsleep-historias-list-' . $this->get_id();
		?>
		<div id="<?php echo esc_attr( $widget_id ); ?>" class="goodsleep-stories" data-empty-state="<?php echo esc_attr( $settings['empty_state'] ); ?>">
			<div class="goodsleep-stories__toolbar">
				<input class="goodsleep-stories__search" type="search" placeholder="<?php esc_attr_e( 'Buscar historias', 'goodsleep-elementor' ); ?>" data-search>
				<div class="goodsleep-stories__filters">
					<button type="button" class="is-active" data-sort="recent"><?php esc_html_e( 'Recientes', 'goodsleep-elementor' ); ?></button>
					<button type="button" data-sort="favorites"><?php esc_html_e( 'Favoritos', 'goodsleep-elementor' ); ?></button>
					<button type="button" data-sort="rank"><?php esc_html_e( 'Rank', 'goodsleep-elementor' ); ?></button>
				</div>
			</div>
			<div class="goodsleep-stories__viewport" data-viewport>
				<div class="goodsleep-stories__list" data-list></div>
				<div class="goodsleep-stories__sentinel" data-sentinel></div>
			</div>
		</div>
		<?php
	}
}

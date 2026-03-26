<?php
/**
 * Widget Historia Generator.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Goodsleep_Historia_Generator_Widget extends \Elementor\Widget_Base {
	/**
	 * @return string
	 */
	public function get_name() {
		return 'goodsleep-historia-generator';
	}

	/**
	 * @return string
	 */
	public function get_title() {
		return __( 'Historia Generator', 'goodsleep-elementor' );
	}

	/**
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-form-horizontal';
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
	 * Registra controles del widget.
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
			'phrase_template',
			array(
				'label'       => __( 'Frase dinamica', 'goodsleep-elementor' ),
				'type'        => \Elementor\Controls_Manager::TEXTAREA,
				'default'     => __( 'Nada le quita el sueno a %s porque toma Goodsleep.', 'goodsleep-elementor' ),
				'description' => __( 'Usa %s para insertar el nombre ingresado.', 'goodsleep-elementor' ),
			)
		);

		$this->add_control(
			'terms_text',
			array(
				'label'   => __( 'Texto de terminos', 'goodsleep-elementor' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => goodsleep_get_setting( 'terms_text', __( 'Acepto terminos y condiciones', 'goodsleep-elementor' ) ),
			)
		);

		$this->add_control(
			'terms_url',
			array(
				'label'   => __( 'URL de terminos', 'goodsleep-elementor' ),
				'type'    => \Elementor\Controls_Manager::URL,
				'default' => array(
					'url'         => goodsleep_get_setting( 'terms_url', '' ),
					'is_external' => true,
				),
			)
		);

		$this->add_control(
			'submit_label',
			array(
				'label'   => __( 'Texto del boton', 'goodsleep-elementor' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Hazlo video', 'goodsleep-elementor' ),
			)
		);

		$this->add_control(
			'loader_label',
			array(
				'label'   => __( 'Texto del loader', 'goodsleep-elementor' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Nada le quita el sueno a %s', 'goodsleep-elementor' ),
			)
		);

		$this->add_control(
			'result_cta_label',
			array(
				'label'   => __( 'Texto del CTA final', 'goodsleep-elementor' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'VER HISTORIAS', 'goodsleep-elementor' ),
			)
		);

		$this->add_control(
			'result_cta_url',
			array(
				'label'   => __( 'Link del CTA final', 'goodsleep-elementor' ),
				'type'    => \Elementor\Controls_Manager::URL,
				'default' => array(
					'url'         => home_url( '/#historias' ),
					'is_external' => false,
				),
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
		$settings       = $this->get_settings_for_display();
		$terms_url      = ! empty( $settings['terms_url']['url'] ) ? $settings['terms_url']['url'] : '';
		$result_cta_url = ! empty( $settings['result_cta_url']['url'] ) ? $settings['result_cta_url']['url'] : home_url( '/#historias' );
		$widget_id      = 'goodsleep-historia-generator-' . $this->get_id();
		$allowed_tracks = goodsleep_get_allowed_tracks();
		$default_track  = goodsleep_get_default_track();
		?>
		<div id="<?php echo esc_attr( $widget_id ); ?>" class="goodsleep-generator" data-phrase-template="<?php echo esc_attr( $settings['phrase_template'] ); ?>" data-loader-template="<?php echo esc_attr( $settings['loader_label'] ); ?>">
			<div class="goodsleep-generator__surface goodsleep-generator__surface--form" data-state="form">
				<form class="goodsleep-generator__form">
					<div class="goodsleep-generator__field-row goodsleep-generator__field-row--email">
						<div class="goodsleep-generator__field goodsleep-generator__field--email">
							<input type="email" name="email" placeholder="<?php esc_attr_e( 'Danos tu correo electronico', 'goodsleep-elementor' ); ?>" required>
						</div>
						<p class="goodsleep-generator__field-note goodsleep-generator__email-note"><?php esc_html_e( 'donde quieres que te llegue el link con el video', 'goodsleep-elementor' ); ?></p>
					</div>
					<div class="goodsleep-generator__field-row goodsleep-generator__field-row--name">
						<div class="goodsleep-generator__field goodsleep-generator__field--name">
							<input type="text" name="name" maxlength="15" placeholder="<?php esc_attr_e( 'Nombre de la persona', 'goodsleep-elementor' ); ?>" required pattern="^\S+$">
						</div>
						<p class="goodsleep-generator__field-note goodsleep-generator__name-note"><?php esc_html_e( 'no incluyas el apellido', 'goodsleep-elementor' ); ?></p>
					</div>
					<div class="goodsleep-generator__bubble">
						<textarea name="story_text" maxlength="500" placeholder="<?php esc_attr_e( 'Escribe su historia', 'goodsleep-elementor' ); ?>" required></textarea>
						<p class="goodsleep-generator__phrase" data-dynamic-phrase><?php echo esc_html( sprintf( $settings['phrase_template'], '' ) ); ?></p>
						<div class="goodsleep-generator__counter"><span data-char-count>0</span>/500</div>
					</div>
					<div class="goodsleep-generator__controls goodsleep-generator__controls--auto" hidden aria-hidden="true">
						<select name="track_id" required>
							<?php foreach ( $allowed_tracks as $track ) : ?>
								<option value="<?php echo esc_attr( $track['id'] ); ?>" data-label="<?php echo esc_attr( $track['label'] ); ?>" <?php selected( ! empty( $default_track['id'] ) ? $default_track['id'] : '', $track['id'] ); ?>><?php echo esc_html( $track['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="goodsleep-generator__footer">
						<label class="goodsleep-generator__terms">
							<input type="checkbox" name="accepted_terms" required>
							<span>
								<?php if ( $terms_url ) : ?>
									<a href="<?php echo esc_url( $terms_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $settings['terms_text'] ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $settings['terms_text'] ); ?>
								<?php endif; ?>
							</span>
						</label>
						<button type="submit" class="goodsleep-generator__submit"><?php echo esc_html( $settings['submit_label'] ); ?></button>
					</div>
				</form>
			</div>
			<div class="goodsleep-generator__surface goodsleep-generator__surface--loading" hidden>
				<div class="goodsleep-generator__loader-orb"></div>
				<p class="goodsleep-generator__loader-text" data-loader-text></p>
			</div>
			<div class="goodsleep-generator__surface goodsleep-generator__surface--result" hidden>
				<div class="goodsleep-generator__result-card goodsleep-story-card goodsleep-generator__result-story-card">
					<p class="goodsleep-story-card__text goodsleep-generator__result-copy" data-result-copy><?php esc_html_e( 'Tu video se esta procesando. Te enviaremos el link por correo cuando este listo.', 'goodsleep-elementor' ); ?></p>
					<video controls preload="metadata" playsinline data-result-video hidden></video>
					<div class="goodsleep-generator__result-cta-wrap">
						<a class="goodsleep-generator__action goodsleep-generator__action--result-cta" href="<?php echo esc_url( $result_cta_url ); ?>"<?php echo ! empty( $settings['result_cta_url']['is_external'] ) ? ' target="_blank" rel="noopener noreferrer"' : ''; ?> data-result-cta><?php echo esc_html( $settings['result_cta_label'] ); ?></a>
					</div>
				</div>
			</div>
			<div class="goodsleep-generator__feedback" data-feedback></div>
		</div>
		<?php
	}
}

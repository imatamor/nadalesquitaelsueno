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
				'label'       => __( 'Frase dinámica', 'goodsleep-elementor' ),
				'type'        => \Elementor\Controls_Manager::TEXTAREA,
				'default'     => __( 'Nada le quita el sueño a %s porque toma Goodsleep.', 'goodsleep-elementor' ),
				'description' => __( 'Usa %s para insertar el nombre ingresado.', 'goodsleep-elementor' ),
			)
		);

		$this->add_control(
			'terms_text',
			array(
				'label'   => __( 'Texto de términos', 'goodsleep-elementor' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => goodsleep_get_setting( 'terms_text', __( 'Acepto términos y condiciones', 'goodsleep-elementor' ) ),
			)
		);

		$this->add_control(
			'terms_url',
			array(
				'label'   => __( 'URL de términos', 'goodsleep-elementor' ),
				'type'    => \Elementor\Controls_Manager::URL,
				'default' => array(
					'url'         => goodsleep_get_setting( 'terms_url', '' ),
					'is_external' => true,
				),
			)
		);

		$this->add_control(
			'phrase_emotion',
			array(
				'label'   => __( 'Emoción de la frase final', 'goodsleep-elementor' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => goodsleep_get_speechify_emotions(),
				'default' => 'cheerful',
				// Hidden for now while we prioritize voice continuity over expressive style changes.
				'render_type' => 'none',
				'condition' => array(
					'_goodsleep_show_phrase_emotion' => 'yes',
				),
			)
		);

		$this->add_control(
			'submit_label',
			array(
				'label'   => __( 'Texto del botón', 'goodsleep-elementor' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Hazlo audio', 'goodsleep-elementor' ),
			)
		);

		$this->add_control(
			'loader_label',
			array(
				'label'   => __( 'Texto del loader', 'goodsleep-elementor' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Nada le quita el sueño a %s', 'goodsleep-elementor' ),
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
		$terms_url = ! empty( $settings['terms_url']['url'] ) ? $settings['terms_url']['url'] : '';
		$widget_id = 'goodsleep-historia-generator-' . $this->get_id();
		$emotion   = ! empty( $settings['phrase_emotion'] ) ? goodsleep_sanitize_speechify_emotion( $settings['phrase_emotion'] ) : 'cheerful';
		?>
		<div id="<?php echo esc_attr( $widget_id ); ?>" class="goodsleep-generator" data-phrase-template="<?php echo esc_attr( $settings['phrase_template'] ); ?>" data-loader-template="<?php echo esc_attr( $settings['loader_label'] ); ?>" data-phrase-emotion="<?php echo esc_attr( $emotion ); ?>">
			<div class="goodsleep-generator__surface goodsleep-generator__surface--form" data-state="form">
				<form class="goodsleep-generator__form">
					<div class="goodsleep-generator__field">
						<input type="text" name="name" maxlength="15" placeholder="<?php esc_attr_e( 'Nombre', 'goodsleep-elementor' ); ?>" required pattern="^\S+$">
					</div>
					<div class="goodsleep-generator__field-row goodsleep-generator__field-row--email">
						<div class="goodsleep-generator__field goodsleep-generator__field--email">
							<input type="email" name="email" placeholder="<?php esc_attr_e( 'Correo electrónico', 'goodsleep-elementor' ); ?>" required>
						</div>
						<p class="goodsleep-generator__email-note"><?php esc_html_e( 'te llegará el link del audio también por correo electrónico', 'goodsleep-elementor' ); ?></p>
					</div>
					<div class="goodsleep-generator__bubble">
						<textarea name="story_text" maxlength="500" placeholder="<?php esc_attr_e( 'Escribe tu historia', 'goodsleep-elementor' ); ?>" required></textarea>
						<p class="goodsleep-generator__phrase" data-dynamic-phrase><?php echo esc_html( sprintf( $settings['phrase_template'], '' ) ); ?></p>
						<div class="goodsleep-generator__counter"><span data-char-count>0</span>/500</div>
					</div>
					<div class="goodsleep-generator__controls">
						<select name="voice_id" required>
							<option value=""><?php esc_html_e( 'Voz', 'goodsleep-elementor' ); ?></option>
							<?php foreach ( goodsleep_get_allowed_voices() as $voice ) : ?>
								<option value="<?php echo esc_attr( $voice['id'] ); ?>" data-label="<?php echo esc_attr( $voice['label'] ); ?>"><?php echo esc_html( $voice['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
						<select name="track_id" required>
							<option value=""><?php esc_html_e( 'Música', 'goodsleep-elementor' ); ?></option>
							<?php foreach ( goodsleep_get_allowed_tracks() as $track ) : ?>
								<option value="<?php echo esc_attr( $track['id'] ); ?>" data-label="<?php echo esc_attr( $track['label'] ); ?>"><?php echo esc_html( $track['label'] ); ?></option>
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
				<div class="goodsleep-generator__result-card">
					<p class="goodsleep-generator__result-copy"><?php esc_html_e( 'Gracias por tu historia, se ha publicado correctamente y aquí la tienes para compartir.', 'goodsleep-elementor' ); ?></p>
					<audio controls preload="metadata" data-result-audio></audio>
					<div class="goodsleep-generator__result-actions">
						<a class="goodsleep-generator__action goodsleep-generator__action--download" href="#" download data-download-link><?php esc_html_e( 'Descargar', 'goodsleep-elementor' ); ?></a>
						<a class="goodsleep-generator__action goodsleep-generator__action--share" href="#" target="_blank" rel="noopener noreferrer" data-share-link><?php esc_html_e( 'Compartir', 'goodsleep-elementor' ); ?></a>
					</div>
				</div>
			</div>
			<div class="goodsleep-generator__feedback" data-feedback></div>
		</div>
		<?php
	}
}

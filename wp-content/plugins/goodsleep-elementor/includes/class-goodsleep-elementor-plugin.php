<?php
/**
 * Plugin principal Goodsleep Elementor.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Goodsleep_Elementor_Plugin {
	/**
	 * Instancia singleton.
	 *
	 * @var Goodsleep_Elementor_Plugin|null
	 */
	protected static $instance = null;

	/**
	 * Devuelve la instancia.
	 *
	 * @return Goodsleep_Elementor_Plugin
	 */
	public static function instance() {
		if ( null === static::$instance ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	/**
	 * Constructor.
	 */
	protected function __construct() {
		$this->includes();
		$this->boot();
	}

	/**
	 * Carga dependencias.
	 *
	 * @return void
	 */
	protected function includes() {
		require_once GOODSLEEP_ELEMENTOR_PATH . 'includes/class-goodsleep-elementor-settings.php';
		require_once GOODSLEEP_ELEMENTOR_PATH . 'includes/class-goodsleep-elementor-story-post-type.php';
		require_once GOODSLEEP_ELEMENTOR_PATH . 'includes/class-goodsleep-elementor-speechify-client.php';
		require_once GOODSLEEP_ELEMENTOR_PATH . 'includes/class-goodsleep-elementor-audio-mixer.php';
		require_once GOODSLEEP_ELEMENTOR_PATH . 'includes/class-goodsleep-elementor-mailjet-client.php';
		require_once GOODSLEEP_ELEMENTOR_PATH . 'includes/class-goodsleep-elementor-campaign-404.php';
		require_once GOODSLEEP_ELEMENTOR_PATH . 'includes/class-goodsleep-elementor-share-router.php';
		require_once GOODSLEEP_ELEMENTOR_PATH . 'includes/class-goodsleep-elementor-rest-controller.php';
		require_once GOODSLEEP_ELEMENTOR_PATH . 'includes/class-goodsleep-elementor-elementor.php';
	}

	/**
	 * Inicializa los modulos del plugin.
	 *
	 * @return void
	 */
	protected function boot() {
		new Goodsleep_Elementor_Settings();
		new Goodsleep_Elementor_Story_Post_Type();
		new Goodsleep_Elementor_Campaign_404();
		new Goodsleep_Elementor_Share_Router();
		new Goodsleep_Elementor_REST_Controller( new Goodsleep_Elementor_Speechify_Client(), new Goodsleep_Elementor_Audio_Mixer(), new Goodsleep_Elementor_Mailjet_Client() );
		new Goodsleep_Elementor_Elementor();

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'register_admin_assets' ) );
		register_activation_hook( GOODSLEEP_ELEMENTOR_FILE, array( __CLASS__, 'activate' ) );
	}

	/**
	 * Carga traducciones.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'goodsleep-elementor', false, dirname( plugin_basename( GOODSLEEP_ELEMENTOR_PATH . 'goodsleep-elementor.php' ) ) . '/languages' );
	}

	/**
	 * Registra assets frontend.
	 *
	 * @return void
	 */
	public function register_assets() {
		wp_register_style( 'goodsleep-elementor-frontend', GOODSLEEP_ELEMENTOR_URL . 'assets/css/frontend.css', array(), GOODSLEEP_ELEMENTOR_VERSION );
		wp_register_script( 'goodsleep-elementor-frontend', GOODSLEEP_ELEMENTOR_URL . 'assets/js/frontend.js', array(), GOODSLEEP_ELEMENTOR_VERSION, true );

		wp_localize_script(
			'goodsleep-elementor-frontend',
			'goodsleepElementor',
			array(
				'restUrl'          => esc_url_raw( rest_url( 'goodsleep/v1/' ) ),
				'nonce'            => wp_create_nonce( 'wp_rest' ),
				'voices'           => goodsleep_get_allowed_voices(),
				'tracks'           => goodsleep_get_allowed_tracks(),
				'termsText'        => goodsleep_get_setting( 'terms_text', 'Acepto terminos y condiciones' ),
				'termsUrl'         => esc_url_raw( goodsleep_get_setting( 'terms_url', '' ) ),
				'whatsappTemplate' => goodsleep_get_setting( 'whatsapp_share_text', '' ),
			)
		);
	}

	/**
	 * Registra assets de admin.
	 *
	 * @param string $hook_suffix Pantalla actual.
	 * @return void
	 */
	public function register_admin_assets( $hook_suffix ) {
		if ( false === strpos( $hook_suffix, 'goodsleep' ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'goodsleep-elementor-admin', GOODSLEEP_ELEMENTOR_URL . 'assets/css/admin.css', array(), GOODSLEEP_ELEMENTOR_VERSION );
		wp_enqueue_script( 'goodsleep-elementor-admin', GOODSLEEP_ELEMENTOR_URL . 'assets/js/admin.js', array(), GOODSLEEP_ELEMENTOR_VERSION, true );
		wp_localize_script(
			'goodsleep-elementor-admin',
			'goodsleepAdmin',
			array(
				'restUrl'          => esc_url_raw( rest_url( 'goodsleep/v1/' ) ),
				'nonce'            => wp_create_nonce( 'wp_rest' ),
				'trackTitle'       => __( 'Seleccionar track de audio', 'goodsleep-elementor' ),
				'trackButton'      => __( 'Usar este audio', 'goodsleep-elementor' ),
				'addTrackLabel'    => __( 'Añadir track', 'goodsleep-elementor' ),
				'removeTrackLabel'   => __( 'Eliminar', 'goodsleep-elementor' ),
				'confirmRemoveTrack' => __( '¿Realmente deseas eliminar este track?', 'goodsleep-elementor' ),
			)
		);
	}

	/**
	 * Acciones de activacion.
	 *
	 * @return void
	 */
	public static function activate() {
		$post_type = new Goodsleep_Elementor_Story_Post_Type();
		$post_type->register();

		$router = new Goodsleep_Elementor_Share_Router();
		$router->register();

		flush_rewrite_rules();
	}
}

<?php
/**
 * Helpers generales de Goodsleep Elementor.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Devuelve la configuracion del plugin con defaults seguros.
 *
 * @return array<string,mixed>
 */
function goodsleep_get_settings() {
	$defaults = array(
		'speechify_api_key'       => '',
		'speechify_base_url'      => 'https://api.sws.speechify.com',
		'speechify_audio_path'    => '/v1/audio/speech',
		'speechify_voices_path'   => '/v1/voices',
		'mailjet_api_key'         => '',
		'mailjet_secret_key'      => '',
		'mailjet_from_email'      => '',
		'mailjet_from_name'       => 'Goodsleep',
		'mailjet_reply_to_email'  => '',
		'mailjet_reply_to_name'   => '',
		'mailjet_monitor_bcc'     => '',
		'whatsapp_share_text'     => 'Nada le quita el sueno a %s. Escucha esta historia: %s',
		'terms_text'              => 'Acepto terminos y condiciones',
		'terms_url'               => '',
		'voice_language_whitelist' => array(),
		'voice_whitelist'         => array(),
		'track_whitelist'         => array(),
		'tracks_catalog'          => array(),
	);

	$settings = get_option( 'goodsleep_elementor_settings', array() );
	$settings = is_array( $settings ) ? $settings : array();

	return wp_parse_args( $settings, $defaults );
}

/**
 * Devuelve una opcion puntual del plugin.
 *
 * @param string $key     Clave del ajuste.
 * @param mixed  $default Valor por defecto.
 * @return mixed
 */
function goodsleep_get_setting( $key, $default = '' ) {
	$settings = goodsleep_get_settings();

	return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
}

/**
 * Normaliza un correo y convierte dominios IDN a punycode cuando aplica.
 *
 * @param string $email Correo base.
 * @return string
 */
function goodsleep_normalize_email( $email ) {
	$email = trim( (string) $email );

	if ( '' === $email || false === strpos( $email, '@' ) ) {
		return sanitize_email( $email );
	}

	list( $local_part, $domain_part ) = array_pad( explode( '@', $email, 2 ), 2, '' );
	$local_part  = trim( $local_part );
	$domain_part = trim( $domain_part );

	if ( '' !== $domain_part && preg_match( '/[^\x20-\x7E]/', $domain_part ) && class_exists( '\WpOrg\Requests\IdnaEncoder' ) ) {
		try {
			$domain_part = \WpOrg\Requests\IdnaEncoder::encode( $domain_part );
		} catch ( Exception $exception ) {
			$domain_part = trim( $domain_part );
		}
	}

	return sanitize_email( $local_part . '@' . $domain_part );
}

/**
 * Devuelve una lista saneada de correos separados por coma o salto de linea.
 *
 * @param string $value Texto base.
 * @return array<int,string>
 */
function goodsleep_parse_email_list( $value ) {
	$emails = preg_split( '/[\r\n,;]+/', (string) $value );
	$emails = array_filter( array_map( 'goodsleep_normalize_email', (array) $emails ) );

	return array_values( array_unique( $emails ) );
}

/**
 * Genera una huella ligera del visitante.
 *
 * @return string
 */
function goodsleep_get_client_fingerprint() {
	$parts = array(
		isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '',
		isset( $_SERVER['HTTP_USER_AGENT'] ) ? wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '',
		isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ? wp_unslash( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) : '',
	);

	return wp_hash( implode( '|', $parts ) );
}

/**
 * Normaliza un slug corto.
 *
 * @param string $value Texto base.
 * @return string
 */
function goodsleep_normalize_slug( $value ) {
	$value = sanitize_title( $value );

	if ( '' === $value ) {
		$value = 'historia';
	}

	return $value;
}

/**
 * Sanitiza el nombre visible para la historia.
 *
 * @param string $name Nombre ingresado.
 * @return string
 */
function goodsleep_sanitize_story_name( $name ) {
	$name = preg_replace( '/\s+/', '', (string) $name );
	$name = substr( $name, 0, 15 );

	return sanitize_text_field( $name );
}

/**
 * Determina si el usuario ya voto hoy por una historia.
 *
 * @param int $story_id ID de historia.
 * @return bool
 */
function goodsleep_has_voted_today( $story_id ) {
	$cookie_key  = 'goodsleep_vote_' . absint( $story_id );
	$fingerprint = goodsleep_get_client_fingerprint();
	$stored_hash = isset( $_COOKIE[ $cookie_key ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ $cookie_key ] ) ) : '';
	$today       = gmdate( 'Y-m-d' );

	return hash_equals( wp_hash( $fingerprint . '|' . $today ), $stored_hash );
}

/**
 * Marca el voto del dia para una historia.
 *
 * @param int $story_id ID de historia.
 * @return void
 */
function goodsleep_set_vote_cookie( $story_id ) {
	$cookie_key = 'goodsleep_vote_' . absint( $story_id );
	$value      = wp_hash( goodsleep_get_client_fingerprint() . '|' . gmdate( 'Y-m-d' ) );

	setcookie( $cookie_key, $value, time() + DAY_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
	$_COOKIE[ $cookie_key ] = $value;
}

/**
 * Devuelve si una historia esta marcada como favorita.
 *
 * @param int $story_id ID de historia.
 * @return bool
 */
function goodsleep_is_favorite_story( $story_id ) {
	$favorites = isset( $_COOKIE['goodsleep_favorites'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['goodsleep_favorites'] ) ) : '';
	$favorites = $favorites ? explode( ',', $favorites ) : array();

	return in_array( (string) absint( $story_id ), $favorites, true );
}

/**
 * Guarda la cookie de favoritos.
 *
 * @param array<int|string> $favorites IDs favoritos.
 * @return void
 */
function goodsleep_store_favorites_cookie( $favorites ) {
	$favorites = array_filter( array_map( 'absint', (array) $favorites ) );
	$value     = implode( ',', $favorites );

	setcookie( 'goodsleep_favorites', $value, time() + MONTH_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
	$_COOKIE['goodsleep_favorites'] = $value;
}

/**
 * Devuelve la URL corta publica de una historia.
 *
 * @param int $story_id ID de historia.
 * @return string
 */
function goodsleep_get_story_share_url( $story_id ) {
	$slug = get_post_meta( $story_id, '_goodsleep_short_slug', true );

	if ( ! $slug ) {
		$story_name = (string) get_post_meta( $story_id, '_goodsleep_story_name', true );
		$slug       = goodsleep_normalize_slug( $story_name ? $story_name : get_the_title( $story_id ) ) . '-' . $story_id;
		update_post_meta( $story_id, '_goodsleep_short_slug', $slug );
	}

	return goodsleep_humanize_share_url( home_url( '/h/' . rawurlencode( $slug ) . '/' ) );
}

/**
 * Devuelve una URL con host legible para compartir cuando el dominio es IDN.
 *
 * @param string $url URL base.
 * @return string
 */
function goodsleep_humanize_share_url( $url ) {
	$parts = wp_parse_url( (string) $url );

	if ( empty( $parts['host'] ) ) {
		return (string) $url;
	}

	$host = (string) $parts['host'];
	if ( function_exists( 'idn_to_utf8' ) && false !== strpos( $host, 'xn--' ) ) {
		$decoded = idn_to_utf8( $host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46 );
		if ( $decoded ) {
			$host = $decoded;
		}
	}

	$rebuilt = '';
	if ( ! empty( $parts['scheme'] ) ) {
		$rebuilt .= $parts['scheme'] . '://';
	}

	if ( ! empty( $parts['user'] ) ) {
		$rebuilt .= $parts['user'];
		if ( ! empty( $parts['pass'] ) ) {
			$rebuilt .= ':' . $parts['pass'];
		}
		$rebuilt .= '@';
	}

	$rebuilt .= $host;

	if ( ! empty( $parts['port'] ) ) {
		$rebuilt .= ':' . $parts['port'];
	}

	$rebuilt .= isset( $parts['path'] ) ? $parts['path'] : '';

	if ( isset( $parts['query'] ) ) {
		$rebuilt .= '?' . $parts['query'];
	}

	if ( isset( $parts['fragment'] ) ) {
		$rebuilt .= '#' . $parts['fragment'];
	}

	return $rebuilt ? $rebuilt : (string) $url;
}

/**
 * Devuelve el catalogo cacheado de voces.
 *
 * @return array<int,array<string,mixed>>
 */
function goodsleep_get_cached_voices() {
	$voices = get_option( 'goodsleep_elementor_voice_catalog', array() );

	return is_array( $voices ) ? $voices : array();
}

/**
 * Devuelve el catalogo cacheado de tracks.
 *
 * @return array<int,array<string,mixed>>
 */
function goodsleep_get_cached_tracks() {
	$tracks = goodsleep_get_setting( 'tracks_catalog', array() );

	return is_array( $tracks ) ? $tracks : array();
}

/**
 * Devuelve un track cacheado por ID.
 *
 * @param string $track_id ID del track.
 * @return array<string,mixed>|null
 */
function goodsleep_get_track_by_id( $track_id ) {
	$track_id = sanitize_text_field( (string) $track_id );

	if ( '' === $track_id ) {
		return null;
	}

	foreach ( goodsleep_get_cached_tracks() as $track ) {
		if ( isset( $track['id'] ) && $track_id === $track['id'] ) {
			return $track;
		}
	}

	return null;
}

/**
 * Devuelve las voces habilitadas por whitelist.
 *
 * @return array<int,array<string,mixed>>
 */
function goodsleep_get_allowed_voices() {
	$catalog            = goodsleep_get_cached_voices();
	$whitelist          = (array) goodsleep_get_setting( 'voice_whitelist', array() );
	$language_whitelist = array_values( array_filter( array_map( 'strtolower', (array) goodsleep_get_setting( 'voice_language_whitelist', array() ) ) ) );

	if ( empty( $whitelist ) ) {
		if ( empty( $language_whitelist ) ) {
			return $catalog;
		}

		return array_values(
			array_filter(
				$catalog,
				static function ( $voice ) use ( $language_whitelist ) {
					$voice_language = '';

					if ( ! empty( $voice['language'] ) && is_string( $voice['language'] ) ) {
						$voice_language = strtolower( $voice['language'] );
					} elseif ( ! empty( $voice['locale'] ) && is_string( $voice['locale'] ) ) {
						$voice_language = strtolower( $voice['locale'] );
					}

					return '' !== $voice_language && in_array( $voice_language, $language_whitelist, true );
				}
			)
		);
	}

	return array_values(
		array_filter(
			$catalog,
			static function ( $voice ) use ( $whitelist ) {
				return isset( $voice['id'] ) && in_array( $voice['id'], $whitelist, true );
			}
		)
	);
}

/**
 * Devuelve los idiomas habilitados por filtro de voces.
 *
 * @return array<int,string>
 */
function goodsleep_get_allowed_voice_languages() {
	$languages = (array) goodsleep_get_setting( 'voice_language_whitelist', array() );

	return array_values( array_filter( array_map( 'sanitize_text_field', $languages ) ) );
}

/**
 * Devuelve los tracks habilitados por whitelist.
 *
 * @return array<int,array<string,mixed>>
 */
function goodsleep_get_allowed_tracks() {
	$catalog   = goodsleep_get_cached_tracks();
	$whitelist = (array) goodsleep_get_setting( 'track_whitelist', array() );

	if ( empty( $whitelist ) ) {
		return $catalog;
	}

	return array_values(
		array_filter(
			$catalog,
			static function ( $track ) use ( $whitelist ) {
				return isset( $track['id'] ) && in_array( $track['id'], $whitelist, true );
			}
		)
	);
}

/**
 * Devuelve las emociones soportadas por Speechify para SSML.
 *
 * @return array<string,string>
 */
function goodsleep_get_speechify_emotions() {
	return array(
		'angry'     => 'Angry',
		'assertive' => 'Assertive',
		'bright'    => 'Bright',
		'calm'      => 'Calm',
		'cheerful'  => 'Cheerful',
		'direct'    => 'Direct',
		'energetic' => 'Energetic',
		'fearful'   => 'Fearful',
		'relaxed'   => 'Relaxed',
		'sad'       => 'Sad',
		'surprised' => 'Surprised',
		'terrified' => 'Terrified',
		'warm'      => 'Warm',
	);
}

/**
 * Sanitiza una emocion soportada por Speechify.
 *
 * @param string $emotion Emocion solicitada.
 * @param string $default Emocion por defecto.
 * @return string
 */
function goodsleep_sanitize_speechify_emotion( $emotion, $default = 'cheerful' ) {
	$emotion  = sanitize_key( (string) $emotion );
	$emotions = goodsleep_get_speechify_emotions();

	if ( isset( $emotions[ $emotion ] ) ) {
		return $emotion;
	}

	return isset( $emotions[ $default ] ) ? $default : 'cheerful';
}

/**
 * Limita la generacion de historias por visitante.
 *
 * @return true|WP_Error
 */
function goodsleep_assert_generation_rate_limit() {
	$fingerprint = goodsleep_get_client_fingerprint();
	$transient   = 'goodsleep_rate_' . md5( $fingerprint );

	if ( get_transient( $transient ) ) {
		return new WP_Error( 'goodsleep_rate_limited', __( 'Espera un momento antes de generar otra historia.', 'goodsleep-elementor' ), array( 'status' => 429 ) );
	}

	set_transient( $transient, 1, MINUTE_IN_SECONDS );

	return true;
}

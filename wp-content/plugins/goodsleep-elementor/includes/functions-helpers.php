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
		'openai_api_key'          => '',
		'openai_base_url'         => 'https://api.openai.com/v1',
		'openai_video_model'      => 'sora-2',
		'openai_video_submit_path'=> '/videos',
		'openai_video_status_path'=> '/videos/%s',
		'openai_video_content_path'=> '/videos/%s/content',
		'video_resolution'        => '720p',
		'video_aspect_ratio'      => '9:16',
		'video_duration'          => 12,
		'video_poll_interval'     => 5,
		'video_poll_attempts'     => 24,
		'video_prompt_style'      => goodsleep_get_default_video_prompt_style(),
		'video_music_enabled'     => 1,
		'video_public_only'       => 0,
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
	$value    = array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;

	if ( 'video_prompt_style' === $key ) {
		return goodsleep_normalize_video_prompt_style( $value );
	}

	return $value;
}

/**
 * Devuelve el prompt base recomendado para Sora 2.
 *
 * @return string
 */
function goodsleep_get_default_video_prompt_style() {
	return 'Video vertical 9:16, estilo cinematografico publicitario, realista y de alto impacto emocional. Estetica premium, actuacion natural y escenas visualmente claras, faciles de entender y conectadas con la historia narrada. La pieza debe representar de forma visual los hechos principales del relato, manteniendo coherencia entre narracion, acciones, emociones y entorno. No usar texto en pantalla, subtitulos ni elementos graficos sobreimpresos. Incluir audio sincronizado con locucion clara en espanol latino, con tono narrativo firme, natural y envolvente. La frase final obligatoria del relato es exactamente esta: "[FRASE_FINAL]". Esa frase debe definir el cierre visual del video. En el plano final, la persona protagonista debe aparecer durmiendo placidamente, en calma absoluta, como si nada le afectara.';
}

/**
 * Indica si el prompt base corresponde al sesgo legacy previo a Sora 2.
 *
 * @param string $style Prompt base configurado.
 * @return bool
 */
function goodsleep_is_legacy_video_prompt_style( $style ) {
	$style = trim( preg_replace( '/\s+/', ' ', (string) $style ) );

	return in_array(
		$style,
		array(
			'Video vertical 9:16 cinematografico, nocturno, dramatico, inspirado en el tono de la campana Nada les quita el sueno. Visuales realistas, alto contraste y atmosfera publicitaria premium. Sin texto en pantalla ni subtitulos incrustados. La pieza debe incluir audio sincronizado y una locucion clara en espanol latino.',
			'Video vertical 9:16 cinematografico, inspirado en el tono de la campana Nada les quita el sueno. Visuales realistas, lenguaje publicitario premium y tono emocional humano. Evita asumir de antemano que es de noche o que ocurre en un entorno especifico: cada escena debe surgir de la historia narrada. Sin texto en pantalla ni subtitulos incrustados. La pieza debe incluir audio sincronizado y una locucion clara en espanol latino.',
		),
		true
	);
}

/**
 * Normaliza el prompt base de video para evitar sesgos no deseados.
 *
 * @param string $style Prompt base configurado.
 * @return string
 */
function goodsleep_normalize_video_prompt_style( $style ) {
	$style = trim( (string) $style );

	if ( '' === $style || goodsleep_is_legacy_video_prompt_style( $style ) ) {
		return goodsleep_get_default_video_prompt_style();
	}

	return $style;
}

/**
 * Escapa texto para citarlo literalmente dentro del prompt.
 *
 * @param string $text Texto a citar.
 * @return string
 */
function goodsleep_escape_prompt_literal( $text ) {
	return str_replace(
		array( '\\', '"' ),
		array( '\\\\', '\"' ),
		trim( preg_replace( '/\s+/', ' ', (string) $text ) )
	);
}

/**
 * Reemplaza placeholders soportados dentro del prompt base.
 *
 * @param string $template       Prompt base configurado.
 * @param string $closing_phrase Frase final obligatoria.
 * @return string
 */
function goodsleep_render_video_prompt_template( $template, $closing_phrase = '' ) {
	$template       = trim( (string) $template );
	$closing_phrase = goodsleep_escape_prompt_literal( $closing_phrase );

	if ( '' === $template ) {
		$template = goodsleep_get_default_video_prompt_style();
	}

	return str_replace(
		array( '[FRASE_FINAL]' ),
		array( $closing_phrase ),
		$template
	);
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
 * Devuelve la primera voz habilitada como fallback operativo.
 *
 * @return array<string,mixed>|null
 */
function goodsleep_get_default_voice() {
	$voices = goodsleep_get_allowed_voices();

	if ( empty( $voices ) ) {
		return null;
	}

	return is_array( $voices[0] ) ? $voices[0] : null;
}

/**
 * Devuelve el primer track habilitado como fallback operativo.
 *
 * @return array<string,mixed>|null
 */
function goodsleep_get_default_track() {
	$tracks = goodsleep_get_allowed_tracks();

	if ( empty( $tracks ) ) {
		return null;
	}

	return is_array( $tracks[0] ) ? $tracks[0] : null;
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

/**
 * Devuelve si el flujo publico debe mostrar solo video.
 *
 * @return bool
 */
function goodsleep_is_video_public_only() {
	return ! empty( goodsleep_get_setting( 'video_public_only', 0 ) );
}

/**
 * Devuelve el ID del adjunto de video de una historia.
 *
 * @param int $story_id ID del post.
 * @return int
 */
function goodsleep_get_story_video_id( $story_id ) {
	return (int) get_post_meta( (int) $story_id, '_goodsleep_story_video_id', true );
}

/**
 * Devuelve la URL del video de una historia.
 *
 * @param int $story_id ID del post.
 * @return string
 */
function goodsleep_get_story_video_url( $story_id ) {
	$video_id = goodsleep_get_story_video_id( $story_id );

	return $video_id ? (string) wp_get_attachment_url( $video_id ) : '';
}

/**
 * Devuelve el ID del audio legacy de una historia.
 *
 * @param int $story_id ID del post.
 * @return int
 */
function goodsleep_get_story_audio_id( $story_id ) {
	return (int) get_post_meta( (int) $story_id, '_goodsleep_story_audio_id', true );
}

/**
 * Devuelve la URL del audio legacy de una historia.
 *
 * @param int $story_id ID del post.
 * @return string
 */
function goodsleep_get_story_audio_url( $story_id ) {
	$audio_id = goodsleep_get_story_audio_id( $story_id );

	return $audio_id ? (string) wp_get_attachment_url( $audio_id ) : '';
}

/**
 * Devuelve el medio publico principal de una historia.
 *
 * @param int $story_id ID del post.
 * @return array<string,mixed>
 */
function goodsleep_get_story_primary_media( $story_id ) {
	$story_id  = (int) $story_id;
	$video_url = goodsleep_get_story_video_url( $story_id );
	$audio_url = goodsleep_get_story_audio_url( $story_id );

	if ( '' !== $video_url ) {
		return array(
			'type'          => 'video',
			'url'           => $video_url,
			'download_url'  => $video_url,
			'attachment_id' => goodsleep_get_story_video_id( $story_id ),
			'is_ready'      => true,
		);
	}

	if ( ! goodsleep_is_video_public_only() && '' !== $audio_url ) {
		return array(
			'type'          => 'audio',
			'url'           => $audio_url,
			'download_url'  => $audio_url,
			'attachment_id' => goodsleep_get_story_audio_id( $story_id ),
			'is_ready'      => true,
		);
	}

	return array(
		'type'          => '',
		'url'           => '',
		'download_url'  => '',
		'attachment_id' => 0,
		'is_ready'      => false,
	);
}

/**
 * Devuelve el texto combinado de una historia.
 *
 * @param int $story_id ID de la historia.
 * @return string
 */
function goodsleep_get_story_combined_text( $story_id ) {
	$story_id = (int) $story_id;
	$combined = (string) get_post_meta( $story_id, '_goodsleep_story_combined', true );

	if ( '' !== trim( $combined ) ) {
		return trim( $combined );
	}

	$story_text = (string) get_post_meta( $story_id, '_goodsleep_story_text', true );
	$phrase     = (string) get_post_meta( $story_id, '_goodsleep_story_phrase', true );

	return trim( trim( $story_text ) . "\n" . trim( $phrase ) );
}

/**
 * Devuelve un prompt de video optimizado para Sora 2.
 *
 * @param string $story_text     Cuerpo principal de la historia.
 * @param string $story_name     Nombre visible.
 * @param string $closing_phrase Frase final obligatoria.
 * @return string
 */
function goodsleep_build_video_prompt( $story_text, $story_name = '', $closing_phrase = '' ) {
	$style          = goodsleep_render_video_prompt_template( (string) goodsleep_get_setting( 'video_prompt_style', '' ), $closing_phrase );
	$story_name     = trim( wp_strip_all_tags( (string) $story_name ) );
	$story_text     = trim( wp_strip_all_tags( (string) $story_text ) );

	$parts = array_filter(
		array(
			$style,
			'' !== $story_name ? 'Personaje central: ' . $story_name . '.' : '',
			'' !== $story_text ? 'La narracion principal en espanol latino debe seguir esta historia: ' . $story_text : '',
		)
	);

	return implode( ' ', $parts );
}

/**
 * Estima cuantas escenas necesita un texto narrado.
 *
 * @param string $combined_text Texto narrativo.
 * @return int
 */
function goodsleep_estimate_scene_count( $combined_text ) {
	$combined_text = trim( (string) $combined_text );
	$length        = function_exists( 'mb_strlen' ) ? mb_strlen( $combined_text ) : strlen( $combined_text );

	if ( $length <= 180 ) {
		return 1;
	}

	if ( $length <= 340 ) {
		return 2;
	}

	if ( $length <= 520 ) {
		return 3;
	}

	return 4;
}

/**
 * Divide un texto narrativo en escenas pequenas para video.
 *
 * @param string $combined_text Texto narrativo.
 * @return array<int,string>
 */
function goodsleep_split_story_into_scenes( $combined_text ) {
	$combined_text = trim( preg_replace( '/\s+/', ' ', (string) $combined_text ) );

	if ( '' === $combined_text ) {
		return array();
	}

	$target_scenes = goodsleep_estimate_scene_count( $combined_text );
	$sentences     = preg_split( '/(?<=[\.\!\?])\s+/u', $combined_text );
	$sentences     = array_values( array_filter( array_map( 'trim', (array) $sentences ) ) );

	if ( count( $sentences ) <= 1 ) {
		return array( $combined_text );
	}

	$scenes = array_fill( 0, $target_scenes, '' );
	foreach ( $sentences as $index => $sentence ) {
		$scene_index = $index % $target_scenes;
		$scenes[ $scene_index ] = trim( $scenes[ $scene_index ] . ' ' . $sentence );
	}

	return array_values( array_filter( $scenes ) );
}

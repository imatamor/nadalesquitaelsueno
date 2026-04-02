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
		'video_provider'          => 'openai',
		'openai_api_key'          => '',
		'openai_base_url'         => 'https://api.openai.com/v1',
		'openai_video_model'      => 'sora-2',
		'openai_video_submit_path'=> '/videos',
		'openai_video_remix_path' => '/videos/%s/remix',
		'openai_video_status_path'=> '/videos/%s',
		'openai_video_content_path'=> '/videos/%s/content',
		'openai_webhook_secret'   => '',
		'kling_access_key'        => '',
		'kling_secret_key'        => '',
		'kling_base_url'          => 'https://api-singapore.klingai.com',
		'kling_video_model'       => 'kling-v3-omni',
		'kling_video_mode'        => 'std',
		'kling_video_sound'       => 'on',
		'kling_negative_prompt'   => '',
		'kling_text_submit_path'  => '/v1/videos/text2video',
		'kling_text_status_path'  => '/v1/videos/text2video/%s',
		'kling_extend_submit_path'=> '/v1/videos/video-extend',
		'kling_extend_status_path'=> '/v1/videos/video-extend/%s',
		'kling_webhook_secret'    => '',
		'video_resolution'        => '720p',
		'video_aspect_ratio'      => '9:16',
		'video_duration'          => 12,
		'video_poll_interval'     => 5,
		'video_poll_attempts'     => 24,
		'video_prompt_style'      => goodsleep_get_default_video_prompt_style(),
		'video_clip_1_prompt_addition' => '',
		'video_clip_2_prompt_addition' => '',
		'video_product_reference' => array(
			'attachment_id' => 0,
			'url'           => '',
		),
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
 * Devuelve el proveedor activo de video.
 *
 * @return string
 */
function goodsleep_get_video_provider() {
	$provider = sanitize_key( (string) goodsleep_get_setting( 'video_provider', 'openai' ) );

	return in_array( $provider, array( 'openai', 'kling' ), true ) ? $provider : 'openai';
}

/**
 * Devuelve el prompt base recomendado para la generacion de video.
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

	if ( '' === $closing_phrase ) {
		$template = preg_replace( '/\s*La frase final obligatoria del relato es exactamente esta:\s*"\[FRASE_FINAL\]"\.\s*/u', ' ', $template );
		$template = preg_replace( '/\s*Esa frase[^.]*\.\s*/u', ' ', $template );
	}

	return str_replace(
		array( '[FRASE_FINAL]' ),
		array( $closing_phrase ),
		$template
	);
}

/**
 * Devuelve una version compacta del prompt base para proveedores con limites bajos.
 *
 * Kling rechaza prompts largos por encima de 2500 caracteres, asi que se usa una
 * instruccion equivalente, mas corta y enfocada en continuidad narrativa.
 *
 * @param string $closing_phrase Frase final obligatoria.
 * @return string
 */
function goodsleep_get_compact_video_prompt_style( $closing_phrase = '' ) {
	$closing_phrase = trim( wp_strip_all_tags( (string) $closing_phrase ) );

	$parts = array(
		'Video vertical 9:16, cinematografico publicitario, realista y emocional.',
		'Escenas claras y coherentes con la historia.',
		'La persona A sufre o cuenta el problema; la persona B lo provoca.',
		'No inventar hechos, acciones, danos, dialogos u objetos que no existan en la historia.',
		'Mantener exactamente la misma voz narradora y los mismos personajes entre clips.',
		'' !== $closing_phrase ? 'La frase final obligatoria es exactamente esta: "' . goodsleep_escape_prompt_literal( $closing_phrase ) . '". Debe corresponder visualmente a la persona B, nunca a la persona A.' : '',
		'Audio sincronizado con locucion clara en espanol latino ecuatoriano, tono natural y envolvente.',
	);

	return trim( preg_replace( '/\s+/', ' ', implode( ' ', array_filter( $parts ) ) ) );
}

/**
 * Convierte numeros pequenos a texto para mejorar la locucion de Kling.
 *
 * @param int $number Numero entero positivo.
 * @return string
 */
function goodsleep_spanish_number_to_words( $number ) {
	$number = (int) $number;

	$units = array(
		0  => 'cero',
		1  => 'uno',
		2  => 'dos',
		3  => 'tres',
		4  => 'cuatro',
		5  => 'cinco',
		6  => 'seis',
		7  => 'siete',
		8  => 'ocho',
		9  => 'nueve',
		10 => 'diez',
		11 => 'once',
		12 => 'doce',
		13 => 'trece',
		14 => 'catorce',
		15 => 'quince',
		16 => 'dieciseis',
		17 => 'diecisiete',
		18 => 'dieciocho',
		19 => 'diecinueve',
		20 => 'veinte',
		21 => 'veintiuno',
		22 => 'veintidos',
		23 => 'veintitres',
		24 => 'veinticuatro',
		25 => 'veinticinco',
		26 => 'veintiseis',
		27 => 'veintisiete',
		28 => 'veintiocho',
		29 => 'veintinueve',
	);

	if ( isset( $units[ $number ] ) ) {
		return $units[ $number ];
	}

	$tens = array(
		30 => 'treinta',
		40 => 'cuarenta',
		50 => 'cincuenta',
		60 => 'sesenta',
		70 => 'setenta',
		80 => 'ochenta',
		90 => 'noventa',
	);

	if ( isset( $tens[ $number ] ) ) {
		return $tens[ $number ];
	}

	if ( $number > 30 && $number < 100 ) {
		$ten  = (int) floor( $number / 10 ) * 10;
		$unit = $number % 10;

		if ( isset( $tens[ $ten ] ) && isset( $units[ $unit ] ) ) {
			return $tens[ $ten ] . ' y ' . $units[ $unit ];
		}
	}

	return (string) $number;
}

/**
 * Normaliza texto narrado para mejorar pronunciacion en Kling.
 *
 * @param string $text Texto base.
 * @return string
 */
function goodsleep_normalize_kling_narration_text( $text ) {
	$text = trim( wp_strip_all_tags( (string) $text ) );
	if ( '' === $text ) {
		return '';
	}

	$text = str_replace(
		array( '“', '”', '’', '‘', '´', '`' ),
		array( '"', '"', "'", "'", "'", "'" ),
		$text
	);

	$text = preg_replace_callback(
		'/\b\d+\b/u',
		function ( $matches ) {
			$value = isset( $matches[0] ) ? (int) $matches[0] : 0;
			if ( $value < 0 || $value > 99 ) {
				return (string) $matches[0];
			}

			return goodsleep_spanish_number_to_words( $value );
		},
		$text
	);

	return trim( preg_replace( '/\s+/', ' ', $text ) );
}

/**
 * Compacta prompts para proveedores con limites estrictos de longitud.
 *
 * @param array<int,string> $parts          Bloques del prompt.
 * @param string            $closing_phrase Frase final obligatoria.
 * @param int               $max_length     Longitud maxima deseada.
 * @return string
 */
function goodsleep_compact_video_prompt_for_provider( $parts, $closing_phrase = '', $max_length = 2400 ) {
	$provider = goodsleep_get_video_provider();
	$parts    = array_values( array_filter( array_map( 'trim', (array) $parts ) ) );
	$prompt   = trim( preg_replace( '/\s+/', ' ', implode( ' ', $parts ) ) );

	if ( 'kling' !== $provider ) {
		return $prompt;
	}

	$length_fn = function_exists( 'mb_strlen' ) ? 'mb_strlen' : 'strlen';
	if ( $length_fn( $prompt ) <= $max_length ) {
		return $prompt;
	}

	if ( ! empty( $parts ) ) {
		$parts[0] = goodsleep_get_compact_video_prompt_style( $closing_phrase );
	}

	$prompt = trim( preg_replace( '/\s+/', ' ', implode( ' ', array_filter( $parts ) ) ) );
	if ( $length_fn( $prompt ) <= $max_length ) {
		return $prompt;
	}

	$last_index = count( $parts ) - 1;
	if ( $last_index >= 0 ) {
		$reserved  = trim( preg_replace( '/\s+/', ' ', implode( ' ', array_slice( $parts, 0, $last_index ) ) ) );
		$reserved  = '' !== $reserved ? $reserved . ' ' : '';
		$available = max( 80, $max_length - $length_fn( $reserved ) );

		if ( function_exists( 'mb_substr' ) ) {
			$parts[ $last_index ] = trim( mb_substr( $parts[ $last_index ], 0, $available ) );
		} else {
			$parts[ $last_index ] = trim( substr( $parts[ $last_index ], 0, $available ) );
		}
	}

	return trim( preg_replace( '/\s+/', ' ', implode( ' ', array_filter( $parts ) ) ) );
}

/**
 * Construye un prompt compacto y estable para Kling.
 *
 * @param string $story_segment  Texto narrativo del clip.
 * @param string $story_name     Nombre visible de la persona B.
 * @param string $closing_phrase Frase final obligatoria.
 * @param int    $clip_index     Posicion del clip.
 * @param int    $clip_count     Total de clips.
 * @return string
 */
function goodsleep_build_kling_clip_prompt( $story_segment, $story_name = '', $closing_phrase = '', $clip_index = 0, $clip_count = 1 ) {
	$story_name    = trim( wp_strip_all_tags( (string) $story_name ) );
	$story_segment = goodsleep_normalize_kling_narration_text( $story_segment );
	$clip_index    = max( 0, (int) $clip_index );
	$clip_count    = max( 1, (int) $clip_count );
	$is_final_clip = $clip_index >= ( $clip_count - 1 );
	$is_multi_clip = $clip_count > 1;

	$parts = array(
		goodsleep_get_compact_video_prompt_style( $is_final_clip ? $closing_phrase : '' ),
		$is_multi_clip ? sprintf( 'Clip %1$d de %2$d.', $clip_index + 1, $clip_count ) : '',
		! $is_final_clip ? 'Representa solo la primera mitad del relato. No cierres aun la historia. Mantener continuidad exacta para el siguiente clip.' : 'Continua exactamente desde el clip anterior. Resuelve con calma la segunda mitad del relato y reserva el cierre para los ultimos segundos.',
		$is_multi_clip ? 'No incluyas producto, packshot ni frasco en el cierre final. El ultimo plano debe concentrarse solo en la persona B durmiendo.' : '',
		$is_final_clip && '' !== $story_name ? 'En el plano final, la persona B, identificada como ' . $story_name . ', debe aparecer durmiendo placidamente, en calma absoluta, como si nada le afectara.' : '',
		'' !== $story_name ? 'Personaje B: ' . $story_name . '.' : '',
		'' !== $story_segment ? 'Historia completa: ' . $story_segment : '',
	);

	return trim( preg_replace( '/\s+/', ' ', implode( ' ', array_filter( $parts ) ) ) );
}

/**
 * Devuelve el secret efectivo para callbacks del proveedor.
 *
 * Para Kling usamos un token propio en la URL del callback porque la
 * documentacion disponible no expone una firma estandar tipo OpenAI.
 *
 * @param string $provider Proveedor.
 * @return string
 */
function goodsleep_get_video_callback_secret( $provider = '' ) {
	$provider = $provider ? sanitize_key( (string) $provider ) : goodsleep_get_video_provider();

	if ( 'openai' === $provider ) {
		return trim( (string) goodsleep_get_setting( 'openai_webhook_secret', '' ) );
	}

	$secret = trim( (string) goodsleep_get_setting( 'kling_webhook_secret', '' ) );
	if ( '' !== $secret ) {
		return $secret;
	}

	return wp_hash( home_url( '/' ) . '|goodsleep-video-webhook|' . $provider );
}

/**
 * Construye la URL de callback del proveedor de video.
 *
 * @param string $provider Proveedor.
 * @return string
 */
function goodsleep_get_video_callback_url( $provider = '' ) {
	$provider = $provider ? sanitize_key( (string) $provider ) : goodsleep_get_video_provider();
	$url      = rest_url( 'goodsleep/v1/video-webhook' );

	return add_query_arg(
		array(
			'provider' => $provider,
			'token'    => goodsleep_get_video_callback_secret( $provider ),
		),
		$url
	);
}

/**
 * Normaliza la duracion enviada al proveedor activo.
 *
 * Kling trabaja por segundos discretos y la configuracion actual puede
 * venir heredada desde Sora con 12 segundos, asi que la traducimos al
 * valor soportado mas cercano.
 *
 * @param int|null $duration Duracion deseada.
 * @return int
 */
function goodsleep_get_provider_video_duration( $duration = null ) {
	$duration = null === $duration ? (int) goodsleep_get_setting( 'video_duration', 12 ) : (int) $duration;

	if ( 'kling' !== goodsleep_get_video_provider() ) {
		return max( 4, $duration );
	}

	if ( $duration <= 5 ) {
		return 5;
	}

	if ( $duration <= 10 ) {
		return 10;
	}

	return 15;
}

/**
 * Devuelve instrucciones adicionales por clip.
 *
 * @param int $clip_index Posicion del clip.
 * @param int $clip_count Total de clips.
 * @return string
 */
function goodsleep_get_video_clip_prompt_addition( $clip_index, $clip_count ) {
	$clip_index = max( 0, (int) $clip_index );
	$clip_count = max( 1, (int) $clip_count );

	if ( 1 === $clip_count || $clip_index >= ( $clip_count - 1 ) ) {
		return trim( (string) goodsleep_get_setting( 'video_clip_2_prompt_addition', '' ) );
	}

	return trim( (string) goodsleep_get_setting( 'video_clip_1_prompt_addition', '' ) );
}

/**
 * Devuelve la referencia de producto configurada para Sora.
 *
 * @return array<string,mixed>
 */
function goodsleep_get_video_product_reference() {
	$reference = goodsleep_get_setting( 'video_product_reference', array() );
	$reference = is_array( $reference ) ? $reference : array();
	$file_id   = ! empty( $reference['attachment_id'] ) ? absint( $reference['attachment_id'] ) : 0;
	$url       = ! empty( $reference['url'] ) ? esc_url_raw( (string) $reference['url'] ) : '';
	$path      = $file_id ? get_attached_file( $file_id ) : '';
	$mime_type = $file_id ? get_post_mime_type( $file_id ) : '';

	if ( ! $path || ! file_exists( $path ) ) {
		$path = '';
	}

	return array(
		'attachment_id' => $file_id,
		'url'           => $url,
		'path'          => $path,
		'mime_type'     => $mime_type ? $mime_type : 'image/jpeg',
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
	if ( 'kling' === goodsleep_get_video_provider() ) {
		return goodsleep_build_kling_clip_prompt( $story_text, $story_name, $closing_phrase, 0, 1 );
	}

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

	return goodsleep_compact_video_prompt_for_provider( $parts, $closing_phrase );
}

/**
 * Devuelve si una historia debe dividirse en dos clips.
 *
 * @param string $story_text Historia principal sin cierre.
 * @return bool
 */
function goodsleep_should_use_two_video_clips( $story_text ) {
	$story_text = trim( (string) $story_text );
	$length     = function_exists( 'mb_strlen' ) ? mb_strlen( $story_text ) : strlen( $story_text );

	return $length > 260;
}

/**
 * Divide una historia en bloques contiguos para varios clips.
 *
 * @param string $story_text Historia principal sin cierre.
 * @param int    $clip_count Numero de clips.
 * @return array<int,string>
 */
function goodsleep_split_story_for_video_clips( $story_text, $clip_count = 2 ) {
	$story_text = trim( preg_replace( '/\s+/', ' ', (string) $story_text ) );
	$clip_count = max( 1, (int) $clip_count );

	if ( '' === $story_text ) {
		return array();
	}

	if ( 1 === $clip_count ) {
		return array( $story_text );
	}

	$sentences = preg_split( '/(?<=[\.\!\?])\s+/u', $story_text );
	$sentences = array_values( array_filter( array_map( 'trim', (array) $sentences ) ) );

	if ( count( $sentences ) <= 1 ) {
		$midpoint = (int) ceil( strlen( $story_text ) / 2 );
		$first    = trim( substr( $story_text, 0, $midpoint ) );
		$second   = trim( substr( $story_text, $midpoint ) );

		return array_values( array_filter( array( $first, $second ) ) );
	}

	$total_length    = 0;
	$sentence_lengths = array();
	foreach ( $sentences as $sentence ) {
		$sentence_length    = function_exists( 'mb_strlen' ) ? mb_strlen( $sentence ) : strlen( $sentence );
		$sentence_lengths[] = $sentence_length;
		$total_length      += $sentence_length;
	}

	$target_length = max( 1, (int) ceil( $total_length / $clip_count ) );
	$clips         = array();
	$current_clip  = array();
	$current_size  = 0;

	foreach ( $sentences as $index => $sentence ) {
		$remaining_sentences = count( $sentences ) - $index;
		$remaining_slots     = $clip_count - count( $clips );

		if ( ! empty( $current_clip ) && $current_size >= $target_length && $remaining_sentences >= $remaining_slots ) {
			$clips[]      = trim( implode( ' ', $current_clip ) );
			$current_clip = array();
			$current_size = 0;
		}

		$current_clip[] = $sentence;
		$current_size  += $sentence_lengths[ $index ];
	}

	if ( ! empty( $current_clip ) ) {
		$clips[] = trim( implode( ' ', $current_clip ) );
	}

	return array_values( array_filter( $clips ) );
}

/**
 * Construye el prompt para un clip especifico dentro de una historia.
 *
 * @param string $story_segment   Texto del clip actual.
 * @param string $story_name      Nombre visible.
 * @param string $closing_phrase  Frase final obligatoria.
 * @param int    $clip_index      Posicion del clip.
 * @param int    $clip_count      Total de clips.
 * @return string
 */
function goodsleep_build_video_clip_prompt( $story_segment, $story_name = '', $closing_phrase = '', $clip_index = 0, $clip_count = 1 ) {
	if ( 'kling' === goodsleep_get_video_provider() ) {
		return goodsleep_build_kling_clip_prompt( $story_segment, $story_name, $closing_phrase, $clip_index, $clip_count );
	}

	$story_name    = trim( wp_strip_all_tags( (string) $story_name ) );
	$story_segment = trim( wp_strip_all_tags( (string) $story_segment ) );
	$clip_index    = max( 0, (int) $clip_index );
	$clip_count    = max( 1, (int) $clip_count );
	$is_final_clip = $clip_index >= ( $clip_count - 1 );
	$is_multi_clip = $clip_count > 1;
	$style         = goodsleep_render_video_prompt_template( (string) goodsleep_get_setting( 'video_prompt_style', '' ), $is_final_clip ? $closing_phrase : '' );
	$clip_addition = goodsleep_get_video_clip_prompt_addition( $clip_index, $clip_count );

	$parts = array_filter(
		array(
			$style,
			$clip_count > 1 ? sprintf( 'Este es el clip %1$d de %2$d de una misma historia.', $clip_index + 1, $clip_count ) : '',
			! $is_final_clip ? 'No cierres todavia la historia. No incluyas aun la frase final obligatoria ni el plano final de la persona B durmiendo; ese cierre pertenece exclusivamente al ultimo clip. Desarrolla esta parte con un ritmo pausado y respirado, dejando espacio para acciones y reacciones visuales, sin comprimir toda la narracion en estos 12 segundos.' : 'Continua exactamente desde el final del clip anterior y usa este clip para resolver con calma la segunda mitad del relato. Mantener ritmo pausado, dejando que las acciones respiren y reservando el cierre para los ultimos segundos.',
			$is_multi_clip ? 'En esta historia de dos clips no incluyas producto, packshot, frasco ni imagen de referencia en el cierre final. El plano final debe concentrarse unicamente en la persona B durmiendo.' : '',
			$clip_addition,
			'' !== $story_name && $is_final_clip ? 'En el plano final, la persona B, identificada como ' . $story_name . ', debe aparecer durmiendo placidamente, en calma absoluta, como si nada le afectara.' : '',
			'' !== $story_name ? 'Personaje B, quien provoca el conflicto y a quien corresponde la frase final: ' . $story_name . '.' : '',
			'' !== $story_segment ? 'La narracion completa en espanol latino debe seguir esta historia: ' . $story_segment : '',
		)
	);

	return goodsleep_compact_video_prompt_for_provider( $parts, $is_final_clip ? $closing_phrase : '' );
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

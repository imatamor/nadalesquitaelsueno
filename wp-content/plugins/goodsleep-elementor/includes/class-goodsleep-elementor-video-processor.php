<?php
/**
 * Postprocesa videos y mezcla musica usando ffmpeg.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Goodsleep_Elementor_Video_Processor {
	/**
	 * Determina si ffmpeg esta disponible.
	 *
	 * @return bool
	 */
	public function is_available() {
		$null_device = strtoupper( substr( PHP_OS, 0, 3 ) ) === 'WIN' ? 'NUL' : '/dev/null';
		$command     = 'ffmpeg -version > ' . $null_device . ' 2>&1';
		$exit_code   = 1;

		exec( $command, $output, $exit_code );

		return 0 === (int) $exit_code;
	}

	/**
	 * Mezcla un track musical con un video remoto o local.
	 *
	 * @param array<string,mixed> $video_source Video generado.
	 * @param array<string,mixed> $track        Track musical.
	 * @param string              $base_name    Nombre base.
	 * @return array<string,string>|WP_Error
	 */
	public function finalize_video( $video_source, $track, $base_name ) {
		return $this->finalize_videos( array( $video_source ), $track, $base_name );
	}

	/**
	 * Concatena uno o varios clips y mezcla el track musical final.
	 *
	 * @param array<int,array<string,mixed>> $video_sources Clips generados.
	 * @param array<string,mixed>            $track         Track musical.
	 * @param string                         $base_name     Nombre base.
	 * @return array<string,string>|WP_Error
	 */
	public function finalize_videos( $video_sources, $track, $base_name ) {
		$this->ensure_wp_file_functions();

		$video_sources = is_array( $video_sources ) ? array_values( array_filter( $video_sources, 'is_array' ) ) : array();
		if ( empty( $video_sources ) ) {
			return new WP_Error( 'goodsleep_video_empty', __( 'No se recibieron clips para finalizar el video.', 'goodsleep-elementor' ) );
		}

		$clip_paths = array();
		foreach ( $video_sources as $index => $video_source ) {
			$clip_path = $this->create_video_source_file( $video_source, $base_name . '-clip-' . ( $index + 1 ) );
			if ( is_wp_error( $clip_path ) ) {
				$this->cleanup_files( $clip_paths );
				return $clip_path;
			}

			$clip_paths[] = $clip_path;
		}

		$video_path = $this->merge_video_files( $clip_paths, $base_name );
		if ( is_wp_error( $video_path ) ) {
			$this->cleanup_files( $clip_paths );
			return $video_path;
		}

		if ( ! empty( $clip_paths ) ) {
			$this->cleanup_files( $clip_paths, array( $video_path ) );
		}

		if ( empty( $track['url'] ) || empty( goodsleep_get_setting( 'video_music_enabled', 1 ) ) ) {
			return array(
				'path'   => $video_path,
				'format' => 'mp4',
			);
		}

		if ( ! $this->can_run_shell_commands() || ! $this->is_available() ) {
			return array(
				'path'   => $video_path,
				'format' => 'mp4',
			);
		}

		$track_source = $this->resolve_track_file( $track );
		if ( is_wp_error( $track_source ) ) {
			return array(
				'path'   => $video_path,
				'format' => 'mp4',
			);
		}

		$output_path = $this->create_temp_file_path( $base_name . '-final.mp4' );
		if ( ! $output_path ) {
			return array(
				'path'   => $video_path,
				'format' => 'mp4',
			);
		}

		$mix_command = sprintf(
			'ffmpeg -y -i %1$s -stream_loop -1 -i %2$s -filter_complex %3$s -map 0:v:0 -map "[aout]" -c:v copy -c:a aac -shortest %4$s 2>&1',
			escapeshellarg( $video_path ),
			escapeshellarg( $track_source['path'] ),
			escapeshellarg( '[1:a]volume=0.08[bg];[0:a][bg]amix=inputs=2:duration=first:dropout_transition=2[aout]' ),
			escapeshellarg( $output_path )
		);

		$command_output = array();
		$command_code   = 0;
		exec( $mix_command, $command_output, $command_code );

		if ( ! empty( $track_source['cleanup'] ) && file_exists( $track_source['path'] ) ) {
			@unlink( $track_source['path'] );
		}

		if ( 0 !== $command_code || ! file_exists( $output_path ) || 0 === filesize( $output_path ) ) {
			return array(
				'path'   => $video_path,
				'format' => 'mp4',
			);
		}

		@unlink( $video_path );

		return array(
			'path'   => $output_path,
			'format' => 'mp4',
		);
	}

	/**
	 * Concatena varios clips en un solo MP4.
	 *
	 * @param array<int,string> $clip_paths Rutas locales.
	 * @param string            $base_name  Nombre base.
	 * @return string|WP_Error
	 */
	protected function merge_video_files( $clip_paths, $base_name ) {
		$clip_paths = array_values( array_filter( array_map( 'strval', (array) $clip_paths ) ) );

		if ( empty( $clip_paths ) ) {
			return new WP_Error( 'goodsleep_video_empty', __( 'No hay clips para unir.', 'goodsleep-elementor' ) );
		}

		if ( 1 === count( $clip_paths ) ) {
			return $clip_paths[0];
		}

		if ( ! $this->can_run_shell_commands() || ! $this->is_available() ) {
			return new WP_Error( 'goodsleep_concat_unavailable', __( 'No se pudo unir los clips porque ffmpeg no esta disponible.', 'goodsleep-elementor' ) );
		}

		$output_path = $this->create_temp_file_path( $base_name . '-merged.mp4' );
		if ( ! $output_path ) {
			return new WP_Error( 'goodsleep_video_temp_failed', __( 'No se pudo preparar el video concatenado.', 'goodsleep-elementor' ) );
		}

		$input_segments = array();
		foreach ( $clip_paths as $clip_path ) {
			$input_segments[] = '-i ' . escapeshellarg( $clip_path );
		}

		$filter_inputs = array();
		for ( $index = 0; $index < count( $clip_paths ); $index++ ) {
			$filter_inputs[] = '[' . $index . ':v:0][' . $index . ':a:0]';
		}

		$concat_filter = implode( '', $filter_inputs ) . 'concat=n=' . count( $clip_paths ) . ':v=1:a=1[v][a]';
		$concat_command = sprintf(
			'ffmpeg -y %1$s -filter_complex %2$s -map "[v]" -map "[a]" -c:v libx264 -preset veryfast -crf 20 -c:a aac -movflags +faststart %3$s 2>&1',
			implode( ' ', $input_segments ),
			escapeshellarg( $concat_filter ),
			escapeshellarg( $output_path )
		);

		$command_output = array();
		$command_code   = 0;
		exec( $concat_command, $command_output, $command_code );

		if ( 0 !== $command_code || ! file_exists( $output_path ) || 0 === filesize( $output_path ) ) {
			@unlink( $output_path );
			return new WP_Error(
				'goodsleep_concat_failed',
				__( 'No se pudieron unir los clips generados.', 'goodsleep-elementor' ),
				array(
					'ffmpeg_output' => implode( "\n", $command_output ),
				)
			);
		}

		return $output_path;
	}

	/**
	 * Determina si se puede ejecutar shell.
	 *
	 * @return bool
	 */
	protected function can_run_shell_commands() {
		if ( ! function_exists( 'exec' ) ) {
			return false;
		}

		$disabled = (string) ini_get( 'disable_functions' );
		if ( '' === $disabled ) {
			return true;
		}

		return ! in_array( 'exec', array_map( 'trim', explode( ',', $disabled ) ), true );
	}

	/**
	 * Crea un archivo local desde el video fuente.
	 *
	 * @param array<string,mixed> $video_source Fuente.
	 * @param string              $base_name    Nombre.
	 * @return string|WP_Error
	 */
	protected function create_video_source_file( $video_source, $base_name ) {
		$this->ensure_wp_file_functions();

		$temp_path = $this->create_temp_file_path( $base_name . '-video.mp4' );
		if ( ! $temp_path ) {
			return new WP_Error( 'goodsleep_video_temp_failed', __( 'No se pudo preparar el video generado.', 'goodsleep-elementor' ) );
		}

		$content = '';
		if ( ! empty( $video_source['video_path'] ) && file_exists( $video_source['video_path'] ) ) {
			$content = file_get_contents( $video_source['video_path'] );
		} elseif ( ! empty( $video_source['video_url'] ) ) {
			$response = wp_remote_get( esc_url_raw( $video_source['video_url'] ), array( 'timeout' => 90 ) );

			if ( is_wp_error( $response ) ) {
				@unlink( $temp_path );
				return $response;
			}

			$content = wp_remote_retrieve_body( $response );
		}

		if ( ! $content ) {
			@unlink( $temp_path );
			return new WP_Error( 'goodsleep_video_empty', __( 'No se pudo obtener el video generado.', 'goodsleep-elementor' ) );
		}

		if ( false === file_put_contents( $temp_path, $content ) ) {
			@unlink( $temp_path );
			return new WP_Error( 'goodsleep_video_write_failed', __( 'No se pudo copiar temporalmente el video generado.', 'goodsleep-elementor' ) );
		}

		return $temp_path;
	}

	/**
	 * Resuelve el archivo del track.
	 *
	 * @param array<string,mixed> $track Track.
	 * @return array<string,mixed>|WP_Error
	 */
	protected function resolve_track_file( $track ) {
		$this->ensure_wp_file_functions();

		$attachment_id = ! empty( $track['attachment_id'] ) ? absint( $track['attachment_id'] ) : 0;
		if ( $attachment_id ) {
			$filepath = get_attached_file( $attachment_id );
			if ( $filepath && file_exists( $filepath ) ) {
				return array(
					'path'    => $filepath,
					'cleanup' => false,
				);
			}
		}

		if ( empty( $track['url'] ) ) {
			return new WP_Error( 'goodsleep_track_missing', __( 'No se encontro el archivo del track musical.', 'goodsleep-elementor' ) );
		}

		$temp_path = $this->create_temp_file_path( wp_basename( $track['url'] ) );
		if ( ! $temp_path ) {
			return new WP_Error( 'goodsleep_track_temp_failed', __( 'No se pudo preparar el track musical.', 'goodsleep-elementor' ) );
		}

		$response = wp_remote_get( esc_url_raw( $track['url'] ), array( 'timeout' => 45 ) );
		if ( is_wp_error( $response ) ) {
			@unlink( $temp_path );
			return $response;
		}

		$content = wp_remote_retrieve_body( $response );
		if ( '' === $content || false === file_put_contents( $temp_path, $content ) ) {
			@unlink( $temp_path );
			return new WP_Error( 'goodsleep_track_write_failed', __( 'No se pudo copiar el track musical temporal.', 'goodsleep-elementor' ) );
		}

		return array(
			'path'    => $temp_path,
			'cleanup' => true,
		);
	}

	/**
	 * Carga helpers de archivos.
	 *
	 * @return void
	 */
	protected function ensure_wp_file_functions() {
		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
	}

	/**
	 * Crea un archivo temporal.
	 *
	 * @param string $filename Nombre.
	 * @return string
	 */
	protected function create_temp_file_path( $filename ) {
		$this->ensure_wp_file_functions();
		$filename  = sanitize_file_name( $filename );
		$temp_base = wp_tempnam( $filename );

		if ( ! $temp_base ) {
			return '';
		}

		$extension = pathinfo( $filename, PATHINFO_EXTENSION );
		if ( '' === $extension ) {
			return $temp_base;
		}

		$target_path = $temp_base . '.' . $extension;
		if ( @rename( $temp_base, $target_path ) ) {
			return $target_path;
		}

		@unlink( $temp_base );

		return '';
	}

	/**
	 * Elimina archivos temporales locales.
	 *
	 * @param array<int,string> $paths  Rutas a borrar.
	 * @param array<int,string> $except Rutas a conservar.
	 * @return void
	 */
	protected function cleanup_files( $paths, $except = array() ) {
		$except = array_filter( array_map( 'strval', (array) $except ) );

		foreach ( array_filter( array_map( 'strval', (array) $paths ) ) as $path ) {
			if ( in_array( $path, $except, true ) ) {
				continue;
			}

			if ( file_exists( $path ) ) {
				@unlink( $path );
			}
		}
	}
}

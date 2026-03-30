<?php
/**
 * Contrato base para proveedores de video.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface Goodsleep_Elementor_Video_Provider_Client_Interface {
	/**
	 * Crea una tarea inicial de video.
	 *
	 * @param array<string,mixed> $payload Payload normalizado.
	 * @return array<string,mixed>|WP_Error
	 */
	public function create_video_task( $payload );

	/**
	 * Consulta una tarea remota.
	 *
	 * @param string              $task_id  ID remoto.
	 * @param array<string,mixed> $context  Contexto guardado.
	 * @return array<string,mixed>|WP_Error
	 */
	public function get_task( $task_id, $context = array() );

	/**
	 * Crea la continuacion del segundo clip.
	 *
	 * @param string              $origin_id      ID o referencia de origen.
	 * @param array<string,mixed> $payload        Payload del segundo clip.
	 * @param array<string,mixed> $source_context Estado del primer clip.
	 * @return array<string,mixed>|WP_Error
	 */
	public function remix_video_task( $origin_id, $payload, $source_context = array() );

	/**
	 * Descarga el MP4 final cuando el proveedor lo requiere.
	 *
	 * @param string              $task_id  ID remoto.
	 * @param array<string,mixed> $context  Contexto guardado o tarea completada.
	 * @return string|WP_Error
	 */
	public function download_video_content( $task_id, $context = array() );

	/**
	 * Extrae el task id.
	 *
	 * @param array<string,mixed> $payload Payload.
	 * @return string
	 */
	public function extract_task_id( $payload );

	/**
	 * Extrae el estado normalizado.
	 *
	 * @param array<string,mixed> $payload Payload.
	 * @return string
	 */
	public function extract_status( $payload );

	/**
	 * Extrae la URL final.
	 *
	 * @param array<string,mixed> $payload Payload.
	 * @return string
	 */
	public function extract_video_url( $payload );

	/**
	 * Extrae el identificador real del clip/video cuando exista.
	 *
	 * @param array<string,mixed> $payload Payload.
	 * @return string
	 */
	public function extract_video_id( $payload );
}

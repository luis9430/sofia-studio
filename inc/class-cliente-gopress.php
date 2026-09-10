<?php
/**
 * Sofia_Cliente_GoPress llama a
 * GET /sites/{nombre}/tema/paginas/{slug}?token=... — ver
 * internal/server/plantillas_pagina.go (handleObtenerPaginaSitioParaTema)
 * en el repo de GoPress. Sin cookie de sesión (el contenedor WP no tiene
 * una): autentica por SOFIA_GOPRESS_TOKEN, mismo mecanismo que
 * GOPRESS_AGENTE_TOKEN del plugin-sensor (ver
 * internal/docker/agente_plugin.go de GoPress) — constantes escritas en
 * wp-config.php al aprovisionar el sitio, nunca configuradas a mano.
 */
class Sofia_Cliente_GoPress {

	/**
	 * Trae {slug, estructura, contenido} de una página — null si GoPress
	 * no está configurado, la página no existe, o la request falla (el
	 * llamador decide qué mostrar en ese caso, ver page.php).
	 *
	 * @return array{slug:string,estructura:array<int,array{id:string,tipo:string}>,contenido:array<string,mixed>}|null
	 */
	public static function obtener_pagina( string $slug ): ?array {
		if ( ! defined( 'SOFIA_GOPRESS_URL' ) || ! defined( 'SOFIA_GOPRESS_TOKEN' ) ) {
			return null;
		}

		$nombre_sitio = defined( 'SOFIA_GOPRESS_SITIO' ) ? SOFIA_GOPRESS_SITIO : '';
		if ( '' === $nombre_sitio ) {
			return null;
		}

		$url = trailingslashit( SOFIA_GOPRESS_URL ) . 'sites/' . rawurlencode( $nombre_sitio ) . '/tema/paginas/' . rawurlencode( $slug );
		$url = add_query_arg( 'token', SOFIA_GOPRESS_TOKEN, $url );

		$respuesta = wp_remote_get(
			$url,
			array(
				'timeout' => 5,
			)
		);
		if ( is_wp_error( $respuesta ) ) {
			return null;
		}
		if ( 200 !== wp_remote_retrieve_response_code( $respuesta ) ) {
			return null;
		}

		$cuerpo = json_decode( wp_remote_retrieve_body( $respuesta ), true );
		if ( ! is_array( $cuerpo ) || ! isset( $cuerpo['estructura'], $cuerpo['contenido'] ) ) {
			return null;
		}

		return $cuerpo;
	}

	/**
	 * Guarda el contenido COMPLETO de una página — llamado por el proxy
	 * REST del editor in-place (ver la memoria de producto "Sofia Studio":
	 * el editor vive DENTRO de wp-admin, este método es la mitad
	 * server-side de ese proxy, nunca se llama directo desde JS del
	 * navegador). Manda a
	 * PUT /sites/{sitio}/paginas/{slug}/contenido?token=... — mismo
	 * endpoint que en teoría también podría usar el dashboard de GoPress
	 * con cookie (ver internal/server/plantillas_pagina.go,
	 * handleActualizarContenidoPaginaSitio: acepta AMBOS mecanismos).
	 *
	 * $contenido siempre va COMPLETO (todo el contenido actual de la
	 * página, no un campo suelto) — mismo criterio que
	 * store.ActualizarContenidoPaginaSitio del lado de GoPress.
	 *
	 * @param array<string,mixed> $contenido
	 * @return bool true si GoPress confirmó el guardado (200 OK).
	 */
	public static function guardar_contenido( string $slug, array $contenido ): bool {
		if ( ! defined( 'SOFIA_GOPRESS_URL' ) || ! defined( 'SOFIA_GOPRESS_TOKEN' ) ) {
			return false;
		}

		$nombre_sitio = defined( 'SOFIA_GOPRESS_SITIO' ) ? SOFIA_GOPRESS_SITIO : '';
		if ( '' === $nombre_sitio ) {
			return false;
		}

		$url = trailingslashit( SOFIA_GOPRESS_URL ) . 'sites/' . rawurlencode( $nombre_sitio ) . '/paginas/' . rawurlencode( $slug ) . '/contenido';
		$url = add_query_arg( 'token', SOFIA_GOPRESS_TOKEN, $url );

		$respuesta = wp_remote_request(
			$url,
			array(
				'method'  => 'PUT',
				'timeout' => 5,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array( 'contenido' => $contenido ) ),
			)
		);
		if ( is_wp_error( $respuesta ) ) {
			return false;
		}

		return 200 === wp_remote_retrieve_response_code( $respuesta );
	}

	/**
	 * Guarda la estructura COMPLETA de bloques de una página — Nivel 2 del
	 * editor (reordenar/agregar/quitar). Manda a
	 * PUT /sites/{sitio}/paginas/{slug}/estructura?token=... (ver
	 * internal/server/plantillas_pagina.go,
	 * handleActualizarEstructuraPaginaSitio). Mismo criterio que
	 * guardar_contenido(): $estructura siempre va COMPLETA, nunca "moví
	 * este bloque a tal posición".
	 *
	 * @param array<int,array{id?:string,tipo:string}> $estructura
	 * @return bool true si GoPress confirmó el guardado (200 OK).
	 */
	public static function guardar_estructura( string $slug, array $estructura ): bool {
		if ( ! defined( 'SOFIA_GOPRESS_URL' ) || ! defined( 'SOFIA_GOPRESS_TOKEN' ) ) {
			return false;
		}

		$nombre_sitio = defined( 'SOFIA_GOPRESS_SITIO' ) ? SOFIA_GOPRESS_SITIO : '';
		if ( '' === $nombre_sitio ) {
			return false;
		}

		$url = trailingslashit( SOFIA_GOPRESS_URL ) . 'sites/' . rawurlencode( $nombre_sitio ) . '/paginas/' . rawurlencode( $slug ) . '/estructura';
		$url = add_query_arg( 'token', SOFIA_GOPRESS_TOKEN, $url );

		$respuesta = wp_remote_request(
			$url,
			array(
				'method'  => 'PUT',
				'timeout' => 5,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array( 'estructura' => $estructura ) ),
			)
		);
		if ( is_wp_error( $respuesta ) ) {
			return false;
		}

		return 200 === wp_remote_retrieve_response_code( $respuesta );
	}
}

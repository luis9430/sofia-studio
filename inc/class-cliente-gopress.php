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
	 * @return array{slug:string,estructura:array<int,array{tipo:string}>,contenido:array<string,string>}|null
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
}

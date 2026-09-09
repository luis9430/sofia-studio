<?php
/**
 * Sofia_REST_Editor expone el proxy REST del editor in-place — vive DENTRO
 * de wp-admin (ver la memoria de producto "Sofia Studio": decisión de
 * arquitectura que revirtió la suposición original de que el editor
 * viviría en el dashboard de GoPress). El JS del admin (Preact) llama a
 * estas rutas de WordPress (mismo origen, SIN CORS); cada una reenvía
 * server-side a GoPress vía Sofia_Cliente_GoPress, usando el
 * SOFIA_GOPRESS_TOKEN ya inyectado en wp-config.php — el navegador nunca
 * ve ni maneja ese token.
 *
 * Namespace "sofia/v1", mismo prefijo que cualquier plugin real de
 * WordPress usaría para su REST API propia.
 */
class Sofia_REST_Editor {

	public static function registrar_rutas(): void {
		register_rest_route(
			'sofia/v1',
			'/paginas/(?P<slug>[a-z0-9-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'obtener_pagina' ),
				'permission_callback' => array( __CLASS__, 'permiso_editar' ),
			)
		);

		register_rest_route(
			'sofia/v1',
			'/paginas/(?P<slug>[a-z0-9-]+)/campo',
			array(
				'methods'             => 'PUT',
				'callback'            => array( __CLASS__, 'guardar_campo' ),
				'permission_callback' => array( __CLASS__, 'permiso_editar' ),
			)
		);
	}

	/**
	 * Mismo chequeo que activa el modo edición dentro del iframe (ver
	 * page.php, ?sofia_editor=1) — sin esto, cualquiera podría llamar
	 * estas rutas y leer/escribir el contenido de la página vía la REST
	 * API de WordPress, que es pública por defecto salvo que cada ruta
	 * declare su propio permission_callback como este.
	 */
	public static function permiso_editar(): bool {
		return current_user_can( 'edit_pages' );
	}

	/**
	 * GET /wp-json/sofia/v1/paginas/{slug} — trae {slug, estructura,
	 * contenido} tal cual los devuelve GoPress, sin transformar: el panel
	 * Preact arma su UI directo a partir de esto (reusa exactamente el
	 * mismo shape que el tema ya consume para renderizar en público).
	 */
	public static function obtener_pagina( WP_REST_Request $request ) {
		$slug   = $request->get_param( 'slug' );
		$pagina = Sofia_Cliente_GoPress::obtener_pagina( $slug );
		if ( null === $pagina ) {
			return new WP_Error( 'sofia_pagina_no_encontrada', 'No se pudo obtener la página desde GoPress.', array( 'status' => 502 ) );
		}
		return rest_ensure_response( $pagina );
	}

	/**
	 * PUT /wp-json/sofia/v1/paginas/{slug}/campo — recibe UN campo editado
	 * ({campo, valor}), lee el contenido ACTUAL completo de la página
	 * (Sofia_Cliente_GoPress::obtener_pagina), mergea ese campo sobre él,
	 * y guarda el objeto completo (Sofia_Cliente_GoPress::guardar_contenido
	 * siempre manda el contenido entero — mismo criterio que GoPress). El
	 * panel Preact nunca necesita mantener en memoria el contenido
	 * completo de la página: informa un campo a la vez, este endpoint
	 * hace el merge — evita que un autosave de un campo pise el valor de
	 * otro editado momentos antes en la misma sesión.
	 */
	public static function guardar_campo( WP_REST_Request $request ) {
		$slug  = $request->get_param( 'slug' );
		$campo = (string) $request->get_param( 'campo' );
		$valor = (string) $request->get_param( 'valor' );

		if ( '' === $campo ) {
			return new WP_Error( 'sofia_campo_requerido', 'El parámetro "campo" es obligatorio.', array( 'status' => 400 ) );
		}

		$pagina = Sofia_Cliente_GoPress::obtener_pagina( $slug );
		if ( null === $pagina ) {
			return new WP_Error( 'sofia_pagina_no_encontrada', 'No se pudo obtener la página desde GoPress.', array( 'status' => 502 ) );
		}

		$contenido          = $pagina['contenido'];
		$contenido[ $campo ] = $valor;

		if ( ! Sofia_Cliente_GoPress::guardar_contenido( $slug, $contenido ) ) {
			return new WP_Error( 'sofia_guardado_fallido', 'GoPress no confirmó el guardado.', array( 'status' => 502 ) );
		}

		return rest_ensure_response( array( 'ok' => true, 'contenido' => $contenido ) );
	}
}

add_action( 'rest_api_init', array( 'Sofia_REST_Editor', 'registrar_rutas' ) );

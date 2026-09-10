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

		register_rest_route(
			'sofia/v1',
			'/paginas/(?P<slug>[a-z0-9-]+)/estructura',
			array(
				'methods'             => 'PUT',
				'callback'            => array( __CLASS__, 'guardar_estructura' ),
				'permission_callback' => array( __CLASS__, 'permiso_editar' ),
			)
		);

		register_rest_route(
			'sofia/v1',
			'/catalogo-bloques',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'catalogo_bloques' ),
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
	 * Purga la caché de página completa (mu-plugin
	 * gopress-pagecache.php, ya existente — ver
	 * gopress_pagecache_purgar_todo(), hoy enganchada a save_post/
	 * switch_theme/activated_plugin/deactivated_plugin) tras un guardado
	 * exitoso del editor. Bug real encontrado en la práctica: guardar
	 * contenido o estructura vía este proxy NUNCA toca la base de datos de
	 * WordPress (el dato real vive en GoPress), así que ninguno de esos
	 * hooks nativos se dispara — sin esta purga explícita, una página ya
	 * cacheada (típicamente la home, marcada page_on_front) seguía
	 * sirviendo el HTML viejo indefinidamente después de editar, hasta que
	 * algo más disparara una purga por otro motivo. function_exists()
	 * porque el mu-plugin es infraestructura de GoPress, no del tema — un
	 * sitio sin ese mu-plugin instalado (entorno de desarrollo del tema
	 * sin GoPress detrás) no debe romper el guardado por esto.
	 */
	private static function purgar_cache_pagina_completa(): void {
		if ( function_exists( 'gopress_pagecache_purgar_todo' ) ) {
			gopress_pagecache_purgar_todo();
		}
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
	 *
	 * $valor NO se castea a string: desde Nivel 2, un campo de tipo "lista
	 * repetible" (ej. "franja_beneficios.items") manda un ARRAY de
	 * objetos ({titulo, texto} por item), no texto plano — mismo mecanismo
	 * que GoPress ya acepta (PaginaSitio.Contenido es map[string]any, ver
	 * la memoria de producto "tema WP con editor de contenido"). Un campo
	 * de texto normal (ej. "hero.titulo") sigue llegando como string sin
	 * ningún cambio.
	 */
	public static function guardar_campo( WP_REST_Request $request ) {
		$slug  = $request->get_param( 'slug' );
		$campo = (string) $request->get_param( 'campo' );
		$valor = $request->get_param( 'valor' );

		if ( '' === $campo ) {
			return new WP_Error( 'sofia_campo_requerido', 'El parámetro "campo" es obligatorio.', array( 'status' => 400 ) );
		}

		$pagina = Sofia_Cliente_GoPress::obtener_pagina( $slug );
		if ( null === $pagina ) {
			return new WP_Error( 'sofia_pagina_no_encontrada', 'No se pudo obtener la página desde GoPress.', array( 'status' => 502 ) );
		}

		$contenido = $pagina['contenido'];
		self::asignar_valor_de_campo( $contenido, $campo, $valor );

		if ( ! Sofia_Cliente_GoPress::guardar_contenido( $slug, $contenido ) ) {
			return new WP_Error( 'sofia_guardado_fallido', 'GoPress no confirmó el guardado.', array( 'status' => 502 ) );
		}

		self::purgar_cache_pagina_completa();
		return rest_ensure_response( array( 'ok' => true, 'contenido' => $contenido ) );
	}

	/**
	 * Muta $contenido en el lugar correcto según la notación de $campo:
	 *
	 * - "id.campo" (2 segmentos, el caso normal): asigna directo,
	 *   $contenido["id.campo"] = $valor — mismo comportamiento de siempre
	 *   ("id" es el ID de instancia del bloque, ver
	 *   Sofia_Componente::atributo_editable()).
	 * - "id.lista.indice.subcampo" (4 segmentos, Nivel 2 — un item DENTRO
	 *   de un campo de tipo lista repetible, ej.
	 *   "a3f92c1b.items.0.titulo"): la clave real en $contenido es
	 *   "id.lista" (ej. "a3f92c1b.items"), cuyo valor es un ARRAY de
	 *   objetos — se muta el subcampo del item en ese índice, sin tocar el
	 *   resto de la lista. Mismo "campo" que ya emite
	 *   Sofia_Componente::atributo_editable() para cada item (ver
	 *   class-franja-beneficios.php), el iframe nunca supo que esto era
	 *   distinto de un campo normal — la interpretación vive acá, en un
	 *   solo lugar.
	 */
	private static function asignar_valor_de_campo( array &$contenido, string $campo, $valor ): void {
		$segmentos = explode( '.', $campo );
		if ( 4 !== count( $segmentos ) ) {
			$contenido[ $campo ] = $valor;
			return;
		}

		list( $id, $lista, $indice, $subcampo ) = $segmentos;
		$clave_lista = "{$id}.{$lista}";
		$items       = is_array( $contenido[ $clave_lista ] ?? null ) ? $contenido[ $clave_lista ] : array();
		$indice      = (int) $indice;

		if ( ! isset( $items[ $indice ] ) || ! is_array( $items[ $indice ] ) ) {
			$items[ $indice ] = array();
		}
		$items[ $indice ][ $subcampo ] = $valor;

		$contenido[ $clave_lista ] = $items;
	}

	/**
	 * PUT /wp-json/sofia/v1/paginas/{slug}/estructura — recibe el árbol
	 * COMPLETO de bloques (reordenar/agregar/quitar, Nivel 2 del editor) y
	 * lo reenvía tal cual a Sofia_Cliente_GoPress::guardar_estructura().
	 * A diferencia de guardar_campo(), acá no hace falta leer+mergear:
	 * Nivel 2 siempre opera sobre el árbol entero (mismo criterio que ya
	 * usa GoPress del lado del store).
	 */
	public static function guardar_estructura( WP_REST_Request $request ) {
		$slug       = $request->get_param( 'slug' );
		$estructura = $request->get_param( 'estructura' );

		if ( ! is_array( $estructura ) || empty( $estructura ) ) {
			return new WP_Error( 'sofia_estructura_requerida', 'El parámetro "estructura" necesita al menos un bloque.', array( 'status' => 400 ) );
		}

		if ( ! Sofia_Cliente_GoPress::guardar_estructura( $slug, $estructura ) ) {
			return new WP_Error( 'sofia_guardado_fallido', 'GoPress no confirmó el guardado.', array( 'status' => 502 ) );
		}

		self::purgar_cache_pagina_completa();
		return rest_ensure_response( array( 'ok' => true, 'estructura' => $estructura ) );
	}

	/**
	 * GET /wp-json/sofia/v1/catalogo-bloques — lista los Componentes REALES
	 * del tema instalado (ver Sofia_Componente_Factory::catalogo()), para
	 * el panel "agregar bloque" del editor (Nivel 2). Nunca llama a
	 * GoPress: esto es 100% local al tema, GoPress no tiene ni necesita
	 * saber qué Componentes PHP existen en cada sitio.
	 */
	public static function catalogo_bloques( WP_REST_Request $request ) {
		return rest_ensure_response( Sofia_Componente_Factory::catalogo() );
	}
}

add_action( 'rest_api_init', array( 'Sofia_REST_Editor', 'registrar_rutas' ) );

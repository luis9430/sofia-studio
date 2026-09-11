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
			'/paginas/(?P<slug>[a-z0-9-]+)/bloque/(?P<id>[a-z0-9]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'obtener_html_de_bloque' ),
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

		register_rest_route(
			'sofia/v1',
			'/estilo-global',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'obtener_estilo_global' ),
					'permission_callback' => array( __CLASS__, 'permiso_editar' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( __CLASS__, 'guardar_estilo_global' ),
					'permission_callback' => array( __CLASS__, 'permiso_editar' ),
				),
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
	 * con_lock_de_pagina() ejecuta $accion con un lock EXCLUSIVO (flock())
	 * sobre un archivo propio de $slug — necesario porque guardar_campo()/
	 * guardar_estructura() son "leer todo el contenido → mutar UNA clave →
	 * escribir todo de vuelta" (ver el comentario de asignar_valor_de_campo()
	 * abajo), sin ningún lock esto es una condición de carrera clásica:
	 *
	 * Bug real encontrado en la práctica: dos PUT /campo casi simultáneos
	 * (ej. el autosave con debounce de un texto recién editado, más el
	 * guardado inmediato de una condición de Visibilidad desde el drawer)
	 * pueden ambos LEER el mismo contenido base antes de que cualquiera
	 * termine de escribir — el que ESCRIBE segundo pisa por completo el
	 * cambio del primero con una versión vieja del resto del contenido. En
	 * la práctica esto se manifestó como items de una lista repetible
	 * (Franja de beneficios) "desapareciendo" al guardar una condición de
	 * Visibilidad justo después de editar un texto.
	 *
	 * flock() (no un transient de WordPress) porque es un lock REAL a nivel
	 * de sistema operativo — funciona sin importar si el sitio tiene un
	 * object cache persistente configurado (muchos no lo tienen, y un
	 * transient respaldado solo por la base de datos no da ninguna garantía
	 * dura de exclusión mutua, es solo un check-and-set con su propia
	 * carrera). LOCK_EX bloquea el request ACTUAL hasta que el lock quede
	 * libre — el pequeño costo de latencia (milisegundos, mientras dura un
	 * guardado normal) es aceptable a cambio de nunca perder contenido.
	 *
	 * Un archivo POR SLUG (no un lock global de todo el sitio) — páginas
	 * distintas se editan en paralelo sin bloquearse entre sí.
	 */
	private static function con_lock_de_pagina( string $slug, callable $accion ) {
		$directorio = trailingslashit( get_temp_dir() ) . 'sofia-studio-locks';
		if ( ! is_dir( $directorio ) ) {
			wp_mkdir_p( $directorio );
		}

		$ruta_lock = $directorio . '/' . sanitize_file_name( $slug ) . '.lock';
		$manejador = fopen( $ruta_lock, 'c' );
		if ( false === $manejador ) {
			// Sin poder abrir el archivo de lock (permisos, disco), se
			// ejecuta igual SIN lock — mejor arriesgar la carrera rara que
			// romper el guardado por completo en un entorno con
			// restricciones de filesystem inusuales.
			return $accion();
		}

		flock( $manejador, LOCK_EX );
		try {
			return $accion();
		} finally {
			flock( $manejador, LOCK_UN );
			fclose( $manejador );
		}
	}

	/**
	 * GET /wp-json/sofia/v1/paginas/{slug} — trae {slug, estructura,
	 * contenido} tal cual los devuelve GoPress, sin transformar: el panel
	 * Preact arma su UI directo a partir de esto (reusa exactamente el
	 * mismo shape que el tema ya consume para renderizar en público).
	 */
	/**
	 * GET /wp-json/sofia/v1/paginas/{slug}/bloque/{id} — devuelve SOLO el
	 * HTML renderizado de UN bloque puntual (Sofia_Componente::render()
	 * real, mismo PHP que emite el sitio público) — evita que el panel
	 * tenga que recargar la página ENTERA del iframe para reflejar un
	 * bloque nuevo o un bloque cuya condición de Visibilidad cambió.
	 *
	 * Sigue el mismo principio de siempre ("PHP es la única fuente de HTML
	 * real, el iframe/panel nunca inventa nada") — el cambio es CUÁNDO se
	 * pide ese HTML: un fragmento puntual en vez de la página completa. El
	 * panel decide qué hacer con el HTML devuelto (insertarlo como bloque
	 * nuevo vía Muuri.add(), o reemplazar una <section> existente), este
	 * endpoint no sabe ni le importa cuál de los dos casos es.
	 */
	public static function obtener_html_de_bloque( WP_REST_Request $request ) {
		$slug = $request->get_param( 'slug' );
		$id   = $request->get_param( 'id' );

		$pagina = Sofia_Cliente_GoPress::obtener_pagina( $slug );
		if ( null === $pagina ) {
			return new WP_Error( 'sofia_pagina_no_encontrada', 'No se pudo obtener la página desde GoPress.', array( 'status' => 502 ) );
		}

		$sofia_pagina = new Sofia_Pagina( $pagina['estructura'], $pagina['contenido'] );
		foreach ( $sofia_pagina->componentes() as $componente ) {
			if ( $id === $componente->id() ) {
				return rest_ensure_response( array( 'ok' => true, 'html' => $componente->render() ) );
			}
		}

		return new WP_Error( 'sofia_bloque_no_encontrado', 'Ese bloque no existe en la estructura de la página.', array( 'status' => 404 ) );
	}

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

		// con_lock_de_pagina(): ver el comentario largo ahí — sin esto, dos
		// PUT /campo casi simultáneos (autosave de un texto + guardado
		// inmediato de una condición de Visibilidad, por ejemplo) podían
		// pisarse entre sí y perder contenido ya guardado.
		return self::con_lock_de_pagina(
			$slug,
			function () use ( $slug, $campo, $valor ) {
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
		);
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
	 * - Cualquier notación de arriba con "._estilo" agregado al final
	 *   (3 o 5 segmentos, ej. "a3f92c1b.titulo._estilo" o
	 *   "a3f92c1b.items.0.titulo._estilo", Nivel 3 — estilo por campo, ver
	 *   Sofia_Componente::atributo_estilo()): se resuelve igual que el
	 *   campo base (2 o 4 segmentos, quitando el sufijo), pero el VALOR
	 *   final se guarda bajo la clave "{subcampo}._estilo" en vez de
	 *   pisar el valor real del campo — ambos conviven como claves
	 *   hermanas en el mismo objeto (el propio $contenido de nivel
	 *   superior, o el mismo item de la lista).
	 * - "id._estilo_bloque" (2 segmentos — "_estilo_bloque" es el "campo",
	 *   nunca colisiona con un nombre real de campo por el guion bajo
	 *   inicial, ver Sofia_Componente::atributo_estilo_bloque()): Nivel 2,
	 *   estilo del BLOQUE completo (columnas de grid, color de fondo de la
	 *   sección, padding vertical) — cae en el caso "2 segmentos" de
	 *   arriba sin ningún cambio; el estilo de bloque nunca tiene sufijo
	 *   "._estilo" (eso es exclusivo del estilo POR CAMPO, Nivel 3).
	 * - "id._condicion_bloque" (2 segmentos, mismo criterio exacto que
	 *   "_estilo_bloque" — ver Sofia_Componente::bloque_visible()): la
	 *   condición de VISIBILIDAD del bloque completo (pestaña Visibilidad
	 *   del drawer), un array de reglas `{campo, operador, valor, enlace}`
	 *   — mismo shape que store.ReglaCondicion del lado GoPress (reusado
	 *   tal cual del motor de condiciones de automatizaciones, nunca
	 *   reinventado). Cae en el caso "2 segmentos" sin cambios.
	 */
	private static function asignar_valor_de_campo( array &$contenido, string $campo, $valor ): void {
		$es_estilo = str_ends_with( $campo, '._estilo' );
		if ( $es_estilo ) {
			$campo = substr( $campo, 0, -strlen( '._estilo' ) );
		}

		$segmentos = explode( '.', $campo );
		if ( 4 !== count( $segmentos ) ) {
			// Caso "id.campo" (2 segmentos) — con o sin sufijo _estilo, es
			// una asignación directa de UNA clave en $contenido.
			$clave                = $es_estilo ? $campo . '._estilo' : $campo;
			$contenido[ $clave ] = $valor;
			return;
		}

		list( $id, $lista, $indice, $subcampo ) = $segmentos;
		$clave_lista = "{$id}.{$lista}";
		$items       = is_array( $contenido[ $clave_lista ] ?? null ) ? $contenido[ $clave_lista ] : array();
		$indice      = (int) $indice;

		if ( ! isset( $items[ $indice ] ) || ! is_array( $items[ $indice ] ) ) {
			$items[ $indice ] = array();
		}
		$clave_subcampo                  = $es_estilo ? $subcampo . '._estilo' : $subcampo;
		$items[ $indice ][ $clave_subcampo ] = $valor;

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

		// Un bloque NUEVO llega SIN "id" en $estructura (ver
		// agregarBloque() en App.jsx: manda solo {tipo}) — es GoPress
		// (store.rellenarIDsFaltantes/GenerarIDBloque, del lado Go) quien
		// le asigna un ID de instancia recién al procesar este guardado.
		// Bug real encontrado en la práctica: persistir_defaults_de_bloques_nuevos()
		// usando $estructura tal cual llegó del request NUNCA encontraba
		// ningún "id" en el bloque recién agregado, así que jamás
		// persistía sus defaults — hay que releer la página para obtener
		// la estructura REAL, ya con los IDs asignados.
		$pagina_actualizada = Sofia_Cliente_GoPress::obtener_pagina( $slug );
		if ( null !== $pagina_actualizada ) {
			self::persistir_defaults_de_bloques_nuevos( $slug, $pagina_actualizada['estructura'] );
		}

		self::purgar_cache_pagina_completa();
		return rest_ensure_response( array( 'ok' => true, 'estructura' => $estructura ) );
	}

	/**
	 * Persiste en el CONTENIDO real de la página los valores por defecto de
	 * cualquier bloque de $estructura que todavía no tenga NADA guardado —
	 * bug real corregido acá: un bloque recién agregado (ej. Franja de
	 * beneficios, con 3 items) solo tenía sus valores por defecto en
	 * memoria PHP (Sofia_Componente::props_por_defecto()), nunca escritos
	 * en el contenido de GoPress. Si el usuario editaba UN SOLO campo de
	 * UN SOLO item de la lista antes de que los demás se guardaran (ej. un
	 * blur accidental al hacer click derecho para el menú contextual),
	 * Sofia_REST_Editor::asignar_valor_de_campo() escribía el array de
	 * items partiendo de CERO — perdiendo los otros items, que nunca
	 * habían llegado a persistirse. Confirmado con logging real: el
	 * guardado individual "funcionaba" (200 OK), pero corrompía en
	 * silencio el array completo.
	 *
	 * "todavía no tiene nada guardado" se detecta por la AUSENCIA de
	 * cualquier clave que empiece con "{id}." en el contenido actual — un
	 * bloque ya editado (aunque sea un solo campo) no se toca, para nunca
	 * pisar contenido real ya escrito por el usuario con sus defaults.
	 */
	private static function persistir_defaults_de_bloques_nuevos( string $slug, array $estructura ): void {
		self::con_lock_de_pagina(
			$slug,
			function () use ( $slug, $estructura ) {
				$pagina = Sofia_Cliente_GoPress::obtener_pagina( $slug );
				if ( null === $pagina ) {
					return;
				}

				$contenido = $pagina['contenido'];
				$cambio    = false;

				foreach ( $estructura as $bloque ) {
					$id   = $bloque['id'] ?? '';
					$tipo = $bloque['tipo'] ?? '';
					if ( '' === $id || '' === $tipo || self::bloque_tiene_contenido( $contenido, $id ) ) {
						continue;
					}

					$componente = Sofia_Componente_Factory::crear( $tipo, $id );
					if ( null === $componente ) {
						continue;
					}

					foreach ( $componente->props() as $campo => $valor ) {
						$contenido[ "{$id}.{$campo}" ] = $valor;
					}
					$cambio = true;
				}

				if ( $cambio ) {
					Sofia_Cliente_GoPress::guardar_contenido( $slug, $contenido );
				}
			}
		);
	}

	/**
	 * true si $contenido ya tiene AL MENOS una clave con el prefijo
	 * "{id}." — mismo criterio de recorte que
	 * Sofia_Pagina::props_de_bloque(), pero acá solo para detectar
	 * presencia, no para extraer valores.
	 */
	private static function bloque_tiene_contenido( array $contenido, string $id ): bool {
		foreach ( array_keys( $contenido ) as $clave ) {
			if ( str_starts_with( $clave, "{$id}." ) ) {
				return true;
			}
		}
		return false;
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

	/**
	 * GET /wp-json/sofia/v1/estilo-global — trae el JSON de paleta/
	 * tipografía del SITIO completo (Nivel 3, distinto del Contenido por
	 * página) para el panel "Estilo global" del editor, ver
	 * Sofia_Cliente_GoPress::obtener_estilo_global(). Nunca devuelve error
	 * — un sitio sin nada configurado responde {} (array vacío en JSON),
	 * el panel simplemente arranca con los controles vacíos.
	 */
	public static function obtener_estilo_global( WP_REST_Request $request ) {
		return rest_ensure_response( Sofia_Cliente_GoPress::obtener_estilo_global() );
	}

	/**
	 * PUT /wp-json/sofia/v1/estilo-global — guarda el JSON completo de
	 * estilo global (siempre el objeto entero, mismo criterio que
	 * guardar_estructura(): nunca "cambié solo el color de acento", el
	 * panel Preact manda el objeto {colores, tipografia} completo cada
	 * vez).
	 */
	public static function guardar_estilo_global( WP_REST_Request $request ) {
		$estilo_global = $request->get_param( 'estilo_global' );
		if ( ! is_array( $estilo_global ) ) {
			return new WP_Error( 'sofia_estilo_global_invalido', 'El parámetro "estilo_global" debe ser un objeto.', array( 'status' => 400 ) );
		}

		if ( ! Sofia_Cliente_GoPress::guardar_estilo_global( $estilo_global ) ) {
			return new WP_Error( 'sofia_guardado_fallido', 'GoPress no confirmó el guardado.', array( 'status' => 502 ) );
		}

		self::purgar_cache_pagina_completa();
		return rest_ensure_response( array( 'ok' => true, 'estilo_global' => $estilo_global ) );
	}
}

add_action( 'rest_api_init', array( 'Sofia_REST_Editor', 'registrar_rutas' ) );

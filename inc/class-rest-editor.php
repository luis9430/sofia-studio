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
			'/catalogo-bloques/(?P<tipo>[a-z0-9_]+)/schema',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'schema_de_bloque' ),
				'permission_callback' => array( __CLASS__, 'permiso_editar' ),
			)
		);

		register_rest_route(
			'sofia/v1',
			'/catalogo-bloques/(?P<tipo>[a-z0-9_]+)/schema-contenido',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'schema_contenido_de_bloque' ),
				'permission_callback' => array( __CLASS__, 'permiso_editar' ),
			)
		);

		// Set de íconos del sistema, para el selector visual del panel de
		// contenido (CampoIcono en CampoDesdeSchema.jsx). Ruta propia, no
		// bajo catalogo-bloques/: ahí "iconos" chocaría con el patrón
		// {tipo} de las rutas de schema.
		register_rest_route(
			'sofia/v1',
			'/iconos',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'catalogo_iconos' ),
				'permission_callback' => array( __CLASS__, 'permiso_editar' ),
			)
		);

		register_rest_route(
			'sofia/v1',
			'/ia/generar',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'ia_generar_arbol' ),
				'permission_callback' => array( __CLASS__, 'permiso_editar' ),
			)
		);

		register_rest_route(
			'sofia/v1',
			'/ia/preview',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'ia_preview_arbol' ),
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

		register_rest_route(
			'sofia/v1',
			'/core-framework/variables',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'variables_core_framework' ),
				'permission_callback' => array( __CLASS__, 'permiso_editar' ),
			)
		);

		register_rest_route(
			'sofia/v1',
			'/estilo-global/roles',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'roles_estilo_global' ),
				'permission_callback' => array( __CLASS__, 'permiso_editar' ),
			)
		);

		register_rest_route(
			'sofia/v1',
			'/core-framework/tokens-visual',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'tokens_core_framework_visual' ),
				'permission_callback' => array( __CLASS__, 'permiso_editar' ),
			)
		);

		register_rest_route(
			'sofia/v1',
			'/core-framework/variables-con-valor',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'variables_core_framework_con_valor' ),
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
	 * nuevo, o reemplazar una <section> existente), este
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
		// buscar_componente_por_id() recursivo (no un foreach plano sobre
		// componentes()) — agregado en Fase 3: componentes() solo devuelve
		// los Sofia_Componente de NIVEL SUPERIOR, un bloque insertado
		// DENTRO de un Container vive únicamente en $hijos de ese
		// Container (ver Sofia_Componente::hijos()), nunca en esta lista
		// plana. Sin la recursión, insertar/actualizar cualquier bloque
		// anidado devolvía 404 acá — silencioso hasta que se lo mira de
		// cerca, porque el guardado de estructura (guardar_estructura())
		// sí lo persiste bien, solo este endpoint de HTML puntual no lo
		// encontraba.
		$componente = self::buscar_componente_por_id( $sofia_pagina->componentes(), $id );
		if ( null !== $componente ) {
			return rest_ensure_response( array( 'ok' => true, 'html' => $componente->render() ) );
		}

		return new WP_Error( 'sofia_bloque_no_encontrado', 'Ese bloque no existe en la estructura de la página.', array( 'status' => 404 ) );
	}

	/**
	 * @param Sofia_Componente[] $componentes
	 */
	private static function buscar_componente_por_id( array $componentes, string $id ): ?Sofia_Componente {
		foreach ( $componentes as $componente ) {
			if ( $id === $componente->id() ) {
				return $componente;
			}
			$encontrado = self::buscar_componente_por_id( $componente->hijos(), $id );
			if ( null !== $encontrado ) {
				return $encontrado;
			}
		}
		return null;
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
	 * GET /wp-json/sofia/v1/catalogo-bloques/{tipo}/schema — schema de
	 * Nivel 2 (genérico + propio, ver Sofia_Componente_Factory::schema_de())
	 * para el drawer de estilo de bloque (DrawerEstilo.jsx vía
	 * CampoDesdeSchema.jsx) — reemplaza los controles antes hardcodeados
	 * en JSX por la fuente de verdad real en PHP, ver Fase 2 del plan de
	 * "primitivas de layout" (memoria de producto). 404 para un tipo que
	 * no corresponde a ningún Componente real (dato corrupto, o un tipo
	 * de una versión más nueva del tema) — mismo criterio de "fail
	 * closed" que el resto de este proxy.
	 */
	public static function schema_de_bloque( WP_REST_Request $request ) {
		$schema = Sofia_Componente_Factory::schema_de( $request->get_param( 'tipo' ) );
		if ( null === $schema ) {
			return new WP_Error( 'sofia_tipo_desconocido', 'Tipo de bloque desconocido.', array( 'status' => 404 ) );
		}
		return rest_ensure_response( $schema );
	}

	/**
	 * GET /wp-json/sofia/v1/catalogo-bloques/{tipo}/schema-contenido — Fase
	 * 4 ("primitivas de layout" + generador de árboles por IA), paralela a
	 * schema_de_bloque() pero para CONTENIDO en vez de estilo, ver
	 * Sofia_Componente_Factory::schema_contenido_de(). Mismo criterio de
	 * 404 para un tipo desconocido.
	 */
	public static function schema_contenido_de_bloque( WP_REST_Request $request ) {
		$schema = Sofia_Componente_Factory::schema_contenido_de( $request->get_param( 'tipo' ) );
		if ( null === $schema ) {
			return new WP_Error( 'sofia_tipo_desconocido', 'Tipo de bloque desconocido.', array( 'status' => 404 ) );
		}
		return rest_ensure_response( $schema );
	}

	/**
	 * GET /wp-json/sofia/v1/iconos — el set de íconos del sistema
	 * ({nombre: contenido interno del <svg>}), para que el panel de
	 * contenido dibuje un selector visual en vez de pedir el nombre exacto
	 * escrito a mano.
	 *
	 * Manda el <path> crudo, no un <svg> armado: el tamaño y la clase los
	 * decide quien lo dibuja (la grilla del panel los quiere chicos, el
	 * render del sitio los quiere del tamaño del Componente), mismo
	 * criterio que Sofia_Componente::svg_icono() ya aplica del lado PHP.
	 */
	public static function catalogo_iconos() {
		return rest_ensure_response( array( 'iconos' => Sofia_Componente::ICONOS_PERMITIDOS ) );
	}

	/**
	 * POST /wp-json/sofia/v1/ia/generar — Fase 4 ("primitivas de layout":
	 * generador de árboles de bloques por IA, ver la memoria de producto).
	 * Recibe {prompt} del admin-app, arma el catálogo COMPLETO de tipos con
	 * AMBOS schemas (estilo + contenido, ver
	 * Sofia_Componente_Factory::catalogo_para_ia()) 100% local (nunca un
	 * request HTTP a sí mismo — este método YA corre server-side dentro de
	 * WordPress), se lo pasa a Sofia_Cliente_GoPress::generar_arbol_ia()
	 * (que reenvía a GoPress, que a su vez llama a OpenRouter con el único
	 * secreto global — WordPress nunca ve la API key de OpenRouter, mismo
	 * principio de proxy que el resto de este cliente), y VALIDA/REPARA el
	 * árbol crudo que devolvió el LLM contra el catálogo REAL de este tema
	 * antes de devolverlo al frontend — ver validar_y_reparar_arbol_ia()
	 * para el detalle de cada regla y por qué.
	 *
	 * Deliberadamente NO persiste nada acá — el árbol reparado vuelve al
	 * admin-app para que el usuario lo previsualice (sofia/v1/ia/preview) y
	 * decida si lo aplica de verdad (PUT sofia/v1/paginas/{slug}/estructura,
	 * el endpoint YA EXISTENTE de Nivel 2 — nunca uno nuevo, ver el plan).
	 */
	public static function ia_generar_arbol( WP_REST_Request $request ) {
		$prompt = (string) $request->get_param( 'prompt' );
		if ( '' === trim( $prompt ) ) {
			return new WP_Error( 'sofia_prompt_requerido', 'El parámetro "prompt" es obligatorio.', array( 'status' => 400 ) );
		}

		$catalogo = Sofia_Componente_Factory::catalogo_para_ia();
		// obtener_estilo_global() (Nivel 3): "capa 2" de la conversación de
		// arquitectura sobre creatividad del generador (ver la memoria de
		// producto) — sin esto la IA diseñaba a ciegas sin saber la
		// paleta/tipografía real del sitio. Array vacío si el sitio no
		// tiene estilo global configurado, nunca un error (mismo criterio
		// que el resto de usos de este método).
		$estilo_global = Sofia_Cliente_GoPress::obtener_estilo_global();

		$resultado = Sofia_Cliente_GoPress::generar_arbol_ia( $prompt, $catalogo, $estilo_global );
		if ( null === $resultado ) {
			return new WP_Error( 'sofia_ia_fallo', 'No se pudo generar el árbol — GoPress no respondió o OpenRouter no está configurado.', array( 'status' => 502 ) );
		}

		$arbol_crudo = is_array( $resultado['arbol'] ?? null ) ? $resultado['arbol'] : array();
		$avisos_go   = is_array( $resultado['avisos'] ?? null ) ? $resultado['avisos'] : array();

		list( $arbol_reparado, $avisos_php ) = self::validar_y_reparar_arbol_ia( $arbol_crudo );

		return rest_ensure_response(
			array(
				'ok'             => true,
				'arbol_reparado' => $arbol_reparado,
				'avisos'         => array_merge( $avisos_go, $avisos_php ),
			)
		);
	}

	/**
	 * validar_y_reparar_arbol_ia(): el árbol crudo que devuelve el LLM
	 * NUNCA se confía tal cual — mismo principio de seguridad que el resto
	 * del plan de "primitivas de layout": el LLM nunca escribe código que
	 * se ejecuta, pasa por el mismo tipo de validación defensiva que
	 * cualquier dato externo. Puerto a PHP del mismo criterio que
	 * validateTree() de CraftTreeAssistantService (ecommerce, ver la
	 * memoria de producto de esta fase) — MISMO ESPÍRITU, forma distinta
	 * porque el modelo de árbol acá es recursivo {id,tipo,hijos} (ver
	 * BloqueEstructuraPlantilla del lado GoPress) en vez de un mapa plano
	 * {nodeId: nodo} con referencias "nodes"/"linkedNodes" — no hace falta
	 * podar referencias colgantes por separado, un hijo inválido
	 * simplemente no aparece en el array "hijos" de su padre.
	 *
	 * Reglas aplicadas, cada una con su criterio documentado:
	 *
	 * 1. Tipo desconocido (no está en Sofia_Componente_Factory::catalogo(),
	 *    ver TIPOS_REGISTRADOS) → se PODA ESE NODO COMPLETO (no solo se
	 *    vacían sus props) — a diferencia de una prop de contenido con
	 *    clave desconocida (regla 3, que descarta solo esa clave puntual),
	 *    un TIPO desconocido no tiene ningún Componente PHP que lo pueda
	 *    renderizar en absoluto (Sofia_Componente_Factory::crear() devuelve
	 *    null) — dejarlo en el árbol con props vacías igual produciría un
	 *    bloque roto en el guardado/preview. Se poda el NODO, no la RAMA
	 *    completa (sus propios hijos, si los tuviera, se re-adjuntan al
	 *    padre en su lugar, en la misma posición) — mismo criterio que
	 *    "nunca tumbar el árbol completo por un nodo malo" del plan: si el
	 *    LLM generó un Container con 3 hijos válidos pero le puso un tipo
	 *    inventado al Container mismo, perder los 3 hijos junto con el
	 *    error sería más destructivo que necesario. Caso real esperado:
	 *    el LLM alucina un tipo plausible pero inexistente (ej. "footer" o
	 *    "navbar", que no existen en este catálogo).
	 * 2. IDs faltantes o duplicados → se REGENERAN con el mismo formato que
	 *    store.GenerarIDBloque() del lado Go (8 hex de
	 *    random_bytes(4)) — replicado acá en vez de llamar a GoPress porque
	 *    es una operación puramente local (generar bytes al azar), pedirle
	 *    esto a GoPress sería un roundtrip HTTP innecesario. IDs faltantes
	 *    de por sí ya los rellena store.RellenarIDsFaltantes() cuando el
	 *    árbol reparado se guarde de verdad vía PUT .../estructura (el
	 *    flujo de "confirmar", ver el plan) — pero acá se generan de todas
	 *    formas para que el PREVIEW (sofia/v1/ia/preview, que NUNCA toca
	 *    GoPress) tenga IDs reales para atributos_seccion()/data-sofia-*, y
	 *    para poder detectar/corregir DUPLICADOS, que
	 *    RellenarIDsFaltantes() no cubre (solo rellena vacíos, nunca
	 *    detecta que dos bloques ya tienen el MISMO id no vacío — caso real
	 *    esperado si el LLM reutiliza un id corto tipo "1"/"2" en más de un
	 *    nodo, algo confirmado como patrón real de alucinación de LLMs con
	 *    árboles grandes).
	 * 3. Prop de contenido con clave no declarada en
	 *    Sofia_Componente_Factory::schema_contenido_de($tipo) → se descarta
	 *    SOLO esa clave puntual, el resto del nodo (y sus props válidas)
	 *    sigue intacto — mismo criterio que "nunca tumbar de más": una
	 *    clave inventada de más (ej. el LLM agrega "subtitulo" a un Hero
	 *    que solo declara "titulo"/"imagen") no amerita perder el resto del
	 *    contenido real que sí generó bien.
	 * 4. "props._estilo_bloque" (capa 1 de composición por IA, ver
	 *    reparar_estilo_bloque_ia()) es un caso ESPECIAL dentro de props:
	 *    no es contenido, es el subconjunto acotado de estilo de Nivel 2
	 *    (Sofia_Componente_Factory::schema_estilo_ia_de($tipo) —
	 *    ancho/alineación + lo que declare schema_propio() del tipo, ej.
	 *    dirección/gap de Container) que la IA puede proponer para que el
	 *    bloque tenga composición real, no solo contenido crudo sin
	 *    layout. Mismo criterio de poda por clave que la regla 3, pero
	 *    contra ESE schema en vez del de contenido.
	 *
	 * @param array<int,array<string,mixed>> $nodos
	 * @return array{0:array<int,array<string,mixed>>,1:string[]} [árbol
	 *         reparado, avisos]
	 */
	private static function validar_y_reparar_arbol_ia( array $nodos ): array {
		$avisos = array();
		$ids_ya_usados = array();
		$reparado = self::reparar_nodos_arbol_ia( $nodos, $avisos, $ids_ya_usados );
		return array( $reparado, $avisos );
	}

	/**
	 * @param array<int,array<string,mixed>> $nodos
	 * @param string[]                       $avisos       (por referencia,
	 *        se van acumulando aunque la recursión baje de nivel)
	 * @param array<string,bool>             $ids_ya_usados (por referencia
	 *        — compartido entre TODA la recursión, no solo el nivel actual,
	 *        así un ID duplicado entre dos ramas distintas del árbol
	 *        también se detecta, no solo entre hermanos directos)
	 * @return array<int,array<string,mixed>>
	 */
	private static function reparar_nodos_arbol_ia( array $nodos, array &$avisos, array &$ids_ya_usados ): array {
		$catalogo_tipos_validos = array_column( Sofia_Componente_Factory::catalogo(), 'tipo' );
		$resultado = array();

		foreach ( $nodos as $nodo ) {
			if ( ! is_array( $nodo ) ) {
				continue;
			}

			$tipo = (string) ( $nodo['tipo'] ?? '' );
			$hijos_crudos = is_array( $nodo['hijos'] ?? null ) ? $nodo['hijos'] : array();
			// Hijos SIEMPRE se procesan primero, tenga o no el nodo actual
			// un tipo válido — ver la regla 1 arriba: si este nodo se poda
			// por tipo desconocido, sus hijos ya reparados se re-adjuntan
			// al padre en su lugar (nunca se pierden junto con el nodo).
			$hijos_reparados = empty( $hijos_crudos ) ? array() : self::reparar_nodos_arbol_ia( $hijos_crudos, $avisos, $ids_ya_usados );

			if ( ! in_array( $tipo, $catalogo_tipos_validos, true ) ) {
				$avisos[] = sprintf( 'La IA propuso un bloque de tipo "%s", que no existe en este tema — se omitió (sus hijos, si tenía, se conservaron en su lugar).', $tipo ?: '(vacío)' );
				// Los hijos ya reparados de un nodo podado pasan a formar
				// parte del resultado directamente, en la misma posición
				// que hubiera ocupado el padre — nunca se descartan.
				foreach ( $hijos_reparados as $hijo_promovido ) {
					$resultado[] = $hijo_promovido;
				}
				continue;
			}

			$id = (string) ( $nodo['id'] ?? '' );
			if ( '' === $id || isset( $ids_ya_usados[ $id ] ) ) {
				$id_anterior = $id;
				$id          = self::generar_id_bloque_ia();
				if ( '' !== $id_anterior ) {
					$avisos[] = sprintf( 'Un bloque tipo "%s" tenía un ID duplicado ("%s") — se le asignó uno nuevo.', $tipo, $id_anterior );
				}
			}
			$ids_ya_usados[ $id ] = true;

			$props_crudas = is_array( $nodo['props'] ?? null ) ? $nodo['props'] : array();
			// Tolerancia real encontrada probando en vivo: el LLM a veces
			// pone "_estilo_bloque" como clave HERMANA de "props" a nivel
			// de nodo ({id,tipo,props:{...},_estilo_bloque:{...}}) en vez
			// de anidado DENTRO de props como pide la regla 2 del prompt
			// (ver construirSystemPromptGenerarArbolIA en GoPress) —
			// modelos más chicos/baratos (ej. mistral-small, el default de
			// este generador) no siempre respetan el formato exacto
			// pedido. Sin esto, ese estilo se perdía en silencio (peor
			// aún: si el nodo raíz también tenía "_estilo_bloque" así,
			// terminaba mezclado con las props de CONTENIDO y
			// reparar_props_contenido_ia lo rechazaba como "campo que no
			// existe" — el bug real reportado por el usuario, ver
			// franja_beneficios en el screenshot). Acá se lo migra DENTRO
			// de props antes de reparar, así ambas formas (correcta o
			// mal anidada) llegan igual a reparar_props_contenido_ia().
			if ( ! isset( $props_crudas['_estilo_bloque'] ) && is_array( $nodo['_estilo_bloque'] ?? null ) ) {
				$props_crudas['_estilo_bloque'] = $nodo['_estilo_bloque'];
			}
			$props_reparadas = self::reparar_props_contenido_ia( $tipo, $props_crudas, $avisos );

			$nodo_reparado = array(
				'id'   => $id,
				'tipo' => $tipo,
			);
			if ( ! empty( $props_reparadas ) ) {
				$nodo_reparado['props'] = $props_reparadas;
			}
			if ( ! empty( $hijos_reparados ) ) {
				$nodo_reparado['hijos'] = $hijos_reparados;
			}
			$resultado[] = $nodo_reparado;
		}

		return $resultado;
	}

	/**
	 * Descarta, de $props_crudas, cualquier clave que
	 * Sofia_Componente_Factory::schema_contenido_de($tipo) no declare para
	 * este $tipo — ver la regla 3 del comentario largo en
	 * validar_y_reparar_arbol_ia(). Un $tipo sin schema de contenido
	 * (Container, o un tipo que aún no lo declaró) descarta TODAS las
	 * props de CONTENIDO — no hay ninguna clave válida a la que
	 * aferrarse (pero SÍ puede tener "_estilo_bloque" válida, ver abajo).
	 *
	 * "_estilo_bloque" (con guion bajo inicial, reservado — ver
	 * Sofia_Componente::atributo_estilo_bloque()) es el caso especial que
	 * habilita la "capa 1" de composición por IA (ver la memoria de
	 * producto, conversación de arquitectura sobre creatividad del
	 * generador): en vez de una clave de CONTENIDO más, es un array
	 * anidado con sus PROPIAS claves de estilo, reparado por separado
	 * contra Sofia_Componente_Factory::schema_estilo_ia_de($tipo) — nunca
	 * confundir con las demás claves de $props_crudas, que van contra
	 * schema_contenido_de(). Sin este caso especial, un Container (que no
	 * tiene NINGÚN schema de contenido) descartaría TODA prop que la IA
	 * le pusiera, incluida su dirección/gap — justo el vocabulario de
	 * composición que más falta hace ahí.
	 *
	 * No valida el VALOR de cada prop (ej. que "imagen" sea de verdad una
	 * URL, o que "ancho" sea una opción real del <select>) — eso queda
	 * para el render real (Sofia_Componente::render()/
	 * clases_utilitarias_bloque() ya validan value contra su propia
	 * whitelist, ver class-componente.php), mismo criterio de "cada capa
	 * valida lo que le corresponde" que el resto del sistema: un valor de
	 * estilo inventado simplemente no genera ninguna clase/declaración
	 * CSS, no rompe el render.
	 *
	 * @param array<string,mixed> $props_crudas
	 * @param string[]            $avisos
	 * @return array<string,mixed>
	 */
	private static function reparar_props_contenido_ia( string $tipo, array $props_crudas, array &$avisos ): array {
		$estilo_crudo = is_array( $props_crudas['_estilo_bloque'] ?? null ) ? $props_crudas['_estilo_bloque'] : array();
		unset( $props_crudas['_estilo_bloque'] );

		$props_validas = self::reparar_props_contenido_de_tipo_ia( $tipo, $props_crudas, $avisos );

		if ( ! empty( $estilo_crudo ) ) {
			$estilo_valido = self::reparar_estilo_bloque_ia( $tipo, $estilo_crudo, $avisos );
			if ( ! empty( $estilo_valido ) ) {
				$props_validas['_estilo_bloque'] = $estilo_valido;
			}
		}

		return $props_validas;
	}

	/**
	 * Mitad de reparar_props_contenido_ia() que se ocupa de las props de
	 * CONTENIDO (todo lo que no sea "_estilo_bloque") — extraído a su
	 * propio método cuando se agregó el caso especial de estilo, para que
	 * ninguno de los dos quede mezclado con lógica del otro.
	 *
	 * @param array<string,mixed> $props_crudas
	 * @param string[]            $avisos
	 * @return array<string,mixed>
	 */
	private static function reparar_props_contenido_de_tipo_ia( string $tipo, array $props_crudas, array &$avisos ): array {
		$schema_contenido = Sofia_Componente_Factory::schema_contenido_de( $tipo );
		if ( ! is_array( $schema_contenido ) || empty( $schema_contenido ) ) {
			if ( ! empty( $props_crudas ) ) {
				$avisos[] = sprintf( 'Un bloque tipo "%s" no tiene campos de contenido declarados — se descartaron %d prop(s) que la IA le puso.', $tipo, count( $props_crudas ) );
			}
			return array();
		}

		$props_validas = array();
		foreach ( $props_crudas as $clave => $valor ) {
			if ( ! isset( $schema_contenido[ $clave ] ) ) {
				$avisos[] = sprintf( 'Un bloque tipo "%s" tenía un campo "%s" que no existe para ese tipo — se descartó.', $tipo, (string) $clave );
				continue;
			}
			$props_validas[ $clave ] = $valor;
		}
		return $props_validas;
	}

	/**
	 * Repara "_estilo_bloque" contra
	 * Sofia_Componente_Factory::schema_estilo_ia_de($tipo) — mismo
	 * criterio de "clave no declarada se descarta" que
	 * reparar_props_contenido_de_tipo_ia(), pero contra el schema de
	 * ESTILO acotado (ancho/alineación + lo que declare schema_propio()
	 * del tipo, ver el comentario largo en schema_estilo_ia_de()) en vez
	 * del de contenido. Un tipo sin schema de estilo IA (no debería pasar
	 * nunca — todo tipo real tiene al menos "ancho"/"alineacion_bloque"
	 * del genérico) descarta todo, mismo criterio defensivo.
	 *
	 * @param array<string,mixed> $estilo_crudo
	 * @param string[]            $avisos
	 * @return array<string,mixed>
	 */
	private static function reparar_estilo_bloque_ia( string $tipo, array $estilo_crudo, array &$avisos ): array {
		$schema_estilo = Sofia_Componente_Factory::schema_estilo_ia_de( $tipo );
		if ( ! is_array( $schema_estilo ) || empty( $schema_estilo ) ) {
			if ( ! empty( $estilo_crudo ) ) {
				$avisos[] = sprintf( 'Un bloque tipo "%s" no tiene controles de estilo disponibles para la IA — se descartó el estilo propuesto.', $tipo );
			}
			return array();
		}

		$estilo_valido = array();
		foreach ( $estilo_crudo as $clave => $valor ) {
			if ( ! isset( $schema_estilo[ $clave ] ) ) {
				$avisos[] = sprintf( 'Un bloque tipo "%s" tenía un control de estilo "%s" que no está disponible para la IA — se descartó.', $tipo, (string) $clave );
				continue;
			}
			$estilo_valido[ $clave ] = $valor;
		}
		return $estilo_valido;
	}

	/**
	 * generar_id_bloque_ia(): MISMO formato que store.GenerarIDBloque() del
	 * lado GoPress (8 caracteres hex, de 4 bytes al azar) — ver el
	 * comentario largo en la regla 2 de validar_y_reparar_arbol_ia() sobre
	 * por qué se replica acá en vez de pedírselo a GoPress. random_bytes()
	 * (no mt_rand/wp_generate_password) por el mismo motivo que el lado Go
	 * usa crypto/rand: no hace falta que sea criptográficamente
	 * impredecible (es solo un identificador corto, no un secreto), pero
	 * random_bytes() ya está disponible en PHP 7+ sin dependencias extra y
	 * da la misma distribución uniforme que bin2hex(random_bytes(4)) —
	 * forma más directa de llegar a "8 hex" que armar un charset a mano.
	 */
	private static function generar_id_bloque_ia(): string {
		try {
			return bin2hex( random_bytes( 4 ) );
		} catch ( \Exception $e ) {
			// random_bytes() puede fallar en teoría si el sistema no tiene
			// ninguna fuente segura de aleatoriedad disponible — caso
			// extremo que nunca se vio en la práctica, pero mejor un ID
			// igual (con uniqid, mucho menos ideal pero nunca vacío) que
			// dejar el bloque sin id y romper el guardado.
			return substr( str_replace( '.', '', uniqid( '', true ) ), 0, 8 );
		}
	}

	/**
	 * POST /wp-json/sofia/v1/ia/preview — Fase 4: recibe {arbol} (el ya
	 * reparado por sofia/v1/ia/generar, confirmado por el usuario que
	 * quiere previsualizarlo) e instancia Sofia_Pagina/
	 * Sofia_Componente_Factory::crear() recursivo contra ESE árbol en
	 * MEMORIA — NUNCA lo persiste en GoPress, ni siquiera de forma
	 * temporal. Mismo renderer real que cualquier página de producción
	 * (Sofia_Componente::render()), mismo criterio de seguridad del plan:
	 * el LLM nunca escribe código que se ejecuta, solo datos que pasan por
	 * el mismo validador/renderer que ya existen para el editor manual.
	 *
	 * $contenido se arma A PARTIR del árbol mismo (no viene de GoPress,
	 * esta página no existe todavía como PaginaSitio real) — cada prop de
	 * cada nodo se aplana a la notación "{id}.campo" que Sofia_Pagina ya
	 * sabe leer (ver props_de_bloque()), mismo shape exacto que
	 * PaginaSitio.Contenido tendría si este árbol ya estuviera guardado.
	 * Sofia_Modo_Editor::activo() se ignora a propósito acá (este preview
	 * no pasa por el flujo normal de ?sofia_editor=1) — el HTML devuelto es
	 * el de una visita PÚBLICA real, sin chrome de edición (handles,
	 * botones "+ Agregar item", etc.), que es lo que el usuario espera ver
	 * en la vista previa antes de aplicar.
	 */
	public static function ia_preview_arbol( WP_REST_Request $request ) {
		$arbol = $request->get_param( 'arbol' );
		if ( ! is_array( $arbol ) || empty( $arbol ) ) {
			return new WP_Error( 'sofia_arbol_requerido', 'El parámetro "arbol" necesita al menos un bloque.', array( 'status' => 400 ) );
		}

		$contenido = array();
		self::aplanar_contenido_arbol_ia( $arbol, $contenido );

		$sofia_pagina = new Sofia_Pagina( $arbol, $contenido );
		$html         = '';
		foreach ( $sofia_pagina->componentes() as $componente ) {
			if ( ! $componente->bloque_visible() ) {
				// Mismo criterio que page.php en visita pública real: un
				// bloque con condición de Visibilidad no cumplida no se
				// renderiza en absoluto (nunca solo se oculta con CSS) —
				// ver Sofia_Componente::bloque_visible().
				continue;
			}
			$html .= $componente->render();
		}

		return rest_ensure_response( array( 'ok' => true, 'html' => $html ) );
	}

	/**
	 * Aplana recursivamente {id,tipo,props,hijos} → {"{id}.campo": valor,
	 * ...} — mismo shape que PaginaSitio.Contenido (y lo que
	 * Sofia_Pagina::props_de_bloque() espera encontrar), necesario porque
	 * ia_preview_arbol() recibe el árbol con las props YA anidadas dentro
	 * de cada nodo (más natural para el LLM/frontend), pero Sofia_Pagina
	 * fue diseñada para recortar un mapa PLANO por prefijo de ID — en vez
	 * de duplicar esa lógica de recorte, se aplana acá una sola vez al
	 * shape que la clase ya sabe consumir.
	 *
	 * @param array<int,array<string,mixed>> $nodos
	 * @param array<string,mixed>            $contenido (por referencia)
	 */
	private static function aplanar_contenido_arbol_ia( array $nodos, array &$contenido ): void {
		foreach ( $nodos as $nodo ) {
			if ( ! is_array( $nodo ) ) {
				continue;
			}
			$id    = (string) ( $nodo['id'] ?? '' );
			$props = is_array( $nodo['props'] ?? null ) ? $nodo['props'] : array();
			if ( '' !== $id ) {
				foreach ( $props as $campo => $valor ) {
					$contenido[ "{$id}.{$campo}" ] = $valor;
				}
			}
			$hijos = is_array( $nodo['hijos'] ?? null ) ? $nodo['hijos'] : array();
			if ( ! empty( $hijos ) ) {
				self::aplanar_contenido_arbol_ia( $hijos, $contenido );
			}
		}
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
	 * panel Preact manda el objeto {roles, tipografia} completo cada vez
	 * — shape simplificado, ver Sofia_Estilo_Global::ROLES_SITIO).
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

	/**
	 * GET /wp-json/sofia/v1/estilo-global/roles — los 13 roles de Estilo
	 * Global (ver Sofia_Estilo_Global::roles_sitio()) con su categoría de
	 * CF/etiqueta humana — PanelEstiloGlobal.jsx arma sus controles a
	 * partir de esto en vez de tenerlos hardcodeados, mismo criterio "PHP
	 * es la fuente de verdad de qué controles existen" que ya rige Nivel 2
	 * desde Fase 2.
	 */
	public static function roles_estilo_global( WP_REST_Request $request ) {
		return rest_ensure_response( Sofia_Estilo_Global::roles_sitio() );
	}

	/**
	 * GET /wp-json/sofia/v1/core-framework/variables — nombres de custom
	 * properties definidas en el CSS real de Core Framework, YA AGRUPADOS
	 * por categoría (ver Sofia_Estilo_Global::variables_core_framework_por_
	 * categoria()) — {color:[...], texto:[...], radius:[...], shadow:[...],
	 * space:[...], otras:[...]}. Agrupado (no la lista plana de antes) para
	 * que cada CampoConToken pueda sugerir solo lo relevante a SU propiedad
	 * (nunca "bg-body" como sugerencia en un campo de Radio de borde) — ver
	 * el bug real reportado por el usuario que motivó este cambio. Array
	 * vacío (nunca error) si el plugin no está activo o el archivo no se
	 * pudo leer — el panel simplemente no muestra sugerencias en ese caso,
	 * el campo de texto sigue funcionando igual.
	 */
	public static function variables_core_framework( WP_REST_Request $request ) {
		return rest_ensure_response( Sofia_Estilo_Global::variables_core_framework_por_categoria() );
	}

	/**
	 * GET /wp-json/sofia/v1/core-framework/tokens-visual — catálogo de
	 * tokens CON ETIQUETA HUMANA + preview (ver
	 * Sofia_Estilo_Global::catalogo_tokens_visual()), consumido por
	 * CampoTokenVisual.jsx (admin-app) para el selector visual — decisión
	 * de arquitectura de esta conversación (ver la memoria de producto):
	 * "Espaciado: Compacto/Base/Amplio", no un <input> de texto libre con
	 * 154 nombres técnicos como sugerencia. Endpoint SEPARADO de
	 * /core-framework/variables (que sigue sirviendo al modo "avanzado" de
	 * CampoConToken.jsx sin cambios) — mismo criterio de "básico vs
	 * avanzado" que el resto de esta pieza: el catálogo visual es
	 * deliberadamente MÁS CHICO (5-6 pasos por escala, colores BASE sin
	 * variantes de tono) que el catálogo completo de 154 variables.
	 */
	public static function tokens_core_framework_visual( WP_REST_Request $request ) {
		return rest_ensure_response( Sofia_Estilo_Global::catalogo_tokens_visual() );
	}

	/**
	 * GET /wp-json/sofia/v1/core-framework/variables-con-valor — las 154
	 * variables reales AGRUPADAS por categoría, cada una con su VALOR
	 * crudo (ver Sofia_Estilo_Global::variables_core_framework_por_categoria_con_valor())
	 * — alimenta el selector "Avanzado" propio (SelectorTokenAvanzado.jsx),
	 * que reemplaza el <datalist> nativo (sin soporte real de preview) por
	 * un dropdown Preact que sí muestra un swatch por variante. Bug real
	 * corregido tras probar en vivo: el usuario reportó que el modo
	 * avanzado (antes datalist con solo nombres) era "a granel" — no se
	 * entendía qué era "tertiary-30" sin verlo pintado.
	 */
	public static function variables_core_framework_con_valor( WP_REST_Request $request ) {
		return rest_ensure_response( Sofia_Estilo_Global::variables_core_framework_por_categoria_con_valor() );
	}
}

add_action( 'rest_api_init', array( 'Sofia_REST_Editor', 'registrar_rutas' ) );

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

	/**
	 * Trae el estilo GLOBAL del sitio (Nivel 3 — paleta de colores/
	 * tipografía base, distinto del contenido de una página individual) vía
	 * GET /sites/{sitio}/tema/estilo-global?token=... (ver
	 * internal/server/estilo_global.go, handleObtenerEstiloGlobalParaTema).
	 * Llamado en CADA render público (ver Sofia_Tema::imprimir_estilo_global
	 * o similar) para imprimir las custom properties en el <head> — mismo
	 * criterio "JSON opaco" que obtener_pagina(): el shape interno no se
	 * valida acá, la whitelist real vive en Sofia_Componente.
	 *
	 * @return array<string,mixed> Array vacío si GoPress no está
	 *         configurado, la petición falla, o el sitio no tiene estilo
	 *         global guardado — nunca null, para que el llamador no
	 *         necesite un chequeo aparte antes de iterarlo.
	 */
	public static function obtener_estilo_global(): array {
		if ( ! defined( 'SOFIA_GOPRESS_URL' ) || ! defined( 'SOFIA_GOPRESS_TOKEN' ) ) {
			return array();
		}

		$nombre_sitio = defined( 'SOFIA_GOPRESS_SITIO' ) ? SOFIA_GOPRESS_SITIO : '';
		if ( '' === $nombre_sitio ) {
			return array();
		}

		$url = trailingslashit( SOFIA_GOPRESS_URL ) . 'sites/' . rawurlencode( $nombre_sitio ) . '/tema/estilo-global';
		$url = add_query_arg( 'token', SOFIA_GOPRESS_TOKEN, $url );

		$respuesta = wp_remote_get(
			$url,
			array(
				'timeout' => 5,
			)
		);
		if ( is_wp_error( $respuesta ) || 200 !== wp_remote_retrieve_response_code( $respuesta ) ) {
			return array();
		}

		$cuerpo = json_decode( wp_remote_retrieve_body( $respuesta ), true );
		$estilo = is_array( $cuerpo ) ? ( $cuerpo['estilo_global'] ?? null ) : null;
		return is_array( $estilo ) ? $estilo : array();
	}

	/**
	 * obtener_componentes_generados(): los Componentes cuya forma viene de
	 * una descripción guardada en GoPress, no de una clase PHP del tema
	 * (ver Sofia_Componente_Generado).
	 *
	 * Se cachea en memoria durante la request: el factory la consulta una
	 * vez por cada bloque generado de la página, y sin caché una página
	 * con cinco de estos haría cinco requests HTTP idénticos.
	 *
	 * Un sitio sin componentes generados —que es el caso normal— devuelve
	 * un array vacío y no vuelve a pedir nada: el array vacío también se
	 * cachea, así que no se reintenta en cada bloque.
	 *
	 * @return array<string,array<string,mixed>> tipo => definición.
	 */
	public static function obtener_componentes_generados(): array {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}
		$cache = array();

		if ( ! defined( 'SOFIA_GOPRESS_URL' ) || ! defined( 'SOFIA_GOPRESS_TOKEN' ) ) {
			return $cache;
		}
		$nombre_sitio = defined( 'SOFIA_GOPRESS_SITIO' ) ? SOFIA_GOPRESS_SITIO : '';
		if ( '' === $nombre_sitio ) {
			return $cache;
		}

		$url = trailingslashit( SOFIA_GOPRESS_URL ) . 'sites/' . rawurlencode( $nombre_sitio ) . '/tema/componentes-generados';
		$url = add_query_arg( 'token', SOFIA_GOPRESS_TOKEN, $url );

		$respuesta = wp_remote_get( $url, array( 'timeout' => 5 ) );
		if ( is_wp_error( $respuesta ) || 200 !== wp_remote_retrieve_response_code( $respuesta ) ) {
			return $cache;
		}

		$cuerpo = json_decode( wp_remote_retrieve_body( $respuesta ), true );
		$lista  = is_array( $cuerpo ) ? ( $cuerpo['componentes'] ?? null ) : null;
		if ( ! is_array( $lista ) ) {
			return $cache;
		}

		foreach ( $lista as $fila ) {
			if ( ! is_array( $fila ) || ! is_array( $fila['definicion'] ?? null ) ) {
				continue;
			}
			// Se REVALIDA acá aunque el tema ya la haya validado antes de
			// guardarla: el dato viaja por la red y vive en una base de
			// datos que otras cosas pueden tocar. Validar solo al escribir
			// deja el render confiando en que nadie alteró la fila.
			$avisos = array();
			$definicion = Sofia_Definicion_Generada::validar( $fila['definicion'], $avisos );
			if ( null === $definicion ) {
				continue;
			}
			$cache[ $definicion['tipo'] ] = $definicion;
		}

		return $cache;
	}

	/**
	 * Guarda el estilo GLOBAL completo del sitio — llamado por el proxy
	 * REST del editor (panel "Estilo global" en la barra superior, distinto
	 * del drawer por campo/bloque). Manda a
	 * PUT /sites/{sitio}/estilo-global?token=... (ver
	 * internal/server/estilo_global.go, handleActualizarEstiloGlobal:
	 * acepta cookie de sesión O token, mismo mecanismo dual que
	 * guardar_contenido()).
	 *
	 * generar_componente_ia(): le pide a la IA que DESCRIBA un Componente
	 * nuevo, uno que el catálogo fijo no sabe dibujar.
	 *
	 * Distinto de generar_arbol_ia(), que ELIGE entre los tipos que ya
	 * existen y arma una página. Este produce un tipo nuevo.
	 *
	 * No manda el catálogo: lo que el modelo tiene que conocer son los
	 * límites de la DESCRIPCIÓN (qué etiquetas HTML puede usar, qué tipos
	 * de campo existen), no los Componentes del tema — está inventando uno
	 * que justamente no está ahí. Esos límites viven en el system prompt
	 * del lado de GoPress.
	 *
	 * Sí manda el estilo global, para que el CSS generado use los tokens
	 * del sitio en vez de colores inventados.
	 *
	 * @param array<string,mixed> $estilo_global
	 * @return array<string,mixed>|null La descripción cruda, o null si
	 *         GoPress no respondió. Se valida del lado del tema
	 *         (Sofia_Definicion_Generada) antes de usarla.
	 */
	public static function generar_componente_ia( string $prompt, array $estilo_global = array() ): ?array {
		if ( ! defined( 'SOFIA_GOPRESS_URL' ) || ! defined( 'SOFIA_GOPRESS_TOKEN' ) ) {
			return null;
		}
		$nombre_sitio = defined( 'SOFIA_GOPRESS_SITIO' ) ? SOFIA_GOPRESS_SITIO : '';
		if ( '' === $nombre_sitio ) {
			return null;
		}

		$url = trailingslashit( SOFIA_GOPRESS_URL ) . 'sites/' . rawurlencode( $nombre_sitio ) . '/ia/generar-componente';
		$url = add_query_arg( 'token', SOFIA_GOPRESS_TOKEN, $url );

		$respuesta = wp_remote_request(
			$url,
			array(
				'method'  => 'POST',
				// Mismo timeout largo que generar_arbol_ia: describir un
				// componente es una respuesta más corta que un árbol de
				// página, pero sigue siendo una llamada a un LLM.
				'timeout' => 180,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'prompt'        => $prompt,
						'estilo_global' => $estilo_global,
					)
				),
			)
		);
		if ( is_wp_error( $respuesta ) || 200 !== wp_remote_retrieve_response_code( $respuesta ) ) {
			return null;
		}

		$cuerpo     = json_decode( wp_remote_retrieve_body( $respuesta ), true );
		$definicion = is_array( $cuerpo ) ? ( $cuerpo['definicion'] ?? null ) : null;
		return is_array( $definicion ) ? $definicion : null;
	}

	/**
	 * guardar_componente_generado(): sube la descripción al catálogo del
	 * sitio, para que quede disponible en TODAS sus páginas.
	 *
	 * Esa persistencia es la diferencia de fondo con el generador de
	 * árboles: un árbol generado se aplica a una página y ahí termina; un
	 * Componente generado se crea una vez y se usa siempre.
	 *
	 * @param array<string,mixed> $definicion Ya validada por
	 *                                        Sofia_Definicion_Generada.
	 * @return bool true si GoPress confirmó el guardado.
	 */
	public static function guardar_componente_generado( array $definicion ): bool {
		if ( ! defined( 'SOFIA_GOPRESS_URL' ) || ! defined( 'SOFIA_GOPRESS_TOKEN' ) ) {
			return false;
		}
		$nombre_sitio = defined( 'SOFIA_GOPRESS_SITIO' ) ? SOFIA_GOPRESS_SITIO : '';
		if ( '' === $nombre_sitio || empty( $definicion['tipo'] ) ) {
			return false;
		}

		$url = trailingslashit( SOFIA_GOPRESS_URL ) . 'sites/' . rawurlencode( $nombre_sitio ) . '/componentes-generados';
		$url = add_query_arg( 'token', SOFIA_GOPRESS_TOKEN, $url );

		$respuesta = wp_remote_request(
			$url,
			array(
				'method'  => 'PUT',
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'tipo'       => $definicion['tipo'],
						'nombre'     => $definicion['nombre'] ?? $definicion['tipo'],
						'definicion' => $definicion,
					)
				),
			)
		);
		return ! is_wp_error( $respuesta ) && 200 === wp_remote_retrieve_response_code( $respuesta );
	}

	/**
	 * @param array<string,mixed> $estilo_global
	 * @return bool true si GoPress confirmó el guardado (200 OK).
	 */
	public static function guardar_estilo_global( array $estilo_global ): bool {
		if ( ! defined( 'SOFIA_GOPRESS_URL' ) || ! defined( 'SOFIA_GOPRESS_TOKEN' ) ) {
			return false;
		}

		$nombre_sitio = defined( 'SOFIA_GOPRESS_SITIO' ) ? SOFIA_GOPRESS_SITIO : '';
		if ( '' === $nombre_sitio ) {
			return false;
		}

		$url = trailingslashit( SOFIA_GOPRESS_URL ) . 'sites/' . rawurlencode( $nombre_sitio ) . '/estilo-global';
		$url = add_query_arg( 'token', SOFIA_GOPRESS_TOKEN, $url );

		$respuesta = wp_remote_request(
			$url,
			array(
				'method'  => 'PUT',
				'timeout' => 5,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array( 'estilo_global' => $estilo_global ) ),
			)
		);
		if ( is_wp_error( $respuesta ) ) {
			return false;
		}

		return 200 === wp_remote_retrieve_response_code( $respuesta );
	}

	/**
	 * generar_arbol_ia(): Fase 4 ("primitivas de layout" — generador de
	 * árboles de bloques por IA, ver la memoria de producto). Llama a
	 * POST /sites/{sitio}/ia/generar-arbol?token=... (ver
	 * internal/server/ia_generar_arbol.go de GoPress) — mismo mecanismo de
	 * autenticación por AgenteToken que el resto de este cliente, sin
	 * cookie de sesión (WordPress corre server-side, dentro del proxy REST
	 * de wp-admin, nunca directo desde el navegador — ver
	 * Sofia_REST_Editor::ia_generar_arbol()).
	 *
	 * $catalogo viaja COMPLETO en el body (tipos + AMBOS schemas, ver
	 * Sofia_Componente_Factory::catalogo_para_ia()) — GoPress es AGNÓSTICO
	 * de qué Componentes PHP existen en este tema (mismo principio que
	 * catalogo_bloques() ya establece: el catálogo real vive en el tema,
	 * nunca curado aparte del lado de GoPress), así que cada request le
	 * manda el catálogo actual en vez de que GoPress lo conozca de
	 * antemano.
	 *
	 * timeout 180s (no los 5s del resto de este cliente) — generar un árbol
	 * completo con un LLM tarda mucho más que cualquier otra llamada a
	 * GoPress (que son todas operaciones locales de SQLite), mismo
	 * criterio de timeout generoso que CraftTreeAssistantService (ecommerce,
	 * ver la memoria de producto de esta fase) usa para el mismo tipo de
	 * llamada.
	 *
	 * $estilo_global (Nivel 3 — paleta/tipografía del SITIO, ver
	 * obtener_estilo_global() arriba) viaja también en el body — "capa 2"
	 * de la conversación de arquitectura sobre creatividad del generador
	 * (ver la memoria de producto): sin esto, el LLM diseñaba a ciegas sin
	 * saber los colores/fuentes reales del sitio. Puede ser array vacío
	 * (sitio sin estilo global configurado) — GoPress simplemente omite
	 * esa sección del prompt en ese caso, nunca es un error.
	 *
	 * @param array<int,array<string,mixed>> $catalogo
	 * @param array<string,mixed>            $estilo_global
	 * @return array{arbol:array<int,array<string,mixed>>,avisos:string[]}|null
	 *         null si GoPress no está configurado, la request falla, o
	 *         GoPress devuelve un error (OPENROUTER_API_KEY no configurada
	 *         del lado GoPress, timeout de OpenRouter, respuesta no
	 *         parseable como JSON — todos casos que GoPress ya distingue
	 *         con su propio mensaje de error, pero este cliente solo
	 *         necesita saber "funcionó o no" para decidir qué WP_Error
	 *         devolver, ver Sofia_REST_Editor::ia_generar_arbol()).
	 */
	public static function generar_arbol_ia( string $prompt, array $catalogo, array $estilo_global = array() ): ?array {
		if ( ! defined( 'SOFIA_GOPRESS_URL' ) || ! defined( 'SOFIA_GOPRESS_TOKEN' ) ) {
			return null;
		}

		$nombre_sitio = defined( 'SOFIA_GOPRESS_SITIO' ) ? SOFIA_GOPRESS_SITIO : '';
		if ( '' === $nombre_sitio ) {
			return null;
		}

		$url = trailingslashit( SOFIA_GOPRESS_URL ) . 'sites/' . rawurlencode( $nombre_sitio ) . '/ia/generar-arbol';
		$url = add_query_arg( 'token', SOFIA_GOPRESS_TOKEN, $url );

		$respuesta = wp_remote_request(
			$url,
			array(
				'method'  => 'POST',
				// 180s: ver el comentario largo arriba — generar un árbol
				// completo con un LLM es, por lejos, la request más lenta
				// de todo este cliente.
				'timeout' => 180,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'prompt'        => $prompt,
						'catalogo'      => $catalogo,
						'estilo_global' => $estilo_global,
					)
				),
			)
		);
		if ( is_wp_error( $respuesta ) ) {
			return null;
		}
		if ( 200 !== wp_remote_retrieve_response_code( $respuesta ) ) {
			return null;
		}

		$cuerpo = json_decode( wp_remote_retrieve_body( $respuesta ), true );
		if ( ! is_array( $cuerpo ) || ! isset( $cuerpo['arbol'] ) ) {
			return null;
		}

		return array(
			'arbol'  => is_array( $cuerpo['arbol'] ) ? $cuerpo['arbol'] : array(),
			'avisos' => is_array( $cuerpo['avisos'] ?? null ) ? $cuerpo['avisos'] : array(),
		);
	}
}

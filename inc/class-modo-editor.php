<?php
/**
 * Sofia_Modo_Editor activa la edición in-place dentro del iframe del panel
 * de admin (ver inc/js/editor-iframe.js) — cuando la visita trae
 * "?sofia_editor=1" Y el usuario tiene permiso real de edición, O cuando la
 * petición actual es la REST API propia de Sofia Studio (namespace
 * "sofia/v1") con ese mismo permiso. Un visitante normal jamás carga este
 * JS ni ve ningún elemento contenteditable: ninguna de las dos condiciones
 * por sí sola alcanza, current_user_can() es lo que de verdad protege esto
 * en ambos casos (sin sesión de wp-admin, WordPress nunca reporta ese
 * permiso, sin importar qué traiga la URL o la ruta REST).
 *
 * Bug real que agregó la rama REST (reportado por el usuario probando
 * Video/Embed/Avatar recién insertados: "no sale nada, no se puede
 * cambiar"): el HTML de un bloque recién agregado no se pinta recargando
 * el iframe completo, se inyecta puntual vía
 * "sofia/v1/paginas/{slug}/bloque/{id}" (ver
 * Sofia_REST_Editor::obtener_html_de_bloque(), invocado desde
 * admin-app/src/App.jsx) — esa petición NUNCA trae "?sofia_editor=1" en su
 * URL, así que activo() daba false ahí, sin importar que el usuario
 * estuviera autenticado y editando activamente. imagen_o_placeholder()
 * (ver class-componente.php) depende de este método para decidir si
 * imprime el placeholder clickeable — sin la rama REST, cualquier
 * Componente cuyo ÚNICO contenido visible en vacío sea ese placeholder
 * (Video, Avatar) quedaba con una <section> completamente vacía, sin nada
 * clickeable, la primera vez que se insertaba. Ya afectaba a Image/Hero
 * también (mismo mecanismo), solo que ahí nunca se notó porque ambos se
 * prueban casi siempre ya con una imagen cargada.
 *
 * defined('REST_REQUEST') — constante que WordPress define true durante
 * CUALQUIER petición a wp-json (ver wp-includes/rest-api.php core), no
 * específica de Sofia Studio por sí sola — por eso se combina con chequear
 * el namespace real de la ruta actual (rest_get_url_prefix() + "sofia/v1"),
 * para no ensanchar el modo editor a peticiones REST de otros plugins.
 */
class Sofia_Modo_Editor {

	public static function activo(): bool {
		if ( isset( $_GET['sofia_editor'] ) ) {
			return current_user_can( 'edit_pages' );
		}
		return self::es_peticion_rest_propia() && current_user_can( 'edit_pages' );
	}

	/**
	 * es_peticion_rest_propia(): true solo durante una petición REST real
	 * hacia el namespace "sofia/v1" — nunca durante una carga normal de
	 * página (REST_REQUEST no definida ahí) ni durante la REST API de
	 * outro plugin/core de WordPress (ese "sofia/v1" en la URL no
	 * matchea). $_SERVER['REQUEST_URI'] (no $_GET['rest_route'], que solo
	 * existe con pretty permalinks desactivados) es la forma real de leer
	 * la ruta pedida en ambos modos de permalink de WordPress.
	 */
	private static function es_peticion_rest_propia(): bool {
		if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
			return false;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		return false !== strpos( $uri, '/' . rest_get_url_prefix() . '/sofia/v1/' );
	}

	/**
	 * Encola el script de edición in-place dentro del iframe, más la
	 * librería de medios de WordPress que wp.media() necesita.
	 *
	 * El canvas es flujo normal, un espejo fiel del sitio público: no hay
	 * ningún CSS "solo modo editor" que simule posiciones. Reordenar
	 * bloques o items de listas no se arrastra sobre el canvas — se hace
	 * desde el panel de Estructura (PanelEstructura.jsx/App.jsx), que le
	 * pide a este script mover un nodo puntual del DOM real.
	 */
	public static function encolar_script(): void {
		if ( ! self::activo() ) {
			return;
		}
		wp_enqueue_media(); // necesario para que wp.media() esté disponible dentro del iframe.
		wp_enqueue_script(
			'sofia-editor-iframe',
			get_stylesheet_directory_uri() . '/inc/js/editor-iframe.js',
			array(),
			wp_get_theme()->get( 'Version' ),
			true
		);

		// Bug real encontrado en la práctica: el fetch a
		// sofia/v1/catalogo-bloques desde dentro del iframe daba 401
		// "rest_forbidden" a pesar de la sesión de admin activa — la REST
		// API de WordPress exige el nonce (X-WP-Nonce) además de la
		// cookie de sesión, como protección CSRF, y este script corre en
		// el FRONT PÚBLICO (no en wp-admin, donde WordPress inyecta
		// wpApiSettings automáticamente) — hay que generarlo acá a mano,
		// mismo wp_create_nonce('wp_rest') que ya usa
		// Sofia_Panel_Editor::encolar_assets() para el panel padre.
		wp_localize_script(
			'sofia-editor-iframe',
			'SofiaEditorIframeConfig',
			array( 'nonce' => wp_create_nonce( 'wp_rest' ) )
		);
	}

	/**
	 * Oculta #wpadminbar por CSS dentro del iframe — a prueba de que algún
	 * plugin de terceros del sitio siga forzando is_admin_bar_showing() a
	 * true a pesar del filtro "show_admin_bar" de abajo (bug real
	 * encontrado en la práctica contra un sitio con muchos plugins
	 * activos: el filtro con prioridad PHP_INT_MAX no bastó). El CSS
	 * siempre gana, sin importar qué decida la lógica PHP de admin bar.
	 */
	public static function imprimir_css_ocultar_admin_bar(): void {
		if ( ! self::activo() ) {
			return;
		}
		echo '<style>#wpadminbar { display: none !important; } html { margin-top: 0 !important; }</style>';
	}

	/**
	 * CSS que SOLO debe existir cuando el modo editor está activo, nunca en
	 * una visita pública normal — inyectado inline en vez de un archivo
	 * .css propio, mismo criterio que imprimir_css_ocultar_admin_bar.
	 *
	 * Es puro chrome de edición (marcas de bloque oculto, badge de token,
	 * botón de agregar item, líneas de inserción): nada acá cambia el
	 * LAYOUT del contenido. El canvas es flujo normal, el mismo layout
	 * exacto que ve un visitante del sitio público — esa fidelidad 1:1 es
	 * deliberada, así que ningún CSS de este método debe posicionar ni
	 * dimensionar bloques.
	 */
	public static function imprimir_css_reordenar(): void {
		if ( ! self::activo() ) {
			return;
		}
		// Colores fijos (no variables CSS) en todo este bloque: se inyecta
		// DENTRO del iframe, en el documento del sitio real — no tiene
		// acceso a las custom properties del panel padre (ver
		// admin-app/src/style.css, que sí las define para su propio
		// documento). Mismos valores hexadecimales exactos del mockup de
		// diseño original ("Editor de Contenido", Artifact): --gp-panel
		// #1c1a17, --gp-accent #d97a4d — así el chrome de edición dentro
		// del iframe combina con el panel Preact que lo rodea, en vez de
		// un gris genérico sin relación con la paleta real del producto.
		echo '<style>
			/* Marca visual de "bloque oculto por condición de visibilidad"
			   (pestaña Visibilidad del drawer) — atributo en la propia
			   <section> (ver Sofia_Componente::atributos_seccion()). ::after
			   con el texto del propio atributo (content: attr(...)) evita
			   duplicar la etiqueta en un data-* Y en un elemento HTML aparte.
			   position:relative en la propia <section> (no heredado) — en
			   flujo normal ninguna <section> tiene position propio por
			   default, así que hace falta declararlo para poder anclar el
			   ::after en su esquina. */
			[data-sofia-oculto-condicion] {
				position: relative;
				outline: 2px dashed #d97a4d; outline-offset: -2px; opacity: 0.6;
			}
			[data-sofia-oculto-condicion]::after {
				content: attr(data-sofia-oculto-condicion);
				position: absolute; top: 8px; right: 8px; z-index: 3;
				background: #d97a4d; color: #1c1a17;
				font-size: 10px; font-weight: 600; padding: 3px 8px; border-radius: 5px;
			}

			/* Badge "CF" — marca visual de "este campo/bloque usa un token de
			   Core Framework en al menos UNA propiedad", pedido explícito del
			   usuario: hoy no hay forma de saberlo sin abrir el drawer de
			   cada campo/bloque uno por uno. Selector combinado: cualquiera
			   de los data-sofia-estilo-{clave} que SÍ pueden ser token (ver
			   Sofia_Componente::atributo_estilo()/atributo_estilo_bloque()) —
			   MISMA lista en ambos niveles, un solo selector cubre Nivel 1
			   (color/tamano_fuente, en el <h3>/<p> editable) y Nivel 2
			   (color_fondo/color_borde/radius/sombra/offset_x/
			   espaciado_vertical, en la <section>).
			   position:relative necesario para posicionar el ::after — ni
			   un <h3>/<p> (Nivel 1) ni una <section> (Nivel 2) tienen
			   position propio por default en flujo normal.
			   Solo visible en :hover (nunca permanente) — pedido explícito
			   del usuario, para no ensuciar el canvas en reposo cuando hay
			   varios campos con token en la misma pantalla. */
			[data-sofia-estilo-color],
			[data-sofia-estilo-tamano_fuente],
			[data-sofia-estilo-color_fondo],
			[data-sofia-estilo-color_borde],
			[data-sofia-estilo-radius],
			[data-sofia-estilo-sombra],
			[data-sofia-estilo-offset_x],
			[data-sofia-estilo-espaciado_vertical] {
				position: relative;
			}
			[data-sofia-estilo-color]::before,
			[data-sofia-estilo-tamano_fuente]::before,
			[data-sofia-estilo-color_fondo]::before,
			[data-sofia-estilo-color_borde]::before,
			[data-sofia-estilo-radius]::before,
			[data-sofia-estilo-sombra]::before,
			[data-sofia-estilo-offset_x]::before,
			[data-sofia-estilo-espaciado_vertical]::before {
				content: "CF"; display: none;
				position: absolute; top: -8px; right: -8px; z-index: 4;
				background: #4a3fd9; color: #fff;
				font-size: 9px; font-weight: 700; letter-spacing: 0.02em;
				padding: 2px 5px; border-radius: 4px; pointer-events: none;
			}
			[data-sofia-estilo-color]:hover::before,
			[data-sofia-estilo-tamano_fuente]:hover::before,
			[data-sofia-estilo-color_fondo]:hover::before,
			[data-sofia-estilo-color_borde]:hover::before,
			[data-sofia-estilo-radius]:hover::before,
			[data-sofia-estilo-sombra]:hover::before,
			[data-sofia-estilo-offset_x]:hover::before,
			[data-sofia-estilo-espaciado_vertical]:hover::before {
				display: block;
			}

			/* Botón "+ Agregar" al final de una lista repetible — ver
			   Sofia_Componente::boton_agregar_item(). Gris neutro (NO
			   naranja) — bug real encontrado en la práctica: con el mismo
			   naranja que la línea de inserción de bloque nuevo (ver
			   .sofia-linea-insertar más abajo), ambos "+" quedaban
			   indistinguibles cuando caían pegados (ej. al final de una
			   Franja, justo antes del borde del bloque siguiente). El
			   naranja de acento queda reservado EXCLUSIVAMENTE para
			   "agregar bloque completo" — un lenguaje de color consistente:
			   gris = acción dentro del bloque actual, naranja = acción
			   sobre la estructura de bloques de la página. Vive como
			   hermano de [data-sofia-lista] (ver
			   Sofia_Componente_Franja_Beneficios::render()), en flujo
			   normal: empuja la altura real de la <section> igual que
			   cualquier otro contenido. */
			.sofia-boton-agregar-item {
				display: block; width: 100%; margin-top: 8px;
				padding: 10px; background: transparent;
				border: 1px dashed #d8d3c9; border-radius: 6px;
				color: #6b6459; opacity: 0.75; cursor: pointer; font-size: 13px;
				font-family: "Inter", -apple-system, sans-serif;
			}
			.sofia-boton-agregar-item:hover { opacity: 1; border-color: #6b6459; background: rgba(107,100,89,0.08); }

			/* Línea de inserción ENTRE bloques — mismo patrón del mockup de
			   diseño original ("Editor de Contenido", Artifact): una raya
			   fina que solo se pinta al hover, con un botón "+" circular al
			   centro. Es hermana suelta de .sofia-pagina, en flujo normal
			   — más simple que reposicionarla a mano sobre cada borde de
			   sección. Que ensucie la lista de hijos no importa:
			   leerBloquesDesde() (editor-iframe.js) filtra explícitamente
			   por data-sofia-bloque-id/-tipo, ignorando cualquier otro
			   hermano. */
			.sofia-linea-insertar {
				height: 16px; margin: -8px 0; position: relative; z-index: 6;
				display: flex; align-items: center; justify-content: center;
			}
			/* --vacio (Fase 3): línea única dentro de un .sofia-container SIN
			   hijos — a diferencia de la línea normal (una raya angosta
			   entre dos bloques), acá no hay ningún bloque del que colgar,
			   así que esta variante ocupa TODO el alto del .sofia-container
			   vacío (min-height:40px, ver la regla de .sofia-container más
			   abajo) — el botón "+" queda centrado en ese espacio en vez de
			   pegado a un borde. Visible SIEMPRE (no solo al hover, a
			   diferencia de la línea normal): un container vacío no tiene
			   ningún otro contenido/affordance visual, sin esto sería un
			   rectángulo en blanco sin pista de que ahí se puede insertar
			   algo. */
			.sofia-linea-insertar--vacio {
				height: auto; margin: 0;
				border: 1.5px dashed #d97a4d33; border-radius: 4px;
			}
			.sofia-linea-insertar--vacio .sofia-linea-insertar__boton { opacity: 1; }
			.sofia-linea-insertar::before {
				content: ""; position: absolute; left: 0; right: 0; top: 50%;
				height: 1px; background: transparent; transition: background 0.15s ease-out;
			}
			.sofia-linea-insertar:hover::before { background: #d97a4d; }
			.sofia-linea-insertar__boton {
				position: relative; width: 22px; height: 22px; border-radius: 50%;
				border: 1.5px solid #d97a4d; background: #fff; color: #d97a4d;
				display: flex; align-items: center; justify-content: center;
				font-size: 15px; line-height: 1; cursor: pointer;
				opacity: 0; transform: scale(0.85); transition: opacity 0.12s, transform 0.12s;
			}
			.sofia-linea-insertar:hover .sofia-linea-insertar__boton { opacity: 1; transform: scale(1); }
			.sofia-linea-insertar__boton:hover { background: #d97a4d; color: #fff; }

			/* Un elemento oculto por una prop de apariencia sigue siendo
			   editable en el canvas: con display:none no se podría
			   clickear para cargarle una imagen ni para volver a
			   mostrarlo. Se muestra atenuado y marcado, para que se
			   entienda que el visitante no lo va a ver. */
			.sofia-testimonios--sin-fotos .sofia-testimonios__foto {
				display: block !important;
				opacity: 0.35;
				outline: 1px dashed #d97a4d;
			}

			/* Bloque SELECCIONADO — el que el panel derecho está
			   editando. Distinto del resaltado de hover (que vive en el
			   documento padre como overlay y se apaga al mover el mouse):
			   este vive en el DOM del iframe, así que sigue al bloque al
			   scrollear y persiste mientras dure la selección.
			   outline (no border) para no correr el layout ni un píxel —
			   el canvas tiene que seguir siendo un espejo fiel del sitio
			   público. El nombre sale del propio atributo, sin insertar
			   ningún elemento que ensucie la lectura de estructura. */
			[data-sofia-seleccionado] {
				position: relative;
				outline: 2px solid #d97a4d;
				outline-offset: -2px;
			}
			[data-sofia-seleccionado]::before {
				content: attr(data-sofia-seleccionado);
				position: absolute; top: 0; left: 0; z-index: 5;
				transform: translateY(-100%);
				background: #d97a4d; color: #fff;
				font-size: 10px; font-weight: 600; line-height: 1.6;
				padding: 1px 7px; border-radius: 4px 4px 0 0;
				font-family: "Inter", -apple-system, sans-serif;
				white-space: nowrap; pointer-events: none;
			}

			/* .sofia-container: min-height para que un container VACÍO
			   siga teniendo un área clickeable real donde mostrar su línea
			   de inserción "vacio" de arriba. */
			.sofia-container {
				min-height: 40px;
			}
		</style>';
	}
}

add_action( 'wp_enqueue_scripts', array( 'Sofia_Modo_Editor', 'encolar_script' ) );
add_action( 'wp_head', array( 'Sofia_Modo_Editor', 'imprimir_css_ocultar_admin_bar' ) );
add_action( 'wp_head', array( 'Sofia_Modo_Editor', 'imprimir_css_reordenar' ) );

// La visita dentro del iframe (?sofia_editor=1) carga la página PÚBLICA
// real con sesión de admin activa — WordPress le pone su PROPIA admin bar
// ahí igual que a cualquier visita autenticada, sin relación con la admin
// bar de la página de administración que contiene el iframe (ver
// Sofia_Panel_Editor::ocultar_chrome_admin, que solo cubre esa OTRA
// pantalla). Bug real encontrado en la práctica: sin esto, se veían DOS
// admin bars superpuestas (la del panel padre y la del iframe). El filtro
// "show_admin_bar" con prioridad PHP_INT_MAX (en vez de la función
// show_admin_bar()) es necesario porque algún plugin de terceros de este
// sitio la vuelve a forzar a "true" más tarde en el ciclo de "init" — ver
// el mismo comentario, más detallado, en Sofia_Panel_Editor::ocultar_chrome_admin.
add_action(
	'init',
	function () {
		if ( Sofia_Modo_Editor::activo() ) {
			add_filter( 'show_admin_bar', '__return_false', PHP_INT_MAX );
		}
	}
);

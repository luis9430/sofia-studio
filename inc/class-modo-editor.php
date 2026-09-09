<?php
/**
 * Sofia_Modo_Editor activa la edición in-place dentro del iframe del panel
 * de admin (ver inc/js/editor-iframe.js) — SOLO cuando la visita trae
 * "?sofia_editor=1" Y el usuario tiene permiso real de edición. Un
 * visitante normal jamás carga este JS ni ve ningún elemento
 * contenteditable: la query var por sí sola no alcanza, current_user_can()
 * es lo que de verdad protege esto (sin sesión de wp-admin, WordPress
 * nunca reporta ese permiso, sin importar qué traiga la URL).
 */
class Sofia_Modo_Editor {

	public static function activo(): bool {
		if ( ! isset( $_GET['sofia_editor'] ) ) {
			return false;
		}
		return current_user_can( 'edit_pages' );
	}

	/**
	 * SortableJS (CDN, vendor sin build step — ver la memoria de producto
	 * "Sofia Studio": decisión tras investigar que NINGUNA librería de
	 * drag-and-drop cruza la frontera de un iframe, así que el drag corre
	 * DENTRO de este mismo documento, informando solo el resultado final
	 * al panel padre) — versión fija, mismo criterio que
	 * SOFIA_LIBRERIAS_JS (functions.php) para las dependencias de los
	 * Componentes del catálogo.
	 */
	const VERSION_SORTABLEJS = '1.15.6';

	public static function encolar_script(): void {
		if ( ! self::activo() ) {
			return;
		}
		wp_enqueue_media(); // necesario para que wp.media() esté disponible dentro del iframe.
		wp_enqueue_script(
			'sofia-sortablejs',
			'https://cdn.jsdelivr.net/npm/sortablejs@' . self::VERSION_SORTABLEJS . '/Sortable.min.js',
			array(),
			self::VERSION_SORTABLEJS,
			true
		);
		wp_enqueue_script(
			'sofia-editor-iframe',
			get_stylesheet_directory_uri() . '/inc/js/editor-iframe.js',
			array( 'sofia-sortablejs' ),
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
	 * CSS del handle de arrastre (Nivel 2) — inyectado inline en vez de un
	 * archivo .css propio, mismo criterio que
	 * imprimir_css_ocultar_admin_bar: es CSS que SOLO debe existir cuando
	 * el modo editor está activo, nunca en una visita pública normal (ver
	 * inc/js/editor-iframe.js, activarReordenar(), que inyecta el
	 * elemento .sofia-handle-arrastre dentro de cada <section>).
	 */
	public static function imprimir_css_reordenar(): void {
		if ( ! self::activo() ) {
			return;
		}
		// Bug real encontrado en la práctica, dos iteraciones:
		// (1) el handle DENTRO de la sección (top/left sobre el propio
		//     contenido) tapaba el título.
		// (2) el intento de sacarlo por fuera del borde (left: -36px) lo
		//     dejaba fuera del viewport visible: la sección ocupa el ancho
		//     completo de la pantalla, así que un hijo con left negativo
		//     queda recortado por el borde real del navegador, invisible
		//     por completo (a diferencia de un layout con margen lateral
		//     libre, donde "salirse del borde" todavía cae dentro de la
		//     ventana).
		// Fix real: el handle vuelve a vivir DENTRO de la sección (top/
		// right en la esquina, no top/left donde suele estar el título) y
		// se mantiene chico + semitransparente por defecto — visible pero
		// discreto, en vez de invisible hasta hover (que además dependía
		// del mismo posicionamiento roto).
		echo '<style>
			.sofia-pagina > section { position: relative; }
			.sofia-handle-arrastre {
				position: absolute; top: 8px; right: 8px; z-index: 5;
				width: 26px; height: 26px; display: flex; align-items: center; justify-content: center;
				background: rgba(30,30,30,0.55); color: #fff; border-radius: 4px;
				cursor: grab; font-size: 14px; user-select: none;
			}
			.sofia-handle-arrastre:hover { background: rgba(30,30,30,0.9); }
			.sofia-handle-arrastre:active { cursor: grabbing; }
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

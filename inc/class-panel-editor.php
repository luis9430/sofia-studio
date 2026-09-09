<?php
/**
 * Sofia_Panel_Editor registra la página de admin del editor in-place — ver
 * la memoria de producto "Sofia Studio": decisión de arquitectura de que
 * el editor vive DENTRO de wp-admin (no en el dashboard de GoPress), con
 * Preact aislado del Alpine.js del dashboard de GoPress.
 *
 * La página en sí es solo un contenedor (<div id="sofia-editor-admin-root">)
 * — todo el panel (iframe + barra de estado) lo pinta admin-app/src/App.jsx
 * ya compilado por Vite a inc/build/editor-admin.js (ver
 * admin-app/vite.config.js).
 */
class Sofia_Panel_Editor {

	public static function registrar_menu(): void {
		add_menu_page(
			'Editor de contenido',
			'Sofia Studio',
			'edit_pages',
			'sofia-editor',
			array( __CLASS__, 'renderizar_pagina' ),
			'dashicons-edit-page',
			20
		);
	}

	public static function es_pagina_del_editor( string $hook ): bool {
		return 'toplevel_page_sofia-editor' === $hook;
	}

	public static function encolar_assets( string $hook ): void {
		if ( ! self::es_pagina_del_editor( $hook ) ) {
			return;
		}

		$build_dir = get_stylesheet_directory() . '/inc/build';
		$build_uri = get_stylesheet_directory_uri() . '/inc/build';
		$version   = file_exists( $build_dir . '/editor-admin.js' ) ? filemtime( $build_dir . '/editor-admin.js' ) : wp_get_theme()->get( 'Version' );

		wp_enqueue_style( 'sofia-editor-admin', $build_uri . '/editor-admin.css', array(), $version );
		wp_enqueue_script( 'sofia-editor-admin', $build_uri . '/editor-admin.js', array(), $version, true );

		$slug = isset( $_GET['pagina'] ) ? sanitize_title( wp_unslash( $_GET['pagina'] ) ) : 'inicio';

		wp_localize_script(
			'sofia-editor-admin',
			'SofiaEditorConfig',
			array(
				'slug'      => $slug,
				'restUrl'   => rest_url( 'sofia/v1/' ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'urlPagina' => home_url( '/' . $slug . '/' ),
				'urlSalir'  => admin_url(),
			)
		);
	}

	/**
	 * Oculta TODO el chrome de wp-admin (admin bar arriba, menú lateral,
	 * el padding/wrap normal del contenido) SOLO en la pantalla del
	 * editor — mismo patrón que Bricks/Elementor usan para su propia
	 * pantalla de edición: full-bleed, pantalla completa, indistinguible
	 * de "estoy editando esta página" (ver la memoria de producto "Sofia
	 * Studio" — decisión tomada tras investigar por qué la industria usa
	 * iframe pero siempre lo esconde visualmente). show_admin_bar(false)
	 * elimina la franja negra de WordPress; el CSS inline se limita a
	 * esta pantalla porque solo se imprime dentro de admin_head cuando
	 * es_pagina_del_editor() es true.
	 */
	public static function ocultar_chrome_admin(): void {
		if ( ! isset( $_GET['page'] ) || 'sofia-editor' !== $_GET['page'] ) {
			return;
		}
		// show_admin_bar(false) por sí solo no bastó en la práctica: algún
		// plugin de los muchos activos en este sitio (WPCode, SG
		// Cachepress, Query Monitor son candidatos reales, todos activos)
		// vuelve a forzarla a "true" en algún punto posterior del ciclo de
		// "init" — bug real encontrado probando contra un sitio con
		// plugins de verdad, no reproducible en aislamiento (confirmado
		// con wp eval que la función SÍ funciona sola). El filtro
		// "show_admin_bar" con prioridad PHP_INT_MAX gana sin importar qué
		// otro código la haya puesto en true antes: es la última palabra
		// sobre el valor que devuelve is_admin_bar_showing().
		add_filter( 'show_admin_bar', '__return_false', PHP_INT_MAX );
		add_action( 'admin_head', array( __CLASS__, 'imprimir_css_pantalla_completa' ) );
	}

	public static function imprimir_css_pantalla_completa(): void {
		echo '<style>
			#adminmenumain, #wpfooter, .notice, #screen-meta-links { display: none !important; }
			/* #wpadminbar oculto por CSS, no solo por show_admin_bar(false) —
			   bug real encontrado en la práctica: en un sitio con muchos
			   plugins de terceros activos (Elementor, WPCode, etc.), algo
			   sigue forzando is_admin_bar_showing() a true incluso con un
			   filtro de prioridad PHP_INT_MAX, así que WordPress igual
			   imprime el HTML de la barra. Ocultarla por CSS es a prueba de
			   eso: gana sin importar qué decida la lógica PHP. */
			#wpadminbar { display: none !important; }
			html.wp-toolbar { padding-top: 0 !important; }
			#wpcontent, #wpbody-content { margin-left: 0 !important; padding-left: 0 !important; padding-bottom: 0 !important; }
			#wpbody-content .wrap.sofia-editor-wrap { margin: 0; padding: 0; max-width: none; }
			#wpbody { padding-bottom: 0; }
		</style>';
	}

	public static function renderizar_pagina(): void {
		echo '<div class="wrap sofia-editor-wrap"><div id="sofia-editor-admin-root"></div></div>';
	}
}

add_action( 'admin_menu', array( 'Sofia_Panel_Editor', 'registrar_menu' ) );
add_action( 'admin_enqueue_scripts', array( 'Sofia_Panel_Editor', 'encolar_assets' ) );
add_action( 'init', array( 'Sofia_Panel_Editor', 'ocultar_chrome_admin' ) );

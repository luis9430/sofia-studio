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
}

add_action( 'wp_enqueue_scripts', array( 'Sofia_Modo_Editor', 'encolar_script' ) );
add_action( 'wp_head', array( 'Sofia_Modo_Editor', 'imprimir_css_ocultar_admin_bar' ) );

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

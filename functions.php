<?php
/**
 * Bootstrap del tema Sofia Studio — carga las clases del patrón
 * Factory+Builder (ver la memoria de producto "tema WP con editor de
 * contenido") y encola las librerías JS que cada página realmente
 * necesita, nunca un bundle único.
 */

require_once __DIR__ . '/inc/class-componente.php';
require_once __DIR__ . '/inc/class-componente-factory.php';
require_once __DIR__ . '/inc/class-pagina.php';
require_once __DIR__ . '/inc/class-cliente-gopress.php';
require_once __DIR__ . '/inc/class-rest-editor.php';
require_once __DIR__ . '/inc/class-modo-editor.php';
require_once __DIR__ . '/inc/class-panel-editor.php';
require_once __DIR__ . '/inc/componentes/class-hero.php';
require_once __DIR__ . '/inc/componentes/class-franja-beneficios.php';
require_once __DIR__ . '/inc/componentes/class-testimonios.php';
require_once __DIR__ . '/inc/componentes/class-faq.php';
require_once __DIR__ . '/inc/componentes/class-cta.php';
require_once __DIR__ . '/inc/componentes/class-texto-libre.php';

/**
 * SOFIA_LIBRERIAS_JS mapea el slug que un Componente declara en
 * dependencias_js() (ej. "gsap") a su script real — agregar una librería
 * nueva es agregar una entrada acá, nunca tocar wp_enqueue_script a mano
 * en cada Componente.
 */
const SOFIA_LIBRERIAS_JS = array(
	'gsap' => array(
		'src'     => 'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js',
		'version' => '3.12.5',
	),
);

/**
 * sofia_encolar_dependencias encola SOLO las librerías que $pagina
 * realmente necesita — ver Sofia_Pagina::dependencias_js(). Una página sin
 * ningún bloque animado nunca carga GSAP.
 */
function sofia_encolar_dependencias( Sofia_Pagina $pagina ): void {
	foreach ( $pagina->dependencias_js() as $slug ) {
		if ( ! isset( SOFIA_LIBRERIAS_JS[ $slug ] ) ) {
			continue;
		}
		$lib = SOFIA_LIBRERIAS_JS[ $slug ];
		wp_enqueue_script( 'sofia-' . $slug, $lib['src'], array(), $lib['version'], true );
	}
}

add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_style( 'sofia-studio', get_stylesheet_uri(), array(), wp_get_theme()->get( 'Version' ) );
	}
);

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
require_once __DIR__ . '/inc/class-estilo-global.php';
require_once __DIR__ . '/inc/class-rest-editor.php';
require_once __DIR__ . '/inc/class-modo-editor.php';
require_once __DIR__ . '/inc/class-panel-editor.php';
require_once __DIR__ . '/inc/componentes/class-hero.php';
require_once __DIR__ . '/inc/componentes/class-franja-beneficios.php';
require_once __DIR__ . '/inc/componentes/class-testimonios.php';
require_once __DIR__ . '/inc/componentes/class-faq.php';
require_once __DIR__ . '/inc/componentes/class-cta.php';
require_once __DIR__ . '/inc/componentes/class-texto-libre.php';
require_once __DIR__ . '/inc/componentes/class-container.php';
require_once __DIR__ . '/inc/componentes/class-image.php';
require_once __DIR__ . '/inc/componentes/class-button.php';
require_once __DIR__ . '/inc/componentes/class-divider.php';
require_once __DIR__ . '/inc/componentes/class-spacer.php';
require_once __DIR__ . '/inc/componentes/class-aspect-ratio.php';
require_once __DIR__ . '/inc/componentes/class-heading.php';
require_once __DIR__ . '/inc/componentes/class-rich-text.php';
require_once __DIR__ . '/inc/componentes/class-icon.php';
require_once __DIR__ . '/inc/componentes/class-video.php';
require_once __DIR__ . '/inc/componentes/class-embed.php';
require_once __DIR__ . '/inc/componentes/class-avatar.php';
require_once __DIR__ . '/inc/componentes/class-badge.php';
require_once __DIR__ . '/inc/componentes/class-link.php';
require_once __DIR__ . '/inc/componentes/class-icon-button.php';
require_once __DIR__ . '/inc/componentes/class-card.php';
require_once __DIR__ . '/inc/componentes/class-surface.php';
require_once __DIR__ . '/inc/componentes/class-callout.php';
require_once __DIR__ . '/inc/componentes/class-list.php';
require_once __DIR__ . '/inc/componentes/class-nav.php';
require_once __DIR__ . '/inc/componentes/class-breadcrumb.php';
require_once __DIR__ . '/inc/componentes/class-stat.php';
require_once __DIR__ . '/inc/componentes/class-progress.php';
require_once __DIR__ . '/inc/componentes/class-rating.php';
require_once __DIR__ . '/inc/componentes/class-tabs.php';
require_once __DIR__ . '/inc/componentes/class-accordion.php';
require_once __DIR__ . '/inc/componentes/class-modal.php';
require_once __DIR__ . '/inc/componentes/class-dropdown.php';
require_once __DIR__ . '/inc/componentes/class-stepper.php';
require_once __DIR__ . '/inc/componentes/class-gallery.php';
require_once __DIR__ . '/inc/componentes/class-masonry.php';
require_once __DIR__ . '/inc/componentes/class-marquee.php';
require_once __DIR__ . '/inc/componentes/class-carousel.php';
require_once __DIR__ . '/inc/componentes/class-header.php';

/**
 * Componentes GENERADOS: su forma viene de una descripción guardada en
 * GoPress, no de una clase de inc/componentes/. Ver el comentario largo
 * de class-componente-generado.php — en resumen, los Componentes fijos
 * saben maquetar pero no diseñar, y esto es lo que permite que la IA
 * describa un bloque que el catálogo no sabe dibujar.
 *
 * Van DESPUÉS de los fijos porque el factory los consulta último: un
 * tipo generado nunca puede suplantar uno del tema.
 */
require_once __DIR__ . '/inc/class-css-generado.php';
require_once __DIR__ . '/inc/class-definicion-generada.php';
require_once __DIR__ . '/inc/class-componente-generado.php';

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
 * SOFIA_SCRIPTS_PROPIOS: igual que SOFIA_LIBRERIAS_JS pero para archivos
 * del PROPIO tema, no URLs de CDN. La versión sale de filemtime(), así
 * que editar el archivo invalida la caché del navegador sin tocar nada
 * más.
 *
 * "interacciones" es un solo archivo para TODOS los patrones que
 * necesitan comportamiento (hoy Tabs, Modal, Dropdown y Carousel;
 * Accordion y Stepper terminaron sin JS) — nunca un archivo por
 * Componente, que es exactamente
 * el spaghetti que la regla 4 del contrato existe para evitar. Como
 * Sofia_Pagina::dependencias_js() deduplica, se encola una sola vez por
 * página, y nunca en una página que no tenga ninguno de esos bloques.
 */
const SOFIA_SCRIPTS_PROPIOS = array(
	'interacciones' => 'inc/js/sofia-interacciones.js',
);

/**
 * sofia_encolar_dependencias encola SOLO las librerías que $pagina
 * realmente necesita — ver Sofia_Pagina::dependencias_js(). Una página sin
 * ningún bloque animado nunca carga GSAP, y una sin bloques interactivos
 * no carga el JS de interacciones.
 */
function sofia_encolar_dependencias( Sofia_Pagina $pagina ): void {
	foreach ( $pagina->dependencias_js() as $slug ) {
		if ( isset( SOFIA_SCRIPTS_PROPIOS[ $slug ] ) ) {
			$ruta = SOFIA_SCRIPTS_PROPIOS[ $slug ];
			$abs  = get_stylesheet_directory() . '/' . $ruta;
			wp_enqueue_script(
				'sofia-' . $slug,
				get_stylesheet_directory_uri() . '/' . $ruta,
				array(),
				file_exists( $abs ) ? filemtime( $abs ) : wp_get_theme()->get( 'Version' ),
				true
			);
			continue;
		}
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

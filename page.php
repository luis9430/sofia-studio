<?php
/**
 * Plantilla de página de Sofia Studio — a diferencia de un tema clásico,
 * el contenido NO sale de post_content de WordPress: viene de GoPress vía
 * Sofia_Cliente_GoPress::obtener_pagina(), usando el slug real de esta
 * página de WordPress (ver internal/store/pagina_sitio.go de GoPress,
 * PaginaSitio.Slug — el mismo "slug" que identifica la página en ambos
 * lados). Si GoPress no responde (no configurado, timeout, página sin
 * migrar todavía), cae al post_content nativo de WordPress — un sitio
 * nunca debe quedar en blanco solo porque GoPress esté caído.
 */

get_header();

global $post;
$datos = Sofia_Cliente_GoPress::obtener_pagina( $post->post_name );

if ( null !== $datos ) {
	$pagina = new Sofia_Pagina( $datos['estructura'], $datos['contenido'] );
	sofia_encolar_dependencias( $pagina );

	echo '<main class="sofia-pagina" data-sofia-slug="' . esc_attr( $datos['slug'] ) . '">';
	$en_editor = class_exists( 'Sofia_Modo_Editor' ) && Sofia_Modo_Editor::activo();
	foreach ( $pagina->componentes() as $componente ) {
		$visible = $componente->bloque_visible();

		// En visita pública, un bloque no visible simplemente NO SE
		// RENDERIZA — nunca llega HTML al navegador para algo que no se
		// debía mostrar (mejor para performance/SEO que ocultar con CSS,
		// ver la decisión explícita del usuario). En el editor SIEMPRE se
		// renderiza igual (el admin logueado nunca vería un bloque "solo
		// para no logueados" si se ocultara de verdad ahí — necesita poder
		// seguir editándolo), marcado con data-sofia-oculto-condicion en
		// la PROPIA <section> — nunca un <div> envolvente: Muuri reconoce
		// sus ítems por selector "section" hijo DIRECTO de .sofia-pagina
		// (ver activarReordenar() en editor-iframe.js), un wrapper extra
		// rompería ese matching y el bloque dejaría de ser arrastrable.
		if ( ! $visible && ! $en_editor ) {
			continue;
		}

		echo $componente->render(); // phpcs:ignore WordPress.Security.EscapeOutput -- cada Componente ya escapa sus propios valores en render(); la marca visual de "oculto por condición" la agrega la propia Sofia_Componente::atributos_seccion() leyendo bloque_visible() internamente.
	}
	echo '</main>';
} else {
	echo '<main class="sofia-pagina sofia-pagina--fallback">';
	the_content();
	echo '</main>';
}

get_footer();

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
	foreach ( $pagina->componentes() as $componente ) {
		echo $componente->render(); // phpcs:ignore WordPress.Security.EscapeOutput -- cada Componente ya escapa sus propios valores en render().
	}
	echo '</main>';
} else {
	echo '<main class="sofia-pagina sofia-pagina--fallback">';
	the_content();
	echo '</main>';
}

get_footer();

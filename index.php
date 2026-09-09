<?php
/**
 * Fallback obligatorio de todo tema de WordPress — Sofia Studio en v1 solo
 * sirve páginas fijas (ver page.php), así que este archivo nunca debería
 * ejecutarse en el uso normal del tema (no hay archive/single de post en
 * v1, ver la memoria de producto "tema WP con editor de contenido").
 */

get_header();
if ( have_posts() ) {
	while ( have_posts() ) {
		the_post();
		the_content();
	}
}
get_footer();

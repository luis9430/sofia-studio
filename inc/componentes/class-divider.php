<?php
/**
 * Divider — primitiva de Layout (Fase 1, plan "50 primitivas", ver la
 * memoria de producto): una línea horizontal simple para separar
 * secciones, sin ningún contenido editable propio — mismo rol de "pieza
 * mínima" que Image/Button cumplen para su tipo de contenido, acá para
 * "separador visual".
 *
 * Caso de referencia elegido a propósito como el PRIMER Componente nuevo
 * de esta fase (ver el plan): el más simple posible, sin contenido, sin
 * hijos, sin JS — valida el checklist completo (archivo, registro en la
 * Factory, perfil de estilo, CSS con tokens reales) sin ninguna
 * complejidad extra que distraiga de confirmar que el patrón funciona.
 */
class Sofia_Componente_Divider extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Divisor', 'sofia-studio' );
	}

	/**
	 * Sin override de schema_contenido() — un Divider no tiene NINGÚN
	 * campo de contenido editable, mismo criterio que Container (el
	 * default vacío heredado de la clase base ya es correcto).
	 */

	/**
	 * claves_estilo_relevantes(): ninguno de los 4 PERFIL_* calza — no es
	 * una "sección visual completa" (PERFIL_SECCION trae radius/sombra/
	 * z_index/columnas que no significan nada para una línea). Se arma un
	 * array a mano, primer caso real de esto en el catálogo (ver el plan,
	 * Divider elegido como ejemplo representativo del caso "ningún perfil
	 * calza").
	 *
	 * "color_borde" NO se incluye a propósito, aunque a primera vista
	 * parecería el control obvio para "el color de la línea" — verificado
	 * en atributo_estilo_bloque() (clase base): color_borde SIEMPRE se
	 * aplica como border-style:solid + border-width:1px sobre la
	 * <section> exterior COMPLETA, nunca sobre un elemento interno — en
	 * un Divider eso dibujaría un RECTÁNGULO bordeado alrededor de toda
	 * la sección (con el <hr> adentro), no una línea de ese color. El
	 * mecanismo genérico de Nivel 2 no tiene ningún control pensado para
	 * "el color de un elemento decorativo interno" — el color de la línea
	 * queda fijo al token de Nivel 3 (var(--sofia-color-borde), ver
	 * style.css) hasta que exista una necesidad real de hacerlo
	 * configurable por instancia.
	 */
	public static function claves_estilo_relevantes(): array {
		return array( 'ancho', 'alineacion_bloque', 'espaciado_vertical' );
	}

	/**
	 * render(): una sola <hr> — su color sale del token de Nivel 3
	 * (var(--sofia-color-borde), ver style.css y el comentario largo de
	 * claves_estilo_relevantes() arriba sobre por qué no es configurable
	 * por instancia todavía).
	 */
	public function render(): string {
		return '<section ' . $this->atributos_seccion( 'sofia-divider' ) . '><hr class="sofia-divider__linea"></section>';
	}
}

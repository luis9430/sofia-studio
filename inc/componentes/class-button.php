<?php
/**
 * Primitiva Button (Fase 0, "primitivas de layout" — ver la memoria de
 * producto): un <a> con texto editable y enlace fijo, sola — mismo botón
 * que Sofia_Componente_CTA ya arma como parte de su bloque compuesto
 * (título + texto + botón), extraído acá como pieza mínima reusable
 * DENTRO de un Container en vez de amarrada siempre a un CTA completo.
 *
 * El enlace (href) NO es editable desde Nivel 1/2 — mismo límite conocido
 * que Sofia_Componente_CTA ya documenta: no hay hoy ningún tipo de campo
 * en el vocabulario de schema (ver Sofia_Componente::schema_bloque_generico(),
 * Fase 2) pensado para CONTENIDO (solo para estilo/layout), y
 * atributo_editable() solo sirve para texto visible dentro del elemento,
 * no para un atributo como href. Queda anotado como pendiente futuro si
 * hiciera falta editar la URL desde el editor visual.
 */
class Sofia_Componente_Button extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Botón', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		return array(
			'texto'  => __( 'Botón', 'sofia-studio' ),
			'enlace' => '#',
		);
	}

	/**
	 * schema_contenido() (Fase 4 — generador de árboles por IA): texto del
	 * botón + su enlace (tipo 'url', no 'texto' — mismo criterio que
	 * Sofia_Componente_CTA::boton_enlace).
	 */
	public static function schema_contenido(): array {
		return array(
			'texto'  => array( 'tipo' => 'texto', 'etiqueta' => __( 'Texto del botón', 'sofia-studio' ) ),
			'enlace' => array( 'tipo' => 'url', 'etiqueta' => __( 'Enlace', 'sofia-studio' ) ),
		);
	}

	public function render(): string {
		$texto  = $this->texto_enriquecido( $this->props['texto'] );
		$enlace = esc_url( $this->props['enlace'] );

		$html  = '<section ' . $this->atributos_seccion( 'sofia-button' ) . '>';
		$html .= '<a class="sofia-button__enlace" href="' . $enlace . '" ' . $this->atributo_editable( 'texto' ) . ' ' . $this->atributo_estilo( 'texto' ) . '>' . $texto . '</a>';
		$html .= '</section>';
		return $html;
	}
}

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

	public function render(): string {
		$texto  = $this->texto_enriquecido( $this->props['texto'] );
		$enlace = esc_url( $this->props['enlace'] );

		$html  = '<section ' . $this->atributos_seccion( 'sofia-button' ) . '>';
		$html .= '<a class="sofia-button__enlace" href="' . $enlace . '" ' . $this->atributo_editable( 'texto' ) . ' ' . $this->atributo_estilo( 'texto' ) . '>' . $texto . '</a>';
		$html .= '</section>';
		return $html;
	}
}

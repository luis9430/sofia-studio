<?php
/**
 * Bloque CTA (Call to Action) — título + texto + botón con enlace, sin
 * lista repetible (mismo patrón simple que Hero: campos sueltos, no
 * "items"). El botón guarda DOS campos: el texto visible y la URL de
 * destino — ambos texto plano, ninguno necesita atributo_editable()
 * especial más allá de lo ya heredado de Sofia_Componente.
 */
class Sofia_Componente_CTA extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Llamado a la acción', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		return array(
			'titulo'       => __( '¿Listo para empezar?', 'sofia-studio' ),
			'texto'        => __( 'Escribe acá una invitación breve a la acción que querés que tome el visitante.', 'sofia-studio' ),
			'boton_texto'  => __( 'Contactanos', 'sofia-studio' ),
			'boton_enlace' => '#',
		);
	}

	public function render(): string {
		$titulo       = $this->texto_enriquecido( $this->props['titulo'] );
		$texto        = $this->texto_enriquecido( $this->props['texto'] );
		$boton_texto  = $this->texto_enriquecido( $this->props['boton_texto'] );
		$boton_enlace = esc_url( $this->props['boton_enlace'] );

		$html  = '<section class="sofia-cta" ' . $this->atributos_seccion() . '>';
		$html .= '<h2 ' . $this->atributo_editable( 'titulo' ) . '>' . $titulo . '</h2>';
		$html .= '<p ' . $this->atributo_editable( 'texto' ) . '>' . $texto . '</p>';
		// El enlace del botón (href) NO es contenteditable — es un
		// atributo, no texto visible. Mismo criterio que
		// Sofia_Componente_Hero con el src de una imagen: el editor
		// in-place edita el TEXTO del botón como cualquier campo normal,
		// la URL de destino queda fuera de alcance de Nivel 1/2 (no hay
		// UI hoy para editar un href) — anotado como pendiente futuro si
		// hiciera falta.
		$html .= '<a class="sofia-cta__boton" href="' . $boton_enlace . '" ' . $this->atributo_editable( 'boton_texto' ) . '>' . $boton_texto . '</a>';
		$html .= '</section>';
		return $html;
	}
}

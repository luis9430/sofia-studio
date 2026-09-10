<?php
/**
 * Bloque Texto libre — un único párrafo de contenido editable, sin
 * título ni estructura adicional. El Componente más simple del catálogo:
 * un solo campo de texto plano, útil para secciones de contenido genérico
 * entre bloques más estructurados (Hero, Franja de beneficios, etc.).
 */
class Sofia_Componente_Texto_Libre extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Texto libre', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		return array(
			'contenido' => __( 'Escribe acá el contenido de este bloque.', 'sofia-studio' ),
		);
	}

	public function render(): string {
		$contenido = $this->texto_enriquecido( $this->props['contenido'] );

		$html  = '<section class="sofia-texto-libre" ' . $this->atributos_seccion() . '>';
		$html .= '<p ' . $this->atributo_editable( 'contenido' ) . '>' . $contenido . '</p>';
		$html .= '</section>';
		return $html;
	}
}

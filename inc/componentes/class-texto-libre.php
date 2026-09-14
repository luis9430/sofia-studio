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

	/**
	 * schema_contenido() (Fase 4 — generador de árboles por IA): un único
	 * campo de texto largo, mismo campo que props_por_defecto() declara.
	 */
	public static function schema_contenido(): array {
		return array(
			'contenido' => array( 'tipo' => 'texto_largo', 'etiqueta' => __( 'Contenido', 'sofia-studio' ) ),
		);
	}

	public function render(): string {
		$contenido = $this->texto_enriquecido( $this->props['contenido'] );

		$html  = '<section ' . $this->atributos_seccion( 'sofia-texto-libre' ) . '>';
		$html .= '<p ' . $this->atributo_editable( 'contenido' ) . ' ' . $this->atributo_estilo( 'contenido' ) . '>' . $contenido . '</p>';
		$html .= '</section>';
		return $html;
	}
}

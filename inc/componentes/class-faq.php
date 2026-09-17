<?php
/**
 * Bloque FAQ — lista de N preguntas frecuentes (pregunta + respuesta cada
 * una), mismo patrón de "lista repetible" que Franja de beneficios/
 * Testimonios (Nivel 2, sub-items — ver la memoria de producto "tema WP
 * con editor de contenido"): un único campo de tipo lista,
 * "faq.items" (array de {pregunta, respuesta}).
 */
class Sofia_Componente_FAQ extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Preguntas frecuentes', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		$items = array();
		for ( $i = 1; $i <= 3; $i++ ) {
			$items[] = array(
				'pregunta'  => sprintf( __( '¿Pregunta frecuente %d?', 'sofia-studio' ), $i ),
				'respuesta' => __( 'Escribe acá la respuesta a esta pregunta.', 'sofia-studio' ),
			);
		}
		return array( 'items' => $items );
	}

	/**
	 * schema_contenido() (Fase 4 — generador de árboles por IA): mismo
	 * criterio que Franja de beneficios/Testimonios — "items" es tipo
	 * 'lista' de {pregunta, respuesta}.
	 */
	public static function schema_contenido(): array {
		return array(
			'items' => array(
				'tipo'     => 'lista',
				'etiqueta' => __( 'Preguntas frecuentes', 'sofia-studio' ),
				'campos'   => array(
					'pregunta'  => array( 'tipo' => 'texto', 'etiqueta' => __( 'Pregunta', 'sofia-studio' ) ),
					'respuesta' => array( 'tipo' => 'texto_largo', 'etiqueta' => __( 'Respuesta', 'sofia-studio' ) ),
				),
			),
		);
	}

	public function render(): string {
		$items = is_array( $this->props['items'] ?? null ) ? $this->props['items'] : array();


		$html  = '<section ' . $this->atributos_seccion( 'sofia-faq' ) . '>';
		$html .= '<div class="sofia-faq__lista" ' . $this->atributo_lista( 'items' ) . '>';
		foreach ( array_values( $items ) as $indice => $item ) {
			$pregunta  = $this->texto_enriquecido( (string) ( $item['pregunta'] ?? '' ) );
			$respuesta = $this->texto_enriquecido( (string) ( $item['respuesta'] ?? '' ) );

			$html .= '<div class="sofia-faq__item" ' . $this->atributo_item( $indice ) . '>';
			$html .= '<h3 class="sofia-faq__pregunta" ' . $this->atributo_editable( "items.{$indice}.pregunta" ) . ' ' . $this->atributo_estilo( "items.{$indice}.pregunta" ) . '>' . $pregunta . '</h3>';
			$html .= '<p class="sofia-faq__respuesta" ' . $this->atributo_editable( "items.{$indice}.respuesta" ) . ' ' . $this->atributo_estilo( "items.{$indice}.respuesta" ) . '>' . $respuesta . '</p>';
			$html .= '</div>';
		}
		$html .= '</div>';
		$html .= $this->boton_agregar_item( 'items' );
		$html .= '</section>';
		return $html;
	}
}

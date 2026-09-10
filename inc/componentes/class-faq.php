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

	public function render(): string {
		$items = is_array( $this->props['items'] ?? null ) ? $this->props['items'] : array();

		$campo_lista = $this->id . '.items';

		$html  = '<section class="sofia-faq" ' . $this->atributos_seccion() . '>';
		$html .= '<div class="sofia-faq__lista" data-sofia-lista="' . esc_attr( $campo_lista ) . '">';
		foreach ( array_values( $items ) as $indice => $item ) {
			$pregunta  = $this->texto_enriquecido( (string) ( $item['pregunta'] ?? '' ) );
			$respuesta = $this->texto_enriquecido( (string) ( $item['respuesta'] ?? '' ) );

			$html .= '<div class="sofia-faq__item" data-sofia-item="' . (int) $indice . '">';
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

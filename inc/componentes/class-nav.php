<?php
/**
 * Nav — primitiva de Navegación (Fase 4, plan "50 primitivas", ver la
 * memoria de producto): lista horizontal de links (texto + enlace) — mismo
 * mecanismo de lista repetible que Franja de beneficios, cada item con 2
 * campos en vez de 1.
 *
 * Mismo límite ya documentado en Button/CTA/Link: el enlace de cada item
 * (href) NO es editable desde Nivel 1/2 (solo el texto es contenteditable,
 * la URL queda fuera de alcance de edición in-place hoy).
 */
class Sofia_Componente_Nav extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Navegación', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		return array(
			'items' => array(
				array( 'texto' => __( 'Inicio', 'sofia-studio' ), 'enlace' => '#' ),
				array( 'texto' => __( 'Nosotros', 'sofia-studio' ), 'enlace' => '#' ),
				array( 'texto' => __( 'Contacto', 'sofia-studio' ), 'enlace' => '#' ),
			),
		);
	}

	public static function schema_contenido(): array {
		return array(
			'items' => array(
				'tipo'     => 'lista',
				'etiqueta' => __( 'Enlaces', 'sofia-studio' ),
				'campos'   => array(
					'texto'  => array( 'tipo' => 'texto', 'etiqueta' => __( 'Texto', 'sofia-studio' ) ),
					'enlace' => array( 'tipo' => 'url', 'etiqueta' => __( 'Enlace', 'sofia-studio' ) ),
				),
			),
		);
	}

	/**
	 * claves_estilo_relevantes(): PERFIL_LISTA — mismo criterio que List/
	 * Franja de beneficios.
	 */
	public static function claves_estilo_relevantes(): array {
		return parent::PERFIL_LISTA;
	}

	public function render(): string {
		$items       = is_array( $this->props['items'] ?? null ) ? $this->props['items'] : array();
		$campo_lista = $this->id . '.items';

		$html  = '<section ' . $this->atributos_seccion( 'sofia-nav' ) . '>';
		$html .= '<nav class="sofia-nav__lista" data-sofia-lista="' . esc_attr( $campo_lista ) . '">';
		foreach ( array_values( $items ) as $indice => $item ) {
			$texto  = $this->texto_enriquecido( (string) ( $item['texto'] ?? '' ) );
			$enlace = esc_url( (string) ( $item['enlace'] ?? '#' ) );
			$html  .= '<div class="sofia-nav__item" data-sofia-item="' . (int) $indice . '">';
			$html  .= '<a class="sofia-nav__enlace" href="' . $enlace . '" ' . $this->atributo_editable( "items.{$indice}.texto" ) . ' ' . $this->atributo_estilo( "items.{$indice}.texto" ) . '>' . $texto . '</a>';
			$html  .= '</div>';
		}
		$html .= '</nav>';
		$html .= $this->boton_agregar_item( 'items' );
		$html .= '</section>';
		return $html;
	}
}

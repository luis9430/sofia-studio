<?php
/**
 * Breadcrumb — primitiva de Navegación (Fase 4, plan "50 primitivas", ver
 * la memoria de producto): mismo mecanismo que Nav (lista de {texto,
 * enlace}), con separador visual "/" entre items en vez de espaciado
 * horizontal simple — la diferencia real con Nav es puramente de CSS
 * (ver style.css), la estructura de datos y el render son idénticos salvo
 * la clase base.
 */
class Sofia_Componente_Breadcrumb extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Breadcrumb', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		return array(
			'items' => array(
				array( 'texto' => __( 'Inicio', 'sofia-studio' ), 'enlace' => '#' ),
				array( 'texto' => __( 'Categoría', 'sofia-studio' ), 'enlace' => '#' ),
				array( 'texto' => __( 'Página actual', 'sofia-studio' ), 'enlace' => '#' ),
			),
		);
	}

	public static function schema_contenido(): array {
		return array(
			'items' => array(
				'tipo'     => 'lista',
				'etiqueta' => __( 'Migas de pan', 'sofia-studio' ),
				'campos'   => array(
					'texto'  => array( 'tipo' => 'texto', 'etiqueta' => __( 'Texto', 'sofia-studio' ) ),
					'enlace' => array( 'tipo' => 'url', 'etiqueta' => __( 'Enlace', 'sofia-studio' ) ),
				),
			),
		);
	}

	/**
	 * claves_estilo_relevantes(): PERFIL_SECCION — ver el comentario largo
	 * en Sofia_Componente_List: las 3 claves extra de PERFIL_LISTA son de
	 * GRILLA y su CSS solo existe para Franja de beneficios/Testimonios.
	 */
	public static function claves_estilo_relevantes(): array {
		return parent::PERFIL_SECCION;
	}

	public function render(): string {
		$items = is_array( $this->props['items'] ?? null ) ? $this->props['items'] : array();
		$total       = count( $items );

		$html  = '<section ' . $this->atributos_seccion( 'sofia-breadcrumb' ) . '>';
		$html .= '<nav class="sofia-breadcrumb__lista" ' . $this->atributo_lista( 'items' ) . ' aria-label="' . esc_attr__( 'Breadcrumb', 'sofia-studio' ) . '">';
		foreach ( array_values( $items ) as $indice => $item ) {
			$texto  = $this->texto_enriquecido( (string) ( $item['texto'] ?? '' ) );
			$enlace = esc_url( (string) ( $item['enlace'] ?? '#' ) );
			$html  .= '<div class="sofia-breadcrumb__item" ' . $this->atributo_item( $indice ) . '>';
			$html  .= '<a class="sofia-breadcrumb__enlace" href="' . $enlace . '" ' . $this->atributo_editable( "items.{$indice}.texto" ) . ' ' . $this->atributo_estilo( "items.{$indice}.texto" ) . '>' . $texto . '</a>';
			if ( $indice < $total - 1 ) {
				$html .= '<span class="sofia-breadcrumb__separador" aria-hidden="true">/</span>';
			}
			$html .= '</div>';
		}
		$html .= '</nav>';
		$html .= $this->boton_agregar_item( 'items' );
		$html .= '</section>';
		return $html;
	}
}

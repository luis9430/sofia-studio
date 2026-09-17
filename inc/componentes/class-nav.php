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
	 * claves_estilo_relevantes(): PERFIL_SECCION — ver el comentario largo
	 * en Sofia_Componente_List: las 3 claves extra de PERFIL_LISTA son de
	 * GRILLA y su CSS solo existe para Franja de beneficios/Testimonios.
	 * Una Nav es una fila horizontal que envuelve, nunca una grilla de
	 * columnas.
	 */
	public static function claves_estilo_relevantes(): array {
		return parent::PERFIL_SECCION;
	}

	/**
	 * Capa 2 — APARIENCIA. La separación entre enlaces cambia por
	 * completo el carácter del bloque: una nav principal necesita aire,
	 * una lista de enlaces al pie se lee mejor compacta. Usa las clases
	 * de gap de Core Framework (regla 3 del contrato), no CSS propio.
	 */
	public static function schema_propio(): array {
		return array(
			'separacion' => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Separación', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'Base', 'sofia-studio' ) ),
					array( 'valor' => 'compacta', 'etiqueta' => __( 'Compacta', 'sofia-studio' ) ),
					array( 'valor' => 'amplia', 'etiqueta' => __( 'Amplia', 'sofia-studio' ) ),
				),
			),
		);
	}

	private const CLASES_SEPARACION = array(
		'compacta' => 'gap-s',
		'amplia'   => 'gap-2xl',
	);

	public function render(): string {
		$items = is_array( $this->props['items'] ?? null ) ? $this->props['items'] : array();

		$html  = '<section ' . $this->atributos_seccion( 'sofia-nav' ) . '>';
		$clases_lista = array_filter( array(
			'sofia-nav__lista',
			$this->clase_de_variante( self::CLASES_SEPARACION, 'separacion' ),
		) );

		$html .= '<nav class="' . esc_attr( implode( ' ', $clases_lista ) ) . '" ' . $this->atributo_lista( 'items' ) . '>';
		foreach ( array_values( $items ) as $indice => $item ) {
			$texto  = $this->texto_enriquecido( (string) ( $item['texto'] ?? '' ) );
			$enlace = esc_url( (string) ( $item['enlace'] ?? '#' ) );
			$html  .= '<div class="sofia-nav__item" ' . $this->atributo_item( $indice ) . '>';
			$html  .= '<a class="sofia-nav__enlace" href="' . $enlace . '" ' . $this->atributo_editable( "items.{$indice}.texto" ) . ' ' . $this->atributo_estilo( "items.{$indice}.texto" ) . '>' . $texto . '</a>';
			$html  .= '</div>';
		}
		$html .= '</nav>';
		$html .= $this->boton_agregar_item( 'items' );
		$html .= '</section>';
		return $html;
	}
}

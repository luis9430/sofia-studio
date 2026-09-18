<?php
/**
 * Dropdown — un menú que se despliega al clickear.
 *
 * Usa <details>/<summary> nativo, igual que Accordion: abre y cierra sin
 * JavaScript, con el manejo de teclado y la accesibilidad ya resueltos
 * por el navegador. El script compartido solo agrega lo que el HTML no
 * da — cerrarlo al clickear afuera o con Escape, que es lo que un menú
 * necesita para no quedar abierto mientras se navega el resto.
 *
 * Mismo límite conocido que Nav/Button: el enlace de cada opción se
 * edita desde el panel, no en el canvas, porque una URL no es
 * contenteditable.
 */
class Sofia_Componente_Dropdown extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Menú desplegable', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		return array(
			'etiqueta' => __( 'Opciones', 'sofia-studio' ),
			'items'    => array(
				array( 'texto' => __( 'Primera opción', 'sofia-studio' ), 'enlace' => '#' ),
				array( 'texto' => __( 'Segunda opción', 'sofia-studio' ), 'enlace' => '#' ),
			),
		);
	}

	/** Capa 1 — CONTENIDO. */
	public static function schema_contenido(): array {
		return array(
			'etiqueta' => array( 'tipo' => 'texto', 'etiqueta' => __( 'Texto del botón', 'sofia-studio' ) ),
			'items'    => array(
				'tipo'     => 'lista',
				'etiqueta' => __( 'Opciones', 'sofia-studio' ),
				'campos'   => array(
					'texto'  => array( 'tipo' => 'texto', 'etiqueta' => __( 'Texto', 'sofia-studio' ) ),
					'enlace' => array( 'tipo' => 'url', 'etiqueta' => __( 'Enlace', 'sofia-studio' ) ),
				),
			),
		);
	}

	/** Capa 2 — APARIENCIA. */
	public static function schema_propio(): array {
		return array(
			'alineacion' => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Abre hacia', 'sofia-studio' ),
				'ayuda'    => __( 'De qué lado del botón aparece el menú.', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => 'izquierda', 'etiqueta' => __( 'Izquierda', 'sofia-studio' ) ),
					array( 'valor' => 'derecha', 'etiqueta' => __( 'Derecha', 'sofia-studio' ) ),
				),
			),
		);
	}

	private const CLASES_ALINEACION = array(
		'derecha' => 'sofia-dropdown--derecha',
	);

	/** Capa 3 — CAJA: es una pieza simple, como un botón. */
	public static function claves_estilo_relevantes(): array {
		return parent::PERFIL_PRIMITIVA;
	}

	public function dependencias_js(): array {
		return array( 'interacciones' );
	}

	/**
	 * render(): en el editor el menú sale ABIERTO. Un menú plegado deja su
	 * contenido sin poder clickearse para editarlo, y el script que lo
	 * cierra no corre ahí — mismo criterio que Accordion.
	 */
	public function render(): string {
		$etiqueta  = $this->texto_enriquecido( (string) ( $this->props['etiqueta'] ?? '' ) );
		$items     = is_array( $this->props['items'] ?? null ) ? $this->props['items'] : array();
		$en_editor = class_exists( 'Sofia_Modo_Editor' ) && Sofia_Modo_Editor::activo();

		$clases = array_filter( array(
			'sofia-dropdown',
			$this->clase_de_variante( self::CLASES_ALINEACION, 'alineacion' ),
		) );

		$html  = '<section ' . $this->atributos_seccion( implode( ' ', $clases ) ) . '>';
		$html .= '<details class="sofia-dropdown__caja" data-sofia-dropdown' . ( $en_editor ? ' open' : '' ) . '>';
		$html .= '<summary class="sofia-dropdown__boton" ' . $this->atributo_editable( 'etiqueta' ) . ' ' . $this->atributo_estilo( 'etiqueta' ) . '>' . $etiqueta . '</summary>';
		$html .= '<div class="sofia-dropdown__menu" ' . $this->atributo_lista( 'items' ) . '>';
		foreach ( array_values( $items ) as $indice => $item ) {
			$texto  = $this->texto_enriquecido( (string) ( $item['texto'] ?? '' ) );
			$enlace = esc_url( (string) ( $item['enlace'] ?? '#' ) );
			$html  .= '<div class="sofia-dropdown__item" ' . $this->atributo_item( $indice ) . '>';
			$html  .= '<a class="sofia-dropdown__enlace" href="' . $enlace . '" ' . $this->atributo_editable( "items.{$indice}.texto" ) . '>' . $texto . '</a>';
			$html  .= '</div>';
		}
		$html .= '</div>';
		$html .= '</details>';
		$html .= $this->boton_agregar_item( 'items' );
		$html .= '</section>';
		return $html;
	}
}

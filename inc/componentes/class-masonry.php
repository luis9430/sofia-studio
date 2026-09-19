<?php
/**
 * Masonry — columnas de altura despareja, tipo muro de ladrillos.
 *
 * Se resuelve con column-count de CSS, sin una sola línea de JS. Vale
 * aclararlo porque en este proyecto Masonry arrastra historia: el editor
 * usó SortableJS y después Muuri justamente para esto, y Muuri se sacó
 * tras un bug real de reordenamiento errático. Nada de eso hace falta
 * acá: aquella librería resolvía DRAG-AND-DROP en el editor, que es otro
 * problema. Acomodar bloques de distinta altura en columnas es algo que
 * el navegador hace solo desde hace años.
 *
 * La contrapartida de column-count, y la razón por la que existen las
 * librerías de masonry: el orden de lectura es por COLUMNA (arriba a
 * abajo y recién ahí a la siguiente), no por fila. Para un muro de fotos
 * o de citas da igual; para contenido donde el orden importa, la pieza
 * correcta es Gallery, que sí es una grilla por filas.
 *
 * A diferencia de Gallery, este bloque SÍ lleva CSS propio: column-count
 * no es algo que Core Framework exponga como utility class, así que la
 * regla 3 no aplica — no hay nada de CF que reusar.
 */
class Sofia_Componente_Masonry extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Muro', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		return array(
			'items' => array(
				array( 'imagen' => '', 'texto' => '' ),
				array( 'imagen' => '', 'texto' => '' ),
				array( 'imagen' => '', 'texto' => '' ),
				array( 'imagen' => '', 'texto' => '' ),
			),
		);
	}

	/** Capa 1 — CONTENIDO. */
	public static function schema_contenido(): array {
		return array(
			'items' => array(
				'tipo'     => 'lista',
				'etiqueta' => __( 'Piezas', 'sofia-studio' ),
				'campos'   => array(
					'imagen' => array( 'tipo' => 'imagen', 'etiqueta' => __( 'Imagen', 'sofia-studio' ) ),
					'texto'  => array( 'tipo' => 'texto_largo', 'etiqueta' => __( 'Texto', 'sofia-studio' ) ),
				),
			),
		);
	}

	/**
	 * Capa 2 — APARIENCIA.
	 *
	 * "columnas" propio y no el del Nivel 2 genérico (PERFIL_LISTA): ese
	 * emite las utility classes columns-N de Core Framework, que son
	 * grid — y grid es justamente lo que un muro NO usa. Serían dos
	 * controles con el mismo nombre peleándose por el mismo layout.
	 */
	public static function schema_propio(): array {
		return array(
			'columnas' => array(
				'tipo'     => 'botones_numero',
				'etiqueta' => __( 'Columnas', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => '2', 'etiqueta' => '2' ),
					array( 'valor' => '3', 'etiqueta' => '3' ),
					array( 'valor' => '4', 'etiqueta' => '4' ),
				),
			),
		);
	}

	private const CLASES_COLUMNAS = array(
		'2' => 'sofia-masonry--2',
		'4' => 'sofia-masonry--4',
	);

	/**
	 * Capa 3 — CAJA. PERFIL_SECCION y no PERFIL_LISTA: los 3 controles de
	 * grilla que trae PERFIL_LISTA (columnas + alineación del contenido)
	 * no tienen efecto sobre column-count, así que estarían muertos en el
	 * panel — el mismo defecto que el plan detectó en List, Nav y
	 * Breadcrumb.
	 */
	public static function claves_estilo_relevantes(): array {
		return parent::PERFIL_SECCION;
	}

	/**
	 * render(): cada pieza es una imagen, un texto, o las dos. Los dos
	 * campos se emiten siempre aunque estén vacíos, por la misma razón
	 * que en Gallery: un campo que solo existe cuando ya tiene contenido
	 * no se puede clickear para estrenarlo.
	 */
	public function render(): string {
		$items = is_array( $this->props['items'] ?? null ) ? $this->props['items'] : array();

		$clases = array_filter( array(
			'sofia-masonry',
			$this->clase_de_variante( self::CLASES_COLUMNAS, 'columnas' ),
		) );

		$html  = '<section ' . $this->atributos_seccion( implode( ' ', $clases ) ) . '>';
		$html .= '<div class="sofia-masonry__muro" ' . $this->atributo_lista( 'items' ) . '>';

		foreach ( array_values( $items ) as $indice => $item ) {
			$imagen = $this->imagen_o_placeholder( (string) ( $item['imagen'] ?? '' ) );
			$texto  = $this->texto_enriquecido( (string) ( $item['texto'] ?? '' ) );

			$html .= '<div class="sofia-masonry__item" ' . $this->atributo_item( $indice ) . '>';
			if ( $imagen ) {
				$html .= '<img class="sofia-masonry__imagen" src="' . esc_url( $imagen ) . '" alt="" loading="lazy" '
					. $this->atributo_editable( "items.{$indice}.imagen" ) . '>';
			}
			$html .= '<div class="sofia-masonry__texto" '
				. $this->atributo_editable( "items.{$indice}.texto" ) . ' '
				. $this->atributo_estilo( "items.{$indice}.texto" ) . '>' . $texto . '</div>';
			$html .= '</div>';
		}

		$html .= '</div>';
		$html .= $this->boton_agregar_item( 'items' );
		$html .= '</section>';
		return $html;
	}
}

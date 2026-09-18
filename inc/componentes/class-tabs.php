<?php
/**
 * Tabs — pestañas con su panel de contenido.
 *
 * Primer Componente del catálogo con comportamiento propio en el sitio
 * publicado. Su JS vive en el archivo compartido de interacciones (ver
 * dependencias_js() abajo), nunca en uno propio — regla 4 del contrato.
 *
 * Los paneles son texto, no bloques anidados: permitir hijos exigiría que
 * el editor supiera insertar dentro de un panel oculto, y hoy la línea de
 * inserción solo aparece en contenedores visibles. Cuando haga falta
 * contenido rico adentro, la vía es anidar un Container en cada panel, y
 * eso pide primero resolver la inserción en contenido plegado.
 */
class Sofia_Componente_Tabs extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Pestañas', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		return array(
			'items' => array(
				array(
					'titulo' => __( 'Primera pestaña', 'sofia-studio' ),
					'texto'  => __( 'Contenido de la primera pestaña.', 'sofia-studio' ),
				),
				array(
					'titulo' => __( 'Segunda pestaña', 'sofia-studio' ),
					'texto'  => __( 'Contenido de la segunda pestaña.', 'sofia-studio' ),
				),
			),
		);
	}

	/** Capa 1 — CONTENIDO. */
	public static function schema_contenido(): array {
		return array(
			'items' => array(
				'tipo'     => 'lista',
				'etiqueta' => __( 'Pestañas', 'sofia-studio' ),
				'campos'   => array(
					'titulo' => array( 'tipo' => 'texto', 'etiqueta' => __( 'Título', 'sofia-studio' ) ),
					'texto'  => array( 'tipo' => 'texto_largo', 'etiqueta' => __( 'Contenido', 'sofia-studio' ) ),
				),
			),
		);
	}

	/** Capa 2 — APARIENCIA. */
	public static function schema_propio(): array {
		return array(
			'estilo' => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Estilo', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => 'linea', 'etiqueta' => __( 'Subrayado', 'sofia-studio' ) ),
					array( 'valor' => 'pildoras', 'etiqueta' => __( 'Píldoras', 'sofia-studio' ) ),
				),
			),
		);
	}

	private const CLASES_ESTILO = array(
		'pildoras' => 'sofia-tabs--pildoras',
	);

	/** Capa 3 — CAJA. */
	public static function claves_estilo_relevantes(): array {
		return parent::PERFIL_SECCION;
	}

	/**
	 * Un solo slug para los ocho patrones interactivos: comparten archivo,
	 * y Sofia_Pagina::dependencias_js() deduplica, así que una página con
	 * Tabs y Modal lo carga una sola vez.
	 */
	public function dependencias_js(): array {
		return array( 'interacciones' );
	}

	/**
	 * render(): el HTML sale completo y usable SIN JavaScript — todas las
	 * pestañas visibles, una debajo de la otra. El script se limita a
	 * ocultar las no activas al cargar.
	 *
	 * Eso importa por tres razones concretas: el contenido de todas las
	 * pestañas es indexable, sigue siendo legible si el script falla, y
	 * dentro del editor (donde el script no corre) se puede editar el
	 * texto de cualquier pestaña sin tener que activarla primero.
	 *
	 * Los ids se derivan del id de INSTANCIA del bloque, no de un contador
	 * global: dos Tabs en la misma página no pueden compartir ids o el
	 * aria-controls de una apuntaría al panel de la otra.
	 */
	public function render(): string {
		$items = is_array( $this->props['items'] ?? null ) ? $this->props['items'] : array();

		$clases = array_filter( array(
			'sofia-tabs',
			$this->clase_de_variante( self::CLASES_ESTILO, 'estilo' ),
		) );

		$html = '<section ' . $this->atributos_seccion( implode( ' ', $clases ) ) . ' data-sofia-tabs>';

		// Pestaña y panel van juntos DENTRO del mismo [data-sofia-item].
		//
		// Es un requisito del mecanismo de listas, no una preferencia:
		// notificarListaActualizada() (editor-iframe.js) reconstruye cada
		// item leyendo los [data-sofia-campo] que encuentra ADENTRO de él.
		// La primera versión de este render ponía los títulos en una lista
		// y los paneles en otra, como hermanos — así que al editar
		// cualquier pestaña el array se reconstruía sin los textos de los
		// paneles y se perdían. Pasó de verdad: quedaron items con
		// {"titulo": ""} y sin campo "texto".
		//
		// El layout (pestañas arriba en fila, paneles abajo) lo resuelve
		// el CSS con grid, no el orden del HTML.
		$html .= '<div class="sofia-tabs__grupo" role="tablist" ' . $this->atributo_lista( 'items' ) . '>';
		foreach ( array_values( $items ) as $indice => $item ) {
			$titulo = $this->texto_enriquecido( (string) ( $item['titulo'] ?? '' ) );
			$texto  = $this->texto_enriquecido( (string) ( $item['texto'] ?? '' ) );

			$html .= '<div class="sofia-tabs__item" ' . $this->atributo_item( $indice ) . '>';
			$html .= '<button type="button" class="sofia-tabs__pestana" role="tab" data-sofia-tab'
				. ' id="' . esc_attr( $this->id ) . '-tab-' . (int) $indice . '"'
				. ' aria-controls="' . esc_attr( $this->id ) . '-panel-' . (int) $indice . '"'
				. ' aria-selected="' . ( 0 === $indice ? 'true' : 'false' ) . '" '
				. $this->atributo_editable( "items.{$indice}.titulo" ) . '>' . $titulo . '</button>';
			$html .= '<div class="sofia-tabs__panel" role="tabpanel" data-sofia-panel'
				. ' id="' . esc_attr( $this->id ) . '-panel-' . (int) $indice . '"'
				. ' aria-labelledby="' . esc_attr( $this->id ) . '-tab-' . (int) $indice . '" '
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

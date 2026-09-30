<?php
/**
 * Pestañas — mucho que contar, sin alargar la página.
 *
 * REDISEÑADO como PIEZA. La versión anterior era una fila de pestañas con
 * un panel de texto: correcta, sin encabezado, y con un solo párrafo por
 * pestaña.
 *
 * SOBRE EL CONTENIDO RICO ADENTRO
 *
 * El docblock anterior decía que meter bloques dentro de un panel exigía
 * que el editor supiera insertar en contenido oculto, y que la vía sería
 * anidar un Container. Con listas anidadas hay un camino más corto y sin
 * esa deuda: cada pestaña puede tener una lista de PUNTOS además de su
 * texto. No es un Container arbitrario —no se puede meter cualquier
 * bloque— pero cubre el caso real: una pestaña que explica algo y enumera
 * lo que incluye.
 *
 * Tres composiciones que resuelven casos distintos, con su criterio
 * declarado en las etiquetas del select.
 */
class Sofia_Componente_Tabs extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Pestañas', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		return array(
			'etiqueta' => '',
			'titulo'   => __( 'Tres formas de conocer la costa', 'sofia-studio' ),
			'texto'    => __( 'Elegí la que se parezca más a cómo viajás.', 'sofia-studio' ),
			'items'    => array(
				array(
					'titulo' => __( 'Por tu cuenta', 'sofia-studio' ),
					'texto'  => __( 'Te damos las rutas, los tiempos y dónde parar. Vos armás el resto.', 'sofia-studio' ),
					'puntos' => array(
						array( 'texto' => __( 'Mapa de las nueve bahías', 'sofia-studio' ) ),
						array( 'texto' => __( 'Distancias reales entre playas', 'sofia-studio' ) ),
						array( 'texto' => __( 'Dónde hay señal y dónde no', 'sofia-studio' ) ),
					),
				),
				array(
					'titulo' => __( 'Ruta armada', 'sofia-studio' ),
					'texto'  => __( 'Nosotros reservamos todo y vos solo llegás. Cambiamos lo que no te convenza.', 'sofia-studio' ),
					'puntos' => array(
						array( 'texto' => __( 'Hospedaje en hoteles chicos', 'sofia-studio' ) ),
						array( 'texto' => __( 'Traslados desde el aeropuerto', 'sofia-studio' ) ),
						array( 'texto' => __( 'Alguien a quien llamar si algo falla', 'sofia-studio' ) ),
					),
				),
				array(
					'titulo' => __( 'A medida', 'sofia-studio' ),
					'texto'  => __( 'Para grupos, bodas o estadías largas. Se cotiza según lo que necesiten.', 'sofia-studio' ),
					'puntos' => array(
						array( 'texto' => __( 'Coordinación de eventos', 'sofia-studio' ) ),
						array( 'texto' => __( 'Contacto directo con los hoteles', 'sofia-studio' ) ),
					),
				),
			),
		);
	}

	/** Capa 1 — CONTENIDO. */
	public static function schema_contenido(): array {
		return array(
			'etiqueta' => array( 'tipo' => 'texto', 'etiqueta' => __( 'Etiqueta (arriba del título)', 'sofia-studio' ) ),
			'titulo'   => array( 'tipo' => 'texto', 'etiqueta' => __( 'Título', 'sofia-studio' ) ),
			'texto'    => array( 'tipo' => 'texto_largo', 'etiqueta' => __( 'Bajada', 'sofia-studio' ) ),
			'items'    => array(
				'tipo'     => 'lista',
				'etiqueta' => __( 'Pestañas', 'sofia-studio' ),
				'campos'   => array(
					'titulo' => array( 'tipo' => 'texto', 'etiqueta' => __( 'Título de la pestaña', 'sofia-studio' ) ),
					'texto'  => array( 'tipo' => 'texto_largo', 'etiqueta' => __( 'Contenido', 'sofia-studio' ) ),
					// La lista anidada que resuelve el "contenido rico" sin
					// meter bloques arbitrarios adentro de un panel oculto.
					'puntos' => array(
						'tipo'     => 'lista',
						'etiqueta' => __( 'Puntos', 'sofia-studio' ),
						'campos'   => array(
							'texto' => array( 'tipo' => 'texto', 'etiqueta' => __( 'Texto', 'sofia-studio' ) ),
						),
					),
				),
			),
		);
	}

	/**
	 * Capa 2 — APARIENCIA.
	 *
	 * Las etiquetas dicen CUÁNDO, no cómo: es lo único que el generador
	 * por IA tiene para elegir entre tres.
	 */
	public static function schema_propio(): array {
		return array(
			'composicion' => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Composición', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'Arriba — pocas pestañas de título corto', 'sofia-studio' ) ),
					array( 'valor' => 'lateral', 'etiqueta' => __( 'Al costado — títulos largos o más de cuatro', 'sofia-studio' ) ),
					array( 'valor' => 'apiladas', 'etiqueta' => __( 'Apiladas — todo abierto, sin hacer clic', 'sofia-studio' ) ),
				),
			),
			'estilo'      => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Estilo de la pestaña', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => 'linea', 'etiqueta' => __( 'Subrayado', 'sofia-studio' ) ),
					array( 'valor' => 'pildoras', 'etiqueta' => __( 'Píldoras', 'sofia-studio' ) ),
				),
			),
			'tono'        => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Tono', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'Claro', 'sofia-studio' ) ),
					array( 'valor' => 'oscuro', 'etiqueta' => __( 'Oscuro', 'sofia-studio' ) ),
				),
			),
		);
	}

	private const CLASES_COMPOSICION = array(
		'lateral'  => 'sofia-tabs--lateral',
		'apiladas' => 'sofia-tabs--apiladas',
	);

	private const CLASES_ESTILO = array(
		'pildoras' => 'sofia-tabs--pildoras',
	);

	private const CLASES_TONO = array(
		'oscuro' => 'sofia-tabs--oscuro',
	);

	/** Capa 3 — CAJA. */
	public static function claves_estilo_relevantes(): array {
		return parent::PERFIL_SECCION;
	}

	/**
	 * Un solo slug para los patrones interactivos: comparten archivo, y
	 * Sofia_Pagina::dependencias_js() deduplica, así que una página con
	 * Tabs y Modal lo carga una sola vez.
	 *
	 * La composición apilada no necesita JS —muestra todo— pero pedirlo
	 * igual evita que cambiar de composición en el editor deje el bloque
	 * sin script hasta recargar la página.
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
		$en_editor = class_exists( 'Sofia_Modo_Editor' ) && Sofia_Modo_Editor::activo();
		$items     = is_array( $this->props['items'] ?? null ) ? $this->props['items'] : array();
		$apiladas  = 'apiladas' === ( $this->estilo_bloque()['composicion'] ?? '' );

		$clases = array_filter( array(
			'sofia-pieza',
			'sofia-tabs',
			$this->clase_de_variante( self::CLASES_COMPOSICION, 'composicion' ),
			$this->clase_de_variante( self::CLASES_ESTILO, 'estilo' ),
			$this->clase_de_variante( self::CLASES_TONO, 'tono' ),
		) );

		// La composición apilada muestra todo a la vez, así que no es un
		// tablist: marcar como pestañas algo que no se puede activar le
		// miente al lector de pantalla.
		$html  = '<section ' . $this->atributos_seccion( implode( ' ', $clases ) )
			. ( $apiladas ? '' : ' data-sofia-tabs' ) . '>';
		$html .= '<div class="sofia-pieza__interior">';
		$html .= $this->encabezado_html( $en_editor );

		$html .= '<div class="sofia-tabs__grupo"' . ( $apiladas ? '' : ' role="tablist"' ) . ' '
			. $this->atributo_lista( 'items' ) . '>';

		foreach ( array_values( $items ) as $indice => $item ) {
			$html .= $this->pestana_html( $indice, is_array( $item ) ? $item : array(), $apiladas );
		}

		$html .= '</div>';
		$html .= $this->boton_agregar_item( 'items' );
		$html .= '</div>';
		$html .= '</section>';
		return $html;
	}

	/** Etiqueta, título y bajada: lo que le da contexto a las pestañas. */
	private function encabezado_html( bool $en_editor ): string {
		$etiqueta = $this->texto_enriquecido( (string) ( $this->props['etiqueta'] ?? '' ) );
		$titulo   = $this->texto_enriquecido( (string) ( $this->props['titulo'] ?? '' ) );
		$texto    = $this->texto_enriquecido( (string) ( $this->props['texto'] ?? '' ) );

		if ( '' === $etiqueta && '' === $titulo && '' === $texto && ! $en_editor ) {
			return '';
		}

		$html = '<div class="sofia-pieza__encabezado">';
		if ( '' !== $etiqueta || $en_editor ) {
			$html .= '<p class="sofia-pieza__etiqueta" ' . $this->atributo_editable( 'etiqueta' ) . '>' . $etiqueta . '</p>';
		}
		$html .= '<h2 class="sofia-pieza__titulo" ' . $this->atributo_editable( 'titulo' ) . ' '
			. $this->atributo_estilo( 'titulo' ) . '>' . $titulo . '</h2>';
		if ( '' !== $texto || $en_editor ) {
			$html .= '<p class="sofia-pieza__bajada" ' . $this->atributo_editable( 'texto' ) . ' '
				. $this->atributo_estilo( 'texto' ) . '>' . $texto . '</p>';
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * Una pestaña con su panel.
	 *
	 * Pestaña y panel van juntos DENTRO del mismo [data-sofia-item].
	 *
	 * Es un requisito del mecanismo de listas, no una preferencia:
	 * leerItemsDeLista() (editor-iframe.js) reconstruye cada item leyendo
	 * los campos que encuentra adentro de él. La primera versión de este
	 * render ponía los títulos en una lista y los paneles en otra, como
	 * hermanos — así que al editar cualquier pestaña el array se
	 * reconstruía sin los textos de los paneles y se perdían. Pasó de
	 * verdad: quedaron items con {"titulo": ""} y sin campo "texto".
	 *
	 * El layout (pestañas arriba en fila, paneles abajo) lo resuelve el
	 * CSS con `display: contents` y `order`, no el orden del HTML.
	 */
	private function pestana_html( int $indice, array $item, bool $apiladas ): string {
		$titulo = $this->texto_enriquecido( (string) ( $item['titulo'] ?? '' ) );
		$texto  = $this->texto_enriquecido( (string) ( $item['texto'] ?? '' ) );
		$puntos = is_array( $item['puntos'] ?? null ) ? array_values( $item['puntos'] ) : array();

		$html = '<div class="sofia-tabs__item" ' . $this->atributo_item( $indice ) . '>';

		// Apiladas: el título es un encabezado, no un control. Un <button>
		// que no hace nada es un control roto para quien navega con
		// teclado.
		if ( $apiladas ) {
			$html .= '<h3 class="sofia-tabs__pestana" ' . $this->atributo_editable( "items.{$indice}.titulo" ) . '>'
				. $titulo . '</h3>';
		} else {
			$html .= '<button type="button" class="sofia-tabs__pestana" role="tab" data-sofia-tab'
				. ' id="' . esc_attr( $this->id ) . '-tab-' . (int) $indice . '"'
				. ' aria-controls="' . esc_attr( $this->id ) . '-panel-' . (int) $indice . '"'
				. ' aria-selected="' . ( 0 === $indice ? 'true' : 'false' ) . '" '
				. $this->atributo_editable( "items.{$indice}.titulo" ) . '>' . $titulo . '</button>';
		}

		$html .= '<div class="sofia-tabs__panel"' . ( $apiladas ? '' : ' role="tabpanel" data-sofia-panel' )
			. ' id="' . esc_attr( $this->id ) . '-panel-' . (int) $indice . '"'
			. ( $apiladas ? '' : ' aria-labelledby="' . esc_attr( $this->id ) . '-tab-' . (int) $indice . '"' ) . '>';

		$html .= '<p class="sofia-tabs__texto" ' . $this->atributo_editable( "items.{$indice}.texto" ) . ' '
			. $this->atributo_estilo( "items.{$indice}.texto" ) . '>' . $texto . '</p>';

		if ( $puntos ) {
			// atributo_lista_anidada() lleva el camino completo
			// ("tabs.items.0.puntos"): sin el índice del padre, los puntos
			// de dos pestañas serían la misma lista para el editor.
			$html .= '<ul class="sofia-tabs__puntos" '
				. $this->atributo_lista_anidada( "items.{$indice}", 'puntos' ) . '>';
			foreach ( $puntos as $i => $punto ) {
				$html .= '<li class="sofia-tabs__punto" ' . $this->atributo_item( $i ) . ' '
					. $this->atributo_editable( "items.{$indice}.puntos.{$i}.texto" ) . '>'
					. $this->texto_enriquecido( (string) ( $punto['texto'] ?? '' ) ) . '</li>';
			}
			$html .= '</ul>';
		}

		$html .= '</div>';
		$html .= '</div>';
		return $html;
	}
}

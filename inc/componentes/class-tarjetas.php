<?php
/**
 * Tarjetas — una grilla de cosas comparables.
 *
 * Pieza nueva. El tipo "card" que ya existía es UNA tarjeta suelta: un
 * átomo que vive dentro de un Container, no una sección. Las referencias
 * de sitios reales (Foundation, KAYAK) muestran siempre lo mismo: una
 * GRILLA de tarjetas con su encabezado, que es lo que una página usa de
 * verdad.
 *
 * Lo que esas referencias tienen y la card suelta no:
 *
 * 1. Una fila de METADATOS. "2h 12m, sin escalas · Wed 4/2 → Wed 4/9" en
 *    KAYAK, "Last sold 0.14 ETH" en Foundation. Es lo que hace comparable
 *    una tarjeta con otra, y sin eso queda un título con un párrafo.
 *
 * 2. Un DATO DESTACADO al pie, separado del resto ("from $46"). Es lo que
 *    la gente compara primero cuando mira varias tarjetas juntas.
 *
 * Tres composiciones que resuelven casos distintos, con su criterio
 * declarado en las etiquetas del select.
 */
class Sofia_Componente_Tarjetas extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Tarjetas', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		return array(
			'etiqueta' => '',
			'titulo'   => __( 'Dónde quedarse', 'sofia-studio' ),
			'texto'    => __( 'Hoteles chicos, todos a menos de diez minutos del mar.', 'sofia-studio' ),
			'items'    => array(
				array(
					'imagen'   => '',
					'titulo'   => __( 'Careyes', 'sofia-studio' ),
					'texto'    => __( 'Casas de arquitectura mexicana sobre los acantilados.', 'sofia-studio' ),
					'datos'    => array(
						array( 'texto' => __( '12 habitaciones', 'sofia-studio' ) ),
						array( 'texto' => __( 'Playa propia', 'sofia-studio' ) ),
					),
					'destacado' => __( 'Desde $4.900', 'sofia-studio' ),
					'enlace'   => '',
				),
				array(
					'imagen'   => '',
					'titulo'   => __( 'Chamela', 'sofia-studio' ),
					'texto'    => __( 'Mar sin olas y una bahía que se cruza nadando.', 'sofia-studio' ),
					'datos'    => array(
						array( 'texto' => __( '8 habitaciones', 'sofia-studio' ) ),
						array( 'texto' => __( 'Bueno con chicos', 'sofia-studio' ) ),
					),
					'destacado' => __( 'Desde $3.200', 'sofia-studio' ),
					'enlace'   => '',
				),
				array(
					'imagen'   => '',
					'titulo'   => __( 'Tenacatita', 'sofia-studio' ),
					'texto'    => __( 'La selva llega hasta la arena. Snorkel a diez metros.', 'sofia-studio' ),
					'datos'    => array(
						array( 'texto' => __( '20 habitaciones', 'sofia-studio' ) ),
						array( 'texto' => __( 'Con restaurante', 'sofia-studio' ) ),
					),
					'destacado' => __( 'Desde $2.800', 'sofia-studio' ),
					'enlace'   => '',
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
				'etiqueta' => __( 'Tarjetas', 'sofia-studio' ),
				'campos'   => array(
					'imagen'    => array( 'tipo' => 'imagen', 'etiqueta' => __( 'Imagen', 'sofia-studio' ) ),
					'titulo'    => array( 'tipo' => 'texto', 'etiqueta' => __( 'Título', 'sofia-studio' ) ),
					'texto'     => array( 'tipo' => 'texto_largo', 'etiqueta' => __( 'Descripción', 'sofia-studio' ) ),
					// La lista anidada: los datos que hacen comparable una
					// tarjeta con otra.
					'datos'     => array(
						'tipo'     => 'lista',
						'etiqueta' => __( 'Datos', 'sofia-studio' ),
						'campos'   => array(
							'texto' => array( 'tipo' => 'texto', 'etiqueta' => __( 'Dato', 'sofia-studio' ) ),
						),
					),
					'destacado' => array( 'tipo' => 'texto', 'etiqueta' => __( 'Dato destacado (al pie)', 'sofia-studio' ) ),
					'enlace'    => array( 'tipo' => 'url', 'etiqueta' => __( 'Destino', 'sofia-studio' ) ),
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
					array( 'valor' => '', 'etiqueta' => __( 'Grilla — tres o cuatro cosas comparables', 'sofia-studio' ) ),
					array( 'valor' => 'lista', 'etiqueta' => __( 'Lista — imagen al costado, para descripciones largas', 'sofia-studio' ) ),
					array( 'valor' => 'tira', 'etiqueta' => __( 'Tira — se desliza de costado, para muchas', 'sofia-studio' ) ),
				),
			),
			// Solo tiene efecto en la composición "tira": en una grilla no
			// hay nada que recorrer. Se declara igual porque el panel no
			// sabe mostrar un control condicionado a otro, y esconderlo
			// sería peor que ofrecerlo sin efecto.
			'controles'   => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Controles (solo en tira)', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'Ninguno — se desliza con el dedo', 'sofia-studio' ) ),
					array( 'valor' => 'flechas', 'etiqueta' => __( 'Flechas — con mouse no hay gesto de deslizar', 'sofia-studio' ) ),
					array( 'valor' => 'puntos', 'etiqueta' => __( 'Puntos — muestran cuántas hay', 'sofia-studio' ) ),
					array( 'valor' => 'ambos', 'etiqueta' => __( 'Flechas y puntos', 'sofia-studio' ) ),
				),
			),
			'superficie'  => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Superficie', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'Con borde', 'sofia-studio' ) ),
					array( 'valor' => 'elevada', 'etiqueta' => __( 'Elevada — se despega del fondo', 'sofia-studio' ) ),
					array( 'valor' => 'plana', 'etiqueta' => __( 'Plana — sin caja, cuando la imagen ya separa', 'sofia-studio' ) ),
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
		'lista' => 'sofia-tarjetas--lista',
		'tira'  => 'sofia-tarjetas--tira',
	);

	private const CLASES_SUPERFICIE = array(
		'elevada' => 'sofia-tarjetas--elevada',
		'plana'   => 'sofia-tarjetas--plana',
	);

	private const CLASES_TONO = array(
		'oscuro' => 'sofia-tarjetas--oscuro',
	);

	/** Capa 3 — CAJA. */
	public static function claves_estilo_relevantes(): array {
		return parent::PERFIL_SECCION;
	}

	/**
	 * El JS de los controles de la tira.
	 *
	 * Se pide SIEMPRE, no solo cuando la composición es tira: cambiar de
	 * composición en el editor no vuelve a encolar scripts, así que sin
	 * esto la tira quedaría sin controles hasta recargar la página. Es un
	 * archivo compartido y Sofia_Pagina::dependencias_js() deduplica, así
	 * que el costo de pedirlo de más es cero.
	 */
	public function dependencias_js(): array {
		return array( 'interacciones' );
	}

	/**
	 * render(): una estructura para las tres composiciones.
	 *
	 * Los controles de la tira reusan el mecanismo del Carousel, no uno
	 * propio: activarCarousel() (sofia-interacciones.js) ya resuelve
	 * flechas, puntos y sincronización, y lo hace sobre cualquier riel con
	 * [data-sofia-pista]. Un segundo JS casi idéntico sería dos lugares
	 * donde arreglar el mismo bug.
	 */
	public function render(): string {
		$en_editor = class_exists( 'Sofia_Modo_Editor' ) && Sofia_Modo_Editor::activo();
		$items     = is_array( $this->props['items'] ?? null ) ? $this->props['items'] : array();
		$estilo    = $this->estilo_bloque();
		$es_tira   = 'tira' === ( $estilo['composicion'] ?? '' );
		$controles = $es_tira ? (string) ( $estilo['controles'] ?? '' ) : '';

		$clases = array_filter( array(
			'sofia-pieza',
			'sofia-tarjetas',
			$this->clase_de_variante( self::CLASES_COMPOSICION, 'composicion' ),
			$this->clase_de_variante( self::CLASES_SUPERFICIE, 'superficie' ),
			$this->clase_de_variante( self::CLASES_TONO, 'tono' ),
			'' !== $controles ? 'sofia-tarjetas--con-controles' : '',
		) );

		$html  = '<section ' . $this->atributos_seccion( implode( ' ', $clases ) )
			. ( '' !== $controles ? ' data-sofia-carousel' : '' ) . '>';
		$html .= '<div class="sofia-pieza__interior">';
		$html .= $this->encabezado_html( $en_editor );

		// El riel se envuelve solo cuando hay controles: las flechas se
		// posicionan contra él, y sin controles ese <div> sería un nodo de
		// más que el editor tendría que ignorar.
		if ( '' !== $controles ) {
			$html .= '<div class="sofia-tarjetas__riel">';
		}

		$html .= '<div class="sofia-tarjetas__grilla"'
			. ( '' !== $controles ? ' data-sofia-pista' : '' ) . ' '
			. $this->atributo_lista( 'items' ) . '>';

		foreach ( array_values( $items ) as $indice => $item ) {
			$html .= $this->tarjeta_html( $indice, is_array( $item ) ? $item : array(), '' !== $controles );
		}

		$html .= '</div>';

		if ( '' !== $controles ) {
			$html .= $this->controles_html( $controles, count( $items ) );
			$html .= '</div>';
		}

		$html .= $this->boton_agregar_item( 'items' );
		$html .= '</div>';
		$html .= '</section>';
		return $html;
	}

	/**
	 * Flechas y puntos de la tira.
	 *
	 * Mismo contrato de atributos que el Carousel (data-sofia-carousel-
	 * anterior, -siguiente, -punto): es lo que activarCarousel() busca, y
	 * cambiarlo acá exigiría un segundo camino en el JS.
	 *
	 * 44px de lado los dos, que es el mínimo táctil (WCAG 2.5.5) — el
	 * Carousel los tenía en 40 y 9px, y fue un defecto real que el arnés
	 * encontró midiendo.
	 */
	private function controles_html( string $controles, int $cuantos ): string {
		$html = '';

		if ( 'puntos' !== $controles ) {
			$html .= '<button type="button" class="sofia-tarjetas__flecha sofia-tarjetas__flecha--anterior"'
				. ' data-sofia-carousel-anterior aria-label="' . esc_attr__( 'Anterior', 'sofia-studio' ) . '"></button>';
			$html .= '<button type="button" class="sofia-tarjetas__flecha sofia-tarjetas__flecha--siguiente"'
				. ' data-sofia-carousel-siguiente aria-label="' . esc_attr__( 'Siguiente', 'sofia-studio' ) . '"></button>';
		}

		if ( 'flechas' !== $controles ) {
			$html .= '<div class="sofia-tarjetas__puntos">';
			for ( $i = 0; $i < $cuantos; $i++ ) {
				$html .= '<button type="button" class="sofia-tarjetas__punto" data-sofia-carousel-punto="' . (int) $i . '"'
					. ' aria-label="' . esc_attr( sprintf( __( 'Ir a la tarjeta %d', 'sofia-studio' ), $i + 1 ) ) . '"></button>';
			}
			$html .= '</div>';
		}

		return $html;
	}

	/** Etiqueta, título y bajada: lo que le da contexto a las tarjetas. */
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
	 * Una tarjeta.
	 *
	 * El enlace envuelve la tarjeta ENTERA y no solo el título: en una
	 * grilla, la gente hace clic en la imagen o en cualquier parte de la
	 * caja, no apunta al texto. Cuando no hay destino se emite un <div>,
	 * porque un <a href=""> recarga la página al clickearlo.
	 *
	 * El dato destacado va al pie y separado: es lo que se compara primero
	 * cuando hay varias tarjetas juntas (ver el "from $46" de KAYAK).
	 */
	private function tarjeta_html( int $indice, array $item, bool $es_slide = false ): string {
		$imagen    = $this->imagen_o_placeholder( (string) ( $item['imagen'] ?? '' ) );
		$titulo    = $this->texto_enriquecido( (string) ( $item['titulo'] ?? '' ) );
		$texto     = $this->texto_enriquecido( (string) ( $item['texto'] ?? '' ) );
		$destacado = $this->texto_enriquecido( (string) ( $item['destacado'] ?? '' ) );
		$datos     = is_array( $item['datos'] ?? null ) ? array_values( $item['datos'] ) : array();
		$destino   = esc_url( (string) ( $item['enlace'] ?? '' ) );

		$etiqueta = '' !== $destino ? 'a' : 'div';
		$href     = '' !== $destino ? ' href="' . $destino . '"' : '';

		// data-sofia-slide es lo que activarCarousel() cuenta para saber a
		// dónde desplazarse. Solo cuando hay controles: en una grilla no
		// hay nada que recorrer.
		$html = '<' . $etiqueta . ' class="sofia-tarjetas__item"' . $href
			. ( $es_slide ? ' data-sofia-slide' : '' ) . ' ' . $this->atributo_item( $indice ) . '>';

		if ( $imagen ) {
			$html .= '<img class="sofia-tarjetas__imagen" src="' . esc_url( $imagen ) . '" alt="" loading="lazy" '
				. $this->atributo_editable( "items.{$indice}.imagen" ) . '>';
		}

		$html .= '<div class="sofia-tarjetas__cuerpo">';
		$html .= '<h3 class="sofia-tarjetas__titulo" ' . $this->atributo_editable( "items.{$indice}.titulo" ) . ' '
			. $this->atributo_estilo( "items.{$indice}.titulo" ) . '>' . $titulo . '</h3>';
		$html .= '<p class="sofia-tarjetas__texto" ' . $this->atributo_editable( "items.{$indice}.texto" ) . ' '
			. $this->atributo_estilo( "items.{$indice}.texto" ) . '>' . $texto . '</p>';

		if ( $datos ) {
			// atributo_lista_anidada() lleva el camino completo
			// ("cd.items.0.datos"): sin el índice del padre, los datos de
			// dos tarjetas serían la misma lista para el editor.
			$html .= '<ul class="sofia-tarjetas__datos" '
				. $this->atributo_lista_anidada( "items.{$indice}", 'datos' ) . '>';
			foreach ( $datos as $i => $dato ) {
				$html .= '<li class="sofia-tarjetas__dato" ' . $this->atributo_item( $i ) . ' '
					. $this->atributo_editable( "items.{$indice}.datos.{$i}.texto" ) . '>'
					. $this->texto_enriquecido( (string) ( $dato['texto'] ?? '' ) ) . '</li>';
			}
			$html .= '</ul>';
		}

		$html .= '<p class="sofia-tarjetas__destacado" ' . $this->atributo_editable( "items.{$indice}.destacado" ) . '>'
			. $destacado . '</p>';
		$html .= '</div>';
		$html .= '</' . $etiqueta . '>';
		return $html;
	}
}

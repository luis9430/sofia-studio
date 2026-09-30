<?php
/**
 * Preguntas frecuentes — lo que la gente pregunta antes de decidir.
 *
 * REDISEÑADA como PIEZA. La versión anterior era una lista de preguntas y
 * respuestas siempre abiertas: correcta, sin encabezado, y con una sola
 * forma de mostrarse.
 *
 * SOBRE LA RELACIÓN CON EL ACORDEÓN
 *
 * Una FAQ es un PROPÓSITO; un acordeón es un MECANISMO. A veces coinciden
 * —doce preguntas plegadas evitan una página interminable— y a veces no:
 * con cuatro preguntas cortas, esconderlas obliga a hacer clic para leer
 * lo que entraba a la vista.
 *
 * Por eso el plegado es una COMPOSICIÓN de esta pieza y no un componente
 * aparte. El tipo "accordion" sigue existiendo para cuando lo plegable no
 * son preguntas (especificaciones, términos, un temario).
 *
 * Tres composiciones que resuelven casos distintos, con su criterio
 * declarado en las etiquetas del select.
 */
class Sofia_Componente_FAQ extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Preguntas frecuentes', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		return array(
			'etiqueta' => '',
			'titulo'   => __( 'Antes de reservar', 'sofia-studio' ),
			'texto'    => __( 'Lo que más nos preguntan. Si falta algo, escribinos.', 'sofia-studio' ),
			'items'    => array(
				array(
					'pregunta'  => __( '¿Hace falta auto para moverse?', 'sofia-studio' ),
					'respuesta' => __( 'Entre las bahías sí: no hay transporte público y las distancias son de veinte a cuarenta minutos. Podemos conseguirte uno con el hospedaje.', 'sofia-studio' ),
				),
				array(
					'pregunta'  => __( '¿Cuál es la mejor época?', 'sofia-studio' ),
					'respuesta' => __( 'De noviembre a mayo, sin lluvia y con el mar calmo. En agosto llueve de tarde y las playas quedan vacías, que a mucha gente le gusta más.', 'sofia-studio' ),
				),
				array(
					'pregunta'  => __( '¿Se puede ir con chicos?', 'sofia-studio' ),
					'respuesta' => __( 'Sí. Chamela y Tenacatita tienen mar sin olas. Careyes es más abierta y conviene con chicos que ya nadan.', 'sofia-studio' ),
				),
				array(
					'pregunta'  => __( '¿Qué pasa si cancelo?', 'sofia-studio' ),
					'respuesta' => __( 'Hasta 48 horas antes te devolvemos todo, sin preguntas. Después depende del hospedaje, y te lo decimos antes de que pagues.', 'sofia-studio' ),
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
				'etiqueta' => __( 'Preguntas', 'sofia-studio' ),
				'campos'   => array(
					'pregunta'  => array( 'tipo' => 'texto', 'etiqueta' => __( 'Pregunta', 'sofia-studio' ) ),
					'respuesta' => array( 'tipo' => 'texto_largo', 'etiqueta' => __( 'Respuesta', 'sofia-studio' ) ),
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
					array( 'valor' => '', 'etiqueta' => __( 'Abiertas — pocas preguntas, se leen de corrido', 'sofia-studio' ) ),
					array( 'valor' => 'plegadas', 'etiqueta' => __( 'Plegadas — muchas preguntas, para no alargar la página', 'sofia-studio' ) ),
					array( 'valor' => 'dos-columnas', 'etiqueta' => __( 'Dos columnas — respuestas cortas que entran de a pares', 'sofia-studio' ) ),
				),
			),
			'estilo'      => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Separación', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'Con línea', 'sofia-studio' ) ),
					array( 'valor' => 'tarjetas', 'etiqueta' => __( 'En tarjetas', 'sofia-studio' ) ),
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
		'plegadas'     => 'sofia-faq--plegadas',
		'dos-columnas' => 'sofia-faq--dos-columnas',
	);

	private const CLASES_ESTILO = array(
		'tarjetas' => 'sofia-faq--tarjetas',
	);

	private const CLASES_TONO = array(
		'oscuro' => 'sofia-faq--oscuro',
	);

	/** Capa 3 — CAJA. */
	public static function claves_estilo_relevantes(): array {
		return parent::PERFIL_SECCION;
	}

	/**
	 * render(): una estructura para las tres composiciones.
	 *
	 * La composición plegada usa <details>/<summary> nativo, así que abre
	 * y cierra sin una línea de JavaScript y sigue funcionando si el
	 * script no llega a cargar. Las otras dos rinden <div> con el mismo
	 * contenido: el texto de toda respuesta es indexable y editable en el
	 * canvas sin tener que abrir nada primero.
	 */
	public function render(): string {
		$en_editor = class_exists( 'Sofia_Modo_Editor' ) && Sofia_Modo_Editor::activo();
		$items     = is_array( $this->props['items'] ?? null ) ? $this->props['items'] : array();
		$plegadas  = 'plegadas' === ( $this->estilo_bloque()['composicion'] ?? '' );

		$clases = array_filter( array(
			'sofia-pieza',
			'sofia-faq',
			$this->clase_de_variante( self::CLASES_COMPOSICION, 'composicion' ),
			$this->clase_de_variante( self::CLASES_ESTILO, 'estilo' ),
			$this->clase_de_variante( self::CLASES_TONO, 'tono' ),
		) );

		$html  = '<section ' . $this->atributos_seccion( implode( ' ', $clases ) ) . '>';
		$html .= '<div class="sofia-pieza__interior">';
		$html .= $this->encabezado_html( $en_editor );
		$html .= '<div class="sofia-faq__lista" ' . $this->atributo_lista( 'items' ) . '>';

		foreach ( array_values( $items ) as $indice => $item ) {
			$html .= $plegadas
				? $this->item_plegado_html( $indice, is_array( $item ) ? $item : array(), $en_editor )
				: $this->item_abierto_html( $indice, is_array( $item ) ? $item : array() );
		}

		$html .= '</div>';
		$html .= $this->boton_agregar_item( 'items' );
		$html .= '</div>';
		$html .= '</section>';
		return $html;
	}

	/** Etiqueta, título y bajada: lo que le da contexto a las preguntas. */
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

	/** Pregunta y respuesta a la vista. */
	private function item_abierto_html( int $indice, array $item ): string {
		$pregunta  = $this->texto_enriquecido( (string) ( $item['pregunta'] ?? '' ) );
		$respuesta = $this->texto_enriquecido( (string) ( $item['respuesta'] ?? '' ) );

		$html  = '<div class="sofia-faq__item" ' . $this->atributo_item( $indice ) . '>';
		$html .= '<h3 class="sofia-faq__pregunta" ' . $this->atributo_editable( "items.{$indice}.pregunta" ) . ' '
			. $this->atributo_estilo( "items.{$indice}.pregunta" ) . '>' . $pregunta . '</h3>';
		$html .= '<p class="sofia-faq__respuesta" ' . $this->atributo_editable( "items.{$indice}.respuesta" ) . ' '
			. $this->atributo_estilo( "items.{$indice}.respuesta" ) . '>' . $respuesta . '</p>';
		$html .= '</div>';
		return $html;
	}

	/**
	 * Pregunta plegada, con <details> nativo.
	 *
	 * En el editor se emite SIEMPRE abierto: un panel cerrado no se puede
	 * editar en el canvas, y forzarlo con JS no sirve porque el editor no
	 * corre el script del sitio. Mismo criterio que sofia-carousel--
	 * estatico y que los submenús del Header.
	 *
	 * La <h3> va DENTRO del <summary> y no al revés: un <summary> es el
	 * control que abre el panel, y meterlo adentro de un encabezado deja
	 * un heading que no se puede clickear entero.
	 */
	private function item_plegado_html( int $indice, array $item, bool $en_editor ): string {
		$pregunta  = $this->texto_enriquecido( (string) ( $item['pregunta'] ?? '' ) );
		$respuesta = $this->texto_enriquecido( (string) ( $item['respuesta'] ?? '' ) );

		// La primera abierta también en el sitio publicado: una FAQ
		// enteramente cerrada no deja ver de qué se trata, y obliga a un
		// clic solo para saber si vale la pena leerla.
		$abierto = ( $en_editor || 0 === $indice ) ? ' open' : '';

		$html  = '<details class="sofia-faq__item" ' . $this->atributo_item( $indice ) . $abierto . '>';
		$html .= '<summary class="sofia-faq__pregunta">';
		$html .= '<h3 class="sofia-faq__pregunta-texto" ' . $this->atributo_editable( "items.{$indice}.pregunta" ) . ' '
			. $this->atributo_estilo( "items.{$indice}.pregunta" ) . '>' . $pregunta . '</h3>';
		$html .= '</summary>';
		$html .= '<p class="sofia-faq__respuesta" ' . $this->atributo_editable( "items.{$indice}.respuesta" ) . ' '
			. $this->atributo_estilo( "items.{$indice}.respuesta" ) . '>' . $respuesta . '</p>';
		$html .= '</details>';
		return $html;
	}
}

<?php
/**
 * Marquee — una fila de textos que se desplaza sin parar.
 *
 * Animación pura de CSS (@keyframes + translateX), sin JS y sin GSAP:
 * un desplazamiento lineal e infinito es el caso que menos justifica una
 * librería de animación.
 *
 * El truco del bucle sin costura: la lista se renderiza DOS VECES y la
 * animación corre hasta -50%. Cuando la primera copia terminó de salir
 * por la izquierda, la segunda está exactamente donde arrancó la
 * primera, así que el salto al reiniciar es invisible. Es la razón por
 * la que render() duplica el foreach en vez de emitir la lista una sola
 * vez — no es un descuido.
 *
 * La copia va con aria-hidden: para un lector de pantalla el contenido
 * está una sola vez. Y lleva data-sofia-marquee-copia (no los atributos
 * de campo del editor), porque si la copia expusiera [data-sofia-campo]
 * el editor vería cada texto duplicado y notificarListaActualizada()
 * reconstruiría la lista con el doble de items.
 *
 * ACCESIBILIDAD, no opcional acá: movimiento perpetuo es justo lo que
 * prefers-reduced-motion existe para frenar, y para parte de los
 * usuarios no es una molestia estética sino un disparador de mareo o
 * migraña. El CSS detiene la animación en ese caso (ver style.css) y el
 * contenido queda legible y quieto.
 */
class Sofia_Componente_Marquee extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Cinta deslizante', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		return array(
			'items' => array(
				array( 'texto' => __( 'Envíos a todo el país', 'sofia-studio' ) ),
				array( 'texto' => __( 'Garantía de 12 meses', 'sofia-studio' ) ),
				array( 'texto' => __( 'Atención personalizada', 'sofia-studio' ) ),
			),
		);
	}

	/** Capa 1 — CONTENIDO. */
	public static function schema_contenido(): array {
		return array(
			'items' => array(
				'tipo'     => 'lista',
				'etiqueta' => __( 'Textos', 'sofia-studio' ),
				'campos'   => array(
					'texto' => array( 'tipo' => 'texto', 'etiqueta' => __( 'Texto', 'sofia-studio' ) ),
				),
			),
		);
	}

	/**
	 * Capa 2 — APARIENCIA.
	 *
	 * "velocidad" es una lista cerrada de tres y no un número libre de
	 * segundos (regla 2): la velocidad correcta depende de cuánto
	 * contenido hay, y un campo abierto invita a poner un 2 que vuelve el
	 * texto ilegible. Los tres valores mapean a duraciones del CSS.
	 */
	public static function schema_propio(): array {
		return array(
			'velocidad' => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Velocidad', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => 'lenta', 'etiqueta' => __( 'Lenta', 'sofia-studio' ) ),
					array( 'valor' => 'normal', 'etiqueta' => __( 'Normal', 'sofia-studio' ) ),
					array( 'valor' => 'rapida', 'etiqueta' => __( 'Rápida', 'sofia-studio' ) ),
				),
			),
			'direccion' => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Dirección', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => 'izquierda', 'etiqueta' => __( 'Hacia la izquierda', 'sofia-studio' ) ),
					array( 'valor' => 'derecha', 'etiqueta' => __( 'Hacia la derecha', 'sofia-studio' ) ),
				),
			),
		);
	}

	private const CLASES_VELOCIDAD = array(
		'lenta'  => 'sofia-marquee--lenta',
		'rapida' => 'sofia-marquee--rapida',
	);

	private const CLASES_DIRECCION = array(
		'derecha' => 'sofia-marquee--derecha',
	);

	/** Capa 3 — CAJA. */
	public static function claves_estilo_relevantes(): array {
		return parent::PERFIL_SECCION;
	}

	/**
	 * render(): en el EDITOR la cinta se renderiza quieta (clase
	 * --estatico, ver el CSS) y sin la copia duplicada.
	 *
	 * Las dos cosas por el mismo motivo: un texto que se desplaza no se
	 * puede clickear para editarlo — el cursor nunca lo alcanza. Es el
	 * mismo criterio con el que el JS de interacciones no corre en el
	 * canvas, solo que acá la animación es CSS y hay que apagarla desde
	 * el render.
	 */
	public function render(): string {
		$items     = is_array( $this->props['items'] ?? null ) ? $this->props['items'] : array();
		$en_editor = class_exists( 'Sofia_Modo_Editor' ) && Sofia_Modo_Editor::activo();

		$clases = array_filter( array(
			'sofia-marquee',
			$this->clase_de_variante( self::CLASES_VELOCIDAD, 'velocidad' ),
			$this->clase_de_variante( self::CLASES_DIRECCION, 'direccion' ),
			$en_editor ? 'sofia-marquee--estatico' : '',
		) );

		$html  = '<section ' . $this->atributos_seccion( implode( ' ', $clases ) ) . '>';
		$html .= '<div class="sofia-marquee__pista">';

		// Copia real: la que el editor ve y edita.
		$html .= '<div class="sofia-marquee__grupo" ' . $this->atributo_lista( 'items' ) . '>';
		foreach ( array_values( $items ) as $indice => $item ) {
			$texto = $this->texto_enriquecido( (string) ( $item['texto'] ?? '' ) );
			$html .= '<span class="sofia-marquee__item" ' . $this->atributo_item( $indice ) . '>';
			$html .= '<span class="sofia-marquee__texto" '
				. $this->atributo_editable( "items.{$indice}.texto" ) . ' '
				. $this->atributo_estilo( "items.{$indice}.texto" ) . '>' . $texto . '</span>';
			$html .= '</span>';
		}
		$html .= '</div>';

		// Copia decorativa: solo para cerrar el bucle. Sin atributos del
		// editor y sin data-sofia-lista, o la lista se leería duplicada.
		if ( ! $en_editor ) {
			$html .= '<div class="sofia-marquee__grupo" aria-hidden="true" data-sofia-marquee-copia>';
			foreach ( array_values( $items ) as $item ) {
				$texto = $this->texto_enriquecido( (string) ( $item['texto'] ?? '' ) );
				$html .= '<span class="sofia-marquee__item"><span class="sofia-marquee__texto">' . $texto . '</span></span>';
			}
			$html .= '</div>';
		}

		$html .= '</div>';
		$html .= $this->boton_agregar_item( 'items' );
		$html .= '</section>';
		return $html;
	}
}

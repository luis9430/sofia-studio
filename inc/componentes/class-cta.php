<?php
/**
 * Llamado a la acción — el pedido.
 *
 * REDISEÑADO como PIEZA. La versión anterior tenía título, texto y un
 * botón: correcta, y exactamente igual a la de cualquier otro sitio.
 *
 * Lo que le faltaba no era diseño sino CONTENIDO que quite fricción. Un
 * botón solo deja al visitante con las preguntas sin responder ("¿me van
 * a cobrar?", "¿cuánto tardan?"), y esas preguntas son las que frenan el
 * clic. Ahora hay una línea de garantía debajo del botón y una segunda
 * acción para quien todavía no está listo — que es la mayoría.
 *
 * Tres composiciones que resuelven casos distintos, con su criterio
 * declarado en las etiquetas del select.
 */
class Sofia_Componente_CTA extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Llamado a la acción', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		return array(
			'etiqueta'     => '',
			'titulo'       => __( '¿Listo para conocer la costa?', 'sofia-studio' ),
			'texto'        => __( 'Contanos qué buscás y te armamos la ruta. Respondemos el mismo día.', 'sofia-studio' ),
			'boton_texto'  => __( 'Escribinos', 'sofia-studio' ),
			'boton_enlace' => '',
			'link_texto'   => __( 'Ver los destinos', 'sofia-studio' ),
			'link_enlace'  => '',
			'garantia'     => __( 'Sin costo y sin compromiso. Respondemos en el día.', 'sofia-studio' ),
			'imagen'       => '',
		);
	}

	/** Capa 1 — CONTENIDO. */
	public static function schema_contenido(): array {
		return array(
			'etiqueta'     => array( 'tipo' => 'texto', 'etiqueta' => __( 'Etiqueta (arriba del título)', 'sofia-studio' ) ),
			'titulo'       => array( 'tipo' => 'texto', 'etiqueta' => __( 'Título', 'sofia-studio' ) ),
			'texto'        => array( 'tipo' => 'texto_largo', 'etiqueta' => __( 'Texto', 'sofia-studio' ) ),
			'boton_texto'  => array( 'tipo' => 'texto', 'etiqueta' => __( 'Botón principal', 'sofia-studio' ) ),
			'boton_enlace' => array( 'tipo' => 'url', 'etiqueta' => __( 'Destino del botón', 'sofia-studio' ) ),
			'link_texto'   => array( 'tipo' => 'texto', 'etiqueta' => __( 'Acción secundaria', 'sofia-studio' ) ),
			'link_enlace'  => array( 'tipo' => 'url', 'etiqueta' => __( 'Destino de la secundaria', 'sofia-studio' ) ),
			'garantia'     => array( 'tipo' => 'texto', 'etiqueta' => __( 'Línea de garantía (debajo del botón)', 'sofia-studio' ) ),
			'imagen'       => array( 'tipo' => 'imagen', 'etiqueta' => __( 'Imagen (solo en la composición partida)', 'sofia-studio' ) ),
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
					array( 'valor' => '', 'etiqueta' => __( 'Centrado — el cierre clásico de una página', 'sofia-studio' ) ),
					array( 'valor' => 'partido', 'etiqueta' => __( 'Partido — texto a la izquierda, botón o imagen a la derecha', 'sofia-studio' ) ),
					array( 'valor' => 'franja', 'etiqueta' => __( 'Franja — compacto, para cortar entre dos secciones', 'sofia-studio' ) ),
				),
			),
			'tono'        => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Tono', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'Color de marca', 'sofia-studio' ) ),
					array( 'valor' => 'suave', 'etiqueta' => __( 'Suave — cuando la sección de al lado ya es fuerte', 'sofia-studio' ) ),
					array( 'valor' => 'oscuro', 'etiqueta' => __( 'Oscuro', 'sofia-studio' ) ),
				),
			),
		);
	}

	private const CLASES_COMPOSICION = array(
		'partido' => 'sofia-cta--partido',
		'franja'  => 'sofia-cta--franja',
	);

	private const CLASES_TONO = array(
		'suave'  => 'sofia-cta--suave',
		'oscuro' => 'sofia-cta--oscuro',
	);

	/** Capa 3 — CAJA. */
	public static function claves_estilo_relevantes(): array {
		return parent::PERFIL_SECCION;
	}

	/**
	 * render(): una estructura para las tres composiciones.
	 *
	 * La imagen se emite siempre que exista, y el CSS la muestra solo en
	 * la composición partida. Renderizarla condicionalmente movería los
	 * [data-sofia-campo] al cambiar de composición, y el editor perdería
	 * el campo seleccionado.
	 */
	public function render(): string {
		$en_editor = class_exists( 'Sofia_Modo_Editor' ) && Sofia_Modo_Editor::activo();

		$clases = array_filter( array(
			'sofia-pieza',
			'sofia-cta',
			$this->clase_de_variante( self::CLASES_COMPOSICION, 'composicion' ),
			$this->clase_de_variante( self::CLASES_TONO, 'tono' ),
		) );

		$html  = '<section ' . $this->atributos_seccion( implode( ' ', $clases ) ) . '>';
		$html .= '<div class="sofia-pieza__interior sofia-cta__interior">';
		$html .= $this->texto_html( $en_editor );
		$html .= $this->acciones_html( $en_editor );
		$html .= $this->imagen_html();
		$html .= '</div>';
		$html .= '</section>';

		return $html;
	}

	private function texto_html( bool $en_editor ): string {
		$etiqueta = $this->texto_enriquecido( (string) ( $this->props['etiqueta'] ?? '' ) );
		$titulo   = $this->texto_enriquecido( (string) ( $this->props['titulo'] ?? '' ) );
		$texto    = $this->texto_enriquecido( (string) ( $this->props['texto'] ?? '' ) );

		$html = '<div class="sofia-cta__texto">';
		if ( '' !== $etiqueta || $en_editor ) {
			$html .= '<p class="sofia-pieza__etiqueta" ' . $this->atributo_editable( 'etiqueta' ) . '>' . $etiqueta . '</p>';
		}
		$html .= '<h2 class="sofia-cta__titulo" ' . $this->atributo_editable( 'titulo' ) . ' '
			. $this->atributo_estilo( 'titulo' ) . '>' . $titulo . '</h2>';
		if ( '' !== $texto || $en_editor ) {
			$html .= '<p class="sofia-cta__bajada" ' . $this->atributo_editable( 'texto' ) . ' '
				. $this->atributo_estilo( 'texto' ) . '>' . $texto . '</p>';
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * Botón, acción secundaria y garantía.
	 *
	 * La secundaria existe porque la mayoría de los visitantes todavía no
	 * está lista para la acción principal: sin una salida intermedia, el
	 * único camino que les queda es irse.
	 *
	 * La garantía va DEBAJO del botón, no arriba: se lee en el momento
	 * exacto de decidir, que es cuando la duda aparece.
	 */
	private function acciones_html( bool $en_editor ): string {
		$boton    = $this->texto_enriquecido( (string) ( $this->props['boton_texto'] ?? '' ) );
		$link     = $this->texto_enriquecido( (string) ( $this->props['link_texto'] ?? '' ) );
		$garantia = $this->texto_enriquecido( (string) ( $this->props['garantia'] ?? '' ) );

		if ( '' === $boton && '' === $link && '' === $garantia && ! $en_editor ) {
			return '';
		}

		$html = '<div class="sofia-cta__acciones">';
		$html .= '<div class="sofia-cta__botones">';

		if ( '' !== $boton || $en_editor ) {
			$html .= '<a class="sofia-cta__boton" href="' . esc_url( (string) ( $this->props['boton_enlace'] ?? '' ) ) . '" '
				. $this->atributo_editable( 'boton_texto' ) . '>' . $boton . '</a>';
		}
		if ( '' !== $link || $en_editor ) {
			$html .= '<a class="sofia-cta__link" href="' . esc_url( (string) ( $this->props['link_enlace'] ?? '' ) ) . '" '
				. $this->atributo_editable( 'link_texto' ) . '>' . $link . '</a>';
		}

		$html .= '</div>';

		if ( '' !== $garantia || $en_editor ) {
			$html .= '<p class="sofia-cta__garantia" ' . $this->atributo_editable( 'garantia' ) . '>' . $garantia . '</p>';
		}

		$html .= '</div>';
		return $html;
	}

	/**
	 * La imagen, solo visible en la composición partida (ver el CSS).
	 *
	 * imagen_o_placeholder() devuelve "" fuera del editor, así que un CTA
	 * sin imagen no emite un <img src=""> — HTML inválido que el
	 * navegador dibuja como ícono de imagen rota.
	 */
	private function imagen_html(): string {
		$imagen = $this->imagen_o_placeholder( esc_url( (string) ( $this->props['imagen'] ?? '' ) ) );
		if ( ! $imagen ) {
			return '';
		}

		return '<div class="sofia-cta__medio"><img class="sofia-cta__imagen" '
			. $this->atributo_editable( 'imagen' ) . ' src="' . $imagen . '" alt="" loading="lazy"></div>';
	}
}

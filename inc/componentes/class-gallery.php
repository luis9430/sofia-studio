<?php
/**
 * Galería — las fotos.
 *
 * REDISEÑADA como PIEZA. La versión anterior era una grilla de imágenes
 * con pie de foto: correcta, sin encabezado, y sin ninguna forma de
 * componerse distinto. Una galería insertada sola en una página quedaba
 * como un bloque de fotos sin contexto, y nadie sabía qué estaba mirando.
 *
 * Lo que le faltaba no era CSS sino dos cosas:
 *
 * 1. Un ENCABEZADO. Es lo que convierte "cinco fotos" en "cinco fotos de
 *    algo". Todas las piezas del sistema lo tienen.
 *
 * 2. Una composición DESTACADA. El patrón que usa Airbnb —una foto grande
 *    con cuatro chicas al lado— es el estándar de cualquier galería de
 *    producto o de lugar, y con una grilla pareja no se puede hacer: la
 *    primera foto tiene que valer más que las otras.
 *
 * Tres composiciones que resuelven casos distintos, con su criterio
 * declarado en las etiquetas del select.
 */
class Sofia_Componente_Gallery extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Galería', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		return array(
			'etiqueta' => '',
			'titulo'   => __( 'La costa, sin filtros', 'sofia-studio' ),
			'texto'    => __( 'Fotos de gente que estuvo, no de catálogo.', 'sofia-studio' ),
			// Seis y no cinco: con la composición destacada (la primera ocupa
			// 2×2 de una grilla de 3) cinco fotos dejan un hueco en la
			// última fila. Seis la completan, y en la grilla pareja se ven
			// igual de bien.
			'items'    => array(
				array( 'imagen' => '', 'pie' => __( 'Bahía de Careyes al amanecer', 'sofia-studio' ) ),
				array( 'imagen' => '', 'pie' => __( 'El camino a Tenacatita', 'sofia-studio' ) ),
				array( 'imagen' => '', 'pie' => __( 'Chamela desde el mirador', 'sofia-studio' ) ),
				array( 'imagen' => '', 'pie' => __( 'Playa Rosada, marzo', 'sofia-studio' ) ),
				array( 'imagen' => '', 'pie' => __( 'La selva llega hasta la arena', 'sofia-studio' ) ),
				array( 'imagen' => '', 'pie' => __( 'Atardecer desde el muelle', 'sofia-studio' ) ),
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
				'etiqueta' => __( 'Imágenes', 'sofia-studio' ),
				'campos'   => array(
					'imagen' => array( 'tipo' => 'imagen', 'etiqueta' => __( 'Imagen', 'sofia-studio' ) ),
					'pie'    => array( 'tipo' => 'texto', 'etiqueta' => __( 'Pie de foto', 'sofia-studio' ) ),
				),
			),
		);
	}

	/**
	 * Capa 2 — APARIENCIA.
	 *
	 * Las etiquetas dicen CUÁNDO, no cómo: es lo único que el generador
	 * por IA tiene para elegir entre tres.
	 *
	 * "proporcion" no cambia QUÉ muestra la galería, solo cómo recorta.
	 * "libre" deja pasar cada imagen con su proporción real, para cuando
	 * el recorte uniforme es justamente lo que estorba (obras, capturas,
	 * retratos).
	 */
	public static function schema_propio(): array {
		return array(
			'composicion' => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Composición', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'Grilla pareja — cuando todas las fotos valen lo mismo', 'sofia-studio' ) ),
					array( 'valor' => 'destacada', 'etiqueta' => __( 'Destacada — una foto manda y el resto acompaña', 'sofia-studio' ) ),
					array( 'valor' => 'tira', 'etiqueta' => __( 'Tira — se desliza de costado, para muchas fotos', 'sofia-studio' ) ),
				),
			),
			'proporcion'  => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Recorte', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => 'cuadrada', 'etiqueta' => __( 'Cuadrado', 'sofia-studio' ) ),
					array( 'valor' => 'apaisada', 'etiqueta' => __( 'Apaisado (4:3)', 'sofia-studio' ) ),
					array( 'valor' => 'panoramica', 'etiqueta' => __( 'Panorámico (16:9)', 'sofia-studio' ) ),
					array( 'valor' => 'libre', 'etiqueta' => __( 'Sin recortar — cuando la proporción es parte de la foto', 'sofia-studio' ) ),
				),
			),
			'tono'        => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Tono', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'Claro', 'sofia-studio' ) ),
					array( 'valor' => 'oscuro', 'etiqueta' => __( 'Oscuro — las fotos resaltan más sobre fondo oscuro', 'sofia-studio' ) ),
				),
			),
		);
	}

	private const CLASES_COMPOSICION = array(
		'destacada' => 'sofia-gallery--destacada',
		'tira'      => 'sofia-gallery--tira',
	);

	private const CLASES_PROPORCION = array(
		'cuadrada'   => 'sofia-gallery--cuadrada',
		'apaisada'   => 'sofia-gallery--apaisada',
		'panoramica' => 'sofia-gallery--panoramica',
	);

	private const CLASES_TONO = array(
		'oscuro' => 'sofia-gallery--oscuro',
	);

	/** Capa 3 — CAJA. */
	public static function claves_estilo_relevantes(): array {
		return parent::PERFIL_SECCION;
	}

	/**
	 * render(): una estructura para las tres composiciones.
	 *
	 * El pie de foto se emite SIEMPRE, incluso vacío. Si solo se
	 * renderizara cuando tiene texto, una galería recién insertada no
	 * tendría dónde hacer click para escribirlo — el campo existiría en el
	 * schema y sería inalcanzable en el canvas. El CSS le da altura mínima
	 * para que se pueda clickear vacío.
	 */
	public function render(): string {
		$en_editor = class_exists( 'Sofia_Modo_Editor' ) && Sofia_Modo_Editor::activo();
		$items     = is_array( $this->props['items'] ?? null ) ? $this->props['items'] : array();

		$clases = array_filter( array(
			'sofia-pieza',
			'sofia-gallery',
			$this->clase_de_variante( self::CLASES_COMPOSICION, 'composicion' ),
			$this->clase_de_variante( self::CLASES_PROPORCION, 'proporcion' ),
			$this->clase_de_variante( self::CLASES_TONO, 'tono' ),
		) );

		$html  = '<section ' . $this->atributos_seccion( implode( ' ', $clases ) ) . '>';
		$html .= '<div class="sofia-pieza__interior">';
		$html .= $this->encabezado_html( $en_editor );
		$html .= '<div class="sofia-gallery__grilla" ' . $this->atributo_lista( 'items' ) . '>';

		foreach ( array_values( $items ) as $indice => $item ) {
			$html .= $this->foto_html( $indice, is_array( $item ) ? $item : array() );
		}

		$html .= '</div>';
		$html .= $this->boton_agregar_item( 'items' );
		$html .= '</div>';
		$html .= '</section>';
		return $html;
	}

	/** Etiqueta, título y bajada: lo que le da contexto a las fotos. */
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
	 * Una foto con su pie.
	 *
	 * El <figcaption> vive DENTRO del <figure> y no como hermano: es el
	 * contrato del editor (todo campo de un item tiene que estar adentro
	 * de su [data-sofia-item]) y además es el HTML correcto para una foto
	 * con epígrafe.
	 */
	private function foto_html( int $indice, array $item ): string {
		$imagen = $this->imagen_o_placeholder( (string) ( $item['imagen'] ?? '' ) );
		$pie    = $this->texto_enriquecido( (string) ( $item['pie'] ?? '' ) );

		$html = '<figure class="sofia-gallery__item" ' . $this->atributo_item( $indice ) . '>';
		if ( $imagen ) {
			$html .= '<img class="sofia-gallery__imagen" src="' . esc_url( $imagen ) . '" alt="" loading="lazy" '
				. $this->atributo_editable( "items.{$indice}.imagen" ) . '>';
		}
		$html .= '<figcaption class="sofia-gallery__pie" '
			. $this->atributo_editable( "items.{$indice}.pie" ) . ' '
			. $this->atributo_estilo( "items.{$indice}.pie" ) . '>' . $pie . '</figcaption>';
		$html .= '</figure>';
		return $html;
	}
}

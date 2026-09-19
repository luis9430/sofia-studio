<?php
/**
 * Gallery — una grilla de imágenes.
 *
 * El componente más liviano de la Fase 6: sin JS y con un CSS mínimo.
 * La grilla la controla el usuario desde el control "columnas" del Nivel
 * 2 genérico (PERFIL_LISTA), que escribe --sofia-columnas en el style de
 * la <section>; el CSS del tema solo la lee, igual que ya hacen Franja de
 * beneficios y Testimonios.
 *
 * La primera versión de este CSS usaba grid-template-columns con
 * auto-fit, que se ve bien pero IGNORA ese control: el panel ofrecía
 * elegir columnas y no pasaba nada. Es exactamente el defecto que el plan
 * describe — "el sistema sabe la respuesta correcta en un lado y no la
 * consulta en el otro" — y el mismo que dejó 3 controles muertos en List,
 * Nav y Breadcrumb. Declarar PERFIL_LISTA es una promesa: si el bloque no
 * la cumple, el control sobra.
 *
 * Lo único que este archivo agrega sobre "una lista de imágenes" es el
 * recorte uniforme: sin aspect-ratio fijo, fotos de proporciones
 * distintas producen una grilla de filas desparejas, que es el defecto
 * clásico de una galería armada a mano.
 */
class Sofia_Componente_Gallery extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Galería', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		return array(
			'items' => array(
				array( 'imagen' => '', 'pie' => '' ),
				array( 'imagen' => '', 'pie' => '' ),
				array( 'imagen' => '', 'pie' => '' ),
			),
		);
	}

	/** Capa 1 — CONTENIDO. */
	public static function schema_contenido(): array {
		return array(
			'items' => array(
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
	 * "proporcion" es de apariencia y no de contenido porque no cambia
	 * QUÉ muestra la galería, solo cómo recorta. "libre" deja pasar cada
	 * imagen con su proporción real, para cuando el recorte uniforme es
	 * justamente lo que estorba (obras, capturas, retratos).
	 */
	public static function schema_propio(): array {
		return array(
			'proporcion' => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Proporción', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => 'cuadrada', 'etiqueta' => __( 'Cuadrada', 'sofia-studio' ) ),
					array( 'valor' => 'apaisada', 'etiqueta' => __( 'Apaisada (4:3)', 'sofia-studio' ) ),
					array( 'valor' => 'panoramica', 'etiqueta' => __( 'Panorámica (16:9)', 'sofia-studio' ) ),
					array( 'valor' => 'libre', 'etiqueta' => __( 'Sin recortar', 'sofia-studio' ) ),
				),
			),
		);
	}

	private const CLASES_PROPORCION = array(
		'cuadrada'   => 'sofia-gallery--cuadrada',
		'apaisada'   => 'sofia-gallery--apaisada',
		'panoramica' => 'sofia-gallery--panoramica',
	);

	/**
	 * Capa 3 — CAJA. PERFIL_LISTA y no PERFIL_SECCION: es lo que trae el
	 * control de columnas, que acá es el control principal del bloque.
	 */
	public static function claves_estilo_relevantes(): array {
		return parent::PERFIL_LISTA;
	}

	/**
	 * render(): el pie de foto se emite SIEMPRE, incluso vacío. Si solo
	 * se renderizara cuando tiene texto, una galería recién insertada no
	 * tendría dónde hacer click para escribirlo — el campo existiría en
	 * el schema pero sería inalcanzable en el canvas. El CSS le da altura
	 * mínima para que se pueda clickear vacío.
	 */
	public function render(): string {
		$items = is_array( $this->props['items'] ?? null ) ? $this->props['items'] : array();

		$clases = array_filter( array(
			'sofia-gallery',
			$this->clase_de_variante( self::CLASES_PROPORCION, 'proporcion' ),
		) );

		$html  = '<section ' . $this->atributos_seccion( implode( ' ', $clases ) ) . '>';
		$html .= '<div class="sofia-gallery__grilla" ' . $this->atributo_lista( 'items' ) . '>';

		foreach ( array_values( $items ) as $indice => $item ) {
			$imagen = $this->imagen_o_placeholder( (string) ( $item['imagen'] ?? '' ) );
			$pie    = $this->texto_enriquecido( (string) ( $item['pie'] ?? '' ) );

			$html .= '<figure class="sofia-gallery__item" ' . $this->atributo_item( $indice ) . '>';
			if ( $imagen ) {
				$html .= '<img class="sofia-gallery__imagen" src="' . esc_url( $imagen ) . '" alt="" loading="lazy" '
					. $this->atributo_editable( "items.{$indice}.imagen" ) . '>';
			}
			$html .= '<figcaption class="sofia-gallery__pie" '
				. $this->atributo_editable( "items.{$indice}.pie" ) . ' '
				. $this->atributo_estilo( "items.{$indice}.pie" ) . '>' . $pie . '</figcaption>';
			$html .= '</figure>';
		}

		$html .= '</div>';
		$html .= $this->boton_agregar_item( 'items' );
		$html .= '</section>';
		return $html;
	}
}

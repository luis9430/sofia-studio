<?php
/**
 * Bloque Hero — título + imagen, el bloque de apertura típico de una
 * landing. Campos editables: "hero.titulo" (texto), "hero.imagen"
 * (imagen) — mismos nombres que PlantillaPagina.Campos declara del lado
 * de GoPress.
 */
class Sofia_Componente_Hero extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Hero', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		return array(
			'titulo' => __( 'Escribe el título de tu página', 'sofia-studio' ),
			'imagen' => '',
		);
	}

	/**
	 * schema_contenido() (Fase 4 — generador de árboles por IA, ver el
	 * comentario largo en Sofia_Componente): mismos 2 campos que
	 * props_por_defecto() declara arriba, con su tipo real para que el LLM
	 * sepa qué generar.
	 */
	public static function schema_contenido(): array {
		return array(
			'titulo' => array( 'tipo' => 'texto', 'etiqueta' => __( 'Título', 'sofia-studio' ) ),
			'imagen' => array( 'tipo' => 'imagen', 'etiqueta' => __( 'Imagen', 'sofia-studio' ) ),
		);
	}

	/**
	 * Capa 2 — APARIENCIA. Un Hero es la apertura de la página y su
	 * variación más evidente es dónde va la imagen respecto del título:
	 * apilada, al costado, o de fondo con el texto encima. Hasta ahora la
	 * única forma era siempre la primera.
	 *
	 * Las dos primeras se resuelven con utility classes de Core Framework
	 * (regla 3 del contrato); "fondo" sí necesita CSS propio, porque
	 * superponer texto sobre imagen no es layout de caja sino
	 * posicionamiento — ver style.css.
	 */
	public static function schema_propio(): array {
		return array(
			'composicion' => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Composición', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'Título arriba, imagen abajo', 'sofia-studio' ) ),
					array( 'valor' => 'lado', 'etiqueta' => __( 'Imagen al costado', 'sofia-studio' ) ),
					array( 'valor' => 'lado-invertido', 'etiqueta' => __( 'Imagen al costado, a la izquierda', 'sofia-studio' ) ),
					array( 'valor' => 'fondo', 'etiqueta' => __( 'Imagen de fondo, título encima', 'sofia-studio' ) ),
				),
			),
		);
	}

	private const CLASES_COMPOSICION = array(
		'lado'           => 'flex-row gap-l items-middle',
		'lado-invertido' => 'flex-row gap-l items-middle sofia-hero--invertido',
		'fondo'          => 'sofia-hero--fondo',
	);

	/**
	 * claves_estilo_relevantes() (Nivel 2, revisión de arquitectura tras
	 * fricción real de edición — ver la memoria de producto): Hero es una
	 * SECCIÓN completa (título + imagen, con su propio fondo/borde/
	 * espaciado como cualquier sección) PERO además tiene una <img>
	 * real adentro — ni PERFIL_SECCION solo (le faltaría object-fit/
	 * aspect-ratio para la imagen) ni PERFIL_IMAGEN solo (le faltaría
	 * color de fondo/espaciado de la sección) alcanzan por separado, así
	 * que es la unión de ambos en vez de un 5to perfil dedicado para un
	 * solo caso.
	 */
	public static function claves_estilo_relevantes(): array {
		return array_unique( array_merge( parent::PERFIL_SECCION, array( 'aspect_ratio', 'object_fit' ) ) );
	}

	public function render(): string {
		$titulo = $this->texto_enriquecido( $this->props['titulo'] );
		// imagen_o_placeholder() (clase base): sin esto, un Hero recién
		// agregado (sin imagen todavía) no tenía forma de agregarla la
		// primera vez fuera de que el TÍTULO diera algo clickeable — mismo
		// bug real que Sofia_Componente_Image, ver el comentario largo del
		// método.
		$imagen = $this->imagen_o_placeholder( esc_url( $this->props['imagen'] ) );

		$clases = array_filter( array(
			'sofia-hero',
			$this->clase_de_variante( self::CLASES_COMPOSICION, 'composicion' ),
		) );

		$html  = '<section ' . $this->atributos_seccion( implode( ' ', $clases ) ) . '>';
		$html .= '<h1 ' . $this->atributo_editable( 'titulo' ) . ' ' . $this->atributo_estilo( 'titulo' ) . '>' . $titulo . '</h1>';
		if ( $imagen ) {
			$html .= '<img ' . $this->atributo_editable( 'imagen' ) . ' src="' . $imagen . '" alt="' . wp_strip_all_tags( $titulo ) . '">';
		}
		$html .= '</section>';
		return $html;
	}
}

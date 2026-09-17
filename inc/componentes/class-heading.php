<?php
/**
 * Heading — primitiva de Contenido (Fase 2, plan "50 primitivas", ver la
 * memoria de producto): un título con NIVEL semántico configurable
 * (h1-h6), texto enriquecido — distinto de Sofia_Componente_Hero (título +
 * imagen, un bloque compuesto) y de Sofia_Componente_Texto_Libre (un
 * párrafo, siempre <p>): esta primitiva es SOLO el título, pensada para
 * combinarse DENTRO de un Container (ej. un título de sección seguido de
 * una Franja de beneficios), con control real sobre qué nivel de heading
 * usa — algo que Hero no ofrece (siempre <h1>, fijo).
 */
class Sofia_Componente_Heading extends Sofia_Componente {

	/**
	 * NIVELES_PERMITIDOS: whitelist de etiquetas HTML válidas para "nivel"
	 * — nunca una etiqueta arbitraria (mismo criterio que
	 * FUENTES_PERMITIDAS/ICONOS_PERMITIDOS en el resto del catálogo).
	 */
	private const NIVELES_PERMITIDOS = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' );

	public function nombre(): string {
		return __( 'Título', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		return array(
			'texto' => __( 'Título de sección', 'sofia-studio' ),
		);
	}

	/** Capa 1 — CONTENIDO. */
	public static function schema_contenido(): array {
		return array(
			'texto' => array( 'tipo' => 'texto', 'etiqueta' => __( 'Texto', 'sofia-studio' ) ),
		);
	}

	/**
	 * Capa 2 — APARIENCIA. "nivel" vivía en Contenido con el argumento de
	 * que decide la SEMÁNTICA (la jerarquía de la página), no la
	 * apariencia. El argumento es cierto pero el criterio del contrato es
	 * otro: una lista cerrada de opciones que cambia cómo se renderiza el
	 * bloque es apariencia, vaya o no acompañada de un significado. Y en
	 * la práctica importa: como texto libre se editaba escribiendo "h3" de
	 * memoria, sin que nada validara; como select, las seis opciones están
	 * a la vista y no se puede elegir una inválida.
	 *
	 * "tamano" existe aparte porque el nivel semántico y el tamaño visual
	 * no tienen por qué coincidir: un h2 puede necesitar verse discreto en
	 * una sección secundaria sin por eso degradarse a h4 y romper la
	 * jerarquía del documento.
	 */
	public static function schema_propio(): array {
		return array(
			'nivel'  => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Nivel', 'sofia-studio' ),
				'ayuda'    => __( 'La jerarquía del título en la página. Afecta al SEO y a los lectores de pantalla, no al tamaño.', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'Título de sección (h2)', 'sofia-studio' ) ),
					array( 'valor' => 'h1', 'etiqueta' => __( 'Título de la página (h1)', 'sofia-studio' ) ),
					array( 'valor' => 'h3', 'etiqueta' => __( 'Subtítulo (h3)', 'sofia-studio' ) ),
					array( 'valor' => 'h4', 'etiqueta' => __( 'Subtítulo menor (h4)', 'sofia-studio' ) ),
					array( 'valor' => 'h5', 'etiqueta' => __( 'Nivel 5 (h5)', 'sofia-studio' ) ),
					array( 'valor' => 'h6', 'etiqueta' => __( 'Nivel 6 (h6)', 'sofia-studio' ) ),
				),
			),
			'tamano' => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Tamaño', 'sofia-studio' ),
				'ayuda'    => __( 'Independiente del nivel: un título puede ser h2 y verse chico.', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'El del nivel', 'sofia-studio' ) ),
					array( 'valor' => 'chico', 'etiqueta' => __( 'Chico', 'sofia-studio' ) ),
					array( 'valor' => 'grande', 'etiqueta' => __( 'Grande', 'sofia-studio' ) ),
					array( 'valor' => 'display', 'etiqueta' => __( 'Display (muy grande)', 'sofia-studio' ) ),
				),
			),
		);
	}

	/**
	 * claves_estilo_relevantes(): PERFIL_PRIMITIVA — mismo criterio que
	 * Icon/Image/Button: una pieza simple sin caja de sección propia. El
	 * color/tamaño de fuente del título en sí se editan vía Nivel 1
	 * (atributo_estilo() sobre "texto", mismo mecanismo que cualquier
	 * campo editable del catálogo — ver render()), no vía Nivel 2.
	 */
	public static function claves_estilo_relevantes(): array {
		return parent::PERFIL_PRIMITIVA;
	}

	private const CLASES_TAMANO = array(
		'chico'   => 'sofia-heading--chico',
		'grande'  => 'sofia-heading--grande',
		'display' => 'sofia-heading--display',
	);

	/**
	 * render(): la etiqueta HTML sale de NIVELES_PERMITIDOS — un nivel
	 * corrupto o de una versión más nueva del tema cae a "h2" en vez de
	 * emitir una etiqueta inválida.
	 */
	public function render(): string {
		$texto = $this->texto_enriquecido( (string) ( $this->props['texto'] ?? '' ) );
		$nivel = (string) ( $this->estilo_bloque()['nivel'] ?? '' );
		if ( ! in_array( $nivel, self::NIVELES_PERMITIDOS, true ) ) {
			$nivel = 'h2';
		}

		$clases = array_filter( array(
			'sofia-heading',
			$this->clase_de_variante( self::CLASES_TAMANO, 'tamano' ),
		) );

		$html  = '<section ' . $this->atributos_seccion( implode( ' ', $clases ) ) . '>';
		$html .= '<' . $nivel . ' ' . $this->atributo_editable( 'texto' ) . ' ' . $this->atributo_estilo( 'texto' ) . '>' . $texto . '</' . $nivel . '>';
		$html .= '</section>';
		return $html;
	}
}

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
			'texto'  => __( 'Título de sección', 'sofia-studio' ),
			'nivel'  => 'h2',
		);
	}

	/**
	 * schema_contenido(): "nivel" es tipo "texto" (mismo criterio que
	 * "icono" en Sofia_Componente_Icon — el generador de IA recibe la
	 * whitelist real vía el schema de estilo del drawer si hiciera falta
	 * exponerlo ahí más adelante; hoy "nivel" vive como prop de CONTENIDO,
	 * no de Nivel 2, porque decide la SEMÁNTICA del elemento —jerarquía de
	 * la página—, no su apariencia visual).
	 */
	public static function schema_contenido(): array {
		return array(
			'texto' => array( 'tipo' => 'texto', 'etiqueta' => __( 'Texto', 'sofia-studio' ) ),
			'nivel' => array( 'tipo' => 'texto', 'etiqueta' => __( 'Nivel (h1-h6)', 'sofia-studio' ) ),
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

	/**
	 * render(): la etiqueta HTML real sale de NIVELES_PERMITIDOS — un
	 * "nivel" corrupto/desconocido cae a "h2" (el default de
	 * props_por_defecto(), nivel de título de sección más común) en vez
	 * de romper con una etiqueta inválida.
	 */
	public function render(): string {
		$texto = $this->texto_enriquecido( (string) ( $this->props['texto'] ?? '' ) );
		$nivel = (string) ( $this->props['nivel'] ?? '' );
		if ( ! in_array( $nivel, self::NIVELES_PERMITIDOS, true ) ) {
			$nivel = 'h2';
		}

		$html  = '<section ' . $this->atributos_seccion( 'sofia-heading' ) . '>';
		$html .= '<' . $nivel . ' ' . $this->atributo_editable( 'texto' ) . ' ' . $this->atributo_estilo( 'texto' ) . '>' . $texto . '</' . $nivel . '>';
		$html .= '</section>';
		return $html;
	}
}

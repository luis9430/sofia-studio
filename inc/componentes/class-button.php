<?php
/**
 * Primitiva Button (Fase 0, "primitivas de layout" — ver la memoria de
 * producto): un <a> con texto editable y enlace fijo, sola — mismo botón
 * que Sofia_Componente_CTA ya arma como parte de su bloque compuesto
 * (título + texto + botón), extraído acá como pieza mínima reusable
 * DENTRO de un Container en vez de amarrada siempre a un CTA completo.
 *
 * El enlace (href) NO es editable desde Nivel 1/2 — mismo límite conocido
 * que Sofia_Componente_CTA ya documenta: no hay hoy ningún tipo de campo
 * en el vocabulario de schema (ver Sofia_Componente::schema_bloque_generico(),
 * Fase 2) pensado para CONTENIDO (solo para estilo/layout), y
 * atributo_editable() solo sirve para texto visible dentro del elemento,
 * no para un atributo como href. Queda anotado como pendiente futuro si
 * hiciera falta editar la URL desde el editor visual.
 */
class Sofia_Componente_Button extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Botón', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		return array(
			'texto'  => __( 'Botón', 'sofia-studio' ),
			'enlace' => '#',
		);
	}

	/**
	 * schema_contenido() (Fase 4 — generador de árboles por IA): texto del
	 * botón + su enlace (tipo 'url', no 'texto' — mismo criterio que
	 * Sofia_Componente_CTA::boton_enlace).
	 */
	public static function schema_contenido(): array {
		return array(
			'texto'  => array( 'tipo' => 'texto', 'etiqueta' => __( 'Texto del botón', 'sofia-studio' ) ),
			'enlace' => array( 'tipo' => 'url', 'etiqueta' => __( 'Enlace', 'sofia-studio' ) ),
		);
	}

	/**
	 * claves_estilo_relevantes() (Nivel 2, revisión de arquitectura tras
	 * fricción real de edición — ver la memoria de producto): Button es
	 * una primitiva visual simple, sin caja de sección propia — ningún
	 * fondo/borde/sombra/espaciado/z-index PROPIO tiene sentido para un
	 * botón (eso lo maneja el estilo de Nivel 1 del texto/enlace en sí),
	 * solo su posición/tamaño dentro de la página.
	 */
	/**
	 * Capa 2 — APARIENCIA. La jerarquía entre acciones es la variación
	 * más básica de cualquier botón: una página con dos acciones
	 * necesita que una pese más que la otra. Hasta ahora todos los
	 * botones se veían igual de importantes.
	 */
	public static function schema_propio(): array {
		return array(
			'jerarquia' => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Jerarquía', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'Principal', 'sofia-studio' ) ),
					array( 'valor' => 'secundario', 'etiqueta' => __( 'Secundario (contorno)', 'sofia-studio' ) ),
					array( 'valor' => 'discreto', 'etiqueta' => __( 'Discreto (solo texto)', 'sofia-studio' ) ),
				),
			),
			'tamano'    => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Tamaño', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'Base', 'sofia-studio' ) ),
					array( 'valor' => 'chico', 'etiqueta' => __( 'Chico', 'sofia-studio' ) ),
					array( 'valor' => 'grande', 'etiqueta' => __( 'Grande', 'sofia-studio' ) ),
				),
			),
		);
	}

	private const CLASES_JERARQUIA = array(
		'secundario' => 'sofia-button--secundario',
		'discreto'   => 'sofia-button--discreto',
	);

	private const CLASES_TAMANO = array(
		'chico'  => 'sofia-button--chico',
		'grande' => 'sofia-button--grande',
	);

	public static function claves_estilo_relevantes(): array {
		return parent::PERFIL_PRIMITIVA;
	}

	public function render(): string {
		$texto  = $this->texto_enriquecido( $this->props['texto'] );
		$enlace = esc_url( $this->props['enlace'] );

		$html  = '<section ' . $this->atributos_seccion( 'sofia-button' ) . '>';
		$clases = array_filter( array(
			'sofia-button__enlace',
			$this->clase_de_variante( self::CLASES_JERARQUIA, 'jerarquia' ),
			$this->clase_de_variante( self::CLASES_TAMANO, 'tamano' ),
		) );
		$html .= '<a class="' . esc_attr( implode( ' ', $clases ) ) . '" href="' . $enlace . '" ' . $this->atributo_editable( 'texto' ) . ' ' . $this->atributo_estilo( 'texto' ) . '>' . $texto . '</a>';
		$html .= '</section>';
		return $html;
	}
}

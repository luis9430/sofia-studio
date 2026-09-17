<?php
/**
 * Callout — primitiva de Superficies (Fase 3, plan "50 primitivas", ver la
 * memoria de producto): caja de aviso con ícono + texto, variante
 * semántica (Neutro/Éxito/Error, mismo patrón de <select> en Nivel 2 que
 * Sofia_Componente_Badge ya usa para "color").
 *
 * El ícono NO es un campo elegible — se resuelve automáticamente según la
 * variante (decisión confirmada con el usuario: menos fricción, siempre
 * coherente entre color e ícono, en vez de permitir combinaciones raras
 * como "variante Éxito con ícono de flecha"). Reusa
 * Sofia_Componente_Icon::ICONOS_PERMITIDOS, mismo whitelist que el resto
 * del sistema.
 */
class Sofia_Componente_Callout extends Sofia_Componente {

	private const CLASES_VARIANTE = array(
		'exito' => 'sofia-callout--exito',
		'error' => 'sofia-callout--error',
	);

	/**
	 * ICONOS_POR_VARIANTE: nombre de ícono (whitelist de
	 * Sofia_Componente_Icon) que corresponde a cada variante — "" (Neutro)
	 * usa "info" en vez de no mostrar nada, un aviso sin ícono pierde
	 * parte de su función de llamar la atención.
	 */
	private const ICONOS_POR_VARIANTE = array(
		''      => 'info-circle',
		'exito' => 'check',
		'error' => 'x',
	);

	public function nombre(): string {
		return __( 'Callout', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		return array(
			'texto' => __( 'Escribe acá un aviso importante para el visitante.', 'sofia-studio' ),
		);
	}

	public static function schema_contenido(): array {
		return array(
			'texto' => array( 'tipo' => 'texto_largo', 'etiqueta' => __( 'Texto', 'sofia-studio' ) ),
		);
	}

	/**
	 * schema_propio() (Nivel 2): "variante" — mismo patrón que Badge/
	 * Surface, <select> con opciones fijas.
	 */
	public static function schema_propio(): array {
		return array(
			'variante' => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Variante', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'Neutro', 'sofia-studio' ) ),
					array( 'valor' => 'exito', 'etiqueta' => __( 'Éxito', 'sofia-studio' ) ),
					array( 'valor' => 'error', 'etiqueta' => __( 'Error', 'sofia-studio' ) ),
				),
			),
		);
	}

	/**
	 * claves_estilo_relevantes(): PERFIL_SECCION — caja real con fondo/
	 * borde propios, típico de un aviso (aunque el color de fondo real de
	 * la variante lo resuelve el CSS fijo, no un token elegible — ver
	 * style.css).
	 */
	public static function claves_estilo_relevantes(): array {
		return parent::PERFIL_SECCION;
	}

	public function render(): string {
		$texto = $this->texto_enriquecido( (string) ( $this->props['texto'] ?? '' ) );

		$variante = (string) ( $this->estilo_bloque()['variante'] ?? '' );

		$clases = array_filter( array(
			'sofia-callout',
			$this->clase_de_variante( self::CLASES_VARIANTE ),
		) );

		$nombre_icono = self::ICONOS_POR_VARIANTE[ $variante ] ?? 'info-circle';

		$html  = '<section ' . $this->atributos_seccion( implode( ' ', $clases ) ) . '>';
		$html .= $this->svg_icono( $nombre_icono, 20, 'sofia-callout__icono', 'info-circle' );
		$html .= '<p class="sofia-callout__texto" ' . $this->atributo_editable( 'texto' ) . ' ' . $this->atributo_estilo( 'texto' ) . '>' . $texto . '</p>';
		$html .= '</section>';
		return $html;
	}
}

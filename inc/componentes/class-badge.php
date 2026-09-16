<?php
/**
 * Badge — primitiva de Contenido (Fase 2, plan "50 primitivas", ver la
 * memoria de producto): etiqueta chica de texto en una píldora.
 *
 * Dos ejes independientes, cada uno su propio campo (Fase 3 — Acciones y
 * Superficies): "forma" (Neutro/Tag, cambia el radius) y "color" (Neutro/
 * Éxito/Error, cambia el fondo) — separados a propósito para poder
 * combinarlos (ej. un Tag de color Éxito), en vez de una única lista plana
 * de 4 opciones que obligaría a un valor por cada combinación
 * (neutro/tag/exito/error/tag-exito/tag-error...).
 *
 * AMBOS campos viven en Nivel 2 (schema_propio(), "_estilo_bloque"), no en
 * Contenido — mismo patrón ya probado por Sofia_Componente_Container
 * (VARIANTES + schema_propio() con tipo 'select'): un <select> real con
 * opciones fijas y validadas, a diferencia de la versión anterior de este
 * archivo, que guardaba "variante" como texto libre sin validar en el
 * panel de Contenido (el único lugar del catálogo que lo hacía así, sin
 * precedente en ningún otro Componente).
 *
 * "color" solo ofrece Éxito/Error (no Advertencia/Info) — decisión
 * confirmada con el usuario: Core Framework no trae tokens de color reales
 * para esos otros 2 (confirmado con curl contra el CSS real del sitio),
 * y la regla del sistema completo es no inventar un hex suelto sin
 * respaldo de token.
 */
class Sofia_Componente_Badge extends Sofia_Componente {

	/**
	 * CLASES_FORMA/CLASES_COLOR: whitelist de valor guardado → clase CSS
	 * modificadora — un valor desconocido/vacío no agrega ninguna clase
	 * (cae al badge neutro por defecto), mismo criterio que el resto del
	 * catálogo.
	 */
	private const CLASES_FORMA = array(
		'tag' => 'sofia-badge--tag',
	);

	private const CLASES_COLOR = array(
		'exito' => 'sofia-badge--exito',
		'error' => 'sofia-badge--error',
	);

	public function nombre(): string {
		return __( 'Badge', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		return array(
			'texto' => __( 'Nuevo', 'sofia-studio' ),
		);
	}

	public static function schema_contenido(): array {
		return array(
			'texto' => array( 'tipo' => 'texto', 'etiqueta' => __( 'Texto', 'sofia-studio' ) ),
		);
	}

	/**
	 * schema_propio() (Nivel 2 — Estilo de bloque): "forma" y "color", cada
	 * uno un <select> con opciones fijas — mismo patrón que
	 * Sofia_Componente_Container::schema_propio() ya usa para "variante".
	 */
	public static function schema_propio(): array {
		return array(
			'forma' => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Forma', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'Neutro (pastilla)', 'sofia-studio' ) ),
					array( 'valor' => 'tag', 'etiqueta' => __( 'Tag (borde apenas redondeado)', 'sofia-studio' ) ),
				),
			),
			'color' => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Color', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'Neutro', 'sofia-studio' ) ),
					array( 'valor' => 'exito', 'etiqueta' => __( 'Éxito', 'sofia-studio' ) ),
					array( 'valor' => 'error', 'etiqueta' => __( 'Error', 'sofia-studio' ) ),
				),
			),
		);
	}

	/**
	 * claves_estilo_relevantes(): PERFIL_PRIMITIVA — mismo criterio que
	 * Icon/Avatar: pieza chica sin caja de sección propia.
	 */
	public static function claves_estilo_relevantes(): array {
		return parent::PERFIL_PRIMITIVA;
	}

	public function render(): string {
		$texto = $this->texto_enriquecido( (string) ( $this->props['texto'] ?? '' ) );

		// _estilo_bloque, no $this->props directo — mismo bug real ya
		// corregido en Container: el schema_propio() de Nivel 2 SIEMPRE
		// viaja anidado ahí, nunca como clave suelta de $this->props.
		$estilo_bloque = is_array( $this->props['_estilo_bloque'] ?? null ) ? $this->props['_estilo_bloque'] : array();
		$forma         = (string) ( $estilo_bloque['forma'] ?? '' );
		$color         = (string) ( $estilo_bloque['color'] ?? '' );

		$clases = array( 'sofia-badge' );
		if ( isset( self::CLASES_FORMA[ $forma ] ) ) {
			$clases[] = self::CLASES_FORMA[ $forma ];
		}
		if ( isset( self::CLASES_COLOR[ $color ] ) ) {
			$clases[] = self::CLASES_COLOR[ $color ];
		}

		$html  = '<section ' . $this->atributos_seccion( 'sofia-badge-wrapper' ) . '>';
		$html .= '<span class="' . esc_attr( implode( ' ', $clases ) ) . '" ' . $this->atributo_editable( 'texto' ) . '>' . $texto . '</span>';
		$html .= '</section>';
		return $html;
	}
}

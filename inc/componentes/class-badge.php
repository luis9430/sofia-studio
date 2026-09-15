<?php
/**
 * Badge — primitiva de Contenido (Fase 2, plan "50 primitivas", ver la
 * memoria de producto): etiqueta chica de texto en una píldora — "Tag" es
 * su variante (mismo Componente, solo cambia la forma del borde: redondeado
 * completo vs. levemente redondeado, ver VARIANTES abajo).
 *
 * SIN variantes semánticas de color todavía (éxito/advertencia/error/info)
 * — decisión confirmada con el usuario: esos 4 roles llegan recién en la
 * Fase 3 (Acciones y Superficies), cuando se agreguen a
 * Sofia_Estilo_Global::ROLES_SITIO. Por ahora Badge usa un único color
 * neutro (--sofia-color-secundario) — visualmente correcto para un badge
 * genérico ("Nuevo", "Categoría X"), sin prometer un semáforo de estados
 * que todavía no existe.
 */
class Sofia_Componente_Badge extends Sofia_Componente {

	/**
	 * VARIANTES: whitelist de formas — clave = valor guardado en "variante",
	 * valor = clase CSS modificadora. Un valor desconocido/vacío cae al
	 * badge por defecto (pastilla completamente redondeada).
	 */
	private const VARIANTES = array(
		'tag' => 'sofia-badge--tag',
	);

	public function nombre(): string {
		return __( 'Badge', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		return array(
			'texto'    => __( 'Nuevo', 'sofia-studio' ),
			'variante' => '',
		);
	}

	public static function schema_contenido(): array {
		return array(
			'texto'    => array( 'tipo' => 'texto', 'etiqueta' => __( 'Texto', 'sofia-studio' ) ),
			'variante' => array( 'tipo' => 'texto', 'etiqueta' => __( 'Variante (vacío o "tag")', 'sofia-studio' ) ),
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
		$texto    = $this->texto_enriquecido( (string) ( $this->props['texto'] ?? '' ) );
		$variante = (string) ( $this->props['variante'] ?? '' );

		$clases = array( 'sofia-badge' );
		if ( isset( self::VARIANTES[ $variante ] ) ) {
			$clases[] = self::VARIANTES[ $variante ];
		}

		$html  = '<section ' . $this->atributos_seccion( 'sofia-badge-wrapper' ) . '>';
		$html .= '<span class="' . esc_attr( implode( ' ', $clases ) ) . '" ' . $this->atributo_editable( 'texto' ) . '>' . $texto . '</span>';
		$html .= '</section>';
		return $html;
	}
}

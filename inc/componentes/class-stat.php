<?php
/**
 * Stat — primitiva de Datos/UI (Fase 4, plan "50 primitivas", ver la
 * memoria de producto): número grande + etiqueta descriptiva ("500+
 * Clientes") — pieza simple, sin lista, pensada para ir en fila/grilla
 * dentro de un Container (varias Stats mostrando distintas métricas).
 */
class Sofia_Componente_Stat extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Estadística', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		return array(
			'numero'   => '500+',
			'etiqueta' => __( 'Clientes satisfechos', 'sofia-studio' ),
		);
	}

	public static function schema_contenido(): array {
		return array(
			'numero'   => array( 'tipo' => 'texto', 'etiqueta' => __( 'Número', 'sofia-studio' ) ),
			'etiqueta' => array( 'tipo' => 'texto', 'etiqueta' => __( 'Etiqueta', 'sofia-studio' ) ),
		);
	}

	/**
	 * claves_estilo_relevantes(): PERFIL_PRIMITIVA — mismo criterio que
	 * Heading/Badge: pieza chica sin caja de sección propia.
	 */
	public static function claves_estilo_relevantes(): array {
		return parent::PERFIL_PRIMITIVA;
	}

	public function render(): string {
		$numero   = $this->texto_enriquecido( (string) ( $this->props['numero'] ?? '' ) );
		$etiqueta = $this->texto_enriquecido( (string) ( $this->props['etiqueta'] ?? '' ) );

		$html  = '<section ' . $this->atributos_seccion( 'sofia-stat' ) . '>';
		$html .= '<strong class="sofia-stat__numero" ' . $this->atributo_editable( 'numero' ) . ' ' . $this->atributo_estilo( 'numero' ) . '>' . $numero . '</strong>';
		$html .= '<span class="sofia-stat__etiqueta" ' . $this->atributo_editable( 'etiqueta' ) . ' ' . $this->atributo_estilo( 'etiqueta' ) . '>' . $etiqueta . '</span>';
		$html .= '</section>';
		return $html;
	}
}

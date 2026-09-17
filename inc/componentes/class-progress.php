<?php
/**
 * Progress — primitiva de Datos/UI (Fase 4, plan "50 primitivas", ver la
 * memoria de producto): barra de progreso, valor 0-100 como campo de
 * contenido tipo texto (decisión confirmada con el usuario: un selector
 * visual tipo slider queda pospuesto, mismo criterio que el selector de
 * íconos de Icon) — el valor se aplica como ancho real vía CSS inline
 * (width:{N}%), la única forma real de que una barra de progreso refleje
 * un número arbitrario sin depender de una escala fija de tokens.
 */
class Sofia_Componente_Progress extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Progreso', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		return array(
			'valor'    => '60',
			'etiqueta' => __( 'Progreso', 'sofia-studio' ),
		);
	}

	public static function schema_contenido(): array {
		return array(
			'valor'    => array( 'tipo' => 'texto', 'etiqueta' => __( 'Valor (0-100)', 'sofia-studio' ) ),
			'etiqueta' => array( 'tipo' => 'texto', 'etiqueta' => __( 'Etiqueta', 'sofia-studio' ) ),
		);
	}

	/**
	 * claves_estilo_relevantes(): PERFIL_PRIMITIVA — pieza simple sin caja
	 * de sección propia; el color de la barra en sí es fijo (--sofia-color-
	 * primario), no configurable desde Nivel 2 todavía (mismo límite ya
	 * documentado en otras primitivas simples del catálogo).
	 */
	public static function claves_estilo_relevantes(): array {
		return parent::PERFIL_PRIMITIVA;
	}

	public function render(): string {
		$valor    = $this->valor_acotado( 'valor', 0, 100 );
		$etiqueta = $this->texto_enriquecido( (string) ( $this->props['etiqueta'] ?? '' ) );

		$html  = '<section ' . $this->atributos_seccion( 'sofia-progress' ) . '>';
		$html .= '<span class="sofia-progress__etiqueta" ' . $this->atributo_editable( 'etiqueta' ) . ' ' . $this->atributo_estilo( 'etiqueta' ) . '>' . $etiqueta . '</span>';
		$html .= '<div class="sofia-progress__pista" role="progressbar" aria-valuenow="' . $valor . '" aria-valuemin="0" aria-valuemax="100">';
		$html .= '<div class="sofia-progress__barra" style="width:' . $valor . '%"></div>';
		$html .= '</div>';
		$html .= '</section>';
		return $html;
	}
}

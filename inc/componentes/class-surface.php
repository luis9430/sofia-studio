<?php
/**
 * Surface — primitiva de Superficies (Fase 3, plan "50 primitivas", ver la
 * memoria de producto): una caja visual real (fondo/borde/sombra/radius,
 * PERFIL_SECCION) que puede contener hijos.
 *
 * SÍ necesita el mismo <div class="sofia-container"> interno que
 * Sofia_Componente_Container usa, aunque Surface no ofrezca ningún control
 * de layout propio — esa clase exacta, sin ninguna clase de layout de Core
 * Framework agregada, es el CONTRATO real que usa inc/js/editor-iframe.js
 * para saber "acá se pueden soltar/leer hijos" (ver contenedorDeHijos(),
 * leerBloquesDesde(), la línea de inserción vacía) — no es solo una clase
 * de layout, es también el marcador de "esto es un contenedor de hijos"
 * para todo el mecanismo de arrastrar/insertar/reordenar del editor. Bug
 * real encontrado probando en vivo: sin este <div>, Surface renderizaba
 * sus hijos bien pero el editor no ofrecía ninguna forma de arrastrar
 * nada adentro — el "+" de inserción vacía nunca aparecía. Si hace falta
 * acomodar varios hijos en fila/columna/grilla adentro de una Surface, se
 * anida un Container real ahí dentro (con sus propias clases de layout) —
 * Surface en sí sigue sin imponer ningún acomodo, solo ahora expone el
 * mismo punto de enganche que el editor ya espera de cualquier contenedor
 * de hijos.
 *
 * "Panel" es su variante (Fase 3): mismo Componente, con menos sombra y
 * radius más chico vía clase CSS modificadora — mismo patrón que
 * Sofia_Componente_Badge::CLASES_FORMA (whitelist plana valor→clase, sin
 * necesidad de resolver overrides de props como Container sí hace).
 */
class Sofia_Componente_Surface extends Sofia_Componente {

	private const CLASES_VARIANTE = array(
		'panel' => 'sofia-surface--panel',
	);

	public function nombre(): string {
		return __( 'Surface', 'sofia-studio' );
	}

	/**
	 * claves_estilo_relevantes(): PERFIL_SECCION — a diferencia de
	 * Container (que no tiene caja visual propia), Surface SIEMPRE es una
	 * caja real con fondo/borde/sombra/radius propios.
	 */
	public static function claves_estilo_relevantes(): array {
		return parent::PERFIL_SECCION;
	}

	/**
	 * schema_propio() (Nivel 2): "variante" — mismo patrón de <select> con
	 * opciones fijas que Container/Badge ya usan.
	 */
	public static function schema_propio(): array {
		return array(
			'variante' => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Variante', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'Surface', 'sofia-studio' ) ),
					array( 'valor' => 'panel', 'etiqueta' => __( 'Panel (más sutil)', 'sofia-studio' ) ),
				),
			),
		);
	}

	public function render(): string {
		$estilo_bloque = is_array( $this->props['_estilo_bloque'] ?? null ) ? $this->props['_estilo_bloque'] : array();
		$variante      = (string) ( $estilo_bloque['variante'] ?? '' );

		$clases = array( 'sofia-surface' );
		if ( isset( self::CLASES_VARIANTE[ $variante ] ) ) {
			$clases[] = self::CLASES_VARIANTE[ $variante ];
		}

		$html  = '<section ' . $this->atributos_seccion( implode( ' ', $clases ) ) . '>';
		$html .= '<div class="sofia-container">';
		foreach ( $this->hijos as $hijo ) {
			$html .= $hijo->render();
		}
		$html .= '</div>';
		$html .= '</section>';
		return $html;
	}
}

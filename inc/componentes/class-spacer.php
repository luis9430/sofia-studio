<?php
/**
 * Spacer — primitiva de Layout (Fase 1, plan "50 primitivas", ver la
 * memoria de producto): un espacio vertical en blanco, del tamaño que el
 * usuario elija — mismo rol que Divider (sin contenido editable), pero
 * sin ninguna marca visual, solo separación.
 *
 * A diferencia del resto del catálogo, NO usa "espaciado_vertical" de
 * Nivel 2 genérico (que agrega padding ARRIBA Y ABAJO de cualquier
 * contenido) — Spacer directamente ES el espacio, así que su altura
 * propia es la única prop que necesita: un campo de contenido nuevo
 * "alto" (medida_token, mismo mecanismo de Core Framework que el resto
 * del sistema), no un control de Nivel 2.
 */
class Sofia_Componente_Spacer extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Espaciador', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		return array(
			// "cf:space-xl" — escala REAL de Core Framework (xs/s/m/l/xl,
			// ver Sofia_Estilo_Global::ETIQUETAS_ESCALA), un espaciador
			// sin configurar arranca con el salto más grande de la escala
			// (tiene sentido: para "un poco de aire" ya existe
			// espaciado_vertical de Nivel 2 en cualquier otro Componente,
			// Spacer se usa quien quiere un salto GRANDE deliberado).
			'alto' => 'cf:space-xl',
		);
	}

	/**
	 * Sin schema_contenido(): un Spacer no dice nada, solo ocupa lugar.
	 * Su único campo es una MEDIDA, y una medida es apariencia — ver
	 * schema_propio().
	 */

	/**
	 * Capa 2 — APARIENCIA. "alto" vivía en Contenido como texto libre, así
	 * que en el panel había que escribir "cf:space-xl" de memoria. Como
	 * medida_token usa el selector visual de espaciado que el sistema ya
	 * tiene (Compacto/Base/Amplio con su preview), el mismo que cualquier
	 * otro control de medida del editor.
	 */
	public static function schema_propio(): array {
		return array(
			'alto' => array(
				'tipo'      => 'medida_token',
				'categoria' => 'space',
				'etiqueta'  => __( 'Alto', 'sofia-studio' ),
			),
		);
	}

	/**
	 * claves_estilo_relevantes(): solo "ancho"/"alineacion_bloque" tienen
	 * sentido real acá (un Spacer SIEMPRE es 100% de ancho salvo que el
	 * usuario quiera limitarlo por algún motivo puntual) — ninguno de los
	 * PERFIL_* calza igual de ajustado que para Divider, mismo criterio
	 * de array a mano.
	 */
	public static function claves_estilo_relevantes(): array {
		return array( 'ancho', 'alineacion_bloque' );
	}

	/**
	 * render(): "alto" se resuelve igual que cualquier medida_token del
	 * sistema (token de Core Framework o medida libre validada) — mismo
	 * patrón que Sofia_Componente::atributo_estilo_bloque() usa para
	 * "espaciado_vertical"/"radius"/"offset_x", pero acá vive en render()
	 * en vez de en la clase base porque "alto" es un campo de CONTENIDO
	 * propio de Spacer (Nivel 1, ver schema_contenido() arriba), no un
	 * control genérico de Nivel 2 que cualquier Componente pudiera tener.
	 */
	public function render(): string {
		// El valor puede venir del schema_propio() (Nivel 2) o, en
		// contenido guardado antes de la migración, de props directo —
		// se leen los dos para no romper páginas ya publicadas.
		$alto = (string) ( $this->estilo_bloque()['alto'] ?? $this->props['alto'] ?? '' );

		$estilo = '';
		if ( '' !== $alto ) {
			if ( Sofia_Estilo_Global::es_token_con_nombre( $alto ) ) {
				$estilo = ' style="height:' . esc_attr( Sofia_Estilo_Global::resolver_valor( $alto ) ) . '"';
			} elseif ( preg_match( '/^\d+(\.\d+)?(px|rem|%|vh)$/', $alto ) ) {
				$estilo = ' style="height:' . esc_attr( $alto ) . '"';
			}
		}

		return '<section ' . $this->atributos_seccion( 'sofia-spacer' ) . '><div class="sofia-spacer__espacio"' . $estilo . '></div></section>';
	}
}

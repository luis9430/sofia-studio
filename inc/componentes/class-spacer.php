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
	 * schema_contenido() (Fase 4 — generador de árboles por IA): "alto" es
	 * el único campo real — tipo "texto" porque acepta tanto un token
	 * ("cf:space-8") como una medida libre ("4rem"), mismo criterio que
	 * cualquier otro campo de medida_token del sistema (no existe un tipo
	 * de schema_contenido dedicado a "medida", solo texto/texto_largo/
	 * imagen/url/lista — ver Sofia_Componente::schema_contenido()).
	 *
	 * PENDIENTE: desde que el panel de contenido existe, esto se ve como un
	 * input donde hay que escribir "cf:space-xl" de memoria, cuando el
	 * sistema ya tiene un selector visual de tokens de espaciado
	 * (CampoTokenVisual con categoria="space"). Al migrar este Componente
	 * al contrato de tres capas, "alto" debería pasar a la capa de
	 * apariencia como medida_token, que es lo que realmente es.
	 */
	public static function schema_contenido(): array {
		return array(
			'alto' => array( 'tipo' => 'texto', 'etiqueta' => __( 'Alto', 'sofia-studio' ) ),
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
		$alto = (string) ( $this->props['alto'] ?? '' );

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

<?php
/**
 * RichText — primitiva de Contenido (Fase 2, plan "50 primitivas", ver la
 * memoria de producto): texto con PÁRRAFOS MÚLTIPLES reales, a diferencia
 * de Sofia_Componente_Texto_Libre (un único <p>, todo el contenido en una
 * sola línea de flujo).
 *
 * Alcance deliberadamente acotado (confirmado con el usuario): NO agrega
 * listas/títulos internos ni un editor de formato nuevo en el drawer —
 * eso exigiría tanto ampliar la whitelist de wp_kses() como dar controles
 * reales en DrawerEstilo.jsx para aplicar esas etiquetas (hoy el drawer
 * solo ofrece negrita/cursiva, ver Sofia_Componente::ETIQUETAS_FORMATO_
 * PERMITIDAS), trabajo de UI fuera de esta fase. La diferencia real con
 * Texto libre es que el contenteditable es un <div> (no un <p>), así que
 * el navegador inserta <p> reales al presionar Enter — mismo
 * comportamiento nativo de cualquier editor "rich text" simple, sin
 * necesitar JS propio para separar párrafos a mano.
 */
class Sofia_Componente_Rich_Text extends Sofia_Componente {

	/**
	 * ETIQUETAS_FORMATO_PERMITIDAS propia: mismo whitelist de
	 * negrita/cursiva/salto de línea que la clase base (b/strong/i/em/br),
	 * MÁS "p" — la única etiqueta nueva real, necesaria para que los
	 * párrafos que el navegador ya inserta sobrevivan el guardado (sin
	 * esto, wp_kses() de la clase base los descartaría, aplanando todo a
	 * texto corrido). No se hace override de texto_enriquecido() (privado
	 * en la clase base, wp_kses() con ETIQUETAS_FORMATO_PERMITIDAS fijo)
	 * — se llama a wp_kses() directo acá con esta whitelist propia,
	 * mismo patrón de "un Componente con necesidad propia no toca lo
	 * genérico, resuelve su caso puntual".
	 */
	private const ETIQUETAS_FORMATO_PERMITIDAS = array(
		'p'      => array(),
		'b'      => array(),
		'strong' => array(),
		'i'      => array(),
		'em'     => array(),
		'br'     => array(),
	);

	public function nombre(): string {
		return __( 'Texto enriquecido', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		return array(
			'contenido' => '<p>' . __( 'Primer párrafo de contenido.', 'sofia-studio' ) . '</p><p>' . __( 'Segundo párrafo, para mostrar el espaciado real entre párrafos.', 'sofia-studio' ) . '</p>',
		);
	}

	public static function schema_contenido(): array {
		return array(
			'contenido' => array( 'tipo' => 'texto_largo', 'etiqueta' => __( 'Contenido', 'sofia-studio' ) ),
		);
	}

	// Sin override de claves_estilo_relevantes(): hereda PERFIL_SECCION de
	// la clase base, que es exactamente lo que corresponde acá — mismo caso
	// que Texto_Libre. Es una sección de contenido completa, con fondo y
	// espaciado propios con sentido real (ej. un bloque de texto largo con
	// fondo distinto al resto de la página).

	public function render(): string {
		$contenido = wp_kses( (string) ( $this->props['contenido'] ?? '' ), self::ETIQUETAS_FORMATO_PERMITIDAS );

		$html  = '<section ' . $this->atributos_seccion( 'sofia-rich-text' ) . '>';
		$html .= '<div class="sofia-rich-text__contenido" ' . $this->atributo_editable( 'contenido' ) . ' ' . $this->atributo_estilo( 'contenido' ) . '>' . $contenido . '</div>';
		$html .= '</section>';
		return $html;
	}
}

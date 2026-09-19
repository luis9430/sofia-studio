<?php
/**
 * Link — primitiva de Acciones (Fase 3, plan "50 primitivas", ver la
 * memoria de producto): un <a> de texto simple (subrayado, sin fondo/
 * padding de botón) — Componente PHP propio, separado de
 * Sofia_Componente_Button (decisión confirmada con el usuario: cada uno
 * su propia entrada en "Agregar bloque", en vez de un campo "variante"
 * sobre Button que obligaría a saber que existe para encontrarlo).
 *
 * Mismo límite ya documentado en Button/CTA: el enlace (href) NO es
 * editable desde Nivel 1/2 (no hay hoy un tipo de campo en el vocabulario
 * de schema pensado para editar un atributo, solo texto visible) — queda
 * anotado como pendiente futuro si hiciera falta.
 */
class Sofia_Componente_Link extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Link', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		return array(
			'texto'  => __( 'Leer más', 'sofia-studio' ),
			'enlace' => '#',
		);
	}

	public static function schema_contenido(): array {
		return array(
			'texto'  => array( 'tipo' => 'texto', 'etiqueta' => __( 'Texto del link', 'sofia-studio' ) ),
			'enlace' => array( 'tipo' => 'url', 'etiqueta' => __( 'Enlace', 'sofia-studio' ) ),
		);
	}

	/**
	 * Capa 2 — APARIENCIA: solo "tono".
	 *
	 * Antes de la Fase F este bloque no tenía capa de apariencia y salía
	 * siempre en el color primario. SCHEMA_TONO vive en la clase base
	 * porque los cinco roles son del SITIO, no de este bloque — ver el
	 * comentario largo ahí.
	 */
	public static function schema_propio(): array {
		return array( 'tono' => parent::SCHEMA_TONO );
	}

	/**
	 * claves_estilo_relevantes(): PERFIL_PRIMITIVA — mismo criterio que
	 * Button: pieza visual simple, sin caja de sección propia.
	 */
	public static function claves_estilo_relevantes(): array {
		return parent::PERFIL_PRIMITIVA;
	}

	public function render(): string {
		$texto  = $this->texto_enriquecido( $this->props['texto'] );
		$enlace = esc_url( $this->props['enlace'] );

		$html  = '<section ' . $this->atributos_seccion( trim( 'sofia-link ' . $this->clase_de_tono() ) ) . '>';
		$html .= '<a class="sofia-link__enlace" href="' . $enlace . '" ' . $this->atributo_editable( 'texto' ) . ' ' . $this->atributo_estilo( 'texto' ) . '>' . $texto . '</a>';
		$html .= '</section>';
		return $html;
	}
}

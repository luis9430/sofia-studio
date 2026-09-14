<?php
/**
 * Bloque Hero — título + imagen, el bloque de apertura típico de una
 * landing. Campos editables: "hero.titulo" (texto), "hero.imagen"
 * (imagen) — mismos nombres que PlantillaPagina.Campos declara del lado
 * de GoPress.
 */
class Sofia_Componente_Hero extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Hero', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		return array(
			'titulo' => __( 'Escribe el título de tu página', 'sofia-studio' ),
			'imagen' => '',
		);
	}

	/**
	 * schema_contenido() (Fase 4 — generador de árboles por IA, ver el
	 * comentario largo en Sofia_Componente): mismos 2 campos que
	 * props_por_defecto() declara arriba, con su tipo real para que el LLM
	 * sepa qué generar.
	 */
	public static function schema_contenido(): array {
		return array(
			'titulo' => array( 'tipo' => 'texto', 'etiqueta' => __( 'Título', 'sofia-studio' ) ),
			'imagen' => array( 'tipo' => 'imagen', 'etiqueta' => __( 'Imagen', 'sofia-studio' ) ),
		);
	}

	public function render(): string {
		$titulo = $this->texto_enriquecido( $this->props['titulo'] );
		// imagen_o_placeholder() (clase base): sin esto, un Hero recién
		// agregado (sin imagen todavía) no tenía forma de agregarla la
		// primera vez fuera de que el TÍTULO diera algo clickeable — mismo
		// bug real que Sofia_Componente_Image, ver el comentario largo del
		// método.
		$imagen = $this->imagen_o_placeholder( esc_url( $this->props['imagen'] ) );

		$html  = '<section ' . $this->atributos_seccion( 'sofia-hero' ) . '>';
		$html .= '<h1 ' . $this->atributo_editable( 'titulo' ) . ' ' . $this->atributo_estilo( 'titulo' ) . '>' . $titulo . '</h1>';
		if ( $imagen ) {
			$html .= '<img ' . $this->atributo_editable( 'imagen' ) . ' src="' . $imagen . '" alt="' . wp_strip_all_tags( $titulo ) . '">';
		}
		$html .= '</section>';
		return $html;
	}
}

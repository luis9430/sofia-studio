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

	public function render(): string {
		$titulo = $this->texto_enriquecido( $this->props['titulo'] );
		$imagen = esc_url( $this->props['imagen'] );

		$html  = '<section class="sofia-hero">';
		$html .= '<h1 ' . $this->atributo_editable( 'hero', 'titulo' ) . '>' . $titulo . '</h1>';
		if ( $imagen ) {
			$html .= '<img ' . $this->atributo_editable( 'hero', 'imagen' ) . ' src="' . $imagen . '" alt="' . wp_strip_all_tags( $titulo ) . '">';
		}
		$html .= '</section>';
		return $html;
	}
}

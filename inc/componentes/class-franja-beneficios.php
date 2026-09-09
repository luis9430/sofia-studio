<?php
/**
 * Bloque Franja de beneficios — 3 columnas fijas (título + texto cada
 * una) en v1. Sin Nivel 2 todavía (ver la memoria de producto "tema WP
 * con editor de contenido"), así que el número de columnas es fijo, no
 * configurable por el usuario. Campos editables: "franja_beneficios.titulo_N"
 * / "franja_beneficios.texto_N" para N en 1..3.
 */
class Sofia_Componente_Franja_Beneficios extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Franja de beneficios', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		$defaults = array();
		for ( $i = 1; $i <= 3; $i++ ) {
			$defaults[ "titulo_{$i}" ] = sprintf( __( 'Beneficio %d', 'sofia-studio' ), $i );
			$defaults[ "texto_{$i}" ]  = __( 'Describe este beneficio en una línea.', 'sofia-studio' );
		}
		return $defaults;
	}

	public function render(): string {
		$html = '<section class="sofia-franja-beneficios"><div class="sofia-franja-beneficios__grid">';
		for ( $i = 1; $i <= 3; $i++ ) {
			$titulo = $this->texto_enriquecido( $this->props[ "titulo_{$i}" ] );
			$texto  = $this->texto_enriquecido( $this->props[ "texto_{$i}" ] );
			$html  .= '<div class="sofia-franja-beneficios__item">';
			$html  .= '<h3 ' . $this->atributo_editable( 'franja_beneficios', "titulo_{$i}" ) . '>' . $titulo . '</h3>';
			$html  .= '<p ' . $this->atributo_editable( 'franja_beneficios', "texto_{$i}" ) . '>' . $texto . '</p>';
			$html  .= '</div>';
		}
		$html .= '</div></section>';
		return $html;
	}
}

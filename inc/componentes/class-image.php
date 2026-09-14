<?php
/**
 * Primitiva Image (Fase 0, "primitivas de layout" — ver la memoria de
 * producto): una <img> editable sola, sin título ni estructura extra —
 * mismo rol de "pieza mínima" que Sofia_Componente_Texto_Libre cumple
 * para texto, pero para imagen. A diferencia de Sofia_Componente_Hero
 * (título + imagen, un bloque COMPUESTO), esta primitiva es de un solo
 * campo — pensada para combinarse DENTRO de un Container (ver
 * class-container.php) en vez de usarse siempre como sección completa
 * por sí sola, aunque ambos usos son válidos.
 */
class Sofia_Componente_Image extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Imagen', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		return array(
			'imagen' => '',
		);
	}

	public function render(): string {
		$imagen = esc_url( $this->props['imagen'] );

		$html = '<section ' . $this->atributos_seccion( 'sofia-image' ) . '>';
		if ( $imagen ) {
			$html .= '<img ' . $this->atributo_editable( 'imagen' ) . ' src="' . $imagen . '" alt="">';
		}
		$html .= '</section>';
		return $html;
	}
}

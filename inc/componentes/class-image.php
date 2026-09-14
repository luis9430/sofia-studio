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

	/**
	 * schema_contenido() (Fase 4 — generador de árboles por IA): un único
	 * campo de imagen.
	 */
	public static function schema_contenido(): array {
		return array(
			'imagen' => array( 'tipo' => 'imagen', 'etiqueta' => __( 'Imagen', 'sofia-studio' ) ),
		);
	}

	public function render(): string {
		// imagen_o_placeholder() (clase base): sin esto, un bloque Image
		// recién agregado (sin valor todavía) no emitía ningún <img> — un
		// <section> completamente vacío no tiene NADA clickeable para
		// abrir el selector de medios, así que el usuario no tenía forma
		// de agregar la imagen la primera vez (bug real reportado tras
		// probar en vivo). Ver el comentario largo del método.
		$imagen = $this->imagen_o_placeholder( esc_url( $this->props['imagen'] ) );

		$html = '<section ' . $this->atributos_seccion( 'sofia-image' ) . '>';
		if ( $imagen ) {
			$html .= '<img ' . $this->atributo_editable( 'imagen' ) . ' src="' . $imagen . '" alt="">';
		}
		$html .= '</section>';
		return $html;
	}
}

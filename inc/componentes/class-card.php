<?php
/**
 * Card — primitiva de Superficies (Fase 3, plan "50 primitivas", ver la
 * memoria de producto): imagen + título + texto + botón, los 4 campos
 * siempre fijos (mismo patrón que Sofia_Componente_CTA, con imagen
 * agregada) — pensada para vivir dentro de un Container variante Grid
 * (varias Cards en fila/grilla), sin necesitar su propio sistema de
 * columnas.
 */
class Sofia_Componente_Card extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Card', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		return array(
			'imagen'       => '',
			'titulo'       => __( 'Título de la tarjeta', 'sofia-studio' ),
			'texto'        => __( 'Describe brevemente este elemento.', 'sofia-studio' ),
			'boton_texto'  => __( 'Ver más', 'sofia-studio' ),
			'boton_enlace' => '#',
		);
	}

	public static function schema_contenido(): array {
		return array(
			'imagen'       => array( 'tipo' => 'imagen', 'etiqueta' => __( 'Imagen', 'sofia-studio' ) ),
			'titulo'       => array( 'tipo' => 'texto', 'etiqueta' => __( 'Título', 'sofia-studio' ) ),
			'texto'        => array( 'tipo' => 'texto_largo', 'etiqueta' => __( 'Texto', 'sofia-studio' ) ),
			'boton_texto'  => array( 'tipo' => 'texto', 'etiqueta' => __( 'Texto del botón', 'sofia-studio' ) ),
			'boton_enlace' => array( 'tipo' => 'url', 'etiqueta' => __( 'Enlace del botón', 'sofia-studio' ) ),
		);
	}

	/**
	 * claves_estilo_relevantes(): unión de PERFIL_SECCION (caja real, con
	 * fondo/borde/sombra/radius propios — a diferencia de Hero, una Card
	 * SIEMPRE es una caja visual, nunca "libre") + aspect_ratio/object_fit
	 * (hay una <img> real adentro), mismo criterio que
	 * Sofia_Componente_Hero::claves_estilo_relevantes().
	 */
	public static function claves_estilo_relevantes(): array {
		return array_unique( array_merge( parent::PERFIL_SECCION, array( 'aspect_ratio', 'object_fit' ) ) );
	}

	public function render(): string {
		$titulo       = $this->texto_enriquecido( $this->props['titulo'] );
		$texto        = $this->texto_enriquecido( $this->props['texto'] );
		$boton_texto  = $this->texto_enriquecido( $this->props['boton_texto'] );
		$boton_enlace = esc_url( $this->props['boton_enlace'] );
		// imagen_o_placeholder() (clase base): mismo motivo que Hero/Image —
		// sin esto, una Card recién agregada sin imagen no tendría forma de
		// cargar una la primera vez.
		$imagen = $this->imagen_o_placeholder( esc_url( $this->props['imagen'] ) );

		$html = '<section ' . $this->atributos_seccion( 'sofia-card' ) . '>';
		if ( $imagen ) {
			$html .= '<img class="sofia-card__imagen" ' . $this->atributo_editable( 'imagen' ) . ' src="' . $imagen . '" alt="' . wp_strip_all_tags( $titulo ) . '">';
		}
		$html .= '<div class="sofia-card__cuerpo">';
		$html .= '<h3 class="sofia-card__titulo" ' . $this->atributo_editable( 'titulo' ) . ' ' . $this->atributo_estilo( 'titulo' ) . '>' . $titulo . '</h3>';
		$html .= '<p class="sofia-card__texto" ' . $this->atributo_editable( 'texto' ) . ' ' . $this->atributo_estilo( 'texto' ) . '>' . $texto . '</p>';
		$html .= '<a class="sofia-card__boton" href="' . $boton_enlace . '" ' . $this->atributo_editable( 'boton_texto' ) . ' ' . $this->atributo_estilo( 'boton_texto' ) . '>' . $boton_texto . '</a>';
		$html .= '</div>';
		$html .= '</section>';
		return $html;
	}
}

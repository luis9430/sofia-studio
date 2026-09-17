<?php
/**
 * List — primitiva de Datos/UI (Fase 4, plan "50 primitivas", ver la
 * memoria de producto): lista simple de items de texto, cada uno con un
 * ícono opcional (check por defecto) — mismo mecanismo de lista repetible
 * que Sofia_Componente_Franja_Beneficios (data-sofia-lista/data-sofia-item/
 * boton_agregar_item()), pero cada item es UN campo de texto (no título+
 * texto) — el caso más simple de lista, pensado para viñetas de
 * características/beneficios en una sola línea cada una.
 */
class Sofia_Componente_List extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Lista', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		return array(
			'items' => array(
				array( 'texto' => __( 'Primer elemento', 'sofia-studio' ) ),
				array( 'texto' => __( 'Segundo elemento', 'sofia-studio' ) ),
				array( 'texto' => __( 'Tercer elemento', 'sofia-studio' ) ),
			),
		);
	}

	public static function schema_contenido(): array {
		return array(
			'items' => array(
				'tipo'     => 'lista',
				'etiqueta' => __( 'Elementos', 'sofia-studio' ),
				'campos'   => array(
					'texto' => array( 'tipo' => 'texto', 'etiqueta' => __( 'Texto', 'sofia-studio' ) ),
				),
			),
		);
	}

	/**
	 * claves_estilo_relevantes(): PERFIL_SECCION, no PERFIL_LISTA — tener
	 * una lista de items NO alcanza para justificar ese perfil. Las 3
	 * claves extra de PERFIL_LISTA (columnas/alineacion_contenido/
	 * alineacion_vertical_contenido) describen una GRILLA de columnas, y su
	 * CSS existe solo para Franja de beneficios y Testimonios (selectores
	 * hardcodeados en style.css). Una Lista se apila en vertical, así que
	 * esos 3 controles aparecían en el drawer sin hacer absolutamente nada
	 * — exactamente el ruido que los perfiles existen para evitar.
	 */
	public static function claves_estilo_relevantes(): array {
		return parent::PERFIL_SECCION;
	}

	public function render(): string {
		$items = is_array( $this->props['items'] ?? null ) ? $this->props['items'] : array();

		$html  = '<section ' . $this->atributos_seccion( 'sofia-list' ) . '>';
		$html .= '<ul class="sofia-list__lista" ' . $this->atributo_lista( 'items' ) . '>';
		foreach ( array_values( $items ) as $indice => $item ) {
			$texto = $this->texto_enriquecido( (string) ( $item['texto'] ?? '' ) );
			$html .= '<li class="sofia-list__item" ' . $this->atributo_item( $indice ) . '>';
			$html .= $this->svg_icono( 'check', 18, 'sofia-list__icono' );
			$html .= '<span ' . $this->atributo_editable( "items.{$indice}.texto" ) . ' ' . $this->atributo_estilo( "items.{$indice}.texto" ) . '>' . $texto . '</span>';
			$html .= '</li>';
		}
		$html .= '</ul>';
		$html .= $this->boton_agregar_item( 'items' );
		$html .= '</section>';
		return $html;
	}
}

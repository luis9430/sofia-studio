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
	 * claves_estilo_relevantes(): PERFIL_LISTA.
	 *
	 * Esta clave cambió DOS veces, y el recorrido es la parte que importa.
	 * Originalmente era PERFIL_LISTA sin que nada lo respaldara: el CSS de
	 * grilla estaba hardcodeado solo para Franja de beneficios y
	 * Testimonios, así que los 3 controles aparecían en el panel sin hacer
	 * absolutamente nada. La Fase A lo bajó a PERFIL_SECCION, que era lo
	 * correcto MIENTRAS el CSS no existiera: mejor no ofrecer un control
	 * que ofrecer uno muerto.
	 *
	 * La Fase F escribió ese CSS (.sofia-list__lista pasó de flex a grid,
	 * leyendo --sofia-columnas como ya hacían Franja y Testimonios), así
	 * que la razón para no tener el perfil desapareció. Una lista larga en
	 * dos o tres columnas es un pedido real, y ahora el bloque sabe
	 * dibujarla.
	 *
	 * La regla que queda: declarar PERFIL_LISTA es una PROMESA de que el
	 * bloque respeta sus tres controles. Nav y Breadcrumb siguen en
	 * PERFIL_SECCION y no por falta de CSS — son filas horizontales, donde
	 * "columnas" no significa nada.
	 */
	public static function claves_estilo_relevantes(): array {
		return parent::PERFIL_LISTA;
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

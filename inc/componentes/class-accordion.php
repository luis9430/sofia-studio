<?php
/**
 * Accordion — paneles plegables.
 *
 * Usa <details>/<summary> nativo, así que abre y cierra sin una línea de
 * JavaScript: el navegador ya lo resuelve, con su accesibilidad y su
 * comportamiento de teclado incluidos. El archivo de interacciones solo
 * agrega el modo "uno a la vez", que es lo único que el HTML no trae.
 *
 * Por eso dependencias_js() solo se declara cuando hace falta — ver
 * abajo: un Accordion normal no carga ningún script.
 */
class Sofia_Componente_Accordion extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Acordeón', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		return array(
			'items' => array(
				array(
					'titulo' => __( 'Primer panel', 'sofia-studio' ),
					'texto'  => __( 'Contenido del primer panel.', 'sofia-studio' ),
				),
				array(
					'titulo' => __( 'Segundo panel', 'sofia-studio' ),
					'texto'  => __( 'Contenido del segundo panel.', 'sofia-studio' ),
				),
			),
		);
	}

	/** Capa 1 — CONTENIDO. */
	public static function schema_contenido(): array {
		return array(
			'items' => array(
				'tipo'     => 'lista',
				'etiqueta' => __( 'Paneles', 'sofia-studio' ),
				'campos'   => array(
					'titulo' => array( 'tipo' => 'texto', 'etiqueta' => __( 'Título', 'sofia-studio' ) ),
					'texto'  => array( 'tipo' => 'texto_largo', 'etiqueta' => __( 'Contenido', 'sofia-studio' ) ),
				),
			),
		);
	}

	/** Capa 2 — APARIENCIA. */
	public static function schema_propio(): array {
		return array(
			'apertura' => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Apertura', 'sofia-studio' ),
				'ayuda'    => __( '"Uno a la vez" cierra los demás al abrir un panel.', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => 'libre', 'etiqueta' => __( 'Varios a la vez', 'sofia-studio' ) ),
					array( 'valor' => 'exclusivo', 'etiqueta' => __( 'Uno a la vez', 'sofia-studio' ) ),
				),
			),
		);
	}

	/** Capa 3 — CAJA. */
	public static function claves_estilo_relevantes(): array {
		return parent::PERFIL_SECCION;
	}

	/**
	 * El JS solo hace falta para "uno a la vez": abrir y cerrar ya lo
	 * resuelve <details>. Un Accordion normal no carga ningún script, que
	 * es el criterio de "cargar solo lo que la página realmente usa" que
	 * este mecanismo existe para cumplir.
	 */
	public function dependencias_js(): array {
		return 'exclusivo' === ( $this->estilo_bloque()['apertura'] ?? '' )
			? array( 'interacciones' )
			: array();
	}

	/**
	 * render(): dentro del editor todos los paneles salen ABIERTOS. Un
	 * panel plegado es contenido que no se puede clickear para editar, y
	 * el script que los cierra no corre ahí — así que el estado abierto
	 * tiene que venir del HTML.
	 */
	public function render(): string {
		$items     = is_array( $this->props['items'] ?? null ) ? $this->props['items'] : array();
		$en_editor = class_exists( 'Sofia_Modo_Editor' ) && Sofia_Modo_Editor::activo();
		$exclusivo = 'exclusivo' === ( $this->estilo_bloque()['apertura'] ?? '' );

		$html  = '<section ' . $this->atributos_seccion( 'sofia-accordion' ) . ( $exclusivo ? ' data-sofia-accordion="exclusivo"' : '' ) . '>';
		$html .= '<div class="sofia-accordion__lista" ' . $this->atributo_lista( 'items' ) . '>';
		foreach ( array_values( $items ) as $indice => $item ) {
			$titulo = $this->texto_enriquecido( (string) ( $item['titulo'] ?? '' ) );
			$texto  = $this->texto_enriquecido( (string) ( $item['texto'] ?? '' ) );

			// Abierto en el editor; en el sitio, solo el primero.
			$abierto = ( $en_editor || 0 === $indice ) ? ' open' : '';

			$html .= '<details class="sofia-accordion__item" ' . $this->atributo_item( $indice ) . $abierto . '>';
			$html .= '<summary class="sofia-accordion__titulo" ' . $this->atributo_editable( "items.{$indice}.titulo" ) . '>' . $titulo . '</summary>';
			$html .= '<div class="sofia-accordion__texto" ' . $this->atributo_editable( "items.{$indice}.texto" ) . ' ' . $this->atributo_estilo( "items.{$indice}.texto" ) . '>' . $texto . '</div>';
			$html .= '</details>';
		}
		$html .= '</div>';
		$html .= $this->boton_agregar_item( 'items' );
		$html .= '</section>';
		return $html;
	}
}

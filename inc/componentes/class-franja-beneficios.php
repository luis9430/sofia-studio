<?php
/**
 * Bloque Franja de beneficios — lista de N columnas (título + texto cada
 * una), reordenable/agregable/eliminable individualmente (Nivel 2, sub-
 * items — ver la memoria de producto "tema WP con editor de contenido").
 * Antes de Nivel 2 esto eran 3 columnas hardcodeadas
 * ("franja_beneficios.titulo_1".."_3") — ahora es un único campo de tipo
 * lista, "franja_beneficios.items" (array de {titulo, texto}), mismo
 * criterio que PaginaSitio.Contenido = map[string]any del lado GoPress:
 * la MAYORÍA de campos son texto plano, este es la excepción que necesita
 * un array como valor.
 */
class Sofia_Componente_Franja_Beneficios extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Franja de beneficios', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		$items = array();
		for ( $i = 1; $i <= 3; $i++ ) {
			$items[] = array(
				'titulo' => sprintf( __( 'Beneficio %d', 'sofia-studio' ), $i ),
				'texto'  => __( 'Describe este beneficio en una línea.', 'sofia-studio' ),
			);
		}
		return array( 'items' => $items );
	}

	/**
	 * schema_contenido() (Fase 4 — generador de árboles por IA): "items" es
	 * tipo 'lista' — cada elemento tiene 'campos' con el mismo shape
	 * recursivo {tipo, etiqueta} que cualquier campo suelto (ver el
	 * comentario largo en Sofia_Componente::schema_contenido()), un solo
	 * nivel de anidamiento (ningún item acá tiene a su vez una lista
	 * propia). El LLM usa esto para saber que "items" es un ARRAY de
	 * {titulo, texto}, no un campo de texto suelto.
	 */
	public static function schema_contenido(): array {
		return array(
			'items' => array(
				'tipo'     => 'lista',
				'etiqueta' => __( 'Beneficios', 'sofia-studio' ),
				'campos'   => array(
					'titulo' => array( 'tipo' => 'texto', 'etiqueta' => __( 'Título', 'sofia-studio' ) ),
					'texto'  => array( 'tipo' => 'texto', 'etiqueta' => __( 'Texto', 'sofia-studio' ) ),
				),
			),
		);
	}

	/**
	 * claves_estilo_relevantes() (Nivel 2, revisión de arquitectura tras
	 * fricción real de edición — ver la memoria de producto): PERFIL_LISTA
	 * — tiene una lista de items propia, así que Columnas/Alineación del
	 * contenido SÍ tienen efecto real acá (a diferencia de un Hero/CTA sin
	 * grid interno).
	 */
	public static function claves_estilo_relevantes(): array {
		return parent::PERFIL_LISTA;
	}

	public function render(): string {
		$items = is_array( $this->props['items'] ?? null ) ? $this->props['items'] : array();

		// data-sofia-lista marca el contenedor de los items de este bloque:
		// editor-iframe.js lo busca por ese atributo para mover, agregar o
		// eliminar un item (alMoverItemDeLista/alAgregarItemALista/
		// alEliminarItemDeLista), disparado desde el árbol de Estructura.
		// data-sofia-item="N" en cada columna es el índice que ese script
		// lee para reconstruir el array resultante.
		// data-sofia-lista usa "{id}.items" (no "franja_beneficios.items")
		// — bug real que esto resuelve: con el tipo crudo, DOS Franjas de
		// beneficios en la misma página compartían el mismo selector
		// data-sofia-lista, y editor-iframe.js no podía distinguir en cuál
		// de las dos instancias operar (ver la memoria de producto).

		// El botón "+ Agregar" vive FUERA de [data-sofia-lista] (hermano,
		// no hijo) a propósito: dentro del contenedor sería un hijo más de
		// la grilla, así que ocuparía una celda de columna como si fuera
		// otro item. Como hermano queda a lo ancho, debajo de la grilla.
		// Solo se renderiza en modo editor (ver boton_agregar_item()).
		$html  = '<section ' . $this->atributos_seccion( 'sofia-franja-beneficios' ) . '>';
		$html .= '<div class="sofia-franja-beneficios__grid" ' . $this->atributo_lista( 'items' ) . '>';
		foreach ( array_values( $items ) as $indice => $item ) {
			$titulo = $this->texto_enriquecido( (string) ( $item['titulo'] ?? '' ) );
			$texto  = $this->texto_enriquecido( (string) ( $item['texto'] ?? '' ) );
			$html  .= '<div class="sofia-franja-beneficios__item" ' . $this->atributo_item( $indice ) . '>';
			$html  .= '<h3 ' . $this->atributo_editable( "items.{$indice}.titulo" ) . ' ' . $this->atributo_estilo( "items.{$indice}.titulo" ) . '>' . $titulo . '</h3>';
			$html  .= '<p ' . $this->atributo_editable( "items.{$indice}.texto" ) . ' ' . $this->atributo_estilo( "items.{$indice}.texto" ) . '>' . $texto . '</p>';
			$html  .= '</div>';
		}
		$html .= '</div>';
		$html .= $this->boton_agregar_item( 'items' );
		$html .= '</section>';
		return $html;
	}
}

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

	public function render(): string {
		$items = is_array( $this->props['items'] ?? null ) ? $this->props['items'] : array();

		// data-sofia-lista marca el contenedor donde editor-iframe.js monta
		// una instancia SortableJS PROPIA para reordenar items DENTRO de
		// este bloque (distinta de la instancia de nivel superior que
		// reordena bloques completos) — ver activarReordenarItems() en
		// editor-iframe.js. data-sofia-item="N" en cada columna es el
		// índice que ese script lee para reconstruir el array tras un
		// drag o una eliminación.
		// data-sofia-lista usa "{id}.items" (no "franja_beneficios.items")
		// — bug real que esto resuelve: con el tipo crudo, DOS Franjas de
		// beneficios en la misma página compartían el mismo selector
		// data-sofia-lista, y editor-iframe.js no podía distinguir en cuál
		// de las dos instancias operar (ver la memoria de producto).
		$campo_lista = $this->id . '.items';

		// El botón "+ Agregar" vive FUERA de [data-sofia-lista] (hermano,
		// no hijo) — bug real encontrado en la práctica: Muuri fija el
		// height del contenedor grid basándose ÚNICAMENTE en sus items
		// position:absolute, ignorando cualquier otro hijo en flujo normal
		// (confirmado contra la documentación oficial: "usar un wrapper
		// separado para contenido que no sea items"). Con el botón DENTRO
		// del grid, la <section> nunca reservaba espacio visual para él —
		// quedaba superpuesto sobre el bloque siguiente. Envuelto en modo
		// editor (Sofia_Modo_Editor::activo()), el botón en flujo normal
		// SÍ empuja la altura real de la <section>, que el
		// ResizeObserver de nivel superior detecta automáticamente.
		$html  = '<section class="sofia-franja-beneficios" ' . $this->atributos_seccion() . '>';
		$html .= '<div class="sofia-franja-beneficios__grid" data-sofia-lista="' . esc_attr( $campo_lista ) . '">';
		foreach ( array_values( $items ) as $indice => $item ) {
			$titulo = $this->texto_enriquecido( (string) ( $item['titulo'] ?? '' ) );
			$texto  = $this->texto_enriquecido( (string) ( $item['texto'] ?? '' ) );
			$html  .= '<div class="sofia-franja-beneficios__item" data-sofia-item="' . (int) $indice . '">';
			$html  .= '<h3 ' . $this->atributo_editable( "items.{$indice}.titulo" ) . '>' . $titulo . '</h3>';
			$html  .= '<p ' . $this->atributo_editable( "items.{$indice}.texto" ) . '>' . $texto . '</p>';
			$html  .= '</div>';
		}
		$html .= '</div>';
		$html .= $this->boton_agregar_item( 'items' );
		$html .= '</section>';
		return $html;
	}
}

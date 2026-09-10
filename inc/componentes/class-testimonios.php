<?php
/**
 * Bloque Testimonios — lista de N testimonios (foto + nombre + cita cada
 * uno), mismo patrón de "lista repetible" que Franja de beneficios (Nivel
 * 2, sub-items — ver la memoria de producto "tema WP con editor de
 * contenido"): un único campo de tipo lista, "testimonios.items" (array
 * de {foto, nombre, cita}).
 *
 * Primer Componente del catálogo con un campo de IMAGEN dentro de cada
 * item — confirma que atributo_editable()/boton_agregar_item() ya
 * heredados de Sofia_Componente funcionan genérico para cualquier tipo de
 * campo (texto o imagen), sin necesitar ningún cambio en editor-iframe.js:
 * activarImagen()/activarTexto() ya deciden por el tagName del elemento
 * editable, no por el tipo de Componente.
 */
class Sofia_Componente_Testimonios extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Testimonios', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		$items = array();
		for ( $i = 1; $i <= 3; $i++ ) {
			$items[] = array(
				'foto'   => '',
				'nombre' => sprintf( __( 'Cliente %d', 'sofia-studio' ), $i ),
				'cita'   => __( 'Escribe acá lo que dijo este cliente sobre tu producto o servicio.', 'sofia-studio' ),
			);
		}
		return array( 'items' => $items );
	}

	public function render(): string {
		$items = is_array( $this->props['items'] ?? null ) ? $this->props['items'] : array();

		// Mismo criterio que Franja de beneficios: data-sofia-lista usa
		// "{id}.items" (el ID de INSTANCIA, no el tipo crudo) para que dos
		// bloques Testimonios en la misma página nunca compartan selector
		// ni contenido.
		$campo_lista = $this->id . '.items';

		$html  = '<section class="sofia-testimonios" ' . $this->atributos_seccion() . '>';
		$html .= '<div class="sofia-testimonios__grid" data-sofia-lista="' . esc_attr( $campo_lista ) . '">';
		foreach ( array_values( $items ) as $indice => $item ) {
			$foto   = esc_url( (string) ( $item['foto'] ?? '' ) );
			$nombre = $this->texto_enriquecido( (string) ( $item['nombre'] ?? '' ) );
			$cita   = $this->texto_enriquecido( (string) ( $item['cita'] ?? '' ) );

			$html .= '<div class="sofia-testimonios__item" data-sofia-item="' . (int) $indice . '">';
			if ( $foto ) {
				$html .= '<img class="sofia-testimonios__foto" ' . $this->atributo_editable( "items.{$indice}.foto" ) . ' src="' . $foto . '" alt="' . wp_strip_all_tags( $nombre ) . '">';
			} else {
				// Placeholder clicable: sin foto todavía, pero igual debe
				// poder abrir wp.media() — mismo criterio que
				// Sofia_Componente_Hero cuando no hay imagen, salvo que
				// acá SIEMPRE se imprime el <img> (vacío) para que el
				// campo exista y sea editable desde el primer render,
				// incluso en un item recién clonado por
				// alAgregarItemALista() en editor-iframe.js.
				$html .= '<img class="sofia-testimonios__foto sofia-testimonios__foto--vacia" ' . $this->atributo_editable( "items.{$indice}.foto" ) . ' src="" alt="">';
			}
			$html .= '<p class="sofia-testimonios__cita" ' . $this->atributo_editable( "items.{$indice}.cita" ) . ' ' . $this->atributo_estilo( "items.{$indice}.cita" ) . '>' . $cita . '</p>';
			$html .= '<h3 class="sofia-testimonios__nombre" ' . $this->atributo_editable( "items.{$indice}.nombre" ) . ' ' . $this->atributo_estilo( "items.{$indice}.nombre" ) . '>' . $nombre . '</h3>';
			$html .= '</div>';
		}
		$html .= '</div>';
		$html .= $this->boton_agregar_item( 'items' );
		$html .= '</section>';
		return $html;
	}
}

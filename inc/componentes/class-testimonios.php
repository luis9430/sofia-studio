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

	/**
	 * schema_contenido() (Fase 4 — generador de árboles por IA): mismo
	 * criterio que Franja de beneficios — "items" es tipo 'lista' de
	 * {foto, nombre, cita}, "foto" declarado como 'imagen' (no 'texto').
	 */
	public static function schema_contenido(): array {
		return array(
			'items' => array(
				'tipo'     => 'lista',
				'etiqueta' => __( 'Testimonios', 'sofia-studio' ),
				'campos'   => array(
					'foto'   => array( 'tipo' => 'imagen', 'etiqueta' => __( 'Foto', 'sofia-studio' ) ),
					'nombre' => array( 'tipo' => 'texto', 'etiqueta' => __( 'Nombre', 'sofia-studio' ) ),
					'cita'   => array( 'tipo' => 'texto_largo', 'etiqueta' => __( 'Cita', 'sofia-studio' ) ),
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

		// Mismo criterio que Franja de beneficios: data-sofia-lista usa
		// "{id}.items" (el ID de INSTANCIA, no el tipo crudo) para que dos
		// bloques Testimonios en la misma página nunca compartan selector
		// ni contenido.

		$html  = '<section ' . $this->atributos_seccion( 'sofia-testimonios' ) . '>';
		$html .= '<div class="sofia-testimonios__grid" ' . $this->atributo_lista( 'items' ) . '>';
		foreach ( array_values( $items ) as $indice => $item ) {
			$foto   = esc_url( (string) ( $item['foto'] ?? '' ) );
			$nombre = $this->texto_enriquecido( (string) ( $item['nombre'] ?? '' ) );
			$cita   = $this->texto_enriquecido( (string) ( $item['cita'] ?? '' ) );

			$html .= '<div class="sofia-testimonios__item" ' . $this->atributo_item( $indice ) . '>';
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

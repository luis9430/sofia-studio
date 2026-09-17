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

	/**
	 * Capa 2 — APARIENCIA. Sin fotos, el bloque queda como una fila de
	 * citas — útil cuando no hay fotos reales de los clientes y los
	 * placeholders grises quedan peor que nada.
	 */
	public static function schema_propio(): array {
		return array(
			'fotos' => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Fotos', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'Mostrar', 'sofia-studio' ) ),
					array( 'valor' => 'ocultas', 'etiqueta' => __( 'Ocultar', 'sofia-studio' ) ),
				),
			),
		);
	}

	private const CLASES_FOTOS = array(
		'ocultas' => 'sofia-testimonios--sin-fotos',
	);

	public function render(): string {
		$items = is_array( $this->props['items'] ?? null ) ? $this->props['items'] : array();

		// Mismo criterio que Franja de beneficios: data-sofia-lista usa
		// "{id}.items" (el ID de INSTANCIA, no el tipo crudo) para que dos
		// bloques Testimonios en la misma página nunca compartan selector
		// ni contenido.

		$clases = array_filter( array(
			'sofia-testimonios',
			$this->clase_de_variante( self::CLASES_FOTOS, 'fotos' ),
		) );

		$html  = '<section ' . $this->atributos_seccion( implode( ' ', $clases ) ) . '>';
		$html .= '<div class="sofia-testimonios__grid" ' . $this->atributo_lista( 'items' ) . '>';
		foreach ( array_values( $items ) as $indice => $item ) {
			$foto   = esc_url( (string) ( $item['foto'] ?? '' ) );
			$nombre = $this->texto_enriquecido( (string) ( $item['nombre'] ?? '' ) );
			$cita   = $this->texto_enriquecido( (string) ( $item['cita'] ?? '' ) );

			$html .= '<div class="sofia-testimonios__item" ' . $this->atributo_item( $indice ) . '>';
			// Sin foto, el <img> igual tiene que existir EN EL EDITOR para
			// poder clickearlo y abrir wp.media() — también en un item
			// recién clonado por alAgregarItemALista(). Pero su src sale
			// de imagen_o_placeholder(), no de un src="" vacío: un <img>
			// con src vacío es HTML inválido y el navegador lo dibuja como
			// imagen rota (bug real, visible en el canvas como un ícono de
			// imagen partida en cada testimonio sin foto).
			//
			// En una visita pública sin foto no se emite ningún <img>:
			// imagen_o_placeholder() devuelve "" fuera del editor, y un
			// hueco vacío se ve mejor que un ícono de error.
			$src = $this->imagen_o_placeholder( $foto );
			if ( $src ) {
				$clase = $foto ? 'sofia-testimonios__foto' : 'sofia-testimonios__foto sofia-testimonios__foto--vacia';
				$html .= '<img class="' . $clase . '" ' . $this->atributo_editable( "items.{$indice}.foto" ) . ' src="' . $src . '" alt="' . esc_attr( wp_strip_all_tags( $nombre ) ) . '">';
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

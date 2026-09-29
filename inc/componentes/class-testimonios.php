<?php
/**
 * Testimonios — la prueba social.
 *
 * REDISEÑADO como PIEZA. La versión anterior tenía tres campos por item
 * (foto, nombre, cita) y nada más: tres citas sueltas sin contexto, sin
 * el cargo de quien habla y sin encabezado que dijera de qué eran.
 *
 * Un testimonio sin cargo ni empresa vale mucho menos: "Marina G." no
 * dice nada, "Marina G., organizó su luna de miel acá" sí. Ese dato es
 * el que convierte una frase amable en prueba social de verdad.
 *
 * Tres composiciones que resuelven casos distintos, no variaciones
 * estéticas de lo mismo — ver las etiquetas del select, que dicen cuándo
 * usar cada una.
 */
class Sofia_Componente_Testimonios extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Testimonios', 'sofia-studio' );
	}

	/**
	 * Defaults de un caso REAL. Un placeholder genérico enseña a dejarlo
	 * genérico, y hace imposible juzgar la composición hasta que alguien
	 * se tome el trabajo de llenarla.
	 */
	protected function props_por_defecto(): array {
		return array(
			'etiqueta' => __( 'Lo que dicen', 'sofia-studio' ),
			'titulo'   => __( 'Gente que volvió al año siguiente', 'sofia-studio' ),
			'bajada'   => '',
			'items'    => array(
				array(
					'foto'   => '',
					'cita'   => __( 'Reservamos tres noches y nos quedamos ocho. La playa estaba vacía un jueves de marzo.', 'sofia-studio' ),
					'nombre' => __( 'Marina G.', 'sofia-studio' ),
					'cargo'  => __( 'Viajó en marzo de 2025', 'sofia-studio' ),
				),
				array(
					'foto'   => '',
					'cita'   => __( 'Nos armaron la ruta completa por WhatsApp en dos días. Cero paquetes cerrados, todo a medida.', 'sofia-studio' ),
					'nombre' => __( 'Diego R.', 'sofia-studio' ),
					'cargo'  => __( 'Luna de miel, 2025', 'sofia-studio' ),
				),
				array(
					'foto'   => '',
					'cita'   => __( 'Fuimos con dos nenes chicos. El hotel tenía cuna y el dueño nos consiguió una silla para el auto.', 'sofia-studio' ),
					'nombre' => __( 'Familia Peralta', 'sofia-studio' ),
					'cargo'  => __( 'Vacaciones de invierno', 'sofia-studio' ),
				),
			),
		);
	}

	/** Capa 1 — CONTENIDO. */
	public static function schema_contenido(): array {
		return array(
			'etiqueta' => array( 'tipo' => 'texto', 'etiqueta' => __( 'Etiqueta de sección', 'sofia-studio' ) ),
			'titulo'   => array( 'tipo' => 'texto', 'etiqueta' => __( 'Título', 'sofia-studio' ) ),
			'bajada'   => array( 'tipo' => 'texto_largo', 'etiqueta' => __( 'Bajada', 'sofia-studio' ) ),
			'items'    => array(
				'tipo'     => 'lista',
				'etiqueta' => __( 'Testimonios', 'sofia-studio' ),
				'campos'   => array(
					'foto'   => array( 'tipo' => 'imagen', 'etiqueta' => __( 'Foto', 'sofia-studio' ) ),
					'cita'   => array( 'tipo' => 'texto_largo', 'etiqueta' => __( 'Lo que dijo', 'sofia-studio' ) ),
					'nombre' => array( 'tipo' => 'texto', 'etiqueta' => __( 'Nombre', 'sofia-studio' ) ),
					'cargo'  => array( 'tipo' => 'texto', 'etiqueta' => __( 'Quién es / cuándo', 'sofia-studio' ) ),
				),
			),
		);
	}

	/**
	 * Capa 2 — APARIENCIA.
	 *
	 * Las etiquetas dicen CUÁNDO usar cada composición: el catálogo que
	 * recibe el generador por IA las incluye, y son lo único que tiene
	 * para elegir entre tres.
	 */
	public static function schema_propio(): array {
		return array(
			'composicion' => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Composición', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'Tarjetas — para tres o cuatro testimonios parejos', 'sofia-studio' ) ),
					array( 'valor' => 'destacado', 'etiqueta' => __( 'Uno destacado — cuando hay una cita que vale más que las otras', 'sofia-studio' ) ),
					array( 'valor' => 'muro', 'etiqueta' => __( 'Muro — para seis o más, de largos distintos', 'sofia-studio' ) ),
				),
			),
			'fotos'       => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Fotos', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'Mostrar', 'sofia-studio' ) ),
					array( 'valor' => 'ocultas', 'etiqueta' => __( 'Ocultar (cuando no hay fotos buenas)', 'sofia-studio' ) ),
				),
			),
			'tono'        => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Tono', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'Claro', 'sofia-studio' ) ),
					array( 'valor' => 'oscuro', 'etiqueta' => __( 'Oscuro (corta el ritmo de la página)', 'sofia-studio' ) ),
				),
			),
		);
	}

	private const CLASES_COMPOSICION = array(
		'destacado' => 'sofia-testimonios--destacado',
		'muro'      => 'sofia-testimonios--muro',
	);

	private const CLASES_FOTOS = array(
		'ocultas' => 'sofia-testimonios--sin-fotos',
	);

	private const CLASES_TONO = array(
		'oscuro' => 'sofia-testimonios--oscuro',
	);

	/**
	 * Capa 3 — CAJA. PERFIL_SECCION y no PERFIL_LISTA: la grilla la
	 * resuelve la composición, no el control genérico de columnas.
	 * Declarar PERFIL_LISTA dejaría tres controles muertos en el panel.
	 */
	public static function claves_estilo_relevantes(): array {
		return parent::PERFIL_SECCION;
	}

	/**
	 * render(): una sola estructura para las tres composiciones.
	 *
	 * Si cada una emitiera HTML distinto, cambiar de composición movería
	 * los [data-sofia-campo] de lugar y el editor perdería el campo
	 * seleccionado.
	 */
	public function render(): string {
		$items     = is_array( $this->props['items'] ?? null ) ? $this->props['items'] : array();
		$en_editor = class_exists( 'Sofia_Modo_Editor' ) && Sofia_Modo_Editor::activo();

		$clases = array_filter( array(
			'sofia-pieza',
			'sofia-testimonios',
			$this->clase_de_variante( self::CLASES_COMPOSICION, 'composicion' ),
			$this->clase_de_variante( self::CLASES_FOTOS, 'fotos' ),
			$this->clase_de_variante( self::CLASES_TONO, 'tono' ),
		) );

		$html  = '<section ' . $this->atributos_seccion( implode( ' ', $clases ) ) . '>';
		$html .= '<div class="sofia-pieza__interior">';
		$html .= $this->encabezado_html( $en_editor );
		$html .= $this->items_html( $items );
		$html .= '</div>';
		$html .= '</section>';

		return $html;
	}

	/** El encabezado de la sección. */
	private function encabezado_html( bool $en_editor ): string {
		$etiqueta = $this->texto_enriquecido( (string) ( $this->props['etiqueta'] ?? '' ) );
		$titulo   = $this->texto_enriquecido( (string) ( $this->props['titulo'] ?? '' ) );
		$bajada   = $this->texto_enriquecido( (string) ( $this->props['bajada'] ?? '' ) );

		if ( '' === $etiqueta && '' === $titulo && '' === $bajada && ! $en_editor ) {
			return '';
		}

		$html = '<div class="sofia-pieza__encabezado">';
		if ( '' !== $etiqueta || $en_editor ) {
			$html .= '<p class="sofia-pieza__etiqueta" ' . $this->atributo_editable( 'etiqueta' ) . '>' . $etiqueta . '</p>';
		}
		if ( '' !== $titulo || $en_editor ) {
			$html .= '<h2 class="sofia-pieza__titulo" ' . $this->atributo_editable( 'titulo' ) . ' '
				. $this->atributo_estilo( 'titulo' ) . '>' . $titulo . '</h2>';
		}
		if ( '' !== $bajada || $en_editor ) {
			$html .= '<p class="sofia-pieza__bajada" ' . $this->atributo_editable( 'bajada' ) . ' '
				. $this->atributo_estilo( 'bajada' ) . '>' . $bajada . '</p>';
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * Los testimonios.
	 *
	 * <blockquote> y <cite> de verdad, no divs: un lector de pantalla
	 * anuncia "cita" y sabe de quién es. Es información que existe en el
	 * contenido y que el markup puede transmitir gratis.
	 *
	 * La foto usa imagen_o_placeholder(): sin eso, un testimonio sin foto
	 * todavía no tendría forma de agregarle una desde el canvas —
	 * <img src=""> es HTML inválido y el navegador dibuja un ícono de
	 * imagen rota (bug real que ya apareció en este componente).
	 */
	private function items_html( array $items ): string {
		$html = '<div class="sofia-testimonios__items" ' . $this->atributo_lista( 'items' ) . '>';

		foreach ( array_values( $items ) as $indice => $item ) {
			$foto   = $this->imagen_o_placeholder( esc_url( (string) ( $item['foto'] ?? '' ) ) );
			$cita   = $this->texto_enriquecido( (string) ( $item['cita'] ?? '' ) );
			$nombre = $this->texto_enriquecido( (string) ( $item['nombre'] ?? '' ) );
			$cargo  = $this->texto_enriquecido( (string) ( $item['cargo'] ?? '' ) );

			$html .= '<figure class="sofia-testimonios__item" ' . $this->atributo_item( $indice ) . '>';

			$html .= '<blockquote class="sofia-testimonios__cita" ' . $this->atributo_editable( "items.{$indice}.cita" ) . ' '
				. $this->atributo_estilo( "items.{$indice}.cita" ) . '>' . $cita . '</blockquote>';

			$html .= '<figcaption class="sofia-testimonios__autor">';
			if ( $foto ) {
				$html .= '<img class="sofia-testimonios__foto" ' . $this->atributo_editable( "items.{$indice}.foto" )
					. ' src="' . $foto . '" alt="" loading="lazy">';
			}
			$html .= '<div class="sofia-testimonios__datos">';
			$html .= '<cite class="sofia-testimonios__nombre" ' . $this->atributo_editable( "items.{$indice}.nombre" ) . '>' . $nombre . '</cite>';
			$html .= '<span class="sofia-testimonios__cargo" ' . $this->atributo_editable( "items.{$indice}.cargo" ) . '>' . $cargo . '</span>';
			$html .= '</div>';
			$html .= '</figcaption>';

			$html .= '</figure>';
		}

		$html .= '</div>';
		$html .= $this->boton_agregar_item( 'items' );
		return $html;
	}
}

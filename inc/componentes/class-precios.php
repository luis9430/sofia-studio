<?php
/**
 * Precios — los planes.
 *
 * Pieza NUEVA. Es la sección más pedida comercialmente y la que más
 * estructura tiene: cada plan lleva nombre, precio, período, una
 * descripción, su lista de features y su botón.
 *
 * LAS FEATURES SON TEXTO MULTILÍNEA, una por renglón, y no una lista
 * anidada. Dos razones:
 *
 * 1. El editor no soporta listas dentro de listas —
 *    notificarListaActualizada() reconstruye cada item leyendo un solo
 *    nivel de data-sofia-item, así que una anidada se corrompería al
 *    editarla.
 * 2. Aunque las soportara, escribir seis features como seis items
 *    separados es mucho más lento que escribirlas de corrido. Para una
 *    lista de frases cortas sin campos propios, el textarea gana.
 *
 * El Footer resolvió el mismo problema de otra forma (columnas como
 * campos separados) porque ahí cada columna tiene título propio. Acá no
 * hace falta: una feature es solo una línea de texto.
 */
class Sofia_Componente_Precios extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Precios', 'sofia-studio' );
	}

	/** Ver Sofia_Componente::proposito(). */
	public function proposito(): string {
		return __( 'Los planes y qué incluye cada uno. Para que el visitante compare y elija sin tener que preguntar cuánto sale.', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		return array(
			'etiqueta' => __( 'Planes', 'sofia-studio' ),
			'titulo'   => __( 'Elegí cómo querés viajar', 'sofia-studio' ),
			'bajada'   => __( 'Todos incluyen traslado desde el aeropuerto y cancelación hasta 48 horas antes.', 'sofia-studio' ),
			'items'    => array(
				array(
					'nombre'      => __( 'Por tu cuenta', 'sofia-studio' ),
					'precio'      => '$0',
					'periodo'     => __( 'sin cargo', 'sofia-studio' ),
					'descripcion' => __( 'Te damos la información y vos armás el viaje.', 'sofia-studio' ),
					'features'    => __( "Guía de las nueve bahías\nMapa de rutas y distancias\nRecomendaciones por época", 'sofia-studio' ),
					'boton_texto' => __( 'Descargar la guía', 'sofia-studio' ),
					'boton_enlace' => '',
					'destacado'   => '',
					'insignia'    => '',
				),
				array(
					'nombre'      => __( 'Ruta armada', 'sofia-studio' ),
					'precio'      => '$4.900',
					'periodo'     => __( 'por persona', 'sofia-studio' ),
					'descripcion' => __( 'Nosotros resolvemos todo y vos solo llegás.', 'sofia-studio' ),
					'features'    => __( "Hospedaje en hoteles chicos\nTraslados ida y vuelta\nItinerario a medida\nAlguien a quien llamar\nCancelación hasta 48h antes", 'sofia-studio' ),
					'boton_texto' => __( 'Empezar a planear', 'sofia-studio' ),
					'boton_enlace' => '',
					'destacado'   => '1',
					'insignia'    => __( 'El más elegido', 'sofia-studio' ),
				),
				array(
					'nombre'      => __( 'A medida', 'sofia-studio' ),
					'precio'      => __( 'Consultanos', 'sofia-studio' ),
					'periodo'     => __( 'según el grupo', 'sofia-studio' ),
					'descripcion' => __( 'Bodas, grupos grandes o estadías largas.', 'sofia-studio' ),
					'features'    => __( "Todo lo del plan anterior\nCoordinación de eventos\nDescuentos por volumen\nContacto directo con los hoteles", 'sofia-studio' ),
					'boton_texto' => __( 'Hablar con alguien', 'sofia-studio' ),
					'boton_enlace' => '',
					'destacado'   => '',
					'insignia'    => '',
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
				'etiqueta' => __( 'Planes', 'sofia-studio' ),
				'campos'   => array(
					'nombre'       => array( 'tipo' => 'texto', 'etiqueta' => __( 'Nombre del plan', 'sofia-studio' ) ),
					'precio'       => array( 'tipo' => 'texto', 'etiqueta' => __( 'Precio', 'sofia-studio' ) ),
					'periodo'      => array( 'tipo' => 'texto', 'etiqueta' => __( 'Período (por mes, por persona…)', 'sofia-studio' ) ),
					'descripcion'  => array( 'tipo' => 'texto', 'etiqueta' => __( 'Para quién es', 'sofia-studio' ) ),
					'features'     => array( 'tipo' => 'texto_largo', 'etiqueta' => __( 'Qué incluye (una por renglón)', 'sofia-studio' ) ),
					'boton_texto'  => array( 'tipo' => 'texto', 'etiqueta' => __( 'Botón', 'sofia-studio' ) ),
					'boton_enlace' => array( 'tipo' => 'url', 'etiqueta' => __( 'Destino del botón', 'sofia-studio' ) ),
					'insignia'     => array( 'tipo' => 'texto', 'etiqueta' => __( 'Insignia (ej. "El más elegido")', 'sofia-studio' ) ),
					'destacado'    => array( 'tipo' => 'toggle', 'etiqueta' => __( 'Destacar este plan', 'sofia-studio' ) ),
				),
			),
		);
	}

	/** Capa 2 — APARIENCIA. */
	public static function schema_propio(): array {
		return array(
			'composicion' => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Composición', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'Tarjetas — dos o tres planes para comparar', 'sofia-studio' ) ),
					array( 'valor' => 'filas', 'etiqueta' => __( 'Filas — cuando los planes tienen muchas diferencias que leer', 'sofia-studio' ) ),
					array( 'valor' => 'unico', 'etiqueta' => __( 'Plan único — un solo precio, sin comparación', 'sofia-studio' ) ),
				),
			),
			'encabezado'  => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Encabezado', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'Centrado', 'sofia-studio' ) ),
					array( 'valor' => 'izquierda', 'etiqueta' => __( 'A la izquierda', 'sofia-studio' ) ),
				),
			),
			'tono'        => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Tono', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'Claro', 'sofia-studio' ) ),
					array( 'valor' => 'oscuro', 'etiqueta' => __( 'Oscuro', 'sofia-studio' ) ),
				),
			),
		);
	}

	private const CLASES_COMPOSICION = array(
		'filas' => 'sofia-precios--filas',
		'unico' => 'sofia-precios--unico',
	);

	private const CLASES_ENCABEZADO = array(
		'izquierda' => 'sofia-precios--enc-izquierda',
	);

	private const CLASES_TONO = array(
		'oscuro' => 'sofia-precios--oscuro',
	);

	/** Capa 3 — CAJA. */
	public static function claves_estilo_relevantes(): array {
		return parent::PERFIL_SECCION;
	}

	public function render(): string {
		$items     = is_array( $this->props['items'] ?? null ) ? $this->props['items'] : array();
		$en_editor = class_exists( 'Sofia_Modo_Editor' ) && Sofia_Modo_Editor::activo();

		$clases = array_filter( array(
			'sofia-pieza',
			'sofia-precios',
			$this->clase_de_variante( self::CLASES_COMPOSICION, 'composicion' ),
			$this->clase_de_variante( self::CLASES_ENCABEZADO, 'encabezado' ),
			$this->clase_de_variante( self::CLASES_TONO, 'tono' ),
		) );

		$html  = '<section ' . $this->atributos_seccion( implode( ' ', $clases ) ) . '>';
		$html .= '<div class="sofia-pieza__interior">';
		$html .= $this->encabezado_html( $en_editor );
		$html .= $this->planes_html( $items );
		$html .= '</div>';
		$html .= '</section>';

		return $html;
	}

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
	 * Los planes.
	 *
	 * El precio va en <strong> y no en un <span> con CSS: que ese número
	 * sea lo más importante de la tarjeta es un hecho del contenido, no
	 * una decisión visual, y un lector de pantalla lo enfatiza.
	 */
	private function planes_html( array $items ): string {
		$html = '<div class="sofia-precios__planes" ' . $this->atributo_lista( 'items' ) . '>';

		foreach ( array_values( $items ) as $indice => $item ) {
			$destacado = ! empty( $item['destacado'] );
			$insignia  = $this->texto_enriquecido( (string) ( $item['insignia'] ?? '' ) );

			$clases_plan = 'sofia-precios__plan' . ( $destacado ? ' sofia-precios__plan--destacado' : '' );

			$html .= '<div class="' . $clases_plan . '" ' . $this->atributo_item( $indice ) . '>';

			// La insignia va PRIMERO en el HTML aunque visualmente flote
			// arriba de la tarjeta: así un lector de pantalla la anuncia
			// antes del plan, que es cuando aporta.
			if ( '' !== $insignia ) {
				$html .= '<span class="sofia-precios__insignia" ' . $this->atributo_editable( "items.{$indice}.insignia" ) . '>'
					. $insignia . '</span>';
			}

			$html .= '<div class="sofia-precios__cabecera">';
			$html .= '<h3 class="sofia-precios__nombre" ' . $this->atributo_editable( "items.{$indice}.nombre" ) . '>'
				. $this->texto_enriquecido( (string) ( $item['nombre'] ?? '' ) ) . '</h3>';

			$html .= '<p class="sofia-precios__precio">';
			$html .= '<strong class="sofia-precios__monto" ' . $this->atributo_editable( "items.{$indice}.precio" ) . '>'
				. $this->texto_enriquecido( (string) ( $item['precio'] ?? '' ) ) . '</strong>';
			$html .= '<span class="sofia-precios__periodo" ' . $this->atributo_editable( "items.{$indice}.periodo" ) . '>'
				. $this->texto_enriquecido( (string) ( $item['periodo'] ?? '' ) ) . '</span>';
			$html .= '</p>';

			$html .= '<p class="sofia-precios__descripcion" ' . $this->atributo_editable( "items.{$indice}.descripcion" ) . '>'
				. $this->texto_enriquecido( (string) ( $item['descripcion'] ?? '' ) ) . '</p>';
			$html .= '</div>';

			$html .= $this->features_html( (string) ( $item['features'] ?? '' ), $indice );

			$html .= '<a class="sofia-precios__boton" href="' . esc_url( (string) ( $item['boton_enlace'] ?? '' ) ) . '" '
				. $this->atributo_editable( "items.{$indice}.boton_texto" ) . '>'
				. $this->texto_enriquecido( (string) ( $item['boton_texto'] ?? '' ) ) . '</a>';

			$html .= '</div>';
		}

		$html .= '</div>';
		$html .= $this->boton_agregar_item( 'items' );
		return $html;
	}

	/**
	 * Las features: una <ul> real a partir del texto multilínea.
	 *
	 * El campo editable es el texto completo (el textarea del panel), no
	 * cada línea: por eso data-sofia-campo va en la <ul> y no en cada
	 * <li>. Si fuera por línea, el editor intentaría guardar cada una
	 * como un campo propio y no habría dónde ponerlas.
	 *
	 * El check es un ::before del CSS y no un ícono en el HTML: es
	 * decoración que se repite en cada línea, y meterlo al markup
	 * significaría un SVG por feature para nada.
	 */
	private function features_html( string $texto, int $indice ): string {
		$lineas = array_values( array_filter( array_map( 'trim', explode( "\n", $texto ) ) ) );

		$html = '<ul class="sofia-precios__features" ' . $this->atributo_editable( "items.{$indice}.features" ) . '>';
		foreach ( $lineas as $linea ) {
			$html .= '<li class="sofia-precios__feature">' . $this->texto_enriquecido( $linea ) . '</li>';
		}
		$html .= '</ul>';
		return $html;
	}
}

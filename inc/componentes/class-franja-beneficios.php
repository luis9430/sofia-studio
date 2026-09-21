<?php
/**
 * Beneficios — la sección que explica por qué elegir esto.
 *
 * REDISEÑADO como PIEZA, no como contenedor. La versión anterior tenía
 * dos campos por item (título y texto) y nada más: tres bloques de texto
 * sueltos, sin encabezado que dijera de qué eran ni ícono que los
 * distinguiera. Ordenada, pero indistinguible de la de cualquier otro
 * sitio.
 *
 * Ahora la sección tiene contexto propio (etiqueta, título, bajada) y
 * cada item un ícono. Y tres composiciones que NO son variaciones
 * estéticas de lo mismo — cada una sirve para un contenido distinto, y
 * eso está declarado en las etiquetas del select para que tanto el
 * usuario como el generador por IA sepan cuándo usar cuál.
 *
 * Sobre el CSS: se apoya en la base compartida de las piezas (ver el
 * bloque "BASE DE LAS PIEZAS DE DISEÑO" en style.css) — espaciado,
 * encabezado, grilla y foco visible salen de ahí. Acá solo va lo propio
 * de esta sección. Sin esa base, cada pieza nueva inventaría su propia
 * escala y en cinco componentes el CSS deja de tener criterio.
 */
class Sofia_Componente_Franja_Beneficios extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Beneficios', 'sofia-studio' );
	}

	/**
	 * Los defaults describen un caso REAL (una costa de Jalisco), no
	 * "Beneficio 1 / Describe este beneficio en una línea".
	 *
	 * Un placeholder genérico enseña a dejarlo genérico, y hace imposible
	 * juzgar la composición hasta que alguien se tome el trabajo de
	 * llenarla — que es exactamente lo que pasaba antes.
	 */
	protected function props_por_defecto(): array {
		return array(
			'etiqueta' => __( 'Por qué acá', 'sofia-studio' ),
			'titulo'   => __( 'Lo que no vas a encontrar en otra costa', 'sofia-studio' ),
			'bajada'   => __( 'Tres razones por las que la gente vuelve cada año, y ninguna tiene que ver con el precio.', 'sofia-studio' ),
			'items'    => array(
				array(
					'icono'  => 'map-pin',
					'titulo' => __( 'Playas sin fila', 'sofia-studio' ),
					'texto'  => __( 'Nueve bahías repartidas en ciento cincuenta kilómetros. En temporada alta seguís teniendo arena para vos.', 'sofia-studio' ),
				),
				array(
					'icono'  => 'heart',
					'titulo' => __( 'Hoteles de doce cuartos', 'sofia-studio' ),
					'texto'  => __( 'Nada de torres de mil habitaciones. Los dueños te reciben y saben tu nombre al segundo día.', 'sofia-studio' ),
				),
				array(
					'icono'  => 'star',
					'titulo' => __( 'Selva pegada al mar', 'sofia-studio' ),
					'texto'  => __( 'La sierra baja hasta la playa. Podés ver guacamayas a la mañana y bucear a la tarde.', 'sofia-studio' ),
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
				'etiqueta' => __( 'Beneficios', 'sofia-studio' ),
				'campos'   => array(
					'icono'  => array( 'tipo' => 'icono', 'etiqueta' => __( 'Ícono', 'sofia-studio' ) ),
					'titulo' => array( 'tipo' => 'texto', 'etiqueta' => __( 'Título', 'sofia-studio' ) ),
					'texto'  => array( 'tipo' => 'texto_largo', 'etiqueta' => __( 'Texto', 'sofia-studio' ) ),
				),
			),
		);
	}

	/**
	 * Capa 2 — APARIENCIA.
	 *
	 * Las etiquetas dicen CUÁNDO usar cada composición, no solo cómo se
	 * ve. Eso no es adorno: el catálogo que recibe el generador por IA
	 * incluye estas etiquetas, así que es lo único que tiene para decidir
	 * entre las tres. "Numerada" a secas no le dice nada; "cuando los
	 * items son pasos de un proceso" sí.
	 */
	public static function schema_propio(): array {
		return array(
			'composicion' => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Composición', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'Tarjetas con ícono — para cualidades o servicios', 'sofia-studio' ) ),
					array( 'valor' => 'numerada', 'etiqueta' => __( 'Numerada — cuando son pasos de un proceso', 'sofia-studio' ) ),
					array( 'valor' => 'lista', 'etiqueta' => __( 'Lista al costado — para muchos items (5 o más)', 'sofia-studio' ) ),
				),
			),
			'encabezado'  => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Encabezado', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'Título y bajada en dos columnas', 'sofia-studio' ) ),
					array( 'valor' => 'apilado', 'etiqueta' => __( 'Apilado a la izquierda', 'sofia-studio' ) ),
					array( 'valor' => 'centrado', 'etiqueta' => __( 'Centrado', 'sofia-studio' ) ),
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
		'numerada' => 'sofia-beneficios--numerada',
		'lista'    => 'sofia-beneficios--lista',
	);

	private const CLASES_ENCABEZADO = array(
		'apilado'  => 'sofia-beneficios--enc-apilado',
		'centrado' => 'sofia-beneficios--enc-centrado',
	);

	private const CLASES_TONO = array(
		'oscuro' => 'sofia-beneficios--oscuro',
	);

	/** Capa 3 — CAJA. */
	public static function claves_estilo_relevantes(): array {
		return parent::PERFIL_SECCION;
	}

	/**
	 * render(): una sola estructura para las tres composiciones.
	 *
	 * Igual que en Hero, y por el mismo motivo: si cada composición
	 * emitiera HTML distinto, cambiar de una a otra movería los
	 * [data-sofia-campo] de lugar y el editor perdería el campo que el
	 * usuario tenía seleccionado.
	 *
	 * El botón que acompaña a la composición "lista" NO existe: sería un
	 * cuarto par de campos (texto + enlace) que las otras dos no usan, y
	 * un campo que solo sirve en una de tres composiciones confunde más
	 * de lo que aporta. Para eso está el Componente CTA, que va justo
	 * abajo.
	 */
	public function render(): string {
		$items     = is_array( $this->props['items'] ?? null ) ? $this->props['items'] : array();
		$en_editor = class_exists( 'Sofia_Modo_Editor' ) && Sofia_Modo_Editor::activo();

		$clases = array_filter( array(
			'sofia-pieza',
			'sofia-beneficios',
			$this->clase_de_variante( self::CLASES_COMPOSICION, 'composicion' ),
			$this->clase_de_variante( self::CLASES_ENCABEZADO, 'encabezado' ),
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

	/**
	 * El encabezado de la sección: lo que le da contexto a la lista.
	 *
	 * Es justamente lo que faltaba antes — tres bloques de texto sin
	 * nada que dijera de qué eran.
	 */
	private function encabezado_html( bool $en_editor ): string {
		$etiqueta = $this->texto_enriquecido( (string) ( $this->props['etiqueta'] ?? '' ) );
		$titulo   = $this->texto_enriquecido( (string) ( $this->props['titulo'] ?? '' ) );
		$bajada   = $this->texto_enriquecido( (string) ( $this->props['bajada'] ?? '' ) );

		// Una sección sin encabezado sigue siendo válida: hay páginas
		// donde los beneficios van sueltos, después de un hero que ya
		// dio el contexto.
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
	 * Los items.
	 *
	 * El número (01, 02, 03) se emite SIEMPRE y el CSS lo muestra solo en
	 * la composición numerada. Va en el HTML y no en un ::before con
	 * counter porque un número que cuenta pasos es contenido, no
	 * decoración: un lector de pantalla tiene que anunciarlo.
	 *
	 * aria-hidden en el ícono: acompaña al título, no agrega información
	 * propia. Anunciarlo sería ruido.
	 */
	private function items_html( array $items ): string {
		$html = '<div class="sofia-pieza__grilla sofia-beneficios__items" ' . $this->atributo_lista( 'items' ) . '>';

		foreach ( array_values( $items ) as $indice => $item ) {
			$icono  = (string) ( $item['icono'] ?? '' );
			$titulo = $this->texto_enriquecido( (string) ( $item['titulo'] ?? '' ) );
			$texto  = $this->texto_enriquecido( (string) ( $item['texto'] ?? '' ) );

			$html .= '<div class="sofia-beneficios__item" ' . $this->atributo_item( $indice ) . '>';

			$html .= '<span class="sofia-beneficios__numero" aria-hidden="true">'
				. esc_html( str_pad( (string) ( $indice + 1 ), 2, '0', STR_PAD_LEFT ) ) . '</span>';

			$html .= '<span class="sofia-pieza__icono sofia-beneficios__icono" '
				. $this->atributo_editable( "items.{$indice}.icono" ) . ' aria-hidden="true">'
				. $this->svg_icono( $icono, 22, '', 'check' ) . '</span>';

			$html .= '<div class="sofia-beneficios__cuerpo">';
			$html .= '<h3 class="sofia-beneficios__titulo" ' . $this->atributo_editable( "items.{$indice}.titulo" ) . ' '
				. $this->atributo_estilo( "items.{$indice}.titulo" ) . '>' . $titulo . '</h3>';
			$html .= '<p class="sofia-beneficios__texto" ' . $this->atributo_editable( "items.{$indice}.texto" ) . ' '
				. $this->atributo_estilo( "items.{$indice}.texto" ) . '>' . $texto . '</p>';
			$html .= '</div>';

			$html .= '</div>';
		}

		$html .= '</div>';
		$html .= $this->boton_agregar_item( 'items' );
		return $html;
	}
}

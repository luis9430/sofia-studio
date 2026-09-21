<?php
/**
 * Hero — la apertura de la página.
 *
 * REDISEÑADO tras una conversación de producto que vale resumir, porque
 * explica por qué este archivo es distinto del resto del catálogo.
 *
 * La versión anterior tenía dos campos: título e imagen. Era un
 * CONTENEDOR MÍNIMO, no una pieza de diseño — y eso tenía consecuencias
 * que tardamos en atribuir a la causa correcta. Se probó darle efectos
 * CSS por encima (recortes, degradados) y generar componentes nuevos con
 * IA; ninguna de las dos cosas arregló nada, porque el problema no era
 * el tratamiento visual sino que faltaba CONTENIDO: un título sobre un
 * bloque vacío no se salva con un borde diagonal.
 *
 * Ahora trae lo que una apertura real necesita —etiqueta, título, texto,
 * dos acciones, imagen y tres datos— y tres composiciones que resuelven
 * casos distintos. Diseñadas primero en un lienzo visual y aprobadas
 * antes de escribir una línea de PHP.
 *
 * El criterio para el resto del catálogo, de acá en adelante: un
 * Componente es una pieza de diseño terminada, no una caja donde meter
 * cosas. Las piezas de maquetación pura (Container, Spacer, Divider)
 * siguen siendo contenedores porque ese ES su trabajo.
 *
 * Los campos de más son OPCIONALES: cada uno se omite del HTML si está
 * vacío, así un Hero con solo título sigue siendo válido y se ve
 * ordenado. Nadie está obligado a llenar siete campos.
 */
class Sofia_Componente_Hero extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Hero', 'sofia-studio' );
	}

	/**
	 * Los valores por defecto describen un sitio REAL (una costa de
	 * Jalisco), no "Lorem ipsum" ni "Escribe acá tu título".
	 *
	 * Es deliberado: un bloque recién insertado tiene que mostrar cómo se
	 * ve cuando está bien usado. Un placeholder genérico enseña a dejarlo
	 * genérico, y además hace imposible juzgar la composición hasta que
	 * alguien se tome el trabajo de llenarla.
	 */
	protected function props_por_defecto(): array {
		return array(
			'etiqueta'     => __( 'Jalisco, México', 'sofia-studio' ),
			'titulo'       => __( 'Donde la sierra se mete al mar', 'sofia-studio' ),
			'texto'        => __( 'Nueve bahías, ciento cincuenta kilómetros de costa y ni un solo edificio de más de tres pisos.', 'sofia-studio' ),
			'boton_texto'  => __( 'Ver hospedajes', 'sofia-studio' ),
			'boton_enlace' => '',
			'link_texto'   => __( 'Cómo llegar', 'sofia-studio' ),
			'link_enlace'  => '',
			'imagen'       => '',
			'datos'        => array(
				array( 'numero' => '9', 'etiqueta' => __( 'bahías', 'sofia-studio' ) ),
				array( 'numero' => '150', 'etiqueta' => __( 'km de costa', 'sofia-studio' ) ),
				array( 'numero' => '3h', 'etiqueta' => __( 'desde Guadalajara', 'sofia-studio' ) ),
			),
		);
	}

	/** Capa 1 — CONTENIDO. */
	public static function schema_contenido(): array {
		return array(
			'etiqueta'     => array( 'tipo' => 'texto', 'etiqueta' => __( 'Etiqueta (arriba del título)', 'sofia-studio' ) ),
			'titulo'       => array( 'tipo' => 'texto', 'etiqueta' => __( 'Título', 'sofia-studio' ) ),
			'texto'        => array( 'tipo' => 'texto_largo', 'etiqueta' => __( 'Texto', 'sofia-studio' ) ),
			'boton_texto'  => array( 'tipo' => 'texto', 'etiqueta' => __( 'Botón principal', 'sofia-studio' ) ),
			'boton_enlace' => array( 'tipo' => 'url', 'etiqueta' => __( 'Enlace del botón', 'sofia-studio' ) ),
			'link_texto'   => array( 'tipo' => 'texto', 'etiqueta' => __( 'Enlace secundario', 'sofia-studio' ) ),
			'link_enlace'  => array( 'tipo' => 'url', 'etiqueta' => __( 'Destino del enlace', 'sofia-studio' ) ),
			'imagen'       => array( 'tipo' => 'imagen', 'etiqueta' => __( 'Imagen', 'sofia-studio' ) ),
			'datos'        => array(
				'tipo'     => 'lista',
				'etiqueta' => __( 'Datos destacados', 'sofia-studio' ),
				'campos'   => array(
					'numero'   => array( 'tipo' => 'texto', 'etiqueta' => __( 'Número', 'sofia-studio' ) ),
					'etiqueta' => array( 'tipo' => 'texto', 'etiqueta' => __( 'Qué es', 'sofia-studio' ) ),
				),
			),
		);
	}

	/**
	 * Capa 2 — APARIENCIA: las tres composiciones.
	 *
	 * Cada una resuelve un caso distinto, no son variaciones estéticas de
	 * lo mismo:
	 *
	 * - PARTIDO: texto a la izquierda, imagen a la derecha en 54/46. El
	 *   desbalance evita la simetría de dos columnas iguales, que se lee
	 *   como plantilla. Es el que más texto admite.
	 * - INMERSIVO: imagen a sangre, texto abajo sobre un degradado. El
	 *   degradado no es decoración: garantiza que el título se lea sin
	 *   importar qué foto suba el cliente, que es la falla clásica de un
	 *   hero con imagen de fondo.
	 * - EDITORIAL: título y texto arriba, y la lista de datos se vuelve
	 *   una fila de tarjetas cortadas abajo, que invitan a scrollear. El
	 *   que más contenido pide.
	 */
	public static function schema_propio(): array {
		return array(
			'composicion' => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Composición', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'Partido (texto e imagen)', 'sofia-studio' ) ),
					array( 'valor' => 'invertido', 'etiqueta' => __( 'Partido, imagen a la izquierda', 'sofia-studio' ) ),
					array( 'valor' => 'inmersivo', 'etiqueta' => __( 'Inmersivo (texto sobre la imagen)', 'sofia-studio' ) ),
					array( 'valor' => 'editorial', 'etiqueta' => __( 'Editorial (con tarjetas abajo)', 'sofia-studio' ) ),
				),
			),
			'altura'      => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Altura', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'Normal', 'sofia-studio' ) ),
					array( 'valor' => 'alta', 'etiqueta' => __( 'Alta', 'sofia-studio' ) ),
					array( 'valor' => 'pantalla', 'etiqueta' => __( 'Pantalla completa', 'sofia-studio' ) ),
				),
			),
		);
	}

	private const CLASES_COMPOSICION = array(
		'invertido' => 'sofia-hero--invertido',
		'inmersivo' => 'sofia-hero--inmersivo',
		'editorial' => 'sofia-hero--editorial',
	);

	private const CLASES_ALTURA = array(
		'alta'     => 'sofia-hero--alta',
		'pantalla' => 'sofia-hero--pantalla',
	);

	/**
	 * Capa 3 — CAJA. PERFIL_SECCION más los dos controles de imagen: un
	 * Hero es una sección completa PERO tiene una <img> real adentro, así
	 * que ninguno de los dos perfiles alcanza por separado.
	 */
	public static function claves_estilo_relevantes(): array {
		return array_unique( array_merge( parent::PERFIL_SECCION, array( 'aspect_ratio', 'object_fit' ) ) );
	}

	/**
	 * render(): la estructura es la MISMA para las cuatro composiciones —
	 * cambia solo la clase de la sección, y el CSS reacomoda.
	 *
	 * Eso importa para el editor: si cada composición emitiera un HTML
	 * distinto, cambiar de una a otra movería los [data-sofia-campo] de
	 * lugar y el campo seleccionado se perdería. Con una sola estructura,
	 * cambiar de composición es cambiar una clase.
	 */
	public function render(): string {
		$etiqueta = $this->texto_enriquecido( (string) ( $this->props['etiqueta'] ?? '' ) );
		$titulo   = $this->texto_enriquecido( (string) ( $this->props['titulo'] ?? '' ) );
		$texto    = $this->texto_enriquecido( (string) ( $this->props['texto'] ?? '' ) );
		$imagen   = $this->imagen_o_placeholder( esc_url( (string) ( $this->props['imagen'] ?? '' ) ) );

		$clases = array_filter( array(
			'sofia-hero',
			$this->clase_de_variante( self::CLASES_COMPOSICION, 'composicion' ),
			$this->clase_de_variante( self::CLASES_ALTURA, 'altura' ),
		) );

		$html = '<section ' . $this->atributos_seccion( implode( ' ', $clases ) ) . '>';

		// La imagen va PRIMERO en el HTML aunque en la mayoría de las
		// composiciones se vea a la derecha: en "inmersivo" tiene que
		// quedar detrás del texto, y el orden visual de las demás lo
		// resuelve el CSS con flex-direction. Un solo HTML para las
		// cuatro (ver el docblock).
		$html .= '<div class="sofia-hero__medio">';
		if ( $imagen ) {
			$html .= '<img class="sofia-hero__imagen" ' . $this->atributo_editable( 'imagen' )
				. ' src="' . $imagen . '" alt="' . esc_attr( wp_strip_all_tags( $titulo ) ) . '">';
		}
		$html .= $this->datos_html();
		$html .= '</div>';

		$html .= '<div class="sofia-hero__cuerpo">';

		// Cada campo se omite si está vacío: un Hero con solo título
		// sigue siendo válido y no deja huecos. En el editor igual se
		// muestran, para poder estrenarlos (ver la regla de los campos
		// vacíos en Sofia_Modo_Editor).
		$en_editor = class_exists( 'Sofia_Modo_Editor' ) && Sofia_Modo_Editor::activo();

		if ( '' !== $etiqueta || $en_editor ) {
			$html .= '<p class="sofia-hero__etiqueta" ' . $this->atributo_editable( 'etiqueta' ) . '>' . $etiqueta . '</p>';
		}

		$html .= '<h1 class="sofia-hero__titulo" ' . $this->atributo_editable( 'titulo' ) . ' '
			. $this->atributo_estilo( 'titulo' ) . '>' . $titulo . '</h1>';

		if ( '' !== $texto || $en_editor ) {
			$html .= '<p class="sofia-hero__texto" ' . $this->atributo_editable( 'texto' ) . ' '
				. $this->atributo_estilo( 'texto' ) . '>' . $texto . '</p>';
		}

		$html .= $this->acciones_html( $en_editor );
		$html .= '</div>';

		$html .= '</section>';
		return $html;
	}

	/**
	 * Los dos botones. El principal es sólido, el secundario un enlace
	 * subrayado — dos pesos distintos a propósito: dos botones iguales no
	 * dicen cuál es la acción que importa.
	 */
	private function acciones_html( bool $en_editor ): string {
		$boton = $this->texto_enriquecido( (string) ( $this->props['boton_texto'] ?? '' ) );
		$link  = $this->texto_enriquecido( (string) ( $this->props['link_texto'] ?? '' ) );

		if ( '' === $boton && '' === $link && ! $en_editor ) {
			return '';
		}

		$html = '<div class="sofia-hero__acciones">';
		if ( '' !== $boton || $en_editor ) {
			$html .= '<a class="sofia-hero__boton" href="' . esc_url( (string) ( $this->props['boton_enlace'] ?? '' ) ) . '" '
				. $this->atributo_editable( 'boton_texto' ) . '>' . $boton . '</a>';
		}
		if ( '' !== $link || $en_editor ) {
			$html .= '<a class="sofia-hero__link" href="' . esc_url( (string) ( $this->props['link_enlace'] ?? '' ) ) . '" '
				. $this->atributo_editable( 'link_texto' ) . '>' . $link . '</a>';
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * Los datos destacados.
	 *
	 * No son adorno: "9 bahías, 150 km de costa, 3h desde Guadalajara" le
	 * da al visitante una razón concreta para quedarse, que es lo que un
	 * título solo no hace. En la composición editorial se convierten en
	 * las tarjetas de abajo — mismo dato, otra forma.
	 */
	private function datos_html(): string {
		$datos = is_array( $this->props['datos'] ?? null ) ? $this->props['datos'] : array();
		if ( empty( $datos ) ) {
			return '';
		}

		$html = '<div class="sofia-hero__datos" ' . $this->atributo_lista( 'datos' ) . '>';
		foreach ( array_values( $datos ) as $indice => $dato ) {
			$html .= '<div class="sofia-hero__dato" ' . $this->atributo_item( $indice ) . '>';
			$html .= '<span class="sofia-hero__numero" ' . $this->atributo_editable( "datos.{$indice}.numero" ) . '>'
				. $this->texto_enriquecido( (string) ( $dato['numero'] ?? '' ) ) . '</span>';
			$html .= '<span class="sofia-hero__dato-etiqueta" ' . $this->atributo_editable( "datos.{$indice}.etiqueta" ) . '>'
				. $this->texto_enriquecido( (string) ( $dato['etiqueta'] ?? '' ) ) . '</span>';
			$html .= '</div>';
		}
		$html .= '</div>';
		$html .= $this->boton_agregar_item( 'datos' );
		return $html;
	}
}

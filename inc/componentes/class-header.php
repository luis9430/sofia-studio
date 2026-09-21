<?php
/**
 * Header — la barra de navegación del sitio.
 *
 * Es una pieza NUEVA, no un rediseño: header.php solo abre el <head> y
 * el <body>, nunca dibujó una barra. Hasta ahora un sitio hecho con
 * Sofia Studio no tenía navegación propia del tema.
 *
 * ALCANCE, decidido con el usuario: por ahora se inserta y edita como
 * cualquier bloque, o sea por página. Un header de verdad es el mismo en
 * todo el sitio, y eso exige guardar su contenido a nivel SITIO —
 * trabajo que se hará aparte. El diseño, las composiciones y el
 * responsive no cambian cuando se migre: lo único que cambia es dónde
 * vive el contenido.
 *
 * Es la primera pieza que necesita JavaScript (abrir y cerrar el menú en
 * teléfono). Va como una función más en el archivo compartido de
 * interacciones, nunca en uno propio.
 *
 * Pendiente conocido: menús desplegables (un enlace que abre una grilla
 * de sub-enlaces, como Deel o Urban Outfitters). Se dejó afuera a
 * propósito — multiplica el contenido editable y conviene que el header
 * básico funcione primero.
 */
class Sofia_Componente_Header extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Header', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		return array(
			'logo_texto'   => __( 'Costalegre', 'sofia-studio' ),
			'logo_imagen'  => '',
			'logo_enlace'  => '',
			'aviso_texto'  => '',
			'aviso_enlace' => '',
			'enlaces'      => array(
				array( 'texto' => __( 'Destinos', 'sofia-studio' ), 'enlace' => '' ),
				array( 'texto' => __( 'Hospedaje', 'sofia-studio' ), 'enlace' => '' ),
				array( 'texto' => __( 'Experiencias', 'sofia-studio' ), 'enlace' => '' ),
				array( 'texto' => __( 'Blog', 'sofia-studio' ), 'enlace' => '' ),
			),
			'link_texto'   => __( 'Iniciar sesión', 'sofia-studio' ),
			'link_enlace'  => '',
			'boton_texto'  => __( 'Reservar', 'sofia-studio' ),
			'boton_enlace' => '',
		);
	}

	/**
	 * Capa 1 — CONTENIDO.
	 *
	 * El logo admite texto O imagen: un sitio sin logo hecho todavía usa
	 * el nombre en la tipografía display, que se ve bien y no obliga a
	 * tener un archivo antes de publicar.
	 */
	public static function schema_contenido(): array {
		return array(
			'logo_texto'   => array( 'tipo' => 'texto', 'etiqueta' => __( 'Nombre del sitio', 'sofia-studio' ) ),
			'logo_imagen'  => array( 'tipo' => 'imagen', 'etiqueta' => __( 'Logo (reemplaza al nombre)', 'sofia-studio' ) ),
			'logo_enlace'  => array( 'tipo' => 'url', 'etiqueta' => __( 'Enlace del logo', 'sofia-studio' ) ),
			'enlaces'      => array(
				'tipo'     => 'lista',
				'etiqueta' => __( 'Enlaces del menú', 'sofia-studio' ),
				'campos'   => array(
					'texto'  => array( 'tipo' => 'texto', 'etiqueta' => __( 'Texto', 'sofia-studio' ) ),
					'enlace' => array( 'tipo' => 'url', 'etiqueta' => __( 'Destino', 'sofia-studio' ) ),
				),
			),
			'link_texto'   => array( 'tipo' => 'texto', 'etiqueta' => __( 'Enlace secundario', 'sofia-studio' ) ),
			'link_enlace'  => array( 'tipo' => 'url', 'etiqueta' => __( 'Destino del enlace', 'sofia-studio' ) ),
			'boton_texto'  => array( 'tipo' => 'texto', 'etiqueta' => __( 'Botón', 'sofia-studio' ) ),
			'boton_enlace' => array( 'tipo' => 'url', 'etiqueta' => __( 'Destino del botón', 'sofia-studio' ) ),
			'aviso_texto'  => array( 'tipo' => 'texto', 'etiqueta' => __( 'Barra de aviso (vacío = sin barra)', 'sofia-studio' ) ),
			'aviso_enlace' => array( 'tipo' => 'url', 'etiqueta' => __( 'Destino del aviso', 'sofia-studio' ) ),
		);
	}

	/**
	 * Capa 2 — APARIENCIA.
	 *
	 * Las etiquetas dicen CUÁNDO usar cada composición: son lo único que
	 * el generador por IA tiene para elegir entre tres.
	 */
	public static function schema_propio(): array {
		return array(
			'composicion' => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Composición', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'Clásico — logo, menú y acciones en una fila', 'sofia-studio' ) ),
					array( 'valor' => 'flotante', 'etiqueta' => __( 'Flotante — cuando el hero siguiente tiene imagen a sangre', 'sofia-studio' ) ),
					array( 'valor' => 'centrado', 'etiqueta' => __( 'Logo centrado — aire editorial, hasta 3 enlaces por lado', 'sofia-studio' ) ),
				),
			),
			'tono'        => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Tono', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'Claro', 'sofia-studio' ) ),
					array( 'valor' => 'oscuro', 'etiqueta' => __( 'Oscuro', 'sofia-studio' ) ),
					array( 'valor' => 'transparente', 'etiqueta' => __( 'Transparente (sobre una imagen)', 'sofia-studio' ) ),
				),
			),
			'fijo'        => array(
				'tipo'     => 'toggle',
				'etiqueta' => __( 'Queda fijo al hacer scroll', 'sofia-studio' ),
			),
		);
	}

	private const CLASES_COMPOSICION = array(
		'flotante' => 'sofia-header--flotante',
		'centrado' => 'sofia-header--centrado',
	);

	private const CLASES_TONO = array(
		'oscuro'       => 'sofia-header--oscuro',
		'transparente' => 'sofia-header--transparente',
	);

	/**
	 * Capa 3 — CAJA, a medida.
	 *
	 * Ni PERFIL_SECCION ni PERFIL_PRIMITIVA calzan: un header no tiene
	 * ancho ni alineación propios (ocupa todo el ancho por definición), y
	 * ofrecer esos controles sería ruido. Lo que sí tiene sentido es el
	 * color de fondo, el espaciado y el z-index — este último importa de
	 * verdad cuando está fijo o flotante.
	 */
	public static function claves_estilo_relevantes(): array {
		return array( 'color_fondo', 'color_borde', 'espaciado_vertical', 'z_index', 'max_width' );
	}

	/**
	 * Necesita JS solo para el menú en teléfono. En escritorio el header
	 * funciona sin una línea de JavaScript.
	 */
	public function dependencias_js(): array {
		return array( 'interacciones' );
	}

	/**
	 * render(): una estructura para las tres composiciones.
	 *
	 * El <header> lleva role="banner" implícito por ser hijo directo del
	 * body en el sitio real, y el <nav> un aria-label porque puede haber
	 * más de una navegación en la página (la del footer).
	 */
	public function render(): string {
		$en_editor = class_exists( 'Sofia_Modo_Editor' ) && Sofia_Modo_Editor::activo();

		$clases = array_filter( array(
			'sofia-header',
			$this->clase_de_variante( self::CLASES_COMPOSICION, 'composicion' ),
			$this->clase_de_variante( self::CLASES_TONO, 'tono' ),
			! empty( $this->estilo_bloque()['fijo'] ) ? 'sofia-header--fijo' : '',
		) );

		$html  = '<section ' . $this->atributos_seccion( implode( ' ', $clases ) ) . '>';
		$html .= $this->aviso_html( $en_editor );
		$html .= '<header class="sofia-header__barra">';
		$html .= '<div class="sofia-header__interior">';
		$html .= $this->logo_html();
		$html .= $this->menu_html();
		$html .= $this->acciones_html( $en_editor );
		$html .= $this->boton_menu_html();
		$html .= '</div>';
		$html .= '</header>';
		$html .= '</section>';

		return $html;
	}

	/**
	 * La barra de aviso: promociones o avisos temporales.
	 *
	 * Se omite si el texto está vacío — apagarla es borrar el texto, no
	 * buscar un toggle aparte. Un control menos para el mismo resultado.
	 */
	private function aviso_html( bool $en_editor ): string {
		$texto = $this->texto_enriquecido( (string) ( $this->props['aviso_texto'] ?? '' ) );
		if ( '' === $texto && ! $en_editor ) {
			return '';
		}

		$enlace = esc_url( (string) ( $this->props['aviso_enlace'] ?? '' ) );
		$html   = '<div class="sofia-header__aviso">';
		$html  .= '<span ' . $this->atributo_editable( 'aviso_texto' ) . '>' . $texto . '</span>';
		if ( '' !== $enlace ) {
			$html .= '<a class="sofia-header__aviso-link" href="' . $enlace . '">' . esc_html__( 'Ver más', 'sofia-studio' ) . '</a>';
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * El logo: imagen si hay, si no el nombre en la tipografía display.
	 *
	 * No usa imagen_o_placeholder(): el nombre YA es un fallback válido y
	 * visible, así que un placeholder gris sería peor que lo que
	 * reemplaza. El campo de imagen se edita desde el panel.
	 */
	private function logo_html(): string {
		$texto  = $this->texto_enriquecido( (string) ( $this->props['logo_texto'] ?? '' ) );
		$imagen = esc_url( (string) ( $this->props['logo_imagen'] ?? '' ) );
		$enlace = esc_url( (string) ( $this->props['logo_enlace'] ?? '' ) );

		$html = '<a class="sofia-header__logo" href="' . ( '' !== $enlace ? $enlace : '#' ) . '">';
		if ( '' !== $imagen ) {
			$html .= '<img class="sofia-header__logo-img" ' . $this->atributo_editable( 'logo_imagen' )
				. ' src="' . $imagen . '" alt="' . esc_attr( wp_strip_all_tags( $texto ) ) . '">';
		} else {
			$html .= '<span class="sofia-header__logo-texto" ' . $this->atributo_editable( 'logo_texto' ) . '>' . $texto . '</span>';
		}
		$html .= '</a>';
		return $html;
	}

	/**
	 * El menú. El mismo <nav> sirve para escritorio y teléfono: el CSS lo
	 * reacomoda y el JS solo alterna un atributo.
	 *
	 * Un solo menú y no dos ocultándose entre sí, porque duplicar la
	 * lista rompería el contrato del editor — los data-sofia-item
	 * aparecerían dos veces y el conteo de items quedaría al doble.
	 */
	private function menu_html(): string {
		$enlaces = is_array( $this->props['enlaces'] ?? null ) ? $this->props['enlaces'] : array();

		$html = '<nav class="sofia-header__nav" aria-label="' . esc_attr__( 'Principal', 'sofia-studio' ) . '" '
			. $this->atributo_lista( 'enlaces' ) . '>';

		foreach ( array_values( $enlaces ) as $indice => $enlace ) {
			$texto   = $this->texto_enriquecido( (string) ( $enlace['texto'] ?? '' ) );
			$destino = esc_url( (string) ( $enlace['enlace'] ?? '' ) );
			$html   .= '<a class="sofia-header__enlace" href="' . ( '' !== $destino ? $destino : '#' ) . '" '
				. $this->atributo_item( $indice ) . ' ' . $this->atributo_editable( "enlaces.{$indice}.texto" ) . '>'
				. $texto . '</a>';
		}

		$html .= '</nav>';
		$html .= $this->boton_agregar_item( 'enlaces' );
		return $html;
	}

	/** Enlace secundario y botón: dos pesos distintos. */
	private function acciones_html( bool $en_editor ): string {
		$link  = $this->texto_enriquecido( (string) ( $this->props['link_texto'] ?? '' ) );
		$boton = $this->texto_enriquecido( (string) ( $this->props['boton_texto'] ?? '' ) );

		if ( '' === $link && '' === $boton && ! $en_editor ) {
			return '';
		}

		$html = '<div class="sofia-header__acciones">';
		if ( '' !== $link || $en_editor ) {
			$html .= '<a class="sofia-header__link" href="' . esc_url( (string) ( $this->props['link_enlace'] ?? '' ) ) . '" '
				. $this->atributo_editable( 'link_texto' ) . '>' . $link . '</a>';
		}
		if ( '' !== $boton || $en_editor ) {
			$html .= '<a class="sofia-header__boton" href="' . esc_url( (string) ( $this->props['boton_enlace'] ?? '' ) ) . '" '
				. $this->atributo_editable( 'boton_texto' ) . '>' . $boton . '</a>';
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * El botón de hamburguesa.
	 *
	 * Un <button> de verdad, no un div con onclick: así el teclado lo
	 * alcanza con Tab y un lector de pantalla lo anuncia como botón.
	 * aria-expanded lo mantiene el JS en sincronía — sin eso, el lector
	 * anuncia un botón que no dice si abre o cierra.
	 *
	 * 44px es el mínimo para acertarle con el dedo en un teléfono.
	 */
	private function boton_menu_html(): string {
		return '<button type="button" class="sofia-header__hamburguesa" data-sofia-menu-boton'
			. ' aria-expanded="false" aria-label="' . esc_attr__( 'Abrir menú', 'sofia-studio' ) . '">'
			. '<span class="sofia-header__hamburguesa-icono" aria-hidden="true">'
			. $this->svg_icono( 'menu-2', 22 )
			. '</span></button>';
	}
}

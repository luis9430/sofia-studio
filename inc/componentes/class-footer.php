<?php
/**
 * Footer — el cierre del sitio.
 *
 * Pieza NUEVA, igual que Header: footer.php solo cerraba el </body>,
 * nunca dibujó nada. Y es lo último que ve cualquier visitante de
 * cualquier página, así que un sitio sin footer se siente incompleto sin
 * que nadie sepa bien por qué.
 *
 * ALCANCE: mismo criterio que Header — por ahora se edita por página.
 * Migrar a nivel sitio es trabajo aparte y no cambia el diseño.
 *
 * Las columnas de enlaces son una LISTA DE LISTAS, que es el primer caso
 * del catálogo. El editor no soporta listas anidadas (reconstruye cada
 * item leyendo un solo nivel de data-sofia-item), así que se resuelve
 * con hasta cuatro columnas declaradas como campos independientes:
 * cada una con su título y sus enlaces. Menos elegante que una lista
 * anidada, pero editable de verdad — que es lo que importa.
 */
class Sofia_Componente_Footer extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Footer', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		return array(
			'logo_texto'    => __( 'Costalegre', 'sofia-studio' ),
			'logo_imagen'   => '',
			'descripcion'   => __( 'Nueve bahías en la costa sur de Jalisco. Hospedaje, traslados y rutas sin paquetes cerrados.', 'sofia-studio' ),

			'cierre_titulo' => __( '¿Listo para conocer la costa?', 'sofia-studio' ),
			'cierre_texto'  => __( 'Contanos qué buscás y te armamos la ruta. Respondemos el mismo día.', 'sofia-studio' ),
			'cierre_boton'  => __( 'Escribinos', 'sofia-studio' ),
			'cierre_enlace' => '',

			'col1_titulo'   => __( 'Destinos', 'sofia-studio' ),
			'col1_enlaces'  => array(
				array( 'texto' => __( 'Careyes', 'sofia-studio' ), 'enlace' => '' ),
				array( 'texto' => __( 'Chamela', 'sofia-studio' ), 'enlace' => '' ),
				array( 'texto' => __( 'Tenacatita', 'sofia-studio' ), 'enlace' => '' ),
			),
			'col2_titulo'   => __( 'Planear', 'sofia-studio' ),
			'col2_enlaces'  => array(
				array( 'texto' => __( 'Cómo llegar', 'sofia-studio' ), 'enlace' => '' ),
				array( 'texto' => __( 'Hospedaje', 'sofia-studio' ), 'enlace' => '' ),
				array( 'texto' => __( 'Mejor época', 'sofia-studio' ), 'enlace' => '' ),
			),
			'col3_titulo'   => __( 'Contacto', 'sofia-studio' ),
			'col3_enlaces'  => array(
				array( 'texto' => 'hola@costalegre.mx', 'enlace' => '' ),
				array( 'texto' => '+52 315 100 0000', 'enlace' => '' ),
				array( 'texto' => __( 'Blog', 'sofia-studio' ), 'enlace' => '' ),
			),

			'redes'         => array(
				array( 'icono' => 'brand-instagram', 'enlace' => '' ),
				array( 'icono' => 'brand-facebook', 'enlace' => '' ),
				array( 'icono' => 'brand-whatsapp', 'enlace' => '' ),
			),

			'legal'         => __( '© 2026 Costalegre. Todos los derechos reservados.', 'sofia-studio' ),
			'legal_enlaces' => array(
				array( 'texto' => __( 'Privacidad', 'sofia-studio' ), 'enlace' => '' ),
				array( 'texto' => __( 'Términos', 'sofia-studio' ), 'enlace' => '' ),
			),
		);
	}

	/**
	 * Capa 1 — CONTENIDO.
	 *
	 * Tres columnas de enlaces como campos separados y no una lista
	 * anidada: el editor reconstruye cada item leyendo un solo nivel de
	 * data-sofia-item, así que una lista dentro de otra se corrompería al
	 * editarla. Tres columnas cubren el 90% de los footers reales; una
	 * columna vacía simplemente no se dibuja.
	 */
	public static function schema_contenido(): array {
		$columna_enlaces = array(
			'tipo'     => 'lista',
			'etiqueta' => __( 'Enlaces', 'sofia-studio' ),
			'campos'   => array(
				'texto'  => array( 'tipo' => 'texto', 'etiqueta' => __( 'Texto', 'sofia-studio' ) ),
				'enlace' => array( 'tipo' => 'url', 'etiqueta' => __( 'Destino', 'sofia-studio' ) ),
			),
		);

		return array(
			'logo_texto'    => array( 'tipo' => 'texto', 'etiqueta' => __( 'Nombre del sitio', 'sofia-studio' ) ),
			'logo_imagen'   => array( 'tipo' => 'imagen', 'etiqueta' => __( 'Logo (reemplaza al nombre)', 'sofia-studio' ) ),
			'descripcion'   => array( 'tipo' => 'texto_largo', 'etiqueta' => __( 'Descripción breve', 'sofia-studio' ) ),

			'cierre_titulo' => array( 'tipo' => 'texto', 'etiqueta' => __( 'Título del cierre', 'sofia-studio' ) ),
			'cierre_texto'  => array( 'tipo' => 'texto_largo', 'etiqueta' => __( 'Texto del cierre', 'sofia-studio' ) ),
			'cierre_boton'  => array( 'tipo' => 'texto', 'etiqueta' => __( 'Botón del cierre', 'sofia-studio' ) ),
			'cierre_enlace' => array( 'tipo' => 'url', 'etiqueta' => __( 'Destino del botón', 'sofia-studio' ) ),

			'col1_titulo'   => array( 'tipo' => 'texto', 'etiqueta' => __( 'Columna 1 — título', 'sofia-studio' ) ),
			'col1_enlaces'  => $columna_enlaces,
			'col2_titulo'   => array( 'tipo' => 'texto', 'etiqueta' => __( 'Columna 2 — título', 'sofia-studio' ) ),
			'col2_enlaces'  => $columna_enlaces,
			'col3_titulo'   => array( 'tipo' => 'texto', 'etiqueta' => __( 'Columna 3 — título', 'sofia-studio' ) ),
			'col3_enlaces'  => $columna_enlaces,

			'redes'         => array(
				'tipo'     => 'lista',
				'etiqueta' => __( 'Redes sociales', 'sofia-studio' ),
				'campos'   => array(
					'icono'  => array( 'tipo' => 'icono', 'etiqueta' => __( 'Red', 'sofia-studio' ) ),
					'enlace' => array( 'tipo' => 'url', 'etiqueta' => __( 'Perfil', 'sofia-studio' ) ),
				),
			),

			'legal'         => array( 'tipo' => 'texto', 'etiqueta' => __( 'Línea legal', 'sofia-studio' ) ),
			'legal_enlaces' => array(
				'tipo'     => 'lista',
				'etiqueta' => __( 'Enlaces legales', 'sofia-studio' ),
				'campos'   => array(
					'texto'  => array( 'tipo' => 'texto', 'etiqueta' => __( 'Texto', 'sofia-studio' ) ),
					'enlace' => array( 'tipo' => 'url', 'etiqueta' => __( 'Destino', 'sofia-studio' ) ),
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
					array( 'valor' => '', 'etiqueta' => __( 'Columnas — cuando hay muchos enlaces que ordenar', 'sofia-studio' ) ),
					array( 'valor' => 'cierre', 'etiqueta' => __( 'Con cierre — una última invitación antes de irse', 'sofia-studio' ) ),
					array( 'valor' => 'minimo', 'etiqueta' => __( 'Mínimo — para sitios de pocas páginas', 'sofia-studio' ) ),
				),
			),
			'tono'        => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Tono', 'sofia-studio' ),
				'opciones' => array(
					// El vacío es OSCURO, no claro: un footer recién
					// insertado tiene que salir bien sin configurar nada, y
					// un footer claro sobre una página clara no marca dónde
					// termina el contenido.
					array( 'valor' => '', 'etiqueta' => __( 'Oscuro', 'sofia-studio' ) ),
					array( 'valor' => 'claro', 'etiqueta' => __( 'Claro', 'sofia-studio' ) ),
				),
			),
		);
	}

	private const CLASES_COMPOSICION = array(
		'cierre' => 'sofia-footer--cierre',
		'minimo' => 'sofia-footer--minimo',
	);

	private const CLASES_TONO = array(
		'claro' => 'sofia-footer--claro',
	);

	/** Capa 3 — CAJA. Mismo criterio que Header: ocupa todo el ancho. */
	public static function claves_estilo_relevantes(): array {
		return array( 'color_fondo', 'color_borde', 'espaciado_vertical', 'max_width' );
	}

	public function render(): string {
		$en_editor = class_exists( 'Sofia_Modo_Editor' ) && Sofia_Modo_Editor::activo();

		$clases = array_filter( array(
			'sofia-footer',
			$this->clase_de_variante( self::CLASES_COMPOSICION, 'composicion' ),
			$this->clase_de_variante( self::CLASES_TONO, 'tono' ),
		) );

		$html  = '<section ' . $this->atributos_seccion( implode( ' ', $clases ) ) . '>';
		$html .= $this->cierre_html( $en_editor );
		$html .= '<footer class="sofia-footer__cuerpo">';
		$html .= '<div class="sofia-footer__interior">';
		$html .= '<div class="sofia-footer__principal">';
		$html .= $this->marca_html( $en_editor );
		$html .= $this->columnas_html();
		$html .= '</div>';
		$html .= $this->legal_html( $en_editor );
		$html .= '</div>';
		$html .= '</footer>';
		$html .= '</section>';

		return $html;
	}

	/**
	 * El bloque de cierre: una última invitación antes de que el
	 * visitante se vaya.
	 *
	 * Solo se dibuja en la composición "cierre" — en las otras dos sería
	 * una sección de más. Y si el título está vacío tampoco se dibuja,
	 * así que apagarlo es borrarlo.
	 */
	private function cierre_html( bool $en_editor ): string {
		if ( 'cierre' !== ( $this->estilo_bloque()['composicion'] ?? '' ) ) {
			return '';
		}

		$titulo = $this->texto_enriquecido( (string) ( $this->props['cierre_titulo'] ?? '' ) );
		$texto  = $this->texto_enriquecido( (string) ( $this->props['cierre_texto'] ?? '' ) );
		$boton  = $this->texto_enriquecido( (string) ( $this->props['cierre_boton'] ?? '' ) );

		if ( '' === $titulo && ! $en_editor ) {
			return '';
		}

		$html  = '<div class="sofia-footer__cierre"><div class="sofia-footer__cierre-interior">';
		$html .= '<div class="sofia-footer__cierre-texto">';
		$html .= '<h2 class="sofia-footer__cierre-titulo" ' . $this->atributo_editable( 'cierre_titulo' ) . ' '
			. $this->atributo_estilo( 'cierre_titulo' ) . '>' . $titulo . '</h2>';
		if ( '' !== $texto || $en_editor ) {
			$html .= '<p class="sofia-footer__cierre-bajada" ' . $this->atributo_editable( 'cierre_texto' ) . '>' . $texto . '</p>';
		}
		$html .= '</div>';
		if ( '' !== $boton || $en_editor ) {
			$html .= '<a class="sofia-footer__cierre-boton" href="' . esc_url( (string) ( $this->props['cierre_enlace'] ?? '' ) ) . '" '
				. $this->atributo_editable( 'cierre_boton' ) . '>' . $boton . '</a>';
		}
		$html .= '</div></div>';
		return $html;
	}

	/** Logo, descripción y redes. */
	private function marca_html( bool $en_editor ): string {
		$texto  = $this->texto_enriquecido( (string) ( $this->props['logo_texto'] ?? '' ) );
		$imagen = esc_url( (string) ( $this->props['logo_imagen'] ?? '' ) );
		$desc   = $this->texto_enriquecido( (string) ( $this->props['descripcion'] ?? '' ) );

		$html = '<div class="sofia-footer__marca">';

		if ( '' !== $imagen ) {
			$html .= '<img class="sofia-footer__logo-img" ' . $this->atributo_editable( 'logo_imagen' )
				. ' src="' . $imagen . '" alt="' . esc_attr( wp_strip_all_tags( $texto ) ) . '">';
		} else {
			$html .= '<p class="sofia-footer__logo" ' . $this->atributo_editable( 'logo_texto' ) . '>' . $texto . '</p>';
		}

		if ( '' !== $desc || $en_editor ) {
			$html .= '<p class="sofia-footer__descripcion" ' . $this->atributo_editable( 'descripcion' ) . '>' . $desc . '</p>';
		}

		$html .= $this->redes_html();
		$html .= '</div>';
		return $html;
	}

	/**
	 * Las redes.
	 *
	 * Cada una es un <a> con aria-label: un enlace cuyo único contenido
	 * es un ícono no tiene texto que un lector de pantalla pueda leer, y
	 * se anunciaría como "enlace" a secas. El nombre sale del ícono, así
	 * que agregar una red nueva no pide un campo de texto extra.
	 */
	private function redes_html(): string {
		$redes = is_array( $this->props['redes'] ?? null ) ? $this->props['redes'] : array();
		if ( empty( $redes ) ) {
			return '';
		}

		$nombres = array(
			'brand-instagram' => 'Instagram',
			'brand-facebook'  => 'Facebook',
			'brand-whatsapp'  => 'WhatsApp',
			'mail'            => __( 'Correo', 'sofia-studio' ),
			'phone'           => __( 'Teléfono', 'sofia-studio' ),
		);

		$html = '<div class="sofia-footer__redes" ' . $this->atributo_lista( 'redes' ) . '>';
		foreach ( array_values( $redes ) as $indice => $red ) {
			$icono  = (string) ( $red['icono'] ?? '' );
			$nombre = $nombres[ $icono ] ?? __( 'Red social', 'sofia-studio' );
			$html  .= '<a class="sofia-footer__red" href="' . esc_url( (string) ( $red['enlace'] ?? '' ) ) . '" '
				. $this->atributo_item( $indice ) . ' aria-label="' . esc_attr( $nombre ) . '">'
				. '<span ' . $this->atributo_editable( "redes.{$indice}.icono" ) . ' aria-hidden="true">'
				. $this->svg_icono( $icono, 17, '', 'external-link' ) . '</span></a>';
		}
		$html .= '</div>';
		$html .= $this->boton_agregar_item( 'redes' );
		return $html;
	}

	/** Las tres columnas de enlaces. Una columna sin título no se dibuja. */
	private function columnas_html(): string {
		$html = '<div class="sofia-footer__columnas">';

		for ( $n = 1; $n <= 3; $n++ ) {
			$titulo  = $this->texto_enriquecido( (string) ( $this->props[ "col{$n}_titulo" ] ?? '' ) );
			$enlaces = is_array( $this->props[ "col{$n}_enlaces" ] ?? null ) ? $this->props[ "col{$n}_enlaces" ] : array();

			if ( '' === $titulo && empty( $enlaces ) ) {
				continue;
			}

			$html .= '<div class="sofia-footer__columna">';
			$html .= '<p class="sofia-footer__columna-titulo" ' . $this->atributo_editable( "col{$n}_titulo" ) . '>' . $titulo . '</p>';
			$html .= '<nav class="sofia-footer__columna-enlaces" aria-label="' . esc_attr( wp_strip_all_tags( $titulo ) ) . '" '
				. $this->atributo_lista( "col{$n}_enlaces" ) . '>';

			foreach ( array_values( $enlaces ) as $indice => $enlace ) {
				$texto   = $this->texto_enriquecido( (string) ( $enlace['texto'] ?? '' ) );
				$destino = esc_url( (string) ( $enlace['enlace'] ?? '' ) );
				$html   .= '<a class="sofia-footer__enlace" href="' . ( '' !== $destino ? $destino : '#' ) . '" '
					. $this->atributo_item( $indice ) . ' ' . $this->atributo_editable( "col{$n}_enlaces.{$indice}.texto" ) . '>'
					. $texto . '</a>';
			}

			$html .= '</nav>';
			$html .= $this->boton_agregar_item( "col{$n}_enlaces" );
			$html .= '</div>';
		}

		$html .= '</div>';
		return $html;
	}

	/** La línea legal de abajo. */
	private function legal_html( bool $en_editor ): string {
		$legal   = $this->texto_enriquecido( (string) ( $this->props['legal'] ?? '' ) );
		$enlaces = is_array( $this->props['legal_enlaces'] ?? null ) ? $this->props['legal_enlaces'] : array();

		if ( '' === $legal && empty( $enlaces ) && ! $en_editor ) {
			return '';
		}

		$html  = '<div class="sofia-footer__legal">';
		$html .= '<p class="sofia-footer__legal-texto" ' . $this->atributo_editable( 'legal' ) . '>' . $legal . '</p>';

		if ( ! empty( $enlaces ) ) {
			$html .= '<nav class="sofia-footer__legal-enlaces" aria-label="' . esc_attr__( 'Legal', 'sofia-studio' ) . '" '
				. $this->atributo_lista( 'legal_enlaces' ) . '>';
			foreach ( array_values( $enlaces ) as $indice => $enlace ) {
				$texto   = $this->texto_enriquecido( (string) ( $enlace['texto'] ?? '' ) );
				$destino = esc_url( (string) ( $enlace['enlace'] ?? '' ) );
				$html   .= '<a class="sofia-footer__legal-enlace" href="' . ( '' !== $destino ? $destino : '#' ) . '" '
					. $this->atributo_item( $indice ) . ' ' . $this->atributo_editable( "legal_enlaces.{$indice}.texto" ) . '>'
					. $texto . '</a>';
			}
			$html .= '</nav>';
			$html .= $this->boton_agregar_item( 'legal_enlaces' );
		}

		$html .= '</div>';
		return $html;
	}
}

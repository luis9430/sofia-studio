<?php
/**
 * Carousel — diapositivas que se deslizan de a una.
 *
 * El único de la Fase 6 que lleva JS, y aun así mucho menos del que un
 * carrusel suele arrastrar. La base es scroll-snap de CSS: el
 * deslizamiento con el dedo, la inercia, el imán que encaja cada
 * diapositiva en su lugar y el recorrido con el teclado ya los da el
 * navegador. Sin scroll-snap habría que reimplementar todo eso a mano —
 * que es de dónde salen los carruseles de 900 líneas.
 *
 * Lo único que el navegador no da declarativamente es lo que agrega
 * activarCarousel() en el archivo compartido de interacciones: los
 * botones anterior/siguiente y los puntos que marcan en qué diapositiva
 * estás. Nada más.
 *
 * Y como el desplazamiento es scroll de verdad, el carrusel SIGUE
 * FUNCIONANDO si el JS no carga: se arrastra con el dedo o con la barra.
 * Los controles son una mejora encima, no el mecanismo.
 */
class Sofia_Componente_Carousel extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Carrusel', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		return array(
			'items' => array(
				array( 'imagen' => '', 'titulo' => __( 'Primera diapositiva', 'sofia-studio' ), 'texto' => '' ),
				array( 'imagen' => '', 'titulo' => __( 'Segunda diapositiva', 'sofia-studio' ), 'texto' => '' ),
				array( 'imagen' => '', 'titulo' => __( 'Tercera diapositiva', 'sofia-studio' ), 'texto' => '' ),
			),
		);
	}

	/** Capa 1 — CONTENIDO. */
	public static function schema_contenido(): array {
		return array(
			'items' => array(
				'tipo'     => 'lista',
				'etiqueta' => __( 'Diapositivas', 'sofia-studio' ),
				'campos'   => array(
					'imagen' => array( 'tipo' => 'imagen', 'etiqueta' => __( 'Imagen', 'sofia-studio' ) ),
					'titulo' => array( 'tipo' => 'texto', 'etiqueta' => __( 'Título', 'sofia-studio' ) ),
					'texto'  => array( 'tipo' => 'texto_largo', 'etiqueta' => __( 'Texto', 'sofia-studio' ) ),
				),
			),
		);
	}

	/**
	 * Capa 2 — APARIENCIA.
	 *
	 * "por_vista" es cuántas diapositivas se ven a la vez. No existe un
	 * control de "avance automático" a propósito: un carrusel que se
	 * mueve solo le saca al usuario el control de la lectura, y para
	 * quien usa lector de pantalla o tiene dificultad motriz es
	 * directamente contenido que se escapa. Si alguna vez se agrega,
	 * tiene que venir con botón de pausa, que es lo que pide la pauta
	 * WCAG 2.2.2.
	 */
	public static function schema_propio(): array {
		return array(
			'por_vista' => array(
				'tipo'     => 'botones_numero',
				'etiqueta' => __( 'Visibles a la vez', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => '1', 'etiqueta' => '1' ),
					array( 'valor' => '2', 'etiqueta' => '2' ),
					array( 'valor' => '3', 'etiqueta' => '3' ),
				),
			),
			'controles' => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Controles', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => 'flechas_y_puntos', 'etiqueta' => __( 'Flechas y puntos', 'sofia-studio' ) ),
					array( 'valor' => 'solo_flechas', 'etiqueta' => __( 'Solo flechas', 'sofia-studio' ) ),
					array( 'valor' => 'solo_puntos', 'etiqueta' => __( 'Solo puntos', 'sofia-studio' ) ),
				),
			),
		);
	}

	private const CLASES_POR_VISTA = array(
		'2' => 'sofia-carousel--dos',
		'3' => 'sofia-carousel--tres',
	);

	/** Capa 3 — CAJA. */
	public static function claves_estilo_relevantes(): array {
		return parent::PERFIL_SECCION;
	}

	public function dependencias_js(): array {
		return array( 'interacciones' );
	}

	/**
	 * render(): en el editor la pista se renderiza como una columna
	 * (clase --estatico), no como un carrusel.
	 *
	 * Misma razón que en Marquee y que en Tabs: el contenido que está
	 * fuera de la ventana visible no se puede clickear para editarlo. Un
	 * carrusel de cinco diapositivas dejaría cuatro inalcanzables en el
	 * canvas. Apilarlas las hace todas editables sin tener que "navegar"
	 * hasta cada una.
	 */
	public function render(): string {
		$items     = is_array( $this->props['items'] ?? null ) ? $this->props['items'] : array();
		$en_editor = class_exists( 'Sofia_Modo_Editor' ) && Sofia_Modo_Editor::activo();
		$controles = (string) ( $this->estilo_bloque()['controles'] ?? 'flechas_y_puntos' );

		$clases = array_filter( array(
			'sofia-carousel',
			$this->clase_de_variante( self::CLASES_POR_VISTA, 'por_vista' ),
			$en_editor ? 'sofia-carousel--estatico' : '',
		) );

		$html  = '<section ' . $this->atributos_seccion( implode( ' ', $clases ) ) . ' data-sofia-carousel>';
		$html .= '<div class="sofia-carousel__pista" ' . $this->atributo_lista( 'items' ) . '>';

		foreach ( array_values( $items ) as $indice => $item ) {
			$imagen = $this->imagen_o_placeholder( (string) ( $item['imagen'] ?? '' ) );
			$titulo = $this->texto_enriquecido( (string) ( $item['titulo'] ?? '' ) );
			$texto  = $this->texto_enriquecido( (string) ( $item['texto'] ?? '' ) );

			$html .= '<div class="sofia-carousel__slide" data-sofia-slide ' . $this->atributo_item( $indice ) . '>';
			if ( $imagen ) {
				$html .= '<img class="sofia-carousel__imagen" src="' . esc_url( $imagen ) . '" alt="" loading="lazy" '
					. $this->atributo_editable( "items.{$indice}.imagen" ) . '>';
			}
			$html .= '<div class="sofia-carousel__cuerpo">';
			$html .= '<div class="sofia-carousel__titulo" '
				. $this->atributo_editable( "items.{$indice}.titulo" ) . ' '
				. $this->atributo_estilo( "items.{$indice}.titulo" ) . '>' . $titulo . '</div>';
			$html .= '<div class="sofia-carousel__texto" '
				. $this->atributo_editable( "items.{$indice}.texto" ) . ' '
				. $this->atributo_estilo( "items.{$indice}.texto" ) . '>' . $texto . '</div>';
			$html .= '</div>';
			$html .= '</div>';
		}

		$html .= '</div>';

		// Los controles no se renderizan en el editor: ahí no hay nada
		// que controlar porque las diapositivas están todas apiladas.
		if ( ! $en_editor && count( $items ) > 1 ) {
			if ( 'solo_puntos' !== $controles ) {
				$html .= '<button type="button" class="sofia-carousel__flecha sofia-carousel__flecha--anterior"'
					. ' data-sofia-carousel-anterior aria-label="' . esc_attr__( 'Anterior', 'sofia-studio' ) . '"></button>';
				$html .= '<button type="button" class="sofia-carousel__flecha sofia-carousel__flecha--siguiente"'
					. ' data-sofia-carousel-siguiente aria-label="' . esc_attr__( 'Siguiente', 'sofia-studio' ) . '"></button>';
			}
			if ( 'solo_flechas' !== $controles ) {
				$html .= '<div class="sofia-carousel__puntos" data-sofia-carousel-puntos>';
				foreach ( array_values( $items ) as $indice => $item ) {
					$html .= '<button type="button" class="sofia-carousel__punto" data-sofia-carousel-punto="' . (int) $indice . '"'
						. ' aria-label="' . esc_attr( sprintf( __( 'Ir a la diapositiva %d', 'sofia-studio' ), $indice + 1 ) ) . '"></button>';
				}
				$html .= '</div>';
			}
		}

		$html .= $this->boton_agregar_item( 'items' );
		$html .= '</section>';
		return $html;
	}
}

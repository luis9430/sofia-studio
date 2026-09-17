<?php
/**
 * Card — imagen + título + texto + botón.
 *
 * MOLDE DEL CONTRATO DE COMPONENTES. Este archivo es la referencia para
 * migrar el resto del catálogo, así que documenta no solo qué hace sino
 * por qué cada decisión cae donde cae. Las cuatro reglas:
 *
 * 1. TRES CAPAS DE PROPS, nunca mezcladas.
 *    - Contenido (schema_contenido): lo que el bloque DICE — imagen,
 *      título, texto, el botón y su enlace.
 *    - Apariencia (schema_propio): cómo se ve ESTE tipo de bloque —
 *      orientación y superficie. Vive en Nivel 2, guardado dentro de
 *      _estilo_bloque.
 *    - Caja (claves_estilo_relevantes): lo transversal que comparte con
 *      cualquier otro bloque — ancho, alineación, fondo, espaciado.
 *
 * 2. TODA PROP DE APARIENCIA ES UNA LISTA CERRADA. Nunca texto libre:
 *    "orientacion" y "superficie" son selects con opciones fijas, así el
 *    editor no puede producir un valor que el render no sepa dibujar, y
 *    la validación del generador por IA tiene contra qué comparar.
 *
 * 3. CORE FRAMEWORK PRIMERO. La orientación no lleva CSS propio: usa las
 *    utility classes reales del plugin (flex-row/flex-column + gap-m +
 *    items-middle), igual que ya hace Container. El CSS del tema queda
 *    solo para lo que es genuinamente de este bloque — el recorte de la
 *    imagen, el espaciado interno del cuerpo.
 *
 * 4. UN COMPONENTE = UN PHP + SU BLOQUE DE CSS. Card no necesita JS; si
 *    lo necesitara, sería una función más en el archivo compartido de
 *    interacciones, nunca un archivo propio.
 *
 * Pensada para vivir dentro de un Container en grilla (varias Cards en
 * fila), así que no trae su propio sistema de columnas.
 */
class Sofia_Componente_Card extends Sofia_Componente {

	/**
	 * CLASES_ORIENTACION: la diferencia entre una Card vertical y una
	 * horizontal es puramente de layout, y Core Framework ya lo resuelve
	 * — este mapa solo elige qué utility classes agregar.
	 *
	 * "vertical" no aparece acá a propósito: es el apilado natural de un
	 * <section> en flujo normal, sin flex de por medio, así que no
	 * necesita ninguna clase. clase_de_variante() devuelve "" para
	 * cualquier valor que no esté en el mapa, incluido ese.
	 */
	private const CLASES_ORIENTACION = array(
		'horizontal' => 'flex-row gap-m items-middle',
	);

	/**
	 * CLASES_SUPERFICIE: agrupa radius + sombra + borde en una sola
	 * decisión con nombre humano, en vez de tres controles sueltos que el
	 * usuario tiene que combinar bien para que la caja se vea coherente.
	 * Quien quiera control fino de cada propiedad lo sigue teniendo en
	 * "Caja y posición" (Nivel 2 genérico), que pisa esto.
	 */
	private const CLASES_SUPERFICIE = array(
		'elevada'    => 'sofia-card--elevada',
		'con-borde'  => 'sofia-card--con-borde',
	);

	public function nombre(): string {
		return __( 'Card', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		return array(
			'imagen'       => '',
			'titulo'       => __( 'Título de la tarjeta', 'sofia-studio' ),
			'texto'        => __( 'Describe brevemente este elemento.', 'sofia-studio' ),
			'boton_texto'  => __( 'Ver más', 'sofia-studio' ),
			'boton_enlace' => '#',
		);
	}

	/** Capa 1 — CONTENIDO: lo que el bloque dice. */
	public static function schema_contenido(): array {
		return array(
			'imagen'       => array( 'tipo' => 'imagen', 'etiqueta' => __( 'Imagen', 'sofia-studio' ) ),
			'titulo'       => array( 'tipo' => 'texto', 'etiqueta' => __( 'Título', 'sofia-studio' ) ),
			'texto'        => array( 'tipo' => 'texto_largo', 'etiqueta' => __( 'Texto', 'sofia-studio' ) ),
			'boton_texto'  => array( 'tipo' => 'texto', 'etiqueta' => __( 'Texto del botón', 'sofia-studio' ) ),
			'boton_enlace' => array( 'tipo' => 'url', 'etiqueta' => __( 'Enlace del botón', 'sofia-studio' ) ),
		);
	}

	/**
	 * Capa 2 — APARIENCIA: cómo se ve una Card, con opciones propias de
	 * este tipo de bloque. Dos ejes independientes y combinables (una Card
	 * horizontal puede ser elevada o plana), mismo criterio que
	 * forma+color en Badge.
	 *
	 * "Orientación horizontal" es, en concreto, la Card que antes no
	 * existía y que motivó toda la discusión sobre variantes: no hace
	 * falta un tipo nuevo en la Factory ni duplicar el componente, es una
	 * prop de apariencia que cambia dos utility classes.
	 */
	public static function schema_propio(): array {
		return array(
			'orientacion' => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Orientación', 'sofia-studio' ),
				'opciones' => array(
					// El valor del default es "vertical", no "": una opción
					// cuya etiqueta dice "Vertical" pero cuyo valor es
					// vacío invita a escribir "vertical" y que se
					// descarte al validar (pasó con el generador). Cuando
					// el default tiene nombre propio, conviene nombrarlo.
					array( 'valor' => 'vertical', 'etiqueta' => __( 'Vertical (imagen arriba)', 'sofia-studio' ) ),
					array( 'valor' => 'horizontal', 'etiqueta' => __( 'Horizontal (imagen al costado)', 'sofia-studio' ) ),
				),
			),
			'superficie'  => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Superficie', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'Plana', 'sofia-studio' ) ),
					array( 'valor' => 'elevada', 'etiqueta' => __( 'Elevada (con sombra)', 'sofia-studio' ) ),
					array( 'valor' => 'con-borde', 'etiqueta' => __( 'Con borde', 'sofia-studio' ) ),
				),
			),
		);
	}

	/**
	 * Capa 3 — CAJA: lo transversal. PERFIL_SECCION porque una Card
	 * SIEMPRE es una caja visual (a diferencia de Hero, que puede ser una
	 * sección libre), más aspect_ratio/object_fit porque tiene una <img>
	 * real que recortar.
	 */
	public static function claves_estilo_relevantes(): array {
		return array_unique( array_merge( parent::PERFIL_SECCION, array( 'aspect_ratio', 'object_fit' ) ) );
	}

	public function render(): string {
		$titulo       = $this->texto_enriquecido( $this->props['titulo'] );
		$texto        = $this->texto_enriquecido( $this->props['texto'] );
		$boton_texto  = $this->texto_enriquecido( $this->props['boton_texto'] );
		$boton_enlace = esc_url( $this->props['boton_enlace'] );
		// imagen_o_placeholder(): sin esto, una Card recién agregada no
		// tendría ninguna <img> que clickear para cargar la primera imagen.
		$imagen = $this->imagen_o_placeholder( esc_url( $this->props['imagen'] ) );

		$clases = array_filter( array(
			'sofia-card',
			$this->clase_de_variante( self::CLASES_ORIENTACION, 'orientacion' ),
			$this->clase_de_variante( self::CLASES_SUPERFICIE, 'superficie' ),
		) );

		$html = '<section ' . $this->atributos_seccion( implode( ' ', $clases ) ) . '>';
		if ( $imagen ) {
			$html .= '<img class="sofia-card__imagen" ' . $this->atributo_editable( 'imagen' ) . ' src="' . $imagen . '" alt="' . esc_attr( wp_strip_all_tags( $titulo ) ) . '">';
		}
		$html .= '<div class="sofia-card__cuerpo">';
		$html .= '<h3 class="sofia-card__titulo" ' . $this->atributo_editable( 'titulo' ) . ' ' . $this->atributo_estilo( 'titulo' ) . '>' . $titulo . '</h3>';
		$html .= '<p class="sofia-card__texto" ' . $this->atributo_editable( 'texto' ) . ' ' . $this->atributo_estilo( 'texto' ) . '>' . $texto . '</p>';
		$html .= '<a class="sofia-card__boton" href="' . $boton_enlace . '" ' . $this->atributo_editable( 'boton_texto' ) . ' ' . $this->atributo_estilo( 'boton_texto' ) . '>' . $boton_texto . '</a>';
		$html .= '</div>';
		$html .= '</section>';
		return $html;
	}
}

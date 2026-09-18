<?php
/**
 * Stepper — una secuencia de pasos numerados, con uno marcado como actual.
 *
 * NO declara dependencias_js(): no hay nada que un script tenga que
 * hacer. Cuál es el paso actual es contenido, no interacción — lo decide
 * quien arma la página, no un click del visitante. Un Stepper "navegable"
 * (donde clickear un paso cambia el contenido) sería otro Componente, y
 * en la práctica sería Tabs con otra apariencia.
 *
 * Aparece en la Fase 5 junto a los interactivos por parentesco visual,
 * pero es HTML y CSS puros.
 */
class Sofia_Componente_Stepper extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Pasos', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		return array(
			'actual' => '1',
			'items'  => array(
				array( 'titulo' => __( 'Primer paso', 'sofia-studio' ), 'texto' => __( 'Qué pasa en este paso.', 'sofia-studio' ) ),
				array( 'titulo' => __( 'Segundo paso', 'sofia-studio' ), 'texto' => __( 'Qué pasa en este paso.', 'sofia-studio' ) ),
				array( 'titulo' => __( 'Tercer paso', 'sofia-studio' ), 'texto' => __( 'Qué pasa en este paso.', 'sofia-studio' ) ),
			),
		);
	}

	/**
	 * Capa 1 — CONTENIDO. "actual" es un número acotado, no un texto
	 * libre: el deslizador comunica el rango y no deja elegir un paso que
	 * no existe.
	 *
	 * El máximo es fijo en 10 porque el schema es por TIPO de bloque, no
	 * por instancia — no puede depender de cuántos items tenga esta
	 * secuencia puntual. Un valor mayor a la cantidad real simplemente no
	 * marca ningún paso, sin romper nada.
	 */
	public static function schema_contenido(): array {
		return array(
			'actual' => array( 'tipo' => 'numero', 'etiqueta' => __( 'Paso actual', 'sofia-studio' ), 'min' => 1, 'max' => 10 ),
			'items'  => array(
				'tipo'     => 'lista',
				'etiqueta' => __( 'Pasos', 'sofia-studio' ),
				'campos'   => array(
					'titulo' => array( 'tipo' => 'texto', 'etiqueta' => __( 'Título', 'sofia-studio' ) ),
					'texto'  => array( 'tipo' => 'texto', 'etiqueta' => __( 'Descripción', 'sofia-studio' ) ),
				),
			),
		);
	}

	/** Capa 2 — APARIENCIA. */
	public static function schema_propio(): array {
		return array(
			'orientacion' => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Orientación', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => 'horizontal', 'etiqueta' => __( 'Horizontal', 'sofia-studio' ) ),
					array( 'valor' => 'vertical', 'etiqueta' => __( 'Vertical', 'sofia-studio' ) ),
				),
			),
		);
	}

	private const CLASES_ORIENTACION = array(
		'vertical' => 'sofia-stepper--vertical',
	);

	/** Capa 3 — CAJA. */
	public static function claves_estilo_relevantes(): array {
		return parent::PERFIL_SECCION;
	}

	public function render(): string {
		$items  = is_array( $this->props['items'] ?? null ) ? $this->props['items'] : array();
		$actual = $this->valor_acotado( 'actual', 1, 10 );

		$clases = array_filter( array(
			'sofia-stepper',
			$this->clase_de_variante( self::CLASES_ORIENTACION, 'orientacion' ),
		) );

		$html  = '<section ' . $this->atributos_seccion( implode( ' ', $clases ) ) . '>';
		$html .= '<ol class="sofia-stepper__lista" ' . $this->atributo_lista( 'items' ) . '>';
		foreach ( array_values( $items ) as $indice => $item ) {
			$titulo = $this->texto_enriquecido( (string) ( $item['titulo'] ?? '' ) );
			$texto  = $this->texto_enriquecido( (string) ( $item['texto'] ?? '' ) );
			$numero = $indice + 1;

			// Tres estados: los anteriores al actual están hechos, el
			// actual está en curso, los siguientes pendientes.
			$estado = $numero < $actual ? 'hecho' : ( $numero === $actual ? 'actual' : 'pendiente' );

			$html .= '<li class="sofia-stepper__item sofia-stepper__item--' . $estado . '" ' . $this->atributo_item( $indice )
				. ( 'actual' === $estado ? ' aria-current="step"' : '' ) . '>';
			// El número sale del orden, no del contenido: renumerar al
			// reordenar o eliminar un paso tiene que ser automático.
			$html .= '<span class="sofia-stepper__numero" aria-hidden="true">' . $numero . '</span>';
			$html .= '<span class="sofia-stepper__cuerpo">';
			$html .= '<span class="sofia-stepper__titulo" ' . $this->atributo_editable( "items.{$indice}.titulo" ) . ' ' . $this->atributo_estilo( "items.{$indice}.titulo" ) . '>' . $titulo . '</span>';
			$html .= '<span class="sofia-stepper__texto" ' . $this->atributo_editable( "items.{$indice}.texto" ) . '>' . $texto . '</span>';
			$html .= '</span>';
			$html .= '</li>';
		}
		$html .= '</ol>';
		$html .= $this->boton_agregar_item( 'items' );
		$html .= '</section>';
		return $html;
	}
}

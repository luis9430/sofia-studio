<?php
/**
 * Rating — primitiva de Datos/UI (Fase 4, plan "50 primitivas", ver la
 * memoria de producto): 5 estrellas, N llenas según un valor 0-5 como
 * campo de contenido tipo texto (mismo criterio que Progress — selector
 * visual pospuesto). Solo valores enteros — una estrella "a medias" (ej.
 * 4.5) exigiría un gradiente SVG por estrella, sin precedente pedido; se
 * redondea hacia abajo en vez de mentir con una estrella de más.
 *
 * Reusa el ícono "star" de Sofia_Componente_Icon::ICONOS_PERMITIDOS — dos
 * copias del mismo <svg> por estrella (una llena, una vacía), superpuestas
 * con opacidad vía CSS, en vez de duplicar el whitelist de íconos.
 */
class Sofia_Componente_Rating extends Sofia_Componente {

	private const MAXIMO = 5;

	public function nombre(): string {
		return __( 'Calificación', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		return array(
			'valor' => '4',
		);
	}

	public static function schema_contenido(): array {
		return array(
			'valor' => array( 'tipo' => 'numero', 'etiqueta' => __( 'Estrellas', 'sofia-studio' ), 'min' => 0, 'max' => self::MAXIMO ),
		);
	}

	/**
	 * claves_estilo_relevantes(): PERFIL_PRIMITIVA — mismo criterio que
	 * Icon: pieza visual simple sin caja de sección propia.
	 */
	public static function claves_estilo_relevantes(): array {
		return parent::PERFIL_PRIMITIVA;
	}

	public function render(): string {
		$valor = $this->valor_acotado( 'valor', 0, self::MAXIMO );

		$html  = '<section ' . $this->atributos_seccion( 'sofia-rating' ) . '>';
		for ( $i = 1; $i <= self::MAXIMO; $i++ ) {
			$clase = ( $i <= $valor ) ? 'sofia-rating__estrella sofia-rating__estrella--llena' : 'sofia-rating__estrella';
			$html .= $this->svg_icono( 'star', 20, $clase );
		}
		$html .= '</section>';
		return $html;
	}
}

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
			'valor' => array( 'tipo' => 'texto', 'etiqueta' => __( 'Valor (0-5)', 'sofia-studio' ) ),
		);
	}

	/**
	 * claves_estilo_relevantes(): PERFIL_PRIMITIVA — mismo criterio que
	 * Icon: pieza visual simple sin caja de sección propia.
	 */
	public static function claves_estilo_relevantes(): array {
		return parent::PERFIL_PRIMITIVA;
	}

	private function valor_normalizado(): int {
		$valor = (int) ( $this->props['valor'] ?? 0 );
		return max( 0, min( self::MAXIMO, $valor ) );
	}

	public function render(): string {
		$valor = $this->valor_normalizado();
		$path  = Sofia_Componente_Icon::ICONOS_PERMITIDOS['star'];

		$html  = '<section ' . $this->atributos_seccion( 'sofia-rating' ) . '>';
		for ( $i = 1; $i <= self::MAXIMO; $i++ ) {
			$clase = ( $i <= $valor ) ? 'sofia-rating__estrella sofia-rating__estrella--llena' : 'sofia-rating__estrella';
			$html .= '<svg class="' . $clase . '" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
		}
		$html .= '</section>';
		return $html;
	}
}

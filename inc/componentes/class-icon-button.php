<?php
/**
 * IconButton — primitiva de Acciones (Fase 3, plan "50 primitivas", ver la
 * memoria de producto): mismo botón sólido que Sofia_Componente_Button,
 * con un ícono antes del texto — Componente PHP propio (decisión
 * confirmada con el usuario, mismo criterio que Link: cada primitiva su
 * propia entrada en "Agregar bloque", no un campo escondido en Button).
 *
 * Reusa Sofia_Componente_Icon::ICONOS_PERMITIDOS tal cual (mismo whitelist
 * de 24 SVGs curados) — un solo lugar de verdad para qué íconos existen en
 * el sistema, sin duplicar la lista acá.
 */
class Sofia_Componente_Icon_Button extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Botón con ícono', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		return array(
			'texto'  => __( 'Continuar', 'sofia-studio' ),
			'enlace' => '#',
			'icono'  => 'arrow-right',
		);
	}

	public static function schema_contenido(): array {
		return array(
			'texto'  => array( 'tipo' => 'texto', 'etiqueta' => __( 'Texto del botón', 'sofia-studio' ) ),
			'enlace' => array( 'tipo' => 'url', 'etiqueta' => __( 'Enlace', 'sofia-studio' ) ),
			'icono'  => array( 'tipo' => 'icono', 'etiqueta' => __( 'Ícono', 'sofia-studio' ) ),
		);
	}

	/**
	 * claves_estilo_relevantes(): PERFIL_PRIMITIVA — mismo criterio que
	 * Button: pieza visual simple, sin caja de sección propia.
	 */
	public static function claves_estilo_relevantes(): array {
		return parent::PERFIL_PRIMITIVA;
	}

	/**
	 * render(): mismo criterio de whitelist estricta que Icon — un nombre
	 * de ícono no reconocido cae a "arrow-right" (el default) en vez de
	 * imprimir un botón sin ícono visible.
	 */
	public function render(): string {
		$texto  = $this->texto_enriquecido( $this->props['texto'] );
		$enlace = esc_url( $this->props['enlace'] );
		$icono  = (string) ( $this->props['icono'] ?? '' );

		$html  = '<section ' . $this->atributos_seccion( 'sofia-icon-button' ) . '>';
		$html .= '<a class="sofia-icon-button__enlace" href="' . $enlace . '" ' . $this->atributo_editable( 'texto' ) . ' ' . $this->atributo_estilo( 'texto' ) . '>';
		$html .= $this->svg_icono( $icono, 20, 'sofia-icon-button__svg', 'arrow-right' );
		$html .= '<span>' . $texto . '</span>';
		$html .= '</a>';
		$html .= '</section>';
		return $html;
	}
}

<?php
/**
 * Icon — primitiva de Contenido (Fase 2, plan "50 primitivas", ver la
 * memoria de producto): un ícono SVG inline de un set curado propio,
 * whitelist estricta (nunca un SVG arbitrario subido por el usuario o un
 * <link> a una librería externa — mismo criterio de seguridad/consistencia
 * que FUENTES_PERMITIDAS/CLASES_UTILITARIAS_BLOQUE en la clase base).
 *
 * Set inicial: 24 íconos de Tabler Icons (MIT, outline, tabler.io/icons —
 * el usuario los usa habitualmente), elegidos por uso genérico (flechas,
 * check/cerrar, navegación, contacto, redes sociales básicas). Cada
 * entrada de ICONOS_PERMITIDOS es el contenido INTERNO real del SVG
 * (uno o más <path>, tal cual vienen de Tabler) — nunca el archivo
 * completo, el wrapper <svg> con sus atributos lo arma render() una sola
 * vez. stroke="currentColor" en el wrapper (no en cada <path>): el ícono
 * hereda el color de texto del elemento que lo contiene automáticamente,
 * sin necesitar re-colorear cada path a mano — mismo comportamiento nativo
 * de Tabler Icons.
 */
class Sofia_Componente_Icon extends Sofia_Componente {


	public function nombre(): string {
		return __( 'Ícono', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		return array(
			'icono' => 'star',
		);
	}

	/**
	 * schema_contenido(): "icono" es tipo "texto" para el generador de IA
	 * (whitelist de nombres válidos — el LLM recibe la lista real vía
	 * "opciones" en el schema de estilo del drawer, no acá; este schema
	 * solo describe la FORMA del campo, la validación real de qué nombres
	 * son válidos la hace Sofia_REST_Editor::reparar_nodos_arbol_ia() al
	 * ignorar cualquier prop con clave desconocida — "icono" siempre es
	 * una clave conocida, pero un VALOR de nombre inválido simplemente no
	 * matchea ninguna entrada de ICONOS_PERMITIDOS en render(), sin romper
	 * nada, ver el comentario ahí).
	 */
	public static function schema_contenido(): array {
		return array(
			'icono' => array( 'tipo' => 'icono', 'etiqueta' => __( 'Ícono', 'sofia-studio' ) ),
		);
	}

	/**
	 * claves_estilo_relevantes(): PERFIL_PRIMITIVA — un ícono es una pieza
	 * visual simple sin caja de sección propia (mismo criterio que Image/
	 * Button), solo posición/tamaño en la página. El TAMAÑO/color reales
	 * del ícono en sí no pasan por Nivel 2 (no hay una prop "color_borde"
	 * pensada para esto) — hereda color de texto (currentColor) y tamaño
	 * de fuente (1em, ver style.css) del contexto donde vive, mismo
	 * criterio que un ícono real de cualquier sistema de diseño.
	 */
	public static function claves_estilo_relevantes(): array {
		return parent::PERFIL_PRIMITIVA;
	}

	public function render(): string {
		$html  = '<section ' . $this->atributos_seccion( 'sofia-icon' ) . '>';
		$html .= $this->svg_icono( (string) ( $this->props['icono'] ?? '' ), 24, 'sofia-icon__svg' );
		$html .= '</section>';
		return $html;
	}
}

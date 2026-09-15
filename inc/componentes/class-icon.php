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

	/**
	 * ICONOS_PERMITIDOS: whitelist completa — clave = nombre guardado en
	 * "icono" (mismo nombre de archivo que Tabler usa, sin extensión),
	 * valor = contenido interno del SVG (ya sin el wrapper <svg>, listo
	 * para interpolar en render()). Un valor no presente acá se ignora en
	 * silencio (mismo criterio que cualquier otra whitelist del sistema,
	 * ej. FUENTES_PERMITIDAS) — nunca se imprime un <path> arbitrario que
	 * no haya sido revisado y agregado acá a mano.
	 */
	private const ICONOS_PERMITIDOS = array(
		'arrow-right'      => '<path d="M5 12l14 0" /><path d="M13 18l6 -6" /><path d="M13 6l6 6" />',
		'arrow-left'       => '<path d="M5 12l14 0" /><path d="M5 12l6 6" /><path d="M5 12l6 -6" />',
		'arrow-up'         => '<path d="M12 5l0 14" /><path d="M18 11l-6 -6" /><path d="M6 11l6 -6" />',
		'arrow-down'       => '<path d="M12 5l0 14" /><path d="M18 13l-6 6" /><path d="M6 13l6 6" />',
		'check'            => '<path d="M5 12l5 5l10 -10" />',
		'x'                => '<path d="M18 6l-12 12" /><path d="M6 6l12 12" />',
		'menu-2'           => '<path d="M4 6l16 0" /><path d="M4 12l16 0" /><path d="M4 18l16 0" />',
		'plus'             => '<path d="M12 5l0 14" /><path d="M5 12l14 0" />',
		'minus'            => '<path d="M5 12l14 0" />',
		'star'             => '<path d="M12 17.75l-6.172 3.245l1.179 -6.873l-5 -4.867l6.9 -1l3.086 -6.253l3.086 6.253l6.9 1l-5 4.867l1.179 6.873l-6.158 -3.245" />',
		'heart'            => '<path d="M19.5 12.572l-7.5 7.428l-7.5 -7.428a5 5 0 1 1 7.5 -6.566a5 5 0 1 1 7.5 6.572" />',
		'mail'             => '<path d="M3 7a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v10a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-10" /><path d="M3 7l9 6l9 -6" />',
		'phone'            => '<path d="M5 4h4l2 5l-2.5 1.5a11 11 0 0 0 5 5l1.5 -2.5l5 2v4a2 2 0 0 1 -2 2a16 16 0 0 1 -15 -15a2 2 0 0 1 2 -2" />',
		'map-pin'          => '<path d="M9 11a3 3 0 1 0 6 0a3 3 0 0 0 -6 0" /><path d="M17.657 16.657l-4.243 4.243a2 2 0 0 1 -2.827 0l-4.244 -4.243a8 8 0 1 1 11.314 0" />',
		'clock'            => '<path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0" /><path d="M12 7v5l3 3" />',
		'calendar'         => '<path d="M4 7a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-12" /><path d="M16 3v4" /><path d="M8 3v4" /><path d="M4 11h16" /><path d="M11 15h1" /><path d="M12 15v3" />',
		'download'         => '<path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2" /><path d="M7 11l5 5l5 -5" /><path d="M12 4l0 12" />',
		'upload'           => '<path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2" /><path d="M7 9l5 -5l5 5" /><path d="M12 4l0 12" />',
		'external-link'    => '<path d="M12 6h-6a2 2 0 0 0 -2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-6" /><path d="M11 13l9 -9" /><path d="M15 4h5v5" />',
		'chevron-right'    => '<path d="M9 6l6 6l-6 6" />',
		'chevron-down'     => '<path d="M6 9l6 6l6 -6" />',
		'brand-facebook'   => '<path d="M7 10v4h3v7h4v-7h3l1 -4h-4v-2a1 1 0 0 1 1 -1h3v-4h-3a5 5 0 0 0 -5 5v2h-3" />',
		'brand-instagram'  => '<path d="M4 8a4 4 0 0 1 4 -4h8a4 4 0 0 1 4 4v8a4 4 0 0 1 -4 4h-8a4 4 0 0 1 -4 -4l0 -8" /><path d="M9 12a3 3 0 1 0 6 0a3 3 0 0 0 -6 0" /><path d="M16.5 7.5v.01" />',
		'brand-whatsapp'   => '<path d="M3 21l1.65 -3.8a9 9 0 1 1 3.4 2.9l-5.05 .9" /><path d="M9 10a.5 .5 0 0 0 1 0v-1a.5 .5 0 0 0 -1 0v1a5 5 0 0 0 5 5h1a.5 .5 0 0 0 0 -1h-1a.5 .5 0 0 0 0 1" />',
		'send'             => '<path d="M10 14l11 -11" /><path d="M21 3l-6.5 18a.55 .55 0 0 1 -1 0l-3.5 -7l-7 -3.5a.55 .55 0 0 1 0 -1l18 -6.5" />',
	);

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
			'icono' => array( 'tipo' => 'texto', 'etiqueta' => __( 'Ícono', 'sofia-studio' ) ),
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

	/**
	 * render(): whitelist estricta — un nombre de ícono no reconocido
	 * (dato corrupto, o una versión más nueva del set que un tema viejo no
	 * tiene) cae a "star" en vez de imprimir un <svg> vacío sin ningún
	 * contenido visible, mismo criterio de "fail closed mostrando algo
	 * razonable" que imagen_o_placeholder() ya usa para Image/Hero.
	 * "aria-hidden" — un ícono decorativo sin texto propio no debe
	 * anunciarse a lectores de pantalla como contenido significativo,
	 * mismo criterio que cualquier ícono puramente visual.
	 */
	public function render(): string {
		$icono   = (string) ( $this->props['icono'] ?? '' );
		$path    = self::ICONOS_PERMITIDOS[ $icono ] ?? self::ICONOS_PERMITIDOS['star'];

		$html  = '<section ' . $this->atributos_seccion( 'sofia-icon' ) . '>';
		$html .= '<svg class="sofia-icon__svg" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">';
		$html .= $path; // $path es SIEMPRE una entrada de ICONOS_PERMITIDOS (whitelist), nunca contenido del usuario — seguro de imprimir directo.
		$html .= '</svg>';
		$html .= '</section>';
		return $html;
	}
}

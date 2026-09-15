<?php
/**
 * Embed — primitiva de Contenido (Fase 2, plan "50 primitivas", ver la
 * memoria de producto): contenido de TERCEROS incrustado vía <iframe>
 * (YouTube/Vimeo) — distinto de Video (archivo propio vía Media Library,
 * sin iframe, ver class-video.php).
 *
 * Whitelist de dominio (nunca un iframe hacia una URL arbitraria — mismo
 * criterio de seguridad que ICONOS_PERMITIDOS/FUENTES_PERMITIDAS en el
 * resto del catálogo, acá aplicado al ORIGEN del iframe en vez de a un
 * valor de contenido): solo se arma el iframe si la URL guardada
 * pertenece a uno de los dominios de embed conocidos de YouTube/Vimeo.
 * Cualquier otra URL (corrupta, o un dominio no soportado) no renderiza
 * ningún iframe — fail closed, mismo criterio que el resto del sistema.
 */
class Sofia_Componente_Embed extends Sofia_Componente {

	/**
	 * DOMINIOS_PERMITIDOS: whitelist de hosts de embed reales — no
	 * "youtube.com" (la URL de ver un video ahí, no de incrustarlo), sino
	 * el host que YouTube/Vimeo usan específicamente para <iframe src>.
	 */
	private const DOMINIOS_PERMITIDOS = array(
		'www.youtube.com',
		'youtube.com',
		'player.vimeo.com',
	);

	public function nombre(): string {
		return __( 'Embed (YouTube/Vimeo)', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		return array(
			'url' => '',
		);
	}

	public static function schema_contenido(): array {
		return array(
			'url' => array( 'tipo' => 'url', 'etiqueta' => __( 'URL de embed (YouTube/Vimeo)', 'sofia-studio' ) ),
		);
	}

	/**
	 * claves_estilo_relevantes(): PERFIL_IMAGEN — mismo criterio que Video:
	 * aspect_ratio/object_fit tienen efecto real sobre el <iframe>, sin
	 * fondo/borde/sombra de "caja de sección" propia.
	 */
	public static function claves_estilo_relevantes(): array {
		return parent::PERFIL_IMAGEN;
	}

	/**
	 * url_es_de_dominio_permitido(): valida el HOST real de la URL contra
	 * DOMINIOS_PERMITIDOS — nunca un match de substring sobre la URL
	 * completa (eso permitiría burlar la whitelist con algo como
	 * "evil.com/?u=youtube.com"), siempre parse_url() + comparación exacta
	 * del host.
	 */
	private function url_es_de_dominio_permitido( string $url ): bool {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		return is_string( $host ) && in_array( $host, self::DOMINIOS_PERMITIDOS, true );
	}

	public function render(): string {
		$url = esc_url( (string) ( $this->props['url'] ?? '' ) );

		$html = '<section ' . $this->atributos_seccion( 'sofia-embed' ) . '>';
		if ( $url && $this->url_es_de_dominio_permitido( $url ) ) {
			$html .= '<iframe class="sofia-embed__iframe" src="' . $url . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe>';
		} elseif ( class_exists( 'Sofia_Modo_Editor' ) && Sofia_Modo_Editor::activo() ) {
			// Mismo límite ya conocido y documentado en Button/CTA para su
			// campo "enlace" (tipo 'url'): no hay hoy edición humana desde
			// el editor visual para este tipo de campo, solo el generador
			// de IA lo completa vía schema_contenido(). El placeholder acá
			// es informativo, no clickeable — no hay ningún mecanismo de
			// edición in-place para un campo de URL en el sistema actual.
			$html .= '<div class="sofia-embed__placeholder">' . esc_html__( 'Sin URL configurada — este bloque se completa desde el generador por IA', 'sofia-studio' ) . '</div>';
		}
		$html .= '</section>';
		return $html;
	}
}

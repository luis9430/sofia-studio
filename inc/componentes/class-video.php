<?php
/**
 * Video — primitiva de Contenido (Fase 2, plan "50 primitivas", ver la
 * memoria de producto): un <video> con controles nativos, fuente propia
 * (archivo de video vía Media Library, mismo mecanismo que Image usa para
 * imágenes) — no YouTube/Vimeo embebido, eso es Embed (otro Componente de
 * esta misma fase, para contenido de terceros vía iframe).
 */
class Sofia_Componente_Video extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Video', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		return array(
			'video'  => '',
			'poster' => '',
		);
	}

	/**
	 * schema_contenido(): "video" tipo "imagen" — mismo mecanismo de
	 * selector de Media Library que ya usa cualquier campo de imagen del
	 * catálogo (wp.media() en editor-iframe.js no distingue tipo de
	 * archivo, la librería de medios de WordPress ya filtra por tipo MIME
	 * real cuando hace falta) — no existe un tipo "video" dedicado en
	 * schema_contenido() hoy, y no hace falta uno nuevo: el generador de
	 * IA nunca inventa una URL real para un campo de medios (ver la regla
	 * "dejalo como string vacío" del prompt), así que "imagen" alcanza
	 * como tipo de campo para cualquier archivo de Media Library, video
	 * incluido.
	 */
	public static function schema_contenido(): array {
		return array(
			'video'  => array( 'tipo' => 'imagen', 'etiqueta' => __( 'Archivo de video', 'sofia-studio' ) ),
			'poster' => array( 'tipo' => 'imagen', 'etiqueta' => __( 'Imagen de portada (poster)', 'sofia-studio' ) ),
		);
	}

	/**
	 * claves_estilo_relevantes(): PERFIL_IMAGEN — mismo perfil que Image/
	 * AspectRatio: aspect_ratio/object_fit tienen efecto real acá (hay un
	 * <video> real que recortar/ajustar), sin fondo/borde/sombra/z-index
	 * de "caja de sección" (un Video no es una sección compuesta, es el
	 * video mismo).
	 */
	public static function claves_estilo_relevantes(): array {
		return parent::PERFIL_IMAGEN;
	}

	/**
	 * render(): sin video configurado, en modo editor SÍ hace falta algo
	 * clickeable para poder agregarlo la primera vez — reusa
	 * imagen_o_placeholder() (clase base) para el campo "video" tal cual,
	 * aunque semánticamente sea un archivo de video y no una imagen: el
	 * mecanismo de click de editor-iframe.js decide qué activar mirando
	 * el TAG del elemento (activarImagen() para cualquier <img>, sin
	 * importar qué tipo de archivo termine eligiendo wp.media() — ver
	 * activarCamposEditables()), así que un placeholder <img> con
	 * data-sofia-campo="video" ya engancha el selector de Media Library
	 * gratis, sin necesitar JS nuevo para un caso "click para elegir
	 * video" dedicado. Una vez que el usuario elige el archivo real, esa
	 * URL se usa directo como <source> del <video> — el placeholder <img>
	 * deja de renderizarse por completo.
	 */
	public function render(): string {
		$video  = esc_url( (string) ( $this->props['video'] ?? '' ) );
		$poster = esc_url( (string) ( $this->props['poster'] ?? '' ) );

		$html = '<section ' . $this->atributos_seccion( 'sofia-video' ) . '>';
		if ( $video ) {
			$html .= '<video class="sofia-video__reproductor" controls' . ( $poster ? ' poster="' . $poster . '"' : '' ) . '>';
			$html .= '<source src="' . $video . '">';
			$html .= '</video>';
		} else {
			$placeholder = $this->imagen_o_placeholder( '' );
			if ( $placeholder ) {
				$html .= '<img ' . $this->atributo_editable( 'video' ) . ' src="' . $placeholder . '" alt="">';
			}
		}
		$html .= '</section>';
		return $html;
	}
}

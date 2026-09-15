<?php
/**
 * Avatar — primitiva de Contenido (Fase 2, plan "50 primitivas", ver la
 * memoria de producto): imagen circular chica (foto de persona/testimonio),
 * con INICIALES como respaldo cuando no hay imagen — a diferencia de
 * Image/Video, un visitante real SÍ debe ver algo con sentido aunque nunca
 * se haya cargado ninguna foto (un círculo vacío o el placeholder de "click
 * para elegir imagen" filtrándose a producción se ve roto; unas iniciales
 * son un resultado válido y esperado de un avatar sin foto, mismo patrón
 * que cualquier sistema de diseño real — Slack, GitHub, etc.).
 */
class Sofia_Componente_Avatar extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Avatar', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		return array(
			'imagen'   => '',
			'nombre'   => __( 'Nombre Apellido', 'sofia-studio' ),
		);
	}

	/**
	 * schema_contenido(): "nombre" además de alimentar las iniciales de
	 * respaldo, es el "alt" real de la imagen cuando sí hay foto — un
	 * Avatar sin nombre asociado no tiene forma de generar iniciales
	 * razonables, así que este campo es obligatorio en la práctica (el
	 * generador de IA siempre lo completa, ver el prompt de composición).
	 */
	public static function schema_contenido(): array {
		return array(
			'imagen' => array( 'tipo' => 'imagen', 'etiqueta' => __( 'Foto', 'sofia-studio' ) ),
			'nombre' => array( 'tipo' => 'texto', 'etiqueta' => __( 'Nombre (para iniciales de respaldo)', 'sofia-studio' ) ),
		);
	}

	/**
	 * claves_estilo_relevantes(): PERFIL_PRIMITIVA — mismo criterio que
	 * Icon: una pieza chica sin caja de sección propia, solo
	 * posición/tamaño. El tamaño real del círculo (ancho/alto) se resuelve
	 * en CSS con un valor fijo (ver .sofia-avatar en style.css) en vez de
	 * un control propio — no hay hoy una prop pensada para "tamaño de
	 * avatar" en el vocabulario de schema, mismo límite ya documentado en
	 * Button para "enlace no editable desde Nivel 1/2".
	 */
	public static function claves_estilo_relevantes(): array {
		return parent::PERFIL_PRIMITIVA;
	}

	/**
	 * iniciales(): primera letra del primer y último "token" del nombre
	 * (ej. "Ana García Pérez" → "AG") — nunca más de 2 caracteres, mismo
	 * criterio visual que cualquier avatar de iniciales real. Un nombre
	 * vacío/de un solo token cae a como máximo 1 letra, nunca rompe.
	 */
	private function iniciales( string $nombre ): string {
		$partes = preg_split( '/\s+/', trim( $nombre ), -1, PREG_SPLIT_NO_EMPTY );
		if ( empty( $partes ) ) {
			return '';
		}
		$primera = mb_substr( $partes[0], 0, 1 );
		if ( count( $partes ) === 1 ) {
			return mb_strtoupper( $primera );
		}
		$ultima = mb_substr( $partes[ count( $partes ) - 1 ], 0, 1 );
		return mb_strtoupper( $primera . $ultima );
	}

	/**
	 * render(): 3 casos reales — (1) hay imagen guardada: <img> circular
	 * normal; (2) sin imagen pero EN MODO EDITOR: placeholder clickeable
	 * de imagen_o_placeholder() (mismo mecanismo de Video/Image, para que
	 * se pueda cargar una foto la primera vez); (3) sin imagen y NO en
	 * editor (visitante real): iniciales — nunca el placeholder de "click
	 * para elegir imagen" llega a producción.
	 */
	public function render(): string {
		$imagen = esc_url( (string) ( $this->props['imagen'] ?? '' ) );
		$nombre = (string) ( $this->props['nombre'] ?? '' );
		$en_editor = class_exists( 'Sofia_Modo_Editor' ) && Sofia_Modo_Editor::activo();

		$html = '<section ' . $this->atributos_seccion( 'sofia-avatar' ) . '>';
		if ( $imagen || $en_editor ) {
			$src = $this->imagen_o_placeholder( $imagen );
			$html .= '<img class="sofia-avatar__imagen" ' . $this->atributo_editable( 'imagen' ) . ' src="' . $src . '" alt="' . esc_attr( $nombre ) . '">';
		} else {
			$html .= '<span class="sofia-avatar__iniciales" aria-label="' . esc_attr( $nombre ) . '">' . esc_html( $this->iniciales( $nombre ) ) . '</span>';
		}
		$html .= '</section>';
		return $html;
	}
}

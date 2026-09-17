<?php
/**
 * Modal — una ventana que se abre con un botón.
 *
 * Usa <dialog> nativo, que ya trae lo que una implementación a mano suele
 * olvidar: cierre con Escape, foco atrapado adentro mientras está abierto,
 * y el resto de la página inerte. El JS compartido solo lo abre, porque a
 * diferencia de <details> no hay forma declarativa de hacerlo.
 *
 * EN EL EDITOR el contenido NO va dentro del <dialog>: un dialog cerrado
 * es invisible e inalcanzable, así que su texto no se podría editar nunca.
 * Ahí se renderiza como una caja normal, marcada para que se entienda que
 * en el sitio aparece solo al abrirla — ver render().
 */
class Sofia_Componente_Modal extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Modal', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		return array(
			'disparador' => __( 'Abrir', 'sofia-studio' ),
			'titulo'     => __( 'Título del modal', 'sofia-studio' ),
			'texto'      => __( 'Contenido de la ventana.', 'sofia-studio' ),
			'cerrar'     => __( 'Cerrar', 'sofia-studio' ),
		);
	}

	/** Capa 1 — CONTENIDO. */
	public static function schema_contenido(): array {
		return array(
			'disparador' => array( 'tipo' => 'texto', 'etiqueta' => __( 'Texto del botón que abre', 'sofia-studio' ) ),
			'titulo'     => array( 'tipo' => 'texto', 'etiqueta' => __( 'Título', 'sofia-studio' ) ),
			'texto'      => array( 'tipo' => 'texto_largo', 'etiqueta' => __( 'Contenido', 'sofia-studio' ) ),
			'cerrar'     => array( 'tipo' => 'texto', 'etiqueta' => __( 'Texto del botón que cierra', 'sofia-studio' ) ),
		);
	}

	/** Capa 2 — APARIENCIA. */
	public static function schema_propio(): array {
		return array(
			'tamano' => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Tamaño', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => 'base', 'etiqueta' => __( 'Base', 'sofia-studio' ) ),
					array( 'valor' => 'chico', 'etiqueta' => __( 'Chico', 'sofia-studio' ) ),
					array( 'valor' => 'grande', 'etiqueta' => __( 'Grande', 'sofia-studio' ) ),
				),
			),
		);
	}

	private const CLASES_TAMANO = array(
		'chico'  => 'sofia-modal__ventana--chica',
		'grande' => 'sofia-modal__ventana--grande',
	);

	/** Capa 3 — CAJA: el disparador es una pieza simple. */
	public static function claves_estilo_relevantes(): array {
		return parent::PERFIL_PRIMITIVA;
	}

	public function dependencias_js(): array {
		return array( 'interacciones' );
	}

	public function render(): string {
		$disparador = $this->texto_enriquecido( (string) ( $this->props['disparador'] ?? '' ) );
		$titulo     = $this->texto_enriquecido( (string) ( $this->props['titulo'] ?? '' ) );
		$texto      = $this->texto_enriquecido( (string) ( $this->props['texto'] ?? '' ) );
		$cerrar     = $this->texto_enriquecido( (string) ( $this->props['cerrar'] ?? '' ) );
		$en_editor  = class_exists( 'Sofia_Modo_Editor' ) && Sofia_Modo_Editor::activo();

		$id_dialog = $this->id . '-dialog';
		$clase_ventana = trim( 'sofia-modal__ventana ' . $this->clase_de_variante( self::CLASES_TAMANO, 'tamano' ) );

		$html  = '<section ' . $this->atributos_seccion( 'sofia-modal' ) . '>';
		$html .= '<button type="button" class="sofia-modal__disparador" data-sofia-abre-modal="' . esc_attr( $id_dialog ) . '" '
			. $this->atributo_editable( 'disparador' ) . ' ' . $this->atributo_estilo( 'disparador' ) . '>' . $disparador . '</button>';

		// El contenido: dentro de un <dialog> en el sitio, en una caja
		// abierta en el editor. Mismo HTML interno en los dos casos, para
		// que lo que se edita sea lo que se publica.
		$interior  = '<h3 class="sofia-modal__titulo" ' . $this->atributo_editable( 'titulo' ) . ' ' . $this->atributo_estilo( 'titulo' ) . '>' . $titulo . '</h3>';
		$interior .= '<div class="sofia-modal__texto" ' . $this->atributo_editable( 'texto' ) . ' ' . $this->atributo_estilo( 'texto' ) . '>' . $texto . '</div>';
		$interior .= '<button type="button" class="sofia-modal__cerrar" data-sofia-cierra-modal ' . $this->atributo_editable( 'cerrar' ) . '>' . $cerrar . '</button>';

		if ( $en_editor ) {
			$html .= '<div class="' . esc_attr( $clase_ventana ) . ' sofia-modal__ventana--editor" data-sofia-nota="' . esc_attr__( 'En el sitio se abre al hacer click', 'sofia-studio' ) . '">' . $interior . '</div>';
		} else {
			$html .= '<dialog class="' . esc_attr( $clase_ventana ) . '" id="' . esc_attr( $id_dialog ) . '">' . $interior . '</dialog>';
		}

		$html .= '</section>';
		return $html;
	}
}

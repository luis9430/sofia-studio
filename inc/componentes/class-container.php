<?php
/**
 * Sofia_Componente_Container — primitiva de layout (ver la memoria de
 * producto "Sofia Studio: plan de primitivas de layout"), PRIMER
 * Componente del catálogo que puede contener OTROS Componentes como
 * hijos, en vez de solo campos de texto/imagen propios.
 *
 * Fase 1 (este archivo): valida el MECANISMO de anidamiento en sí — un
 * <div> que renderiza sus hijos, ya resueltos por Sofia_Pagina
 * (Sofia_Componente::$hijos, ver el comentario largo ahí sobre por qué
 * llegan YA instanciados y no como array crudo). Deliberadamente SIN
 * ningún control de layout propio todavía (dirección, gap, grilla,
 * alineación) — eso es Fase 2 (schema tipado de campos), que se agrega
 * sobre esta base sin romper nada de lo que ya funciona acá: fondo, borde,
 * radio de borde, sombra, ancho, utility classes de Core Framework, TODO
 * eso ya funciona gratis vía atributos_seccion()/Nivel 2, heredado tal
 * cual de cualquier otro Componente del catálogo.
 *
 * Deliberadamente NO está en Sofia_Componente_Factory::TIPOS_REGISTRADOS
 * — no aparece todavía en el catálogo "+ Agregar bloque" del editor
 * visual (eso es Fase 3, cuando el drag-and-drop para anidar bloques
 * exista). Por ahora solo se puede instanciar armando el JSON de
 * Estructura a mano (vía la API de plantillas/páginas de GoPress), que es
 * exactamente el alcance declarado de Fase 1.
 */
class Sofia_Componente_Container extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Container', 'sofia-studio' );
	}

	public function render(): string {
		$html = '<div ' . $this->atributos_seccion( 'sofia-container' ) . '>';
		foreach ( $this->hijos as $hijo ) {
			$html .= $hijo->render();
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * schema_propio(): controles de layout que solo Container tiene —
	 * ninguno de estos afecta el HTML de render() todavía (eso queda
	 * fuera de esta Fase 2, ver el comentario largo arriba de la clase);
	 * esto valida el MECANISMO de schema propio fusionado con el
	 * genérico, mismo alcance deliberado que Fase 1 validó solo el
	 * mecanismo de anidamiento. Adaptado de la tabla "Props reales de
	 * Container" del plan de arquitectura (memoria de producto).
	 */
	public static function schema_propio(): array {
		return array(
			'display'    => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Layout interno', 'sofia-studio' ),
				'ayuda'    => __( 'Decide qué controles de abajo tienen efecto — "Fila"/"Columna" habilitan Dirección/Alinear/Justificar, "Grilla" habilita Columnas de grilla.', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'Bloque (por defecto)', 'sofia-studio' ) ),
					array( 'valor' => 'fila', 'etiqueta' => __( 'Fila', 'sofia-studio' ) ),
					array( 'valor' => 'columna', 'etiqueta' => __( 'Columna', 'sofia-studio' ) ),
					array( 'valor' => 'grilla', 'etiqueta' => __( 'Grilla', 'sofia-studio' ) ),
					array( 'valor' => 'ninguno', 'etiqueta' => __( 'Ninguno (solo espacio)', 'sofia-studio' ) ),
				),
			),
			'direccion'  => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Dirección', 'sofia-studio' ),
				'ayuda'    => __( 'Solo con Layout interno = Fila o Columna.', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'Normal', 'sofia-studio' ) ),
					array( 'valor' => 'invertida', 'etiqueta' => __( 'Invertida', 'sofia-studio' ) ),
				),
			),
			'envolver'   => array(
				'tipo'     => 'toggle',
				'etiqueta' => __( 'Envolver (flex-wrap)', 'sofia-studio' ),
				'ayuda'    => __( 'Solo con Layout interno = Fila o Columna.', 'sofia-studio' ),
			),
			'justificar' => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Justificar', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'Por defecto', 'sofia-studio' ) ),
					array( 'valor' => 'start', 'etiqueta' => __( 'Inicio', 'sofia-studio' ) ),
					array( 'valor' => 'center', 'etiqueta' => __( 'Centro', 'sofia-studio' ) ),
					array( 'valor' => 'end', 'etiqueta' => __( 'Fin', 'sofia-studio' ) ),
					array( 'valor' => 'space-between', 'etiqueta' => __( 'Espacio entre', 'sofia-studio' ) ),
				),
			),
			'alinear'    => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Alinear', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'Por defecto', 'sofia-studio' ) ),
					array( 'valor' => 'start', 'etiqueta' => __( 'Inicio', 'sofia-studio' ) ),
					array( 'valor' => 'center', 'etiqueta' => __( 'Centro', 'sofia-studio' ) ),
					array( 'valor' => 'end', 'etiqueta' => __( 'Fin', 'sofia-studio' ) ),
				),
			),
			'columnas_grilla' => array(
				'tipo'     => 'botones_numero',
				'etiqueta' => __( 'Columnas de grilla', 'sofia-studio' ),
				'ayuda'    => __( 'Solo con Layout interno = Grilla.', 'sofia-studio' ),
				'opciones' => array( '2', '3', '4' ),
			),
			'gap'        => array(
				'tipo'       => 'medida_token',
				'etiqueta'   => __( 'Espacio entre hijos (gap)', 'sofia-studio' ),
				'categoria'  => 'space',
				'con_unidad' => true,
			),
		);
	}
}

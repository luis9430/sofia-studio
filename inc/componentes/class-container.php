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
 * Fase 3 (editor visual — ver Sofia_Componente_Factory::TIPOS_REGISTRADOS,
 * editor-iframe.js y App.jsx) agregó el mecanismo real de anidamiento
 * manual en el editor: instancias Muuri acotadas a cada
 * ".sofia-container" (activarReordenarDentroDeContainers), líneas "+" de
 * inserción también dentro de un container, e inserción/eliminación en
 * profundidad del lado de App.jsx. Este archivo (render()) no necesitó
 * ningún cambio para eso — ya renderizaba sus hijos correctamente desde
 * Fase 1, el trabajo de Fase 3 fue enteramente en cómo el editor visual
 * arma/lee el árbol {id,tipo,hijos}, salvo por UN detalle: cuando un
 * Container se inserta a NIVEL SUPERIOR (no dentro de otro container), ver
 * el comentario sobre el wrapper <section> más abajo en render().
 */
class Sofia_Componente_Container extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Container', 'sofia-studio' );
	}

	/**
	 * render(): DOS elementos anidados, no uno — decisión de Fase 3 para
	 * resolver un choque real entre dos invariantes que ya existían antes
	 * de esta fase y que no se podían romper:
	 *
	 * 1. atributos_seccion()/data-sofia-bloque-id/-tipo SIEMPRE van en el
	 *    elemento que editor-iframe.js trata como "la sección completa
	 *    de este bloque" — todo el JS (mouseover, contextmenu, Muuri)
	 *    busca esto con closest("section")/[data-sofia-bloque-id], nunca
	 *    con closest("div.sofia-container").
	 * 2. gridNivelSuperior (Muuri de nivel superior) usa items:"section"
	 *    aplicado con matches() a cada HIJO DIRECTO de .sofia-pagina (ver
	 *    el comentario largo en activarReordenar(), editor-iframe.js) —
	 *    un <div class="sofia-container"> ahí NUNCA matchea, Muuri lo
	 *    ignoraría por completo como ítem reordenable/insertable.
	 *
	 * Todo Componente del catálogo (Hero, CTA, etc.) resuelve esto
	 * gratis porque su ÚNICO elemento raíz ya es un <section> con
	 * atributos_seccion() encima — Container es el primero cuya raíz
	 * "natural" es un <div> (necesita seguir siendo <div> para que
	 * display:flex/grid + gap de Fase 2 tengan un contenedor real sin
	 * pelearse con el position:absolute que el editor le impone a la
	 * <section>, ver class-modo-editor.php). La solución más chica que
	 * no exige tocar ni el editor ni el CSS existente: envolver ese
	 * <div class="sofia-container"> (SIN atributos_seccion(), sin id/tipo)
	 * dentro de un <section> exterior que SÍ los lleva — así:
	 *   - A nivel superior: la <section> exterior es el ítem de
	 *     gridNivelSuperior, indistinguible de un Hero/CTA para ese grid.
	 *   - Dentro de OTRO Container: la <section> exterior es el hijo
	 *     directo que activarReordenarDentroDeContainers() espera (mismo
	 *     "items:section" aplicado a hijos de .sofia-container) — un
	 *     Container anidado dentro de otro Container sigue calzando con
	 *     el mismo criterio "los hijos son <section> completas" que
	 *     cualquier otro Componente.
	 *   - detección de anidamiento (leerBloquesDeNivelSuperior recursiva)
	 *     sigue reconociendo a ESTE bloque como container buscando
	 *     ":scope > .sofia-container" DENTRO de la <section>, en vez de
	 *     asumir que la propia <section> tiene la clase — ver el
	 *     comentario largo en editor-iframe.js sobre por qué.
	 *
	 * El div.sofia-container interno conserva su propio class="sofia-container"
	 * (sin los data-sofia-bloque-*, que ahora viven en la <section> de
	 * afuera) — el CSS de Fase 2 (display/direccion/gap, cuando se
	 * implemente su efecto visual) sigue pudiendo targetear
	 * ".sofia-container" tal cual, sin saber ni importarle que ahora está
	 * un nivel más adentro.
	 */
	public function render(): string {
		$html  = '<section ' . $this->atributos_seccion( 'sofia-container-exterior' ) . '>';
		$html .= '<div class="sofia-container">';
		foreach ( $this->hijos as $hijo ) {
			$html .= $hijo->render();
		}
		$html .= '</div>';
		$html .= '</section>';
		return $html;
	}

	/**
	 * Sin override de schema_contenido() (Fase 4 — generador de árboles por
	 * IA, ver Sofia_Componente::schema_contenido()) — Container no tiene
	 * NINGÚN campo de contenido propio, su "contenido" es enteramente su
	 * árbol de $hijos, ya cubierto por el modelo recursivo {id,tipo,hijos}
	 * (ver BloqueEstructuraPlantilla del lado GoPress) — el LLM llena el
	 * contenido de cada HIJO por separado, no de este nodo. El default
	 * vacío heredado de la clase base es exactamente lo correcto acá, así
	 * que no hace falta el override — mismo criterio documentado en el
	 * plan de la Fase 4.
	 */

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

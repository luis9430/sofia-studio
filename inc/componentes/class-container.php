<?php
/**
 * Sofia_Componente_Container — primitiva de layout (ver la memoria de
 * producto), PRIMER Componente del catálogo que puede contener OTROS
 * Componentes como hijos, en vez de solo campos de texto/imagen propios.
 * Resuelve, por sí solo, el rol de "Box"/"Stack"/"Grid"/"Cluster"/
 * "Split"/"Inline" del catálogo de 50 primitivas (memoria de producto,
 * plan "50 primitivas de UI") — ver VARIANTES más abajo: nunca son tipos
 * nuevos en la Factory, son presets de esta misma clase.
 *
 * Historial real (Fase 1/2/3 abajo son de un plan ANTERIOR de la memoria
 * de producto, "primitivas de layout" — no confundir con las fases del
 * plan actual "50 primitivas"): un `<div>` que renderiza sus hijos, ya
 * resueltos por Sofia_Pagina (Sofia_Componente::$hijos, ver el comentario
 * largo ahí sobre por qué llegan YA instanciados y no como array crudo).
 * El editor visual (paso 2 del rediseño de layout a 3 zonas fijas —
 * Panel de Estructura, ver la memoria de producto) reordena/selecciona
 * hijos de un Container desde el árbol lateral, no arrastrando en el
 * canvas — este archivo (render()) no necesita saber nada de eso, solo
 * emite HTML real, salvo por UN detalle: cuando un Container se inserta a
 * NIVEL SUPERIOR (no dentro de otro container), ver el comentario sobre
 * el wrapper <section> más abajo en render().
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
		$html .= '<div ' . $this->atributos_div_interno() . '>';
		foreach ( $this->hijos as $hijo ) {
			$html .= $hijo->render();
		}
		$html .= '</div>';
		$html .= '</section>';
		return $html;
	}

	/**
	 * atributos_div_interno(): class="..." + style="..." del
	 * <div class="sofia-container"> interno — Fase 1 de "50 primitivas"
	 * (ver la memoria de producto) cierra un hueco real encontrado
	 * releyendo este archivo: schema_propio() ya declaraba display/
	 * direccion/envolver/justificar/alinear/columnas_grilla/gap como
	 * controles del drawer desde Fase 2 ("primitivas de layout"), pero
	 * render() nunca los leía — el usuario podía elegir "Fila" en el
	 * drawer y el bloque seguía viéndose exactamente igual, porque ningún
	 * atributo salía al HTML para que el CSS pudiera targetear. Mismo
	 * patrón que el resto del sistema (utility classes de Core Framework +
	 * custom properties para valores libres, ver
	 * Sofia_Componente::clases_utilitarias_bloque()/atributo_estilo_bloque()):
	 * "display" resuelve a una clase CSS ("sofia-container--fila"), "gap"
	 * (input libre o token) resuelve a --sofia-container-gap inline.
	 *
	 * VARIANTE (Fase 1, nueva): un preset que solo cambia los DEFAULTS de
	 * display/gap/justificar/envolver — nunca agrega un tipo nuevo a la
	 * Factory (ver Sofia_Componente_Factory::TIPOS_REGISTRADOS). Sigue
	 * siendo "container" para GoPress/el catálogo de la IA; Stack/Grid/
	 * Cluster/Split/Inline/Box son simplemente Container con otro punto de
	 * partida — el usuario puede seguir ajustando cada control suelto
	 * después de elegir una variante, esta nunca es una restricción.
	 * valores_con_variante() resuelve variante → overrides ANTES de leer
	 * cada prop individual, así una variante nunca pisa un control que el
	 * usuario ya tocó a mano (props reales siempre ganan sobre el default
	 * de la variante).
	 */
	private function atributos_div_interno(): string {
		$valores = $this->valores_con_variante();

		$clases = array( 'sofia-container' );

		$display = $valores['display'] ?? '';
		if ( isset( self::CLASES_DISPLAY[ $display ] ) ) {
			$clases[] = self::CLASES_DISPLAY[ $display ];
		}

		// direccion/envolver/justificar/alinear solo tienen sentido real con
		// display fila/columna (mismo criterio ya documentado en la "ayuda"
		// de schema_propio()) — igual se resuelven sin condicionar por
		// display acá: una clase "sofia-container--justificar-centro" sin
		// display:flex de por medio simplemente no tiene ningún selector
		// CSS que la consuma con efecto visible, mismo patrón "no rompe,
		// no hace nada" que ya usa CLASES_UTILITARIAS_BLOQUE con controles
		// fuera de contexto (ej. aspect_ratio en un Componente sin imagen).
		if ( 'invertida' === ( $valores['direccion'] ?? '' ) ) {
			$clases[] = 'sofia-container--invertida';
		}
		if ( ! empty( $valores['envolver'] ) ) {
			$clases[] = 'sofia-container--envolver';
		}
		$justificar = $valores['justificar'] ?? '';
		if ( isset( self::CLASES_JUSTIFICAR[ $justificar ] ) ) {
			$clases[] = self::CLASES_JUSTIFICAR[ $justificar ];
		}
		$alinear = $valores['alinear'] ?? '';
		if ( isset( self::CLASES_ALINEAR[ $alinear ] ) ) {
			$clases[] = self::CLASES_ALINEAR[ $alinear ];
		}
		$columnas_grilla = (string) ( $valores['columnas_grilla'] ?? '' );
		if ( 'grilla' === $display && in_array( $columnas_grilla, array( '2', '3', '4' ), true ) ) {
			$clases[] = 'sofia-container--grilla-' . $columnas_grilla;
		}

		// gap: INPUT LIBRE o token de Core Framework (mismo mecanismo que
		// "espaciado_vertical" de Nivel 2 genérico, ver
		// Sofia_Componente::atributo_estilo_bloque()) — nunca una utility
		// class fija, porque el espacio entre hijos es un valor continuo,
		// no una opción corta de una lista. --sofia-container-gap (no
		// "gap:" directo en style, aunque acá SIEMPRE tendría el mismo
		// efecto): custom property porque el CSS de esta clase necesita
		// poder tener un fallback propio (ver style.css) cuando la
		// variante define un gap por defecto sin que el usuario haya
		// tocado nada.
		$estilo = '';
		$gap    = $valores['gap'] ?? '';
		if ( is_string( $gap ) && '' !== $gap ) {
			if ( Sofia_Estilo_Global::es_token_con_nombre( $gap ) ) {
				$estilo = 'style="--sofia-container-gap:' . esc_attr( Sofia_Estilo_Global::resolver_valor( $gap ) ) . '"';
			} elseif ( preg_match( '/^\d+(\.\d+)?(px|rem|%)$/', $gap ) ) {
				$estilo = 'style="--sofia-container-gap:' . esc_attr( $gap ) . '"';
			}
		}

		return sprintf( 'class="%s" %s', esc_attr( implode( ' ', $clases ) ), $estilo );
	}

	/**
	 * VARIANTES: preset de $tipo => overrides de props que esa variante
	 * aplica CUANDO el usuario no configuró ese control a mano — nunca
	 * pisa un valor real ya presente en $this->props (ver
	 * valores_con_variante()). "libre" (default, sin variante elegida) no
	 * tiene entrada acá — sin overrides, el comportamiento es exactamente
	 * el de Container tal cual venía siendo hasta Fase 1.
	 */
	// Tokens "cf:space-{xs|s|m|l|xl}" — escala REAL de Core Framework
	// (confirmado en Sofia_Estilo_Global::ETIQUETAS_ESCALA, nunca
	// "space-2"/"space-4"/"space-8": esa numeración no existe en este
	// sistema de tokens, era una suposición sin verificar descartada
	// antes de commitear).
	private const VARIANTES = array(
		'stack'   => array( 'display' => 'columna', 'gap' => 'cf:space-m' ),
		'grid'    => array( 'display' => 'grilla', 'columnas_grilla' => '3', 'gap' => 'cf:space-m' ),
		'cluster' => array( 'display' => 'fila', 'envolver' => true, 'justificar' => 'start', 'gap' => 'cf:space-s' ),
		'split'   => array( 'display' => 'fila', 'justificar' => 'space-between' ),
		'inline'  => array( 'display' => 'fila', 'gap' => 'cf:space-s' ),
	);

	private const CLASES_DISPLAY = array(
		'fila'    => 'sofia-container--fila',
		'columna' => 'sofia-container--columna',
		'grilla'  => 'sofia-container--grilla',
		'ninguno' => 'sofia-container--ninguno',
	);

	private const CLASES_JUSTIFICAR = array(
		'start'         => 'sofia-container--justificar-inicio',
		'center'        => 'sofia-container--justificar-centro',
		'end'           => 'sofia-container--justificar-fin',
		'space-between' => 'sofia-container--justificar-entre',
	);

	private const CLASES_ALINEAR = array(
		'start'  => 'sofia-container--alinear-inicio',
		'center' => 'sofia-container--alinear-centro',
		'end'    => 'sofia-container--alinear-fin',
	);

	/**
	 * valores_con_variante(): los controles de layout (display/direccion/
	 * envolver/justificar/alinear/columnas_grilla/gap) con el override de
	 * la VARIANTE elegida como base, y cualquier valor que el usuario haya
	 * tocado a mano encima — un control queda "sin tocar" cuando su prop
	 * guardada es "" (string vacío) o ausente, el mismo significado que
	 * "" ya tiene en cada <select> de schema_propio() ("Por defecto"/
	 * "Bloque (por defecto)", nunca un valor real distinguible de "no
	 * elegido"). Por eso NO alcanza con un array_merge() simple (que
	 * pisaría el override de la variante con un "" real presente en
	 * props): cada clave se resuelve por separado, prop real primero si
	 * NO está vacía, si no el override de la variante, si no "" — mismo
	 * criterio de "la variante es un punto de partida, nunca una
	 * restricción" documentado en atributos_div_interno().
	 *
	 * Caso de borde aceptado, documentado a propósito: "envolver" es un
	 * toggle booleano (CampoDesdeSchema.jsx), así que "false" (desmarcado
	 * a mano) y "nunca tocado" son indistinguibles acá — hoy la ÚNICA
	 * variante que lo activa por defecto es "cluster", así que el único
	 * efecto real de esta ambigüedad es que un Cluster no puede
	 * desactivar "Envolver" sin también cambiarlo a otra variante o a
	 * "libre". Se acepta por ahora (impacto chico, un control de un caso
	 * de uso específico) en vez de cambiar el contrato de "" como "sin
	 * tocar" que usa TODO el resto del sistema.
	 */
	private function valores_con_variante(): array {
		$variante = (string) ( $this->props['variante'] ?? '' );
		$override = self::VARIANTES[ $variante ] ?? array();

		$valores = array();
		foreach ( array( 'display', 'direccion', 'envolver', 'justificar', 'alinear', 'columnas_grilla', 'gap' ) as $clave ) {
			$propio = $this->props[ $clave ] ?? '';
			$valores[ $clave ] = ( '' !== $propio && null !== $propio && false !== $propio )
				? $propio
				: ( $override[ $clave ] ?? '' );
		}
		return $valores;
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
	 * schema_propio(): controles de layout que solo Container tiene.
	 *
	 * Historial real (por si el nombre "Fase 1" confunde entre dos planes
	 * distintos de la memoria de producto): estos controles existían
	 * desde el plan de "primitivas de layout" (Fase 2 de ESE plan), pero
	 * render() nunca los leía — display/direccion/gap/etc. se guardaban
	 * bien pero no tenían NINGÚN efecto visual, un hueco real encontrado
	 * al arrancar la Fase 1 del plan de "50 primitivas" (memoria de
	 * producto). atributos_div_interno() (ver render()) ahora sí los
	 * traduce a clases CSS + custom properties reales.
	 *
	 * "variante" (nuevo en Fase 1 de "50 primitivas"): preset que cambia
	 * los DEFAULTS de display/gap/justificar/envolver sin agregar un tipo
	 * nuevo a la Factory — ver el comentario largo en
	 * valores_con_variante(). Cubre Stack/Grid/Cluster/Split/Inline del
	 * catálogo de 50 primitivas pedido por el usuario; "Box" (el catálogo
	 * también lo pide) es Container SIN variante, ya cubierto por
	 * "" (Libre).
	 */
	public static function schema_propio(): array {
		return array(
			'variante'   => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Variante', 'sofia-studio' ),
				'ayuda'    => __( 'Un punto de partida — cambia los valores por defecto de Layout interno/Espacio entre hijos/etc. de abajo, que seguís pudiendo ajustar uno por uno después.', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'Libre (Box)', 'sofia-studio' ) ),
					array( 'valor' => 'stack', 'etiqueta' => __( 'Stack (columna, con espacio)', 'sofia-studio' ) ),
					array( 'valor' => 'grid', 'etiqueta' => __( 'Grid (grilla de 3)', 'sofia-studio' ) ),
					array( 'valor' => 'cluster', 'etiqueta' => __( 'Cluster (fila que envuelve)', 'sofia-studio' ) ),
					array( 'valor' => 'split', 'etiqueta' => __( 'Split (extremos separados)', 'sofia-studio' ) ),
					array( 'valor' => 'inline', 'etiqueta' => __( 'Inline (fila compacta)', 'sofia-studio' ) ),
				),
			),
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

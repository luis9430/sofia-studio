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
	 *    de este bloque" — todo el JS (mouseover, contextmenu, lectura de
	 *    estructura) busca esto con closest("section")/
	 *    [data-sofia-bloque-id], nunca con closest("div.sofia-container").
	 * 2. leerBloquesDesde()/contenedorDeHijos() (editor-iframe.js) recorren
	 *    ":scope > section" — un <div class="sofia-container"> nunca
	 *    matchea ahí, así que si la raíz del bloque fuera ese <div>, el
	 *    bloque entero sería invisible para la lectura de estructura.
	 *
	 * Todo Componente del catálogo (Hero, CTA, etc.) resuelve esto
	 * gratis porque su ÚNICO elemento raíz ya es un <section> con
	 * atributos_seccion() encima — Container es el primero cuya raíz
	 * "natural" es un <div> (necesita seguir siendo <div> para que
	 * display:flex/grid + gap tengan un contenedor real, separado de la
	 * <section> que lleva el estilo de caja de Nivel 2). La solución más
	 * chica que no exige tocar ni el editor ni el CSS existente: envolver
	 * ese <div class="sofia-container"> (SIN atributos_seccion(), sin
	 * id/tipo) dentro de un <section> exterior que SÍ los lleva — así:
	 *   - A nivel superior: la <section> exterior es indistinguible de un
	 *     Hero/CTA para la lectura de estructura.
	 *   - Dentro de OTRO Container: la <section> exterior es el hijo
	 *     directo que espera contenedorDeHijos() (mismo
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
	 * atributos_div_interno(): class="..." del <div class="sofia-container">
	 * interno — Fase 1 de "50 primitivas" (ver la memoria de producto) cerró
	 * un hueco real (schema declarado pero render() nunca lo leía). Anexo
	 * posterior de esa misma fase: MIGRADO a las utility classes REALES de
	 * Core Framework (flex-row, flex-column, row, columns-N, gap-N,
	 * content-N, space-between, space-around, items-top/middle/bottom) en
	 * vez de clases propias "sofia-container--*" — investigación real
	 * (CSS descargado del sitio en vivo con curl) confirmó que Core
	 * Framework YA trae un sistema de layout completo y equivalente; las
	 * clases propias duplicaban exactamente eso. Mismo patrón que
	 * Sofia_Componente::clases_utilitarias_bloque() ya usa para las
	 * utility classes de ancho/aspecto: agregar la clase real es todo lo
	 * que hace falta, sin resolver ningún valor propio.
	 *
	 * Colisión de nombres CONOCIDA y aceptada (confirmada con el usuario):
	 * Core Framework usa "items-top / items-middle / items-bottom" con
	 * sentido align-items (grid/flex) — Sofia Studio YA usa
	 * "items-left / items-center / items-right / items-top / items-middle
	 * / items-bottom" con OTRO sentido (alineación de TEXTO en Franja de
	 * beneficios/Testimonios, ver style.css) en la SECCION de esos
	 * Componentes. Nunca conviven en el mismo elemento (acá siempre es el
	 * DIV interno "sofia-container", nunca una sección de lista), así que
	 * no hay conflicto real de CSS — solo dos usos legítimos del mismo
	 * nombre en contextos distintos.
	 *
	 * VARIANTE (Fase 1): un preset que solo cambia los DEFAULTS de
	 * display, gap, justificar y envolver — nunca agrega un tipo nuevo a
	 * la Factory. valores_con_variante() resuelve variante → overrides
	 * ANTES de leer cada prop individual, así una variante nunca pisa un
	 * control que el usuario ya tocó a mano.
	 */
	private function atributos_div_interno(): string {
		$valores = $this->valores_con_variante();

		$clases = array( 'sofia-container' );

		$display         = $valores['display'] ?? '';
		$columnas_grilla = (string) ( $valores['columnas_grilla'] ?? '' );

		// "grilla" con columnas_grilla elegido usa .columns-N (que YA trae
		// su propio grid-template-columns real) EN VEZ de .row (el display
		// grid genérico de Core Framework, sin columnas definidas) — .row
		// solo se usa como respaldo si el usuario elige Grilla sin elegir
		// un número de columnas todavía.
		if ( 'grilla' === $display && isset( self::CLASES_COLUMNAS[ $columnas_grilla ] ) ) {
			$clases[] = self::CLASES_COLUMNAS[ $columnas_grilla ];
		} elseif ( isset( self::CLASES_DISPLAY[ $display ] ) ) {
			$clases[] = self::CLASES_DISPLAY[ $display ];
		}

		// direccion/envolver/justificar/alinear solo tienen sentido real con
		// display fila/columna/grilla (mismo criterio ya documentado en la
		// "ayuda" de schema_propio()) — igual se resuelven sin condicionar
		// por display acá: una clase ".content-center" sin display:flex/grid
		// de por medio simplemente no tiene ningún efecto visible, mismo
		// patrón "no rompe, no hace nada fuera de contexto" que ya usa
		// CLASES_UTILITARIAS_BLOQUE (clase base) con controles fuera de
		// contexto (ej. aspect_ratio en un Componente sin imagen).
		//
		// "invertida" sigue siendo CSS PROPIO ("sofia-container--invertida",
		// ver style.css) — Core Framework no trae una utility class
		// equivalente a flex-direction:row-reverse/column-reverse.
		if ( 'invertida' === ( $valores['direccion'] ?? '' ) ) {
			$clases[] = 'sofia-container--invertida';
		}
		if ( ! empty( $valores['envolver'] ) ) {
			$clases[] = 'flex-wrap';
		}
		$justificar = $valores['justificar'] ?? '';
		if ( isset( self::CLASES_JUSTIFICAR[ $justificar ] ) ) {
			$clases[] = self::CLASES_JUSTIFICAR[ $justificar ];
		}
		$alinear = $valores['alinear'] ?? '';
		if ( isset( self::CLASES_ALINEAR[ $alinear ] ) ) {
			$clases[] = self::CLASES_ALINEAR[ $alinear ];
		}

		// gap: escala FIJA de Core Framework (.gap-4xs..gap-4xl, 11 pasos)
		// — mismo criterio que columnas_grilla, ya no es input libre (antes
		// emitía --sofia-container-gap inline con cualquier valor/token; se
		// migró a clase fija porque Core Framework ya cubre toda la escala
		// real de espaciado del sistema, sin necesitar un valor fuera de
		// ella).
		$gap = (string) ( $valores['gap'] ?? '' );
		if ( isset( self::CLASES_GAP[ $gap ] ) ) {
			$clases[] = self::CLASES_GAP[ $gap ];
		}

		return sprintf( 'class="%s"', esc_attr( implode( ' ', $clases ) ) );
	}

	/**
	 * VARIANTES: preset de $tipo => overrides de props que esa variante
	 * aplica CUANDO el usuario no configuró ese control a mano — nunca
	 * pisa un valor real ya presente en $this->props (ver
	 * valores_con_variante()). "libre" (default, sin variante elegida) no
	 * tiene entrada acá — sin overrides, el comportamiento es exactamente
	 * el de Container tal cual venía siendo hasta Fase 1.
	 */
	// "gap" en la escala REAL de Core Framework (4xs/3xs/2xs/xs/s/m/l/xl/
	// 2xl/3xl/4xl, confirmado con curl contra el CSS real del sitio en
	// vivo — ver CLASES_GAP abajo) — ya NO tokens "cf:space-*" sueltos
	// (versión anterior de esta migración, descartada): "gap" pasó de
	// input libre/token a una escala fija, mismo criterio que
	// columnas_grilla.
	private const VARIANTES = array(
		'stack'   => array( 'display' => 'columna', 'gap' => 'm' ),
		'grid'    => array( 'display' => 'grilla', 'columnas_grilla' => '3', 'gap' => 'm' ),
		'cluster' => array( 'display' => 'fila', 'envolver' => true, 'justificar' => 'start', 'gap' => 's' ),
		'split'   => array( 'display' => 'fila', 'justificar' => 'space-between' ),
		'inline'  => array( 'display' => 'fila', 'gap' => 's' ),
	);

	// CLASES_DISPLAY/CLASES_COLUMNAS/CLASES_JUSTIFICAR/CLASES_ALINEAR/
	// CLASES_GAP: utility classes REALES de Core Framework (confirmadas
	// con curl contra core_framework.css del sitio en vivo, nunca
	// inventadas) — mismo criterio de "agregar la clase es todo lo que
	// hace falta" que Sofia_Componente::CLASES_UTILITARIAS_BLOQUE (clase
	// base) ya usa para max-width-*/width-*/aspect-*.
	private const CLASES_DISPLAY = array(
		'fila'    => 'flex-row',
		'columna' => 'flex-column',
		'grilla'  => 'row', // grid genérico de CF, sin columnas propias — ver CLASES_COLUMNAS para el caso con número elegido.
		// "ninguno" no tiene clase — sin display:flex/grid, los hijos
		// simplemente se apilan en flujo normal (comportamiento de
		// "Bloque (por defecto)" también, mismo resultado visual, pero
		// "ninguno" es la elección EXPLÍCITA de "no quiero que esto sea un
		// contenedor de layout").
	);

	// .columns-2 a .columns-8 — Core Framework trae el rango completo,
	// aunque el control columnas_grilla hoy solo ofrezca 2/3/4 (ver
	// schema_propio()) — el mapa cubre las 3 opciones reales del select
	// actual, ampliable sin tocar este mapa si el control gana más
	// opciones después.
	private const CLASES_COLUMNAS = array(
		'2' => 'columns-2',
		'3' => 'columns-3',
		'4' => 'columns-4',
	);

	private const CLASES_JUSTIFICAR = array(
		'start'         => 'content-left',
		'center'        => 'content-center',
		'end'           => 'content-right',
		'space-between' => 'space-between',
		'space-around'  => 'space-around',
	);

	// .items-top/-middle/-bottom de Core Framework (align-items) — mismo
	// NOMBRE que las clases propias del tema ".items-left/-center/-right/
	// -top/-middle/-bottom" (alineación de TEXTO en Franja de beneficios/
	// Testimonios, ver style.css), pero NUNCA en el mismo elemento — acá
	// siempre es el <div class="sofia-container">, jamás una <section> de
	// lista. Colisión de nombre conocida y aceptada, ver el comentario
	// largo en atributos_div_interno().
	private const CLASES_ALINEAR = array(
		'start'  => 'items-top',
		'center' => 'items-middle',
		'end'    => 'items-bottom',
	);

	// .gap-4xs .. .gap-4xl — escala completa de Core Framework (11 pasos),
	// confirmada dos veces con curl contra el sitio en vivo antes de
	// commitear (ver el comentario largo del plan). Mismas 9 claves que
	// ETIQUETAS_ESCALA ya usa en Sofia_Estilo_Global para el resto del
	// sistema, más los 2 extremos (4xs/4xl) que ese mapa no necesitaba
	// nombrar hasta ahora.
	private const CLASES_GAP = array(
		'4xs' => 'gap-4xs',
		'3xs' => 'gap-3xs',
		'2xs' => 'gap-2xs',
		'xs'  => 'gap-xs',
		's'   => 'gap-s',
		'm'   => 'gap-m',
		'l'   => 'gap-l',
		'xl'  => 'gap-xl',
		'2xl' => 'gap-2xl',
		'3xl' => 'gap-3xl',
		'4xl' => 'gap-4xl',
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
		// BUG REAL corregido tras probar en vivo: display/direccion/gap/
		// etc. (schema_propio(), Nivel 2 igual que el genérico) viajan
		// dentro de $this->props['_estilo_bloque'], NUNCA como claves
		// sueltas de $this->props — mismo lugar exacto de donde ya las
		// lee atributo_estilo_bloque() en la clase base (ver
		// $this->props['_estilo_bloque'] ahí). La primera versión de este
		// método leía $this->props['display'] directo: el drawer SÍ
		// guardaba bien, pero render() nunca encontraba nada ahí (siempre
		// "" ausente), así que ningún control de Layout interno/Variante
		// tenía efecto visual — confirmado por el usuario probando en
		// vivo (Container con "Grilla" elegido seguía viéndose igual).
		$estilo_bloque = $this->estilo_bloque();

		$variante = (string) ( $estilo_bloque['variante'] ?? '' );
		$override = self::VARIANTES[ $variante ] ?? array();

		$valores = array();
		foreach ( array( 'display', 'direccion', 'envolver', 'justificar', 'alinear', 'columnas_grilla', 'gap' ) as $clave ) {
			$propio = $estilo_bloque[ $clave ] ?? '';
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
	 * "variante" es el ÚNICO control básico (sin 'avanzado') — el resto
	 * (display/direccion/envolver/justificar/alinear/columnas_grilla/gap)
	 * lleva 'avanzado' => true: CampoDesdeSchema.jsx los agrupa detrás de
	 * un botón "Personalizar" (mismo patrón ya validado en
	 * CampoTokenVisual.jsx, básico + "Avanzado") — el drawer muestra solo
	 * "Variante" de entrada en vez de 8 controles de una, y la variante
	 * sigue siendo el punto de partida real: "Personalizar" solo REVELA
	 * los controles sueltos, nunca oculta ni reemplaza lo que la variante
	 * ya definió (valores_con_variante() no cambia).
	 *
	 * Etiquetas de "variante" con el nombre técnico entre paréntesis
	 * (decisión confirmada con el usuario: sirve de puente para quien ya
	 * conoce Stack/Cluster/Split de otras herramientas, sin obligar a
	 * nadie a saberlos) — el VALOR guardado ("stack", "cluster", etc.) no
	 * cambia, solo la etiqueta visible, cero riesgo de romper contenido
	 * ya guardado en sitios existentes.
	 */
	public static function schema_propio(): array {
		return array(
			'variante'   => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Variante', 'sofia-studio' ),
				'ayuda'    => __( 'Un punto de partida — cambia los valores por defecto de "Personalizar" (abajo), que seguís pudiendo ajustar uno por uno después.', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'Libre', 'sofia-studio' ) ),
					array( 'valor' => 'stack', 'etiqueta' => __( 'Uno debajo del otro (Stack)', 'sofia-studio' ) ),
					array( 'valor' => 'grid', 'etiqueta' => __( 'En grilla, 3 columnas (Grid)', 'sofia-studio' ) ),
					array( 'valor' => 'cluster', 'etiqueta' => __( 'En fila, salta de línea si no entra (Cluster)', 'sofia-studio' ) ),
					array( 'valor' => 'split', 'etiqueta' => __( 'Extremos separados (Split)', 'sofia-studio' ) ),
					array( 'valor' => 'inline', 'etiqueta' => __( 'En fila, pegados (Inline)', 'sofia-studio' ) ),
				),
			),
			'display'    => array(
				'tipo'      => 'select',
				'etiqueta'  => __( 'Cómo se acomodan los hijos', 'sofia-studio' ),
				'ayuda'     => __( 'Fila y Columna habilitan Dirección, Alinear y Justificar de abajo. Grilla habilita Columnas de grilla.', 'sofia-studio' ),
				'avanzado'  => true,
				'opciones'  => array(
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
				'avanzado' => true,
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'Normal', 'sofia-studio' ) ),
					array( 'valor' => 'invertida', 'etiqueta' => __( 'Invertida', 'sofia-studio' ) ),
				),
			),
			'envolver'   => array(
				'tipo'     => 'toggle',
				'etiqueta' => __( 'Envolver (flex-wrap)', 'sofia-studio' ),
				'ayuda'    => __( 'Solo con Layout interno = Fila o Columna.', 'sofia-studio' ),
				'avanzado' => true,
			),
			'justificar' => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Justificar', 'sofia-studio' ),
				'avanzado' => true,
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'Por defecto', 'sofia-studio' ) ),
					array( 'valor' => 'start', 'etiqueta' => __( 'Inicio', 'sofia-studio' ) ),
					array( 'valor' => 'center', 'etiqueta' => __( 'Centro', 'sofia-studio' ) ),
					array( 'valor' => 'end', 'etiqueta' => __( 'Fin', 'sofia-studio' ) ),
					array( 'valor' => 'space-between', 'etiqueta' => __( 'Espacio entre', 'sofia-studio' ) ),
					array( 'valor' => 'space-around', 'etiqueta' => __( 'Espacio alrededor', 'sofia-studio' ) ),
				),
			),
			'alinear'    => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Alinear', 'sofia-studio' ),
				'avanzado' => true,
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
				'avanzado' => true,
				'opciones' => array( '2', '3', '4' ),
			),
			// "gap" migrado de medida_token (input libre + token) a la
			// escala FIJA de Core Framework (11 pasos) — mismo criterio
			// que columnas_grilla, confirmado con el usuario: consistente
			// con que el resto del control ya viene de clases fijas de
			// CF, sin inventar un valor fuera de la escala del sistema de
			// diseño. Etiquetas humanas (no los sufijos técnicos crudos
			// "xs"/"2xl"), mismo criterio que
			// Sofia_Estilo_Global::ETIQUETAS_ESCALA ya usa para espaciado
			// en otros contextos del editor.
			'gap'        => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Espacio entre hijos', 'sofia-studio' ),
				'avanzado' => true,
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'Sin espacio', 'sofia-studio' ) ),
					array( 'valor' => '4xs', 'etiqueta' => __( 'Mínimo', 'sofia-studio' ) ),
					array( 'valor' => '3xs', 'etiqueta' => __( 'Muy compacto', 'sofia-studio' ) ),
					array( 'valor' => '2xs', 'etiqueta' => __( 'Compacto', 'sofia-studio' ) ),
					array( 'valor' => 'xs', 'etiqueta' => __( 'Ajustado', 'sofia-studio' ) ),
					array( 'valor' => 's', 'etiqueta' => __( 'Chico', 'sofia-studio' ) ),
					array( 'valor' => 'm', 'etiqueta' => __( 'Base', 'sofia-studio' ) ),
					array( 'valor' => 'l', 'etiqueta' => __( 'Amplio', 'sofia-studio' ) ),
					array( 'valor' => 'xl', 'etiqueta' => __( 'Grande', 'sofia-studio' ) ),
					array( 'valor' => '2xl', 'etiqueta' => __( 'Muy grande', 'sofia-studio' ) ),
					array( 'valor' => '3xl', 'etiqueta' => __( 'Extra grande', 'sofia-studio' ) ),
					array( 'valor' => '4xl', 'etiqueta' => __( 'Máximo', 'sofia-studio' ) ),
				),
			),
		);
	}
}

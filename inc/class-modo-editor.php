<?php
/**
 * Sofia_Modo_Editor activa la edición in-place dentro del iframe del panel
 * de admin (ver inc/js/editor-iframe.js) — SOLO cuando la visita trae
 * "?sofia_editor=1" Y el usuario tiene permiso real de edición. Un
 * visitante normal jamás carga este JS ni ve ningún elemento
 * contenteditable: la query var por sí sola no alcanza, current_user_can()
 * es lo que de verdad protege esto (sin sesión de wp-admin, WordPress
 * nunca reporta ese permiso, sin importar qué traiga la URL).
 */
class Sofia_Modo_Editor {

	public static function activo(): bool {
		if ( ! isset( $_GET['sofia_editor'] ) ) {
			return false;
		}
		return current_user_can( 'edit_pages' );
	}

	/**
	 * Muuri (CDN, vendor sin build step — mismo criterio que
	 * SOFIA_LIBRERIAS_JS para las dependencias de los Componentes del
	 * catálogo). Reemplaza a SortableJS — bug real encontrado en la
	 * práctica: con bloques de altura MUY dispar (Hero ~37px junto a una
	 * Franja de 267px+), SortableJS reordenaba erráticamente MIENTRAS el
	 * drag seguía activo (confirmado con logging real: oldIndex/newIndex
	 * saltando sin relación clara con el movimiento del mouse, incluso
	 * lento), sin que swapThreshold/invertSwap/fallbackOnBody lo
	 * resolvieran. Causa raíz (investigación real): SortableJS reordena
	 * nodos en el FLUJO NORMAL del documento y lee getBoundingClientRect()
	 * en vivo mientras hay animación CSS en curso — geometría inestable
	 * por diseño con alturas dispares. Muuri usa un modelo
	 * fundamentalmente distinto: calcula posiciones con su propio motor de
	 * layout (bin-packing tipo Masonry) y las aplica vía transform sobre
	 * ítems position:absolute — nunca depende del reflow del navegador
	 * sobre un flujo variable, y su algoritmo de swap compara ÁREA DE
	 * INTERSECCIÓN real (mecanismos anti-jitter propios: sortInterval,
	 * minDragDistance, minBounceBackAngle) en vez de comparar bounding
	 * rects de un documento en movimiento.
	 */
	const VERSION_MUURI = '0.9.5';

	public static function encolar_script(): void {
		if ( ! self::activo() ) {
			return;
		}
		wp_enqueue_media(); // necesario para que wp.media() esté disponible dentro del iframe.
		wp_enqueue_script(
			'sofia-muuri',
			'https://cdn.jsdelivr.net/npm/muuri@' . self::VERSION_MUURI . '/dist/muuri.min.js',
			array(),
			self::VERSION_MUURI,
			true
		);
		wp_enqueue_script(
			'sofia-editor-iframe',
			get_stylesheet_directory_uri() . '/inc/js/editor-iframe.js',
			array( 'sofia-muuri' ),
			wp_get_theme()->get( 'Version' ),
			true
		);

		// Bug real encontrado en la práctica: el fetch a
		// sofia/v1/catalogo-bloques desde dentro del iframe daba 401
		// "rest_forbidden" a pesar de la sesión de admin activa — la REST
		// API de WordPress exige el nonce (X-WP-Nonce) además de la
		// cookie de sesión, como protección CSRF, y este script corre en
		// el FRONT PÚBLICO (no en wp-admin, donde WordPress inyecta
		// wpApiSettings automáticamente) — hay que generarlo acá a mano,
		// mismo wp_create_nonce('wp_rest') que ya usa
		// Sofia_Panel_Editor::encolar_assets() para el panel padre.
		wp_localize_script(
			'sofia-editor-iframe',
			'SofiaEditorIframeConfig',
			array( 'nonce' => wp_create_nonce( 'wp_rest' ) )
		);
	}

	/**
	 * Oculta #wpadminbar por CSS dentro del iframe — a prueba de que algún
	 * plugin de terceros del sitio siga forzando is_admin_bar_showing() a
	 * true a pesar del filtro "show_admin_bar" de abajo (bug real
	 * encontrado en la práctica contra un sitio con muchos plugins
	 * activos: el filtro con prioridad PHP_INT_MAX no bastó). El CSS
	 * siempre gana, sin importar qué decida la lógica PHP de admin bar.
	 */
	public static function imprimir_css_ocultar_admin_bar(): void {
		if ( ! self::activo() ) {
			return;
		}
		echo '<style>#wpadminbar { display: none !important; } html { margin-top: 0 !important; }</style>';
	}

	/**
	 * CSS del handle de arrastre (Nivel 2) — inyectado inline en vez de un
	 * archivo .css propio, mismo criterio que
	 * imprimir_css_ocultar_admin_bar: es CSS que SOLO debe existir cuando
	 * el modo editor está activo, nunca en una visita pública normal (ver
	 * inc/js/editor-iframe.js, activarReordenar(), que inyecta el
	 * elemento .sofia-handle-arrastre dentro de cada <section>).
	 */
	public static function imprimir_css_reordenar(): void {
		if ( ! self::activo() ) {
			return;
		}
		// Bug real encontrado en la práctica, dos iteraciones:
		// (1) el handle DENTRO de la sección (top/left sobre el propio
		//     contenido) tapaba el título.
		// (2) el intento de sacarlo por fuera del borde (left: -36px) lo
		//     dejaba fuera del viewport visible: la sección ocupa el ancho
		//     completo de la pantalla, así que un hijo con left negativo
		//     queda recortado por el borde real del navegador, invisible
		//     por completo (a diferencia de un layout con margen lateral
		//     libre, donde "salirse del borde" todavía cae dentro de la
		//     ventana).
		// Fix real: el handle vuelve a vivir DENTRO de la sección (top/
		// right en la esquina, no top/left donde suele estar el título) y
		// se mantiene chico + semitransparente por defecto — visible pero
		// discreto, en vez de invisible hasta hover (que además dependía
		// del mismo posicionamiento roto).
		// Colores fijos (no variables CSS): este <style> se inyecta DENTRO
		// del iframe, en el documento del sitio real — no tiene acceso a
		// las custom properties del panel padre (ver admin-app/src/style.css,
		// que sí las define para su propio documento). Mismos valores
		// hexadecimales exactos del mockup de diseño original ("Editor de
		// Contenido", Artifact): --gp-panel #1c1a17, --gp-accent #d97a4d,
		// --gp-panel-border #38342f — así el chrome de edición dentro del
		// iframe combina con el panel Preact que lo rodea, en vez de un
		// gris genérico sin relación con la paleta real del producto.
		// Investigación real confirmó por qué el handle NO puede vivir en
		// el panel Preact exterior (fuera del iframe): SortableJS, en
		// escritorio, depende del "dragstart" NATIVO de HTML5 Drag&Drop —
		// el navegador solo lo dispara a partir de un gesto de mouse real
		// sobre un elemento draggable=true; un evento sintético
		// (dispatchEvent) siempre tiene isTrusted:false y el navegador lo
		// ignora para ese propósito. El handle tiene que seguir siendo del
		// documento del iframe.
		//
		// Para lograr igual la sensación visual de "handle fuera del
		// contenido" (pedido explícito, mismo patrón de Bricks/Elementor):
		// SOLO EN MODO EDITOR, .sofia-pagina gana un padding lateral
		// transparente (MARGEN_HANDLE) — nunca en una visita pública
		// normal, así que el layout real de la página no cambia para
		// ningún visitante. El handle se posiciona en left negativo DENTRO
		// de ese margen recién creado — a diferencia del primer intento
		// (bug real ya documentado: left negativo sin margen real
		// disponible queda recortado fuera del viewport), acá el margen SÍ
		// existe de verdad dentro del propio documento del iframe.
		//
		// padding-left 60px (no 44px, el ancho exacto del handle): bug de
		// UI real encontrado en la práctica — con margen y left-negativo
		// del MISMO tamaño, el handle quedaba pegado justo al borde
		// izquierdo del canvas/iframe, sin aire de sobra. El handle sigue
		// en left:-44px (ver abajo), quedando centrado dentro de estos
		// 60px con ~16px de aire real a su izquierda.
		// .sofia-pagina > section pasa a position:absolute (requisito de
		// Muuri: calcula y aplica sus propias coordenadas x/y vía
		// transform, nunca depende del flujo normal del documento — ver el
		// comentario largo sobre por qué se migró de SortableJS en
		// activarReordenar(), editor-iframe.js). width:100% explícito
		// porque position:absolute ya no hereda el ancho automático de
		// bloque en flujo normal.
		// Bug real encontrado en la práctica, confirmado con
		// getBoundingClientRect() real en el navegador: Muuri mide el
		// ancho DISPONIBLE del contenedor grid (.sofia-pagina) para
		// posicionar sus items, pero ignora por completo cualquier
		// padding-left declarado ahí (documentación oficial: usar un
		// WRAPPER aparte para necesidades de layout adicionales, nunca
		// aplicarlas al elemento que Muuri gestiona directo). Con
		// padding-left en .sofia-pagina, cada <section> (width:100%)
		// terminaba calculando su ancho sobre el BORDER-BOX completo
		// (padding incluido), empezando en x:0 en vez de x:60 — el margen
		// que debía existir para el handle nunca se materializó de
		// verdad, dejando el handle en left:-44px REAL (fuera del
		// viewport, recortado) en vez de dentro de un margen real.
		// Fix: el margen se mueve a margin-left EN CADA SECTION (un item
		// individual, no el contenedor grid) — Muuri no interfiere con el
		// margin de sus propios items, solo con el padding del contenedor
		// que los agrupa.
		echo '<style>
			.sofia-pagina {
				position: relative; overflow: visible;
			}
			/* .sofia-pagina > section SIN z-index en el CSS estático — bug
			   real encontrado en la práctica: un z-index alto en el HANDLE
			   (hijo) no alcanza, porque cada <section> con position:absolute
			   crea su PROPIO stacking context implícito — el z-index de un
			   hijo solo compite DENTRO de ese contexto, nunca "sale" a
			   competir contra otra sección completa. Lo que decide si la
			   Franja de beneficios (sección posterior) tapa el handle del
			   Hero anterior es el z-index de las SECCIONES mismas, que sin
			   valor explícito quedan empatadas en 0 y gana el orden del DOM
			   (la posterior se pinta encima). Fix real: JS asigna z-index
			   INVERSO al orden a cada <section> (ver activarReordenar(),
			   editor-iframe.js) — la primera sección siempre queda arriba
			   de todas las siguientes, así su handle (que sobresale del
			   width:100% del contenido, en la franja izquierda de padding)
			   nunca quede tapado por ninguna sección posterior.
			   overflow:visible en <section> sigue siendo necesario para
			   que el navegador ni siquiera intente recortar ese handle que
			   sobresale del ancho declarado. */
			.sofia-pagina > section {
				position: absolute; margin-left: 60px; width: calc(100% - 60px);
				box-sizing: border-box; overflow: visible;
			}
			.sofia-handle-arrastre {
				position: absolute; top: 8px; left: -44px; z-index: 1;
				width: 26px; height: 26px; display: flex; align-items: center; justify-content: center;
				background: #1c1a17; border: 1px solid #38342f; color: #ede9e3; border-radius: 6px;
				cursor: grab; font-size: 14px; user-select: none;
			}
			.sofia-handle-arrastre:hover { background: #262320; border-color: #4a453e; }
			.sofia-handle-arrastre:active { cursor: grabbing; }

			/* [data-sofia-lista]/[data-sofia-item] pasan a position:relative/
			   absolute — mismo requisito de Muuri que .sofia-pagina/section
			   arriba, pero SOLO en modo editor (nunca en el CSS de
			   producción del Componente, que sigue usando su layout real,
			   ej. display:grid de 3 columnas, para cualquier visitante).
			   width explícito en el item: position:absolute no hereda el
			   ancho automático que tenía en flujo normal (grid/flex). */
			[data-sofia-lista] { position: relative; }
			/* width vía --sofia-columnas (default 3) — Muuri en modo editor
			   NUNCA usa CSS Grid/display:grid real (cada item se posiciona a
			   mano con position:absolute + transform, ver el comentario largo
			   sobre Muuri en activarReordenar(), editor-iframe.js), así que
			   el ancho de cada item tiene que calcularse en JS/CSS acá — el
			   grid-template-columns real de .sofia-franja-beneficios__grid/
			   .sofia-testimonios__grid (ver style.css) solo aplica en el
			   sitio PÚBLICO, sin Muuri encima. Bug real encontrado en la
			   práctica: "Estilo del bloque" → Columnas cambiaba
			   --sofia-columnas en la <section>, pero acá seguía hardcodeado a
			   33.333% (3 columnas fijas) — el control no tenía NINGÚN efecto
			   visible dentro del editor, solo hubiera funcionado en el sitio
			   público. --sofia-columnas hereda de la <section> (heredable por
			   ser custom property) hasta [data-sofia-item], sin necesitar que
			   editor-iframe.js la vuelva a fijar en cada item individual. */
			[data-sofia-item] {
				position: absolute; width: calc((100% / var(--sofia-columnas, 3)) - 20px); box-sizing: border-box;
			}
			/* Handle de un ITEM dentro de una lista repetible (ej. un
			   "Beneficio" de la Franja) — mismo criterio que el handle de
			   bloque de arriba, pero más chico y posicionado dentro del
			   propio item. */
			.sofia-handle-arrastre-item {
				position: absolute; top: 4px; right: 4px; z-index: 5;
				width: 20px; height: 20px; display: flex; align-items: center; justify-content: center;
				background: #1c1a17; border: 1px solid #38342f; color: #ede9e3; border-radius: 5px;
				cursor: grab; font-size: 11px; user-select: none;
			}
			.sofia-handle-arrastre-item:hover { background: #262320; border-color: #4a453e; }
			.sofia-handle-arrastre-item:active { cursor: grabbing; }

			/* Marca visual de "bloque oculto por condición de visibilidad"
			   (pestaña Visibilidad del drawer) — atributo en la propia
			   <section> (ver Sofia_Componente::atributos_seccion()), NUNCA un
			   <div> envolvente que rompería el matching de Muuri. ::after con
			   el texto del propio atributo (content: attr(...)) evita
			   duplicar la etiqueta en un data-* Y en un elemento HTML aparte. */
			[data-sofia-oculto-condicion] {
				outline: 2px dashed #d97a4d; outline-offset: -2px; opacity: 0.6;
			}
			[data-sofia-oculto-condicion]::after {
				content: attr(data-sofia-oculto-condicion);
				position: absolute; top: 8px; right: 8px; z-index: 3;
				background: #d97a4d; color: #1c1a17;
				font-size: 10px; font-weight: 600; padding: 3px 8px; border-radius: 5px;
			}

			/* Botón "+ Agregar" al final de una lista repetible — ver
			   Sofia_Componente::boton_agregar_item(). Gris neutro (NO
			   naranja) — bug real encontrado en la práctica: con el mismo
			   naranja que la línea de inserción de bloque nuevo (ver
			   .sofia-linea-insertar más abajo), ambos "+" quedaban
			   indistinguibles cuando caían pegados (ej. al final de una
			   Franja, justo antes del borde del bloque siguiente). El
			   naranja de acento queda reservado EXCLUSIVAMENTE para
			   "agregar bloque completo" — un lenguaje de color consistente:
			   gris = acción dentro del bloque actual, naranja = acción
			   sobre la estructura de bloques de la página. */
			/* Flujo normal (no position:absolute) — bug real encontrado en
			   la práctica: un primer intento puso este botón DENTRO de
			   [data-sofia-lista] con position:absolute/top:100%, pero
			   Muuri fija el height de ESE contenedor basándose
			   ÚNICAMENTE en sus items — un botón absoluto nunca aporta a
			   ese cálculo, así que la <section> jamás reservaba espacio
			   visual real para él (quedaba superpuesto sobre el bloque
			   siguiente). Fix real: el botón se movió a HERMANO de
			   [data-sofia-lista] (ver
			   Sofia_Componente_Franja_Beneficios::render()), fuera del
			   contenedor que Muuri gestiona — en flujo normal ahí, sí
			   empuja la altura real de la <section>, que el
			   ResizeObserver de nivel superior detecta solo. */
			.sofia-boton-agregar-item {
				display: block; width: 100%; margin-top: 8px;
				padding: 10px; background: transparent;
				border: 1px dashed #d8d3c9; border-radius: 6px;
				color: #6b6459; opacity: 0.75; cursor: pointer; font-size: 13px;
				font-family: "Inter", -apple-system, sans-serif;
			}
			.sofia-boton-agregar-item:hover { opacity: 1; border-color: #6b6459; background: rgba(107,100,89,0.08); }

			/* Línea de inserción ENTRE bloques — mismo patrón del mockup de
			   diseño original ("Editor de Contenido", Artifact): una raya
			   fina que solo se pinta al hover, con un botón "+" circular al
			   centro. Bug real encontrado en la práctica (confirmado con
			   logging real de SortableJS): vivir como HERMANA de las
			   <section> dentro de .sofia-pagina contaminaba oldIndex/
			   newIndex del Sortable de nivel superior — "draggable"
			   filtra qué es arrastrable, pero NO excluye del conteo de
			   índices. Fix: la línea vive DENTRO de cada <section>
			   (position:absolute sobre su borde superior/inferior, ver
			   activarLineasInsertar()) — .sofia-pagina vuelve a tener SOLO
			   <section> como hijos directos.
			   z-index alto + pointer-events:none en el ::before (la raya)
			   para no interceptar clicks destinados al contenido real;
			   solo el botón "+" en sí es clickeable. */
			.sofia-linea-insertar {
				position: absolute; left: 0; right: 0; height: 16px; z-index: 6;
				display: flex; align-items: center; justify-content: center;
				pointer-events: none;
			}
			.sofia-linea-insertar--arriba { top: -8px; }
			.sofia-linea-insertar--abajo { bottom: -8px; }
			.sofia-linea-insertar::before {
				content: ""; position: absolute; left: 0; right: 0; top: 50%;
				height: 1px; background: transparent; transition: background 0.15s ease-out;
				pointer-events: none;
			}
			.sofia-linea-insertar:hover::before { background: #d97a4d; }
			.sofia-linea-insertar__boton {
				position: relative; width: 22px; height: 22px; border-radius: 50%;
				border: 1.5px solid #d97a4d; background: #fff; color: #d97a4d;
				display: flex; align-items: center; justify-content: center;
				font-size: 15px; line-height: 1; cursor: pointer; pointer-events: auto;
				opacity: 0; transform: scale(0.85); transition: opacity 0.12s, transform 0.12s;
			}
			.sofia-linea-insertar:hover .sofia-linea-insertar__boton { opacity: 1; transform: scale(1); }
			.sofia-linea-insertar__boton:hover { background: #d97a4d; color: #fff; }
		</style>';
	}
}

add_action( 'wp_enqueue_scripts', array( 'Sofia_Modo_Editor', 'encolar_script' ) );
add_action( 'wp_head', array( 'Sofia_Modo_Editor', 'imprimir_css_ocultar_admin_bar' ) );
add_action( 'wp_head', array( 'Sofia_Modo_Editor', 'imprimir_css_reordenar' ) );

// La visita dentro del iframe (?sofia_editor=1) carga la página PÚBLICA
// real con sesión de admin activa — WordPress le pone su PROPIA admin bar
// ahí igual que a cualquier visita autenticada, sin relación con la admin
// bar de la página de administración que contiene el iframe (ver
// Sofia_Panel_Editor::ocultar_chrome_admin, que solo cubre esa OTRA
// pantalla). Bug real encontrado en la práctica: sin esto, se veían DOS
// admin bars superpuestas (la del panel padre y la del iframe). El filtro
// "show_admin_bar" con prioridad PHP_INT_MAX (en vez de la función
// show_admin_bar()) es necesario porque algún plugin de terceros de este
// sitio la vuelve a forzar a "true" más tarde en el ciclo de "init" — ver
// el mismo comentario, más detallado, en Sofia_Panel_Editor::ocultar_chrome_admin.
add_action(
	'init',
	function () {
		if ( Sofia_Modo_Editor::activo() ) {
			add_filter( 'show_admin_bar', '__return_false', PHP_INT_MAX );
		}
	}
);

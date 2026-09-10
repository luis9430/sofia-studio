/**
 * Corre DENTRO del iframe del editor in-place — solo se encola cuando
 * Sofia_Modo_Editor::activo() es true (ver inc/class-modo-editor.php),
 * nunca en una visita pública normal.
 *
 * Este script NUNCA guarda nada por su cuenta ni conoce a GoPress — solo
 * detecta cambios sobre los elementos marcados data-sofia-campo (ver
 * Sofia_Componente::atributo_editable) y los informa al panel padre
 * (Preact, en wp-admin) vía postMessage. El panel padre es el único que
 * decide cuándo/cómo persistir (autosave, debounce, llamada al proxy REST
 * sofia/v1) — ver la memoria de producto "Sofia Studio".
 *
 * El drawer de estilo (alineación, color, negrita/cursiva) vive en el
 * panel padre, NUNCA acá — mismo patrón "controles fuera del documento del
 * iframe" validado contra Bricks/AEM (ver la memoria de producto). Este
 * script solo informa un click sobre un campo vía postMessage
 * ("sofia:campo-clickeado"); el padre dibuja el botón ✏️ y el drawer, y al
 * cambiar algo manda un mensaje de vuelta ("sofia:aplicar-formato" /
 * "sofia:aplicar-estilo") que este script ejecuta — el único lugar donde
 * el DOM real del texto se toca es acá, dentro del iframe.
 */
(function () {
	"use strict";

	var elementoConSeleccion = null;

	function notificarCambio(campo, valor) {
		window.parent.postMessage({ tipo: "sofia:campo-editado", campo: campo, valor: valor }, "*");
	}

	// innerHTML (no innerText): permite que negrita/cursiva sobrevivan al
	// guardado — ver Sofia_Componente::texto_enriquecido() del lado PHP,
	// que sanitiza a un whitelist chico (b/strong/i/em/br) antes de
	// imprimir. Guardar innerText descartaría cualquier <b>/<i> aplicado
	// por la barra de formato.
	function activarTexto(el) {
		var campo = el.getAttribute("data-sofia-campo");
		el.setAttribute("contenteditable", "true");
		el.addEventListener("blur", function () {
			// Un pequeño setTimeout: si el blur ocurrió porque el usuario
			// hizo click en la barra de formato flotante (que vive en el
			// padre, fuera de este documento), queremos que
			// aplicar-formato ya haya llegado y actuado antes de leer
			// innerHTML — si no, se guardaría el valor viejo sin el
			// formato recién aplicado.
			setTimeout(function () {
				notificarCambio(campo, el.innerHTML.trim());
			}, 50);
		});
		// Un solo click (sin seleccionar texto) muestra un botón pequeño
		// "editar estilo" pegado al campo — pedido explícito del usuario:
		// antes solo aparecía al SELECCIONAR texto (arrastrando), lo cual no
		// era descubrible con un click simple.
		el.addEventListener("click", function () {
			var rect = el.getBoundingClientRect();
			window.parent.postMessage(
				{
					tipo: "sofia:campo-clickeado",
					campo: campo,
					rect: { top: rect.top, left: rect.left, width: rect.width, bottom: rect.bottom },
					estilo: estiloActualDe(el),
				},
				"*"
			);
		});
	}

	function activarImagen(el) {
		var campo = el.getAttribute("data-sofia-campo");
		el.style.cursor = "pointer";
		el.title = "Click para cambiar la imagen";
		el.addEventListener("click", function (evento) {
			evento.preventDefault();
			var selector = wp.media({
				title: "Elegir imagen",
				button: { text: "Usar esta imagen" },
				multiple: false,
			});
			selector.on("select", function () {
				var adjunto = selector.state().get("selection").first().toJSON();
				el.src = adjunto.url;
				notificarCambio(campo, adjunto.url);
			});
			selector.open();
		});
	}

	// Solo actualiza elementoConSeleccion — la referencia que
	// alAplicarEstilo()/"sofia:aplicar-formato" (negrita/cursiva, ver
	// alRecibirMensajeDelPadre) necesitan para saber sobre qué elemento
	// aplicar document.execCommand. Ya NO dibuja ninguna UI en el padre: el
	// drawer se abre con un click simple (ver "sofia:campo-clickeado" en
	// activarTexto), no con este evento — antes, la única forma de llegar
	// al control de estilo era ARRASTRAR para seleccionar texto, un gesto
	// poco descubrible que el usuario señaló explícitamente.
	function alCambiarSeleccion() {
		var seleccion = window.getSelection();
		if (!seleccion || seleccion.isCollapsed || seleccion.rangeCount === 0) {
			elementoConSeleccion = null;
			return;
		}

		var nodo = seleccion.anchorNode;
		var elemento = nodo && nodo.nodeType === Node.TEXT_NODE ? nodo.parentElement : nodo;
		var campoEditable = elemento ? elemento.closest("[data-sofia-campo][contenteditable]") : null;
		elementoConSeleccion = campoEditable || null;
	}

	// Lee el estilo YA APLICADO al elemento (por Sofia_Componente::atributo_estilo()
	// del lado PHP, o por un cambio ya hecho en esta misma sesión vía
	// alAplicarEstilo() abajo) para que el drawer del panel padre abra con
	// los controles YA reflejando el estado real, en vez de siempre en
	// blanco — mismo criterio de "editar lo que ves" que el resto del
	// editor. style.color/textAlign (no getComputedStyle): getComputedStyle
	// devolvería el valor HEREDADO/default también cuando el elemento no
	// tiene NADA propio guardado, imposible de distinguir de "el usuario
	// eligió justo ese color" — el atributo style="" inline solo existe
	// cuando atributo_estilo() de verdad emitió algo.
	// Claves en snake_case (no camelCase) a propósito — coinciden 1:1 con
	// Sofia_Componente::ESTILOS_CAMPO_PERMITIDOS del lado PHP, que las usa
	// tal cual como clave del JSON guardado. Mismo objeto viaja sin
	// traducción en todo el recorrido: iframe → postMessage → drawer
	// (Preact) → postMessage → iframe → guardado.
	function estiloActualDe(el) {
		return {
			alineacion: el.style.textAlign || "",
			color: el.style.color || "",
			tamano_fuente: el.style.fontSize || "",
			negrita: el.style.fontWeight || "",
			color_fondo: el.style.backgroundColor || "",
			sombra_texto: el.style.textShadow || "",
		};
	}

	// Aplica el estilo elegido en el drawer (panel padre) directo al
	// elemento en pantalla — feedback inmediato, sin esperar el roundtrip
	// de guardado — y notifica al padre para persistir bajo "{campo}._estilo"
	// (ver Sofia_REST_Editor::asignar_valor_de_campo(), que ya reconoce
	// este sufijo en cualquier campo, simple o dentro de un item de lista).
	//
	// Busca el elemento por [data-sofia-campo="..."] en vez de depender de
	// elementoConSeleccion: el usuario abre el drawer con una selección de
	// texto activa, pero mover el foco hacia los controles del panel padre
	// (fuera del iframe) puede disparar un blur/selectionchange que ya
	// vació esa referencia — el campo llega explícito desde el padre en
	// cada mensaje, así que no hace falta mantener ningún estado vivo acá.
	function alAplicarEstilo(campo, estilo) {
		var el = document.querySelector('[data-sofia-campo="' + campo + '"]');
		if (!el) return;

		el.style.textAlign = estilo.alineacion || "";
		el.style.color = estilo.color || "";
		el.style.fontSize = estilo.tamano_fuente || "";
		el.style.fontWeight = estilo.negrita || "";
		el.style.backgroundColor = estilo.color_fondo || "";
		el.style.textShadow = estilo.sombra_texto || "";

		notificarCambio(campo + "._estilo", estilo);
	}

	function alRecibirMensajeDelPadre(evento) {
		var datos = evento.data;
		if (!datos) return;
		if (datos.tipo === "sofia:aplicar-formato" && elementoConSeleccion) {
			elementoConSeleccion.focus();
			document.execCommand(datos.comando, false, null);
			return;
		}
		if (datos.tipo === "sofia:aplicar-estilo") {
			alAplicarEstilo(datos.campo, datos.estilo);
			return;
		}
		if (datos.tipo === "sofia:eliminar-bloque") {
			alEliminarBloque(datos.indice);
			return;
		}
		if (datos.tipo === "sofia:eliminar-item-lista") {
			alEliminarItemDeLista(datos.campoLista, datos.indiceItem);
		}
	}

	// nombresBloque se llena al cargar, pidiendo el catálogo REAL a
	// sofia/v1/catalogo-bloques (ver Sofia_Componente_Factory::catalogo())
	// — a diferencia de un mapa hardcodeado acá, esto nunca se desincroniza
	// de qué Componentes existen de verdad en el tema instalado. Mientras
	// la respuesta no llegó (o si falla), alMoverMouse cae al propio
	// "tipo" crudo como nombre — mejor mostrar "hero" que nada.
	//
	// X-WP-Nonce es OBLIGATORIO acá — bug real encontrado en la práctica:
	// sin él, la REST API de WordPress devuelve 401 "rest_forbidden" a
	// pesar de la sesión de admin activa (protección CSRF). Este script
	// corre en el front público (no wp-admin, donde WordPress inyecta
	// wpApiSettings automáticamente), así que el nonce llega vía
	// SofiaEditorIframeConfig (wp_localize_script, ver
	// Sofia_Modo_Editor::encolar_script).
	var nombresBloque = {};
	fetch("/wp-json/sofia/v1/catalogo-bloques", {
		headers: { "X-WP-Nonce": (window.SofiaEditorIframeConfig || {}).nonce || "" },
	})
		.then(function (resp) { return resp.ok ? resp.json() : []; })
		.then(function (catalogo) {
			catalogo.forEach(function (bloque) {
				nombresBloque[bloque.tipo] = bloque.nombre;
			});
		})
		.catch(function () {});

	var seccionResaltada = null;

	// Resalta el BLOQUE completo (la <section> que contiene el campo bajo
	// el mouse), no el campo individual — decisión explícita: ayuda a
	// orientarse entre bloques distintos de una página con varios, sin el
	// ruido visual de resaltar cada texto/imagen suelto (ver la memoria de
	// producto "Sofia Studio"). Mismo patrón "controles fuera del iframe"
	// que la barra de formato: este script solo informa posición+nombre,
	// el panel padre dibuja el overlay.
	function alMoverMouse(evento) {
		// Se busca la <section> PRIMERO (no un data-sofia-campo primero) —
		// bug real encontrado en la práctica: buscar el campo primero hacía
		// que el overlay "parpadeara" al mover el mouse sobre cualquier
		// zona de la sección que no fuera exactamente un elemento
		// editable (el padding entre el título y el borde, por ejemplo),
		// porque closest("[data-sofia-campo]") devolvía null ahí y se
		// apagaba el resaltado — aunque el mouse siguiera técnicamente
		// dentro del bloque completo.
		var seccion = evento.target.closest ? evento.target.closest("section") : null;

		if (!seccion) {
			if (seccionResaltada) {
				seccionResaltada = null;
				window.parent.postMessage({ tipo: "sofia:bloque-sin-resaltar" }, "*");
			}
			return;
		}
		if (seccion === seccionResaltada) return; // evita spam de postMessage en cada pixel de movimiento dentro del mismo bloque.

		seccionResaltada = seccion;
		// tipo viene de data-sofia-bloque-tipo en la <section> misma (ver
		// Sofia_Componente::atributos_seccion()) — ya NO se extrae del
		// primer data-sofia-campo, que desde el ID de instancia solo
		// contiene el ID (ej. "a3f92c1b.titulo"), nunca el tipo.
		var tipo = seccion.getAttribute("data-sofia-bloque-tipo") || "";
		var rect = seccion.getBoundingClientRect();
		// indice = posición entre las <section> de nivel superior de
		// ".sofia-pagina" — identificador estable para "eliminar ESTE
		// bloque". :scope > section (no contenedor.children): desde
		// activarLineasInsertar(), .sofia-pagina mezcla <section> con
		// .sofia-linea-insertar como hermanos — children daría un índice
		// desalineado con el que espera alEliminarBloque().
		var contenedor = document.querySelector(".sofia-pagina");
		var indice = contenedor
			? Array.prototype.indexOf.call(contenedor.querySelectorAll(":scope > section"), seccion)
			: -1;
		window.parent.postMessage(
			{
				tipo: "sofia:bloque-resaltado",
				nombre: nombresBloque[tipo] || tipo,
				indice: indice,
				rect: { top: rect.top, left: rect.left, width: rect.width, height: rect.height },
			},
			"*"
		);
	}

	// Menú contextual (Nivel 2) — click derecho sobre un bloque, en vez de
	// un botón "✕" flotante en el overlay de resaltado. Decisión explícita
	// del usuario tras un bug real: un botón superpuesto en el documento
	// PADRE, encima del iframe, competía por el mouse con los eventos
	// mouseover/mouseleave que ESTE script dispara — mover el cursor hacia
	// el botón lo sacaba del iframe, apagando el resaltado (y con él el
	// botón) antes de que el click llegara a procesarse. Un evento
	// "contextmenu" no tiene ese problema: se dispara UNA vez, dentro del
	// iframe, sin competir con mouseover/mouseleave. preventDefault() evita
	// que el navegador muestre además su propio menú nativo.
	function alHacerClickDerecho(evento) {
		var seccion = evento.target.closest ? evento.target.closest("section") : null;
		if (!seccion) return;
		evento.preventDefault();

		var contenedor = document.querySelector(".sofia-pagina");
		// :scope > section (no contenedor.children) — ver el mismo
		// comentario en alMoverMouse.
		var indice = contenedor
			? Array.prototype.indexOf.call(contenedor.querySelectorAll(":scope > section"), seccion)
			: -1;

		// Si el click fue DENTRO de un item de lista repetible (ej. un
		// "Beneficio" concreto de la Franja), el menú ofrece "Eliminar
		// item" en vez de "Eliminar bloque" — mismo evento contextmenu,
		// pero el padre necesita saber cuál de las dos acciones mostrar y
		// con qué datos, así que se manda la info de ambos niveles.
		// campoLista (ej. "a3f92c1b.items") ya identifica de forma
		// inequívoca a ESTA instancia de lista — usa el ID del bloque, no
		// su tipo (ver Sofia_Componente::atributo_editable()), así que dos
		// "Franja de beneficios" en la misma página nunca comparten este
		// valor. No hace falta ningún índice de posición adicional.
		var itemEl = evento.target.closest ? evento.target.closest("[data-sofia-item]") : null;
		var listaEl = itemEl ? itemEl.closest("[data-sofia-lista]") : null;
		var itemInfo = null;
		if (itemEl && listaEl) {
			itemInfo = {
				campoLista: listaEl.getAttribute("data-sofia-lista"),
				indiceItem: parseInt(itemEl.getAttribute("data-sofia-item"), 10),
			};
		}

		window.parent.postMessage(
			{
				tipo: "sofia:menu-contextual-bloque",
				indice: indice,
				item: itemInfo,
				x: evento.clientX,
				y: evento.clientY,
			},
			"*"
		);
	}

	// Elimina UN item de una lista repetible (ej. un "Beneficio" de la
	// Franja) — distinto de alEliminarBloque: acá el bloque entero sigue
	// existiendo, solo se quita un item de su lista. Reusa
	// notificarListaActualizada() para reportar el array resultante, mismo
	// mecanismo que un reordenamiento.
	function alEliminarItemDeLista(campoLista, indiceItem) {
		var contenedorLista = document.querySelector('[data-sofia-lista="' + campoLista + '"]');
		var item = contenedorLista ? contenedorLista.querySelector(':scope > [data-sofia-item="' + indiceItem + '"]') : null;
		if (!item) return;

		// grid.remove() de Muuri (no item.remove() a mano) — necesario
		// para que el estado interno del grid (posiciones, layout) se
		// actualice correctamente; el DOM real se limpia solo con
		// removeElements:true.
		var instancia = instanciasListas.get(contenedorLista);
		if (instancia) {
			var instanciaItem = instancia.getItems([item]);
			instancia.remove(instanciaItem, { removeElements: true });
		} else {
			item.remove();
		}
		reindexarItemsDeLista(contenedorLista);
		notificarListaActualizada(contenedorLista);

		// Mismo motivo que en alAgregarItemALista(): eliminar un item
		// cambia la altura de la <section> contenedora — el grid de nivel
		// superior necesita refrescar sus dimensiones cacheadas.
		if (gridNivelSuperior) {
			gridNivelSuperior.refreshItems().layout();
		}
	}

	// Agrega un item nuevo al final de una lista repetible — clona el
	// ÚLTIMO item existente del DOM, en vez de construir HTML desde cero:
	// este script no conoce la forma específica de cada tipo de lista
	// (Franja de beneficios tiene titulo+texto, un futuro "Testimonios"
	// podría tener nombre+cita+foto) — clonar es genérico para cualquiera.
	//
	// Bug real encontrado en la práctica: la primera versión VACIABA el
	// texto de los campos clonados ("" en vez del texto heredado) — el
	// usuario reportó que un item nuevo aparecía completamente en blanco,
	// sin ningún valor de referencia para empezar a editar. Fix: el clon
	// conserva el texto del último item tal cual (mismo criterio que
	// "editar lo que ves": el usuario ve un item con contenido real, que
	// edita, en vez de una plantilla vacía que no comunica qué va ahí).
	//
	// Requiere que la lista tenga AL MENOS un item para clonar; una lista
	// vacía no puede agregar por este mecanismo (caso borde no soportado
	// hoy: recargar el iframe tras eliminar el último item, si hiciera
	// falta, sería la vía de escape).
	function alAgregarItemALista(campoLista) {
		var contenedorLista = document.querySelector('[data-sofia-lista="' + campoLista + '"]');
		var ultimoItem = contenedorLista ? contenedorLista.querySelector(":scope > [data-sofia-item]:last-of-type") : null;
		if (!ultimoItem) return;

		var itemNuevo = ultimoItem.cloneNode(true);
		ultimoItem.after(itemNuevo);

		// grid.add() de Muuri (no solo insertar en el DOM) — necesario
		// para que el grid reconozca el nuevo elemento como item propio y
		// lo incluya en su layout/estado interno; sin esto, el elemento
		// existiría en el DOM pero Muuri lo ignoraría por completo.
		var instancia = instanciasListas.get(contenedorLista);
		if (instancia) {
			instancia.add(itemNuevo);
		}

		reindexarItemsDeLista(contenedorLista);
		activarCamposEditables(itemNuevo);
		notificarListaActualizada(contenedorLista);

		// Agregar un item cambia la ALTURA de la <section> que lo contiene
		// (Franja de beneficios) — el grid de NIVEL SUPERIOR (que posiciona
		// esa sección respecto a las demás) necesita refrescar sus
		// dimensiones cacheadas, mismo bug/fix que en activarLineasInsertar().
		if (gridNivelSuperior) {
			gridNivelSuperior.refreshItems().layout();
		}
	}

	// Reindexa data-sofia-item Y data-sofia-campo de cada item restante
	// tras un borrado — sin esto, un campo editado justo después de
	// eliminar (antes de que el padre recargue el iframe) mandaría el
	// ÍNDICE VIEJO en su "campo" (ej. "franja_beneficios.items.2.titulo"
	// cuando en realidad ahora es el item 1), y
	// Sofia_REST_Editor::asignar_valor_de_campo() escribiría en la
	// posición equivocada del array guardado.
	function reindexarItemsDeLista(contenedorLista) {
		var campoLista = contenedorLista.getAttribute("data-sofia-lista");
		Array.prototype.forEach.call(contenedorLista.querySelectorAll(":scope > [data-sofia-item]"), function (item, indice) {
			item.setAttribute("data-sofia-item", indice);
			item.querySelectorAll("[data-sofia-campo]").forEach(function (campoEl) {
				var subcampo = campoEl.getAttribute("data-sofia-campo").split(".").pop();
				campoEl.setAttribute("data-sofia-campo", campoLista + "." + indice + "." + subcampo);
			});
		});
	}

	// Quita la sección del DOM real (para que el usuario vea el resultado
	// de inmediato, sin esperar el guardado) y reporta la estructura
	// resultante al padre con el MISMO mensaje que ya usa el reordenamiento
	// — el padre no necesita saber si el cambio vino de un drag o de un
	// comando del menú contextual, solo persiste la lista de bloques que
	// recibe. Llamado desde el padre vía postMessage tras elegir "Eliminar
	// bloque" en el menú.
	function alEliminarBloque(indice) {
		var contenedor = document.querySelector(".sofia-pagina");
		if (!contenedor || !gridNivelSuperior) return;

		// remove() de Muuri toma INSTANCIAS de Item (getItems(indice)), no
		// el elemento DOM crudo — usar seccion.remove() a mano dejaría al
		// grid con una referencia interna a un item ya destruido, rompiendo
		// su estado. removeElements:true además borra el <section> del DOM
		// por nosotros.
		var items = gridNivelSuperior.getItems(indice);
		if (!items.length) return;
		gridNivelSuperior.remove(items, { removeElements: true });

		seccionResaltada = null;
		window.parent.postMessage({ tipo: "sofia:bloque-sin-resaltar" }, "*");
		actualizarZIndexSecciones(contenedor); // menos secciones ahora, recalcula el z-index de cada una.
		activarLineasInsertar(); // reconstruye posiciones tras el borrado.
		window.parent.postMessage(
			{ tipo: "sofia:estructura-reordenada", bloques: leerBloquesDeNivelSuperior(contenedor) },
			"*"
		);
	}

	// activarReordenar (Nivel 2) — Muuri corre DENTRO de este documento
	// (ver la memoria de producto "Sofia Studio": ninguna librería de DnD
	// cruza la frontera del iframe de forma madura, mismo patrón que
	// Elementor/Bricks: drag en el canvas real, informando solo el
	// resultado final al padre). "dragHandle" restringe el arrastre a un
	// ícono propio (".sofia-handle-arrastre", inyectado acá, nunca parte
	// del HTML que emite cada Componente) — SIN esto, arrastrar por
	// cualquier parte de la sección competiría con hacer click para editar
	// el texto/imagen de adentro.
	// gridNivelSuperior: instancia de Muuri para reordenar bloques de nivel
	// superior (Hero, Franja de beneficios, etc.) — reemplaza a SortableJS
	// tras un bug real de fondo: con bloques de altura MUY dispar (Hero
	// ~37px junto a una Franja de 267px+), SortableJS reordenaba
	// erráticamente MIENTRAS el drag seguía activo (confirmado con
	// logging real: oldIndex/newIndex saltando sin relación clara con el
	// movimiento del mouse, incluso lento), sin que
	// swapThreshold/invertSwap/fallbackOnBody lo resolvieran — causa raíz:
	// SortableJS reordena nodos en el FLUJO NORMAL del documento y lee
	// getBoundingClientRect() en vivo mientras hay animación CSS en curso.
	// Muuri calcula posiciones con su propio motor de layout y las aplica
	// vía transform sobre ítems position:absolute (ver CSS en
	// class-modo-editor.php) — nunca depende del reflow del navegador
	// sobre un flujo variable.
	var gridNivelSuperior = null;

	// Asigna z-index DECRECIENTE a cada <section> de nivel superior según
	// su posición real en el DOM — la primera sección siempre queda por
	// encima de todas las que le siguen. Necesario porque cada <section>
	// (position:absolute) crea su propio stacking context: sin esto, dos
	// secciones con z-index empatado en 0 se apilan por orden del DOM (la
	// posterior tapa a la anterior), y el handle de arrastre (que
	// sobresale del ancho declarado, en la franja de padding izquierda)
	// quedaba oculto detrás de cualquier sección siguiente. Se llama al
	// inicializar y de nuevo tras cualquier reordenamiento/inserción, ya
	// que el orden real puede cambiar.
	function actualizarZIndexSecciones(contenedor) {
		var secciones = contenedor.querySelectorAll(":scope > section");
		var total = secciones.length;
		secciones.forEach(function (seccion, indice) {
			seccion.style.zIndex = total - indice;
		});
	}

	function activarReordenar() {
		var contenedor = document.querySelector(".sofia-pagina");
		if (!contenedor || typeof Muuri === "undefined" || gridNivelSuperior) return; // ya inicializado, evita doble-instancia.

		contenedor.querySelectorAll(":scope > section").forEach(function (seccion) {
			if (seccion.querySelector(":scope > .sofia-handle-arrastre")) return; // ya tiene handle, evita duplicar si esto corriera dos veces.
			var handle = document.createElement("div");
			handle.className = "sofia-handle-arrastre";
			// El título menciona el click derecho explícitamente — bug de
			// UX real señalado por el usuario: sin esta pista, un usuario
			// no técnico no tiene forma de descubrir que existe un menú
			// contextual (eliminar bloque/item) más allá de arrastrar.
			handle.setAttribute("title", "Arrastrar para reordenar · Click derecho para más opciones");
			handle.textContent = "⠿";
			seccion.prepend(handle);
		});

		actualizarZIndexSecciones(contenedor);

		gridNivelSuperior = new Muuri(contenedor, {
			// "section" (sin ":scope >") — bug real encontrado en la
			// práctica: Muuri filtra hijos DIRECTOS del contenedor
			// aplicando el selector con matches() a cada uno; ":scope >
			// section" es sintaxis pensada para querySelectorAll relativo
			// al documento, no para matches() de un elemento individual —
			// el filtro fallaba silenciosamente (sin error en consola) y
			// dejaba TODAS las secciones superpuestas en top:0/left:0 en
			// vez de apiladas, porque Muuri nunca las reconoció como
			// items válidos para calcular su layout.
			items: "section",
			dragEnabled: true,
			dragHandle: ".sofia-handle-arrastre",
			// dragSortHeuristics: mecanismos anti-jitter que SortableJS no
			// tiene — sortInterval (pausa antes de reevaluar el orden),
			// minDragDistance (umbral mínimo antes de considerar cualquier
			// sort), atacan justo la clase de síntoma ya vivida
			// (reordenamiento errático con movimientos chicos).
			dragSortHeuristics: { sortInterval: 100, minDragDistance: 10 },
			dragSortPredicate: { threshold: 50, action: "move" },
		});

		gridNivelSuperior.on("dragEnd", function () {
			// synchronize(): alinea el orden real del DOM con el orden
			// interno de Muuri tras el drag — necesario antes de leer las
			// secciones en su posición final.
			gridNivelSuperior.synchronize();
			actualizarZIndexSecciones(contenedor); // el orden cambió, recalcula qué sección va arriba de cuál.
			activarLineasInsertar(); // reconstruye posiciones tras el nuevo orden.
			window.parent.postMessage(
				{ tipo: "sofia:estructura-reordenada", bloques: leerBloquesDeNivelSuperior(contenedor) },
				"*"
			);
		});

		// Bug real encontrado en la práctica: Muuri solo recalcula alturas
		// cuando se le pide explícitamente (refreshItems().layout()) — un
		// cambio de contenido que no pasa por un punto ya cubierto a mano
		// (ej. editar un texto y que crezca a 2 líneas, o que una imagen
		// termine de cargar) dejaba el layout desactualizado hasta que
		// algo ajeno (un resize de ventana) forzaba un recálculo. Un
		// ResizeObserver sobre cada <section> cubre TODOS los casos de una
		// sola vez, sin depender de recordar llamar refreshItems() en cada
		// punto nuevo del código que pueda cambiar una altura.
		if (typeof ResizeObserver !== "undefined") {
			var observerAlturaSecciones = new ResizeObserver(function () {
				if (gridNivelSuperior) {
					gridNivelSuperior.refreshItems().layout();
				}
			});
			contenedor.querySelectorAll(":scope > section").forEach(function (seccion) {
				observerAlturaSecciones.observe(seccion);
			});
		}
	}

	// Lee {id, tipo} de cada <section> de nivel superior desde sus propios
	// data-sofia-bloque-id/data-sofia-bloque-tipo (ver
	// Sofia_Componente::atributos_seccion()) — reconstruye la estructura
	// completa tras un reordenamiento o una eliminación. Manda el ID YA
	// EXISTENTE de cada bloque (nunca uno nuevo): sin esto, el servidor
	// recibiría bloques "sin ID" en cada reordenamiento y les asignaría
	// IDs NUEVOS cada vez, perdiendo la asociación con el contenido ya
	// guardado de cada instancia.
	function leerBloquesDeNivelSuperior(contenedor) {
		return Array.prototype.map
			.call(contenedor.querySelectorAll(":scope > section"), function (seccion) {
				var id = seccion.getAttribute("data-sofia-bloque-id");
				var tipo = seccion.getAttribute("data-sofia-bloque-tipo");
				return id && tipo ? { id: id, tipo: tipo } : null;
			})
			.filter(Boolean);
	}

	// Líneas de inserción entre bloques — mismo patrón del mockup de diseño
	// original ("Editor de Contenido", Artifact: .insertar-linea con un
	// botón "+" en el medio, uno ANTES del primer bloque, uno DESPUÉS de
	// cada bloque). Pedido explícito del usuario: "+  Agregar bloque" de
	// la barra superior solo insertaba al final; esto permite elegir la
	// posición exacta. Igual que el resto del chrome de edición, vive
	// DENTRO del iframe (SortableJS/handles ya establecieron ese patrón) —
	// el click solo abre el MISMO catálogo que ya existe en el panel
	// padre, pasando la posición exacta vía postMessage.
	// Bug real encontrado en la práctica, tras investigación con logging
	// real de SortableJS: las líneas vivían como HERMANAS de las
	// <section> dentro de .sofia-pagina — mismo contenedor que gestiona
	// Sortable.create(). La opción "draggable" de Sortable solo filtra
	// qué es ARRASTRABLE, pero oldIndex/newIndex siguen contando TODOS
	// los hijos del contenedor (líneas incluidas) — con 3 secciones + 4
	// líneas de inserción (antes/entre/después), una sección en posición
	// real 1 podía reportarse con oldIndex 3 o 5 según cuántas líneas
	// hubiera antes en el DOM, rompiendo por completo el cálculo de swap
	// (confirmado con logs reales: oldIndex 3/5/1 con solo 3 secciones).
	// Fix real: la línea vive DENTRO de cada <section> (position:absolute,
	// ver CSS), nunca como hermana suelta — .sofia-pagina vuelve a tener
	// SOLO <section> como hijos directos, Sortable indexa correctamente.
	function activarLineasInsertar() {
		var contenedor = document.querySelector(".sofia-pagina");
		if (!contenedor) return;

		// Reconstruye TODAS las líneas desde cero cada vez que se llama —
		// más simple que actualizar posiciones a mano tras un
		// reordenamiento/agregado, y esta función es barata (unos pocos
		// elementos DOM por página).
		contenedor.querySelectorAll(".sofia-linea-insertar").forEach(function (linea) {
			linea.remove();
		});

		function crearLinea(posicion, ubicacion) {
			var linea = document.createElement("div");
			linea.className = "sofia-linea-insertar sofia-linea-insertar--" + ubicacion; // "arriba" | "abajo"
			var boton = document.createElement("button");
			boton.type = "button";
			boton.className = "sofia-linea-insertar__boton";
			boton.setAttribute("title", "Insertar bloque aquí");
			boton.textContent = "+";
			boton.addEventListener("click", function (evento) {
				evento.preventDefault();
				var rect = boton.getBoundingClientRect();
				window.parent.postMessage(
					{
						tipo: "sofia:abrir-insertar-bloque",
						posicion: posicion,
						x: rect.left + rect.width / 2,
						y: rect.top,
					},
					"*"
				);
			});
			linea.appendChild(boton);
			return linea;
		}

		var secciones = contenedor.querySelectorAll(":scope > section");
		secciones.forEach(function (seccion, indice) {
			// "arriba" en cada sección cubre "insertar antes de esta" —
			// la primera sección además necesita la línea de "arriba"
			// visible (ninguna otra sección la tapa desde encima).
			seccion.appendChild(crearLinea(indice, "arriba"));
			if (indice === secciones.length - 1) {
				// "abajo" SOLO en la última sección — el resto ya cubre
				// "después de mí" con el "arriba" de la sección siguiente.
				seccion.appendChild(crearLinea(secciones.length, "abajo"));
			}
		});

		// Bug real encontrado en la práctica: agregar las líneas cambia la
		// altura REAL de cada <section> (position:absolute — ver CSS), pero
		// Muuri ya había calculado/cacheado las alturas ANTES de este
		// cambio (en activarReordenar(), que corre primero). Sin refrescar
		// esas dimensiones, Muuri seguía posicionando el siguiente bloque
		// según la altura VIEJA, más chica — el último bloque terminaba
		// superpuesto sobre el anterior en vez de ir debajo.
		// refreshItems() releé las dimensiones reales, layout() reubica
		// todos los items con esos valores actualizados.
		if (gridNivelSuperior) {
			gridNivelSuperior.refreshItems().layout();
		}
	}

	// instanciasListas: registro de las instancias Sortable de CADA lista
	// repetible presente en la página (una por cada [data-sofia-lista], ej.
	// una por cada "Franja de beneficios") — investigación confirmó que
	// SortableJS espera exactamente este patrón (una instancia por
	// contenedor anidado, sin API de "árbol" propia). Indexado por el
	// contenedor DOM mismo, no por índice numérico: más simple que
	// mantenerlo sincronizado con la posición del bloque en la página.
	var instanciasListas = new Map();

	// Reconstruye el array completo de items de UNA lista leyendo el DOM
	// actual (ya reordenado/editado por el usuario) y lo manda al padre
	// como un "sofia:campo-editado" normal — la clave sigue siendo
	// "tipo.lista" (ej. "franja_beneficios.items"), cuyo valor es el array
	// entero. Reusa el mecanismo de guardado YA EXISTENTE (mismo
	// postMessage que un campo de texto suelto) en vez de inventar un tipo
	// de mensaje nuevo: Sofia_REST_Editor::guardar_campo() ya sabe
	// interpretar esta notación (ver asignar_valor_de_campo en
	// class-rest-editor.php).
	function notificarListaActualizada(contenedorLista) {
		var campo = contenedorLista.getAttribute("data-sofia-lista");
		var items = Array.prototype.map.call(contenedorLista.querySelectorAll(":scope > [data-sofia-item]"), function (item) {
			var objeto = {};
			item.querySelectorAll("[data-sofia-campo]").forEach(function (campoEl) {
				// El subcampo es el último segmento de
				// "tipo.lista.indice.subcampo" — ver
				// Sofia_Componente_Franja_Beneficios::render().
				var partes = campoEl.getAttribute("data-sofia-campo").split(".");
				objeto[partes[partes.length - 1]] = campoEl.innerHTML.trim();
			});
			return objeto;
		});
		notificarCambio(campo, items);
	}

	// activarReordenarListas (Nivel 2, sub-items) — monta una instancia
	// Sortable POR CADA [data-sofia-lista] presente en la página (ej. cada
	// Franja de beneficios), separada de la instancia de nivel superior.
	// Sin "group" en ninguna de las dos: investigación confirmó que sin un
	// group.name COMPARTIDO, SortableJS confina el drag a su propio
	// contenedor automáticamente — un "Beneficio" nunca puede terminar
	// arrastrado hacia la lista de bloques de nivel superior, ni viceversa,
	// sin necesidad de declarar pull:false/put:false a mano.
	function activarReordenarListas() {
		if (typeof Muuri === "undefined") return;

		document.querySelectorAll("[data-sofia-lista]").forEach(function (contenedorLista) {
			if (instanciasListas.has(contenedorLista)) return; // ya tiene instancia, evita doble-inicialización.

			contenedorLista.querySelectorAll(":scope > [data-sofia-item]").forEach(function (item) {
				if (item.querySelector(":scope > .sofia-handle-arrastre-item")) return;
				var handle = document.createElement("div");
				handle.className = "sofia-handle-arrastre-item";
				handle.setAttribute("title", "Arrastrar para reordenar · Click derecho para más opciones");
				handle.textContent = "⠿";
				item.prepend(handle);
			});

			var instancia = new Muuri(contenedorLista, {
				// "[data-sofia-item]" (sin ":scope >", mismo bug/fix que
				// gridNivelSuperior más arriba) — a diferencia de
				// SortableJS (que usaba "draggable" para excluir el botón
				// "+ Agregar", hermano directo de los items dentro del
				// mismo grid, ver Sofia_Componente::boton_agregar_item()),
				// Muuri excluye por completo cualquier elemento que no
				// matchee este selector desde la propia inicialización del
				// grid — el botón nunca es candidato a moverse.
				items: "[data-sofia-item]",
				dragEnabled: true,
				dragHandle: ".sofia-handle-arrastre-item",
				dragSortHeuristics: { sortInterval: 100, minDragDistance: 10 },
				dragSortPredicate: { threshold: 50, action: "move" },
			});
			instancia.on("dragEnd", function () {
				instancia.synchronize();
				reindexarItemsDeLista(contenedorLista);
				notificarListaActualizada(contenedorLista);
			});
			instanciasListas.set(contenedorLista, instancia);

			// Mismo fix que en activarReordenar(): un cambio de contenido
			// (ej. editar un texto y que crezca a 2 líneas) dentro de un
			// item cambia la altura de este grid interno Y de la <section>
			// que lo contiene — ambos grids (este y gridNivelSuperior)
			// necesitan refrescarse, no solo el que detecta el cambio.
			if (typeof ResizeObserver !== "undefined") {
				var observerAlturaItems = new ResizeObserver(function () {
					instancia.refreshItems().layout();
					if (gridNivelSuperior) {
						gridNivelSuperior.refreshItems().layout();
					}
				});
				contenedorLista.querySelectorAll(":scope > [data-sofia-item]").forEach(function (item) {
					observerAlturaItems.observe(item);
				});
			}
		});
	}

	// Activa contenteditable/wp.media() sobre los [data-sofia-campo] bajo
	// $raiz — extraído de una llamada inline en DOMContentLoaded para
	// poder reusarlo sobre un item nuevo clonado (ver alAgregarItemALista),
	// que no pasa por la carga inicial del documento.
	function activarCamposEditables(raiz) {
		raiz.querySelectorAll("[data-sofia-campo]").forEach(function (el) {
			if (el.tagName === "IMG") {
				activarImagen(el);
			} else {
				activarTexto(el);
			}
		});
	}

	document.addEventListener("DOMContentLoaded", function () {
		activarCamposEditables(document);

		document.addEventListener("selectionchange", alCambiarSeleccion);
		document.addEventListener("mouseover", alMoverMouse);
		document.addEventListener("mouseleave", function () {
			seccionResaltada = null;
			window.parent.postMessage({ tipo: "sofia:bloque-sin-resaltar" }, "*");
		});
		// Bug real encontrado en la práctica: getBoundingClientRect() da
		// coordenadas relativas al VIEWPORT, no al documento — al hacer
		// scroll dentro del iframe sin mover el mouse, el rect ya
		// calculado en el último mouseover queda desactualizado, pero el
		// overlay del padre lo sigue dibujando en esa posición vieja
		// (se veía "pegado" en pantalla, cruzando sobre contenido que ya
		// no era el bloque resaltado). Recalcular en cada evento de scroll
		// sería spam de postMessage por frame — más simple y robusto
		// apagar el resaltado: reaparece solo en el próximo mouseover
		// real, con coordenadas ya correctas para la posición nueva.
		// { passive: true }: este listener nunca llama preventDefault(),
		// permite que el navegador optimice el scroll.
		window.addEventListener(
			"scroll",
			function () {
				seccionResaltada = null;
				window.parent.postMessage({ tipo: "sofia:bloque-sin-resaltar" }, "*");
			},
			{ passive: true }
		);
		document.addEventListener("contextmenu", alHacerClickDerecho);
		document.addEventListener("click", function (evento) {
			var boton = evento.target.closest ? evento.target.closest("[data-sofia-agregar-item]") : null;
			if (!boton) return;
			evento.preventDefault();
			alAgregarItemALista(boton.getAttribute("data-sofia-agregar-item"));
		});
		window.addEventListener("message", alRecibirMensajeDelPadre);
		activarReordenar();
		activarReordenarListas();
		activarLineasInsertar();
	});
})();

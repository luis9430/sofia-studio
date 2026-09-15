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

	// dragStartPredicate compartido por las 2 instancias de Muuri (nivel
	// superior y listas internas) — bug real encontrado en la práctica:
	// Muuri no distingue el botón del mouse en su dragHandle, así que un
	// CLICK DERECHO sobre o cerca de un handle (para abrir el menú
	// contextual, ver alHacerClickDerecho) podía interpretarse como el
	// inicio de un drag. Ese "drag" fantasma disparaba dragEnd con el DOM
	// a medio camino, y notificarListaActualizada() guardaba una lista de
	// items CORROMPIDA (items perdidos) — confirmado en la práctica: el
	// bug solo ocurría al hacer click derecho encima o cerca del contenido
	// de un item. e.button === 2 es el estándar MouseEvent para el botón
	// derecho; para cualquier otro botón, cae en el predicado default de
	// Muuri (mismo comportamiento de siempre).
	function noArrastrarConBotonDerecho(item, evento) {
		if (evento.button === 2) return false;
		return Muuri.ItemDrag.defaultStartPredicate(item, evento);
	}

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
	// Mismas 2 claves que Sofia_Componente::FUENTES_PERMITIDAS del lado PHP
	// — "tipo_fuente" guarda la CLAVE lógica ("display"/"texto"), nunca el
	// font-family crudo, así que estiloActualDe necesita este mapa para
	// reconstruir la clave a partir del font-family ya aplicado al
	// elemento (ver alAplicarEstilo, que sí conoce la clave directo).
	var FUENTES_PERMITIDAS = {
		display: "'Fraunces', serif",
		texto: "'Inter', sans-serif",
	};

	function claveDeFuente(fontFamily) {
		for (var clave in FUENTES_PERMITIDAS) {
			if (FUENTES_PERMITIDAS[clave] === fontFamily) return clave;
		}
		return "";
	}

	// PREFIJO_TOKEN_CORE_FRAMEWORK/resolverValorConToken: espejo EXACTO de
	// Sofia_Estilo_Global::PREFIJO_TOKEN_CORE_FRAMEWORK/resolver_valor()
	// del lado PHP — necesario acá porque este script aplica el color EN
	// VIVO (feedback inmediato, antes de guardar/recargar), y el valor
	// crudo guardado ("cf:border-primary") NO es un color CSS válido por
	// sí solo: sin resolverlo a var(--border-primary), el navegador lo
	// ignora en silencio (bug real reportado por el usuario: "el color no
	// se aplica"). Nunca duplicar la lógica de emisión, solo espejarla acá
	// porque JS no puede llamar directo al método PHP.
	var PREFIJO_TOKEN_CORE_FRAMEWORK = "cf:";

	// esTokenConNombre: espejo EXACTO de
	// Sofia_Estilo_Global::es_token_con_nombre() del lado PHP — rechaza
	// "cf:" solo (sin nombre después del prefijo), que puede quedar en el
	// estado del drawer si el usuario activa el botón "CF" y cambia de
	// campo sin llegar a escribir un nombre. Sin esto, resolverValorConToken
	// devolvía "var(--)" — CSS inválido que el navegador descarta en
	// silencio, dejando la propiedad entera sin valor (bug real reportado
	// por el usuario: un color de texto que desaparecía por completo).
	function esTokenConNombre(valor) {
		return !!valor && valor.indexOf(PREFIJO_TOKEN_CORE_FRAMEWORK) === 0 && valor.length > PREFIJO_TOKEN_CORE_FRAMEWORK.length;
	}

	function resolverValorConToken(valor) {
		if (!esTokenConNombre(valor)) return valor && valor.indexOf(PREFIJO_TOKEN_CORE_FRAMEWORK) === 0 ? "" : valor || "";
		return "var(--" + valor.slice(PREFIJO_TOKEN_CORE_FRAMEWORK.length) + ")";
	}

	// Lee el estilo ya aplicado, PRIORIZANDO el data-attribute crudo
	// (data-sofia-estilo-color/-color_fondo, ver
	// Sofia_Componente::atributo_estilo()) sobre el.style.color — bug real
	// reportado por el usuario ("se borra al salir del modal"): el
	// navegador, al leer de vuelta el.style.color después de haber
	// aplicado var(--border-primary), devuelve el color YA COMPUTADO (un
	// rgb() resuelto de la cascada), nunca el string "cf:..." original.
	// Sin el data-attribute, el drawer perdía el modo token cada vez que
	// se reabría sobre el mismo campo.
	function valorDeEstiloOToken(el, atributoToken, propiedadStyle) {
		var crudo = el.getAttribute(atributoToken);
		return crudo || el.style[propiedadStyle] || "";
	}

	function estiloActualDe(el) {
		return {
			alineacion: el.style.textAlign || "",
			color: valorDeEstiloOToken(el, "data-sofia-estilo-color", "color"),
			tamano_fuente: valorDeEstiloOToken(el, "data-sofia-estilo-tamano_fuente", "fontSize"),
			tipo_fuente: claveDeFuente(el.style.fontFamily),
			negrita: el.style.fontWeight || "",
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
		el.style.color = resolverValorConToken(estilo.color);
		el.style.fontSize = resolverValorConToken(estilo.tamano_fuente);
		el.style.fontFamily = FUENTES_PERMITIDAS[estilo.tipo_fuente] || "";
		el.style.fontWeight = estilo.negrita || "";
		el.style.textShadow = estilo.sombra_texto || "";

		// data-attribute con el valor CRUDO — ver el comentario largo en
		// valorDeEstiloOToken(): sin esto, estiloActualDe() no podría
		// reconstruir el modo token la próxima vez que se abra el drawer
		// sobre este campo, dentro de la misma carga de página.
		if (esTokenConNombre(estilo.color)) {
			el.setAttribute("data-sofia-estilo-color", estilo.color);
		} else {
			el.removeAttribute("data-sofia-estilo-color");
		}
		if (esTokenConNombre(estilo.tamano_fuente)) {
			el.setAttribute("data-sofia-estilo-tamano_fuente", estilo.tamano_fuente);
		} else {
			el.removeAttribute("data-sofia-estilo-tamano_fuente");
		}

		notificarCambio(campo + "._estilo", estilo);
	}

	// Lee el estilo de BLOQUE (Nivel 2) ya aplicado a la <section> — mismo
	// criterio de "editar lo que ves" que estiloActualDe() (Nivel 1).
	// --sofia-columnas se lee vía getPropertyValue (no el objeto style
	// plano, que no expone custom properties de la misma forma que
	// propiedades CSS estándar).
	// PROPIEDADES_TOKEN_BLOQUE: mismo mapa {clave => [propiedad style, data-attribute]}
	// que Sofia_Componente::atributo_estilo_bloque() del lado PHP (array
	// $propiedades_con_token_de_bloque) — necesario para no repetir 4 veces
	// el mismo bloque if/else de "leer con fallback a data-attribute" /
	// "aplicar y marcar data-attribute si es token".
	var PROPIEDADES_TOKEN_BLOQUE = {
		color_fondo: ["backgroundColor", "data-sofia-estilo-color_fondo"],
		color_borde: ["borderColor", "data-sofia-estilo-color_borde"],
		radius: ["borderRadius", "data-sofia-estilo-radius"],
		sombra: ["boxShadow", "data-sofia-estilo-sombra"],
	};

	// CLASES_UTILITARIAS_BLOQUE: espejo EXACTO de
	// Sofia_Componente::CLASES_UTILITARIAS_BLOQUE del lado PHP — a
	// diferencia de PROPIEDADES_TOKEN_BLOQUE (un VALOR de estilo), acá cada
	// opción es una utility class YA COMPLETA de Core Framework; aplicarla
	// es agregarla al classList de la <section>, no escribir en su style.
	var CLASES_UTILITARIAS_BLOQUE = {
		max_width: { 10: "max-width-10", 20: "max-width-20", 30: "max-width-30", 40: "max-width-40", 50: "max-width-50", 60: "max-width-60", 70: "max-width-70", 80: "max-width-80", 90: "max-width-90", 100: "max-width-100", site: "max-site-width" },
		ancho: { 10: "width-10", 20: "width-20", 30: "width-30", 40: "width-40", 50: "width-50", 60: "width-60", 70: "width-70", 80: "width-80", 90: "width-90", full: "full-width", auto: "auto-width" },
		aspect_ratio: { 1: "aspect-1", "4-3": "aspect-4-3", "3-4": "aspect-3-4", "3-2": "aspect-3-2", "2-3": "aspect-2-3", "16-9": "aspect-16-9", "9-16": "aspect-9-16" },
		object_fit: { contain: "fit-contain", cover: "fit-cover", fill: "fit-fill" },
		z_index: { "-1": "z--1", 0: "z-0", 1: "z-1", 10: "z-10", 100: "z-100", 1000: "z-1000", 10000: "z-10000" },
		alineacion_bloque: { left: "self-left", center: "self-center", right: "self-right" },
		alineacion_contenido: { left: "items-left", center: "items-center", right: "items-right" },
		alineacion_vertical_contenido: { top: "items-top", middle: "items-middle", bottom: "items-bottom" },
	};

	// claveUtilitariaActual: recorre las clases posibles de UNA categoría
	// (ej. todas las de aspect_ratio) y devuelve el valor guardado ("16-9")
	// cuya clase CSS ("aspect-16-9") está presente en la sección — a lo
	// sumo una debería estarlo, porque alAplicarEstiloBloque() siempre
	// quita las demás de la misma categoría antes de agregar la nueva.
	function claveUtilitariaActual(seccion, opciones) {
		for (var valor in opciones) {
			if (seccion.classList.contains(opciones[valor])) return valor;
		}
		return "";
	}

	function estiloBloqueActualDe(seccion) {
		var estilo = {
			columnas: seccion.style.getPropertyValue("--sofia-columnas").trim() || "",
			// data-sofia-estilo-espaciado_vertical/offset_x guardan SIEMPRE
			// el crudo (token o fijo) — ver el comentario largo en
			// alAplicarEstiloBloque().
			espaciado_vertical: seccion.getAttribute("data-sofia-estilo-espaciado_vertical") || "",
			offset_x: seccion.getAttribute("data-sofia-estilo-offset_x") || "",
		};
		for (var clave in PROPIEDADES_TOKEN_BLOQUE) {
			var par = PROPIEDADES_TOKEN_BLOQUE[clave];
			estilo[clave] = valorDeEstiloOToken(seccion, par[1], par[0]);
		}
		for (var claveUtil in CLASES_UTILITARIAS_BLOQUE) {
			estilo[claveUtil] = claveUtilitariaActual(seccion, CLASES_UTILITARIAS_BLOQUE[claveUtil]);
		}
		return estilo;
	}

	// Aplica el estilo de bloque elegido en el drawer directo a la
	// <section> real — mismo patrón que alAplicarEstilo() (Nivel 1), pero
	// buscando por [data-sofia-bloque-id] en vez de [data-sofia-campo].
	function alAplicarEstiloBloque(id, estilo) {
		var seccion = document.querySelector('[data-sofia-bloque-id="' + id + '"]');
		if (!seccion) return;

		if (estilo.columnas) {
			seccion.style.setProperty("--sofia-columnas", estilo.columnas);
		} else {
			seccion.style.removeProperty("--sofia-columnas");
		}
		var espaciadoResuelto = resolverValorConToken(estilo.espaciado_vertical);
		seccion.style.paddingTop = espaciadoResuelto;
		seccion.style.paddingBottom = espaciadoResuelto;
		if (estilo.espaciado_vertical) {
			seccion.setAttribute("data-sofia-estilo-espaciado_vertical", estilo.espaciado_vertical);
		} else {
			seccion.removeAttribute("data-sofia-estilo-espaciado_vertical");
		}

		for (var clave in PROPIEDADES_TOKEN_BLOQUE) {
			var par = PROPIEDADES_TOKEN_BLOQUE[clave];
			var valorResuelto = resolverValorConToken(estilo[clave]);
			seccion.style[par[0]] = valorResuelto;
			if (clave === "color_borde") {
				// !!valorResuelto (no !!estilo[clave]) — un token "cf:" sin
				// nombre resuelve a "" (ver resolverValorConToken), y sin este
				// chequeo quedaba un border-style:solid/border-width:1px sin
				// ningún border-color real, un borde invisible pero presente.
				seccion.style.borderStyle = valorResuelto ? "solid" : "";
				seccion.style.borderWidth = valorResuelto ? "1px" : "";
			}
			if (esTokenConNombre(estilo[clave])) {
				seccion.setAttribute(par[1], estilo[clave]);
			} else {
				seccion.removeAttribute(par[1]);
			}
		}

		// Utility classes de Core Framework — quita TODAS las clases
		// posibles de cada categoría antes de agregar la elegida (nunca dos
		// clases de la misma categoría a la vez, ej. "aspect-1" y
		// "aspect-16-9" juntas no tendría sentido).
		for (var claveUtil in CLASES_UTILITARIAS_BLOQUE) {
			var opciones = CLASES_UTILITARIAS_BLOQUE[claveUtil];
			for (var valorOpcion in opciones) {
				seccion.classList.remove(opciones[valorOpcion]);
			}
			if (estilo[claveUtil] && opciones[estilo[claveUtil]]) {
				seccion.classList.add(opciones[estilo[claveUtil]]);
			}
		}

		// offset_x (Desplazamiento horizontal): a diferencia de las
		// propiedades de PROPIEDADES_TOKEN_BLOQUE, nunca se escribe en
		// seccion.style.transform acá — Muuri es quien controla ese
		// atributo (su propio transform:translate(x,y) de posición, ver
		// layoutNivelSuperiorConAlineacion). Acá se resuelve el valor
		// (fijo o token) a PÍXELES reales aplicándolo temporalmente a un
		// elemento invisible y leyendo getComputedStyle, y se guarda ese
		// número en data-sofia-offset-x-px — que layoutNivelSuperiorConAlineacion
		// lee y suma en el próximo layout() de abajo.
		//
		// data-sofia-estilo-offset_x guarda el valor CRUDO completo
		// (token O fijo, cualquiera) — a diferencia de color/color_fondo
		// (donde el crudo solo hace falta para un TOKEN, porque un color
		// fijo se puede releer tal cual de el.style.color), acá no hay
		// ningún "el.style.transform" del que leer de vuelta el string
		// original (Muuri lo pisa) — sin este data-attribute, reabrir el
		// drawer perdería el valor guardado incluso para un offset fijo.
		if (estilo.offset_x) {
			seccion.setAttribute("data-sofia-estilo-offset_x", estilo.offset_x);

			var medidor = document.createElement("div");
			medidor.style.position = "absolute";
			medidor.style.visibility = "hidden";
			medidor.style.transform = "translateX(" + resolverValorConToken(estilo.offset_x) + ")";
			document.body.appendChild(medidor);
			var tx = 0;
			var transformResuelto = window.getComputedStyle(medidor).transform;
			var match = transformResuelto && transformResuelto.match(/matrix\(([^)]+)\)/);
			if (match) {
				var valores = match[1].split(",").map(function (v) {
					return parseFloat(v.trim());
				});
				tx = valores.length >= 5 ? valores[4] : 0;
			}
			document.body.removeChild(medidor);
			if (tx) {
				seccion.setAttribute("data-sofia-offset-x-px", String(tx));
			} else {
				seccion.removeAttribute("data-sofia-offset-x-px");
			}
		} else {
			seccion.removeAttribute("data-sofia-estilo-offset_x");
			seccion.removeAttribute("data-sofia-offset-x-px");
		}

		notificarCambio(id + "._estilo_bloque", estilo);

		// Cambiar columnas/padding altera la altura real de la <section> —
		// mismo motivo que en alAgregarItemALista()/activarLineasInsertar():
		// el grid de nivel superior necesita refrescar sus dimensiones
		// cacheadas para no superponer el bloque siguiente.
		if (gridNivelSuperior) {
			gridNivelSuperior.refreshItems().layout();
		}
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
		if (datos.tipo === "sofia:aplicar-estilo-bloque") {
			alAplicarEstiloBloque(datos.id, datos.estilo);
			return;
		}
		if (datos.tipo === "sofia:eliminar-bloque") {
			// datos.id (no datos.indice) — ver el comentario largo en
			// alEliminarBloque() sobre el cambio de contrato de Fase 3.
			alEliminarBloque(datos.id);
			return;
		}
		if (datos.tipo === "sofia:eliminar-item-lista") {
			alEliminarItemDeLista(datos.campoLista, datos.indiceItem);
			return;
		}
		if (datos.tipo === "sofia:seleccionar-contenedor-padre") {
			alSeleccionarContenedorPadre(datos.containerId, datos.x, datos.y);
			return;
		}
		if (datos.tipo === "sofia:insertar-bloque-html") {
			alInsertarBloqueHTML(datos.html, datos.posicion, datos.containerId);
			return;
		}
		if (datos.tipo === "sofia:reemplazar-bloque-html") {
			alReemplazarBloqueHTML(datos.id, datos.html);
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
		// contenedorGridDe (Fase 3): indice YA NO asume siempre nivel
		// superior — ver el comentario largo ahí. El id/tipo del bloque
		// resaltado en sí no cambian, solo de dónde sale "indice".
		var grid = contenedorGridDe(seccion);
		window.parent.postMessage(
			{
				tipo: "sofia:bloque-resaltado",
				nombre: nombresBloque[tipo] || tipo,
				indice: grid.indice,
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
	// mandarMenuContextualDe(seccion, x, y, itemInfo) — arma y manda
	// "sofia:menu-contextual-bloque" para UNA <section> ya resuelta.
	// Extraído de alHacerClickDerecho (que sigue siendo el caller
	// principal, vía evento de mouse real) para que
	// alSeleccionarContenedorPadre (Fase 3, botón "Seleccionar
	// contenedor" del menú — ver MenuContextualBloque.jsx) pueda abrir el
	// MISMO menú apuntando al Container PADRE de un hijo, sin duplicar
	// toda esta lógica. Bug de UX real reportado por el usuario: sin este
	// mecanismo, no existía ninguna forma de apuntar al Container en sí
	// cuando tenía hijos adentro — cualquier click (derecho incluido) en
	// el área visible de un Container con contenido siempre resolvía
	// primero al HIJO bajo el cursor (evento.target.closest("section")
	// encuentra la <section> más cercana, nunca la del padre), así que
	// "Eliminar bloque" sobre el Container completo (con todo lo de
	// adentro) era inalcanzable desde la UI aunque el mecanismo de borrado
	// en sí ya lo soportara (ver alEliminarBloque, limpieza recursiva de
	// instanciasContainers).
	function mandarMenuContextualDe(seccion, x, y, itemInfo) {
		// contenedorGridDe (Fase 3) — el índice y el containerId ahora son
		// relativos al grid REAL que contiene a esta sección (nivel
		// superior o el .sofia-container que la envuelve), ver el
		// comentario largo en esa función. Sin esto, click derecho sobre
		// un hijo de un container mandaba un índice contado sobre
		// .sofia-pagina (que ni siquiera tiene a ese hijo como hijo
		// directo) — eliminar/reordenar ese bloque desde el menú
		// contextual hubiera actuado sobre el bloque EQUIVOCADO de nivel
		// superior, en la misma posición numérica por coincidencia.
		var grid = contenedorGridDe(seccion);

		// id/tipo/estilo del bloque — necesarios para "Estilo del bloque"
		// (Nivel 2): a diferencia de eliminar (que solo necesita la
		// POSICIÓN, indice), abrir el drawer de estilo necesita la clave
		// real "{id}._estilo_bloque" para guardar (ver
		// Sofia_Componente::atributo_estilo_bloque()) y el estilo YA
		// aplicado para que el drawer abra reflejando el estado real (mismo
		// criterio de "editar lo que ves" que el resto del editor) — se
		// manda directo acá, en vez de un roundtrip aparte cuando el
		// usuario elige la opción del menú.
		window.parent.postMessage(
			{
				tipo: "sofia:menu-contextual-bloque",
				indice: grid.indice,
				containerId: grid.containerId,
				id: seccion.getAttribute("data-sofia-bloque-id") || "",
				tipoBloque: seccion.getAttribute("data-sofia-bloque-tipo") || "",
				estiloBloque: estiloBloqueActualDe(seccion),
				item: itemInfo || null,
				x: x,
				y: y,
			},
			"*"
		);
	}

	function alHacerClickDerecho(evento) {
		var seccion = evento.target.closest ? evento.target.closest("section") : null;
		if (!seccion) return;
		evento.preventDefault();

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

		mandarMenuContextualDe(seccion, evento.clientX, evento.clientY, itemInfo);
	}

	// alHacerClickIzquierdo(evento) — paso 1 del rediseño de layout a 3
	// zonas fijas (ver la memoria de producto): un CLICK SIMPLE (no click
	// derecho) sobre el área de un bloque ya muestra su Nivel 2 (Estilo)
	// en la zona de Propiedades fija — mismo criterio que
	// "sofia:campo-clickeado" ya usa para Nivel 1 desde el día 1, ahora
	// extendido a bloques completos. Bug de UX real reportado por el
	// usuario probando en vivo: el menú contextual (click derecho) tapaba
	// el propio contenido que se estaba por editar, y era un paso extra
	// (click derecho > Estilo del bloque) para llegar a algo que ahora
	// puede ser un solo click.
	//
	// Reusa mandarMenuContextualDe() (mismo cálculo de grid/containerId/
	// estilo, ver ahí) pero manda "sofia:bloque-clickeado" en vez de
	// "sofia:menu-contextual-bloque" — nunca abre el menú flotante, solo
	// actualiza la zona de Propiedades.
	//
	// Ignora el click si cayó DENTRO de un campo editable
	// (data-sofia-campo) — un campo de texto/imagen ya tiene su propio
	// listener de click que manda "sofia:campo-clickeado" (Nivel 1); sin
	// este chequeo, cualquier click en un título/párrafo dispararía AMBOS
	// mensajes (el evento burbujea desde el campo hasta la <section> que
	// lo contiene), y Nivel 2 pisaría a Nivel 1 en la zona de Propiedades
	// justo cuando el usuario quería editar el TEXTO, no el bloque
	// completo. Mismo criterio para un item de lista repetible
	// (data-sofia-item) — ese click tiene su propio significado (foco en
	// el item, no en el bloque completo). Y para el propio handle de
	// arrastre/líneas de inserción, que ya manejan su click.
	function alHacerClickIzquierdo(evento) {
		if (evento.target.closest("[data-sofia-campo], [data-sofia-item], .sofia-handle-arrastre, .sofia-linea-insertar")) {
			return;
		}
		var seccion = evento.target.closest ? evento.target.closest("section") : null;
		if (!seccion) return;

		var grid = contenedorGridDe(seccion);
		window.parent.postMessage(
			{
				tipo: "sofia:bloque-clickeado",
				containerId: grid.containerId,
				id: seccion.getAttribute("data-sofia-bloque-id") || "",
				tipoBloque: seccion.getAttribute("data-sofia-bloque-tipo") || "",
				estiloBloque: estiloBloqueActualDe(seccion),
			},
			"*"
		);
	}

	// alSeleccionarContenedorPadre(containerId, x, y) — Fase 3: reabre el
	// menú contextual apuntando al Container PADRE (dado su id de
	// instancia, que el menú del hijo ya conocía vía "containerId" en
	// "sofia:menu-contextual-bloque") en vez de al bloque que el usuario
	// clickeó originalmente. x/y llegan del click en el botón del menú
	// (mismo criterio que activarLineasInsertar: coordenadas de PANTALLA,
	// no del documento del iframe) para que el menú reabierto aparezca
	// donde el usuario tiene el cursor, no en el origen del documento.
	function alSeleccionarContenedorPadre(containerId, x, y) {
		var seccionPadre = document.querySelector('[data-sofia-bloque-id="' + containerId + '"]');
		if (!seccionPadre) return;
		mandarMenuContextualDe(seccionPadre, x, y, null);
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

	// Inserta un bloque NUEVO recibido como HTML ya renderizado por PHP
	// (ver Sofia_REST_Editor::obtener_html_de_bloque(), pedido desde
	// agregarBloque() en App.jsx) — reemplaza el reload completo de página
	// que usaba antes: bug de UX real señalado por el usuario ("es una
	// experiencia algo molesta"), perdía scroll/estado del canvas por
	// agregar UNA sección. $posicion es el índice DENTRO del contenedor
	// destino (nivel superior o un container puntual, ver $containerId).
	//
	// template.content (no innerHTML de un <div>) — un <template> no
	// ejecuta scripts ni produce efectos secundarios al parsear, y
	// firstElementChild da directo el nodo <section> real sin envolverlo
	// en nada. Todo Componente del catálogo (Container incluido, desde
	// Fase 3 — ver el comentario largo en class-container.php sobre el
	// wrapper <section> exterior) renderiza su raíz como <section>, así
	// que este chequeo sigue siendo válido sin distinguir "es container o
	// no": para efectos de INSERCIÓN, un Container es una sección más.
	//
	// $containerId (nuevo en Fase 3): id del .sofia-container destino, o
	// null/ausente para nivel superior (".sofia-pagina" directo) — mismo
	// contrato que agrega App.jsx al mensaje "sofia:insertar-bloque-html"
	// (ver agregarBloque() ahí). Cuando hay containerId, la sección nueva
	// se inserta DENTRO del <div class="sofia-container"> de esa sección
	// (nunca directo en .sofia-pagina) y se registra contra la instancia
	// Muuri de ESE container (ver activarReordenarDentroDeContainers),
	// nunca contra gridNivelSuperior — insertar un ítem en el grid
	// equivocado dejaría a Muuri con un estado interno inconsistente con
	// el DOM real.
	function alInsertarBloqueHTML(html, posicion, containerId) {
		var contenedor = containerId
			? contenedorDeHijos(document.querySelector('[data-sofia-bloque-id="' + containerId + '"]'))
			: document.querySelector(".sofia-pagina");
		if (!contenedor) return;

		var plantilla = document.createElement("template");
		plantilla.innerHTML = html.trim();
		var seccionNueva = plantilla.content.firstElementChild;
		if (!seccionNueva || "SECTION" !== seccionNueva.tagName) return;

		var secciones = contenedor.querySelectorAll(":scope > section");
		var referencia = secciones[posicion] || null; // null → insertBefore(nodo, null) inserta al final, mismo comportamiento que "no hay siguiente".
		contenedor.insertBefore(seccionNueva, referencia);

		agregarHandleASeccion(seccionNueva);
		activarCamposEditables(seccionNueva);

		if (containerId) {
			// Dentro de un container: el grid dueño de este espacio es la
			// instancia de activarReordenarDentroDeContainers() para ESE
			// .sofia-container puntual, no gridNivelSuperior — ver el
			// comentario largo ahí sobre por qué cada container tiene su
			// propia instancia Muuri independiente.
			var instancia = instanciasContainers.get(contenedor);
			if (instancia) {
				instancia.add(seccionNueva, { index: posicion });
			}
		} else {
			// Sin esto, la sección nueva nunca queda observada — ver el
			// comentario largo junto a la declaración de
			// observerAlturaSecciones (arriba, cerca de gridNivelSuperior).
			// Solo aplica a nivel superior: observerAlturaSecciones está
			// atado 1:1 a gridNivelSuperior, un ítem dentro de un container
			// no necesita este mecanismo porque ese container (si está a
			// su vez a nivel superior) YA está observado como sección
			// propia, y su altura real (incluyendo hijos) es la que
			// gridNivelSuperior necesita, no la de cada hijo individual.
			if (observerAlturaSecciones) {
				observerAlturaSecciones.observe(seccionNueva);
			}

			if (gridNivelSuperior) {
				congelarOffsetXDe(seccionNueva); // ANTES de add() — ver el comentario largo en congelarOffsetXDe().
				// add() con el índice real: Muuri necesita saber DÓNDE en su
				// propio orden interno va el ítem nuevo, no solo agregarlo al
				// final — sin esto, el layout visual coincidiría con el DOM
				// pero el orden que reporta leerBloquesDeNivelSuperior() (que
				// lee el DOM, no Muuri) quedaría bien igual; se pasa el índice
				// de todos modos por claridad y por si Muuri lo necesita para
				// animar la inserción en la posición correcta.
				gridNivelSuperior.add(seccionNueva, { index: posicion });
			}
		}

		// La Franja de beneficios/Testimonios/FAQ tienen su propia lista
		// interna — activarReordenarListas() ya sabe ignorar contenedores
		// que ya tienen instancia (instanciasListas.has), así que llamarlo
		// de nuevo sobre TODO el documento es seguro y más simple que
		// filtrar manualmente solo la sección nueva. Mismo criterio para
		// activarReordenarDentroDeContainers() — si la sección nueva ES un
		// Container (vacío, recién insertado), necesita su propia
		// instancia Muuri para poder recibir hijos después; si no es
		// container, no encuentra ningún ".sofia-container" nuevo y no
		// hace nada.
		activarReordenarListas();
		activarReordenarDentroDeContainers();

		actualizarZIndexSecciones(contenedor);
		activarLineasInsertar();
		if (gridNivelSuperior) {
			gridNivelSuperior.refreshItems().layout();
		}
	}

	// Reemplaza una <section> EXISTENTE por su HTML actualizado (ver
	// Sofia_REST_Editor::obtener_html_de_bloque(), pedido desde
	// cambiarCondicionDrawer() en App.jsx al guardar Visibilidad) —
	// distinto de alInsertarBloqueHTML: acá el bloque YA estaba en la
	// página, solo cambió su marca de "oculto por condición"
	// (data-sofia-oculto-condicion, ver Sofia_Componente::atributos_seccion()).
	// Mismo motivo de UX que agregar/eliminar: evita recargar la página
	// ENTERA del iframe por actualizar UNA sección.
	//
	// Quita la sección vieja de Muuri PRIMERO (con removeElements:true,
	// que también la borra del DOM) y recién ahí inserta la nueva en el
	// MISMO índice — reusa alInsertarBloqueHTML en vez de duplicar la
	// lógica de "parsear + insertar + activar campos + agregar a Muuri".
	// Usa contenedorGridDe() (mismo criterio que
	// alEliminarBloque/alHacerClickDerecho/alMoverMouse) para resolver el
	// grid/índice/containerId REALES del bloque — bug real encontrado en
	// revisión de código: la versión original de Fase 3 asumía SIEMPRE
	// nivel superior acá, así que cambiar la condición de Visibilidad
	// (único caller, ver cambiarCondicionDrawer en App.jsx) de un bloque
	// DENTRO de un container no hacía nada (indice quedaba en -1, return
	// temprano silencioso, sin ningún error visible para el usuario).
	function alReemplazarBloqueHTML(id, html) {
		var seccionVieja = document.querySelector('[data-sofia-bloque-id="' + id + '"]');
		if (!seccionVieja) return;

		var grid = contenedorGridDe(seccionVieja);
		if (grid.indice < 0) return;
		var instanciaGrid = grid.containerId ? instanciasContainers.get(grid.contenedor) : gridNivelSuperior;
		if (!instanciaGrid) return;

		var itemViejo = instanciaGrid.getItems(seccionVieja);
		if (itemViejo.length) {
			instanciaGrid.remove(itemViejo, { removeElements: true });
		}

		alInsertarBloqueHTML(html, grid.indice, grid.containerId);
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
	// alEliminarBloque(id) — Fase 3 cambia el contrato de "indice" (posición
	// PLANA a nivel superior) a "id" (ID de instancia del bloque, ver
	// Sofia_Componente::atributos_seccion()): un índice plano dejó de ser
	// suficiente en cuanto un bloque puede vivir DENTRO de un container —
	// "eliminar el bloque en posición 2" es ambiguo sin saber además EN
	// QUÉ GRID (nivel superior o cuál container); identificar por ID
	// evita ese problema de raíz Y es más simple: mismo criterio que ya
	// usa el resto del sistema para identificar un bloque sin ambigüedad
	// (ver el comentario largo de Sofia_Componente::$id), en vez de sumar
	// un segundo parámetro "containerId" a la par de "indice" que
	// duplicaría lo que el ID ya resuelve solo.
	function alEliminarBloque(id) {
		var seccion = document.querySelector('[data-sofia-bloque-id="' + id + '"]');
		if (!seccion) return;

		// contenedorGridDe decide si esto es un bloque de nivel superior
		// (gridNivelSuperior) o un hijo de un container puntual (su propia
		// instancia en instanciasContainers) — remove() tiene que pedirse
		// SIEMPRE a la instancia Muuri DUEÑA real del ítem, nunca a
		// gridNivelSuperior a secas: pedirle a un grid que remueva un ítem
		// que no es suyo deja tanto al DOM como al estado interno de Muuri
		// inconsistentes (getItems() de un grid ajeno no encuentra nada,
		// pero tampoco avisa del error).
		var grid = contenedorGridDe(seccion);
		var instanciaGrid = grid.containerId ? instanciasContainers.get(grid.contenedor) : gridNivelSuperior;
		if (!instanciaGrid) return;

		// remove() de Muuri toma INSTANCIAS de Item (getItems(seccion)), no
		// el elemento DOM crudo — usar seccion.remove() a mano dejaría al
		// grid con una referencia interna a un item ya destruido, rompiendo
		// su estado. removeElements:true además borra el <section> del DOM
		// por nosotros. getItems(seccion) (el elemento, no un índice) —
		// más directo que recalcular el índice que ya usamos para
		// encontrar el grid dueño.
		var items = instanciaGrid.getItems(seccion);
		if (!items.length) return;
		instanciaGrid.remove(items, { removeElements: true });

		// Si el bloque eliminado ES un container (o contiene, en cualquier
		// profundidad, otros containers anidados adentro), sus instancias
		// Muuri propias (las que gobiernan SUS hijos, ver
		// activarReordenarDentroDeContainers) quedan con el elemento raíz ya
		// fuera del DOM (removeElements:true de arriba) pero siguen vivas en
		// instanciasContainers — bug real encontrado en revisión de código:
		// sin este cleanup, cada container eliminado deja una o más
		// instancias Muuri huérfanas que activarLineasInsertar() sigue
		// invocando (refreshItems/layout) indefinidamente en cada
		// reordenamiento/inserción posterior, acumulando memoria y
		// listeners fantasma en una sesión de edición larga. Recursivo
		// (querySelectorAll, no solo el hijo directo) porque un Container
		// puede tener otro Container anidado adentro — eliminar el de
		// afuera debe limpiar TODA la cadena, no solo el nivel inmediato.
		var containersInternos = seccion.querySelectorAll(".sofia-container");
		Array.prototype.forEach.call(containersInternos, function (contenedorInterno) {
			if (instanciasContainers.has(contenedorInterno)) {
				instanciasContainers.get(contenedorInterno).destroy();
				instanciasContainers.delete(contenedorInterno);
			}
		});

		seccionResaltada = null;
		window.parent.postMessage({ tipo: "sofia:bloque-sin-resaltar" }, "*");
		actualizarZIndexSecciones(grid.contenedor); // menos secciones ahora, recalcula el z-index de cada una.
		activarLineasInsertar(); // reconstruye posiciones tras el borrado.
		window.parent.postMessage(
			{
				tipo: "sofia:estructura-reordenada",
				bloques: leerBloquesDeNivelSuperior(document.querySelector(".sofia-pagina")),
			},
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

	// observerAlturaSecciones: mismo ResizeObserver que activarReordenar()
	// registra sobre las <section> presentes al cargar — variable de
	// módulo (no local a esa función) para poder sumarle también una
	// sección agregada DESPUÉS (ver alInsertarBloqueHTML), que de otro
	// modo nunca quedaba observada (bug real: el contenedor .sofia-pagina
	// se quedaba con su altura vieja, sin reflejar el bloque nuevo, y el
	// footer terminaba superpuesto sobre contenido real).
	var observerAlturaSecciones = null;

	// layoutNivelSuperiorConAlineacion: layout personalizado de Muuri (API
	// documentada: function(grid, layoutId, items, width, height, callback))
	// para gridNivelSuperior — reemplaza el layout default de "una columna,
	// x siempre en 0" por uno que respeta "Alineación del bloque" (Nivel 2,
	// ver CLASES_UTILITARIAS_BLOQUE.alineacion_bloque/self-left/-center/
	// -right) cuando el bloque tiene un Ancho/Ancho máximo menor al 100%.
	//
	// Bug real que esto resuelve: sin un layout custom, Ancho/Ancho máximo
	// SÍ reducían el tamaño visual del bloque dentro del editor (ver el fix
	// de especificidad en class-modo-editor.php), pero el bloque quedaba
	// siempre pegado a la izquierda — Muuri fija left:0/x:0 para todo item
	// en su layout default de una columna, ignorando cualquier margin:auto
	// que el CSS pudiera declarar (position:absolute no reacciona a margin
	// auto). Acá se calcula el X a mano: si el item tiene "self-center" o
	// "self-right", se centra/alinea a la derecha DENTRO del espacio
	// disponible real (el ancho del contenedor MENOS el margin-left:60px
	// fijo del handle, que sigue aplicando siempre — ver
	// class-modo-editor.php).
	//
	// itemMargin.left ya incluye ese margin-left:60px (Muuri lee el margin
	// CSS real de cada item) — nunca duplicar sumándolo de nuevo al X
	// calculado, solo usarlo para saber dónde empieza el espacio útil.
	//
	// data-sofia-offset-x-px (leído acá, escrito por congelarOffsetXInicial
	// más abajo): el offset EN PÍXELES ya resuelto, tomado UNA SOLA VEZ
	// ANTES de que Muuri tome control del transform del elemento — Muuri
	// fija su propio transform:translate(x,y) completo en cada layout
	// (element.style.transform es un único atributo, no se pueden componer
	// 2 transforms independientes ahí), así que leer el offset con
	// getComputedStyle DENTRO de esta función leería el transform que
	// MUURI ya puso (que incluye el x/y calculado), no el offset original
	// — el offset se sumaría de nuevo en cada recálculo, creciendo sin
	// control. El data-attribute es un snapshot inmutable tomado antes de
	// ese punto.
	function offsetXDesdeAtributo(el) {
		var valor = el.getAttribute("data-sofia-offset-x-px");
		return valor ? parseFloat(valor) || 0 : 0;
	}

	function layoutNivelSuperiorConAlineacion(grid, layoutId, items, width, height, callback) {
		var layout = { id: layoutId, items: items, slots: [], styles: {} };
		var y = 0;

		items.forEach(function (item) {
			var el = item.getElement();
			var itemMargin = item.getMargin();
			var itemWidth = item.getWidth();
			var itemHeight = item.getHeight();
			var espacioDisponible = width - itemMargin.left - itemMargin.right;
			var alineacion = claveUtilitariaActual(el, CLASES_UTILITARIAS_BLOQUE.alineacion_bloque);

			var x = 0; // default: pegado al margin-left fijo (comportamiento de siempre).
			if ("center" === alineacion) {
				x = Math.max(0, (espacioDisponible - itemWidth) / 2);
			} else if ("right" === alineacion) {
				x = Math.max(0, espacioDisponible - itemWidth);
			}
			x += offsetXDesdeAtributo(el); // se SUMA a la alineación, nunca la reemplaza — pedido explícito del usuario.

			layout.slots.push(x, y);
			y += itemHeight + itemMargin.top + itemMargin.bottom;
		});

		layout.styles.width = width + "px";
		layout.styles.height = y + "px";
		callback(layout);
	}

	// congelarOffsetXDe: lee el transform:translateX(...) que PHP pudo
	// haber emitido en el style="..." inicial de UNA <section> (ver
	// Sofia_Componente::atributo_estilo_bloque(), "offset_x") y lo
	// convierte a un número de píxeles YA RESUELTO (sea un valor fijo o un
	// token de Core Framework — getComputedStyle devuelve el resultado
	// final de la cascada en ambos casos), guardándolo en
	// data-sofia-offset-x-px ANTES de que Muuri tome control del transform
	// del elemento. Debe llamarse ANTES de instanciar Muuri (todas las
	// secciones de la carga inicial, ver activarReordenar) o ANTES de
	// gridNivelSuperior.add() (una sección insertada/reemplazada después,
	// ver alInsertarBloqueHTML) — nunca dentro del layout function (ver el
	// comentario largo ahí): para ese punto Muuri ya pisó el transform con
	// su propia posición calculada, y leerlo ahí sumaría el offset una y
	// otra vez en cada recálculo.
	function congelarOffsetXDe(seccion) {
		var transform = window.getComputedStyle(seccion).transform;
		if (!transform || "none" === transform) return;
		var match = transform.match(/matrix\(([^)]+)\)/);
		if (!match) return;
		var valores = match[1].split(",").map(function (v) {
			return parseFloat(v.trim());
		});
		var tx = valores.length >= 5 ? valores[4] : 0; // tx es el 5to valor de matrix(a,b,c,d,tx,ty).
		if (tx) seccion.setAttribute("data-sofia-offset-x-px", String(tx));
	}

	function congelarOffsetXInicial(contenedor) {
		contenedor.querySelectorAll(":scope > section").forEach(congelarOffsetXDe);
	}

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

	// Extraído de activarReordenar() para poder reusarlo sobre una
	// <section> insertada dinámicamente (ver alInsertarBloqueHTML) —
	// mismo handle, mismo título, un solo lugar que mantener.
	function agregarHandleASeccion(seccion) {
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
	}

	function activarReordenar() {
		var contenedor = document.querySelector(".sofia-pagina");
		if (!contenedor || typeof Muuri === "undefined" || gridNivelSuperior) return; // ya inicializado, evita doble-instancia.

		contenedor.querySelectorAll(":scope > section").forEach(agregarHandleASeccion);

		actualizarZIndexSecciones(contenedor);
		congelarOffsetXInicial(contenedor); // ANTES de instanciar Muuri — ver el comentario largo en esa función.

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
			dragStartPredicate: noArrastrarConBotonDerecho,
			// dragSortHeuristics: mecanismos anti-jitter que SortableJS no
			// tiene — sortInterval (pausa antes de reevaluar el orden),
			// minDragDistance (umbral mínimo antes de considerar cualquier
			// sort), atacan justo la clase de síntoma ya vivida
			// (reordenamiento errático con movimientos chicos).
			dragSortHeuristics: { sortInterval: 100, minDragDistance: 10 },
			dragSortPredicate: { threshold: 50, action: "move" },
			layout: layoutNivelSuperiorConAlineacion,
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
		//
		// observerAlturaSecciones queda en una variable de módulo (no
		// local a esta función) para poder observar TAMBIÉN una <section>
		// agregada dinámicamente después (ver alInsertarBloqueHTML) — bug
		// real encontrado en la práctica: activarReordenar() corre UNA
		// SOLA VEZ al cargar la página, así que un bloque insertado más
		// tarde nunca quedaba observado. El contenedor .sofia-pagina
		// (position:relative, sin height propio — todas sus <section> hijas
		// son position:absolute, que no aportan altura al padre en flujo
		// normal) dependía ENTERAMENTE de que Muuri fijara su altura real
		// vía este observer; sin él para el bloque nuevo, el contenedor se
		// quedaba con la altura vieja y el footer (fuera de .sofia-pagina,
		// en flujo normal del documento) quedaba pegado ahí, superpuesto
		// sobre el contenido real que Muuri sí había posicionado más abajo.
		if (typeof ResizeObserver !== "undefined") {
			observerAlturaSecciones = new ResizeObserver(function () {
				if (gridNivelSuperior) {
					gridNivelSuperior.refreshItems().layout();
				}
			});
			contenedor.querySelectorAll(":scope > section").forEach(function (seccion) {
				observerAlturaSecciones.observe(seccion);
			});
		}
	}

	// contenedorDeHijos: dado el <section> de un bloque, devuelve el
	// .sofia-container DIRECTO adentro (o null si este bloque no es un
	// container, o lo es pero está vacío) — un solo lugar que sabe cómo
	// bajar un nivel en el árbol, reusado por leerEstructura (lectura) y
	// por activarReordenarDentroDeContainers/activarLineasInsertar
	// (escritura/UI). ":scope > .sofia-container" (no
	// section.querySelector(".sofia-container") a secas) — un Container
	// anidado DENTRO de otro Container también tiene un
	// ".sofia-container" en algún descendiente más profundo (el de sus
	// propios hijos), ":scope >" evita agarrar ese de más abajo por
	// error; ver el comentario largo en class-container.php sobre por
	// qué el <div class="sofia-container"> vive envuelto en un <section>
	// propio en vez de que la <section> misma lleve esa clase.
	function contenedorDeHijos(seccion) {
		return seccion.querySelector(":scope > .sofia-container");
	}

	// contenedorGridDe: dada CUALQUIER <section> de bloque (nivel superior
	// O hija de un container), devuelve {contenedor, containerId, indice}
	// — el grid REAL que la gestiona y su posición DENTRO de ese grid.
	// Reusado por alMoverMouse/alHacerClickDerecho (Fase 3): antes de esta
	// fase, ambas funciones asumían SIEMPRE ".sofia-pagina" como
	// contenedor — correcto mientras todo bloque vivía a nivel superior,
	// pero un bloque hijo de un container tiene su propio índice DENTRO
	// del .sofia-container que lo contiene, nunca dentro de .sofia-pagina
	// (que ni siquiera lo tiene como hijo directo). closest(".sofia-container")
	// sobre el PADRE de la sección (nunca sobre la sección misma — una
	// sección que ES un container no debe confundirse con estar DENTRO de
	// uno) decide cuál de los dos casos aplica.
	function contenedorGridDe(seccion) {
		var padreContainer = seccion.parentElement ? seccion.parentElement.closest(".sofia-container") : null;
		if (padreContainer) {
			var seccionContainer = padreContainer.closest("section");
			return {
				contenedor: padreContainer,
				containerId: seccionContainer ? seccionContainer.getAttribute("data-sofia-bloque-id") : null,
				indice: Array.prototype.indexOf.call(padreContainer.querySelectorAll(":scope > section"), seccion),
			};
		}
		var contenedor = document.querySelector(".sofia-pagina");
		return {
			contenedor: contenedor,
			containerId: null,
			// :scope > section (no contenedor.children) — desde
			// activarLineasInsertar(), .sofia-pagina mezcla <section> con
			// .sofia-linea-insertar como hermanos — children daría un
			// índice desalineado con el que espera el resto del código.
			indice: contenedor ? Array.prototype.indexOf.call(contenedor.querySelectorAll(":scope > section"), seccion) : -1,
		};
	}

	// Lee {id, tipo, hijos?} de cada <section> de nivel superior (y, si es
	// un container, de sus hijos directos, RECURSIVO — un container puede
	// contener otro container) desde data-sofia-bloque-id/-tipo (ver
	// Sofia_Componente::atributos_seccion()) — reconstruye la estructura
	// completa tras un reordenamiento o una eliminación, en cualquier
	// profundidad. Mismo shape {id,tipo,hijos} que BloqueEstructuraPlantilla
	// del lado Go (ver internal/store/plantilla_pagina.go) — "hijos" SOLO
	// se agrega a un bloque cuando de verdad tiene al menos uno, para no
	// romper el shape plano {id,tipo} que el resto del código (y GoPress)
	// espera de un bloque simple.
	//
	// Manda el ID YA EXISTENTE de cada bloque (nunca uno nuevo): sin esto,
	// el servidor recibiría bloques "sin ID" en cada reordenamiento y les
	// asignaría IDs NUEVOS cada vez, perdiendo la asociación con el
	// contenido ya guardado de cada instancia — mismo criterio en
	// cualquier profundidad del árbol, no solo nivel superior.
	function leerBloquesDesde(secciones) {
		return Array.prototype.map
			.call(secciones, function (seccion) {
				var id = seccion.getAttribute("data-sofia-bloque-id");
				var tipo = seccion.getAttribute("data-sofia-bloque-tipo");
				if (!id || !tipo) return null;

				var bloque = { id: id, tipo: tipo };
				var contenedorHijos = contenedorDeHijos(seccion);
				if (contenedorHijos) {
					var hijos = leerBloquesDesde(contenedorHijos.querySelectorAll(":scope > section"));
					if (hijos.length) bloque.hijos = hijos;
				}
				return bloque;
			})
			.filter(Boolean);
	}

	function leerBloquesDeNivelSuperior(contenedor) {
		return leerBloquesDesde(contenedor.querySelectorAll(":scope > section"));
	}

	// crearLineaInsertar: fábrica de UNA línea "+" — extraída para
	// reusarse tanto a nivel superior como DENTRO de cada .sofia-container
	// (Fase 3). $containerId viaja en el mensaje "sofia:abrir-insertar-bloque"
	// (null/undefined = nivel superior, mismo criterio que el resto del
	// contrato de esta fase — ver leerBloquesDesde/alInsertarBloqueHTML) —
	// cambio de CONTRATO sobre la versión anterior del mensaje (que solo
	// mandaba "posicion" a secas), por eso App.jsx también necesitó
	// actualizarse para leer este campo nuevo.
	function crearLineaInsertar(posicion, ubicacion, containerId) {
		var linea = document.createElement("div");
		linea.className = "sofia-linea-insertar sofia-linea-insertar--" + ubicacion; // "arriba" | "abajo" | "vacio"
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
					containerId: containerId || null,
					x: rect.left + rect.width / 2,
					y: rect.top,
				},
				"*"
			);
		});
		linea.appendChild(boton);
		return linea;
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
	//
	// Fase 3 extiende esto para vivir TAMBIÉN dentro de cada
	// .sofia-container — mismo criterio de "línea DENTRO de la sección
	// que gestiona el grid correspondiente, nunca hermana suelta del
	// grid": acá el grid es la instancia de Muuri del container (ver
	// activarReordenarDentroDeContainers), así que las líneas de un
	// container van DENTRO de cada <section> hija de ESE
	// .sofia-container, exactamente el mismo patrón que a nivel superior,
	// solo que agregarLineasEnGrid() ahora es una función reusada dos
	// veces en vez de código inline.
	function agregarLineasEnGrid(contenedorGrid, containerId) {
		var secciones = contenedorGrid.querySelectorAll(":scope > section");

		if (!secciones.length) {
			// Container vacío: una sola línea "+" ocupando todo el espacio
			// disponible (el propio .sofia-container, con min-height:40px
			// vía CSS para que haya algo clickeable incluso sin ningún
			// hijo) — mismo botón "+", distinta clase de ubicación
			// ("vacio") para que el CSS lo estire en vez de pegarlo al
			// borde superior/inferior de una sección como "arriba"/"abajo".
			contenedorGrid.appendChild(crearLineaInsertar(0, "vacio", containerId));
			return;
		}

		secciones.forEach(function (seccion, indice) {
			// "arriba" en cada sección cubre "insertar antes de esta" —
			// la primera sección además necesita la línea de "arriba"
			// visible (ninguna otra sección la tapa desde encima).
			seccion.appendChild(crearLineaInsertar(indice, "arriba", containerId));
			if (indice === secciones.length - 1) {
				// "abajo" SOLO en la última sección — el resto ya cubre
				// "después de mí" con el "arriba" de la sección siguiente.
				seccion.appendChild(crearLineaInsertar(secciones.length, "abajo", containerId));
			}
		});
	}

	function activarLineasInsertar() {
		var contenedor = document.querySelector(".sofia-pagina");
		if (!contenedor) return;

		// Reconstruye TODAS las líneas desde cero cada vez que se llama —
		// más simple que actualizar posiciones a mano tras un
		// reordenamiento/agregado, y esta función es barata (unos pocos
		// elementos DOM por página). document (no solo $contenedor): las
		// líneas dentro de un .sofia-container también deben limpiarse
		// acá, sino se duplicarían en cada llamada posterior.
		document.querySelectorAll(".sofia-linea-insertar").forEach(function (linea) {
			linea.remove();
		});

		agregarLineasEnGrid(contenedor, null);

		// Un .sofia-container puede estar anidado dentro de otro — recorre
		// TODOS los que existan en el documento, cada uno con sus propias
		// líneas relativas a SU PROPIO contenido, identificado por el ID
		// del bloque Container que lo envuelve (ver el comentario largo en
		// class-container.php sobre por qué el ID vive en la <section>
		// exterior, no en el propio .sofia-container).
		document.querySelectorAll(".sofia-container").forEach(function (contenedorHijos) {
			var seccionContainer = contenedorHijos.closest("section");
			var containerId = seccionContainer ? seccionContainer.getAttribute("data-sofia-bloque-id") : null;
			if (!containerId) return;
			agregarLineasEnGrid(contenedorHijos, containerId);
		});

		// Bug real encontrado en la práctica: agregar las líneas cambia la
		// altura REAL de cada <section> (position:absolute — ver CSS), pero
		// Muuri ya había calculado/cacheado las alturas ANTES de este
		// cambio (en activarReordenar(), que corre primero). Sin refrescar
		// esas dimensiones, Muuri seguía posicionando el siguiente bloque
		// según la altura VIEJA, más chica — el último bloque terminaba
		// superpuesto sobre el anterior en vez de ir debajo.
		// refreshItems() releé las dimensiones reales, layout() reubica
		// todos los items con esos valores actualizados. Mismo refresco
		// para CADA instancia de container, no solo gridNivelSuperior —
		// mismo motivo, una línea nueva también cambia la altura de la
		// sección que la contiene dentro de un container.
		if (gridNivelSuperior) {
			gridNivelSuperior.refreshItems().layout();
		}
		instanciasContainers.forEach(function (instancia) {
			instancia.refreshItems().layout();
		});
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
				dragStartPredicate: noArrastrarConBotonDerecho,
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

	// instanciasContainers: registro de las instancias Muuri de CADA
	// .sofia-container presente en la página (Fase 3, "primitivas de
	// layout") — mismo patrón que instanciasListas (Nivel 2, sub-items),
	// pero para bloques-componente completos en vez de items de una lista
	// de datos. Indexado por el <div class="sofia-container"> mismo (el
	// contenedor de HIJOS, no la <section> exterior que lo envuelve — ver
	// class-container.php), consistente con instanciasListas (que también
	// indexa por el contenedor de items, no por el bloque padre).
	//
	// UNA instancia Muuri INDEPENDIENTE por container, sin "group"
	// compartido con gridNivelSuperior ni entre containers hermanos —
	// decisión de diseño explícita (ver el plan de esta fase): mover un
	// bloque ENTRE niveles (nivel superior ↔ dentro de un container, o
	// entre dos containers distintos) está FUERA DE ALCANCE. Sin un
	// "group.name" compartido, Muuri confina cada drag a su propio grid
	// automáticamente — mismo mecanismo que ya confirmó
	// activarReordenarListas() para listas de datos, acá aplicado a
	// bloques-componente.
	var instanciasContainers = new Map();

	// activarReordenarDentroDeContainers (Fase 3) — instancia Muuri
	// acotada a cada .sofia-container, items:"section" (a diferencia de
	// activarReordenarListas, que usa "[data-sofia-item]": acá los hijos
	// son BLOQUES-COMPONENTE completos, cada uno su propia <section> con
	// atributos_seccion() — mismo criterio de "sección completa" que
	// gridNivelSuperior, solo que acotado a un contenedor anidado en vez
	// de .sofia-pagina). Reusa agregarHandleASeccion (mismo handle visual
	// que un bloque de nivel superior — el usuario ya conoce qué significa
	// ese ícono) en vez de inventar un handle propio para bloques
	// anidados.
	function activarReordenarDentroDeContainers() {
		if (typeof Muuri === "undefined") return;

		document.querySelectorAll(".sofia-container").forEach(function (contenedorHijos) {
			if (instanciasContainers.has(contenedorHijos)) return; // ya tiene instancia, evita doble-inicialización.

			contenedorHijos.querySelectorAll(":scope > section").forEach(agregarHandleASeccion);
			actualizarZIndexSecciones(contenedorHijos);
			congelarOffsetXInicial(contenedorHijos);

			var instancia = new Muuri(contenedorHijos, {
				items: "section",
				dragEnabled: true,
				dragHandle: ".sofia-handle-arrastre",
				dragStartPredicate: noArrastrarConBotonDerecho,
				dragSortHeuristics: { sortInterval: 100, minDragDistance: 10 },
				dragSortPredicate: { threshold: 50, action: "move" },
				// Sin layout custom (a diferencia de gridNivelSuperior) —
				// "Alineación del bloque" (self-left/-center/-right) dentro
				// de un container es un caso de borde deliberadamente fuera
				// de alcance de esta fase: el layout default de Muuri (una
				// columna, x:0) alcanza para validar el mecanismo de
				// anidamiento en sí, mismo criterio de alcance acotado que
				// ya usó Fase 1/2 para Container.
			});

			instancia.on("dragEnd", function () {
				instancia.synchronize();
				actualizarZIndexSecciones(contenedorHijos);
				activarLineasInsertar();
				// Estructura COMPLETA recursiva (no solo la de este
				// container) — mismo mensaje que ya persiste
				// gridNivelSuperior/instanciasListas, App.jsx no necesita
				// saber que este cambio vino de un container anidado en vez
				// de nivel superior, solo persiste el árbol que recibe.
				window.parent.postMessage(
					{
						tipo: "sofia:estructura-reordenada",
						bloques: leerBloquesDeNivelSuperior(document.querySelector(".sofia-pagina")),
					},
					"*"
				);
			});

			instanciasContainers.set(contenedorHijos, instancia);

			// Mismo fix de ResizeObserver que activarReordenar()/
			// activarReordenarListas(): un cambio de altura DENTRO de un
			// hijo (ej. texto que crece a 2 líneas) necesita refrescar TRES
			// grids potencialmente afectados — el propio container, y
			// gridNivelSuperior (la <section> exterior del container
			// también puede haber cambiado de altura).
			if (typeof ResizeObserver !== "undefined") {
				var observerAlturaHijos = new ResizeObserver(function () {
					instancia.refreshItems().layout();
					if (gridNivelSuperior) {
						gridNivelSuperior.refreshItems().layout();
					}
				});
				contenedorHijos.querySelectorAll(":scope > section").forEach(function (seccion) {
					observerAlturaHijos.observe(seccion);
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
		document.addEventListener("click", alHacerClickIzquierdo);
		window.addEventListener("message", alRecibirMensajeDelPadre);
		activarReordenar();
		activarReordenarListas();
		activarReordenarDentroDeContainers();
		activarLineasInsertar();
	});
})();

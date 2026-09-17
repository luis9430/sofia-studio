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

		// offset_x (Desplazamiento horizontal): se aplica directo como
		// transform:translateX(...), mismo patrón que cualquier otra
		// propiedad de PROPIEDADES_TOKEN_BLOQUE. Nadie más escribe en
		// seccion.style.transform, así que no hace falta ningún mecanismo
		// de convivencia acá.
		seccion.style.transform = estilo.offset_x ? "translateX(" + resolverValorConToken(estilo.offset_x) + ")" : "";
		if (estilo.offset_x) {
			seccion.setAttribute("data-sofia-estilo-offset_x", estilo.offset_x);
		} else {
			seccion.removeAttribute("data-sofia-estilo-offset_x");
		}

		notificarCambio(id + "._estilo_bloque", estilo);
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
		if (datos.tipo === "sofia:mover-item-lista") {
			alMoverItemDeLista(datos.campoLista, datos.indiceItem, datos.posicion);
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
			return;
		}
		if (datos.tipo === "sofia:mover-bloque") {
			alMoverBloque(datos.id, datos.posicion);
			return;
		}
		if (datos.tipo === "sofia:resaltar-bloque-por-id") {
			alResaltarBloquePorId(datos.id);
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
	// resaltarSeccion(seccion): arma y manda "sofia:bloque-resaltado" para
	// UNA <section> ya resuelta — extraído de alMoverMouse (que sigue
	// siendo el caller principal, vía mouseover real) para que
	// alResaltarBloquePorId (panel de Estructura, ver PanelEstructura.jsx)
	// pueda disparar el mismo overlay sin pasar por un evento de mouse
	// real, mismo criterio que mandarMenuContextualDe/contenedorGridDe ya
	// usan para separar "resolver los datos de una sección" de "cómo se
	// disparó".
	function resaltarSeccion(seccion) {
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

		resaltarSeccion(seccion);
	}

	// alResaltarBloquePorId (paso 2 del rediseño de layout, panel de
	// Estructura): a diferencia de alMoverMouse (que resuelve la sección
	// bajo el CURSOR), acá no hay ningún evento de mouse real — el click
	// ocurrió en el árbol, fuera del iframe. scrollIntoView primero: un
	// bloque fuera del viewport actual del iframe no tendría sentido
	// resaltar sin antes traerlo a la vista. behavior:"auto" (instantáneo),
	// no "smooth" — con scroll animado, el getBoundingClientRect() de
	// resaltarSeccion() se dispararía a mitad de la animación, mandando un
	// rect que todavía no es la posición final del bloque.
	function alResaltarBloquePorId(id) {
		var seccion = document.querySelector('[data-sofia-bloque-id="' + id + '"]');
		if (!seccion) return;
		seccion.scrollIntoView({ block: "center", behavior: "auto" });
		resaltarSeccion(seccion);
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
	// en sí ya lo soportara (ver alEliminarBloque).
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
	// el item, no en el bloque completo). Y para las propias líneas de
	// inserción, que ya manejan su click.
	function alHacerClickIzquierdo(evento) {
		if (evento.target.closest("[data-sofia-campo], [data-sofia-item], .sofia-linea-insertar")) {
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
	// mecanismo que un reordenamiento. Quitar el elemento del DOM alcanza:
	// no hay ningún estado de grid paralelo que sincronizar.
	function alEliminarItemDeLista(campoLista, indiceItem) {
		var contenedorLista = document.querySelector('[data-sofia-lista="' + campoLista + '"]');
		var item = contenedorLista ? contenedorLista.querySelector(':scope > [data-sofia-item="' + indiceItem + '"]') : null;
		if (!item) return;

		item.remove();
		reindexarItemsDeLista(contenedorLista);
		notificarListaActualizada(contenedorLista);
	}

	// moverNodoAPosicion(contenedor, selector, nodo, posicion): mueve $nodo
	// (ya hijo DIRECTO de $contenedor, matcheado por $selector — mismo
	// criterio de "solo los hijos que de verdad cuentan" que
	// leerBloquesDesde/reindexarItemsDeLista, ignorando hermanos ajenos
	// como .sofia-linea-insertar) para que termine exactamente en el
	// índice $posicion, con la MISMA semántica que Array.prototype.splice
	// (sacar de origen, insertar en destino) — reusado por alMoverBloque y
	// alMoverItemDeLista, los dos casos donde un mensaje puntual "mover a
	// esta posición" reemplaza a un drag real sobre el canvas.
	//
	// insertBefore(nodo, referencia) por sí solo NO alcanza con la
	// referencia tomada del array actual sin más: como el propio nodo se
	// saca de su posición vieja al insertarlo, todo lo que quedaba
	// DESPUÉS de él se corre un lugar hacia atrás — insertar "antes de
	// items[posicion]" deja el nodo en posicion-1, no en posicion, cuando
	// se mueve hacia ADELANTE (origen < destino). Bug real detectado con
	// un smoke test antes de integrar esto: mover el primer item a
	// "posición 2" en [A,B,C,D] daba [B,A,C,D] (A en índice 1) en vez de
	// [B,C,A,D] (A en índice 2, el resultado correcto). Fix: cuando se
	// mueve hacia adelante, la referencia es el elemento que debe quedar
	// INMEDIATAMENTE DESPUÉS del destino final (items[posicion+1]), no el
	// que hoy ocupa esa posición.
	function moverNodoAPosicion(contenedor, selector, nodo, posicion) {
		var hermanos = Array.prototype.slice.call(contenedor.querySelectorAll(":scope > " + selector));
		var indiceActual = hermanos.indexOf(nodo);
		if (indiceActual === posicion) return; // ya está en esa posición, nada que hacer.

		var referencia = indiceActual < posicion ? hermanos[posicion + 1] || null : hermanos[posicion] || null;
		contenedor.insertBefore(nodo, referencia);
	}

	// alMoverItemDeLista (paso 3 del rediseño de layout, ver la memoria de
	// producto): reordena UN item de una lista repetible a $posicion —
	// mismo mecanismo que alMoverBloque, pero para un [data-sofia-item] en
	// vez de una <section> de nivel superior. El panel de Estructura
	// (PanelEstructura.jsx) suma los items de cada lista repetible como
	// nodos hijos de su bloque contenedor, mismo árbol que ya reordena
	// bloques — reemplaza el drag directo sobre el canvas que antes movía
	// estos items.
	function alMoverItemDeLista(campoLista, indiceItem, posicion) {
		var contenedorLista = document.querySelector('[data-sofia-lista="' + campoLista + '"]');
		var item = contenedorLista ? contenedorLista.querySelector(':scope > [data-sofia-item="' + indiceItem + '"]') : null;
		if (!item || !contenedorLista) return;

		moverNodoAPosicion(contenedorLista, "[data-sofia-item]", item, posicion);
		reindexarItemsDeLista(contenedorLista);
		notificarListaActualizada(contenedorLista);
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
	// falta, sería la vía de escape). Insertar en el DOM ya alcanza: no hay
	// ningún grid paralelo al que avisarle del elemento nuevo.
	function alAgregarItemALista(campoLista) {
		var contenedorLista = document.querySelector('[data-sofia-lista="' + campoLista + '"]');
		var ultimoItem = contenedorLista ? contenedorLista.querySelector(":scope > [data-sofia-item]:last-of-type") : null;
		if (!ultimoItem) return;

		var itemNuevo = ultimoItem.cloneNode(true);
		ultimoItem.after(itemNuevo);

		reindexarItemsDeLista(contenedorLista);
		activarCamposEditables(itemNuevo);
		notificarListaActualizada(contenedorLista);
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
	// (nunca directo en .sofia-pagina). Insertar en el DOM en la posición
	// correcta ya alcanza: no hay ningún grid paralelo al que registrar el
	// elemento nuevo.
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

		activarCamposEditables(seccionNueva);
		activarLineasInsertar();
	}

	// alMoverBloque (paso 2 del rediseño de layout a 3 zonas fijas, ver la
	// memoria de producto): reordena un bloque a $posicion SIN un drag real
	// del mouse — el panel de estructura/árbol de bloques (App.jsx) manda
	// este mensaje cuando el usuario arrastra un nodo del árbol, ya que ese
	// árbol vive FUERA del iframe y no tiene acceso al mouse real dentro de
	// él. Restricción de diseño vigente: solo reordena DENTRO del mismo
	// padre — el árbol nunca manda un
	// containerId destino distinto de donde el bloque ya está, así que
	// moverNodoAPosicion() (mismo contenedor de origen y destino) alcanza,
	// sin necesitar remove+insertar-HTML como si fuera un bloque nuevo.
	function alMoverBloque(id, posicion) {
		var seccion = document.querySelector('[data-sofia-bloque-id="' + id + '"]');
		if (!seccion) return;

		var grid = contenedorGridDe(seccion);
		moverNodoAPosicion(grid.contenedor, "section", seccion, posicion);
		activarLineasInsertar();
		window.parent.postMessage(
			{
				tipo: "sofia:estructura-reordenada",
				bloques: leerBloquesDeNivelSuperior(document.querySelector(".sofia-pagina")),
			},
			"*"
		);
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
	// Quita la sección vieja del DOM PRIMERO y recién ahí inserta la nueva
	// en el MISMO índice — reusa alInsertarBloqueHTML en vez de duplicar
	// la lógica de "parsear + insertar + activar campos". Usa
	// contenedorGridDe() (mismo criterio que
	// alEliminarBloque/alHacerClickDerecho/alMoverMouse) para resolver el
	// contenedor/índice/containerId REALES del bloque — bug real
	// encontrado en revisión de código: la versión original de Fase 3
	// asumía SIEMPRE nivel superior acá, así que cambiar la condición de
	// Visibilidad (único caller, ver cambiarCondicionDrawer en App.jsx) de
	// un bloque DENTRO de un container no hacía nada (indice quedaba en
	// -1, return temprano silencioso, sin ningún error visible para el
	// usuario).
	function alReemplazarBloqueHTML(id, html) {
		var seccionVieja = document.querySelector('[data-sofia-bloque-id="' + id + '"]');
		if (!seccionVieja) return;

		var grid = contenedorGridDe(seccionVieja);
		if (grid.indice < 0) return;

		seccionVieja.remove();
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

		// Quitar la sección del DOM alcanza: no hay ningún grid paralelo
		// cuyo estado interno mantener sincronizado, ni instancias
		// huérfanas que limpiar si el bloque eliminado era (o contenía) un
		// Container.
		seccion.remove();

		seccionResaltada = null;
		window.parent.postMessage({ tipo: "sofia:bloque-sin-resaltar" }, "*");
		activarLineasInsertar(); // reconstruye posiciones tras el borrado.
		window.parent.postMessage(
			{
				tipo: "sofia:estructura-reordenada",
				bloques: leerBloquesDeNivelSuperior(document.querySelector(".sofia-pagina")),
			},
			"*"
		);
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
	// DENTRO del iframe — el click solo abre el MISMO catálogo que ya
	// existe en el panel padre, pasando la posición exacta vía postMessage.
	//
	// La línea vive como HERMANA suelta de las <section> (ver CSS en
	// class-modo-editor.php). Que ensucie la lista de hijos no importa:
	// leerBloquesDesde ya filtra explícitamente por
	// data-sofia-bloque-id/-tipo, ignorando cualquier hermano que no lo
	// tenga.
	//
	// Se extiende igual dentro de cada .sofia-container — mismo criterio,
	// las líneas de un container van DENTRO de ese .sofia-container como
	// hermanas de sus <section> hijas, identificado por el ID del bloque
	// Container que lo envuelve.
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

		// Primera línea, ANTES de la primera sección — hermana suelta, ver
		// el comentario largo arriba.
		contenedorGrid.insertBefore(crearLineaInsertar(0, "arriba", containerId), secciones[0]);

		secciones.forEach(function (seccion, indice) {
			// Una línea DESPUÉS de cada sección cubre "insertar entre esta
			// y la siguiente" (o al final, para la última).
			var linea = crearLineaInsertar(indice + 1, "abajo", containerId);
			seccion.after(linea);
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
	}

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
		activarLineasInsertar();
	});
})();

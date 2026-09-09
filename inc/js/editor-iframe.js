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
 * La barra de formato flotante (negrita/cursiva) vive en el panel padre,
 * NUNCA acá — mismo patrón "controles fuera del documento del iframe"
 * validado contra Bricks/AEM (ver la memoria de producto). Este script
 * solo informa POSICIÓN de la selección vía postMessage; el padre dibuja
 * la barra y, al hacer click en un botón, manda un mensaje de vuelta
 * ("sofia:aplicar-formato") que este script ejecuta con
 * document.execCommand — el único lugar donde el DOM real del texto se
 * toca es acá, dentro del iframe.
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

	// Detecta selección de texto DENTRO de un elemento editable y avisa al
	// padre dónde dibujar la barra flotante — las coordenadas de
	// getBoundingClientRect() son relativas a ESTE documento (el del
	// iframe), por eso el padre las usa tal cual: un iframe con
	// posición fija a inset:0 hace que las coordenadas internas coincidan
	// con las del documento padre sin ningún offset que sumar.
	function alCambiarSeleccion() {
		var seleccion = window.getSelection();
		if (!seleccion || seleccion.isCollapsed || seleccion.rangeCount === 0) {
			elementoConSeleccion = null;
			window.parent.postMessage({ tipo: "sofia:seleccion-vacia" }, "*");
			return;
		}

		var nodo = seleccion.anchorNode;
		var elemento = nodo && nodo.nodeType === Node.TEXT_NODE ? nodo.parentElement : nodo;
		var campoEditable = elemento ? elemento.closest("[data-sofia-campo][contenteditable]") : null;
		if (!campoEditable) {
			elementoConSeleccion = null;
			return;
		}

		elementoConSeleccion = campoEditable;
		var rect = seleccion.getRangeAt(0).getBoundingClientRect();
		window.parent.postMessage(
			{
				tipo: "sofia:seleccion-texto",
				rect: { top: rect.top, left: rect.left, width: rect.width, bottom: rect.bottom },
			},
			"*"
		);
	}

	function alRecibirMensajeDelPadre(evento) {
		var datos = evento.data;
		if (!datos || datos.tipo !== "sofia:aplicar-formato" || !elementoConSeleccion) return;
		elementoConSeleccion.focus();
		document.execCommand(datos.comando, false, null);
	}

	document.addEventListener("DOMContentLoaded", function () {
		document.querySelectorAll("[data-sofia-campo]").forEach(function (el) {
			if (el.tagName === "IMG") {
				activarImagen(el);
			} else {
				activarTexto(el);
			}
		});

		document.addEventListener("selectionchange", alCambiarSeleccion);
		window.addEventListener("message", alRecibirMensajeDelPadre);
	});
})();

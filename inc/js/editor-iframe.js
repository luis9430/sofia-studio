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
 */
(function () {
	"use strict";

	function notificarCambio(campo, valor) {
		window.parent.postMessage({ tipo: "sofia:campo-editado", campo: campo, valor: valor }, "*");
	}

	function activarTexto(el) {
		var campo = el.getAttribute("data-sofia-campo");
		el.setAttribute("contenteditable", "true");
		el.addEventListener("blur", function () {
			notificarCambio(campo, el.innerText.trim());
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

	document.addEventListener("DOMContentLoaded", function () {
		document.querySelectorAll("[data-sofia-campo]").forEach(function (el) {
			if (el.tagName === "IMG") {
				activarImagen(el);
			} else {
				activarTexto(el);
			}
		});
	});
})();

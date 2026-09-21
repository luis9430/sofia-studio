/**
 * Comportamiento de los Componentes interactivos, para el SITIO PÚBLICO.
 *
 * Un solo archivo para todos los patrones, no uno por Componente: esa es
 * la regla 4 del contrato, y existe para evitar el spaghetti de
 * carousel.js + carousel-init.js + modal.js + modal-events.js que este
 * tipo de código genera si se lo deja crecer suelto. Carousel vive acá
 * por eso mismo: es el candidato natural a archivo propio, y justamente
 * por eso es el que confirma la regla.
 *
 * Cada patrón es una función independiente que se activa buscando su
 * propio atributo data-sofia-*. No hay estado compartido entre ellas ni
 * orden de inicialización que respetar: agregar un patrón nuevo es
 * agregar una función y llamarla en activarTodo().
 *
 * Nada de esto corre en el editor. El canvas es un espejo fiel del sitio
 * publicado, pero un Accordion que se cierra al clickearlo haría
 * imposible editar su contenido — ver el chequeo de MODO_EDITOR abajo.
 *
 * Accesibilidad: cada patrón mantiene sus atributos ARIA en sincronía con
 * el estado visual. No es adorno — sin aria-expanded, un lector de
 * pantalla anuncia un botón que no dice si abre o cierra, y sin
 * aria-selected las pestañas se leen como una lista de links sueltos.
 */
(function () {
	"use strict";

	// El editor carga la página con ?sofia_editor=1 (ver
	// Sofia_Modo_Editor::activo). Ahí el contenido tiene que estar todo
	// visible y quieto para poder editarlo: un panel plegado o una
	// pestaña oculta serían contenido inalcanzable.
	var MODO_EDITOR = window.location.search.indexOf("sofia_editor=1") !== -1;

	/**
	 * Tabs: una fila de pestañas y sus paneles.
	 *
	 * El estado vive en el DOM (aria-selected y el atributo hidden), no en
	 * una variable: así el patrón funciona aunque el HTML se reemplace
	 * entero, que es justo lo que hace el editor al re-renderizar un
	 * bloque.
	 */
	function activarTabs(raiz) {
		raiz.querySelectorAll("[data-sofia-tabs]").forEach(function (grupo) {
			var pestanas = Array.prototype.slice.call(grupo.querySelectorAll("[data-sofia-tab]"));
			var paneles = Array.prototype.slice.call(grupo.querySelectorAll("[data-sofia-panel]"));
			if (pestanas.length === 0) return;

			function mostrar(indice) {
				pestanas.forEach(function (pestana, i) {
					pestana.setAttribute("aria-selected", i === indice ? "true" : "false");
					// tabindex -1 en las no activas: el foco entra al grupo
					// una sola vez y se mueve entre pestañas con las
					// flechas, que es cómo se espera que funcione.
					pestana.setAttribute("tabindex", i === indice ? "0" : "-1");
				});
				paneles.forEach(function (panel, i) {
					panel.hidden = i !== indice;
				});
			}

			pestanas.forEach(function (pestana, i) {
				pestana.addEventListener("click", function () {
					mostrar(i);
				});
				pestana.addEventListener("keydown", function (evento) {
					var salto = evento.key === "ArrowRight" ? 1 : evento.key === "ArrowLeft" ? -1 : 0;
					if (!salto) return;
					evento.preventDefault();
					var siguiente = (i + salto + pestanas.length) % pestanas.length;
					mostrar(siguiente);
					pestanas[siguiente].focus();
				});
			});

			mostrar(0);
		});
	}

	/**
	 * Accordion: paneles plegables.
	 *
	 * Usa <details>/<summary> nativo, así que no necesita JS para abrir y
	 * cerrar — el navegador ya lo hace, y funciona incluso si este script
	 * no llega a cargar. Lo único que agrega esta función es el modo
	 * "uno a la vez": cerrar los hermanos al abrir uno.
	 */
	function activarAccordion(raiz) {
		raiz.querySelectorAll("[data-sofia-accordion='exclusivo']").forEach(function (grupo) {
			var paneles = Array.prototype.slice.call(grupo.querySelectorAll("details"));
			paneles.forEach(function (panel) {
				panel.addEventListener("toggle", function () {
					if (!panel.open) return;
					paneles.forEach(function (otro) {
						if (otro !== panel) otro.open = false;
					});
				});
			});
		});
	}

	/**
	 * Modal: un <dialog> nativo abierto por un disparador.
	 *
	 * <dialog> ya trae el cierre con Escape, el foco atrapado dentro y el
	 * fondo inerte — todo lo que una implementación a mano suele olvidar.
	 * Lo único que falta es abrirlo, porque a diferencia de <details> no
	 * hay forma declarativa de hacerlo.
	 */
	function activarModal(raiz) {
		raiz.querySelectorAll("[data-sofia-abre-modal]").forEach(function (disparador) {
			disparador.addEventListener("click", function (evento) {
				evento.preventDefault();
				var id = disparador.getAttribute("data-sofia-abre-modal");
				var modal = document.getElementById(id);
				if (modal && typeof modal.showModal === "function") modal.showModal();
			});
		});

		raiz.querySelectorAll("[data-sofia-cierra-modal]").forEach(function (boton) {
			boton.addEventListener("click", function () {
				var modal = boton.closest("dialog");
				if (modal) modal.close();
			});
		});
	}

	/**
	 * Dropdown: un menú que se despliega.
	 *
	 * Otra vez <details>/<summary>: abre y cierra sin JS, con su
	 * accesibilidad y su teclado ya resueltos. Lo único que el HTML no da
	 * es cerrarlo al clickear afuera, que es lo que un menú necesita para
	 * no quedar abierto mientras se navega el resto de la página.
	 *
	 * Un solo listener en el documento, no uno por menú: el patrón
	 * "clickeaste fuera de mí" se resuelve mirando dónde cayó el click,
	 * no escuchando a cada elemento.
	 */
	function activarDropdown(raiz) {
		if (raiz !== document) return;
		document.addEventListener("click", function (evento) {
			document.querySelectorAll("[data-sofia-dropdown][open]").forEach(function (menu) {
				if (!menu.contains(evento.target)) menu.open = false;
			});
		});
		// Escape cierra el menú enfocado, igual que haría un <dialog>.
		document.addEventListener("keydown", function (evento) {
			if (evento.key !== "Escape") return;
			document.querySelectorAll("[data-sofia-dropdown][open]").forEach(function (menu) {
				menu.open = false;
			});
		});
	}

	/**
	 * Carousel: diapositivas con scroll-snap.
	 *
	 * El desplazamiento NO lo hace esta función — lo hace el navegador,
	 * porque la pista es un contenedor con overflow y scroll-snap. Acá
	 * solo se empuja el scroll con scrollTo() y se lee de vuelta con
	 * scrollLeft para saber en qué diapositiva quedó.
	 *
	 * Esa asimetría es deliberada: si el estado viviera en una variable
	 * "indiceActual", se desincronizaría apenas el usuario arrastrara con
	 * el dedo, que es algo que pasa sin avisarle a este código. Leer la
	 * posición real del scroll es la única fuente que no puede mentir.
	 */
	function activarCarousel(raiz) {
		raiz.querySelectorAll("[data-sofia-carousel]").forEach(function (carrusel) {
			var pista = carrusel.querySelector(".sofia-carousel__pista");
			var slides = Array.prototype.slice.call(carrusel.querySelectorAll("[data-sofia-slide]"));
			var puntos = Array.prototype.slice.call(carrusel.querySelectorAll("[data-sofia-carousel-punto]"));
			var anterior = carrusel.querySelector("[data-sofia-carousel-anterior]");
			var siguiente = carrusel.querySelector("[data-sofia-carousel-siguiente]");
			if (!pista || slides.length === 0) return;

			// Cuál está a la vista = cuál arranca más cerca del borde
			// izquierdo de la pista. Con varias visibles a la vez, esa es
			// la primera de las visibles, que es la que corresponde
			// marcar en los puntos.
			function indiceVisible() {
				var mejor = 0;
				var menor = Infinity;
				slides.forEach(function (slide, i) {
					var distancia = Math.abs(slide.offsetLeft - pista.scrollLeft);
					if (distancia < menor) {
						menor = distancia;
						mejor = i;
					}
				});
				return mejor;
			}

			function irA(indice) {
				var destino = slides[Math.max(0, Math.min(indice, slides.length - 1))];
				if (destino) pista.scrollTo({ left: destino.offsetLeft, behavior: "smooth" });
			}

			function sincronizar() {
				var actual = indiceVisible();
				puntos.forEach(function (punto, i) {
					punto.setAttribute("aria-current", i === actual ? "true" : "false");
				});
				// Los extremos se deshabilitan en vez de envolver: un
				// carrusel que vuelve al principio sin avisar hace perder
				// el lugar, y con scroll real el rebote se ve raro.
				if (anterior) anterior.disabled = pista.scrollLeft <= 1;
				if (siguiente) {
					siguiente.disabled = pista.scrollLeft + pista.clientWidth >= pista.scrollWidth - 1;
				}
			}

			if (anterior) {
				anterior.addEventListener("click", function () {
					irA(indiceVisible() - 1);
				});
			}
			if (siguiente) {
				siguiente.addEventListener("click", function () {
					irA(indiceVisible() + 1);
				});
			}
			puntos.forEach(function (punto, i) {
				punto.addEventListener("click", function () {
					irA(i);
				});
			});

			// El scroll dispara muy seguido; requestAnimationFrame alcanza
			// para no recalcular más veces de las que la pantalla dibuja.
			var pendiente = false;
			pista.addEventListener("scroll", function () {
				if (pendiente) return;
				pendiente = true;
				window.requestAnimationFrame(function () {
					pendiente = false;
					sincronizar();
				});
			});

			sincronizar();
		});
	}

	/**
	 * Efectos de entrada: que un bloque aparezca al llegar a pantalla.
	 *
	 * IntersectionObserver nativo, no GSAP. El proyecto tiene GSAP
	 * declarado y disponible, pero para "avisame cuando esto entra en
	 * pantalla" el navegador ya trae la herramienta exacta — sumar 70 KB
	 * de librería para eso sería peso sin beneficio. GSAP se justifica
	 * cuando haga falta coreografiar varias animaciones en el tiempo, que
	 * no es este caso.
	 *
	 * El estado inicial invisible lo pone ESTE script (la clase
	 * --armado), nunca el CSS. Si el CSS dejara los bloques en opacity:0
	 * y el script fallara en cargar, el contenido quedaría invisible para
	 * siempre. Así, sin JS no hay animación y todo se ve — que es el peor
	 * caso aceptable.
	 *
	 * Se desconecta el observer después de disparar: la animación es de
	 * entrada, no un ida y vuelta cada vez que se scrollea.
	 */
	function activarEntradas(raiz) {
		var bloques = Array.prototype.slice.call(
			raiz.querySelectorAll(".sofia-fx-aparece, .sofia-fx-desde-abajo")
		);
		if (bloques.length === 0) return;

		// Sin IntersectionObserver (navegador viejo) el contenido se ve
		// normalmente: no se arma nada y no hay nada que revelar.
		if (typeof window.IntersectionObserver !== "function") return;

		var observer = new IntersectionObserver(
			function (entradas) {
				entradas.forEach(function (entrada) {
					if (!entrada.isIntersecting) return;
					entrada.target.classList.add("sofia-fx--listo");
					observer.unobserve(entrada.target);
				});
			},
			// El margen negativo abajo hace que dispare cuando el bloque
			// ya entró de verdad, no apenas asoma un pixel.
			{ rootMargin: "0px 0px -10% 0px", threshold: 0.05 }
		);

		bloques.forEach(function (bloque) {
			bloque.classList.add("sofia-fx--armado");
			observer.observe(bloque);
		});
	}

	function activarTodo(raiz) {
		activarTabs(raiz);
		activarAccordion(raiz);
		activarModal(raiz);
		activarDropdown(raiz);
		activarCarousel(raiz);
		activarEntradas(raiz);
	}

	if (MODO_EDITOR) return;

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", function () {
			activarTodo(document);
		});
	} else {
		activarTodo(document);
	}
})();

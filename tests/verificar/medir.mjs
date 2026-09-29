/**
 * Capa MEDIBLE del arnés: lo que se puede afirmar con certeza.
 *
 *     node tests/verificar/medir.mjs
 *
 * Abre cada caso de tests/verificar/salida/ en tres anchos, corre nueve
 * chequeos deterministas y deja:
 *   - capturas/{caso}--{ancho}.png   para la capa interpretable
 *   - medidas.json                   los hallazgos, con severidad
 *
 * Por qué esta capa va PRIMERO y separada del juicio: un desborde es un
 * número, no una opinión. Si se lo pregunto a un modelo puede decir que
 * no lo ve; getBoundingClientRect() no se equivoca. Y al filtrar acá lo
 * duro, el juez no gasta atención en decir "esto desborda" — queda libre
 * para lo que solo él puede ver (jerarquía, ritmo, foco).
 *
 * Cuando una medición y un juicio se contradigan, MANDA LA MEDICIÓN.
 * El juicio queda como nota.
 */

import { chromium } from "playwright";
import { readFile, writeFile, mkdir, rm } from "node:fs/promises";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const AQUI = dirname(fileURLToPath(import.meta.url));

// La carpeta a medir se puede pasar como argumento: "salida" (las piezas
// hechas a mano, el default) o "salida-ia" (las páginas que genera la IA).
// La misma vara para las dos — si el generador se midiera con chequeos
// distintos, comparar los resultados no querría decir nada.
const CARPETA = process.argv[2] || "salida";
const SALIDA = join(AQUI, CARPETA);
const CAPTURAS = join(AQUI, CARPETA === "salida" ? "capturas" : "capturas-" + CARPETA.replace(/^salida-/, ""));
const ARCHIVO_MEDIDAS = CARPETA === "salida" ? "medidas.json" : "medidas-" + CARPETA.replace(/^salida-/, "") + ".json";

/** Mínimo de WCAG AA para texto normal. */
const CONTRASTE_MINIMO = 4.5;
/** Mínimo táctil recomendado (WCAG 2.5.5 / iOS HIG). */
const TOQUE_MINIMO = 44;

/**
 * Los nueve chequeos, evaluados DENTRO de la página.
 *
 * Va como un string para `page.evaluate`: necesita el DOM real con los
 * estilos ya computados, que es la única forma de medir lo que el
 * navegador efectivamente dibujó en vez de lo que el CSS pedía.
 */
const CHEQUEOS = ({ contrasteMinimo, toqueMinimo }) => {
  const hallazgos = [];
  const agregar = (regla, severidad, mensaje, elemento = "") =>
    hallazgos.push({ regla, severidad, mensaje, elemento });

  /**
   * Un selector corto y legible para señalar el elemento en el reporte.
   *
   * getAttribute y no .className: en SVG, className es un
   * SVGAnimatedString y toString() devuelve "[object SVGAnimatedString]".
   */
  const senalar = (el) => {
    if (!el || !el.tagName) return "";
    const clase = (el.getAttribute("class") || "").trim().split(/\s+/)[0];
    return el.tagName.toLowerCase() + (clase ? "." + clase : "");
  };

  /**
   * Está DENTRO de un <svg> (el <svg> mismo no cuenta).
   *
   * closest("svg") sobre el propio <svg> se devuelve a sí mismo, así que
   * se pregunta por el padre: el <svg> ocupa lugar en el layout y hay que
   * medirlo; sus <path> son dibujo.
   */
  const enSvg = (el) => !!(el.parentElement && el.parentElement.closest("svg"));

  const visible = (el) => {
    const e = getComputedStyle(el);
    if (e.display === "none" || e.visibility === "hidden" || e.opacity === "0") return false;
    const r = el.getBoundingClientRect();
    return r.width > 0 && r.height > 0;
  };

  // El interior de un <svg> queda fuera: sus <path> se cruzan entre sí por
  // definición (es un dibujo) y no tienen ni texto ni caja que medir. El
  // <svg> en sí SÍ se mide, porque ocupa lugar en el layout.
  const todos = [...document.querySelectorAll("body *")].filter(
    (el) => visible(el) && !enSvg(el)
  );
  const ancho = document.documentElement.clientWidth;

  // --- 1. Desborde horizontal de la página ---------------------------------
  // El síntoma que el usuario ve como "aparece scroll horizontal".
  const scrollPagina = document.documentElement.scrollWidth;
  if (scrollPagina > ancho + 1) {
    agregar(
      "desborde-pagina",
      "error",
      `la página scrollea ${scrollPagina - ancho}px de más (scrollWidth ${scrollPagina} vs ${ancho})`
    );
  }

  // --- 2. Elemento que sale de la ventana ----------------------------------
  // Se reporta solo el culpable más ancho por rama para no listar al
  // elemento y a sus 6 padres diciendo lo mismo.
  const fuera = [];
  for (const el of todos) {
    const r = el.getBoundingClientRect();
    if (r.right > ancho + 1 || r.left < -1) {
      fuera.push({ el, exceso: Math.round(Math.max(r.right - ancho, -r.left)) });
    }
  }
  const culpables = fuera.filter(({ el }) => !fuera.some((o) => o.el !== el && o.el.contains(el)));
  for (const { el, exceso } of culpables.slice(0, 5)) {
    agregar("elemento-fuera", "error", `se sale ${exceso}px de la ventana`, senalar(el));
  }

  // --- 3. Texto cortado ----------------------------------------------------
  // Solo donde hay overflow oculto: si el texto desborda un contenedor que
  // permite scroll, se puede leer; si está oculto, se perdió.
  for (const el of todos) {
    const e = getComputedStyle(el);
    const oculto = e.overflow === "hidden" || e.overflowY === "hidden";
    if (!oculto) continue;
    if (el.scrollHeight > el.clientHeight + 2 && el.textContent.trim()) {
      agregar(
        "texto-cortado",
        "error",
        `contenido cortado: ${el.scrollHeight - el.clientHeight}px no se ven`,
        senalar(el)
      );
    }
  }

  // --- 4. Contraste ---------------------------------------------------------
  const canal = (c) => {
    const s = c / 255;
    return s <= 0.03928 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4);
  };
  const luminancia = ([r, g, b]) => 0.2126 * canal(r) + 0.7152 * canal(g) + 0.0722 * canal(b);
  /**
   * Lee un color computado a [0-255].
   *
   * Chromium devuelve DOS formatos y hay que distinguirlos: "rgb(28, 26,
   * 23)" en 0-255, y "color(srgb 0.75 0.74 0.74)" en 0-1 cuando el valor
   * salió de un color-mix() — que es exactamente lo que usan todos los
   * tonos oscuros del tema. Leer el segundo como 0-255 da contrastes
   * falsos de 1.2:1 sobre negro.
   */
  const aRgb = (css) => {
    if (!css) return null;
    const t = css.trim();

    // Hex: getComputedStyle de una propiedad de color siempre devuelve
    // rgb(), pero getPropertyValue de una CUSTOM PROPERTY devuelve el
    // literal tal como se escribió — y el tema declara sus tokens en hex.
    // Sin esta rama, el regex numérico de abajo lee "#1c1a17" como los
    // números 1, 1 y 17, y la paleta entera queda mal.
    const hex = t.match(/^#([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i);
    if (hex) {
      let h = hex[1];
      if (h.length <= 4) h = [...h].map((c) => c + c).join("");
      const n = [0, 2, 4].map((i) => parseInt(h.slice(i, i + 2), 16));
      const a = h.length === 8 ? parseInt(h.slice(6, 8), 16) / 255 : 1;
      return { rgb: n, alfa: a };
    }

    const m = t.match(/[\d.]+(?:e-?\d+)?/g);
    if (!m || m.length < 3) return null;
    const esFraccion = /^color\(/.test(t);
    const nums = m.map(Number);
    // color(srgb r g b / a) — el alfa es el cuarto si está.
    const rgb = nums.slice(0, 3).map((n) => (esFraccion ? n * 255 : n));
    const alfa = nums.length > 3 ? nums[3] : 1;
    return { rgb, alfa };
  };
  const mezclar = (frente, fondo, alfa) => frente.map((c, i) => c * alfa + fondo[i] * (1 - alfa));

  /**
   * El fondo efectivo detrás de un elemento.
   *
   * Se juntan las capas semitransparentes DE ARRIBA HACIA ABAJO y se
   * compositan al revés, de abajo hacia arriba: el fondo de una capa
   * traslúcida es lo que haya debajo, no lo que haya encima. Componer en
   * el orden equivocado da blanco sobre blanco.
   */
  const fondoDe = (el) => {
    const capas = [];
    let capa = el;
    while (capa) {
      const c = aRgb(getComputedStyle(capa).backgroundColor);
      if (c && c.alfa > 0) {
        capas.push(c);
        if (c.alfa >= 1) break;
      }
      capa = capa.parentElement;
    }
    // Sin ninguna capa opaca, el lienzo del navegador es blanco.
    let fondo = capas.length && capas[capas.length - 1].alfa >= 1 ? capas.pop().rgb : [255, 255, 255];
    // De la más profunda a la más superficial.
    for (const c of capas.reverse()) {
      fondo = mezclar(c.rgb, fondo, c.alfa);
    }
    return fondo;
  };

  const razon = (a, b) => {
    const [l1, l2] = [luminancia(a), luminancia(b)].sort((x, y) => y - x);
    return (l1 + 0.05) / (l2 + 0.05);
  };

  /** Solo elementos con texto PROPIO: si no, se mide al padre por cada hijo. */
  const conTextoPropio = todos.filter((el) =>
    [...el.childNodes].some((n) => n.nodeType === 3 && n.textContent.trim())
  );

  /**
   * ¿Hay una imagen de fondo detrás? Entonces el contraste no se puede
   * medir: depende de la foto que ponga el usuario.
   *
   * Importa porque Hero inmersivo y Header transparente ponen texto blanco
   * pensado para ir sobre una imagen. Sin imagen el medidor ve blanco sobre
   * blanco y reporta 1:1 — cierto, pero no es un defecto de color sino la
   * pieza usada sin lo que necesita. Se reporta como nota aparte.
   */
  const sobreImagen = (el) => {
    let capa = el;
    while (capa) {
      const e = getComputedStyle(capa);
      if (e.backgroundImage && e.backgroundImage !== "none") return true;

      // Un hermano apilado en la MISMA celda de grid: es el patrón de
      // "texto encima de imagen" (Hero inmersivo, Header transparente).
      // Se detecta por la celda y no por un <img>, porque la pieza sin
      // imagen configurada no emite ninguno — y es justo ese caso el que
      // produce el blanco-sobre-blanco que hay que reportar como nota.
      const padre = capa.parentElement;
      if (padre && getComputedStyle(padre).display.includes("grid")) {
        const miCelda = e.gridArea;
        const apilado = [...padre.children].some(
          (h) => h !== capa && getComputedStyle(h).gridArea === miCelda
        );
        if (apilado && miCelda && miCelda !== "auto") return true;
      }

      // Una pieza de nivel raíz sin fondo propio Y con texto claro está
      // hecha para ir sobre algo oscuro que la página pone debajo — el
      // Header transparente va encima del Hero.
      //
      // Las dos condiciones juntas, no solo la primera: la mayoría de las
      // piezas no declara fondo (heredan el blanco del body), así que
      // exentar todo lo que no tenga fondo propio silenciaría contrastes
      // reales. El texto claro es lo que delata la intención.
      if (capa.parentElement === document.body) {
        const fondoPropio = aRgb(e.backgroundColor);
        const sinFondo = !fondoPropio || fondoPropio.alfa < 1;
        const colorPieza = aRgb(e.color);
        const textoClaro = colorPieza && luminancia(colorPieza.rgb) > 0.5;
        if (sinFondo && textoClaro) return true;
      }

      capa = capa.parentElement;
    }
    return false;
  };

  const yaVisto = new Set();
  for (const el of conTextoPropio) {
    const e = getComputedStyle(el);
    const texto = aRgb(e.color);
    if (!texto) continue;
    const fondo = fondoDe(el);
    const frente = texto.alfa < 1 ? mezclar(texto.rgb, fondo, texto.alfa) : texto.rgb;
    const r = razon(frente, fondo);

    // Texto grande tiene un umbral más bajo en WCAG (3:1): 24px normal o
    // 18.66px en negrita.
    const px = parseFloat(e.fontSize);
    const peso = parseInt(e.fontWeight, 10) || 400;
    const grande = px >= 24 || (px >= 18.66 && peso >= 700);
    const minimo = grande ? 3 : contrasteMinimo;

    if (r < minimo) {
      const clave = senalar(el) + Math.round(r * 10);
      if (yaVisto.has(clave)) continue;
      yaVisto.add(clave);

      // Sobre imagen no se puede afirmar nada: el contraste real depende de
      // la foto. Queda como nota para que la capa interpretable lo mire.
      const conImagen = sobreImagen(el);
      agregar(
        conImagen ? "contraste-sobre-imagen" : "contraste",
        conImagen ? "nota" : "error",
        conImagen
          ? `texto pensado para ir sobre imagen; sin imagen queda en ${r.toFixed(2)}:1`
          : `contraste ${r.toFixed(2)}:1, mínimo ${minimo} (texto ${e.color} sobre rgb(${fondo
              .map(Math.round)
              .join(",")}), ${Math.round(px)}px)`,
        senalar(el)
      );
    }
  }

  // --- 5. Área de toque ----------------------------------------------------
  // Solo en teléfono: en escritorio se apunta con el mouse y 44px no aplica.
  //
  // Se exige el mínimo en AMBAS dimensiones solo cuando el control es
  // chico en las dos. Un enlace de 350×17 es una fila de ancho completo:
  // se toca sin problema, y marcarlo llena el reporte de ruido que esconde
  // los casos reales (un botón de 38×38 sí es difícil de acertar).
  if (ancho <= 480) {
    for (const el of document.querySelectorAll("a, button, [role=button]")) {
      if (!visible(el)) continue;
      const r = el.getBoundingClientRect();
      const anchoOk = r.width >= toqueMinimo;
      const altoOk = r.height >= toqueMinimo;
      // Con una dimensión holgada, el blanco es fácil de acertar aunque la
      // otra quede corta.
      if (anchoOk || altoOk) continue;
      agregar(
        "area-toque",
        "duda",
        `${Math.round(r.width)}×${Math.round(r.height)}px, mínimo ${toqueMinimo}px en alguna dimensión`,
        senalar(el)
      );
    }
  }

  // --- 6. Solapamiento entre hermanos --------------------------------------
  // Solo hermanos en flujo normal: una superposición con position absolute
  // o un transform negativo suele ser deliberada (es, de hecho, diseño).
  const enFlujo = (el) => {
    const e = getComputedStyle(el);
    return e.position === "static" && e.transform === "none" && e.float === "none";
  };
  for (const padre of todos) {
    // padre.children NO viene filtrado por `todos`: sin excluir el interior
    // de los SVG acá, se comparan los <path> de cada ícono entre sí y se
    // reportan como solapamiento los trazos del propio dibujo.
    const hijos = [...padre.children].filter((h) => visible(h) && enFlujo(h) && !enSvg(h));
    for (let i = 0; i < hijos.length; i++) {
      for (let j = i + 1; j < hijos.length; j++) {
        const a = hijos[i].getBoundingClientRect();
        const b = hijos[j].getBoundingClientRect();
        const cruceX = Math.min(a.right, b.right) - Math.max(a.left, b.left);
        const cruceY = Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top);
        if (cruceX > 2 && cruceY > 2) {
          agregar(
            "solapamiento",
            "error",
            `se cruza ${Math.round(cruceX)}×${Math.round(cruceY)}px con ${senalar(hijos[j])}`,
            senalar(hijos[i])
          );
        }
      }
    }
  }

  // --- 7. Imagen sin alt ---------------------------------------------------
  for (const img of document.querySelectorAll("img")) {
    if (!img.hasAttribute("alt")) {
      agregar("img-sin-alt", "error", "falta el atributo alt", senalar(img));
    }
  }

  // --- 8. Jerarquía de encabezados -----------------------------------------
  const titulos = [...document.querySelectorAll("h1,h2,h3,h4,h5,h6")].filter(visible);
  const niveles = titulos.map((t) => Number(t.tagName[1]));
  const h1 = niveles.filter((n) => n === 1).length;
  if (h1 > 1) {
    agregar("encabezados", "duda", `${h1} <h1> en la misma pieza`);
  }
  for (let i = 1; i < niveles.length; i++) {
    if (niveles[i] - niveles[i - 1] > 1) {
      agregar(
        "encabezados",
        "duda",
        `salta de h${niveles[i - 1]} a h${niveles[i]}`,
        senalar(titulos[i])
      );
    }
  }

  // --- 11. Superficie que no se despega de su fondo ------------------------
  // Un botón o una tarjeta con fondo propio tiene que distinguirse de lo
  // que tiene detrás. Si coinciden, el elemento desaparece como objeto
  // aunque su texto se lea perfecto — y el chequeo de contraste, que mira
  // texto contra fondo, lo da por bueno.
  //
  // Caso real que lo motivó: el botón del CTA en tono oscuro usaba el
  // color primario, que con la paleta por defecto es el mismo #1c1a17 del
  // fondo. Botón negro sobre negro, contraste de TEXTO perfecto.
  for (const el of document.querySelectorAll("a, button, [role=button]")) {
    if (!visible(el) || enSvg(el)) continue;
    const propio = aRgb(getComputedStyle(el).backgroundColor);
    // Sin fondo propio no hay superficie que despegar: es un enlace de
    // texto, y ahí manda el chequeo de contraste.
    if (!propio || propio.alfa < 0.9) continue;

    // Misma exención que el chequeo de contraste: sobre una imagen no se
    // puede afirmar nada, porque el fondo real lo pone la foto. Sin esto,
    // el botón blanco del Hero inmersivo (pensado para ir sobre una
    // imagen) se reporta como invisible sobre el blanco del arnés.
    if (sobreImagen(el)) continue;

    const detras = el.parentElement ? fondoDe(el.parentElement) : [255, 255, 255];
    const r = razon(propio.rgb, detras);
    // 1.2:1 y no un umbral WCAG: no se pide que la superficie "contraste"
    // como texto, solo que se vea que es un objeto aparte. Un borde
    // visible ya cumple esa función, así que no se reporta.
    const e2 = getComputedStyle(el);
    const tieneBorde =
      parseFloat(e2.borderTopWidth) > 0 &&
      e2.borderTopStyle !== "none" &&
      (aRgb(e2.borderTopColor)?.alfa ?? 0) > 0.15;
    if (r < 1.2 && !tieneBorde) {
      agregar(
        "superficie-invisible",
        "error",
        `su fondo es casi igual al de atrás (${r.toFixed(2)}:1) y no tiene borde: se ve el texto pero no el botón`,
        senalar(el)
      );
    }
  }

  // --- 10. Alineación entre elementos repetidos ----------------------------
  // En una fila de tarjetas, los elementos equivalentes (el nombre, el
  // precio, el botón) tienen que arrancar a la misma altura. Un desfase de
  // 1px no se ve mirando pero delata que algo empuja el contenido — un
  // borde más grueso, un padding distinto.
  //
  // Lo encontró primero el juez (capa interpretable) en Precios: el plan
  // destacado tenía border 2px contra 1px y su título quedaba 1px más
  // abajo. Un número lo decide con certeza, así que pasa a medirse acá.
  for (const padre of todos) {
    const hijos = [...padre.children].filter((h) => visible(h) && !enSvg(h));
    if (hijos.length < 2) continue;

    // Solo filas: si los hermanos están apilados verticalmente, que sus
    // topes difieran es el layout, no un defecto.
    const enFila = hijos.every(
      (h, i) =>
        i === 0 ||
        h.getBoundingClientRect().top < hijos[i - 1].getBoundingClientRect().bottom - 2
    );
    if (!enFila) continue;

    // Elementos equivalentes = misma clase principal, uno por hermano.
    const porClase = new Map();
    for (const h of hijos) {
      for (const desc of [h, ...h.querySelectorAll("*")]) {
        if (enSvg(desc) || !visible(desc)) continue;
        const cls = (desc.getAttribute("class") || "").trim().split(/\s+/)[0];
        if (!cls) continue;
        if (!porClase.has(cls)) porClase.set(cls, []);
        porClase.get(cls).push(desc);
      }
    }

    for (const [cls, nodos] of porClase) {
      if (nodos.length !== hijos.length || nodos.length < 2) continue;

      // Solo lo PRIMERO de cada tarjeta. Un elemento que viene después de
      // texto de largo variable (el autor debajo de una cita larga, una
      // feature en una grilla) queda a distinta altura por el contenido,
      // no por un defecto — marcarlo convierte el chequeo en ruido.
      // Arriba del todo, en cambio, nada lo empujó salvo la caja misma.
      const primeroDeSuTarjeta = nodos.every((n) => {
        const tarjeta = hijos.find((h) => h.contains(n));
        if (!tarjeta) return false;
        const arribaDeTodo = n.getBoundingClientRect().top;
        return [...tarjeta.querySelectorAll("*")].every((otro) => {
          if (!visible(otro) || enSvg(otro)) return true;
          if (otro.contains(n) || n.contains(otro)) return true;
          // Un elemento posicionado no empuja a nadie: la insignia "EL MÁS
          // ELEGIDO" se dibuja sobre el borde superior de la tarjeta, por
          // encima del nombre, pero el nombre sigue siendo lo primero del
          // flujo. Sin esta excepción el chequeo se desactiva solo.
          const pos = getComputedStyle(otro).position;
          if (pos === "absolute" || pos === "fixed") return true;
          return otro.getBoundingClientRect().top >= arribaDeTodo - 0.5;
        });
      });
      if (!primeroDeSuTarjeta) continue;

      const tops = nodos.map((n) => n.getBoundingClientRect().top);
      const desfase = Math.max(...tops) - Math.min(...tops);
      // 0.5px es redondeo subpíxel del navegador, no un defecto.
      if (desfase > 0.5 && desfase < 40) {
        agregar(
          "alineacion-repetidos",
          "duda",
          `${nodos.length} elementos equivalentes arrancan a alturas distintas (desfase ${desfase.toFixed(2)}px)`,
          "." + cls
        );
      }
    }
  }

  // --- 9. Color fuera de la paleta -----------------------------------------
  // Los tokens se leen POR NOMBRE: iterar getComputedStyle no enumera las
  // custom properties en Chromium, así que recorrer el objeto devuelve una
  // paleta vacía y todo parece estar fuera de token.
  //
  // Se aceptan también los derivados de color-mix() sobre esos tokens, que
  // son legítimos: para eso, en vez de exigir una coincidencia exacta se
  // exige que el color esté en la MISMA LÍNEA entre dos tokens de la
  // paleta — que es lo que produce un color-mix de dos de ellos.
  const nombresToken = [
    "--sofia-color-texto",
    "--sofia-color-texto-suave",
    "--sofia-color-fondo",
    "--sofia-color-primario",
    "--sofia-color-acento",
    "--sofia-color-borde",
  ];
  const raiz = getComputedStyle(document.documentElement);
  const paleta = [];
  for (const nombre of nombresToken) {
    const v = aRgb(raiz.getPropertyValue(nombre).trim());
    if (v) paleta.push(v.rgb);
  }

  /** ¿Está en el segmento entre dos tokens (o sea, es un mix de ellos)? */
  const esMezclaDeTokens = (c) => {
    for (const a of paleta) {
      for (const b of paleta) {
        if (a === b) continue;
        // Proyección de c sobre el segmento a-b, y distancia a la línea.
        const ab = b.map((x, i) => x - a[i]);
        const ac = c.map((x, i) => x - a[i]);
        const largo2 = ab.reduce((s, x) => s + x * x, 0);
        if (largo2 === 0) continue;
        const t = ac.reduce((s, x, i) => s + x * ab[i], 0) / largo2;
        if (t < -0.02 || t > 1.02) continue;
        const dist = Math.hypot(...c.map((x, i) => x - (a[i] + ab[i] * t)));
        if (dist <= 4) return true;
      }
    }
    return false;
  };

  const fueraDePaleta = new Set();
  for (const el of todos) {
    const r = el.getBoundingClientRect();
    if (r.width * r.height < 5000) continue;
    const c = aRgb(getComputedStyle(el).backgroundColor);
    if (!c || c.alfa < 1) continue;
    const rgb = c.rgb.map(Math.round);
    const exacto = paleta.some((p) => p.every((x, i) => Math.abs(x - rgb[i]) <= 2));
    if (exacto || esMezclaDeTokens(rgb)) continue;
    fueraDePaleta.add(`${senalar(el)} → rgb(${rgb.join(",")})`);
  }
  for (const f of [...fueraDePaleta].slice(0, 5)) {
    agregar("color-sin-token", "nota", `fondo fuera de la paleta: ${f}`);
  }

  return {
    hallazgos,
    // Contexto para el reporte y para la capa interpretable.
    alto: document.documentElement.scrollHeight,
    ancho,
    elementos: todos.length,
    titulos: titulos.map((t) => `${t.tagName.toLowerCase()}: ${t.textContent.trim().slice(0, 60)}`),
  };
};

// --- Correr ---------------------------------------------------------------

const indice = JSON.parse(await readFile(join(SALIDA, "indice.json"), "utf8"));

await rm(CAPTURAS, { recursive: true, force: true });
await mkdir(CAPTURAS, { recursive: true });

const navegador = await chromium.launch();
const resultados = [];

for (const caso of indice.casos) {
  for (const [nombreAncho, px] of Object.entries(indice.anchos)) {
    const pagina = await navegador.newPage({
      viewport: { width: px, height: 900 },
      deviceScaleFactor: 1,
    });

    await pagina.goto("file://" + join(SALIDA, caso.archivo).replace(/\\/g, "/"), {
      waitUntil: "load",
    });
    // Las fuentes cambian las medidas de texto: medir antes de que estén
    // listas reporta desbordes que no existen.
    await pagina.evaluate(() => document.fonts.ready);

    const medida = await pagina.evaluate(CHEQUEOS, {
      contrasteMinimo: CONTRASTE_MINIMO,
      toqueMinimo: TOQUE_MINIMO,
    });

    const captura = `${caso.archivo.replace(/\.html$/, "")}--${nombreAncho}.png`;
    await pagina.screenshot({ path: join(CAPTURAS, captura), fullPage: true });
    await pagina.close();

    resultados.push({ ...caso, ancho: nombreAncho, px, captura, ...medida });

    const errores = medida.hallazgos.filter((h) => h.severidad === "error").length;
    const dudas = medida.hallazgos.filter((h) => h.severidad === "duda").length;
    const marca = errores ? "ERROR" : dudas ? "duda " : "ok   ";
    console.log(
      `  ${marca} ${caso.archivo.replace(/\.html$/, "").padEnd(34)} ${nombreAncho.padEnd(11)}` +
        (errores || dudas ? ` ${errores} error, ${dudas} duda` : "")
    );
  }
}

await navegador.close();

await writeFile(
  join(AQUI, ARCHIVO_MEDIDAS),
  JSON.stringify({ generado: new Date().toISOString(), resultados }, null, 2)
);

// --- Resumen --------------------------------------------------------------
const porRegla = {};
for (const r of resultados) {
  for (const h of r.hallazgos) {
    porRegla[h.regla] ??= { error: 0, duda: 0, nota: 0 };
    porRegla[h.regla][h.severidad]++;
  }
}

console.log("\n=== Hallazgos por regla ===");
const orden = { error: 0, duda: 1, nota: 2 };
for (const [regla, cuenta] of Object.entries(porRegla).sort(
  (a, b) => orden[peor(a[1])] - orden[peor(b[1])]
)) {
  console.log(
    `  ${regla.padEnd(18)} ${cuenta.error} error, ${cuenta.duda} duda, ${cuenta.nota} nota`
  );
}

function peor(c) {
  return c.error ? "error" : c.duda ? "duda" : "nota";
}

const totalErrores = resultados.reduce(
  (n, r) => n + r.hallazgos.filter((h) => h.severidad === "error").length,
  0
);
console.log(
  `\n${resultados.length} mediciones · ${totalErrores} errores duros · medidas.json escrito`
);

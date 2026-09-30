/**
 * Prueba del reconstructor de listas ANIDADAS del editor.
 *
 *     node tests/test-listas-anidadas.mjs
 *
 * Verifica que leerItemsDeLista() (editor-iframe.js) arme bien el array de
 * un menú con submenús: que los campos del hijo NO pisen los del padre, y
 * que los hijos se guarden como un array adentro de su item.
 *
 * Por qué existe: ésta es la clase de bug que no se ve. El editor
 * reconstruye la lista entera leyendo el DOM, y la versión anterior hacía
 * item.querySelectorAll("[data-sofia-campo]"), que recorre TODO el
 * subárbol — con un submenú adentro, el "texto" del último hijo terminaba
 * guardado como el texto del padre. No hay error, no hay aviso: el menú
 * simplemente queda mal la próxima vez que se carga.
 *
 * Es el mismo defecto que ya rompió las Pestañas una vez.
 *
 * Se corre con Playwright porque estas funciones necesitan un DOM real:
 * usan closest(), :scope y querySelectorAll, que no tienen equivalente
 * razonable en un stub.
 */

import { chromium } from "playwright";
import { readFile } from "node:fs/promises";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const AQUI = dirname(fileURLToPath(import.meta.url));
const RAIZ = dirname(AQUI);

let pasadas = 0;
let falladas = 0;

function comprobar(descripcion, condicion, detalle = "") {
  if (condicion) {
    pasadas++;
    console.log(`  [ok]    ${descripcion}`);
  } else {
    falladas++;
    console.log(`  [FALLA] ${descripcion}${detalle ? "\n          " + detalle : ""}`);
  }
}

/** Un menú con un submenú adentro, tal como lo emite el tema. */
const HTML = `
<section data-sofia-bloque-id="hdr" data-sofia-bloque-tipo="header">
  <ul data-sofia-lista="hdr.enlaces">
    <li data-sofia-item="0">
      <a data-sofia-campo="hdr.enlaces.0.texto">Destinos</a>
      <ul data-sofia-lista="hdr.enlaces.0.hijos">
        <li data-sofia-item="0"><a data-sofia-campo="hdr.enlaces.0.hijos.0.texto">Careyes</a></li>
        <li data-sofia-item="1"><a data-sofia-campo="hdr.enlaces.0.hijos.1.texto">Chamela</a></li>
        <li data-sofia-item="2"><a data-sofia-campo="hdr.enlaces.0.hijos.2.texto">Tenacatita</a></li>
      </ul>
    </li>
    <li data-sofia-item="1">
      <a data-sofia-campo="hdr.enlaces.1.texto">Hospedaje</a>
    </li>
  </ul>
</section>`;

const navegador = await chromium.launch();
const pagina = await navegador.newPage();
await pagina.setContent(`<!doctype html><meta charset="utf-8"><body>${HTML}</body>`);

// El archivo real del editor, no una copia: si alguien cambia la función y
// rompe el contrato, esta prueba tiene que enterarse.
const fuente = await readFile(join(RAIZ, "inc", "js", "editor-iframe.js"), "utf8");

// Se inyectan SOLO las tres funciones bajo prueba. El archivo entero se
// registra a sí mismo con listeners y postMessage al padre, que acá no
// existe.
const extraer = (nombre) => {
  const inicio = fuente.indexOf(`\tfunction ${nombre}(`);
  if (inicio === -1) throw new Error(`no encontré la función ${nombre} en editor-iframe.js`);
  // Cuenta llaves desde la apertura hasta cerrar el cuerpo.
  let i = fuente.indexOf("{", inicio);
  let nivel = 0;
  for (; i < fuente.length; i++) {
    if (fuente[i] === "{") nivel++;
    else if (fuente[i] === "}") {
      nivel--;
      if (nivel === 0) return fuente.slice(inicio, i + 1);
    }
  }
  throw new Error(`no pude delimitar ${nombre}`);
};

await pagina.evaluate(
  ([f1, f2, f3]) => {
    // eslint-disable-next-line no-eval
    window.eval(`${f1}\n${f2}\n${f3}\nwindow.leerItemsDeLista = leerItemsDeLista;`);
  },
  [extraer("leerItemsDeLista"), extraer("camposPropiosDe"), extraer("listasPropiasDe")]
);

const leido = await pagina.evaluate(() =>
  window.leerItemsDeLista(document.querySelector('[data-sofia-lista="hdr.enlaces"]'))
);

console.log("=== Reconstrucción de un menú con submenú ===\n");
console.log(JSON.stringify(leido, null, 2), "\n");

comprobar("el menú tiene 2 enlaces de primer nivel", leido.length === 2, `obtuve ${leido.length}`);

comprobar(
  'el padre conserva su propio texto ("Destinos", no el del último hijo)',
  leido[0]?.texto === "Destinos",
  `obtuve ${JSON.stringify(leido[0]?.texto)}`
);

comprobar(
  "el padre trae sus hijos como array",
  Array.isArray(leido[0]?.hijos) && leido[0].hijos.length === 3,
  `obtuve ${JSON.stringify(leido[0]?.hijos)}`
);

comprobar(
  "los hijos conservan su orden y su texto",
  leido[0]?.hijos?.map((h) => h.texto).join(",") === "Careyes,Chamela,Tenacatita",
  `obtuve ${JSON.stringify(leido[0]?.hijos?.map((h) => h.texto))}`
);

comprobar(
  "un enlace sin submenú no inventa la clave",
  leido[1]?.texto === "Hospedaje" && leido[1]?.hijos === undefined,
  `obtuve ${JSON.stringify(leido[1])}`
);

comprobar(
  "el texto del hijo NO se filtró al padre",
  leido[0]?.texto !== "Tenacatita",
  "el padre quedó con el texto del último hijo — es el bug que esta prueba existe para atrapar"
);

// --- El item que ES su propio campo -------------------------------------
// Caso real del Header: el <a> del subenlace lleva data-sofia-item Y
// data-sofia-campo. La primera versión de esta prueba no lo cubría —usaba
// nodos separados— y por eso no atrapó que los hijos salían vacíos.
await pagina.setContent(`<!doctype html><meta charset="utf-8"><body>
<nav data-sofia-lista="hdr.enlaces">
  <div data-sofia-item="0">
    <a data-sofia-campo="hdr.enlaces.0.texto">Destinos</a>
    <div data-sofia-lista="hdr.enlaces.0.hijos">
      <a data-sofia-item="0" data-sofia-campo="hdr.enlaces.0.hijos.0.texto">Careyes</a>
      <a data-sofia-item="1" data-sofia-campo="hdr.enlaces.0.hijos.1.texto">Chamela</a>
    </div>
  </div>
</nav></body>`);

await pagina.evaluate(
  ([f1, f2, f3]) => {
    // eslint-disable-next-line no-eval
    window.eval(`${f1}\n${f2}\n${f3}\nwindow.leerItemsDeLista = leerItemsDeLista;`);
  },
  [extraer("leerItemsDeLista"), extraer("camposPropiosDe"), extraer("listasPropiasDe")]
);

const mismoNodo = await pagina.evaluate(() =>
  window.leerItemsDeLista(document.querySelector('[data-sofia-lista="hdr.enlaces"]'))
);

comprobar(
  "un item que es su propio campo se lee (no queda vacío)",
  mismoNodo[0]?.hijos?.[0]?.texto === "Careyes" && mismoNodo[0]?.hijos?.[1]?.texto === "Chamela",
  JSON.stringify(mismoNodo)
);

comprobar(
  "y su padre conserva el texto propio",
  mismoNodo[0]?.texto === "Destinos",
  `obtuve ${JSON.stringify(mismoNodo[0]?.texto)}`
);

// --- Tres niveles: la recursión no debe tener un techo escondido ---------
await pagina.setContent(`<!doctype html><meta charset="utf-8"><body>
<ul data-sofia-lista="x.a">
  <li data-sofia-item="0">
    <span data-sofia-campo="x.a.0.t">N1</span>
    <ul data-sofia-lista="x.a.0.b">
      <li data-sofia-item="0">
        <span data-sofia-campo="x.a.0.b.0.t">N2</span>
        <ul data-sofia-lista="x.a.0.b.0.c">
          <li data-sofia-item="0"><span data-sofia-campo="x.a.0.b.0.c.0.t">N3</span></li>
        </ul>
      </li>
    </ul>
  </li>
</ul></body>`);

await pagina.evaluate(
  ([f1, f2, f3]) => {
    // eslint-disable-next-line no-eval
    window.eval(`${f1}\n${f2}\n${f3}\nwindow.leerItemsDeLista = leerItemsDeLista;`);
  },
  [extraer("leerItemsDeLista"), extraer("camposPropiosDe"), extraer("listasPropiasDe")]
);

const profundo = await pagina.evaluate(() =>
  window.leerItemsDeLista(document.querySelector('[data-sofia-lista="x.a"]'))
);

comprobar(
  "tres niveles de anidación se leen completos",
  profundo[0]?.t === "N1" && profundo[0]?.b?.[0]?.t === "N2" && profundo[0]?.b?.[0]?.c?.[0]?.t === "N3",
  JSON.stringify(profundo)
);

await navegador.close();

console.log(`\n${pasadas} pasadas, ${falladas} falladas`);
process.exit(falladas > 0 ? 1 : 0);

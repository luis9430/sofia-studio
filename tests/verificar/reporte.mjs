/**
 * Arma un HTML con lo medido: captura, hallazgos y contexto por caso.
 *
 *     node tests/verificar/reporte.mjs
 *
 * Deja tests/verificar/reporte.html — abrilo en el navegador.
 *
 * Existe porque medidas.json tiene la verdad pero no se puede leer: 90
 * mediciones en JSON son ilegibles, y lo que hace falta es ver la captura
 * al lado del hallazgo para decidir si es real. Los casos con errores van
 * PRIMERO; los que están limpios se colapsan.
 */

import { readFile, writeFile } from "node:fs/promises";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const AQUI = dirname(fileURLToPath(import.meta.url));
const { generado, resultados } = JSON.parse(
  await readFile(join(AQUI, "medidas.json"), "utf8")
);

const esc = (s) =>
  String(s).replace(
    /[&<>"]/g,
    (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" })[c]
  );

/** Agrupa las tres anchuras de un mismo caso en una fila. */
const porCaso = new Map();
for (const r of resultados) {
  const clave = r.archivo;
  if (!porCaso.has(clave)) porCaso.set(clave, []);
  porCaso.get(clave).push(r);
}

const severidadMax = (medidas) => {
  const todas = medidas.flatMap((m) => m.hallazgos.map((h) => h.severidad));
  return todas.includes("error") ? "error" : todas.includes("duda") ? "duda" : todas.length ? "nota" : "ok";
};

const orden = { error: 0, duda: 1, nota: 2, ok: 3 };
const casos = [...porCaso.entries()].sort(
  (a, b) => orden[severidadMax(a[1])] - orden[severidadMax(b[1])]
);

const totales = { error: 0, duda: 0, nota: 0 };
for (const r of resultados) for (const h of r.hallazgos) totales[h.severidad]++;

const secciones = casos
  .map(([archivo, medidas]) => {
    const sev = severidadMax(medidas);
    const nombre = archivo.replace(/\.html$/, "");
    const limpio = sev === "ok";

    const columnas = medidas
      .map((m) => {
        const hall = m.hallazgos
          .map(
            (h) =>
              `<li class="h h--${h.severidad}"><code>${esc(h.elemento || "—")}</code> ${esc(
                h.mensaje
              )}</li>`
          )
          .join("");
        return `<div class="vista">
          <div class="vista__cab"><strong>${esc(m.ancho)}</strong> <span>${m.px}px · ${m.alto}px de alto · ${m.elementos} elementos</span></div>
          <a href="capturas/${esc(m.captura)}" target="_blank"><img loading="lazy" src="capturas/${esc(m.captura)}" alt="${esc(nombre)} en ${esc(m.ancho)}"></a>
          ${hall ? `<ul class="hallazgos">${hall}</ul>` : '<p class="ok">sin hallazgos</p>'}
        </div>`;
      })
      .join("");

    return `<details class="caso caso--${sev}" ${limpio ? "" : "open"}>
      <summary>
        <span class="sev sev--${sev}">${sev}</span>
        <strong>${esc(nombre)}</strong>
        <span class="cuenta">${medidas.reduce((n, m) => n + m.hallazgos.length, 0)} hallazgos</span>
      </summary>
      <div class="vistas">${columnas}</div>
    </details>`;
  })
  .join("");

const html = `<!doctype html><html lang="es"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Verificación de piezas</title>
<style>
:root{--fondo:#fafaf9;--panel:#fff;--borde:#e7e5e4;--texto:#1c1917;--suave:#78716c;
  --error:#b91c1c;--duda:#b45309;--nota:#0369a1;--ok:#15803d}
*{box-sizing:border-box}
body{margin:0;background:var(--fondo);color:var(--texto);
  font:15px/1.55 ui-sans-serif,system-ui,sans-serif}
header{padding:26px 24px;background:var(--texto);color:var(--fondo)}
header h1{margin:0 0 6px;font-size:20px;letter-spacing:-.01em}
header p{margin:0;font-size:13px;opacity:.72}
.resumen{display:flex;gap:18px;margin-top:14px;flex-wrap:wrap}
.resumen b{font-variant-numeric:tabular-nums}
main{max-width:1500px;margin:0 auto;padding:22px 24px 60px}
.caso{margin-bottom:14px;background:var(--panel);border:1px solid var(--borde);border-radius:8px;overflow:hidden}
.caso--error{border-color:#fca5a5}
.caso--duda{border-color:#fcd34d}
summary{display:flex;align-items:center;gap:12px;padding:13px 16px;cursor:pointer;
  background:#fcfcfb;border-bottom:1px solid transparent}
.caso[open] summary{border-bottom-color:var(--borde)}
.sev{flex:none;padding:3px 9px;border-radius:99px;font:600 11px/1.4 ui-monospace,monospace;
  text-transform:uppercase;letter-spacing:.05em}
.sev--error{background:#fee2e2;color:var(--error)}
.sev--duda{background:#fef3c7;color:var(--duda)}
.sev--nota{background:#e0f2fe;color:var(--nota)}
.sev--ok{background:#dcfce7;color:var(--ok)}
summary strong{font-weight:600;font-family:ui-monospace,monospace;font-size:13.5px}
.cuenta{margin-left:auto;font-size:12px;color:var(--suave);font-variant-numeric:tabular-nums}
.vistas{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:1px;background:var(--borde)}
.vista{background:var(--panel);padding:14px}
.vista__cab{display:flex;align-items:baseline;gap:8px;margin-bottom:10px;font-size:12px}
.vista__cab span{color:var(--suave);font-variant-numeric:tabular-nums}
.vista img{display:block;width:100%;height:auto;border:1px solid var(--borde);border-radius:4px;background:#fff}
.hallazgos{margin:10px 0 0;padding:0;list-style:none;display:flex;flex-direction:column;gap:6px}
.h{padding:7px 9px;border-radius:5px;font-size:12.5px;line-height:1.45}
.h code{font-family:ui-monospace,monospace;font-size:11.5px;padding:1px 4px;border-radius:3px;background:rgba(0,0,0,.06)}
.h--error{background:#fef2f2;color:#7f1d1d}
.h--duda{background:#fffbeb;color:#78350f}
.h--nota{background:#f0f9ff;color:#0c4a6e}
.ok{margin:10px 0 0;font-size:12.5px;color:var(--ok)}
@media (max-width:700px){.vistas{grid-template-columns:1fr}}
</style></head><body>
<header>
  <h1>Verificación de piezas — capa medible</h1>
  <p>${resultados.length} mediciones · ${porCaso.size} casos × 3 anchos · ${esc(
  new Date(generado).toLocaleString("es")
)}</p>
  <div class="resumen">
    <span><b>${totales.error}</b> errores</span>
    <span><b>${totales.duda}</b> dudas</span>
    <span><b>${totales.nota}</b> notas</span>
  </div>
</header>
<main>${secciones}</main>
</body></html>`;

await writeFile(join(AQUI, "reporte.html"), html);
console.log(
  `reporte.html escrito · ${totales.error} errores, ${totales.duda} dudas, ${totales.nota} notas`
);

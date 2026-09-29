/**
 * Capa INTERPRETABLE del arnés: lo que un número no puede decidir.
 *
 *     node tests/verificar/juzgar.mjs            # todas las piezas
 *     node tests/verificar/juzgar.mjs precios    # solo una
 *
 * Lee las capturas que dejó medir.mjs, se las manda al modelo con la
 * rúbrica de RUBRICA.md y deja juicio.json.
 *
 * Por qué existe: Playwright detecta que algo desborda, no que la
 * jerarquía esté confusa o que el botón principal no resalte. Esa capa
 * necesita interpretación, y es donde un modelo mirando la captura ve lo
 * mismo que ve una persona.
 *
 * TRES DECISIONES QUE HACEN QUE SIRVA:
 *
 * 1. El juez ve CAPTURAS, no código. Con el HTML delante juzga el CSS;
 *    lo que importa es lo que el navegador dibujó.
 *
 * 2. Una llamada POR PIEZA con todas sus capturas juntas, no una por
 *    captura. Así compara sus composiciones entre sí, que es donde se
 *    nota que una quedó peor que las otras dos.
 *
 * 3. Los hallazgos duros de medir.mjs se le PASAN ya resueltos. Sin eso
 *    gasta atención repitiendo "esto desborda" cuando una medición ya lo
 *    sabía con certeza.
 *
 * Y el límite, dicho de frente: el modelo tiende a lo convencional.
 * Sirve para detectar problemas, no para aprobar diseño. Cuando su
 * juicio contradiga una medición, MANDA LA MEDICIÓN.
 */

import { readFile, writeFile } from "node:fs/promises";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const AQUI = dirname(fileURLToPath(import.meta.url));


/** El .env de GoPress: un solo lugar para el secreto, no una copia acá. */
const RUTA_ENV = "C:/laragon/www/GoPress/.env";
const MODELO = process.env.SOFIA_MODELO_JUEZ || "google/gemini-2.5-flash";

/**
 * Las siete preguntas de RUBRICA.md.
 *
 * Son CERRADAS a propósito. "¿Se ve bien?" se contesta con un halago, y
 * un halago no es información; "¿qué se lee primero y es lo que debería?"
 * se contesta con algo accionable.
 */
const PREGUNTAS = [
  ["jerarquia", "¿Se distingue el título del subtítulo del cuerpo? ¿Qué se lee primero, y es lo que debería leerse primero?"],
  ["ritmo", "¿Los espacios entre elementos hermanos son parejos? ¿Hay algo pegado o algo suelto sin motivo?"],
  ["foco", "¿La acción principal (botón, CTA) gana la atención? ¿Compite con otra cosa?"],
  ["densidad", "¿Hay aire donde hace falta, sin huecos muertos?"],
  ["alineacion", "¿Coinciden bordes y líneas base entre elementos repetidos?"],
  ["mobile", "A 390px, ¿sigue leyéndose como una pieza, o es una lista de cosas apiladas?"],
  ["defectos", "¿Algo se ve roto, desalineado o fuera de lugar?"],
];

const SISTEMA = `Sos un director de arte revisando secciones de una página web antes de mostrárselas a un cliente.

Mirás capturas reales del navegador. Juzgás SOLO lo que ves.

QUÉ SE TE PIDE
Detectar problemas de composición: jerarquía, ritmo, foco, densidad, alineación y cómo se comporta en teléfono.

QUÉ NO SE TE PIDE
No opines sobre si el diseño "te gusta", si es "moderno" o si la paleta es acertada — eso lo decide el cliente. No propongas rediseños.
No reportes desbordes, contraste, texto cortado ni áreas táctiles: eso ya se midió con precisión y se te pasa resuelto. Repetirlo es ruido.

CÓMO RESPONDER
Para cada pregunta: severidad ("error" si está claramente mal, "duda" si algo llama la atención pero puede ser deliberado, "ok" si no hay nada que decir) y, cuando no sea "ok", QUÉ elemento y en qué captura.
Un hallazgo sin elemento concreto no sirve. "El espaciado es inconsistente" no es accionable; "el bloque de datos está pegado al título, a diferencia del resto" sí.

SÉ EXIGENTE PERO NO INVENTES
Si una pieza está bien, decí "ok". Marcar problemas que no existen para parecer riguroso es peor que no revisar: hace perder tiempo y entrena a que te ignoren.`;

/** Lee OPENROUTER_API_KEY del .env de GoPress. */
async function leerClave() {
  if (process.env.OPENROUTER_API_KEY) return process.env.OPENROUTER_API_KEY;
  try {
    const env = await readFile(RUTA_ENV, "utf8");
    const m = env.match(/^OPENROUTER_API_KEY=(.+)$/m);
    if (m) return m[1].trim();
  } catch {
    /* cae al error de abajo */
  }
  throw new Error(
    `no encontré OPENROUTER_API_KEY (ni en el entorno ni en ${RUTA_ENV})`
  );
}

/** El esquema de la respuesta: obliga a contestar las 7, con elemento. */
const ESQUEMA = {
  type: "object",
  properties: {
    resumen: {
      type: "string",
      description: "Una o dos frases sobre el estado general de la pieza.",
    },
    hallazgos: {
      type: "array",
      items: {
        type: "object",
        properties: {
          pregunta: { type: "string", enum: PREGUNTAS.map(([k]) => k) },
          severidad: { type: "string", enum: ["error", "duda", "ok"] },
          elemento: {
            type: "string",
            description: "Qué elemento concreto. Vacío solo si la severidad es ok.",
          },
          captura: {
            type: "string",
            description: "En qué captura se ve (nombre de la composición y ancho).",
          },
          observacion: { type: "string" },
        },
        required: ["pregunta", "severidad", "elemento", "captura", "observacion"],
        additionalProperties: false,
      },
    },
  },
  required: ["resumen", "hallazgos"],
  additionalProperties: false,
};

/**
 * Arma el mensaje de una pieza: sus capturas + lo que ya se midió.
 *
 * Las capturas van etiquetadas ANTES de cada imagen, porque el modelo no
 * puede nombrar una imagen que recibió sin nombre — y un hallazgo que no
 * dice en qué captura se ve no se puede ir a mirar.
 */
async function armarMensaje(pieza, medidas) {
  const partes = [
    {
      type: "text",
      text:
        `Sección: ${pieza.toUpperCase()}\n\n` +
        `Vas a ver ${medidas.length} capturas: las composiciones de esta sección en tres anchos ` +
        `(escritorio 1280px, tablet 768px, teléfono 390px).\n\n` +
        `Compará las composiciones entre sí: si una quedó peor que las otras, eso es lo más útil que podés señalar.`,
    },
  ];

  for (const m of medidas) {
    const b64 = await readFile(join(AQUI, CAPTURAS_DE, m.captura), { encoding: "base64" });
    const duros = m.hallazgos.filter((h) => h.severidad !== "nota");
    partes.push({
      type: "text",
      text:
        `\n--- ${m.composicion}${m.tono !== "base" ? " / " + m.tono : ""} · ${m.ancho} (${m.px}px) ---` +
        (duros.length
          ? `\nYa medido (no lo repitas): ${duros.map((h) => h.mensaje).join("; ")}`
          : `\nYa medido: sin problemas de desborde, contraste ni recorte.`),
    });
    partes.push({ type: "image_url", image_url: { url: "data:image/png;base64," + b64 } });
  }

  partes.push({
    type: "text",
    text:
      `\n\nRespondé las siete preguntas:\n` +
      PREGUNTAS.map(([k, q]) => `- ${k}: ${q}`).join("\n"),
  });

  return partes;
}

/** Una llamada, con reintento ante 429/5xx. */
async function juzgarPieza(clave, pieza, medidas) {
  const contenido = await armarMensaje(pieza, medidas);

  for (let intento = 1; intento <= 3; intento++) {
    const r = await fetch("https://openrouter.ai/api/v1/chat/completions", {
      method: "POST",
      headers: { Authorization: "Bearer " + clave, "Content-Type": "application/json" },
      body: JSON.stringify({
        model: MODELO,
        // Temperatura baja: acá no se quiere variedad sino criterio
        // estable. Es lo contrario del generador de componentes, que
        // corre a 1.0 justamente para que no repita.
        temperature: 0.2,
        messages: [
          { role: "system", content: SISTEMA },
          { role: "user", content: contenido },
        ],
        response_format: {
          type: "json_schema",
          json_schema: { name: "juicio", strict: true, schema: ESQUEMA },
        },
      }),
    });

    if (r.ok) {
      const j = await r.json();
      const texto = j.choices?.[0]?.message?.content;
      if (!texto) throw new Error("respuesta sin contenido: " + JSON.stringify(j).slice(0, 300));
      return JSON.parse(texto);
    }

    const cuerpo = await r.text();
    if (intento === 3 || (r.status !== 429 && r.status < 500)) {
      throw new Error(`HTTP ${r.status}: ${cuerpo.slice(0, 300)}`);
    }
    const espera = 2000 * intento;
    console.log(`     HTTP ${r.status}, reintento en ${espera / 1000}s`);
    await new Promise((s) => setTimeout(s, espera));
  }
}

// --- Correr ---------------------------------------------------------------

const clave = await leerClave();

// Se puede juzgar el set de piezas a mano (el default) o el de paginas
// generadas por IA: "node juzgar.mjs --ia". Mismo juez y misma rubrica para
// los dos — si el generador se juzgara con otro criterio, comparar no
// querria decir nada.
const esIA = process.argv.includes("--ia");
const ARCHIVO_MEDIDAS = esIA ? "medidas-ia.json" : "medidas.json";
const ARCHIVO_JUICIO = esIA ? "juicio-ia.json" : "juicio.json";
const CAPTURAS_DE = esIA ? "capturas-ia" : "capturas";

const { resultados } = JSON.parse(await readFile(join(AQUI, ARCHIVO_MEDIDAS), "utf8"));

const soloPieza = process.argv.slice(2).find((a) => !a.startsWith("--"));
const piezas = [...new Set(resultados.map((r) => r.pieza))].filter(
  (p) => !soloPieza || p === soloPieza
);

if (!piezas.length) {
  console.error(
    `no hay ninguna pieza "${soloPieza}". Hay: ${[...new Set(resultados.map((r) => r.pieza))].join(", ")}`
  );
  process.exit(1);
}

console.log(`Juzgando ${piezas.length} pieza(s) con ${MODELO}\n`);

const juicios = [];
for (const pieza of piezas) {
  const medidas = resultados.filter((r) => r.pieza === pieza);
  process.stdout.write(`  ${pieza.padEnd(20)} ${medidas.length} capturas... `);

  try {
    const juicio = await juzgarPieza(clave, pieza, medidas);
    const err = juicio.hallazgos.filter((h) => h.severidad === "error").length;
    const dud = juicio.hallazgos.filter((h) => h.severidad === "duda").length;
    console.log(err || dud ? `${err} error, ${dud} duda` : "sin observaciones");
    juicios.push({ pieza, ...juicio });
  } catch (e) {
    console.log(`FALLÓ — ${e.message}`);
    juicios.push({ pieza, error: e.message, resumen: "", hallazgos: [] });
  }
}

// Juzgar una sola pieza NO debe borrar el juicio de las otras seis: cada
// llamada cuesta plata, y lo normal es rejuzgar solo la que se acaba de
// tocar. Se fusiona con lo que ya había.
let previos = [];
try {
  previos = JSON.parse(await readFile(join(AQUI, ARCHIVO_JUICIO), "utf8")).juicios ?? [];
} catch {
  /* primera corrida */
}
const fusionados = [
  ...previos.filter((p) => !juicios.some((j) => j.pieza === p.pieza)),
  ...juicios,
];

await writeFile(
  join(AQUI, ARCHIVO_JUICIO),
  JSON.stringify(
    { generado: new Date().toISOString(), modelo: MODELO, juicios: fusionados },
    null,
    2
  )
);

// --- Resumen --------------------------------------------------------------
console.log("\n=== Observaciones por pregunta ===");
const porPregunta = {};
for (const j of juicios) {
  for (const h of j.hallazgos) {
    if (h.severidad === "ok") continue;
    porPregunta[h.pregunta] ??= { error: 0, duda: 0 };
    porPregunta[h.pregunta][h.severidad]++;
  }
}
if (!Object.keys(porPregunta).length) {
  console.log("  ninguna");
} else {
  for (const [p, c] of Object.entries(porPregunta)) {
    console.log(`  ${p.padEnd(12)} ${c.error} error, ${c.duda} duda`);
  }
}
console.log("\njuicio.json escrito · `node tests/verificar/reporte.mjs` para verlo con las capturas");

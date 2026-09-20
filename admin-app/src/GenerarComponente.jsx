import { useState } from "preact/hooks";

/**
 * GenerarComponente — al pie de la pestaña "Agregar" del panel de
 * Estructura: describir con palabras un bloque que el catálogo no tiene.
 *
 * Por qué existe: los ~39 Componentes del tema saben maquetar pero no
 * diseñar — no pueden expresar un corte diagonal, una superposición ni
 * una forma decorativa. Esta caja es la salida cuando el usuario busca
 * algo en la lista de arriba y no lo encuentra, que es exactamente el
 * momento en que la necesita. Por eso vive acá abajo y no en una pantalla
 * aparte.
 *
 * Tres pasos, cada uno un request propio (mismo criterio que
 * FranjaGenerarIA: nunca un mega-endpoint que haga todo):
 *
 *  1. POST sofia/v1/ia/generar-componente {prompt} → {definicion, html,
 *     css, avisos}. NO guarda nada: la descripción ya viene validada
 *     contra el contrato del tema (Sofia_Definicion_Generada) y el HTML
 *     sale del mismo render que usaría en la página real.
 *  2. El usuario mira el preview y decide.
 *  3. "Agregar al sitio" → POST sofia/v1/componentes-generados, que lo
 *     sube al catálogo de GoPress. Recién ahí persiste.
 *
 * Esa separación importa: un componente que no convence se descarta sin
 * dejar rastro en el catálogo del sitio.
 *
 * Los AVISOS se muestran siempre que existan. Si la validación descartó
 * una etiqueta prohibida o una regla de CSS, el usuario tiene que
 * enterarse — los primeros bugs de este sistema (un var() que no existía,
 * un overflow que recortaba) costaron encontrar justamente porque fallaban
 * en silencio.
 */
export function GenerarComponente({ config, onCreado }) {
  const [prompt, setPrompt] = useState("");
  const [estado, setEstado] = useState("listo"); // listo | generando | generado | guardando | error
  const [resultado, setResultado] = useState(null);
  const [error, setError] = useState("");

  const ocupado = estado === "generando" || estado === "guardando";

  async function pedir(ruta, cuerpo) {
    const respuesta = await fetch(`${config.restUrl}${ruta}`, {
      method: "POST",
      headers: { "Content-Type": "application/json", "X-WP-Nonce": config.nonce },
      body: JSON.stringify(cuerpo),
    });
    const datos = await respuesta.json().catch(() => ({}));
    if (!respuesta.ok) {
      throw new Error(datos.message || "No se pudo completar la operación.");
    }
    return datos;
  }

  async function generar() {
    if (!prompt.trim() || ocupado) return;
    setEstado("generando");
    setError("");
    setResultado(null);
    try {
      setResultado(await pedir("ia/generar-componente", { prompt }));
      setEstado("generado");
    } catch (e) {
      setError(e.message);
      setEstado("error");
    }
  }

  async function guardar() {
    if (!resultado || ocupado) return;
    setEstado("guardando");
    try {
      const guardado = await pedir("componentes-generados", { definicion: resultado.definicion });
      // El catálogo del sitio cambió, así que el panel tiene que
      // recargarlo: el bloque nuevo recién aparece en la lista de arriba
      // después de esto.
      onCreado(guardado.tipo);
      setPrompt("");
      setResultado(null);
      setEstado("listo");
    } catch (e) {
      setError(e.message);
      setEstado("error");
    }
  }

  return (
    <div className="sofia-generar-componente">
      <p className="sofia-generar-componente__intro">
        ¿No encontrás lo que buscás? Describilo y la IA lo crea.
      </p>

      <textarea
        id="sofia-generar-componente-prompt"
        className="sofia-generar-componente__prompt"
        placeholder="Ej: un hero con la imagen cortada en diagonal y el título encima"
        value={prompt}
        rows={3}
        disabled={ocupado}
        onInput={(e) => setPrompt(e.currentTarget.value)}
      />

      <button
        type="button"
        className="sofia-generar-componente__boton"
        onClick={generar}
        disabled={!prompt.trim() || ocupado}
      >
        {estado === "generando" ? "Creando…" : "Crear componente"}
      </button>

      {estado === "error" && <p className="sofia-generar-componente__error">{error}</p>}

      {resultado && (
        <div className="sofia-generar-componente__resultado">
          <p className="sofia-generar-componente__nombre">{resultado.definicion.nombre}</p>

          {/*
            Preview en un <iframe srcDoc>, no inyectado en este documento:
            el HTML y el CSS vienen de un modelo, y aunque los dos pasaron
            por el validador, aislarlos evita que una regla suelta pise el
            estilo del panel. Mismo criterio que el preview de página en
            FranjaGenerarIA.
          */}
          <iframe
            className="sofia-generar-componente__preview"
            title={`Vista previa de ${resultado.definicion.nombre}`}
            srcDoc={`<!doctype html><html><head><meta charset="utf-8">${(config.hojasEstiloTema || [])
              .map((href) => `<link rel="stylesheet" href="${href}">`)
              .join("")}<style>body{margin:0}${resultado.css || ""}</style></head><body class="sofia-pagina">${resultado.html}</body></html>`}
          />

          {resultado.avisos && resultado.avisos.length > 0 && (
            <ul className="sofia-generar-componente__avisos">
              {resultado.avisos.map((aviso, i) => (
                <li key={i}>{aviso}</li>
              ))}
            </ul>
          )}

          <div className="sofia-generar-componente__acciones">
            <button
              type="button"
              className="sofia-generar-componente__boton"
              onClick={guardar}
              disabled={ocupado}
            >
              {estado === "guardando" ? "Agregando…" : "Agregar al sitio"}
            </button>
            <button
              type="button"
              className="sofia-generar-componente__descartar"
              onClick={() => {
                setResultado(null);
                setEstado("listo");
              }}
              disabled={ocupado}
            >
              Descartar
            </button>
          </div>

          <p className="sofia-generar-componente__nota">
            Queda disponible en todas las páginas de este sitio.
          </p>
        </div>
      )}
    </div>
  );
}

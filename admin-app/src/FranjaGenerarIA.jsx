import { useState } from "preact/hooks";

/**
 * FranjaGenerarIA (paso 4 del rediseño de layout a 3 zonas fijas, ver la
 * memoria de producto) — reemplaza el modal centrado PanelGenerarIA.jsx
 * (eliminado): vive SIEMPRE montada, anclada al pie del canvas, dentro de
 * .sofia-sitio-frame — nunca un overlay que tapa el resto de la pantalla.
 * Dos estados: RETRAÍDA (una sola línea, input + botón "Generar", el
 * estado por defecto — mismo criterio que "el panel siempre está ahí" ya
 * usado para Propiedades/Estructura en pasos anteriores) y EXPANDIDA (se
 * abre sola al generar, muestra el preview real + Agregar/Cancelar, crece
 * hacia ARRIBA empujando el canvas en vez de superponerse). Decisión
 * confirmada con el usuario tras comparar un mockup (Artifact) contra la
 * alternativa de meter el chat en una pestaña más del panel de Estructura
 * — una columna de 240px es demasiado angosta para un preview de página
 * completa; una franja que se expande tiene margen real para crecer y se
 * retrae sola cuando no hace falta.
 *
 * Mismo flujo de 3 pasos que tenía el modal (sin cambios de lógica, solo
 * de contenedor — cada paso sigue siendo un request propio, nunca un solo
 * mega-endpoint):
 *  1. POST sofia/v1/ia/generar {prompt} → {arbol_reparado, avisos}. El
 *     árbol YA viene validado/reparado contra el catálogo real de este
 *     tema (ver Sofia_REST_Editor::ia_generar_arbol() del lado PHP) — este
 *     panel nunca confía en el árbol crudo del LLM, solo en lo que PHP ya
 *     revisó.
 *  2. POST sofia/v1/ia/preview {arbol} → {html}. Renderiza el árbol YA
 *     reparado con el mismo Sofia_Pagina/Sofia_Componente_Factory que
 *     cualquier página real, SIN guardar nada en GoPress. Se llama
 *     automáticamente apenas llega el árbol reparado del paso 1.
 *  3. "Agregar a la página" llama onAplicar (aplicarArbolGeneradoPorIA en
 *     App.jsx), que AGREGA el árbol al final de la estructura existente
 *     (nunca la reemplaza) reusando el mismo PUT
 *     sofia/v1/paginas/{slug}/estructura que cualquier otro cambio de
 *     Nivel 2. Este panel solo le entrega el árbol ya confirmado por el
 *     usuario, App.jsx hace el guardado real + recarga del iframe.
 *
 * Preview vía <iframe srcDoc="..."> — mismo criterio documentado en el
 * PanelGenerarIA original: aísla el HTML del árbol (viene de un LLM, no
 * 100% de fiar) en su propio documento, con los mismos <link> de CSS del
 * tema activo inyectados para que se vea parecido a la página real.
 */
export function FranjaGenerarIA({ config, onAplicar }) {
  const [prompt, setPrompt] = useState("");
  const [estado, setEstado] = useState("listo"); // "listo" | "generando" | "generado" | "aplicando" | "error"
  const [avisos, setAvisos] = useState([]);
  const [arbol, setArbol] = useState(null);
  const [html, setHtml] = useState("");
  const [mensajeError, setMensajeError] = useState("");
  // expandida: independiente de `estado` — se abre sola al llegar un
  // resultado (generado o error), pero el usuario puede cerrarla con "×"
  // sin perder lo ya generado (reabre con el mismo preview si vuelve a
  // tocar el input), y sigue expandida mientras "generando"/"aplicando"
  // para no ocultar el progreso de una operación en curso.
  const [expandida, setExpandida] = useState(false);

  async function generar() {
    if (!prompt.trim()) return;
    setExpandida(true);
    setEstado("generando");
    setMensajeError("");
    setArbol(null);
    setHtml("");
    setAvisos([]);
    try {
      const respuestaGenerar = await fetch(`${config.restUrl}ia/generar`, {
        method: "POST",
        headers: { "Content-Type": "application/json", "X-WP-Nonce": config.nonce },
        body: JSON.stringify({ prompt }),
      });
      const datosGenerar = await respuestaGenerar.json().catch(() => null);
      if (!respuestaGenerar.ok || !datosGenerar) {
        setMensajeError((datosGenerar && datosGenerar.message) || "No se pudo generar el árbol.");
        setEstado("error");
        return;
      }

      const arbolReparado = datosGenerar.arbol_reparado || [];
      setAvisos(datosGenerar.avisos || []);
      setArbol(arbolReparado);

      if (arbolReparado.length === 0) {
        setMensajeError("La IA no generó ningún bloque válido para este prompt — probá reformularlo.");
        setEstado("error");
        return;
      }

      const respuestaPreview = await fetch(`${config.restUrl}ia/preview`, {
        method: "POST",
        headers: { "Content-Type": "application/json", "X-WP-Nonce": config.nonce },
        body: JSON.stringify({ arbol: arbolReparado }),
      });
      const datosPreview = await respuestaPreview.json().catch(() => null);
      if (!respuestaPreview.ok || !datosPreview) {
        setMensajeError("El árbol se generó, pero no se pudo renderizar la vista previa.");
        setEstado("error");
        return;
      }

      setHtml(datosPreview.html || "");
      setEstado("generado");
    } catch {
      setMensajeError("No se pudo conectar con el servidor.");
      setEstado("error");
    }
  }

  async function aplicar() {
    if (!arbol || arbol.length === 0) return;
    setEstado("aplicando");
    try {
      await onAplicar(arbol);
      cerrar();
    } catch {
      setMensajeError("No se pudo aplicar el árbol a la página.");
      setEstado("error");
    }
  }

  // cerrar(): vuelve a retraída y limpia todo el resultado — a diferencia
  // de solo "expandida=false", esto es lo que dispara "Cancelar"/la "×" o
  // un aplicar exitoso: el próximo prompt empieza de cero, sin arrastrar
  // avisos/preview de la generación anterior.
  function cerrar() {
    setExpandida(false);
    setEstado("listo");
    setPrompt("");
    setAvisos([]);
    setArbol(null);
    setHtml("");
    setMensajeError("");
  }

  const srcDocPreview = `<!doctype html><html><head><meta charset="utf-8">${(config.hojasEstiloTema || [])
    .map((href) => `<link rel="stylesheet" href="${href}">`)
    .join("")}</head><body class="sofia-pagina">${html}</body></html>`;

  const ocupado = estado === "generando" || estado === "aplicando";

  return (
    <div className={`sofia-franja-ia ${expandida ? "sofia-franja-ia--expandida" : ""}`}>
      <div className="sofia-franja-ia__cabecera">
        <span className="sofia-franja-ia__icono">✨</span>
        <input
          className="sofia-franja-ia__input"
          value={prompt}
          onInput={(evento) => setPrompt(evento.currentTarget.value)}
          onKeyDown={(evento) => evento.key === "Enter" && !ocupado && generar()}
          placeholder="Describí qué querés agregar a esta página…"
          disabled={ocupado}
        />
        {expandida ? (
          <button type="button" className="sofia-franja-ia__cerrar" onClick={cerrar} disabled={ocupado} title="Cerrar">
            ×
          </button>
        ) : (
          <button type="button" className="sofia-franja-ia__generar" onClick={generar} disabled={!prompt.trim()}>
            Generar
          </button>
        )}
      </div>

      {expandida && (
        <div className="sofia-franja-ia__cuerpo">
          {estado === "generando" && <p className="sofia-franja-ia__estado">Generando… puede tardar hasta un par de minutos.</p>}

          {mensajeError && <p className="sofia-franja-ia__error">{mensajeError}</p>}

          {avisos.length > 0 && (
            <div className="sofia-franja-ia__avisos">
              <strong>La IA propuso algo que se tuvo que ajustar:</strong>
              <ul>
                {avisos.map((aviso, indice) => (
                  <li key={indice}>{aviso}</li>
                ))}
              </ul>
            </div>
          )}

          {estado === "generado" && arbol && arbol.length > 0 && (
            <div className="sofia-franja-ia__preview">
              <iframe
                className="sofia-franja-ia__preview-iframe"
                srcDoc={srcDocPreview}
                title="Vista previa de bloques generados por IA"
                sandbox=""
              />
              <div className="sofia-franja-ia__acciones">
                <button type="button" className="sofia-franja-ia__aplicar" onClick={aplicar} disabled={estado === "aplicando"}>
                  {estado === "aplicando" ? "Agregando…" : "Agregar a la página"}
                </button>
                <button type="button" className="sofia-franja-ia__cancelar" onClick={cerrar} disabled={estado === "aplicando"}>
                  Cancelar
                </button>
                <span className="sofia-franja-ia__aviso-lento">Nada de esto está guardado todavía</span>
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  );
}

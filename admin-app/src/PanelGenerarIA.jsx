import { useState } from "preact/hooks";

/**
 * Panel "Generar con IA" (Fase 4 del plan de "primitivas de layout" — ver la
 * memoria de producto) — primera pasada del mecanismo, mismo criterio de
 * alcance acotado que las fases anteriores (Fase 1 "valida el mecanismo",
 * Fase 3 "editor manual antes de IA"): un textarea de prompt, un botón
 * Generar, un preview, y "Aplicar"/"Cancelar". Sin pantalla nueva grande ni
 * pulida — vive detrás de su propio botón en la barra superior, mismo lugar
 * y mismo patrón visual (modal centrado) que PanelEstiloGlobal.jsx.
 *
 * Flujo (3 pasos, cada uno un request propio — nunca un solo mega-endpoint):
 *  1. POST sofia/v1/ia/generar {prompt} → {arbol_reparado, avisos}. El
 *     árbol YA viene validado/reparado contra el catálogo real de este
 *     tema (ver Sofia_REST_Editor::ia_generar_arbol() del lado PHP) — este
 *     panel nunca confía en el árbol crudo del LLM, solo en lo que PHP ya
 *     revisó.
 *  2. POST sofia/v1/ia/preview {arbol} → {html}. Renderiza el árbol YA
 *     reparado con el mismo Sofia_Pagina/Sofia_Componente_Factory que
 *     cualquier página real, SIN guardar nada en GoPress — mismo criterio
 *     de seguridad del plan ("el LLM nunca escribe código que se ejecuta").
 *     Se llama automáticamente apenas llega el árbol reparado del paso 1,
 *     así el usuario ve el resultado sin un click extra.
 *  3. "Aplicar a la página" reusa el mismo guardarEstructura() que YA
 *     existe en App.jsx (el mismo PUT sofia/v1/paginas/{slug}/estructura
 *     que usa cualquier otro cambio de Nivel 2) — nunca un endpoint nuevo,
 *     tal como pide el plan. onAplicado() (pasado por App.jsx) hace el
 *     guardado real + recarga del iframe; este panel solo le entrega el
 *     árbol ya confirmado por el usuario.
 *
 * Preview vía <iframe srcdoc="..."> en vez de un <div
 * dangerouslySetInnerHTML>: decisión documentada acá porque el plan pedía
 * elegir y justificar. El HTML que devuelve ia/preview es el de
 * Sofia_Componente::render() real — las MISMAS <section> con
 * data-sofia-bloque-* y clases sofia-xxx que ya arma el tema, pensadas para
 * vivir dentro del <body> completo del tema (con el CSS de Core Framework +
 * style.css del tema cargados en el <head>). Un <div
 * dangerouslySetInnerHTML> lo insertaría DESNUDO dentro del DOM del propio
 * admin-app (que corre en el contexto de wp-admin, con SU PROPIO CSS y sin
 * ninguno de los estilos del tema) — el resultado no se parecería en nada a
 * cómo se va a ver la página real, y closest("section")/selectores CSS de
 * Sofia Studio (".sofia-hero", etc.) podrían chocar por nombre con clases
 * de wp-admin. Un <iframe srcdoc> aísla el HTML en su propio documento
 * (mismo mecanismo de sandboxing implícito que YA usa el iframe principal
 * del editor, ver App.jsx/urlIframe) — se le puede inyectar un <head> con
 * los mismos <link> de CSS del tema activo (ver headHTML más abajo, leído
 * de config) para que el preview se vea razonablemente parecido a la
 * página real, sin arriesgar que el HTML del bloque (nunca 100% de fiar,
 * viene de un LLM que pasó por reparación pero no por un sanitizador HTML
 * completo) interfiera con el propio DOM/JS de este panel.
 */
export function PanelGenerarIA({ config, onCerrar, onAplicar }) {
  const [prompt, setPrompt] = useState("");
  const [estado, setEstado] = useState("listo"); // "listo" | "generando" | "generado" | "aplicando" | "error"
  const [avisos, setAvisos] = useState([]);
  const [arbol, setArbol] = useState(null);
  const [html, setHtml] = useState("");
  const [mensajeError, setMensajeError] = useState("");

  async function generar() {
    if (!prompt.trim()) return;
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

      // Preview automático apenas llega el árbol reparado — el usuario no
      // necesita un segundo click para "Generar" y otro para "Ver
      // preview", un solo botón alcanza para el flujo completo.
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
      onCerrar();
    } catch {
      setMensajeError("No se pudo aplicar el árbol a la página.");
      setEstado("error");
    }
  }

  // srcdoc completo: head mínimo (charset + los mismos <link> de CSS del
  // tema que config ya expone, ver config.hojasEstiloTema en App.jsx/PHP
  // que arma "config") + el HTML de los bloques dentro de un <body> con la
  // misma clase "sofia-pagina" que usa el tema real (ver page.php) — así
  // cualquier selector de style.css que dependa de ese ancestro (ej. reglas
  // de espaciado entre secciones hijas directas) sigue aplicando dentro
  // del preview.
  const srcDocPreview = `<!doctype html><html><head><meta charset="utf-8">${(config.hojasEstiloTema || [])
    .map((href) => `<link rel="stylesheet" href="${href}">`)
    .join("")}</head><body class="sofia-pagina">${html}</body></html>`;

  return (
    <div className="sofia-panel-ia__fondo" onClick={onCerrar}>
      <div className="sofia-panel-ia" onClick={(evento) => evento.stopPropagation()}>
        <div className="sofia-panel-ia__cabecera">
          <h2>Generar bloques con IA</h2>
          <button type="button" className="sofia-panel-ia__cerrar" onClick={onCerrar} title="Cerrar">
            ×
          </button>
        </div>

        <div className="sofia-panel-ia__cuerpo">
          <label className="sofia-panel-ia__campo-prompt">
            <span>Describí qué querés agregar a esta página</span>
            <textarea
              value={prompt}
              onInput={(evento) => setPrompt(evento.currentTarget.value)}
              placeholder="Ej: un Hero de bienvenida y abajo una sección de 3 beneficios"
              rows={3}
              disabled={estado === "generando" || estado === "aplicando"}
            />
          </label>

          <div className="sofia-panel-ia__acciones">
            <button
              type="button"
              className="sofia-panel-ia__generar"
              onClick={generar}
              disabled={!prompt.trim() || estado === "generando" || estado === "aplicando"}
            >
              {estado === "generando" ? "Generando…" : "Generar"}
            </button>
            <span className="sofia-panel-ia__aviso-lento">
              Puede tardar hasta un par de minutos — genera el árbol completo con IA.
            </span>
          </div>

          {mensajeError && <p className="sofia-panel-ia__error">{mensajeError}</p>}

          {avisos.length > 0 && (
            <div className="sofia-panel-ia__avisos">
              <strong>La IA propuso algo que se tuvo que ajustar:</strong>
              <ul>
                {avisos.map((aviso, indice) => (
                  <li key={indice}>{aviso}</li>
                ))}
              </ul>
            </div>
          )}

          {estado === "generado" && arbol && arbol.length > 0 && (
            <div className="sofia-panel-ia__preview">
              <p className="sofia-panel-ia__preview-titulo">Vista previa (nada de esto está guardado todavía):</p>
              <iframe
                className="sofia-panel-ia__preview-iframe"
                srcDoc={srcDocPreview}
                title="Vista previa de bloques generados por IA"
                sandbox=""
              />
              <div className="sofia-panel-ia__confirmar">
                <button type="button" className="sofia-panel-ia__aplicar" onClick={aplicar} disabled={estado === "aplicando"}>
                  {estado === "aplicando" ? "Aplicando…" : "Aplicar a la página"}
                </button>
                <button type="button" className="sofia-panel-ia__cancelar" onClick={onCerrar}>
                  Cancelar
                </button>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

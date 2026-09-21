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
export function FranjaGenerarIA({ config, onAplicar, onComponenteCreado }) {
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
  // modo: que devolvio el servidor, "pagina" o "componente". Una sola
  // caja genera las dos cosas (el backend clasifica el pedido, ver
  // pide_un_componente en Sofia_REST_Editor), asi que la franja tiene que
  // saber cual llego para mostrar los controles correctos.
  const [modo, setModo] = useState("pagina");
  const [definicion, setDefinicion] = useState(null);
  const [cssComponente, setCssComponente] = useState("");

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

      setAvisos(datosGenerar.avisos || []);
      setModo(datosGenerar.modo || "pagina");

      // Un COMPONENTE ya viene con su HTML renderizado: no hace falta el
      // segundo request de preview, que solo existe para armar el HTML de
      // un arbol de pagina.
      if (datosGenerar.modo === "componente") {
        setDefinicion(datosGenerar.definicion || null);
        setCssComponente(datosGenerar.css || "");
        setHtml(datosGenerar.html || "");
        setEstado("generado");
        return;
      }

      const arbolReparado = datosGenerar.arbol_reparado || [];
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
    setEstado("aplicando");
    try {
      if (modo === "componente") {
        if (!definicion) return;
        // Dos pasos: primero entra al catalogo del sitio (queda
        // disponible en TODAS las paginas), despues se inserta en esta.
        const resp = await fetch(`${config.restUrl}componentes-generados`, {
          method: "POST",
          headers: { "Content-Type": "application/json", "X-WP-Nonce": config.nonce },
          body: JSON.stringify({ definicion }),
        });
        const datos = await resp.json().catch(() => null);
        if (!resp.ok || !datos) {
          setMensajeError((datos && datos.message) || "No se pudo guardar el componente.");
          setEstado("error");
          return;
        }
        await onComponenteCreado(datos.tipo);
        cerrar();
        return;
      }

      if (!arbol || arbol.length === 0) return;
      await onAplicar(arbol);
      cerrar();
    } catch {
      setMensajeError("No se pudo aplicar lo generado a la página.");
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
    setModo("pagina");
    setDefinicion(null);
    setCssComponente("");
  }

  // El CSS de un componente generado todavia no esta en el <head> del
  // sitio (recien se imprime cuando esta guardado), asi que el preview lo
  // inyecta aparte. Para una pagina queda vacio.
  const srcDocPreview = `<!doctype html><html><head><meta charset="utf-8">${cssComponente ? `<style>${cssComponente}</style>` : ""}${(config.hojasEstiloTema || [])
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

          {estado === "generado" && (modo === "componente" ? definicion : arbol && arbol.length > 0) && (
            <div className="sofia-franja-ia__preview">
              {/*
                Decir QUÉ se generó, no solo mostrarlo. Una sola caja
                produce dos cosas distintas y el backend clasifica el
                pedido: cuando se equivoque, el usuario tiene que poder
                verlo ANTES de aplicar. Reformular el pedido nombrando
                "página" o "un hero" corrige la clasificación.
              */}
              <p className="sofia-franja-ia__modo">
                {modo === "componente" ? (
                  <>
                    Componente nuevo: <strong>{definicion.nombre}</strong>. Queda disponible en todas las páginas del sitio.
                  </>
                ) : (
                  <>
                    Bloques para esta página: <strong>{arbol.length}</strong>.
                  </>
                )}
              </p>

              <iframe
                className="sofia-franja-ia__preview-iframe"
                srcDoc={srcDocPreview}
                title={modo === "componente" ? "Vista previa del componente generado" : "Vista previa de bloques generados por IA"}
                sandbox=""
              />
              <div className="sofia-franja-ia__acciones">
                <button type="button" className="sofia-franja-ia__aplicar" onClick={aplicar} disabled={estado === "aplicando"}>
                  {estado === "aplicando" ? "Agregando…" : modo === "componente" ? "Agregar al sitio" : "Agregar a la página"}
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

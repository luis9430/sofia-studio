import { useEffect, useRef, useState } from "preact/hooks";

// debounce simple: junta ediciones rápidas del mismo campo (ej. varias
// pulsaciones de blur/focus seguidas) antes de llamar al proxy REST — el
// iframe ya solo notifica en "blur", así que esto es una segunda capa de
// seguridad, no el mecanismo principal de limitar llamadas.
const RETRASO_GUARDADO_MS = 400;

/**
 * App es el panel completo del editor in-place: un iframe full-bleed (ocupa
 * toda la pantalla disponible, sin bordes ni chrome de WordPress visible —
 * ver Sofia_Panel_Editor::ocultar_chrome_admin en el tema, y la memoria de
 * producto "Sofia Studio" sobre por qué se eligió pulir el iframe en vez de
 * eliminarlo) con una barra de estado flotante SOBRE el contenido, nunca
 * empujando el layout — mismo patrón documentado en Bricks/Elementor/AEM:
 * los controles viven fuera del documento del iframe, superpuestos.
 *
 * El iframe nunca guarda nada por su cuenta (ver inc/js/editor-iframe.js)
 * — solo informa cambios vía postMessage, que este componente escucha y
 * persiste llamando al proxy REST de WordPress (mismo origen, sin CORS —
 * ver inc/class-rest-editor.php).
 */
export function App({ config }) {
  const iframeRef = useRef(null);
  const timersPorCampo = useRef({});
  const [estado, setEstado] = useState("listo"); // "listo" | "guardando" | "guardado" | "error"

  useEffect(() => {
    function alRecibirMensaje(evento) {
      const datos = evento.data;
      if (!datos || typeof datos !== "object") return;
      if (datos.tipo === "sofia:campo-editado") {
        programarGuardado(datos.campo, datos.valor);
      }
    }

    window.addEventListener("message", alRecibirMensaje);
    return () => window.removeEventListener("message", alRecibirMensaje);
  }, []);

  function programarGuardado(campo, valor) {
    clearTimeout(timersPorCampo.current[campo]);
    timersPorCampo.current[campo] = setTimeout(() => guardarCampo(campo, valor), RETRASO_GUARDADO_MS);
  }

  async function guardarCampo(campo, valor) {
    setEstado("guardando");
    try {
      const respuesta = await fetch(`${config.restUrl}paginas/${config.slug}/campo`, {
        method: "PUT",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": config.nonce,
        },
        body: JSON.stringify({ campo, valor }),
      });
      setEstado(respuesta.ok ? "guardado" : "error");
    } catch {
      setEstado("error");
    }
  }

  const urlIframe = `${config.urlPagina}${config.urlPagina.includes("?") ? "&" : "?"}sofia_editor=1`;

  return (
    <div className="sofia-editor-admin">
      <div className="sofia-editor-admin__barra-flotante">
        <a href={config.urlSalir} className="sofia-editor-admin__salir">
          ← Volver
        </a>
        <span className={`sofia-editor-admin__estado sofia-editor-admin__estado--${estado}`}>
          {estado === "guardando" && "Guardando…"}
          {estado === "guardado" && "Guardado"}
          {estado === "error" && "No se pudo guardar"}
          {estado === "listo" && "Hacé click en un texto o imagen para editarlo"}
        </span>
      </div>
      <iframe ref={iframeRef} src={urlIframe} title="Editor de página" className="sofia-editor-admin__iframe" />
    </div>
  );
}

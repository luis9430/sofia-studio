import { useEffect, useRef, useState } from "preact/hooks";
import { BarraFormato } from "./BarraFormato.jsx";
import { ResaltadoBloque } from "./ResaltadoBloque.jsx";

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
 * los controles viven fuera del documento del iframe, superpuestos. La
 * barra de formato (BarraFormato.jsx) sigue el mismo patrón para
 * negrita/cursiva al seleccionar texto.
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
  const [posicionSeleccion, setPosicionSeleccion] = useState(null);
  const [bloqueResaltado, setBloqueResaltado] = useState(null);

  useEffect(() => {
    function alRecibirMensaje(evento) {
      const datos = evento.data;
      if (!datos || typeof datos !== "object") return;

      if (datos.tipo === "sofia:campo-editado") {
        programarGuardado(datos.campo, datos.valor);
        return;
      }
      if (datos.tipo === "sofia:seleccion-texto") {
        setPosicionSeleccion(datos.rect);
        return;
      }
      if (datos.tipo === "sofia:seleccion-vacia") {
        setPosicionSeleccion(null);
        return;
      }
      if (datos.tipo === "sofia:bloque-resaltado") {
        setBloqueResaltado({ rect: datos.rect, nombre: datos.nombre });
        return;
      }
      if (datos.tipo === "sofia:bloque-sin-resaltar") {
        setBloqueResaltado(null);
        return;
      }
      if (datos.tipo === "sofia:estructura-reordenada") {
        guardarEstructura(datos.tipos);
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

  // Nivel 2 — reordenar bloques: el iframe ya movió los nodos reales
  // (SortableJS corre ADENTRO del documento del iframe, ver
  // activarReordenar() en editor-iframe.js) y solo informa el resultado
  // final (la lista de tipos en el nuevo orden). Este panel no reordena
  // nada por su cuenta, solo persiste ese orden ya decidido — sin
  // debounce: a diferencia de un campo de texto (que dispara en cada
  // "blur"), un drag termina en un único evento "onEnd".
  async function guardarEstructura(tipos) {
    setEstado("guardando");
    try {
      const respuesta = await fetch(`${config.restUrl}paginas/${config.slug}/estructura`, {
        method: "PUT",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": config.nonce,
        },
        body: JSON.stringify({ estructura: tipos.map((tipo) => ({ tipo })) }),
      });
      setEstado(respuesta.ok ? "guardado" : "error");
    } catch {
      setEstado("error");
    }
  }

  // El propio iframe ejecuta document.execCommand sobre su selección real
  // (ver alRecibirMensajeDelPadre en editor-iframe.js) — este panel nunca
  // toca el DOM del iframe directo, solo le pide que aplique el comando.
  function aplicarFormato(comando) {
    iframeRef.current?.contentWindow.postMessage({ tipo: "sofia:aplicar-formato", comando }, "*");
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
      <ResaltadoBloque bloque={bloqueResaltado} />
      <BarraFormato posicion={posicionSeleccion} onAplicarFormato={aplicarFormato} />
      <iframe ref={iframeRef} src={urlIframe} title="Editor de página" className="sofia-editor-admin__iframe" />
    </div>
  );
}

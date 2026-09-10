import { useRef, useState } from "preact/hooks";

/**
 * Drawer de estilo — panel lateral con pestañas (Estilo | Visibilidad |
 * Datos), decisión de diseño confirmada con el usuario tras comparar 3
 * variantes en un Artifact (una barra con todo, dos barras separadas, este
 * drawer). Nace con las 3 pestañas visibles desde el día 1 aunque solo
 * "Estilo" tenga controles reales — Visibilidad reusará el motor de
 * ReglaCondicion ya construido para automatizaciones, Datos ofrecerá fuente
 * dinámica (Post/Propiedad/Testimonio); ambas están diseñadas pero sin
 * código propio todavía, así que aparecen como "próx." en vez de ocultarse
 * — evita tener que re-maquetar el contenedor cuando tengan contenido real.
 *
 * Único control por campo: negrita/cursiva viven ACÁ (no en una barra de
 * formato aparte) — mismo criterio de "un solo lugar para todo lo de este
 * campo" que motivó el drawer en primer lugar.
 *
 * Vive FUERA del documento del iframe, nunca toca su DOM directo — manda
 * "sofia:aplicar-estilo"/"sofia:aplicar-formato" y deja que sea el propio
 * iframe quien aplique el cambio al elemento real (ver alAplicarEstilo /
 * alRecibirMensajeDelPadre en editor-iframe.js).
 *
 * Arrastrable por la cabecera — pedido explícito del usuario: en el último
 * bloque de una página, el drawer anclado a la esquina superior derecha
 * tapaba contenido sin espacio para esquivarlo. offsetArrastre es un
 * desplazamiento relativo a la posición CSS de anclaje (top/right fijos en
 * style.css) — se resetea a {0,0} en cada campo nuevo (ver la key en
 * App.jsx) para que el drawer siempre "aparezca" en su esquina de siempre,
 * en vez de arrastrar la posición vieja de un campo distinto.
 */
const PALETA_COLORES = [
  { valor: "", etiqueta: "Por defecto" },
  { valor: "#1c1a17", etiqueta: "Texto oscuro" },
  { valor: "#6b6459", etiqueta: "Texto suave" },
  { valor: "#d97a4d", etiqueta: "Acento" },
  { valor: "#ffffff", etiqueta: "Blanco" },
];

const ALINEACIONES = [
  { valor: "left", etiqueta: "Izquierda", icono: "≡" },
  { valor: "center", etiqueta: "Centro", icono: "≣" },
  { valor: "right", etiqueta: "Derecha", icono: "≡" },
];

export function DrawerEstilo({ campo, estilo, onCambiarEstilo, onAplicarFormato, onCerrar }) {
  const [tab, setTab] = useState("estilo");
  const [offsetArrastre, setOffsetArrastre] = useState({ x: 0, y: 0 });
  const arrastreRef = useRef(null); // { inicioX, inicioY, offsetInicial } mientras el mouse está presionado.

  if (!campo) return null;

  function actualizar(cambios) {
    onCambiarEstilo({ ...estilo, ...cambios });
  }

  function alPresionarCabecera(evento) {
    // Ignora clicks sobre botones de la propia cabecera (tabs, cerrar) —
    // solo el área libre de la cabecera arrastra el drawer.
    if (evento.target.closest("button")) return;
    arrastreRef.current = { inicioX: evento.clientX, inicioY: evento.clientY, offsetInicial: offsetArrastre };
    window.addEventListener("mousemove", alMoverMouse);
    window.addEventListener("mouseup", alSoltarMouse);
  }

  function alMoverMouse(evento) {
    if (!arrastreRef.current) return;
    const { inicioX, inicioY, offsetInicial } = arrastreRef.current;
    setOffsetArrastre({
      x: offsetInicial.x + (evento.clientX - inicioX),
      y: offsetInicial.y + (evento.clientY - inicioY),
    });
  }

  function alSoltarMouse() {
    arrastreRef.current = null;
    window.removeEventListener("mousemove", alMoverMouse);
    window.removeEventListener("mouseup", alSoltarMouse);
  }

  return (
    <div
      className="sofia-drawer-estilo"
      style={{ transform: `translate(${offsetArrastre.x}px, ${offsetArrastre.y}px)` }}
    >
      <div className="sofia-drawer-estilo__cabecera" onMouseDown={alPresionarCabecera}>
        <div className="sofia-drawer-estilo__tabs">
          <button
            type="button"
            className={`sofia-drawer-estilo__tab ${tab === "estilo" ? "sofia-drawer-estilo__tab--activo" : ""}`}
            onMouseDown={(evento) => evento.stopPropagation()}
            onClick={() => setTab("estilo")}
          >
            Estilo
          </button>
          <button
            type="button"
            className="sofia-drawer-estilo__tab sofia-drawer-estilo__tab--proximo"
            onMouseDown={(evento) => evento.stopPropagation()}
            title="Próximamente: condición de visibilidad por regla"
          >
            Visibilidad <span>próx.</span>
          </button>
          <button
            type="button"
            className="sofia-drawer-estilo__tab sofia-drawer-estilo__tab--proximo"
            onMouseDown={(evento) => evento.stopPropagation()}
            title="Próximamente: fuente dinámica (Post, Propiedad, Testimonio)"
          >
            Datos <span>próx.</span>
          </button>
        </div>
        <button
          type="button"
          className="sofia-drawer-estilo__cerrar"
          onMouseDown={(evento) => evento.stopPropagation()}
          onClick={onCerrar}
          title="Cerrar"
        >
          ×
        </button>
      </div>

      {tab === "estilo" && (
        <div className="sofia-drawer-estilo__cuerpo" onMouseDown={(evento) => evento.preventDefault()}>
          <div className="sofia-drawer-estilo__grupo">
            <span className="sofia-drawer-estilo__etiqueta">Texto</span>
            <div className="sofia-drawer-estilo__formato">
              <button type="button" onClick={() => onAplicarFormato("bold")} title="Negrita">
                <strong>B</strong>
              </button>
              <button type="button" onClick={() => onAplicarFormato("italic")} title="Cursiva">
                <em>I</em>
              </button>
            </div>
          </div>

          <div className="sofia-drawer-estilo__grupo">
            <span className="sofia-drawer-estilo__etiqueta">Alineación</span>
            <div className="sofia-drawer-estilo__alineaciones">
              {ALINEACIONES.map((op) => (
                <button
                  key={op.valor}
                  type="button"
                  className={`sofia-drawer-estilo__alineacion ${estilo.alineacion === op.valor ? "sofia-drawer-estilo__alineacion--activo" : ""}`}
                  title={op.etiqueta}
                  onClick={() => actualizar({ alineacion: estilo.alineacion === op.valor ? "" : op.valor })}
                >
                  {op.icono}
                </button>
              ))}
            </div>
          </div>

          <div className="sofia-drawer-estilo__grupo">
            <span className="sofia-drawer-estilo__etiqueta">Color del texto</span>
            <div className="sofia-drawer-estilo__swatches">
              {PALETA_COLORES.map((op) => (
                <button
                  key={op.valor || "default"}
                  type="button"
                  className={`sofia-drawer-estilo__swatch ${estilo.color === op.valor ? "sofia-drawer-estilo__swatch--activo" : ""} ${op.valor === "" ? "sofia-drawer-estilo__swatch--vacio" : ""}`}
                  style={op.valor ? { backgroundColor: op.valor } : undefined}
                  title={op.etiqueta}
                  onClick={() => actualizar({ color: op.valor })}
                />
              ))}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

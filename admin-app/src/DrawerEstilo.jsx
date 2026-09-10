import { useState } from "preact/hooks";

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
 * Igual que BarraFormato/MenuContextualBloque: vive FUERA del documento del
 * iframe, nunca toca su DOM directo — manda "sofia:aplicar-estilo" y deja
 * que sea el propio iframe quien aplique el cambio al elemento real (ver
 * alAplicarEstilo en editor-iframe.js).
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

export function DrawerEstilo({ campo, estilo, onCambiarEstilo, onCerrar }) {
  const [tab, setTab] = useState("estilo");

  if (!campo) return null;

  function actualizar(cambios) {
    onCambiarEstilo({ ...estilo, ...cambios });
  }

  return (
    <div className="sofia-drawer-estilo" onMouseDown={(evento) => evento.preventDefault()}>
      <div className="sofia-drawer-estilo__cabecera">
        <div className="sofia-drawer-estilo__tabs">
          <button
            type="button"
            className={`sofia-drawer-estilo__tab ${tab === "estilo" ? "sofia-drawer-estilo__tab--activo" : ""}`}
            onClick={() => setTab("estilo")}
          >
            Estilo
          </button>
          <button
            type="button"
            className="sofia-drawer-estilo__tab sofia-drawer-estilo__tab--proximo"
            title="Próximamente: condición de visibilidad por regla"
          >
            Visibilidad <span>próx.</span>
          </button>
          <button
            type="button"
            className="sofia-drawer-estilo__tab sofia-drawer-estilo__tab--proximo"
            title="Próximamente: fuente dinámica (Post, Propiedad, Testimonio)"
          >
            Datos <span>próx.</span>
          </button>
        </div>
        <button type="button" className="sofia-drawer-estilo__cerrar" onClick={onCerrar} title="Cerrar">
          ×
        </button>
      </div>

      {tab === "estilo" && (
        <div className="sofia-drawer-estilo__cuerpo">
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

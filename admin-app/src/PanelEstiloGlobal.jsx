import { useEffect, useState } from "preact/hooks";
import { CampoTokenVisual } from "./CampoTokenVisual.jsx";

/**
 * Panel de Estilo Global (Nivel 3) — configuración del SITIO completo,
 * distinto del drawer de campo/bloque (DrawerEstilo.jsx, que edita UN
 * elemento o sección de UNA página). Vive detrás de un botón propio en la
 * barra superior, nunca dentro del iframe ni anclado a ninguna posición del
 * canvas — no depende de que haya nada seleccionado.
 *
 * REDISEÑO COMPLETO (ver la memoria de producto, conversación "¿de verdad
 * nos sirve Estilo Global?"): la versión anterior de este panel INVENTABA
 * 8 variables propias del tema (--sofia-color-texto, etc.) — un color hex
 * fijo por defecto, con un botón CF opcional para referenciar un token. Eso
 * era un sistema paralelo a los 154 tokens reales de Core Framework, sin
 * agregar nada que CF no tuviera ya mejor resuelto (escalas coherentes,
 * responsive fluido real). Decisión acordada con el usuario: Estilo Global
 * deja de INVENTAR valores — su trabajo pasa a ser asignar ROLES, "el texto
 * principal de este sitio es el token primary de Core Framework". 12 roles
 * (ver Sofia_Estilo_Global::ROLES_SITIO, fuente de verdad — este panel los
 * pide vía sofia/v1/estilo-global/roles en vez de tenerlos hardcodeados,
 * mismo criterio "PHP decide qué controles existen" que ya rige Nivel 2
 * desde Fase 2) agrupados por categoría de CF (color/texto/radius/shadow/
 * space/breakpoint), cada uno resuelto con CampoTokenVisual.jsx — el mismo
 * selector con etiqueta humana + preview real que ya usa el drawer de
 * bloque, reusado tal cual acá (nunca un control paralelo).
 *
 * Shape del JSON guardado simplificado de {colores, tipografia, medidas} a
 * {roles: {rol: "cf:{token}"}, tipografia: {...}} — decisión explícita del
 * usuario ("puedes romper cualquier cosa, es demo y de prueba"), sin
 * necesidad de migrar sitios reales.
 *
 * "Tipografía base" (fuente de títulos/texto corrido) es la ÚNICA sección
 * que sigue como lista curada propia, sin tocar — Core Framework no expone
 * font-family, solo tamaños de texto (ya cubiertos por el rol
 * "tamano_base").
 */
export function PanelEstiloGlobal({ config, onCerrar }) {
  const [roles, setRoles] = useState(null); // {rol: {variable, categoria, etiqueta, token_fijo?}}
  const [estilo, setEstilo] = useState({ roles: {}, tipografia: {} });
  const [cargando, setCargando] = useState(true);
  const [guardando, setGuardando] = useState(false);

  useEffect(() => {
    Promise.all([
      fetch(`${config.restUrl}estilo-global/roles`, { headers: { "X-WP-Nonce": config.nonce } }).then((resp) =>
        resp.ok ? resp.json() : {}
      ),
      fetch(`${config.restUrl}estilo-global`, { headers: { "X-WP-Nonce": config.nonce } }).then((resp) =>
        resp.ok ? resp.json() : {}
      ),
    ])
      .then(([rolesRecibidos, datos]) => {
        setRoles(rolesRecibidos);
        setEstilo({ roles: datos.roles || {}, tipografia: datos.tipografia || {} });
      })
      .catch(() => {})
      .finally(() => setCargando(false));
  }, []);

  async function guardar(estiloNuevo) {
    setEstilo(estiloNuevo);
    setGuardando(true);
    try {
      await fetch(`${config.restUrl}estilo-global`, {
        method: "PUT",
        headers: { "Content-Type": "application/json", "X-WP-Nonce": config.nonce },
        body: JSON.stringify({ estilo_global: estiloNuevo }),
      });
    } finally {
      setGuardando(false);
    }
  }

  function actualizarRol(rol, valor) {
    guardar({ ...estilo, roles: { ...estilo.roles, [rol]: valor } });
  }

  function actualizarFuente(rolTipografia, valor) {
    guardar({ ...estilo, tipografia: { ...estilo.tipografia, [rolTipografia]: valor } });
  }

  // Agrupa los 12 roles por categoría de CF — mismo orden/etiquetas de
  // sección que se acordó en la conversación: Colores, luego Tipografía
  // (tamaño, no fuente — eso vive en su propia sección más abajo),
  // Espaciado y forma (space+radius+shadow juntos, son "cómo se siente la
  // caja" del sitio), y Pantalla (breakpoints).
  const rolesPorCategoria = roles
    ? Object.entries(roles).reduce((acc, [rol, definicion]) => {
        (acc[definicion.categoria] ||= []).push({ rol, ...definicion });
        return acc;
      }, {})
    : {};

  const FUENTES = [
    { valor: "", etiqueta: "Por defecto del tema" },
    { valor: "display", etiqueta: "Fraunces (display)" },
    { valor: "texto", etiqueta: "Inter (texto)" },
  ];

  return (
    <div className="sofia-panel-global__fondo" onClick={onCerrar}>
      <div className="sofia-panel-global" onClick={(evento) => evento.stopPropagation()}>
        <div className="sofia-panel-global__cabecera">
          <h2>Estilo global del sitio</h2>
          <button type="button" className="sofia-panel-global__cerrar" onClick={onCerrar} title="Cerrar">
            ×
          </button>
        </div>

        {cargando ? (
          <p className="sofia-panel-global__cargando">Cargando…</p>
        ) : (
          <div className="sofia-panel-global__cuerpo">
            <section className="sofia-panel-global__seccion">
              <h3>Colores</h3>
              <p className="sofia-panel-global__ayuda">
                Cada rol es un color real de la paleta de Core Framework de este sitio — aplica a todas las páginas.
              </p>
              <div className="sofia-panel-global__roles">
                {(rolesPorCategoria.color || []).map(({ rol, etiqueta }) => (
                  <div key={rol} className="sofia-panel-global__rol">
                    <span className="sofia-panel-global__rol-etiqueta">{etiqueta}</span>
                    <CampoTokenVisual
                      categoria="color"
                      valor={estilo.roles[rol]}
                      onCambiar={(valor) => actualizarRol(rol, valor)}
                      restUrl={config.restUrl}
                      nonce={config.nonce}
                    />
                  </div>
                ))}
              </div>
            </section>

            <section className="sofia-panel-global__seccion">
              <h3>Tipografía base</h3>
              <p className="sofia-panel-global__ayuda">
                Fuente: lista corta curada, las mismas 2 que ya carga el tema. Tamaño: de la escala real de Core
                Framework.
              </p>
              <div className="sofia-panel-global__tipografia">
                <label>
                  <span>Fuente de títulos (display)</span>
                  <select
                    value={estilo.tipografia.display || ""}
                    onChange={(evento) => actualizarFuente("display", evento.currentTarget.value)}
                  >
                    {FUENTES.map((op) => (
                      <option key={op.valor || "default"} value={op.valor}>
                        {op.etiqueta}
                      </option>
                    ))}
                  </select>
                </label>
                <label>
                  <span>Fuente de texto corrido</span>
                  <select
                    value={estilo.tipografia.texto || ""}
                    onChange={(evento) => actualizarFuente("texto", evento.currentTarget.value)}
                  >
                    {FUENTES.map((op) => (
                      <option key={op.valor || "default"} value={op.valor}>
                        {op.etiqueta}
                      </option>
                    ))}
                  </select>
                </label>
              </div>
              <div className="sofia-panel-global__roles">
                {(rolesPorCategoria.texto || []).map(({ rol, etiqueta }) => (
                  <div key={rol} className="sofia-panel-global__rol">
                    <span className="sofia-panel-global__rol-etiqueta">{etiqueta}</span>
                    <CampoTokenVisual
                      categoria="texto"
                      valor={estilo.roles[rol]}
                      onCambiar={(valor) => actualizarRol(rol, valor)}
                      restUrl={config.restUrl}
                      nonce={config.nonce}
                    />
                  </div>
                ))}
              </div>
            </section>

            <section className="sofia-panel-global__seccion">
              <h3>Espaciado y forma</h3>
              <p className="sofia-panel-global__ayuda">
                Cómo se siente la caja del sitio por defecto — espaciado entre secciones, radio de borde, sombra.
              </p>
              <div className="sofia-panel-global__roles">
                {[...(rolesPorCategoria.space || []), ...(rolesPorCategoria.radius || []), ...(rolesPorCategoria.shadow || [])].map(
                  ({ rol, categoria, etiqueta }) => (
                    <div key={rol} className="sofia-panel-global__rol">
                      <span className="sofia-panel-global__rol-etiqueta">{etiqueta}</span>
                      <CampoTokenVisual
                        categoria={categoria}
                        valor={estilo.roles[rol]}
                        onCambiar={(valor) => actualizarRol(rol, valor)}
                        restUrl={config.restUrl}
                        nonce={config.nonce}
                      />
                    </div>
                  )
                )}
              </div>
            </section>

            {(rolesPorCategoria.breakpoint || []).length > 0 && (
              <section className="sofia-panel-global__seccion">
                <h3>Pantalla</h3>
                <p className="sofia-panel-global__ayuda">
                  Activá los breakpoints ya definidos en Core Framework para este sitio — decisión del usuario: "mejor
                  que lo maneje CF", Sofia Studio solo detecta y activa el token real, nunca inventa un valor propio.
                </p>
                <div className="sofia-panel-global__roles">
                  {rolesPorCategoria.breakpoint.map(({ rol, etiqueta, token_fijo }) => (
                    <div key={rol} className="sofia-panel-global__rol sofia-panel-global__rol--fila">
                      <span className="sofia-panel-global__rol-etiqueta">
                        {etiqueta}
                        <code className="sofia-panel-global__token-fijo">--{token_fijo}</code>
                      </span>
                      <button
                        type="button"
                        className={`sofia-drawer-estilo__toggle ${estilo.roles[rol] ? "sofia-drawer-estilo__toggle--activo" : ""}`}
                        onClick={() => actualizarRol(rol, estilo.roles[rol] ? "" : "activo")}
                      >
                        {estilo.roles[rol] ? "Activado" : "Desactivado"}
                      </button>
                    </div>
                  ))}
                </div>
              </section>
            )}

            <p className="sofia-panel-global__estado">{guardando ? "Guardando…" : "Los cambios se guardan al instante"}</p>
          </div>
        )}
      </div>
    </div>
  );
}

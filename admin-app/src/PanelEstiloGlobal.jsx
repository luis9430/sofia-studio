import { useEffect, useState } from "preact/hooks";

/**
 * Panel de Estilo Global (Nivel 3) — configuración del SITIO completo
 * (paleta de colores + tipografía base), distinto del drawer de campo/
 * bloque (DrawerEstilo.jsx, que edita UN elemento o sección de UNA
 * página). Vive detrás de un botón propio en la barra superior, nunca
 * dentro del iframe ni anclado a ninguna posición del canvas — no depende
 * de que haya nada seleccionado.
 *
 * Se guarda vía sofia/v1/estilo-global (GET/PUT), que a su vez persiste en
 * store.Sitio.EstiloGlobal del lado de GoPress — sobrevive a reinstalar el
 * tema, a diferencia de cualquier config que viviera en wp_options. El
 * tema imprime estas mismas custom properties en CADA visita pública (ver
 * Sofia_Estilo_Global::imprimir(), enganchado a wp_head).
 *
 * Mismas claves que la whitelist del lado PHP
 * (Sofia_Estilo_Global::COLORES_PERMITIDOS/FUENTES_PERMITIDAS) — nunca CSS
 * arbitrario, un color hex libre por rol y una fuente de una lista corta
 * curada, mismo criterio que el resto del editor.
 *
 * Cada color puede ser un hex normal O una referencia a un token de Core
 * Framework (coreframework.com) — guardado como "cf:{nombre}" (ver
 * Sofia_Estilo_Global::PREFIJO_TOKEN_CORE_FRAMEWORK/resolver_valor() del
 * lado PHP, que lo traduce a var(--cf-{nombre}, ...) en el CSS emitido).
 * Core Framework NO expone la lista de tokens que un sitio definió (ni vía
 * API ni vía opción legible de WordPress, investigado contra su código
 * fuente real) — así que el campo es texto libre, el usuario escribe el
 * nombre tal como lo llamó en el editor visual de Core Framework, nunca un
 * dropdown poblado automáticamente.
 */
const PREFIJO_TOKEN_CORE_FRAMEWORK = "cf:";

const ROLES_COLOR = [
  { clave: "texto", etiqueta: "Texto principal" },
  { clave: "texto_suave", etiqueta: "Texto suave" },
  { clave: "acento", etiqueta: "Acento" },
  { clave: "fondo", etiqueta: "Fondo" },
];

const FUENTES = [
  { valor: "", etiqueta: "Por defecto del tema" },
  { valor: "display", etiqueta: "Fraunces (display)" },
  { valor: "texto", etiqueta: "Inter (texto)" },
];

export function PanelEstiloGlobal({ config, onCerrar }) {
  const [estilo, setEstilo] = useState({ colores: {}, tipografia: {} });
  const [cargando, setCargando] = useState(true);
  const [guardando, setGuardando] = useState(false);

  useEffect(() => {
    fetch(`${config.restUrl}estilo-global`, { headers: { "X-WP-Nonce": config.nonce } })
      .then((resp) => (resp.ok ? resp.json() : {}))
      .then((datos) => setEstilo({ colores: datos.colores || {}, tipografia: datos.tipografia || {} }))
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

  function actualizarColor(clave, valor) {
    guardar({ ...estilo, colores: { ...estilo.colores, [clave]: valor } });
  }

  function actualizarFuente(rol, valor) {
    guardar({ ...estilo, tipografia: { ...estilo.tipografia, [rol]: valor } });
  }

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
              <h3>Paleta de colores</h3>
              <p className="sofia-panel-global__ayuda">
                Aplica a todas las páginas del sitio. Con el botón <strong>CF</strong> podés usar un token ya definido
                en Core Framework (si el plugin está activo en este sitio) en vez de un color fijo — escribí el
                nombre tal como lo llamaste ahí.
              </p>
              <div className="sofia-panel-global__colores">
                {ROLES_COLOR.map((rol) => {
                  const valorActual = estilo.colores[rol.clave] || "";
                  const esToken = valorActual.startsWith(PREFIJO_TOKEN_CORE_FRAMEWORK);
                  const nombreToken = esToken ? valorActual.slice(PREFIJO_TOKEN_CORE_FRAMEWORK.length) : "";

                  return (
                    <div key={rol.clave} className="sofia-panel-global__color">
                      <div className="sofia-panel-global__color-fila">
                        {esToken ? (
                          <input
                            type="text"
                            className="sofia-panel-global__color-token"
                            placeholder="nombre-del-token"
                            value={nombreToken}
                            onInput={(evento) =>
                              actualizarColor(rol.clave, PREFIJO_TOKEN_CORE_FRAMEWORK + evento.currentTarget.value)
                            }
                          />
                        ) : (
                          <input
                            type="color"
                            value={valorActual || "#1c1a17"}
                            onInput={(evento) => actualizarColor(rol.clave, evento.currentTarget.value)}
                          />
                        )}
                        <button
                          type="button"
                          className={`sofia-panel-global__color-cf ${esToken ? "sofia-panel-global__color-cf--activo" : ""}`}
                          title={
                            esToken
                              ? "Usando un token de Core Framework — click para volver a un color fijo"
                              : "Usar un token de Core Framework en vez de un color fijo"
                          }
                          onClick={() => actualizarColor(rol.clave, esToken ? "#1c1a17" : PREFIJO_TOKEN_CORE_FRAMEWORK)}
                        >
                          CF
                        </button>
                      </div>
                      <span>{rol.etiqueta}</span>
                    </div>
                  );
                })}
              </div>
            </section>

            <section className="sofia-panel-global__seccion">
              <h3>Tipografía base</h3>
              <p className="sofia-panel-global__ayuda">
                Lista corta curada — las mismas 2 fuentes que ya carga el tema, para mantener cohesión visual.
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
            </section>

            <p className="sofia-panel-global__estado">{guardando ? "Guardando…" : "Los cambios se guardan al instante"}</p>
          </div>
        )}
      </div>
    </div>
  );
}

import { useEffect, useState } from "preact/hooks";
import { CampoConToken } from "./CampoConToken.jsx";

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
 * (Sofia_Estilo_Global::COLORES_PERMITIDOS/FUENTES_PERMITIDAS/MEDIDAS_PERMITIDAS)
 * — nunca CSS arbitrario, solo lo que este panel realmente ofrece como
 * control: color hex/medida libre por rol, fuente de una lista corta
 * curada.
 *
 * Cada color O medida puede ser un valor fijo O una referencia a un token
 * de Core Framework (coreframework.com) — guardado como "cf:{nombre}" (ver
 * Sofia_Estilo_Global::PREFIJO_TOKEN_CORE_FRAMEWORK/resolver_valor() del
 * lado PHP, que lo traduce a var(--{nombre}, ...) en el CSS emitido — SIN
 * prefijo "cf-" propio, corrige una suposición equivocada de la primera
 * versión: el CSS real exportado por Core Framework usa nombres tal
 * cual, "--primary"/"--secondary", nunca "--cf-primary"). El mismo
 * mecanismo (botón CF, ver CampoConToken) es agnóstico a la categoría de
 * Core Framework (Colors, Typography, Spacing, etc.) — cualquier custom
 * property que el CSS real defina puede referenciarse desde cualquier
 * control de este panel, no hay lógica separada por categoría.
 *
 * Core Framework no expone una API de tokens, pero SÍ es un archivo CSS
 * legible desde PHP — sofia/v1/core-framework/variables (ver
 * Sofia_Estilo_Global::variables_core_framework()) lee ese archivo real y
 * devuelve los nombres encontrados, usados como sugerencias de un
 * <datalist> — el campo sigue siendo texto libre (el usuario puede
 * escribir un nombre que no esté en la lista, ej. si Core Framework
 * generó el CSS después de cargar este panel), pero ya no es "a ciegas".
 *
 * CampoConToken vive en su propio archivo (CampoConToken.jsx) — el mismo
 * control se reusa en DrawerEstilo.jsx (Nivel 1/2, estilo por campo/
 * bloque), nunca duplicado entre los 2 niveles. El <datalist> compartido
 * (ListaVariablesCoreFramework) vive montado en App.jsx, NO acá — el
 * botón CF también aparece en el drawer, que puede estar abierto sin que
 * este panel lo esté, así que el elemento con ese id necesita existir
 * siempre en el documento, sin importar cuál de los 2 está montado.
 */
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

const ROLES_MEDIDA = [
  { clave: "tamano_base", etiqueta: "Tamaño de fuente base", placeholder: "1rem" },
  { clave: "espaciado_base", etiqueta: "Espaciado base", placeholder: "1rem" },
];

export function PanelEstiloGlobal({ config, onCerrar }) {
  const [estilo, setEstilo] = useState({ colores: {}, tipografia: {}, medidas: {} });
  const [cargando, setCargando] = useState(true);
  const [guardando, setGuardando] = useState(false);

  useEffect(() => {
    fetch(`${config.restUrl}estilo-global`, { headers: { "X-WP-Nonce": config.nonce } })
      .then((resp) => (resp.ok ? resp.json() : {}))
      .then((datos) =>
        setEstilo({ colores: datos.colores || {}, tipografia: datos.tipografia || {}, medidas: datos.medidas || {} })
      )
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

  function actualizarMedida(clave, valor) {
    guardar({ ...estilo, medidas: { ...estilo.medidas, [clave]: valor } });
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
                {ROLES_COLOR.map((rol) => (
                  <div key={rol.clave} className="sofia-panel-global__color">
                    <CampoConToken
                      tipo="color"
                      valor={estilo.colores[rol.clave]}
                      onCambiar={(valor) => actualizarColor(rol.clave, valor)}
                    />
                    <span>{rol.etiqueta}</span>
                  </div>
                ))}
              </div>
            </section>

            <section className="sofia-panel-global__seccion">
              <h3>Medidas base</h3>
              <p className="sofia-panel-global__ayuda">
                Tamaño de fuente y espaciado de referencia del sitio — valor libre (ej. "1rem", "16px") o un token de
                Core Framework con el botón <strong>CF</strong> (ej. las categorías Typography/Spacing del editor
                visual).
              </p>
              <div className="sofia-panel-global__medidas">
                {ROLES_MEDIDA.map((rol) => (
                  <div key={rol.clave} className="sofia-panel-global__color">
                    <CampoConToken
                      tipo="text"
                      valor={estilo.medidas[rol.clave]}
                      placeholderNormal={rol.placeholder}
                      onCambiar={(valor) => actualizarMedida(rol.clave, valor)}
                    />
                    <span>{rol.etiqueta}</span>
                  </div>
                ))}
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

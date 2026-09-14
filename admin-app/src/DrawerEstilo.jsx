import { useEffect, useRef, useState } from "preact/hooks";
import { CampoConToken } from "./CampoConToken.jsx";
import { CamposDesdeSchema } from "./CampoDesdeSchema.jsx";

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
 * Un mismo componente sirve para 2 niveles de estilo, según la prop
 * `nivel`: "campo" (default, Nivel 1 — SOLO texto/contenido puntual de UN
 * elemento: alineación/tipografía/color/sombra de texto, abierto con el
 * botón ✏️ de BotonEditarCampo, controles hardcodeados acá tal cual) y
 * "bloque" (Nivel 2 — todo lo de CAJA/contenedor: columnas de grid, fondo,
 * borde, radius, sombra, espaciado vertical, ancho/ancho
 * máximo/aspect-ratio/object-fit/z-index, de la <section> completa, abierto
 * desde "Estilo del bloque" en el menú contextual, ver App.jsx). Regla de
 * qué va en cada nivel confirmada con el usuario tras auditar el catálogo
 * completo de Core Framework: "color_fondo"/"margen"/"relleno" vivían mal
 * ubicados en Nivel 1 (son propiedades de la CAJA del campo, no de su
 * texto) y se movieron a Nivel 2. Mismas pestañas/cabecera/arrastre para
 * los 2, solo cambia qué controles se listan en el cuerpo — evita duplicar
 * toda la mecánica de drawer (arrastre, overlay, pestañas) en un
 * componente aparte para un caso que en el fondo es "el mismo panel con
 * otro grupo de controles".
 *
 * Nivel 2 YA NO tiene sus controles hardcodeados acá — se piden vía
 * GET sofia/v1/catalogo-bloques/{tipoBloque}/schema (ver
 * Sofia_Componente::schema_bloque_generico()/schema_propio() del lado PHP)
 * y se renderizan con CampoDesdeSchema.jsx, así un Componente con
 * necesidades propias (ej. Container: dirección/gap/grilla) agrega
 * controles sin tocar este archivo — ver Fase 2 del plan de "primitivas de
 * layout" (memoria de producto). El PHP sigue mezclando los mismos 2
 * mecanismos de Core Framework que antes vivían acá como JSX, ahora como
 * `tipo` de entrada de schema: "color_token"/"medida_token" (CampoConToken,
 * VALOR que puede ser fijo o token "cf:{nombre}") vs. "select" (opciones
 * fijas, cada una YA es una utility class completa, ej. "aspect-16-9", que
 * Sofia_Componente::clases_utilitarias_bloque() agrega al classList de la
 * <section> — nunca llevan botón CF, no hay nada que tokenizar).
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
 * style.css) — persiste mientras el drawer sigue MONTADO, aunque el
 * usuario cambie de campo activo (ver App.jsx: el drawer no se remonta al
 * cambiar de campo, solo al cerrarse y volver a abrirse) — bug real
 * encontrado en la práctica: una key por campo remontaba el componente en
 * cada cambio de campo, reseteando la posición arrastrada de golpe cada
 * vez que el usuario elegía otro texto sin cerrar el drawer primero. Solo
 * vuelve a {0,0} cuando App.jsx desmonta el componente entero (drawerEstilo
 * pasa a null) y lo vuelve a montar desde cero.
 */
const ALINEACIONES = [
  { valor: "left", etiqueta: "Izquierda", icono: "≡" },
  { valor: "center", etiqueta: "Centro", icono: "≣" },
  { valor: "right", etiqueta: "Derecha", icono: "≡" },
];

// SOMBRA_TEXTO: valor PRE-ARMADO (no un input libre de rgba) — mismo
// criterio de whitelist que alineación/color, evita una sombra ilegible
// por accidente. "Set completo tipo Elementor, veamos qué tal se ve"
// (pedido explícito del usuario) — de acá se decide después qué se queda.
const SOMBRA_TEXTO = "0 2px 4px rgba(0, 0, 0, 0.35)";

// FUENTES: mismas 2 claves que Sofia_Componente::FUENTES_PERMITIDAS del
// lado PHP — lista corta curada (no cualquier fuente de Google Fonts, ver
// la decisión explícita del usuario) para no romper la cohesión visual del
// tema con una fuente que no combina.
const FUENTES = [
  { valor: "", etiqueta: "Por defecto" },
  { valor: "display", etiqueta: "Display (Fraunces)" },
  { valor: "texto", etiqueta: "Texto (Inter)" },
];

// VARIABLES_CONDICION: mismas claves que
// Sofia_Componente::variables_condicion() del lado PHP — lista corta
// curada (whitelist), nunca un campo de texto libre para "campo" como el
// builder de automatizaciones de GoPress permite (ahí cualquier dot-path
// del payload es válido; acá el universo de variables de WordPress es
// chico y conocido de antemano, así que un <select> es más simple y más
// seguro que pedirle al usuario que escriba "usuario_logueado" a mano).
const VARIABLES_CONDICION = [{ valor: "usuario_logueado", etiqueta: "Usuario logueado" }];

const OPERADORES_CONDICION = [
  { valor: "eq", etiqueta: "es igual a" },
  { valor: "ne", etiqueta: "es distinto de" },
];

// VALORES_CONDICION: acotado a "true"/"false" porque hoy la única
// variable (usuario_logueado) es booleana — mismo criterio de whitelist
// que el resto: si se agrega una variable de texto/número a futuro, este
// selector pasaría a ser un input libre solo para esa variable.
const VALORES_CONDICION = [
  { valor: "true", etiqueta: "Sí" },
  { valor: "false", etiqueta: "No" },
];

function reglaVacia(enlace = "y") {
  return { campo: "usuario_logueado", operador: "eq", valor: "true", enlace };
}

/**
 * EditorCondicion — pestaña Visibilidad del drawer (Nivel 2, solo bloque
 * completo). Edita `reglas`, un array con el MISMO shape que
 * store.ReglaCondicion del lado GoPress ({campo, operador, valor, enlace})
 * — reusado tal cual, el motor de evaluación ya existe para automatizaciones
 * (ver evaluarCondicion/evaluarRegla en
 * internal/temporal/workflow_automatizacion_builder.go) y su espejo mínimo
 * en PHP (Sofia_Componente::evaluar_regla_condicion()). "enlace" de la
 * PRIMERA regla nunca se usa (mismo criterio que el builder de
 * automatizaciones) — decide sola, cada regla siguiente se combina con la
 * anterior según su propio enlace.
 */
function EditorCondicion({ reglas, onCambiar }) {
  function actualizarRegla(indice, cambios) {
    onCambiar(reglas.map((r, i) => (i === indice ? { ...r, ...cambios } : r)));
  }

  function quitarRegla(indice) {
    onCambiar(reglas.filter((_, i) => i !== indice));
  }

  function agregarRegla() {
    onCambiar([...reglas, reglaVacia()]);
  }

  return (
    <div className="sofia-drawer-estilo__grupo">
      <span className="sofia-drawer-estilo__etiqueta">Mostrar este bloque solo si…</span>
      <p className="sofia-drawer-estilo__ayuda-condicion">
        Sin reglas, el bloque es siempre visible. Con reglas, se oculta para cualquier visitante que no las cumpla —
        en el editor seguís viéndolo, marcado como oculto.
      </p>

      {reglas.map((regla, indice) => (
        <div key={indice} className="sofia-drawer-estilo__regla">
          {indice > 0 && (
            <select
              className="sofia-drawer-estilo__regla-enlace"
              value={regla.enlace || "y"}
              onChange={(evento) => actualizarRegla(indice, { enlace: evento.currentTarget.value })}
            >
              <option value="y">Y</option>
              <option value="o">O</option>
            </select>
          )}
          <select value={regla.campo} onChange={(evento) => actualizarRegla(indice, { campo: evento.currentTarget.value })}>
            {VARIABLES_CONDICION.map((op) => (
              <option key={op.valor} value={op.valor}>
                {op.etiqueta}
              </option>
            ))}
          </select>
          <select
            value={regla.operador}
            onChange={(evento) => actualizarRegla(indice, { operador: evento.currentTarget.value })}
          >
            {OPERADORES_CONDICION.map((op) => (
              <option key={op.valor} value={op.valor}>
                {op.etiqueta}
              </option>
            ))}
          </select>
          <select value={regla.valor} onChange={(evento) => actualizarRegla(indice, { valor: evento.currentTarget.value })}>
            {VALORES_CONDICION.map((op) => (
              <option key={op.valor} value={op.valor}>
                {op.etiqueta}
              </option>
            ))}
          </select>
          <button
            type="button"
            className="sofia-drawer-estilo__regla-quitar"
            onClick={() => quitarRegla(indice)}
            title="Quitar regla"
          >
            ×
          </button>
        </div>
      ))}

      <button type="button" className="sofia-drawer-estilo__regla-agregar" onClick={agregarRegla}>
        + Regla
      </button>
    </div>
  );
}

export function DrawerEstilo({
  campo,
  tipoBloque,
  estilo,
  nivel = "campo",
  condicion,
  onCambiarEstilo,
  onCambiarCondicion,
  onAplicarFormato,
  onCerrar,
  tabInicial = "estilo",
  restUrl,
  nonce,
}) {
  const [tab, setTab] = useState(tabInicial);
  const [offsetArrastre, setOffsetArrastre] = useState({ x: 0, y: 0 });
  const [arrastrando, setArrastrando] = useState(false);
  const arrastreRef = useRef(null); // { inicioX, inicioY, offsetInicial } mientras el mouse está presionado.
  // schemaBloque: schema de Nivel 2 pedido a GoPress/WP vía
  // GET sofia/v1/catalogo-bloques/{tipoBloque}/schema — null mientras
  // carga o si nivel !== "bloque" (Nivel 1 no lo necesita). Se vuelve a
  // pedir si tipoBloque cambia (el usuario cierra el drawer y abre el de
  // OTRO bloque de distinto tipo sin desmontar del todo el árbol, aunque
  // en la práctica App.jsx sí lo desmonta al cerrar — de todas formas
  // correcto tenerlo como dependencia).
  const [schemaBloque, setSchemaBloque] = useState(null);

  useEffect(() => {
    if (nivel !== "bloque" || !tipoBloque || !restUrl) {
      setSchemaBloque(null);
      return;
    }
    let cancelado = false;
    fetch(`${restUrl}catalogo-bloques/${tipoBloque}/schema`, { headers: { "X-WP-Nonce": nonce } })
      .then((resp) => (resp.ok ? resp.json() : null))
      .then((datos) => {
        if (!cancelado) setSchemaBloque(datos);
      })
      .catch(() => {
        if (!cancelado) setSchemaBloque(null);
      });
    return () => {
      cancelado = true;
    };
  }, [nivel, tipoBloque, restUrl, nonce]);

  if (!campo) return null;

  function actualizar(cambios) {
    onCambiarEstilo({ ...estilo, ...cambios });
  }

  // arrastrando activa un overlay transparente que cubre TODO el viewport,
  // iframe incluido (ver JSX abajo) — bug real encontrado en la práctica:
  // el iframe es un documento distinto con su propio contexto de eventos;
  // en cuanto el cursor entraba al área del iframe DURANTE el arrastre
  // (mouse rápido, cosa casi inevitable con el drawer ancho), los eventos
  // "mousemove" dejaban de llegar a este documento por completo, y el
  // drawer se quedaba pegado en su posición original. El overlay, al vivir
  // en ESTE documento por encima del iframe, garantiza que el mouse nunca
  // "se va" a otro contexto mientras dura el drag.
  function alPresionarCabecera(evento) {
    // Ignora clicks sobre botones de la propia cabecera (tabs, cerrar) —
    // solo el área libre de la cabecera arrastra el drawer.
    if (evento.target.closest("button")) return;
    evento.preventDefault(); // evita selección de texto accidental mientras se arrastra.
    arrastreRef.current = { inicioX: evento.clientX, inicioY: evento.clientY, offsetInicial: offsetArrastre };
    setArrastrando(true);
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
    setArrastrando(false);
    window.removeEventListener("mousemove", alMoverMouse);
    window.removeEventListener("mouseup", alSoltarMouse);
  }

  return (
    <>
      {arrastrando && <div className="sofia-drawer-estilo__overlay-arrastre" />}
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
            {condicion ? (
              <button
                type="button"
                className={`sofia-drawer-estilo__tab ${tab === "visibilidad" ? "sofia-drawer-estilo__tab--activo" : ""}`}
                onMouseDown={(evento) => evento.stopPropagation()}
                onClick={() => setTab("visibilidad")}
              >
                Visibilidad
              </button>
            ) : (
              <button
                type="button"
                className="sofia-drawer-estilo__tab sofia-drawer-estilo__tab--proximo"
                onMouseDown={(evento) => evento.stopPropagation()}
                title="Visibilidad se edita desde 'Visibilidad del bloque' en el menú contextual (click derecho)"
              >
                Visibilidad
              </button>
            )}
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
          // Sin preventDefault en mousedown acá — bug real encontrado en la
          // práctica: bloqueaba el foco de CUALQUIER control, inputs de
          // texto/número incluidos (tamaño de fuente, margen, relleno), no
          // solo los botones tipo swatch para los que se había agregado
          // originalmente. El blur del campo editable en el iframe ya tiene
          // su propio setTimeout (ver activarTexto en editor-iframe.js) que
          // da margen de sobra para que un click en un botón del drawer
          // llegue antes de leerse el valor final — este preventDefault era
          // una capa redundante que rompía los inputs nuevos.
          <div className="sofia-drawer-estilo__cuerpo">
            {nivel === "campo" && (
              <>
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
                  <span className="sofia-drawer-estilo__etiqueta">Tamaño de fuente</span>
                  <CampoConToken
                    tipo="text"
                    categoria="texto"
                    conUnidad
                    valor={estilo.tamano_fuente}
                    onCambiar={(valor) => actualizar({ tamano_fuente: valor })}
                  />
                </div>

                <div className="sofia-drawer-estilo__grupo">
                  <span className="sofia-drawer-estilo__etiqueta">Tipo de fuente</span>
                  <select
                    className="sofia-drawer-estilo__select-fuente"
                    value={estilo.tipo_fuente || ""}
                    onChange={(evento) => actualizar({ tipo_fuente: evento.currentTarget.value })}
                  >
                    {FUENTES.map((op) => (
                      <option key={op.valor || "default"} value={op.valor}>
                        {op.etiqueta}
                      </option>
                    ))}
                  </select>
                </div>

                <div className="sofia-drawer-estilo__grupo sofia-drawer-estilo__grupo--fila">
                  <span className="sofia-drawer-estilo__etiqueta">Negrita</span>
                  <button
                    type="button"
                    className={`sofia-drawer-estilo__toggle ${estilo.negrita === "700" ? "sofia-drawer-estilo__toggle--activo" : ""}`}
                    onClick={() => actualizar({ negrita: estilo.negrita === "700" ? "" : "700" })}
                  >
                    {estilo.negrita === "700" ? "Activada" : "Desactivada"}
                  </button>
                </div>

                <div className="sofia-drawer-estilo__grupo">
                  <span className="sofia-drawer-estilo__etiqueta">Color del texto</span>
                  <CampoConToken tipo="color" valor={estilo.color} onCambiar={(valor) => actualizar({ color: valor })} />
                </div>

                <div className="sofia-drawer-estilo__grupo sofia-drawer-estilo__grupo--fila">
                  <span className="sofia-drawer-estilo__etiqueta">Sombra de texto</span>
                  <button
                    type="button"
                    className={`sofia-drawer-estilo__toggle ${estilo.sombra_texto ? "sofia-drawer-estilo__toggle--activo" : ""}`}
                    onClick={() => actualizar({ sombra_texto: estilo.sombra_texto ? "" : SOMBRA_TEXTO })}
                  >
                    {estilo.sombra_texto ? "Activada" : "Desactivada"}
                  </button>
                </div>
              </>
            )}

            {nivel === "bloque" && schemaBloque && (
              <CamposDesdeSchema schema={schemaBloque} estilo={estilo} actualizar={actualizar} />
            )}

            {nivel === "bloque" && !schemaBloque && (
              <p className="sofia-drawer-estilo__ayuda-condicion">Cargando controles del bloque…</p>
            )}
          </div>
        )}

        {tab === "visibilidad" && condicion && (
          <div className="sofia-drawer-estilo__cuerpo">
            <EditorCondicion reglas={condicion} onCambiar={onCambiarCondicion} />
          </div>
        )}
      </div>
    </>
  );
}

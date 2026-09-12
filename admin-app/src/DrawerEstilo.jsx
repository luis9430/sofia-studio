import { useRef, useState } from "preact/hooks";
import { CampoConToken } from "./CampoConToken.jsx";

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
 * botón ✏️ de BotonEditarCampo) y "bloque" (Nivel 2 — todo lo de
 * CAJA/contenedor: columnas de grid, fondo, borde, radius, sombra,
 * espaciado vertical, ancho/ancho máximo/aspect-ratio/object-fit/z-index,
 * de la <section> completa, abierto desde "Estilo del bloque" en el menú
 * contextual, ver App.jsx). Regla de qué va en cada nivel confirmada con el
 * usuario tras auditar el catálogo completo de Core Framework:
 * "color_fondo"/"margen"/"relleno" vivían mal ubicados en Nivel 1 (son
 * propiedades de la CAJA del campo, no de su texto) y se
 * movieron a Nivel 2. Mismas pestañas/cabecera/arrastre para los 2,
 * solo cambia qué controles se listan en el cuerpo — evita duplicar toda
 * la mecánica de drawer (arrastre, overlay, pestañas) en un componente
 * aparte para un caso que en el fondo es "el mismo panel con otro grupo de
 * controles".
 *
 * Nivel 2 mezcla 2 mecanismos DISTINTOS de Core Framework, nunca
 * confundirlos: CampoConToken (fondo/borde/radius/sombra) edita un VALOR
 * de estilo que puede ser fijo o un token "cf:{nombre}" resuelto a
 * var(--nombre); Ancho máximo/Ancho/Aspect ratio/Object-fit/Z-index son
 * <select> de una lista fija porque cada opción ES una utility class ya
 * completa (ej. "aspect-16-9") que Sofia_Componente::clases_utilitarias_
 * bloque() agrega al classList de la <section> — no hay ningún valor que
 * tokenizar ahí, por eso nunca llevan el botón CF.
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

// parsearMedida/formatearMedida: separan un shorthand CSS de 1-4 valores
// ("12px 8px" → {valores: ["12","8"], unidad: "px"}) para poder editarlos
// como inputs numéricos independientes por lado (arriba/derecha/abajo/
// izquierda) sin que el usuario escriba CSS a mano — mismo criterio de
// "nunca CSS arbitrario" que el resto del drawer, con más control que un
// select de 3 opciones (pedido explícito: "like Elementor", los 4 lados).
function parsearMedida(shorthand) {
  if (!shorthand) return { top: "", right: "", bottom: "", left: "", unidad: "px" };
  const partes = shorthand.trim().split(/\s+/);
  const unidad = (partes[0].match(/[a-z%]+$/) || ["px"])[0];
  const numeros = partes.map((p) => p.replace(/[a-z%]+$/, ""));
  // Shorthand CSS: 1 valor = los 4 lados, 2 = vertical/horizontal, 4 = cada lado.
  if (numeros.length === 1) return { top: numeros[0], right: numeros[0], bottom: numeros[0], left: numeros[0], unidad };
  if (numeros.length === 2) return { top: numeros[0], right: numeros[1], bottom: numeros[0], left: numeros[1], unidad };
  return { top: numeros[0] || "", right: numeros[1] || "", bottom: numeros[2] || "", left: numeros[3] || "", unidad };
}

function formatearMedida({ top, right, bottom, left, unidad }) {
  if (!top && !right && !bottom && !left) return "";
  const n = (v) => `${v || 0}${unidad}`;
  return `${n(top)} ${n(right)} ${n(bottom)} ${n(left)}`;
}

// COLUMNAS: mismas 3 opciones que Sofia_Componente::COLUMNAS_PERMITIDAS del
// lado PHP — un grid solo tiene sentido en un rango chico (2 a 4).
const COLUMNAS = ["2", "3", "4"];

// UTILITY CLASSES DE CORE FRAMEWORK (Nivel 2) — a diferencia de
// CampoConToken (un VALOR de estilo, con opción de token "cf:..."), estas
// opciones son <select> de una lista fija: cada una ES una utility class ya
// completa de Core Framework (ver Sofia_Componente::CLASES_UTILITARIAS_BLOQUE,
// mismas claves/valores espejados acá) — no hay "valor libre" posible, ni
// tiene sentido un botón CF (no hay nada que tokenizar, ya es una clase).
const ANCHOS_MAXIMOS = [
  { valor: "", etiqueta: "Sin límite" },
  { valor: "site", etiqueta: "Ancho del sitio" },
  { valor: "100", etiqueta: "100rem" },
  { valor: "90", etiqueta: "90rem" },
  { valor: "80", etiqueta: "80rem" },
  { valor: "70", etiqueta: "70rem" },
  { valor: "60", etiqueta: "60rem" },
  { valor: "50", etiqueta: "50rem" },
  { valor: "40", etiqueta: "40rem" },
  { valor: "30", etiqueta: "30rem" },
  { valor: "20", etiqueta: "20rem" },
  { valor: "10", etiqueta: "10rem" },
];

const ANCHOS = [
  { valor: "", etiqueta: "Por defecto" },
  { valor: "full", etiqueta: "100%" },
  { valor: "90", etiqueta: "90%" },
  { valor: "80", etiqueta: "80%" },
  { valor: "70", etiqueta: "70%" },
  { valor: "60", etiqueta: "60%" },
  { valor: "50", etiqueta: "50%" },
  { valor: "40", etiqueta: "40%" },
  { valor: "30", etiqueta: "30%" },
  { valor: "20", etiqueta: "20%" },
  { valor: "10", etiqueta: "10%" },
  { valor: "auto", etiqueta: "Automático" },
];

const ASPECT_RATIOS = [
  { valor: "", etiqueta: "Ninguna" },
  { valor: "1", etiqueta: "1:1 (cuadrado)" },
  { valor: "16-9", etiqueta: "16:9" },
  { valor: "9-16", etiqueta: "9:16" },
  { valor: "4-3", etiqueta: "4:3" },
  { valor: "3-4", etiqueta: "3:4" },
  { valor: "3-2", etiqueta: "3:2" },
  { valor: "2-3", etiqueta: "2:3" },
];

const OBJECT_FITS = [
  { valor: "", etiqueta: "Por defecto" },
  { valor: "cover", etiqueta: "Cubrir (recorta)" },
  { valor: "contain", etiqueta: "Contener (sin recortar)" },
  { valor: "fill", etiqueta: "Estirar" },
];

const Z_INDICES = [
  { valor: "", etiqueta: "Por defecto" },
  { valor: "-1", etiqueta: "-1 (detrás)" },
  { valor: "0", etiqueta: "0" },
  { valor: "1", etiqueta: "1" },
  { valor: "10", etiqueta: "10" },
  { valor: "100", etiqueta: "100" },
  { valor: "1000", etiqueta: "1000" },
  { valor: "10000", etiqueta: "10000 (siempre encima)" },
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
  estilo,
  nivel = "campo",
  condicion,
  onCambiarEstilo,
  onCambiarCondicion,
  onAplicarFormato,
  onCerrar,
  tabInicial = "estilo",
}) {
  const [tab, setTab] = useState(tabInicial);
  const [offsetArrastre, setOffsetArrastre] = useState({ x: 0, y: 0 });
  const [arrastrando, setArrastrando] = useState(false);
  const arrastreRef = useRef(null); // { inicioX, inicioY, offsetInicial } mientras el mouse está presionado.

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
                  <div className="sofia-drawer-estilo__tamano-numerico">
                    <input
                      type="number"
                      min="0"
                      placeholder="Por defecto"
                      value={parsearMedida(estilo.tamano_fuente).top}
                      onInput={(evento) => {
                        const numero = evento.currentTarget.value;
                        const unidad = parsearMedida(estilo.tamano_fuente).unidad;
                        actualizar({ tamano_fuente: numero ? `${numero}${unidad}` : "" });
                      }}
                    />
                    <select
                      value={parsearMedida(estilo.tamano_fuente).unidad}
                      onChange={(evento) => {
                        const numero = parsearMedida(estilo.tamano_fuente).top;
                        actualizar({ tamano_fuente: numero ? `${numero}${evento.currentTarget.value}` : "" });
                      }}
                    >
                      <option value="px">px</option>
                      <option value="rem">rem</option>
                      <option value="%">%</option>
                    </select>
                  </div>
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

            {nivel === "bloque" && (
              <>
                <div className="sofia-drawer-estilo__grupo">
                  <span className="sofia-drawer-estilo__etiqueta">Columnas</span>
                  <div className="sofia-drawer-estilo__tamanos">
                    {COLUMNAS.map((n) => (
                      <button
                        key={n}
                        type="button"
                        className={`sofia-drawer-estilo__tamano ${estilo.columnas === n ? "sofia-drawer-estilo__tamano--activo" : ""}`}
                        onClick={() => actualizar({ columnas: estilo.columnas === n ? "" : n })}
                      >
                        {n}
                      </button>
                    ))}
                  </div>
                </div>

                <div className="sofia-drawer-estilo__grupo">
                  <span className="sofia-drawer-estilo__etiqueta">Color de fondo de la sección</span>
                  <CampoConToken
                    tipo="color"
                    valor={estilo.color_fondo}
                    onCambiar={(valor) => actualizar({ color_fondo: valor })}
                  />
                </div>

                <div className="sofia-drawer-estilo__grupo">
                  <span className="sofia-drawer-estilo__etiqueta">Color de borde</span>
                  <CampoConToken
                    tipo="color"
                    valor={estilo.color_borde}
                    onCambiar={(valor) => actualizar({ color_borde: valor })}
                  />
                </div>

                <div className="sofia-drawer-estilo__grupo">
                  <span className="sofia-drawer-estilo__etiqueta">Radio de borde</span>
                  <CampoConToken
                    tipo="text"
                    categoria="radius"
                    conUnidad
                    valor={estilo.radius}
                    onCambiar={(valor) => actualizar({ radius: valor })}
                  />
                </div>

                <div className="sofia-drawer-estilo__grupo">
                  <span className="sofia-drawer-estilo__etiqueta">Sombra</span>
                  <CampoConToken
                    tipo="text"
                    categoria="shadow"
                    placeholderNormal="ej. 0 2px 6px rgba(0,0,0,.15)"
                    valor={estilo.sombra}
                    onCambiar={(valor) => actualizar({ sombra: valor })}
                  />
                </div>

                <div className="sofia-drawer-estilo__grupo">
                  <span className="sofia-drawer-estilo__etiqueta">Espaciado vertical (arriba y abajo)</span>
                  <div className="sofia-drawer-estilo__tamano-numerico">
                    <input
                      type="number"
                      min="0"
                      placeholder="Por defecto"
                      value={parsearMedida(estilo.espaciado_vertical).top}
                      onInput={(evento) => {
                        const numero = evento.currentTarget.value;
                        const unidad = parsearMedida(estilo.espaciado_vertical).unidad;
                        actualizar({ espaciado_vertical: numero ? `${numero}${unidad}` : "" });
                      }}
                    />
                    <select
                      value={parsearMedida(estilo.espaciado_vertical).unidad}
                      onChange={(evento) => {
                        const numero = parsearMedida(estilo.espaciado_vertical).top;
                        actualizar({ espaciado_vertical: numero ? `${numero}${evento.currentTarget.value}` : "" });
                      }}
                    >
                      <option value="px">px</option>
                      <option value="rem">rem</option>
                      <option value="%">%</option>
                    </select>
                  </div>
                </div>

                <div className="sofia-drawer-estilo__grupo">
                  <span className="sofia-drawer-estilo__etiqueta">Ancho máximo</span>
                  <select
                    value={estilo.max_width || ""}
                    onChange={(evento) => actualizar({ max_width: evento.currentTarget.value })}
                  >
                    {ANCHOS_MAXIMOS.map((op) => (
                      <option key={op.valor || "ninguno"} value={op.valor}>
                        {op.etiqueta}
                      </option>
                    ))}
                  </select>
                </div>

                <div className="sofia-drawer-estilo__grupo">
                  <span className="sofia-drawer-estilo__etiqueta">Ancho</span>
                  <select value={estilo.ancho || ""} onChange={(evento) => actualizar({ ancho: evento.currentTarget.value })}>
                    {ANCHOS.map((op) => (
                      <option key={op.valor || "ninguno"} value={op.valor}>
                        {op.etiqueta}
                      </option>
                    ))}
                  </select>
                </div>

                <div className="sofia-drawer-estilo__grupo">
                  <span className="sofia-drawer-estilo__etiqueta">Relación de aspecto</span>
                  <select
                    value={estilo.aspect_ratio || ""}
                    onChange={(evento) => actualizar({ aspect_ratio: evento.currentTarget.value })}
                  >
                    {ASPECT_RATIOS.map((op) => (
                      <option key={op.valor || "ninguno"} value={op.valor}>
                        {op.etiqueta}
                      </option>
                    ))}
                  </select>
                </div>

                <div className="sofia-drawer-estilo__grupo">
                  <span className="sofia-drawer-estilo__etiqueta">Ajuste de imagen/video (object-fit)</span>
                  <p className="sofia-drawer-estilo__ayuda-condicion">
                    Solo tiene efecto si este bloque es o contiene una &lt;img&gt;/&lt;video&gt; directa — no aplica a
                    un color/imagen de fondo (background-image usa otra propiedad, no object-fit).
                  </p>
                  <select
                    value={estilo.object_fit || ""}
                    onChange={(evento) => actualizar({ object_fit: evento.currentTarget.value })}
                  >
                    {OBJECT_FITS.map((op) => (
                      <option key={op.valor || "ninguno"} value={op.valor}>
                        {op.etiqueta}
                      </option>
                    ))}
                  </select>
                </div>

                <div className="sofia-drawer-estilo__grupo">
                  <span className="sofia-drawer-estilo__etiqueta">Z-index (superposición)</span>
                  <select value={estilo.z_index || ""} onChange={(evento) => actualizar({ z_index: evento.currentTarget.value })}>
                    {Z_INDICES.map((op) => (
                      <option key={op.valor || "ninguno"} value={op.valor}>
                        {op.etiqueta}
                      </option>
                    ))}
                  </select>
                </div>
              </>
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

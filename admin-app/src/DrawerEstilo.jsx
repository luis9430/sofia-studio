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
 * Un mismo componente sirve para 2 niveles de estilo, según la prop
 * `nivel`: "campo" (default, Nivel 1 — alineación/tipografía/color/
 * espaciado de UN elemento, abierto con el botón ✏️ de BotonEditarCampo)
 * y "bloque" (Nivel 2 — columnas de grid/color de fondo/espaciado vertical
 * de la <section> completa, abierto desde "Estilo del bloque" en el menú
 * contextual, ver App.jsx). Mismas pestañas/cabecera/arrastre para los 2,
 * solo cambia qué controles se listan en el cuerpo — evita duplicar toda
 * la mecánica de drawer (arrastre, overlay, pestañas) en un componente
 * aparte para un caso que en el fondo es "el mismo panel con otro grupo de
 * controles".
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

// EspaciadoLados: 4 inputs numéricos (arriba/derecha/abajo/izquierda) +
// selector de unidad, compartido entre margen y relleno.
function EspaciadoLados({ etiqueta, valor, onCambiar }) {
  const medida = parsearMedida(valor);

  function actualizarLado(lado, nuevoValor) {
    onCambiar(formatearMedida({ ...medida, [lado]: nuevoValor }));
  }

  function actualizarUnidad(nuevaUnidad) {
    onCambiar(formatearMedida({ ...medida, unidad: nuevaUnidad }));
  }

  return (
    <div className="sofia-drawer-estilo__grupo">
      <span className="sofia-drawer-estilo__etiqueta">{etiqueta}</span>
      <div className="sofia-drawer-estilo__espaciado">
        <div className="sofia-drawer-estilo__espaciado-grid">
          <input
            type="number"
            placeholder="Arriba"
            value={medida.top}
            onInput={(evento) => actualizarLado("top", evento.currentTarget.value)}
          />
          <input
            type="number"
            placeholder="Derecha"
            value={medida.right}
            onInput={(evento) => actualizarLado("right", evento.currentTarget.value)}
          />
          <input
            type="number"
            placeholder="Abajo"
            value={medida.bottom}
            onInput={(evento) => actualizarLado("bottom", evento.currentTarget.value)}
          />
          <input
            type="number"
            placeholder="Izquierda"
            value={medida.left}
            onInput={(evento) => actualizarLado("left", evento.currentTarget.value)}
          />
        </div>
        <select value={medida.unidad} onChange={(evento) => actualizarUnidad(evento.currentTarget.value)}>
          <option value="px">px</option>
          <option value="rem">rem</option>
          <option value="%">%</option>
        </select>
      </div>
    </div>
  );
}

// COLUMNAS: mismas 3 opciones que Sofia_Componente::COLUMNAS_PERMITIDAS del
// lado PHP — un grid solo tiene sentido en un rango chico (2 a 4).
const COLUMNAS = ["2", "3", "4"];

export function DrawerEstilo({ campo, estilo, nivel = "campo", onCambiarEstilo, onAplicarFormato, onCerrar }) {
  const [tab, setTab] = useState("estilo");
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

                <div className="sofia-drawer-estilo__grupo">
                  <span className="sofia-drawer-estilo__etiqueta">Color de fondo</span>
                  <div className="sofia-drawer-estilo__swatches">
                    {PALETA_COLORES.map((op) => (
                      <button
                        key={op.valor || "default"}
                        type="button"
                        className={`sofia-drawer-estilo__swatch ${estilo.color_fondo === op.valor ? "sofia-drawer-estilo__swatch--activo" : ""} ${op.valor === "" ? "sofia-drawer-estilo__swatch--vacio" : ""}`}
                        style={op.valor ? { backgroundColor: op.valor } : undefined}
                        title={op.etiqueta}
                        onClick={() => actualizar({ color_fondo: op.valor })}
                      />
                    ))}
                  </div>
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

                <EspaciadoLados
                  etiqueta="Margen (arriba / derecha / abajo / izquierda)"
                  valor={estilo.margen}
                  onCambiar={(valor) => actualizar({ margen: valor })}
                />

                <EspaciadoLados
                  etiqueta="Relleno (arriba / derecha / abajo / izquierda)"
                  valor={estilo.relleno}
                  onCambiar={(valor) => actualizar({ relleno: valor })}
                />
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
                  <div className="sofia-drawer-estilo__swatches">
                    {PALETA_COLORES.map((op) => (
                      <button
                        key={op.valor || "default"}
                        type="button"
                        className={`sofia-drawer-estilo__swatch ${estilo.color_fondo === op.valor ? "sofia-drawer-estilo__swatch--activo" : ""} ${op.valor === "" ? "sofia-drawer-estilo__swatch--vacio" : ""}`}
                        style={op.valor ? { backgroundColor: op.valor } : undefined}
                        title={op.etiqueta}
                        onClick={() => actualizar({ color_fondo: op.valor })}
                      />
                    ))}
                  </div>
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
              </>
            )}
          </div>
        )}
      </div>
    </>
  );
}

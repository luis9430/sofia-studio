import { useState } from "preact/hooks";

/**
 * CatalogoAgregar — pestaña "Agregar" del panel de Estructura (paso 4 del
 * rediseño de layout a 3 zonas fijas, ver la memoria de producto):
 * reemplaza el botón "+ Agregar bloque" de la barra superior y el menú
 * desplegable que abría (MenuAgregarBloque.jsx, que sigue existiendo tal
 * cual para las líneas "+" del canvas — esas sí necesitan un menú flotante
 * puntual, insertar EN una posición del medio de la página, algo que esta
 * pestaña no resuelve). Mismo catálogo real (Sofia_Componente_Factory,
 * ver catalogoBloques en App.jsx), sin lista curada aparte.
 *
 * Solo click, agrega siempre al FINAL de la página (decisión explícita:
 * arrastrar un item del catálogo hasta una posición puntual del canvas
 * cruzaría el límite del iframe — dos documentos DOM distintos — algo que
 * ninguna otra pieza del editor hace hoy; para una posición puntual, las
 * líneas "+" entre bloques siguen siendo el camino, sin cambios).
 */
// Ícono por tipo de bloque. Un glifo, no un SVG: el árbol necesita una
// pista de forma rápida de leer, no un dibujo detallado — y así no hay
// que cargar el set de íconos en el panel.
//
// Los tipos no listados caen a "▪": es deliberado que un Componente
// nuevo no obligue a tocar este mapa para funcionar.
const ICONOS_TIPO = {
  hero: "▤",
  cta: "◈",
  franja_beneficios: "▦",
  testimonios: "❞",
  faq: "?",
  texto_libre: "¶",
  rich_text: "¶",
  heading: "H",
  container: "▣",
  surface: "▣",
  image: "▢",
  video: "▶",
  embed: "▶",
  button: "▭",
  icon_button: "▭",
  link: "↗",
  icon: "★",
  avatar: "◍",
  badge: "◉",
  card: "▤",
  callout: "!",
  list: "☰",
  nav: "☰",
  breadcrumb: "›",
  stat: "#",
  progress: "▬",
  rating: "★",
  divider: "—",
  spacer: "⇕",
  aspect_ratio: "▢",
};

function CatalogoAgregar({ catalogo, onAgregar }) {
  if (!catalogo.length) {
    return <p className="sofia-zona-estructura__vacio">Cargando catálogo…</p>;
  }
  return (
    <div className="sofia-catalogo-agregar">
      {catalogo.map((bloque) => (
        <button
          key={bloque.tipo}
          type="button"
          className="sofia-catalogo-agregar__item"
          onClick={() => onAgregar(bloque.tipo)}
          title={`Agregar "${bloque.nombre}" al final de la página`}
        >
          <span className="sofia-catalogo-agregar__nombre">{bloque.nombre}</span>
          <span className="sofia-catalogo-agregar__mas">+</span>
        </button>
      ))}
    </div>
  );
}

/**
 * PanelEstructura — paso 2 del rediseño de layout a 3 zonas fijas (ver la
 * memoria de producto): columna SIEMPRE montada a la IZQUIERDA del canvas,
 * hermana de .sofia-zona-propiedades (que quedó a la derecha desde el paso
 * 1). Gana pestañas en el paso 4: "Árbol" (todo lo que ya hacía, sin
 * cambios) y "Agregar" (catálogo de Componentes, ver CatalogoAgregar
 * arriba) — un solo panel angosto con varios modos, en vez de sumar una
 * 4ta columna que angostara más el canvas (decisión confirmada con el
 * usuario tras ver un mockup comparando ambas opciones).
 *
 * El árbol de bloques resuelve 2 problemas reales a la vez:
 * 1. "Seleccionar el padre" ya no depende de "Seleccionar contenedor" del
 *    menú contextual (click derecho sobre un hijo, reabrir el menú apuntando
 *    al padre) — un click directo en el nodo del Container en este árbol
 *    hace lo mismo en un solo paso.
 * 2. Reordenar bloques deja de depender de arrastrar ENCIMA del canvas (que
 *    tapaba contenido real mientras se arrastraba) — se arrastra acá, en una
 *    lista angosta y previsible, y el iframe se sincroniza por mensaje (ver
 *    alMoverBloque en editor-iframe.js) en vez de recibir el drag directo.
 *
 * Restricción de alcance (decisión de diseño explícita, Fase 3 de
 * "primitivas de layout"): el drag acá SOLO reordena dentro del mismo padre
 * — nunca mueve un nodo de un Container a otro, ni entre nivel superior y
 * un Container. Ampliar a eso es una fase aparte (requiere reconstruir el
 * HTML del bloque movido para su nuevo padre, no solo reordenar el
 * existente).
 *
 * Los nodos de un bloque con lista repetible (Franja de beneficios/
 * Testimonios/FAQ) traen ADEMÁS un item por hijo, marcados `esItem:true`
 * (ver conNombres en App.jsx) — mismos gestos de selección/arrastre que
 * un bloque real, pero un item nunca tiene hijos propios ni abre el
 * drawer de Estilo (no tiene Nivel 2 propio, solo el texto que ya se edita
 * directo en el canvas) — onSeleccionar/onMover en App.jsx despachan
 * según esta marca.
 *
 * Drag & drop nativo HTML5 (draggable=true + eventos dragstart/dragover/
 * drop) en vez de sumar una librería nueva — la lista es plana
 * visualmente (una fila por nodo), así que el mecanismo nativo del
 * navegador alcanza sin dependencias extra.
 */
export function PanelEstructura({ estructura, seleccionado, onSeleccionar, onMover, catalogo, onAgregar }) {
  const [tab, setTab] = useState("arbol");

  // arrastrando: { nodo, padreId } del nodo que se está arrastrando —
  // padreId es null a nivel superior. Se usa para bloquear el drop sobre
  // un nodo de OTRO padre (ver alSoltar) sin tener que recalcular el
  // árbol en cada dragover, y para el estilo visual de "fila siendo
  // arrastrada". Guarda el NODO completo (no solo su id) porque onMover
  // necesita saber si es un item de lista (nodo.esItem) para elegir qué
  // mensaje mandarle al iframe.
  const [arrastrando, setArrastrando] = useState(null);
  const [sobreId, setSobreId] = useState(null);

  function alEmpezarArrastre(evento, nodo, padreId) {
    setArrastrando({ nodo, padreId });
    evento.dataTransfer.effectAllowed = "move";
    // Firefox requiere setData con ALGO para permitir el drag — el valor en
    // sí no se usa, todo el estado real vive en el `arrastrando` de React.
    evento.dataTransfer.setData("text/plain", nodo.id);
  }

  function alPasarPorEncima(evento, nodo, padreId) {
    if (!arrastrando || arrastrando.padreId !== padreId || arrastrando.nodo.id === nodo.id) return;
    evento.preventDefault();
    setSobreId(nodo.id);
  }

  function alSoltar(evento, nodo, padreId, indice) {
    evento.preventDefault();
    setSobreId(null);
    if (!arrastrando || arrastrando.padreId !== padreId || arrastrando.nodo.id === nodo.id) {
      setArrastrando(null);
      return;
    }
    onMover(arrastrando.nodo.id, indice, arrastrando.nodo);
    setArrastrando(null);
  }

  function renderNodo(nodo, padreId, indice) {
    const tieneHijos = nodo.hijos && nodo.hijos.length > 0;
    const estaSeleccionado = seleccionado === nodo.id;
    const estaArrastrando = arrastrando?.nodo.id === nodo.id;
    const estaSobre = sobreId === nodo.id;

    return (
      <div key={nodo.id} className="sofia-estructura-nodo-grupo">
        <div
          className={[
            "sofia-estructura-nodo",
            nodo.esItem && "sofia-estructura-nodo--item",
            estaSeleccionado && "sofia-estructura-nodo--activo",
            estaArrastrando && "sofia-estructura-nodo--arrastrando",
            estaSobre && "sofia-estructura-nodo--sobre",
          ]
            .filter(Boolean)
            .join(" ")}
          draggable
          onDragStart={(evento) => alEmpezarArrastre(evento, nodo, padreId)}
          onDragOver={(evento) => alPasarPorEncima(evento, nodo, padreId)}
          onDragLeave={() => setSobreId((actual) => (actual === nodo.id ? null : actual))}
          onDrop={(evento) => alSoltar(evento, nodo, padreId, indice)}
          onDragEnd={() => {
            setArrastrando(null);
            setSobreId(null);
          }}
          onClick={() => onSeleccionar(nodo)}
        >
          <span className="sofia-estructura-nodo__handle" title="Arrastrar para reordenar">
            ⠿
          </span>
          {!nodo.esItem && (
            <span className="sofia-estructura-nodo__icono" aria-hidden="true">
              {ICONOS_TIPO[nodo.tipo] || "▪"}
            </span>
          )}
          <span className="sofia-estructura-nodo__etiqueta">{nodo.nombre || nodo.tipo}</span>
          {nodo.pendiente && (
            <span className="sofia-estructura-nodo__pendiente" title={nodo.pendiente} aria-label={nodo.pendiente}>
              ●
            </span>
          )}
          {tieneHijos && !nodo.esItem && (
            <span className="sofia-estructura-nodo__cuenta">{nodo.hijos.length}</span>
          )}
        </div>
        {tieneHijos && (
          // Un solo wrapper por nivel: da la sangría Y la línea de
          // jerarquía. Antes la sangría se calculaba inline por nodo,
          // lo que dejaba la línea sin nada con qué alinearse.
          <div className="sofia-estructura-nodo__hijos">
            {nodo.hijos.map((hijo, i) => renderNodo(hijo, nodo.id, i))}
          </div>
        )}
      </div>
    );
  }

  return (
    <div className="sofia-zona-estructura">
      <div className="sofia-zona-estructura__tabs">
        <button
          type="button"
          className={`sofia-zona-estructura__tab ${tab === "arbol" ? "sofia-zona-estructura__tab--activo" : ""}`}
          onClick={() => setTab("arbol")}
        >
          Árbol
        </button>
        <button
          type="button"
          className={`sofia-zona-estructura__tab ${tab === "agregar" ? "sofia-zona-estructura__tab--activo" : ""}`}
          onClick={() => setTab("agregar")}
        >
          Agregar
        </button>
      </div>
      <div className="sofia-zona-estructura__lista">
        {tab === "arbol" &&
          (estructura && estructura.length ? (
            estructura.map((nodo, indice) => renderNodo(nodo, null, indice))
          ) : (
            <p className="sofia-zona-estructura__vacio">Sin bloques todavía</p>
          ))}
        {tab === "agregar" && <CatalogoAgregar catalogo={catalogo} onAgregar={onAgregar} />}
      </div>
    </div>
  );
}

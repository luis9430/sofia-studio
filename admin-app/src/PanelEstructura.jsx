import { useState } from "preact/hooks";

/**
 * PanelEstructura — paso 2 del rediseño de layout a 3 zonas fijas (ver la
 * memoria de producto): columna SIEMPRE montada a la IZQUIERDA del canvas,
 * hermana de .sofia-zona-propiedades (que quedó a la derecha desde el paso
 * 1) — árbol de TODOS los bloques de la página, en cualquier profundidad de
 * anidamiento (un bloque dentro de un Container aparece indentado bajo él).
 *
 * Resuelve 2 problemas reales a la vez:
 * 1. "Seleccionar el padre" ya no depende de "Seleccionar contenedor" del
 *    menú contextual (click derecho sobre un hijo, reabrir el menú apuntando
 *    al padre) — un click directo en el nodo del Container en este árbol
 *    hace lo mismo en un solo paso.
 * 2. Reordenar bloques deja de depender de arrastrar ENCIMA del canvas (que
 *    tapaba contenido real mientras se arrastraba) — se arrastra acá, en una
 *    lista angosta y previsible, y el iframe se sincroniza por mensaje (ver
 *    alMoverBloque en editor-iframe.js) en vez de recibir el drag directo.
 *
 * Mismo alcance que tenía el drag de Muuri en el canvas cuando existía
 * (decisión de diseño explícita, Fase 3 de "primitivas de layout" —
 * vigente aunque Muuri ya no exista, ver el paso 3 del rediseño en la
 * memoria de producto): el drag acá SOLO reordena dentro del mismo padre
 * — nunca mueve un nodo de un Container a otro, ni entre nivel superior y
 * un Container. Ampliar a eso es una fase aparte (requiere reconstruir el
 * HTML del bloque movido para su nuevo padre, no solo reordenar el
 * existente).
 *
 * Paso 3 (Muuri eliminado del canvas, ver la memoria de producto): los
 * nodos de un bloque con lista repetible (Franja de beneficios/
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
export function PanelEstructura({ estructura, seleccionado, onSeleccionar, onMover }) {
  // arrastrando: { nodo, padreId } del nodo que se está arrastrando —
  // padreId es null a nivel superior. Se usa para bloquear el drop sobre
  // un nodo de OTRO padre (ver alSoltar) sin tener que recalcular el
  // árbol en cada dragover, y para el estilo visual de "fila siendo
  // arrastrada". Guarda el NODO completo (no solo su id) porque onMover
  // necesita saber si es un item de lista (nodo.esItem) para elegir qué
  // mensaje mandarle al iframe.
  const [arrastrando, setArrastrando] = useState(null);
  const [sobreId, setSobreId] = useState(null);

  if (!estructura || !estructura.length) {
    return (
      <div className="sofia-zona-estructura">
        <div className="sofia-zona-estructura__cabecera">Estructura</div>
        <p className="sofia-zona-estructura__vacio">Sin bloques todavía</p>
      </div>
    );
  }

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

  function renderNodo(nodo, padreId, indice, profundidad) {
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
          style={{ paddingLeft: `${12 + profundidad * 16}px` }}
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
          <span className="sofia-estructura-nodo__etiqueta">{nodo.nombre || nodo.tipo}</span>
        </div>
        {tieneHijos && (
          <div className="sofia-estructura-nodo__hijos">
            {nodo.hijos.map((hijo, i) => renderNodo(hijo, nodo.id, i, profundidad + 1))}
          </div>
        )}
      </div>
    );
  }

  return (
    <div className="sofia-zona-estructura">
      <div className="sofia-zona-estructura__cabecera">Estructura</div>
      <div className="sofia-zona-estructura__lista">
        {estructura.map((nodo, indice) => renderNodo(nodo, null, indice, 0))}
      </div>
    </div>
  );
}

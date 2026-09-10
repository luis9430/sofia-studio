/**
 * Menú desplegable de "agregar bloque" — se abre desde el botón de la
 * barra de estado flotante (App.jsx). Lista el catálogo REAL de
 * Sofia_Componente_* del tema (mismo endpoint sofia/v1/catalogo-bloques
 * que ya usa editor-iframe.js para los nombres del overlay de resaltado),
 * nunca una lista curada aparte.
 *
 * Elegir un tipo no toca el iframe directo — App.jsx guarda la estructura
 * ampliada contra el proxy REST y recarga el iframe, para que WordPress
 * renderice el bloque nuevo con el PHP real de su Componente (ver el
 * comentario de agregarBloque() en App.jsx).
 */
// posicion (opcional): {x, y} — cuando el menú se abre desde una línea de
// inserción DENTRO del iframe (ver activarLineasInsertar() en
// editor-iframe.js, click en el "+" entre dos bloques), se dibuja anclado
// a esas coordenadas en vez del dropdown fijo bajo el botón de la barra
// superior — mismo patrón que MenuContextualBloque.
export function MenuAgregarBloque({ catalogo, onElegir, onCerrar, posicion, titulo }) {
  const estiloPosicion = posicion ? { top: `${posicion.y}px`, left: `${posicion.x}px`, right: "auto" } : undefined;

  return (
    <div className="sofia-menu-agregar__fondo" onClick={onCerrar}>
      <div className="sofia-menu-agregar" style={estiloPosicion} onClick={(evento) => evento.stopPropagation()}>
        <div className="sofia-menu-agregar__titulo">{titulo || "Agregar bloque al final de la página"}</div>
        {catalogo.length === 0 && <div className="sofia-menu-agregar__vacio">Cargando catálogo…</div>}
        {catalogo.map((bloque) => (
          <button
            key={bloque.tipo}
            type="button"
            className="sofia-menu-agregar__opcion"
            onClick={() => onElegir(bloque.tipo)}
          >
            {bloque.nombre}
          </button>
        ))}
      </div>
    </div>
  );
}

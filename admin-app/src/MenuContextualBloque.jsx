/**
 * Menú contextual — se abre con click derecho DENTRO del iframe (ver
 * alHacerClickDerecho en editor-iframe.js, que manda
 * "sofia:menu-contextual-bloque" con índice+coordenadas, y opcionalmente
 * `item` si el click fue sobre un item de una lista repetible), se dibuja
 * acá en el documento padre por el mismo motivo de siempre: los controles
 * de edición viven fuera del iframe, superpuestos por posición.
 *
 * Dos acciones posibles, mutuamente excluyentes según si `posicion.item`
 * viene informado: "Eliminar item" (un "Beneficio" individual dentro de
 * una Franja, el bloque sigue existiendo) o "Eliminar bloque" (la sección
 * completa). El diseño ya contempla sumar más comandos (clonar, mover)
 * más adelante, mismo patrón: un botón nuevo acá + un caso nuevo en
 * alRecibirMensajeDelPadre del iframe.
 */
export function MenuContextualBloque({ posicion, onEliminarBloque, onEliminarItem, onEstiloBloque, onCerrar }) {
  if (!posicion) return null;

  return (
    <div className="sofia-menu-contextual__fondo" onClick={onCerrar} onContextMenu={(e) => e.preventDefault()}>
      <div
        className="sofia-menu-contextual"
        style={{ top: `${posicion.y}px`, left: `${posicion.x}px` }}
        onClick={(evento) => evento.stopPropagation()}
      >
        <button type="button" className="sofia-menu-contextual__opcion" onClick={onEstiloBloque}>
          Estilo del bloque
        </button>
        {posicion.item && (
          <button
            type="button"
            className="sofia-menu-contextual__opcion sofia-menu-contextual__opcion--eliminar"
            onClick={onEliminarItem}
          >
            Eliminar item
          </button>
        )}
        <button
          type="button"
          className="sofia-menu-contextual__opcion sofia-menu-contextual__opcion--eliminar"
          onClick={onEliminarBloque}
        >
          Eliminar bloque
        </button>
      </div>
    </div>
  );
}

/**
 * Menú contextual — se abre con click derecho DENTRO del iframe (ver
 * alHacerClickDerecho en editor-iframe.js, que manda
 * "sofia:menu-contextual-bloque" con índice+coordenadas, y opcionalmente
 * `item` si el click fue sobre un item de una lista repetible), se dibuja
 * acá en el documento padre por el mismo motivo de siempre: los controles
 * de edición viven fuera del iframe, superpuestos por posición.
 *
 * Acciones posibles: "Eliminar item" (un "Beneficio" individual dentro de
 * una Franja, el bloque sigue existiendo, mutuamente excluyente con
 * "Eliminar bloque" según si `posicion.item` viene informado), "Eliminar
 * bloque" (la sección completa), y "Seleccionar contenedor" (Fase 3,
 * "primitivas de layout" — SOLO si `posicion.containerId` viene informado,
 * es decir, el bloque clickeado vive DENTRO de un Container). Bug de UX
 * real reportado por el usuario: sin esta opción, no había forma de
 * apuntar al Container PADRE cuando tenía hijos adentro — cualquier click
 * en su área visible resolvía primero al hijo bajo el cursor, así que
 * "Eliminar bloque" sobre el Container completo (borrando también su
 * contenido) era inalcanzable desde la UI. El diseño ya contempla sumar
 * más comandos (clonar, mover) más adelante, mismo patrón: un botón nuevo
 * acá + un caso nuevo en alRecibirMensajeDelPadre del iframe.
 */
export function MenuContextualBloque({
  posicion,
  onEliminarBloque,
  onEliminarItem,
  onEstiloBloque,
  onSeleccionarContenedorPadre,
  onCerrar,
}) {
  if (!posicion) return null;

  return (
    <div className="sofia-menu-contextual__fondo" onClick={onCerrar} onContextMenu={(e) => e.preventDefault()}>
      <div
        className="sofia-menu-contextual"
        style={{ top: `${posicion.y}px`, left: `${posicion.x}px` }}
        onClick={(evento) => evento.stopPropagation()}
      >
        {/* Un solo punto de entrada al drawer de bloque — Visibilidad se
            alcanza cambiando de pestaña ADENTRO del drawer (ver
            DrawerEstilo.jsx), no necesita su propia opción acá. Menos
            redundante que 2 botones que abren el mismo componente. */}
        <button type="button" className="sofia-menu-contextual__opcion" onClick={onEstiloBloque}>
          Estilo del bloque
        </button>
        {posicion.containerId && (
          <button type="button" className="sofia-menu-contextual__opcion" onClick={onSeleccionarContenedorPadre}>
            Seleccionar contenedor
          </button>
        )}
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

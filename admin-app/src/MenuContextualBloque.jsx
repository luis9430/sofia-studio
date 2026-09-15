/**
 * Menú contextual — se abre con click derecho DENTRO del iframe (ver
 * alHacerClickDerecho en editor-iframe.js, que manda
 * "sofia:menu-contextual-bloque" con índice+coordenadas, y opcionalmente
 * `item` si el click fue sobre un item de una lista repetible), se dibuja
 * acá en el documento padre por el mismo motivo de siempre: los controles
 * de edición viven fuera del iframe, superpuestos por posición.
 *
 * REDISEÑO (extensión del paso 1 de la migración a layout de 3 zonas
 * fijas, ver la memoria de producto): "Estilo del bloque" y "Eliminar
 * bloque" salieron de acá — un click SIMPLE sobre el bloque ya muestra su
 * Nivel 2 en la zona de Propiedades fija (ver alHacerClickIzquierdo en
 * editor-iframe.js y mostrarEstiloDeBloque en App.jsx), que ahora también
 * trae el botón "Eliminar bloque" al pie (ver DrawerEstilo.jsx). Bug de UX
 * real reportado por el usuario: el menú contextual, al aparecer pegado al
 * click, tapaba justo el contenido que se quería editar — sacarlo de acá
 * resuelve ese caso sin perder ninguna acción, solo cambia el gesto (click
 * simple en vez de click derecho + elegir del menú).
 *
 * Lo que SIGUE necesitando el menú flotante (no tiene un lugar fijo
 * natural, depende de la POSICIÓN exacta del click): "Eliminar item" (un
 * "Beneficio" individual dentro de una Franja — solo si `posicion.item`
 * viene informado) y "Seleccionar contenedor" (Fase 3, "primitivas de
 * layout" — SOLO si `posicion.containerId` viene informado, es decir, el
 * bloque clickeado vive DENTRO de un Container). Bug de UX real reportado
 * por el usuario: sin esta opción, no había forma de apuntar al Container
 * PADRE cuando tenía hijos adentro — cualquier click en su área visible
 * resolvía primero al hijo bajo el cursor.
 */
export function MenuContextualBloque({ posicion, onEliminarItem, onSeleccionarContenedorPadre, onCerrar }) {
  if (!posicion) return null;
  if (!posicion.containerId && !posicion.item) return null;

  return (
    <div className="sofia-menu-contextual__fondo" onClick={onCerrar} onContextMenu={(e) => e.preventDefault()}>
      <div
        className="sofia-menu-contextual"
        style={{ top: `${posicion.y}px`, left: `${posicion.x}px` }}
        onClick={(evento) => evento.stopPropagation()}
      >
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
      </div>
    </div>
  );
}

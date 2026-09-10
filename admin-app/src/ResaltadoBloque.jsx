/**
 * Overlay de resaltado del bloque bajo el mouse (Hero, Franja de
 * beneficios, etc.) — ayuda a orientarse en páginas con varios bloques.
 * Mismo patrón "controles fuera del documento del iframe" ya usado en
 * DrawerEstilo.jsx: el iframe informa posición+nombre vía postMessage,
 * este componente dibuja el borde y la etiqueta superpuestos, sin tocar
 * el DOM del iframe.
 *
 * Puramente visual, sin controles interactivos propios — las acciones
 * sobre un bloque (eliminar, y a futuro clonar/mover) viven en el menú
 * contextual (MenuContextualBloque.jsx, click derecho), no acá: un botón
 * flotante en este overlay competía por el mouse con los propios eventos
 * mouseover/mouseleave que lo hacen aparecer/desaparecer (bug real
 * encontrado en la práctica, ver la memoria de producto "Sofia Studio").
 */
export function ResaltadoBloque({ bloque }) {
  if (!bloque) return null;

  const { rect, nombre } = bloque;

  return (
    <div
      className="sofia-resaltado-bloque"
      style={{
        top: `${rect.top}px`,
        left: `${rect.left}px`,
        width: `${rect.width}px`,
        height: `${rect.height}px`,
      }}
    >
      <span className="sofia-resaltado-bloque__etiqueta">{nombre}</span>
    </div>
  );
}

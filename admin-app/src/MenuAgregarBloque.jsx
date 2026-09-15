/**
 * Menú desplegable de "agregar bloque" — paso 4 del rediseño de layout
 * (ver la memoria de producto): único caller hoy es una LÍNEA de
 * inserción DENTRO del iframe (el "+" entre dos bloques, ver
 * activarLineasInsertar() en editor-iframe.js) — agregar al FINAL de la
 * página se movió a la pestaña "Agregar" del panel de Estructura
 * (CatalogoAgregar, PanelEstructura.jsx), que no necesita este menú
 * flotante. Este sigue existiendo porque insertar EN una posición
 * puntual del medio de la página sí necesita anclarse a coordenadas
 * exactas, algo que una pestaña fija no puede resolver.
 *
 * Lista el catálogo REAL de Sofia_Componente_* del tema (mismo endpoint
 * sofia/v1/catalogo-bloques que ya usa editor-iframe.js para los nombres
 * del overlay de resaltado), nunca una lista curada aparte.
 *
 * Elegir un tipo no toca el iframe directo — App.jsx guarda la estructura
 * ampliada contra el proxy REST y pide el HTML de solo ese bloque, para
 * que WordPress renderice el bloque nuevo con el PHP real de su
 * Componente (ver el comentario de agregarBloque() en App.jsx).
 */
// posicion: {x, y} — coordenadas donde dibujar el menú, tomadas del click
// en el "+" de la línea de inserción — mismo patrón que MenuContextualBloque.
export function MenuAgregarBloque({ catalogo, onElegir, onCerrar, posicion, titulo }) {
  const estiloPosicion = posicion ? { top: `${posicion.y}px`, left: `${posicion.x}px`, right: "auto" } : undefined;

  return (
    <div className="sofia-menu-agregar__fondo" onClick={onCerrar}>
      <div className="sofia-menu-agregar" style={estiloPosicion} onClick={(evento) => evento.stopPropagation()}>
        <div className="sofia-menu-agregar__titulo">{titulo}</div>
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

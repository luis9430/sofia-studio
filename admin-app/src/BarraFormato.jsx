/**
 * Barra de formato flotante — aparece sobre la selección de texto dentro
 * del iframe (nunca dentro del propio iframe, ver inc/js/editor-iframe.js:
 * mismo patrón "controles fuera del documento del iframe" ya validado
 * contra Bricks/Adobe AEM). Recibe la posición vía postMessage
 * (coordenadas relativas al iframe, que coinciden con las del documento
 * padre porque el iframe es full-bleed a inset:0 — sin offset que sumar).
 *
 * Al hacer click en un botón, NO toca el DOM directo — manda un mensaje de
 * vuelta al iframe ("sofia:aplicar-formato") para que sea el propio
 * documento del iframe el que ejecute document.execCommand sobre su
 * selección real.
 */
// ALTO_BARRA/MARGEN: si la selección está muy cerca del borde superior
// (ej. la primera línea de contenido de la página, justo debajo de la
// barra de estado flotante que ya ocupa esa franja), no hay espacio para
// dibujar la barra ARRIBA de la selección sin que quede recortada o
// tapada por la barra de estado — en ese caso se dibuja ABAJO de la
// selección en su lugar. Bug real encontrado en la práctica: "Beneficio 1"
// es la primera línea de la Franja de beneficios, justo debajo del Hero,
// y la barra quedaba pegada al borde/tapada.
const ALTO_BARRA = 36;
const MARGEN = 8;
const ALTO_BARRA_ESTADO = 50; // franja que ya ocupa .sofia-editor-admin__barra-flotante

export function BarraFormato({ posicion, onAplicarFormato, onAbrirEstilo }) {
  if (!posicion) return null;

  const espacioArriba = posicion.top - ALTO_BARRA_ESTADO;
  const vaArriba = espacioArriba > ALTO_BARRA + MARGEN;
  const top = vaArriba ? posicion.top - ALTO_BARRA - MARGEN : posicion.bottom + MARGEN;

  return (
    <div
      className="sofia-barra-formato"
      style={{ top: `${top}px`, left: `${Math.max(posicion.left, 60)}px` }}
      // evita que el mousedown sobre la barra dispare un "blur" del campo
      // editable en el iframe ANTES de que el click llegue a ejecutarse.
      onMouseDown={(evento) => evento.preventDefault()}
    >
      <button type="button" onClick={() => onAplicarFormato("bold")} title="Negrita">
        <strong>B</strong>
      </button>
      <button type="button" onClick={() => onAplicarFormato("italic")} title="Cursiva">
        <em>I</em>
      </button>
      <span className="sofia-barra-formato__separador" />
      <button type="button" onClick={onAbrirEstilo} title="Estilo (alineación, color)">
        Estilo
      </button>
    </div>
  );
}

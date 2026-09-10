/**
 * Botón ✏️ que aparece pegado a un campo tras un solo click (ver
 * "sofia:campo-clickeado" en editor-iframe.js) — pedido explícito del
 * usuario: antes, la única forma de llegar al control de estilo era
 * SELECCIONAR texto arrastrando, un gesto poco descubrible con un click
 * simple. Presionarlo abre el drawer de estilo para ese campo.
 *
 * Mismo patrón "controles fuera del documento del iframe" que el resto del
 * chrome de edición — coordenadas ya vienen relativas a este documento
 * (iframe full-bleed a inset:0, ver App.jsx).
 */
export function BotonEditarCampo({ rect, onClick }) {
  if (!rect) return null;

  return (
    <button
      type="button"
      className="sofia-boton-editar-campo"
      style={{ top: `${rect.top}px`, left: `${rect.left + rect.width}px` }}
      title="Editar estilo"
      // evita que el mousedown dispare el blur del campo editable en el
      // iframe antes de que el click llegue a abrir el drawer.
      onMouseDown={(evento) => evento.preventDefault()}
      onClick={onClick}
    >
      ✏️
    </button>
  );
}

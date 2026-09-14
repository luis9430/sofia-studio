import { useEffect, useRef, useState } from "preact/hooks";

/**
 * SelectorTokenAvanzado — dropdown Preact propio para el modo "Avanzado"
 * de CampoTokenVisual.jsx, con swatch de color real por CADA una de las
 * 154 variables (variantes de tono/opacidad incluidas) — reemplaza el
 * <datalist> HTML nativo que CampoConToken.jsx usaba antes acá.
 *
 * Bug real reportado por el usuario probando en vivo: un <datalist> es
 * autocompletado de un <input>, no puede mostrar nada más que texto plano
 * por opción — es una limitación de la PLATAFORMA (soporte de `label` en
 * <option> es inconsistente entre navegadores y de todas formas nunca
 * pinta un swatch), no algo que se arregle agregando más código al
 * datalist. Con 154 nombres reales, una lista de solo texto como
 * "tertiary-30" es, en palabras del usuario, "a granel" — no se entiende
 * qué es sin verlo pintado. Este componente resuelve eso de raíz: un menú
 * propio, filtrable escribiendo, donde CADA fila tiene su swatch real
 * (mismo criterio "lo que se puede preview" que ya usa el selector básico,
 * ver CampoTokenVisual.jsx) — solo para la categoría "color", donde un
 * swatch tiene sentido; el resto de categorías (space/radius/shadow/
 * texto/otras) no tienen un valor "pintable" así que siguen usando el
 * <input> de texto libre simple, sin este selector.
 *
 * Solo se usa acá — el <datalist>/CampoConToken.jsx en sí NO se toca ni se
 * elimina, sigue existiendo tal cual para cualquier otro caller (ninguno
 * hoy, pero no hay motivo para romper su API pública).
 */
export function SelectorTokenAvanzado({ valor, onCambiar, restUrl, nonce }) {
  const [variables, setVariables] = useState([]); // [{nombre, valor}]
  const [abierto, setAbierto] = useState(false);
  const [filtro, setFiltro] = useState("");
  const contenedorRef = useRef(null);

  useEffect(() => {
    if (!restUrl) return;
    let cancelado = false;
    fetch(`${restUrl}core-framework/variables-con-valor`, { headers: { "X-WP-Nonce": nonce } })
      .then((resp) => (resp.ok ? resp.json() : null))
      .then((datos) => {
        if (!cancelado && datos?.color) setVariables(datos.color);
      })
      .catch(() => {});
    return () => {
      cancelado = true;
    };
  }, [restUrl, nonce]);

  // Cierra el menú al hacer click afuera — mismo patrón ya usado en el
  // resto del admin-app para menús flotantes (ver MenuContextualBloque.jsx).
  useEffect(() => {
    if (!abierto) return;
    function alClickFuera(evento) {
      if (contenedorRef.current && !contenedorRef.current.contains(evento.target)) {
        setAbierto(false);
      }
    }
    window.addEventListener("mousedown", alClickFuera);
    return () => window.removeEventListener("mousedown", alClickFuera);
  }, [abierto]);

  const filtradas = filtro
    ? variables.filter((v) => v.nombre.toLowerCase().includes(filtro.toLowerCase()))
    : variables;

  return (
    <div className="sofia-selector-token-avanzado" ref={contenedorRef}>
      <button
        type="button"
        className="sofia-selector-token-avanzado__gatillo"
        onClick={() => setAbierto(!abierto)}
      >
        {valor ? (
          <>
            <span className="sofia-selector-token-avanzado__swatch-chico" style={{ background: `var(--${valor})` }} />
            {valor}
          </>
        ) : (
          "Elegir variante…"
        )}
      </button>

      {abierto && (
        <div className="sofia-selector-token-avanzado__menu">
          <input
            type="text"
            className="sofia-selector-token-avanzado__filtro"
            placeholder="Buscar…"
            value={filtro}
            onInput={(evento) => setFiltro(evento.currentTarget.value)}
            autoFocus
          />
          <div className="sofia-selector-token-avanzado__lista">
            {filtradas.length === 0 && <p className="sofia-selector-token-avanzado__vacio">Sin resultados.</p>}
            {filtradas.map((v) => (
              <button
                key={v.nombre}
                type="button"
                className={`sofia-selector-token-avanzado__fila ${valor === v.nombre ? "sofia-selector-token-avanzado__fila--activa" : ""}`}
                onClick={() => {
                  onCambiar(v.nombre);
                  setAbierto(false);
                  setFiltro("");
                }}
              >
                <span className="sofia-selector-token-avanzado__swatch-chico" style={{ background: v.valor }} />
                <span>{v.nombre}</span>
              </button>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

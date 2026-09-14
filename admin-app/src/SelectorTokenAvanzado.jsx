import { useEffect, useRef, useState } from "preact/hooks";

/**
 * SelectorTokenAvanzado — dropdown Preact propio para el modo "Avanzado"
 * de CampoTokenVisual.jsx, con preview REAL por CADA una de las 154
 * variables (variantes de tono/opacidad incluidas) — reemplaza el
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
 * propio, filtrable escribiendo, donde CADA fila tiene su preview real —
 * swatch circular para "color", o una muestra con la propiedad CSS
 * aplicada (border-radius/box-shadow/width) para radius/shadow/space,
 * mismo criterio que el selector básico (ver CampoTokenVisual.jsx). Solo
 * "texto"/"otras" no tienen preview posible, siguen usando el
 * <input>+datalist de CampoConToken.jsx sin cambios.
 *
 * `categoria` decide QUÉ agrupación del catálogo pedir y cómo aplicar el
 * preview de cada fila — mismo shape {nombre, valor} para las 4, pero
 * "valor" significa cosas distintas según la propiedad CSS real
 * (background vs. border-radius vs. box-shadow vs. width).
 */
export function SelectorTokenAvanzado({ categoria, valor, onCambiar, restUrl, nonce }) {
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
        if (!cancelado) setVariables(datos?.[categoria] || []);
      })
      .catch(() => {});
    return () => {
      cancelado = true;
    };
  }, [restUrl, nonce, categoria]);

  // estiloPreview: mismo mapeo categoria → propiedad CSS que
  // CampoTokenVisual.jsx usa para las opciones del selector básico — un
  // solo lugar decide "qué significa el valor de esta categoría", nunca
  // duplicado con lógica distinta entre ambos componentes.
  function estiloPreview(valorCrudo) {
    if (!valorCrudo) return undefined;
    if ( 'color' === categoria ) return { background: valorCrudo };
    if ( 'radius' === categoria ) return { borderRadius: valorCrudo };
    if ( 'shadow' === categoria ) return { boxShadow: valorCrudo };
    if ( 'space' === categoria ) return { width: valorCrudo };
    return undefined;
  }

  const valorRealActual = variables.find((v) => v.nombre === valor)?.valor;

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
            <span
              className={`sofia-selector-token-avanzado__swatch-chico ${categoria === "color" ? "sofia-selector-token-avanzado__swatch-chico--color" : ""}`}
              style={estiloPreview(valorRealActual)}
            />
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
                <span
                  className={`sofia-selector-token-avanzado__swatch-chico ${categoria === "color" ? "sofia-selector-token-avanzado__swatch-chico--color" : ""}`}
                  style={estiloPreview(v.valor)}
                />
                <span>{v.nombre}</span>
              </button>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

/**
 * CampoConToken — control dual valor-fijo/token de Core Framework,
 * compartido por PanelEstiloGlobal.jsx (Nivel 3, paleta/medidas del
 * sitio) y DrawerEstilo.jsx (Nivel 1/2, estilo por campo/bloque) — mismo
 * mecanismo en los 3 niveles: nunca duplicar la lógica de "¿es un token
 * cf: o un valor normal?" en cada lugar que necesite un color.
 *
 * Un <input> normal (tipo variable según `tipo`: "color" para un color
 * picker nativo, "text" para una medida libre) O un campo de texto con
 * sugerencias de un <datalist id="sofia-variables-core-framework-{categoria}">
 * cuando el valor referencia un token ("cf:{nombre}", ver
 * Sofia_Estilo_Global::PREFIJO_TOKEN_CORE_FRAMEWORK/resolver_valor() del
 * lado PHP — mismo prefijo reusado tal cual desde
 * Sofia_Componente::atributo_estilo()/atributo_estilo_bloque()).
 *
 * `categoria` ("color" | "texto" | "radius" | "shadow" | "space" | "otras",
 * default "color") decide QUÉ datalist alimenta las sugerencias — mismas
 * categorías que Sofia_Estilo_Global::variables_core_framework_por_
 * categoria() del lado PHP. Necesario por un bug real reportado por el
 * usuario: con un único datalist plano (todas las 154 variables del sitio
 * juntas), nada impedía escribir "bg-body" (una variable de COLOR) como
 * sugerencia en "Radio de borde" (que espera "radius-*") — sintácticamente
 * válido pero sin sentido para esa propiedad. Sigue siendo texto libre
 * (nunca se bloquea lo que no está en la lista), solo cambia qué aparece
 * sugerido.
 *
 * El botón "CF" alterna entre ambos modos — pedido explícito del usuario:
 * en el drawer de campo, el color pasa de una paleta fija de 5 swatches a
 * un <input type="color"> libre + este mismo botón, para no perder la
 * opción de "cualquier color" ni la de "un token real del sitio".
 *
 * `conUnidad` (default false): cuando el valor NO es token, en vez de un
 * <input type="text"> plano para escribir "8px" a mano, muestra un
 * <input type="number"> + <select> de unidad (px/rem/%) — mismo patrón ya
 * usado en DrawerEstilo.jsx para tamaño de fuente/espaciado vertical
 * (parsearMedida/formatearMedida), ahora reusado acá para Radio de borde.
 * Pedido explícito del usuario ("mejorar los controles para px/rem/%") —
 * escribir la unidad a mano en un campo de texto libre es más propenso a
 * error (typos, olvidar la unidad) que elegirla de una lista corta. Nunca
 * se usa en el modo token (ahí el valor es un NOMBRE, no una medida).
 */
export const PREFIJO_TOKEN_CORE_FRAMEWORK = "cf:";

// CATEGORIAS_TOKEN: mismas 6 claves que
// Sofia_Estilo_Global::variables_core_framework_por_categoria() del lado
// PHP — un id de <datalist> por categoría, nunca un solo id compartido.
const CATEGORIAS_TOKEN = ["color", "texto", "radius", "shadow", "space", "otras"];

function idDatalist(categoria) {
  return `sofia-variables-core-framework-${CATEGORIAS_TOKEN.includes(categoria) ? categoria : "otras"}`;
}

// parsearMedidaSimple/formatearMedidaSimple: versión de 1 solo número (no
// el shorthand de 4 lados de EspaciadoLados, ya eliminado) — separan
// "8px" en {numero:"8", unidad:"px"} para poder editarlos como 2 controles
// independientes.
function parsearMedidaSimple(valor) {
  if (!valor) return { numero: "", unidad: "px" };
  const match = String(valor).match(/^(-?\d+(?:\.\d+)?)(px|rem|%)?$/);
  if (!match) return { numero: "", unidad: "px" };
  return { numero: match[1], unidad: match[2] || "px" };
}

function formatearMedidaSimple(numero, unidad) {
  return numero ? `${numero}${unidad}` : "";
}

export function CampoConToken({ tipo, valor, placeholderNormal, categoria = "color", conUnidad = false, onCambiar }) {
  const valorActual = valor || "";
  const esToken = valorActual.startsWith(PREFIJO_TOKEN_CORE_FRAMEWORK);
  const nombreToken = esToken ? valorActual.slice(PREFIJO_TOKEN_CORE_FRAMEWORK.length) : "";
  const medida = conUnidad ? parsearMedidaSimple(valorActual) : null;

  return (
    <div className="sofia-campo-token__fila">
      {esToken ? (
        <input
          type="text"
          className="sofia-campo-token__input-token"
          placeholder="nombre-del-token"
          list={idDatalist(categoria)}
          value={nombreToken}
          onInput={(evento) => onCambiar(PREFIJO_TOKEN_CORE_FRAMEWORK + evento.currentTarget.value)}
        />
      ) : conUnidad ? (
        <div className="sofia-campo-token__medida">
          <input
            type="number"
            placeholder="Por defecto"
            value={medida.numero}
            onInput={(evento) => onCambiar(formatearMedidaSimple(evento.currentTarget.value, medida.unidad))}
          />
          <select
            value={medida.unidad}
            onChange={(evento) => onCambiar(formatearMedidaSimple(medida.numero, evento.currentTarget.value))}
          >
            <option value="px">px</option>
            <option value="rem">rem</option>
            <option value="%">%</option>
          </select>
        </div>
      ) : (
        <input
          type={tipo}
          className={tipo === "text" ? "sofia-campo-token__input-token" : undefined}
          placeholder={placeholderNormal}
          value={tipo === "color" ? valorActual || "#1c1a17" : valorActual}
          onInput={(evento) => onCambiar(evento.currentTarget.value)}
        />
      )}
      <button
        type="button"
        className={`sofia-campo-token__cf ${esToken ? "sofia-campo-token__cf--activo" : ""}`}
        title={
          esToken
            ? "Usando un token de Core Framework — click para volver a un valor fijo"
            : "Usar un token de Core Framework en vez de un valor fijo"
        }
        onClick={() => onCambiar(esToken ? "" : PREFIJO_TOKEN_CORE_FRAMEWORK)}
      >
        CF
      </button>
    </div>
  );
}

/**
 * ListaVariablesCoreFramework — los <datalist> compartidos (uno por
 * categoría) que alimentan `list="sofia-variables-core-framework-{categoria}"`
 * de arriba. Un componente que use CampoConToken necesita renderizar ESTE
 * también (una sola vez en su árbol) con el objeto agrupado real leído de
 * GET sofia/v1/core-framework/variables — {color:[...], texto:[...],
 * radius:[...], shadow:[...], space:[...], otras:[...]}.
 */
export function ListaVariablesCoreFramework({ nombres }) {
  return (
    <>
      {CATEGORIAS_TOKEN.map((categoria) => (
        <datalist key={categoria} id={idDatalist(categoria)}>
          {(nombres[categoria] || []).map((nombre) => (
            <option key={nombre} value={nombre} />
          ))}
        </datalist>
      ))}
    </>
  );
}

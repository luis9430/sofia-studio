/**
 * CampoConToken — control dual valor-fijo/token de Core Framework,
 * compartido por PanelEstiloGlobal.jsx (Nivel 3, paleta/medidas del
 * sitio) y DrawerEstilo.jsx (Nivel 1/2, estilo por campo/bloque) — mismo
 * mecanismo en los 3 niveles: nunca duplicar la lógica de "¿es un token
 * cf: o un valor normal?" en cada lugar que necesite un color.
 *
 * Un <input> normal (tipo variable según `tipo`: "color" para un color
 * picker nativo, "text" para una medida libre) O un campo de texto con
 * sugerencias de <datalist id="sofia-variables-core-framework"> cuando el
 * valor referencia un token ("cf:{nombre}", ver
 * Sofia_Estilo_Global::PREFIJO_TOKEN_CORE_FRAMEWORK/resolver_valor() del
 * lado PHP — mismo prefijo reusado tal cual desde
 * Sofia_Componente::atributo_estilo()/atributo_estilo_bloque()).
 *
 * El botón "CF" alterna entre ambos modos — pedido explícito del usuario:
 * en el drawer de campo, el color pasa de una paleta fija de 5 swatches a
 * un <input type="color"> libre + este mismo botón, para no perder la
 * opción de "cualquier color" ni la de "un token real del sitio".
 */
export const PREFIJO_TOKEN_CORE_FRAMEWORK = "cf:";

export function CampoConToken({ tipo, valor, placeholderNormal, onCambiar }) {
  const valorActual = valor || "";
  const esToken = valorActual.startsWith(PREFIJO_TOKEN_CORE_FRAMEWORK);
  const nombreToken = esToken ? valorActual.slice(PREFIJO_TOKEN_CORE_FRAMEWORK.length) : "";

  return (
    <div className="sofia-campo-token__fila">
      {esToken ? (
        <input
          type="text"
          className="sofia-campo-token__input-token"
          placeholder="nombre-del-token"
          list="sofia-variables-core-framework"
          value={nombreToken}
          onInput={(evento) => onCambiar(PREFIJO_TOKEN_CORE_FRAMEWORK + evento.currentTarget.value)}
        />
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
 * ListaVariablesCoreFramework — el <datalist> compartido que alimenta el
 * `list="sofia-variables-core-framework"` de arriba. Un componente que
 * use CampoConToken necesita renderizar ESTE también (una sola vez en su
 * árbol) con los nombres reales leídos de
 * GET sofia/v1/core-framework/variables.
 */
export function ListaVariablesCoreFramework({ nombres }) {
  return (
    <datalist id="sofia-variables-core-framework">
      {nombres.map((nombre) => (
        <option key={nombre} value={nombre} />
      ))}
    </datalist>
  );
}

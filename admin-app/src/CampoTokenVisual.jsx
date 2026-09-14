import { useEffect, useState } from "preact/hooks";
import { CampoConToken, PREFIJO_TOKEN_CORE_FRAMEWORK } from "./CampoConToken.jsx";
import { SelectorTokenAvanzado } from "./SelectorTokenAvanzado.jsx";

// CATEGORIAS_CON_PREVIEW: mismas 4 que
// Sofia_Estilo_Global::catalogo_tokens_visual() manda con "preview" real
// (ver el comentario largo ahí) — "texto"/"otras" no tienen un valor
// "pintable" como propiedad CSS de una sola línea, así que su modo
// avanzado sigue siendo el <input>+datalist simple de CampoConToken.jsx.
const CATEGORIAS_CON_PREVIEW = ["color", "radius", "shadow", "space"];

/**
 * CampoTokenVisual — selector VISUAL de tokens de Core Framework, con
 * etiqueta humana + preview (swatch de color / muestra de espaciado-radio-
 * sombra) — reemplaza el <input> de texto libre + autocompletado de
 * CampoConToken.jsx como control PRIMARIO para las categorías que
 * Sofia_Estilo_Global::catalogo_tokens_visual() sabe traducir.
 *
 * Decisión de arquitectura de esta conversación (ver la memoria de
 * producto, "evaluar Core Framework"): investigación real confirmó que
 * Core Framework en sí es un sistema de diseño sólido — 154 tokens reales,
 * escalas coherentes (space-xs..xl, radius-xs..full, shadow-xs..xl),
 * responsive fluido real vía clamp(). El problema nunca fue el plugin, fue
 * que Sofia Studio lo exponía como texto libre sin traducción — un usuario
 * no tiene por qué saber que "space-m" es el espaciado "normal" del
 * sistema. Acá se traduce a lenguaje humano ("Espaciado: Compacto/Base/
 * Amplio", pedido explícito del usuario) con preview real.
 *
 * Modo dual, igual que CampoConToken ya resolvía para "valor fijo vs
 * token": acá es "selector visual (básico) vs texto libre con
 * autocompletado (avanzado, CampoConToken sin cambios)" — un botón
 * "Avanzado" cae al control de siempre para el 10% de casos que el
 * catálogo visual no cubre (variantes de tono de color, una variable
 * "otras" sin traducir). El VALOR guardado es siempre el mismo formato
 * ("cf:{token}") sin importar qué control se usó para elegirlo — ambos
 * modos son intercambiables, nunca un formato paralelo.
 *
 * `categoria` acepta las mismas 6 que CampoConToken ("color", "texto",
 * "radius", "shadow", "space", "otras") — solo "color"/"radius"/"shadow"/
 * "space" tienen traducción real hoy (ver ETIQUETAS_ESCALA/ETIQUETAS_COLOR
 * del lado PHP); para cualquier otra categoría este componente cae
 * directo al modo avanzado (nada que mostrar en el selector visual).
 */
export function CampoTokenVisual({ categoria = "color", valor, onCambiar, restUrl, nonce }) {
  const [catalogo, setCatalogo] = useState(null); // null = cargando
  const [modoAvanzado, setModoAvanzado] = useState(false);

  useEffect(() => {
    if (!restUrl) return;
    let cancelado = false;
    fetch(`${restUrl}core-framework/tokens-visual`, { headers: { "X-WP-Nonce": nonce } })
      .then((resp) => (resp.ok ? resp.json() : null))
      .then((datos) => {
        if (!cancelado) setCatalogo(datos);
      })
      .catch(() => {
        if (!cancelado) setCatalogo(null);
      });
    return () => {
      cancelado = true;
    };
  }, [restUrl, nonce]);

  const valorActual = valor || "";
  const esToken = valorActual.startsWith(PREFIJO_TOKEN_CORE_FRAMEWORK);
  const tokenActual = esToken ? valorActual.slice(PREFIJO_TOKEN_CORE_FRAMEWORK.length) : "";
  const opciones = catalogo?.[categoria] || [];

  // Sin catálogo visual para esta categoría (todavía cargando, la petición
  // falló, o la categoría no tiene traducción — ej. "texto"/"otras" hoy) —
  // cae directo al control de siempre, nunca un selector vacío sin
  // ninguna opción entre las que elegir.
  if (!catalogo || opciones.length === 0 || modoAvanzado) {
    return (
      <div className="sofia-token-visual">
        {CATEGORIAS_CON_PREVIEW.includes(categoria) && esToken ? (
          // SelectorTokenAvanzado (no el <input list=...> de
          // CampoConToken) — preview real por cada una de las 154
          // variables, ver el comentario largo del componente. Solo
          // reemplaza el INPUT del modo token; el botón "CF" sigue siendo
          // el de CampoConToken de siempre (mismo componente, mismo
          // toggle) para no duplicar esa lógica — se renderiza acá aparte
          // porque CampoConToken no acepta inyectar un input custom en su
          // lugar del <input list=...>. Extendido a radius/shadow/space
          // (no solo color) tras probar en vivo: el modo avanzado de esas
          // 3 categorías seguía siendo el datalist "a granel" original —
          // mismo bug, mismo fix.
          <div className="sofia-campo-token__fila">
            <SelectorTokenAvanzado
              categoria={categoria}
              valor={tokenActual}
              onCambiar={(nombreToken) => onCambiar(PREFIJO_TOKEN_CORE_FRAMEWORK + nombreToken)}
              restUrl={restUrl}
              nonce={nonce}
            />
            <button
              type="button"
              className="sofia-campo-token__cf sofia-campo-token__cf--activo"
              title="Usando un token de Core Framework — click para volver a un valor fijo"
              onClick={() => onCambiar("")}
            >
              CF
            </button>
          </div>
        ) : (
          <CampoConToken
            tipo={categoria === "color" ? "color" : "text"}
            categoria={categoria}
            conUnidad={categoria !== "color"}
            valor={valor}
            onCambiar={onCambiar}
          />
        )}
        {opciones.length > 0 && (
          <button type="button" className="sofia-token-visual__modo" onClick={() => setModoAvanzado(false)}>
            ← Volver al selector
          </button>
        )}
      </div>
    );
  }

  return (
    <div className="sofia-token-visual">
      <div className="sofia-token-visual__opciones">
        {opciones.map((opcion) => (
          <button
            key={opcion.token}
            type="button"
            className={`sofia-token-visual__opcion sofia-token-visual__opcion--${categoria} ${tokenActual === opcion.token ? "sofia-token-visual__opcion--activa" : ""}`}
            title={opcion.etiqueta}
            onClick={() => onCambiar(tokenActual === opcion.token ? "" : PREFIJO_TOKEN_CORE_FRAMEWORK + opcion.token)}
          >
            {/* El preview ES el control — nunca solo texto. "color" pinta
                un swatch circular con el color real; radius/shadow/space
                aplican el VALOR CRUDO real (ver
                Sofia_Estilo_Global::catalogo_tokens_visual()) a la
                propiedad CSS que corresponde — border-radius/box-shadow/
                width — nunca "var(--token)" a mano: esa variable no
                existe en el documento de wp-admin (bug real ya corregido
                una vez para color, replicado acá porque el mismo
                problema afectaba a estas 3 categorías también). Sin
                preview (categoría desconocida) cae a un cuadrado neutro
                sin efecto, mejor que romper el layout. */}
            {categoria === "color" && opcion.preview ? (
              <span className="sofia-token-visual__swatch" style={{ background: opcion.preview }} />
            ) : (
              <span
                className={`sofia-token-visual__muestra sofia-token-visual__muestra--${categoria}`}
                style={
                  categoria === "radius"
                    ? { borderRadius: opcion.preview }
                    : categoria === "shadow"
                      ? { boxShadow: opcion.preview }
                      : categoria === "space"
                        ? { width: opcion.preview }
                        : undefined
                }
              />
            )}
            <span className="sofia-token-visual__etiqueta">{opcion.etiqueta}</span>
          </button>
        ))}
      </div>
      <button type="button" className="sofia-token-visual__modo" onClick={() => setModoAvanzado(true)}>
        Avanzado (elegir cualquier token)
      </button>
    </div>
  );
}

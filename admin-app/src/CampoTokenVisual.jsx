import { useEffect, useState } from "preact/hooks";
import { CampoConToken, PREFIJO_TOKEN_CORE_FRAMEWORK } from "./CampoConToken.jsx";

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
        <CampoConToken tipo={categoria === "color" ? "color" : "text"} categoria={categoria} conUnidad={categoria !== "color"} valor={valor} onCambiar={onCambiar} />
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
            {/* El preview ES el control — un swatch de color real, o (para
                espaciado/radio/sombra) una muestra visual con la propiedad
                real aplicada vía CSS var(), nunca solo texto. "Lo que se
                puede preview estaría bien" — pedido explícito del usuario. */}
            {opcion.preview ? (
              <span className="sofia-token-visual__swatch" style={{ background: opcion.preview }} />
            ) : (
              <span className={`sofia-token-visual__muestra sofia-token-visual__muestra--${categoria}`} style={{ "--sofia-token-visual-valor": `var(--${opcion.token})` }} />
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

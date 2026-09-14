import { CampoTokenVisual } from "./CampoTokenVisual.jsx";

/**
 * CampoDesdeSchema — un control de Nivel 2 (estilo de bloque) renderizado a
 * partir de UNA entrada del schema que devuelve
 * GET sofia/v1/catalogo-bloques/{tipo}/schema (ver
 * Sofia_Componente::schema_bloque_generico()/schema_propio() del lado PHP,
 * fusionados en Sofia_Componente_Factory::schema_de()). Reemplaza los
 * <select>/botones que DrawerEstilo.jsx tenía hardcodeados uno por uno — el
 * PHP es ahora la única fuente de verdad de "qué controles existen", este
 * componente solo sabe traducir CADA tipo de entrada a su control real, ver
 * Fase 2 del plan de "primitivas de layout" (memoria de producto).
 *
 * Tipos soportados, mismo vocabulario que ya usaba DrawerEstilo.jsx a mano:
 * - "select": <select> de opciones fijas ({opciones:[{valor,etiqueta}]}).
 * - "botones_numero": fila de botones exclusivos de un número
 *   ({opciones:["2","3","4"]}), ej. Columnas/Columnas de grilla.
 * - "color_token" / "medida_token": CampoTokenVisual.jsx — selector VISUAL
 *   con etiqueta humana + preview (Espaciado: Compacto/Base/Amplio, no un
 *   input de texto con 154 nombres técnicos como sugerencia) sobre el
 *   catálogo de Sofia_Estilo_Global::catalogo_tokens_visual(); cae a
 *   CampoConToken.jsx (texto libre + autocompletado) en modo "Avanzado" —
 *   ver el comentario largo en CampoTokenVisual.jsx. "color_token" fija
 *   categoria="color"; "medida_token" usa lo que declare el schema
 *   (radius/shadow/space/etc.).
 * - "toggle": botón on/off — a diferencia de Nivel 1 (Negrita/Sombra de
 *   texto, que activan un valor pre-armado propio de cada campo), acá
 *   activa/desactiva simplemente "true"/"" (ningún Componente de Nivel 2
 *   necesitó hoy un valor pre-armado distinto).
 */
export function CampoDesdeSchema({ nombre, definicion, valor, onCambiar, restUrl, nonce }) {
  const { tipo, etiqueta, ayuda, opciones } = definicion;

  return (
    <div className="sofia-drawer-estilo__grupo">
      <span className="sofia-drawer-estilo__etiqueta">{etiqueta}</span>
      {ayuda && <p className="sofia-drawer-estilo__ayuda-condicion">{ayuda}</p>}

      {tipo === "botones_numero" && (
        <div className="sofia-drawer-estilo__tamanos">
          {opciones.map((n) => (
            <button
              key={n}
              type="button"
              className={`sofia-drawer-estilo__tamano ${valor === n ? "sofia-drawer-estilo__tamano--activo" : ""}`}
              onClick={() => onCambiar(valor === n ? "" : n)}
            >
              {n}
            </button>
          ))}
        </div>
      )}

      {tipo === "color_token" && (
        <CampoTokenVisual categoria="color" valor={valor} onCambiar={onCambiar} restUrl={restUrl} nonce={nonce} />
      )}

      {tipo === "medida_token" && (
        <CampoTokenVisual
          categoria={definicion.categoria || "otras"}
          valor={valor}
          onCambiar={onCambiar}
          restUrl={restUrl}
          nonce={nonce}
        />
      )}

      {tipo === "select" && (
        <select value={valor || ""} onChange={(evento) => onCambiar(evento.currentTarget.value)}>
          {opciones.map((op) => (
            <option key={op.valor || "ninguno"} value={op.valor}>
              {op.etiqueta}
            </option>
          ))}
        </select>
      )}

      {tipo === "toggle" && (
        <button
          type="button"
          className={`sofia-drawer-estilo__toggle ${valor ? "sofia-drawer-estilo__toggle--activo" : ""}`}
          onClick={() => onCambiar(valor ? "" : "true")}
        >
          {valor ? "Activada" : "Desactivada"}
        </button>
      )}
    </div>
  );
}

/**
 * CamposDesdeSchema — recorre TODO el schema (objeto {nombre: definicion},
 * orden de inserción de PHP preservado por json_decode/fetch) y renderiza un
 * CampoDesdeSchema por entrada — lo que DrawerEstilo.jsx monta entero para
 * nivel="bloque" en vez del bloque de JSX fijo que tenía antes.
 */
export function CamposDesdeSchema({ schema, estilo, actualizar, restUrl, nonce }) {
  return Object.entries(schema).map(([nombre, definicion]) => (
    <CampoDesdeSchema
      key={nombre}
      nombre={nombre}
      definicion={definicion}
      valor={estilo[nombre]}
      onCambiar={(valorNuevo) => actualizar({ [nombre]: valorNuevo })}
      restUrl={restUrl}
      nonce={nonce}
    />
  ));
}

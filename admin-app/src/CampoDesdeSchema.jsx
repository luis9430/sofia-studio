import { useState } from "preact/hooks";
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

// AVISOS_CANTIDAD_HIJOS: anexo del plan "50 primitivas" (ver la memoria de
// producto, rediseño de Container) — los controles de layout interno de un
// Container (Fila/Columna/Grilla/Justificar/Alinear/etc.) solo acomodan
// VARIOS hijos entre sí; con 0 o 1 hijo no hay nada que acomodar, y nada
// en el drawer avisaba de eso (bug de UX real reportado por el usuario:
// "no le veo funcionamiento" probando con un Container de un solo hijo,
// aunque las clases CSS SÍ se aplicaban correctamente). Un mensaje único
// arriba del grupo de controles "avanzados" (nunca deshabilitar los
// controles — el usuario puede estar preparando el Container para
// agregar un segundo hijo después, no le sirve perder acceso a ellos).
function AVISOS_CANTIDAD_HIJOS(cantidadHijos) {
  if (0 === cantidadHijos) {
    return "Este Container está vacío — agregá al menos 2 bloques adentro para ver el efecto de estos controles.";
  }
  if (1 === cantidadHijos) {
    return "Con un solo bloque adentro no vas a notar cambios acá — estos controles se ven cuando hay 2 o más.";
  }
  return null;
}

/**
 * CamposDesdeSchema — recorre TODO el schema (objeto {nombre: definicion},
 * orden de inserción de PHP preservado por json_decode/fetch) y renderiza un
 * CampoDesdeSchema por entrada — lo que DrawerEstilo.jsx monta entero para
 * nivel="bloque" en vez del bloque de JSX fijo que tenía antes.
 *
 * Progresividad (anexo del plan "50 primitivas", ver la memoria de
 * producto): una entrada con `definicion.avanzado === true` (hoy solo
 * Container: display/direccion/envolver/justificar/alinear/
 * columnas_grilla/gap) se agrupa DETRÁS de un botón "Personalizar" en vez
 * de mostrarse siempre — mismo patrón ya validado en CampoTokenVisual.jsx
 * (básico + "Avanzado (elegir cualquier token)"). Las entradas básicas
 * (sin `avanzado`, ej. "Variante") siguen mostrándose siempre. El toggle
 * nunca excluye lo básico — "Personalizar" solo REVELA los controles
 * sueltos, que un usuario puede seguir ajustando individualmente
 * encima de lo que la variante ya definió (mismo mecanismo de PHP,
 * sin cambios).
 *
 * `cantidadHijos` (solo pasada por DrawerEstilo.jsx para Container, ver
 * ahí): si viene definida, el aviso de AVISOS_CANTIDAD_HIJOS se muestra
 * UNA sola vez arriba del grupo de avanzados — nunca repetido por cada
 * uno de los 7 controles, que sería ruido.
 */
export function CamposDesdeSchema({ schema, estilo, actualizar, restUrl, nonce, cantidadHijos }) {
  const [personalizarAbierto, setPersonalizarAbierto] = useState(false);

  const entradas = Object.entries(schema);
  const basicas = entradas.filter(([, definicion]) => !definicion.avanzado);
  const avanzadas = entradas.filter(([, definicion]) => definicion.avanzado);

  function renderCampo([nombre, definicion]) {
    return (
      <CampoDesdeSchema
        key={nombre}
        nombre={nombre}
        definicion={definicion}
        valor={estilo[nombre]}
        onCambiar={(valorNuevo) => actualizar({ [nombre]: valorNuevo })}
        restUrl={restUrl}
        nonce={nonce}
      />
    );
  }

  if (avanzadas.length === 0) {
    // Ningún Componente aparte de Container declara "avanzado" hoy — sin
    // grupo que ocultar, se muestra el schema entero tal cual (mismo
    // comportamiento de siempre para el resto del catálogo).
    return basicas.map(renderCampo);
  }

  const aviso = cantidadHijos !== undefined ? AVISOS_CANTIDAD_HIJOS(cantidadHijos) : null;

  return (
    <>
      {basicas.map(renderCampo)}
      <button
        type="button"
        className="sofia-drawer-estilo__personalizar"
        onClick={() => setPersonalizarAbierto((abierto) => !abierto)}
      >
        {personalizarAbierto ? "Ocultar personalización" : "Personalizar"}
      </button>
      {personalizarAbierto && (
        <div className="sofia-drawer-estilo__avanzados">
          {aviso && <p className="sofia-drawer-estilo__aviso-contexto">{aviso}</p>}
          {avanzadas.map(renderCampo)}
        </div>
      )}
    </>
  );
}

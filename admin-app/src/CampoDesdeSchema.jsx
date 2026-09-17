import { useState, useEffect, useRef } from "preact/hooks";
import { CampoTokenVisual } from "./CampoTokenVisual.jsx";

// Un cambio de texto se guarda con PUT sofia/v1/paginas/{slug}/campo —
// mandar uno por tecla saturaría la red y pisaría respuestas entre sí. El
// input se mantiene en estado local (para que el cursor no salte) y el
// guardado sale recién tras esta pausa sin escribir. Mismo criterio que
// programarGuardado() en App.jsx, que ya aplica este retraso a la edición
// in-place del canvas.
const RETRASO_ESCRITURA_MS = 500;

function CampoTexto({ valor, onCambiar, multilinea, placeholder }) {
  const [local, setLocal] = useState(valor || "");
  const temporizador = useRef(null);
  // Última propuesta local: distingue "el valor de afuera cambió de verdad"
  // de "volvió mi propio cambio tras guardarse". Sin esto, el eco del
  // guardado reescribe el input mientras se sigue tecleando.
  const propio = useRef(valor || "");

  useEffect(() => {
    const externo = valor || "";
    if (externo !== propio.current) {
      propio.current = externo;
      setLocal(externo);
    }
  }, [valor]);

  useEffect(() => () => clearTimeout(temporizador.current), []);

  function alEscribir(evento) {
    const texto = evento.currentTarget.value;
    setLocal(texto);
    propio.current = texto;
    clearTimeout(temporizador.current);
    temporizador.current = setTimeout(() => onCambiar(texto), RETRASO_ESCRITURA_MS);
  }

  // blur guarda de inmediato: si el usuario cierra el panel o clickea otro
  // bloque antes de que venza la pausa, el cambio no se pierde.
  function alSalir() {
    clearTimeout(temporizador.current);
    if ((valor || "") !== local) onCambiar(local);
  }

  const Etiqueta = multilinea ? "textarea" : "input";
  return (
    <Etiqueta
      className="sofia-drawer-estilo__texto"
      type={multilinea ? undefined : "text"}
      rows={multilinea ? 4 : undefined}
      value={local}
      placeholder={placeholder}
      onInput={alEscribir}
      onBlur={alSalir}
    />
  );
}

/**
 * CampoNumero — un valor numérico dentro de un rango, con deslizador.
 *
 * El deslizador es el control honesto para un rango cerrado: comunica el
 * mínimo y el máximo sin explicarlos, y no deja escribir un valor
 * inválido. El número al lado queda para cuando se quiere precisión.
 */
function CampoNumero({ valor, onCambiar, min = 0, max = 100 }) {
  const actual = Math.max(min, Math.min(max, parseInt(valor, 10) || 0));
  return (
    <div className="sofia-drawer-estilo__numero">
      <input
        type="range"
        min={min}
        max={max}
        value={actual}
        onInput={(evento) => onCambiar(String(evento.currentTarget.value))}
      />
      <span className="sofia-drawer-estilo__numero-valor">{actual}</span>
    </div>
  );
}

/**
 * CampoImagen — abre el selector de medios de WordPress, el mismo que ya
 * usa el click sobre una <img> del canvas (activarImagen en
 * editor-iframe.js). Acá corre en la página de admin, así que depende de
 * wp_enqueue_media() en class-panel-editor.php.
 *
 * Muestra la miniatura de lo elegido en vez de la URL cruda: para decidir
 * si es la imagen correcta, verla vale más que leer su ruta.
 */
function CampoImagen({ valor, onCambiar }) {
  function elegir() {
    if (typeof wp === "undefined" || !wp.media) return;
    const selector = wp.media({
      title: "Elegir imagen",
      button: { text: "Usar esta imagen" },
      multiple: false,
    });
    selector.on("select", () => {
      const adjunto = selector.state().get("selection").first().toJSON();
      onCambiar(adjunto.url);
    });
    selector.open();
  }

  return (
    <div className="sofia-drawer-estilo__imagen">
      {valor ? (
        <button type="button" className="sofia-drawer-estilo__imagen-preview" onClick={elegir}>
          <img src={valor} alt="" />
          <span>Cambiar</span>
        </button>
      ) : (
        <button type="button" className="sofia-drawer-estilo__imagen-vacia" onClick={elegir}>
          Elegir imagen
        </button>
      )}
      {valor && (
        <button type="button" className="sofia-drawer-estilo__imagen-quitar" onClick={() => onCambiar("")}>
          Quitar
        </button>
      )}
    </div>
  );
}

/**
 * CampoIcono — grilla visual del set de íconos del sistema
 * (Sofia_Componente::ICONOS_PERMITIDOS, expuesto vía GET sofia/v1/iconos).
 * Reemplaza al input de texto donde había que escribir el nombre exacto
 * del ícono de memoria.
 */
function CampoIcono({ valor, onCambiar, restUrl, nonce }) {
  const [iconos, setIconos] = useState(null);

  useEffect(() => {
    if (!restUrl) return;
    let cancelado = false;
    fetch(`${restUrl}iconos`, { headers: { "X-WP-Nonce": nonce } })
      .then((resp) => (resp.ok ? resp.json() : null))
      .then((datos) => {
        if (!cancelado) setIconos(datos && datos.iconos ? datos.iconos : null);
      })
      .catch(() => {
        if (!cancelado) setIconos(null);
      });
    return () => {
      cancelado = true;
    };
  }, [restUrl, nonce]);

  // Sin catálogo (fetch fallido) cae a texto libre: es peor control, pero
  // deja editar el campo igual en vez de dejarlo inaccesible.
  if (!iconos) {
    return <CampoTexto valor={valor} onCambiar={onCambiar} placeholder="Nombre del ícono" />;
  }

  return (
    <div className="sofia-drawer-estilo__iconos">
      {Object.entries(iconos).map(([nombre, paths]) => (
        <button
          key={nombre}
          type="button"
          title={nombre}
          className={`sofia-drawer-estilo__icono ${valor === nombre ? "sofia-drawer-estilo__icono--activo" : ""}`}
          onClick={() => onCambiar(nombre)}
        >
          {/* El endpoint manda el contenido INTERNO del svg (los <path>),
              no el svg armado — el wrapper lo pone quien dibuja, porque
              cada contexto lo quiere de otro tamaño. */}
          <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
            dangerouslySetInnerHTML={{ __html: paths }}
          />
        </button>
      ))}
    </div>
  );
}

/**
 * CampoDesdeSchema — UN control renderizado a partir de una entrada de
 * schema del lado PHP. Sirve a los dos schemas que declara un Componente:
 * el de ESTILO (schema_bloque_generico() + schema_propio(), vía
 * GET catalogo-bloques/{tipo}/schema) y el de CONTENIDO
 * (schema_contenido(), vía .../schema-contenido).
 *
 * PHP es la única fuente de verdad de qué controles existen; este
 * componente solo sabe traducir cada TIPO de entrada a su control real.
 *
 * Tipos de ESTILO:
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
 *
 * Tipos de CONTENIDO:
 * - "texto" / "url": input con debounce (ver CampoTexto).
 * - "texto_largo": textarea, mismo debounce.
 * - "imagen": selector de medios de WordPress con miniatura.
 * - "icono": grilla visual del set de íconos del sistema.
 * - "numero": deslizador con rango (min/max del propio schema).
 * - "lista": no se edita acá — los items de una lista repetible se
 *   agregan, reordenan y eliminan desde el árbol de Estructura y el
 *   canvas, que ya resuelven ese gesto mejor que un control de panel.
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

      {/* Tipos de CONTENIDO (schema_contenido() del lado PHP). Hasta este
          cambio ningún frontend los consumía: los 29 Componentes declaraban
          sus campos y esa información solo la usaba la validación de la IA,
          así que 9 campos "url" no se podían editar desde ningún lado y
          Embed —cuyo único campo es la URL— era inusable a mano. */}
      {(tipo === "texto" || tipo === "url") && (
        <CampoTexto
          valor={valor}
          onCambiar={onCambiar}
          placeholder={tipo === "url" ? "https://…" : undefined}
        />
      )}

      {tipo === "texto_largo" && <CampoTexto valor={valor} onCambiar={onCambiar} multilinea />}

      {tipo === "imagen" && <CampoImagen valor={valor} onCambiar={onCambiar} />}

      {tipo === "icono" && (
        <CampoIcono valor={valor} onCambiar={onCambiar} restUrl={restUrl} nonce={nonce} />
      )}

      {tipo === "numero" && (
        <CampoNumero valor={valor} onCambiar={onCambiar} min={definicion.min} max={definicion.max} />
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
export function CamposDesdeSchema({
  schema,
  estilo,
  actualizar,
  seccionesAbiertas,
  onAlternarSeccion,
  restUrl,
  nonce,
  cantidadHijos,
}) {
  const [personalizarAbierto, setPersonalizarAbierto] = useState(false);

  const entradas = Object.entries(schema);
  // capa_apariencia dice de qué CAPA es cada prop (lo propio del tipo de
  // bloque vs. lo genérico transversal); requiere_render dice CÓMO se
  // aplica. Son preguntas distintas: spacer.alto es apariencia pero el
  // iframe puede aplicarlo como CSS, sin pedir el bloque de nuevo. Las
  // dos marcas las pone schema_de() del lado PHP, así el panel no
  // mantiene su propia lista de qué prop es de quién.
  const apariencia = entradas.filter(([, d]) => d.capa_apariencia === true);
  const caja = entradas.filter(([, d]) => d.capa_apariencia !== true);

  function renderCampo([nombre, definicion]) {
    return (
      <CampoDesdeSchema
        key={nombre}
        nombre={nombre}
        definicion={definicion}
        valor={estilo[nombre]}
        onCambiar={(valorNuevo) => actualizar({ [nombre]: valorNuevo }, definicion.requiere_render === true)}
        restUrl={restUrl}
        nonce={nonce}
      />
    );
  }

  // Cuántos valores hay puestos en un grupo — se muestra en el
  // encabezado cuando la sección está cerrada, para que algo
  // personalizado ahí dentro no quede invisible.
  function conValor(grupo) {
    return grupo.filter(([nombre]) => estilo[nombre]).length;
  }

  const basicas = apariencia.filter(([, d]) => !d.avanzado);
  const avanzadas = apariencia.filter(([, d]) => d.avanzado);
  const aviso = cantidadHijos !== undefined ? AVISOS_CANTIDAD_HIJOS(cantidadHijos) : null;

  return (
    <>
      {apariencia.length > 0 && (
        <SeccionPanel
          titulo="Apariencia"
          abierta={seccionesAbiertas?.apariencia ?? true}
          onAlternar={() => onAlternarSeccion?.("apariencia")}
          cantidad={conValor(apariencia)}
        >
          {basicas.map(renderCampo)}
          {avanzadas.length > 0 && (
            <>
              {/* "Personalizar" sigue existiendo DENTRO de Apariencia y
                  no se reemplaza por la sección: cumple otra función —
                  revelar los controles sueltos que la variante ya definió
                  como punto de partida (hoy solo Container). */}
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
          )}
        </SeccionPanel>
      )}

      {caja.length > 0 && (
        <SeccionPanel
          titulo="Caja y posición"
          abierta={seccionesAbiertas?.caja ?? false}
          onAlternar={() => onAlternarSeccion?.("caja")}
          cantidad={conValor(caja)}
        >
          {caja.map(renderCampo)}
        </SeccionPanel>
      )}
    </>
  );
}

/**
 * CamposContenido — la sección "Contenido" del panel de bloque: un control
 * por cada campo que el Componente declara en schema_contenido() del lado
 * PHP.
 *
 * Es la pieza que faltaba para que ese schema sirviera de algo en el
 * editor. Los 29 Componentes ya lo declaraban, pero ningún frontend lo
 * pedía: la información existía y solo la usaba la validación del
 * generador por IA. Como consecuencia, 9 campos "url" (el enlace de
 * Button, Link, IconButton, CTA, Card, y el de cada item de Nav y
 * Breadcrumb) no se podían editar desde ningún lado, y Embed —cuyo único
 * campo ES la URL— era un bloque inusable a mano.
 *
 * Las listas repetibles se omiten a propósito (ver el comentario de tipos
 * en CampoDesdeSchema): sus items ya se editan en el canvas y se
 * reordenan desde el árbol de Estructura.
 */
export function CamposContenido({ schema, contenido, onCambiarCampo, restUrl, nonce }) {
  const entradas = Object.entries(schema || {}).filter(([, definicion]) => definicion.tipo !== "lista");
  if (entradas.length === 0) return null;

  // Devuelve el array directo, sin envolver en un fragmento: mismo
  // criterio que CamposDesdeSchema. Envolverlo fue un bug real — la
  // sección aparecía con su encabezado pero sin ningún campo adentro.
  return entradas.map(([nombre, definicion]) => (
    <CampoDesdeSchema
      key={nombre}
      nombre={nombre}
      definicion={definicion}
      valor={contenido[nombre]}
      onCambiar={(valorNuevo) => onCambiarCampo(nombre, valorNuevo)}
      restUrl={restUrl}
      nonce={nonce}
    />
  ));
}

/**
 * SeccionPanel — un grupo colapsable del panel de bloque ("Contenido",
 * "Apariencia", "Caja y posición").
 *
 * Colapsable y no una lista corrida porque el panel mezcla tres cosas de
 * naturaleza distinta, y la que más se toca —lo propio del bloque— queda
 * sepultada bajo controles genéricos que casi nunca se cambian. Plegadas,
 * las tres caben en una pantalla y se ve de un vistazo qué ofrece el
 * bloque.
 *
 * `cantidad` marca cuántos valores hay puestos en el grupo, para que algo
 * personalizado dentro de una sección cerrada no quede invisible.
 */
export function SeccionPanel({ titulo, abierta, onAlternar, cantidad, children }) {
  return (
    <div className="sofia-panel-seccion">
      <button
        type="button"
        className={`sofia-panel-seccion__titulo ${abierta ? "sofia-panel-seccion__titulo--abierta" : ""}`}
        onClick={onAlternar}
        aria-expanded={abierta}
      >
        <span className="sofia-panel-seccion__flecha" aria-hidden="true">
          {abierta ? "⌄" : "›"}
        </span>
        {titulo}
        {!abierta && cantidad > 0 && <span className="sofia-panel-seccion__cuenta">{cantidad}</span>}
      </button>
      {abierta && <div className="sofia-panel-seccion__cuerpo">{children}</div>}
    </div>
  );
}

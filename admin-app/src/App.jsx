import { useEffect, useRef, useState } from "preact/hooks";
import { DrawerEstilo } from "./DrawerEstilo.jsx";
import { ResaltadoBloque } from "./ResaltadoBloque.jsx";
import { MenuAgregarBloque } from "./MenuAgregarBloque.jsx";
import { MenuContextualBloque } from "./MenuContextualBloque.jsx";
import { PanelEstiloGlobal } from "./PanelEstiloGlobal.jsx";
import { PanelGenerarIA } from "./PanelGenerarIA.jsx";
import { ListaVariablesCoreFramework } from "./CampoConToken.jsx";

// debounce simple: junta ediciones rápidas del mismo campo (ej. varias
// pulsaciones de blur/focus seguidas) antes de llamar al proxy REST — el
// iframe ya solo notifica en "blur", así que esto es una segunda capa de
// seguridad, no el mecanismo principal de limitar llamadas.
const RETRASO_GUARDADO_MS = 400;

// insertarEnArbol/todosLosIds (Nivel 3, "primitivas de layout"): mismo
// principio recursivo que Sofia_Pagina::resolver_bloques() del lado PHP
// (ver class-pagina.php) aplicado acá para MUTAR el árbol {id,tipo,hijos}
// en vez de solo leerlo — agregarBloque() ya no puede asumir un array
// plano de nivel superior (slice/spread directo) desde que un bloque
// puede insertarse DENTRO de un Container. Funciones puras module-level
// (no dependen de nada de App) porque no necesitan ningún estado del
// componente, mismo criterio que cualquier otro helper de este archivo.

// insertarEnArbol: devuelve una COPIA de $estructura con $bloqueNuevo
// insertado en $posicion — a nivel superior si $containerId es
// null/undefined, o dentro de "hijos" del nodo cuyo id === $containerId
// (buscado recursivamente, un container puede estar anidado a cualquier
// profundidad). Nunca muta el array/objetos originales (spread en cada
// nivel) — mismo criterio de inmutabilidad que ya usaba el código plano
// anterior (estructuraActual.slice/spread), necesario para que Preact
// detecte el cambio si este array llegara a guardarse en estado (hoy no,
// pero evita una trampa futura).
function insertarEnArbol(estructura, bloqueNuevo, posicion, containerId) {
  if (!containerId) {
    return [...estructura.slice(0, posicion), bloqueNuevo, ...estructura.slice(posicion)];
  }
  return estructura.map((bloque) => {
    if (bloque.id === containerId) {
      const hijos = bloque.hijos || [];
      return { ...bloque, hijos: [...hijos.slice(0, posicion), bloqueNuevo, ...hijos.slice(posicion)] };
    }
    if (bloque.hijos && bloque.hijos.length) {
      return { ...bloque, hijos: insertarEnArbol(bloque.hijos, bloqueNuevo, posicion, containerId) };
    }
    return bloque;
  });
}

// todosLosIds: junta el id de CADA bloque de $estructura, en cualquier
// profundidad — usado para el diff "cuál id es nuevo" (ver agregarBloque):
// un bloque agregado DENTRO de un container solo aparece en $hijos de ese
// container, nunca en la lista plana de nivel superior, así que
// estructura.map(b => b.id) (el cálculo plano de antes de esta fase) NUNCA
// lo hubiera encontrado como "nuevo" — el flujo de agregar-vía-drawer se
// rompía en silencio para ese caso específico (el guardado de estructura
// sí funcionaba, pero el HTML del bloque nunca llegaba a insertarse en el
// iframe).
function todosLosIds(estructura) {
  const ids = [];
  for (const bloque of estructura) {
    if (bloque.id) ids.push(bloque.id);
    if (bloque.hijos && bloque.hijos.length) ids.push(...todosLosIds(bloque.hijos));
  }
  return ids;
}

/**
 * App es el panel completo del editor in-place, con layout de 3 zonas
 * fijas (paso 1 de la migración, ver la memoria de producto — el resto de
 * zonas, catálogo arrastrable y chat de IA in-context, quedan para pasos
 * siguientes): el sitio (iframe, ver Sofia_Panel_Editor::ocultar_chrome_admin
 * en el tema) al centro, y la zona de Propiedades (DrawerEstilo.jsx)
 * SIEMPRE montada a la derecha — nunca un panel flotante que hay que abrir
 * ni cerrar, un click en cualquier campo/bloque simplemente cambia qué
 * muestra. Todo control de un campo (negrita/cursiva, alineación, color)
 * vive ahí, un solo lugar, en vez de una barra de formato aparte más un
 * drawer flotante.
 *
 * El iframe nunca guarda nada por su cuenta (ver inc/js/editor-iframe.js)
 * — solo informa cambios vía postMessage, que este componente escucha y
 * persiste llamando al proxy REST de WordPress (mismo origen, sin CORS —
 * ver inc/class-rest-editor.php).
 */
export function App({ config }) {
  const iframeRef = useRef(null);
  const timersPorCampo = useRef({});
  // Debounce del guardado de condición de Visibilidad — bug real
  // encontrado en la práctica: cada cambio en el formulario (agregar
  // regla, elegir variable/operador/valor) disparaba un guardado
  // INMEDIATO, y cada guardado exitoso hacía su propio
  // iframe.reload() — 2+ cambios seguidos en poco tiempo (ej. "+ Regla"
  // más elegir su valor) encadenaban 2 reloads, el segundo interrumpiendo
  // al primero A MITAD DE CARGA. Muuri se inicializa en DOMContentLoaded;
  // un reload interrumpido dejaba el DOM a medio construir, y cualquier
  // evento de Muuri disparado sobre ese estado parcial reportaba una
  // lista de items corrupta (items "perdidos"). Mismo mecanismo de
  // debounce que timersPorCampo, pero con su propio timer: solo el
  // ÚLTIMO cambio de la ráfaga dispara guardado + reload.
  const timerCondicion = useRef(null);
  const [estado, setEstado] = useState("listo"); // "listo" | "guardando" | "guardado" | "error"
  // Campo+estilo mostrado en la zona de Propiedades fija (paso 1 del
  // rediseño de layout, ver la memoria de producto) — { campo, estilo,
  // nivel } | null. nivel "campo" (default) usa `campo` como identificador
  // ("{id}.subcampo..."), nivel "bloque" usa `campo` como el ID DIRECTO
  // del bloque (mismo shape, distinto significado — más simple que 2
  // estados separados que solo uno puede estar activo a la vez). Un solo
  // click en CUALQUIER campo actualiza esto directo (ver
  // "sofia:campo-clickeado" más abajo) — ya no hace falta el paso
  // intermedio del botón ✏️ flotante (BotonEditarCampo.jsx, eliminado):
  // con el panel siempre visible, "click en un campo" y "mostrar su
  // estilo" son el mismo gesto.
  const [drawerEstilo, setDrawerEstilo] = useState(null);
  const [bloqueResaltado, setBloqueResaltado] = useState(null);
  const [catalogoBloques, setCatalogoBloques] = useState([]);
  const [menuAgregarAbierto, setMenuAgregarAbierto] = useState(false);
  // Panel de Estilo Global (Nivel 3) — configuración del SITIO completo
  // (paleta/tipografía), sin relación con ningún campo/bloque seleccionado
  // — vive detrás de su propio botón en la barra superior, ver
  // PanelEstiloGlobal.jsx.
  const [panelGlobalAbierto, setPanelGlobalAbierto] = useState(false);
  // Panel "Generar con IA" (Fase 4, ver PanelGenerarIA.jsx) — mismo
  // criterio que panelGlobalAbierto: un booleano simple, sin relación con
  // ningún campo/bloque seleccionado del iframe.
  const [panelIAAbierto, setPanelIAAbierto] = useState(false);
  // Menú contextual de un bloque (click derecho, ver
  // alHacerClickDerecho en editor-iframe.js) — reemplaza un primer intento
  // con un botón "✕" flotante en el overlay de resaltado, que tenía un bug
  // real de carrera de eventos: mover el mouse HACIA el botón (que vive en
  // ESTE documento, superpuesto sobre el iframe) sacaba el cursor del
  // iframe, disparando su propio mouseleave y apagando el resaltado (y con
  // él el botón) antes de que el click llegara a procesarse. Un solo
  // evento "contextmenu" dentro del iframe no compite con
  // mouseover/mouseleave, así que no tiene ese problema.
  const [menuContextual, setMenuContextual] = useState(null); // { indice, item, x, y } | null
  // Menú "agregar bloque" abierto desde una LÍNEA de inserción entre dos
  // bloques (click en el "+" dentro del iframe, ver
  // activarLineasInsertar() en editor-iframe.js) — distinto del botón fijo
  // de la barra superior (menuAgregarAbierto, siempre agrega al final):
  // este guarda EN QUÉ POSICIÓN insertar y las coordenadas donde dibujar
  // el menú, igual que menuContextual.
  const [menuAgregarEnPosicion, setMenuAgregarEnPosicion] = useState(null); // { posicion, x, y } | null
  // Nombres reales leídos del CSS de Core Framework, YA AGRUPADOS por
  // categoría (ver Sofia_Estilo_Global::variables_core_framework_por_
  // categoria()) — {color:[...], texto:[...], radius:[...], shadow:[...],
  // space:[...], otras:[...]}. Vive ACÁ, no dentro de PanelEstiloGlobal,
  // porque el botón "CF" (CampoConToken) también aparece en
  // DrawerEstilo.jsx (Nivel 1/2), que puede abrirse sin que el panel
  // global esté montado — los <datalist> que alimenta necesitan existir en
  // el documento sin importar cuál de los 2 esté abierto. Agrupado (no la
  // lista plana de antes) para que cada CampoConToken sugiera solo la
  // categoría relevante a SU propiedad — bug real reportado por el
  // usuario: el datalist plano permitía escribir "bg-body" (una variable
  // de color) como sugerencia válida en "Radio de borde".
  const [variablesCoreFramework, setVariablesCoreFramework] = useState({
    color: [],
    texto: [],
    radius: [],
    shadow: [],
    space: [],
    otras: [],
  });

  // Catálogo real de Componentes del tema (mismo endpoint que ya usa
  // editor-iframe.js para los nombres del overlay de resaltado) — se pide
  // una sola vez al montar el panel, nunca una lista curada aparte que
  // pueda desincronizarse.
  useEffect(() => {
    fetch(`${config.restUrl}catalogo-bloques`, { headers: { "X-WP-Nonce": config.nonce } })
      .then((resp) => (resp.ok ? resp.json() : []))
      .then(setCatalogoBloques)
      .catch(() => setCatalogoBloques([]));

    fetch(`${config.restUrl}core-framework/variables`, { headers: { "X-WP-Nonce": config.nonce } })
      .then((resp) => (resp.ok ? resp.json() : {}))
      .then((agrupadas) =>
        setVariablesCoreFramework({
          color: agrupadas.color || [],
          texto: agrupadas.texto || [],
          radius: agrupadas.radius || [],
          shadow: agrupadas.shadow || [],
          space: agrupadas.space || [],
          otras: agrupadas.otras || [],
        })
      )
      .catch(() => {});
  }, []);

  useEffect(() => {
    function alRecibirMensaje(evento) {
      const datos = evento.data;
      if (!datos || typeof datos !== "object") return;

      if (datos.tipo === "sofia:campo-editado") {
        programarGuardado(datos.campo, datos.valor);
        return;
      }
      if (datos.tipo === "sofia:campo-clickeado") {
        // Actualiza la zona de Propiedades directo — un click en CUALQUIER
        // campo (nivel "campo", el default) muestra su estilo ahí, sin
        // paso intermedio. Si el panel estaba mostrando un BLOQUE (nivel
        // "bloque", abierto desde el menú contextual), este click lo pisa
        // igual — mismo criterio que "el panel siempre refleja la última
        // selección" acordado para todo este rediseño.
        setDrawerEstilo({ campo: datos.campo, estilo: datos.estilo });
        return;
      }
      if (datos.tipo === "sofia:bloque-resaltado") {
        setBloqueResaltado({ rect: datos.rect, nombre: datos.nombre, indice: datos.indice });
        return;
      }
      if (datos.tipo === "sofia:bloque-sin-resaltar") {
        setBloqueResaltado(null);
        return;
      }
      if (datos.tipo === "sofia:bloque-clickeado") {
        // Paso 1 del rediseño de layout a 3 zonas fijas (ver la memoria
        // de producto): un click SIMPLE (no click derecho) sobre un
        // bloque ya muestra su Nivel 2 en la zona de Propiedades — mismo
        // camino que "Estilo del bloque" del menú contextual (mismo
        // fetch de la condición de Visibilidad incluido, ver
        // mostrarEstiloDeBloque más abajo), así la pestaña Visibilidad
        // queda igual de disponible desde ambos caminos.
        mostrarEstiloDeBloque(datos.id, datos.tipoBloque, datos.estiloBloque);
        return;
      }
      if (datos.tipo === "sofia:menu-contextual-bloque") {
        setMenuContextual({
          indice: datos.indice,
          // containerId (Nivel 3, "primitivas de layout"): id del
          // .sofia-container que contiene a este bloque, o null si es de
          // nivel superior — ver contenedorGridDe() en editor-iframe.js.
          // Ya no se usa para eliminar (eliminarBloque() pasó a
          // identificar por `id`, ver más abajo), pero sigue viajando por
          // si un comando futuro del menú (clonar, mover) lo necesita.
          containerId: datos.containerId,
          id: datos.id,
          tipoBloque: datos.tipoBloque,
          estiloBloque: datos.estiloBloque,
          item: datos.item,
          x: datos.x,
          y: datos.y,
        });
        return;
      }
      if (datos.tipo === "sofia:abrir-insertar-bloque") {
        // containerId (Nivel 3): null/ausente = nivel superior, mismo
        // contrato que agregarBloque()/alInsertarBloqueHTML() en
        // editor-iframe.js — ver el comentario largo ahí.
        setMenuAgregarEnPosicion({ posicion: datos.posicion, containerId: datos.containerId || null, x: datos.x, y: datos.y });
        return;
      }
      if (datos.tipo === "sofia:estructura-reordenada") {
        guardarEstructura(datos.bloques);
      }
    }

    window.addEventListener("message", alRecibirMensaje);
    return () => window.removeEventListener("message", alRecibirMensaje);
  }, []);

  function programarGuardado(campo, valor) {
    clearTimeout(timersPorCampo.current[campo]);
    timersPorCampo.current[campo] = setTimeout(() => guardarCampo(campo, valor), RETRASO_GUARDADO_MS);
  }

  async function guardarCampo(campo, valor) {
    setEstado("guardando");
    try {
      const respuesta = await fetch(`${config.restUrl}paginas/${config.slug}/campo`, {
        method: "PUT",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": config.nonce,
        },
        body: JSON.stringify({ campo, valor }),
      });
      setEstado(respuesta.ok ? "guardado" : "error");
    } catch {
      setEstado("error");
    }
  }

  // Nivel 2 — reordenar bloques: el iframe ya movió los nodos reales
  // (SortableJS corre ADENTRO del documento del iframe, ver
  // activarReordenar() en editor-iframe.js) y solo informa el resultado
  // final: {id, tipo} de cada bloque en el nuevo orden, leído directo de
  // sus atributos data-sofia-bloque-id/-tipo (ver
  // Sofia_Componente::atributos_seccion()) — el ID YA EXISTENTE de cada
  // bloque viaja tal cual, nunca se regenera acá; si se mandara sin ID, el
  // servidor le asignaría uno NUEVO en cada reordenamiento, perdiendo la
  // asociación con el contenido ya guardado. Este panel no reordena nada
  // por su cuenta, solo persiste ese orden ya decidido — sin debounce: a
  // diferencia de un campo de texto (que dispara en cada "blur"), un drag
  // termina en un único evento "onEnd".
  async function guardarEstructura(bloques) {
    setEstado("guardando");
    try {
      const respuesta = await fetch(`${config.restUrl}paginas/${config.slug}/estructura`, {
        method: "PUT",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": config.nonce,
        },
        body: JSON.stringify({ estructura: bloques }),
      });
      setEstado(respuesta.ok ? "guardado" : "error");
    } catch {
      setEstado("error");
    }
  }

  // aplicarArbolGeneradoPorIA (Fase 4, ver PanelGenerarIA.jsx): "Aplicar a
  // la página" del panel de IA — el árbol YA viene reparado/confirmado por
  // el usuario después de ver el preview. AGREGA el árbol generado al
  // FINAL de la estructura ya existente (nunca la reemplaza) — mismo
  // comportamiento que "+ Agregar bloque" manual (ver agregarBloque() más
  // abajo). Bug real corregido tras probar en vivo: la primera versión de
  // esta función reemplazaba la estructura completa, razonando que "el
  // usuario describe lo que quiere EN la página, no un bloque más" — en la
  // práctica eso significó que pedir "agregá una sección de beneficios"
  // borró TODO el contenido real de una página en producción (navbar,
  // Hero con imagen, todo) para dejar solo la sección nueva. Nadie espera
  // que "agregar" borre lo demás; recargar el iframe completo tras guardar
  // sigue siendo más simple que un diff fino, mismo criterio de alcance
  // acotado que el resto de esta fase.
  async function aplicarArbolGeneradoPorIA(arbolGenerado) {
    setEstado("guardando");
    try {
      // Bug real reportado por el usuario probando en vivo: esta función
      // ANTES mandaba arbolGenerado tal cual como la estructura COMPLETA
      // de la página — reemplazando (borrando) todo lo que ya hubiera
      // (Hero, navbar, contenido real de un sitio en producción) solo
      // porque el usuario pidió "agregá una sección de beneficios". Nadie
      // espera que "agregar" borre el resto — mismo comportamiento que
      // "+ Agregar bloque" manual (agregarBloque() más abajo), que
      // siempre suma sin tocar lo existente. Por eso acá también hace
      // falta leer la estructura ACTUAL primero (mismo patrón que
      // agregarBloque()) y concatenar, nunca reemplazar directo.
      const pagina = await fetch(`${config.restUrl}paginas/${config.slug}`, {
        headers: { "X-WP-Nonce": config.nonce },
      }).then((r) => r.json());
      const estructuraActual = Array.isArray(pagina.estructura) ? pagina.estructura : [];
      const estructuraFinal = [...estructuraActual, ...arbolGenerado];

      const respuesta = await fetch(`${config.restUrl}paginas/${config.slug}/estructura`, {
        method: "PUT",
        headers: { "Content-Type": "application/json", "X-WP-Nonce": config.nonce },
        body: JSON.stringify({ estructura: estructuraFinal }),
      });
      if (!respuesta.ok) {
        setEstado("error");
        throw new Error("No se pudo guardar la estructura generada por IA.");
      }
      setEstado("guardado");
      iframeRef.current?.contentWindow.location.reload();
    } catch (error) {
      setEstado("error");
      throw error;
    }
  }

  // El propio iframe ejecuta document.execCommand sobre su selección real
  // (ver alRecibirMensajeDelPadre en editor-iframe.js) — este panel nunca
  // toca el DOM del iframe directo, solo le pide que aplique el comando.
  function aplicarFormato(comando) {
    iframeRef.current?.contentWindow.postMessage({ tipo: "sofia:aplicar-formato", comando }, "*");
  }

  // Muestra el Nivel 2 de un bloque en la zona de Propiedades — llamado
  // tanto desde el menú contextual ("Estilo del bloque", click derecho)
  // como desde un click SIMPLE directo sobre el bloque (paso 1 del
  // rediseño de layout a 3 zonas fijas, ver la memoria de producto y
  // "sofia:bloque-clickeado" más abajo) — único punto de entrada tanto
  // para Estilo como para Visibilidad (el usuario cambia de pestaña
  // DENTRO del drawer). Único caller: el handler de "sofia:bloque-clickeado"
  // más abajo (click simple sobre un bloque, ver alHacerClickIzquierdo en
  // editor-iframe.js) — "Estilo del bloque" del menú contextual se
  // eliminó (ver MenuContextualBloque.jsx), el click simple lo reemplaza
  // por completo. La condición de Visibilidad NO se refleja en ningún
  // atributo del DOM inspeccionable (solo la marca visual de "oculto", no
  // las reglas en sí), así que hay que pedirle el contenido completo de
  // la página al proxy REST, mismo fetch que ya usa agregarBloque() para
  // leer la estructura actual.
  async function mostrarEstiloDeBloque(id, tipoBloque, estiloBloque) {
    if (!id) return;
    try {
      const pagina = await fetch(`${config.restUrl}paginas/${config.slug}`, {
        headers: { "X-WP-Nonce": config.nonce },
      }).then((r) => r.json());
      const reglas = pagina.contenido?.[`${id}._condicion_bloque`] || [];
      setDrawerEstilo({ campo: id, tipoBloque: tipoBloque || "", estilo: estiloBloque || {}, condicion: reglas, nivel: "bloque" });
    } catch {
      setEstado("error");
    }
  }

  // El iframe aplica el estilo al elemento/sección real Y notifica el
  // cambio para persistir (ver alAplicarEstilo/alAplicarEstiloBloque en
  // editor-iframe.js) — este panel nunca toca el DOM del iframe directo,
  // mismo patrón que aplicarFormato(). Actualiza el estado local del
  // drawer de inmediato (sin esperar el roundtrip del iframe) para que los
  // controles reflejen el cambio al instante. El nivel decide qué mensaje
  // mandar y qué significa "campo" en cada caso (clave de campo vs. ID de
  // bloque directo).
  function cambiarEstiloDrawer(estiloNuevo) {
    if (!drawerEstilo) return;
    setDrawerEstilo({ ...drawerEstilo, estilo: estiloNuevo });
    const tipoMensaje = drawerEstilo.nivel === "bloque" ? "sofia:aplicar-estilo-bloque" : "sofia:aplicar-estilo";
    const clave = drawerEstilo.nivel === "bloque" ? "id" : "campo";
    iframeRef.current?.contentWindow.postMessage(
      { tipo: tipoMensaje, [clave]: drawerEstilo.campo, estilo: estiloNuevo },
      "*"
    );
  }

  // Guarda la condición de Visibilidad — a diferencia del estilo (que pasa
  // por el iframe para reflejarse al instante en el DOM, ver
  // cambiarEstiloDrawer), la condición decide si el bloque se RENDERIZA o
  // no del lado de PHP (ver page.php/Sofia_Componente::bloque_visible()) —
  // no hay nada que el iframe pueda simular con CSS sin conocer el HTML
  // real. Antes recargaba la página ENTERA del iframe — mismo bug de UX
  // señalado por el usuario que agregarBloque(): pide el HTML actualizado
  // de ESE bloque puntual (GET .../bloque/{id}, mismo endpoint que
  // agregarBloque) y lo manda al iframe para REEMPLAZAR la <section>
  // existente (ver alReemplazarBloqueHTML en editor-iframe.js) — nunca
  // una inserción nueva, es el mismo bloque con su marca de "oculto"
  // actualizada.
  function cambiarCondicionDrawer(reglasNuevas) {
    if (!drawerEstilo) return;
    setDrawerEstilo({ ...drawerEstilo, condicion: reglasNuevas });
    setEstado("guardando");

    const idBloque = drawerEstilo.campo;
    clearTimeout(timerCondicion.current);
    timerCondicion.current = setTimeout(async () => {
      try {
        const respuesta = await fetch(`${config.restUrl}paginas/${config.slug}/campo`, {
          method: "PUT",
          headers: { "Content-Type": "application/json", "X-WP-Nonce": config.nonce },
          body: JSON.stringify({ campo: `${idBloque}._condicion_bloque`, valor: reglasNuevas }),
        });
        setEstado(respuesta.ok ? "guardado" : "error");
        if (!respuesta.ok || !iframeRef.current) return;

        const html = await fetch(`${config.restUrl}paginas/${config.slug}/bloque/${idBloque}`, {
          headers: { "X-WP-Nonce": config.nonce },
        }).then((r) => r.json());
        if (!html.ok) {
          iframeRef.current.contentWindow.location.reload();
          return;
        }
        iframeRef.current.contentWindow.postMessage(
          { tipo: "sofia:reemplazar-bloque-html", id: idBloque, html: html.html },
          "*"
        );
      } catch {
        setEstado("error");
      }
    }, RETRASO_GUARDADO_MS);
  }

  // Eliminar bloque: comando explícito desde el menú contextual, mismo
  // patrón que aplicarFormato — el iframe es quien de verdad quita la
  // sección del DOM (ver alEliminarBloque en editor-iframe.js) y reporta
  // la estructura resultante vía "sofia:estructura-reordenada", que ya
  // persiste arriba.
  //
  // Identifica por `id` (no por `indice`) desde Fase 3 — un índice plano
  // dejó de alcanzar en cuanto un bloque puede vivir DENTRO de un
  // container: "eliminar la posición 2" es ambiguo sin saber además en
  // qué grid (nivel superior o cuál container). El ID de instancia ya
  // identifica al bloque sin ambigüedad en TODO el resto del sistema
  // (mismo criterio que guardar_campo/guardar_estructura del lado PHP),
  // así que reusarlo acá evita sumar un segundo parámetro `containerId`
  // que solo duplicaría lo que el ID ya resuelve solo — ver el mismo
  // cambio de contrato en alEliminarBloque(), editor-iframe.js.
  // idExplicito: paso 1 del rediseño de layout a 3 zonas fijas (ver la
  // memoria de producto) — "Eliminar bloque" ahora también se dispara
  // desde el botón al pie de la zona de Propiedades (drawerEstilo?.campo,
  // nivel "bloque"), no solo desde el menú contextual de click derecho
  // (menuContextual?.id). Parámetro opcional en vez de 2 funciones
  // separadas: mismo mensaje al iframe, solo cambia de dónde sale el id.
  function eliminarBloque(idExplicito) {
    const id = idExplicito || menuContextual?.id;
    setMenuContextual(null);
    setBloqueResaltado(null);
    if (!id) return;
    // Si la zona de Propiedades estaba mostrando justo este bloque (el
    // caso más común: se elimina desde el botón al pie del propio
    // drawer), la limpiamos — si no, quedaría mostrando controles de
    // estilo para un bloque que ya no existe en el árbol.
    if (drawerEstilo?.nivel === "bloque" && drawerEstilo?.campo === id) {
      setDrawerEstilo(null);
    }
    iframeRef.current?.contentWindow.postMessage({ tipo: "sofia:eliminar-bloque", id }, "*");
  }

  // Seleccionar contenedor: reabre el menú contextual apuntando al
  // Container PADRE del bloque sobre el que se clickeó — ver el botón
  // "Seleccionar contenedor" en MenuContextualBloque.jsx (solo visible
  // cuando el bloque clickeado tiene containerId, es decir, vive DENTRO
  // de un Container) y alSeleccionarContenedorPadre en editor-iframe.js.
  // Reusa las MISMAS coordenadas x/y del menú actual — el usuario no movió
  // el mouse, solo cambió de opción dentro del mismo menú, así que el
  // reemplazo debe aparecer en el mismo lugar de la pantalla.
  function seleccionarContenedorPadre() {
    const containerId = menuContextual?.containerId;
    const x = menuContextual?.x;
    const y = menuContextual?.y;
    setMenuContextual(null);
    if (!containerId) return;
    iframeRef.current?.contentWindow.postMessage(
      { tipo: "sofia:seleccionar-contenedor-padre", containerId, x, y },
      "*"
    );
  }

  // Eliminar UN item de una lista repetible (ej. un "Beneficio" de la
  // Franja) — distinto de eliminarBloque: el bloque sigue existiendo,
  // solo se quita un item de su array. El iframe reconstruye el array
  // completo tras el borrado (ver alEliminarItemDeLista en
  // editor-iframe.js) y lo reporta como un "sofia:campo-editado" normal —
  // este panel no necesita un guardarEstructura especial para esto.
  function eliminarItemDeLista() {
    const item = menuContextual?.item;
    setMenuContextual(null);
    setBloqueResaltado(null);
    if (!item) return;
    iframeRef.current?.contentWindow.postMessage(
      { tipo: "sofia:eliminar-item-lista", campoLista: item.campoLista, indiceItem: item.indiceItem },
      "*"
    );
  }

  // Agregar bloque: a diferencia de reordenar/eliminar, un bloque NUEVO
  // necesita HTML real emitido por el Componente PHP correspondiente — el
  // iframe no puede "inventarlo" en JS sin duplicar el render (el tema es
  // deliberadamente "tonto", todo el HTML sale de Sofia_Componente::render()
  // del lado PHP). Antes esto recargaba la página ENTERA del iframe — bug
  // de UX real señalado por el usuario ("es una experiencia algo molesta"):
  // perdía scroll/estado por un cambio que solo agrega UNA sección. Ahora
  // pide el HTML de SOLO ese bloque (GET .../bloque/{id}, ver
  // Sofia_REST_Editor::obtener_html_de_bloque()) y lo manda al iframe para
  // insertarlo vía Muuri.add() (ver alInsertarBloqueHTML en
  // editor-iframe.js) — sigue siendo PHP la única fuente de HTML real,
  // cambia solo CUÁNDO se pide: un fragmento puntual, no la página entera.
  //
  // Un bloque nuevo no trae "id" hasta que GoPress se lo asigna al guardar
  // la estructura (ver store.GenerarIDBloque/rellenarIDsFaltantes, lado
  // Go) — hay que releer la página y comparar contra la estructura ANTERIOR
  // para identificar cuál id es el nuevo (el que no estaba antes). Desde
  // Fase 3, esa comparación es recursiva (ver todosLosIds arriba): el id
  // nuevo puede aparecer DENTRO de "hijos" de un container, no solo a
  // nivel superior.
  //
  // $posicion: índice donde insertar DENTRO del contenedor destino (0 =
  // antes de todos, length de ese contenedor = al final) — undefined/null
  // preserva el comportamiento original ("+ Agregar bloque" de la barra
  // superior, siempre al final DE NIVEL SUPERIOR). Con posicion explícita,
  // el mismo mecanismo sirve para "insertar ENTRE dos bloques" (líneas
  // dentro del iframe, ver activarLineasInsertar() en editor-iframe.js) —
  // pedido explícito del usuario tras ver el mockup original, que ya tenía
  // este patrón (.insertar-linea entre cada par de bloques).
  //
  // $containerId (nuevo en Fase 3): id del .sofia-container destino, o
  // null/undefined para nivel superior — mismo contrato que agrega
  // editor-iframe.js al mensaje "sofia:abrir-insertar-bloque" (ver el
  // handler de ese mensaje arriba, que lo guarda en
  // menuAgregarEnPosicion.containerId). Cuando hay containerId, la
  // construcción de estructuraNueva YA NO puede ser un slice/spread plano
  // de nivel superior (ver insertarEnArbol arriba) — necesita navegar el
  // árbol hasta encontrar el nodo container correcto, en cualquier
  // profundidad.
  async function agregarBloque(tipo, posicion, containerId) {
    setMenuAgregarAbierto(false);
    setMenuAgregarEnPosicion(null);
    setEstado("guardando");
    try {
      const actual = await fetch(`${config.restUrl}paginas/${config.slug}`, {
        headers: { "X-WP-Nonce": config.nonce },
      }).then((r) => r.json());
      const estructuraActual = actual.estructura || [];
      const idsAntes = new Set(todosLosIds(estructuraActual));
      // indice: SOLO tiene sentido "longitud del contenedor destino" como
      // default de "al final" cuando ese destino es nivel superior — con
      // containerId, "al final de TODO nivel superior" no significa nada
      // para un hijo, así que sin posicion explícita ahí simplemente
      // inserta en 0 (un caso que hoy no ocurre en la práctica: toda
      // inserción dentro de un container llega con posicion explícita
      // desde una línea "+", nunca desde el botón fijo de la barra
      // superior, que siempre agrega a nivel superior).
      const indice = posicion === undefined || posicion === null ? estructuraActual.length : posicion;
      const estructuraNueva = insertarEnArbol(estructuraActual, { tipo }, indice, containerId);
      const respuesta = await fetch(`${config.restUrl}paginas/${config.slug}/estructura`, {
        method: "PUT",
        headers: { "Content-Type": "application/json", "X-WP-Nonce": config.nonce },
        body: JSON.stringify({ estructura: estructuraNueva }),
      });
      setEstado(respuesta.ok ? "guardado" : "error");
      if (!respuesta.ok || !iframeRef.current) return;

      const pagina = await fetch(`${config.restUrl}paginas/${config.slug}`, {
        headers: { "X-WP-Nonce": config.nonce },
      }).then((r) => r.json());
      const idsDespues = todosLosIds(pagina.estructura || []);
      const idNuevo = idsDespues.find((id) => !idsAntes.has(id));
      if (!idNuevo) {
        // No debería pasar (el guardado ya confirmó éxito), pero si por
        // algún motivo no se puede identificar el bloque nuevo, un reload
        // sigue siendo el fallback seguro — mejor una recarga ocasional
        // que un editor que parece no haber agregado nada.
        iframeRef.current.contentWindow.location.reload();
        return;
      }

      const html = await fetch(`${config.restUrl}paginas/${config.slug}/bloque/${idNuevo}`, {
        headers: { "X-WP-Nonce": config.nonce },
      }).then((r) => r.json());
      if (!html.ok) {
        iframeRef.current.contentWindow.location.reload();
        return;
      }

      iframeRef.current.contentWindow.postMessage(
        { tipo: "sofia:insertar-bloque-html", html: html.html, posicion: indice, containerId: containerId || null },
        "*"
      );
    } catch {
      setEstado("error");
    }
  }

  const urlIframe = `${config.urlPagina}${config.urlPagina.includes("?") ? "&" : "?"}sofia_editor=1`;
  // Solo el host (sin protocolo/ruta) para el chrome falso de "navegador"
  // — mismo criterio visual del mockup ("costalegre.com.mx"), no la URL
  // completa con ?sofia_editor=1 que sería ruido de implementación.
  const urlHost = (() => {
    try {
      return new URL(config.urlPagina).host;
    } catch {
      return config.urlPagina;
    }
  })();

  return (
    <div className="sofia-editor-admin">
      <ListaVariablesCoreFramework nombres={variablesCoreFramework} />
      <div className="sofia-editor-admin__barra-flotante">
        <a href={config.urlSalir} className="sofia-editor-admin__salir">
          ← Volver
        </a>
        <span className={`sofia-editor-admin__estado sofia-editor-admin__estado--${estado}`}>
          {estado === "guardando" && "Guardando…"}
          {estado === "guardado" && "Guardado"}
          {estado === "error" && "No se pudo guardar"}
          {estado === "listo" && "Hacé click en un texto o imagen para editarlo"}
        </span>
        <button
          type="button"
          className="sofia-editor-admin__estilo-global"
          onClick={() => setPanelGlobalAbierto(true)}
        >
          Estilo global
        </button>
        <button
          type="button"
          className="sofia-editor-admin__generar-ia"
          onClick={() => setPanelIAAbierto(true)}
        >
          ✨ Generar con IA
        </button>
        <button
          type="button"
          className="sofia-editor-admin__agregar-bloque"
          onClick={() => setMenuAgregarAbierto((abierto) => !abierto)}
        >
          + Agregar bloque
        </button>
      </div>
      {menuAgregarAbierto && (
        <MenuAgregarBloque
          catalogo={catalogoBloques}
          onElegir={(tipo) => agregarBloque(tipo)}
          onCerrar={() => setMenuAgregarAbierto(false)}
        />
      )}
      {panelGlobalAbierto && (
        <PanelEstiloGlobal
          config={config}
          onCerrar={() => setPanelGlobalAbierto(false)}
          onGuardado={() => iframeRef.current?.contentWindow.location.reload()}
        />
      )}
      {panelIAAbierto && (
        <PanelGenerarIA
          config={config}
          onCerrar={() => setPanelIAAbierto(false)}
          onAplicar={aplicarArbolGeneradoPorIA}
        />
      )}
      <div className="sofia-lienzo-wrap">
        <div className="sofia-sitio-frame">
          <div className="sofia-sitio-chrome">
            <div className="sofia-sitio-chrome__trafico">
              <span></span>
              <span></span>
              <span></span>
            </div>
            <div className="sofia-sitio-chrome__url">{urlHost}</div>
          </div>
          {/* sofia-sitio-viewport envuelve SOLO el iframe y los overlays
              que apuntan a coordenadas DENTRO de él — bug real encontrado
              en la práctica: cuando los overlays eran hermanos del chrome
              falso (dentro de .sofia-sitio-frame completo), el resaltado
              aparecía corrido hacia arriba exactamente la altura de esa
              barra de "navegador" falsa, porque getBoundingClientRect()
              dentro del iframe (relativo a SU PROPIO viewport, que empieza
              en 0,0 desde el borde superior del iframe) no coincidía con
              .sofia-sitio-frame (que incluye el chrome arriba). Fix: un
              wrapper propio, del mismo tamaño exacto que el iframe,
              hermano del chrome — así el offset "el iframe no empieza en
              0,0 de la tarjeta" queda contenido en el propio layout CSS
              (position:relative en este wrapper), sin que el chrome de
              arriba lo desplace. */}
          <div className="sofia-sitio-viewport">
            <iframe ref={iframeRef} src={urlIframe} title="Editor de página" className="sofia-editor-admin__iframe" />
            <ResaltadoBloque bloque={bloqueResaltado} />
            <MenuContextualBloque
              posicion={menuContextual}
              onEliminarItem={eliminarItemDeLista}
              onSeleccionarContenedorPadre={seleccionarContenedorPadre}
              onCerrar={() => setMenuContextual(null)}
            />
            {menuAgregarEnPosicion && (
              <MenuAgregarBloque
                catalogo={catalogoBloques}
                posicion={menuAgregarEnPosicion}
                titulo="Insertar bloque aquí"
                onElegir={(tipo) => agregarBloque(tipo, menuAgregarEnPosicion.posicion, menuAgregarEnPosicion.containerId)}
                onCerrar={() => setMenuAgregarEnPosicion(null)}
              />
            )}
          </div>
        </div>

        {/* ZonaPropiedades — paso 1 del rediseño de layout a 3 zonas fijas
            (ver la memoria de producto): columna SIEMPRE montada, hermana
            de .sofia-sitio-frame (nunca dentro del "marco de navegador").
            DrawerEstilo ya NO es un overlay flotante — vive acá completo,
            sin abrir/cerrar; con drawerEstilo=null muestra un estado vacío
            en vez de desmontarse, así la columna nunca "parpadea" de
            ancho. */}
        <div className="sofia-zona-propiedades">
          {drawerEstilo ? (
            <DrawerEstilo
              campo={drawerEstilo.campo}
              tipoBloque={drawerEstilo.tipoBloque}
              estilo={drawerEstilo.estilo}
              nivel={drawerEstilo.nivel || "campo"}
              condicion={drawerEstilo.condicion}
              onCambiarEstilo={cambiarEstiloDrawer}
              onCambiarCondicion={cambiarCondicionDrawer}
              onAplicarFormato={aplicarFormato}
              onEliminarBloque={eliminarBloque}
              restUrl={config.restUrl}
              nonce={config.nonce}
            />
          ) : (
            <p className="sofia-zona-propiedades__vacio">Seleccioná un bloque para editarlo</p>
          )}
        </div>
      </div>
    </div>
  );
}

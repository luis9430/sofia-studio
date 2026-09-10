import { useEffect, useRef, useState } from "preact/hooks";
import { BotonEditarCampo } from "./BotonEditarCampo.jsx";
import { DrawerEstilo } from "./DrawerEstilo.jsx";
import { ResaltadoBloque } from "./ResaltadoBloque.jsx";
import { MenuAgregarBloque } from "./MenuAgregarBloque.jsx";
import { MenuContextualBloque } from "./MenuContextualBloque.jsx";

// debounce simple: junta ediciones rápidas del mismo campo (ej. varias
// pulsaciones de blur/focus seguidas) antes de llamar al proxy REST — el
// iframe ya solo notifica en "blur", así que esto es una segunda capa de
// seguridad, no el mecanismo principal de limitar llamadas.
const RETRASO_GUARDADO_MS = 400;

/**
 * App es el panel completo del editor in-place: un iframe full-bleed (ocupa
 * toda la pantalla disponible, sin bordes ni chrome de WordPress visible —
 * ver Sofia_Panel_Editor::ocultar_chrome_admin en el tema, y la memoria de
 * producto "Sofia Studio" sobre por qué se eligió pulir el iframe en vez de
 * eliminarlo) con una barra de estado flotante SOBRE el contenido, nunca
 * empujando el layout — mismo patrón documentado en Bricks/Elementor/AEM:
 * los controles viven fuera del documento del iframe, superpuestos. Todo
 * control de un campo (negrita/cursiva, alineación, color) vive en el
 * drawer de estilo (DrawerEstilo.jsx), abierto con el botón ✏️ que aparece
 * al hacer click en el campo (BotonEditarCampo.jsx) — un solo control por
 * campo, en vez de una barra de formato aparte más el drawer.
 *
 * El iframe nunca guarda nada por su cuenta (ver inc/js/editor-iframe.js)
 * — solo informa cambios vía postMessage, que este componente escucha y
 * persiste llamando al proxy REST de WordPress (mismo origen, sin CORS —
 * ver inc/class-rest-editor.php).
 */
export function App({ config }) {
  const iframeRef = useRef(null);
  const timersPorCampo = useRef({});
  const [estado, setEstado] = useState("listo"); // "listo" | "guardando" | "guardado" | "error"
  // Botón "editar estilo" (✏️) que aparece al hacer UN SOLO click sobre un
  // campo editable (ver "sofia:campo-clickeado" en editor-iframe.js,
  // activarTexto) — reemplaza la barra de formato flotante que antes solo
  // aparecía al SELECCIONAR texto (arrastrando), un gesto poco descubrible
  // que el usuario señaló explícitamente. Retiene {campo, rect, estilo} del
  // ÚLTIMO click, insumo para abrir el drawer al presionar el botón.
  const [campoClickeado, setCampoClickeado] = useState(null); // { campo, rect, estilo } | null
  // Campo+estilo del drawer de estilo (Nivel 3) — RETENIDO mientras el
  // drawer sigue abierto, aunque el usuario haga click en otro campo (en
  // ese caso el drawer simplemente cambia de campo, ver alRecibirMensaje) o
  // mueva el mouse hacia los propios controles del drawer (fuera del
  // iframe) — sin esto, un segundo click en el mismo campo o un drag del
  // drawer podría perder el campo activo a mitad de camino.
  // { campo, estilo, nivel } | null — nivel "campo" (default) usa `campo`
  // como identificador ("{id}.subcampo..."), nivel "bloque" usa `campo`
  // como el ID DIRECTO del bloque (mismo shape, distinto significado —
  // más simple que 2 estados separados que solo uno puede estar activo a
  // la vez).
  const [drawerEstilo, setDrawerEstilo] = useState(null);
  const drawerAbierto = useRef(false);
  const [bloqueResaltado, setBloqueResaltado] = useState(null);
  const [catalogoBloques, setCatalogoBloques] = useState([]);
  const [menuAgregarAbierto, setMenuAgregarAbierto] = useState(false);
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

  // Catálogo real de Componentes del tema (mismo endpoint que ya usa
  // editor-iframe.js para los nombres del overlay de resaltado) — se pide
  // una sola vez al montar el panel, nunca una lista curada aparte que
  // pueda desincronizarse.
  useEffect(() => {
    fetch(`${config.restUrl}catalogo-bloques`, { headers: { "X-WP-Nonce": config.nonce } })
      .then((resp) => (resp.ok ? resp.json() : []))
      .then(setCatalogoBloques)
      .catch(() => setCatalogoBloques([]));
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
        setCampoClickeado({ campo: datos.campo, rect: datos.rect, estilo: datos.estilo });
        // Si el drawer YA estaba abierto, un click en otro campo lo mueve
        // a ese campo nuevo directo — evita el paso extra de cerrar y
        // volver a abrir con el botón ✏️ cuando el usuario va editando
        // varios campos seguidos.
        if (drawerAbierto.current) {
          setDrawerEstilo({ campo: datos.campo, estilo: datos.estilo });
        }
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
      if (datos.tipo === "sofia:menu-contextual-bloque") {
        setMenuContextual({
          indice: datos.indice,
          id: datos.id,
          estiloBloque: datos.estiloBloque,
          item: datos.item,
          x: datos.x,
          y: datos.y,
        });
        return;
      }
      if (datos.tipo === "sofia:abrir-insertar-bloque") {
        setMenuAgregarEnPosicion({ posicion: datos.posicion, x: datos.x, y: datos.y });
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

  // El propio iframe ejecuta document.execCommand sobre su selección real
  // (ver alRecibirMensajeDelPadre en editor-iframe.js) — este panel nunca
  // toca el DOM del iframe directo, solo le pide que aplique el comando.
  function aplicarFormato(comando) {
    iframeRef.current?.contentWindow.postMessage({ tipo: "sofia:aplicar-formato", comando }, "*");
  }

  // Abre el drawer de estilo (Nivel 3) sobre el campo del último click (ver
  // campoClickeado, llenado por "sofia:campo-clickeado" en
  // editor-iframe.js) — el botón ✏️ que lo dispara solo se muestra cuando
  // campoClickeado existe, así que este chequeo es más una guarda de tipos
  // que un caso real esperado.
  function abrirDrawerEstilo() {
    if (!campoClickeado) return;
    drawerAbierto.current = true;
    setDrawerEstilo({ campo: campoClickeado.campo, estilo: campoClickeado.estilo });
    setCampoClickeado(null); // el drawer ya abierto reemplaza al botón ✏️.
  }

  function cerrarDrawerEstilo() {
    drawerAbierto.current = false;
    setDrawerEstilo(null);
    setCampoClickeado(null);
  }

  // Abre el drawer de estilo en modo "bloque" (Nivel 2) — desde "Estilo
  // del bloque" en el menú contextual (ver MenuContextualBloque.jsx). A
  // diferencia del Nivel 1, no depende de ningún click previo sobre un
  // campo: el id/estilo ya vienen en menuContextual (ver
  // "sofia:menu-contextual-bloque" arriba, que el iframe manda con TODO lo
  // necesario de una vez, sin roundtrip aparte).
  function abrirDrawerEstiloBloque() {
    if (!menuContextual?.id) return;
    drawerAbierto.current = true;
    setDrawerEstilo({ campo: menuContextual.id, estilo: menuContextual.estiloBloque || {}, nivel: "bloque" });
    setMenuContextual(null);
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

  // Eliminar bloque: comando explícito desde el menú contextual, mismo
  // patrón que aplicarFormato — el iframe es quien de verdad quita la
  // sección del DOM (ver alEliminarBloque en editor-iframe.js) y reporta
  // la estructura resultante vía "sofia:estructura-reordenada", que ya
  // persiste arriba.
  function eliminarBloque() {
    const indice = menuContextual?.indice;
    setMenuContextual(null);
    setBloqueResaltado(null);
    if (indice === undefined || indice === null) return;
    iframeRef.current?.contentWindow.postMessage({ tipo: "sofia:eliminar-bloque", indice }, "*");
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
  // del lado PHP). Por eso este comando NO pasa por el iframe: guarda la
  // estructura ampliada directo contra el proxy REST y recién ahí recarga
  // el iframe, para que WordPress renderice el bloque nuevo con PHP real.
  // $posicion: índice donde insertar dentro de la estructura (0 = antes
  // de todos, estructura.length = al final) — undefined/null preserva el
  // comportamiento original ("+ Agregar bloque" de la barra superior,
  // siempre al final). Con posicion explícita, el mismo mecanismo sirve
  // para "insertar ENTRE dos bloques" (líneas dentro del iframe, ver
  // activarLineasInsertar() en editor-iframe.js) — pedido explícito del
  // usuario tras ver el mockup original, que ya tenía este patrón
  // (.insertar-linea entre cada par de bloques).
  async function agregarBloque(tipo, posicion) {
    setMenuAgregarAbierto(false);
    setMenuAgregarEnPosicion(null);
    setEstado("guardando");
    try {
      const actual = await fetch(`${config.restUrl}paginas/${config.slug}`, {
        headers: { "X-WP-Nonce": config.nonce },
      }).then((r) => r.json());
      const estructuraActual = actual.estructura || [];
      const indice = posicion === undefined || posicion === null ? estructuraActual.length : posicion;
      const estructuraNueva = [...estructuraActual.slice(0, indice), { tipo }, ...estructuraActual.slice(indice)];
      const respuesta = await fetch(`${config.restUrl}paginas/${config.slug}/estructura`, {
        method: "PUT",
        headers: { "Content-Type": "application/json", "X-WP-Nonce": config.nonce },
        body: JSON.stringify({ estructura: estructuraNueva }),
      });
      setEstado(respuesta.ok ? "guardado" : "error");
      if (respuesta.ok && iframeRef.current) {
        iframeRef.current.contentWindow.location.reload();
      }
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
            {campoClickeado && !drawerEstilo && (
              <BotonEditarCampo rect={campoClickeado.rect} onClick={abrirDrawerEstilo} />
            )}
            {drawerEstilo && (
              <DrawerEstilo
                campo={drawerEstilo.campo}
                estilo={drawerEstilo.estilo}
                nivel={drawerEstilo.nivel || "campo"}
                onCambiarEstilo={cambiarEstiloDrawer}
                onAplicarFormato={aplicarFormato}
                onCerrar={cerrarDrawerEstilo}
              />
            )}
            <MenuContextualBloque
              posicion={menuContextual}
              onEliminarBloque={eliminarBloque}
              onEliminarItem={eliminarItemDeLista}
              onEstiloBloque={abrirDrawerEstiloBloque}
              onCerrar={() => setMenuContextual(null)}
            />
            {menuAgregarEnPosicion && (
              <MenuAgregarBloque
                catalogo={catalogoBloques}
                posicion={menuAgregarEnPosicion}
                titulo="Insertar bloque aquí"
                onElegir={(tipo) => agregarBloque(tipo, menuAgregarEnPosicion.posicion)}
                onCerrar={() => setMenuAgregarEnPosicion(null)}
              />
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

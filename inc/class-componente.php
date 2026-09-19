<?php
/**
 * Clase base de todo Componente del catálogo de Sofia Studio (Hero, Franja
 * de beneficios, etc.) — cada subclase implementa render() y, si necesita
 * JS propio (GSAP, Preact, una librería de calendario), dependencias_js().
 *
 * El Builder fluido (con_titulo/con_imagen/...) es azúcar sobre un array
 * interno $props — cada subclase decide qué métodos with_* expone según
 * los campos que realmente usa (ver class-hero.php para el ejemplo).
 * "campos" viene siempre con la notación "bloque.campo" declarada en
 * PlantillaPagina.Campos (ver internal/store/plantilla_pagina.go de
 * GoPress) — este Componente solo ve SU propia porción ya resuelta
 * (contenido_de_pagina.php se la recorta antes de llamar build()).
 */
abstract class Sofia_Componente {
	/**
	 * ID de INSTANCIA de este bloque — separado del "tipo" (que decide qué
	 * clase PHP instanciar, ver Sofia_Componente_Factory::crear()), mismo
	 * criterio confirmado contra Elementor/Bricks Builder (investigación
	 * real: ambos separan "id" de "elType"/"name" como campos hermanos).
	 * Bug real que este campo resuelve: sin ID de instancia, dos bloques
	 * del MISMO tipo en la misma página (ej. 2 "Franja de beneficios")
	 * compartían la misma clave de contenido ("franja_beneficios.items")
	 * — editar uno pisaba el contenido guardado del otro. Con ID, la clave
	 * pasa a ser "{id}.campo" — nunca ambigua entre instancias.
	 */
	protected string $id;

	/**
	 * Tipo de este Componente (ej. "hero", "franja_beneficios") — el mismo
	 * que decide qué clase PHP instanciar en
	 * Sofia_Componente_Factory::crear(). Guardado acá SOLO para poder
	 * imprimirlo en atributos_seccion() (ver abajo): el JS del editor
	 * necesita saber tanto el ID como el TIPO de cada bloque de nivel
	 * superior para reconstruir la estructura completa tras reordenar o
	 * eliminar uno — antes de este campo, el tipo se perdía por completo
	 * una vez que atributo_editable() empezó a usar el ID en vez del tipo.
	 */
	protected string $tipo;

	/**
	 * @var array<string,mixed> Valores ya resueltos para este bloque —
	 *      clave corta (sin el prefijo "id."), ej. "titulo" en vez de
	 *      "a3f92c1b.titulo". La mayoría de campos son string (texto/URL
	 *      de imagen), pero un campo de "lista repetible" (Nivel 2, ej.
	 *      "items" de Franja de beneficios) es un array de objetos —
	 *      mismo criterio que PaginaSitio.Contenido del lado GoPress
	 *      (map[string]any, ver la memoria de producto "tema WP con
	 *      editor de contenido").
	 */
	protected array $props = array();

	/**
	 * Bloques HIJOS de este Componente, YA RESUELTOS por Sofia_Pagina
	 * (instanciados vía Sofia_Componente_Factory::crear(), con su propio
	 * contenido ya recortado) — ver Sofia_Pagina::resolver_bloques() y la
	 * memoria de producto "Sofia Studio: plan de primitivas de layout".
	 * Vacío para el 99% de los Componentes (Hero, CTA, etc. no tienen
	 * hijos) — solo Sofia_Componente_Container los usa de verdad, llamando
	 * ->render() de cada uno dentro de su propio render().
	 *
	 * Deliberadamente objetos YA resueltos, nunca el array crudo de
	 * {id,tipo,hijos} ni acceso al contenido completo de la página — un
	 * Container no debe poder ver/leer el contenido de un bloque FUERA de
	 * su propio árbol de hijos, mismo principio de aislamiento que ya
	 * protege a dos bloques del mismo tipo entre sí a nivel superior.
	 *
	 * @var Sofia_Componente[]
	 */
	protected array $hijos = array();

	/**
	 * Construye un Componente ya con su tipo, ID de instancia, props
	 * resueltas e hijos ya resueltos — usado por Sofia_Pagina, nunca
	 * instanciado directo.
	 *
	 * @param array<string,mixed> $props
	 * @param Sofia_Componente[]  $hijos Vacío para cualquier Componente sin
	 *        anidamiento, que puede ignorar este parámetro por completo.
	 */
	final public function __construct( string $tipo, string $id, array $props = array(), array $hijos = array() ) {
		$this->tipo  = $tipo;
		$this->id    = $id;
		$this->props = array_merge( $this->props_por_defecto(), $props );
		$this->hijos = $hijos;
	}

	/**
	 * Valores de respaldo cuando la página todavía no tiene nada editado
	 * para este bloque (ej. una página recién creada) — cada Componente
	 * decide los suyos, para no mostrar una sección vacía en blanco.
	 *
	 * @return array<string,mixed>
	 */
	protected function props_por_defecto(): array {
		return array();
	}

	/**
	 * Expone $this->props ya resueltas (defaults + lo que se le haya
	 * pasado al construir) — usado por
	 * Sofia_REST_Editor::guardar_estructura() para PERSISTIR los valores
	 * por defecto de un bloque recién agregado en el contenido real de la
	 * página, en vez de dejarlos existir solo en PHP hasta la primera
	 * edición.
	 *
	 * Bug real que esto resuelve: un bloque con lista repetible (ej.
	 * Franja de beneficios, 3 items) recién agregado no tenía
	 * "{id}.items" en el contenido de GoPress — solo props_por_defecto()
	 * los generaba en memoria en cada render. Si el usuario editaba UN
	 * SOLO campo de UN SOLO item antes de que los demás se guardaran (ej.
	 * un blur accidental al hacer click derecho para abrir el menú
	 * contextual), Sofia_REST_Editor::asignar_valor_de_campo() escribía
	 * "{id}.items" = [ese índice => ese campo] — un array con SOLO un
	 * elemento, perdiendo los otros 2 que nunca habían llegado a
	 * persistirse. Confirmado con logging real: el guardado individual
	 * "funcionaba" (200 OK), pero corrompía silenciosamente el array
	 * completo porque partía de una base vacía.
	 *
	 * @return array<string,mixed>
	 */
	public function props(): array {
		return $this->props;
	}

	/**
	 * ID de instancia de este bloque — usado por
	 * Sofia_REST_Editor::obtener_html_de_bloque() para encontrar, dentro
	 * de la lista de Componentes de una página, cuál es el bloque puntual
	 * pedido (evita recargar la página ENTERA del iframe solo para
	 * insertar/actualizar UN bloque, ver la memoria de producto).
	 */
	public function id(): string {
		return $this->id;
	}

	/**
	 * Hijos YA resueltos de este Componente — accessor público (mismo
	 * criterio que props()/id()) agregado en Fase 3 ("primitivas de
	 * layout") para que Sofia_REST_Editor::obtener_html_de_bloque() pueda
	 * buscar un bloque por ID RECURSIVAMENTE, no solo entre los
	 * Componentes de nivel superior de Sofia_Pagina::componentes() — sin
	 * esto, pedir el HTML de un bloque recién insertado DENTRO de un
	 * Container (ver agregarBloque() en App.jsx, que depende de este
	 * endpoint para toda inserción, container o no) devolvía 404: el
	 * bloque existe en el árbol, pero nunca en esa lista plana de nivel
	 * superior. Vacío para el 99% de los Componentes, igual que $hijos en
	 * sí — ver el comentario largo ahí.
	 *
	 * @return Sofia_Componente[]
	 */
	public function hijos(): array {
		return $this->hijos;
	}

	/**
	 * Slugs de librerías JS que este Componente necesita en el frente
	 * público (ej. array('gsap'), array('preact')) — Sofia_Tema::encolar_dependencias()
	 * recorre TODOS los Componentes de la página, junta y deduplica esto
	 * antes de encolar nada. Un Componente sin JS (la mayoría: Hero,
	 * Testimonios, FAQ) deja el array vacío — es el default acá a
	 * propósito, así una subclase nueva no rompe si se olvida de
	 * declarar esto.
	 *
	 * @return string[]
	 */
	public function dependencias_js(): array {
		return array();
	}

	/**
	 * Devuelve el HTML del bloque — implementado por cada subclase.
	 */
	abstract public function render(): string;

	/**
	 * Nombre legible del Componente (ej. "Hero", "Franja de beneficios")
	 * — fuente de verdad ÚNICA para el catálogo de bloques insertables del
	 * editor (ver Sofia_Componente_Factory::catalogo(), consumido por
	 * Sofia_REST_Editor vía sofia/v1/catalogo-bloques) y para el overlay
	 * de resaltado dentro del iframe (ver editor-iframe.js — el JS ya NO
	 * mantiene su propio mapa NOMBRES_BLOQUE, lo pide a este endpoint).
	 */
	abstract public function nombre(): string;

	/**
	 * Atributos class="{clase_base} {utility classes}" +
	 * data-sofia-bloque-id/data-sofia-bloque-tipo (+ el style="..." de
	 * atributo_estilo_bloque(), si el bloque tiene uno guardado) en la
	 * <section> raíz de este Componente — editor-iframe.js los lee directo
	 * (en vez de "adivinar" el tipo a partir del primer data-sofia-campo,
	 * que ya no lo contiene desde que atributo_editable() usa el ID) para
	 * reconstruir {id, tipo} de cada bloque al reordenar/eliminar un bloque
	 * de nivel superior — ver activarReordenar()/alEliminarBloque(). Cada
	 * Componente debe usar esto para SU <section> completa (clase base
	 * propia incluida), ej.:
	 *   '<section ' . $this->atributos_seccion( 'sofia-hero' ) . '>'
	 *
	 * $clase_base ("sofia-hero", "sofia-cta", etc.) se recibe como
	 * parámetro en vez de que cada Componente escriba su propio
	 * class="..." aparte — necesario desde que este método también emite
	 * utility classes de Core Framework (ver clases_utilitarias_bloque()):
	 * un <section> no puede tener dos atributos class="..." (el navegador
	 * solo aplica el último y descarta el primero en silencio), así que
	 * clase base + utility classes tienen que armarse juntas acá.
	 *
	 * El estilo de BLOQUE (Nivel 2 — columnas de grid, color de fondo de la
	 * sección, padding vertical, utility classes) se agrega ACÁ (una sola
	 * vez, para los 6 Componentes) en vez de que cada uno llame un método
	 * aparte — mismo criterio que atributo_editable(): centralizar en la
	 * clase base lo que es igual para cualquier Componente, así un
	 * Componente nuevo lo hereda gratis sin tener que acordarse de nada.
	 *
	 * data-sofia-oculto-condicion (solo en modo editor, solo si
	 * bloque_visible() es false) marca la <section> con la pista visual de
	 * "esto está oculto para un visitante real, mismo criterio en el CSS
	 * del modo editor (class-modo-editor.php) que .sofia-handle-arrastre —
	 * un ATRIBUTO en la propia sección, nunca un <div> envolvente:
	 * editor-iframe.js reconoce cada bloque por "section" hijo DIRECTO de
	 * .sofia-pagina (ver leerBloquesDesde()), así que un wrapper extra
	 * rompería ese matching. page.php ya decidió SI
	 * renderizar el bloque (visita pública: no se renderiza en absoluto si
	 * no es visible) — acá solo se agrega la marca cuando corresponde.
	 */
	protected function atributos_seccion( string $clase_base ): string {
		$oculto = ( ! $this->bloque_visible() && class_exists( 'Sofia_Modo_Editor' ) && Sofia_Modo_Editor::activo() )
			? 'data-sofia-oculto-condicion="Oculto: condición no cumplida"'
			: '';
		$clases = trim( $clase_base . ' ' . $this->clases_utilitarias_bloque() );
		return sprintf(
			'class="%s" data-sofia-bloque-id="%s" data-sofia-bloque-tipo="%s"%s %s %s',
			esc_attr( $clases ),
			esc_attr( $this->id ),
			esc_attr( $this->tipo ),
			$this->atributo_grilla(),
			$this->atributo_estilo_bloque(),
			$oculto
		);
	}

	/**
	 * data-sofia-grilla: marca las secciones que son una GRILLA de items,
	 * derivándolo de que el bloque declare PERFIL_LISTA.
	 *
	 * Existe para que el CSS de los 3 controles de grilla deje de
	 * enumerar componentes a mano. Antes de esto, la alineación del
	 * contenido eran seis reglas que nombraban a Franja de beneficios y
	 * Testimonios una por una:
	 *
	 *   .sofia-franja-beneficios.items-left .sofia-franja-beneficios__item,
	 *   .sofia-testimonios.items-left .sofia-testimonios__item { … }
	 *
	 * Con dos componentes se tolera; con cuatro (llegaron Gallery y List)
	 * son doce selectores que hay que acordarse de ampliar cada vez, y
	 * olvidarse no rompe nada visible — el control simplemente deja de
	 * funcionar en el bloque nuevo, en silencio. Es la misma clase de
	 * desconexión que el plan describe: el perfil ya sabía cuáles son
	 * grillas, y el CSS no lo consultaba.
	 *
	 * Ahora lo consulta: [data-sofia-grilla].items-left > * { … }.
	 */
	private function atributo_grilla(): string {
		return static::claves_estilo_relevantes() === self::PERFIL_LISTA ? ' data-sofia-grilla' : '';
	}

	/**
	 * Utility classes de Core Framework para la <section> del bloque (Nivel
	 * 2) — a diferencia de atributo_estilo_bloque() (que arma un
	 * style="..." con VALORES), estas son clases CSS YA COMPLETAS que Core
	 * Framework define (ver CLASES_UTILITARIAS_BLOQUE): agregar la clase es
	 * todo lo que hace falta, no hay nada que resolver ni ningún token
	 * "cf:..." involucrado. Cada categoría (max_width/ancho/aspect_ratio/
	 * object_fit/z_index) es un <select> de opciones fijas en el drawer
	 * (nunca texto libre), así que alcanza con mapear el valor guardado
	 * contra la whitelist — un valor desconocido (dato corrupto) se ignora
	 * en silencio, mismo criterio que el resto de la clase.
	 */
	protected function clases_utilitarias_bloque(): string {
		$estilo = $this->props['_estilo_bloque'] ?? null;
		if ( ! is_array( $estilo ) || empty( $estilo ) ) {
			return '';
		}

		$clases = array();
		foreach ( self::CLASES_UTILITARIAS_BLOQUE as $clave => $opciones ) {
			$valor = $estilo[ $clave ] ?? null;
			if ( is_string( $valor ) && isset( $opciones[ $valor ] ) ) {
				$clases[] = $opciones[ $valor ];
			}
		}

		return implode( ' ', $clases );
	}

	/**
	 * Atributo data-sofia-campo="{id}.campo" en el elemento editable — el
	 * editor in-place (panel de GoPress) lo usa para saber qué campo de
	 * PaginaSitio.Contenido actualizar al editar ese elemento del iframe.
	 * Usa $this->id (la INSTANCIA), no el tipo — desde que dos bloques del
	 * mismo tipo dejaron de compartir contenido (ver el comentario de
	 * $id arriba), cada Componente arma esta notación con su propio ID.
	 */
	protected function atributo_editable( string $campo ): string {
		return sprintf( 'data-sofia-campo="%s.%s"', esc_attr( $this->id ), esc_attr( $campo ) );
	}

	/**
	 * imagen_o_placeholder( $valor ): $valor tal cual (ya pasado por
	 * esc_url()) si no está vacío, o un placeholder SVG inline (data-URI,
	 * nunca un archivo/request externo) SOLO en modo editor si está
	 * vacío — usar esto en vez de un `if ($imagen) { <img ...> }` a secas
	 * en cualquier Componente con un campo de imagen opcional.
	 *
	 * Bug real reportado por el usuario ("el de imagen no funciona, no
	 * sale nada al darle agregar y no hay errores"): un Componente con
	 * SOLO un campo de imagen (Sofia_Componente_Image) que omite el
	 * <img> por completo cuando está vacío deja su <section> sin NINGÚN
	 * elemento clickeable — activarImagen()/wp.media() (ver
	 * editor-iframe.js) necesita un <img> real para engancharse, así que
	 * el usuario no tenía ninguna forma de agregar la imagen la primera
	 * vez. Mismo problema (menos grave, porque el título sí da algo
	 * clickeable) en Sofia_Componente_Hero.
	 *
	 * Vacío en visita pública real: mismo criterio silencioso que el
	 * resto del sistema con un campo sin configurar (ver
	 * props_por_defecto()) — un visitante real nunca debe ver el
	 * placeholder de "Click para elegir imagen", eso es chrome de
	 * edición puro (mismo criterio que boton_agregar_item()/
	 * data-sofia-oculto-condicion).
	 *
	 * Devuelve '' (nunca el placeholder) si $valor ya viene con contenido
	 * — el caller decide si imprime el <img> según este resultado estar
	 * vacío o no, igual que antes.
	 */
	protected function imagen_o_placeholder( string $valor ): string {
		if ( $valor ) {
			return $valor;
		}
		if ( ! class_exists( 'Sofia_Modo_Editor' ) || ! Sofia_Modo_Editor::activo() ) {
			return '';
		}
		return 'data:image/svg+xml,' . rawurlencode( '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="300" viewBox="0 0 400 300"><rect width="400" height="300" fill="%23e5e5e5"/><text x="200" y="150" font-family="sans-serif" font-size="18" fill="%23888" text-anchor="middle" dominant-baseline="middle">Click para elegir imagen</text></svg>' );
	}

	/**
	 * ESTILOS_CAMPO_PERMITIDOS: whitelist de propiedades CSS que un campo
	 * puede tener guardadas en su "{campo}._estilo" (ver atributo_estilo()
	 * abajo) — mismo criterio que ETIQUETAS_FORMATO_PERMITIDAS: nunca CSS
	 * arbitrario, solo lo que el drawer de estilo (Nivel 1, panel Preact)
	 * realmente ofrece como control. Clave = nombre guardado en el JSON,
	 * valor = propiedad CSS real a emitir (permite que ambos difieran si
	 * hiciera falta — hoy coinciden).
	 *
	 * Regla de qué va en Nivel 1 (acá) vs Nivel 2 (atributo_estilo_bloque()):
	 * Nivel 1 es solo lo que afecta TEXTO/CONTENIDO puntual de ese elemento
	 * (tipografía + color); todo lo que es de la CAJA/contenedor (fondo,
	 * borde, sombra de caja, radius, espaciado) vive en Nivel 2 — mismo
	 * criterio con el que "color_fondo"/"margen"/"relleno" se movieron
	 * desde acá a atributo_estilo_bloque() (estaban mal ubicados: el fondo y
	 * el espaciado de un campo son en realidad propiedades de su caja, no de
	 * su texto).
	 */
	private const ESTILOS_CAMPO_PERMITIDOS = array(
		'alineacion'      => 'text-align',
		'color'           => 'color',
		'tamano_fuente'   => 'font-size',
		'tipo_fuente'     => 'font-family',
		'negrita'         => 'font-weight',
		'sombra_texto'    => 'text-shadow',
	);

	/**
	 * FUENTES_PERMITIDAS: whitelist de valores completos de font-family que
	 * "tipo_fuente" puede tomar — a diferencia del resto de
	 * ESTILOS_CAMPO_PERMITIDOS (que solo mapea NOMBRE→propiedad CSS y deja
	 * el VALOR libre), font-family sí necesita restringir también el
	 * valor: una fuente que el tema no cargó rompe silenciosamente a la
	 * fuente por defecto del navegador. Clave = valor guardado en el JSON
	 * (lo que manda el drawer), valor = la declaración font-family real,
	 * con su fallback — mismas 2 fuentes que ya carga el tema (ver
	 * functions.php / Google Fonts), nunca una fuente arbitraria de
	 * Internet que exigiría cargar un <link> nuevo dentro del iframe.
	 */
	private const FUENTES_PERMITIDAS = array(
		'display' => "'Fraunces', serif",
		'texto'   => "'Inter', sans-serif",
	);

	/**
	 * constantes_para_editor(): los valores que el script del iframe
	 * necesita conocer para aplicar estilo EN VIVO, antes de guardar.
	 *
	 * Hasta que existió este método, editor-iframe.js los tenía copiados a
	 * mano, con un comentario que decía "espejo EXACTO" de cada uno — tres
	 * listas duplicadas, una de ellas con unos 55 pares. Ese tipo de
	 * espejo falla en el peor modo posible: agregar una opción de ancho
	 * acá no rompe nada visible, el editor simplemente deja de reconocer
	 * esa clase y el control se comporta raro sin ningún error.
	 *
	 * Ahora viajan por wp_localize_script (ver
	 * Sofia_Modo_Editor::encolar_script), así que PHP sigue siendo la
	 * única fuente de verdad y el JS no puede desincronizarse.
	 *
	 * @return array<string,mixed>
	 */
	public static function constantes_para_editor(): array {
		return array(
			'fuentes'           => self::FUENTES_PERMITIDAS,
			'clasesUtilitarias' => self::CLASES_UTILITARIAS_BLOQUE,
			'prefijoToken'      => Sofia_Estilo_Global::PREFIJO_TOKEN_CORE_FRAMEWORK,
		);
	}

	/**
	 * PATRON_MEDIDA_CSS valida un valor de tamaño de fuente/espaciado antes
	 * de emitirlo — necesario porque, a diferencia de alineación/color
	 * (opciones fijas elegidas por el drawer), "tamano_fuente" (Nivel 1) y
	 * "espaciado_vertical" (Nivel 2) llegan como INPUT LIBRE del usuario
	 * (ver DrawerEstilo.jsx). Sin este chequeo, un valor corrupto o con
	 * intención maliciosa ("expression(...)", "javascript:", etc.) pasaría
	 * directo a esc_attr() — que sanea comillas/HTML pero NO valida que el
	 * contenido sea CSS válido. El patrón acepta 1 a 4 números (separados
	 * por espacio, para el shorthand de margin/padding) con unidad
	 * px/rem/%, cada uno opcionalmente negativo (margin negativo es válido
	 * CSS).
	 */
	private const PATRON_MEDIDA_CSS = '/^-?\d+(\.\d+)?(px|rem|%)( -?\d+(\.\d+)?(px|rem|%)){0,3}$/';

	/**
	 * Valores permitidos para "columnas" — a diferencia de un color/medida
	 * libre, el número de columnas solo tiene sentido en un rango chico
	 * (2 a 4, mismo criterio que cualquier grid de contenido real); un
	 * valor fuera de esta lista se ignora en silencio.
	 */
	private const COLUMNAS_PERMITIDAS = array( '2', '3', '4' );

	/**
	 * CLASES_UTILITARIAS_BLOQUE: whitelist de utility classes REALES de
	 * Core Framework que Nivel 2 (bloque) puede agregar a la <section> —
	 * a diferencia de ESTILOS_CAMPO_PERMITIDOS/atributo_estilo_bloque()
	 * (que arman un style="..." con un VALOR), estas no son un valor de
	 * propiedad CSS: cada opción ES una clase CSS completa ya definida por
	 * Core Framework (ver el CSS default exportado, auditado a mano —
	 * memoria de producto "Sofia Studio: sistema de estilo"), agregarla o
	 * quitarla del elemento es todo lo que hace falta.
	 *
	 * Estructura: {clave guardada en "_estilo_bloque" => {valor guardado =>
	 * clase CSS real}} — mismo criterio de whitelist de VALOR (no solo de
	 * nombre) que FUENTES_PERMITIDAS, necesario porque estos también son
	 * input elegido de una lista corta (un <select> en el drawer), nunca
	 * texto libre.
	 */
	private const CLASES_UTILITARIAS_BLOQUE = array(
		'max_width'   => array(
			'10'  => 'max-width-10',
			'20'  => 'max-width-20',
			'30'  => 'max-width-30',
			'40'  => 'max-width-40',
			'50'  => 'max-width-50',
			'60'  => 'max-width-60',
			'70'  => 'max-width-70',
			'80'  => 'max-width-80',
			'90'  => 'max-width-90',
			'100' => 'max-width-100',
			'site' => 'max-site-width',
		),
		'ancho'       => array(
			'10' => 'width-10',
			'20' => 'width-20',
			'30' => 'width-30',
			'40' => 'width-40',
			'50' => 'width-50',
			'60' => 'width-60',
			'70' => 'width-70',
			'80' => 'width-80',
			'90' => 'width-90',
			'full' => 'full-width',
			'auto' => 'auto-width',
		),
		'aspect_ratio' => array(
			'1'    => 'aspect-1',
			'4-3'  => 'aspect-4-3',
			'3-4'  => 'aspect-3-4',
			'3-2'  => 'aspect-3-2',
			'2-3'  => 'aspect-2-3',
			'16-9' => 'aspect-16-9',
			'9-16' => 'aspect-9-16',
		),
		'object_fit'  => array(
			'contain' => 'fit-contain',
			'cover'   => 'fit-cover',
			'fill'    => 'fit-fill',
		),
		'z_index'     => array(
			'-1'    => 'z--1',
			'0'     => 'z-0',
			'1'     => 'z-1',
			'10'    => 'z-10',
			'100'   => 'z-100',
			'1000'  => 'z-1000',
			'10000' => 'z-10000',
		),
		// alineacion_bloque: solo tiene efecto real cuando además hay un
		// Ancho/Ancho máximo menor al 100% (mismo criterio documentado en
		// style.css) — .self-left/-center/-right son las utility classes
		// REALES de Core Framework (margin-inline/left/right:auto +
		// place-self), funcionan tal cual tanto en la visita pública como
		// dentro del editor: en ambos el bloque está en flujo normal, así
		// que el margin auto de la propia utility class alcanza, sin
		// necesidad de que el JS calcule ninguna posición a mano.
		'alineacion_bloque' => array(
			'left'   => 'self-left',
			'center' => 'self-center',
			'right'  => 'self-right',
		),
		// alineacion_contenido/alineacion_vertical_contenido: solo tienen
		// efecto real en Componentes con una lista de items propia (Franja
		// de beneficios/Testimonios) — en un Componente sin eso (Hero/CTA/
		// FAQ/Texto libre) la clase no tiene nada que targetear, así que no
		// rompe nada pero tampoco hace nada visible (mismo criterio ya
		// usado en object_fit: se muestra siempre en el drawer con una nota
		// aclaratoria, en vez de ocultar el control según el tipo de
		// bloque).
		//
		// Nombres "items-*" heredados de la utility class real de Core
		// Framework, pero el CSS real (ver style.css) NO usa justify-items/
		// align-items en el contenedor — bug real encontrado en la
		// práctica: justify-items:center colapsaba cada columna a su
		// min-content (texto envuelto letra por letra, sin ancho del que
		// partir). El CSS mueve el TEXTO (text-align para horizontal,
		// flex-column + justify-content para vertical) en vez de la CAJA
		// de la columna — ver el comentario largo en style.css.
		'alineacion_contenido' => array(
			'left'   => 'items-left',
			'center' => 'items-center',
			'right'  => 'items-right',
		),
		'alineacion_vertical_contenido' => array(
			'top'    => 'items-top',
			'middle' => 'items-middle',
			'bottom' => 'items-bottom',
		),
	);

	/**
	 * Atributo style="..." para la <section> del bloque — estilo de NIVEL 2
	 * (el bloque completo, no un campo individual dentro de él), guardado
	 * en la clave especial "{id}._estilo_bloque" — nunca colisiona con un
	 * nombre de campo real porque empieza con guion bajo, mismo criterio
	 * que "_estilo" como sufijo reservado en Nivel 1.
	 *
	 * A diferencia de atributo_estilo() (Nivel 1), acá cada propiedad se
	 * valida a mano en vez de recorrer una whitelist genérica {clave =>
	 * propiedad_css} — "columnas" no es una propiedad CSS 1:1 (se emite
	 * como custom property --sofia-columnas, que el CSS de cada Componente
	 * con grid, Franja de beneficios/Testimonios, consume vía
	 * grid-template-columns: repeat(var(--sofia-columnas, 3), 1fr); un
	 * Componente sin grid como Hero/CTA simplemente nunca lee esa
	 * variable, definirla ahí no tiene efecto ni rompe nada) y
	 * "espaciado_vertical" expande a DOS propiedades (padding-top Y
	 * padding-bottom) desde un único valor.
	 *
	 * "color_fondo"/"color_borde"/"radius"/"sombra" son las propiedades de
	 * CAJA/contenedor que Core Framework expone como custom property real
	 * (colores, radius-*, shadow-*) — por eso viven acá y no en Nivel 1, y
	 * por eso (a diferencia de columnas/espaciado_vertical) sí pueden ser un
	 * token "cf:{nombre}", mismo mecanismo que atributo_estilo().
	 */
	protected function atributo_estilo_bloque(): string {
		$estilo = $this->props['_estilo_bloque'] ?? null;
		if ( ! is_array( $estilo ) || empty( $estilo ) ) {
			return '';
		}

		$declaraciones = array();

		if ( ! empty( $estilo['columnas'] ) && in_array( (string) $estilo['columnas'], self::COLUMNAS_PERMITIDAS, true ) ) {
			$declaraciones[] = '--sofia-columnas:' . (int) $estilo['columnas'];
		}

		// $tokens_crudos: mismo motivo que en atributo_estilo() (Nivel 1) —
		// el navegador nunca devuelve "cf:..." al leer de vuelta el estilo ya
		// aplicado, hace falta un data-attribute aparte por cada propiedad
		// para que editor-iframe.js pueda reconstruir el modo token al
		// reabrir el drawer.
		$tokens_crudos                    = array();
		$propiedades_con_token_de_bloque = array(
			'color_fondo'  => 'background-color',
			'color_borde'  => 'border-color',
			'radius'       => 'border-radius',
			'sombra'       => 'box-shadow',
		);
		foreach ( $propiedades_con_token_de_bloque as $clave => $propiedad_css ) {
			$valor = $estilo[ $clave ] ?? null;
			if ( empty( $valor ) || ! is_string( $valor ) ) {
				continue;
			}

			if ( Sofia_Estilo_Global::es_token_con_nombre( $valor ) ) {
				$declaraciones[]        = $propiedad_css . ':' . Sofia_Estilo_Global::resolver_valor( $valor );
				$tokens_crudos[ $clave ] = $valor;
			} elseif ( str_starts_with( $valor, Sofia_Estilo_Global::PREFIJO_TOKEN_CORE_FRAMEWORK ) ) {
				// Token SIN nombre ("cf:" solo) — se ignora en silencio, ver
				// Sofia_Estilo_Global::es_token_con_nombre().
				continue;
			} elseif ( 'radius' === $clave ) {
				// "radius" es INPUT LIBRE de una sola medida (ver
				// CampoConToken.jsx, conUnidad) cuando no es token — mismo
				// criterio de validación que tamano_fuente/espaciado_vertical,
				// nunca solo esc_attr() para un valor que debería ser CSS
				// válido.
				if ( preg_match( self::PATRON_MEDIDA_CSS, $valor ) ) {
					$declaraciones[] = $propiedad_css . ':' . esc_attr( $valor );
				}
			} else {
				$declaraciones[] = $propiedad_css . ':' . esc_attr( $valor );
			}

			// "color_borde" no tiene efecto sin border-style/width — a
			// diferencia de fondo/radius/sombra, un color de borde solo
			// tiene sentido si también hay un borde real; 1px solid fijo
			// (sin control de grosor propio, no lo pidieron) es suficiente
			// para que el color se vea. Fuera del if/else de arriba: aplica
			// tanto si "color_borde" vino como token como si vino fijo.
			if ( 'color_borde' === $clave ) {
				$declaraciones[] = 'border-style:solid';
				$declaraciones[] = 'border-width:1px';
			}
		}

		// espaciado_vertical: INPUT LIBRE (número + unidad, ver
		// CampoConToken.jsx conUnidad) que ahora también puede ser un token
		// de Core Framework — mismo criterio que "radius"/offset_x: el
		// data-attribute crudo se emite SIEMPRE (token o fijo), porque acá
		// no hay un "el.style.paddingTop" simple del que el JS pueda leer
		// de vuelta CUALQUIERA de los 2 casos de forma confiable (dos
		// propiedades, padding-top Y padding-bottom, derivadas del mismo
		// valor único).
		$espaciado_vertical = $estilo['espaciado_vertical'] ?? null;
		if ( ! empty( $espaciado_vertical ) && is_string( $espaciado_vertical ) ) {
			if ( Sofia_Estilo_Global::es_token_con_nombre( $espaciado_vertical ) ) {
				$valor_resuelto                        = Sofia_Estilo_Global::resolver_valor( $espaciado_vertical );
				$declaraciones[]                       = 'padding-top:' . $valor_resuelto;
				$declaraciones[]                       = 'padding-bottom:' . $valor_resuelto;
				$tokens_crudos['espaciado_vertical'] = $espaciado_vertical;
			} elseif ( ! str_starts_with( $espaciado_vertical, Sofia_Estilo_Global::PREFIJO_TOKEN_CORE_FRAMEWORK ) && preg_match( self::PATRON_MEDIDA_CSS, $espaciado_vertical ) ) {
				$valor            = esc_attr( $espaciado_vertical );
				$declaraciones[] = 'padding-top:' . $valor;
				$declaraciones[] = 'padding-bottom:' . $valor;
				$tokens_crudos['espaciado_vertical'] = $espaciado_vertical;
			}
		}

		// offset_x: desplazamiento horizontal LIBRE del bloque, que se SUMA
		// a "Alineación del bloque" (self-left/-center/-right) en vez de
		// reemplazarla — pedido explícito del usuario ("centrado pero un
		// poco corrido"). transform:translateX() en vez de sumarlo a
		// margin-left: "self-center" ya define margin-inline:auto (visita
		// pública) — un margin-left fijo adicional ahí competiría con ese
		// auto en vez de sumarse, dando un resultado impredecible.
		// translateX() se aplica DESPUÉS de que el navegador ya resolvió
		// margin/posición, así que siempre desplaza desde donde sea que el
		// bloque haya quedado, sin importar qué alineación esté activa.
		// Puede ser un token de Core Framework (ej. un valor de "space-*"),
		// mismo mecanismo que radius/sombra.
		//
		// data-sofia-estilo-offset_x se emite SIEMPRE (fijo o token) — a
		// diferencia del resto de $tokens_crudos (que solo hace falta para
		// un TOKEN, porque un valor fijo se puede releer tal cual de
		// el.style.borderRadius, por ejemplo), acá no hay ningún
		// "el.style.transform" del que el JS pueda leer de vuelta el
		// string ORIGINAL: el navegador normaliza transform a una matriz,
		// perdiendo tanto el token como la unidad escrita — sin este
		// data-attribute, reabrir el drawer perdería el offset guardado
		// incluso cuando es un valor fijo.
		$offset_x = $estilo['offset_x'] ?? null;
		if ( ! empty( $offset_x ) && is_string( $offset_x ) ) {
			if ( Sofia_Estilo_Global::es_token_con_nombre( $offset_x ) ) {
				$declaraciones[]           = 'transform:translateX(' . Sofia_Estilo_Global::resolver_valor( $offset_x ) . ')';
				$tokens_crudos['offset_x'] = $offset_x;
			} elseif ( ! str_starts_with( $offset_x, Sofia_Estilo_Global::PREFIJO_TOKEN_CORE_FRAMEWORK ) && preg_match( self::PATRON_MEDIDA_CSS, $offset_x ) ) {
				$declaraciones[]           = 'transform:translateX(' . esc_attr( $offset_x ) . ')';
				$tokens_crudos['offset_x'] = $offset_x;
			}
		}

		if ( empty( $declaraciones ) ) {
			return '';
		}

		$atributos_token = '';
		foreach ( $tokens_crudos as $clave => $valor_crudo ) {
			$atributos_token .= sprintf( ' data-sofia-estilo-%s="%s"', esc_attr( $clave ), esc_attr( $valor_crudo ) );
		}

		return 'style="' . implode( ';', $declaraciones ) . '"' . $atributos_token;
	}

	/**
	 * Atributo style="..." armado desde el estilo guardado de $campo.
	 *
	 * Dos formas de $campo, mismo criterio que atributo_editable():
	 * - "campo" (bloque simple, ej. "titulo"): el estilo vive en
	 *   $this->props["{campo}._estilo"] — clave plana hermana del valor,
	 *   armada por Sofia_REST_Editor::asignar_valor_de_campo() en el caso
	 *   de 2 (o con sufijo, 3) segmentos.
	 * - "lista.indice.subcampo" (item de una lista repetible, ej.
	 *   "items.0.titulo"): $this->props["lista"] es el ARRAY de items
	 *   completo (Nivel 2), así que el estilo vive DENTRO de ese array, en
	 *   $this->props["lista"][indice]["subcampo._estilo"] — mismo lugar
	 *   donde asignar_valor_de_campo() lo escribe para el caso de 4 (o con
	 *   sufijo, 5) segmentos. Iterar $this->props plano con la clave
	 *   completa ("items.0.titulo._estilo") nunca encontraría nada: esa
	 *   clave no existe, el array real está anidado.
	 *
	 * Devuelve string vacío si el campo no tiene estilo guardado — el
	 * elemento queda exactamente igual que antes de que existiera esta
	 * pieza, ningún Componente rompe por no tener "_estilo" en su
	 * contenido.
	 *
	 * Solo emite las propiedades de ESTILOS_CAMPO_PERMITIDOS — un valor
	 * con una clave desconocida (dato corrupto, o una versión más nueva
	 * del editor que un tema viejo no reconoce) se ignora en silencio,
	 * mismo criterio que un tipo de bloque desconocido en la Factory.
	 */
	protected function atributo_estilo( string $campo ): string {
		$segmentos = explode( '.', $campo );
		if ( 3 === count( $segmentos ) ) {
			list( $lista, $indice, $subcampo ) = $segmentos;
			$items  = is_array( $this->props[ $lista ] ?? null ) ? $this->props[ $lista ] : array();
			$item   = $items[ (int) $indice ] ?? null;
			$estilo = is_array( $item ) ? ( $item[ "{$subcampo}._estilo" ] ?? null ) : null;
		} else {
			$estilo = $this->props[ "{$campo}._estilo" ] ?? null;
		}

		if ( ! is_array( $estilo ) || empty( $estilo ) ) {
			return '';
		}

		$declaraciones = array();
		// $tokens_crudos guarda, por clave ("color", hoy la única propiedad
		// de Nivel 1 que puede ser token), el valor CRUDO tal cual se
		// guardó ("cf:border-primary") cuando ES un token — necesario
		// porque el navegador, al leer de vuelta el.style.color, devuelve
		// el color YA COMPUTADO (ej. un rgb() resuelto de la cascada),
		// nunca el string "cf:..." original. Sin esto, editor-iframe.js
		// (estiloActualDe) no podría distinguir "el usuario eligió un
		// token" de "el usuario eligió ese color exacto", y el drawer
		// perdía el modo token al reabrirse — bug real reportado por el
		// usuario ("se borra al salir del modal").
		$tokens_crudos = array();
		foreach ( self::ESTILOS_CAMPO_PERMITIDOS as $clave => $propiedad_css ) {
			$valor = $estilo[ $clave ] ?? null;
			if ( empty( $valor ) || ! is_string( $valor ) ) {
				continue;
			}

			// "tipo_fuente" no emite su valor tal cual — resuelve contra la
			// whitelist de FUENTES_PERMITIDAS (ver el comentario ahí: una
			// fuente que el tema no cargó rompe silenciosamente). Un valor
			// desconocido (dato corrupto) se ignora en silencio, igual que
			// cualquier otra clave no reconocida.
			if ( 'tipo_fuente' === $clave ) {
				if ( ! isset( self::FUENTES_PERMITIDAS[ $valor ] ) ) {
					continue;
				}
				$declaraciones[] = $propiedad_css . ':' . self::FUENTES_PERMITIDAS[ $valor ];
				continue;
			}

			// "color"/"tamano_fuente" pueden ser un token de Core Framework
			// ("cf:{nombre}", mismo mecanismo que Sofia_Estilo_Global —
			// PREFIJO_TOKEN_CORE_FRAMEWORK/resolver_valor() reusados tal
			// cual, nunca duplicados) en vez de un valor fijo elegido con el
			// <input type="color">/número+unidad del drawer.
			if ( in_array( $clave, array( 'color', 'tamano_fuente' ), true ) && Sofia_Estilo_Global::es_token_con_nombre( $valor ) ) {
				$declaraciones[]        = $propiedad_css . ':' . Sofia_Estilo_Global::resolver_valor( $valor );
				$tokens_crudos[ $clave ] = $valor;
				continue;
			}

			// Token SIN nombre ("cf:" solo) — se ignora en silencio, mismo
			// criterio que cualquier otro valor inválido de esta clave;
			// nunca se emite como valor fijo tampoco (str_starts_with sigue
			// siendo true, "cf:" no es un color hex ni una medida válida).
			if ( in_array( $clave, array( 'color', 'tamano_fuente' ), true ) && str_starts_with( $valor, Sofia_Estilo_Global::PREFIJO_TOKEN_CORE_FRAMEWORK ) ) {
				continue;
			}

			// tamano_fuente es INPUT LIBRE del usuario (ver DrawerEstilo.jsx)
			// cuando NO es token — a diferencia del resto (opciones fijas
			// elegidas por el drawer), necesita validarse como una medida
			// CSS real antes de emitirse, no solo escaparse.
			if ( 'tamano_fuente' === $clave && ! preg_match( self::PATRON_MEDIDA_CSS, $valor ) ) {
				continue;
			}

			// esc_attr() sobre el VALOR completo de cada propiedad —
			// suficiente porque los valores vienen de un swatch/alineación
			// controlados por el drawer (nunca texto libre del usuario),
			// pero se sanea igual por si el JSON llegara manipulado.
			$declaraciones[] = $propiedad_css . ':' . esc_attr( $valor );
		}

		if ( empty( $declaraciones ) ) {
			return '';
		}

		$atributos_token = '';
		foreach ( $tokens_crudos as $clave => $valor_crudo ) {
			// data-sofia-estilo-{clave} — nombre derivado de la clave, un
			// atributo por propiedad que SÍ puede ser token (hoy "color" y
			// "tamano_fuente" en Nivel 1).
			$atributos_token .= sprintf( ' data-sofia-estilo-%s="%s"', esc_attr( $clave ), esc_attr( $valor_crudo ) );
		}

		return 'style="' . implode( ';', $declaraciones ) . '"' . $atributos_token;
	}

	/**
	 * VARIABLES_CONDICION_PERMITIDAS: whitelist de nombres de "campo" que
	 * una regla de condición de visibilidad (pestaña Visibilidad del
	 * drawer, Nivel 2 — bloque completo) puede referenciar — mismo criterio
	 * de whitelist que el resto del editor: el drawer solo ofrece esta
	 * lista corta como control, nunca texto libre de variables de
	 * WordPress arbitrarias. Clave = nombre guardado en la regla, valor =
	 * closure que resuelve el valor REAL en este request (siempre string,
	 * mismo tipo que ReglaCondicion.Valor del lado GoPress — comparación
	 * siempre como string, ver evaluar_regla_condicion()).
	 *
	 * "usuario_logueado" es la única variable de arranque (decisión
	 * explícita del usuario) — más variables (rol, dispositivo, categoría
	 * del post) se agregan acá mismo el día que hagan falta, sin tocar el
	 * evaluador.
	 */
	private static function variables_condicion(): array {
		return array(
			'usuario_logueado' => static fn(): string => is_user_logged_in() ? 'true' : 'false',
		);
	}

	/**
	 * evaluar_regla_condicion espeja evaluarRegla() de
	 * internal/temporal/workflow_automatizacion_builder.go — MISMO shape de
	 * regla ({campo, operador, valor, enlace}, ver
	 * store.ReglaCondicion), pero acotado a los operadores que de verdad
	 * hacen falta acá: "eq"/"ne" alcanzan para una variable booleana como
	 * "usuario_logueado". Si en algún momento se agrega una variable
	 * numérica/de texto, sumar "gt"/"lt"/"has"/"exists" acá replicando la
	 * misma lógica que el lado Go, no antes.
	 *
	 * Un campo NO reconocido (no está en variables_condicion()) evalúa
	 * como false — mismo criterio de "fail closed" que el resto del
	 * editor con datos desconocidos/corruptos: mejor ocultar de más que
	 * mostrar por error un bloque que debía quedar condicionado.
	 */
	private static function evaluar_regla_condicion( array $regla ): bool {
		$campo    = $regla['campo'] ?? '';
		$operador = $regla['operador'] ?? 'eq';
		$valor    = (string) ( $regla['valor'] ?? '' );

		$variables = self::variables_condicion();
		if ( ! isset( $variables[ $campo ] ) ) {
			return false;
		}

		$valor_real = $variables[ $campo ]();

		if ( 'ne' === $operador ) {
			return $valor_real !== $valor;
		}
		return $valor_real === $valor; // "eq" es el default, mismo criterio que el lado Go.
	}

	/**
	 * bloque_visible() evalúa la condición de Visibilidad guardada en
	 * "{id}._condicion_bloque" — mismo mecanismo de encadenado "y"/"o" que
	 * evaluarCondicion() del lado Go: la primera regla decide sola, cada
	 * regla siguiente se combina con la anterior según su propio "enlace".
	 * Sin condición guardada (array vacío o ausente) siempre es visible —
	 * ningún bloque existente rompe por no tener "_condicion_bloque".
	 */
	public function bloque_visible(): bool {
		$reglas = $this->props['_condicion_bloque'] ?? null;
		if ( ! is_array( $reglas ) || empty( $reglas ) ) {
			return true;
		}

		$resultado = self::evaluar_regla_condicion( $reglas[0] );
		for ( $i = 1, $total = count( $reglas ); $i < $total; $i++ ) {
			$actual = self::evaluar_regla_condicion( $reglas[ $i ] );
			$enlace = $reglas[ $i ]['enlace'] ?? 'y';
			$resultado = ( 'o' === $enlace ) ? ( $resultado || $actual ) : ( $resultado && $actual );
		}
		return $resultado;
	}

	/**
	 * Botón "+ Agregar item" al final de una lista repetible (Nivel 2,
	 * sub-items — ver class-franja-beneficios.php) — SOLO se imprime en
	 * modo editor (Sofia_Modo_Editor::activo()), nunca en una visita
	 * pública real, mismo criterio que el resto de chrome de edición
	 * (handles de arrastre, contenteditable). editor-iframe.js engancha
	 * el click y manda "sofia:agregar-item-lista" al panel padre — el
	 * HTML del item nuevo lo emite PHP tras guardar, mismo patrón que
	 * "agregar bloque" de nivel superior: el iframe nunca inventa HTML de
	 * un Componente.
	 *
	 * $nombre_campo es el nombre corto del campo de lista dentro de ESTE
	 * Componente (ej. "items") — se le antepone $this->id para armar la
	 * clave completa ("{id}.items"), igual que atributo_editable().
	 */
	protected function boton_agregar_item( string $nombre_campo ): string {
		if ( ! class_exists( 'Sofia_Modo_Editor' ) || ! Sofia_Modo_Editor::activo() ) {
			return '';
		}
		return sprintf(
			'<button type="button" class="sofia-boton-agregar-item" data-sofia-agregar-item="%s.%s">+ %s</button>',
			esc_attr( $this->id ),
			esc_attr( $nombre_campo ),
			esc_html__( 'Agregar', 'sofia-studio' )
		);
	}

	/**
	 * ICONOS_PERMITIDOS: whitelist del set de íconos del sistema — clave =
	 * nombre guardado (el mismo de Tabler, sin extensión), valor = contenido
	 * INTERNO del SVG (uno o más <path>, sin el wrapper, que arma
	 * svg_icono()). Nunca se imprime un <path> que no haya sido revisado y
	 * agregado acá a mano.
	 *
	 * Vive en la clase base, no en Sofia_Componente_Icon, porque son cinco
	 * los Componentes que dibujan íconos (Icon, IconButton, Callout, List,
	 * Rating) y el helper que los consume —svg_icono()— también está acá.
	 * Mismo criterio que FUENTES_PERMITIDAS: un whitelist compartido del
	 * sistema pertenece al lugar común, no a uno de sus consumidores.
	 */
	public const ICONOS_PERMITIDOS = array(
		'arrow-right'      => '<path d="M5 12l14 0" /><path d="M13 18l6 -6" /><path d="M13 6l6 6" />',
		'arrow-left'       => '<path d="M5 12l14 0" /><path d="M5 12l6 6" /><path d="M5 12l6 -6" />',
		'arrow-up'         => '<path d="M12 5l0 14" /><path d="M18 11l-6 -6" /><path d="M6 11l6 -6" />',
		'arrow-down'       => '<path d="M12 5l0 14" /><path d="M18 13l-6 6" /><path d="M6 13l6 6" />',
		'check'            => '<path d="M5 12l5 5l10 -10" />',
		'info-circle'      => '<path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0" /><path d="M12 9h.01" /><path d="M11 12h1v4h1" />',
		'x'                => '<path d="M18 6l-12 12" /><path d="M6 6l12 12" />',
		'menu-2'           => '<path d="M4 6l16 0" /><path d="M4 12l16 0" /><path d="M4 18l16 0" />',
		'plus'             => '<path d="M12 5l0 14" /><path d="M5 12l14 0" />',
		'minus'            => '<path d="M5 12l14 0" />',
		'star'             => '<path d="M12 17.75l-6.172 3.245l1.179 -6.873l-5 -4.867l6.9 -1l3.086 -6.253l3.086 6.253l6.9 1l-5 4.867l1.179 6.873l-6.158 -3.245" />',
		'heart'            => '<path d="M19.5 12.572l-7.5 7.428l-7.5 -7.428a5 5 0 1 1 7.5 -6.566a5 5 0 1 1 7.5 6.572" />',
		'mail'             => '<path d="M3 7a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v10a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-10" /><path d="M3 7l9 6l9 -6" />',
		'phone'            => '<path d="M5 4h4l2 5l-2.5 1.5a11 11 0 0 0 5 5l1.5 -2.5l5 2v4a2 2 0 0 1 -2 2a16 16 0 0 1 -15 -15a2 2 0 0 1 2 -2" />',
		'map-pin'          => '<path d="M9 11a3 3 0 1 0 6 0a3 3 0 0 0 -6 0" /><path d="M17.657 16.657l-4.243 4.243a2 2 0 0 1 -2.827 0l-4.244 -4.243a8 8 0 1 1 11.314 0" />',
		'clock'            => '<path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0" /><path d="M12 7v5l3 3" />',
		'calendar'         => '<path d="M4 7a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-12" /><path d="M16 3v4" /><path d="M8 3v4" /><path d="M4 11h16" /><path d="M11 15h1" /><path d="M12 15v3" />',
		'download'         => '<path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2" /><path d="M7 11l5 5l5 -5" /><path d="M12 4l0 12" />',
		'upload'           => '<path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2" /><path d="M7 9l5 -5l5 5" /><path d="M12 4l0 12" />',
		'external-link'    => '<path d="M12 6h-6a2 2 0 0 0 -2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-6" /><path d="M11 13l9 -9" /><path d="M15 4h5v5" />',
		'chevron-right'    => '<path d="M9 6l6 6l-6 6" />',
		'chevron-down'     => '<path d="M6 9l6 6l6 -6" />',
		'brand-facebook'   => '<path d="M7 10v4h3v7h4v-7h3l1 -4h-4v-2a1 1 0 0 1 1 -1h3v-4h-3a5 5 0 0 0 -5 5v2h-3" />',
		'brand-instagram'  => '<path d="M4 8a4 4 0 0 1 4 -4h8a4 4 0 0 1 4 4v8a4 4 0 0 1 -4 4h-8a4 4 0 0 1 -4 -4l0 -8" /><path d="M9 12a3 3 0 1 0 6 0a3 3 0 0 0 -6 0" /><path d="M16.5 7.5v.01" />',
		'brand-whatsapp'   => '<path d="M3 21l1.65 -3.8a9 9 0 1 1 3.4 2.9l-5.05 .9" /><path d="M9 10a.5 .5 0 0 0 1 0v-1a.5 .5 0 0 0 -1 0v1a5 5 0 0 0 5 5h1a.5 .5 0 0 0 0 -1h-1a.5 .5 0 0 0 0 1" />',
		'send'             => '<path d="M10 14l11 -11" /><path d="M21 3l-6.5 18a.55 .55 0 0 1 -1 0l-3.5 -7l-7 -3.5a.55 .55 0 0 1 0 -1l18 -6.5" />',
	);

	/**
	 * atributo_lista() / atributo_item(): los dos marcadores que
	 * editor-iframe.js busca para operar sobre una lista repetible — mover,
	 * agregar o eliminar un item (alMoverItemDeLista y compañía).
	 *
	 * Son un CONTRATO con el editor, no decoración: si el nombre del
	 * atributo o el formato de la clave no coinciden exactamente, el editor
	 * no encuentra la lista y los gestos del árbol de Estructura dejan de
	 * funcionar, sin ningún error visible. Por eso el formato vive acá y no
	 * escrito a mano en cada Componente con lista (hoy seis).
	 *
	 * La clave lleva el ID de instancia, no el tipo ("{id}.items", igual
	 * que atributo_editable()): dos Franjas de beneficios en la misma
	 * página necesitan selectores distintos, o el editor no puede saber
	 * sobre cuál de las dos operar.
	 */
	protected function atributo_lista( string $nombre_campo ): string {
		return sprintf( 'data-sofia-lista="%s.%s"', esc_attr( $this->id ), esc_attr( $nombre_campo ) );
	}

	protected function atributo_item( int $indice ): string {
		return sprintf( 'data-sofia-item="%d"', $indice );
	}

	/**
	 * svg_icono(): un <svg> inline de ICONOS_PERMITIDOS, con el wrapper
	 * completo ya armado. Antes esto estaba escrito carácter
	 * por carácter en 5 Componentes (Icon, IconButton, Callout, List,
	 * Rating), idéntico salvo clase y tamaño.
	 *
	 * stroke="currentColor" (no un color propio) es lo que hace que el
	 * ícono herede el color del texto que lo rodea, sin que cada Componente
	 * tenga que resolverlo. Un nombre fuera del whitelist cae al de
	 * respaldo en vez de imprimir un <svg> vacío e invisible — mismo
	 * criterio de "fail closed mostrando algo razonable" que
	 * imagen_o_placeholder().
	 */
	protected function svg_icono( string $nombre, int $tamano = 24, string $clase = '', string $respaldo = 'star' ): string {
		$iconos = self::ICONOS_PERMITIDOS;
		$path   = $iconos[ $nombre ] ?? ( $iconos[ $respaldo ] ?? reset( $iconos ) );

		return sprintf(
			'<svg class="%s" xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%s</svg>',
			esc_attr( $clase ),
			$tamano,
			$tamano,
			$path // Siempre una entrada del whitelist, nunca contenido del usuario.
		);
	}

	/**
	 * estilo_bloque(): el array de estilo de Nivel 2 de ESTE bloque, o uno
	 * vacío si no hay nada guardado.
	 *
	 * Todo lo que declara schema_propio() viaja anidado en
	 * $this->props['_estilo_bloque'], NUNCA como clave suelta de props —
	 * leerlo del lugar equivocado fue un bug real que dejó sin efecto todos
	 * los controles de layout de Container hasta que se detectó probando en
	 * vivo. Este método es el único lugar que debe conocer esa estructura.
	 */
	protected function estilo_bloque(): array {
		return is_array( $this->props['_estilo_bloque'] ?? null ) ? $this->props['_estilo_bloque'] : array();
	}

	/**
	 * clase_de_variante(): traduce el valor guardado de una prop de
	 * apariencia (Nivel 2) a su clase CSS modificadora, contra un mapa
	 * whitelist del propio Componente. Un valor desconocido —dato corrupto,
	 * o de una versión más nueva del tema— no agrega ninguna clase, en vez
	 * de imprimir una inventada.
	 */
	protected function clase_de_variante( array $mapa, string $clave = 'variante' ): string {
		$valor = (string) ( $this->estilo_bloque()[ $clave ] ?? '' );
		return $mapa[ $valor ] ?? '';
	}

	/**
	 * SCHEMA_TONO: la prop de apariencia "tono" — de qué color se pinta
	 * un bloque que tiene UN color de marca y nada más.
	 *
	 * Es una constante compartida y no un select copiado en cada
	 * Componente porque los roles semánticos son del SITIO (Nivel 3, ver
	 * Sofia_Estilo_Global), no de cada bloque: si mañana aparece un rol
	 * "aviso", el lugar donde agregarlo tiene que ser uno solo.
	 *
	 * El valor vacío es "Primario" a propósito, no una opción "sin tono":
	 * estos bloques SIEMPRE tienen color, y el default es el de marca. Así
	 * ninguna instancia ya guardada cambia de aspecto al aparecer la prop.
	 *
	 * Por qué existe: Progress, Rating, IconButton y Link salían los
	 * cuatro fijos en --sofia-color-primario, sin forma de marcar un
	 * progreso en rojo o un rating destacado. Un catálogo donde cada
	 * bloque tiene exactamente un aspecto posible es también la razón por
	 * la que el generador por IA "hace siempre lo mismo" — no le estamos
	 * dando de dónde elegir.
	 */
	protected const SCHEMA_TONO = array(
		'tipo'     => 'select',
		'etiqueta' => 'Tono',
		'opciones' => array(
			array( 'valor' => '', 'etiqueta' => 'Primario' ),
			array( 'valor' => 'secundario', 'etiqueta' => 'Secundario' ),
			array( 'valor' => 'acento', 'etiqueta' => 'Acento' ),
			array( 'valor' => 'exito', 'etiqueta' => 'Éxito' ),
			array( 'valor' => 'error', 'etiqueta' => 'Error' ),
		),
	);

	/**
	 * clase_de_tono(): la clase modificadora del tono elegido.
	 *
	 * Devuelve una clase GENÉRICA (sofia-tono--exito), no una por
	 * componente (sofia-progress--exito): los cinco tonos son los mismos
	 * en todos lados, así que el CSS los define una vez apoyándose en
	 * currentColor y cada bloque decide qué pintar con él. Sin esto serían
	 * cinco reglas por componente, veinte en total, todas iguales salvo el
	 * prefijo — la misma duplicación que el atributo data-sofia-grilla
	 * vino a sacar del CSS de las grillas.
	 */
	protected function clase_de_tono(): string {
		$valor = (string) ( $this->estilo_bloque()['tono'] ?? '' );
		$validos = array( 'secundario', 'acento', 'exito', 'error' );
		return in_array( $valor, $validos, true ) ? 'sofia-tono--' . $valor : '';
	}

	/**
	 * valor_acotado(): un entero del contenido, forzado al rango válido.
	 * Lo que se guarda como texto puede llegar fuera de rango o no ser un
	 * número (dato corrupto, o escrito a mano), y de ahí sale un ancho de
	 * barra o una cuenta de estrellas — nunca se imprime sin acotar.
	 */
	protected function valor_acotado( string $campo, int $min, int $max ): int {
		return max( $min, min( $max, (int) ( $this->props[ $campo ] ?? $min ) ) );
	}

	/**
	 * ETIQUETAS_FORMATO_PERMITIDAS es el whitelist completo de formato
	 * rico que un campo de texto puede llevar — ver el drawer de estilo del
	 * editor (admin-app/src/DrawerEstilo.jsx), que hoy solo ofrece
	 * negrita/cursiva. Deliberadamente chico: el contenido de un
	 * campo sigue siendo "texto con un poco de énfasis", nunca HTML
	 * arbitrario (sin <div>, <script>, atributos de estilo, etc.) — mismo
	 * criterio de "estructura fija, solo contenido editable" que el resto
	 * del producto.
	 */
	private const ETIQUETAS_FORMATO_PERMITIDAS = array(
		'b'      => array(),
		'strong' => array(),
		'i'      => array(),
		'em'     => array(),
		'br'     => array(),
	);

	/**
	 * Devuelve $valor listo para imprimir en HTML, permitiendo SOLO las
	 * etiquetas de ETIQUETAS_FORMATO_PERMITIDAS — usar esto (nunca
	 * esc_html()) en cualquier campo de texto que la barra de formato
	 * pueda editar; esc_html() destruiría el <b>/<i> guardado,
	 * mostrándolo como texto literal "&lt;b&gt;" en vez de negrita real.
	 * wp_kses() ya elimina cualquier otra etiqueta/atributo, así que este
	 * método es seguro de imprimir directo sin escapar de nuevo.
	 */
	protected function texto_enriquecido( string $valor ): string {
		return wp_kses( $valor, self::ETIQUETAS_FORMATO_PERMITIDAS );
	}

	/**
	 * schema_bloque_generico(): los 12 controles de Nivel 2 (estilo de
	 * BLOQUE — caja/contenedor completo) que hoy son IGUALES para
	 * cualquier Componente del catálogo, mismos campos que
	 * DrawerEstilo.jsx ya venía renderizando a mano (hardcodeados en JSX,
	 * ver ANCHOS/ALINEACIONES_BLOQUE/etc. ahí). Fuente de verdad única de
	 * "qué controles tiene el drawer de bloque" — pasa a vivir acá (PHP)
	 * en vez de en dos lugares (las validaciones de
	 * atributo_estilo_bloque()/clases_utilitarias_bloque() arriba, y las
	 * constantes de JSX) — ver Sofia Studio: plan de "primitivas de
	 * layout" (Fase 2, schema tipado de campos), memoria de producto.
	 *
	 * Cada entrada: {tipo, ...datos propios del tipo de control}. Tipos
	 * reales usados hoy (ver CampoDesdeSchema.jsx, admin-app):
	 * - "botones_numero": fila de botones exclusivos ({opciones:[...]}).
	 * - "color_token" / "medida_token": CampoConToken.jsx tal cual
	 *   ({categoria, conUnidad, placeholder}).
	 * - "select": <select> de opciones fijas ({opciones:[{valor,etiqueta}]}).
	 * - "alineacion_iconos": fila de botones exclusivos con ícono en vez
	 *   de texto ({opciones:[{valor,etiqueta,icono}]}).
	 *
	 * `final` — el CATÁLOGO completo de controles posibles es el mismo
	 * para todo el sistema (nunca un Componente inventa un control
	 * genérico nuevo, eso es tarea de schema_propio()). CUÁLES de estas
	 * 15 claves se le muestran a un Componente puntual ya NO es "todas
	 * siempre" (decisión original de Fase 2, revertida tras evidencia
	 * real de fricción — ver claves_estilo_relevantes()/PERFIL_* más
	 * abajo y la memoria de producto): eso lo filtra
	 * Sofia_Componente_Factory::schema_de() usando
	 * claves_estilo_relevantes(), nunca este método.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	final public static function schema_bloque_generico(): array {
		return array(
			'columnas'                     => array(
				'tipo'      => 'botones_numero',
				'etiqueta'  => __( 'Columnas', 'sofia-studio' ),
				'opciones'  => self::COLUMNAS_PERMITIDAS,
			),
			'color_fondo'                  => array(
				'tipo'     => 'color_token',
				'etiqueta' => __( 'Color de fondo de la sección', 'sofia-studio' ),
			),
			'color_borde'                  => array(
				'tipo'     => 'color_token',
				'etiqueta' => __( 'Color de borde', 'sofia-studio' ),
			),
			'radius'                       => array(
				'tipo'       => 'medida_token',
				'etiqueta'   => __( 'Radio de borde', 'sofia-studio' ),
				'categoria'  => 'radius',
			),
			'sombra'                       => array(
				'tipo'              => 'medida_token',
				'etiqueta'          => __( 'Sombra', 'sofia-studio' ),
				'categoria'         => 'shadow',
				'con_unidad'        => false,
				'placeholder'       => 'ej. 0 2px 6px rgba(0,0,0,.15)',
			),
			'espaciado_vertical'           => array(
				'tipo'       => 'medida_token',
				'etiqueta'   => __( 'Espaciado vertical (arriba y abajo)', 'sofia-studio' ),
				'categoria'  => 'space',
				'con_unidad' => true,
			),
			'max_width'                    => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Ancho máximo', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'Sin límite', 'sofia-studio' ) ),
					array( 'valor' => 'site', 'etiqueta' => __( 'Ancho del sitio', 'sofia-studio' ) ),
					array( 'valor' => '100', 'etiqueta' => '100rem' ),
					array( 'valor' => '90', 'etiqueta' => '90rem' ),
					array( 'valor' => '80', 'etiqueta' => '80rem' ),
					array( 'valor' => '70', 'etiqueta' => '70rem' ),
					array( 'valor' => '60', 'etiqueta' => '60rem' ),
					array( 'valor' => '50', 'etiqueta' => '50rem' ),
					array( 'valor' => '40', 'etiqueta' => '40rem' ),
					array( 'valor' => '30', 'etiqueta' => '30rem' ),
					array( 'valor' => '20', 'etiqueta' => '20rem' ),
					array( 'valor' => '10', 'etiqueta' => '10rem' ),
				),
			),
			'ancho'                         => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Ancho', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'Por defecto', 'sofia-studio' ) ),
					array( 'valor' => 'full', 'etiqueta' => '100%' ),
					array( 'valor' => '90', 'etiqueta' => '90%' ),
					array( 'valor' => '80', 'etiqueta' => '80%' ),
					array( 'valor' => '70', 'etiqueta' => '70%' ),
					array( 'valor' => '60', 'etiqueta' => '60%' ),
					array( 'valor' => '50', 'etiqueta' => '50%' ),
					array( 'valor' => '40', 'etiqueta' => '40%' ),
					array( 'valor' => '30', 'etiqueta' => '30%' ),
					array( 'valor' => '20', 'etiqueta' => '20%' ),
					array( 'valor' => '10', 'etiqueta' => '10%' ),
					array( 'valor' => 'auto', 'etiqueta' => __( 'Automático', 'sofia-studio' ) ),
				),
			),
			'alineacion_bloque'             => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Alineación del bloque', 'sofia-studio' ),
				'ayuda'    => __( 'Solo tiene efecto visible si además elegiste un Ancho o Ancho máximo menor al 100% — un bloque de ancho completo no tiene espacio de sobra para desplazarse.', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'Por defecto (izquierda)', 'sofia-studio' ) ),
					array( 'valor' => 'center', 'etiqueta' => __( 'Centrado', 'sofia-studio' ) ),
					array( 'valor' => 'right', 'etiqueta' => __( 'Derecha', 'sofia-studio' ) ),
				),
			),
			'offset_x'                      => array(
				'tipo'       => 'medida_token',
				'etiqueta'   => __( 'Desplazamiento horizontal', 'sofia-studio' ),
				'ayuda'      => __( 'Se suma a la Alineación del bloque de arriba — ej. "Centrado" + 20px queda centrado y corrido 20px más a la derecha desde ese centro.', 'sofia-studio' ),
				'categoria'  => 'space',
				'con_unidad' => true,
			),
			'alineacion_contenido'          => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Alineación del contenido', 'sofia-studio' ),
				'ayuda'    => __( 'Solo tiene efecto visible en bloques con una lista de items (ej. Franja de beneficios, Testimonios) — alinea el texto DENTRO de cada columna, a diferencia de "Alineación del bloque" que mueve el bloque entero en la página.', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'Por defecto (izquierda)', 'sofia-studio' ) ),
					array( 'valor' => 'center', 'etiqueta' => __( 'Centrado', 'sofia-studio' ) ),
					array( 'valor' => 'right', 'etiqueta' => __( 'Derecha', 'sofia-studio' ) ),
				),
			),
			'alineacion_vertical_contenido' => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Alineación vertical del contenido', 'sofia-studio' ),
				'ayuda'    => __( 'Útil cuando los items tienen alturas distintas (ej. un título más largo que otro) — mismo alcance que la Alineación del contenido de arriba.', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'Por defecto (arriba)', 'sofia-studio' ) ),
					array( 'valor' => 'middle', 'etiqueta' => __( 'Al medio', 'sofia-studio' ) ),
					array( 'valor' => 'bottom', 'etiqueta' => __( 'Abajo', 'sofia-studio' ) ),
				),
			),
			'aspect_ratio'                  => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Relación de aspecto', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'Ninguna', 'sofia-studio' ) ),
					array( 'valor' => '1', 'etiqueta' => '1:1 (cuadrado)' ),
					array( 'valor' => '16-9', 'etiqueta' => '16:9' ),
					array( 'valor' => '9-16', 'etiqueta' => '9:16' ),
					array( 'valor' => '4-3', 'etiqueta' => '4:3' ),
					array( 'valor' => '3-4', 'etiqueta' => '3:4' ),
					array( 'valor' => '3-2', 'etiqueta' => '3:2' ),
					array( 'valor' => '2-3', 'etiqueta' => '2:3' ),
				),
			),
			'object_fit'                    => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Ajuste de imagen/video (object-fit)', 'sofia-studio' ),
				'ayuda'    => __( 'Solo tiene efecto si este bloque es o contiene una <img>/<video> directa — no aplica a un color/imagen de fondo (background-image usa otra propiedad, no object-fit).', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'Por defecto', 'sofia-studio' ) ),
					array( 'valor' => 'cover', 'etiqueta' => __( 'Cubrir (recorta)', 'sofia-studio' ) ),
					array( 'valor' => 'contain', 'etiqueta' => __( 'Contener (sin recortar)', 'sofia-studio' ) ),
					array( 'valor' => 'fill', 'etiqueta' => __( 'Estirar', 'sofia-studio' ) ),
				),
			),
			'z_index'                       => array(
				'tipo'     => 'select',
				'etiqueta' => __( 'Z-index (superposición)', 'sofia-studio' ),
				'opciones' => array(
					array( 'valor' => '', 'etiqueta' => __( 'Por defecto', 'sofia-studio' ) ),
					array( 'valor' => '-1', 'etiqueta' => '-1 (detrás)' ),
					array( 'valor' => '0', 'etiqueta' => '0' ),
					array( 'valor' => '1', 'etiqueta' => '1' ),
					array( 'valor' => '10', 'etiqueta' => '10' ),
					array( 'valor' => '100', 'etiqueta' => '100' ),
					array( 'valor' => '1000', 'etiqueta' => '1000' ),
					array( 'valor' => '10000', 'etiqueta' => __( '10000 (siempre encima)', 'sofia-studio' ) ),
				),
			),
		);
	}

	/**
	 * PERFIL_SECCION / PERFIL_PRIMITIVA / PERFIL_LISTA: 3 subconjuntos
	 * reusables de schema_bloque_generico() — decisión de arquitectura
	 * tomada con el usuario tras evidencia real de fricción de edición
	 * (ver la memoria de producto, conversación "no me gusta lo que se
	 * está haciendo"): mostrar los 15 controles genéricos COMPLETOS a
	 * cualquier tipo (el comportamiento original de Fase 2, ver el
	 * comentario ahora desactualizado en schema_bloque_generico() de
	 * arriba) resultó en un drawer "tipo Elementor de 200 opciones" para
	 * un Button o una Image, donde Object-fit/Aspect-ratio/Columnas no
	 * significan nada — el usuario tenía que scrollear controles
	 * irrelevantes para llegar a los 2-3 que sí le servían.
	 *
	 * Un Componente nuevo (Card, Accordion, Carousel — el catálogo de
	 * contenido/UI que el plan original ya identificaba como huecos
	 * reales) NO necesita enumerar controles uno por uno: elige el
	 * perfil que mejor describe QUÉ ES (una sección de contenido, una
	 * primitiva visual simple, o algo con lista repetible interna) hacer
	 * override de claves_estilo_relevantes() con UNA línea — ver el
	 * comentario largo ahí.
	 *
	 * PERFIL_SECCION: cualquier bloque que es "una sección visual
	 * completa" — posición/tamaño en la página + controles de CAJA
	 * (fondo, borde, sombra, padding vertical, superposición). Es el
	 * comportamiento ORIGINAL de Fase 2 (todos los genéricos), ahora
	 * como un perfil explícito en vez del único comportamiento posible.
	 */
	protected const PERFIL_SECCION = array(
		'ancho', 'max_width', 'alineacion_bloque', 'offset_x',
		'color_fondo', 'color_borde', 'radius', 'sombra', 'espaciado_vertical', 'z_index',
	);

	/**
	 * PERFIL_PRIMITIVA: bloques que son una pieza visual SIMPLE, sin caja
	 * de sección propia (Image, Button, y a futuro Icon) — solo
	 * posición/tamaño en la página. Sin fondo/borde/sombra/padding/
	 * z-index propios: un botón no necesita su propio color de fondo de
	 * SECCIÓN (eso ya lo resuelve el estilo de Nivel 1 del texto/enlace
	 * en sí), mostrarle esos 6 controles era ruido puro.
	 */
	protected const PERFIL_PRIMITIVA = array( 'ancho', 'max_width', 'alineacion_bloque', 'offset_x' );

	/**
	 * PERFIL_LISTA: PERFIL_SECCION + los 3 controles de GRILLA — columnas
	 * + alineación horizontal/vertical del contenido DENTRO de cada
	 * columna.
	 *
	 * REGLA para usarlo, más estricta que "el Componente tiene items":
	 * solo lo declara un Componente que renderiza una GRILLA REAL de
	 * columnas Y tiene el CSS que consume esas 3 claves. Hoy eso es
	 * exactamente Franja de beneficios y Testimonios — los selectores de
	 * alineación (.sofia-franja-beneficios.items-*, .sofia-testimonios
	 * .items-*) y el consumo de --sofia-columnas están escritos a mano
	 * para esos dos, no de forma genérica.
	 *
	 * Bug real que motivó esta regla: List, Nav y Breadcrumb lo declararon
	 * por analogía ("tienen lista de items") en la Fase 4, y mostraban 3
	 * controles que no hacían nada en el drawer — justo el ruido que los
	 * perfiles existen para evitar. Los tres pasaron a PERFIL_SECCION.
	 * Un Componente nuevo con lista pero sin grilla (o con grilla propia
	 * resuelta por utility classes de Core Framework) va a PERFIL_SECCION.
	 */
	protected const PERFIL_LISTA = array(
		'ancho', 'max_width', 'alineacion_bloque', 'offset_x',
		'color_fondo', 'color_borde', 'radius', 'sombra', 'espaciado_vertical', 'z_index',
		'columnas', 'alineacion_contenido', 'alineacion_vertical_contenido',
	);

	/**
	 * PERFIL_IMAGEN: PERFIL_PRIMITIVA + los 2 controles que solo tienen
	 * sentido donde hay una <img>/<video> real de por medio (Image, y
	 * Hero por tener su propia imagen) — relación de aspecto + ajuste
	 * (object-fit).
	 */
	protected const PERFIL_IMAGEN = array( 'ancho', 'max_width', 'alineacion_bloque', 'offset_x', 'aspect_ratio', 'object_fit' );

	/**
	 * claves_estilo_relevantes(): QUÉ subconjunto de
	 * schema_bloque_generico() (15 claves totales) le corresponde a ESTE
	 * Componente — default PERFIL_SECCION (mismo comportamiento que
	 * antes de este cambio, ningún Componente existente que no haga
	 * override pierde ningún control que ya tuviera). Un Componente con
	 * necesidades propias hace override eligiendo uno de los PERFIL_*
	 * de arriba (o, en un caso realmente atípico, una lista de claves a
	 * mano) — ver class-image.php/class-button.php para los primeros 2
	 * casos reales.
	 *
	 * Consumido por Sofia_Componente_Factory::schema_de()/
	 * schema_estilo_ia_de() para FILTRAR schema_bloque_generico() antes
	 * de fusionar con schema_propio() — el genérico completo (con sus 15
	 * claves) sigue siendo `final` y sin cambios, este método decide
	 * cuáles de esas 15 se muestran, nunca inventa controles nuevos (eso
	 * sigue siendo trabajo de schema_propio()).
	 *
	 * @return string[]
	 */
	public static function claves_estilo_relevantes(): array {
		return self::PERFIL_SECCION;
	}

	/**
	 * schema_propio(): controles de Nivel 2 EXTRA que este Componente
	 * agrega sobre schema_bloque_generico() — vacío por defecto (la
	 * mayoría del catálogo, Hero/CTA/FAQ/etc., no necesita ningún control
	 * propio, todo lo cubre el genérico). Sofia_Componente_Container es
	 * el primer caso real (dirección/gap/grilla, ver ahí) — un Componente
	 * nuevo con necesidades propias hace override de este método, nunca
	 * de schema_bloque_generico() (final).
	 *
	 * Mismo shape de entrada que schema_bloque_generico() — fusionado por
	 * Sofia_Componente_Factory::schema_de(), propio SIEMPRE después del
	 * genérico para que aparezca al final del drawer.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function schema_propio(): array {
		return array();
	}

	/**
	 * schema_contenido(): declara los campos de CONTENIDO de este
	 * Componente (título, texto, imagen, url, lista repetible) — distinto
	 * de schema_bloque_generico()/schema_propio() (Nivel 2, ESTILO visual
	 * de la caja/bloque completo): esto describe qué CAMPOS existen y de
	 * qué TIPO son, no cómo se ven. Agregado en la Fase 4 del plan de
	 * "primitivas de layout" (memoria de producto) — el generador de
	 * árboles por IA necesita saber, para cada tipo de bloque, "Hero tiene
	 * título (texto) e imagen (imagen)" antes de poder llenar props de
	 * contenido con sentido, y el validador/reparador del lado PHP
	 * (Sofia_REST_Editor::validar_y_reparar_arbol(), ver ia/generar) lo usa
	 * para descartar cualquier prop con una clave que el Componente real no
	 * declara.
	 *
	 * Vacío por defecto (mismo criterio que schema_propio()) — un
	 * Componente sin campos de contenido propios (hoy solo Container, cuyo
	 * "contenido" es enteramente su árbol de $hijos, ya cubierto por el
	 * modelo recursivo {id,tipo,hijos} y no por props) simplemente no hace
	 * override, en vez de tener que devolver un array vacío explícito.
	 *
	 * Shape de cada entrada: {tipo: 'texto'|'texto_largo'|'imagen'|'url'|'lista',
	 * etiqueta: string, ...}. Una entrada de tipo 'lista' además lleva
	 * 'campos' con el MISMO shape recursivo (ej. items de Franja de
	 * beneficios: cada item tiene 'titulo' y 'texto', ambos declarados con
	 * su propio {tipo, etiqueta}) — un solo nivel de anidamiento alcanza
	 * hoy (ninguna lista real del catálogo tiene una lista DENTRO de otra
	 * lista), así que no se generaliza más allá de lo que existe.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function schema_contenido(): array {
		return array();
	}
}

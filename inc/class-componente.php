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
	 * Construye un Componente ya con su tipo, ID de instancia y props
	 * resueltas — usado por Sofia_Componente_Factory::crear(), nunca
	 * instanciado directo.
	 *
	 * @param array<string,mixed> $props
	 */
	final public function __construct( string $tipo, string $id, array $props = array() ) {
		$this->tipo  = $tipo;
		$this->id    = $id;
		$this->props = array_merge( $this->props_por_defecto(), $props );
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
	 * un ATRIBUTO en la propia sección, nunca un <div> envolvente: Muuri
	 * reconoce sus ítems por selector "section" hijo DIRECTO de
	 * .sofia-pagina (ver activarReordenar() en editor-iframe.js), un
	 * wrapper extra rompería ese matching. page.php ya decidió SI
	 * renderizar el bloque (visita pública: no se renderiza en absoluto si
	 * no es visible) — acá solo se agrega la marca cuando corresponde.
	 */
	protected function atributos_seccion( string $clase_base ): string {
		$oculto = ( ! $this->bloque_visible() && class_exists( 'Sofia_Modo_Editor' ) && Sofia_Modo_Editor::activo() )
			? 'data-sofia-oculto-condicion="Oculto: condición no cumplida"'
			: '';
		$clases = trim( $clase_base . ' ' . $this->clases_utilitarias_bloque() );
		return sprintf(
			'class="%s" data-sofia-bloque-id="%s" data-sofia-bloque-tipo="%s" %s %s',
			esc_attr( $clases ),
			esc_attr( $this->id ),
			esc_attr( $this->tipo ),
			$this->atributo_estilo_bloque(),
			$oculto
		);
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

			if ( str_starts_with( $valor, Sofia_Estilo_Global::PREFIJO_TOKEN_CORE_FRAMEWORK ) ) {
				$declaraciones[]        = $propiedad_css . ':' . Sofia_Estilo_Global::resolver_valor( $valor );
				$tokens_crudos[ $clave ] = $valor;
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

		if ( ! empty( $estilo['espaciado_vertical'] ) && is_string( $estilo['espaciado_vertical'] ) && preg_match( self::PATRON_MEDIDA_CSS, $estilo['espaciado_vertical'] ) ) {
			$valor            = esc_attr( $estilo['espaciado_vertical'] );
			$declaraciones[] = 'padding-top:' . $valor;
			$declaraciones[] = 'padding-bottom:' . $valor;
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

			// tamano_fuente es INPUT LIBRE del usuario (ver DrawerEstilo.jsx)
			// — a diferencia del resto (opciones fijas elegidas por el
			// drawer), necesita validarse como una medida CSS real antes de
			// emitirse, no solo escaparse.
			if ( 'tamano_fuente' === $clave && ! preg_match( self::PATRON_MEDIDA_CSS, $valor ) ) {
				continue;
			}

			// "color" puede ser un token de Core Framework
			// ("cf:{nombre}", mismo mecanismo que Sofia_Estilo_Global —
			// PREFIJO_TOKEN_CORE_FRAMEWORK/resolver_valor() reusados tal
			// cual, nunca duplicados) en vez de un hex fijo elegido con el
			// <input type="color"> del drawer.
			if ( 'color' === $clave && str_starts_with( $valor, Sofia_Estilo_Global::PREFIJO_TOKEN_CORE_FRAMEWORK ) ) {
				$declaraciones[]        = $propiedad_css . ':' . Sofia_Estilo_Global::resolver_valor( $valor );
				$tokens_crudos[ $clave ] = $valor;
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
			// data-sofia-estilo-color — nombre derivado de la clave, un
			// atributo por propiedad que SÍ puede ser token (hoy solo
			// "color" en Nivel 1).
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
}

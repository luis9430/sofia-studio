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
	 * Atributos data-sofia-bloque-id/data-sofia-bloque-tipo en la <section>
	 * raíz de este Componente — editor-iframe.js los lee directo (en vez
	 * de "adivinar" el tipo a partir del primer data-sofia-campo, que ya
	 * no lo contiene desde que atributo_editable() usa el ID) para
	 * reconstruir {id, tipo} de cada bloque al reordenar/eliminar un
	 * bloque de nivel superior — ver activarReordenar()/alEliminarBloque().
	 * Cada Componente debe usar esto al abrir su <section>, ej.:
	 *   '<section class="sofia-hero" ' . $this->atributos_seccion() . '>'
	 */
	protected function atributos_seccion(): string {
		return sprintf(
			'data-sofia-bloque-id="%s" data-sofia-bloque-tipo="%s"',
			esc_attr( $this->id ),
			esc_attr( $this->tipo )
		);
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
	 * arbitrario, solo lo que el drawer de estilo (Nivel 3, panel Preact)
	 * realmente ofrece como control. Clave = nombre guardado en el JSON,
	 * valor = propiedad CSS real a emitir (permite que ambos difieran si
	 * hiciera falta — hoy coinciden).
	 */
	private const ESTILOS_CAMPO_PERMITIDOS = array(
		'alineacion'    => 'text-align',
		'color'         => 'color',
		'tamano_fuente' => 'font-size',
		'negrita'       => 'font-weight',
		'color_fondo'   => 'background-color',
		'sombra_texto'  => 'text-shadow',
	);

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
		foreach ( self::ESTILOS_CAMPO_PERMITIDOS as $clave => $propiedad_css ) {
			if ( empty( $estilo[ $clave ] ) || ! is_string( $estilo[ $clave ] ) ) {
				continue;
			}
			// esc_attr() sobre el VALOR completo de cada propiedad —
			// suficiente porque los valores vienen de un swatch/alineación
			// controlados por el drawer (nunca texto libre del usuario),
			// pero se sanea igual por si el JSON llegara manipulado.
			$declaraciones[] = $propiedad_css . ':' . esc_attr( $estilo[ $clave ] );
		}

		if ( empty( $declaraciones ) ) {
			return '';
		}
		return 'style="' . implode( ';', $declaraciones ) . '"';
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

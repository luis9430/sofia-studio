<?php
/**
 * Sofia_Componente_Generado — un Componente cuya forma viene de DATOS, no
 * de una clase PHP escrita a mano.
 *
 * Esta es la pieza central de la Fase 1 del plan "componentes generativos"
 * (ver el documento de arquitectura): hoy la IA elige entre los 39 tipos
 * del catálogo, y por eso "hace siempre lo mismo" — si le piden un hero en
 * diagonal, no tiene ninguna pieza que sepa dibujar diagonales. La salida
 * es que pueda DESCRIBIR un Componente nuevo y que ese Componente entre al
 * catálogo del sitio como cualquier otro.
 *
 * DECISIÓN DE SEGURIDAD, la más importante de todo el diseño: no se genera
 * ni se ejecuta PHP. Una clase PHP escrita por un modelo correría con los
 * permisos de WordPress — base de datos, archivos, todo — y un modelo
 * equivocándose (ni hablar de un prompt malicioso) haría daño real en el
 * sitio de un cliente. Acá no hay nada que ejecutar: la descripción es
 * HTML y CSS, este archivo los INTERPRETA, y el único código que corre es
 * este, escrito a mano y revisado.
 *
 * El HTML se sanitiza contra una whitelist de etiquetas y atributos (ver
 * ETIQUETAS_ESTRUCTURA) y el CSS contra un validador que solo acepta
 * declaraciones acotadas al propio componente (ver
 * Sofia_CSS_Generado::sanitizar). Ninguno de los dos se evalúa.
 *
 * Por qué una sola clase y no un archivo PHP por componente: un archivo
 * generado habría que escribirlo en disco, cargarlo con require_once y
 * mantenerlo sincronizado con la base de datos; además el tema se
 * despliega por ZIP desde origin/main, así que cualquier archivo escrito
 * en runtime se pierde al reinstalar. Una descripción en la base de datos
 * no tiene ninguno de esos problemas.
 *
 * Lo que hace que esto funcione sin tocar el editor: el editor visual
 * descubre los campos editables leyendo data-sofia-campo del DOM (ver
 * Sofia_Componente::atributo_editable) — nunca inspecciona la clase PHP.
 * Un Componente generado que emita esos atributos es editable
 * exactamente igual que uno escrito a mano, sin un solo cambio en
 * editor-iframe.js.
 */
class Sofia_Componente_Generado extends Sofia_Componente {

	/**
	 * La descripción que define a ESTE componente — ver
	 * Sofia_Definicion_Generada para su forma y su validación.
	 *
	 * @var array<string,mixed>
	 */
	private array $definicion = array();

	/**
	 * definir(): le da su forma a esta instancia, después de construida.
	 *
	 * El constructor de Sofia_Componente es `final` a propósito — todos
	 * los Componentes se construyen igual, y eso es una garantía que vale
	 * la pena mantener. Así que la definición entra por acá en vez de por
	 * un quinto parámetro del constructor.
	 *
	 * La secuencia (construir, después definir) la encapsula el factory,
	 * así que nadie más ve este paso extra. Si por algún motivo no se
	 * llamara, render() devuelve "" en vez de romper — mismo criterio que
	 * un tipo desconocido, que se omite en silencio.
	 *
	 * @param array<string,mixed> $definicion Ya validada por
	 *                                        Sofia_Definicion_Generada::validar().
	 */
	public function definir( array $definicion ): void {
		$this->definicion = $definicion;
		// props_por_defecto() corre en el constructor, cuando este objeto
		// todavía no sabía qué campos tiene — así que los defaults se
		// completan recién acá, sin pisar lo que el usuario ya guardó.
		foreach ( $this->props_por_defecto() as $clave => $valor ) {
			if ( ! isset( $this->props[ $clave ] ) || '' === $this->props[ $clave ] ) {
				$this->props[ $clave ] = $valor;
			}
		}
	}

	public function nombre(): string {
		return (string) ( $this->definicion['nombre'] ?? $this->tipo );
	}

	/**
	 * props_por_defecto(): sale de los campos declarados. Un componente
	 * recién insertado tiene que mostrar algo — un bloque vacío no se
	 * puede clickear para empezar a editarlo, que es un bug real que ya
	 * apareció en este catálogo con Video y Avatar.
	 */
	protected function props_por_defecto(): array {
		$props = array();
		foreach ( $this->definicion['campos'] ?? array() as $clave => $campo ) {
			if ( 'lista' === $campo['tipo'] ) {
				$props[ $clave ] = $this->items_por_defecto( $campo );
				continue;
			}
			$props[ $clave ] = (string) ( $campo['por_defecto'] ?? '' );
		}
		return $props;
	}

	/**
	 * Los items con los que arranca una lista.
	 *
	 * Salen de la descripción, que ya los dejó listos (ver
	 * Sofia_Definicion_Generada::items_por_defecto): si el modelo propuso
	 * items concretos se usan esos, y si no, esa función arma el relleno.
	 *
	 * Esta versión duplicaba el "por_defecto" de cada subcampo para armar
	 * dos items idénticos, y eso produjo un bug visible: una tarjeta de
	 * precios generada por IA mostraba "Soporte 24/7" dos veces, mientras
	 * las tres características que el modelo había propuesto se
	 * descartaban antes de llegar acá.
	 *
	 * @param array<string,mixed> $campo
	 * @return array<int,array<string,string>>
	 */
	private function items_por_defecto( array $campo ): array {
		$items = $campo['por_defecto'] ?? null;
		return is_array( $items ) && ! empty( $items ) ? $items : array();
	}

	/**
	 * Capa 1 — CONTENIDO. Se deriva de los campos declarados, que es
	 * exactamente lo que el panel del editor necesita para dibujar sus
	 * controles.
	 *
	 * Es un método de INSTANCIA acá y estático en el resto del catálogo:
	 * los 39 escritos a mano tienen un schema fijo por clase, pero todos
	 * los generados comparten esta misma clase y cada uno tiene el suyo.
	 * Sofia_Componente_Factory sabe de esa diferencia (ver
	 * schema_contenido_de()).
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function schema_contenido_propio(): array {
		$schema = array();
		foreach ( $this->definicion['campos'] ?? array() as $clave => $campo ) {
			$entrada = array(
				'tipo'     => $campo['tipo'],
				'etiqueta' => $campo['etiqueta'],
			);
			if ( 'lista' === $campo['tipo'] ) {
				$entrada['campos'] = array();
				foreach ( $campo['campos'] as $sub_clave => $sub ) {
					$entrada['campos'][ $sub_clave ] = array(
						'tipo'     => $sub['tipo'],
						'etiqueta' => $sub['etiqueta'],
					);
				}
			}
			$schema[ $clave ] = $entrada;
		}
		return $schema;
	}

	/**
	 * Capa 3 — CAJA. PERFIL_SECCION para todos los generados: son
	 * secciones visuales completas, con su propio fondo y espaciado.
	 *
	 * No se deriva de la descripción a propósito. Los perfiles existen
	 * para NO ofrecer controles que el bloque no respeta (el defecto que
	 * dejó 3 controles muertos en List/Nav/Breadcrumb), y no hay forma de
	 * saber desde una descripción si su CSS respeta, por ejemplo, el
	 * control de columnas. PERFIL_SECCION es el conjunto que cualquier
	 * sección honra por venir de atributos_seccion().
	 */
	public static function claves_estilo_relevantes(): array {
		return parent::PERFIL_SECCION;
	}

	/**
	 * render(): recorre la estructura declarada y emite el HTML.
	 *
	 * La estructura es un ÁRBOL DE DATOS, no una cadena de HTML con
	 * marcadores que haya que parsear. Parsear HTML de un modelo para
	 * después reemplazar marcadores es donde aparecen los agujeros
	 * (etiquetas sin cerrar, atributos inyectados, marcadores dentro de un
	 * atributo). Con un árbol, cada nodo se emite con etiquetas y
	 * atributos que salen de una whitelist, y el texto siempre escapado.
	 */
	public function render(): string {
		if ( empty( $this->definicion ) ) {
			return '';
		}
		$clase = 'sofia-gen sofia-gen--' . $this->tipo;
		$html  = '<section ' . $this->atributos_seccion( $clase ) . '>';
		foreach ( $this->definicion['estructura'] as $nodo ) {
			$html .= $this->render_nodo( $nodo );
		}
		$html .= '</section>';
		return $html;
	}

	/**
	 * render_nodo(): un nodo del árbol.
	 *
	 * @param array<string,mixed>  $nodo
	 * @param array<string,string> $contexto Valores del item de lista en
	 *                                       curso, si estamos dentro de
	 *                                       uno — ver render_lista().
	 * @param string               $prefijo  Ruta del campo dentro del item
	 *                                       ("items.0."), vacía fuera de
	 *                                       una lista.
	 */
	private function render_nodo( array $nodo, array $contexto = array(), string $prefijo = '' ): string {
		if ( isset( $nodo['lista'] ) ) {
			return $this->render_lista( $nodo );
		}

		$etiqueta = $nodo['etiqueta'];
		$atributos = $this->atributos_de_nodo( $nodo, $prefijo );

		// Etiquetas vacías (img, br, hr) no llevan contenido ni cierre.
		if ( in_array( $etiqueta, self::ETIQUETAS_VACIAS, true ) ) {
			return '<' . $etiqueta . $atributos . '>';
		}

		$interior = '';
		if ( isset( $nodo['campo'] ) ) {
			$interior = $this->valor_de_campo( $nodo['campo'], $contexto );
		}
		foreach ( $nodo['hijos'] ?? array() as $hijo ) {
			$interior .= $this->render_nodo( $hijo, $contexto, $prefijo );
		}

		return '<' . $etiqueta . $atributos . '>' . $interior . '</' . $etiqueta . '>';
	}

	/**
	 * render_lista(): el nodo que se repite por cada item.
	 *
	 * El contrato de listas del editor es estricto y ya rompió una vez
	 * (Tabs, que perdía el texto de los paneles): notificarListaActualizada()
	 * reconstruye cada item leyendo los [data-sofia-campo] que encuentra
	 * ADENTRO de su [data-sofia-item]. Por eso el atributo de item va en
	 * el nodo que se repite y los campos salen con la ruta completa
	 * "items.{i}.{campo}" — un campo que quede fuera de su item se pierde
	 * al editar cualquier otro.
	 *
	 * @param array<string,mixed> $nodo
	 */
	private function render_lista( array $nodo ): string {
		$campo_lista = $nodo['lista'];
		$items       = is_array( $this->props[ $campo_lista ] ?? null ) ? $this->props[ $campo_lista ] : array();

		$atributos = $this->atributos_de_nodo( $nodo, '' );
		$html      = '<' . $nodo['etiqueta'] . $atributos . ' ' . $this->atributo_lista( $campo_lista ) . '>';

		foreach ( array_values( $items ) as $indice => $item ) {
			$contexto = is_array( $item ) ? $item : array();
			$prefijo  = $campo_lista . '.' . $indice . '.';
			foreach ( $nodo['item'] as $hijo ) {
				$html .= $this->render_nodo_item( $hijo, $contexto, $prefijo, $indice );
			}
		}

		$html .= '</' . $nodo['etiqueta'] . '>';
		$html .= $this->boton_agregar_item( $campo_lista );
		return $html;
	}

	/**
	 * El nodo raíz de cada item lleva data-sofia-item; sus descendientes
	 * no. Separar este caso del render general evita que el atributo se
	 * repita en cada nivel del item, que rompería el conteo de items del
	 * editor.
	 *
	 * @param array<string,mixed>  $nodo
	 * @param array<string,string> $contexto
	 */
	private function render_nodo_item( array $nodo, array $contexto, string $prefijo, int $indice ): string {
		$etiqueta  = $nodo['etiqueta'];
		$atributos = $this->atributos_de_nodo( $nodo, $prefijo );

		if ( in_array( $etiqueta, self::ETIQUETAS_VACIAS, true ) ) {
			return '<' . $etiqueta . $atributos . ' ' . $this->atributo_item( $indice ) . '>';
		}

		$interior = '';
		if ( isset( $nodo['campo'] ) ) {
			$interior = $this->valor_de_campo( $nodo['campo'], $contexto );
		}
		foreach ( $nodo['hijos'] ?? array() as $hijo ) {
			$interior .= $this->render_nodo( $hijo, $contexto, $prefijo );
		}

		return '<' . $etiqueta . $atributos . ' ' . $this->atributo_item( $indice ) . '>' . $interior . '</' . $etiqueta . '>';
	}

	/**
	 * atributos_de_nodo(): class + los atributos del editor.
	 *
	 * La clase se prefija con "sg-" y se filtra a [a-z0-9_-] — así el CSS
	 * del componente no puede pisar una clase del tema ni una utility
	 * class de Core Framework. Es la misma razón por la que el CSS se
	 * acota al bloque (ver Sofia_CSS_Generado).
	 *
	 * @param array<string,mixed> $nodo
	 */
	private function atributos_de_nodo( array $nodo, string $prefijo ): string {
		$salida = '';

		if ( isset( $nodo['clase'] ) ) {
			$salida .= ' class="' . esc_attr( 'sg-' . $nodo['clase'] ) . '"';
		}

		if ( isset( $nodo['campo'] ) ) {
			$ruta = $prefijo . $nodo['campo'];
			$salida .= ' ' . $this->atributo_editable( $ruta );
			// atributo_estilo() habilita el Nivel 1 (tipografía y color de
			// ESE texto) — un componente generado lo gana gratis, igual
			// que cualquier campo de texto del catálogo.
			if ( 'img' !== $nodo['etiqueta'] ) {
				$salida .= ' ' . $this->atributo_estilo( $ruta );
			}
		}

		// Una imagen toma su src del campo, no su contenido de texto.
		if ( 'img' === $nodo['etiqueta'] && isset( $nodo['campo'] ) ) {
			$valor = $this->valor_crudo( $nodo['campo'] );
			$src   = $this->imagen_o_placeholder( $valor );
			$salida .= ' src="' . esc_url( $src ) . '" alt="" loading="lazy"';
		}

		return $salida;
	}

	/**
	 * El valor de un campo, ya escapado para emitir como contenido.
	 * texto_enriquecido() permite el formato básico que el editor produce
	 * (negrita, cursiva, enlaces) y descarta cualquier otra etiqueta.
	 *
	 * @param array<string,string> $contexto
	 */
	private function valor_de_campo( string $campo, array $contexto ): string {
		if ( array_key_exists( $campo, $contexto ) ) {
			return $this->texto_enriquecido( (string) $contexto[ $campo ] );
		}
		return $this->texto_enriquecido( (string) ( $this->props[ $campo ] ?? '' ) );
	}

	/** El valor sin escapar — solo para src de imagen, que pasa por esc_url(). */
	private function valor_crudo( string $campo ): string {
		return (string) ( $this->props[ $campo ] ?? '' );
	}

	/**
	 * ETIQUETAS_VACIAS: las que no llevan cierre. Un componente generado
	 * solo puede usar estas tres — el resto de la whitelist
	 * (ETIQUETAS_ESTRUCTURA en Sofia_Definicion_Generada) son todas
	 * contenedoras.
	 */
	private const ETIQUETAS_VACIAS = array( 'img', 'br', 'hr' );
}

/**
 * Imprime el CSS de los Componentes generados que este sitio tenga.
 *
 * Va inline en el <head> y no en un archivo encolado por dos razones: el
 * CSS es distinto por sitio (no hay un archivo estático que sirva para
 * todos), y ya está acotado al bloque por Sofia_CSS_Generado, así que no
 * puede afectar nada fuera de su propia sección.
 *
 * Se imprime SIEMPRE que haya definiciones, aunque la página en curso no
 * use ninguno de esos bloques. Filtrar por los tipos realmente presentes
 * exigiría recorrer la estructura de la página antes del <head>, y el
 * ahorro no lo justifica: son unos pocos kilobytes y el caso normal es
 * un sitio con cero componentes generados, donde esto no imprime nada.
 *
 * Prioridad 20: después de Sofia_Estilo_Global (que corre en la 10 por
 * defecto), para que el CSS generado pueda usar las custom properties de
 * la paleta del sitio — si se imprimiera antes, las variables todavía no
 * estarían definidas.
 */
function sofia_imprimir_css_generado(): void {
	if ( ! class_exists( 'Sofia_Cliente_GoPress' ) ) {
		return;
	}

	$css = '';
	foreach ( Sofia_Cliente_GoPress::obtener_componentes_generados() as $definicion ) {
		if ( '' !== ( $definicion['css'] ?? '' ) ) {
			$css .= $definicion['css'] . "\n";
		}
	}

	if ( '' === trim( $css ) ) {
		return;
	}

	// wp_strip_all_tags como última barrera: el CSS ya pasó por
	// Sofia_CSS_Generado (que descarta cualquier regla con "</"), pero
	// esto va DENTRO de un <style> y cerrar esa etiqueta es la única
	// forma de escapar de acá. Dos defensas para el mismo agujero, a
	// propósito.
	echo "<style id=\"sofia-componentes-generados\">\n" . wp_strip_all_tags( $css ) . "</style>\n";
}
add_action( 'wp_head', 'sofia_imprimir_css_generado', 20 );

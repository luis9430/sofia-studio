<?php
/**
 * Sofia_Definicion_Generada — valida la descripción de un Componente
 * generado antes de que llegue a renderizarse.
 *
 * Esta clase es la frontera de confianza del sistema generativo. Todo lo
 * que entra acá viene de afuera (hoy de un JSON escrito a mano, mañana de
 * un modelo) y nada de lo que sale puede depender de que el emisor se
 * haya portado bien.
 *
 * El criterio es whitelist en todos lados, nunca blacklist: se enumera lo
 * PERMITIDO y se descarta todo lo demás. Una blacklist de "etiquetas
 * peligrosas" siempre se queda corta — hay más formas de ejecutar
 * JavaScript de las que uno enumera de memoria (<script>, onclick,
 * javascript: en un href, <svg onload>, <iframe srcdoc>...). Enumerar las
 * ~15 etiquetas que un componente visual necesita es corto y no deja
 * lugar a sorpresas.
 *
 * validar() devuelve la descripción NORMALIZADA (con los defaults puestos
 * y lo desconocido sacado) o null si es inservible. Nunca lanza: una
 * descripción rota tiene que dejar el sitio en pie, igual que un tipo
 * desconocido en el factory se omite en silencio en vez de tumbar la
 * página entera.
 */
class Sofia_Definicion_Generada {

	/**
	 * ETIQUETAS_ESTRUCTURA: el vocabulario HTML completo de un componente
	 * generado.
	 *
	 * Están las que sirven para maquetar y dar semántica, y NO están las
	 * que ejecutan o traen contenido externo: script, style, iframe,
	 * object, embed, form, input, link, meta, base, svg. svg queda afuera
	 * aunque sea tentador para decoración — admite <script> adentro y
	 * atributos de evento, y sanitizarlo bien es un problema aparte.
	 *
	 * Tampoco está <a>: un enlace necesita href, y un href es una vía de
	 * ejecución (javascript:) y de phishing. Los enlaces se agregan cuando
	 * haya un campo de tipo url con su propia validación, no antes.
	 */
	private const ETIQUETAS_ESTRUCTURA = array(
		'div', 'section', 'article', 'header', 'footer', 'aside',
		'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
		'p', 'span', 'strong', 'em', 'small',
		'ul', 'ol', 'li', 'figure', 'figcaption', 'blockquote',
		'img', 'br', 'hr',
	);

	/** Los tipos de campo que el panel del editor sabe dibujar. */
	private const TIPOS_CAMPO = array( 'texto', 'texto_largo', 'imagen' );

	/**
	 * Límites. No son arbitrarios: existen para que una descripción
	 * absurda (un bucle en el modelo, un JSON inflado a propósito) no
	 * consuma memoria ni tiempo de render sin control.
	 */
	private const MAX_PROFUNDIDAD = 6;
	private const MAX_NODOS       = 80;
	private const MAX_CAMPOS      = 12;
	private const MAX_CSS         = 8000;

	/**
	 * validar(): la descripción normalizada, o null si no se puede usar.
	 *
	 * @param array<string,mixed> $bruto
	 * @param string[]            $avisos Se llena con lo que se descartó —
	 *                                    mismo patrón que la reparación
	 *                                    del estilo generado por IA: nada
	 *                                    se tira en silencio, o un bug se
	 *                                    vuelve invisible.
	 * @return array<string,mixed>|null
	 */
	public static function validar( array $bruto, array &$avisos = array() ): ?array {
		$tipo = self::slug( (string) ( $bruto['tipo'] ?? '' ) );
		if ( '' === $tipo ) {
			$avisos[] = 'La descripción no tiene un "tipo" válido (solo letras, números y guión bajo).';
			return null;
		}

		$nombre = trim( wp_strip_all_tags( (string) ( $bruto['nombre'] ?? '' ) ) );
		if ( '' === $nombre ) {
			$nombre = $tipo;
		}

		$campos = self::validar_campos( $bruto['campos'] ?? array(), $avisos );
		if ( empty( $campos ) ) {
			$avisos[] = 'La descripción no declara ningún campo editable válido.';
			return null;
		}

		$contador   = 0;
		$estructura = self::validar_nodos( $bruto['estructura'] ?? array(), $campos, 1, $contador, $avisos );
		if ( empty( $estructura ) ) {
			$avisos[] = 'La descripción no tiene una estructura válida.';
			return null;
		}

		$css = Sofia_CSS_Generado::sanitizar( (string) ( $bruto['css'] ?? '' ), $tipo, self::MAX_CSS, $avisos );
		self::avisar_variables_inexistentes( $css, $campos, $avisos );

		return array(
			'tipo'       => $tipo,
			'nombre'     => $nombre,
			'campos'     => $campos,
			'estructura' => $estructura,
			'css'        => $css,
		);
	}

	/**
	 * Avisa cuando el CSS usa var(--x) donde "x" es el nombre de un campo.
	 *
	 * Error real y repetido del generador por IA: declaraba un campo
	 * "color_circulo" y escribía background: var(--color_circulo) — una
	 * variable que nada define nunca, así que el elemento quedaba sin
	 * color de fondo y nadie entendía por qué.
	 *
	 * Es un intento razonable de hacer el diseño configurable, pero un
	 * campo es CONTENIDO: se imprime adentro de una etiqueta, no hay
	 * ningún mecanismo que lo conecte a una propiedad CSS.
	 *
	 * Solo avisa, no descarta la regla: la variable indefinida hace que
	 * esa propiedad se ignore, que es exactamente lo que pasaría igual. Y
	 * descartar la regla entera se llevaría puesto el resto de sus
	 * propiedades, que sí son válidas. Lo que hacía falta era que dejara
	 * de ser invisible.
	 *
	 * @param array<string,array<string,mixed>> $campos
	 * @param string[]                          $avisos
	 */
	private static function avisar_variables_inexistentes( string $css, array $campos, array &$avisos ): void {
		if ( '' === $css || ! preg_match_all( '/var\(\s*--([a-z0-9_-]+)/i', $css, $coincidencias ) ) {
			return;
		}

		foreach ( array_unique( $coincidencias[1] ) as $variable ) {
			// Las del sistema (--sofia-*) sí existen: las define
			// Sofia_Estilo_Global en el <head> de cada página.
			if ( 0 === strpos( $variable, 'sofia-' ) ) {
				continue;
			}
			if ( isset( $campos[ $variable ] ) ) {
				$avisos[] = sprintf(
					'El CSS usa var(--%1$s), pero "%1$s" es un campo de contenido, no una variable CSS — esa propiedad no va a tener efecto. Los colores y medidas van fijos en el CSS.',
					$variable
				);
			}
		}
	}

	/**
	 * Los campos editables. Un campo sin tipo conocido se descarta entero:
	 * el panel no sabría qué control dibujar, y un control que no existe es
	 * un campo que el cliente no puede editar — justo lo que este sistema
	 * viene a evitar.
	 *
	 * @param mixed    $bruto
	 * @param string[] $avisos
	 * @return array<string,array<string,mixed>>
	 */
	private static function validar_campos( $bruto, array &$avisos ): array {
		if ( ! is_array( $bruto ) ) {
			return array();
		}

		$campos = array();
		foreach ( $bruto as $clave_bruta => $campo ) {
			if ( count( $campos ) >= self::MAX_CAMPOS ) {
				$avisos[] = sprintf( 'Se descartaron campos: el máximo es %d.', self::MAX_CAMPOS );
				break;
			}
			$clave = self::slug( (string) $clave_bruta );
			if ( '' === $clave || ! is_array( $campo ) ) {
				continue;
			}

			$tipo = (string) ( $campo['tipo'] ?? 'texto' );

			if ( 'lista' === $tipo ) {
				$sub = self::validar_campos_de_item( $campo['campos'] ?? array(), $avisos );
				if ( empty( $sub ) ) {
					$avisos[] = sprintf( 'El campo lista "%s" no declara subcampos válidos — se descartó.', $clave );
					continue;
				}
				$campos[ $clave ] = array(
					'tipo'        => 'lista',
					'etiqueta'    => self::etiqueta( $campo, $clave ),
					'campos'      => $sub,
					'por_defecto' => self::items_por_defecto( $campo['por_defecto'] ?? null, $sub ),
				);
				continue;
			}

			if ( ! in_array( $tipo, self::TIPOS_CAMPO, true ) ) {
				$avisos[] = sprintf( 'El campo "%s" tiene el tipo "%s", que no existe — se descartó.', $clave, $tipo );
				continue;
			}

			$campos[ $clave ] = array(
				'tipo'        => $tipo,
				'etiqueta'    => self::etiqueta( $campo, $clave ),
				'por_defecto' => self::texto_plano( $campo['por_defecto'] ?? '' ),
			);
		}
		return $campos;
	}

	/**
	 * Los items con los que arranca una lista.
	 *
	 * Bug real, visible en el sitio: una tarjeta de precios generada por
	 * IA mostraba "Soporte 24/7" DOS VECES. Pasaba por dos razones que se
	 * sumaban — esta función descartaba el "por_defecto" de la lista (el
	 * modelo había mandado tres características distintas y se tiraban), y
	 * el Componente rellenaba el hueco duplicando el "por_defecto" de cada
	 * subcampo. El resultado era contenido repetido que el usuario tenía
	 * que corregir a mano en cada bloque que insertara.
	 *
	 * Ahora se respeta lo que el modelo propuso. Solo si no propuso nada
	 * se cae al relleno de dos items, que sigue siendo mejor que una lista
	 * vacía: sin ningún item el editor no tiene dónde dibujar el botón de
	 * agregar.
	 *
	 * @param mixed                              $bruto Lo que declaró la descripción.
	 * @param array<string,array<string,string>> $sub   Subcampos ya validados.
	 * @return array<int,array<string,string>>
	 */
	private static function items_por_defecto( $bruto, array $sub ): array {
		$items = array();

		if ( is_array( $bruto ) ) {
			foreach ( $bruto as $item ) {
				if ( ! is_array( $item ) || count( $items ) >= 10 ) {
					continue;
				}
				// Cada item se recorta a los subcampos DECLARADOS: una
				// clave de más viajaría al editor sin control que la
				// muestre, y al guardar se perdería igual.
				$limpio = array();
				foreach ( $sub as $clave => $definicion ) {
					$limpio[ $clave ] = self::texto_plano( $item[ $clave ] ?? '' );
				}
				$items[] = $limpio;
			}
		}

		if ( ! empty( $items ) ) {
			return $items;
		}

		// Relleno: dos items con el por_defecto de cada subcampo.
		$item = array();
		foreach ( $sub as $clave => $definicion ) {
			$item[ $clave ] = (string) ( $definicion['por_defecto'] ?? '' );
		}
		return array( $item, $item );
	}

	/**
	 * Los subcampos de una lista. Sin listas anidadas: el editor las
	 * reconstruye leyendo un solo nivel de [data-sofia-item], así que una
	 * lista dentro de otra se corrompería al editarla.
	 *
	 * @param mixed    $bruto
	 * @param string[] $avisos
	 * @return array<string,array<string,string>>
	 */
	private static function validar_campos_de_item( $bruto, array &$avisos ): array {
		if ( ! is_array( $bruto ) ) {
			return array();
		}

		$sub = array();
		foreach ( $bruto as $clave_bruta => $campo ) {
			$clave = self::slug( (string) $clave_bruta );
			if ( '' === $clave || ! is_array( $campo ) ) {
				continue;
			}
			$tipo = (string) ( $campo['tipo'] ?? 'texto' );
			if ( ! in_array( $tipo, self::TIPOS_CAMPO, true ) ) {
				$avisos[] = sprintf( 'El subcampo "%s" tiene un tipo inválido ("%s") — se descartó.', $clave, $tipo );
				continue;
			}
			$sub[ $clave ] = array(
				'tipo'        => $tipo,
				'etiqueta'    => self::etiqueta( $campo, $clave ),
				'por_defecto' => self::texto_plano( $campo['por_defecto'] ?? '' ),
			);
		}
		return $sub;
	}

	/**
	 * El árbol de estructura.
	 *
	 * Se valida con profundidad y cuenta de nodos porque es recursivo y
	 * viene de afuera: sin tope, una estructura muy anidada agota la pila,
	 * y una muy ancha el tiempo de render.
	 *
	 * @param mixed                             $bruto
	 * @param array<string,array<string,mixed>> $campos
	 * @param string[]                          $avisos
	 * @return array<int,array<string,mixed>>
	 */
	private static function validar_nodos( $bruto, array $campos, int $profundidad, int &$contador, array &$avisos ): array {
		if ( ! is_array( $bruto ) || $profundidad > self::MAX_PROFUNDIDAD ) {
			if ( $profundidad > self::MAX_PROFUNDIDAD ) {
				$avisos[] = sprintf( 'Se recortó la estructura: supera las %d capas de anidamiento.', self::MAX_PROFUNDIDAD );
			}
			return array();
		}

		$nodos = array();
		foreach ( $bruto as $nodo_bruto ) {
			if ( ! is_array( $nodo_bruto ) || $contador >= self::MAX_NODOS ) {
				if ( $contador >= self::MAX_NODOS ) {
					$avisos[] = sprintf( 'Se recortó la estructura: supera los %d elementos.', self::MAX_NODOS );
					break;
				}
				continue;
			}

			$etiqueta = strtolower( trim( (string) ( $nodo_bruto['etiqueta'] ?? '' ) ) );
			if ( ! in_array( $etiqueta, self::ETIQUETAS_ESTRUCTURA, true ) ) {
				$avisos[] = sprintf( 'Se descartó un elemento "%s": no está entre los permitidos.', $etiqueta );
				continue;
			}

			++$contador;
			$nodo = array( 'etiqueta' => $etiqueta );

			$clase = self::slug_clase( (string) ( $nodo_bruto['clase'] ?? '' ) );
			if ( '' !== $clase ) {
				$nodo['clase'] = $clase;
			}

			// Nodo de lista: se repite por cada item.
			$campo_lista = self::slug( (string) ( $nodo_bruto['lista'] ?? '' ) );
			if ( '' !== $campo_lista ) {
				if ( ! isset( $campos[ $campo_lista ] ) || 'lista' !== $campos[ $campo_lista ]['tipo'] ) {
					$avisos[] = sprintf( 'Un nodo apunta a la lista "%s", que no está declarada como campo — se descartó.', $campo_lista );
					continue;
				}
				$item = self::validar_nodos( $nodo_bruto['item'] ?? array(), $campos, $profundidad + 1, $contador, $avisos );
				if ( empty( $item ) ) {
					$avisos[] = sprintf( 'La lista "%s" no tiene un item válido — se descartó.', $campo_lista );
					continue;
				}
				$nodo['lista'] = $campo_lista;
				$nodo['item']  = $item;
				$nodos[]       = $nodo;
				continue;
			}

			// Nodo con campo: tiene que apuntar a uno declarado. Si no, el
			// editor mostraría un campo editable que no existe en el
			// schema y el guardado lo descartaría en silencio.
			$campo = self::slug( (string) ( $nodo_bruto['campo'] ?? '' ) );
			if ( '' !== $campo ) {
				if ( ! self::campo_existe( $campo, $campos ) ) {
					$avisos[] = sprintf( 'Un nodo apunta al campo "%s", que no está declarado — se ignoró ese vínculo.', $campo );
				} else {
					$nodo['campo'] = $campo;
				}
			}

			$hijos = self::validar_nodos( $nodo_bruto['hijos'] ?? array(), $campos, $profundidad + 1, $contador, $avisos );
			if ( ! empty( $hijos ) ) {
				$nodo['hijos'] = $hijos;
			}

			$nodos[] = $nodo;
		}
		return $nodos;
	}

	/**
	 * Un campo puede estar en el nivel de arriba o adentro de una lista.
	 *
	 * @param array<string,array<string,mixed>> $campos
	 */
	private static function campo_existe( string $campo, array $campos ): bool {
		if ( isset( $campos[ $campo ] ) ) {
			return true;
		}
		foreach ( $campos as $definicion ) {
			if ( 'lista' === $definicion['tipo'] && isset( $definicion['campos'][ $campo ] ) ) {
				return true;
			}
		}
		return false;
	}

	/** @param array<string,mixed> $campo */
	private static function etiqueta( array $campo, string $respaldo ): string {
		$etiqueta = trim( wp_strip_all_tags( (string) ( $campo['etiqueta'] ?? '' ) ) );
		return '' !== $etiqueta ? $etiqueta : ucfirst( str_replace( '_', ' ', $respaldo ) );
	}

	/** @param mixed $valor */
	private static function texto_plano( $valor ): string {
		return is_scalar( $valor ) ? wp_strip_all_tags( (string) $valor ) : '';
	}

	/** Identificador seguro: minúsculas, números y guión bajo. */
	private static function slug( string $valor ): string {
		return (string) preg_replace( '/[^a-z0-9_]/', '', strtolower( trim( $valor ) ) );
	}

	/** Igual pero admite guión medio, que es lo habitual en nombres de clase. */
	private static function slug_clase( string $valor ): string {
		return (string) preg_replace( '/[^a-z0-9_-]/', '', strtolower( trim( $valor ) ) );
	}
}

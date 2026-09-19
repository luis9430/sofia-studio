<?php
/**
 * Sofia_CSS_Generado — sanitiza y acota el CSS de un Componente generado.
 *
 * El CSS es donde vive toda la libertad visual del sistema generativo:
 * gradientes, clip-path, transform, superposiciones, formas. Es
 * deliberadamente lo MENOS restringido de la descripción, porque es lo
 * que separa un sitio que se ve diseñado de uno que se ve ordenado.
 *
 * Pero CSS no es inofensivo, y hay dos problemas distintos que resolver:
 *
 * 1. EJECUCIÓN Y FILTRACIÓN. url() puede traer un recurso externo (y
 *    filtrar la IP de cada visitante a un tercero), @import trae una hoja
 *    entera, expression() ejecutaba JS en IE viejo, y behavior/-moz-binding
 *    ataban código a un elemento. Se bloquean por patrón, y se bloquea
 *    también todo lo que se le parezca.
 *
 * 2. ESCAPE DEL BLOQUE. Un selector como "body" o "*" dejaría que un
 *    componente pinte toda la página, incluido el editor. Todo selector se
 *    reescribe anteponiendo la clase del bloque, así que ninguna regla
 *    puede alcanzar nada fuera de su propia sección.
 *
 * El punto 2 es el que hace que esto sea seguro de usar en un sitio real:
 * un componente generado mal no puede romper más que a sí mismo.
 *
 * Nota sobre el enfoque: esto no es un parser de CSS completo, y no
 * pretende serlo. Es un filtro conservador — ante la duda, descarta. Un
 * componente al que se le cae una regla se ve mal y se regenera; uno que
 * deja pasar algo peligroso es un problema real.
 */
class Sofia_CSS_Generado {

	/**
	 * PATRONES_PROHIBIDOS: si alguno aparece en la declaración, la regla
	 * entera se descarta.
	 *
	 * Se mira la declaración completa y no propiedad por propiedad porque
	 * estos pueden aparecer en cualquier valor — url() sirve en
	 * background, mask, cursor, content, border-image, y enumerarlas todas
	 * sería otra lista que se queda corta.
	 */
	private const PATRONES_PROHIBIDOS = array(
		'url(',        // recursos externos: filtran la IP del visitante
		'@import',     // trae una hoja entera de otro lado
		'expression',  // ejecutaba JS en IE
		'javascript:',
		'behavior',    // ataba un script a un elemento (IE)
		'-moz-binding',
		'</',          // cerrar la etiqueta <style> e inyectar HTML
		'<!--',
	);

	/**
	 * SELECTORES_PROHIBIDOS: partes de selector que alcanzan fuera del
	 * bloque aunque se les anteponga la clase del componente.
	 *
	 * ":root" y "html" son los casos claros — ".sofia-gen--x :root" no
	 * matchea nada, pero ":root" suelto redefinido por un componente
	 * pisaría los tokens de TODO el sitio si el prefijado fallara. Se
	 * descartan de entrada en vez de confiar en que el prefijado siempre
	 * los neutralice.
	 */
	private const SELECTORES_PROHIBIDOS = array( ':root', 'html', 'body' );

	/**
	 * sanitizar(): el CSS listo para emitir, ya acotado al bloque.
	 *
	 * @param string   $css    CSS crudo de la descripción.
	 * @param string   $tipo   Tipo del componente — da la clase que acota.
	 * @param int      $maximo Largo máximo aceptado.
	 * @param string[] $avisos Se llena con lo descartado.
	 */
	public static function sanitizar( string $css, string $tipo, int $maximo, array &$avisos = array() ): string {
		$css = trim( $css );
		if ( '' === $css ) {
			return '';
		}

		if ( strlen( $css ) > $maximo ) {
			$avisos[] = sprintf( 'El CSS supera los %d caracteres — se descartó entero.', $maximo );
			return '';
		}

		// Los comentarios se sacan antes de cualquier otra cosa: sirven
		// para partir un patrón prohibido en dos (ur/**/l(...)) y que el
		// filtro no lo vea.
		$css = (string) preg_replace( '#/\*.*?\*/#s', '', $css );

		$ambito = '.sofia-gen--' . preg_replace( '/[^a-z0-9_]/', '', strtolower( $tipo ) );
		$salida = array();

		// Se parte por bloques "selector { declaraciones }". Las at-rules
		// con bloques anidados (@media, @supports) no se soportan en esta
		// primera versión: anidan llaves y este partido simple no las
		// maneja bien. El componente igual es responsive con las unidades
		// relativas y el clamp() de CSS, que sí pasan.
		if ( false !== strpos( $css, '@media' ) ) {
			$avisos[] = 'El CSS usa @media, que todavía no está soportado — esas reglas se descartaron.';
		}

		preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $css, $reglas, PREG_SET_ORDER );

		foreach ( $reglas as $regla ) {
			$selector     = trim( $regla[1] );
			$declaracion  = trim( $regla[2] );

			if ( '' === $selector || '' === $declaracion ) {
				continue;
			}

			// Una at-rule que sobrevivió al partido (queda como parte del
			// selector) se descarta.
			if ( false !== strpos( $selector, '@' ) ) {
				$avisos[] = sprintf( 'Se descartó una regla con "@": %s', self::recortar( $selector ) );
				continue;
			}

			$declaracion_baja = strtolower( $declaracion );
			$prohibido        = false;
			foreach ( self::PATRONES_PROHIBIDOS as $patron ) {
				if ( false !== strpos( $declaracion_baja, $patron ) ) {
					$avisos[] = sprintf( 'Se descartó una regla que usa "%s": %s', $patron, self::recortar( $selector ) );
					$prohibido = true;
					break;
				}
			}
			if ( $prohibido ) {
				continue;
			}

			$selector_acotado = self::acotar_selector( $selector, $ambito, $avisos );
			if ( '' === $selector_acotado ) {
				continue;
			}

			$salida[] = $selector_acotado . ' { ' . $declaracion . ' }';
		}

		return implode( "\n", $salida );
	}

	/**
	 * acotar_selector(): antepone la clase del bloque a cada selector de
	 * la lista, de modo que ninguna regla salga de su propia sección.
	 *
	 * ".titulo, .pie" se vuelve ".sofia-gen--x .sg-titulo, .sofia-gen--x
	 * .sg-pie". El prefijo "sg-" coincide con el que pone el render (ver
	 * Sofia_Componente_Generado::atributos_de_nodo) — la descripción
	 * declara clases sin prefijo y las dos puntas lo agregan igual.
	 *
	 * @param string[] $avisos
	 */
	private static function acotar_selector( string $selector, string $ambito, array &$avisos ): string {
		$partes  = array_map( 'trim', explode( ',', $selector ) );
		$acotados = array();

		foreach ( $partes as $parte ) {
			if ( '' === $parte ) {
				continue;
			}

			$baja = strtolower( $parte );
			foreach ( self::SELECTORES_PROHIBIDOS as $prohibido ) {
				if ( false !== strpos( $baja, $prohibido ) ) {
					$avisos[] = sprintf( 'Se descartó el selector "%s": alcanza fuera del bloque.', self::recortar( $parte ) );
					continue 2;
				}
			}

			// El "&" de la descripción significa "el bloque mismo" — así
			// se puede estilar la sección entera (fondo, padding, altura)
			// y no solo sus hijos.
			//
			// Se resuelve ANTES del chequeo de caracteres a propósito:
			// "&" no es un carácter válido de un selector CSS, es una
			// convención de la descripción que se traduce acá. Ponerlo
			// después hacía que el chequeo lo descartara siempre, así que
			// no había forma de estilar la sección misma.
			if ( '&' === $parte ) {
				$acotados[] = $ambito;
				continue;
			}

			// Solo caracteres de selector conocidos. Deja pasar clases,
			// etiquetas, descendencia, hijo directo, pseudo-clases y
			// pseudo-elementos; corta cualquier cosa rara.
			if ( ! preg_match( '/^[a-zA-Z0-9_\-\s.:>()\[\]="\'+~#*]+$/', $parte ) ) {
				$avisos[] = sprintf( 'Se descartó un selector con caracteres no permitidos: %s', self::recortar( $parte ) );
				continue;
			}

			// Las clases de la descripción vienen sin prefijo; se les
			// agrega el mismo "sg-" que pone el render.
			$parte = (string) preg_replace( '/\.([a-z0-9_-]+)/i', '.sg-$1', $parte );

			$acotados[] = $ambito . ' ' . $parte;
		}

		return implode( ', ', $acotados );
	}

	private static function recortar( string $texto ): string {
		$texto = trim( preg_replace( '/\s+/', ' ', $texto ) );
		return strlen( $texto ) > 60 ? substr( $texto, 0, 57 ) . '...' : $texto;
	}
}

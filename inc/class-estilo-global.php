<?php
/**
 * Sofia_Estilo_Global imprime las custom properties de paleta/tipografía
 * del SITIO completo (Nivel 3 del editor — distinto del estilo por campo/
 * bloque de Sofia_Componente, que vive en el Contenido de UNA página) en
 * el <head> de cada visita pública, vía wp_head.
 *
 * Fuente de verdad: Sofia_Cliente_GoPress::obtener_estilo_global(), que
 * trae el JSON guardado en store.Sitio.EstiloGlobal del lado de GoPress —
 * nunca WordPress ni este tema son la fuente de verdad, mismo criterio que
 * el resto del contenido de Sofia Studio.
 *
 * Emite las custom properties SIEMPRE con el mismo nombre
 * (--sofia-color-N / --sofia-fuente-N), sin importar si el sitio configuró algo o no — un
 * sitio sin estilo global guardado simplemente no tiene ninguna regla que
 * las sobreescriba, así que valen los defaults ya declarados en style.css
 * (ver el bloque :root ahí). El drawer de campo/bloque (Sofia_Componente)
 * podrá referenciar estas mismas variables a futuro (ej. un swatch "usa el
 * acento del sitio" en vez de un color hex suelto) — ese enganche queda
 * fuera de esta pieza, es la integración con Core Framework pospuesta.
 */
class Sofia_Estilo_Global {

	/**
	 * COLORES_PERMITIDOS/FUENTES_PERMITIDAS: whitelist de qué claves del
	 * JSON de estilo global tienen efecto — mismo criterio que
	 * Sofia_Componente::ESTILOS_CAMPO_PERMITIDOS/FUENTES_PERMITIDAS: nunca
	 * CSS arbitrario, solo lo que el panel "Estilo global" del editor
	 * realmente ofrece como control. Clave = nombre guardado en el JSON,
	 * valor = nombre de la custom property CSS a emitir.
	 */
	private const COLORES_PERMITIDOS = array(
		'texto'        => '--sofia-color-texto',
		'texto_suave'  => '--sofia-color-texto-suave',
		'acento'       => '--sofia-color-acento',
		'fondo'        => '--sofia-color-fondo',
	);

	/**
	 * Mismas 2 fuentes que Sofia_Componente::FUENTES_PERMITIDAS — acá se
	 * declaran las custom properties que le dan NOMBRE a cada una
	 * (--sofia-fuente-display/--sofia-fuente-texto), que el CSS del tema
	 * (style.css) puede usar como default de toda la tipografía del sitio,
	 * sin que cada Componente tenga que declarar su propio font-family.
	 */
	private const FUENTES_PERMITIDAS = array(
		'display' => array( 'variable' => '--sofia-fuente-display', 'valor' => "'Fraunces', serif" ),
		'texto'   => array( 'variable' => '--sofia-fuente-texto', 'valor' => "'Inter', sans-serif" ),
	);

	/**
	 * Imprime <style>:root{...}</style> con las custom properties
	 * configuradas — string vacío (nada impreso) si el sitio no tiene
	 * estilo global guardado, mismo criterio de "no romper nada" que
	 * Sofia_Componente::atributo_estilo().
	 */
	public static function imprimir(): void {
		$estilo = Sofia_Cliente_GoPress::obtener_estilo_global();
		if ( empty( $estilo ) ) {
			return;
		}

		$declaraciones = array();

		$colores = is_array( $estilo['colores'] ?? null ) ? $estilo['colores'] : array();
		foreach ( self::COLORES_PERMITIDOS as $clave => $variable_css ) {
			if ( empty( $colores[ $clave ] ) || ! is_string( $colores[ $clave ] ) ) {
				continue;
			}
			$declaraciones[] = $variable_css . ':' . esc_attr( $colores[ $clave ] );
		}

		$tipografia = is_array( $estilo['tipografia'] ?? null ) ? $estilo['tipografia'] : array();
		foreach ( array( 'display', 'texto' ) as $rol ) {
			if ( empty( $tipografia[ $rol ] ) || ! isset( self::FUENTES_PERMITIDAS[ $tipografia[ $rol ] ] ) ) {
				continue;
			}
			$fuente          = self::FUENTES_PERMITIDAS[ $tipografia[ $rol ] ];
			$declaraciones[] = $fuente['variable'] . ':' . $fuente['valor'];
		}

		if ( empty( $declaraciones ) ) {
			return;
		}

		echo '<style id="sofia-estilo-global">:root{' . implode( ';', $declaraciones ) . '}</style>' . "\n";
	}
}

add_action( 'wp_head', array( 'Sofia_Estilo_Global', 'imprimir' ) );

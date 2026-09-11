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
	 * Prefijo que distingue "este color es una REFERENCIA a un token de
	 * Core Framework" de un hex elegido a mano — ej. guardado como
	 * "cf:acento-primario" en vez de "#d97a4d". Sin un campo aparte en el
	 * JSON: el prefijo alcanza para decidir cómo emitir el valor.
	 */
	private const PREFIJO_TOKEN_CORE_FRAMEWORK = 'cf:';

	/**
	 * Prefijo real que Core Framework usa en sus custom properties
	 * generadas (ver la investigación en la memoria de producto: código
	 * fuente de corebunch/core-framework, packages/core/src/cssGenerator/
	 * prefixer/variablePrefixer.ts — "cf-" es el default publicado, aunque
	 * técnicamente configurable por instalación; no hay forma de saber
	 * desde afuera qué prefijo eligió cada sitio sin leer su CSS generado,
	 * así que se asume el default).
	 */
	private const PREFIJO_VARIABLE_CORE_FRAMEWORK = '--cf-';

	/**
	 * true si el plugin Core Framework está activo en este sitio —
	 * chequea la presencia de su clase de storage real
	 * (\CoreFramework\StylesheetStorage), el único punto de acoplamiento
	 * con su código interno (ver el comentario de enlazar_css_core_framework()
	 * para el porqué de esta clase puntual).
	 */
	private static function core_framework_activo(): bool {
		return class_exists( '\CoreFramework\StylesheetStorage' );
	}

	/**
	 * Enlaza el CSS YA GENERADO por Core Framework (un archivo físico en
	 * wp-content/uploads/core-framework/css/core_framework.css, ver
	 * \CoreFramework\StylesheetStorage::get_url()/get_version()) — SIN
	 * esto, un color guardado como "cf:algo" (ver imprimir()) referenciaría
	 * una custom property que nunca llegó a definirse en la página, y el
	 * navegador caería silenciosamente al valor de respaldo de var().
	 *
	 * Core Framework NO expone una API de tokens estructurados (investigado
	 * contra su código fuente real, ver la memoria de producto) — la única
	 * forma de integrarlo es enlazar este CSS estático y dejar que la
	 * cascada normal resuelva las custom properties.
	 */
	public static function enlazar_css_core_framework(): void {
		if ( ! self::core_framework_activo() ) {
			return;
		}
		wp_enqueue_style(
			'core-framework',
			\CoreFramework\StylesheetStorage::get_url(),
			array(),
			\CoreFramework\StylesheetStorage::get_version()
		);
	}

	/**
	 * Resuelve el valor final de una propiedad de color/fuente: si
	 * $valor_guardado empieza con "cf:" (el usuario eligió "usar token de
	 * Core Framework" en el panel), emite var(--cf-{token}, $fallback) —
	 * el propio navegador resuelve la cascada: si el CSS de Core Framework
	 * definió ese token, gana; si no (plugin desactivado, token borrado,
	 * nombre mal escrito), cae a $fallback sin romper nada. Si
	 * $valor_guardado es un hex normal, se devuelve tal cual.
	 *
	 * $fallback nunca es obligatorio — un color de respaldo vacío en
	 * var(--cf-x, ) es CSS válido (la declaración completa se ignora si
	 * ninguno de los dos resuelve), mismo comportamiento que no tener nada
	 * configurado.
	 */
	private static function resolver_valor( string $valor_guardado, string $fallback = '' ): string {
		if ( ! str_starts_with( $valor_guardado, self::PREFIJO_TOKEN_CORE_FRAMEWORK ) ) {
			return $valor_guardado;
		}
		$token = substr( $valor_guardado, strlen( self::PREFIJO_TOKEN_CORE_FRAMEWORK ) );
		return 'var(' . self::PREFIJO_VARIABLE_CORE_FRAMEWORK . esc_attr( $token ) . ( '' !== $fallback ? ', ' . $fallback : '' ) . ')';
	}

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
			$valor            = self::resolver_valor( $colores[ $clave ] );
			$declaraciones[] = $variable_css . ':' . ( str_starts_with( $valor, 'var(' ) ? $valor : esc_attr( $valor ) );
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

add_action( 'wp_enqueue_scripts', array( 'Sofia_Estilo_Global', 'enlazar_css_core_framework' ) );
add_action( 'wp_head', array( 'Sofia_Estilo_Global', 'imprimir' ) );

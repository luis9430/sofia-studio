<?php
/**
 * Renderiza cada PIEZA en todas sus composiciones, como HTML standalone
 * listo para que Playwright lo mida.
 *
 *     php tests/verificar/render-piezas.php
 *
 * Deja un archivo por combinación en tests/verificar/salida/, más un
 * indice.json con la matriz para que medir.mjs sepa qué abrir.
 *
 * Por qué existe: las muestras que veníamos mirando (hero.html,
 * precios.html…) salieron de scripts sueltos que no quedaron
 * versionados, así que no se podían volver a generar. Esto sí.
 *
 * Dos decisiones que hacen que lo medido sea real:
 *
 * 1. Cada pieza va SOLA y a ancho completo, sin caja contenedora. El
 *    arnés viejo envolvía el render en .render{overflow:hidden}, que
 *    esconde exactamente los desbordes que queremos detectar.
 *
 * 2. Las composiciones se LEEN de schema_propio() en vez de estar
 *    escritas acá. Una pieza nueva, o una composición nueva en una
 *    pieza existente, entra a la matriz sin tocar este archivo — que es
 *    la única forma de que un arnés siga midiendo la verdad.
 */

require_once __DIR__ . '/../stubs-wordpress.php';

$base = dirname( __DIR__, 2 ) . '/';
require_once $base . 'inc/class-estilo-global.php';
require_once $base . 'inc/class-componente.php';
require_once $base . 'inc/class-componente-factory.php';

foreach ( glob( $base . 'inc/componentes/class-*.php' ) as $archivo ) {
	require_once $archivo;
}

/**
 * Las piezas: las secciones diseñadas, no las primitivas.
 *
 * Se listan a mano porque "pieza" es una distinción de diseño, no algo
 * que el código pueda deducir: precios y card ambos rinden HTML, pero
 * solo una es una sección compuesta que se juzga como unidad.
 */
const PIEZAS = array( 'header', 'hero', 'franja_beneficios', 'testimonios', 'precios', 'cta', 'footer', 'gallery' );

/** Los anchos donde se mide. Los mismos breakpoints del CSS del tema. */
const ANCHOS = array(
	'escritorio' => 1280,
	'tablet'     => 768,
	'telefono'   => 390,
);

/**
 * Las combinaciones de una pieza: su composición por su tono.
 *
 * No se hace el producto completo de todas las props de apariencia —
 * Hero tiene composición × altura × superposición, que son 24
 * combinaciones y casi todas dicen lo mismo. Composición y tono son las
 * dos que cambian la estructura y el color, que es lo que se mide.
 */
function combinaciones_de( string $tipo ): array {
	// schema_propio() es estático, pero no hay un mapa tipo→clase público
	// en el factory: se instancia una vez y se le pregunta a la instancia,
	// que resuelve al mismo método.
	$muestra = Sofia_Componente_Factory::crear( $tipo, $tipo . '-schema' );
	if ( null === $muestra ) {
		fwrite( STDERR, "tipo desconocido: {$tipo}\n" );
		exit( 1 );
	}
	$schema = $muestra::schema_propio();

	$composiciones = valores_de( $schema, 'composicion' );
	$tonos         = valores_de( $schema, 'tono' );

	$combos = array();
	foreach ( $composiciones as $comp ) {
		foreach ( $tonos as $tono ) {
			// Solo el tono por defecto para las composiciones que no son
			// la principal: el tono no cambia la estructura, y medir
			// 3×3 por pieza infla la matriz sin agregar información.
			if ( '' !== $comp && '' !== $tono ) {
				continue;
			}
			$combos[] = array( 'composicion' => $comp, 'tono' => $tono );
		}
	}
	return $combos;
}

/** Los valores de un select del schema, o [''] si la pieza no lo tiene. */
function valores_de( array $schema, string $clave ): array {
	if ( ! isset( $schema[ $clave ]['opciones'] ) ) {
		return array( '' );
	}
	return array_map(
		static fn( $o ) => (string) ( $o['valor'] ?? '' ),
		$schema[ $clave ]['opciones']
	);
}

/**
 * La página que envuelve una pieza.
 *
 * Trae el CSS del tema entero (style.css) porque las piezas dependen de
 * las variables y de la base compartida .sofia-pieza__*. Sin eso se
 * mide una pieza sin diseño y todo parece roto.
 */
function pagina( string $html, string $css, string $titulo ): string {
	return '<!doctype html><html lang="es"><head><meta charset="utf-8">'
		. '<meta name="viewport" content="width=device-width, initial-scale=1">'
		. '<title>' . htmlspecialchars( $titulo, ENT_QUOTES ) . '</title>'
		// margin:0 y nada más: cualquier estilo propio del arnés falsea
		// la medición del espaciado de la pieza.
		. '<style>*,*::before,*::after{box-sizing:border-box}html,body{margin:0;padding:0}</style>'
		. '<style>' . $css . '</style>'
		. '</head><body>' . $html . '</body></html>';
}

// --- Correr ---------------------------------------------------------------

$css = file_get_contents( $base . 'style.css' );
if ( false === $css ) {
	fwrite( STDERR, "no se pudo leer style.css\n" );
	exit( 1 );
}

$dir = __DIR__ . '/salida';
if ( ! is_dir( $dir ) ) {
	mkdir( $dir, 0777, true );
}
foreach ( glob( $dir . '/*.html' ) as $viejo ) {
	unlink( $viejo );
}

$indice = array();

foreach ( PIEZAS as $tipo ) {
	foreach ( combinaciones_de( $tipo ) as $combo ) {
		// Los defaults de la pieza son contenido real a propósito (una de
		// las seis reglas), así que sirven para medir tal cual: es lo que
		// el usuario ve al insertarla. crear() mergea props_por_defecto(),
		// así que solo hace falta pasar el estilo de la combinación.
		$componente = Sofia_Componente_Factory::crear(
			$tipo,
			$tipo . '-verif',
			array( '_estilo_bloque' => array_filter( $combo, static fn( $v ) => '' !== $v ) )
		);

		$nombre = $tipo
			. ( '' !== $combo['composicion'] ? '--' . $combo['composicion'] : '--base' )
			. ( '' !== $combo['tono'] ? '--' . $combo['tono'] : '' );

		$html = $componente->render();
		file_put_contents( $dir . '/' . $nombre . '.html', pagina( $html, $css, $nombre ) );

		$indice[] = array(
			'archivo'     => $nombre . '.html',
			'pieza'       => $tipo,
			'composicion' => $combo['composicion'] ?: 'base',
			'tono'        => $combo['tono'] ?: 'base',
		);
	}
}

file_put_contents(
	$dir . '/indice.json',
	json_encode(
		array( 'anchos' => ANCHOS, 'casos' => $indice ),
		JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
	)
);

printf(
	"%d casos × %d anchos = %d mediciones\n",
	count( $indice ),
	count( ANCHOS ),
	count( $indice ) * count( ANCHOS )
);
foreach ( PIEZAS as $tipo ) {
	$n = count( array_filter( $indice, static fn( $c ) => $c['pieza'] === $tipo ) );
	printf( "  %-20s %d\n", $tipo, $n );
}

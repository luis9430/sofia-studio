<?php
/**
 * Pruebas del sistema de Componentes generados.
 *
 * Se corre a mano desde la raíz del tema:
 *
 *     php tests/test-componente-generado.php
 *
 * No usa PHPUnit a propósito: el tema no tiene suite ni autoloader de
 * tests, y para un archivo la dependencia cuesta más de lo que aporta.
 * Lo que importa es que exista y se pueda volver a correr — estas son
 * pruebas de SEGURIDAD, y la seguridad que no se verifica de nuevo cada
 * vez que se toca el código es seguridad que se pierde en silencio.
 *
 * Las funciones de WordPress se reemplazan por equivalentes mínimos
 * (abajo), así que no hace falta levantar WordPress para correrlo.
 *
 * Criterio de las pruebas de ataque: NO se verifica que una palabra
 * peligrosa no aparezca en la salida — "onclick" puede quedar adentro de
 * un nombre de clase como texto muerto y eso es inofensivo. Se verifica
 * lo que de verdad importa: que no haya quedado un atributo ejecutable,
 * ni una etiqueta que ejecute, ni una regla CSS que alcance fuera del
 * bloque.
 */

// --- Funciones de WordPress, lo mínimo que usa el código bajo prueba ---
function __( $t, $d = '' ) { return $t; }
function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function esc_url( $t ) { return (string) $t; }
function wp_kses( $t, $a = array() ) { return strip_tags( (string) $t, '<strong><em><b><i><br>' ); }
function wp_strip_all_tags( $t ) { return strip_tags( (string) $t ); }
function add_action() {}
function get_option( $n, $d = false ) { return $d; }

$base = dirname( __DIR__ ) . '/';
require_once $base . 'inc/class-estilo-global.php';
require_once $base . 'inc/class-componente.php';
require_once $base . 'inc/class-css-generado.php';
require_once $base . 'inc/class-definicion-generada.php';
require_once $base . 'inc/class-componente-generado.php';

$pasadas = 0;
$falladas = 0;

/** Arma un componente desde una descripción cruda y devuelve [html, css]. */
function construir( array $bruto ): array {
	$avisos = array();
	$def    = Sofia_Definicion_Generada::validar( $bruto, $avisos );
	if ( null === $def ) {
		return array( '', '', $avisos, false );
	}
	$c = new Sofia_Componente_Generado( $def['tipo'], 'prueba' );
	$c->definir( $def );
	return array( $c->render(), $def['css'], $avisos, true );
}

/**
 * Verifica que una descripción hostil no produzca nada ejecutable ni
 * nada que alcance fuera del bloque.
 */
function ataque( string $nombre, array $bruto ): void {
	global $pasadas, $falladas;
	list( $html, $css ) = construir( $bruto );

	$problema = '';

	// Un atributo de evento (onclick, onload, onerror...) es ejecución.
	if ( preg_match( '/\son[a-z]+\s*=/i', $html ) ) {
		$problema = 'quedó un atributo on*=';
	}

	// Una etiqueta que ejecuta o trae contenido externo.
	if ( '' === $problema && preg_match( '/<(script|iframe|object|embed|svg|form|style|link|meta|base)\b/i', $html ) ) {
		$problema = 'quedó una etiqueta ejecutable';
	}

	// Un href/src con javascript: o data: (data: en un href ejecuta HTML).
	if ( '' === $problema && preg_match( '/(href|src)\s*=\s*["\']?\s*(javascript|data):/i', $html ) ) {
		$problema = 'quedó una URL ejecutable';
	}

	// Toda regla CSS tiene que estar acotada al bloque: si una línea no
	// arranca con el ámbito, puede pintar el resto de la página.
	if ( '' === $problema ) {
		foreach ( explode( "\n", $css ) as $linea ) {
			$linea = trim( $linea );
			if ( '' !== $linea && 0 !== strpos( $linea, '.sofia-gen--' ) ) {
				$problema = 'regla CSS sin acotar: ' . $linea;
				break;
			}
		}
	}

	if ( '' === $problema ) {
		++$pasadas;
		printf( "  [ok]    %s\n", $nombre );
		return;
	}
	++$falladas;
	printf( "  [FALLA] %s — %s\n", $nombre, $problema );
}

/** Verifica una condición cualquiera. */
function afirmar( string $nombre, bool $condicion, string $detalle = '' ): void {
	global $pasadas, $falladas;
	if ( $condicion ) {
		++$pasadas;
		printf( "  [ok]    %s\n", $nombre );
		return;
	}
	++$falladas;
	printf( "  [FALLA] %s%s\n", $nombre, $detalle ? ' — ' . $detalle : '' );
}

$campos_base = array(
	'titulo' => array( 'tipo' => 'texto', 'etiqueta' => 'Título', 'por_defecto' => 'hola' ),
);

/** Una descripción mínima válida, con el CSS que se quiera probar. */
function con_css( string $css ): array {
	global $campos_base;
	return array(
		'tipo'       => 'prueba',
		'campos'     => $campos_base,
		'estructura' => array( array( 'etiqueta' => 'p', 'clase' => 'z', 'campo' => 'titulo' ) ),
		'css'        => $css,
	);
}

echo "\n--- HTML: etiquetas y atributos ---\n";

ataque( 'etiqueta <script>', array(
	'tipo' => 'prueba', 'campos' => $campos_base,
	'estructura' => array( array( 'etiqueta' => 'script' ), array( 'etiqueta' => 'p', 'campo' => 'titulo' ) ),
) );

ataque( 'etiqueta <iframe>', array(
	'tipo' => 'prueba', 'campos' => $campos_base,
	'estructura' => array( array( 'etiqueta' => 'iframe' ), array( 'etiqueta' => 'p', 'campo' => 'titulo' ) ),
) );

// svg queda afuera de la whitelist aunque sea tentador para decoración:
// admite <script> adentro y atributos de evento propios.
ataque( 'etiqueta <svg>', array(
	'tipo' => 'prueba', 'campos' => $campos_base,
	'estructura' => array( array( 'etiqueta' => 'svg' ), array( 'etiqueta' => 'p', 'campo' => 'titulo' ) ),
) );

ataque( 'etiqueta <form>', array(
	'tipo' => 'prueba', 'campos' => $campos_base,
	'estructura' => array( array( 'etiqueta' => 'form' ), array( 'etiqueta' => 'p', 'campo' => 'titulo' ) ),
) );

// Cerrar el atributo class e inyectar uno propio.
ataque( 'romper el atributo class con comillas', array(
	'tipo' => 'prueba', 'campos' => $campos_base,
	'estructura' => array( array( 'etiqueta' => 'p', 'clase' => 'x" onclick="alert(1)', 'campo' => 'titulo' ) ),
) );

// Lo mismo desde el tipo, que también termina en un atributo.
ataque( 'romper un atributo desde el tipo', array(
	'tipo' => 'prueba" onload="alert(1)', 'campos' => $campos_base,
	'estructura' => array( array( 'etiqueta' => 'p', 'campo' => 'titulo' ) ),
) );

ataque( 'HTML en el valor por defecto de un campo', array(
	'tipo' => 'prueba',
	'campos' => array( 'titulo' => array( 'tipo' => 'texto', 'etiqueta' => 'T', 'por_defecto' => '<script>alert(1)</script>' ) ),
	'estructura' => array( array( 'etiqueta' => 'p', 'campo' => 'titulo' ) ),
) );

ataque( 'HTML en la etiqueta de un campo', array(
	'tipo' => 'prueba',
	'campos' => array( 'titulo' => array( 'tipo' => 'texto', 'etiqueta' => '<img src=x onerror=alert(1)>', 'por_defecto' => 'x' ) ),
	'estructura' => array( array( 'etiqueta' => 'p', 'campo' => 'titulo' ) ),
) );

echo "\n--- CSS: ejecución y filtración ---\n";

// url() externa: cada visitante le filtra su IP a un tercero.
ataque( 'url() a un dominio externo', con_css( '.z { background: url(https://ajeno.test/p.gif); }' ) );
ataque( '@import de otra hoja', con_css( '@import url(https://ajeno.test/x.css);' ) );
ataque( 'expression() (ejecutaba JS en IE)', con_css( '.z { width: expression(alert(1)); }' ) );
ataque( '-moz-binding', con_css( '.z { -moz-binding: url(http://ajeno.test/x.xml#y); }' ) );
ataque( 'cerrar </style> e inyectar HTML', con_css( '.z { color: red; } </style><script>alert(1)</script>' ) );
// Un comentario en el medio parte el patrón prohibido en dos.
ataque( 'comentario partiendo url(', con_css( '.z { background: ur/**/l(https://ajeno.test/x); }' ) );

echo "\n--- CSS: escape del bloque ---\n";

ataque( 'selector body', con_css( 'body { display: none; }' ) );
ataque( 'selector :root (pisaría los tokens del sitio)', con_css( ':root { --sofia-color-primario: red; }' ) );
ataque( 'selector html', con_css( 'html { filter: invert(1); }' ) );
ataque( 'selector universal', con_css( '* { display: none; }' ) );
ataque( 'selector de otro bloque del tema', con_css( '.sofia-hero { display: none; }' ) );

echo "\n--- Límites de tamaño ---\n";

// Una estructura muy honda agota la pila al recorrerla.
$hondo = array( 'etiqueta' => 'div' );
for ( $i = 0; $i < 40; $i++ ) {
	$hondo = array( 'etiqueta' => 'div', 'hijos' => array( $hondo ) );
}
list( , , , $ok ) = construir( array( 'tipo' => 'prueba', 'campos' => $campos_base, 'estructura' => array( $hondo ) ) );
$avisos = array();
$def    = Sofia_Definicion_Generada::validar( array( 'tipo' => 'prueba', 'campos' => $campos_base, 'estructura' => array( $hondo ) ), $avisos );
$nodos  = $def ? substr_count( wp_json_encode_local( $def['estructura'] ), '"etiqueta"' ) : 0;
afirmar( 'anidamiento de 40 niveles se recorta', $nodos > 0 && $nodos <= 8, "quedaron $nodos nodos" );

$muchos = array();
for ( $i = 0; $i < 300; $i++ ) {
	$muchos[] = array( 'etiqueta' => 'div' );
}
$avisos = array();
$def    = Sofia_Definicion_Generada::validar( array( 'tipo' => 'prueba', 'campos' => $campos_base, 'estructura' => $muchos ), $avisos );
afirmar( '300 nodos hermanos se recortan a 80', $def && count( $def['estructura'] ) <= 80, $def ? count( $def['estructura'] ) . ' nodos' : 'rechazada' );

$avisos = array();
$def    = Sofia_Definicion_Generada::validar( con_css( str_repeat( '.z{color:red;}', 2000 ) ), $avisos );
afirmar( 'CSS de 28KB se descarta entero', $def && '' === $def['css'] );

echo "\n--- Descripciones inservibles ---\n";

$avisos = array();
afirmar( 'sin tipo se rechaza', null === Sofia_Definicion_Generada::validar( array( 'campos' => $campos_base ), $avisos ) );

$avisos = array();
afirmar( 'sin campos se rechaza', null === Sofia_Definicion_Generada::validar( array( 'tipo' => 'x', 'estructura' => array( array( 'etiqueta' => 'p' ) ) ), $avisos ) );

$avisos = array();
afirmar( 'sin estructura se rechaza', null === Sofia_Definicion_Generada::validar( array( 'tipo' => 'x', 'campos' => $campos_base ), $avisos ) );

$avisos = array();
$def = Sofia_Definicion_Generada::validar( array(
	'tipo' => 'x', 'campos' => $campos_base,
	'estructura' => array( array( 'etiqueta' => 'p', 'campo' => 'no_declarado' ) ),
), $avisos );
afirmar( 'campo no declarado se desvincula', $def && ! isset( $def['estructura'][0]['campo'] ) );
afirmar( 'y deja aviso', ! empty( $avisos ) );

echo "\n--- Contrato del editor ---\n";

$avisos = array();
$def = Sofia_Definicion_Generada::validar( array(
	'tipo'   => 'con_lista',
	'campos' => array(
		'items' => array(
			'tipo'     => 'lista',
			'etiqueta' => 'Items',
			'campos'   => array(
				'titulo' => array( 'tipo' => 'texto', 'etiqueta' => 'Título', 'por_defecto' => 'Uno' ),
				'texto'  => array( 'tipo' => 'texto', 'etiqueta' => 'Texto', 'por_defecto' => 'Dos' ),
			),
		),
	),
	'estructura' => array(
		array(
			'etiqueta' => 'ul',
			'lista'    => 'items',
			'item'     => array(
				array(
					'etiqueta' => 'li',
					'hijos'    => array(
						array( 'etiqueta' => 'h3', 'campo' => 'titulo' ),
						array( 'etiqueta' => 'p', 'campo' => 'texto' ),
					),
				),
			),
		),
	),
), $avisos );

afirmar( 'una lista válida se acepta', null !== $def );

if ( $def ) {
	$c = new Sofia_Componente_Generado( $def['tipo'], 'b1' );
	$c->definir( $def );
	$html = $c->render();

	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?><body>' . $html . '</body>' );
	libxml_clear_errors();
	$x = new DOMXPath( $doc );

	afirmar( 'la sección lleva data-sofia-bloque-id', 1 === $x->query( '//*[@data-sofia-bloque-id]' )->length );
	afirmar( 'la lista lleva data-sofia-lista', 1 === $x->query( '//*[@data-sofia-lista]' )->length );
	afirmar( 'hay un data-sofia-item por cada item', 2 === $x->query( '//*[@data-sofia-item]' )->length );

	// EL contrato que ya rompió una vez (Tabs perdía el texto de los
	// paneles): notificarListaActualizada() reconstruye cada item leyendo
	// los campos que encuentra ADENTRO de su [data-sofia-item]. Un campo
	// que quede afuera se pierde al editar cualquier otro.
	$fuera = $x->query( '//*[@data-sofia-campo][not(ancestor::*[@data-sofia-item])]' );
	afirmar( 'ningún campo de lista queda fuera de su item', 0 === $fuera->length, $fuera->length . ' fuera' );

	// Las rutas del DOM tienen que existir en el schema, o el guardado
	// las descarta en silencio.
	$schema = $c->schema_contenido_propio();
	afirmar( 'el schema declara la lista', isset( $schema['items']['campos']['titulo'] ) );
}

echo "\n--- items por defecto de una lista ---\n";

// Bug real, visible en el sitio: una tarjeta de precios generada por IA
// mostraba "Soporte 24/7" DOS VECES. El modelo había propuesto tres
// características distintas, pero el validador descartaba el
// "por_defecto" de la lista y el Componente rellenaba el hueco
// duplicando el "por_defecto" de cada subcampo.
$avisos = array();
$def    = Sofia_Definicion_Generada::validar( array(
	'tipo'   => 'con_items',
	'campos' => array(
		'caracteristicas' => array(
			'tipo'        => 'lista',
			'etiqueta'    => 'Características',
			'campos'      => array(
				'texto' => array( 'tipo' => 'texto', 'etiqueta' => 'Texto', 'por_defecto' => 'Genérico' ),
			),
			'por_defecto' => array(
				array( 'texto' => '10 GB de almacenamiento' ),
				array( 'texto' => 'Acceso premium' ),
				array( 'texto' => 'Actualizaciones gratuitas' ),
			),
		),
	),
	'estructura' => array(
		array(
			'etiqueta' => 'ul',
			'lista'    => 'caracteristicas',
			'item'     => array( array( 'etiqueta' => 'li', 'campo' => 'texto' ) ),
		),
	),
), $avisos );

afirmar( 'la lista con items propios se acepta', null !== $def );

if ( $def ) {
	$c = new Sofia_Componente_Generado( $def['tipo'], 'li1' );
	$c->definir( $def );
	$html = $c->render();

	afirmar( 'se respetan los 3 items propuestos', 3 === substr_count( $html, 'data-sofia-item' ),
		substr_count( $html, 'data-sofia-item' ) . ' items' );
	afirmar( 'con su contenido real', false !== strpos( $html, '10 GB de almacenamiento' ) );
	afirmar( 'sin duplicar el genérico', substr_count( $html, 'Genérico' ) < 2 );
}

// Sin "por_defecto" propio sigue habiendo relleno: una lista vacía no le
// da al editor dónde dibujar el botón de agregar.
$avisos = array();
$def    = Sofia_Definicion_Generada::validar( array(
	'tipo'   => 'sin_items',
	'campos' => array(
		'items' => array(
			'tipo'     => 'lista',
			'etiqueta' => 'Items',
			'campos'   => array( 'texto' => array( 'tipo' => 'texto', 'etiqueta' => 'T', 'por_defecto' => 'Ejemplo' ) ),
		),
	),
	'estructura' => array(
		array( 'etiqueta' => 'ul', 'lista' => 'items', 'item' => array( array( 'etiqueta' => 'li', 'campo' => 'texto' ) ) ),
	),
), $avisos );

if ( $def ) {
	$c = new Sofia_Componente_Generado( $def['tipo'], 'li2' );
	$c->definir( $def );
	afirmar( 'una lista sin items propuestos arranca con relleno', substr_count( $c->render(), 'data-sofia-item' ) >= 1 );
}

echo "\n--- var() que apunta a un campo ---\n";

// Error real del generador por IA: declaraba un campo "color_circulo" y
// escribía var(--color_circulo) en el CSS. Esa variable no la define
// nadie, así que el elemento quedaba sin color de fondo — y el fallo era
// invisible, porque una var() indefinida hace que el navegador ignore la
// propiedad en silencio.
$avisos = array();
$def    = Sofia_Definicion_Generada::validar( array(
	'tipo'   => 'con_var',
	'campos' => array(
		'titulo'      => array( 'tipo' => 'texto', 'etiqueta' => 'T', 'por_defecto' => 'x' ),
		'color_fondo' => array( 'tipo' => 'texto', 'etiqueta' => 'C', 'por_defecto' => '#fff' ),
	),
	'estructura' => array( array( 'etiqueta' => 'p', 'clase' => 'z', 'campo' => 'titulo' ) ),
	'css'        => '.z { background: var(--color_fondo); color: var(--sofia-color-texto); }',
), $avisos );

afirmar( 'la definición se acepta igual', null !== $def );

$aviso_de_var = false;
$sobre_sofia  = false;
foreach ( $avisos as $a ) {
	if ( false !== strpos( $a, 'color_fondo' ) && false !== strpos( $a, 'campo de contenido' ) ) {
		$aviso_de_var = true;
	}
	if ( false !== strpos( $a, 'sofia-color-texto' ) ) {
		$sobre_sofia = true;
	}
}

afirmar( 'avisa que var(--color_fondo) no va a funcionar', $aviso_de_var, implode( ' | ', $avisos ) );
// Las --sofia-* sí existen: las define Sofia_Estilo_Global en el <head>.
afirmar( 'no avisa sobre var(--sofia-*), que sí existen', ! $sobre_sofia );

echo "\n--- Registro en el factory ---\n";

// El factory necesita los Componentes fijos y un doble del cliente de
// GoPress. Se cargan ACÁ y no arriba porque las pruebas anteriores no
// los necesitan.
if ( ! class_exists( 'Sofia_Cliente_GoPress' ) ) {
	/**
	 * Doble del cliente: devuelve lo que se le ponga en $respuesta, sin
	 * tocar la red. Valida igual que el real, para que la prueba recorra
	 * el mismo camino que producción.
	 */
	class Sofia_Cliente_GoPress {
		/** @var array<int,array<string,mixed>> */
		public static array $respuesta = array();

		/** @return array<string,array<string,mixed>> */
		public static function obtener_componentes_generados(): array {
			$salida = array();
			foreach ( self::$respuesta as $fila ) {
				$avisos     = array();
				$definicion = Sofia_Definicion_Generada::validar( $fila, $avisos );
				if ( null !== $definicion ) {
					$salida[ $definicion['tipo'] ] = $definicion;
				}
			}
			return $salida;
		}
	}
}

foreach ( glob( dirname( __DIR__ ) . '/inc/componentes/*.php' ) as $archivo ) {
	require_once $archivo;
}
require_once dirname( __DIR__ ) . '/inc/class-componente-factory.php';

$hero = json_decode( file_get_contents( dirname( __DIR__ ) . '/inc/generados/hero-diagonal.json' ), true );

// Sin componentes generados —el caso de casi todos los sitios— el
// catálogo tiene que quedar exactamente como estaba.
Sofia_Cliente_GoPress::$respuesta = array();
$fijos = count( Sofia_Componente_Factory::catalogo() );
afirmar( 'sin generados, el catálogo son solo los fijos', $fijos > 0, "$fijos tipos" );
afirmar( 'un tipo inexistente sigue devolviendo null', null === Sofia_Componente_Factory::crear( 'no_existe_nada', 'x' ) );

// Con uno cargado, aparece en el catálogo y se puede instanciar.
Sofia_Cliente_GoPress::$respuesta = array( $hero );
afirmar( 'un generado se suma al catálogo', count( Sofia_Componente_Factory::catalogo() ) === $fijos + 1 );

$generado = Sofia_Componente_Factory::crear( 'hero_diagonal', 'blq1' );
afirmar( 'el factory lo instancia', $generado instanceof Sofia_Componente_Generado );
afirmar( 'con su nombre propio', $generado && 'Hero diagonal' === $generado->nombre() );
afirmar( 'y renderiza', $generado && '' !== trim( $generado->render() ) );

// schema_contenido_de() llama al método ESTÁTICO de la clase, que en un
// generado devolvería vacío — tiene que resolverlo por instancia.
$schema = Sofia_Componente_Factory::schema_contenido_de( 'hero_diagonal' );
afirmar( 'schema_contenido_de resuelve el schema por instancia', is_array( $schema ) && isset( $schema['titulo'] ) );

// LA garantía más importante del registro dinámico: una descripción
// guardada en GoPress NO puede reemplazar un Componente del tema. Si
// pudiera, cualquiera con acceso a esa tabla redefiniría el hero de
// todos los sitios.
$impostor         = $hero;
$impostor['tipo'] = 'hero';
Sofia_Cliente_GoPress::$respuesta = array( $impostor );
$resuelto = Sofia_Componente_Factory::crear( 'hero', 'x' );
afirmar(
	'un generado no puede suplantar un tipo fijo',
	$resuelto instanceof Sofia_Componente_Hero,
	$resuelto ? get_class( $resuelto ) : 'null'
);

// Una fila corrupta se ignora sin arrastrar al resto del catálogo.
Sofia_Cliente_GoPress::$respuesta = array( array( 'tipo' => 'roto_sin_campos' ) );
afirmar( 'una definición corrupta no se instancia', null === Sofia_Componente_Factory::crear( 'roto_sin_campos', 'x' ) );
afirmar( 'y no rompe el catálogo', count( Sofia_Componente_Factory::catalogo() ) === $fijos );

// Los generados tienen que llegar TAMBIEN al catalogo que ve la IA.
//
// Bug real, visible en el sitio: el generador de paginas propuso un
// bloque con id "hero-diagonal" pero tipo "hero" — quiso usar el hero
// diagonal del sitio, no lo encontro entre los tipos que le ensenamos, y
// cayo al mas parecido. Un Componente generado que no esta en este
// catalogo es, para la IA, un Componente que no existe.
Sofia_Cliente_GoPress::$respuesta = array( $hero );
$para_ia = Sofia_Componente_Factory::catalogo_para_ia();
$tipos_ia = array_column( $para_ia, 'tipo' );
afirmar( 'el generado llega al catalogo de la IA', in_array( 'hero_diagonal', $tipos_ia, true ) );

$entrada = array_values( array_filter( $para_ia, function ( $t ) { return 'hero_diagonal' === $t['tipo']; } ) );
afirmar( 'con su schema de contenido', ! empty( $entrada ) && isset( $entrada[0]['schema_contenido']['titulo'] ) );
afirmar( 'y su schema de estilo', ! empty( $entrada ) && ! empty( $entrada[0]['schema_estilo'] ) );

printf( "\n%d pasadas, %d falladas\n\n", $pasadas, $falladas );
exit( $falladas > 0 ? 1 : 0 );

/** json_encode sin depender de WordPress. */
function wp_json_encode_local( $v ) {
	return json_encode( $v );
}

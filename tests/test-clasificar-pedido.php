<?php
/**
 * Prueba de la heurística que decide, a partir de lo que escribe el
 * usuario, si pide una PÁGINA o un COMPONENTE nuevo.
 *
 *     php tests/test-clasificar-pedido.php
 *
 * Existe porque esa decisión es invisible: el usuario escribe una sola
 * frase en una sola caja y el sistema elige por él. Si se equivoca, el
 * síntoma no es un error sino "me devolvió otra cosa", que es mucho más
 * difícil de reportar y de diagnosticar.
 *
 * El default es PÁGINA a propósito: es lo que este editor hizo siempre, y
 * equivocarse hacia ahí devuelve algo usable igual, mientras que
 * equivocarse al revés entrega un solo bloque a quien esperaba un sitio.
 */

// Shims mínimos: solo se carga la clase, nunca se ejecuta WordPress.
function __( $t, $d = '' ) { return $t; }
function add_action() {}
function register_rest_route() {}
function esc_attr( $t ) { return $t; }
function esc_html( $t ) { return $t; }
function esc_url( $t ) { return $t; }
function wp_kses( $t, $a = array() ) { return $t; }
function wp_strip_all_tags( $t ) { return strip_tags( (string) $t ); }
function get_option( $n, $d = false ) { return $d; }
function current_user_can( $c ) { return true; }
function rest_ensure_response( $r ) { return $r; }
class WP_REST_Request {}
class WP_Error {}

require_once dirname( __DIR__ ) . '/inc/class-rest-editor.php';

$metodo = new ReflectionMethod( 'Sofia_REST_Editor', 'pide_un_componente' );
$metodo->setAccessible( true );

/** @var array<int,array{0:string,1:bool}> */
$casos = array(
	// Pide UN bloque.
	array( 'un hero con la imagen cortada en diagonal', true ),
	array( 'una tarjeta de precio con efecto vidrio esmerilado', true ),
	array( 'un componente de testimonios en carrusel', true ),
	array( 'una cinta con logos de clientes', true ),
	array( 'un bloque de estadísticas en hexágonos', true ),
	array( 'una sección de cita destacada', true ),
	array( 'un banner con cuenta regresiva', true ),

	// Pide una PÁGINA.
	array( 'una página de inicio para un hotel boutique', false ),
	array( 'una landing para vender un curso', false ),
	array( 'un sitio para un estudio contable', false ),
	array( 'página de contacto', false ),
	array( 'armame el home del sitio', false ),

	// El caso que más importa: nombra un bloque PERO pide una página.
	// Los indicadores de página ganan sobre los de componente.
	array( 'la página home con un hero diagonal y testimonios', false ),
	array( 'una landing con un hero, beneficios y un carrusel', false ),

	// Ambiguo y largo: cae al default, que es página.
	array( 'quiero algo moderno para mostrar los servicios que ofrecemos y los planes de precios', false ),
);

$fallas = 0;
foreach ( $casos as $caso ) {
	list( $prompt, $esperado ) = $caso;
	$obtenido = $metodo->invoke( null, $prompt );
	$ok       = $obtenido === $esperado;
	if ( ! $ok ) {
		++$fallas;
	}
	printf(
		"%s %-64s -> %s
",
		$ok ? '  [ok]   ' : '  [FALLA]',
		mb_substr( $prompt, 0, 62 ),
		$obtenido ? 'componente' : 'pagina'
	);
}

printf( "
%d/%d correctos

", count( $casos ) - $fallas, count( $casos ) );
exit( $fallas > 0 ? 1 : 0 );

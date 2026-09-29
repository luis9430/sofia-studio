<?php
/**
 * Le pide páginas completas a la IA y las renderiza para medirlas.
 *
 *     php tests/verificar/generar-paginas.php
 *     php tests/verificar/generar-paginas.php mis-pedidos.txt
 *
 * Deja un HTML por pedido en tests/verificar/salida-ia/, más un
 * indice.json con el mismo formato que usa medir.mjs — así la página
 * generada pasa por EXACTAMENTE los mismos diez chequeos que las piezas
 * hechas a mano, sin una segunda vara.
 *
 * Por qué existe: la pregunta abierta desde hace semanas es si el
 * generador sirve, y hasta ahora se contestaba mirando. Ahora las piezas
 * son de calidad, el arnés existe, y la pregunta se puede contestar con
 * evidencia: ¿la IA compone bien con material decente?
 *
 * Mide DOS cosas distintas, y conviene no confundirlas:
 *
 *   1. Si el árbol que propone es VÁLIDO (tipos que existen, valores que
 *      el schema acepta) — eso lo dicen los avisos de la reparación.
 *   2. Si la página resultante está BIEN COMPUESTA — eso lo dicen
 *      medir.mjs y juzgar.mjs, igual que con las piezas a mano.
 *
 * Un árbol puede ser perfectamente válido y componer una página fea. Son
 * preguntas separadas y este arnés no las mezcla.
 */

require_once __DIR__ . '/../stubs-wordpress.php';

$base = dirname( __DIR__, 2 ) . '/';
require_once $base . 'inc/class-estilo-global.php';
require_once $base . 'inc/class-componente.php';
require_once $base . 'inc/class-componente-factory.php';
require_once $base . 'inc/class-css-generado.php';
require_once $base . 'inc/class-definicion-generada.php';
require_once $base . 'inc/class-componente-generado.php';
require_once $base . 'inc/class-pagina.php';

foreach ( glob( $base . 'inc/componentes/class-*.php' ) as $archivo ) {
	require_once $archivo;
}

// --- Config ----------------------------------------------------------------
$url_gopress = getenv( 'SOFIA_GOPRESS_URL' ) ?: 'http://localhost:8090';
$sitio       = getenv( 'SOFIA_GOPRESS_SITIO' ) ?: 'costalegre';
$token       = getenv( 'SOFIA_GOPRESS_TOKEN' ) ?: '';

/**
 * Los pedidos por defecto.
 *
 * Son PEDIDOS DE NEGOCIO, no descripciones de layout: "una landing para
 * una escuela de surf", no "un hero, tres beneficios y un CTA". Si hay
 * que dictarle la estructura, el generador no está resolviendo el
 * problema — solo transcribiendo.
 *
 * Variados a propósito en rubro y en temperatura: si solo funciona con
 * pedidos de cierto tipo, eso también es un resultado que vale saber.
 */
$pedidos_por_defecto = array(
	'una landing para una escuela de surf en la costa',
	'la página de inicio de un estudio de arquitectura',
	'una landing para una app de finanzas personales',
	'la home de una cafetería de especialidad',
	'una página para un consultorio odontológico',
	'landing de un SaaS de facturación para monotributistas',
);

$archivo_pedidos = $argv[1] ?? '';
if ( '' !== $archivo_pedidos ) {
	if ( ! is_readable( $archivo_pedidos ) ) {
		fwrite( STDERR, "no se pudo leer $archivo_pedidos\n" );
		exit( 1 );
	}
	$pedidos = array_values( array_filter( array_map( 'trim', file( $archivo_pedidos ) ) ) );
} else {
	$pedidos = $pedidos_por_defecto;
}

if ( '' === $token ) {
	fwrite( STDERR, "falta el token del sitio.\n" );
	fwrite( STDERR, "pasalo con: SOFIA_GOPRESS_TOKEN=... php tests/verificar/generar-paginas.php\n" );
	exit( 1 );
}

// --- Pedir los árboles, en paralelo ----------------------------------------
// Generar 6 páginas en serie, a ~30s cada una, son tres minutos de espera.
// En paralelo es poco más que la más lenta.

$catalogo = Sofia_Componente_Factory::catalogo_para_ia();
printf(
	"Pidiendo %d páginas en paralelo (%d tipos en el catálogo)…\n\n",
	count( $pedidos ),
	count( $catalogo )
);

$url = rtrim( $url_gopress, '/' ) . '/sites/' . rawurlencode( $sitio )
	. '/ia/generar-arbol?token=' . rawurlencode( $token );

$multi   = curl_multi_init();
$handles = array();

foreach ( $pedidos as $i => $pedido ) {
	$ch = curl_init( $url );
	curl_setopt_array(
		$ch,
		array(
			CURLOPT_POST           => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER     => array( 'Content-Type: application/json' ),
			CURLOPT_POSTFIELDS     => json_encode(
				array( 'prompt' => $pedido, 'catalogo' => $catalogo ),
				JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
			),
			// El mismo timeout generoso que usa el cliente real: generar un
			// árbol completo es la request más lenta de todo el sistema.
			CURLOPT_TIMEOUT        => 180,
		)
	);
	curl_multi_add_handle( $multi, $ch );
	$handles[ $i ] = $ch;
}

$inicio   = microtime( true );
$corriendo = null;
do {
	curl_multi_exec( $multi, $corriendo );
	curl_multi_select( $multi, 1.0 );
} while ( $corriendo > 0 );

$respuestas = array();
foreach ( $handles as $i => $ch ) {
	$cuerpo = curl_multi_getcontent( $ch );
	$codigo = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
	$respuestas[ $i ] = array( 'codigo' => $codigo, 'cuerpo' => $cuerpo );
	curl_multi_remove_handle( $multi, $ch );
	curl_close( $ch );
}
curl_multi_close( $multi );

printf( "listo en %.1fs\n\n", microtime( true ) - $inicio );

// --- Renderizar cada árbol -------------------------------------------------

$dir = __DIR__ . '/salida-ia';
if ( ! is_dir( $dir ) ) {
	mkdir( $dir, 0777, true );
}
foreach ( glob( $dir . '/*.html' ) as $viejo ) {
	unlink( $viejo );
}

$css    = file_get_contents( $base . 'style.css' );
$indice = array();
$resumen = array();

foreach ( $pedidos as $i => $pedido ) {
	$nombre = sprintf( 'pagina-%02d', $i + 1 );
	$r      = $respuestas[ $i ];

	if ( 200 !== $r['codigo'] ) {
		$resumen[] = array(
			'pedido' => $pedido,
			'error'  => 'HTTP ' . $r['codigo'] . ': ' . substr( (string) $r['cuerpo'], 0, 200 ),
		);
		printf( "  FALLO  %-52s HTTP %d\n", recortar( $pedido, 50 ), $r['codigo'] );
		continue;
	}

	$datos = json_decode( (string) $r['cuerpo'], true );
	$arbol = $datos['arbol'] ?? null;
	if ( ! is_array( $arbol ) ) {
		$resumen[] = array( 'pedido' => $pedido, 'error' => 'respuesta sin árbol' );
		printf( "  FALLO  %-52s sin árbol\n", recortar( $pedido, 50 ) );
		continue;
	}

	// El árbol de la IA trae estructura y contenido MEZCLADOS por nodo;
	// Sofia_Pagina los espera separados, que es como viven en el sitio.
	$estructura = array();
	$contenido  = array();
	aplanar( $arbol, $estructura, $contenido );

	// Si el árbol no aportó contenido, lo que se va a medir son los
	// defaults del tema y no lo que la IA escribió — una página que se ve
	// perfecta y no tiene nada que ver con el pedido. Vale como error
	// ruidoso: es el bug que tuvo este arnés en su primera corrida.
	if ( empty( $contenido ) ) {
		$resumen[] = array(
			'pedido' => $pedido,
			'error'  => 'el árbol no trajo contenido — se estarían midiendo los defaults del tema',
		);
		printf( "  FALLO  %-52s árbol sin contenido\n", recortar( $pedido, 50 ) );
		continue;
	}

	$pagina      = new Sofia_Pagina( $estructura, $contenido );
	$componentes = $pagina->componentes();

	// La cabecera y el pie del SITIO envuelven a toda página (ver page.php).
	// El arnés tiene que medir la página COMPLETA: sin esto se estaría
	// midiendo solo el medio, y justamente el motivo de haberlos movido a
	// nivel sitio fue que las páginas generadas salían sin navegación.
	//
	// Van a decir el nombre del sitio de prueba (Costalegre) aunque el
	// pedido sea de una cafetería o un consultorio, y eso es CORRECTO: son
	// del sitio, no de la página. Un sitio real tiene un solo nombre. Lo
	// que se mide acá es que la página quede bien armada de punta a punta,
	// no que el texto del pie coincida con el rubro del pedido.
	$componentes = array_merge(
		componentes_del_sitio( 'cabecera' ),
		$componentes,
		componentes_del_sitio( 'pie' )
	);

	$html = '';
	foreach ( $componentes as $c ) {
		$html .= $c->render();
	}

	// El CSS de los Componentes generados va aparte del style.css: cada
	// uno trae el suyo, ya saneado y acotado a su bloque.
	$css_generado = '';
	foreach ( $componentes as $c ) {
		if ( method_exists( $c, 'css' ) ) {
			$css_generado .= $c->css();
		}
	}

	file_put_contents( $dir . '/' . $nombre . '.html', pagina_html( $html, $css . $css_generado, $pedido ) );

	$tipos = array_map( static fn( $b ) => $b['tipo'], $estructura );
	$indice[] = array(
		'archivo'     => $nombre . '.html',
		'pieza'       => $nombre,
		'composicion' => 'ia',
		'tono'        => 'base',
	);
	$resumen[] = array(
		'pedido'  => $pedido,
		'archivo' => $nombre . '.html',
		'bloques' => count( $estructura ),
		'tipos'   => $tipos,
		'avisos'  => $datos['avisos'] ?? array(),
	);

	printf(
		"  ok     %-52s %d bloques: %s\n",
		recortar( $pedido, 50 ),
		count( $estructura ),
		implode( ', ', array_slice( $tipos, 0, 6 ) ) . ( count( $tipos ) > 6 ? '…' : '' )
	);
}

file_put_contents(
	$dir . '/indice.json',
	json_encode(
		array(
			'anchos' => array( 'escritorio' => 1280, 'tablet' => 768, 'telefono' => 390 ),
			'casos'  => $indice,
		),
		JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
	)
);
file_put_contents(
	$dir . '/pedidos.json',
	json_encode( $resumen, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
);

// --- Qué tipos usó, que es la pregunta del "siempre lo mismo" ---------------
$uso = array();
foreach ( $resumen as $r ) {
	foreach ( $r['tipos'] ?? array() as $t ) {
		$uso[ $t ] = ( $uso[ $t ] ?? 0 ) + 1;
	}
}
arsort( $uso );

echo "\n=== Tipos usados ===\n";
foreach ( $uso as $tipo => $n ) {
	printf( "  %-22s %d\n", $tipo, $n );
}
printf(
	"\n%d de %d tipos del catálogo · %d páginas en salida-ia/\n",
	count( $uso ),
	count( $catalogo ),
	count( $indice )
);
echo "medilas con: node tests/verificar/medir.mjs salida-ia\n";

// --- Funciones -------------------------------------------------------------

/**
 * La cabecera o el pie del SITIO, como componentes listos.
 *
 * Se piden a GoPress igual que lo hace el tema en cada render — no se
 * reconstruyen de los defaults: lo que hay que medir es lo que el sitio
 * realmente dibuja.
 *
 * @return Sofia_Componente[] Vacío si el sitio no tiene esa parte, que es
 *         un estado legítimo (un sitio recién creado).
 */
function componentes_del_sitio( string $cual ): array {
	static $cache = null;

	if ( null === $cache ) {
		global $url_gopress, $sitio, $token;
		$url = rtrim( $url_gopress, '/' ) . '/sites/' . rawurlencode( $sitio )
			. '/tema/cabecera-pie?token=' . rawurlencode( $token );

		$ch = curl_init( $url );
		curl_setopt_array( $ch, array( CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10 ) );
		$cuerpo = curl_exec( $ch );
		$codigo = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close( $ch );

		$datos = 200 === $codigo ? json_decode( (string) $cuerpo, true ) : null;
		$cache = is_array( $datos ) && is_array( $datos['cabecera_pie'] ?? null )
			? $datos['cabecera_pie']
			: array();
	}

	$parte = $cache[ $cual ] ?? null;
	if ( ! is_array( $parte ) || empty( $parte['estructura'] ) ) {
		return array();
	}

	$pieza = new Sofia_Pagina(
		$parte['estructura'],
		is_array( $parte['contenido'] ?? null ) ? $parte['contenido'] : array()
	);
	return $pieza->componentes();
}

/**
 * Separa el árbol de la IA en estructura + contenido.
 *
 * La IA devuelve cada nodo con sus props adentro; Sofia_Pagina espera la
 * estructura por un lado y el contenido aplanado como "{id}.{campo}" por
 * el otro, que es como vive en el sitio.
 */
function aplanar( array $nodos, array &$estructura, array &$contenido ): void {
	foreach ( $nodos as $nodo ) {
		$id   = (string) ( $nodo['id'] ?? '' );
		$tipo = (string) ( $nodo['tipo'] ?? '' );
		if ( '' === $id || '' === $tipo ) {
			continue;
		}

		$bloque = array( 'id' => $id, 'tipo' => $tipo );

		// La clave es "props", no "contenido" — y adentro viene también
		// "_estilo_bloque" mezclado con el contenido, que es exactamente
		// la forma que Sofia_Pagina espera aplanada como "{id}.{campo}".
		//
		// Leer la clave equivocada no da error: el árbol se renderiza
		// igual, con los defaults del tema. La primera corrida de este
		// arnés produjo seis páginas sobre la costa de Jalisco cuando los
		// pedidos eran de odontología, arquitectura y SaaS — y las midió
		// con 0 errores, porque medir el default mide una pieza correcta.
		// Un arnés puede estar verde y estar midiendo otra cosa.
		foreach ( (array) ( $nodo['props'] ?? array() ) as $campo => $valor ) {
			$contenido[ $id . '.' . $campo ] = $valor;
		}

		if ( ! empty( $nodo['hijos'] ) && is_array( $nodo['hijos'] ) ) {
			$hijos = array();
			aplanar( $nodo['hijos'], $hijos, $contenido );
			$bloque['hijos'] = $hijos;
		}

		$estructura[] = $bloque;
	}
}

/** La misma página que usa render-piezas.php, para medir lo mismo. */
function pagina_html( string $html, string $css, string $titulo ): string {
	return '<!doctype html><html lang="es"><head><meta charset="utf-8">'
		. '<meta name="viewport" content="width=device-width, initial-scale=1">'
		. '<title>' . htmlspecialchars( $titulo, ENT_QUOTES ) . '</title>'
		. '<style>*,*::before,*::after{box-sizing:border-box}html,body{margin:0;padding:0}</style>'
		. '<style>' . $css . '</style>'
		. '</head><body>' . $html . '</body></html>';
}

function recortar( string $t, int $n ): string {
	$t = trim( preg_replace( '/\s+/', ' ', $t ) );
	return mb_strlen( $t ) > $n ? mb_substr( $t, 0, $n - 1 ) . '…' : $t;
}

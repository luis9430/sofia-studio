<?php
/**
 * Genera un LOTE de componentes por IA y arma una página con todos, para
 * poder juzgar el enfoque de un vistazo en vez de uno por uno.
 *
 *     php tests/lote-componentes.php                 # los pedidos de abajo
 *     php tests/lote-componentes.php mis-pedidos.txt # uno por línea
 *
 * Deja dos archivos en tests/salida-lote/:
 *   - lote.html       abrilo en el navegador: todos los componentes, con
 *                     su pedido arriba y sus avisos abajo.
 *   - lote.json       las definiciones crudas, por si querés guardar
 *                     alguna en el sitio después.
 *
 * Por qué existe: con 4 componentes generados no alcanza para saber si
 * este enfoque sirve. Hace falta volumen —20, 30— y verlos juntos, que
 * es cuando aparece el patrón: qué pide bien, dónde se confunde, qué
 * defectos se repiten. Pedirlos de a uno por el editor es demasiado
 * lento para esa pregunta.
 *
 * NO guarda nada en el sitio. El lote es para mirar y descartar.
 *
 * Las llamadas van EN PARALELO (curl_multi): generar 20 componentes en
 * serie, a ~15s cada uno, son cinco minutos de espera; en paralelo es
 * poco más que el más lento.
 */

// --- Config: se lee del wp-config del sitio, o de variables de entorno.
$url_gopress = getenv( 'SOFIA_GOPRESS_URL' ) ?: 'http://localhost:8090';
$sitio       = getenv( 'SOFIA_GOPRESS_SITIO' ) ?: 'costalegre';
$token       = getenv( 'SOFIA_GOPRESS_TOKEN' ) ?: '';

// --- Pedidos por defecto: variados a propósito, para ver dónde se rompe.
//
// Mezclan lo que el catálogo fijo NO puede hacer (cortes, superposición,
// formas) con casos normales, y algunos deliberadamente vagos: si el
// enfoque solo funciona con pedidos muy específicos, eso también es un
// resultado que vale saber.
$pedidos_por_defecto = array(
	// Formas y recortes
	'un hero con la imagen cortada en diagonal y el título encima',
	'una sección con el fondo en forma de onda abajo',
	'una tarjeta con la esquina superior derecha doblada como papel',

	// Superposición y profundidad
	'una tarjeta de precio con efecto vidrio esmerilado',
	'un bloque donde la imagen sobresale del borde de la sección',
	'dos tarjetas superpuestas, una corrida hacia abajo y a la derecha',

	// Tipografía como elemento gráfico
	'una cita con comillas gigantes semitransparentes detrás del texto',
	'un título enorme donde una palabra tiene el color de marca',
	'una sección con un número gigante de fondo, muy tenue',

	// Layout que el catálogo no arma
	'una franja de estadísticas donde cada número está en un hexágono',
	'una grilla de fotos donde la primera ocupa el doble',
	'un timeline vertical con una línea que conecta los puntos',

	// Casos normales, para contrastar
	'una sección de tres beneficios con ícono, título y texto',
	'un bloque de llamada a la acción con botón',

	// Vagos a propósito
	'algo moderno para presentar el equipo',
	'una sección que se vea premium',
);

// --- Argumentos
$archivo_pedidos = $argv[1] ?? '';
if ( '' !== $archivo_pedidos ) {
	if ( ! is_readable( $archivo_pedidos ) ) {
		fwrite( STDERR, "No se pudo leer $archivo_pedidos\n" );
		exit( 1 );
	}
	$pedidos = array_values( array_filter( array_map( 'trim', file( $archivo_pedidos ) ) ) );
} else {
	$pedidos = $pedidos_por_defecto;
}

if ( '' === $token ) {
	$token = leer_token_del_wp_config();
}
if ( '' === $token ) {
	fwrite( STDERR, "Falta el token del sitio.\n" );
	fwrite( STDERR, "Pasalo con: SOFIA_GOPRESS_TOKEN=... php tests/lote-componentes.php\n" );
	exit( 1 );
}

printf( "Generando %d componentes en paralelo contra %s (sitio %s)…\n\n", count( $pedidos ), $url_gopress, $sitio );

$inicio     = microtime( true );
$respuestas = generar_en_paralelo( $url_gopress, $sitio, $token, $pedidos );
$segundos   = round( microtime( true ) - $inicio, 1 );

// --- Validar cada uno con el contrato REAL del tema.
require_once __DIR__ . '/stubs-wordpress.php';
$base = dirname( __DIR__ ) . '/';
require_once $base . 'inc/class-estilo-global.php';
require_once $base . 'inc/class-componente.php';
require_once $base . 'inc/class-css-generado.php';
require_once $base . 'inc/class-definicion-generada.php';
require_once $base . 'inc/class-componente-generado.php';

$resultados = array();
$ok         = 0;
$con_avisos = 0;

foreach ( $pedidos as $i => $pedido ) {
	$cruda = $respuestas[ $i ];

	if ( ! is_array( $cruda ) ) {
		$resultados[] = array(
			'pedido' => $pedido,
			'error'  => is_string( $cruda ) ? $cruda : 'el modelo no devolvió una descripción',
		);
		continue;
	}

	$avisos     = array();
	$definicion = Sofia_Definicion_Generada::validar( $cruda, $avisos );

	if ( null === $definicion ) {
		$resultados[] = array(
			'pedido' => $pedido,
			'error'  => 'descripción rechazada: ' . implode( ' ', $avisos ),
		);
		continue;
	}

	$componente = new Sofia_Componente_Generado( $definicion['tipo'], 'lote' . $i );
	$componente->definir( $definicion );

	$resultados[] = array(
		'pedido'     => $pedido,
		'definicion' => $definicion,
		'html'       => $componente->render(),
		'avisos'     => $avisos,
	);
	++$ok;
	if ( ! empty( $avisos ) ) {
		++$con_avisos;
	}
}

// --- Salida
$dir = __DIR__ . '/salida-lote';
if ( ! is_dir( $dir ) ) {
	mkdir( $dir, 0777, true );
}

file_put_contents( $dir . '/lote.json', json_encode( $resultados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
file_put_contents( $dir . '/lote.html', armar_pagina( $resultados ) );

printf( "\n%d de %d válidos  ·  %d con avisos  ·  %ss\n", $ok, count( $pedidos ), $con_avisos, $segundos );
printf( "\nAbrí: %s\n\n", $dir . '/lote.html' );

foreach ( $resultados as $r ) {
	if ( isset( $r['error'] ) ) {
		printf( "  FALLO  %-56s %s\n", recortar( $r['pedido'], 54 ), recortar( $r['error'], 60 ) );
	} elseif ( ! empty( $r['avisos'] ) ) {
		printf( "  aviso  %-56s %s\n", recortar( $r['pedido'], 54 ), recortar( $r['avisos'][0], 60 ) );
	}
}

// ---------------------------------------------------------------------

/**
 * Lee el AgenteToken del wp-config del contenedor, para no tener que
 * pasarlo a mano en cada corrida.
 */
function leer_token_del_wp_config(): string {
	$salida = array();
	@exec( 'wsl -e bash -lc "docker exec gopress-app-costalegre grep SOFIA_GOPRESS_TOKEN /var/www/html/wp-config.php 2>/dev/null"', $salida );
	foreach ( $salida as $linea ) {
		if ( preg_match( "/'SOFIA_GOPRESS_TOKEN',\s*'([^']+)'/", $linea, $m ) ) {
			return $m[1];
		}
	}
	return '';
}

/**
 * Lanza todas las generaciones a la vez.
 *
 * En serie, 20 pedidos a ~15s son cinco minutos de espera; en paralelo
 * tarda poco más que el más lento. Para un script cuyo punto es ver
 * volumen, esa diferencia decide si se usa o no.
 *
 * @param string[] $pedidos
 * @return array<int,array<string,mixed>|string> La descripción de cada
 *         uno, o un string con el error.
 */
function generar_en_paralelo( string $url_gopress, string $sitio, string $token, array $pedidos ): array {
	$multi   = curl_multi_init();
	$handles = array();

	foreach ( $pedidos as $i => $pedido ) {
		$ch = curl_init();
		curl_setopt_array(
			$ch,
			array(
				CURLOPT_URL            => rtrim( $url_gopress, '/' ) . '/sites/' . rawurlencode( $sitio ) . '/ia/generar-componente?token=' . rawurlencode( $token ),
				CURLOPT_POST           => true,
				CURLOPT_POSTFIELDS     => json_encode( array( 'prompt' => $pedido ) ),
				CURLOPT_HTTPHEADER     => array( 'Content-Type: application/json' ),
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_TIMEOUT        => 240,
			)
		);
		curl_multi_add_handle( $multi, $ch );
		$handles[ $i ] = $ch;
	}

	$activos = null;
	do {
		$estado = curl_multi_exec( $multi, $activos );
		if ( $activos ) {
			curl_multi_select( $multi, 1.0 );
		}
	} while ( $activos && CURLM_OK === $estado );

	$respuestas = array();
	foreach ( $handles as $i => $ch ) {
		$cuerpo = curl_multi_getcontent( $ch );
		$codigo = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_multi_remove_handle( $multi, $ch );
		curl_close( $ch );

		if ( 200 !== $codigo ) {
			$respuestas[ $i ] = 'HTTP ' . $codigo . ': ' . recortar( (string) $cuerpo, 120 );
			continue;
		}
		$datos            = json_decode( (string) $cuerpo, true );
		$respuestas[ $i ] = is_array( $datos ) && is_array( $datos['definicion'] ?? null )
			? $datos['definicion']
			: 'respuesta sin definición';

		printf( "  [%2d/%d] %s\n", $i + 1, count( $handles ), recortar( $GLOBALS['pedidos'][ $i ] ?? '', 70 ) );
	}

	curl_multi_close( $multi );
	ksort( $respuestas );
	return $respuestas;
}

/**
 * Arma la página de revisión: cada componente con su pedido arriba y sus
 * avisos abajo, uno tras otro.
 *
 * El CSS de cada uno ya viene acotado a su propio bloque
 * (Sofia_CSS_Generado), así que todos pueden convivir en un documento
 * sin pisarse — que es justamente lo que permite verlos juntos.
 *
 * @param array<int,array<string,mixed>> $resultados
 */
function armar_pagina( array $resultados ): string {
	$css_componentes = '';
	$cuerpo          = '';

	foreach ( $resultados as $i => $r ) {
		$n      = $i + 1;
		$pedido = htmlspecialchars( $r['pedido'], ENT_QUOTES );

		if ( isset( $r['error'] ) ) {
			$cuerpo .= '<section class="pieza pieza--fallo">'
				. '<header><span class="n">' . $n . '</span><p class="pedido">' . $pedido . '</p></header>'
				. '<p class="error">' . htmlspecialchars( $r['error'], ENT_QUOTES ) . '</p>'
				. '</section>';
			continue;
		}

		$css_componentes .= $r['definicion']['css'] . "\n";

		$avisos = '';
		if ( ! empty( $r['avisos'] ) ) {
			$avisos = '<ul class="avisos">';
			foreach ( $r['avisos'] as $a ) {
				$avisos .= '<li>' . htmlspecialchars( $a, ENT_QUOTES ) . '</li>';
			}
			$avisos .= '</ul>';
		}

		$cuerpo .= '<section class="pieza">'
			. '<header>'
			. '<span class="n">' . $n . '</span>'
			. '<p class="pedido">' . $pedido . '</p>'
			. '<p class="meta">' . htmlspecialchars( $r['definicion']['nombre'], ENT_QUOTES )
			. ' · ' . count( $r['definicion']['campos'] ) . ' campos'
			. ' · ' . strlen( $r['definicion']['css'] ) . ' chars de CSS</p>'
			. '</header>'
			. '<div class="render">' . $r['html'] . '</div>'
			. $avisos
			. '</section>';
	}

	return '<!doctype html><html lang="es"><head><meta charset="utf-8">'
		. '<meta name="viewport" content="width=device-width, initial-scale=1">'
		. '<title>Lote de componentes generados</title>'
		. '<style>'
		. 'body{margin:0;background:#f4f4f5;font:15px/1.5 system-ui,sans-serif;color:#18181b}'
		. '.cab{padding:28px 24px;background:#18181b;color:#fafafa}'
		. '.cab h1{margin:0 0 6px;font-size:20px}'
		. '.cab p{margin:0;opacity:.7;font-size:13px}'
		. '.pieza{margin:22px auto;max-width:1100px;background:#fff;border:1px solid #e4e4e7;border-radius:6px;overflow:hidden}'
		. '.pieza header{padding:14px 18px;border-bottom:1px solid #e4e4e7;background:#fafafa;display:flex;align-items:baseline;gap:12px;flex-wrap:wrap}'
		. '.n{font:600 13px/1 ui-monospace,monospace;color:#71717a;flex:none}'
		. '.pedido{margin:0;font-weight:500;flex:1 1 320px}'
		. '.meta{margin:0;font-size:12px;color:#71717a;font-family:ui-monospace,monospace}'
		// El render va SIN padding: un componente de ancho completo tiene
		// que verse como se vería en la página, no dentro de una caja.
		. '.render{overflow:hidden}'
		. '.avisos{margin:0;padding:10px 18px 10px 38px;background:#fffbeb;border-top:1px solid #fde68a;font-size:13px;color:#92400e}'
		. '.pieza--fallo{border-color:#fecaca}'
		. '.error{margin:0;padding:14px 18px;background:#fef2f2;color:#991b1b;font-size:13px}'
		. '</style>'
		. '<style>' . $css_componentes . '</style>'
		. '</head><body>'
		. '<div class="cab"><h1>Lote de componentes generados</h1>'
		. '<p>' . count( $resultados ) . ' pedidos · generado el ' . date( 'd/m/Y H:i' ) . '</p></div>'
		. $cuerpo
		. '</body></html>';
}

function recortar( string $texto, int $largo ): string {
	$texto = trim( preg_replace( '/\s+/', ' ', $texto ) );
	return mb_strlen( $texto ) > $largo ? mb_substr( $texto, 0, $largo - 1 ) . '…' : $texto;
}

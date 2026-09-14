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
	 * MEDIDAS_PERMITIDAS: whitelist de propiedades de tamaño/espaciado base
	 * del sitio — mismo criterio que COLORES_PERMITIDOS, pero acá el valor
	 * es siempre INPUT LIBRE (px/rem/%) o un token de Core Framework, nunca
	 * una opción fija — se valida con el mismo patrón que
	 * Sofia_Componente::PATRON_MEDIDA_CSS (Nivel 1/2) antes de emitirse.
	 */
	private const MEDIDAS_PERMITIDAS = array(
		'tamano_base'    => '--sofia-tamano-base',
		'espaciado_base' => '--sofia-espaciado-base',
	);

	/**
	 * Mismo patrón exacto que Sofia_Componente::PATRON_MEDIDA_CSS — un
	 * valor de medida (nunca un token de Core Framework, esos ya vienen
	 * resueltos por resolver_valor() antes de llegar acá) tiene que ser un
	 * número con unidad px/rem/% para poder emitirse, mismo criterio de
	 * "nunca CSS arbitrario" que el resto del editor.
	 */
	private const PATRON_MEDIDA_CSS = '/^-?\d+(\.\d+)?(px|rem|%)$/';

	/**
	 * Prefijo que distingue "este valor es una REFERENCIA a un token de
	 * Core Framework" de un valor fijo elegido a mano — ej. guardado como
	 * "cf:acento-primario" en vez de "#d97a4d". Sin un campo aparte en el
	 * JSON: el prefijo alcanza para decidir cómo emitir el valor. PÚBLICA
	 * (no solo esta clase la usa): Sofia_Componente::atributo_estilo()/
	 * atributo_estilo_bloque() (Nivel 1/2, estilo por campo/bloque) también
	 * la necesitan para el mismo botón "CF" del drawer — mismo mecanismo,
	 * reusado tal cual en vez de duplicarlo con un prefijo propio.
	 */
	const PREFIJO_TOKEN_CORE_FRAMEWORK = 'cf:';

	/**
	 * true si $valor_guardado es un token de Core Framework CON NOMBRE —
	 * a diferencia de un simple str_starts_with($valor, PREFIJO), esto
	 * rechaza "cf:" solo (sin nombre después del prefijo), que puede
	 * quedar guardado si el usuario activa el botón "CF" y cambia de campo
	 * sin llegar a escribir un nombre (ver CampoConToken.jsx). Sin este
	 * chequeo, resolver_valor("cf:") emite "var(--)" — CSS inválido que el
	 * navegador descarta en silencio, dejando la propiedad entera sin
	 * valor (bug real reportado por el usuario: un color de texto que
	 * desaparecía por completo).
	 */
	public static function es_token_con_nombre( string $valor_guardado ): bool {
		return str_starts_with( $valor_guardado, self::PREFIJO_TOKEN_CORE_FRAMEWORK )
			&& '' !== substr( $valor_guardado, strlen( self::PREFIJO_TOKEN_CORE_FRAMEWORK ) );
	}

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
	 * Lee el CSS REAL ya generado por Core Framework y extrae los nombres
	 * de sus custom properties (ej. "primary", "secondary", "primary-5") —
	 * corrige una suposición equivocada de una primera versión de esta
	 * integración: Core Framework NO prefija sus variables con "cf-" (se
	 * había asumido eso a partir de su código fuente, packages/core/src/
	 * cssGenerator/prefixer/variablePrefixer.ts, que SOPORTA un prefijo
	 * configurable, pero el export real de un proyecto nuevo no lo usa —
	 * confirmado mirando el CSS real exportado por el usuario: "--primary",
	 * "--primary-5", "--secondary", sin ningún "cf-").
	 *
	 * Como el archivo SÍ es legible desde PHP (vive en
	 * wp-content/uploads/core-framework/css/, mismo StylesheetStorage que
	 * ya usa enlazar_css_core_framework()), no hace falta que el usuario
	 * escriba un nombre a ciegas — se puede ofrecer un dropdown real. Una
	 * regex simple sobre declaraciones de nivel raíz (--nombre: valor;)
	 * alcanza para esto: no hace falta un parser CSS completo, solo los
	 * NOMBRES, nunca sus valores (que siguen resolviéndose por cascada del
	 * navegador, no por PHP).
	 *
	 * @return string[] Nombres SIN el "--" inicial, ordenados y sin
	 *         duplicados — vacío si el plugin no está activo o el archivo
	 *         no se pudo leer.
	 */
	public static function variables_core_framework(): array {
		return array_keys( self::variables_core_framework_con_valor() );
	}

	/**
	 * variables_core_framework_con_valor(): mismo archivo/parseo que
	 * variables_core_framework(), pero devuelve {nombre => valor CRUDO tal
	 * cual el CSS lo declara} — necesario para catalogo_tokens_visual()
	 * (ver el comentario largo ahí): un swatch de color con
	 * background:var(--primary) depende de que el navegador tenga el CSS
	 * de Core Framework CARGADO en ese documento — bug real encontrado
	 * probando en vivo: el panel del editor corre en wp-admin, y
	 * enlazar_css_core_framework() solo se engancha a wp_enqueue_scripts
	 * (frontend público, ver el hook al final del archivo) — en wp-admin
	 * ese CSS nunca se carga, así que TODOS los swatches se veían iguales
	 * (color heredado/default, ninguna var() resolvía a nada real). Con el
	 * valor YA resuelto en PHP, el swatch pinta el color REAL sin depender
	 * de la cascada del documento donde se muestra.
	 *
	 * Primera coincidencia de cada nombre (nunca la última) — confirmado
	 * en el CSS real: --primary/--secondary/etc. solo se declaran UNA vez,
	 * pero --shadow-primary aparece 2 veces (modo claro/oscuro del propio
	 * plugin, selectores distintos) — la primera es la del selector base
	 * (:root sin variante de tema), consistente con lo que el navegador
	 * usaría por defecto sin ningún data-theme activo.
	 *
	 * @return array<string,string> {nombre sin "--" => valor crudo, ej.
	 *         "hsla(238,100%,62%,1)" o "clamp(1rem,...)"} — vacío mismo
	 *         criterio que variables_core_framework() (plugin inactivo o
	 *         archivo no legible).
	 */
	private static function variables_core_framework_con_valor(): array {
		if ( ! self::core_framework_activo() ) {
			return array();
		}

		$ruta = \CoreFramework\StylesheetStorage::get_path();
		if ( ! is_readable( $ruta ) ) {
			return array();
		}

		$css = file_get_contents( $ruta );
		if ( false === $css ) {
			return array();
		}

		preg_match_all( '/--([a-zA-Z0-9_-]+)\s*:\s*([^;]+);/', $css, $coincidencias, PREG_SET_ORDER );
		$valores = array();
		foreach ( $coincidencias as $match ) {
			$nombre = $match[1];
			if ( ! isset( $valores[ $nombre ] ) ) { // primera coincidencia gana, ver el comentario largo arriba.
				$valores[ $nombre ] = trim( $match[2] );
			}
		}

		// Resolver referencias var(--otro-nombre) DENTRO de un valor —
		// bug real encontrado probando en vivo: shadow-l/m/s/xl/xs se
		// declaran como "0 3px 12px var(--shadow-primary)" (una sombra
		// completa que REFERENCIA otra variable para el color) — sin
		// resolver esa referencia, el valor "crudo" que se manda al
		// selector visual seguía conteniendo un var() sin resolver, y
		// box-shadow no se veía distinto entre "Sutil" y "Fuerte" por el
		// mismo motivo original (esa variable tampoco existe en
		// wp-admin). Se resuelve ACÁ, una sola vez, para que CUALQUIER
		// consumidor (catálogo visual, selector avanzado) reciba
		// siempre valores ya completos, nunca con un var() colgante.
		foreach ( $valores as $nombre => $valor ) {
			$valores[ $nombre ] = self::resolver_referencias_var( $valor, $valores );
		}

		ksort( $valores );
		return $valores;
	}

	/**
	 * resolver_referencias_var( $valor, $todos_los_valores ): reemplaza
	 * CADA "var(--nombre)" (u opcional "var(--nombre, fallback)") dentro
	 * de $valor por el valor REAL de esa variable en $todos_los_valores —
	 * recursivo (una variable puede referenciar otra que a su vez
	 * referencia otra), con un límite de 5 niveles de profundidad como
	 * protección contra una referencia circular en el CSS real del plugin
	 * (nunca vista en la práctica, pero un bucle infinito sería peor que
	 * dejar un var() sin resolver en el caso límite). Una referencia a un
	 * nombre DESCONOCIDO (no está en $todos_los_valores) se deja tal cual
	 * — nunca se inventa un valor, mejor un var() colgante ocasional que
	 * silencioso texto incorrecto.
	 */
	private static function resolver_referencias_var( string $valor, array $todos_los_valores, int $profundidad = 0 ): string {
		if ( $profundidad >= 5 || false === strpos( $valor, 'var(--' ) ) {
			return $valor;
		}

		$resuelto = preg_replace_callback(
			'/var\(\s*--([a-zA-Z0-9_-]+)\s*(?:,\s*([^)]+))?\)/',
			function ( $match ) use ( $todos_los_valores, $profundidad ) {
				$nombre_referenciado = $match[1];
				if ( isset( $todos_los_valores[ $nombre_referenciado ] ) ) {
					return self::resolver_referencias_var( $todos_los_valores[ $nombre_referenciado ], $todos_los_valores, $profundidad + 1 );
				}
				return $match[0]; // nombre desconocido — se deja tal cual, nunca se inventa un valor.
			},
			$valor
		);

		return null === $resuelto ? $valor : $resuelto; // preg_replace_callback devuelve null solo ante un error de regex real.
	}

	/**
	 * PREFIJOS_CATEGORIA_CORE_FRAMEWORK: a qué CATEGORÍA pertenece cada
	 * custom property de Core Framework, según su prefijo de nombre — ver
	 * el catálogo completo auditado a mano (memoria de producto "Sofia
	 * Studio: sistema de estilo") sobre el CSS real exportado. Necesario
	 * porque el datalist de sugerencias, sin esto, ofrecía las 154
	 * variables del sitio TAL CUAL en cualquier campo — nada impedía
	 * escribir "bg-body" (una variable de COLOR) en "Radio de borde" (que
	 * espera "radius-*"), un valor sintácticamente válido pero sin sentido
	 * para esa propiedad. Con esto, cada CampoConToken solo sugiere las
	 * variables de SU categoría — sigue siendo texto libre (nunca se
	 * bloquea lo que no está en la lista), solo cambia qué aparece en el
	 * <datalist>.
	 *
	 * Orden importa: "radius"/"shadow"/"space" antes que el genérico de
	 * color, porque un nombre como "shadow-primary" también empieza con un
	 * prefijo de color-family conocido si se buscara al revés — se
	 * recorre en este orden y gana el primer prefijo que matchee.
	 */
	private const PREFIJOS_CATEGORIA_CORE_FRAMEWORK = array(
		'radius'    => array( 'radius-' ),
		'shadow'    => array( 'shadow-' ),
		'space'     => array( 'space-' ),
		'texto'     => array( 'text-' ),
		'color'     => array(
			'primary',
			'secondary',
			'tertiary',
			'light',
			'dark',
			'success',
			'error',
			'bg-',
			'border-',
		),
	);

	/**
	 * Clasifica UNA variable ya conocida (nombre sin "--") contra
	 * PREFIJOS_CATEGORIA_CORE_FRAMEWORK — "otras" para cualquier nombre que
	 * no matchee ninguna categoría reconocida (ej. una variable específica
	 * de un Componente del propio proyecto de Core Framework, como
	 * "btn-space"): sigue apareciendo en el datalist SIN categorizar, nunca
	 * se descarta silenciosamente solo porque no encaja en el catálogo
	 * genérico.
	 */
	private static function categoria_de_variable( string $nombre ): string {
		foreach ( self::PREFIJOS_CATEGORIA_CORE_FRAMEWORK as $categoria => $prefijos ) {
			foreach ( $prefijos as $prefijo ) {
				if ( str_starts_with( $nombre, $prefijo ) ) {
					return $categoria;
				}
			}
		}
		return 'otras';
	}

	/**
	 * Mismas variables de variables_core_framework(), pero agrupadas por
	 * categoría — {categoria => [nombres]} — para que el frontend pueda
	 * ofrecer sugerencias específicas por control (ver CampoConToken.jsx,
	 * prop `categoria`). "otras" siempre existe en el resultado (aunque
	 * vacío) para que el frontend no tenga que chequear su ausencia.
	 */
	public static function variables_core_framework_por_categoria(): array {
		$agrupadas = array( 'color' => array(), 'texto' => array(), 'radius' => array(), 'shadow' => array(), 'space' => array(), 'otras' => array() );
		foreach ( self::variables_core_framework() as $nombre ) {
			$agrupadas[ self::categoria_de_variable( $nombre ) ][] = $nombre;
		}
		return $agrupadas;
	}

	/**
	 * variables_core_framework_por_categoria_con_valor(): mismo agrupado
	 * que arriba, pero cada entrada es {nombre, valor} en vez de solo el
	 * nombre — decisión de arquitectura tras probar en vivo: el modo
	 * "Avanzado" de CampoTokenVisual.jsx usaba un <datalist> HTML nativo
	 * (autocompletado de un <input>), que NO puede mostrar un swatch junto
	 * a cada opción (limitación de la propia plataforma, no de este
	 * código) — con 154 variables reales, una lista de solo nombres como
	 * "tertiary-30" es, en palabras del usuario, "a granel": no se
	 * entiende qué es sin verlo pintado. Este método alimenta un selector
	 * Preact PROPIO (ver SelectorTokenAvanzado.jsx) que sí puede mostrar
	 * el color real de CADA variable, variantes de tono incluidas — a
	 * diferencia de catalogo_tokens_visual() (que deliberadamente excluye
	 * esas variantes del modo básico), acá SÍ viajan las 154 completas,
	 * es el modo avanzado.
	 *
	 * @return array<string,array<int,array{nombre:string,valor:string}>>
	 */
	public static function variables_core_framework_por_categoria_con_valor(): array {
		$valores   = self::variables_core_framework_con_valor();
		$agrupadas = array( 'color' => array(), 'texto' => array(), 'radius' => array(), 'shadow' => array(), 'space' => array(), 'otras' => array() );
		foreach ( $valores as $nombre => $valor ) {
			$agrupadas[ self::categoria_de_variable( $nombre ) ][] = array( 'nombre' => $nombre, 'valor' => $valor );
		}
		return $agrupadas;
	}

	/**
	 * ETIQUETAS_ESCALA: traduce el SUFIJO de escala de un token
	 * (space-xs/s/m/l/xl, radius-xs/s/m/l/xl/full, shadow-xs/s/m/l/xl) a
	 * lenguaje humano — decisión de arquitectura tomada con el usuario
	 * ("lenguaje y lo que se puede preview estaría bien", ver la
	 * conversación en la memoria de producto): con 154 variables reales
	 * (confirmado leyendo el CSS exportado de Core Framework), un nombre
	 * técnico como "space-m" o "radius-xl" no le dice nada a un usuario
	 * que solo quiere "un poco más de aire" — necesita una etiqueta, no
	 * memorizar el catálogo. "xs/s/m/l/xl" NO se traduce literal a
	 * "muy chico/chico/mediano/grande/muy grande" (sonaría a talles de
	 * ropa) — se usa vocabulario de diseño real, mismo que el usuario
	 * pidió explícitamente ("Compacto / Base / Amplio").
	 *
	 * Cada categoría tiene su propio set de etiquetas (nunca "Compacto"
	 * para una sombra) porque "poca sombra" y "poco espaciado" no son la
	 * misma idea aunque ambos sean una escala xs..xl — mismo criterio que
	 * ya separaba "categoria" en variables_core_framework_por_categoria().
	 */
	private const ETIQUETAS_ESCALA = array(
		'space'  => array(
			'xs' => 'Mínimo',
			's'  => 'Compacto',
			'm'  => 'Base',
			'l'  => 'Amplio',
			'xl' => 'Máximo',
		),
		'radius' => array(
			'xs'   => 'Recto',
			's'    => 'Sutil',
			'm'    => 'Base',
			'l'    => 'Redondeado',
			'xl'   => 'Muy redondeado',
			'full' => 'Circular',
		),
		'shadow' => array(
			'xs' => 'Ninguna',
			's'  => 'Sutil',
			'm'  => 'Base',
			'l'  => 'Marcada',
			'xl' => 'Fuerte',
		),
	);

	/**
	 * ETIQUETAS_COLOR: nombre humano de cada color BASE (sin sufijo de
	 * tono/opacidad, ver etiqueta_de_color() abajo) — a diferencia de las
	 * escalas de arriba, un color no necesita traducción de "tamaño", el
	 * propio nombre + su swatch visual (ver catalogo_tokens_visual()) ya
	 * comunican qué es. Solo se listan los 6 colores reales confirmados en
	 * el CSS exportado (primary/secondary/tertiary/success/error, más
	 * bg-body/bg-surface/border-primary semánticos) — cualquier otro color
	 * que el CSS real tenga pero no esté acá cae a "otras" (mismo criterio
	 * de "nunca se descarta, solo queda sin categorizar" que
	 * categoria_de_variable()).
	 */
	private const ETIQUETAS_COLOR = array(
		'primary'       => 'Primario',
		'secondary'     => 'Secundario',
		'tertiary'      => 'Terciario',
		'success'       => 'Éxito',
		'error'         => 'Error',
		'bg-body'       => 'Fondo',
		'bg-surface'    => 'Fondo (superficie)',
		'border-primary' => 'Borde',
	);

	/**
	 * etiqueta_de( $categoria, $nombre ): traduce UN nombre técnico
	 * (ej. "space-l", "primary-40") a su etiqueta humana — usa
	 * ETIQUETAS_ESCALA para space/radius/shadow (matchea el sufijo tras
	 * el último "-") y ETIQUETAS_COLOR para color (matchea el nombre BASE,
	 * ignorando sufijos de tono "-40"/opacidad "-d-1"/etc, ver el
	 * comentario largo abajo). Devuelve null si no hay traducción
	 * conocida — el catálogo visual SOLO expone tokens con etiqueta (ver
	 * catalogo_tokens_visual()), cualquier variable sin traducir queda
	 * disponible únicamente en el modo "avanzado" (texto libre,
	 * CampoConToken.jsx sin cambios), nunca se le miente al usuario con
	 * una etiqueta inventada.
	 */
	private static function etiqueta_de( string $categoria, string $nombre ): ?string {
		if ( 'color' === $categoria ) {
			return self::etiqueta_de_color( $nombre );
		}
		if ( ! isset( self::ETIQUETAS_ESCALA[ $categoria ] ) ) {
			return null;
		}
		$sufijo = substr( $nombre, strrpos( $nombre, '-' ) + 1 );
		return self::ETIQUETAS_ESCALA[ $categoria ][ $sufijo ] ?? null;
	}

	/**
	 * etiqueta_de_color( $nombre ): el CSS real de Core Framework no
	 * exporta SOLO "primary" — exporta la familia completa ("primary",
	 * "primary-5".."primary-90" de opacidad, "primary-d-1".."d-4" oscuros,
	 * "primary-l-1".."l-4" claros — confirmado contando 154 variables
	 * reales contra ~6 colores base, la mayoría son variantes de tono).
	 * El catálogo VISUAL (para el usuario elegir con etiqueta+swatch) solo
	 * expone el color BASE de cada familia — mostrar 10+ variantes de
	 * "Primario" con nombres tipo "Primario 40% opacidad" sería exactamente
	 * la sobrecarga que esta pieza busca evitar. Las variantes de tono
	 * siguen existiendo y siguen siendo válidas como valor "cf:primary-40"
	 * a mano en el modo avanzado — no se pierden, solo no aparecen en el
	 * selector visual básico.
	 */
	private static function etiqueta_de_color( string $nombre ): ?string {
		return self::ETIQUETAS_COLOR[ $nombre ] ?? null;
	}

	/**
	 * catalogo_tokens_visual(): catálogo de tokens de Core Framework
	 * AGRUPADO por categoría y con ETIQUETA HUMANA — la pieza que permite
	 * un selector visual real (ver CampoTokenVisual.jsx, admin-app) en vez
	 * del <input> de texto libre con autocompletado que CampoConToken.jsx
	 * ya ofrecía. Decisión de arquitectura de esta conversación (ver la
	 * memoria de producto): Core Framework en sí es un sistema de diseño
	 * sólido (154 tokens, escalas coherentes, responsive fluido real vía
	 * clamp()) — el problema nunca fue el plugin, fue que Sofia Studio lo
	 * exponía como texto libre sin ningún criterio de "esto se ve así" ni
	 * traducción a lenguaje humano.
	 *
	 * Solo incluye tokens con ETIQUETA CONOCIDA (ver etiqueta_de()) — a
	 * diferencia de variables_core_framework_por_categoria() (usada por el
	 * modo avanzado de CampoConToken.jsx, que sigue mostrando las 154
	 * variables sin filtrar como sugerencias de texto libre), este
	 * catálogo es deliberadamente MÁS CHICO: 5-6 pasos por escala + los
	 * colores base, nunca las 154 variantes completas — ver el
	 * comentario largo en etiqueta_de_color().
	 *
	 * $preview (solo categoría "color"): el VALOR CRUDO real del token
	 * (ej. "hsla(238,100%,62%,1)"), leído directo del CSS de Core
	 * Framework — NUNCA "var(--primary)". Bug real encontrado probando en
	 * vivo: el panel del editor corre en wp-admin, donde el CSS de Core
	 * Framework no está cargado (enlazar_css_core_framework() solo se
	 * engancha al frontend público) — con "var(--primary)" como
	 * background, TODOS los swatches se veían idénticos (la variable no
	 * resolvía a nada en ese documento). Con el valor ya resuelto en PHP
	 * (ver variables_core_framework_con_valor()), el swatch pinta el color
	 * real sin depender de qué CSS esté cargado donde se muestra — mismo
	 * criterio "lo que se puede preview" pedido explícitamente por el
	 * usuario, ahora cumplido de verdad.
	 *
	 * @return array<string,array<int,array{token:string,etiqueta:string,preview?:string}>>
	 *         {categoria => [{token, etiqueta, preview?}]} — mismas 6
	 *         categorías que variables_core_framework_por_categoria()
	 *         ("otras" siempre presente, aunque vacía: esa categoría no
	 *         tiene traducción posible por definición).
	 */
	public static function catalogo_tokens_visual(): array {
		$agrupadas = self::variables_core_framework_por_categoria();
		$valores   = self::variables_core_framework_con_valor();
		$catalogo  = array();

		// Categorías con preview real — bug real corregido tras probar en
		// vivo: originalmente SOLO "color" mandaba preview, radius/shadow/
		// space quedaban sin nada y el frontend armaba
		// "var(--{token})" a mano (ver el CSS de admin-app/src/style.css,
		// .sofia-token-visual__muestra) — mismo bug que ya tuvimos con
		// color: esa variable nunca existe en el documento de wp-admin
		// (Core Framework solo se enlaza al frontend público, ver
		// enlazar_css_core_framework()), así que TODAS las muestras se
		// veían idénticas (radio "Circular" igual a "Recto", etc.). Ahora
		// las 4 categorías con escala real mandan su valor CRUDO ya
		// resuelto — el mismo criterio que color, aplicado también acá.
		$categorias_con_preview = array( 'color', 'radius', 'shadow', 'space' );

		foreach ( $agrupadas as $categoria => $nombres ) {
			$catalogo[ $categoria ] = array();
			foreach ( $nombres as $nombre ) {
				$etiqueta = self::etiqueta_de( $categoria, $nombre );
				if ( null === $etiqueta ) {
					continue; // sin traducción conocida — queda solo en modo avanzado (texto libre).
				}
				$entrada = array( 'token' => $nombre, 'etiqueta' => $etiqueta );
				if ( in_array( $categoria, $categorias_con_preview, true ) && isset( $valores[ $nombre ] ) ) {
					$entrada['preview'] = $valores[ $nombre ];
				}
				$catalogo[ $categoria ][] = $entrada;
			}
		}

		return $catalogo;
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
	 * Core Framework" en el panel), emite var(--{token}, $fallback) — el
	 * propio navegador resuelve la cascada: si el CSS de Core Framework
	 * definió ese token, gana; si no (plugin desactivado, token borrado,
	 * nombre mal escrito), cae a $fallback sin romper nada. Si
	 * $valor_guardado es un hex normal, se devuelve tal cual.
	 *
	 * SIN prefijo "cf-" propio — corrige una suposición equivocada de la
	 * primera versión: Core Framework no prefija sus variables reales
	 * (confirmado mirando el CSS exportado real: "--primary",
	 * "--secondary", nunca "--cf-primary"), ver el comentario largo en
	 * variables_core_framework().
	 *
	 * $fallback nunca es obligatorio — un color de respaldo vacío en
	 * var(--x, ) es CSS válido (la declaración completa se ignora si
	 * ninguno de los dos resuelve), mismo comportamiento que no tener nada
	 * configurado.
	 *
	 * PÚBLICO: mismo motivo que PREFIJO_TOKEN_CORE_FRAMEWORK — reusado tal
	 * cual desde Sofia_Componente para el estilo por campo/bloque.
	 */
	public static function resolver_valor( string $valor_guardado, string $fallback = '' ): string {
		if ( ! str_starts_with( $valor_guardado, self::PREFIJO_TOKEN_CORE_FRAMEWORK ) ) {
			return $valor_guardado;
		}
		$token = substr( $valor_guardado, strlen( self::PREFIJO_TOKEN_CORE_FRAMEWORK ) );
		return 'var(--' . esc_attr( $token ) . ( '' !== $fallback ? ', ' . $fallback : '' ) . ')';
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

		$medidas = is_array( $estilo['medidas'] ?? null ) ? $estilo['medidas'] : array();
		foreach ( self::MEDIDAS_PERMITIDAS as $clave => $variable_css ) {
			if ( empty( $medidas[ $clave ] ) || ! is_string( $medidas[ $clave ] ) ) {
				continue;
			}
			$valor = self::resolver_valor( $medidas[ $clave ] );
			if ( str_starts_with( $valor, 'var(' ) ) {
				// Token de Core Framework — ya viene resuelto por
				// resolver_valor(), nunca se valida como medida CSS (el
				// contenido real vive del lado de Core Framework, no acá).
				$declaraciones[] = $variable_css . ':' . $valor;
				continue;
			}
			// Input libre (px/rem/%) — mismo criterio de whitelist que
			// Sofia_Componente::PATRON_MEDIDA_CSS: un valor que no matchea
			// se ignora en silencio, nunca CSS arbitrario.
			if ( preg_match( self::PATRON_MEDIDA_CSS, $valor ) ) {
				$declaraciones[] = $variable_css . ':' . esc_attr( $valor );
			}
		}

		if ( empty( $declaraciones ) ) {
			return;
		}

		echo '<style id="sofia-estilo-global">:root{' . implode( ';', $declaraciones ) . '}</style>' . "\n";
	}
}

add_action( 'wp_enqueue_scripts', array( 'Sofia_Estilo_Global', 'enlazar_css_core_framework' ) );
add_action( 'wp_head', array( 'Sofia_Estilo_Global', 'imprimir' ) );

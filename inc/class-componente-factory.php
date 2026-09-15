<?php
/**
 * Sofia_Componente_Factory::crear($tipo, $props) instancia el Componente
 * correcto según el "tipo" de un bloque de la estructura de una
 * PlantillaPagina (ver internal/store/plantilla_pagina.go de GoPress,
 * BloqueEstructuraPlantilla.Tipo) — un match/switch simple, nunca
 * autodescubrimiento de clases por convención de nombre: agregar un
 * Componente nuevo es agregar su require_once (ver el bloque de abajo) y
 * un case acá, sin magia.
 */
class Sofia_Componente_Factory {

	/**
	 * @param string              $tipo  ej. "hero", "franja_beneficios",
	 *                                   "container".
	 * @param string              $id    ID de INSTANCIA de este bloque en la
	 *                                   página (ver Sofia_Componente::$id) —
	 *                                   distinto del tipo, generado por
	 *                                   GoPress (store.GenerarIDBloque).
	 * @param array<string,mixed> $props Props YA recortadas a este bloque
	 *                                    (ver Sofia_Pagina::resolver_bloques()).
	 * @param Sofia_Componente[]  $hijos Hijos YA resueltos (ver
	 *                                    Sofia_Componente::$hijos) — vacío
	 *                                    para cualquier tipo sin anidamiento,
	 *                                    que lo ignora por completo.
	 */
	public static function crear( string $tipo, string $id, array $props = array(), array $hijos = array() ): ?Sofia_Componente {
		switch ( $tipo ) {
			case 'hero':
				return new Sofia_Componente_Hero( $tipo, $id, $props, $hijos );
			case 'franja_beneficios':
				return new Sofia_Componente_Franja_Beneficios( $tipo, $id, $props, $hijos );
			case 'testimonios':
				return new Sofia_Componente_Testimonios( $tipo, $id, $props, $hijos );
			case 'faq':
				return new Sofia_Componente_FAQ( $tipo, $id, $props, $hijos );
			case 'cta':
				return new Sofia_Componente_CTA( $tipo, $id, $props, $hijos );
			case 'texto_libre':
				return new Sofia_Componente_Texto_Libre( $tipo, $id, $props, $hijos );
			case 'container':
				return new Sofia_Componente_Container( $tipo, $id, $props, $hijos );
			case 'image':
				return new Sofia_Componente_Image( $tipo, $id, $props, $hijos );
			case 'button':
				return new Sofia_Componente_Button( $tipo, $id, $props, $hijos );
			case 'divider':
				return new Sofia_Componente_Divider( $tipo, $id, $props, $hijos );
			case 'spacer':
				return new Sofia_Componente_Spacer( $tipo, $id, $props, $hijos );
			case 'aspect_ratio':
				return new Sofia_Componente_Aspect_Ratio( $tipo, $id, $props, $hijos );
			case 'heading':
				return new Sofia_Componente_Heading( $tipo, $id, $props, $hijos );
			case 'rich_text':
				return new Sofia_Componente_Rich_Text( $tipo, $id, $props, $hijos );
			case 'icon':
				return new Sofia_Componente_Icon( $tipo, $id, $props, $hijos );
			case 'video':
				return new Sofia_Componente_Video( $tipo, $id, $props, $hijos );
			case 'embed':
				return new Sofia_Componente_Embed( $tipo, $id, $props, $hijos );
			case 'avatar':
				return new Sofia_Componente_Avatar( $tipo, $id, $props, $hijos );
			case 'badge':
				return new Sofia_Componente_Badge( $tipo, $id, $props, $hijos );
			default:
				// Un tipo desconocido (plantilla más nueva que el tema
				// instalado, o dato corrupto) no debe tumbar el render de
				// TODA la página — se omite ese bloque en silencio, mismo
				// criterio que WordPress con un widget/bloque de Gutenberg
				// de un plugin desactivado.
				return null;
		}
	}

	/**
	 * TIPOS_REGISTRADOS es la ÚNICA lista de tipos que existe en el
	 * catálogo — agregar un Componente nuevo es agregarlo acá (y a
	 * crear(), y su require_once en functions.php); catalogo() la
	 * recorre para no duplicar la lista de tipos en dos lugares.
	 *
	 * 'container' se agrega acá en Fase 3 ("primitivas de layout" — ver la
	 * memoria de producto): Fase 1/2 lo dejaron deliberadamente AFUERA
	 * (ver el comentario largo en class-container.php) porque el
	 * mecanismo de anidamiento (drag-and-drop, líneas de inserción dentro
	 * de un container) todavía no existía del lado del editor visual —
	 * agregarlo antes hubiera ofrecido "+ Agregar bloque → Container" sin
	 * ninguna forma real de poner algo adentro salvo armando el JSON a
	 * mano. Con editor-iframe.js/App.jsx ya soportando anidamiento, este
	 * es el único cambio que faltaba del lado PHP.
	 *
	 * 'image'/'button' se agregan en Fase 0 (mismo plan — "vocabulario de
	 * primitivas"): junto con 'container' (Fase 1/3) y 'texto_libre' (ya
	 * existente, reusado tal cual como la primitiva "Text" — un solo
	 * campo editable, sin título ni estructura extra, mismo rol) completan
	 * el set mínimo de 5 piezas combinables (Section/Container/Text/Image/
	 * Button) que el plan define como alternativa a seguir sumando
	 * Componentes temáticos fijos.
	 *
	 * 'divider'/'spacer'/'aspect_ratio' se agregan en la Fase 1 del plan
	 * "50 primitivas de UI" (memoria de producto) — primeros 3 tipos
	 * nuevos genuinos de ese catálogo más grande, categoría Layout.
	 * "container" gana además un campo "variante" (ver class-container.php)
	 * que cubre Stack/Grid/Cluster/Split/Inline/Box del mismo catálogo sin
	 * agregar tipos nuevos acá — son presets de este mismo Componente, no
	 * clases separadas.
	 */
	private const TIPOS_REGISTRADOS = array( 'hero', 'franja_beneficios', 'testimonios', 'faq', 'cta', 'texto_libre', 'container', 'image', 'button', 'divider', 'spacer', 'aspect_ratio', 'heading', 'rich_text', 'icon', 'video', 'embed', 'avatar', 'badge' );

	/**
	 * Catálogo de bloques insertables — consumido por
	 * sofia/v1/catalogo-bloques (ver Sofia_REST_Editor), que a su vez
	 * alimenta el panel "agregar bloque" del editor (Nivel 2). Decisión de
	 * arquitectura confirmada: esta lista sale de los Componentes PHP
	 * REALES del tema instalado, nunca de una lista curada aparte en
	 * GoPress — así nunca se desincroniza de qué bloques existen de
	 * verdad en este sitio.
	 *
	 * @return array<int,array{tipo:string,nombre:string}>
	 */
	public static function catalogo(): array {
		$catalogo = array();
		foreach ( self::TIPOS_REGISTRADOS as $tipo ) {
			// ID dummy: este Componente nunca se renderiza como bloque real
			// de página, solo se instancia para leer su nombre() — el ID de
			// instancia real lo asigna GoPress (store.GenerarIDBloque) recién
			// cuando el usuario elige este tipo en "+ Agregar bloque".
			$componente = self::crear( $tipo, 'catalogo' );
			if ( null === $componente ) {
				continue;
			}
			$catalogo[] = array(
				'tipo'   => $tipo,
				'nombre' => $componente->nombre(),
			);
		}
		return $catalogo;
	}

	/**
	 * schema_de( $tipo ): schema de Nivel 2 (estilo de bloque) fusionado
	 * — genérico (Sofia_Componente::schema_bloque_generico(), igual para
	 * todo el catálogo) + propio del Componente (schema_propio(), vacío
	 * salvo que la subclase haga override, ver Sofia_Componente_Container).
	 * Propio SIEMPRE al final, así los controles específicos de un
	 * Componente aparecen después de los genéricos en el drawer — ver Fase
	 * 2 del plan de "primitivas de layout" (memoria de producto).
	 *
	 * Deliberadamente NO pasa por TIPOS_REGISTRADOS/catalogo() — pedir un
	 * schema no depende de que el tipo esté en el catálogo insertable
	 * (hoy "container" también está, ver TIPOS_REGISTRADOS, pero esto
	 * sigue haciendo falta para cualquier tipo futuro con el mismo
	 * problema): una página puede tener un bloque de un tipo que ya no
	 * está en el catálogo actual (removido, o de una versión más nueva
	 * del tema) y aun así necesitar poder pedir su schema.
	 *
	 * @return array<string,array<string,mixed>>|null null si $tipo no
	 *         corresponde a ningún Componente real (mismo criterio que
	 *         crear() devolviendo null).
	 */
	public static function schema_de( string $tipo ): ?array {
		$componente = self::crear( $tipo, 'schema' );
		if ( null === $componente ) {
			return null;
		}
		return array_merge( self::generico_relevante_de( $componente ), $componente::schema_propio() );
	}

	/**
	 * generico_relevante_de( $componente ): schema_bloque_generico()
	 * FILTRADO a solo las claves que $componente::claves_estilo_relevantes()
	 * declara como suyas — extraído como helper compartido porque
	 * schema_de() (drawer manual) Y schema_estilo_ia_de() (generador de
	 * IA) necesitan exactamente el mismo filtro, aplicado sobre bases
	 * distintas (acá el genérico completo de 15 claves; en
	 * schema_estilo_ia_de() ya viene pre-acotado por
	 * CLAVES_ESTILO_GENERICO_IA antes de este filtro — ver ahí).
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private static function generico_relevante_de( Sofia_Componente $componente ): array {
		$generico_completo = Sofia_Componente::schema_bloque_generico();
		$claves_relevantes = $componente::claves_estilo_relevantes();

		$relevante = array();
		foreach ( $claves_relevantes as $clave ) {
			if ( isset( $generico_completo[ $clave ] ) ) {
				$relevante[ $clave ] = $generico_completo[ $clave ];
			}
		}
		return $relevante;
	}

	/**
	 * CLAVES_ESTILO_GENERICO_IA: subconjunto de
	 * Sofia_Componente::schema_bloque_generico() (15 campos totales) que el
	 * generador de árboles por IA puede llenar — "capa 1" de la
	 * conversación de arquitectura sobre por qué la IA generaba diseños sin
	 * criterio (ver la memoria de producto): sin ESTA lista, el LLM no
	 * tenía ningún vocabulario para expresar composición (imagen al
	 * costado vs. arriba, centrado vs. ancho completo), solo podía llenar
	 * contenido — así que cualquier variación de layout era literalmente
	 * imposible de generar, sin importar qué tan bueno fuera el prompt.
	 *
	 * Deliberadamente ACOTADO a "ancho"/"alineacion_bloque" en esta primera
	 * pasada — decisión explícita con el usuario: layout esencial primero
	 * (lo mínimo que ya habilita las variantes de composición reales que
	 * motivaron esta conversación), color/sombra/radius/z-index/aspect-
	 * ratio/etc. quedan para una vuelta posterior. Sumar los 15 campos de
	 * una sola vez alarga el prompt y multiplica la superficie de error de
	 * un modelo que todavía no probamos con NINGÚN campo de estilo — mejor
	 * validar el mecanismo acotado primero (mismo criterio de alcance
	 * incremental que ya usó todo el resto de este plan, Fase 1/2/3).
	 */
	private const CLAVES_ESTILO_GENERICO_IA = array( 'ancho', 'alineacion_bloque' );

	/**
	 * schema_estilo_ia_de( $tipo ): subconjunto de schema_de($tipo) que el
	 * generador de IA puede llenar — CLAVES_ESTILO_GENERICO_IA (whitelist
	 * fija, igual para cualquier tipo) MÁS el schema_propio() completo del
	 * tipo (hoy solo Container: display/direccion/envolver/justificar/
	 * alinear/columnas_grilla/gap — ya son pocos y TODOS relevantes a
	 * composición, a diferencia del genérico de 15 campos, así que no hace
	 * falta acotarlo también).
	 *
	 * Separado de schema_de() (que sigue devolviendo los 15+ campos
	 * completos para el drawer manual, sin cambios) — mismo criterio que
	 * ya separaba schema_de()/schema_contenido_de(): "qué puede editar un
	 * humano en el drawer" y "qué puede generar la IA" son preguntas
	 * relacionadas pero distintas, nunca el mismo array.
	 *
	 * @return array<string,array<string,mixed>>|null null si $tipo no
	 *         corresponde a ningún Componente real, mismo criterio que
	 *         schema_de().
	 */
	public static function schema_estilo_ia_de( string $tipo ): ?array {
		$componente = self::crear( $tipo, 'schema' );
		if ( null === $componente ) {
			return null;
		}

		// Intersección de 2 filtros distintos: CLAVES_ESTILO_GENERICO_IA
		// (qué puede tocar la IA, en general, de los genéricos — "layout
		// esencial primero", ver el comentario largo arriba) Y
		// claves_estilo_relevantes() del TIPO puntual (qué le corresponde
		// a ESE tipo, ver Sofia_Componente::PERFIL_*/
		// claves_estilo_relevantes()) — ambos filtros deben aprobar una
		// clave para que la IA pueda usarla. En la práctica hoy no cambia
		// nada (ancho/alineacion_bloque están en los 4 perfiles), pero es
		// la combinación correcta: si el genérico-IA creciera a futuro con
		// una clave de "caja" (ej. color_fondo), un Button (perfil
		// primitiva, sin controles de caja) seguiría sin poder recibirla
		// de la IA, igual que un humano no la ve en su drawer.
		$claves_relevantes = $componente::claves_estilo_relevantes();
		$generico_completo  = Sofia_Componente::schema_bloque_generico();
		$generico_acotado   = array();
		foreach ( self::CLAVES_ESTILO_GENERICO_IA as $clave ) {
			if ( in_array( $clave, $claves_relevantes, true ) && isset( $generico_completo[ $clave ] ) ) {
				$generico_acotado[ $clave ] = $generico_completo[ $clave ];
			}
		}

		return array_merge( $generico_acotado, $componente::schema_propio() );
	}

	/**
	 * schema_contenido_de( $tipo ): schema de CONTENIDO (Fase 4 — generador
	 * de árboles por IA, ver Sofia_Componente::schema_contenido()) de un
	 * tipo puntual — hermano de schema_de() (que es de ESTILO), nunca
	 * fusionado con él: son dos preguntas distintas ("qué campos tiene este
	 * bloque" vs. "cómo se ve"), y el generador de IA necesita poder pedir
	 * SOLO contenido sin arrastrar los 12+ controles de estilo genérico que
	 * no le sirven para decidir qué texto escribir.
	 *
	 * A diferencia de schema_de(), acá NO hay nada "genérico" que fusionar
	 * — cada Componente declara sus propios campos de punta a punta, no
	 * existe un set de campos de contenido común a todo el catálogo (a
	 * diferencia de Nivel 2, donde color de fondo/ancho/etc. sí aplican a
	 * cualquier bloque).
	 *
	 * @return array<string,array<string,mixed>>|null null si $tipo no
	 *         corresponde a ningún Componente real, mismo criterio que
	 *         schema_de().
	 */
	public static function schema_contenido_de( string $tipo ): ?array {
		$componente = self::crear( $tipo, 'schema' );
		if ( null === $componente ) {
			return null;
		}
		return $componente::schema_contenido();
	}

	/**
	 * catalogo_para_ia(): el catálogo completo de tipos registrados, cada
	 * uno con su nombre legible + AMBOS schemas (estilo y contenido) ya
	 * resueltos — pensado específicamente para armar el system prompt del
	 * generador de árboles por IA (ver Sofia_REST_Editor::generar_arbol_ia()
	 * y Sofia_Cliente_GoPress::generar_arbol_ia()), que necesita el
	 * catálogo COMPLETO de una sola vez, no tipo por tipo como sí hace el
	 * drawer manual (que solo pide el schema del bloque seleccionado en ese
	 * momento).
	 *
	 * Decisión de diseño (documentada acá porque el plan dejaba la elección
	 * abierta): NO se agregó un endpoint REST nuevo "estilo+contenido
	 * juntos" expuesto directo al navegador — este método es 100% interno,
	 * llamado desde PHP dentro del mismo request de
	 * sofia/v1/ia/generar (Sofia_REST_Editor ya corre server-side y puede
	 * llamar estos métodos directo, sin necesitar un roundtrip HTTP a sí
	 * mismo). El drawer manual (Nivel 2) sigue usando
	 * sofia/v1/catalogo-bloques/{tipo}/schema tal cual, sin cambios — ese
	 * endpoint es de ESTILO nada más y seguirá siéndolo, separar
	 * "estilo puro" (drawer) de "estilo+contenido" (generador de IA) evita
	 * que un cliente HTTP futuro reciba de más sin pedirlo.
	 *
	 * @return array<int,array{tipo:string,nombre:string,schema_estilo:array,schema_contenido:array}>
	 */
	public static function catalogo_para_ia(): array {
		$catalogo = array();
		foreach ( self::TIPOS_REGISTRADOS as $tipo ) {
			$componente = self::crear( $tipo, 'catalogo' );
			if ( null === $componente ) {
				continue;
			}
			$catalogo[] = array(
				'tipo'             => $tipo,
				'nombre'           => $componente->nombre(),
				// schema_estilo_ia_de() (no schema_de()/schema_bloque_generico()
				// completo) — la IA solo puede llenar el subconjunto acotado
				// de estilo (ver el comentario largo en
				// schema_estilo_ia_de()), mandarle los 15 campos genéricos
				// completos la confundiría ofreciéndole controles que
				// reparar_props_contenido_ia() va a descartar de todas
				// formas.
				'schema_estilo'    => self::schema_estilo_ia_de( $tipo ),
				'schema_contenido' => $componente::schema_contenido(),
			);
		}
		return $catalogo;
	}
}

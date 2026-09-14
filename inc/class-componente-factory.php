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
	 */
	private const TIPOS_REGISTRADOS = array( 'hero', 'franja_beneficios', 'testimonios', 'faq', 'cta', 'texto_libre', 'container' );

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
		return array_merge( Sofia_Componente::schema_bloque_generico(), $componente::schema_propio() );
	}
}

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
	 * @param string             $tipo  ej. "hero", "franja_beneficios".
	 * @param string             $id    ID de INSTANCIA de este bloque en la
	 *                                  página (ver Sofia_Componente::$id) —
	 *                                  distinto del tipo, generado por
	 *                                  GoPress (store.GenerarIDBloque).
	 * @param array<string,mixed> $props Props YA recortadas a este bloque
	 *                                    (ver Sofia_Pagina::componentes()).
	 */
	public static function crear( string $tipo, string $id, array $props = array() ): ?Sofia_Componente {
		switch ( $tipo ) {
			case 'hero':
				return new Sofia_Componente_Hero( $tipo, $id, $props );
			case 'franja_beneficios':
				return new Sofia_Componente_Franja_Beneficios( $tipo, $id, $props );
			case 'testimonios':
				return new Sofia_Componente_Testimonios( $tipo, $id, $props );
			case 'faq':
				return new Sofia_Componente_FAQ( $tipo, $id, $props );
			case 'cta':
				return new Sofia_Componente_CTA( $tipo, $id, $props );
			case 'texto_libre':
				return new Sofia_Componente_Texto_Libre( $tipo, $id, $props );
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
	 */
	private const TIPOS_REGISTRADOS = array( 'hero', 'franja_beneficios', 'testimonios', 'faq', 'cta', 'texto_libre' );

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
}

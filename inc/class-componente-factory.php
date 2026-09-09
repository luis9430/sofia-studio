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
	 * @param string               $tipo  ej. "hero", "franja_beneficios".
	 * @param array<string,string> $props Props YA recortadas a este bloque
	 *                                    (ver Sofia_Pagina::componentes()).
	 */
	public static function crear( string $tipo, array $props = array() ): ?Sofia_Componente {
		switch ( $tipo ) {
			case 'hero':
				return new Sofia_Componente_Hero( $props );
			case 'franja_beneficios':
				return new Sofia_Componente_Franja_Beneficios( $props );
			default:
				// Un tipo desconocido (plantilla más nueva que el tema
				// instalado, o dato corrupto) no debe tumbar el render de
				// TODA la página — se omite ese bloque en silencio, mismo
				// criterio que WordPress con un widget/bloque de Gutenberg
				// de un plugin desactivado.
				return null;
		}
	}
}

<?php
/**
 * Clase base de todo Componente del catálogo de Sofia Studio (Hero, Franja
 * de beneficios, etc.) — cada subclase implementa render() y, si necesita
 * JS propio (GSAP, Preact, una librería de calendario), dependencias_js().
 *
 * El Builder fluido (con_titulo/con_imagen/...) es azúcar sobre un array
 * interno $props — cada subclase decide qué métodos with_* expone según
 * los campos que realmente usa (ver class-hero.php para el ejemplo).
 * "campos" viene siempre con la notación "bloque.campo" declarada en
 * PlantillaPagina.Campos (ver internal/store/plantilla_pagina.go de
 * GoPress) — este Componente solo ve SU propia porción ya resuelta
 * (contenido_de_pagina.php se la recorta antes de llamar build()).
 */
abstract class Sofia_Componente {
	/**
	 * @var array<string,string> Valores ya resueltos para este bloque —
	 *      clave corta (sin el prefijo "bloque."), ej. "titulo" en vez de
	 *      "hero.titulo".
	 */
	protected array $props = array();

	/**
	 * Construye un Componente ya con sus props resueltas — usado por
	 * Sofia_Componente_Factory::crear(), nunca instanciado directo.
	 *
	 * @param array<string,string> $props
	 */
	final public function __construct( array $props = array() ) {
		$this->props = array_merge( $this->props_por_defecto(), $props );
	}

	/**
	 * Valores de respaldo cuando la página todavía no tiene nada editado
	 * para este bloque (ej. una página recién creada) — cada Componente
	 * decide los suyos, para no mostrar una sección vacía en blanco.
	 *
	 * @return array<string,string>
	 */
	protected function props_por_defecto(): array {
		return array();
	}

	/**
	 * Slugs de librerías JS que este Componente necesita en el frente
	 * público (ej. array('gsap'), array('preact')) — Sofia_Tema::encolar_dependencias()
	 * recorre TODOS los Componentes de la página, junta y deduplica esto
	 * antes de encolar nada. Un Componente sin JS (la mayoría: Hero,
	 * Testimonios, FAQ) deja el array vacío — es el default acá a
	 * propósito, así una subclase nueva no rompe si se olvida de
	 * declarar esto.
	 *
	 * @return string[]
	 */
	public function dependencias_js(): array {
		return array();
	}

	/**
	 * Devuelve el HTML del bloque — implementado por cada subclase.
	 */
	abstract public function render(): string;

	/**
	 * Atributo data-sofia-campo="bloque.campo" en el elemento editable —
	 * el editor in-place (panel de GoPress) lo usa para saber qué campo
	 * de PaginaSitio.Contenido actualizar al editar ese elemento del
	 * iframe. $tipo debe ser el mismo "tipo" del bloque en la estructura
	 * de la plantilla (ver class-componente-factory.php).
	 */
	protected function atributo_editable( string $tipo, string $campo ): string {
		return sprintf( 'data-sofia-campo="%s.%s"', esc_attr( $tipo ), esc_attr( $campo ) );
	}
}

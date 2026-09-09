<?php
/**
 * Sofia_Pagina arma la lista de Componentes ya instanciados de una página
 * — el punto donde la respuesta plana de GoPress ({estructura, contenido})
 * se convierte en objetos Sofia_Componente listos para -> render().
 */
class Sofia_Pagina {

	/** @var array{tipo:string}[] */
	private array $estructura;

	/** @var array<string,string> "bloque.campo" => valor, sin recortar */
	private array $contenido;

	/**
	 * @param array{tipo:string}[] $estructura
	 * @param array<string,string> $contenido
	 */
	public function __construct( array $estructura, array $contenido ) {
		$this->estructura = $estructura;
		$this->contenido  = $contenido;
	}

	/**
	 * @return Sofia_Componente[] En el mismo orden que la estructura — un
	 *         tipo de bloque desconocido (Sofia_Componente_Factory::crear
	 *         devolviendo null) se omite, no rompe el resto de la página.
	 */
	public function componentes(): array {
		$componentes = array();
		foreach ( $this->estructura as $bloque ) {
			$tipo      = $bloque['tipo'] ?? '';
			$props     = $this->props_de_bloque( $tipo );
			$componente = Sofia_Componente_Factory::crear( $tipo, $props );
			if ( null !== $componente ) {
				$componentes[] = $componente;
			}
		}
		return $componentes;
	}

	/**
	 * Recorta $this->contenido a solo las claves que empiezan con
	 * "{$tipo}." y les quita ese prefijo — un Componente nunca ve el
	 * contenido de otro bloque, ni conoce la notación "bloque.campo" por
	 * su cuenta (eso es responsabilidad de esta clase, no de cada
	 * Componente).
	 *
	 * @return array<string,string>
	 */
	private function props_de_bloque( string $tipo ): array {
		$prefijo = $tipo . '.';
		$props   = array();
		foreach ( $this->contenido as $clave => $valor ) {
			if ( str_starts_with( $clave, $prefijo ) ) {
				$props[ substr( $clave, strlen( $prefijo ) ) ] = $valor;
			}
		}
		return $props;
	}

	/**
	 * Slugs de JS a encolar para esta página — unión deduplicada de las
	 * dependencias de cada Componente presente (ver
	 * Sofia_Componente::dependencias_js()). Este es el mecanismo real
	 * detrás de "no cargar GSAP si ningún bloque lo necesita" (ver la
	 * memoria de producto).
	 *
	 * @return string[]
	 */
	public function dependencias_js(): array {
		$deps = array();
		foreach ( $this->componentes() as $componente ) {
			$deps = array_merge( $deps, $componente->dependencias_js() );
		}
		return array_values( array_unique( $deps ) );
	}
}

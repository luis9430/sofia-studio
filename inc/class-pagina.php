<?php
/**
 * Sofia_Pagina arma la lista de Componentes ya instanciados de una página
 * — el punto donde la respuesta plana de GoPress ({estructura, contenido})
 * se convierte en objetos Sofia_Componente listos para -> render().
 */
class Sofia_Pagina {

	/** @var array{id:string,tipo:string}[] */
	private array $estructura;

	/**
	 * @var array<string,mixed> "bloque.campo" => valor, sin recortar. La
	 *      mayoría de valores son string; un campo de "lista repetible"
	 *      (Nivel 2, ej. "franja_beneficios.items") es un array de
	 *      objetos — ver Sofia_Componente::$props.
	 */
	private array $contenido;

	/**
	 * @param array{id:string,tipo:string}[] $estructura
	 * @param array<string,mixed> $contenido
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
			$tipo       = $bloque['tipo'] ?? '';
			$id         = $bloque['id'] ?? '';
			$props      = $this->props_de_bloque( $id );
			$componente = Sofia_Componente_Factory::crear( $tipo, $id, $props );
			if ( null !== $componente ) {
				$componentes[] = $componente;
			}
		}
		return $componentes;
	}

	/**
	 * Recorta $this->contenido a solo las claves que empiezan con "{$id}."
	 * y les quita ese prefijo — un Componente nunca ve el contenido de
	 * otro bloque, ni conoce la notación "id.campo" por su cuenta (eso es
	 * responsabilidad de esta clase, no de cada Componente). Se recorta
	 * por ID de INSTANCIA, no por tipo — bug real que esto resuelve: dos
	 * bloques del mismo tipo (ej. 2 "Franja de beneficios") ya no
	 * comparten la misma porción de contenido entre sí.
	 *
	 * @return array<string,mixed>
	 */
	private function props_de_bloque( string $id ): array {
		$prefijo = $id . '.';
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

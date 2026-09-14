<?php
/**
 * Sofia_Componente_Container — primitiva de layout (ver la memoria de
 * producto "Sofia Studio: plan de primitivas de layout"), PRIMER
 * Componente del catálogo que puede contener OTROS Componentes como
 * hijos, en vez de solo campos de texto/imagen propios.
 *
 * Fase 1 (este archivo): valida el MECANISMO de anidamiento en sí — un
 * <div> que renderiza sus hijos, ya resueltos por Sofia_Pagina
 * (Sofia_Componente::$hijos, ver el comentario largo ahí sobre por qué
 * llegan YA instanciados y no como array crudo). Deliberadamente SIN
 * ningún control de layout propio todavía (dirección, gap, grilla,
 * alineación) — eso es Fase 2 (schema tipado de campos), que se agrega
 * sobre esta base sin romper nada de lo que ya funciona acá: fondo, borde,
 * radio de borde, sombra, ancho, utility classes de Core Framework, TODO
 * eso ya funciona gratis vía atributos_seccion()/Nivel 2, heredado tal
 * cual de cualquier otro Componente del catálogo.
 *
 * Deliberadamente NO está en Sofia_Componente_Factory::TIPOS_REGISTRADOS
 * — no aparece todavía en el catálogo "+ Agregar bloque" del editor
 * visual (eso es Fase 3, cuando el drag-and-drop para anidar bloques
 * exista). Por ahora solo se puede instanciar armando el JSON de
 * Estructura a mano (vía la API de plantillas/páginas de GoPress), que es
 * exactamente el alcance declarado de Fase 1.
 */
class Sofia_Componente_Container extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Container', 'sofia-studio' );
	}

	public function render(): string {
		$html = '<div ' . $this->atributos_seccion( 'sofia-container' ) . '>';
		foreach ( $this->hijos as $hijo ) {
			$html .= $hijo->render();
		}
		$html .= '</div>';
		return $html;
	}
}

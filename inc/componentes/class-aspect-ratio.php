<?php
/**
 * AspectRatio — primitiva de Layout (Fase 1, plan "50 primitivas", ver la
 * memoria de producto): fuerza a su contenido (típicamente UNA imagen o
 * video, pero acepta cualquier hijo) a mantener una relación de aspecto
 * fija, recortando/ajustando lo que haya adentro — mismo mecanismo que ya
 * existe como utility class de Core Framework (CLASES_UTILITARIAS_BLOQUE,
 * clase base) para "Relación de aspecto"/"Ajuste de imagen" de un
 * Componente CUALQUIERA, pero expuesto acá como su propia primitiva
 * dedicada, para envolver un hijo puntual DENTRO de un layout más grande
 * (ej. una imagen a la mitad de ancho de un Hero armado a mano con
 * Container) sin tener que forzar esa relación de aspecto a la sección
 * completa que la contiene.
 *
 * Segundo Componente del catálogo (después de Container) que usa $hijos
 * — a diferencia de Container (layout libre, cualquier cantidad de
 * hijos), AspectRatio es conceptualmente "1 hijo" (más de uno se
 * superpondría, mismo criterio de "position:absolute todos menos el
 * primero" que cualquier caja de aspect-ratio real) — el editor no
 * impide agregar más de un hijo (mismo criterio que Container: es una
 * responsabilidad de USO, no una restricción técnica dura), pero
 * render()/CSS asumen que el caso real es uno solo.
 */
class Sofia_Componente_Aspect_Ratio extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Relación de aspecto', 'sofia-studio' );
	}

	/**
	 * Sin override de schema_contenido() — igual que Container, el
	 * "contenido" es enteramente $hijos, ya cubierto por el modelo
	 * recursivo {id,tipo,hijos}.
	 */

	/**
	 * claves_estilo_relevantes(): PERFIL_IMAGEN completo — YA incluye
	 * "aspect_ratio"/"object_fit" (las 2 claves que le dan su función
	 * real a este Componente) además de ancho/max_width/alineacion_bloque/
	 * offset_x, mismo perfil que Image. object_fit acá aplica al PRIMER
	 * hijo directo (ver el CSS en style.css), no específicamente a una
	 * <img> — a diferencia de Sofia_Componente_Hero/Image (donde
	 * object-fit siempre targetea una <img> real conocida), un hijo de
	 * AspectRatio puede ser cualquier Componente.
	 */
	public static function claves_estilo_relevantes(): array {
		return parent::PERFIL_IMAGEN;
	}

	/**
	 * render(): "aspect_ratio" (aspect-1/aspect-16-9/etc.) NO se emite
	 * acá — clases_utilitarias_bloque() (clase base) ya lo agrega solo a
	 * la <section> exterior, y esas clases son utility classes REALES de
	 * Core Framework (confirmado en el comentario de
	 * CLASES_UTILITARIAS_BLOQUE, clase base: "agregar la clase es todo lo
	 * que hace falta"), nada que resolver del lado del tema.
	 *
	 * "object_fit" (fit-cover/fit-contain/fit-fill) es DISTINTO — esas 3
	 * clases sí son del TEMA (ver style.css, hoy solo con reglas
	 * ".sofia-hero.fit-cover > img" etc.), Core Framework las agrega al
	 * classList pero no las define — así que .sofia-aspect-ratio necesita
	 * sus 3 reglas propias (ver style.css), targeteando "> img"/"> video"
	 * dentro del wrapper interno. object-fit solo tiene efecto real en
	 * <img>/<video>/<canvas> (el navegador lo ignora en cualquier otro
	 * elemento) — si el hijo real es otro Componente sin media directa
	 * (ej. un Container), este control simplemente no hace nada visible,
	 * mismo criterio de "no rompe, no hace nada fuera de contexto" que ya
	 * usa el resto de CLASES_UTILITARIAS_BLOQUE.
	 */
	public function render(): string {
		$html  = '<section ' . $this->atributos_seccion( 'sofia-aspect-ratio' ) . '>';
		$html .= '<div class="sofia-aspect-ratio__contenido">';
		foreach ( $this->hijos as $hijo ) {
			$html .= $hijo->render();
		}
		$html .= '</div>';
		$html .= '</section>';
		return $html;
	}
}

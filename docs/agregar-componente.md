# Cómo agregar un Componente nuevo a Sofia Studio

Un "Componente" es un bloque insertable en el catálogo del editor (Hero, Franja de beneficios, CTA...). El sistema está diseñado para que agregar uno nuevo sea barato: la clase base (`Sofia_Componente`) ya provee TODO el sistema de estilo (Nivel 1/2, Visibilidad, tokens de Core Framework, badge visual, listas repetibles) — un Componente nuevo solo escribe su propio HTML.

No hace falta tocar GoPress (Go). El campo `Tipo` de un bloque es un string libre sin validación del lado de Go (`internal/store/plantilla_pagina.go`) — todo el "significado" de un tipo vive exclusivamente en el tema PHP.

## Los 3 archivos a tocar

1. **Crear** `inc/componentes/class-{tipo}.php` — la clase del Componente nuevo.
2. **Registrar** en `inc/class-componente-factory.php` — `require_once`, `case` en `crear()`, entrada en `TIPOS_REGISTRADOS`.
3. Nada más. El catálogo de "+ Agregar bloque" del editor, el guardado de contenido (`class-rest-editor.php`), y todo el drawer de estilo (`DrawerEstilo.jsx`) son genéricos — no requieren ningún cambio por Componente.

## Patrón A — Componente simple (sin lista repetible)

Ejemplo real: `class-cta.php` (título + texto + botón, 3 campos sueltos).

```php
<?php
class Sofia_Componente_Mi_Bloque extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Mi Bloque', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		return array(
			'titulo' => __( 'Título de ejemplo', 'sofia-studio' ),
			'texto'  => __( 'Texto de ejemplo.', 'sofia-studio' ),
		);
	}

	public function render(): string {
		$titulo = $this->texto_enriquecido( $this->props['titulo'] );
		$texto  = $this->texto_enriquecido( $this->props['texto'] );

		$html  = '<section ' . $this->atributos_seccion( 'sofia-mi-bloque' ) . '>';
		$html .= '<h2 ' . $this->atributo_editable( 'titulo' ) . ' ' . $this->atributo_estilo( 'titulo' ) . '>' . $titulo . '</h2>';
		$html .= '<p ' . $this->atributo_editable( 'texto' ) . ' ' . $this->atributo_estilo( 'texto' ) . '>' . $texto . '</p>';
		$html .= '</section>';
		return $html;
	}
}
```

Qué hace cada pieza:
- `atributos_seccion('sofia-mi-bloque')`: arma `class`, `data-sofia-bloque-id`, `data-sofia-bloque-tipo`, el `style="..."` de Nivel 2 (si hay estilo de bloque guardado), y las utility classes de Core Framework — **siempre la clase base del Componente como único argumento**, nunca escribir `class="..."` a mano en el `<section>`.
- `atributo_editable('titulo')`: marca el elemento como editable in-place, usando `"{id}.titulo"` como clave de contenido.
- `atributo_estilo('titulo')`: imprime el `style="..."` de Nivel 1 de ese campo puntual (si el usuario le configuró algo desde el drawer).
- `texto_enriquecido(...)`: sanea el valor permitiendo solo negrita/cursiva/salto de línea (whitelist fija) — usar SIEMPRE en vez de `esc_html()` para cualquier campo de texto editable, o el formato guardado se muestra como texto literal.

Un campo de **imagen** (ej. `class-hero.php`) sigue el mismo patrón, pero con `<img>` y `esc_url()`:

```php
$imagen = esc_url( $this->props['imagen'] );
if ( $imagen ) {
	$html .= '<img ' . $this->atributo_editable( 'imagen' ) . ' src="' . $imagen . '" alt="...">';
}
```

Un campo que NO es texto visible (ej. el `href` de un botón) va como atributo normal, sin `atributo_editable()` — ver `class-cta.php`, el `href` del botón.

## Patrón B — Componente con lista repetible (items)

Ejemplo real: `class-franja-beneficios.php` (N columnas, cada una con sus propios campos, reordenable/agregable/eliminable).

```php
<?php
class Sofia_Componente_Mi_Lista extends Sofia_Componente {

	public function nombre(): string {
		return __( 'Mi Lista', 'sofia-studio' );
	}

	protected function props_por_defecto(): array {
		$items = array();
		for ( $i = 1; $i <= 3; $i++ ) {
			$items[] = array(
				'titulo' => sprintf( __( 'Item %d', 'sofia-studio' ), $i ),
				'texto'  => __( 'Describe este item.', 'sofia-studio' ),
			);
		}
		return array( 'items' => $items );
	}

	public function render(): string {
		$items       = is_array( $this->props['items'] ?? null ) ? $this->props['items'] : array();
		$campo_lista = $this->id . '.items'; // SIEMPRE con el ID de instancia, nunca "mi_lista.items" a secas.

		$html  = '<section ' . $this->atributos_seccion( 'sofia-mi-lista' ) . '>';
		$html .= '<div class="sofia-mi-lista__grid" data-sofia-lista="' . esc_attr( $campo_lista ) . '">';
		foreach ( array_values( $items ) as $indice => $item ) {
			$titulo = $this->texto_enriquecido( (string) ( $item['titulo'] ?? '' ) );
			$texto  = $this->texto_enriquecido( (string) ( $item['texto'] ?? '' ) );
			$html  .= '<div class="sofia-mi-lista__item" data-sofia-item="' . (int) $indice . '">';
			$html  .= '<h3 ' . $this->atributo_editable( "items.{$indice}.titulo" ) . ' ' . $this->atributo_estilo( "items.{$indice}.titulo" ) . '>' . $titulo . '</h3>';
			$html  .= '<p ' . $this->atributo_editable( "items.{$indice}.texto" ) . ' ' . $this->atributo_estilo( "items.{$indice}.texto" ) . '>' . $texto . '</p>';
			$html  .= '</div>';
		}
		$html .= '</div>';
		$html .= $this->boton_agregar_item( 'items' ); // FUERA del <div data-sofia-lista>, hermano — nunca adentro.
		$html .= '</section>';
		return $html;
	}
}
```

Reglas que no son opcionales acá (todas ya resolvieron un bug real en su momento, ver `inc/class-componente.php` y `inc/class-modo-editor.php` para el detalle):

- `data-sofia-lista="{id}.items"` — con el **ID de instancia**, nunca el tipo crudo (`"mi_lista.items"`), o dos bloques del mismo tipo en la misma página comparten contenido.
- `data-sofia-item="{indice}"` en cada item — Muuri/`editor-iframe.js` lo necesita para reordenar y reconstruir el array tras un drag.
- `atributo_editable("items.{$indice}.titulo")` / `atributo_estilo("items.{$indice}.titulo")` — la notación de 3 segmentos (lista.índice.subcampo) es la que activa el camino de "campo dentro de lista" en `atributo_estilo()`; con 2 segmentos apuntaría a un campo simple que no existe.
- `boton_agregar_item('items')` va **hermano** de `[data-sofia-lista]`, nunca hijo — si está adentro, Muuri no lo cuenta para calcular la altura del contenedor y queda superpuesto sobre el bloque siguiente.
- El CSS real del grid (`.sofia-mi-lista__grid { display:grid; grid-template-columns: repeat(var(--sofia-columnas, 3), 1fr); gap: 24px }`) va en `style.css`, y el contenedor de la lista necesita `width:100%` en el CSS de modo editor si sus items van a ser `position:absolute` (ya cubierto genéricamente por `[data-sofia-lista] { width: 100% }` en `class-modo-editor.php` — no hace falta repetirlo por Componente).

## Paso 2: registrar en la Factory

```php
// functions.php (o donde estén los demás require_once de componentes)
require_once get_stylesheet_directory() . '/inc/componentes/class-mi-bloque.php';
```

```php
// inc/class-componente-factory.php
public static function crear( string $tipo, string $id, array $props = array() ): ?Sofia_Componente {
	switch ( $tipo ) {
		// ... casos existentes ...
		case 'mi_bloque':
			return new Sofia_Componente_Mi_Bloque( $tipo, $id, $props );
		default:
			return null;
	}
}

private const TIPOS_REGISTRADOS = array( /* ...existentes..., */ 'mi_bloque' );
```

Con esto, `"mi_bloque"` ya aparece en el catálogo de "+ Agregar bloque" del editor (vía `sofia/v1/catalogo-bloques`), sin tocar nada más.

## Lo que se hereda gratis (no requiere código propio)

- **Nivel 1** (estilo de campo): alineación, color de texto (+ token CF), tamaño de fuente (+ token CF), tipo de fuente, negrita, sombra de texto — con solo usar `atributo_estilo('campo')`.
- **Nivel 2** (estilo de bloque): columnas de grid, color de fondo/borde/radio de borde/sombra (+ token CF en los 4), espaciado vertical (+ token CF), Ancho/Ancho máximo/Alineación del bloque/Desplazamiento horizontal/Aspect ratio/Object-fit/Z-index/Alineación del contenido — con solo usar `atributos_seccion('clase-base')`.
- **Visibilidad**: la pestaña de condiciones (`bloque_visible()`) funciona automático en cualquier Componente, sin código propio.
- **Badge "CF"**: si un campo/bloque usa un token, el badge visual en hover aparece solo — es CSS genérico sobre `data-sofia-estilo-*`, no depende del tipo de Componente.
- **Reordenar/eliminar/insertar**: Muuri de nivel superior ya trata cualquier `<section>` con `atributos_seccion()` como un bloque más — no hace falta nada especial.

## Lo que SÍ es específico de cada Componente

- Su HTML propio en `render()`.
- Su CSS visual real (`style.css`) — la clase base (`sofia-mi-bloque`) no trae ningún estilo, es solo el gancho para las utility classes de Nivel 2.
- Si necesita JS de terceros (GSAP, una librería de calendario, etc.): sobreescribir `dependencias_js()` devolviendo el slug — ver `Sofia_Tema::encolar_dependencias()`, que junta y deduplica de todos los Componentes de la página.
- Si tiene un campo que no es texto ni imagen simple (ej. un `href` de botón, un `<select>` guardado como prop): decidir a mano cómo se edita — hoy Sofia Studio no tiene UI para eso, solo texto/imagen vía `atributo_editable()`.

## Checklist rápido

- [ ] `inc/componentes/class-{tipo}.php` con `nombre()`, `render()`, `props_por_defecto()` (opcional).
- [ ] `require_once` en `functions.php`.
- [ ] `case` en `Sofia_Componente_Factory::crear()`.
- [ ] Entrada en `Sofia_Componente_Factory::TIPOS_REGISTRADOS`.
- [ ] CSS visual real en `style.css` (clase base + cualquier elemento hijo propio).
- [ ] Si tiene lista repetible: `data-sofia-lista`/`data-sofia-item` con el ID de instancia, notación de 3 segmentos en `atributo_editable`/`atributo_estilo`, botón agregar hermano del contenedor.
- [ ] Probar en el editor: agregar el bloque, editar cada campo, abrir su drawer de Estilo (Nivel 1 y 2), confirmar que Visibilidad/reordenar/eliminar funcionan sin código extra.

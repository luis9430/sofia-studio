---
name: pieza
description: Construir o rediseñar un Componente de Sofia Studio como PIEZA de diseño (Hero, Beneficios, Testimonios, CTA, Precios, Header, Footer). Usar al crear un Componente nuevo, al rediseñar uno existente, o al agregarle composiciones. No usar para primitivas de maquetación (Container, Spacer, Divider, AspectRatio), que son contenedores neutros a propósito.
---

# Construir una pieza de Sofia Studio

Una PIEZA es un Componente que se ve terminado: Hero, Beneficios,
Testimonios, CTA, Precios. Una PRIMITIVA es una caja neutra para armar
otras cosas: Container, Spacer, Divider, AspectRatio.

La diferencia no es de tamaño, es de propósito, y decide todo lo demás.
Una primitiva sin diseño está bien; una pieza sin diseño es el bug.

## Por qué existen las piezas

Contexto que conviene no volver a descubrir: el catálogo tenía ~39
Componentes correctos y sosos, todos contenedores mínimos. Se probaron
dos salidas antes de llegar a esto, y las dos fallaron:

1. **Efectos CSS por encima** (recortes, degradados, superposiciones).
   No arregló nada: un título sobre un bloque vacío no se salva con un
   borde diagonal.
2. **Que la IA describiera Componentes nuevos** con su propio CSS. Falla
   para composición espacial — se le pidió "dos tarjetas superpuestas" y
   devolvió dos bloques apilados. Entendía el pedido pero no sabía
   escribir el CSS que lo produce, y sin verse a sí misma no lo
   detectaba.

Lo que sí funcionó: rediseñar el Componente para que tenga el CONTENIDO
que la sección necesita de verdad. El problema nunca fue el tratamiento
visual.

La IA compone bien entre piezas existentes (medido: eligió gallery +
testimonios para un hotel, stepper + faq para un estudio contable). Lo
que no hace es inventarlas. Por eso la creatividad se pone una vez, en
la pieza, y la IA la reutiliza.

## Las cuatro partes

| Parte | Dónde | Cuándo |
|---|---|---|
| PHP | `inc/componentes/class-X.php` | siempre |
| CSS | `style.css` | siempre |
| JS | `inc/js/sofia-interacciones.js` | solo si la pieza lo pide (6 de 39 lo hacen) |
| Registro | `functions.php` + `inc/class-componente-factory.php` | solo si el tipo es nuevo |

## Las seis reglas

### 1. Una estructura, varias composiciones

Todas las composiciones emiten el MISMO HTML y cambian por la clase de
la sección. El CSS reacomoda.

No es preferencia: si cada composición emitiera markup distinto, cambiar
de una a otra movería los `data-sofia-campo` de lugar y el editor
perdería el campo que el usuario tenía seleccionado.

```php
$clases = array_filter( array(
    'sofia-pieza',
    'sofia-hero',
    $this->clase_de_variante( self::CLASES_COMPOSICION, 'composicion' ),
) );
```

Cuando el orden visual cambia entre composiciones, se resuelve con
`order` en CSS, nunca reordenando el HTML.

### 2. Las etiquetas dicen CUÁNDO, no cómo

```php
array( 'valor' => 'numerada', 'etiqueta' => __( 'Numerada — cuando son pasos de un proceso', 'sofia-studio' ) ),
```

No es cosmético. El catálogo que recibe el generador por IA
(`catalogo_para_ia()`) incluye estas etiquetas, y son lo único que tiene
para elegir entre tres composiciones. "Numerada" a secas no le dice
nada.

### 3. Todo campo extra es opcional

Cada campo se omite del HTML si está vacío, y se muestra igual en el
editor para poder estrenarlo:

```php
$en_editor = class_exists( 'Sofia_Modo_Editor' ) && Sofia_Modo_Editor::activo();
if ( '' !== $etiqueta || $en_editor ) {
    $html .= '<p class="sofia-pieza__etiqueta" ' . $this->atributo_editable( 'etiqueta' ) . '>' . $etiqueta . '</p>';
}
```

Nadie está obligado a llenar siete campos para que la pieza se vea bien.

### 4. Defaults reales, nunca lorem

Contenido de un caso concreto, no "Escribe acá tu título". Un
placeholder genérico enseña a dejarlo genérico, y hace imposible juzgar
la composición hasta que alguien se tome el trabajo de llenarla.

### 5. clamp() antes que @media

Espaciado y tipografía escalan continuo con los tokens `--pieza-*` de la
base compartida. Los `@media` quedan solo para lo que no se puede
interpolar: cuántas columnas tiene una grilla.

Tres breakpoints, y nada más: **1024** (tres columnas → dos), **768**
(todo a una columna), **480** (se achica el espaciado, no el layout).

### 6. Accesible como se dibuja

- `h2` para el título de la sección, `h3` para los items.
- `aria-hidden="true"` en íconos y números decorativos.
- Contraste con `color-mix` contra el fondo, nunca con `opacity`: una
  opacidad no garantiza 4.5:1.
- El foco visible ya viene de `.sofia-pieza :is(a,button,summary):focus-visible`.

## La base compartida

`style.css` tiene un bloque `BASE DE LAS PIEZAS DE DISEÑO` con los
tokens y las clases que toda pieza reusa. **Leerlo antes de escribir
CSS nuevo** — si ya está ahí, no se reescribe.

Trae: escala de espaciado (`--pieza-gap-*`), padding de sección,
escala tipográfica, `.sofia-pieza__interior` (ancho máximo centrado),
`.sofia-pieza__encabezado` con sus variantes, `.sofia-pieza__grilla`,
`.sofia-pieza__icono` y el foco visible.

Si varias piezas necesitan algo nuevo, va ahí. Si lo necesita una sola,
va en su propio bloque.

## El contrato del editor

Esto no es estilo, es lo que hace que el bloque sea editable. Romperlo
no da error: da campos que el usuario no puede tocar.

- `$this->atributos_seccion( $clases )` en la `<section>` raíz.
- `$this->atributo_editable( 'campo' )` en cada elemento editable.
- `$this->atributo_estilo( 'campo' )` en los de texto que admiten
  formato (Nivel 1).
- Listas: `$this->atributo_lista( 'items' )` en el contenedor y
  `$this->atributo_item( $i )` en cada item.
- **Todo `data-sofia-campo` de una lista tiene que estar DENTRO de su
  `data-sofia-item`.** `notificarListaActualizada()` reconstruye cada
  item leyendo los campos que encuentra adentro; uno que quede afuera se
  pierde al editar cualquier otro. Pasó de verdad con Tabs.
- `$this->boton_agregar_item( 'items' )` va FUERA del contenedor de la
  lista, como hermano.

## Las tres capas de props

| Capa | Método | Qué va |
|---|---|---|
| Contenido | `schema_contenido()` | lo que el bloque DICE |
| Apariencia | `schema_propio()` | composición, tono — propias de este tipo |
| Caja | `claves_estilo_relevantes()` | ancho, fondo, espaciado — transversales |

Toda prop de apariencia es un `select` con opciones fijas. Nunca texto
libre: el editor no puede producir un valor que el render no sepa
dibujar, y la validación de la IA tiene contra qué comparar.

Para la caja, devolver un `PERFIL_*` de la clase base. `PERFIL_SECCION`
para una pieza normal; `PERFIL_LISTA` **solo si el CSS respeta de verdad
sus tres controles de grilla** — declararlo sin cumplirlo deja controles
muertos en el panel.

## Tipos de campo disponibles

El panel sabe dibujar doce; las piezas usan seis. Antes de inventar uno
nuevo, revisar si ya existe:

`texto` · `texto_largo` · `url` · `imagen` · `numero` · `icono` ·
`lista` · `select` · `toggle` · `color_token` · `medida_token` ·
`botones_numero`

Dentro de una lista, el panel solo muestra los subcampos que el canvas
NO puede editar: `url`, `imagen`, `icono`. Los de texto se editan
clickeando en el canvas.

Íconos disponibles (26): ver `ICONOS_PERMITIDOS` en
`inc/class-componente.php`.

## Verificar antes de dar por hecho

```bash
php -l inc/componentes/class-X.php
php tests/test-componente-generado.php     # 54 pruebas
php tests/test-clasificar-pedido.php       # 15 pruebas
```

Y siempre: **mirar el render**. Los bugs de este sistema no fallan, se
ven mal. Ejemplos reales, todos con las pruebas en verde:

- Un círculo cortado a la mitad por un `overflow: hidden`.
- "Soporte 24/7" apareciendo dos veces.
- La composición editorial con el título en la columna equivocada.
- El degradado saliendo negro porque los defaults del tema tienen
  primario, acento y texto en el mismo `#1c1a17`.

Para verlo, un script en `tests/` que renderice las composiciones con
una paleta real (los defaults del tema no sirven para juzgar) y escriba
un HTML. Hay ejemplos en `tests/` de sesiones anteriores.

## Trampas conocidas

- **`git add -A` arrastra archivos generados.** `tests/salida-lote/`
  está en `.gitignore`; revisar `git status` antes de commitear.
- **Un heredoc de bash rompe CSS y PHP con comillas.** Para editar
  archivos grandes, escribir un script Python con la herramienta Write y
  ejecutarlo — la cadena bash → python → archivo destroza los literales.
- **Los tokens por defecto del tema son todos el mismo casi-negro.** Al
  probar, inyectar una paleta real o cualquier degradado se ve plano.
- **El tema se llama `sofia-studio-main` en el contenedor**, no
  `sofia-studio`.
- **El tema se despliega por ZIP desde `origin/main`**: commitear y
  pushear antes de pedir que reinstalen.

## Cómo se diseña antes de escribir

Para una pieza nueva o un rediseño, el orden que funcionó:

1. Diseñar 3 composiciones en un lienzo (Artifact de tipo Design), con
   contenido real y una nota que diga cuándo sirve cada una.
2. Que el usuario apruebe o corrija — es más fácil juzgar algo visual
   que una descripción.
3. Recién ahí escribir PHP y CSS.

El usuario de este proyecto no es diseñador pero reconoce lo bueno al
verlo. Ese reparto funciona: proponer opciones concretas, no preguntar
qué quiere en abstracto.

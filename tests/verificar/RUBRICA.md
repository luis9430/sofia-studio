# Rúbrica de verificación de piezas

Tres capas, y cada una la juzga quien puede.

| Capa | Quién juzga | Ejemplo |
|---|---|---|
| **Medible** | Playwright (`medir.mjs`) | desborda, contraste 3.1:1, texto cortado |
| **Interpretable** | LLM con esta rúbrica | jerarquía confusa, aire desparejo, el CTA no resalta |
| **Gusto** | el cliente | "no me late el azul" |

**Cuando una medición y un juicio se contradigan, manda la medición.** Un
contraste es un número; que "se lea flojo" es una impresión. El juicio queda
como nota al pie, no como veredicto.

---

## Capa 1 — Medible

Nueve chequeos deterministas en `medir.mjs`, sobre 3 anchos (1280 / 768 / 390).

| Regla | Qué afirma | Severidad |
|---|---|---|
| `desborde-pagina` | la página no scrollea de costado | error |
| `elemento-fuera` | nada se sale de la ventana | error |
| `texto-cortado` | nada queda oculto por `overflow:hidden` | error |
| `contraste` | ≥ 4.5:1 (≥ 3:1 en texto grande), WCAG AA | error |
| `solapamiento` | hermanos en flujo normal no se cruzan | error |
| `img-sin-alt` | toda imagen tiene `alt` | error |
| `area-toque` | ≥ 44px en alguna dimensión, solo ≤ 480px | duda |
| `encabezados` | un `h1`, sin saltos de nivel | duda |
| `alineacion-repetidos` | lo primero de cada tarjeta de una fila arranca a la misma altura | duda |
| `superficie-invisible` | un botón con fondo propio se distingue de lo que tiene detrás | error |
| `color-sin-token` | los fondos salen de la paleta o de un mix de ella | nota |
| `contraste-sobre-imagen` | no se puede afirmar: depende de la foto | nota |

### Por qué algunas son "duda" y no "error"

`area-toque` y `encabezados` dependen del contexto. Un enlace de 350×17px se
toca sin problema aunque falle el mínimo en una dimensión; dos `h1` en una
pieza suelta pueden ser correctos si la pieza va sola en la página. Se
reportan para mirar, no para bloquear.

### Los límites, dichos de frente

**Una pieza medida sola no es una pieza en una página.** El Header
transparente va sobre el Hero y el Hero inmersivo va sobre una foto; medidos
solos no tienen nada debajo, así que su contraste es indeterminable y se
reporta como nota. Cuando se verifique una landing completa, esos casos pasan
a ser medibles.

**Los defaults son el contenido medido.** Es deliberado: los defaults del tema
son contenido real, y es lo que el usuario ve al insertar la pieza. Pero un
texto tres veces más largo puede desbordar donde el default no desborda.

---

## Capa 2 — Interpretable

Preguntas cerradas, derivadas de las seis reglas de pieza. **El juez ve las
capturas, no el código**: mirando CSS juzga CSS, y lo que importa es lo que se
dibujó.

Cada respuesta pide severidad (`error` / `duda` / `ok`) y **qué elemento**.
Sin el elemento no es accionable.

1. **Jerarquía** — ¿se distingue el título del subtítulo del cuerpo? ¿Qué se
   lee primero, y es lo que debería leerse primero?
2. **Ritmo** — ¿los espacios entre elementos hermanos son parejos? ¿hay algo
   pegado o algo suelto sin motivo?
3. **Foco** — ¿la acción principal gana la atención? ¿compite con otra cosa?
4. **Densidad** — ¿hay aire donde hace falta, sin huecos muertos?
5. **Alineación** — ¿coinciden bordes y líneas base entre elementos
   repetidos?
6. **Mobile** — a 390px, ¿sigue leyéndose como una pieza, o es una lista de
   cosas apiladas?
7. **Defectos** — ¿algo se ve roto, desalineado o fuera de lugar?

### Lo que NO se le pregunta

"¿Se ve bien?", "¿te gusta?", "¿es moderno?". Eso es la capa de gusto. Un
modelo contesta esas preguntas con halagos, y un halago no es información.

### El sesgo que tiene, dicho de frente

El juez tiende a lo convencional. Una decisión deliberadamente rara y buena
puede aparecer marcada. **Sirve para detectar problemas, no para aprobar
diseño**: lo que cambia es que llegás a revisar con una lista de sospechas en
vez de con 90 capturas en blanco.

### Qué tan bien funciona, medido

Primera corrida sobre las 7 piezas: **6 errores y 45 dudas**. Verificados uno
por uno contra el CSS y las capturas:

**Acertó en lo que ningún chequeo buscaba.** En Precios vio que el nombre del
plan destacado arrancaba más abajo que los otros dos. Medido después: **1.00px
exacto**, causado por el borde de 2px contra 1px de las demás tarjetas. Nadie
lo había notado en semanas de mirar esa pieza. Eso solo ya paga la capa.

**Se equivoca de tres formas, todas reconocibles:**

1. **Llama error a lo deliberado.** En Precios `filas` dijo que la insignia
   "EL MÁS ELEGIDO" estaba "desalineada respecto al centro" — está a la
   izquierda a propósito, porque la tarjeta es ancha. Esperaba el centrado
   convencional.
2. **Alucina diferencias que no existen.** Cuatro de sus seis errores fueron
   "los botones del CTA tienen anchos distintos". Medidos: **350.0px los dos,
   mismo `left`**. Inventó el defecto.
3. **Confunde un patrón con un defecto.** Insistió tres veces en que el
   eyebrow de Testimonios ("LO QUE DICEN", 12.48px) es "demasiado chico
   frente al título". Es un eyebrow: contextualiza, no compite.

**Y aun equivocándose, sirvió.** El error falso de los botones del CTA hizo
mirar de cerca, y ahí sí había algo: 44px contra 46px de alto, porque el
secundario lleva borde y el primario no. El juez señaló el lugar correcto por
la razón equivocada.

**Regla práctica: tratá cada hallazgo como una sospecha, no como un veredicto.**
Andá a la captura, y si se puede medir, medilo. De los seis "errores", uno era
real, uno apuntaba a un defecto vecino, y cuatro eran invento.

### Cuando el juez encuentre algo que un número podría decidir

Pasalo a la capa 1. Así ocurrió con `alineacion-repetidos`: el juez lo vio
primero, la medición lo confirmó con un número, y ahora es un chequeo
determinista que no depende de que el modelo lo note la próxima vez.

---

## Correr

```bash
npm run verificar          # renderiza, mide y arma el reporte (capa 1)
```

Abrí `tests/verificar/reporte.html`. Los casos con errores van primero y
abiertos; los limpios quedan colapsados.

La capa 2 va aparte porque cuesta plata: son 7 llamadas con ~12 imágenes cada
una.

```bash
npm run juzgar             # las 7 piezas
npm run juzgar precios     # una sola, para probar
npm run verificar:reporte  # rearma el reporte con el juicio incluido
```

El juez lee `OPENROUTER_API_KEY` del `.env` de GoPress — un solo lugar para el
secreto, sin copiarlo acá. El modelo se cambia con `SOFIA_MODELO_JUEZ`.

```bash
npm run verificar:medir    # solo medir (los HTML ya están)
```

## Medir lo que genera la IA

```bash
SOFIA_GOPRESS_TOKEN=... php tests/verificar/generar-paginas.php
node tests/verificar/medir.mjs salida-ia
```

**La misma vara que las piezas a mano** — si el generador se midiera con
chequeos distintos, comparar no querría decir nada. Los pedidos por defecto
son de negocio ("una landing para una escuela de surf"), no de layout: si hay
que dictarle la estructura, el generador no resuelve el problema, lo
transcribe.

### Lo que la primera corrida enseñó

**Las 6 de 6 páginas salieron contaminadas con contenido de otro rubro.** El
modelo llenaba 3 de los 9 campos del Hero y omitía el resto; un campo omitido
no queda vacío, el tema cae a `props_por_defecto()`. Seis páginas de
odontología, café y SaaS hablando de las nueve bahías de Jalisco.

Se arregló con **una regla en el prompt de GoPress** (la 4b): llená todos los
campos, y si uno no aplica poné `""` explícito. De 6 contaminadas a 0.

**Y el arnés tuvo el mismo bug en su primera versión**, leyendo `contenido`
donde el árbol trae `props`. Midió 18 mediciones con 0 errores — porque medir
el default mide una pieza correcta. *Un arnés puede estar verde y estar
midiendo otra cosa.* Ahora falla ruidosamente si un árbol no trae contenido.

### Dos defectos del tema que solo aparecieron acá

Ninguno se veía en las 7 piezas, porque dependen de combinaciones que los
defaults no producen:

- **Stepper desbordaba 105px en teléfono.** Es de la Fase 5 y nunca había
  pasado por el arnés, que solo cubría las piezas. Le faltaba `min-width: 0`
  y apilarse en móvil.
- **El botón del CTA oscuro era negro sobre negro.** Usaba
  `--sofia-color-primario`, que con la paleta por defecto es el mismo
  `#1c1a17` del fondo. El texto contrastaba perfecto, así que el chequeo de
  contraste lo daba por bueno — de ahí nació `superficie-invisible`.

La IA no rompe las piezas: solo elige y ordena. Lo que hace es **usarlas en
combinaciones que nadie probó**, y ahí aparecen los huecos.

### El hueco más grande, y cómo se cerró

**Ninguna de las 6 páginas traía header ni footer.** No es que la IA los
evitara mal: eran dos bloques más del catálogo, y acordarse de ponerlos en
cada página no es una decisión de contenido.

Se movieron a **nivel sitio** (columna `cabecera_pie` en GoPress, ver
`inc/class-cliente-gopress.php` y `page.php`) y se sacaron del catálogo que
ve el generador. Ahora están siempre, sin que nadie los elija. **De 0 de 6 a
6 de 6.**

El arnés los incluye al medir, porque medir solo el medio de una página no
dice si la página está bien. Van a decir el nombre del sitio de prueba
aunque el pedido sea de otro rubro — eso es correcto: un sitio real tiene un
solo nombre.

## Cuando el arnés diga que todo está bien

Desconfiá y comprobalo. Un arnés que no encuentra nada puede estar roto:
inyectá un defecto conocido en un HTML de `salida/` (un `color:#c9c6c0`, un
`width:3000px`) y confirmá que lo detecta. Así se verificó esta versión —
encontró los cuatro defectos inyectados, y en el camino aparecieron seis bugs
del propio medidor que daban falsos positivos.

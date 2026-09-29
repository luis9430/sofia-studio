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

---

## Correr

```bash
npm run verificar          # renderiza, mide y arma el reporte
```

Abrí `tests/verificar/reporte.html`. Los casos con errores van primero y
abiertos; los limpios quedan colapsados.

```bash
npm run verificar:medir    # solo medir (los HTML ya están)
npm run verificar:reporte  # solo rearmar el reporte
```

## Cuando el arnés diga que todo está bien

Desconfiá y comprobalo. Un arnés que no encuentra nada puede estar roto:
inyectá un defecto conocido en un HTML de `salida/` (un `color:#c9c6c0`, un
`width:3000px`) y confirmá que lo detecta. Así se verificó esta versión —
encontró los cuatro defectos inyectados, y en el camino aparecieron seis bugs
del propio medidor que daban falsos positivos.

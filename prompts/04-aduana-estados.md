<!-- ciclo: critica=no turno-noche=0 -->
# Tarea 04 — Aduana de la invariante 7: ninguna asignación de estado suelta

Sesión nueva y aislada. Todo lo que necesitás está acá o en el repo.

Cargá los skills `verificacion` y `dominio-backend`. No leas `docs/` entero.

## Por qué esta tarea

La invariante 7 de `CLAUDE.md` dice que **toda transición de estado pasa por el
servicio de dominio de la máquina de estados correspondiente** — tabla de
transiciones permitidas más guardas — y nunca por un `estado = ...` suelto en
un controlador o en un caso de uso.

Hoy eso se cumple por disciplina: nadie lo impide y la cascada quedaría igual
de verde. Y el ciclo automático mergea con ese verde. Mientras no exista la
aduana, el verde no significa lo que parece
(`docs/gestion/automatizacion_desarrollo.md`, "lo que falta", punto 1).

Se escribe **ahora**, antes que el dominio que vigila. Puesta antes, falla la
primera vez que alguien la cruce; puesta después, hay que salir a buscar qué se
coló.

## Qué hacer

Un test de aduana en `tests/Unit/`, al estilo de los dos que ya existen —
leelos primero, la forma importa: `tests/Unit/ArquitecturaModulosTest.php`
(descubre los módulos recorriendo `app/Dominios/`, no con una lista fija) y
`tests/Unit/TokensColorTest.php` (escanea archivos, descarta comentarios y
declara sus excepciones de forma explícita).

El gate tiene que detectar, en `app/`:

- Asignación directa a una propiedad de estado: `->estado = ...`,
  `->estado_actual = ...` y variantes con el sufijo `_estado`.
- Asignación por arreglo en una escritura: `update([... 'estado' => ...])`,
  `fill([...])`, `create([...])` con clave de estado.
- `forceFill` con clave de estado.

Y admitirlo **solo** donde la invariante lo permite: dentro del servicio de
máquina de estados del módulo que lo declare. Como ese servicio todavía no
existe, definí vos la convención — nombre y ubicación — y dejala documentada en
el propio test: es la que van a seguir las tareas del Sprint 2. Elegí algo que
se descubra recorriendo el árbol, no una lista fija de archivos.

Fuera del alcance del escaneo, porque no son transiciones de dominio:
migraciones, seeders, factories, tests y comentarios.

**Verificá el gate en los dos sentidos.** Un test que solo pasa en verde no
prueba nada: metele una asignación de estado suelta a un archivo de `app/`,
confirmá que el gate falla y que reporta `archivo:línea`, y sacala. Contá en
`runs/04.md` qué inyectaste y qué reportó — sin esa evidencia la tarea no está
hecha.

## Qué NO hacer

- No implementes la máquina de estados. Esta tarea es la aduana, no el dominio.
- No toques `CLAUDE.md`, los ADRs ni `.claude/`.
- No agregues `ignoreErrors`, baselines ni `skip` para que la cascada pase.
- No reformatees los tests que ya existen; leelos y seguí su forma.

## Criterio de aceptación

`./bin/verify` devuelve 0, y el gate falla —con `archivo:línea`— cuando se le
inyecta una asignación de estado suelta.

## Máximo de intentos

3.

## Commits

Agrupados por función, no uno por archivo ni uno solo con todo. Mensajes en
español, imperativo, explicando el **porqué** — mirá `git log` para el tono.

## Cierre obligatorio

- `runs/04.estado` con una sola palabra: `OK` o `BLOQUEADA`.
- `runs/04.md`: qué hace el gate, qué convención definiste para el servicio de
  estados, la evidencia de la inyección, y qué quedó afuera.
- `runs/04.pr.md`: título del PR en la primera línea, cuerpo debajo. El ciclo
  lo usa tal cual para abrir el PR — no lo abras vos.

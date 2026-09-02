<!-- ciclo: critica=si turno-noche=1 descongela=tests rama=feature/timezone-operaciones etapas=3 -->

# Tarea 29 — corregir el bug de timezone en horarios de trabajo y sesión

## Por qué esta tarea, y por qué es crítica

`docs/gestion/cola_tareas.md` no la tenía anotada como fila propia todavía,
pero el hallazgo está documentado DOS VECES, en dos tareas distintas, cada una
pidiendo explícitamente que se convierta en tarea propia:

- `runs/24.md:195-206` (tarea 24, HU-17): "recomiendo que la próxima sesión de
  planificación lo convierta en tarea propia — dado que Bolivia es UTC-4 y
  probablemente TODO cierre real en producción lo dispara, no es cosmético."
- `runs/25.md`, sección "Hallazgo, no corregido: timezone en `Sesion.inicio`/`fin`":
  mismo bug, confirmado de nuevo, tampoco corregido porque el prompt de esa
  tarea prohibía tocar `Sesion`/`Trabajo`.

Toca `MaquinaEstadosTrabajo` y `MaquinaEstadosSesion` — el servicio de estados
de la lista de "qué no delegar sin revisión línea por línea" de `CLAUDE.md`.
Por eso `critica=si`: se implementa igual, pero el PR se anota en
`runs/revision-pendiente.txt` para revisión humana posterior (no se abre en
borrador — ver la política vigente en `automatizacion_desarrollo.md` §5).

## El mecanismo exacto (verificado en el código, no de oídas)

- `ope_trabajos.inicio`/`fin` y `ope_sesiones.inicio`/`fin` son
  `$table->dateTime(...)` — sin timezone (`database/migrations/2026_09_01_100001_create_ope_trabajos_table.php:37-38`,
  `2026_09_01_100002_create_ope_sesiones_table.php:39-40`).
- Los modelos `Trabajo`/`Sesion` castean esas columnas como
  `'inicio' => 'immutable_datetime'` / `'fin' => 'immutable_datetime'`, sin
  formato explícito.
- `config('app.timezone')` es `'UTC'` (`config/app.php:68`).
- `MaquinaEstadosTrabajo::cerrar()` y `MaquinaEstadosSesion::cerrar()` asignan
  `$modelo->fin = CarbonImmutable::parse($fin);` — `parse()` CONSERVA el
  offset original del string entrante (p. ej. `-04:00`), no lo normaliza. Al
  guardar, Eloquent formatea con ese offset conservado: escribe la hora LOCAL
  literal ("16:30:00") en una columna sin tz. Al releer, Carbon la reconstruye
  asumiendo UTC — el instante real queda corrido por el valor del offset
  (4 horas, en Bolivia).
- Esto YA se encontró y corrigió una vez, en un caso hermano:
  `MaquinaEstadosActa::firmar()` (`app/Dominios/Operaciones/Aplicacion/MaquinaEstados/MaquinaEstadosActa.php:49-56`)
  aplica `CarbonImmutable::parse($fechaFirma)->utc()` — con el comentario que
  explica exactamente este mecanismo. Es el patrón a replicar, no a
  reinventar.
- `abrir()` tiene el MISMO problema, y nadie lo anotó todavía porque las dos
  tareas que lo encontraron trabajaban sobre `cerrar()`: `MaquinaEstadosTrabajo::abrir()`
  y `MaquinaEstadosSesion::abrir()` reciben `inicio` (y a veces `fin`) dentro
  de `$atributos` y lo pasan directo a `Model::create()` sin ningún `->utc()`
  — el cast automático de Eloquent tampoco lo normaliza. Confirmá esto vos
  mismo antes de asumir el alcance completo; no lo des por sentado solo porque
  lo dice este prompt.

## Qué hacer

Cargar skill `verificacion` y `dominio-backend` antes de tocar nada.

1. En `MaquinaEstadosTrabajo::abrir()` y `::cerrar()`, y en
   `MaquinaEstadosSesion::abrir()` y `::cerrar()`: normalizá `inicio`/`fin` a
   UTC antes de persistir, mismo criterio que `MaquinaEstadosActa::firmar()`
   (`CarbonImmutable::parse($valor)->utc()`). `abrir()` recibe un array —
   interceptá `inicio`/`fin` ahí antes de pasarlos a `create()`.
2. Revisá los DTOs que alimentan estos métodos
   (`AperturaTrabajo`/`AperturaSesion`/`CierreTrabajo`/`CierreSesion` en
   `Operaciones/Contratos/` o donde vivan) — confirmá en qué formato llega
   `inicio`/`fin` desde el dispositivo (¿siempre con offset explícito? ¿a
   veces ya en `Z`/UTC?) para no asumir un formato que no se cumple siempre.
3. **No cambies el tipo de columna** (`dateTime` → `timestamptz`): la
   corrección es de aplicación, igual que se hizo en `MaquinaEstadosActa`, no
   de esquema. Cambiar el tipo de columna es una migración con más blast
   radius del que esta tarea necesita.
4. Revisá si algún lector aguas abajo (paneles, `ArmarContenidoReporteTecnico`,
   `ActaConformidadTest`, alertas de HU-19 que comparan horas) asumía el
   comportamiento viejo — con `app.timezone = UTC` y todo ya se guardaba
   "como si fuera UTC" aunque no lo fuera, así que un valor que ya estaba en
   UTC (offset `Z` o `+00:00`) no cambia de comportamiento; solo cambia lo que
   antes venía con OTRO offset.

## Cómo repartir las etapas

- **Etapa 1**: el fix en los cuatro métodos (`abrir`/`cerrar` × `Trabajo`/`Sesion`)
  + el test de round-trip que prueba el bug (ver criterio de aceptación).
- **Etapa 2**: recorrida de consumidores aguas abajo — confirmar que nada
  dependía del comportamiento roto, correr toda la suite de `Operaciones` y
  `Sincronizacion`.
- **Etapa 3**: margen si aparece algo — no lo uses si no hace falta.

## Qué NO hacer

- No toques `MaquinaEstadosActa::firmar()` — ya está corregido, es la
  referencia, no el objetivo.
- No amplíes el rastreo a otras tablas con columnas `dateTime` fuera de
  `Trabajo`/`Sesion` salvo que encuentres el MISMO patrón exacto
  (`CarbonImmutable::parse()` sin `->utc()` sobre un valor que puede traer
  offset no-UTC, persistido en una columna sin tz) — si lo encontrás, anotalo
  en `runs/29.md` para una tarea futura en vez de ampliar el alcance de esta.
- No es la tarea para completar incidencias en el reporte técnico (tarea 28,
  ya en la cola) ni para la FK de auditoría (tarea 30).

## Criterio de aceptación

`./bin/verify` = 0, con un test que reproduce el bug de punta a punta (no solo
sobre el objeto en memoria — tiene que pasar por guardado y relectura real):
crear un trabajo/sesión con `inicio`/`fin` en un string con offset NO-UTC
(p. ej. `2026-09-02T16:30:00-04:00`) vía el motor de sync real (`POST
/api/sync`, abrir y cerrar), recargar desde la base (`->fresh()`) y comparar
el instante contra el equivalente en UTC (`2026-09-02T20:30:00Z`) — no contra
el string literal. Ese test debe fallar en rojo si corrés la suite ANTES del
fix (confirmalo vos mismo en la etapa 1, no lo asumas) y pasar después.

## Cierre de la etapa

`runs/29.estado` con una palabra. `runs/29.md` con qué se hizo y qué falta —
en particular, si encontraste el mismo patrón en otra tabla y lo dejaste sin
tocar, anotalo ahí explícito. Al llegar a `OK`, `runs/29.pr.md`.

## Commits

Agrupados por función: el fix de `MaquinaEstadosTrabajo`/`MaquinaEstadosSesion`
en un commit (o dos si conviene separar abrir/cerrar), el test de round-trip
en otro. Español, imperativo, explicando el porqué del `->utc()` (podés citar
el comentario ya existente en `MaquinaEstadosActa::firmar()` como precedente).
Sin `Co-Authored-By`.

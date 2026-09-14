<!-- ciclo: critica=no turno-noche=1 rama=feature/campania-estacion etapas=2 descongela=tests -->

# Tarea 93 — HU-77: campaña con estación y nombre autogenerado

## Qué hacer

`cpn_campanias` (ADR 0015) no distingue si una campaña es de invierno o de
verano, y hoy `nombre` siempre lo tipea el encargado a mano. El dueño pidió
las dos cosas el 13/9/2026 (`observaciones_operaciones_comercial_2026-09-13.md`,
fila HU-77): un catálogo cerrado `estacion` (`invierno`/`verano`) y que
`nombre` se arme solo cuando no se especifica, con el formato
`Estación/AñoInicio/AñoFin` (p. ej. `Verano/2025/2026`, con la estación
capitalizada). El dueño también pidió mostrar "Activa"/"Inactiva" en el
panel — **es una etiqueta de presentación, no una columna nueva**: la
máquina real sigue siendo `planificada → abierta → cerrada`, irreversible
desde `cerrada` (ADR 0015), y esta tarea no la toca. "Activa" = estado
`planificada` o `abierta`; "Inactiva" = `cerrada`.

Cargá el skill `verificacion` antes de tocar código, y `modelo-datos` antes
de escribir la migración.

1. **Migración `ALTER` aditiva** sobre `cpn_campanias`: columna `estacion`
   `string(10)` `NOT NULL` (sin default — toda campaña nueva la declara; las
   filas existentes de datos demo necesitan un valor de relleno explícito en
   la propia migración, no un default silencioso). `CHECK` solo pgsql
   (`invierno`/`verano`), mismo molde que `cpn_campanias_estado_chk` en
   `database/migrations/2026_09_08_100001_create_cpn_campanias_table.php`.

2. **`Campania::$fillable`** suma `estacion`. Revisá `casts()` si hace falta
   (no debería: es un string plano, no un enum de dominio propio — no hay
   ninguno en el módulo hoy, mismo criterio que `estado`).

3. **`CrearCampania::ejecutar()`** (`app/Dominios/Campania/Aplicacion/CrearCampania.php`)
   suma el parámetro `estacion` y autogenera `nombre` cuando llega `null`:
   `estacion` capitalizada (`Invierno`/`Verano`) + `/` + año de
   `fecha_inicio` + `/` + año de `fecha_fin` (`Carbon::parse($fechaInicio)->year`).
   Revisá si `ActualizarCampania` tiene una lógica de `nombre` equivalente
   (editar campaña sin nombre no debería vaciarlo ni regenerarlo solo, salvo
   que el propio criterio de alta ya cubra ese caso — decidilo mirando el
   código real, no lo asumas).

4. **`CrearCampaniaRequest`**: `estacion` requerida, `in:invierno,verano`.
   Mensaje traducido si hace falta uno nuevo (mismo patrón que los mensajes
   de catálogo cerrado ya existentes en el módulo, p. ej. `error_estado`).

5. **Vista de listado/ficha de campaña**: la etiqueta "Activa"/"Inactiva"
   derivada del estado — un método de presentación en el modelo o en la
   vista, no una columna. Select de `estacion` en el formulario de alta.
   `lang/es/campania.php` suma las etiquetas nuevas.

## Cómo repartir las etapas

- **Etapa 1**: migración, modelo, `CrearCampania`/`ActualizarCampania`,
  Request, tests de esquema y de caso de uso/HTTP (autogeneración de
  nombre, catálogo cerrado). `./bin/verify --sin-assets` en verde.
- **Etapa 2**: vista (select de estación, etiqueta Activa/Inactiva), lang,
  test de que el formulario y el listado los muestran. `./bin/verify`
  completo.

## Qué NO hacer

- No agregar una columna `activa` booleana ni ningún campo persistido para
  la etiqueta — es derivada del `estado` que ya existe.
- No tocar `TransicionesCampania` ni `MaquinaEstadosCampania`: la
  irreversibilidad desde `cerrada` no cambia, y no hay transición nueva que
  agregar.
- No reescribir el ADR 0015 — ya describe el modelo de estados; esta tarea
  solo lo reafirma en la UI.
- No confundir `estacion` de campaña con `tipo_aplicacion` de la orden
  (`siembra`/`desarrollo`/`cosecha`, tarea 70/HU-47) — son catálogos
  distintos, sin relación entre sí.

## Criterio de aceptación

`./bin/verify` = 0, con:
- Test de que crear una campaña sin `nombre` lo autogenera correcto
  (estación + años de las fechas dadas).
- Test de regresión: una campaña `cerrada` sigue sin poder reabrirse (el
  test ya existente de la máquina de estados no debe romperse).
- Test de `estacion` fuera de `{invierno, verano}` rechazado.

## Cierre de la etapa

`runs/93.estado` con una palabra (`PARCIAL`/`OK`/`BLOQUEADA`). `runs/93.md`
con qué se hizo y qué falta. Al llegar a `OK`, `runs/93.pr.md` con el título
en la primera línea y el cuerpo debajo.

Commits agrupados por función (esquema+dominio, luego vista+lang), en
español, imperativo, sin `Co-Authored-By`.

<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/horas-generador etapas=2 -->

# Tarea 101 — HU-86: horas inicial y actual de un generador

## Qué hacer

`man_generadores.horas_uso` es hoy un único valor cargado a mano. HU-86 lo
separa en `horas_inicial` (con cuántas horas entró el generador a la flota)
y `horas_actual` (cuántas lleva hoy) — mismo espíritu que `ciclos_inicial`/
`ciclos_acumulados` de batería (tarea 98) y que `kilometraje_inicial`/
`kilometraje_actual` de vehículo (tarea 99), pero acá además hay que
**migrar datos existentes**: `horas_inicial = horas_actual = horas_uso`
antes de dropear la columna vieja. Es la primera migración de este repo que
mezcla backfill de datos con cambio de esquema en el mismo paso — no hay
precedente exacto que copiar; usá el criterio general de
`docs/decisiones/` (soft delete, DECIMAL, CHECK solo pgsql) y el patrón de
`ALTER` + `CHECK` de las tareas 92/98/99, no un mecanismo nuevo.

Cargá el skill `verificacion` antes de tocar código, y `modelo-datos` antes
de escribir la migración.

1. **Migración**
   `database/migrations/2026_09_14_100015_reemplaza_horas_uso_por_inicial_y_actual_en_man_generadores_table.php`:
   - `up()`: agregá `horas_inicial` (`decimal(8,2)`, nullable,
     `after('estado')`) y `horas_actual` (`decimal(8,2)`, nullable,
     `after('horas_inicial')`) con `Schema::table`. Backfill con
     `DB::table('man_generadores')->whereNotNull('horas_uso')->update(['horas_inicial' => DB::raw('horas_uso'), 'horas_actual' => DB::raw('horas_uso')])`
     — **antes** de dropear la columna vieja. `CHECK` (solo pgsql):
     `horas_inicial IS NULL OR horas_inicial >= 0`,
     `horas_actual IS NULL OR horas_actual >= 0`, y
     `horas_actual IS NULL OR horas_inicial IS NULL OR horas_actual >= horas_inicial`.
     Por último, `Schema::table(...)->dropColumn('horas_uso')`. En Postgres,
     dropear una columna se lleva consigo el `CHECK` que depende solo de
     ella (`man_generadores_horas_uso_chk`) — **comprobalo con el test de
     migración**, no lo des por hecho sin verlo pasar.
   - `down()`: recreá `horas_uso` (`decimal(8,2)`, nullable), backfillealo
     desde `horas_actual` (mejor esfuerzo — revertir pierde precisión si
     `horas_inicial != horas_actual`, aceptable para una migración de
     desarrollo), dropeá `horas_inicial`/`horas_actual` y sus `CHECK`.
2. `app/Dominios/Mantenimiento/Infraestructura/Eloquent/Generador.php`:
   `horas_uso` sale de `$fillable`/`casts()`/docblock; `horas_inicial`/
   `horas_actual` entran (`decimal:2`).
3. `CrearGenerador::ejecutar()`/`ActualizarGenerador::ejecutar()`: el
   parámetro `?string $horasUso` se reemplaza por `?string $horasInicial,
   ?string $horasActual`.
4. `CrearGeneradorRequest`/`ActualizarGeneradorRequest`: sacá `horas_uso`;
   sumá `horas_inicial`/`horas_actual` (`nullable|numeric|min:0`) más la
   regla de que `horas_actual` no puede ser menor que `horas_inicial`
   cuando los dos vienen cargados (`gte:horas_inicial` de Laravel, o una
   regla a medida — a tu criterio, pero el rechazo tiene que salir como
   error de validación del Request, nunca como excepción de base de datos
   sin capturar).
5. `GeneradoresController`: `store()`/`update()` pasan los dos campos
   nuevos.
6. `_formulario.blade.php` de generadores: reemplazá el campo único "horas
   de uso" por dos (`campo_horas_inicial`/`campo_horas_actual`).
7. `lang/es/mantenimiento.php`, bloque `generadores`: sacá la clave de
   "horas de uso" y sumá las dos nuevas.
8. Tests: extendé
   `tests/Feature/Mantenimiento/GestionGeneradoresPanelTest.php` con el
   rechazo de `horas_actual < horas_inicial` y el guardado/lectura de
   ambos campos. Sumá además un test de la migración en sí (backfill): no
   se puede inspeccionar el estado intermedio bajo `RefreshDatabase`
   (corre las migraciones hasta el final, y `horas_uso` ya no existe ahí).
   Instanciá la migración directo:
   `$migracion = require database_path('migrations/2026_09_14_100015_....php');`
   da un objeto con `up()`/`down()` — montá el escenario "pre-migración"
   con `Schema::table` agregando `horas_uso` a mano, insertá una fila con
   valor, corré `$migracion->up()` y verificá que `horas_inicial` y
   `horas_actual` quedaron con ese valor. Es un patrón nuevo en este repo:
   documentá la decisión con un comentario corto en el test.

## Cómo repartir las etapas

- Etapa 1: migración (con su test de backfill) + `Generador.php` + casos de
  uso + Requests.
- Etapa 2: `GeneradoresController`, `_formulario.blade.php`,
  `lang/es/mantenimiento.php`, tests HTTP de
  `GestionGeneradoresPanelTest.php`.

Es sugerencia, no contrato.

## Qué NO hacer

- No toques `man_vehiculos` ni `man_baterias` — son las tareas 99 y 102.
- No dejes `horas_uso` conviviendo con las columnas nuevas "por las dudas":
  la HU pide reemplazo, no adición. Si algo en el código todavía lee
  `horas_uso` después de este cambio (grep antes de cerrar), es una
  regresión, no un caso a tolerar.
- No inventes una máquina de estados para `horas_actual`: sigue siendo
  carga manual, igual que antes — un generador no vuela, no hay de dónde
  derivarlo (mismo criterio que la migración original de esta tabla).
- No abras el PR — lo hace `bin/ciclo`. Dejá el cuerpo en `runs/101.pr.md`.

## Criterio de aceptación

- `./bin/verify` = 0.
- Test de migración de datos que preserva el valor existente en ambas
  columnas nuevas.
- Test de que `horas_actual < horas_inicial` se rechaza.

## Cierre obligatorio de cada etapa

`runs/101.estado` con una sola palabra: `PARCIAL` si avanzó y commiteó pero
la HU sigue abierta, `OK` recién cuando está entera, `BLOQUEADA` si falta
una decisión que no le corresponde. `runs/101.md` con qué se hizo y **qué
falta**, concreto. Al cerrar con `OK`, `runs/101.pr.md` con el título del PR
en la primera línea y el cuerpo debajo.

## Commits

Agrupados por función (migración+dominio+casos de uso+requests en uno,
controlador+vista+idioma+tests HTTP en otro), español, imperativo, el
porqué. Sin trailer `Co-Authored-By`.

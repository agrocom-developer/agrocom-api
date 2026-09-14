<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/ficha-vehiculo etapas=3 -->

# Tarea 99 — HU-84: ficha completa de vehículo y estado "pausa"

## Qué hacer

`man_vehiculos` hoy solo tiene `identificador`/`base_id`/`estado`. HU-84 la
completa con los mismos datos de inventario que ya tiene el resto de la
flota (`docs/negocio/observaciones_recursos_2026-09-13.md` §3) y suma un
cuarto estado, `pausa`, distinto de `taller` (en reparación) y de `de_baja`
(fuera de servicio definitivo). Es el mismo tipo de cambio que la tarea 98
(HU-83, ya integrada) acaba de hacer sobre `man_baterias` — usala como
referencia directa de patrón, migración, tests y estructura de commits.

Cargá el skill `verificacion` antes de tocar código, y `modelo-datos` antes
de escribir la migración.

1. **Migración** `database/migrations/2026_09_14_100013_add_ficha_completa_y_pausa_a_man_vehiculos_table.php`
   — mismo molde que
   `2026_09_14_100012_add_ciclos_inicial_y_mantenimiento_a_man_baterias_table.php`
   (columnas nuevas + `CHECK` de `estado` actualizado en la misma migración,
   `up()`/`down()` simétricos, `CHECK` solo en pgsql):
   - `marca` (`string(60)`, nullable), `modelo` (`string(60)`, nullable —
     `man_vehiculos` no lo tiene hoy, a diferencia de `man_generadores`),
     `anio` (`unsignedSmallInteger`, nullable), `combustible` (`string(20)`,
     nullable, `CHECK combustible IS NULL OR combustible IN ('gasolina',
     'diesel')`), `es_4x4` (`boolean`, default `false`),
     `kilometraje_inicial` (`decimal(10,2)`, nullable, `CHECK >= 0`),
     `kilometraje_actual` (`decimal(10,2)`, nullable, `CHECK >= 0`).
   - `estado`: drop + recreate del `CHECK man_vehiculos_estado_chk` sumando
     `'pausa'` — mismo patrón exacto que el `CHECK` de `estado` en la
     migración de la tarea 98.
2. **Dominio**:
   - `app/Dominios/Mantenimiento/Dominio/EstadoVehiculo.php`: nuevo caso
     `Pausa = 'pausa'`. Docblock aclarando que sigue sin ser máquina de
     estados (invariante 7 de CLAUDE.md no aplica, mismo criterio que
     `EstadoBateria`).
   - Enum nuevo `app/Dominios/Mantenimiento/Dominio/TipoCombustibleVehiculo.php`
     (`Gasolina = 'gasolina'`, `Diesel = 'diesel'`) — catálogo cerrado,
     mismo patrón que `EstadoVehiculo`/`EstadoGenerador` (backed enum +
     `Rule::enum()` en el Request).
3. **Casos de uso**: `CrearVehiculo::ejecutar()` y
   `ActualizarVehiculo::ejecutar()` suman los parámetros nuevos (`?string
   $marca, ?string $modelo, ?int $anio, ?TipoCombustibleVehiculo
   $combustible, bool $es4x4, ?string $kilometrajeInicial, ?string
   $kilometrajeActual`) — **sin** restricción de inmutabilidad. A
   diferencia de `ciclos_inicial` en baterías (tarea 98), el criterio de
   esta HU no pide que `kilometraje_inicial` quede fijo tras el alta: los
   dos casos de uso lo aceptan igual en alta y en edición. No repitas ahí
   la regla de la 98 — es de otro campo, con otro criterio.
4. `Vehiculo.php` (Eloquent): los 7 campos nuevos a `$fillable`, a
   `casts()` (`es_4x4` → `boolean`; `kilometraje_inicial`/
   `kilometraje_actual` → `decimal:2`; `anio` → `integer`) y al docblock
   `@property`.
5. `CrearVehiculoRequest`/`ActualizarVehiculoRequest`: `marca`/`modelo`
   (`nullable|string|max:60`), `anio` (`nullable|integer`), `combustible`
   (`nullable`, `Rule::enum(TipoCombustibleVehiculo::class)`), `es_4x4`
   (`boolean`), `kilometraje_inicial`/`kilometraje_actual`
   (`nullable|numeric|min:0`).
6. `VehiculosController`: `store()`/`update()` pasan los campos nuevos al
   caso de uso; `create()`/`edit()` suman `'combustibles' =>
   TipoCombustibleVehiculo::cases()` a la vista.
7. `_formulario.blade.php` de vehículos: los 7 campos nuevos (texto para
   marca/modelo/año/kilometrajes, select para combustible, checkbox para
   4x4) — mismo estilo `x-atoms.input`/`x-atoms.select` que los campos
   existentes. Actualizar el contador de `form-section` (de 3 a 10).
8. `lang/es/mantenimiento.php`, bloque `vehiculos`: `estado.pausa` (en el
   bloque `estado` compartido), los `campo_*`/`campo_*_ayuda` de los 7
   campos nuevos, y las etiquetas de combustible.
9. Tests: extendé `tests/Feature/Mantenimiento/GestionVehiculosPanelTest.php`
   (alta/edición con los campos nuevos, `combustible` fuera de catálogo
   rechazado, transición a `pausa` aceptada, regresión de
   `activo`/`taller`/`de_baja`) y sumá un archivo nuevo
   `tests/Feature/Mantenimiento/CasosDeUsoVehiculoTest.php` (mismo molde
   que `CasosDeUsoBateriaTest.php` de la tarea 98) para las reglas que no
   dependen de HTTP.

## Cómo repartir las etapas

- Etapa 1: migración + dominio (`EstadoVehiculo`, `TipoCombustibleVehiculo`)
  + casos de uso + `Vehiculo.php` + Requests + `CasosDeUsoVehiculoTest.php`.
- Etapa 2: `VehiculosController`, `_formulario.blade.php`,
  `lang/es/mantenimiento.php`.
- Etapa 3: `GestionVehiculosPanelTest.php` (HTTP) + cierre.

Es sugerencia, no contrato — la sesión puede llegar más lejos o menos.

## Qué NO hacer

- No repitas la regla "odómetro" de la tarea 102 (HU-87, batería) para
  `kilometraje_inicial`: esa inmutabilidad es específica de
  `ciclos_inicial` y todavía no está pedida para vehículos. Si la
  tentación aparece por simetría con la tarea 98, dejala fuera y anotalo
  en "Qué NO se tocó" de `runs/99.md`.
- No toques `man_generadores` ni `man_baterias` — son las tareas 101 y
  102, no esta.
- No agregues pantalla de menú nueva: el propio Sprint 17 dice "sin
  pantallas nuevas de menú, todas amplían fichas existentes".
- No toques `docs/especificacion/` ni `docs/gestion/cola_tareas.md` — fuera
  de alcance, mismo criterio que las tareas 97/98.
- No abras el PR — lo hace `bin/ciclo`. Dejá el cuerpo listo en
  `runs/99.pr.md`.

## Criterio de aceptación

- `./bin/verify` = 0.
- Test de guardado/lectura de los 7 campos nuevos (alta y edición).
- Test de `combustible` fuera de `{gasolina,diesel}` rechazado.
- Test de transición al estado `pausa` aceptada (alta y edición), sin
  romper `activo`/`taller`/`de_baja` existentes.

## Cierre obligatorio de cada etapa

`runs/99.estado` con una sola palabra: `PARCIAL` si avanzó y commiteó pero
la HU sigue abierta, `OK` recién cuando está entera, `BLOQUEADA` si falta
una decisión que no le corresponde. `runs/99.md` con qué se hizo y **qué
falta**, concreto. Al cerrar con `OK`, `runs/99.pr.md` con el título del PR
en la primera línea y el cuerpo debajo.

## Commits

Agrupados por función (esquema+dominio+casos de uso en uno,
controlador+vista+idioma+tests HTTP en otro — mismo split que la tarea 98),
español, imperativo, explicando el porqué. Sin trailer `Co-Authored-By`.

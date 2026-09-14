<!-- ciclo: critica=no turno-noche=1 rama=feature/tipo-vehiculo etapas=2 descongela=tests -->

# Tarea 105 — HU-90: tipo de vehículo (catálogo cerrado, incluye "chata")

## Qué hacer

Como **encargado**, clasificar un vehículo por tipo (catálogo cerrado que
incluye "chata"), para diferenciar la flota. Complementa HU-84 (tarea 99,
ya integrada), misma tabla `man_vehiculos`, sin bloquearla.

Cargá los skills `verificacion`, `dominio-backend` y `modelo-datos` antes de
tocar código.

1. **Migración `ALTER`** sobre `man_vehiculos`: columna `tipo` `string(20)`
   nullable (un vehículo existente no tiene tipo asignado todavía — no hay
   valor por defecto razonable que inventar). `CHECK` solo pgsql, mismo
   molde que `man_vehiculos_combustible_chk`
   (`database/migrations/2026_09_14_100013_add_ficha_completa_y_pausa_a_man_vehiculos_table.php`).
   **El catálogo exacto**: el documento de negocio
   (`docs/negocio/observaciones_mantenimiento_2026-09-13.md`, fila HU-90)
   solo confirma que hay que sumar `chata`; no da el resto del catálogo. Los
   identificadores demo ya usan prefijos `CAM-`/`MOT-`
   (`database/seeders/Demo/FlotaDemoSeeder.php:134-137`) que sugieren
   `camioneta`/`camion` y `moto` como valores reales del dominio. Definí un
   catálogo cerrado razonable a partir de eso (por ejemplo
   `camioneta`/`camion`/`moto`/`chata`) y documentá el porqué en el docblock
   de la migración — es una decisión tuya, pero tiene que quedar escrita, no
   implícita.

2. **Enum de dominio** `TipoVehiculo` en `Mantenimiento/Dominio/`, mismo
   molde que `TipoCombustibleVehiculo`
   (`app/Dominios/Mantenimiento/Dominio/TipoCombustibleVehiculo.php`).

3. **`Vehiculo` (Eloquent)**: `tipo` en `$fillable`, cast y docblock
   `@property`.

4. **`CrearVehiculoRequest`/`ActualizarVehiculoRequest`**: regla `'tipo' =>
   ['nullable', Rule::enum(TipoVehiculo::class)]`, mismo patrón que
   `combustible`.

5. **Vista** de vehículos: sumá el campo `tipo` (select) al formulario, y
   mostralo en el listado/ficha si ya se muestran `marca`/`modelo`/etc.

6. **`lang/es/mantenimiento.php`**: labels del campo y de cada valor del
   catálogo.

7. **Demo** (opcional pero recomendable): en `FlotaDemoSeeder.php`, asigná
   `tipo` a los vehículos ya sembrados (`CAM-*` → `camion`/`camioneta`,
   `MOT-01` → `moto`) y dejá al menos uno como `chata` para que el valor
   nuevo se vea en el panel de demo.

## Cómo repartir las etapas

- **Etapa 1**: migración + enum + modelo + Requests + vista + lang.
- **Etapa 2**: demo (si corresponde) + tests + `./bin/verify` completo.

## Qué NO hacer

- No toques `equipo_tipo` de `man_ordenes_mantenimiento`: ese campo
  distingue en qué tabla vive el equipo (`dron`/`vehiculo`), no el tipo de
  vehículo en sí — una chata sigue siendo `equipo_tipo = 'vehiculo'`
  (confirmado con el usuario, ver el documento de negocio citado arriba).
  Es la confusión que el propio documento aclara explícitamente: no la
  repitas.
- No reabras HU-84 (ficha completa del vehículo, tarea 99, ya integrada)
  más allá de sumar esta columna — no toques `marca`/`modelo`/`anio`/etc.

## Criterio de aceptación

`./bin/verify` = 0, con:
- Test de que un valor de `tipo` fuera del catálogo se rechaza.
- Test de que un vehículo `chata` se crea, lista y opera igual que cualquier
  otro tipo en las pantallas ya existentes (sin tratamiento especial).

## Cierre de cada etapa

`runs/105.estado` con una palabra (`PARCIAL`/`OK`/`BLOQUEADA`). `runs/105.md`
con qué se hizo y qué falta. Al llegar a `OK`, `runs/105.pr.md` con título +
cuerpo del PR.

Commits agrupados por función, en español, imperativo, explicando el
porqué. Sin `Co-Authored-By`.

<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/ficha-inventario-dron etapas=3 -->

# Tarea 97 — HU-82: ficha de inventario del dron

## Por qué esta tarea

`ope_drones` nació "deliberadamente mínima" (docblock de
`2026_09_01_100013_create_ope_drones_table.php`): solo `identificador`, y
después `modelo`/`capacidad_l`/`capacidad_kg` (tarea 36 y 96) porque
`Operaciones` los necesitaba para operar. El encargado necesita además llevar
el activo completo del dron — serie, chasis, versión de software, región,
serie del control, accesorios — que es dato de `Mantenimiento`, no de
`Operaciones`. No es crítica: es un ABM nuevo sin máquina de estados, sin
tocar el motor de sync ni ninguna tabla de `Operaciones`. Independiente de las
tareas 85/94/95 (no toca `ope_ordenes_aplicacion`).

## Lo que ya existe

- `ope_drones.identificador`: catálogo mínimo en `Operaciones`, sin ciclo de
  vida propio (ver su docblock).
- El patrón de correlación por identificador de TEXTO entre módulos, sin FK
  real, ya existe dos veces: `man_baterias.identificador` ↔
  `ope_recargas.bateria_saliente_id` (ver docblock de
  `App\Dominios\Operaciones\Contratos\LecturaAlertasTemperaturaBateria`), y
  `man_ordenes_mantenimiento.equipo_id` ↔ `ope_drones.id`/`man_vehiculos.id`
  (ver docblock de `CrearOrdenMantenimientoRequest`, que sí valida con
  `Rule::exists()` sobre la tabla ajena aunque no haya FK real).
- Molde de ABM completo del módulo `Mantenimiento` a copiar: `man_baterias`
  (tarea 51) — migración con índice único parcial sobre `identificador`,
  `CrearBateria`/`ActualizarBateria`/`EliminarBateria`/`ListarBaterias`,
  `BateriaDuplicada`, `BateriasController`, `CrearBateriaRequest`/
  `ActualizarBateriaRequest`, vistas `baterias/{index,create,edit,_formulario}.blade.php`.
- Permisos y menú siguen el mismo molde para cada recurso nuevo de
  `Mantenimiento`: 4 permisos de grano fino en `SeguridadSeeder.php`
  (`mantenimiento.<recurso>.ver/crear/editar/eliminar`, ver bloque
  `mantenimiento.bateria.*` ~línea 331) y un ítem bajo el grupo `$recursos`
  en `SecMenuSeeder.php` (ver `$this->item($recursos, 'recursos', 'baterias',
  ...)` ~línea 205) — revisá el `orden` más alto ya usado en ese grupo y
  seguí la numeración, no asumas un número fijo de este prompt.

## Qué hacer

Cargá los skills `verificacion` y `modelo-datos`.

1. **Migración nueva `man_drones`** (módulo `Mantenimiento`, prefijo `man_`
   ya asignado por ADR 0011): `identificador_dron` (string 40, correlación de
   TEXTO con `ope_drones.identificador`, **sin FK real** — mismo criterio que
   `bateria_saliente_id`), `numero_serie`/`chasis`/`version_software`/
   `region`/`serie_control` (string nullable), `tiene_cargador_control`/
   `tiene_modem`/`tiene_maletin` (boolean, default `false`). Auditoría +
   soft delete estándar (`created_by`/`updated_by`/`timestamps`/
   `softDeletes`). Índice único PARCIAL sobre `identificador_dron`
   (`WHERE deleted_at IS NULL`) — una ficha activa por dron, mismo patrón que
   `man_baterias_identificador_unico`.
2. **Modelo Eloquent**: nombralo `FichaDron` (NO reutilices el nombre `Dron`
   — ya existe `App\Dominios\Operaciones\Infraestructura\Eloquent\Dron` y
   son clases de módulos distintos, sin relación entre sí). `ModeloDominio`
   + `RegistraBitacora`, mismo criterio que `Bateria`/`Vehiculo`.
3. **`Aplicacion/`**: `CrearFichaDron`, `ActualizarFichaDron`,
   `EliminarFichaDron`, `ListarFichasDron` — mismo molde que
   `Crear/Actualizar/Eliminar/ListarBateria`, incluida la traducción de la
   violación del índice único a `FichaDronDuplicada` (nueva excepción en
   `Dominio/Excepciones/`).
4. **Request**: `identificador_dron` valida
   `Rule::exists('ope_drones', 'identificador')->whereNull('deleted_at')` —
   mismo criterio que `equipo_id` en `CrearOrdenMantenimientoRequest`, solo
   que acá la columna destino es de texto en vez de `id`. Los tres booleanos
   de accesorios y el resto de los campos, `nullable`/`boolean` según
   corresponda.
5. **Controlador + rutas**: `FichasDronController` (`GET/POST/PUT/DELETE
   /panel/fichas-dron*` o el nombre de ruta que definas, consistente con el
   resto del panel), cuatro permisos de grano fino verificados contra el rol
   activo (mismo criterio que `BateriasController`).
6. **`SeguridadSeeder.php`**: `mantenimiento.ficha_dron.ver/crear/editar/eliminar`
   — mismo bloque de comentario explicando la HU, junto al resto de
   `mantenimiento.*`.
7. **`SecMenuSeeder.php`**: un ítem nuevo bajo `$recursos` (icono a tu
   criterio, p. ej. `memory` o `inventory_2` — evitá repetir el de drones
   operativo, `airplanemode_active`), gateado por
   `mantenimiento.ficha_dron.ver`.
8. **Vistas**: `pages/fichas-dron/{index,create,edit,_formulario}.blade.php`,
   mismo arquetipo que `baterias/*` — un formulario plano sin sub-entidad,
   los tres accesorios como checkboxes.
9. **`lang/es/mantenimiento.php`**: bloque nuevo para la pantalla, mismo
   patrón que el bloque `baterias`.

## Qué NO hacer

- No conviertas `identificador_dron` en FK real hacia `ope_drones` — el
  propio HU-82 pide explícitamente el mismo patrón sin FK que
  batería/recarga, y `ope_drones` es de otro módulo (ADR 0003: sin
  `belongsTo` cross-módulo).
- No le agregues máquina de estados: es un ABM plano, mismo criterio que
  `Bateria`/`Vehiculo` (`estado` ahí es descriptivo libre, y esta ficha ni
  siquiera tiene columna de estado).
- No toques `ope_drones` ni el módulo `Operaciones` — la única lectura hacia
  `ope_drones` es la validación `Rule::exists()` del Request.
- No le agregues `base_id`: la ficha no lo pide, no inventes una asignación
  a base que la HU no menciona.

## Cómo repartir las etapas

- **Etapa 1**: migración, `FichaDron`, los cuatro casos de uso,
  `FichaDronDuplicada`, tests de caso de uso (duplicado, alta, edición,
  borrado).
- **Etapa 2**: Request (con la validación cruzada `Rule::exists`),
  controlador, rutas, `SeguridadSeeder`, `SecMenuSeeder`, tests Feature (403
  sin permiso, 422 con `identificador_dron` inexistente, alta válida para un
  dron existente).
- **Etapa 3**: vistas, `lang/es/mantenimiento.php`, test de que borrar la
  ficha no toca la fila de `ope_drones`, `bin/verify`.

## Criterio de aceptación

- `./bin/verify` = 0.
- Test: alta de una ficha con `identificador_dron` de un dron activo real →
  201/redirect exitoso.
- Test: alta con `identificador_dron` que no corresponde a ningún dron activo
  → 422.
- Test: dar de baja (soft delete) la ficha no modifica ni borra la fila
  correspondiente de `ope_drones` (leela de nuevo después del borrado y
  confirmá que sigue idéntica).
- Test: dos fichas activas con el mismo `identificador_dron` chocan contra el
  índice único parcial como error de validación, no como 500.

## Puede tocar

Módulo `Mantenimiento` (nuevo recurso), `Operaciones/**` NO — solo lectura
vía `Rule::exists()` en el Request, sin tocar sus archivos —, migración
nueva, `routes/web.php`, `SeguridadSeeder`, `SecMenuSeeder`,
`lang/es/mantenimiento.php`, `tests/**`.

## Cierre obligatorio de cada etapa

`runs/97.estado`, `runs/97.md`, y al `OK` `runs/97.pr.md`. Commits agrupados
por función, español, imperativo, sin `Co-Authored-By`.

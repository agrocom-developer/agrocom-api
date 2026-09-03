<!-- ciclo: critica=no turno-noche=1 descongela=decisiones,tests rama=feature/vehiculos-flota etapas=3 -->

# Tarea 50 — HU-40: vehículos de la flota

## Por qué esta tarea

`plan_sprints.md` Sprint 11 (§238): "Como encargado, quiero administrar
los vehículos con su asignación a base." CA esencial: ABM con asignación
y estado. Abre Sprint 11 ("inventario y mantenimiento") con Sprint 10
cerrado por la tarea 49 (HU-35, combustible).

**No es crítica**: ABM simple, sin máquina de estados de negocio ni
dinero involucrado.

## Esta tarea crea el módulo `Mantenimiento` — decisión de arquitectura ya tomada, falta solo dejarla escrita

`docs/decisiones/0011-convencion-prefijos-tabla.md` (extensión del
26/8/2026, punto 3) reservó explícitamente "drones, baterías, vehículos y
generadores... para cuando existan los módulos `Mantenimiento`/
`Inventario`". Ninguno de los dos existe todavía. Consulta previa al
agente `arquitectura` (fuera de esta tarea) ya resolvió el reparto:

- **`Mantenimiento` (`man_`)**: equipos con desgaste que disparan alerta
  por umbral — vehículos (esta tarea), baterías (tarea 51, siguiente en
  la cola), y más adelante generadores y los planes/órdenes de
  mantenimiento (HU-37/HU-38, sin prompt todavía). `Dron` **no** se
  mueve: sigue en `Operaciones` (`ope_drones`) porque ese precedente ya
  está integrado y no hay motivo para reabrirlo.
- **`Inventario` (`inv_`)**: repuestos, stock y sus movimientos —
  exclusivo de HU-36, todavía sin prompt.

Como sos la primera tarea en tocar esta reserva, **extendé
`docs/decisiones/0011-convencion-prefijos-tabla.md`** (por eso
`descongela=decisiones` — es ampliar algo que el propio ADR dejó
explícitamente abierto, no reabrir lo que ya decidió) con un punto nuevo
que fije este mapeo (`man_` → Mantenimiento, con la lista de arriba;
`inv_` → Inventario, todavía sin tablas) para que HU-36/37/38 no
vuelvan a decidirlo. No es una decisión tuya — es transcribir la ya
tomada.

Creá el módulo desde cero: `app/Dominios/Mantenimiento/{Contratos,
Aplicacion,Dominio,Infraestructura}/`, `MantenimientoServiceProvider`
(registralo en `bootstrap/providers.php` o donde estén los demás — mirá
`OperacionesServiceProvider`/`PersonalServiceProvider` como plantilla:
qué hace su `boot()` — `View::addNamespace()` — y su `register()` —
bindings de contratos, que esta tarea no necesita todavía).

## Qué hacer

Cargá las skills `dominio-backend`, `modelo-datos`, `panel-design-ui` y
`verificacion`.

### 1. Modelo de datos

`man_vehiculos`: `id, identificador` (string(40), placa o código interno,
índice único **parcial** `WHERE deleted_at IS NULL` — mismo patrón que
`ope_drones.identificador`), `base_id` (FK `per_bases`, entero plano sin
`belongsTo` cross-módulo — mismo criterio que `Gasto.base_id`), `estado`
(`string(20)`, `CHECK` con un set simple y razonable — p. ej. `activo` /
`taller` / `de_baja`; documentá tu elección en el docblock de la
migración), auditoría, soft delete.

"Estado" acá es un campo descriptivo, no una máquina de estados de
negocio: no hay guardas ni transiciones gobernadas por una regla del
dominio (a diferencia de `MaquinaEstadosContrato` o `MaquinaEstadosOrden`)
— la invariante 7 de `CLAUDE.md` aplica a transiciones de negocio, no a
un enum de estado libre. No construyas una máquina de estados para esto.

### 2. Caso de uso y ABM

`Mantenimiento/Aplicacion/`: `CrearVehiculo`, `ActualizarVehiculo`,
`EliminarVehiculo` (soft delete, `updated_by` a mano antes de `delete()`
— mismo patrón que `EliminarDron`), `ListarVehiculos` (paginado, filtro
por base/estado). Excepción de dominio para el `identificador` duplicado
(traducí el `QueryException` del índice único parcial a un 422 legible —
mirá `DronDuplicado.php` como plantilla exacta).

### 3. HTTP, permisos, menú

`VehiculosController@index/create/store/edit/update/destroy`. Permisos
`mantenimiento.vehiculo.ver`/`.crear`/`.editar`/`.eliminar` en
`PERMISOS_ENCARGADO_OPERACIONES` (el encargado administra la flota, HU-40
lo dice literal). El ítem de menú `vehiculos` ya está sembrado como
"botón sin link" en el grupo `recursos` de `SecMenuSeeder.php` — activalo
con `ruta`/`codigoPermiso`, no crees uno nuevo. La agrupación bajo
"Recursos" es solo layout del sidebar (ADR 0011 punto 3: "esa agrupación
no es una frontera de módulo") — el backend igual vive en
`Mantenimiento`.

## Qué NO hacer

- No toques `ope_drones` ni muevas nada de `Operaciones` a
  `Mantenimiento` — el precedente de `Dron` queda donde está.
- No implementes `man_baterias`, planes de mantenimiento ni órdenes de
  mantenimiento — son las tareas 51 y siguientes, con sus propios
  prompts.
- No construyas una máquina de estados para el campo `estado`.
- No toques `Inventario` ni reserves sus tablas — es exclusivo de HU-36.

## Cómo repartir las etapas

- **Etapa 1**: extensión del ADR 0011, scaffolding del módulo
  `Mantenimiento` (carpetas, `ServiceProvider`), migración
  (`man_vehiculos`), modelo Eloquent.
- **Etapa 2**: casos de uso, controller, rutas, permisos, menú, vistas +
  copy.
- **Etapa 3**: tests Feature + spec visual + checklist §8.

## Criterio de aceptación

- `./bin/verify` = 0, con Playwright.
- Test Feature (`tests/Feature/Mantenimiento/GestionVehiculosPanelTest.php`)
  cubriendo: alta y edición válidas; `identificador` duplicado como 422
  (no 500); bitácora completa (alta/edición/baja); soft delete real (404
  en segundo intento); 403 sin permiso; multirol con rol activo
  incorrecto (invariante 10); ítem de menú publicado y gateado.
- Spec visual (`tests/Visual/vehiculos.spec.ts`), claro y oscuro.
- Checklist de `docs/diseno/guia_pantalla_panel.md` §8 pasa.
- `tests/Unit/ArquitecturaModulosTest.php` sigue en verde con el módulo
  nuevo (te descubre solo por carpeta).

## Puede tocar

`app/Dominios/Mantenimiento/**` (nuevo), `routes/web.php`,
`database/seeders/Catalogo/SeguridadSeeder.php`,
`database/seeders/Catalogo/SecMenuSeeder.php`, `lang/es/mantenimiento.php`
(nuevo), `docs/decisiones/0011-convencion-prefijos-tabla.md` (solo para
agregar el punto del mapeo `man_`/`inv_`, no reescribir lo existente),
`tests/**`.

Fuera de alcance: `Operaciones/**`, `Inventario` (sin crear todavía),
cualquier otro ADR.

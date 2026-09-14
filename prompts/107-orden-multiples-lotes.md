<!-- ciclo: critica=si turno-noche=1 rama=feature/orden-multiples-lotes etapas=5 descongela=tests -->

# Tarea 107 — HU-92: Orden de Aplicación con varios lotes

## Contexto — por qué esta tarea existe

HU-70 (tarea 85, PR #189, **ya integrada a `develop`**) construyó la
asignación de equipos asumiendo el modelo vigente: 1 orden = 1 lote, con un
índice único "una orden vigente por lote". El dueño confirmó el 14/9/2026,
con el texto completo del documento original a la vista, que el pedido real
es más amplio: una Orden de Aplicación puede cubrir varios lotes de la
propiedad, y cuando hacen falta 2 o más equipos para cubrirla, cada uno
elige qué lotes le tocan (selección múltiple) y sus hectáreas. Ver
`docs/negocio/observaciones_operaciones_comercial_2026-09-14.md` §4 — es
una **ampliación** de HU-70, no una corrección de bug, mismo tratamiento que
la reversión de CR-01 (HU-78).

Esta tarea es **crítica** (toca el motor de sync y una guarda de negocio ya
existente): se implementa e integra igual, la revisión línea por línea es
posterior, anotada en `runs/revision-pendiente.txt` — no retengas el PR en
borrador por eso (`CLAUDE.md`, `docs/gestion/automatizacion_desarrollo.md`
§5: "ninguna sesión abre su PR en borrador por ser crítica").

Cargá los skills `verificacion`, `dominio-backend` y `modelo-datos` antes de
tocar código.

## Qué hacer

### Etapa 1 — esquema y modelo

1. **Migración nueva**: tabla `ope_orden_lotes` (prefijo del módulo
   Operaciones, ADR 0011) con `orden_id` (FK a `ope_ordenes_aplicacion`,
   `cascadeOnDelete` — es un detalle de la orden, sin vida propia fuera de
   ella), `lote_id` (FK a `com_lotes`, `restrictOnDelete`, mismo criterio
   que hoy tiene `ope_ordenes_aplicacion.lote_id`), `hectareas_solicitadas`
   `decimal(10,2)`, columnas de auditoría. Revisá si una tabla de detalle
   comparable del repo (`com_contrato_ventanas`, `mez_mezcla_detalles`)
   lleva soft delete antes de decidir si esta la lleva.
   Migración de datos: por cada `OrdenAplicacion` existente, creá su fila en
   `ope_orden_lotes` con su `lote_id` actual y `hectareas_solicitadas =
   com_lotes.hectareas` (mismo valor que hoy usa `AsignarEquiposOrden` como
   tope — no pierdas esa semántica).

2. **Decisión de diseño que tenés que resolver explícitamente, no esquivar**:
   el índice único parcial actual
   (`database/migrations/2026_08_26_100009_create_ope_ordenes_aplicacion_table.php:83-89`,
   nombre `ope_ordenes_aplicacion_lote_vigente_unico`, sobre `lote_id`,
   condición `estado = 'vigente' AND deleted_at IS NULL`) vive en la misma
   tabla que tiene `estado`. Con N lotes, "un lote no puede estar en más de
   una orden vigente" cruza dos tablas (`ope_orden_lotes.lote_id` +
   `ope_ordenes_aplicacion.estado`) — un índice parcial de Postgres no
   puede expresar una condición sobre una tabla ajena. Preferí mover la
   guarda a `MaquinaEstadosOrden::activar()`
   (`app/Dominios/Operaciones/Aplicacion/MaquinaEstados/MaquinaEstadosOrden.php:41-79`):
   dentro de la misma transacción, comprobá explícitamente que ningún lote
   de esta orden aparece en `ope_orden_lotes` de otra orden `vigente` antes
   de commitear (con lock si hace falta para cerrar la carrera). Reemplazá
   `relanzarComoDuplicado()` (detecta hoy el duplicado por `str_contains`
   sobre el nombre del índice viejo) por esta guarda explícita, no por un
   índice nuevo que no puede expresarse.

3. Decidí qué hacer con `ope_ordenes_aplicacion.lote_id`: eliminarla (más
   correcto, pero repercute en ~40 archivos de test no relacionados con
   esta HU que hoy crean una orden con `lote_id` escalar solo para tener
   una orden de apoyo — ver etapa 5) o dejarla nullable, sin `fillable`, sin
   uso en el flujo nuevo, documentando por qué. Lo que **no** es aceptable
   es dejar dos fuentes de verdad activas que puedan decir cosas distintas.

4. `OrdenAplicacion` (Eloquent): relación `ordenLotes(): HasMany` hacia el
   modelo nuevo (`app/Dominios/Operaciones/Infraestructura/Eloquent/`).

### Etapa 2 — asignación de equipos

5. `AsignarEquiposOrden`
   (`app/Dominios/Operaciones/Aplicacion/AsignarEquiposOrden.php`): cambiá
   la firma de `ejecutar()` para que cada asignación traiga, además de
   `equipo_trabajo_id`, la lista de lotes que le tocan con su hectáreas.
   Documentá la forma elegida. La guarda de tope de hectáreas (hoy líneas
   82-102, `HectareasAsignadasSuperanLote`) pasa a ser **por lote**: sumá lo
   ya asignado a ESE lote (`Trabajo::where('orden_id', ..)->where('lote_id',
   ..)->sum('hectareas_declaradas')`) contra `hectareas_solicitadas` de ESE
   lote en `ope_orden_lotes` — no contra `com_lotes.hectareas` completo, que
   es la superficie total del lote, no lo que esta orden pidió de él. Al
   crear cada `Trabajo` (líneas 104-114), asignale el `lote_id` del PAR
   equipo↔lote que le corresponde, nunca `$orden->lote_id`.

6. `AsignarEquipoOrdenRequest`: reglas nuevas para la lista de lotes por
   equipo (cada lote debe pertenecer a `ope_orden_lotes` de esa orden).

7. `AsignacionEquiposController::asignar()` (líneas 101-121) + vista
   `show.blade.php` (líneas 114-139): hoy dan de alta un equipo a la vez.
   Reemplazalo por una confirmación en bloque: con 1 equipo, se le asigna
   el total de hectáreas de todos los lotes de la orden; con 2+, cada
   equipo elige (selección múltiple) qué lotes le tocan y sus hectáreas, y
   un solo submit confirma todo y genera un `Trabajo` por cada par. Revisá
   si el panel ya tiene un patrón de "filas repetibles" (usado en mezclas o
   planillas) antes de inventar un componente nuevo.

8. Vista `index.blade.php` de asignación de equipos (columna "Lote" única,
   línea 65): mostrar la lista de lotes de la orden, no un solo valor.

### Etapa 3 — alta de la orden y motor de sync

9. `/panel/ordenes/crear`: `OrdenesController::lotesDisponibles()` pasa de
   un único `<select>` a selección múltiple con hectáreas por lote;
   `CrearOrdenRequest`/`ActualizarOrdenRequest` reemplazan la regla
   `lote_id` única por una lista de lotes con hectáreas; sumá
   `cantidad_equipos_necesarios` (integer, `min:1`, default 1).
   `_formulario.blade.php`: multi-select o filas repetibles de lote +
   hectáreas, campo nuevo de cantidad de equipos, ajustá el
   `campos_contador` de la sección.

10. **Rename de label** (`lang/es/operaciones.php:397`):
    `'campo_nro_aplicacion' => 'Número de aplicación'` → `'Número de
    aplicaciones'`. Es el único cambio: el campo `nro_aplicacion` (nombre de
    columna, atributo, todo lo demás) no cambia.

11. `EscrituraSincronizacionEloquent::abrirTrabajo()` (línea 89): la guarda
    `(int) $orden->lote_id !== $datos->loteId` pasa a comprobar que el lote
    declarado por el dispositivo pertenece a `ope_orden_lotes` de esa orden
    — no una igualdad simple contra un único lote.

### Etapa 4 — contrato público (catálogo de sync y API)

12. `OrdenAplicacionCatalogo` (DTO) + `LecturaOrdenesVigentesEloquent`
    (línea 45, `loteId: $orden->lote_id`): `loteId: int` pasa a una lista de
    lotes. Documentá la forma elegida.

13. `CatalogoController`: schema OpenAPI `OrdenCatalogo` (líneas 22-53)
    refleja el cambio de forma. **`TrabajoCatalogo` y `LoteCatalogo` NO
    cambian** — cada `Trabajo` sigue con un solo `lote_id` (es un par
    equipo↔lote), y el catálogo de lotes es independiente de las órdenes.

14. `OrdenAplicacionResource` (`GET /api/ordenes`) + su schema OpenAPI:
    mismo cambio de forma. `ListarOrdenesAplicacion` (filtro `lote_id`,
    línea 41): pasar a filtrar por pertenencia en `ope_orden_lotes`.

15. `docs/api/openapi.yaml`: reflejar el cambio de forma en ambos lugares.
    Este es un cambio de contrato de API que consume `agrocom-field`
    (Flutter, otro repo) — no lo toques, pero dejalo anotado explícito en
    `runs/107.md` para quien haga la revisión línea por línea.

### Etapa 5 — tests y cierre

16. Reescribí `tests/Feature/Operaciones/AsignarEquiposOrdenTest.php` y
    `tests/Feature/Operaciones/AsignacionEquiposPanelTest.php` para el
    flujo de N lotes/N equipos.

17. Ajustá `tests/Feature/Operaciones/GestionOrdenesPanelTest.php` (payload
    de alta/edición con lista de lotes en vez de `lote_id` escalar; el test
    de "segunda activación sobre el mismo lote falla", líneas 195-217, con
    la semántica nueva de la guarda) y
    `tests/Feature/Api/ListadoOrdenesAplicacionTest.php` (filtro por lote y
    estructura JSON del resource).

18. El resto de los archivos de test que crean una `OrdenAplicacion` con
    `lote_id` escalar solo para tener una orden de apoyo en otro flujo (no
    ejercitan HU-92: `EsquemaOperacionesTest`, `EscrituraSincronizacionTest`,
    `MaquinaEstadosOperacionesTest`, y el resto de `tests/Feature/Operaciones/`
    y `tests/Feature/Api/` que crean una orden) — ajustalos mecánicamente
    para que sigan compilando: no los rediseñes, solo hacé que su helper de
    creación arme también la fila de `ope_orden_lotes` correspondiente.

19. `./bin/verify` completo.

## Qué NO hacer

- No reabras el alcance de HU-70 más allá de esta ampliación: no toques la
  guarda de "equipo vigente a la fecha" ni la de "orden vigente para
  asignar", ya correctas.
- No le busques cambios de esquema a `Trabajo`/`ope_trabajos`: ya es "un
  renglón por par equipo↔lote" — el modelo mental de HU-92 encaja sin
  tocarlo.
- No abras el PR en modo borrador por ser crítica: la revisión línea por
  línea es posterior a la integración. Al cerrar con `OK`, anotá el PR en
  `runs/revision-pendiente.txt` y dejá que el ciclo lo abra normal.
- No cambies `TrabajoCatalogo` ni `LoteCatalogo` del schema de sync — no les
  corresponde ningún cambio de forma.
- No te enganches con HU-79 (tarea 95, `tipo_insumo` en la orden): tiene su
  propio prompt ya escrito (`prompts/95-tipo-insumo-orden.md`) y el orden de
  la cola la deja después de esta tarea justamente para no tener que
  coordinar — si la ves correr, es la sesión que sigue a esta.

## Criterio de aceptación

`./bin/verify` = 0, con:
- Test de que una orden admite N filas de lote, con `SUM(hectareas_solicitadas)`
  acotado contra las hectáreas de esos lotes.
- Test de que "una orden vigente por lote" sigue garantizado (ahora por la
  guarda nueva en `MaquinaEstadosOrden::activar()`).
- Test de que asignar un equipo a 2 lotes genera 2 `Trabajo` (uno por lote)
  con su lote y hectáreas ya resueltos.
- Test de que `GET /api/sync/catalogo` sigue resolviendo el equipo de cada
  `Trabajo` generado.

## Cierre de cada etapa

`runs/107.estado` con una palabra (`PARCIAL`/`OK`/`BLOQUEADA`). `runs/107.md`
con qué se hizo y qué falta — concreto, sin dar nada por sobreentendido para
la etapa siguiente. Al llegar a `OK`, `runs/107.pr.md` con título + cuerpo
del PR, y sumá la línea del PR a `runs/revision-pendiente.txt`.

Commits agrupados por función (esquema, asignación, alta de orden, contrato
público, tests), en español, imperativo, explicando el porqué. Sin
`Co-Authored-By`.

<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/inventario-repuestos etapas=4 -->

# Tarea 52 — HU-36: inventario de repuestos con stock por base

## Por qué esta tarea

`plan_sprints.md` Sprint 11 (§234): "Como encargado, quiero llevar stock
de repuestos por base con alerta de mínimo, para reponer antes de
quedarme sin." CA esencial: movimientos de compra, salida, ajuste y
traslado; el stock nunca queda negativo; alerta al cruzar el mínimo.

Sigue a las tareas 50 (HU-40, vehículos) y 51 (HU-39, baterías), que
crearon y poblaron `Mantenimiento`. Esta tarea crea el módulo **nuevo**
`Inventario` (`inv_`) — reservado desde la extensión del 3/9/2026 al ADR
0011 (punto 14, la escribió la tarea 50) para "repuestos, stock y sus
movimientos — exclusivo de HU-36". No lo compartas con `Mantenimiento`:
son módulos separados a propósito (consultado con el agente
`arquitectura` antes de escribir el prompt de la 50/51). No hace falta
tocar el ADR de nuevo — ya está escrito.

**No es crítica**: ABM + control de cantidades con una guarda de
aplicación (stock no negativo), sin dinero ni máquina de estados de
negocio todavía. La tarea 53 sí va a cruzar dinero — no esta.

## Modelo de datos — leé la especificación completa antes de recortar

`docs/especificacion/especificacion_funcional_tecnica.md` §4.5 y §12
describen un modelo más rico (costo promedio ponderado recalculado en
cada compra, punto de reposición, marca de "repuesto crítico",
trazabilidad por serie). El CA esencial de `plan_sprints.md` es más chico
— implementá ese, no el modelo completo, y documentá cada recorte en el
docblock de la migración correspondiente (mismo estilo que
`fin_gastos`/`man_baterias`):

- **Sin costo promedio ponderado.** Guardá `costo_unitario` en
  `inv_repuestos` como el valor de la **última compra** (se
  sobrescribe en cada movimiento `compra`) — recorte explícito de
  "ponderado" a "último costo conocido". La tarea 53 (que factura la
  salida de repuestos a un gasto) necesita ESE valor: documentalo para
  que no lo reinvente.
- **Sin "repuesto crítico" ni trazabilidad por serie.** No aportan al CA
  esencial de esta HU.
- **Punto de reposición por (repuesto, base)**, no global — esto SÍ
  coincide con el CA esencial ("alerta al cruzar el mínimo") y con la
  especificación ("punto de reposición por base"), así que no es un
  recorte: va en la fila que ya junta repuesto+base (ver abajo).

### Tablas sugeridas (el nombre exacto de columnas es tu criterio, documentalo)

- `inv_repuestos`: `id, codigo` (string, identificador legible, único
  parcial `WHERE deleted_at IS NULL` — mismo patrón que
  `ope_drones.identificador`), `descripcion`, `unidad` (string libre,
  sin catálogo cerrado — mismo criterio que `ope_drones.modelo`),
  `costo_unitario` (`DECIMAL`, nullable, ver recorte de arriba),
  auditoría, soft delete.
- `inv_stock`: fila de AGREGADO por `(repuesto_id, base_id)` — `id,
  repuesto_id` (FK `inv_repuestos`), `base_id` (FK `per_bases`, entero
  plano, sin `belongsTo` cross-módulo), `cantidad` (`DECIMAL` o entero,
  default 0, `CHECK >= 0` — la guarda dura contra negativo vive en la
  base, no solo en PHP), `stock_minimo` (default 0), `unique
  (repuesto_id, base_id)`, auditoría. Es una fila derivada, no un alta
  humana directa — decidí si extiende `ModeloDominio` completo (soft
  delete incluido) o si al ser 100 % recalculable desde
  `inv_movimientos` alcanza con auditoría sin soft delete; documentá cuál
  elegiste y por qué en el docblock.
- `inv_movimientos`: asiento de cada movimiento — `id, repuesto_id, base_id`
  (la base afectada; en `traslado`, la de ORIGEN), `base_destino_id`
  (nullable, FK `per_bases`, solo se usa en `traslado`), `tipo` (`string`,
  `CHECK IN ('compra','salida','ajuste','traslado')`), `cantidad`
  (magnitud; el signo que aplica a `inv_stock` lo decide `tipo` — para
  `ajuste`, que puede ser positivo o negativo, elegí si guardás el signo
  en `cantidad` o agregás un campo `sentido`; documentalo),
  `costo_unitario` (nullable, solo tiene sentido en `compra`), `motivo`
  (nullable, para `ajuste`/`traslado`), `orden_mantenimiento_id`
  (`unsignedBigInteger` nullable, **entero plano sin FK todavía** — lo
  usa la tarea 53, que no existe aún; no le pongas `->constrained()`
  contra una tabla que no existe), auditoría. Es un asiento contable: si
  no le das soft delete, es la "excepción justificada explícitamente en
  el PR" que pide la invariante 8 de `CLAUDE.md` — decilo en el docblock
  y en la descripción del PR, no lo des por sentado en silencio.

Un `traslado` afecta DOS filas de `inv_stock` en la misma transacción
(decrementa `base_id`, incrementa `base_destino_id`) desde una sola fila
de `inv_movimientos` — no crees dos movimientos separados para esto, la
especificación lo describe como un único movimiento con dos bases.

## Caso de uso — la guarda de "nunca negativo" es de aplicación, no solo de base

`Inventario/Aplicacion/RegistrarMovimientoStock` (o el nombre que
seguido armes desde `Inventario/Aplicacion/`): recibe `tipo` + datos y,
dentro de una `DB::transaction()`, hace `lockForUpdate()` sobre la fila
de `inv_stock` afectada (créala con `firstOrCreate` si es la primera vez
que se mueve ese repuesto en esa base) antes de decidir si hay cantidad
suficiente para `salida`/`traslado`. Si no alcanza, lanzá una excepción
de dominio nueva (`Dominio/Excepciones/StockInsuficiente.php`, mismo
molde que `DronDuplicado`/`BateriaDuplicada`) que el controller traduce a
422 — nunca dejes que la resta llegue a violar el `CHECK` de la base como
mecanismo primario de validación (ese `CHECK` es el backstop, no el
camino feliz).

`ListarRepuestos`/`ListarStock` (paginado, filtro por base): calculá la
alerta por fila (`cantidad <= stock_minimo`) al leer — mismo criterio que
`ListarBaterias` (tarea 51), sin tabla de alertas propia.

## HTTP, permisos, menú

Dos pantallas (repuestos, movimientos/stock) o una con pestañas — tu
criterio, documentalo. Permisos `inventario.repuesto.ver/.crear/.editar/.eliminar`
e `inventario.movimiento.ver/.crear` en `PERMISOS_ENCARGADO_OPERACIONES`.
Los ítems `repuestos` y `stock` ya están sembrados como "botón sin link"
bajo el grupo de menú `mantenimiento` en `SecMenuSeeder.php` (igual que
`vehiculos`/`baterias` quedaron bajo "Recursos" con backend en otro
módulo) — activalos con `ruta`/`codigoPermiso`, no crees ítems nuevos.

## Qué NO hacer

- No implementes costo promedio ponderado, trazabilidad por serie, ni
  "repuesto crítico" — quedan fuera del CA esencial, documentados como
  recorte.
- No crees `man_ordenes_mantenimiento` ni nada de `Mantenimiento` — es
  la tarea 53, con su propio prompt.
- No le pongas FK real a `inv_movimientos.orden_mantenimiento_id` contra
  una tabla que todavía no existe.
- No captures la restricción de "solo salidas de su propia base" del
  jefe de campo (espec §4, línea 91, nota 4) — no está en el CA esencial
  de `plan_sprints.md`; si te sobra tiempo documentalo como pendiente, no
  lo inventes a medias.

## Cómo repartir las etapas

- **Etapa 1**: scaffolding del módulo `Inventario` (carpetas,
  `InventarioServiceProvider`, sin bindings todavía), migraciones
  (`inv_repuestos`, `inv_stock`, `inv_movimientos`), modelos Eloquent.
- **Etapa 2**: `RegistrarMovimientoStock` con la guarda de stock (los 4
  tipos), `ListarRepuestos`/`ListarStock` con alerta calculada.
- **Etapa 3**: controllers, rutas, permisos, menú, vistas + copy.
- **Etapa 4**: tests Feature + spec visual + checklist §8.

## Criterio de aceptación

- `./bin/verify` = 0, con Playwright.
- Test Feature (`tests/Feature/Inventario/GestionStockPanelTest.php` o
  similar) cubriendo: alta de repuesto; cada uno de los 4 tipos de
  movimiento aplicado correctamente a `inv_stock`; `salida`/`traslado`
  que dejaría el stock negativo rechazado (422, no 500, ni el `CHECK` de
  la base como único mecanismo); alerta activada al cruzar
  `stock_minimo` y no antes; bitácora completa; 403 sin permiso; ítems
  de menú publicados y gateados.
- Spec visual, claro y oscuro.
- Checklist de `docs/diseno/guia_pantalla_panel.md` §8 pasa.
- `tests/Unit/ArquitecturaModulosTest.php` sigue en verde con el módulo
  nuevo.

## Puede tocar

`app/Dominios/Inventario/**` (nuevo), migraciones nuevas,
`routes/web.php`, `database/seeders/Catalogo/SeguridadSeeder.php`,
`database/seeders/Catalogo/SecMenuSeeder.php`, `lang/es/inventario.php`
(nuevo), `tests/**`.

Fuera de alcance: `Mantenimiento/**`, `Operaciones/**`, `Finanzas/**`.

---

**Nota de la planificación**: no depende de ninguna tarea previa más que
de que `Mantenimiento` ya exista (tareas 50/51, ya integradas) — esta
tarea no lo toca, solo crea `Inventario` al lado. La tarea 53 (HU-37) va
a depender de que ESTA tarea esté integrada: necesita consumir stock real
para cerrar una orden.

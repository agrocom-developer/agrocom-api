<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/ordenes-mantenimiento etapas=5 -->

# Tarea 53 — HU-37: órdenes de mantenimiento que consumen stock y generan gasto

## Por qué esta tarea

`plan_sprints.md` Sprint 11 (§235): "Como encargado, quiero abrir
órdenes de mantenimiento y cerrarlas consumiendo repuestos, para que el
costo quede imputado." CA esencial: el cierre descuenta stock y genera
el gasto asociado en una transacción; no cierra sin repuestos
disponibles.

**Depende de que la tarea 52 (HU-36, inventario de repuestos) esté
integrada en `develop`** — el orden de `runs/cola.txt` lo garantiza. Si
por algo excepcional `Inventario` no existe todavía cuando arranques, es
`BLOQUEADA`: no inventes el módulo de stock por tu cuenta.

**No es crítica**, aunque genera dinero: la generación del gasto no es
un listener de dominio oculto (a diferencia de `SesionValidada` →
devengos, que sí es crítica) — es la consecuencia DIRECTA y explícita de
que el encargado aprieta "cerrar orden", igual que HU-31 (facturar un
trabajo desde su acta, tarea 45, tampoco crítica) calcula un monto
dentro de la misma acción explícita que lo pide. La lista de
`CLAUDE.md` que exige revisión línea por línea nombra "los listeners que
generan dinero (devengos, planilla)" — esto no es un listener.

## Esta HU es la primera vez que un módulo escribe en `fin_gastos` desde afuera de `Finanzas`

Hoy `Finanzas` no tiene carpeta `Contratos/` — nadie externo escribe sus
tablas todavía. Vas a crear el primer contrato de ESCRITURA cross-módulo
del proyecto (hasta ahora todos los `Contratos/` eran de lectura, salvo
`Operaciones/Contratos/EscrituraSincronizacion`, que es la plantilla de
forma a seguir: interfaz en `Finanzas/Contratos/`, implementación en
`Finanzas/Infraestructura/`, bindeada en `FinanzasServiceProvider`,
consumida desde `Mantenimiento/Aplicacion/` por la interfaz — nunca
`Gasto::create()` directo desde `Mantenimiento`).

**Reusá `Finanzas/Aplicacion/CrearGasto` por dentro de esa
implementación**, no la reescribas: ya calcula `monto` con
`Brick\Math\BigDecimal` y guarda con la invariante 6 de `CLAUDE.md`
(nunca float). El caso de uso de mantenimiento ya trae el monto total
calculado (suma de lo consumido, ver abajo) — pasale `cantidad='1'` y
`precioUnitario=$montoTotal` a `CrearGasto::ejecutar()` para que
`monto = 1 × montoTotal` sin reinventar el cálculo. `rubro`/`subrubro`:
buscá por nombre (`Rubro::where('nombre', 'Mantenimiento de equipos')`,
`Subrubro::where('nombre', 'Repuestos')`) — ya están sembrados por
`FinanzasRubrosSeeder` desde la tarea 47, no crees rubros nuevos.

No hace falta alterar `fin_gastos` para esto: guardá la trazabilidad
al revés, en `man_ordenes_mantenimiento.gasto_id` (FK plana, nullable
hasta el cierre) — un gasto no necesita saber de dónde vino para
existir como tal.

## El cruce con `Inventario` (tarea 52) — contrato de escritura, no lectura

`Mantenimiento` necesita descontar stock real al cerrar. Creá
`Inventario/Contratos/EscrituraConsumoStock` (interfaz chica, un
método — mirá `Operaciones/Contratos/EscrituraSincronizacion` para el
tono, aunque esa es más grande) con una firma parecida a:

```php
interface EscrituraConsumoStock
{
    /**
     * Descuenta $cantidad de inv_stock (repuesto+base), dentro de la
     * transacción del llamador. Lanza StockInsuficiente si no alcanza.
     * Devuelve el costo total aplicado (costo_unitario vigente × cantidad,
     * como string decimal — invariante 6) para que el llamador arme su
     * propio monto sin conocer el modelo de costeo de Inventario.
     */
    public function consumir(int $repuestoId, int $baseId, string $cantidad, ?int $ordenMantenimientoId): string;
}
```

Implementación en `Inventario/Infraestructura/`, reusando
`RegistrarMovimientoStock` (tarea 52) con `tipo='salida'` por dentro —
no dupliques la lógica de `lockForUpdate()`/guarda de stock que esa
clase ya tiene. Si `RegistrarMovimientoStock` no expone hoy el
`costo_unitario` aplicado como valor de retorno, extendela (es tu propio
módulo desde el punto de vista de esta tarea, podés tocar
`Inventario/**`).

**La transacción es UNA sola**, abierta por el caso de uso de
`Mantenimiento` (`DB::transaction()`), no una por módulo: adentro llamás
al contrato de `Inventario` por cada línea de repuesto (si cualquiera
lanza `StockInsuficiente`, todo se revierte — nada de stock consumido a
medias) y, si todas las líneas salen bien, al contrato de `Finanzas`
para crear el gasto y guardar su id en la orden. Postgres soporta
transacciones anidadas por savepoint sin problema; no necesitás que
`Inventario`/`Finanzas` abran las suyas propias para este flujo.

## Modelo de datos y máquina de estados — esto SÍ es invariante 7, a diferencia de vehículo/batería

`man_vehiculos.estado`/`man_baterias.estado` son campos descriptivos
libres (sin guarda de negocio, documentado explícito en sus propios
docblocks). **Esta tabla es distinta**: `abierta → cerrada` tiene una
guarda real (repuestos disponibles) y un efecto de dominio (consume
stock, genera gasto) — pasa por el mismo patrón que
`MaquinaEstadosOrden` (`Operaciones/Aplicacion/MaquinaEstados/`, HU-25,
tarea 38): mirala como plantilla exacta.

- `Mantenimiento/Dominio/EstadoOrdenMantenimiento.php` — enum
  (`Abierta`, `Cerrada`).
- `Mantenimiento/Dominio/MaquinaEstados/TransicionesOrdenMantenimiento.php`
  — tabla de transiciones permitidas (acá solo hay una:
  `Abierta → Cerrada`).
- `Mantenimiento/Dominio/Excepciones/TransicionOrdenMantenimientoNoPermitida.php`.
- `Mantenimiento/Dominio/Excepciones/RepuestosInsuficientes.php` — la
  guarda de negocio en sí (envolvé acá la `StockInsuficiente` que viene
  de `Inventario`, no dejes escapar la excepción del otro módulo tal
  cual hasta el controller).
- `Mantenimiento/Aplicacion/MaquinaEstados/MaquinaEstadosOrdenMantenimiento.php`
  — única clase que crea/muta `estado`. `abrir()` crea en `Abierta`.
  `cerrar(orden, líneas de repuestos)` valida la transición, abre la
  transacción, llama a `Inventario` línea por línea, suma los montos
  devueltos con `BigDecimal`, llama a `Finanzas`, guarda `gasto_id` y
  `fecha_cierre`, y recién ahí pone `estado = Cerrada`.

Migración `man_ordenes_mantenimiento`: `id, equipo_tipo` (`string(20)`,
`CHECK IN ('dron','vehiculo')` — **sin `generador`**: no existe catálogo
de generadores en este alcance, documentalo como recorte real, no
inventado), `equipo_id` (`unsignedBigInteger`, **sin FK real**: apunta a
`ope_drones.id` o `man_vehiculos.id` según `equipo_tipo`, dos tablas
distintas no pueden compartir una sola FK — validá la existencia según
tipo en la capa de aplicación, documentá el porqué en el docblock, mismo
espíritu que la correlación por texto de `bateria_saliente_id`), `tipo`
(`string(20)`, `CHECK IN ('preventivo','correctivo')`), `descripcion`
(text), `estado` (`string(20)`, gobernado por la máquina de estados de
arriba), `fecha_apertura`, `fecha_cierre` (nullable), `gasto_id`
(`unsignedBigInteger` nullable, FK plana a `fin_gastos.id`,
`restrictOnDelete`), auditoría, soft delete.

**Sin `plan_id`.** El modelo completo de la especificación (§4.5,
`ordenes_mantenimiento.plan_id`) lo vincula a un plan preventivo, pero
`man_planes_mantenimiento` no existe todavía — es la tarea 54, sin
prompt escrito cuando arranques esta. No le pongas ni siquiera un
entero plano placeholder: agregalo por `ALTER` cuando la 54 exista,
mismo patrón que `capacidad_l` se agregó después a `ope_drones` (tarea
36) y `rendicion_id` después a `fin_gastos` (tarea 48). Por ahora toda
orden es autónoma, preventiva o correctiva, sin plan que la dispare.

**Sin detalle persistido de "qué repuestos se consumieron"** en
`Mantenimiento`: esa traza ya queda en `inv_movimientos.orden_mantenimiento_id`
(columna que la tarea 52 dejó lista sin FK — ahora sí podés agregarle la
FK real por `ALTER`, es tu tarea la que crea la tabla de destino). Si el
panel necesita mostrar el detalle en la pantalla de la orden, leelo con
un contrato de lectura nuevo de `Inventario` en vez de duplicar la
información en una tabla propia.

## HTTP, permisos, menú

`OrdenesMantenimientoController@index/create/store/edit/cerrar` (sin
`update`/`destroy` de negocio libre — una orden abierta se edita en sus
datos descriptivos si querés, pero el `estado` solo cambia por
`cerrar()`; documentá qué decidiste permitir editar). Permisos
`mantenimiento.orden.ver/.crear/.editar/.cerrar` en
`PERMISOS_ENCARGADO_OPERACIONES`. El ítem `ordenes` ya está sembrado
como "botón sin link" bajo el grupo de menú `mantenimiento` en
`SecMenuSeeder.php` — activalo, no crees uno nuevo.

## Qué NO hacer

- No dupliques la guarda de stock en PHP dentro de `Mantenimiento` — la
  guarda vive en `Inventario`, `Mantenimiento` solo reacciona a la
  excepción.
- No le agregues `plan_id` a la migración, ni placeholder.
- No inventes un tipo `generador` sin catálogo detrás.
- No dejes que `cerrar()` marque `estado = Cerrada` antes de que el
  gasto exista — si `Finanzas` falla, toda la transacción se revierte.
- No toques `fin_gastos` (ni migración ni modelo) — la trazabilidad va
  al revés, desde `man_ordenes_mantenimiento.gasto_id`.

## Cómo repartir las etapas

- **Etapa 1**: migración `man_ordenes_mantenimiento`, enum/transiciones/
  excepciones de estado, modelo Eloquent.
- **Etapa 2**: contrato `EscrituraConsumoStock` (`Inventario/Contratos/`
  + su implementación), extendiendo `RegistrarMovimientoStock` si hace
  falta.
- **Etapa 3**: contrato de escritura de gasto en `Finanzas/Contratos/` +
  implementación sobre `CrearGasto`; `MaquinaEstadosOrdenMantenimiento`
  completa con la transacción de cierre.
- **Etapa 4**: controller, rutas, permisos, menú, vistas + copy.
- **Etapa 5**: tests Feature + spec visual + checklist §8.

## Criterio de aceptación

- `./bin/verify` = 0, con Playwright.
- Test Feature (`tests/Feature/Mantenimiento/OrdenesMantenimientoPanelTest.php`)
  cubriendo: apertura válida; cierre con stock suficiente descuenta
  `inv_stock` exacto y crea un `fin_gastos` con `monto` exacto; cierre
  sin stock suficiente rechazado (422), sin descontar NADA de stock y
  sin crear ningún gasto (verificá ambas tablas después del intento
  fallido); transición inválida (cerrar una orden ya cerrada) rechazada;
  bitácora completa; 403 sin permiso; ítem de menú publicado y gateado.
- Spec visual, claro y oscuro.
- Checklist de `docs/diseno/guia_pantalla_panel.md` §8 pasa.
- `tests/Unit/ArquitecturaModulosTest.php` sigue en verde: los contratos
  nuevos no rompen el aislamiento entre `Mantenimiento`, `Inventario` y
  `Finanzas`.

## Puede tocar

`app/Dominios/Mantenimiento/**`, `app/Dominios/Inventario/Contratos/**`
e `Infraestructura/**` (el contrato de escritura nuevo y lo que necesite
`RegistrarMovimientoStock` para exponer el costo aplicado),
`app/Dominios/Finanzas/Contratos/**` (nuevo) e
`Infraestructura/**` (implementación sobre `CrearGasto`, binding en
`FinanzasServiceProvider`), migración nueva, `routes/web.php`,
`database/seeders/Catalogo/SeguridadSeeder.php`,
`database/seeders/Catalogo/SecMenuSeeder.php`,
`lang/es/mantenimiento.php`, `tests/**`.

Fuera de alcance: `fin_gastos` (ni migración ni modelo), `ope_drones`,
`man_vehiculos`, `man_baterias`, `man_planes_mantenimiento` (no existe
todavía).

---

**Nota de la planificación**: depende de que la tarea 52 (HU-36) esté
integrada. No depende de la tarea 54 (HU-38, planes) — por eso va antes
en la cola pese a compartir sprint, siguiendo el orden literal de
`plan_sprints.md`.

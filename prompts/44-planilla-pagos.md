<!-- ciclo: critica=si turno-noche=1 descongela=tests rama=feature/planilla-pagos etapas=5 -->

# Tarea 44 — HU-30: planilla del período desde devengos y anticipos

## Por qué esta tarea

`plan_sprints.md` Sprint 8 (§193): "Como dueño, quiero generar la planilla
del período desde los devengos y aprobarla, para pagar con un respaldo que
cuadre." Criterio: planilla en borrador desde devengos + anticipos; solo el
dueño aprueba; recibo individual en PDF; el total cuadra exacto contra los
devengos de origen. Cierra Sprint 8 completo (HU-28 tarea 40/42, HU-29 tarea
41/43, HU-30 esta tarea).

**Va `critica=si`.** `CLAUDE.md`, "Qué no delegar sin revisión línea por
línea", nombra literal "los listeners que generan dinero (devengos,
planilla)". El PR se abre en borrador; la revisión línea por línea es
posterior a la integración (`CLAUDE.md`, sección de esa lista) — no
retengas el PR esperando revisión, el ciclo lo mergea igual y queda anotado
en `runs/revision-pendiente.txt`.

**Depende de que la tarea 43 (anticipos) esté integrada.** La planilla resta
los anticipos del período del devengado — sin `fin_anticipos` en `develop`
no hay nada que restar. Si por algún motivo llegás a esta tarea con la 43
todavía sin integrar, es `BLOQUEADA`, no una tarea para adivinar el diseño
de `fin_anticipos` en paralelo.

## Qué hacer

Cargá las skills `modelo-datos`, `dominio-backend`, `panel-design-ui`,
`seguridad-roles` y `verificacion`.

### 1. Diseño de datos — dos tablas nuevas

`fin_planillas`: `id, periodo VARCHAR(7)` (formato `YYYY-MM`, `UNIQUE` entre
vivas — una planilla por mes calendario, mismo criterio de idempotencia por
regla de negocio que `ope_actas.trabajo_id`, no por `uuid_cliente`: esto
nace en el panel), `estado`, `total DECIMAL(12,2)`, `aprobada_por` (nullable,
`sec_user.id`), `aprobada_en` (nullable, datetime), auditoría, soft delete.

`fin_planilla_detalles`: `id, planilla_id` (FK `fin_planillas`,
`cascadeOnDelete` — el detalle no tiene sentido sin su planilla, a
diferencia del resto del esquema que usa `restrictOnDelete`; justificalo en
el docblock de la migración), `persona_id` (FK `per_personas`,
`restrictOnDelete`), `devengado DECIMAL(12,2)`, `anticipos DECIMAL(12,2)`,
`neto DECIMAL(12,2)` (= `devengado - anticipos`, calculado y persistido al
generar, no una columna generada por Postgres — mismo criterio que
`com_contratos.monto_total`), `pdf_path` nullable, auditoría. `UNIQUE
(planilla_id, persona_id)` — una persona aparece una sola vez por planilla.

### 2. Máquina de estados — `Dominio/EstadoPlanilla.php`, `Dominio/MaquinaEstados/TransicionesPlanilla.php`, `Aplicacion/MaquinaEstados/MaquinaEstadosPlanilla.php`

Dos estados: `Borrador` → `Aprobada`. Mismo patrón exacto que
`EstadoActa`/`TransicionesActa`/`MaquinaEstadosActa`
(`Operaciones/Dominio/MaquinaEstados/TransicionesActa.php`,
`Operaciones/Aplicacion/MaquinaEstados/MaquinaEstadosActa.php`) — leelos
antes de escribir los tuyos, son la referencia directa de esta tarea.
`MaquinaEstadosPlanilla::generar()` crea en `Borrador`;
`MaquinaEstadosPlanilla::aprobar()` valida la transición vía
`TransicionesPlanilla::permitida()` y no hace nada más — las guardas de
NEGOCIO (¿hay algo que aprobar?, ¿quién puede aprobar?) viven en el caso de
uso que la invoca, no acá (invariante 7, mismo reparto que
`GenerarActaTrabajo` frente a `MaquinaEstadosActa`).

### 3. Generación — `Aplicacion/GenerarPlanilla.php`

Recibe un `$periodo` (`YYYY-MM`). Idempotente por regla de negocio: si ya
existe una planilla viva para ese período, la devuelve sin tocarla (mismo
patrón que `GenerarActaTrabajo` con `trabajo_id`), con `lockForUpdate()`
para serializar dos pedidos concurrentes del mismo período.

Para cada persona con al menos un `fin_devengos_personal` en el período:
reusá `Aplicacion/ListarDevengosPersona` para el total devengado (ya suma
con `BigDecimal`, no la reimplementes), sumá sus `fin_anticipos` vivos del
mismo período (mismo cálculo de rango de mes que
`CalcularDisponibleAnticipo::sumarAnticiposDelMes()` — extraelo a un método
compartido si podés reusarlo tal cual, no lo dupliques a mano). `neto =
devengado - anticipos`, todo con `BigDecimal`, nunca floats (invariante 6).
`total` de la planilla = suma de los `neto` de sus detalles.

**El total tiene que cuadrar exacto contra los devengos de origen** (CA
literal): sumá también, por separado, el total de `fin_devengos_personal`
del período completo (todas las personas) y verificá en el test que
`sum(detalles.devengado) === sum(fin_devengos_personal.monto)` del período —
son la misma cifra vista desde dos tablas distintas, tienen que coincidir a
centavo exacto.

### 4. Aprobación — `Aplicacion/AprobarPlanilla.php`

Solo transiciona `Borrador → Aprobada` (guarda: la planilla debe estar en
`Borrador`, si no lanzá una excepción de dominio nueva
`PlanillaNoAprobable`). Gateada por el permiso `finanzas.planilla.aprobar`,
asignado **solo** al rol `dueno` en `SeguridadSeeder.php` — no es una guarda
de persona-contra-persona (invariante 4 es piloto≠validador; acá no hay
"quién generó" que se compare contra "quién aprueba", es simplemente un
permiso que ningún otro rol tiene). Al aprobar: fija `aprobada_por` (usuario
autenticado) y `aprobada_en` (ahora), y genera el PDF de cada detalle (punto
5) dentro de la misma transacción — mismo criterio que
`GenerarActaTrabajo`: si el `Storage::put()` de algún recibo falla, la
aprobación completa se revierte, nunca una planilla aprobada con recibos a
medio generar.

### 5. Recibo individual en PDF — `Aplicacion/GenerarReciboPlanilla.php` (o inline en `AprobarPlanilla`, tu criterio)

Un PDF por fila de `fin_planilla_detalles` (persona, período, devengado,
anticipos, neto) usando `Barryvdh\DomPDF\Facade\Pdf::loadView(...)->output()`
+ `Storage::disk('r2')->put(...)`, mismo patrón exacto que
`Operaciones/Aplicacion/GenerarActaTrabajo.php` (leelo, es la referencia).
Vista PDF nueva en `Infraestructura/Http/Views/pdf/recibo-planilla.blade.php`
(namespace `finanzas::`, ya registrado por `FinanzasServiceProvider`).
Guardá la ruta en `fin_planilla_detalles.pdf_path`.

### 6. HTTP — `Infraestructura/Http/Controllers/Web/PlanillasController.php`

`index` (listado de planillas, con su período/estado/total),
`show` (detalle con los renglones por persona), `store` (dispara
`GenerarPlanilla` para el período pedido — formulario simple con selector de
mes), `aprobar` (dispara `AprobarPlanilla`), y una ruta de descarga del PDF
individual (`GET /panel/planillas/{planilla}/detalles/{detalle}/recibo`,
sirve el archivo de `Storage::disk('r2')`). Tres permisos de grano fino:
`finanzas.planilla.ver`, `.generar`, `.aprobar` — verificados dentro del
controlador contra el rol activo, mismo criterio que `AnticiposController`.

### 7. Vistas, permisos, menú, rutas, copy

Arquetipos Listado (`index`) y Detalle (`show`) del catálogo del panel.
Activá el ítem "planilla" que ya existe como "botón sin link" en
`SecMenuSeeder.php` (viste en la tarea 40/42 que "devengos" estaba en la
misma situación) — no crees uno nuevo, buscalo primero. Permisos nuevos en
`SeguridadSeeder.php`: `.ver`/`.generar` en `PERMISOS_ENCARGADO_OPERACIONES`
(o el rol que ya gestiona devengos/anticipos), `.aprobar` **solo** en
`dueno`. `lang/es/finanzas.php` bloque `planilla`.

## Qué NO hacer

- No dupliques la suma de devengos por período — reusá
  `Aplicacion/ListarDevengosPersona` tal cual.
- No calcules nada con floats — `BigDecimal` en todo el camino, incluida la
  verificación de cuadre exacto.
- No inventes un tercer estado (`rechazada`, `pagada`) — el criterio de
  aceptación es literal `borrador → aprobada`, dos estados. Si el negocio
  necesita más adelante un flujo de pago real, es una HU futura, no la
  ensanches acá.
- No dejes que la aprobación sea alcanzable por cualquier rol con el permiso
  genérico de "encargado" — `finanzas.planilla.aprobar` es exclusivo de
  `dueno`, con test que lo pruebe explícitamente (un encargado con todos los
  demás permisos de planilla pero sin este → 403 al aprobar).
- No generes los PDF antes de que la fila de detalle exista (guardarraíl ya
  usado en `RegistrarEvidencia`/`GenerarActaTrabajo`: nunca I/O de archivo
  antes de persistir).

## Cómo repartir las etapas

- **Etapa 1**: migraciones (2 tablas), modelos, `EstadoPlanilla` +
  `TransicionesPlanilla` + `MaquinaEstadosPlanilla`.
- **Etapa 2**: `GenerarPlanilla` (con el cuadre exacto), `AprobarPlanilla`,
  `PlanillaNoAprobable`.
- **Etapa 3**: `GenerarReciboPlanilla` (PDF + storage), controller, rutas,
  permisos, menú.
- **Etapa 4**: vistas + copy.
- **Etapa 5**: tests Feature + spec visual + checklist §8.

## Criterio de aceptación

- `./bin/verify` = 0, con Playwright.
- Test Feature cubriendo: generar la planilla de un período arma un
  `Borrador` con un detalle por persona con devengos, `neto = devengado -
  anticipos` exacto; el total de la planilla cuadra exacto contra la suma de
  `fin_devengos_personal` del período; generar dos veces el mismo período es
  idempotente (misma fila, no duplica); aprobar transiciona a `Aprobada` y
  genera un PDF por detalle; aprobar una planilla ya aprobada falla
  (`PlanillaNoAprobable`); solo `dueno` puede aprobar (403 para otro rol con
  el resto de permisos de planilla); 403 sin `finanzas.planilla.ver`.
- Spec visual (`tests/Visual/planillas.spec.ts`), claro y oscuro.
- Checklist de `docs/diseno/guia_pantalla_panel.md` §8 pasa.

## Puede tocar

`app/Dominios/Finanzas/**`, migraciones nuevas, `routes/web.php`,
`database/seeders/Catalogo/SeguridadSeeder.php`,
`database/seeders/Catalogo/SecMenuSeeder.php`, `lang/es/finanzas.php`,
`resources/css/pages/planilla.css`, `tests/**`.

Fuera de alcance: cualquier flujo de pago real (transferencia, cheque),
HU-31/HU-32 (Sprint 9, facturación al cliente — es dinero que entra, esta
tarea es dinero que sale).

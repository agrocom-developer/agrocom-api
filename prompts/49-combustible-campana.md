<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/combustible-campana etapas=3 -->

# Tarea 49 — HU-35: combustible del generador y vehículos

## Por qué esta tarea

`plan_sprints.md` Sprint 10 (§220): "Como encargado, quiero registrar el
combustible del generador y de los vehículos, para imputarlo a la
campaña." CA esencial: carga por base y fecha; litros y monto en
`DECIMAL`; consultable por período. Cierra Sprint 10 ("gastos y
rendiciones") — HU-33 (tarea 47) y HU-34 (tarea 48) ya están integradas.

**No es crítica**: ABM de carga administrativa, no un listener que genera
dinero.

**Módulo dueño: `Finanzas`** (`fin_`), junto a `fin_gastos`/
`fin_rendiciones` — mismo criterio de dinero de operación, y ya tiene el
precedente de `base_id` como FK plana a `per_bases`.

## No es lo mismo que `ope_recargas.litros_combustible_generador` — no la toques

`ope_recargas` (tarea 23, HU-13) ya tiene una columna de combustible del
generador, pero es otra cosa: litros sueltos, sin monto, ligados a **una
recarga puntual dentro de una sesión de vuelo**. Su propio docblock en
`SyncController` la describe como "informativo, sin costeo (Fase 3)" — el
costeo real es justamente esta tarea. `fin_combustibles` es una entidad
nueva e independiente: no la leas, no la escribas, no intentes
correlacionarla. Si en algún momento se decide vincular ambas, es una
decisión de negocio futura, no parte del CA esencial de esta HU.

## Recorte respecto de la especificación — mismo criterio que gastos (tarea 47)

`especificacion_funcional_tecnica.md` (~línea 146) describe
`cargas_combustible` con `dron_id`, `vehiculo_id`, `destino
(generador/camioneta)`, `gasto_id`, y un flujo de dos pasos (el auxiliar
registra litros en campo, el encargado carga precio después, se vinculan
más tarde — para separar desvío de precio de mercado de desvío de
consumo real). El CA esencial de `plan_sprints.md` es más chico: una
sola carga, litros y monto, por base y fecha. Recortá así:

- Un solo registro por carga, con litros y monto cargados juntos por el
  encargado desde el panel — **no** el flujo de dos pasos auxiliar/
  encargado (no hay endpoint del motor de sync para esto, y no es del
  CA esencial).
- `destino`: campo `string` con `CHECK IN ('generador', 'vehiculo')` —
  **no** `vehiculo_id` como FK real. No existe módulo `Vehiculo` todavía
  (llega con HU-40, después en la cola). Cuando exista, se agrega por
  `ALTER` igual que `rendicion_id` se agregó a `fin_gastos`.
- Sin `gasto_id` ni vínculo con `fin_gastos` — no está en el CA esencial,
  y no hay dato de "fondos_caja" que lo sostenga.
- Sin comprobante adjunto — a diferencia de `fin_gastos`, esta HU no lo
  pide.

## Qué hacer

Cargá las skills `modelo-datos`, `dominio-backend`, `panel-design-ui` y
`verificacion`.

### 1. Modelo de datos

`fin_combustibles`: `id, fecha DATE, base_id` (FK `per_bases`, entero
plano sin `belongsTo` cross-módulo — mismo criterio que
`Gasto.base_id`), `destino` (`string(20)`, `CHECK IN ('generador',
'vehiculo')`), `litros DECIMAL(10,2)` (`CHECK > 0`), `monto DECIMAL(12,2)`
(`CHECK > 0`, cargado directo — no derivado de `litros × precio`, porque
no hay columna de precio unitario en el CA esencial; documentá esto en
el docblock de la migración), `descripcion` nullable, auditoría, soft
delete. Índices por `base_id`/`fecha`. Mirá la migración de `fin_gastos`
(tarea 47) como plantilla directa de estilo.

### 2. Caso de uso y ABM

`Finanzas/Aplicacion/`: `CrearCombustible` (persiste tal cual, sin
cálculo — litros y monto son ambos datos de entrada), `ListarCombustibles`
(filtro por base y por rango de fecha — "consultable por período" es el
CA literal), `EliminarCombustible` (baja lógica, mismo criterio que
`EliminarGasto`).

### 3. HTTP, permisos, menú

`CombustibleController@index/create/store/destroy`. Permisos
`finanzas.combustible.ver`/`.crear`/`.eliminar` en
`PERMISOS_ENCARGADO_OPERACIONES` (mismo patrón que `finanzas.gasto.*`).
Activá el ítem **`combustible`** ya sembrado como "botón sin link" en el
grupo `financiero` de `SecMenuSeeder.php` (línea ~113) — no crees uno
nuevo.

## Qué NO hacer

- No toques `ope_recargas` ni su campo de combustible del generador.
- No implementes `vehiculo_id` como FK real ni el módulo `Vehiculo`
  (HU-40, todavía sin prompt).
- No implementes el flujo de dos pasos auxiliar/encargado ni ningún
  endpoint del motor de sync para esto.
- No agregues comprobante ni vínculo con `fin_gastos`/`fondos_caja`.

## Cómo repartir las etapas

- **Etapa 1**: migración (`fin_combustibles`), modelo Eloquent.
- **Etapa 2**: casos de uso, controller, rutas, permisos, menú, vistas +
  copy.
- **Etapa 3**: tests Feature + spec visual + checklist §8.

## Criterio de aceptación

- `./bin/verify` = 0, con Playwright.
- Test Feature (`tests/Feature/Finanzas/CombustiblePanelTest.php`)
  cubriendo: carga válida con `destino = generador` y con `destino =
  vehiculo`; `destino` fuera del enum rechazado (422, no 500); `litros`/
  `monto` no positivos rechazados; filtro por base y por rango de fecha
  en el listado; 403 sin permiso; bitácora en alta y baja; baja lógica
  no borra físicamente la fila; ítem de menú publicado y gateado.
- Spec visual (`tests/Visual/combustible.spec.ts`), claro y oscuro.
- Checklist de `docs/diseno/guia_pantalla_panel.md` §8 pasa.

## Puede tocar

`app/Dominios/Finanzas/**`, migración nueva, `routes/web.php`,
`database/seeders/Catalogo/SeguridadSeeder.php`,
`database/seeders/Catalogo/SecMenuSeeder.php`, `lang/es/finanzas.php`,
`tests/**`.

Fuera de alcance: `Operaciones/**` (incluida `ope_recargas`), cualquier
módulo `Vehiculo`/`Mantenimiento`/`Inventario` (Sprint 11, sin prompt
todavía).

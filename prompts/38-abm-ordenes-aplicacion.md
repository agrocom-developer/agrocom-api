<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/ordenes-aplicacion-panel etapas=5 -->

# Tarea 38 — HU-25: órdenes de aplicación desde el panel

## Por qué esta tarea y por qué no es crítica

`plan_sprints.md` Sprint 7 (§176): "Como encargado, quiero crear y seguir las
órdenes de aplicación desde el panel, para que el piloto las reciba en el
pull de catálogo." Es la última y más compleja de las cuatro HU que quedan
del sprint (33-35 ya establecieron el patrón ABM simple; 36/37, si ya están
integradas, hicieron lo mismo sin máquina de estados) — esta es la primera
del sprint con una máquina de estados propia.

**No es `critica=si`.** La lista de "qué no delegar sin revisión línea por
línea" de `CLAUDE.md` nombra "el servicio de estados" en singular: el gate
genérico de la invariante 7 (`tests/Unit/TransicionesEstadoTest.php`, tarea
04), que ya existe y ya corre en `bin/verify`. Implementar una máquina de
estados de dominio puntual que pasa por ese servicio **no es lo que la lista
protege** — mismo criterio que la tarea 34 (HU-23, contratos con su propia
máquina de estados `borrador/vigente/finalizado/cancelado`), que quedó
`critica=no` en `cola_tareas.md` fila 34 y se revisó por diff y test en el
PR, sin borrador. Tomá esa tarea como precedente si dudás.

**El modelo de datos y el enum ya existen, no los toques**:
`app/Dominios/Operaciones/Infraestructura/Eloquent/OrdenAplicacion.php` y
`app/Dominios/Operaciones/Dominio/EstadoOrdenAplicacion.php` (4 casos:
`Emitida`, `Vigente`, `Consumida`, `Vencida`). Ambos docblocks dicen
explícito que las transiciones "pasarán por el servicio de dominio de la
máquina de estados cuando se implemente" — sos vos quien la implementa.

**El caso de uso de lectura ya existe y está pensado para esto**:
`app/Dominios/Operaciones/Aplicacion/ListarOrdenesAplicacion.php`, cuyo
propio docblock dice "lo invocan tanto el controller de API como, cuando
exista, el listado del panel". **Reutilizalo para el `index` del panel — no
crees uno nuevo.**

**`GET /api/sync/catalogo` ya incluye órdenes vigentes automáticamente.**
`LecturaOrdenesVigentesEloquent` filtra `WHERE estado = 'vigente'` directo de
la tabla, sin caché — una orden que tu ABM deje en `vigente` aparece en el
próximo pull sin que sumes nada al motor de sync. No toques
`Sincronizacion/**` ni `EscrituraSincronizacionEloquent`.

## Qué hacer

Cargá las skills `dominio-backend`, `modelo-datos`, `panel-design-ui` y
`verificacion`. Leé `docs/diseno/guia_pantalla_panel.md` (arquetipo Listado
§6.2, Formulario §6.3) y `app/Dominios/Comercial/Aplicacion/MaquinaEstados/
MaquinaEstadosContrato.php` + `app/Dominios/Comercial/Dominio/MaquinaEstados/
TransicionesContrato.php` como patrón exacto a seguir (misma estructura:
clase `final`, tabla de transiciones separada, excepción con factory
estático `::entre()`).

### 1. Máquina de estados — `app/Dominios/Operaciones/Dominio/MaquinaEstados/` y `Aplicacion/MaquinaEstados/`

- `TransicionesOrden`: tabla de transiciones permitidas. **Solo `emitida →
  vigente`.** `consumida` y `vencida` no tienen ningún disparador en el
  dominio hoy — ninguna otra tarea los setea, no hay evento ni cierre de
  trabajo que los dispare todavía (verificado: `grep` de "consumida"/
  "vencida" en `app/` no devuelve más que el enum y los docblocks). No
  inventes cuándo ocurren ni agregues transiciones hacia ellos — es trabajo
  de una tarea futura, cuando exista la regla de negocio que las dispare.
- `MaquinaEstadosOrden` (`final`): `crear()` fija `estado = Emitida`.
  `activar()` transiciona `emitida → vigente`. La guarda de "una única orden
  vigente por lote" **ya está en el índice parcial de la base**
  (`ope_ordenes_aplicacion_lote_vigente_unico`) — no la dupliques en PHP,
  atrapá la violación (`QueryException`) en el caso de uso y traducila a un
  error de validación legible, mismo patrón que el nombre/código duplicado de
  clientes/campos.
- Excepción `TransicionOrdenNoPermitida` (mismo patrón que
  `TransicionContratoNoPermitida`).

### 2. Capa de aplicación — `app/Dominios/Operaciones/Aplicacion/`

- `CrearOrden`: usa `MaquinaEstadosOrden::crear()`.
- `ActivarOrden`: usa `MaquinaEstadosOrden::activar()`.
- `ActualizarOrden`: **decidí y documentá** (docblock del caso de uso) si una
  orden se puede editar una vez `vigente`. Es razonable que no — una vez
  vigente puede estar ya en el pull de catálogo de la app y el piloto
  operando sobre esos parámetros — pero es tu decisión, no una regla ya
  escrita. Si la restringís, hacelo en el caso de uso (no solo ocultando el
  link en la vista) y cubrilo con test.
- `EliminarOrden`: soft delete (invariante 8). Mismo criterio que arriba:
  decidí si una orden `vigente` se puede eliminar sin pasar antes por un
  estado terminal, documentalo, testealo.
- Reutilizá `ListarOrdenesAplicacion` para el listado del panel — no
  dupliques su lógica de filtros.

### 3. HTTP — `app/Dominios/Operaciones/Infraestructura/Http/`

- `Controllers/Web/OrdenesController.php`: `index`, `create`, `store`,
  `edit`, `update`, `destroy`, `activar`. Permiso vía `AutorizacionPanelWeb`,
  sin cálculo de negocio en el controller.
- `Requests/CrearOrdenRequest.php`/`ActualizarOrdenRequest.php`: `contrato_id`
  requerido + `exists:com_contratos,id`; `lote_id` requerido + `exists:
  com_lotes,id`; `nro_aplicacion` entero `>= 1`; `litros_ha` numérico `> 0`;
  límites (`humedad_min_pct`, `humedad_max_pct`, `viento_max_kmh`,
  `temperatura_max_c`, `velocidad_max_kmh`) opcionales con sus rangos (mismo
  CHECK que la migración: humedad 0-100, `humedad_min <= humedad_max` cuando
  ambos vienen); parámetros de vuelo (`altura_vuelo_m`, `velocidad_vuelo_kmh`,
  `ancho_pasada_m`) opcionales `> 0`; `emitida_por_contacto_id` opcional +
  `exists:com_cliente_contactos,id`; `fecha_emision` requerida, fecha válida.
  Validar contra `com_contratos`/`com_lotes`/`com_cliente_contactos` por
  `exists:` (consulta directa a la tabla, sin importar el modelo Eloquent de
  `Comercial` — respeta ADR 0003 regla 3 igual que el resto del módulo) es
  aceptable para existencia; si además querés exigir que el contrato esté
  `vigente` (lo que sugiere la redacción de la HU: "para que las órdenes
  cuelguen de un contrato vigente" es en realidad el criterio de HU-23, no
  de esta — no lo asumas como obligatorio), documentá esa decisión también.
- Vistas en `Infraestructura/Http/Views/pages/ordenes/`: `index.blade.php`
  (arquetipo Listado, badge de estado, botón "Activar" visible solo si
  `estado = emitida` y el usuario tiene `operaciones.orden.activar`),
  `create.blade.php`/`edit.blade.php` (arquetipo Formulario con `<select>`
  nativo para `contrato_id`/`lote_id`/`emitida_por_contacto_id` — no hay
  átomo `select`, no inventes uno).

### 4. Rutas — `routes/web.php`

Mismo grupo `auth:interno` → `rol.activo`. Nombres `panel.ordenes.index`,
`.create`, `.store`, `.edit`, `.update`, `.destroy`, y
`panel.ordenes.activar` (`POST /panel/ordenes/{orden}/activar`, mismo patrón
que `panel.contratos.cambiar-estado`).

### 5. Permisos — `database/seeders/Catalogo/SeguridadSeeder.php`

```
operaciones.orden.ver
operaciones.orden.crear
operaciones.orden.editar
operaciones.orden.activar
operaciones.orden.eliminar
```

`.activar` separado de `.editar` — mismo criterio que
`comercial.contrato.cambiar_estado` separado de `.editar`. Sumalos a
`PERMISOS_ENCARGADO_OPERACIONES`.

### 6. Menú — `database/seeders/Catalogo/SecMenuSeeder.php`

El ítem `operacion.ordenes` ya está sembrado como botón sin link (línea 61:
`$this->item($operacion, 'operacion', 'ordenes', 'assignment', 2)`).
Activalo con `ruta: 'panel.ordenes.index', codigoPermiso:
'operaciones.orden.ver'`.

### 7. Copy — `lang/es/operaciones.php`

Ya existe — sumale las claves de esta pantalla.

## Qué NO hacer

- No implementes transiciones hacia `consumida`/`vencida` — no tienen
  disparador definido, documentá por qué quedan afuera en el docblock de
  `TransicionesOrden`.
- No dupliques en PHP la unicidad de "una vigente por lote" — el índice
  parcial de la base ya la garantiza, solo atrapá la violación.
- No toques `EstadoOrdenAplicacion`, la migración de `ope_ordenes_aplicacion`,
  `EscrituraSincronizacionEloquent` ni nada de `Sincronizacion/**`.
- No agregues relaciones Eloquent desde `OrdenAplicacion` hacia modelos de
  `Comercial` — solo FK por ID, mismo criterio que el resto del módulo.

## Cómo repartir las etapas

- **Etapa 1**: `TransicionesOrden` + `MaquinaEstadosOrden` + excepción;
  `Aplicacion/` (`CrearOrden`, `ActivarOrden`, `ActualizarOrden`,
  `EliminarOrden`).
- **Etapa 2**: `Infraestructura/Http` (controller, requests, rutas),
  permisos, menú.
- **Etapa 3**: las tres vistas sobre el arquetipo, `lang/es/operaciones.php`.
- **Etapa 4**: tests Feature (ver criterio de aceptación).
- **Etapa 5**: margen — spec visual, checklist §8, verificación en navegador
  en los dos temas.

## Criterio de aceptación

- `./bin/verify` = 0, con la etapa de Playwright.
- Test Feature (`tests/Feature/Operaciones/GestionOrdenesPanelTest.php` o
  similar) que cubra:
  - Alta de una orden → persiste en `emitida`.
  - `activar()` sobre una orden `emitida` → pasa a `vigente`; sobre una
    orden que no está `emitida` → `TransicionOrdenNoPermitida`, sin mutar el
    estado (mismo patrón de test que
    `tests/Unit/TransicionesEstadoTest.php`).
  - Dos órdenes `vigente` para el mismo lote → la segunda activación falla
    como error de validación legible, no `QueryException` cruda.
  - La orden queda visible en `GET /api/sync/catalogo` una vez `vigente`, no
    antes (reusa el patrón de test de la tarea 08/TE-06).
  - `litros_ha <= 0`, `nro_aplicacion < 1`, o `humedad_min_pct >
    humedad_max_pct` → error de validación, no persiste.
  - Bitácora de alta, edición y baja.
  - Baja: soft delete, no aparece en `index`, 404 si se reintenta.
  - 403 para un rol sin el permiso correspondiente; permiso en un rol
    no-activo no alcanza; `operaciones.orden.activar` separado de `.editar`
    (un rol con uno y no el otro no puede hacer la acción que no tiene).
  - El ítem de menú "Órdenes" queda gateado por `operaciones.orden.ver`.
- Spec visual nuevo (`tests/Visual/ordenes.spec.ts`, mismo patrón que
  `contratos.spec.ts`), claro y oscuro.
- Checklist de `docs/diseno/guia_pantalla_panel.md` §8 pasa.

## Puede tocar

`app/Dominios/Operaciones/**` (nuevo `Dominio/MaquinaEstados/`,
`Aplicacion/MaquinaEstados/`, casos de uso de órdenes, `Infraestructura/Http/`
de órdenes — no toques trabajos/sesiones/alertas/actas/reportes/drones
existentes), `routes/web.php`,
`database/seeders/Catalogo/SeguridadSeeder.php`,
`database/seeders/Catalogo/SecMenuSeeder.php`, `lang/es/operaciones.php`,
`resources/css/pages/ordenes.css` (si hace falta), `tests/**`.

Fuera de alcance: `Sincronizacion/**`, `EscrituraSincronizacionEloquent`,
cambios al esquema de `ope_ordenes_aplicacion`, transiciones hacia
`consumida`/`vencida`, cualquier otra HU de Sprint 7.

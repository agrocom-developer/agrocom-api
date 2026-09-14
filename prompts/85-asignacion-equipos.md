<!-- ciclo: critica=si turno-noche=1 descongela=tests rama=feature/asignacion-equipos etapas=5 -->

# Tarea 85 — HU-70: asignación de equipos de trabajo a una orden de aplicación

## Por qué esta tarea

Reclamo activo del dueño, audio 1 del 13/9/2026 (`docs/negocio/observaciones_operaciones_comercial_2026-09-13.md`,
fila HU-70): *"dónde asignarle el trabajo al piloto"* — hoy el jefe de campo se
entera de qué le toca a cada equipo por WhatsApp. El Word suma el ejemplo que
fija la cardinalidad: una orden de 500 ha puede repartirse Equipo 1 → 300 ha,
Equipo 2 → 200 ha. Depende de la tarea 72 (`per_equipos_trabajo`,
`LecturaEquipoTrabajo`), ya integrada.

Es la primera del Sprint 16 — el propio plan la ordena antes que el resto
porque varias observaciones del Word cuelgan de esta misma cardinalidad
orden↔trabajo.

## Lo que ya existe

- `ope_ordenes_aplicacion`: una orden referencia **un solo** `lote_id`
  (`Operaciones/Infraestructura/Eloquent/OrdenAplicacion.php`). El reparto de
  HU-70 divide las **hectáreas de ese lote** entre equipos, no reparte lotes
  distintos — el `lote_id` de cada `Trabajo` generado es siempre el de la
  orden.
- `ope_trabajos`: **hoy nace solo por sync**, con `uuid_cliente` generado en
  el dispositivo (`EscrituraSincronizacionEloquent::abrirTrabajo()` →
  `MaquinaEstadosTrabajo::abrir()`), `inicio` `dateTime` `NOT NULL`. Tarea 12
  ya rechaza abrir un trabajo si la orden no existe, no está `vigente`, o el
  `lote_id` no coincide — mismo criterio que necesita esta tarea.
  `hectareas_declaradas` no tiene UNIQUE ni CHECK de tope: la tarea 12 la
  valida como numérica, nada más.
- `Personal/Contratos/LecturaEquipoTrabajo::vigentesAFecha()` (tarea 72) ya
  expone los equipos vigentes de Personal. **Consumilo por ahí, nunca leas
  `per_equipos_trabajo` directo** (ADR 0003 regla 2). La tarea 72 dejó
  `equipo_trabajo_id` fuera de `ope_sesiones` a propósito y con nota explícita
  reservándolo para esta tarea.
- `GET /api/sync/catalogo` (`Sincronizacion/Aplicacion/ObtenerCatalogoDesdeCursor.php`)
  hoy arma tres secciones (`ordenes`, `lotes`, `personas`) desde
  `CursorCatalogo::SECCIONES`, cada una con su propio contrato de lectura
  inyectado. No existe sección `trabajos` — la agregás vos, mismo molde que
  `LecturaOrdenesVigentes`/`OrdenAplicacionCatalogo`.

## La decisión de diseño que esta tarea tiene que tomar y documentar

Hoy el `Trabajo` nace **exclusivamente** por sync, con un `uuid_cliente` que
trae el dispositivo (invariante 1: idempotencia de lo que "nace en la app de
campo"). HU-70 pide que nazca **antes**, desde el panel, cuando el jefe
confirma la asignación — sin que el piloto haya tocado el dispositivo
todavía.

Un `Trabajo` creado por el panel **no nace en la app de campo**: la
invariante 1 no le exige un UUID de dispositivo. Generá el suyo propio en el
servidor (`Str::uuid()`) al confirmar la asignación — sigue siendo único,
sigue siendo el valor que la app va a usar después para abrir sesiones sobre
ese trabajo (`abrirSesion` resuelve el trabajo por `trabajo_uuid_cliente`;
por eso el `uuid_cliente` generado en el panel tiene que viajar en la
sección `trabajos` del catálogo, para que la app lo lea y lo use tal cual).

Para `inicio` (`dateTime NOT NULL`, hoy es "cuándo abrió el dispositivo"):
documentá en el docblock de la migración o del caso de uso qué significa
para un trabajo pre-creado (razonable: el instante de la confirmación —
"trabajo abierto en el sistema desde acá", no "vuelo iniciado"; las horas
reales de vuelo siguen viniendo de las sesiones). Es tu decisión, pero
escribí el porqué — no lo dejes implícito.

No toques `abrirTrabajo()`/`MaquinaEstadosTrabajo::abrir()` para el flujo
viejo: seguí soportando que un trabajo nazca por sync (compatibilidad con lo
que ya está integrado). Sumá el camino nuevo sin romper el existente.

## Qué hacer

Cargá los skills `verificacion`, `dominio-backend`, `modelo-datos` y
`flujo-git-pr`.

1. **Migración** `ALTER ope_trabajos`: agregá `equipo_trabajo_id`
   (`foreignId(...)->nullable()->constrained('per_equipos_trabajo')->restrictOnDelete()`,
   mismo patrón de FK real sin `belongsTo` cruzado que ya usa `lote_id` —
   nullable porque los trabajos viejos y los que abre el dispositivo
   directamente no llevan equipo). Documentá en el docblock por qué nace
   nullable.
2. **Caso de uso** `Operaciones/Aplicacion/AsignarEquiposOrden.php` (o el
   nombre que seas coherente al elegir): recibe la orden y una lista
   `[{equipo_trabajo_id, hectareas}]`. Guardas, en este orden:
   - la orden existe y está `Vigente` (mismo criterio que la tarea 12);
   - cada `equipo_trabajo_id` está en `LecturaEquipoTrabajo::vigentesAFecha(hoy)`;
   - `SUM(hectareas ya asignadas a esa orden, trabajos no eliminados) + SUM(hectareas nuevas) ≤ com_lotes.hectareas` del lote de la orden (leído por el contrato de lectura de `Comercial` que ya use el módulo, no un `DB::table`).
   Si pasa, crea un `Trabajo` **por equipo** en una transacción: `orden_id`,
   `lote_id` (el de la orden), `nro_aplicacion` (el de la orden),
   `hectareas_declaradas` (la asignada), `equipo_trabajo_id`, `uuid_cliente`
   propio, estado `Abierto` vía `MaquinaEstadosTrabajo` (agregale el método
   que haga falta, no dupliques la lógica de `abrir()`).
3. **Pantalla del panel**: sobre la ficha de la orden (o una ficha propia de
   asignación — vos decidís, documentá el porqué si te apartás de la ficha de
   orden existente), listado de equipos vigentes con hectáreas a asignar,
   contador de hectáreas restantes del lote, confirmación que dispara el caso
   de uso. Permiso nuevo `operaciones.orden.asignar_equipos` (mismo patrón
   que las filas ya sembradas en `SeguridadSeeder`), sin romper los permisos
   existentes de orden.
4. **Contrato de lectura nuevo** `Operaciones/Contratos/LecturaTrabajosAsignados`
   (mismo molde que `LecturaOrdenesVigentes`/`OrdenAplicacionCatalogo`):
   trabajos con `equipo_trabajo_id` no nulo, `listarModificadosDesde(cursor,
   limite)`, DTO con `uuid_cliente`, `orden_id`, `lote_id`,
   `hectareas_declaradas`, `equipo_trabajo_id`, `updated_at`, `id`.
5. **`CursorCatalogo`**: sumá `TRABAJOS = 'trabajos'` a `SECCIONES`.
   **`ObtenerCatalogoDesdeCursor`**: inyectá el contrato nuevo, agregá la
   sección `trabajos` al array de salida con el mismo patrón de avance de
   cursor que `ordenes`/`lotes`/`personas`.
6. **`docs/api/openapi.yaml`**: documentá la sección `trabajos` nueva de
   `GET /api/sync/catalogo` (con `equipo_trabajo_id` y `uuid_cliente`).

## Qué NO hacer

- No agregues `equipo_trabajo_id` a `ope_sesiones` ni a ningún otro registro
  del motor de sync — la tarea 72 ya dejó escrito por qué no hace falta
  (el gasto/combustible/estadía lo llevan explícito, nunca lo deducen).
- No conviertas la orden en multi-lote. El `lote_id` de cada `Trabajo`
  generado es siempre el de la orden — lo que se reparte son hectáreas, no
  lotes.
- No implementes "editar/eliminar trabajo validado": es el punto 4 de
  `observaciones_operaciones_comercial_2026-09-13.md` §4, sigue ambiguo y
  sin HU. Si tu pantalla necesita corregir una asignación antes de que el
  piloto la toque, limitalo a soft-delete de un `Trabajo` **sin sesiones**
  (invariante 2 y 8) — nada sobre uno que ya tiene una sesión abierta o
  validada.
- No toques `MaquinaEstadosOrden`/`TransicionesOrden` — la orden sigue
  pasando a `vigente` exactamente igual que hoy.
- No implementes HU-79 (tipo sólido/líquido de la orden): es la tarea 95 y
  depende de que esta esté integrada para no iterar dos veces sobre
  `ope_ordenes_aplicacion`.

## Cómo repartir las etapas

- **Etapa 1**: migración `equipo_trabajo_id`, contrato `LecturaEquipoTrabajo`
  ya inyectable en `Operaciones`, tests unitarios de la guarda de suma de
  hectáreas.
- **Etapa 2**: `AsignarEquiposOrden` completo con sus tres guardas y creación
  transaccional de los `Trabajo`, tests Feature.
- **Etapa 3**: pantalla del panel (listado + confirmación), permiso, menú si
  corresponde.
- **Etapa 4**: contrato `LecturaTrabajosAsignados`, sección `trabajos` en el
  catálogo, `CursorCatalogo` actualizado, tests de sincronización de cursor.
- **Etapa 5**: `openapi.yaml`, `bin/verify` de punta a punta, snapshots si la
  pantalla nueva los necesita.

## Criterio de aceptación

- `./bin/verify` = 0.
- Test: asignar 300 ha + 200 ha a un lote de 500 ha en dos equipos distintos
  se acepta y crea dos `Trabajo` con su `equipo_trabajo_id` cada uno.
- Test: una tercera asignación que llevaría la suma por encima de las
  hectáreas del lote se rechaza sin crear nada.
- Test: asignar un `equipo_trabajo_id` que no está vigente hoy se rechaza.
- Test: asignar equipos a una orden que no está `vigente` se rechaza.
- Test: el trabajo generado aparece en `GET /api/sync/catalogo` (sección
  `trabajos`) con su `uuid_cliente` propio y su `equipo_trabajo_id`, y un
  segundo pull con el cursor devuelto no lo repite.

## Puede tocar

`app/Dominios/Operaciones/**`, `app/Dominios/Personal/Contratos/**` (solo
lectura, sin escribir `per_equipos_trabajo`), `app/Dominios/Sincronizacion/**`,
`app/Dominios/Seguridad/**` (solo `SeguridadSeeder`/`SecMenuSeeder`),
migración nueva, `routes/web.php`, `lang/es/operaciones.php`,
`docs/api/openapi.yaml`, `tests/**`.

## Cierre obligatorio de cada etapa

`runs/85.estado` (`PARCIAL`/`OK`/`BLOQUEADA`), `runs/85.md` (qué falta,
concreto), y al `OK` `runs/85.pr.md`. Commits agrupados por función, español,
imperativo, sin `Co-Authored-By`.

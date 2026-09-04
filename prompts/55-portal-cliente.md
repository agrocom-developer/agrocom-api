<!-- ciclo: critica=si turno-noche=1 descongela=tests,decisiones rama=feature/portal-cliente etapas=4 -->

# Tarea 55 — HU-41: portal del cliente

## Por qué esta tarea

`plan_sprints.md` Sprint 12 (§250), primera HU del sprint: "Como cliente,
quiero entrar al portal y ver solo mis reportes, actas y avance, para
verificar sin llamar a nadie." Criterio esencial: todo consultado desde el
`contrato` del usuario autenticado (invariante 5 de CLAUDE.md); test
obligatorio en cada endpoint nuevo: cliente A pide un recurso de cliente B →
404.

Sprint 11 (tareas 50-54) y la tarea 32 (bug de LSP de `atoms/input`) ya
están integrados — este es el primer ítem de Sprint 12, el que abre "lo que
ve el cliente".

**Es crítica**: CLAUDE.md nombra literal "el scoping del portal del
cliente" en la lista de lo que no se delega sin revisión línea por línea.
El PR se abre en borrador.

## Lo que ya existe — no lo redescubras

- Guard `cliente` y modelo `SecUsuarioCliente` (scope `type = 'cliente'`) ya
  declarados en `config/auth.php` y
  `app/Dominios/Seguridad/Infraestructura/Eloquent/SecUsuarioCliente.php`
  desde HU-01, sin consumidores todavía — es tu punto de partida.
- `sec_user.contrato_id` (FK a `com_contratos`, nullable) con el CHECK de
  exclusión mutua contra `persona_id` ya existe.
- `database/factories/SecUserFactory.php` ya acepta `type`/`contrato_id`
  para crear un usuario de portal en tests.
- **ADR 0002, punto 6**: "El portal del cliente reutiliza el mismo layout
  con un guard de autenticación separado, sin acceso a los menús internos."
  No inventes un layout nuevo — reusá el layout AdminLTE existente, sin la
  navegación de `sec_menu` (que no aplica a cuentas `cliente`, no tienen
  rol).
- **ADR 0008**: el portal vive en `routes/web.php`, con sesión, igual que el
  panel — nunca en `routes/api.php`. La especificación (§13) nombra los
  endpoints como `GET /api/portal/...`; es solo cómo la espec de negocio
  nombra recursos, no un mandato de infraestructura. No los pongas en
  `routes/api.php`: ese archivo es exclusivo de las apps de campo con
  Sanctum (ADR 0008), y compartir guard/middleware entre los dos archivos
  de rutas está prohibido.
- Precedente de agregación liviana cross-módulo, ya integrado:
  `Comercial/Aplicacion/ObtenerAvanceComercial.php` (HU-32, tarea 46) ya
  acepta `?contratoId` — **reusalo tal cual** para la parte de "avance"
  (hectáreas contratadas/aplicadas/facturadas). No lo reescribas.
- Precedente de contrato de lectura cross-módulo:
  `Operaciones/Contratos/LecturaActaConformada.php` +
  `Operaciones/Infraestructura/LecturaActaConformadaEloquent.php` (resuelve
  `Acta.trabajo_id → Trabajo.orden_id → OrdenAplicacion.contrato_id` con
  consultas simples, sin JOIN optimizado — "el volumen no lo justifica").
  Es el molde para todo lo que sigue.

## Qué hacer

Cargá las skills `seguridad-roles`, `dominio-backend`, `panel-design-ui` y
`verificacion`. Leé ADR 0002 (punto 6), ADR 0003, ADR 0004, ADR 0008,
ADR 0012 y `especificacion_funcional_tecnica.md` §13 antes de tocar código.

### 1. Login/logout del portal

En `Seguridad` (dueño de `SecUsuarioCliente` y el guard `cliente`, ADR
0004): un controlador nuevo (p. ej. `SesionPortalController`, junto a
`SesionController.php`) que autentica contra `Auth::guard('cliente')`, sin
selección de rol (a diferencia del panel interno, una cuenta de portal no
tiene `sec_user_role`). Rutas `GET/POST /portal/login`,
`POST /portal/logout`, guard `cliente`. No reuses `IniciarSesionPanel`: esa
lógica resuelve rol activo, que no existe del lado del portal — un
controlador delgado que llama `Auth::guard('cliente')->attempt(...)` alcanza,
sin inventar un caso de uso para algo que no tiene regla de negocio propia.

### 2. Contrato de autorización del portal

Nueva interfaz en `Seguridad/Contratos/` (p. ej.
`AutorizacionPortalCliente`), mismo espíritu que `AutorizacionPanelWeb` pero
para el guard `cliente`: `contratoId(Request $request): ?int` (resuelto de
`Auth::guard('cliente')->user()->contrato_id`) y una `cascara()` mínima
(tema/chrome, sin menú — no hay `sec_menu` que listar para esta cuenta). El
consumidor de `Portal` nunca importa `SecUser`/`SecUsuarioCliente`
directo — mismo criterio que el panel interno (ADR 0003, regla 2).

### 3. Extender `LecturaActaConformada` con filtro por contrato

Agregá un método a la interfaz existente (p. ej.
`listarFirmadasPorContrato(int $contratoId): array`) — **no dupliques la
interfaz**, ampliala. Implementalo en `LecturaActaConformadaEloquent`
resolviendo qué `trabajo_id` pertenecen a ese contrato (vía
`OrdenAplicacion.contrato_id`) antes de filtrar `Acta` por `estado =
Firmada` — la consulta nace del contrato, nunca "todas las actas con un
`where` después" (invariante 5, aunque acá el consumidor sea interno del
servidor, no HTTP directo).

### 4. Contrato nuevo: reportes técnicos por contrato

No existe hoy ningún contrato de lectura sobre `ope_reportes_tecnicos`.
Creá `Operaciones/Contratos/LecturaReporteTecnico` (+ su DTO
`DatosReporteTecnico`: `reporteId`, `trabajoId`, `contratoId`, `loteId`,
`horaInicio`, `horaFin`, `pdfPath`, `generadoEn`) y su implementación
Eloquent, mismo molde exacto que `LecturaActaConformadaEloquent`
(`ReporteTecnico.trabajo_id → Trabajo.orden_id/lote_id →
OrdenAplicacion.contrato_id`). Método `listarPorContrato(int $contratoId):
array`. Bindealo en `OperacionesServiceProvider`, al lado del bind
existente de `LecturaActaConformada`.

### 5. Módulo `Portal`

`ADR 0003` ya lista `Portal` como módulo de dominio propio, sin tabla
propia (solo lectura). Creá `app/Dominios/Portal/Infraestructura/Http/Controllers/`
con controladores de solo lectura (mismo patrón `Controller + Blade` que
`FacturasController`/`DevengosController` — **no** introduzcas Livewire acá:
nada en el panel lo usa hoy pese a que ADR 0002 lo mencione en el nombre,
así que no seas la primera pantalla que lo hace sin necesidad real, el
portal es puro de lectura). Tres pantallas mínimas, todas resolviendo
`contratoId` desde `AutorizacionPortalCliente` ANTES de cualquier consulta
(nunca un id que llegue por parámetro de ruta sin verificar contra el
usuario autenticado):

- **Avance**: `Comercial\Aplicacion\ObtenerAvanceComercial->ejecutar(contratoId: $contratoId)`.
- **Actas firmadas**: `LecturaActaConformada::listarFirmadasPorContrato($contratoId)`
  (punto 3), con descarga de PDF — mismo patrón de streaming que
  `TrabajosController::actaPdf` (`Storage::disk('r2')->get($acta->pdfPath)`,
  nunca `Storage::url()`: pese al nombre de columna, `archivo_url`/`pdf_path`
  son rutas privadas del disco `r2`, no URLs públicas). La ruta de descarga
  debe volver a resolver `contratoId` del usuario autenticado y comparar
  contra el `contratoId` del acta pedida — 404 si no coincide, incluso si el
  `id` del acta existe.
- **Reportes técnicos**: `LecturaReporteTecnico::listarPorContrato($contratoId)`
  (punto 4), con descarga de PDF, mismo criterio de 404 cruzado.

Rutas bajo `/portal/*` (`/portal/avance`, `/portal/actas`,
`/portal/actas/{acta}/pdf`, `/portal/reportes`,
`/portal/reportes/{reporte}/pdf`), todas `middleware('auth:cliente')`. Sin
permisos de grano fino tipo `sec_permission`: una cuenta de portal no tiene
rol, el único gate es el guard + el `contratoId` resuelto.

### 6. Nota en ADR 0012 (deuda documental que esta tarea deja escrita)

`ADR 0012` (lecturas complejas → vistas SQL `vw_*`) lista "armar reportes en
PHP hidratando agregados Eloquent" como alternativa **descartada**. HU-32
(tarea 46, ya integrada) ya hizo exactamente eso, y esta tarea repite el
patrón dos veces más (puntos 3 y 4) sin que nadie lo haya dejado escrito.
Agregá un párrafo corto a la sección "Consecuencias" del ADR (no reescribas
la Decisión) documentando que la agregación liviana vía contratos de lectura
es el patrón aceptado para reportes de bajo volumen (avance por contrato,
actas/reportes por contrato) — `vw_*`/vistas materializadas quedan
reservadas para lecturas realmente pesadas (dashboards agregados sobre toda
la base), como el propio ADR ya insinúa ("vistas materializadas cuando el
costo lo justifique"). Es la razón del `descongela=decisiones` de esta
tarea: **solo** ese párrafo, no toques la Decisión ni las Alternativas.

## Qué NO hacer

- No crees vistas SQL (`vw_*`) ni modelos Eloquent de solo lectura sobre
  vistas — ver punto 6, es deuda documentada, no deuda resuelta con más
  infraestructura en esta tarea.
- No le des a una cuenta de portal ningún `sec_role`/`sec_permission`: el
  aislamiento es guard + `contrato_id`, no el sistema de permisos del panel
  interno.
- No reuses `AutorizacionPanelWeb` ni `IniciarSesionPanel` para el guard
  `cliente` — son del guard `interno`, con selección de rol activo que acá
  no existe.
- No pongas ninguna ruta de portal en `routes/api.php`.
- No muestres costos, márgenes, personal, gastos ni inventario en ninguna
  pantalla del portal (espec §13, "no ve"): solo reportes técnicos, actas,
  avance comercial (hectáreas + monto facturado, sin desglose de costo).
- No toques `ObtenerAvanceComercial` más que para llamarlo — ya acepta el
  filtro que necesitás.

## Cómo repartir las etapas

- **Etapa 1**: login/logout del portal (guard `cliente`), contrato
  `AutorizacionPortalCliente`, tests de login/logout y de que el guard
  `interno` no puede autenticar acá ni viceversa.
- **Etapa 2**: extender `LecturaActaConformada` (punto 3) y crear
  `LecturaReporteTecnico` (punto 4), con tests unitarios de la resolución
  de `contratoId`.
- **Etapa 3**: controladores/vistas de `Portal` (avance, actas, reportes,
  descargas), rutas, tests Feature con el caso cliente A → recurso de
  cliente B → 404 en CADA endpoint nuevo.
- **Etapa 4**: nota de ADR 0012, spec visual (claro/oscuro), checklist
  `guia_pantalla_panel.md` §8, `bin/verify` completo.

## Criterio de aceptación

- `./bin/verify` = 0, con Playwright.
- Test explícito, para cada endpoint del portal (avance, actas, descarga de
  acta, reportes, descarga de reporte): cliente A autenticado pidiendo un
  recurso de cliente B → 404 (invariante 5, literal del CA de HU-41).
- Test de que las tres pantallas devuelven, para el cliente correcto,
  exactamente los datos de SU contrato (avance con las tres magnitudes,
  actas firmadas, reportes técnicos).
- Test de login/logout del guard `cliente`, y de que una cuenta `interno`
  no autentica contra `auth:cliente` (ni al revés).
- Spec visual nueva (`tests/Visual/portal-*.spec.ts`), claro y oscuro.
- Checklist de `docs/diseno/guia_pantalla_panel.md` §8 pasa.

## Puede tocar

`app/Dominios/Seguridad/**` (login/logout/contrato del portal), módulo
nuevo `app/Dominios/Portal/**`, `app/Dominios/Operaciones/Contratos/**` +
su implementación (el contrato nuevo y la extensión del existente),
`app/Dominios/Operaciones/Infraestructura/OperacionesServiceProvider.php`,
`routes/web.php`, `lang/es/portal.php` (nuevo), `resources/views/portal/**`
si hace falta un layout propio mínimo, `tests/**`,
`docs/decisiones/0012-lecturas-complejas-vistas-sql-eloquent.md` (solo el
párrafo del punto 6).

Fuera de alcance: `Comercial/Aplicacion/ObtenerAvanceComercial.php` (solo
se consume), cualquier tabla/migración nueva (el portal no escribe nada,
ADR 0011), `sec_menu`/`sec_permission` del panel interno.

# ADR 0007 — Borrado lógico (soft delete) y bitácora de auditoría transversal

**Estado:** Aceptada.

## Contexto

El sistema es, por diseño, un registro de eventos operativos con consecuencias financieras (hectáreas, devengos, gastos, movimientos de stock). Perder un registro por un `DELETE` físico, o no saber quién cambió qué y cuándo, rompe exactamente el activo que el sistema existe para proteger: la trazabilidad (`docs/especificacion/especificacion_funcional_tecnica.md`, sección 6). Esto es más estricto que la inmutabilidad ya definida para los modelos operativos validados (sección 5 de la especificación): acá se trata de **toda** la base — incluyendo catálogos y datos maestros (clientes, productos, personas, drones) — no solo de sesiones y trabajos validados.

## Decisión

**Dos mecanismos transversales, aplicados a todos los módulos (`Compartido/`, ver ADR 0003):**

1. **Soft delete por defecto en todo modelo Eloquent** (`SoftDeletes` de Laravel, columna `deleted_at`). Ningún módulo hace `DELETE` físico salvo limpieza de datos explícitamente solicitada (ej. cumplimiento legal) — y esa excepción se documenta caso por caso, nunca por comodidad. Los recursos "eliminados" desde el panel o la API quedan con `deleted_at` poblado, dejan de listarse por defecto, pero siguen existiendo para auditoría y para no romper referencias de registros históricos (una `sesion` no puede quedar huérfana porque se "borró" el `dron_id` que usó).
2. **Bitácora de auditoría transversal**: toda creación, modificación, borrado lógico y cambio de estado relevante queda registrada con actor (`sec_user.id`), fecha, entidad afectada, acción, y — donde aplique — valores antes/después. No se limita a las cuatro áreas que ya mencionaba la especificación original (validaciones, planillas, gastos, movimientos de stock): es un trait/observer que se cuelga de cualquier modelo del dominio que lo declare, empezando por todo lo que toque dinero, roles/permisos (`sec_permission_log`, ya definido en ADR 0004) y estados operativos.

Implementación de referencia: un trait `Auditable` (o el paquete `spatie/laravel-activitylog`, a evaluar en la implementación) que se adjunta a los modelos vía Eloquent observers — no requiere que cada caso de uso llame manualmente a un servicio de auditoría, así un agente de IA no puede "olvidarlo" al escribir un nuevo caso de uso.

## Alternativas descartadas

- **`DELETE` físico + tabla de auditoría manual por módulo**: exige que cada implementación (agente de IA incluido) recuerde escribir el registro de auditoría a mano; se descarta por ser una regla de disciplina, no una que la arquitectura defienda sola (mismo criterio que ADR 0003, sección de tests de arquitectura).
- **Sin soft delete, con restauración desde el respaldo diario** (`docs/legacy/enfoque_desarrollo_sistema_fumigacion.md`, sección 3.4): el respaldo protege ante desastres, no ante un borrado accidental de un registro puntual en medio de la operación normal.

## Consecuencias

- Toda migración que cree una tabla de dominio incluye `deleted_at` y las columnas de auditoría (`created_by`, `updated_by` como mínimo) salvo justificación explícita en el PR.
- Las consultas de listados por defecto excluyen soft-deleted (comportamiento estándar de Eloquent); las vistas de auditoría e historial los incluyen explícitamente.
- El panel web (ADR 0002) necesita una vista de bitácora (quién hizo qué, cuándo) accesible al menos para encargado y dueño — no es una pantalla opcional, es la razón de ser de este ADR.
- Se agrega como invariante en `CLAUDE.md` para que ningún agente de IA genere un modelo o migración sin estos dos mecanismos.

## Nota (31/8/2026) — la bitácora del punto 2 ya existe

La pieza que este ADR dejaba pendiente ("todavía a evaluar en la implementación") ya está construida, en `app/Dominios/Compartido/Infraestructura/Eloquent/`: trait `RegistraBitacora` (colgable por modelo) + observer `BitacoraObserver`, sobre la tabla `plt_bitacoras` (prefijo `plt_`, asignado en ADR 0011, extensión 31/8/2026).

Se optó por el trait/observer propio en vez de `spatie/laravel-activitylog`: el paquete trae su propia tabla sin prefijo de módulo y su propio modelo, que no extiende `ModeloDominio` — el mismo problema que HU-03 resolvió no publicando la tabla de Sanctum (ver `SecTokenDispositivo`). Mantener el mecanismo propio, del mismo tamaño que `RegistraAutoria`, evita esa dualidad sin sumar una dependencia para algo que el proyecto ya sabe construir con sus propias convenciones (soft delete, prefijo de tabla, columnas de auditoría).

Aplicado hoy a los modelos `sec_*` de roles y permisos (`SecRole`, `SecPermission`, `SecUser`, `SecUserRole`, `SecRolePermission`), que es la categoría "roles/permisos" que este ADR nombra explícitamente. Las categorías "dinero" y "estados operativos" (`com_contratos`, `com_lotes`, `ope_ordenes_aplicacion`) quedan para la tarea que implemente la máquina de estados/devengos — la siguiente en la cola después de ésta —, que de todos modos va a tocar esos modelos. `tests/Unit/BitacoraAuditoriaTest.php` decide, a partir del esquema real de cada tabla, qué modelos están obligados a llevar el trait, y falla si a alguno le falta.

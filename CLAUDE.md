# CLAUDE.md — invariantes de `agrocom-api`

Este repo es el backend (Laravel + PostgreSQL) y el panel web (AdminLTE + Blade/Livewire) de Agrocom SRL. El desarrollo lo hace una sola persona con agentes de IA como implementadores principales; estas invariantes existen para que la coherencia no dependa de la memoria de nadie. La fuente de verdad funcional/técnica es `docs/especificacion/especificacion_funcional_tecnica.md`; el porqué de cada decisión de arquitectura está en `docs/decisiones/` (ADRs). Léelos antes de implementar algo que los toque.

## Invariantes no negociables

1. **Idempotencia por UUID.** Todo registro que nace en la app de campo trae su `uuid_cliente` generado en el dispositivo. El servidor lo hace único (`UNIQUE (uuid_cliente)`) y responde `duplicado` ante un reintento — nunca duplica hectáreas.
2. **El servidor nunca sobrescribe un registro validado.** Las correcciones son registros nuevos con `anula_a_id`, motivo y autor — nunca un `UPDATE` sobre lo validado.
3. **El devengo se genera solo al validar una sesión, nunca al cerrarla.** Es un evento de dominio (`SesionValidada`), idempotente por `UNIQUE (sesion_id, persona_id)`.
4. **Validador ≠ piloto de esa sesión, a nivel de persona — no de rol.** Un jefe de campo que también es piloto no valida sus propias sesiones (ver `docs/decisiones/0004-modelo-seguridad-sec-multirol.md`).
5. **El portal del cliente siempre consulta desde el `contrato` del usuario autenticado**, nunca desde la tabla global con un `where` agregado después. Todo endpoint nuevo de `/api/portal/*` lleva un test: cliente A pidiendo un recurso de cliente B → 404.
6. **Dinero y hectáreas en `DECIMAL`, jamás `float`.** Todo monto derivado debe poder recalcularse desde los registros de origen y cuadrar exacto.
7. **Toda transición de estado pasa por el servicio de dominio de la máquina de estados correspondiente** (tabla de transiciones permitidas + guardas) — nunca un `estado = ...` suelto en un controlador.
8. **Soft delete por defecto en todo modelo de dominio.** Ningún `DELETE` físico salvo excepción justificada explícitamente en el PR (`docs/decisiones/0007-soft-delete-y-bitacora-auditoria.md`).
9. **Bitácora de auditoría en toda mutación relevante**: quién, cuándo, qué entidad, qué acción, valores antes/después donde aplique. Es un trait/observer de plataforma — no una llamada manual que cada caso de uso deba recordar.
10. **Un usuario, un login, múltiples roles.** Nunca se crean cuentas duplicadas por rol; los permisos se evalúan por unión de roles vía `sec_user_role`.
11. **Ningún color hardcodeado en el panel.** Todo color se referencia por token CSS — es lo que permite el theming por usuario (`docs/decisiones/0002-panel-web-adminlte-livewire-atomic-design.md`).

## Convenciones

- **Dominio en español, infraestructura en inglés**: `Trabajo`, `Sesion`, `Mezcla`, `hectareas_declaradas`, pero `SyncController`, `Repository`. El vocabulario del negocio es en español; traducirlo crea dos idiomas para la misma cosa.
- **Commits en español, imperativo**: `agrega validación de solape en sesiones`.
- **Arquitectura**: monolito modular Laravel, un módulo de dominio = una carpeta bajo `app/Dominios/`, con `Contratos/`, `Aplicacion/`, `Dominio/`, `Infraestructura/` (ver `docs/decisiones/0003-arquitectura-modular-clean-por-feature.md`). Un módulo solo escribe sus propias tablas; entre módulos se viaja por contratos o eventos de dominio, nunca por modelos ajenos.
- **Panel web**: componentes Blade en Atomic Design (atoms/molecules/organisms/templates/pages), AdminLTE (Bootstrap) para estructura + Material Design para inputs/cards/iconos (ver ADR 0002).
- **Branching**: GitFlow simplificado — ver `CONTRIBUTING.md` y `docs/decisiones/0006-gitflow-simplificado.md`.

## Qué no delegar sin revisión línea por línea

El motor de sync, el servicio de estados, los listeners que generan dinero (devengos, planilla), y el scoping del portal del cliente. El resto (CRUDs, pantallas no críticas, recursos de listado, plantillas PDF) se revisa por diff y test en el PR.

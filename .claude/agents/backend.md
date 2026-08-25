---
name: backend
description: Usar para implementar lógica de dominio en Laravel — casos de uso, modelos Eloquent, eventos de dominio, listeners, servicios de máquina de estados, endpoints de API. El trabajo central de cada HU/TE del backend de `agrocom-api`. No usar para pantallas del panel (`frontend`), para diseño visual (`design-ui`), para migraciones puras de esquema (`modelo-datos`, aunque suele coordinar con este), ni para permisos/roles (`modulos-roles`).
tools: Read, Write, Edit, Bash, Grep, Glob
---

Implementás la lógica de dominio de `agrocom-api`: casos de uso, modelos Eloquent, eventos, listeners, servicios de estado, endpoints de `routes/api.php`.

Leé primero:
- `docs/especificacion/especificacion_funcional_tecnica.md` (contrato funcional/técnico completo — secciones 2, 4, 5, 6, 8 son las que más vas a usar).
- `docs/decisiones/0003-arquitectura-modular-clean-por-feature.md` y `0008-separacion-backend-frontend-monolito.md`.
- `CLAUDE.md` — invariantes no negociables, especialmente las de sync, dinero y transiciones de estado.
- `docs/glosario.md` si un término de negocio no es obvio.

Reglas de trabajo:
1. Un módulo solo escribe sus propias tablas. Si necesitás algo de otro módulo, lo pedís por `Contratos/` (síncrono) o reaccionás a un evento de dominio — nunca importás un modelo Eloquent ajeno.
2. Toda transición de estado (sesión, trabajo, orden) pasa por el servicio de dominio de esa máquina de estados — nunca un `estado = ...` suelto en un controller.
3. El devengo y cualquier monto derivado se genera por evento de dominio, nunca directo en el controller que cierra o valida.
4. Dinero y hectáreas en `DECIMAL`, nunca `float`.
5. Todo modelo lleva soft delete y las columnas de auditoría (ADR 0007) — no lo agregues "cuando haga falta", va desde la primera migración/modelo.
6. Los endpoints de API (`Http/Controllers/Api/`) son adaptadores delgados: reciben, invocan el caso de uso de `Aplicacion/`, devuelven — la lógica vive una sola vez ahí, no en el controller (ADR 0008).
7. El motor de sync (idempotencia por `uuid_cliente`, `ON CONFLICT`, transacciones por registro) es la pieza que se revisa línea por línea — no la apures.

Si un caso de uso ya existe (porque el panel también lo necesita), reutilizalo — no lo dupliques entre el controller de API y el componente Livewire del `frontend`.

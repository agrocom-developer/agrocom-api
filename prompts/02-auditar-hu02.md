# Tarea 02 — Auditar HU-02 contra su criterio de aceptación

Sesión nueva y aislada. No asumas nada de tareas anteriores: leé `runs/01.md`
si existe, pero el estado real está en git y en el código.

Cargá los skills `verificacion`, `seguridad-roles` y `dominio-backend`.

## Qué hacer

Los criterios de aceptación de HU-02 están en `docs/gestion/plan_sprints.md`,
fila `HU-02`. Son cinco, y hay que tratarlos uno por uno:

1. Menú renderizado desde `sec_menu`/`sec_permission` según el **rol activo**
   (no la unión de roles — invariante 10 de `CLAUDE.md`).
2. Selector de rol al login si el usuario tiene más de uno asignado.
3. Cambio de rol activo en caliente, misma sesión, sin volver a loguearse.
4. Botones ocultos sin permiso.
5. Preferencia de tema persistida por usuario, sin ningún color hardcodeado.

Para cada uno: buscá el test que lo cubre y reportá `archivo:línea`.
- Si el comportamiento está implementado pero **no tiene test**, escribí el test.
- Si **no está implementado**, implementalo con su test.
- Si ya está cubierto, no toques nada.

Si escribiste código, va en una rama `feature/*` nueva (nombre de 2–3 palabras)
con PR a `develop` (sin `--draft`). Si no escribiste código, no hay rama ni PR:
la auditoría es el entregable.

## Criterio de aceptación

`./bin/verify` devuelve 0, y existe una tabla CA → test → veredicto para los
cinco criterios.

## Cierre obligatorio

Escribí `runs/02.estado` con **una sola palabra**:
- `CERRADA` si los cinco CA están implementados y cubiertos por test.
- `PENDIENTE` si falta algo que no pudiste completar.
- `BLOQUEADA` si necesitás una decisión del usuario.

Escribí `runs/02.md` con la tabla de los cinco CA y el detalle.

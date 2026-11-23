# ADR 0028 — Proveedores: propio, independiente y tercero

**Estado:** aceptada, revisable · **Fecha:** 2026-11-23 · **Decide:** el dueño (autonomía delegada)

## Contexto

Quien ejecuta el servicio puede ser personal de Agrocom, un piloto con su propio dron o una empresa que tiene varios drones y pilotos. El sistema debe tratarlos con las mismas reglas de operación y distinguirlos solo donde importa: prioridad de asignación y comisión.

## Decisión

1. **Proveedor** (`prv_proveedores`) es la entidad que presta el servicio y recibe ofertas. Tiene un `tipo`: `propio` (Agrocom), `independiente` (un piloto que es su propio proveedor) o `tercero` (empresa o dueño de drones con pilotos a su cargo).
2. Los pilotos y auxiliares pertenecen a un proveedor. Un piloto registra a su auxiliar y un administrador lo aprueba.
3. El proveedor `propio` recibe la prioridad de asignación (ADR 0030); los demás compiten por calificación y cercanía.
4. Las apps `piloto` y `auxiliar` funcionan sin conexión en el lote. Aceptar o rechazar una oferta requiere red: es la **excepción documentada** al principio offline, porque la aceptación es un acuerdo entre dos partes que tiene que resolverse de forma atómica en el servidor. Todo lo ejecutado en el lote sigue siendo offline, con `uuid_cliente`, outbox, idempotencia y evidencias comprimidas.
5. Cada registro de ejecución lo escribe un solo rol: la sesión, el piloto; la mezcla, el auxiliar.

## Alternativas descartadas

- Un modelo distinto por tipo de proveedor: triplica las pantallas y las reglas sin diferencia funcional.
- Aceptación offline con resolución posterior: dos proveedores podrían creer que tienen el mismo trabajo.

## Consecuencias

Las políticas de verificación, calificación y cancelación se escriben una vez para todos los tipos. La comisión puede diferir por tipo (ADR 0034).

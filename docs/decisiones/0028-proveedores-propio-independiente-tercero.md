# ADR 0028 — Proveedores: propio, independiente y tercero

**Estado:** aceptada, revisable · **Fecha:** 2026-11-23 · **Decide:** el dueño (autonomía delegada)

## Contexto

Quien ejecuta el servicio puede ser personal de Agrocom, un piloto con su propio dron o una empresa que tiene varios drones y pilotos. El sistema debe tratarlos con las mismas reglas de operación y distinguirlos solo donde importa: la etiqueta visible y la verificación.

## Decisión

1. **Proveedor** (`prv_proveedores`) es la entidad que presta el servicio y recibe ofertas. Tiene un `tipo`: `propio` (Agrocom), `independiente` (un piloto que es su propio proveedor) o `tercero` (empresa o dueño de drones con pilotos a su cargo).
2. Los pilotos y auxiliares pertenecen a un proveedor. Un piloto registra a su auxiliar y un administrador lo aprueba.
3. El proveedor `propio` (Agrocom) compite en igualdad con los demás: ofrece, contraoferta y se califica igual. No hay prioridad de asignación (ADR 0030).
4. Las apps `piloto` y `auxiliar` funcionan sin conexión en el lote. Publicar, ofertar, contraofertar, aceptar, rechazar y retirar una oferta requieren red: es la **excepción documentada** al principio offline, porque la adjudicación es un acuerdo entre dos partes que tiene que resolverse de forma atómica en el servidor. Todo lo registrado en el lote (inicio, avance, cierre, mensajes y fotos) sigue siendo offline, con `uuid_cliente`, outbox, idempotencia y evidencias comprimidas.
5. Cada registro de ejecución lo escribe un solo rol: el avance, el piloto; cada mensaje, su autor; cada foto, quien la sube.

## Alternativas descartadas

- Un modelo distinto por tipo de proveedor: triplica las pantallas y las reglas sin diferencia funcional.
- Negociación offline con resolución posterior: dos proveedores podrían creer que tienen el mismo trabajo.

## Consecuencias

Las políticas de verificación, calificación y cancelación se escriben una vez para todos los tipos.

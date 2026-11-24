# ADR 0031 — Calificación del proveedor y del cliente

**Estado:** aceptada, revisable · **Fecha:** 2026-11-23 · **Decide:** el dueño (autonomía delegada)

## Contexto

La confianza del marketplace se apoya en reputación. En la movilidad ambas partes se califican; aquí además hay un tercero, el dron, que no se califica.

## Decisión

1. El **cliente** califica al proveedor, al piloto y al auxiliar (1 a 5 estrellas, etiquetas y comentario) solo cuando la solicitud está `finalizada`; al hacerlo pasa a `calificada`. Es un registro propio (`cal_calificaciones`) escrito por el cliente; el proveedor solo lo ve.
2. El **proveedor** califica al cliente de forma privada; sirve a moderación y a soporte, no se publica.
3. La calificación nunca se edita. El **administrador modera** (oculta con motivo y autor) las que incumplen la política: insultos, calificación por causas ajenas al proveedor (clima, demora del cliente), represalia.
4. El promedio de un proveedor se calcula sobre sus últimas 100 calificaciones visibles, con un mínimo de 10 para activar umbrales. Bajo el umbral (por defecto 4.0) se emite una alerta al panel y un administrador decide, con aviso previo y vía de apelación; no hay restricción automática.
5. Plazo para calificar: 7 días desde `finalizada`.

## Alternativas descartadas

- Calificación editable: abre la puerta a presión sobre el cliente.
- Calificación pública del cliente: expone a personas sin beneficio claro.

## Consecuencias

Detalle operativo en `docs/politicas/calificacion.md`. La calificación y la tasa de cancelación del proveedor se muestran al cliente en cada oferta (ADR 0030).

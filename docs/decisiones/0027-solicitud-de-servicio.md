# ADR 0027 — Solicitud de servicio como unidad de negocio

**Estado:** aceptada, revisable · **Fecha:** 2026-11-23 · **Decide:** el dueño (autonomía delegada)

## Contexto

Agrocom es un marketplace: el cliente pide un servicio de fumigación y un proveedor lo ejecuta. Hace falta una unidad única que una a ambos, lleve el precio acordado, el estado y la evidencia de lo ejecutado.

## Decisión

1. La **solicitud de servicio** (`sol_solicitudes_servicio`) es la unidad de negocio. Todo lo demás (oferta, equipo, sesión de vuelo, acta, calificación, disputa) cuelga de ella.
2. Todo es solicitud por solicitud. No hay contratos marco, campañas ni exclusividad de terreno entre solicitudes.
3. La tarifa es **por hectárea**, con recargos explícitos, y se congela al publicar. No hay tarifa dinámica por demanda en la primera versión.
4. La solicitud nace con `uuid_cliente` (idempotencia) y su ciclo de estados está en `docs/especificacion/marketplace.md` §5.1. Cada transición deja una fila inmutable de historial.
5. El cliente confirma la ejecución con un acta de conformidad; el silencio durante el plazo configurable (72 horas) equivale a conformidad.

## Alternativas descartadas

- **Tarifa dinámica estilo Uber**: opaca para un cliente agrícola que presupuesta por hectárea y sin volumen suficiente para equilibrar oferta y demanda.
- **Contratos con campañas**: añaden un nivel administrativo que el marketplace no necesita.

## Consecuencias

La facturación, la comisión y la reputación se calculan por solicitud. El panel y las apps se organizan alrededor de ella.

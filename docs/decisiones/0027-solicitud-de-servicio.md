# ADR 0027 — Solicitud de servicio como unidad de negocio

**Estado:** aceptada, revisable · **Fecha:** 2026-11-23 · **Decide:** el dueño

## Contexto

Agrocom es un marketplace: el cliente pide un servicio de fumigación y un proveedor lo ejecuta. Hace falta una unidad única que una a ambos, lleve el precio acordado, el estado y la evidencia de lo ejecutado.

## Decisión

1. La **solicitud de servicio** (`sol_solicitudes_servicio`) es la unidad de negocio. Todo lo demás (oferta, equipo, avance, calificación, disputa) cuelga de ella.
2. Todo es solicitud por solicitud. Un terreno puede tener varias solicitudes, secuenciales o simultáneas.
3. El cliente propone un **precio por hectárea**; el precio acordado nace de la negociación (ADR 0030) y se congela al adjudicar. No hay tarifa impuesta por la plataforma.
4. La solicitud nace con `uuid_cliente` (idempotencia) y su ciclo de estados está en `docs/especificacion/marketplace.md` §5.1: `borrador`, `abierta`, `con_ofertas`, `adjudicada`, `en_ejecucion`, `finalizada`, `calificada`, más `cancelada`, `expirada` y `disputada`. Cada transición deja una fila inmutable de historial.
5. El piloto marca el avance y el cierre; al finalizar se congela el precio final (hectáreas ejecutadas por precio acordado) y el cliente puede calificar y, dentro de su plazo, disputar.

## Alternativas descartadas

- **Tarifa dinámica estilo Uber**: opaca para un cliente agrícola que presupuesta por hectárea.
- **Acta de conformidad firmada**: añade una fricción que la plataforma no necesita; el desacuerdo se resuelve con una disputa.

## Consecuencias

El seguimiento, el precio final y la reputación se calculan por solicitud. El panel y las apps se organizan alrededor de ella.

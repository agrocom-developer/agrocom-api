# ADR 0030 — Negociación oferta-demanda entre cliente y pilotos

**Estado:** aceptada, revisable · **Fecha:** 2026-11-24 · **Decide:** el dueño · **Reemplazado en parte por:** ADR 0045 (punto 6: hay avisos por oleadas y orden sugerido con propios primero, sin asignación automática; punto 7: vigencia larga; distancia y zonas retiradas)

## Contexto

El precio de una fumigación depende del lote, el acceso, el cultivo, la distancia y la temporada, y el piloto quiere ver el estado de la propiedad antes de aceptar un precio. Un cliente puede recibir varias ofertas y elegir la que más le conviene. Agrocom es el nexo y el registro del resultado; lo operativo se acuerda en persona. El mercado es de oferta y demanda.

## Decisión

1. **El cliente publica y propone.** La solicitud (`abierta`) lleva lotes en el mapa, hectáreas, datos previos y un `precio_ha_propuesto`. Es visible para todos los pilotos habilitados en el mapa de solicitudes abiertas.
2. **El piloto acepta o contraoferta.** Una oferta es un precio por hectárea con el equipo (dron) que el proveedor usará. Aceptar el precio propuesto es una oferta de tipo `acepta_propuesta`; otro precio es una `contraoferta`.
3. **El cliente elige.** Recibe varias negociaciones, compara (precio, calificación, dron con fotos, distancia) y acepta una, contraoferta o rechaza. Cada proveedor tiene su propia negociación; las demás no se ven entre sí.
4. **Rondas acotadas.** En una negociación hay una sola oferta vigente. Quien la recibe acepta, contraoferta (la reemplaza) o rechaza. El máximo de ofertas por negociación es un parámetro (5); la última es definitiva y solo se acepta o se rechaza. Cada oferta vence a las 24 horas (parámetro).
5. **Adjudicación atómica.** Aceptar la oferta vigente de la contraparte la adjudica: la solicitud pasa a `adjudicada` con el precio acordado, el proveedor y el equipo, y las demás ofertas se cierran. La decisión bloquea la fila de la solicitud; una segunda aceptación recibe conflicto.
6. **Sin asignación automática.** No hay prioridad para proveedores propios, radios de escalado, rondas por tiempo ni puntuación. Agrocom compite como un proveedor más. Un administrador puede cancelar una solicitud con motivo, pero no adjudica por el cliente.
7. **Vigencia.** Una solicitud sin adjudicar expira a los 7 días o al fin de su ventana (parámetros) y el cliente la renueva dentro de 30 días.
8. **Requiere red.** Ofertar, contraofertar, aceptar, rechazar y retirar son llamadas en línea con confirmación del servidor; son la excepción documentada del ADR 0028.

## Alternativas descartadas

- **Asignación automática por cercanía y calificación**: quita al cliente y al piloto la negociación del precio, que es el centro del negocio local.
- **Subasta inversa abierta con precios visibles entre pilotos**: invita a bajar el precio por debajo del costo y expone la oferta de cada proveedor.
- **Negociación sin límite de rondas**: se alarga sin decidir; el límite y el vencimiento empujan a cerrar.
- **Adjudicación automática al primer piloto que acepta el precio propuesto**: quita al cliente la comparación de calificación y dron. Queda como opción futura por solicitud.

## Consecuencias

Hace falta un proceso programado que venza ofertas y expire solicitudes (ADR 0038). El comparador del cliente y el mapa del piloto son las dos pantallas centrales. Las reglas exactas están en `docs/especificacion/negociacion-contrato.md` y la política en `docs/politicas/negociacion.md`.

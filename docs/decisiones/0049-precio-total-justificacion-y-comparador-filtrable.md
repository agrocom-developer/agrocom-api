# ADR 0049 — Precio total de solo lectura, justificación del precio y comparador filtrable

**Estado:** Aceptada · **Fecha:** 2026-11-27 · **Relacionados:** ADR 0030 (negociación), ADR 0034 (precio acordado y pago fuera de la app), ADR 0045 (mercado: puntos 24 y 30), invariante 6 (dinero en decimal exacto) e invariante 13 (contacto solo tras adjudicar).

## Contexto

La política de precio no cambia: el cliente propone un precio por hectárea y los proveedores lo aceptan o contraofertan, por rondas. Faltaban tres cosas visibles para ambas apps: que el **total** del servicio quede explícito y no editable, que cada parte pueda **explicar** su precio con un texto corto, y que el cliente pueda **ordenar y filtrar** las ofertas que le llegan. «Buscar pilotos» no significa un directorio de pilotos: es que el servidor le muestra al cliente las ofertas encontradas para su solicitud (el comparador).

## Decisión

1. **El total lo calcula solo el servidor y es de solo lectura.** `monto_estimado` es `hectareas_solicitadas × precio_ha` con decimal exacto (`MontoDelServicio`, redondeo a 2 decimales), por oferta y en la solicitud (con el precio propuesto mientras no está adjudicada; con el acordado desde que se adjudica). Ninguna petición lo recibe: si una app lo envía, se ignora. Nunca pasa por `float`. El nombre `monto_estimado` se conserva para no romper el contrato; la app lo presenta como «Total estimado».
2. **Justificación del precio.** Campo opcional de texto `justificacion_precio`, de hasta 500 caracteres, que explica el precio. Se sanea al guardar: sin etiquetas HTML, sin caracteres de control, espacios colapsados, y vacío equivale a nulo. Es visible para la contraparte. **Cada parte escribe su propio campo, nunca el de la otra:**
   - El cliente la escribe en la solicitud (`sol_solicitudes_servicio.justificacion_precio`) al crearla y al cambiar el precio (`POST /solicitudes`, `POST /solicitudes/{id}/precio`); el proveedor la ve en el mapa y en el detalle de la solicitud abierta, y con la solicitud en `GET /solicitudes`.
   - Cada oferta o contraoferta lleva la suya (`neg_ofertas.justificacion_precio`), escrita solo por el autor de esa oferta (`POST /solicitudes/{id}/ofertas`, `POST /ofertas/{id}/contraofertar`). La oferta no se edita después: una contraoferta es una fila nueva, así que no hay forma de que dos roles editen la misma fila.
   - Es independiente de `nota` (mensaje libre de la oferta): `nota` acompaña la oferta; `justificacion_precio` explica el precio y es lo que la app muestra junto al total.
3. **El comparador se filtra y se ordena en el servidor.** `GET /solicitudes/{id}/ofertas` acepta `orden` (`recomendado`, `precio`, `calificacion`, `reciente`; ADR 0045, punto 24) y los filtros `precio_ha_min`, `precio_ha_max`, `monto_max`, `calificacion_min`, `tipo_proveedor` (`propio`, `independiente`, `tercero`) y `capacidad_tanque_min` (litros del dron). Un valor inválido responde 422. Los filtros solo reducen la lista; el comparador nunca incluye nombre, teléfono, documento ni dirección de nadie que no deba verlos antes de adjudicar: la tarjeta del proveedor trae nombre comercial o razón social, foto, calificación, tasa de cancelación, tipo y dron.
4. **La disponibilidad no es un filtro del cliente.** La decide el servidor: una oferta de un proveedor `ocupado` o `en_trabajo` espera y no se muestra al cliente (ADR 0045, punto 30). Exponerla como filtro le revelaría el estado de la cuenta. Hasta que llegue la bandeja con cola, el comparador no la aplica (ver «Consecuencias»).

## Consecuencias

- Migración aditiva: dos columnas de texto nulas (`justificacion_precio`), sin vistas afectadas.
- Cambia el contrato: `justificacion_precio` en `Oferta`, `SolicitudServicio` y `SolicitudAbierta`; `monto_estimado` también en `SolicitudAbierta`; parámetros nuevos del comparador; `tipo` en la tarjeta del proveedor.
- Pendiente de la bandeja y la cola (ADR 0045, puntos 15 a 17 y 30): ocultar al cliente las ofertas de proveedores no disponibles. Mientras tanto el comparador las lista.

## Auditoría de la disponibilidad del proveedor (27/11/2026)

Lo que el contrato ya cubre: el proveedor se apaga a mano con `PUT /proveedores/mi/disponibilidad` (`disponible`, `ocupado`, `en_trabajo`); el encargado de la empresa marca a un piloto con `PUT /proveedores/pilotos/{id}/disponibilidad`; `GET /proveedores/mi` devuelve `disponibilidad` y `disponibilidad_empresa` con `cambiada_en`, `cambiada_por_usuario_id` y `cambiada_por_propia` (la app distingue «lo cambié yo» de «lo cambió mi empresa»); `GET/PUT /notificaciones/preferencias` trae `nuevas_solicitudes` con los filtros de aviso `hectareas_minimas` y `tipos_aplicacion_excluidos` y la lista `filtros_disponibles`.

Huecos, sin cambiarlos en este ADR:

1. **`SolicitudAbierta` no lleva `departamentoCodigo`.** El evento de dominio que dispara los avisos trae solo id, cliente, hectáreas, precio y tipo de aplicación; el departamento existe en la solicitud pero el aviso por departamento (punto 47 del ADR 0045, fase D1) tendrá que leerlo de la solicitud o agregarlo al evento. Mientras `departamentos_excluidos` siga vigente, ese filtro se aplica sobre la solicitud, no sobre el evento.
2. **La disponibilidad no filtra todavía las ofertas del cliente ni la cola.** El punto 30 del ADR 0045 (la oferta de un proveedor no disponible espera y no se le muestra al cliente) depende de la bandeja y la cola, que no están implementadas; hoy un proveedor `ocupado` sigue apareciendo en el comparador si oferta.
3. **Sin `espera_por_disponibilidad` en la oferta.** Es la señal que la app del proveedor necesita para decir «tu oferta espera hasta que vuelvas a disponible»; llega con la cola.
4. **Sin `recibir_urgentes` ni `departamentos` propios en los filtros.** `filtros_disponibles` solo declara lo que hoy existe; `departamentos_excluidos` sigue en su sitio hasta la fase D1, que lo reemplaza.

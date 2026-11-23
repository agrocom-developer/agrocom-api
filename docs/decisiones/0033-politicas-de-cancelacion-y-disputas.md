# ADR 0033 — Políticas de cancelación y disputas

**Estado:** aceptada, revisable · **Fecha:** 2026-11-23 · **Decide:** el dueño (autonomía delegada)

## Contexto

Las plataformas de movilidad cobran penalidad cuando el cliente cancela con el conductor ya en camino y registran las cancelaciones del conductor; ofrecen soporte para reembolsos. En fumigación el costo de una cancelación tardía es un desplazamiento con dron, insumos y una ventana climática perdida.

## Decisión

1. Cancelar antes de la aceptación es gratuito. Después se aplica una escala configurable según la anticipación y el estado (`asignada`, `en_camino`, en el lote). No hay penalidad por clima, emergencia, causa del otro lado ni por falta de proveedor.
2. El proveedor que cancela después de aceptar suma a su tasa de cancelación; reincidencia implica advertencia, restricción y suspensión. La solicitud vuelve a `en_asignacion`.
3. Una **disputa** la abre el cliente (hasta 72 horas después de `ejecutada`) o el proveedor (por cobro o por acceso al lote). Una por solicitud. Soporte media y un administrador resuelve con evidencia, con resultado `a_favor_cliente`, `a_favor_proveedor`, `parcial` o `sin_lugar` y un monto de reembolso.
4. La resolución es un registro nuevo con autor y motivo; nada se sobrescribe.

## Alternativas descartadas

- Penalidad fija única: no distingue un aviso de días de uno de minutos.
- Reembolso automático: abre el servicio al abuso.

## Consecuencias

Documentación operativa en `docs/politicas/cancelacion.md`, `disputas.md` y `seguridad.md`.

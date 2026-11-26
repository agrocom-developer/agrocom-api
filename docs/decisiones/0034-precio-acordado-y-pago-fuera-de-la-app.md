# ADR 0034 — Precio acordado y pago fuera de la aplicación

**Estado:** aceptada, revisable · **Fecha:** 2026-11-24 · **Decide:** el dueño · **Reemplazado en parte por:** ADR 0045 (punto 6: no hay precio de referencia)

## Contexto

El precio por hectárea se negocia entre cliente y piloto. Integrar pasarelas de pago exige cumplimiento y tiempo que no son necesarios para validar el mercado, y el modelo de ingresos de Agrocom se define en el negocio, no en el código.

## Decisión

1. La aplicación **no cobra**. El cliente paga al proveedor fuera de ella (transferencia o efectivo) y la app muestra el monto.
2. **Precio acordado.** Al adjudicar se congela `precio_ha_acordado` y el `monto_estimado` (hectáreas solicitadas por precio acordado).
3. **Precio final.** Al finalizar se congela `monto_final = hectareas_ejecutadas × precio_ha_acordado`, redondeado a 2 decimales, y se muestra a ambos como el monto a pagar. Un reclamo resuelto puede fijar un `monto_ajustado` que pasa a ser el precio final vigente.
4. El proveedor puede registrar que recibió el pago (`seg_pagos_confirmados`); es informativo y no cambia el estado.
5. **La plataforma no calcula comisión.** No hay porcentaje, estado de cuenta ni bloqueo por deuda en el sistema. Una futura pasarela de pago o un modelo de ingresos se decide en un ADR nuevo.
6. El precio de referencia por tipo de aplicación y zona (parámetro) es una guía visible para ambos lados, no un límite.

## Alternativas descartadas

- **Retención automática con pasarela desde el inicio.**
- **Comisión calculada por la plataforma**: sin cobro en la app no hay forma de verificarla y condiciona la negociación.

## Consecuencias

Detalle en `docs/politicas/precio_y_pago.md`. Montos y hectáreas en decimal exacto.

# Política de precio y pago

**Agrocom SRL · Vigente** · Decisión: ADR 0034, ADR 0045 · Especificación: `docs/especificacion/marketplace.md` §8.2

## Precio por hectárea

- Lo propone el cliente al publicar y se negocia con cada piloto (política de negociación).
- Al adjudicar queda congelado como `precio_ha_acordado`; no cambia después, salvo por la resolución de una disputa.
- No hay precio de referencia: lo ponen el cliente y el proveedor. El sistema solo rechaza un precio por encima de un máximo de sanidad (parámetro) para evitar errores de digitación.

## Monto

- **Estimado** al adjudicar: hectáreas solicitadas por precio acordado.
- **Final** al finalizar: hectáreas ejecutadas por precio acordado, redondeado a 2 decimales. Las hectáreas ejecutadas no superan las solicitadas más la tolerancia (parámetro, 5 %). Todo se calcula en decimal exacto.
- Si un reclamo resuelto fija un monto ajustado, ese pasa a ser el final vigente.

## Pago

- La aplicación no procesa cobros. El cliente paga al proveedor fuera de ella (transferencia o efectivo) y la app muestra el monto a pagar.
- El proveedor puede registrar que recibió el pago; es un aviso informativo que no cambia el estado.
- Los reembolsos que resulten de un reclamo se liquidan entre las partes fuera de la aplicación.

## Comisión

La plataforma no calcula ni cobra comisión. El modelo de ingresos de Agrocom se define fuera de la aplicación; cualquier cambio que lo traiga al sistema se decide en un ADR nuevo.

## Transparencia

Cliente y proveedor ven el mismo precio acordado, el mismo monto estimado y el mismo monto final. Soporte explica cualquier diferencia con el expediente.

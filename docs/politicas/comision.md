# Política de comisión, tarifa y pagos

**Agrocom SRL · Vigente** · Decisión: ADR 0034 · Especificación: `docs/especificacion/marketplace.md` §8.2

## Tarifa al cliente

- Se fija por hectárea, según tipo de aplicación y zona. Los recargos (urgencia, acceso difícil, producto aportado por el proveedor) se muestran de forma separada antes de confirmar.
- Se congela al publicar la solicitud. Cambiarla exige aceptación del cliente.
- Las hectáreas facturadas son las confirmadas en el acta, con tolerancia sobre las solicitadas definida por parámetro; todas se calculan en decimal exacto.

## Pagos en la versión 1

- La aplicación no procesa cobros. El cliente paga al proveedor fuera de la app y el proveedor confirma el cobro en su pantalla de solicitud.
- La app muestra el monto acordado, la penalidad aplicada y el reembolso resuelto.

## Comisión

| Proveedor | Comisión inicial |
|---|---|
| Propio | 0 %, el ingreso es de Agrocom |
| Independiente y tercero | 15 % sobre el monto final |
| Dron vendido por Agrocom | Tasa preferencial del grupo, definida por parámetro |

La comisión se calcula al completar la solicitud y se congela en ella. Se liquida en un estado de cuenta periódico por proveedor; el proveedor transfiere a Agrocom. Un estado de cuenta vencido por más de 15 días retira al proveedor de la asignación hasta ponerse al día.

## Transparencia

El proveedor ve en la oferta cuánto cobrará neto. Soporte explica cualquier diferencia con el expediente.

# ADR 0034 — Comisión y pagos: sin cobro en la aplicación en la versión 1

**Estado:** aceptada, revisable · **Fecha:** 2026-11-23 · **Decide:** el dueño (autonomía delegada)

## Contexto

Las plataformas cobran una comisión sobre cada servicio (en movilidad suele rondar entre 20 % y 30 % con variación por viaje). Integrar pasarelas de pago exige cumplimiento y tiempo que no son necesarios para validar el mercado.

## Decisión

1. La versión 1 **no cobra en la aplicación**. El cliente paga al proveedor fuera de la app (transferencia o efectivo) por el monto de la solicitud, y la app muestra el monto acordado.
2. Agrocom devenga una **comisión** por solicitud `completada`: porcentaje sobre el monto final, congelado en la solicitud (`comision_porcentaje`, `comision_monto`). Valor inicial 15 % para terceros e independientes; 0 % para proveedores propios (ingreso pleno). Es un parámetro.
3. La comisión se liquida en un estado de cuenta por proveedor, que el administrador consulta y marca como cobrada. Un proveedor con comisión vencida no recibe ofertas hasta ponerse al día.
4. Una futura pasarela de pago se decide en un ADR nuevo; este registro de comisión ya es compatible.

## Alternativas descartadas

- Retención automática con pasarela desde el inicio.
- Comisión fija por solicitud: no escala con el tamaño del trabajo.

## Consecuencias

Detalle en `docs/politicas/comision.md`. Montos y porcentajes en decimal exacto.

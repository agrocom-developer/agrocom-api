# Política de cancelación

**Agrocom SRL · Vigente** · Decisión: ADR 0033 · Especificación: `docs/especificacion/marketplace.md` §5.1, §8.1

## Principios

- Cancelar debe ser fácil y justo para ambas partes.
- Una cancelación tardía cuesta un desplazamiento, insumos y una ventana climática; la penalidad lo refleja.
- Nadie paga por causas que no controla.

## Cancelación del cliente

| Momento | Penalidad |
|---|---|
| Antes de que un proveedor acepte | Ninguna |
| Aceptada, con más de 48 horas de anticipación al inicio de la ventana | Ninguna |
| Aceptada, entre 48 y 12 horas | 10 % del monto estimado |
| Aceptada, con menos de 12 horas o equipo en camino | 25 % del monto estimado |
| Equipo en el lote y trabajo no ejecutable por causa del cliente (sin acceso, terreno distinto al declarado, producto no entregado) | 50 % del monto estimado más recargo por desplazamiento |

Los porcentajes y las horas son parámetros. La penalidad se cobra al cliente fuera de la app (ADR 0034) y el proveedor la recibe descontando la comisión. Un cliente con penalidades impagas no puede publicar nuevas solicitudes.

## Cancelación del proveedor

- Después de aceptar, cancelar suma a la tasa de cancelación del proveedor.
- La solicitud vuelve a `en_asignacion` con prioridad y se avisa al cliente.
- Tres cancelaciones del proveedor en 30 días generan advertencia; cinco, restricción temporal de ofertas; la reincidencia lleva a suspensión revisada por un administrador.
- Cancelar con menos de 12 horas pesa el doble en la tasa.

## Sin penalidad para nadie

- Clima fuera de límites (viento, lluvia, temperatura); la solicitud pasa a `suspendida_por_clima` y se reprograma.
- Emergencia declarada o incidencia de seguridad.
- Falla comprobada del equipo del proveedor, que no cuenta como cancelación si se avisó con más de 12 horas.
- Falta de proveedor (`sin_proveedor`).
- Error de datos de la solicitud reconocido por soporte.

## Registro

Toda cancelación guarda quién, cuándo y motivo en el historial de la solicitud.

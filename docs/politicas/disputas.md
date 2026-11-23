# Política de disputas y reembolsos

**Agrocom SRL · Vigente** · Decisión: ADR 0033 · Especificación: `docs/especificacion/marketplace.md` §4.8, §8.4

## Cuándo se abre

- El **cliente** puede abrir una disputa desde `ejecutada` hasta 72 horas después (la conformidad tácita cierra el plazo).
- El **proveedor** puede abrirla por cobro pendiente, por acceso negado al lote o por una calificación abusiva.
- Una disputa por solicitud.

## Motivos

Cobertura incompleta, mala aplicación (franjas sin cubrir o doble pasada), daño a cultivos o a terceros, producto distinto al pactado, equipo ausente, evidencia faltante, cobro indebido, trato inadecuado.

## Evidencia

La plataforma conserva fotos antes y después, la sesión de vuelo con hectáreas y condiciones, la mezcla con receta y volumen, el acta y los mensajes. Quien abre la disputa agrega sus pruebas. Una sesión con condiciones forzadas o con evidencias obligatorias ausentes pesa en contra del proveedor.

## Proceso

1. Apertura con motivo y monto en disputa. La solicitud pasa a `en_disputa`.
2. Soporte pide información a la contraparte, que responde en 48 horas.
3. Un administrador resuelve: `a_favor_cliente`, `a_favor_proveedor`, `parcial` o `sin_lugar`.
4. La decisión es un registro con autor y motivo; ambas partes pueden apelar una vez en 7 días con pruebas nuevas.

## Reembolsos

| Resultado | Efecto |
|---|---|
| Cobertura incompleta | Reembolso proporcional a las hectáreas faltantes o repetición del trabajo sin costo |
| Mala aplicación | Repetición o reembolso hasta el 100 % |
| Daño probado | Se deriva al seguro del proveedor; Agrocom facilita el expediente |
| Equipo ausente | Reembolso total y falta del proveedor |
| Sin lugar | Sin cambios; si hay mala fe, se registra en la reputación del cliente |

Los reembolsos se liquidan entre las partes fuera de la app (ADR 0034); Agrocom ajusta la comisión del servicio en la misma proporción.

## Represalias

Se prohíbe calificar o presionar por haber abierto una disputa; se modera y se sanciona.

# Política de disputas y reembolsos

**Agrocom SRL · Vigente** · Decisión: ADR 0033 · Especificación: `docs/especificacion/marketplace.md` §4.8, §8.4

## Cuándo se abre

- El **cliente** puede abrir una disputa desde `finalizada` hasta 72 horas después.
- El **proveedor** puede abrirla por cobro pendiente, por acceso negado al lote o por una calificación abusiva.
- Una disputa por solicitud.

## Motivos

Cobertura incompleta, mala aplicación (franjas sin cubrir o doble pasada), daño a cultivos o a terceros, producto distinto al pactado, equipo ausente, hectáreas ejecutadas discutidas, precio final discutido, trato inadecuado; del lado del proveedor, cobro pendiente, acceso negado y calificación abusiva.

## Evidencia

La plataforma conserva las fotos de avance con su fecha, los registros de avance y cierre con las hectáreas, la ubicación no (se elimina al finalizar) y los mensajes. Quien abre la disputa agrega sus pruebas. Un cierre sin fotos de avance pesa en contra del proveedor.

## Proceso

1. Apertura con motivo y monto en disputa. La solicitud pasa a `disputada`.
2. Soporte pide información a la contraparte, que responde en 48 horas.
3. Un administrador resuelve: `a_favor_cliente`, `a_favor_proveedor`, `parcial` o `sin_lugar`.
4. La decisión es un registro con autor y motivo; ambas partes pueden apelar una vez en 7 días con pruebas nuevas.

## Reembolsos

| Resultado | Efecto |
|---|---|
| Cobertura incompleta | Monto ajustado proporcional a las hectáreas faltantes o repetición del trabajo sin costo |
| Mala aplicación | Repetición o ajuste hasta el 100 % |
| Daño probado | Se deriva al seguro del proveedor; Agrocom facilita el expediente |
| Equipo ausente | Ajuste total y registro en la tasa de cancelación del proveedor |
| Sin lugar | Sin cambios; si hay mala fe, se registra en la reputación del cliente |

La resolución fija un monto ajustado que pasa a ser el precio final vigente. Los reembolsos se liquidan entre las partes fuera de la app (ADR 0034).

## Represalias

Se prohíbe calificar o presionar por haber abierto una disputa; se modera y se sanciona.

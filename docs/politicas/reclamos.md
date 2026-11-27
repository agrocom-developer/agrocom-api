# Política de reclamos y reembolsos

**Agrocom SRL · Vigente** · Decisión: ADR 0033, ADR 0047 · Especificación: `docs/especificacion/marketplace.md` §4.8, §8.4

## Cuándo se abre

- El **cliente** puede abrir un reclamo desde `finalizada` hasta 72 horas después.
- El **proveedor** puede abrirlo por cobro pendiente, por acceso negado al lote o por una calificación abusiva.
- Un reclamo **de servicio** por solicitud.

## Reclamo de conducta

Es el reclamo que nace al **reportar** un mensaje, una conversación o una foto de avance (`seguimiento_y_privacidad.md`). Lo puede abrir cualquiera de las dos partes mientras el chat está abierto, sin monto y sin el plazo de 72 horas; hay uno abierto por reportante y objeto reportado. Motivos: acoso o insultos, amenazas, contenido sexual o violento, datos de terceros, estafa o pago por fuera, suplantación, otro. Soporte lee solo lo reportado (el mensaje y 10 antes y después, o los últimos 50 de la conversación) mientras el reporte está abierto, y deja constancia de cada lectura. Responde en 24 horas hábiles con `sin_lugar`, `advertencia`, `contenido_ocultado` o `cuenta_suspendida`, con motivo y autor, y la decisión se apela una vez en 7 días. El reportado no ve el nombre de quien reportó. Un reclamo de conducta no cambia el estado de la solicitud ni el precio.

## Motivos del reclamo de servicio

Cobertura incompleta, mala aplicación (franjas sin cubrir o doble pasada), daño a cultivos o a terceros, producto distinto al pactado, equipo ausente, hectáreas ejecutadas discutidas, precio final discutido, trato inadecuado; del lado del proveedor, cobro pendiente, acceso negado y calificación abusiva.

## Evidencia

La plataforma conserva las fotos de avance con su fecha, los registros de avance y cierre con las hectáreas, y los mensajes, hasta 12 meses después de que cierra el plazo de reclamo (`seguimiento_y_privacidad.md`); la ubicación no (se elimina al finalizar). Una cuenta con un reclamo abierto no puede eliminarse hasta que se resuelva (`eliminacion_de_cuenta.md`). Quien abre el reclamo agrega sus pruebas. Un cierre sin fotos de avance pesa en contra del proveedor.

## Proceso

1. Apertura con motivo y monto reclamado. La solicitud pasa a `en_reclamo`.
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

Se prohíbe calificar o presionar por haber abierto un reclamo; se modera y se sanciona.

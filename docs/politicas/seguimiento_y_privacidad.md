# Política de seguimiento, mensajes y privacidad

**Agrocom SRL · Vigente** · Decisión: ADR 0040, ADR 0041 · Especificación: `docs/especificacion/marketplace.md` §9.2

## Qué hace el piloto en la plataforma

Marca el inicio, sube fotos de su avance, marca el progreso (porcentaje u hectáreas) y cierra el servicio con las hectáreas ejecutadas. Cómo trabaja, cuándo vuela y con qué condiciones lo acuerda en persona con el cliente.

## Qué ve el cliente

Las fotos de avance, el porcentaje y las hectáreas ejecutadas, los mensajes con el piloto adjudicado y la última ubicación de la app del piloto durante la ejecución. No ve ruta, velocidad ni tiempo estimado.

## Quién ve qué

| Dato | Piloto antes de ofertar | Piloto adjudicado | Cliente | Otros pilotos |
|---|---|---|---|---|
| Lotes, hectáreas, cultivo, accesos, observaciones, estado y fotos del terreno | Sí | Sí | Sí | Sí, mientras la solicitud esté abierta |
| Nombre, teléfono, documento y dirección del cliente | No | Sí | Sí | No |
| Teléfono y documento del piloto | — | — | Solo tras adjudicar | — |
| Ofertas de otros pilotos | No | No | Sí | No |
| Fotos y avance | — | Sí | Sí | No |
| Ubicación del piloto | — | Sí (la propia) | Durante la ejecución | No |
| Mensajes | — | Sí | Sí | No |
| Documentos del proveedor (licencias, matrículas) | No | No | No | No |

## Ubicación

- Se pide solo cuando la función la necesita, explicando el motivo antes del permiso del sistema.
- Se comparte únicamente mientras la solicitud está en ejecución y la app del piloto está abierta; el piloto puede desactivarla.
- El servidor guarda solo la última posición. Se elimina al finalizar o cancelar. No se escribe en historial, bitácora, logs ni analíticas.
- La posición que el piloto envía al consultar el mapa sirve para calcular distancias y no se guarda.

## Mensajes

Solo texto, entre el cliente y el proveedor adjudicado, desde la adjudicación hasta que cierra el plazo de calificación o la disputa. Soporte puede leer una conversación solo si hay una disputa abierta. Se prohíben insultos, amenazas y datos de terceros.

## Fotos

Comprimidas a menos de 300 KB antes de subir. Las fotos de avance son de lo trabajado; no deben mostrar personas identificables ni viviendas de terceros más de lo necesario. Una foto indebida puede ocultarla un administrador.

## Retención

Los mensajes y las fotos de un servicio se conservan mientras sea posible una disputa y luego según la política de retención de la plataforma. La ubicación no se conserva.

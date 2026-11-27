# Política de seguimiento, mensajes y privacidad

**Agrocom SRL · Vigente** · Decisión: ADR 0040, ADR 0041, ADR 0045, ADR 0046, ADR 0047 · Especificación: `docs/especificacion/marketplace.md` §9.2

## Qué hace el piloto en la plataforma

Marca el inicio, sube fotos de su avance, marca el progreso (porcentaje u hectáreas) y cierra el servicio con las hectáreas ejecutadas. Cómo trabaja, cuándo vuela y con qué condiciones lo acuerda en persona con el cliente.

## Qué ve el cliente

Las fotos de avance, el porcentaje y las hectáreas ejecutadas, los mensajes con el piloto adjudicado y la última ubicación de la app del piloto durante la ejecución. No ve ruta, velocidad ni tiempo estimado.

## Quién ve qué

| Dato | Piloto antes de ofertar | Piloto adjudicado | Cliente | Otros pilotos |
|---|---|---|---|---|
| Lotes, departamento, provincia, municipio y comunidad, hectáreas, cultivo, accesos, observaciones, estado y fotos del terreno | Sí | Sí | Sí | Solo si la solicitud les es visible (política de negociación) |
| Nombre, teléfono, documento y dirección del cliente | No | Sí | Sí | No |
| Teléfono y documento del piloto | — | — | Solo tras adjudicar | — |
| Equipo del servicio: el piloto (marcado como tal) y los demás integrantes con su nombre y celular opcional | — | — | Solo tras adjudicar | — |
| Logo y nombre comercial del cliente | No | Sí | Sí | No |
| Selfie y documento de identidad de una persona | No | No | No | No |
| Ofertas de otros pilotos | No | No | Sí | No |
| Fotos y avance | — | Sí | Sí | No |
| Ubicación del piloto | — | Sí (la propia) | Durante la ejecución | No |
| Mensajes | — | Sí | Sí | No |
| Documentos del proveedor (licencias, matrículas) | No | No | No | No |

La selfie y el documento de identidad de la verificación los ve solo quien revisa, con permiso propio; las demás partes ven únicamente la insignia «Verificado». Se conservan mientras exista la cuenta; después, 12 meses si hubo antecedentes o una baja administrativa y 90 días si la persona eliminó su cuenta sin antecedentes, y luego se eliminan; solo queda un registro mínimo antifraude sin imágenes ni nombre. Cada acceso y cada eliminación quedan registrados sin el contenido (ADR 0046 y 0047; `eliminacion_de_cuenta.md`). El teléfono y el correo de respaldo de una cuenta no los ve ninguna otra parte; el teléfono del proveedor lo ve el cliente solo después de adjudicar. Los integrantes del equipo son informativos y no tienen cuenta: los carga el proveedor, que responde por esos datos, y se borran con el equipo.

## Ubicación

- Se pide solo cuando la función la necesita, explicando el motivo antes del permiso del sistema.
- **Durante la ejecución:** se comparte únicamente mientras la solicitud está en ejecución y la app del piloto está abierta; el piloto puede desactivarla. El servidor guarda solo la última posición y la elimina al finalizar o cancelar. No se escribe en historial, bitácora, logs ni analíticas.
- **En una emergencia o una incidencia:** la posición que acompaña al botón SOS o a una incidencia se guarda únicamente porque la persona pulsó el botón o reportó, nunca de forma automática ni en segundo plano, con finalidad de seguridad. La ve soporte, no la contraparte, y se elimina **90 días después de cerrar la incidencia**. En el piloto es la posición del dispositivo en ese momento; en el cliente, la ubicación del terreno del servicio, así que su app no pide permiso de ubicación. Está declarada en la política de privacidad y en las declaraciones de las tiendas (ADR 0047).
- El mapa de solicitudes no recibe la posición del piloto ni calcula distancias.

## Mensajes

Solo texto, entre el cliente y el proveedor adjudicado, desde la adjudicación hasta que cierra el plazo de calificación o el reclamo. Se prohíben insultos, amenazas, contenido sexual o violento, datos de terceros y pagos o acuerdos por fuera para estafar; un filtro básico rechaza las palabras y las direcciones web que un administrador configura.

- **Reportar.** Cada mensaje, la conversación y cada foto de avance se pueden reportar con un motivo y un detalle opcional. El reporte es un **reclamo de tipo conducta**. Al reportar, la persona acepta que soporte lea **solo lo reportado**: el mensaje y los 10 anteriores y 10 posteriores, o los últimos 50 si reporta la conversación. Esa lectura se abre con el reporte, se cierra con él y deja constancia (quién, cuándo y qué rango, sin el contenido). Sin un reclamo abierto, soporte no lee la conversación; con un reclamo de servicio abierto, puede leer el chat de esa solicitud, como antes. Primera respuesta en 24 horas hábiles.
- **Bloquear.** Cualquiera de las dos partes puede bloquear a la otra. El chat de esa solicitud se cierra para las dos y queda de solo lectura, sin decirle al bloqueado quién lo cerró. Un servicio ya adjudicado o en ejecución **sigue**: el acuerdo no se rompe por bloquear, y las partes lo cierran, lo cancelan o abren un reclamo por la vía normal. Si estaban negociando, la conversación se cierra y se libera el lugar del cliente en su bandeja. Hacia adelante, el bloqueado no ve las solicitudes de quien lo bloqueó en el mapa, no recibe sus avisos y no puede ofertarlas. Se deshace desde «Cuentas bloqueadas», sin reabrir lo cerrado.
- **Qué puede resolver soporte.** Sin lugar, una advertencia, ocultar el contenido o suspender la cuenta, siempre con motivo y autor, y con la apelación de una vez en 7 días. El reportado no recibe el nombre ni el texto de quien reportó. Nada es automático: ninguna cuenta se restringe sola por un reporte o un bloqueo, y el panel avisa al administrador cuando una cuenta suma varios bloqueos.

## Fotos

Comprimidas a menos de 300 KB antes de subir. Las fotos de avance son de lo trabajado; no deben mostrar personas identificables ni viviendas de terceros más de lo necesario. Una foto indebida se puede reportar y la puede ocultar un administrador.

## Retención

Los mensajes y las fotos de un servicio se conservan hasta 12 meses después de que cierra el plazo de reclamo y de calificación, y luego se eliminan; el precio, el avance, las hectáreas y las calificaciones agregadas quedan como registro del resultado. La ubicación del piloto no se conserva; la de una emergencia o incidencia, 90 días tras su cierre. La eliminación de una cuenta anonimiza lo demás (`eliminacion_de_cuenta.md`).

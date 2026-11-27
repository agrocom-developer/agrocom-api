# Política de eliminación de cuenta y retención de datos

**Agrocom SRL · Vigente, pendiente de revisión legal** · Decisión: ADR 0047, ADR 0046 · Especificación: `docs/especificacion/marketplace.md` §4.10 y §10 · Requisitos de las tiendas: `tiendas-y-privacidad.md` §1.5 y §1.6

Cualquier persona puede eliminar su cuenta de cliente o de proveedor, desde la app o desde una página web. Eliminar no es congelar: los datos personales se anonimizan o se borran. Solo se conserva lo que Agrocom necesita para el registro del resultado de los servicios, para defenderse en un reclamo y para prevenir el fraude, y esa lista está aquí y en la política de privacidad.

## Cómo se pide

| Vía | Quién | Cómo se confirma |
|---|---|---|
| En la app (Perfil, Privacidad y datos, «Eliminar mi cuenta») | Quien puede entrar | La contraseña o un código de 6 dígitos que llega por WhatsApp, SMS o correo (el que la persona elija) |
| Web `/eliminar-cuenta` | Quien no puede entrar a la app | Teléfono, usuario o correo y la app (cliente o piloto), más el código por el canal que elija; la respuesta es siempre la misma exista o no la cuenta |
| Oficina | Quien perdió el acceso a su correo | Un administrador compara la selfie y el documento y registra la solicitud con su nombre y motivo |

Cada cuenta se elimina por separado: eliminar la de cliente no elimina la de proveedor de la misma persona.

## Cuándo no se puede

La app y la web explican el motivo y la salida:

- Tienes un servicio adjudicado o en ejecución: ciérralo o cancélalo primero.
- Tienes un reclamo abierto: espera a que se resuelva.
- Tienes una incidencia grave o de emergencia abierta.
- Eres el encargado de una empresa con pilotos vinculados: libéralos o pide a un administrador el cambio de encargado.
- Tu app tiene registros del lote sin sincronizar: sincronízalos antes (la app lo exige).

Un servicio ya finalizado no lo impide; si la contraparte abre un reclamo, la eliminación espera a que se resuelva. Las cuentas que aún no fueron aprobadas se eliminan sin condiciones.

## Qué pasa al pedirla

1. Se cierran tus sesiones. Tus **solicitudes abiertas se cancelan** y tus **ofertas vigentes se retiran**; quienes tenían una conversación contigo reciben el aviso de siempre y el cupo del cliente se libera. Eso no se deshace.
2. Tienes **7 días** para arrepentirte: entras a la app, ves el aviso y eliges «Conservar mi cuenta». En ese periodo la cuenta no opera.
3. Pasado ese tiempo la eliminación se ejecuta y recibes un correo de confirmación. El plazo máximo desde tu pedido es de 30 días.

## Qué se elimina y qué se conserva

| Dato | Qué pasa |
|---|---|
| Nombre, usuario, teléfono, correo, contraseña, foto de perfil, logo, tokens y avisos | Se anonimizan o se borran; el teléfono y el correo quedan libres |
| Datos de empresa personales, contactos de referencia, documentos y fotos del dron, integrantes del equipo, terrenos sin servicio vigente | Se borran |
| Mensajes que escribiste | Se borra el texto; los de la otra parte siguen en su historial hasta que vence la retención del servicio |
| Calificaciones | Se conservan el puntaje y las etiquetas sin tu nombre; el comentario se borra |
| Servicios, ofertas, precio acordado, avance, cierre y reclamos cerrados | Se conservan como registro del resultado; apareces como «Cuenta eliminada». En los servicios de un cliente se borran el contorno de los lotes, las fotos del terreno, los accesos, las observaciones, el municipio y la comunidad |
| Selfie y documento de identidad | Se conservan aislados y se destruyen: a los **90 días** si no tuviste antecedentes; a los **12 meses** si los tuviste |
| Registro mínimo antifraude | Un código derivado de tu documento con una clave del servidor, el motivo y la fecha; sin imágenes ni nombre. Dura 12 meses sin antecedentes y 36 con ellos |
| Bitácora de auditoría | Se conserva quién hizo qué y cuándo; los valores con datos personales se redactan |
| Respaldos | Rotan en 30 días |

**Antecedentes** son: una suspensión, un rechazo por fraude o falsedad documental, una incidencia grave o de emergencia con tu responsabilidad, un reclamo resuelto con tu responsabilidad, o una baja administrativa. Solo para ellos la retención es de 12 meses. Lo conservado no se usa para otro fin, no se vende ni se comparte, y lo ve solo quien tiene permiso, dejando constancia de cada acceso.

## Retención del servicio

Los mensajes, las fotos de avance y los mensajes de un reclamo se conservan hasta 12 meses después de que cierra el plazo de reclamo y de calificación, y luego se eliminan. El precio, el avance, las hectáreas y las calificaciones agregadas quedan como registro del resultado.

## Si vuelves

Una persona que eliminó su cuenta puede registrarse de nuevo y empieza de cero: ni la reputación ni los servicios anteriores se recuperan. Si su documento tiene un registro con antecedentes, un administrador lo revisa antes de aprobar; no hay sanciones automáticas.

## Auditoría y parámetros

Cada paso deja un evento sin datos personales (`cuenta.eliminacion_solicitada`, `cuenta.eliminacion_cancelada`, `cuenta.eliminacion_pospuesta`, `cuenta.eliminada`, `verificacion.purgada`). Parámetros: `eliminacion.gracia_dias` (7), `eliminacion.plazo_dias` (30), `verificacion.retencion_meses_tras_baja` (12), `verificacion.retencion_eliminacion_dias` (90), `verificacion.retencion_rechazadas_dias` (90), `antifraude.registro_meses_sin_antecedentes` (12), `antifraude.registro_meses_con_antecedentes` (36), `retencion.servicio_meses` (12), `retencion.aceptaciones_meses` (36) y `respaldos.retencion_dias` (30).

## Por qué el borrado lógico y los respaldos de retiro no sirven

Una fila con el nombre y el teléfono marcada como borrada sigue siendo el dato, y un esquema `respaldo_*` es otra copia del dato. Por eso los datos personales de una cuenta eliminada se anonimizan en el lugar o se borran físicamente (excepción a los invariantes 8 y 15 de `CLAUDE.md`, ADR 0047), y los respaldos de la base y de los archivos rotan en 30 días. Una restauración reaplica las eliminaciones ejecutadas desde un registro que vive fuera de la base.

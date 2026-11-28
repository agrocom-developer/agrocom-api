# Glosario del proyecto

**Agrocom SRL · Documento oficial vigente**

Referencia rápida de vocabulario, de negocio y técnico, para que cualquier persona o agente de IA entienda un término sin leer toda la especificación. Cuando un término tiene definición formal en otro documento, se referencia en vez de repetirla.

## Marketplace

| Término | Definición | Referencia |
|---|---|---|
| **Marketplace** | Plataforma donde clientes publican solicitudes de fumigación con drones y proveedores ofertan; el precio se negocia. Agrocom es el nexo y el registro del resultado | `especificacion/marketplace.md` |
| **Cliente** | Tipo de cuenta de app: quien publica la solicitud, elige la oferta, sigue el avance y califica; persona o empresa; debe estar aprobado para publicar y aceptar | especificación §2; ADR 0046 |
| **Cuenta de panel** | Cuenta del personal de Agrocom; tiene un solo rol: Administrador (el rol del sistema) u otro rol del panel (Operador o uno creado en Roles) | ADR 0046 |
| **Cuenta de app** | Cuenta de una persona en una de las dos apps; es de cliente o de proveedor, sin roles ni conversión entre tipos; con su propia verificación y aprobación | ADR 0046 |
| **Administrador** | Rol del panel que ve todo: verifica y aprueba, atiende, modera y configura | ADR 0046 |
| **Operador** | Rol del panel que ve las partes de algunos módulos que el administrador le asigna | ADR 0046 |
| **Persona** (`sec_personas`) | Persona real dueña de una o dos cuentas de app, identificada por su documento (único): nombres, apellidos y documento. Tiene a lo sumo una cuenta de cliente y una de proveedor | ADR 0046 |
| **Teléfono de la cuenta** | Dato de referencia para entrar a una cuenta de app; único por tipo de cuenta (el mismo teléfono puede estar en una cuenta de cliente y una de proveedor); no prueba posesión hasta que exista un código por SMS o WhatsApp | ADR 0046 |
| **Correo de respaldo** | Correo de la cuenta de app al que llegan los códigos para recuperar el acceso y cambiar el teléfono | ADR 0046 |
| **Recuperar mi acceso** | Pantalla de la app que, con un código enviado por WhatsApp, SMS o al correo de respaldo (el que la persona elige), permite fijar una contraseña nueva y ver el usuario; no hay página web de restablecimiento | ADR 0046 |
| **Aceptación de términos y privacidad** | Registro por cuenta de la versión y la fecha de los términos y de la privacidad que aceptó al registrarse | ADR 0046 |
| **Contador de solicitudes abiertas** | Número que ve el proveedor no aprobado en lugar del mapa, filtrable por departamento, para saber cuántos trabajos no puede ver todavía | ADR 0045, 0046 |
| **Encargado de la empresa** | Titular de un proveedor tercero, con su propia cuenta; gestiona el vínculo de los pilotos de la empresa y puede liberarlos | ADR 0046 |
| **Vínculo de piloto** | Relación entre la cuenta de un piloto y una empresa, con el consentimiento de ambos; el encargado la libera para que el piloto trabaje por su cuenta | ADR 0046 |
| **Verificación de identidad** | Selfie tomada con la cámara y documento (carnet de identidad, licencia de conducir o pasaporte) que cada cuenta de app necesita para ser aprobada | ADR 0046; `politicas/verificacion_proveedores.md` |
| **Selfie** | Foto del rostro tomada en el momento con la cámara de la app, distinta de la foto de perfil; solo la ve quien revisa, con permiso; se conserva mientras exista la cuenta y después 12 meses si hubo antecedentes o 90 días si la persona eliminó su cuenta sin antecedentes | ADR 0046, 0047 |
| **Cargada por administrador** | Marca de una verificación que el administrador creó desde el panel; si no hay documento de la persona, lleva un archivo marcador generado por el sistema y el motivo | ADR 0046 |
| **Eliminación de cuenta** | Lo que pide la persona para dejar de existir en Agrocom: anonimiza sus datos personales en el lugar tras 7 días de gracia; no es la baja administrativa ni un congelamiento | ADR 0047; `politicas/eliminacion_de_cuenta.md` |
| **Anonimización** | Reemplazar o borrar los datos personales de una cuenta eliminada (nombre, teléfono, correo, foto, documento, textos), conservando servicios, ofertas y calificaciones sin datos identificables | ADR 0047 |
| **Antecedentes** | Suspensión, rechazo por fraude o falsedad documental, incidencia grave o reclamo con responsabilidad de la cuenta, o baja administrativa; alargan a 12 meses la retención de la selfie y el documento | ADR 0047 |
| **Registro mínimo antifraude** | Lo único que queda de una cuenta eliminada: una huella del documento con clave del servidor, el motivo y la fecha, sin imágenes ni nombre | ADR 0047 |
| **Reclamo de conducta** | Reclamo que nace al reportar un mensaje, una conversación o una foto; sin monto; soporte lee solo lo reportado | ADR 0047; `politicas/reclamos.md` |
| **Reportar** | Avisar a soporte de un mensaje, una conversación o una foto que incumple la política de conducta, con un motivo | ADR 0047 |
| **Bloquear** | Cerrar el chat y la negociación con la otra parte y dejar de verse en el mapa y en los avisos; un servicio adjudicado sigue | ADR 0047 |
| **Ubicación de emergencia** | Posición que acompaña a un SOS o una incidencia; se guarda 90 días después de cerrar la incidencia | ADR 0047 |
| **Solicitud de servicio** | Pedido del cliente sobre sus lotes: hectáreas, tipo de aplicación, producto, fecha deseada y precio por hectárea propuesto. Es la unidad de negocio | especificación §4.2; ADR 0027 |
| **Terreno** | Superficie del cliente con geometría y hectáreas, sobre la que se pide un servicio | especificación §4.1 |
| **Proveedor** | Entidad que presta el servicio: propio, independiente o tercero | ADR 0028 |
| **Proveedor propio** | Agrocom como proveedor, con sus pilotos y drones; compite como cualquier otro | ADR 0028 |
| **Proveedor independiente** | Piloto que es su propio proveedor; se registra solo (o lo registra la oficina) y lo aprueba un administrador | ADR 0028, 0032, 0046 |
| **Proveedor tercero** | Empresa o dueño de drones con pilotos a su cargo | ADR 0028 |
| **Piloto** | Persona que opera el dron, ve el mapa de solicitudes abiertas, ofrece y marca el avance | especificación §2 |
| **Dron (vehículo)** | Activo que presta el servicio, con dueño y operador; requiere aprobación | ADR 0029 |
| **Dueño del dron** | Proveedor que posee el dron, aunque lo opere otro | ADR 0029 |
| **Equipo** | Combinación operativa de piloto y dron que ejecuta una solicitud | especificación §4.4 |
| **Integrantes del equipo del servicio** | Personas que el proveedor anota, de forma opcional y solo como información, junto al piloto (nombre y celular opcional); sin cuenta ni verificación; el cliente las ve solo tras adjudicar | ADR 0046 |
| **Mapa de solicitudes abiertas** | Vista del piloto con las solicitudes `abierta` y `con_ofertas` que le son visibles, con filtros por departamento, hectáreas, precio, fecha y cultivo; el proveedor aún no aprobado ve solo un contador de solicitudes abiertas | ADR 0041, 0045 |
| **Departamento y provincia** | Ubicación de la solicitud, elegida de un catálogo oficial de Bolivia, de solo lectura (el departamento es el único filtro geográfico); el municipio y la comunidad son texto libre. No hay zonas, regiones, radio ni distancia | ADR 0045 |
| **Departamentos donde trabaja** | Los departamentos (uno o más, al menos uno) que un proveedor declara en su cuenta; una solicitud solo le llega, y solo la ve en su mapa, si es de uno de ellos o si el cliente la amplió. En una empresa los define el encargado | ADR 0045 (punto 47) |
| **Alcance de la solicitud** | `departamento` (se ofrece a los proveedores que trabajan en su departamento) o `ampliado` (el cliente autorizó ofrecerla también en otros departamentos). Solo lo cambia el cliente, y de forma reversible hasta adjudicar | ADR 0045 (punto 48) |
| **Buscar también en otros departamentos** | Acción del cliente que amplía el alcance de su solicitud a los demás departamentos; típicamente cuando está «Esperando piloto». Ni el sistema, ni la urgencia, ni un administrador la activan | ADR 0045 (punto 48) |
| **En otro departamento** | Indicación que ve el proveedor en el mapa, el detalle y el aviso de una solicitud ampliada cuyo departamento no es de los que trabaja; no necesita estar allí y el viaje se negocia | ADR 0045 (punto 49) |
| **Criterio de alcance (filtro)** | Regla que decide si un proveedor es alcanzable por una solicitud; hay del sistema (entre ellos `departamento`), del cliente y del proveedor, y la lista crece sin migraciones | ADR 0045 |
| **Oleada de aviso** | Aviso de una nueva solicitud por anillos de prioridad («sistema solar»): primero a quienes cumplen todos los filtros (propios y mejor calificados primero) y, pasado un intervalo, a los nuevos que cumplen tras relajar un filtro; a nadie dos veces por ciclo | ADR 0045 |
| **Súper ola** | Último paso de la escalera (o único paso de un aviso forzado por el administrador): aviso a todos los elegibles del alcance autorizado (aprobados, disponibles, que trabajan en el departamento de la solicitud o en cualquiera si el cliente amplió, y que no excluyeron la solicitud con sus filtros); nunca a quien está ocupado o en trabajo, y no abre sola a otros departamentos | ADR 0045 |
| **Esperando piloto** | Etapa de la solicitud a la que nadie aceptó tras la súper ola; no se desactiva ni vence, y el cliente la relanza a mano o por su horario, o busca también en otros departamentos; al cliente solo se le avisa cuánto lleva sin respuesta (nombre visible confirmado) | ADR 0045 |
| **Relanzamiento** | Ciclo nuevo de oleadas desde el primer anillo; lo pide el cliente o lo hace el sistema según los días y la hora que el cliente eligió (mínimo 2 días por semana, a lo más uno por día) | ADR 0045 |
| **Escalera de oleadas** | Lista ordenada de pasos que dice qué filtro se relaja en cada oleada | ADR 0045 |
| **Aceptación** | Primera oferta de un proveedor sobre una solicitud (acepta el precio o contraoferta); llega a la bandeja del cliente o espera en la cola | ADR 0045 |
| **Bandeja** | Aceptaciones que el cliente ve y puede responder («Tienes N aceptaciones nuevas»); nunca más que el cupo a la vez | ADR 0045 |
| **Cupo de negociaciones** | Cuántos proveedores conversan a la vez con el cliente en una solicitud (`cupo_negociaciones`, 3 por defecto) | ADR 0045 |
| **Cola** | Aceptaciones que superan el cupo o cuyo proveedor no está disponible; el cliente no las ve ni sabe cuántas son; se ordenan por propios, calificación y llegada y pasan a la bandeja cuando se libera un lugar | ADR 0045 |
| **Casilla** | Lugar de la bandeja que ocupa una negociación `en_bandeja` o `en_conversacion`; hay tantas como el cupo | ADR 0045 |
| **En acuerdo** | Estado de la solicitud que el cliente marca cuando la conversación llega a un punto sólido: ya no se avisa a más pilotos y la cola se congela; se puede volver a buscar (el término «tomada» no se usa) | ADR 0045 |
| **Solicitud urgente** | Solicitud que el cliente declara urgente porque pisa una ventana agronómica que se pierde (siembra, cosecha, plaga); lleva una fecha límite obligatoria y un motivo opcional. La declara el cliente, nunca el sistema. Para el piloto cambia la insignia, el orden y la frecuencia de los avisos; no lleva recargo | ADR 0045 (punto K) |
| **Fecha límite** | Día después del cual el servicio ya no sirve al cliente urgente; es dura y distinta de la fecha deseada (una preferencia). Pasada sin trato, la solicitud no se cancela: deja de recibir olas y queda «Esperando piloto» | ADR 0045 (puntos 38 y 42) |
| **Motivo de urgencia** | Causa opcional de la urgencia: plaga, siembra, cosecha, clima u otro | ADR 0045 (punto 37) |
| **Perfil de la oleada** | `normal` o `urgente`; fija por ciclo el intervalo T, la escalera y el relanzamiento por defecto (los de urgencia son más cortos) | ADR 0045 (punto 40) |
| **Avisar ahora a todos** | Acción manual del administrador, con motivo y en la bitácora, que fuerza una súper ola en un ciclo propio y dentro del alcance autorizado | ADR 0045 (punto 43) |
| **Recibir solicitudes urgentes** | Filtro del proveedor (`recibir_urgentes`): si lo apaga no recibe avisos de urgentes; nunca se relaja en las oleadas | ADR 0045 (punto 39) |
| **Disponibilidad** | Interruptor manual de cada cuenta de proveedor (`disponible`, `ocupado`, `en_trabajo`); un piloto de una empresa tiene el suyo y el encargado puede marcarlo; los dos últimos estados no reciben avisos | ADR 0045 |
| **Oferta que no cumple** | Oferta de un equipo que no cumple un requisito del cliente (p. ej. la capacidad del dron); se marca y el cliente decide | ADR 0045 |
| **Oferta** | Precio por hectárea que una parte propone en una negociación, con vencimiento; se acepta, se contraoferta o se rechaza | especificación §4.5, §5.3 |
| **Total estimado** | Hectáreas por precio por hectárea (`monto_estimado`); lo calcula el servidor con decimal exacto y ninguna parte lo edita | ADR 0049 |
| **Justificación del precio** | Texto opcional de hasta 500 caracteres con que una parte explica su precio (`justificacion_precio`); cada parte escribe el suyo y la contraparte lo ve | ADR 0049 |
| **Comparador** | Lista que el servidor arma al cliente con las ofertas encontradas para su solicitud, ordenable (`orden`) y filtrable por precio, total, calificación, tipo de proveedor y capacidad del dron; no es un directorio de pilotos | ADR 0045, 0049 |
| **Negociación** | Conjunto de ofertas entre el cliente y un proveedor sobre una solicitud; hay una sola oferta vigente y un máximo de ofertas; es asíncrona (cada quien responde cuando puede) y pasa por `en_cola`, `en_bandeja` y `en_conversacion` | ADR 0030, 0045 |
| **Adjudicación** | Aceptar la oferta vigente de la contraparte: fija el precio acordado, el proveedor y el equipo y cierra las demás negociaciones | ADR 0030 |
| **Precio por hectárea** | Precio que el cliente propone y que se negocia; se congela al adjudicar como precio acordado. No hay precio de referencia | ADR 0030, 0034, 0045 |
| **Precio final** | Hectáreas ejecutadas por precio acordado; es el monto a pagar, fuera de la app | ADR 0034 |
| **Avance** | Registro del piloto con hectáreas acumuladas o porcentaje, junto con fotos; lo ve el cliente | ADR 0040 |
| **Mensajes** | Chat de texto entre el cliente y el proveedor adjudicado | ADR 0040 |
| **Ubicación del piloto** | Última posición de la app del piloto durante la ejecución; se elimina al finalizar | ADR 0040 |
| **Preferencias de notificación** | Categorías de aviso que cada cuenta activa o desactiva; para las nuevas solicitudes, los filtros del proveedor (hectáreas mínimas, tipos de aplicación que no quiere y solicitudes urgentes; los departamentos donde trabaja son su alcance, no un filtro de aviso) | ADR 0041, 0045 |
| **Evidencia** | Foto comprimida (menos de 300 KB) que respalda un avance, una incidencia o un reclamo | especificación §4.6 |
| **Calificación** | Valoración de 1 a 5 estrellas con etiquetas que el cliente da a un proveedor; el proveedor la ve y un administrador la modera | ADR 0031 |
| **Moderación** | Ocultamiento con motivo y autor de una calificación que incumple la política | `politicas/calificacion.md` |
| **Reclamo** | Queja sobre un servicio ya prestado que abre un cliente o un proveedor y atiende un administrador, con evidencia y un monto ajustado | ADR 0033 |
| **Incidencia** | Evento anómalo (deriva, caída, lesión, derrame, conflicto) reportado durante o tras un servicio | `politicas/seguridad.md` |
| **SOS** | Botón de emergencia que avisa a soporte con la ubicación (la del dispositivo en el piloto; la del terreno en el cliente) | `politicas/seguridad.md`; ADR 0047 |
| **Tasa de cancelación** | Proporción de servicios adjudicados que cancela una parte; es visible y alimenta alertas, no sanciones automáticas | ADR 0033 |
| **Suspensión** | Retiro de la cuenta por documentos vencidos, incidencia grave o decisión de un administrador | `politicas/verificacion_proveedores.md` |
| **Ciclos de batería** | Contador conservado dentro del dron | ADR 0029 |
| **Deriva** | Arrastre de la gota fuera del lote por viento; riesgo para vecinos y colmenas | `politicas/seguridad.md` |
| **Tanque del dron** | Depósito líquido del dron; su capacidad se muestra en la oferta | especificación §4.4 |

## Técnico

| Término | Definición | Referencia |
|---|---|---|
| **`uuid_cliente`** | Identificador único generado en el dispositivo antes de sincronizar; garantiza idempotencia | especificación §10.3 |
| **Outbox** | Cola local donde toda escritura offline se encola antes de sincronizar | especificación §13 |
| **Idempotencia** | Reintentar la misma operación no cambia el resultado; se logra con `UNIQUE (uuid_cliente)` | ADR 0001 |
| **`secuencia`** | Contador local de cada dispositivo que ordena sus registros; el orden causal no usa el reloj | especificación §4.6 |
| **Flavor** | Variante de la app móvil: `cliente` o `piloto` | especificación §13 |
| **`sec_permission`** | Código de permiso abstracto que el menú referencia | ADR 0004 |
| **Policy** | Clase de Laravel que responde si un usuario puede actuar sobre un registro concreto | ADR 0004 |
| **ADR** | Documento corto en `docs/decisiones/` con contexto, decisión, alternativas y consecuencias | `README.md` |
| **Parámetro** | Valor de negocio configurable sin desplegar | ADR 0016 |
| **`feature/*`** | Rama de GitFlow simplificado para una tarea | ADR 0006 |
| **Soft delete** | Borrado lógico (`deleted_at`) | ADR 0007 |
| **Bitácora (de auditoría)** | Registro transversal de quién hizo qué, cuándo y sobre qué | ADR 0007 |
| **Atomic Design** | Metodología de componentes del panel Blade | ADR 0002 |
| **Token (de diseño)** | Variable CSS que representa un color, espaciado o tipografía; nunca se hardcodea un valor | ADR 0002 |

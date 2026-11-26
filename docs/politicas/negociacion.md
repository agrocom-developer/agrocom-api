# Política de negociación de ofertas

**Agrocom SRL · Vigente** · Decisión: ADR 0030, ADR 0038, ADR 0045 (revisión del 27/11/2026) · Especificación: `docs/especificacion/marketplace.md` §6 · Reglas exactas: `docs/especificacion/negociacion-contrato.md`

## Principios

- El precio lo acuerdan el cliente y el piloto; Agrocom es el nexo y el registro.
- El cliente decide con información: precio, calificación y dron. La distancia no se calcula: se acuerda en la negociación.
- El piloto decide con información: ve el estado de la propiedad y los lotes antes de ofertar.
- Nadie queda obligado por una oferta que no aceptó.

## Cómo se negocia

La negociación es **asíncrona**, como una bandeja de entrada y no como un chat en vivo: el cliente envía la solicitud a las 4 de la tarde, nadie responde en ese momento, un piloto responde de noche y el cliente contesta al día siguiente. El sistema procesa y envía igual; cada quien responde cuando puede.

1. El cliente publica la solicitud con un precio por hectárea, el departamento y la provincia del terreno y, si quiere, requisitos como la capacidad mínima del dron. Queda abierta. El sistema avisa a los proveedores por oleadas y la solicitud es visible para los pilotos habilitados que le corresponde (ver «Quién recibe el aviso»). No tiene vencimiento: sigue en el mercado hasta que se adjudica o el cliente la cancela.
2. Un piloto acepta el precio o propone el suyo, con el dron que usará. Esa es su **aceptación** (su primera oferta) y llega a la **bandeja de entrada** del cliente (ver «Bandeja, cupo y cola»). Abrir la pantalla de precios sin enviar nada no cambia nada. Que un piloto acepte **no oculta** la solicitud a los demás: sigue ofertándose hasta que el cliente la marque «En acuerdo».
3. El cliente puede aceptar, contraofertar o rechazar cada aceptación. Si contraoferta, el piloto puede aceptar, contraofertar o rechazar, y así por rondas. Hay una sola oferta vigente por negociación (una negociación es la de un piloto con el cliente sobre esa solicitud).
4. El máximo de ofertas por negociación es 5; la última es definitiva y solo se acepta o se rechaza.
5. Cada oferta vence a las 72 horas desde que llega a la bandeja del cliente (parámetro, admite horas, días o semanas; la negociación es asíncrona y el cliente puede contestar al día siguiente). Una aceptación en cola no vence por tiempo. La solicitud no vence: puede esperar semanas hasta cerrar trato y solo la cancela el cliente (o se adjudica). Puede llevar una fecha deseada de fumigación, hasta un año adelante. Mientras nadie acepta, el cliente recibe un aviso periódico que le dice cuánto tiempo lleva sin respuesta y le ofrece modificarla o retirarla; a los pilotos no se les avisa nada de eso.
6. Cuando una parte acepta la oferta vigente de la otra, la solicitud queda adjudicada con ese precio. Las demás negociaciones (también las que esperaban en la cola) se cierran y sus pilotos reciben el aviso.

## Qué puede y qué no puede hacer cada parte

| Parte | Puede | No puede |
|---|---|---|
| Cliente | Cambiar el precio propuesto mientras no está adjudicada; elegir libremente; rechazar o cerrar una conversación sin motivo; marcar la solicitud «En acuerdo»; cancelar | Ver el precio de la oferta de un piloto a otro; adjudicar a una oferta vencida; recibir más aceptaciones que el cupo a la vez; ver la cola |
| Piloto | Ofertar sobre cualquier solicitud abierta que le sea visible; retirar su oferta vigente | Ofertar sin estar aprobado y sin un dron aprobado con fotos; ver los datos de contacto antes de adjudicar; ofertar de nuevo tras un rechazo del cliente en esa solicitud; ver su posición en la cola ni las ofertas de otros pilotos |
| Encargado de una empresa | Ofertar por la empresa; delegar la oferta a pilotos concretos (apagada por defecto); marcar a sus pilotos como ocupados | Ofertar dos veces sobre la misma solicitud con la misma empresa |
| Piloto de una empresa | Ver el mapa; ofertar solo si el encargado le delegó la oferta | Ofertar sin delegación |
| Administrador | Ver todas las negociaciones; cancelar una solicitud con motivo | Adjudicar por el cliente ni cambiar un precio |

## Sin penalidades automáticas

Rechazar, retirar o dejar vencer una oferta no tiene consecuencia. Lo que sí queda registrado y visible es la cancelación tras adjudicar (política de cancelación).

## Conducta

Los mensajes y las ofertas respetan a la contraparte. Pactar por fuera de la plataforma lo ya adjudicado no está prohibido, pero la calificación y el reclamo solo cubren lo registrado en ella. Un precio claramente fuera de lo razonable puede ocultarlo un administrador.

## Quién recibe el aviso

- No hay zonas ni límite geográfico. La solicitud lleva su departamento y su provincia, y cualquier piloto habilitado decide si quiere ir; la distancia se acuerda en la negociación.
- **Primera ola.** Al publicar, el sistema avisa a los proveedores que cumplen **todos** los filtros: los del sistema (aprobado con equipo apto, disponible, calificación), los del cliente (como la capacidad mínima del dron) y los del proveedor, que él ajusta en su app (departamentos que no acepta, hectáreas mínimas, tipos de aplicación que no quiere). La lista de filtros puede crecer. Los propios de Agrocom entran desde la primera ola.
- **Anillos.** Pasado un tiempo sin que nadie acepte ni contraoferte, se relanza la ola saltando a quienes ya la recibieron: se relaja un filtro (primero la calificación mínima, luego el requisito de dron del cliente) y se avisa solo a los nuevos que cumplen. Nadie recibe dos veces el mismo aviso dentro de un ciclo.
- **Súper ola.** Cuando ya no quedan filtros por relajar, la solicitud se avisa a todos los elegibles y se abre a todos en el mapa. Nunca se avisa a quien está `ocupado` o `en_trabajo`, ni se relajan los filtros que el proveedor puso.
- **Esperando piloto.** Si nadie acepta, la solicitud queda «Esperando piloto» (nombre confirmado por el dueño). El sistema no la desactiva ni la vence. El cliente puede ajustar sus parámetros y volver a lanzar la ola, o retirarla. Si no lo hace a mano, el sistema la relanza según el horario que el cliente eligió: al menos 2 días por semana, a lo más una vez por día, a la hora que prefiera. Cada relanzamiento vuelve a avisar desde el primer anillo. Las olas y los relanzamientos siguen mientras el cliente no marque la solicitud «En acuerdo»: una aceptación no las detiene. A los pilotos con quienes el cliente conversa, o que ya aceptaron, o a los que el cliente rechazó, no se les vuelve a avisar esa solicitud.
- **Disponibilidad.** Cada cuenta de proveedor tiene un interruptor «Disponible», «Ocupado» o «En trabajo»; quien no está disponible no recibe avisos y, si envía una oferta, esta espera en la cola hasta que vuelva a «Disponible»; puede seguir viendo el mapa. Un piloto de una empresa tiene el suyo y el encargado de la empresa puede marcarlo ocupado. Al volver a «Disponible» no recibe avisos atrasados.
- **Avisos sin push.** Mientras no haya notificaciones push, los avisos se ven dentro de la app (campana y lista) y el tiempo entre olas es más largo; el modelo es el mismo.
- **Proveedor aún no aprobado.** No ve el mapa ni ofrece. Ve solo cuántas solicitudes abiertas hay, que puede filtrar por departamento, sin lotes, clientes ni ubicación detallada.
- Quien tiene un dron que no cumple lo que pidió el cliente puede ofertar: la oferta se marca y el cliente decide.
- Nadie tiene prioridad al adjudicar. La prioridad es solo en el aviso y en el orden sugerido de las ofertas (propios de Agrocom primero, luego calificación); el cliente elige y puede ordenar como quiera.

## Bandeja, cupo y cola

- **Bandeja.** Cada aceptación llega a la bandeja de entrada del cliente («Tienes 2 aceptaciones nuevas»). El cliente la abre, y puede aceptar, contraofertar o rechazar.
- **Cupo.** El cliente conversa con hasta 3 pilotos a la vez por solicitud (parámetro; nadie conversa con más de 4). Si aceptan más pilotos, los excedentes esperan **en cola** sin avisarle al cliente. La cola se ordena por prioridad: primero los pilotos propios de Agrocom, luego los mejor calificados y luego por orden de llegada. La prioridad solo decide qué aceptación ve antes el cliente; no da ventaja al adjudicar.
- **Liberación.** Cuando el cliente rechaza o cierra una conversación (o el piloto retira su oferta, o esta vence), pasan a su bandeja las siguientes de la cola, hasta completar el cupo. Antes de pasar a cada una se revisa que el piloto siga disponible (que no esté «Ocupado» ni «En trabajo») y que la solicitud siga vigente; a quien no está disponible se le salta sin descartarlo. Al cliente nunca le llegan más aceptaciones que el cupo: tiene que rechazar o cerrar una para recibir nuevas.
- **Lo que ve el piloto.** Solo lo suyo: «Tu oferta está en cola» o «El cliente la está viendo» o «En conversación». Nunca su posición en la cola, cuántos esperan, quiénes son ni los precios de otros.
- **Lo que ve el cliente.** Solo lo que está en su bandeja o en conversación. Nunca la cola ni cuántos esperan.

## Solicitud «En acuerdo»

Cuando la conversación con un piloto llega a un punto sólido, el cliente puede marcar la solicitud como **«En acuerdo»**. Desde ese momento ya no se avisa a más pilotos, la solicitud sale del mapa de quienes no están en conversación y las aceptaciones que esperaban en la cola quedan quietas. El cliente sigue negociando con quienes ya tiene. Puede **volver a buscar** cuando quiera, y si la última conversación se cae sin acuerdo, la solicitud vuelve sola a buscar piloto y se le avisa. Aceptar la oferta vigente de la contraparte adjudica la solicitud como siempre. Un piloto que reaparece disponible días después puede aceptar mientras la solicitud no esté en acuerdo: entra a la cola o a la bandeja según el cupo.

## Una empresa ofrece por su encargado

En una empresa con pilotos, la oferta la envía el encargado. Puede delegarla a pilotos concretos (opción apagada por defecto). Un piloto sin delegación ve el mapa pero no ofrece. Los avisos de nuevas solicitudes llegan al encargado y a los pilotos con delegación.

## Precios

No hay precio de referencia: el precio lo ponen el cliente y el proveedor. El sistema solo rechaza un precio por encima de un máximo de sanidad, para evitar errores de digitación.

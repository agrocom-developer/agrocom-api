# Política de negociación de ofertas

**Agrocom SRL · Vigente** · Decisión: ADR 0030, ADR 0038, ADR 0045 · Especificación: `docs/especificacion/marketplace.md` §6 · Reglas exactas: `docs/especificacion/negociacion-contrato.md`

## Principios

- El precio lo acuerdan el cliente y el piloto; Agrocom es el nexo y el registro.
- El cliente decide con información: precio, calificación y dron. La distancia no se calcula: se acuerda en la negociación.
- El piloto decide con información: ve el estado de la propiedad y los lotes antes de ofertar.
- Nadie queda obligado por una oferta que no aceptó.

## Cómo se negocia

1. El cliente publica la solicitud con un precio por hectárea, el departamento y la provincia del terreno y, si quiere, requisitos como la capacidad mínima del dron. Queda abierta. El sistema avisa a los proveedores por oleadas y la solicitud es visible para los pilotos habilitados que le corresponde (ver «Quién recibe el aviso»). No tiene vencimiento: sigue en el mercado hasta que se adjudica o el cliente la cancela.
2. Un piloto acepta el precio o propone el suyo, con el dron que usará. Esa es su primera oferta y, desde que se registra, la solicitud pasa a estar «En negociación» (ver más abajo). Abrir la pantalla de precios sin enviar nada no cambia nada.
3. El cliente puede aceptar, contraofertar o rechazar. Si contraoferta, el piloto puede aceptar, contraofertar o rechazar, y así por rondas. Hay una sola oferta vigente por negociación.
4. El máximo de ofertas por negociación es 5; la última es definitiva y solo se acepta o se rechaza.
5. Cada oferta vence a las 24 horas (parámetro, admite días o semanas). La solicitud no: puede esperar semanas hasta cerrar trato y solo la cancela el cliente (o se adjudica). Puede llevar una fecha deseada de fumigación, hasta un año adelante. Mientras no hay trato, el cliente recibe un recordatorio periódico para que ajuste el precio o los requisitos, o la cancele.
6. Cuando una parte acepta la oferta vigente de la otra, la solicitud queda adjudicada con ese precio. Las demás negociaciones se cierran y sus pilotos reciben el aviso.

## Qué puede y qué no puede hacer cada parte

| Parte | Puede | No puede |
|---|---|---|
| Cliente | Cambiar el precio propuesto mientras no está adjudicada; elegir libremente; rechazar sin motivo; cancelar | Ver el precio de la oferta de un piloto a otro; adjudicar a una oferta vencida |
| Piloto | Ofertar sobre cualquier solicitud abierta de su alcance; retirar su oferta vigente | Ofertar sin estar aprobado y sin un dron aprobado con fotos; ver los datos de contacto antes de adjudicar; ofertar de nuevo tras un rechazo del cliente en esa solicitud |
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
- **Esperando piloto.** Si nadie acepta ni contraoferta, la solicitud queda «Esperando piloto». El sistema no la desactiva ni la vence. El cliente puede ajustar sus parámetros y volver a lanzar la ola, o cancelarla. Si no lo hace a mano, el sistema la relanza según el horario que el cliente eligió: al menos 2 días por semana, a lo más una vez por día, a la hora que prefiera. Cada relanzamiento vuelve a avisar desde el primer anillo.
- **Disponibilidad.** Cada cuenta de proveedor tiene un interruptor «Disponible», «Ocupado» o «En trabajo»; quien no está disponible no recibe avisos, pero puede seguir viendo el mapa. Un piloto de una empresa tiene el suyo y el encargado de la empresa puede marcarlo ocupado. Al volver a «Disponible» no recibe avisos atrasados.
- **Avisos sin push.** Mientras no haya notificaciones push, los avisos se ven dentro de la app (campana y lista) y el tiempo entre olas es más largo; el modelo es el mismo.
- **Proveedor aún no aprobado.** No ve el mapa ni ofrece. Ve solo cuántas solicitudes abiertas hay, que puede filtrar por departamento, sin lotes, clientes ni ubicación detallada.
- Quien tiene un dron que no cumple lo que pidió el cliente puede ofertar: la oferta se marca y el cliente decide.
- Nadie tiene prioridad al adjudicar. La prioridad es solo en el aviso y en el orden sugerido de las ofertas (propios de Agrocom primero, luego calificación); el cliente elige y puede ordenar como quiera.

## Etapa de negociación

Cuando un proveedor toma la solicitud para negociar, es decir, cuando registra su primera oferta (acepta el precio o propone el suyo), la solicitud está «En negociación»: deja de ofertarse y de aparecer a los demás pilotos y no se avisa a más proveedores hasta que el cliente cierre esa negociación o se cierre el trato. Si la negociación termina sin acuerdo (el cliente rechaza, el piloto retira o la oferta vence), la solicitud vuelve a ofertarse y las olas continúan. El cliente también puede elegir «Sin buscar más pilotos» cuando ya conversa con quien le interesa: no se ofrece a más proveedores ni se relanza sola, y puede volver a buscar cuando quiera.

## Precios

No hay precio de referencia: el precio lo ponen el cliente y el proveedor. El sistema solo rechaza un precio por encima de un máximo de sanidad, para evitar errores de digitación.

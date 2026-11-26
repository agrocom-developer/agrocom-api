# Política de negociación de ofertas

**Agrocom SRL · Vigente** · Decisión: ADR 0030, ADR 0038, ADR 0045 · Especificación: `docs/especificacion/marketplace.md` §6 · Reglas exactas: `docs/especificacion/negociacion-contrato.md`

## Principios

- El precio lo acuerdan el cliente y el piloto; Agrocom es el nexo y el registro.
- El cliente decide con información: precio, calificación y dron. La distancia no se calcula: se acuerda en la negociación.
- El piloto decide con información: ve el estado de la propiedad y los lotes antes de ofertar.
- Nadie queda obligado por una oferta que no aceptó.

## Cómo se negocia

1. El cliente publica la solicitud con un precio por hectárea, el departamento y la provincia del terreno y, si quiere, requisitos como la capacidad mínima del dron. Queda abierta. El sistema avisa a los proveedores por oleadas y la solicitud es visible para los pilotos habilitados que le corresponde (ver «Quién recibe el aviso»).
2. Un piloto acepta el precio o propone el suyo, con el dron que usará. Esa es su primera oferta.
3. El cliente puede aceptar, contraofertar o rechazar. Si contraoferta, el piloto puede aceptar, contraofertar o rechazar, y así por rondas. Hay una sola oferta vigente por negociación.
4. El máximo de ofertas por negociación es 5; la última es definitiva y solo se acepta o se rechaza.
5. Cada oferta vence a las 24 horas (parámetro, admite días o semanas). La fecha deseada puede estar a semanas o meses: una solicitud sin adjudicar expira al fin de su fecha deseada, con un máximo de un año desde que se publica (30 días si no tiene fecha deseada). El cliente recibe un aviso antes del vencimiento y puede renovarla dentro de 30 días.
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
- Al publicar, el sistema avisa a los proveedores que cumplen **todos** los filtros: los del sistema (aprobado con equipo apto, disponible, calificación), los del cliente (como la capacidad mínima del dron) y los del proveedor, que él ajusta en su app (departamentos que no acepta, hectáreas mínimas, tipos de aplicación que no quiere). La lista de filtros puede crecer.
- Los primeros avisos favorecen a los proveedores propios de Agrocom y a los mejor calificados. Cada pocos minutos, si nadie ofertó, se relaja un filtro y se avisa a los nuevos que cumplen; a quien ya se avisó no se le avisa de nuevo. Cuando no quedan filtros por relajar, la solicitud se abre a todos en el mapa y, pasado un tiempo, se vuelve a avisar a los del inicio.
- Un proveedor que se marca `ocupado` o `en_trabajo` no recibe avisos; puede seguir viendo el mapa.
- Quien tiene un dron que no cumple lo que pidió el cliente puede ofertar: la oferta se marca y el cliente decide.
- Nadie tiene prioridad al adjudicar. La prioridad es solo en el aviso y en el orden sugerido de las ofertas (propios de Agrocom primero, luego calificación); el cliente elige y puede ordenar como quiera.

## Etapa de negociación

Mientras el cliente negocia con proveedores, la solicitud está «En negociación»: deja de aparecer a los demás pilotos y no se avisa a más proveedores. Vuelve a aparecer cuando esas negociaciones terminan sin acuerdo. El cliente también puede elegir «Sin buscar más pilotos» cuando ya conversa con quien le interesa: la solicitud sigue disponible para quienes ya la ven, pero no se ofrece a más proveedores. Puede volver a buscar cuando quiera.

## Precios

No hay precio de referencia: el precio lo ponen el cliente y el proveedor. El sistema solo rechaza un precio por encima de un máximo de sanidad, para evitar errores de digitación.

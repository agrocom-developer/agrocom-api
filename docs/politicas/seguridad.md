# Política de seguridad, incidencias y emergencias

**Agrocom SRL · Vigente** · Decisión: ADR 0047 · Especificación: `docs/especificacion/marketplace.md` §9

## Antes de operar

- Solo ofrece y opera un equipo con piloto y dron aprobados y documentos vigentes.
- El cliente declara los colindantes sensibles (apiarios, cultivos orgánicos, viviendas, fuentes de agua) al crear el terreno; el piloto los ve antes de ofertar.
- Las condiciones de vuelo, la mezcla y los resguardos los acuerdan y los asumen el piloto y el cliente en el lote; la plataforma no los controla.

## Durante el servicio

- Ante personas o animales en el lote o deriva visible, el piloto detiene la aplicación y reporta una incidencia.
- Cualquier participante puede pedir ayuda con el botón SOS.

## Incidencias

Tipos: deriva de producto, dron caído, lesión, fuga o derrame, intrusión de personas o animales, conflicto con cliente, conflicto con proveedor, falla de equipo, otro. Cualquier participante puede reportarlas con fotos y, si quiere, su ubicación; las del piloto se registran sin conexión y se sincronizan al recuperarla.

| Severidad | Atención |
|---|---|
| Informativa | Revisión en el día hábil siguiente |
| Grave | Contacto de soporte en 2 horas; un administrador decide si el proveedor puede seguir ofertando |
| Emergencia | Atención inmediata |

## Emergencias y SOS

El botón SOS de las apps avisa a soporte con la ubicación, abre una incidencia de severidad emergencia y muestra el número de emergencias local. En el piloto la ubicación es la del dispositivo en ese momento; en el cliente, la del terreno del servicio (su app no pide permiso de ubicación). En caso de peligro para la vida se llama primero a los servicios de emergencia. Soporte contacta al equipo y al cliente.

## Ubicación de una emergencia o incidencia

Es la única ubicación, además de la última posición del piloto durante la ejecución, que el servidor guarda. Se guarda solo si la persona pulsa el SOS o reporta una incidencia, nunca de forma automática ni en segundo plano; la finalidad es la seguridad (dirigir ayuda y atender el caso); la ve soporte y no la contraparte; se elimina **90 días después de cerrar la incidencia** (o del reclamo asociado) y no entra a logs, bitácora ni analíticas. Se declara en la política de privacidad y en las declaraciones de las tiendas (ADR 0047).

## Conducta en el chat

Reportar un mensaje, una conversación o una foto, y bloquear a la otra parte, se explican en `seguimiento_y_privacidad.md`; el reporte llega a soporte como un reclamo de conducta (`disputas.md`). Una amenaza o un riesgo físico se atiende como incidencia grave y, ante peligro para la vida, se llama primero a los servicios de emergencia.

## Consecuencias

Una incidencia grave probada con responsabilidad del proveedor suspende al proveedor y puede dar lugar a un reclamo o a una baja. El soporte guarda el expediente de cada incidencia.

## Soporte

Disponible desde la app y la web. Primera respuesta a reclamos e incidencias no urgentes en 24 horas hábiles (parámetro).

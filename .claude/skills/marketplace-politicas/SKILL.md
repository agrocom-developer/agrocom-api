---
name: marketplace-politicas
description: Resumen operativo de las políticas del marketplace de fumigación con drones de Agrocom — roles, ciclo de una solicitud, negociación de ofertas por precio por hectárea, visibilidad, calificaciones, precio final sin cobro en la app, aprobaciones y excepciones de red. Usar antes de diseñar o implementar cualquier caso de uso, endpoint, permiso o pantalla que dependa de quién puede hacer qué y cuándo.
---

# Políticas del marketplace — referencia operativa

Agrocom es un marketplace de servicio de fumigación con drones. El cliente publica una solicitud con un precio por hectárea; los pilotos la ven en un mapa y aceptan o contraofertan; el cliente elige. Los drones son los "vehículos". Agrocom es el nexo y el registro del resultado, y además un proveedor propio que compite como cualquier otro. Lo operativo en el lote lo acuerdan en persona las partes. Cada regla es una decisión revisable: si cambia, se cambia aquí y en la especificación (`docs/especificacion/`), no solo en el código. El detalle exacto de estados, reglas y recursos está en `docs/especificacion/negociacion-contrato.md`.

## Roles

| Quién | Qué hace | Superficie |
|---|---|---|
| Cliente (cuenta de la app `cliente`) | Publica la solicitud con su precio, compara y elige ofertas, ve fotos, chat y ubicación del piloto, califica; debe estar aprobado | App `cliente` |
| Proveedor (cuenta de la app `piloto`: independiente, dueño de drones, Agrocom propio o piloto de un tercero) | Se registra con datos de la persona y del dron con fotos; ve el mapa, ofrece, ejecuta y chatea tras la aprobación | App `piloto` |
| Administrador | Personal de Agrocom: verifica y aprueba clientes y proveedores, modera calificaciones, resuelve disputas, administra usuarios y roles | Panel web |
| Operador | Personal de Agrocom con acceso a las partes del panel que el administrador le asigna | Panel web |

Cada app admite un solo tipo de cuenta, con su propio usuario, verificación (selfie con cámara y documento de identidad) y aprobación; no hay conversión entre tipos. Una persona (por su documento) tiene a lo sumo una cuenta de cliente y una de proveedor. Un proveedor no pide servicios y nadie ofrece sobre una solicitud propia (ADR 0046).

## Ciclo de una solicitud

`borrador` → `abierta` → `con_ofertas` → `adjudicada` → `en_ejecucion` → `finalizada` → `calificada`; además `cancelada`, `expirada` y `disputada`. Todo es solicitud por solicitud: no hay contratos ni campañas. Cada transición pasa por el servicio de estado de su máquina, nunca por un `estado = ...` suelto.

## Negociación

1. El cliente propone un precio por hectárea; la solicitud queda abierta.
2. El piloto acepta el precio o contraoferta, con el dron que usará.
3. En cada negociación hay una sola oferta vigente: quien la recibe acepta, contraoferta o rechaza. Máximo de ofertas por negociación, con la última definitiva. Vigencias de oferta (24 horas) y de solicitud (7 días) por parámetro.
4. Aceptar la oferta vigente de la contraparte adjudica la solicitud: precio acordado, proveedor y equipo; las demás ofertas se cierran. La operación es atómica en el servidor.
5. El cliente elige entre las ofertas: ningún proveedor tiene prioridad sobre otro y no hay puntuación ni sanciones automáticas.

## Qué ve cada parte

- Antes de ofertar, el piloto ve lotes, hectáreas, cultivo, accesos, observaciones y fotos del terreno; no ve datos del cliente ni las ofertas de otros pilotos.
- El cliente ve por oferta el precio, la calificación, el dron con fotos aprobadas y la distancia.
- Los datos de contacto se entregan solo tras adjudicar. La ubicación del piloto se comparte solo durante `en_ejecucion`.

## Reglas de red

- Publicar, ofertar, contraofertar, aceptar, rechazar y retirar requieren red: las confirma el servidor.
- Lo registrado en el lote (inicio, avance, cierre, mensajes y fotos) lo captura la app sin señal y llega por el protocolo de sincronización idempotente (`uuid_cliente`).
- La app `cliente` es online-first: no usa outbox.

## Calificaciones

El cliente califica al proveedor en un registro propio. El proveedor solo ve su calificación; no la edita. El admin modera (oculta o corrige abusos con motivo y autor). El proveedor califica al cliente en un registro aparte, privado. Una calificación confirmada no se reescribe: la moderación es un registro nuevo.

## Dinero

La app no cobra ni calcula comisión. Precio acordado al adjudicar; precio final = hectáreas ejecutadas × precio acordado, mostrado como monto a pagar fuera de la app. Dinero y hectáreas en `DECIMAL`, nunca `float`.

## Cancelación

Gratuita y sin registro antes de adjudicar. Tras adjudicar se registra quién cancela y se muestra en su tasa de cancelación; sin penalidad en dinero ni restricción automática. Los umbrales alertan al panel.

## Alta y aprobación

- Cualquiera se registra y entra a la app; el cliente y el proveedor se verifican (selfie con cámara y documento) y un admin aprueba cada cuenta antes de que publique, acepte, ofrezca o vea el mapa. El proveedor sube además los datos de su dron con fotos y sus documentos.
- El administrador puede dar de alta y aprobar desde el panel; la verificación queda marcada «cargada por administrador» si no hay documento de la persona. Nadie aprueba su propia cuenta.
- La aprobación es un permiso propio con autor, fecha y motivo.

## Lo que no existe

Contratos, campañas, inventario, mantenimiento, planilla ni contabilidad internos. De mantenimiento solo se conservan los ciclos de batería, dentro del dron.

## Quién decide qué

Regla nueva de producto: `producto-marketplace`. Negociación de ofertas: `negociacion-ofertas`. Verificación, abuso y privacidad: `confianza-seguridad`. Permisos: `modulos-roles`.

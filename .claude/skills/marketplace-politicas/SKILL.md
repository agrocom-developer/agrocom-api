---
name: marketplace-politicas
description: Resumen operativo de las políticas del marketplace de fumigación con drones de Agrocom — roles, ciclo de una solicitud, asignación (propios primero, luego terceros), calificaciones, comisión, aprobaciones y excepciones de red. Usar antes de diseñar o implementar cualquier caso de uso, endpoint, permiso o pantalla que dependa de quién puede hacer qué y cuándo.
---

# Políticas del marketplace — referencia operativa

Agrocom es un marketplace de servicio de fumigación con drones, al estilo de las plataformas de transporte bajo demanda. El cliente solicita un servicio; los proveedores lo ejecutan; los drones son los "vehículos". Agrocom capta clientes, es el nexo y además es proveedor propio. Su negocio incluye vender drones y entregar a cada comprador trabajo asegurado. Cada regla es una decisión revisable: si cambia, se cambia aquí y en la especificación (`docs/especificacion/`), no solo en el código.

## Roles

| Rol | Qué hace | Superficie |
|---|---|---|
| Cliente | Solicita un servicio, sigue su estado, califica al proveedor | App `cliente` y portal web |
| Piloto propio de Agrocom | Recibe las ofertas primero, ejecuta el vuelo | App `piloto` |
| Auxiliar | Prepara la mezcla y apoya en el lote | App `auxiliar` |
| Proveedor independiente / dueño de drones | Se registra solo, opera con sus drones y auxiliares tras aprobación | Apps y portal |
| Admin | Aprueba proveedores y auxiliares, modera calificaciones, supervisa | Panel web |

## Ciclo de una solicitud

solicitada → buscando proveedor → asignada → en ejecución → finalizada → calificada. Todo es solicitud por solicitud: no hay contratos ni campañas. Cada transición pasa por el servicio de estado de su máquina, nunca por un `estado = ...` suelto.

## Asignación

1. Primero pilotos y drones propios de Agrocom.
2. Si ninguno acepta dentro de la ventana, o ninguno está disponible, se ofrece a terceros, escalando por radio o zona y ordenando por calificación.
3. El servidor decide al recibir la primera aceptación válida (operación atómica).
El detalle de tiempos y casos límite lo mantiene el agente `asignacion-matching`; los parámetros viven en la configuración del sistema (ADR 0016).

## Reglas de red

- Aceptar o rechazar una oferta requiere red: la confirma el servidor.
- Lo ejecutado en el lote (sesiones, hectáreas, mezcla, evidencias) lo captura la app sin señal y llega por el protocolo de sincronización idempotente (`uuid_cliente`).
- La app `cliente` es online-first: no usa outbox.

## Calificaciones

El cliente califica al proveedor en un registro propio. El proveedor solo ve su calificación; no la edita. El admin modera (oculta o corrige abusos con motivo y autor). El proveedor califica al cliente en un registro aparte, visible para el admin y para la asignación. Una calificación confirmada no se reescribe: la moderación es un registro nuevo.

## Dinero

La primera versión no cobra dentro de la app. El modelo de comisión (porcentaje de la plataforma sobre el servicio finalizado) está documentado en la especificación y se liquida fuera de la app. Dinero y hectáreas en `DECIMAL`, nunca `float`. Se paga por hectárea validada, no declarada.

## Alta y aprobación

- El proveedor independiente se registra solo; un admin lo aprueba antes de que reciba ofertas.
- El piloto de un proveedor registra a su auxiliar; un admin lo aprueba antes de que opere.
- La aprobación es un permiso propio con autor, fecha y motivo.

## Lo que no existe

Contratos, campañas, inventario, mantenimiento, planilla ni contabilidad internos. De mantenimiento solo se conservan los ciclos de batería, dentro del dron.

## Quién decide qué

Regla nueva de producto: `producto-marketplace`. Despacho: `asignacion-matching`. Verificación, abuso y privacidad: `confianza-seguridad`. Permisos: `modulos-roles`.

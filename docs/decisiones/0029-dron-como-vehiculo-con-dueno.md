# ADR 0029 — El dron es el vehículo y tiene dueño

**Estado:** aceptada, revisable · **Fecha:** 2026-11-23 · **Decide:** el dueño (autonomía delegada)

## Contexto

Como los vehículos en una plataforma de transporte, los drones son el activo que presta el servicio. Los hay de la flota de Agrocom, vendidos por Agrocom a un comprador y de terceros. El dueño puede no ser quien lo opera.

## Decisión

1. `flo_drones` guarda `dueno_proveedor_id` y `operador_proveedor_id`. Un dron solo ejecuta solicitudes dentro de un equipo (`flo_equipos`) con piloto aprobado.
2. Un dron necesita aprobación de un administrador (documentos de registro, habilitación y seguro) antes de recibir ofertas.
3. Un dron en dos equipos vigentes a la vez no se admite.
4. Del mantenimiento y el inventario internos solo se conserva el contador de **ciclos de batería** dentro del dron. No hay módulo de mantenimiento, inventario, planilla ni contabilidad interna.
5. Los compradores de drones de Agrocom reciben trabajo asegurado por la plataforma: su dron entra como `origen = vendido_por_agrocom` y su proveedor tiene prioridad de matching dentro del grupo de terceros (parámetro).

## Alternativas descartadas

- Drones sin dueño, solo asignados a pilotos: no permite al dueño tercero gestionar su flota ni cobrar.
- Conservar mantenimiento completo: es operación interna de Agrocom, no producto del marketplace.

## Consecuencias

La capacidad del tanque y el ancho de pasada del dron alimentan la elegibilidad y la estimación de tiempo.

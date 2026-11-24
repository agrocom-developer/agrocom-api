# ADR 0029 — El dron es el vehículo y tiene dueño

**Estado:** aceptada, revisable · **Fecha:** 2026-11-23 · **Decide:** el dueño (autonomía delegada)

## Contexto

Como los vehículos en una plataforma de transporte, los drones son el activo que presta el servicio. Los hay de la flota de Agrocom, vendidos por Agrocom a un comprador y de terceros. El dueño puede no ser quien lo opera.

## Decisión

1. `flo_drones` guarda `dueno_proveedor_id` y `operador_proveedor_id`. Un dron solo ejecuta solicitudes dentro de un equipo (`flo_equipos`) con piloto aprobado.
2. Un dron necesita aprobación de un administrador (documentos de registro, habilitación y seguro, y las fotos obligatorias, ADR 0042) antes de ofertar.
3. Un dron en dos equipos vigentes a la vez no se admite.
4. Del mantenimiento y el inventario internos solo se conserva el contador de **ciclos de batería** dentro del dron. No hay módulo de mantenimiento, inventario, planilla ni contabilidad interna.
5. Los compradores de drones de Agrocom reciben trabajo asegurado por la plataforma: su dron entra como `origen = vendido_por_agrocom`, y su proveedor es visible en el mapa y en las ofertas como cualquier otro; el trabajo asegurado lo da la demanda que Agrocom capta, no un orden de prioridad.

## Alternativas descartadas

- Drones sin dueño, solo asignados a pilotos: no permite al dueño tercero gestionar su flota ni cobrar.
- Conservar mantenimiento completo: es operación interna de Agrocom, no producto del marketplace.

## Consecuencias

La marca, el modelo, la capacidad del tanque y las fotos del dron se muestran al cliente en cada oferta.

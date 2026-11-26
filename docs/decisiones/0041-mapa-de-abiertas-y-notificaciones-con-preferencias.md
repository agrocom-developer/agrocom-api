# ADR 0041 — Mapa de solicitudes abiertas y notificaciones con preferencias

**Estado:** aceptada, revisable · **Fecha:** 2026-11-24 · **Decide:** el dueño · **Reemplazado en parte por:** ADR 0045 (puntos 1, 3, 4 y 6: sin zonas ni distancia, filtros por departamento y avisos por oleadas)

## Contexto

En la región de Santa Cruz no es lo mismo ir a los valles por un solo cliente que a la Chiquitania por cuatro. El piloto necesita ver dónde está la demanda para decidir a qué zona llevar su equipo, y Agrocom necesita incentivar que los pilotos reciban ofertas cuando un cliente pide un servicio, sin saturarlos.

## Decisión

1. **Mapa de solicitudes abiertas.** El piloto habilitado ve en un mapa las solicitudes `abierta` y `con_ofertas` con filtros por encuadre, zona, macrorregión (`valles`, `chiquitania`, `otras`), hectáreas, precio por hectárea, fecha y cultivo, y un resumen por zona (solicitudes, hectáreas, precio medio). El catálogo de zonas y macrorregiones es dato administrable.
2. **Detalle previo a ofertar.** Lotes dibujados, hectáreas, cultivo, accesos, observaciones, estado del terreno y fotos. Sin datos de contacto (ADR 0040).
3. **Preferencias de notificación.** Cada cuenta tiene preferencias por categoría: `nuevas_solicitudes` (con zonas, macrorregiones y hectáreas mínimas), `ofertas`, `mensajes`, `avance` y `calificaciones`. Se activan y desactivan desde la app. Las categorías obligatorias (adjudicación, cierre, expiración, cuenta, aprobación, disputas y emergencias) no se pueden desactivar.
4. **Aviso de nueva solicitud.** Al abrirse una solicitud se avisa a las cuentas de proveedor aprobadas cuyas preferencias coincidan; una solicitud avisa a una cuenta una sola vez.
5. **Dispositivos.** La app registra el token de push de su dispositivo y lo retira al cerrar sesión. Sin proveedor de push configurado, los avisos viven en la lista in-app y se consultan por sondeo.
6. **Distancia.** El piloto envía su posición con la consulta del mapa solo para ordenar y calcular `distancia_km`; el servidor no la guarda.

## Alternativas descartadas

- **Radio fijo de operación por proveedor**: limita al piloto a decidir con un número en vez de mirar el mapa.
- **Avisar a todos los pilotos de todas las solicitudes**: fatiga y desactivación masiva.
- **Preferencias solo en el dispositivo**: el servidor es quien decide a quién enviar.

## Consecuencias

Tablas `ntf_preferencias` y `ntf_dispositivos` y dos reglas nuevas en el motor del ADR 0025. Credencial del proveedor de push fuera del repositorio (ADR 0016).

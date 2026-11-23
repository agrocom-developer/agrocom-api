# ADR 0030 — Asignación con prioridad a propios y escalado a terceros

**Estado:** aceptada, revisable · **Fecha:** 2026-11-23 · **Decide:** el dueño (autonomía delegada)

## Contexto

Agrocom es proveedor y nexo a la vez. Debe llenar primero su propia capacidad y, cuando no alcance, apoyarse en terceros sin dejar sin servicio al cliente. Las plataformas de movilidad despachan por cercanía con una ventana de aceptación corta y escalan el radio si nadie acepta.

## Decisión

1. **Ronda 1, propios**: se ofrece la solicitud a los proveedores propios elegibles, ordenados por cercanía. Ventana de oferta: 10 minutos si es urgente, 2 horas si es programada.
2. **Ronda 2 en adelante, terceros e independientes**: radio inicial de 50 km, luego 100 y 150 km. Orden por puntuación `0.40 cercanía + 0.35 calificación + 0.15 aceptación + 0.10 (1 − cancelación)`. Máximo de 5 proveedores por ronda.
3. Una oferta se acepta una sola vez; la aceptación bloquea la solicitud y retira las demás ofertas.
4. Agotadas las rondas, la solicitud pasa a `sin_proveedor` y el cliente elige ampliar la ventana, mejorar la tarifa o cancelar sin penalidad.
5. Elegibilidad: aprobado, documentos vigentes, equipo completo, zona, capacidad del dron y sin conflicto de agenda.
6. El administrador puede reasignar manualmente con motivo.
7. Todos los valores son parámetros del sistema (ADR 0016).

## Alternativas descartadas

- Oferta simultánea a todos: el proveedor propio pierde la prioridad y el tercero recibe ruido.
- Subasta de precio: complica la experiencia del cliente y baja la calidad.

## Consecuencias

Hace falta un proceso programado (cola con retraso) que venza ofertas y abra la ronda siguiente. Cada ronda queda en `asg_rondas` para métricas.

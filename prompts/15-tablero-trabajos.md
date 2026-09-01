<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/tablero-trabajos etapas=3 -->

# Tarea 15 — HU-15: tablero de trabajos y avance por lote/aplicación

El encargado necesita seguir la campaña desde la ciudad: qué trabajos están
abiertos, cerrados o validados, por lote y por orden de aplicación, sin
esperar a que alguien le cuente. La tarea 13 (HU-05) dejó una pantalla mínima
de trabajos sin filtros ni detalle; esta tarea la extiende hasta el tablero
real. No es crítica: es de solo lectura, no muta estado ni dinero.

## Qué hacer

Cargá el skill `verificacion`. Leé la pantalla que dejó la tarea 13
(`app/Dominios/Operaciones/Infraestructura/Http/...` del lado panel — el
nombre exacto depende de cómo la haya llamado esa tarea) y extendela en vez
de duplicarla:

1. **Filtros**: por estado (`abierto`/`cerrado`/`validado`, y lo que haya
   agregado la tarea 14), por lote, por orden de aplicación. Paginado.
2. **Detalle de trabajo**: sus sesiones (piloto, hectáreas, estado,
   `motivo_cierre` si lo tiene), y sus evidencias — **hoy no hay ninguna**:
   TE-07/HU-08/HU-09 (compresión, fotos, captura del RC) son sprint 3 y no
   están implementadas. La sección de evidencias del detalle tiene que
   convivir con eso vacío sin romper — no inventes datos ni tablas de
   evidencias para llenarla.
3. Permiso nuevo (o reusá el de la tarea 13 si el alcance calza,
   `operaciones.trabajo.ver`) gateando el acceso, patrón
   `AutorizacionPanelWeb` + `abort_unless`.

## Cómo repartir las etapas

1. Filtros y paginado sobre la pantalla existente.
2. Vista de detalle de un trabajo con sus sesiones.
3. Tests de panel (filtros, detalle, permiso) y pulido de la cascada.

## Qué NO hacer

- No inventes evidencias que no existen — el detalle debe mostrar la
  ausencia con normalidad, no simular datos.
- No toques la cola de validación de la tarea 14 (pantalla distinta, aunque
  lea las mismas tablas).
- No agregues acciones de mutación (validar, rechazar, cerrar) a esta
  pantalla — es de solo lectura.
- Nada de `agrocom-field`/Flutter.

## Criterio de aceptación

`./bin/verify` = 0, con tests nuevos:

1. El filtro por estado devuelve solo los trabajos de ese estado.
2. El detalle de un trabajo muestra sus sesiones asociadas.
3. Un usuario sin el permiso recibe 403; uno con permiso ve el tablero.

## Cierre obligatorio de cada etapa

`runs/15.estado` (`PARCIAL`/`OK`/`BLOQUEADA`). `runs/15.md` con lo hecho y lo
que falta. Al llegar a `OK`, `runs/15.pr.md`.

## Commits

Agrupados: filtros, detalle, tests. Español, imperativo, el porqué antes que
el qué. Sin trailer `Co-Authored-By`.

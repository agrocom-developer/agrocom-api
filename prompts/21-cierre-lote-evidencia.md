<!-- ciclo: critica=si turno-noche=1 descongela=tests rama=feature/cierre-lote-evidencia etapas=3 -->

# Tarea 21 — HU-09: cierre de lote con captura del RC e imagen del campo

`plan_sprints.md`, fila HU-09: "Como piloto, quiero cerrar el lote
adjuntando captura del RC e imagen del campo, para que el trabajo quede
completo y demostrable". CA: "Sin captura no cierra; validación de suma
dispara `observado` si excede tolerancia".

Escrita asumiendo que la tarea 19 (TE-07, recepción de evidencias) y la
tarea 20 (HU-07, relevo/`parcial`/`observado`/tolerancia) ya están
integradas — es el orden de la cola el que lo garantiza. Si por lo que sea
no lo están, esta tarea queda `BLOQUEADA`, no la fuerces sin lo que
depende.

**Es crítica**: extiende el motor de sync (`Contratos/CierreTrabajo.php`,
`EscrituraSincronizacionEloquent::cerrarTrabajo()`). El PR se abre en
borrador.

## Qué hacer

Cargá el skill `verificacion` y `dominio-backend`. Leé antes de tocar nada:

- `docs/especificacion/especificacion_funcional_tecnica.md` línea 360 ("Sin
  evidencia | Sesión cerrada sin captura de RC, o lote conformado sin imagen
  del campo") y línea 342 (el reporte técnico por lote exige "imagen del
  campo (capturada por el dron)" como contenido obligatorio).
- `app/Dominios/Operaciones/Contratos/CierreTrabajo.php` — el DTO que vas a
  extender. Fijate el comentario de por qué NO lleva un campo de hectáreas
  (son derivadas, invariante 6): la evidencia que agregás acá es lo opuesto,
  un dato que nadie más puede reconstruir por el cliente, mismo tratamiento
  que `litrosSobrante`.
- Lo que haya quedado de la tarea 19 sobre cómo se referencia una evidencia
  ya subida (probablemente por su `uuid_cliente`) y de la tarea 20 sobre
  dónde vive `observado` — leé `runs/19.md` y `runs/20.md` antes de escribir
  una línea, no asumas la forma exacta desde acá.

1. **`CierreTrabajo` exige evidencia.** Al menos la imagen del campo
   (`tipo: imagen_campo`), referenciada por el `uuid_cliente` de una
   evidencia ya subida vía el endpoint de la tarea 19. Sin ella, o
   referenciando una evidencia que no existe o es de tipo distinto, el
   registro se rechaza — mismo patrón `rechazado` sin frenar el resto del
   lote, nunca un 422 del lote completo.
2. **Reutilizá el mecanismo de `observado`** de la tarea 20 — no lo
   reimplementes. Esta tarea agrega un test de regresión: un trabajo cuya
   suma de sesiones excede la tolerancia sigue reflejado como `observado`
   después de cerrarse (confirma que el cierre no "limpia" el cálculo).
3. **Tests de integración** de punta a punta: cierre sin evidencia, cierre
   con evidencia de tipo incorrecto, cierre con evidencia inexistente,
   cierre válido, reintento idempotente.

## Cómo repartir las etapas

1. Extensión de `CierreTrabajo` + `EscrituraSincronizacionEloquent::cerrarTrabajo()`
   con la validación de evidencia obligatoria.
2. Tests de integración de los cinco casos de arriba.
3. Pulido y cascada verde.

## Qué NO hacer

- No reimplementes la subida de evidencia — ya existe desde la tarea 19,
  esta tarea solo la referencia y la exige.
- No toques `parcial`/la lógica de tolerancia en sí — ya existe desde la
  tarea 20, esta tarea solo confirma que sigue viva tras el cierre.
- Sin incidencias (HU-08, tarea aparte, todavía sin planificar).
- No exijas `captura_rc` a nivel de trabajo si tu lectura de la tarea 20 la
  dejó a nivel de sesión (`sesiones.captura_rc_id`, espec línea 195) — esta
  HU, según su CA literal en `plan_sprints.md`, solo exige la imagen del
  campo al cerrar el lote. Si además querés exigir que todas las sesiones
  del trabajo ya tengan su `captura_rc_id`, documentalo como decisión propia
  con su porqué, no lo des por sentado.

## Criterio de aceptación

`./bin/verify` = 0, con los tests del punto 3 de arriba en verde.

## Cierre obligatorio de cada etapa

`runs/21.estado` (`PARCIAL`/`OK`/`BLOQUEADA`). `runs/21.md` con qué se hizo y
qué falta. Al llegar a `OK`, `runs/21.pr.md`.

## Commits

Agrupados: extensión del DTO/motor de sync, tests de integración. Español,
imperativo, el porqué antes que el qué. Sin trailer `Co-Authored-By`.

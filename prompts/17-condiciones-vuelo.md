<!-- ciclo: critica=si turno-noche=1 descongela=tests rama=feature/condiciones-vuelo etapas=4 -->

# Tarea 17 — HU-06: condiciones al iniciar sesión, autoriza o bloquea

`agrocom-field` es Flutter y queda fuera de este ciclo (regla permanente de
`cola_tareas.md`): la pantalla donde el piloto tipea viento/temperatura/
humedad, y lo que la app hace cuando el servidor rechaza el registro, no son
parte de esta tarea. Lo que sí es de este repo: el servidor recibe esas
condiciones (vía `POST /api/sync`, el motor de la tarea 09/TE-05) y decide si
autoriza, autoriza con observación, o rechaza.

**Es crítica**: agrega un tipo de registro nuevo al motor de sync
(`SincronizarLote`, `EscrituraSincronizacion`) — lo que `CLAUDE.md` no
delega sin revisión línea por línea, mismo criterio que ya aplicó la tarea
13 al extenderlo con `cierre_trabajo`/`cierre_sesion`. El PR se abre en
borrador.

## Qué hacer

Cargá el skill `verificacion`. Leé antes de tocar nada:

- `app/Dominios/Sincronizacion/Aplicacion/SincronizarLote.php` — el orden
  causal fijo (`ORDEN_CAUSAL`) y el patrón `aplicarXxx()` por tipo de
  registro.
- `app/Dominios/Operaciones/Contratos/CierreSesion.php` y
  `EscrituraSincronizacion.php` — el patrón de DTO (`intentarDesdeArreglo`,
  validación de números no negativos) y de contrato que vas a replicar para
  `condiciones`.
- `docs/especificacion/especificacion_funcional_tecnica.md` §4.3 (tabla
  `condiciones`: `trabajo_id, sesion_id, momento, viento_kmh, temperatura_c,
  humedad_pct, autorizado, observacion_agronomo, firma_observacion`) y §5
  (tabla de transiciones: "Existe orden de aplicación vigente y condiciones
  dentro de rango" / "Condiciones fuera de rango + observación firmada por
  el agrónomo").

**Dónde vive el resultado "autoriza o bloquea":** `EstadoTrabajo` hoy solo
tiene `Abierto`/`Cerrado` (simplificación deliberada de la tarea 09 para el
esqueleto vertical) — no hay estados `autorizado`/`bloqueado` en el trabajo
real, y esta tarea no se los agrega. El resultado vive en el propio registro
de `condiciones`, mismo criterio que la tarea 15 usó una proyección de
lectura en vez de tocar `EstadoTrabajo`: dentro de rango, el registro se
acepta autorizado; fuera de rango con `observacion_agronomo` y
`firma_observacion` presentes, se acepta autorizado-con-observación; fuera
de rango sin ellas, el registro se **rechaza** — mismo mecanismo que ya usa
el motor de sync para un `piloto_id` que no corresponde (`ResultadoSincronizacion::rechazado(...)`).
Que la app bloquee el vuelo en pantalla ante ese rechazo es cosa de
`agrocom-field`, fuera de esta tarea. Si al diseñar preferís otra forma de
modelar "autorizado"/"autorizado_con_observacion" (columna enum en vez de
bool + observación opcional, por ejemplo), documentá la decisión y el
porqué en `runs/17.md` — es la pieza más abierta de esta tarea, mismo
tratamiento que el mecanismo de rechazo de la tarea 14.

1. **Migración y modelo.** `ope_condiciones` (Operaciones, `sesion_id`,
   `trabajo_id`, `momento`, `viento_kmh`, `temperatura_c`, `humedad_pct`,
   `autorizado`, `observacion_agronomo` nullable, `firma_observacion`
   nullable — sin evidencia real todavía, TE-07 no está implementada, así
   que es un campo de texto/flag, no un `evidencia_id`), `uuid_cliente`
   `UNIQUE` (invariante 1, nace en la app de campo), auditoría/soft-delete.
   Alcance de `momento`: solo `inicio_sesion` en esta tarea —
   `incidencia` es HU-08, sprint 3, tarea aparte.
2. **Contrato y validación.** DTO `RegistroCondiciones` en
   `Operaciones/Contratos/` (nombre a tu criterio), rangos como constantes:
   viento ≤ 17 km/h, temperatura ≤ 30 °C, humedad ≤ 90 %. Referenciá la
   sesión por `sesion_uuid_cliente`, mismo criterio que `CierreSesion`.
3. **Motor de sync.** Agregá `'condiciones'` a `ORDEN_CAUSAL`, después de
   `'sesion'` (puede llegar en el mismo lote que la apertura de la sesión
   que referencia). Extendé `EscrituraSincronizacion` con
   `registrarCondiciones()`, implementalo en
   `EscrituraSincronizacionEloquent`.
4. **Tests de integración** del lote completo: condiciones dentro de rango,
   fuera de rango con observación, fuera de rango sin observación
   (rechazado), duplicado por `uuid_cliente`, condiciones en el mismo lote
   que la sesión que abre.

## Cómo repartir las etapas

1. Migración `ope_condiciones` + modelo + DTO `RegistroCondiciones` con los
   tres rangos.
2. Extensión del motor de sync (`ORDEN_CAUSAL`, `EscrituraSincronizacion`,
   `aplicarCondiciones()`).
3. Lógica de autorización (dentro de rango / con observación / rechazo) +
   tests unitarios del DTO y del caso de uso.
4. Tests de integración de lote completo + pulido, cascada verde.

## Qué NO hacer

- No toques `EstadoTrabajo` ni `EstadoSesion`, ni sus máquinas de estado —
  el resultado vive en el registro de `condiciones` (ver arriba).
- No implementes `momento = incidencia` — HU-08, tarea aparte.
- Sin evidencia real de la firma (archivo, hash) — TE-07 no existe todavía;
  un campo de texto/flag alcanza.
- Nada de `agrocom-field`/Flutter, ni de cómo la app reacciona al rechazo.

## Criterio de aceptación

`./bin/verify` = 0, con tests nuevos:

1. Condiciones dentro de rango (viento ≤17, temp ≤30, humedad ≤90) →
   aceptado, autorizado sin observación.
2. Condiciones fuera de rango con `observacion_agronomo` y
   `firma_observacion` → aceptado, autorizado-con-observación.
3. Condiciones fuera de rango sin observación → rechazado, sin fila nueva.
4. Reintento del mismo `uuid_cliente` → duplicado, no crea una segunda fila.
5. Condiciones y apertura de sesión en el mismo lote, en cualquier orden de
   entrada → se aplican en el orden causal correcto.

## Cierre obligatorio de cada etapa

`runs/17.estado` (`PARCIAL`/`OK`/`BLOQUEADA`). `runs/17.md` con lo hecho,
la decisión de diseño de "autorizado"/"autorizado_con_observacion" con su
porqué, y qué falta. Al llegar a `OK`, `runs/17.pr.md`.

## Commits

Agrupados: migración y DTO, extensión del motor de sync, lógica de
autorización, tests de integración. Español, imperativo, el porqué antes
que el qué. Sin trailer `Co-Authored-By`.

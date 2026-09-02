<!-- ciclo: critica=si turno-noche=1 descongela=tests rama=feature/relevo-piloto etapas=5 -->

# Tarea 20 — HU-07: relevo de piloto y cambio de dron

`plan_sprints.md`, fila HU-07: "Como piloto, quiero cerrar mi sesión con
motivo (completado/relevo/falla/clima/jornada) y que el que entra registre
la hectárea acumulada de partida, para que cada uno cobre lo suyo". CA:
"Ha de sesión = diferencia contra acumulada; trabajo queda `parcial` con
pendiente visible; suma ≤ lote + tolerancia".

El catálogo de motivos (`completado`, `relevo_piloto`, `cambio_dron`,
`falla_equipo`, `clima`, `fin_jornada`, `otro`) ya existe — lo dejó la tarea
13 (`database/migrations/2026_09_01_100004_add_cierre_a_ope_sesiones_table.php`,
`Contratos/CierreSesion.php`). Esta tarea es la lógica de negocio que esa
migración dejó explícitamente afuera (ver su docblock, línea 9-14): hectárea
acumulada de partida, tolerancia y el estado `parcial`.

**Es crítica**: extiende el motor de sync (`AperturaSesion`) y potencialmente
el servicio de estados — depende de la decisión de diseño que tomes, ver
abajo. El PR se abre en borrador.

## Aviso: hay tensión real entre lo que dice la espec y lo que el código ya hizo

Leé, en este orden, antes de decidir nada:

- `docs/especificacion/especificacion_funcional_tecnica.md` §5 completa
  (líneas 173-214): el diagrama original de `Trabajo` tiene ocho estados
  (`planificado → autorizado → en_ejecucion → parcial → completo → validado
  → conformado → facturado`, con un bucle `parcial ⟷ en_ejecucion` para
  relevo/cambio de dron) y una tabla de transiciones que incluye "→ parcial"
  y "→ completo" como transiciones reales con condición propia.
- Líneas 202, 204 y 206 en particular: "el piloto saliente cierra su sesión
  con hectáreas, captura de RC y motivo; el trabajo queda `parcial` con las
  hectáreas pendientes visibles; el piloto entrante abre una sesión nueva
  registrando la `hectarea_inicial_acumulada`"; "cada sesión registra
  `hectarea_inicial_acumulada` y las hectáreas de la sesión son la
  diferencia contra ese valor" (control de doble conteo del acumulado de
  DJI); "la suma de sesiones no puede superar las hectáreas del lote más una
  tolerancia configurable por solape. Si la excede, el trabajo queda
  `observado` hasta que el encargado lo resuelva".
- `app/Dominios/Operaciones/Dominio/EstadoTrabajo.php` — la implementación
  real tiene **dos** estados, `Abierto`/`Cerrado`. Ninguna tarea desde la 09
  reintrodujo el diagrama de ocho estados: HU-06 (condiciones, tarea 17) y
  HU-10 (recepción de caldo, tarea 18) resolvieron autorización/bloqueo y
  registro de volumen con registros nuevos (`Condiciones`, `RecepcionCaldo`),
  no con estados nuevos de `Trabajo`. HU-15 (tarea 15) agregó
  `EstadoTableroTrabajo` (`Abierto`/`Validado`/`Cerrado`,
  `Trabajo::estadoTablero()`) como una proyección DERIVADA para el panel —
  nunca tocó `EstadoTrabajo`/`TransicionesTrabajo`.

**La decisión de diseño es tuya, con dos caminos razonables:**

1. **`parcial`/`observado` como estados reales**, agregados a `EstadoTrabajo`
   y a `TransicionesTrabajo::PERMITIDAS`, con sus guardas — fiel al diagrama
   original de la espec, pero reabre una máquina de estados que el proyecto
   viene evitando deliberadamente desde la tarea 09.
2. **`parcial`/`observado` como proyecciones derivadas**, mismo patrón que
   `Trabajo::estadoTablero()`/`EstadoTableroTrabajo` (tarea 15): se calculan
   desde `sesiones` + `hectareas_declaradas` + tolerancia en cada consulta,
   sin persistir un nuevo valor de `estado`. Más simple, más consistente con
   lo ya construido, y con la invariante 6 (recalculable) sin esfuerzo extra
   — pero "hasta que el encargado lo resuelva" (línea 206) sugiere una
   acción humana que en este camino no tiene dónde vivir todavía (podría ser
   HU-19, bandeja de alertas, sprint 5, que sí maneja "atendida/pendiente"
   genérico).

Elegí uno, documentá el porqué en `runs/20.md` con el mismo nivel de detalle
que la tarea 18 le dio a la decisión de módulo `Operaciones` vs `Mezclas`. Si
a mitad de camino ninguno de los dos convence — igual que preveía
`runs/12-plan.md` para HU-14 —, terminá la etapa en curso, dejá documentado
por qué, y declará `BLOQUEADA`. No hay pena por eso; forzar un diseño que no
cierra es peor.

## Qué hacer

Cargá los skills `verificacion`, `dominio-backend` y `modelo-datos`.

**Catálogo mínimo de `dron` — decisión también tuya.**
`docs/decisiones/0011-convencion-prefijos-tabla.md` punto 3 dice
explícitamente que `drones`, `baterías`, `vehículos` y `generadores` "quedan
para cuando existan los módulos `Mantenimiento`/`Inventario`" — que no
existen y no les toca el turno hasta bien entrada la fase de post-v1.0
(`plan_sprints.md`, "Sprint 10-11"). Pero esta HU necesita `sesiones.dron_id`
ahora. Recomendado: un catálogo mínimo (`id`, algún identificador — no
`uso_acumulado`, no historial de mantenimiento, nada de lo que sí le
corresponde a `Mantenimiento` el día que exista) dentro de `Operaciones`,
mismo argumento que la tarea 18 usó para no crear `Mezclas`: sin ciclo de
vida propio todavía, no amerita módulo aparte. Documentá la decisión.

1. **`hectarea_inicial_acumulada` en apertura de sesión.** Campo opcional
   (decimal, invariante 6) en `Contratos/AperturaSesion.php` — mismo patrón
   que `hectareasDeclaradas` de ese mismo DTO (`esNumeroNoNegativoOAusente()`),
   migración de columna en `ope_sesiones`, `Sesion::$fillable`/`casts()`.
2. **`dron_id` en apertura de sesión.** Catálogo + columna + FK plano (ADR
   0003 regla 3), igual que `piloto_id`/`auxiliar_id` ya lo hacen contra
   `per_personas`.
3. **Implementación de `parcial`/`observado`** según lo que decidiste arriba.
4. **Tolerancia configurable.** Un valor de config (no hardcodeado en el
   código de negocio), con su default documentado como pendiente de
   validación de campo (espec línea 454: "Tolerancia de solape entre
   sesiones: parámetro configurable, a definir con la experiencia de
   campo"). **No inventes el número real** — el mecanismo tiene que
   funcionar con cualquier valor configurado; el test usa un valor propio,
   explícito, no el que termine en el `.env` de producción.

## Cómo repartir las etapas

1. Decisión de diseño (`parcial`/`observado`, documentada) + decisión de
   catálogo de dron (documentada).
2. Migraciones + modelos: `hectarea_inicial_acumulada`, `dron_id`, catálogo
   de dron.
3. Implementación de `parcial`/`observado` + tolerancia configurable.
4. Tests de integración: relevo con dos sesiones y dron distinto, caso sin
   exceder tolerancia, caso excediéndola.
5. Pulido, cascada verde, `runs/20.md` con las dos decisiones documentadas.

## Qué NO hacer

- Sin el mecanismo "sin captura no cierra" de `CierreTrabajo` (HU-09, tarea
  21) — esta tarea no bloquea el cierre de nada, solo dejá `parcial`/
  `observado` correctamente calculables para que la 21 los reutilice.
- Sin bandeja de alertas ni "atendida/pendiente" — eso es HU-19, sprint 5.
- No reabras `EstadoSesion`/el mecanismo de validación de HU-14
  (`validado_por`, `fecha_validacion`, rechazo) — nada de esto lo toca.
- No crees el módulo `Mantenimiento`/`Inventario` completo — solo el
  catálogo mínimo de dron que esta HU necesita, documentado como tal.
- No hardcodees el porcentaje/valor de tolerancia como si fuera la
  decisión de negocio ya tomada — no lo está.

## Criterio de aceptación

`./bin/verify` = 0, con tests nuevos:

1. Abrir una sesión con `dron_id` y `hectarea_inicial_acumulada` persiste
   ambos correctamente.
2. Cerrar una sesión con motivo distinto de `completado`, dejando hectáreas
   del lote sin cubrir, deja el trabajo reflejado como `parcial` (donde lo
   hayas implementado).
3. La suma de hectáreas de sesiones vigentes de un trabajo, comparada contra
   lote + tolerancia configurada en el test: dentro del límite no dispara
   nada; superándolo, el trabajo queda `observado`.
4. `parcial`/`observado` se recalculan desde los registros de origen, no
   quedan pegados a un valor cacheado (mismo criterio que `cuadreCaldo()`/
   `estadoTablero()`).
5. Si terminó siendo un estado real de `EstadoTrabajo`: la transición pasa
   por un servicio de dominio, nunca un `estado = ...` suelto (invariante 7,
   ya cubierto por la aduana de la tarea 04 — confirmá que sigue en verde).

## Cierre obligatorio de cada etapa

`runs/20.estado` (`PARCIAL`/`OK`/`BLOQUEADA`). `runs/20.md` con ambas
decisiones de diseño documentadas y su porqué, y qué falta. Al llegar a
`OK`, `runs/20.pr.md`.

## Commits

Agrupados: decisiones + migraciones/modelos, implementación de
`parcial`/`observado` + tolerancia, tests de integración. Español,
imperativo, el porqué antes que el qué. Sin trailer `Co-Authored-By`.

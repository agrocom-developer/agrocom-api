# Corrección del dueño — Contratos, lotes y Órdenes de aplicación (18/9/2026)

**Origen.** El 18/9/2026 el dueño corrigió el enfoque con que se venía
construyendo la relación entre contrato, lotes y orden de aplicación, mirando el
panel andando. No es una ronda de features nueva sino una **corrección de dos
enfoques erróneos** ya implementados o planificados, más las decisiones que los
reemplazan. Este documento consolida esa conversación con el mismo criterio que
los del 13 y 14/9: no hace falta volver a preguntar qué se pidió.

**Dos tramos, y no están en el mismo estado.**

- **Tramo de contratos — exclusividad de lotes** (secciones 2 y 4): decidido e
  **implementado ahora**, es la HU-96 del Sprint 19 de
  `docs/gestion/plan_sprints.md`. El porqué de la decisión está en el ADR 0021.
- **Tramo de Órdenes de aplicación** (sección 3): decidido, **pendiente de
  implementar en el siguiente tramo**. Se registra acá para que no se pierda,
  no como comportamiento ya vigente: nada de la sección 3 está en el sistema hoy.

---

## 1. Lo que se corrige — el enfoque anterior

Dos supuestos que el dueño desechó:

**1.1 La orden de aplicación con selección de lotes y hectáreas parciales por
lote.** HU-92 (ampliación de HU-70, ver
`observaciones_operaciones_comercial_2026-09-14.md` §4) modeló la orden como
una lista de lotes de la propiedad, cada uno con `hectareas_solicitadas`,
elegidos al crear la orden. **Enfoque erróneo:** la orden no elige lotes ni
reparte hectáreas parciales por lote; es siempre por la aplicación completa del
contrato (sección 3).

**1.2 El contrato con guarda por propiedad completa.** La tarea `contratos-lotes`
(16/9/2026, PR #233) le dio al contrato la elección de lotes y una guarda de
"propiedad agotada" (`LotesDePropiedadAgotados`): solo rechazaba el guardado
cuando el 100 % de los lotes activos de una **propiedad** ya estaba en otros
contratos `vigente` de la misma campaña. **Enfoque erróneo:** la unidad que se
compromete es el **lote**; con esa guarda, dos contratos podían tomar el mismo
lote mientras quedara otro libre en la propiedad (sección 2).

---

## 2. Decisiones — contratos: exclusividad de lotes (implementado, HU-96)

1. **Los lotes del contrato quedan bloqueados al aprobarse.** Un contrato elige
   lotes de las propiedades del cliente. Cuando queda **aprobado** (estado
   `vigente`, en el panel "En Ejecución"), sus lotes quedan bloqueados para
   cualquier otro contrato de la **misma campaña**, hasta que se ejecute la
   última aplicación (el contrato pasa a `finalizado`, "Ejecutado") o el
   contrato se cancele. Un contrato `pausado` **conserva** sus lotes: solo
   cancelar o finalizar los libera.
2. **En aprobación se puede repetir el lote.** Mientras un contrato **no** está
   aprobado (`borrador`, "En Aprobación") el mismo lote puede estar en varios
   contratos.
3. **Estado nuevo `conflicto` ("En conflicto").** Al aprobarse un contrato, los
   otros contratos de la misma campaña que están en `borrador` y comparten al
   menos un lote con él pasan automáticamente a `conflicto`, ya no "En
   Aprobación". Un contrato en `conflicto` **no se puede aprobar**. Sale de
   `conflicto` de vuelta a `borrador` automáticamente cuando ya no comparte
   ningún lote con un contrato que retenga lotes (porque se quitó el lote
   editando el contrato, o porque el otro contrato se canceló o finalizó).
   También puede cancelarse (`conflicto → cancelado`). **Nadie puede pedir
   `conflicto` a mano desde el panel**: lo fija solo el sistema.
4. **No se puede guardar un lote ya retenido.** No se puede **registrar ni
   editar** un contrato agregándole un lote que ya está retenido por otro
   contrato (`vigente` o `pausado`) de la misma campaña: el servidor rechaza el
   guardado con un mensaje que nombra el lote y el contrato que lo tiene. En
   edición solo se validan los lotes **nuevos** agregados: un contrato en
   `conflicto` puede guardarse mientras arrastra lotes ya ocupados.
5. **Reemplaza la guarda anterior.** La regla es ahora por **lote**; la guarda
   por propiedad ("propiedad agotada") deja de existir (punto 1.2).
6. **Máquina de estados del contrato** (tabla de transiciones permitidas +
   servicio de dominio `MaquinaEstadosContrato`, invariante 7):

   | Desde | Hacia |
   |---|---|
   | `borrador` | `vigente`, `cancelado`, `conflicto` |
   | `conflicto` | `borrador`, `cancelado` |
   | `vigente` | `finalizado`, `cancelado`, `pausado` |
   | `pausado` | `vigente` |
   | `finalizado`, `cancelado` | sin salida |

   `borrador → conflicto` y `conflicto → borrador` las dispara solo el sistema
   (reconciliación de conflictos por campaña), nunca un usuario. Estados que
   **retienen lotes**: `vigente` y `pausado`.

   | Estado guardado | Etiqueta del panel | ¿Retiene lotes? |
   |---|---|---|
   | `borrador` | En Aprobación | No |
   | `conflicto` | En conflicto | No |
   | `vigente` | En Ejecución | Sí |
   | `pausado` | Pausado | Sí |
   | `finalizado` | Ejecutado | No (libera) |
   | `cancelado` | Cancelado | No (libera) |

7. **Migración.** Se agrega `conflicto` al `CHECK` `com_contratos_estado_chk`
   (solo Postgres).
8. **`finalizar` sigue manual en este tramo.** El cierre automático del contrato
   al cerrarse la última aplicación llega en un tramo posterior, con las
   órdenes de aplicación (sección 3). Hasta entonces, "se ejecutó la última
   aplicación" se registra finalizando el contrato a mano.

---

## 3. Decisiones — Órdenes de aplicación (PENDIENTE de implementar en el siguiente tramo)

> **Nada de esta sección está implementado.** Es lo que el dueño dejó decidido
> el 18/9/2026 para el tramo siguiente; se registra aquí como observación de
> negocio y se convertirá en historia de usuario cuando se planifique.

1. **La orden es siempre por toda la aplicación del contrato** (las hectáreas
   contratadas). No se eligen lotes ni hectáreas parciales por lote.
2. **El número de aplicación es correlativo y lo calcula el servidor** (1, 2,
   3…). No se elige.
3. **Solo se puede crear una orden nueva si la anterior del mismo contrato está
   cerrada.**
4. **Solo para contratos `vigente`.**
5. **Se cierra con una acción manual "Cerrar"** (`vigente → consumida`).
6. **Se puede cancelar, con motivo y causa** (`cliente` o `fuerza_mayor`):
   - una cancelación por **fuerza mayor NO consume el número de aplicación**: se
     rehace con el mismo número;
   - una cancelación por **causa del cliente SÍ lo consume**.
7. **La última aplicación cerrada finaliza el contrato y libera sus lotes.** Es
   el cierre automático que la sección 2 (punto 8) deja para este tramo.
8. **El índice de órdenes muestra** el número de aplicación ("2 de 3"), la
   cantidad de lotes del contrato y la cantidad de órdenes de trabajo
   realizadas de esa orden.
9. **La sección de lotes de la orden es una lista de solo lectura** con los
   códigos de los lotes del contrato y su propiedad.

**Por definir al implementarlo** (no lo resolvió el dueño, no se asume): la
máquina de estados vigente de la orden (`emitida → vigente → consumida | vencida`)
no tiene un estado de cancelación; cómo se representa la orden cancelada, con
su motivo y causa, se decide al planificar ese tramo.

---

## 4. Cómo cambia cada HU afectada

| HU / pieza | Qué cambia |
|---|---|
| **HU-96** (nueva, Sprint 19) | Implementa la sección 2: retención de lotes por campaña, estado `conflicto`, rechazo del guardado, máquina de estados completa. ADR 0021. |
| Guarda "propiedad agotada" (`LotesDePropiedadAgotados`, PR #233, tarea `contratos-lotes`, sin HU numerada) | **Reemplazada** por la regla por lote (sección 2, punto 5). |
| **HU-71** — estados del contrato | Se **extiende**, no se reemplaza: la máquina gana `conflicto`, y `pausado` conserva sus lotes. Las etiquetas del vocabulario del negocio suman "En conflicto". |
| **HU-92** — orden con varios lotes (amplía HU-70) | **Parcialmente superada.** Lo de "orden con N lotes y hectáreas solicitadas por lote" (`orden_lotes.hectareas_solicitadas`) queda superado por la sección 3: la orden es por toda la aplicación del contrato y la lista de lotes es de solo lectura. El campo "Número de aplicaciones" deja de elegirse: lo calcula el servidor. Todo esto se resuelve en el tramo siguiente, no en HU-96. |
| **HU-70** — asignación de equipos a la orden | **No cambia** el reparto por equipo y lote de la Orden de Trabajo. |

**Lo que no cambia.** El reparto por equipo y lote de la Orden de Trabajo; las
invariantes de `CLAUDE.md` (en particular la 7: toda transición pasa por el
servicio de dominio de su máquina de estados); y que `finalizar` es manual hasta
el tramo de órdenes.

---

**Nuevo en Sprint 19 de `plan_sprints.md`:** HU-96. El tramo de Órdenes de
aplicación (sección 3) no tiene HU todavía: se numera cuando se planifique.

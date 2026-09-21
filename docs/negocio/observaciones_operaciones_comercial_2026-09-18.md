# Corrección del dueño — Contratos, lotes y Órdenes de aplicación (18/9/2026)

**Origen.** El 18/9/2026 el dueño corrigió el enfoque con que se venía
construyendo la relación entre contrato, lotes y orden de aplicación, mirando el
panel andando. No es una ronda de features nueva sino una **corrección de dos
enfoques erróneos** ya implementados o planificados, más las decisiones que los
reemplazan. Este documento consolida esa conversación con el mismo criterio que
los del 13 y 14/9: no hace falta volver a preguntar qué se pidió.

**Dos tramos, los dos implementados.**

- **Tramo de contratos — exclusividad de lotes** (secciones 2 y 4): decidido e
  implementado, es la HU-96 del Sprint 19 de
  `docs/gestion/plan_sprints.md`. El porqué de la decisión está en el ADR 0021.
- **Tramo de Órdenes de aplicación** (sección 3): decidido e **implementado en
  la rama `feature/orden-correlativa` (pendiente de merge)**, es la HU-97 del
  Sprint 20 de `plan_sprints.md`. El porqué de la decisión está en el ADR 0022,
  que se apoya en el 0021. Lo que quedó **pendiente** —las limitaciones
  conocidas— está al final de la sección 3: no está resuelto.

---

## 1. Lo que se corrige — el enfoque anterior

Dos supuestos que el dueño desechó:

**1.1 La orden de aplicación con selección de lotes y hectáreas parciales por
lote.** HU-92 (ampliación de HU-70, ver
`observaciones_operaciones_comercial_2026-09-14.md` §4) modeló la orden como
una lista de lotes de la propiedad, cada uno con `hectareas_solicitadas`,
elegidos al crear la orden. De ahí venía lo demás: un número de aplicación
editable, varias órdenes con el mismo número para una misma aplicación, y una
guarda "una orden vigente por lote". **Enfoque erróneo:** la orden no elige
lotes ni reparte hectáreas parciales por lote; es siempre por la aplicación
completa del contrato (sección 3).

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
8. **`finalizar` era manual en este tramo.** El cierre automático del contrato
   al cerrarse la última aplicación llegó con las órdenes de aplicación
   (sección 3, punto 8): ahora también finaliza el contrato solo. La acción
   manual sigue existiendo — p. ej. el dueño decide finalizar en pleno proceso
   por falta de pago (sección 3, punto 9).

---

## 3. Decisiones — Órdenes de aplicación (implementado en `feature/orden-correlativa`, pendiente de merge · HU-97)

> **Implementado, pendiente de merge.** Es lo que el dueño dejó decidido el
> 18/9/2026 para las órdenes de aplicación, con sus aclaraciones sobre pausa,
> cancelación, pago y la guarda del contrato. Vive en la rama
> `feature/orden-correlativa` (HU-97, Sprint 20 de `plan_sprints.md`; ADR 0022).
> Las limitaciones conocidas del final **no están resueltas**.

1. **La orden es siempre UNA aplicación completa del contrato.** Un contrato
   tiene N aplicaciones (`aplicaciones_previstas`) y **cada una se realiza sobre
   TODAS las hectáreas y lotes del contrato**. No se agregan ni se quitan lotes
   a una orden, ni hay hectáreas parciales por lote: las hectáreas de la orden
   son las `hectareas_contratadas` del contrato.
2. **El número de aplicación es correlativo y lo calcula el servidor** (1, 2,
   3…). Ya no se elige: un contrato sin órdenes siempre parte de la aplicación
   1. Tope: no se puede pasar de `aplicaciones_previstas`.
3. **Cuándo se puede emitir una orden nueva.** Solo si (a) el contrato está
   `vigente` ("En Ejecución"; antes se permitía cualquier estado a propósito,
   decisión que se **revoca**), (b) no tiene una aplicación **abierta** (estados
   abiertos: `emitida`, `vigente`, `pausada`) y (c) no agotó sus aplicaciones.
   Garantía en la base de datos: índice único parcial "una orden abierta por
   contrato" (`ope_ordenes_aplicacion (contrato_id) WHERE deleted_at IS NULL AND
   estado IN ('emitida','vigente','pausada')`) e índice único parcial del número
   por contrato (`(contrato_id, nro_aplicacion)`, excluyendo las canceladas por
   fuerza mayor).
4. **Máquina de estados de la orden** (servicio de dominio
   `MaquinaEstadosOrden`, invariante 7):

   | Desde | Hacia |
   |---|---|
   | `emitida` | `vigente` |
   | `vigente` | `pausada`, `consumida`, `cancelada` |
   | `pausada` | `vigente`, `cancelada` |
   | `consumida`, `cancelada`, `vencida` | sin salida |

   `vencida` sigue sin disparador de negocio. Una orden `emitida` que ya no se
   quiere se **elimina** (baja lógica), no se cancela; solo las `emitida` se
   editan y eliminan.
5. **Pausar, cerrar y cancelar.**
   - **Pausar** (`vigente → pausada`) exige un **motivo escrito**. **Reanudar**
     (`pausada → vigente`) no pide nada.
   - **Cerrar** (`vigente → consumida`) es una acción **manual** del encargado,
     con el informe del equipo a la vista. Nada cierra la orden solo.
   - **Cancelar** (`vigente | pausada → cancelada`) exige una **causa**
     (`cliente` o `fuerza_mayor`) y un **motivo** escrito. La falta de pago se
     registra como causa `cliente` más su motivo.
6. **Numeración y causa de cancelación.** Una aplicación cancelada por **fuerza
   mayor** (p. ej. un dron caído) **NO consume su número**: se rehace con el
   mismo. Una cancelada por causa del **cliente SÍ lo consume**: la siguiente
   lleva el número que sigue.
7. **La app de campo nunca pausa, cierra ni cancela una orden: eso es exclusivo
   del panel.** El operador decide, junto con el dueño, leyendo lo que reporta
   el equipo. Lo que la app sí registra son las **incidencias**
   (`ope_incidencias`) y las **pausas de sesión** (`ope_pausas`) en los
   trabajos. En la orden aparece un **badge "Con inconvenientes"** (con conteo)
   cuando sus trabajos tienen incidencias o pausas registradas; el detalle vive
   en las órdenes de trabajo. Es solo informativo: **no cambia estados solo**.
   Los motivos típicos son: clima, entrega tardía de la calda, acceso
   complicado a la propiedad, falta de insumos o equipamiento, enfermedad o
   accidente, y **el pago** — mucho del trabajo se paga en efectivo y, si no se
   registra el pago, el dueño puede decidir finalizar el contrato.
8. **La última aplicación cerrada finaliza el contrato y libera sus lotes.**
   Cuando se **cierra** la última aplicación (número de la orden cerrada ≥
   `aplicaciones_previstas`), el contrato pasa solo a `finalizado`
   ("Ejecutado") y libera sus lotes. Es el cierre automático que la sección 2
   (punto 8) dejaba para este tramo: evento de dominio `AplicacionCerrada`,
   oyente en Comercial `FinalizarContratoPorUltimaAplicacion`, y solo si el
   contrato está `vigente`. Una aplicación **cancelada no** dispara esto.
9. **Un contrato no se puede cancelar ni finalizar mientras tenga una
   aplicación abierta:** primero se cierra o se cancela esa aplicación. Con la
   aplicación ya cancelada, el contrato **sí** se puede cancelar o finalizar
   aunque queden aplicaciones pendientes (el dueño decide, p. ej. por falta de
   pago). La guarda vive en `MaquinaEstadosContrato` y lee las órdenes por el
   contrato de `Operaciones` (`LecturaResumenOrdenesContrato`, campo
   `abiertas`).
10. **Las órdenes ya no validan choques de lotes.** La guarda "orden vigente
    duplicada en lote" se **elimina**: eso se garantiza antes, entre contratos
    (ADR 0021). La orden solo se ocupa de la aplicación.
11. **Modelo de datos.** `ope_orden_lotes` se **conserva** como copia
    automática, tomada al emitir la orden, de TODOS los lotes del contrato, con
    `hectareas_solicitadas` = las hectáreas completas del lote: el conjunto de
    lotes de una orden ya emitida no cambia y el catálogo de la app de campo
    (`GET /api/sync/catalogo`) mantiene su forma. Columnas nuevas en
    `ope_ordenes_aplicacion`: `motivo_pausa`, `pausada_at`, `reanudada_at`,
    `cerrada_at`, `cancelada_at`, `causa_cancelacion`, `motivo_cancelacion`;
    `CHECK` de estado con `pausada` y `cancelada` (solo Postgres). Permisos
    nuevos: `operaciones.orden.pausar` (pausar y reanudar),
    `operaciones.orden.cerrar` y `operaciones.orden.cancelar`.
12. **El índice de órdenes del panel muestra** el número de aplicación ("2 de
    3"), la cantidad de lotes del contrato y la cantidad de órdenes de trabajo
    realizadas de esa orden, más el badge de inconvenientes. La sección
    "Lotes" de la orden (formulario y detalle) es una **lista de solo lectura**
    con el código de cada lote del contrato y su propiedad. En el contrato, el
    botón "Nueva orden de aplicación" se **deshabilita** si el contrato no está
    vigente, tiene una aplicación abierta o ya completó sus aplicaciones.

**Lo que estaba por definir, resuelto al implementar.** La versión anterior de
este documento dejaba abierto cómo se representa la orden cancelada, con su
motivo y causa: la máquina `emitida → vigente → consumida | vencida` no tenía
un estado de cancelación. Se resolvió con dos estados nuevos, `pausada` y
`cancelada`, y las columnas de causa y motivo (puntos 4, 5 y 11).

**Limitaciones conocidas — PENDIENTES, no resueltas.**

- **(a) La app de campo no se entera** de que una orden fue cancelada, pausada
  o cerrada: el catálogo solo entrega órdenes `vigente` y el sync no tiene
  forma de retirar registros.
- **(b) Las sesiones ya abiertas en el campo no se frenan** al pausar o
  cancelar la orden.
- **(c) Si se agrega un lote al contrato mientras hay una aplicación abierta,
  esa aplicación no lo incluye** (la siguiente sí): el conjunto de lotes de una
  orden ya emitida no cambia.
- **(d) Las migraciones fallan a propósito**, con un mensaje que nombra los
  contratos, si la base ya trae órdenes con número repetido o varias abiertas
  por contrato: hay que resolver esos datos a mano y volver a migrar.

---

## 4. Cómo cambia cada HU afectada

| HU / pieza | Qué cambia |
|---|---|
| **HU-96** (nueva, Sprint 19) | Implementa la sección 2: retención de lotes por campaña, estado `conflicto`, rechazo del guardado, máquina de estados completa. ADR 0021. El cierre automático del contrato, que allí quedó manual, llega con HU-97. |
| **HU-97** (nueva, Sprint 20) | Implementa la sección 3: orden = aplicación completa, número correlativo, una abierta por contrato, estados `pausada` y `cancelada`, cierre manual, cierre del contrato al cerrarse la última aplicación y guarda de cancelar/finalizar con aplicación abierta. Implementada en `feature/orden-correlativa`, pendiente de merge. ADR 0022. |
| Guarda "propiedad agotada" (`LotesDePropiedadAgotados`, PR #233, tarea `contratos-lotes`, sin HU numerada) | **Reemplazada** por la regla por lote (sección 2, punto 5). |
| Guarda "orden vigente duplicada en lote" | **Eliminada**: la reemplaza "una orden abierta por contrato" y los choques de lotes se garantizan entre contratos (sección 3, puntos 3 y 10). |
| **HU-71** — estados del contrato | Se **extiende**, no se reemplaza: la máquina gana `conflicto`, y `pausado` conserva sus lotes. Las etiquetas del vocabulario del negocio suman "En conflicto". |
| **HU-25** — ABM de órdenes de aplicación | La máquina de estados de la orden se amplía (`pausada`, `cancelada`), con número correlativo y una sola orden abierta por contrato (HU-97). |
| **HU-92** — orden con varios lotes (amplía HU-70) | **Parcialmente superada por HU-97.** Lo de "orden con N lotes y hectáreas solicitadas por lote" (`orden_lotes.hectareas_solicitadas`) queda superado por la sección 3: la orden es por toda la aplicación del contrato, `orden_lotes` queda como copia automática de todos sus lotes y la lista de lotes es de solo lectura. "Una orden vigente por lote" pasa a "una orden abierta por contrato". El campo "Número de aplicaciones" deja de elegirse: lo calcula el servidor. |
| **HU-70** — asignación de equipos a la orden | **No cambia** el reparto por equipo y lote de la Orden de Trabajo. |

**Lo que no cambia.** El reparto por equipo y lote de la Orden de Trabajo; las
invariantes de `CLAUDE.md` (en particular la 7: toda transición pasa por el
servicio de dominio de su máquina de estados); y que finalizar el contrato a
mano sigue siendo posible, ahora junto al cierre automático de la última
aplicación.

---

**Nuevo en `plan_sprints.md`:** HU-96 (Sprint 19) y HU-97 (Sprint 20).

---

## 5. Ronda del 19/9/2026 — contrato: estados a la vista, avisos y horario por lote (HU-98)

Pedidos del dueño sobre la ficha de edición del contrato, implementados en
`feature/contrato-pasos` (Sprint 21). **No cambian ninguna regla de negocio**
(ADR 0021 y 0022): cambian lo que el panel muestra.

| Pedido | Qué se hizo |
|---|---|
| Pasos con forma de flecha en la ficha de edición, como en campañas; cada paso abre el mismo alert que el listado | `molecules/step-arrow` con cinco pasos «En Aprobación → En Ejecución → Pausado → Ejecutado → Cancelado»; en un contrato en conflicto «En conflicto» ocupa el primer lugar. Solo en edición. Los tonos son los de los badges del listado; los pasos recorridos y el estado final se suavizan solos. El cambio vuelve a la ficha. |
| Los párrafos de apoyo para aplicar el cambio de estado | `comercial.contrato.estado_ayuda.*`, un texto por estado, bajo los pasos. |
| Con órdenes de aplicación en ejecución, un alert que explique por qué no se puede cancelar ni finalizar; primero la orden y después el contrato | Modal informativo (sin botón de confirmar) que dice qué aplicación sigue abierta, pide cerrarla o cancelarla primero y lleva a la orden. Vale en los pasos y en el listado. El servidor sigue rechazándolo (ADR 0022 punto 9). |
| Contratos que comparten lotes al pasar a «En ejecución»: remarcar el que queda con conflictos, para decidir | Al aprobar, el modal lista los contratos que van a quedar «En conflicto» y los lotes compartidos. En la ficha del contrato en conflicto, un aviso lista con quién choca y ofrece cancelarlo o quitar los lotes en conflicto. |
| Colores de los estados también en el alert de confirmación | El círculo del ícono lleva el color del estado al que se pasa, y dentro va «estado actual → estado destino» con los mismos badges. |
| Cargar siempre la campaña activa en el formulario; si hay varias, la primera | El formulario ofrece elegida la primera campaña `abierta` (por código) cuando el contrato no trae una. |
| Horario por lote: un componente moderno, con inicio y fin en la misma casilla | `atoms/time-range`: una sola casilla compacta («10:00 a. m. – 3:30 p. m.») y un popup anclado a ella —no un modal— con reloj estilo Material de 12 horas y «a. m.»/«p. m.» explícitos junto a cada hora (el período de la hora de fin se elige solo para quedar después del inicio). Se envía igual que antes (`lotes[N][hora_inicio]`/`hora_fin`, 24 h). |
| El modal de lotes no tiene paginación y hace scroll; lo mismo la tabla de lotes del contrato (20 por página) | El modal de una propiedad y la tabla de lotes del contrato se paginan de a 20; lo marcado en otras páginas se conserva, «seleccionar todos» abarca todas y el modal cuenta los seleccionados; el diálogo deja siempre a la vista «Guardar selección». |
| «Ver contrato» en un lote en conflicto deforma la tabla de lotes | La tabla usa una cuadrícula compartida: todas las columnas quedan alineadas con o sin «Ver contrato» (que en un bloque angosto queda como ícono). |

**Dos lecturas que conviene confirmar con el dueño** (se tomó la más coherente con
lo ya decidido, y queda escrito acá para corregirla si no era esa):

1. **«La restricción no es rígida, es informativa, para que el usuario indique el
   motivo y la causa del cierre prematuro».** Se leyó junto con «primero se
   finaliza o cancela la orden antes del contrato» (ADR 0022 punto 9): el aviso es
   informativo —no un error después de confirmar— y lleva a la orden, donde se
   indican causa y motivo (punto 5). El contrato **no** se cierra con la aplicación
   abierta. Si lo que se quería era poder cerrar el contrato con la aplicación
   abierta pidiendo el motivo ahí mismo, contradice el punto 9 y la alternativa
   descartada de cancelar en cascada: sería una decisión nueva (ADR).
2. **«Cancelarlo o revocar dicho contrato» (el que queda en conflicto).** Se leyó
   como las dos salidas que ya define el ADR 0021: cancelar el contrato, o quitarle
   los lotes en conflicto (vuelve solo a «En Aprobación»). No existe una transición
   «revocar la aprobación» del contrato que ya está en ejecución.

## 6. Ronda del 21/9/2026 — contrato: solo qué lotes entran (HU-103)

Pedidos del dueño sobre el formulario de contrato, implementados en
`feature/contrato-lotes-borrador` (Sprint 25). **Cambia una regla de negocio**:
el horario por lote deja de ser un dato del contrato.

| Pedido | Qué se hizo |
|---|---|
| «Día completo» y «Horario» están adelantados en el contrato: esa información va directo a la orden de trabajo; el contrato solo elige qué lotes entran | Se quitaron las dos columnas de la tabla de lotes, las reglas de `CrearContratoRequest`/`ActualizarContratoRequest` y la escritura en `CrearContrato`/`ActualizarContrato`. Las columnas `com_contrato_lotes.hora_inicio`/`hora_fin` quedan en la tabla (nullable) con lo que ya tuvieran; nada las lee. Operaciones nunca las leyó: el turno de cada lote ya se carga en la orden de trabajo (`turno`, `turno_hora_inicio`, `turno_hora_fin`). Reemplaza la fila «Horario por lote» de la ronda del 19/9 (§5): `atoms/time-range` sigue en el catálogo, lo usa Pausas. |
| Los lotes se ordenan L1, L10, L11…; tiene que ser 1, 2, 3, … como números, respetando el orden literal de lo que el código lleve delante — en la tabla y en el modal | Orden natural por código en los dos lados: el servidor manda los lotes de cada propiedad con `Lote::scopeOrdenadosPorCodigo()` (el mismo del listado de Lotes), el modal los recorre en ese orden y la tabla reacomoda sus filas al agregar; al cargar la edición se ordena con `Dominio/OrdenCodigoLote` (misma regla, en memoria). |
| Un mensaje arriba de la tabla con la suma de hectáreas de los lotes y las que encarga el contrato; no es una restricción | Aviso `info` sobre la tabla: «15 lotes elegidos, que suman 900,00 ha. Son 50,00 ha menos que las 950,00 ha contratadas. Es solo una guía: puedes guardar igual.» Se actualiza al agregar o quitar lotes y al escribir las hectáreas contratadas. Solo la diferencia va en color alert; el resto conserva el color del aviso. Sin hectáreas contratadas todavía, pide cargarlas; si coincide, lo dice. |
| El nombre de la propiedad como columna de la tabla | Columna «Propiedad» entre Código y Hectáreas; se retiró la banda de título por grupo (las filas siguen agrupadas por propiedad en el DOM, de ahí salen las pills y el modal). |
| Al salir por un input group a crear lo que falta y volver, los switch vuelven apagados («Brinda alimentación»); que se consideren también radio, casilla y `range`, en todos los formularios | La causa: el borrador se armaba con `FormData` y reponía por nombre, así que escribía sobre el `<input type="hidden" value="0">` que acompaña a cada switch y nunca sobre el switch. Ahora lo hace `resources/js/shared/borrador-formulario.js` para todo formulario con un enlace `data-ag-link-accent`: cada control se guarda por lo que es (tildado o no, cuál radio, valor). En contratos vuelven además las propiedades y los lotes elegidos, que antes se perdían en el alta. |

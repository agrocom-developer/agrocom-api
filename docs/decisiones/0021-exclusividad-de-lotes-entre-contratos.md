# ADR 0021 — Exclusividad de lotes entre contratos: lotes retenidos por campaña y estado `conflicto`

**Estado:** Aceptada · **Fecha:** 18/9/2026 · **Origen:** corrección de negocio del dueño sobre la guarda "propiedad agotada" de la tarea `contratos-lotes` (16/9/2026, PR #233) — registrada en `docs/negocio/observaciones_operaciones_comercial_2026-09-18.md` · **Se implementa en:** HU-96, Sprint 19 de `docs/gestion/plan_sprints.md`.

## Contexto

El 16/9/2026 el contrato pasó a elegir lotes concretos de las propiedades del cliente (`com_contrato_lotes`, PR #233; reemplazó a `com_contrato_alcances`, una tabla del diseño de los ADR 0018/0020 que nunca llegó a usarse). Esa tarea incluyó una guarda para que dos contratos no se disputaran la misma superficie: rechazaba el guardado solo cuando el 100% de los lotes activos de una **propiedad** ya estaba cubierto por otros contratos `vigente` de la misma campaña (excepción `LotesDePropiedadAgotados`).

El dueño la corrigió el 18/9/2026: la unidad que se compromete es el **lote**, no la propiedad. Con la guarda por propiedad, mientras quedara un solo lote libre en la propiedad, dos contratos podían tomar el mismo lote de los demás — la misma superficie comprometida dos veces dentro de la campaña. Además, el modelo no tenía cómo decir "este contrato en aprobación compite por un lote con otro que ya se aprobó": el contrato seguía en `borrador` sin ninguna señal.

Dos piezas de contexto que acotan la decisión:

- "Misma campaña" es el alcance del bloqueo porque la campaña es un catálogo compartido (ADR 0015, corrección del 15/9/2026): muchos contratos de muchos clientes apuntan a la misma fila de `cpn_campanias`. El mismo lote en campañas distintas no compite.
- Toda transición de estado pasa por el servicio de dominio de la máquina de estados (invariante 7 de `CLAUDE.md`); un estado nuevo entra por ahí, no por un `estado = ...` suelto.

## Decisión

### 1. La retención es por lote y por campaña

Un contrato **retiene** sus lotes cuando está `vigente` ("En Ejecución") o `pausado`. Mientras los retiene, esos lotes quedan bloqueados para cualquier otro contrato de la **misma campaña**. Los libera únicamente pasar a `finalizado` ("Ejecutado", cuando se ejecuta la última aplicación) o a `cancelado`.

**`pausado` conserva sus lotes.** Una pausa es una interrupción, no una salida (HU-71): solo cancelar o finalizar los libera. "Qué estados retienen lotes" se define en un único lugar, el enum `EstadoContrato`, y lo consumen tanto la guarda del servidor como la lectura para la UI (punto 5).

**Un contrato `borrador` ("En Aprobación") no retiene.** Todavía es una propuesta y no compromete superficie: el mismo lote puede repetirse en varios contratos mientras ninguno esté aprobado.

### 2. Nuevo estado `conflicto` ("En conflicto"), persistido y fijado solo por el sistema

Al aprobarse un contrato (`borrador → vigente`), los otros contratos de la misma campaña que están en `borrador` y comparten al menos un lote con él pasan automáticamente a `conflicto`. Un contrato en `conflicto`:

- **no se puede aprobar**;
- vuelve solo a `borrador` cuando ya no comparte ningún lote con un contrato que retenga lotes — porque se le quitó el lote editando el contrato, o porque el otro contrato se canceló o finalizó;
- también puede cancelarse (`conflicto → cancelado`).

**Nadie puede pedir `conflicto` a mano desde el panel**: el panel no lo ofrece ni lo acepta como destino. `borrador → conflicto` y `conflicto → borrador` los dispara únicamente el sistema. Un contrato en `conflicto` tampoco pasa directo a `vigente`: primero vuelve a `borrador` y desde ahí se aprueba.

### 3. La máquina de estados del contrato, completa

Tabla de transiciones permitidas + servicio de dominio `MaquinaEstadosContrato` (invariante 7):

| Desde | Hacia |
|---|---|
| `borrador` | `vigente`, `cancelado`, `conflicto` |
| `conflicto` | `borrador`, `cancelado` |
| `vigente` | `finalizado`, `cancelado`, `pausado` |
| `pausado` | `vigente` |
| `finalizado`, `cancelado` | — (sin salida) |

**La reconciliación de conflictos por campaña vive en `MaquinaEstadosContrato`**: es el único punto que mueve un contrato entre `borrador` y `conflicto`, dentro del mismo servicio que ya es el único que escribe `estado`. Ni un controlador ni un listener tocan esos dos estados por su cuenta.

### 4. El servidor rechaza el guardado de un lote ya retenido

**Registrar** un contrato, o **editarlo** agregándole un lote, que ya está retenido por otro contrato (`vigente` o `pausado`) de la misma campaña se rechaza con un mensaje que nombra el lote y el contrato que lo tiene.

**En edición solo se validan los lotes nuevos agregados**, no los que el contrato ya traía: un contrato en `conflicto` sigue siendo editable mientras arrastra lotes que ya no puede tener libres. El rechazo es para lo que se suma, no para lo que ya estaba.

Esto **reemplaza** la guarda por propiedad completa: la regla pasa a ser por lote y la excepción `LotesDePropiedadAgotados` deja de aplicar.

### 5. La lectura para la UI usa la misma definición de "retener"

`LecturaOcupacionLotesPorCampania` (solo lectura: excluir del modal de selección los lotes ocupados, marcar los que chocan dentro del contrato en edición) usa los mismos estados que retienen lotes que la guarda del servidor. La pantalla y el servidor no pueden discrepar sobre qué lote está ocupado. La lectura pinta; quien rechaza un guardado es el servidor.

### 6. Migración

`conflicto` se agrega al `CHECK` `com_contratos_estado_chk` (solo Postgres, mismo criterio que `pausado` en HU-71). Solo suma un valor al enum de la base: no reescribe la tabla ni reclasifica contratos ya cargados.

## Consecuencias

**A favor**

- La misma superficie no se compromete dos veces en una campaña: el bloqueo se da al aprobar, que es cuando el contrato deja de ser una propuesta.
- El encargado ve de un vistazo qué contratos en aprobación compiten por un lote ya comprometido, sin revisar borrador por borrador — y el estado se levanta solo cuando el choque desaparece.
- La regla coincide con la unidad real que se contrata (el lote) y es estrictamente más fina que la anterior: la guarda por propiedad era el caso particular en que *todos* los lotes estaban tomados.
- Una sola definición de "retener lotes" para el servidor y para la pantalla.

**En contra, y asumido**

- La regla vive en código, no en el esquema: cruza `com_contrato_lotes` con el `estado` y la `campania_id` del contrato dueño, algo que un `CHECK` o un índice único no expresan. La garantía depende de que todo guardado y toda transición pasen por los casos de uso y por `MaquinaEstadosContrato` (invariante 7).
- Un estado más en la máquina y en el panel: listados, filtros y etiquetas del contrato tienen que conocer "En conflicto".
- **Un contrato `pausado` bloquea superficie mientras dure la pausa**: hasta que se cancele o se finalice, sus lotes no están disponibles para otros contratos de la campaña. Es la decisión del dueño (la pausa no es una salida).
- **La liberación por ejecución es manual en este tramo.** "Hasta que se ejecute la última aplicación" se materializa hoy con `finalizar` a mano; el cierre automático del contrato al cerrarse la última aplicación llega con las órdenes de aplicación, en un tramo posterior.
- La migración solo amplía el `CHECK`; no reclasifica contratos ya cargados.

## Alternativas descartadas

**Mantener la guarda por propiedad completa** — descartada por el dueño: solo se dispara cuando la propiedad está agotada, así que deja pasar dos contratos sobre el mismo lote mientras quede cualquier otro lote libre en la propiedad. No expresa lo que importa (un lote aprobado no está disponible para otro contrato de la campaña).

**`conflicto` solo visual, sin persistir** (calcularlo al vuelo para pintar el listado) — descartada: sin un estado persistido, "no se puede aprobar un contrato en conflicto" tendría que vivir en la pantalla y no en la máquina de estados, no habría forma de listar ni filtrar por él, y la regla dejaría de ser una garantía de código (invariante 7).

**Que `pausado` libere los lotes** — descartada: `pausado → vigente` no tiene guarda, así que al reanudar podrían quedar dos contratos aprobados sobre el mismo lote, un conflicto entre contratos ya en ejecución que la regla existe justamente para evitar. Una pausa es una interrupción, no una salida.

**Validar solo en el cliente (JS)** — descartada: excluir del modal los lotes ocupados es comodidad de pantalla, no garantía. Un pedido directo al servidor, o dos usuarios guardando a la vez, se saltan cualquier chequeo del navegador; el rechazo tiene que ser del servidor.

## Adenda (19/9/2026) — El conflicto se remarca en el panel: al aprobar y en la ficha

**Contexto.** El sistema ya fijaba `conflicto` al aprobar un contrato (punto 2), pero el usuario solo lo veía después, como un estado en el listado. El dueño pidió remarcarlo en el momento de pasar un contrato a «En Ejecución» y en la ficha del contrato en conflicto, para que se decida qué hacer con él.

**Decisión.** No cambia ninguna regla; cambia lo que se muestra.
- **Al aprobar**, el modal de confirmación —el del paso «En ejecución» de la ficha y el de la acción «Aprobar» del listado— lista los contratos `borrador` de la misma campaña que comparten lotes con este y que van a pasar a «En conflicto», con los lotes compartidos y un enlace a cada uno (`LecturaOcupacionLotesPorCampania::contratosQueEntranEnConflicto()`, solo lectura: quien los mueve sigue siendo `MaquinaEstadosContrato::reconciliarConflictos()`).
- **En la ficha de un contrato en conflicto**, un aviso arriba lista los contratos en ejecución con los que choca y los lotes compartidos, y ofrece las dos salidas del punto 2: cancelar el contrato, o revisar sus lotes y quitar los que chocan (vuelve solo a `borrador`). En los pasos, «En conflicto» ocupa el primer lugar y «En ejecución» aparece bloqueado, con la pista de por qué.

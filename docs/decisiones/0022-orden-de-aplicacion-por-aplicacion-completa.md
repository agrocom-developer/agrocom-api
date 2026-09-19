# ADR 0022 — La orden de aplicación es una aplicación completa: número correlativo, una abierta por contrato y estados `pausada` y `cancelada`

**Estado:** Aceptada · **Fecha:** 19/9/2026 · **Origen:** corrección de negocio del dueño (18/9/2026) sobre el enfoque de la Orden de Aplicación de HU-92 (Sprint 16) — registrada en `docs/negocio/observaciones_operaciones_comercial_2026-09-18.md` (sección 3) · **Reemplaza la premisa de:** HU-92 (orden con N lotes elegidos y hectáreas solicitadas por lote) · **Se apoya en:** ADR 0021 · **Se implementa en:** HU-97, Sprint 20 de `docs/gestion/plan_sprints.md`.

## Contexto

HU-92 (14/9/2026) modeló la orden de aplicación como una lista de lotes de la propiedad, cada uno con `hectareas_solicitadas`, elegidos al crear la orden. De ahí se desprendía el resto del diseño de entonces: un número de aplicación que se elegía a mano ("Número de aplicaciones"), varias órdenes con el mismo número para una misma aplicación (cada una con su tanda de lotes), una guarda "una orden vigente por lote" (`orden_vigente_duplicada_en_lote`) para que dos órdenes no se disputaran un lote, y —a propósito— la posibilidad de emitir órdenes con el contrato en cualquier estado.

El dueño corrigió la premisa el 18/9/2026, mirando el panel andando: un contrato tiene N aplicaciones (`aplicaciones_previstas`) y **cada aplicación se realiza sobre TODAS las hectáreas y lotes del contrato**. La orden no elige lotes ni reparte hectáreas parciales por lote; es siempre una aplicación completa.

Piezas de contexto que acotan la decisión:

- **ADR 0021** ya garantiza, entre contratos, que un lote no se compromete dos veces dentro de la campaña. Por eso la orden no necesita validar choques de lotes: solo se ocupa de la aplicación. Ese mismo ADR dejó manual `finalizar` ("el cierre automático del contrato al cerrarse la última aplicación llega con las órdenes de aplicación, en un tramo posterior"): este ADR es ese tramo.
- Toda transición de estado pasa por el servicio de dominio de la máquina de estados (invariante 7 de `CLAUDE.md`). Los estados nuevos y el cierre del contrato entran por ahí, no por un `estado = ...` suelto.
- Entre módulos se viaja por contratos o eventos de dominio (ADR 0003): `Comercial` no lee la tabla de órdenes de `Operaciones` ni al revés.
- La app de campo es offline-first y el catálogo (`GET /api/sync/catalogo`) solo baja órdenes `vigente`; el sync no tiene forma de retirar un registro ya entregado (ver Consecuencias).

## Decisión

### 1. Una orden es UNA aplicación completa del contrato

La orden cubre todas las hectáreas y todos los lotes del contrato; sus hectáreas son las `hectareas_contratadas`. No se agregan ni se quitan lotes a una orden, ni hay hectáreas parciales por lote.

### 2. El número de aplicación es correlativo y lo calcula el servidor

`nro_aplicacion` es 1, 2, 3… y ya no se elige: un contrato sin órdenes siempre parte de la aplicación 1. Tope: no se puede pasar de `aplicaciones_previstas`.

### 3. Cuándo se puede emitir una orden nueva

Solo si se cumplen las tres condiciones:

- (a) el contrato está `vigente` ("En Ejecución"). Antes se permitía cualquier estado, a propósito; esa decisión se **revoca**;
- (b) el contrato no tiene una aplicación **abierta** — las órdenes en estado `emitida`, `vigente` o `pausada`;
- (c) el contrato no agotó sus aplicaciones.

**Garantía en la base de datos, no solo en código:** índice único parcial "una orden abierta por contrato" (`ope_ordenes_aplicacion (contrato_id) WHERE deleted_at IS NULL AND estado IN ('emitida','vigente','pausada')`) e índice único parcial del número por contrato (`(contrato_id, nro_aplicacion)` excluyendo las canceladas por fuerza mayor y las eliminadas lógicamente).

### 4. La máquina de estados de la orden, completa

Tabla de transiciones permitidas + servicio de dominio `MaquinaEstadosOrden` (invariante 7):

| Desde | Hacia |
|---|---|
| `emitida` | `vigente` |
| `vigente` | `pausada`, `consumida`, `cancelada` |
| `pausada` | `vigente`, `cancelada` |
| `consumida`, `cancelada`, `vencida` | — (sin salida) |

`vencida` sigue sin disparador de negocio: ninguna transición llega a ella. Una orden `emitida` que ya no se quiere se **elimina** (baja lógica), no se cancela; solo las `emitida` se editan y eliminan. Los estados **abiertos** son `emitida`, `vigente` y `pausada`; los cerrados, `consumida`, `cancelada` y `vencida`.

### 5. Pausar, cerrar y cancelar

- **Pausar** (`vigente → pausada`) exige un motivo escrito. **Reanudar** (`pausada → vigente`) no pide nada.
- **Cerrar** (`vigente → consumida`) es una acción **manual** del encargado, con el informe del equipo a la vista. Nada cierra la orden solo.
- **Cancelar** (`vigente | pausada → cancelada`) exige una **causa** (`cliente` o `fuerza_mayor`) y un **motivo** escrito. La falta de pago se registra como causa `cliente` más su motivo.

### 6. Numeración y causa de cancelación

Una aplicación cancelada por **fuerza mayor** (p. ej. un dron caído) **no consume su número**: se rehace con el mismo. Una cancelada por causa del **cliente** **sí** lo consume: la siguiente lleva el número que sigue.

### 7. Solo el panel pausa, cierra y cancela; la app registra lo que pasa

La app de campo **nunca** pausa, cierra ni cancela una orden. El operador decide, junto con el dueño, leyendo lo que reporta el equipo. Lo que la app sí registra son incidencias (`ope_incidencias`) y pausas de sesión (`ope_pausas`) en los trabajos. La orden muestra un **badge "Con inconvenientes"** (con conteo) cuando sus trabajos tienen incidencias o pausas registradas; el detalle vive en las órdenes de trabajo. Es solo informativo: no cambia estados solo.

Los motivos típicos de una pausa o cancelación son: clima, entrega tardía de la calda, acceso complicado a la propiedad, falta de insumos o equipamiento, enfermedad o accidente, y el pago — mucho del trabajo se paga en efectivo y, si no se registra el pago, el dueño puede decidir finalizar el contrato.

### 8. Cerrar la última aplicación finaliza el contrato

Al cerrarse una orden (`vigente → consumida`) se anuncia el evento de dominio `AplicacionCerrada` (Operaciones → Comercial). El oyente de Comercial, `FinalizarContratoPorUltimaAplicacion`, pasa el contrato a `finalizado` ("Ejecutado") y libera sus lotes (ADR 0021) cuando el número de la orden cerrada es mayor o igual que `aplicaciones_previstas` — y solo si el contrato está `vigente`. La transición pasa por `MaquinaEstadosContrato` (invariante 7). Una aplicación **cancelada** no dispara esto.

### 9. Un contrato no se cancela ni se finaliza con una aplicación abierta

Primero se cierra o se cancela esa aplicación. Con la aplicación ya cancelada, el contrato **sí** se puede cancelar o finalizar aunque queden aplicaciones pendientes: lo decide el dueño (p. ej. por falta de pago). La guarda vive en `MaquinaEstadosContrato` y lee las órdenes a través del contrato de lectura de `Operaciones` (`LecturaResumenOrdenesContrato`, campo `abiertas`), nunca por su tabla.

### 10. Las órdenes ya no validan choques de lotes

La guarda "orden vigente duplicada en lote" se **elimina**. Los choques se garantizan antes, entre contratos (ADR 0021); la orden solo se ocupa de la aplicación.

### 11. `ope_orden_lotes` se conserva como copia automática

Al emitir la orden se copian a `ope_orden_lotes` **todos** los lotes del contrato, con `hectareas_solicitadas` = las hectáreas completas del lote. El conjunto de lotes de una orden ya emitida no cambia, y `GET /api/sync/catalogo` mantiene su forma (`lotes[{lote_id, hectareas_solicitadas}]`). El reparto por equipo y lote de la Orden de Trabajo (HU-70) no cambia. En el panel, la sección "Lotes" de la orden es una lista de solo lectura con el código de cada lote del contrato y su propiedad.

### 12. Migración y permisos

Columnas nuevas en `ope_ordenes_aplicacion`: `motivo_pausa`, `pausada_at`, `reanudada_at`, `cerrada_at`, `cancelada_at`, `causa_cancelacion`, `motivo_cancelacion`. El `CHECK` de estado suma `pausada` y `cancelada` (solo Postgres, mismo criterio que en ADR 0021). Permisos nuevos: `operaciones.orden.pausar` (pausar y reanudar), `operaciones.orden.cerrar` y `operaciones.orden.cancelar`.

**Las migraciones fallan a propósito** si la base ya trae órdenes que no cumplen las reglas nuevas —número de aplicación repetido dentro de un contrato, o varias órdenes abiertas por contrato—, con un mensaje que nombra los contratos. Esos datos no se pueden reparar solos sin inventar historia: se resuelven a mano y se vuelve a migrar.

## Consecuencias

**A favor**

- Cada aplicación se hace de punta a punta y el número dice cuál es dentro del contrato ("2 de 3"); ya no hay aplicaciones repartidas en varias órdenes con el mismo número.
- Una sola aplicación abierta por contrato, garantizada por un índice de la base y no solo por código.
- Un solo lugar para los choques de lotes (entre contratos, ADR 0021): la orden deja de repetir esa validación.
- Se completa la liberación de lotes que ADR 0021 dejó manual: cerrar la última aplicación finaliza el contrato y libera sus lotes.
- La decisión de pausar, cerrar o cancelar queda en el panel, en manos del operador y del dueño, con causa y motivo auditados (invariante 9).
- El catálogo de la app de campo no cambia de forma.

**En contra, y asumido**

- **La app de campo no se entera** de que una orden fue cancelada, pausada o cerrada: el catálogo solo entrega órdenes `vigente` y el sync no tiene forma de retirar registros. *Pendiente, no resuelto.*
- **Las sesiones ya abiertas en el campo no se frenan** al pausar o cancelar la orden. *Pendiente, no resuelto.*
- **Un lote agregado al contrato mientras hay una aplicación abierta no entra en esa aplicación** (la siguiente sí): el conjunto de lotes de una orden ya emitida no cambia. *Pendiente, no resuelto.*
- **Las migraciones frenan** si la base ya trae números repetidos o varias abiertas por contrato: hay que resolver esos datos a mano antes de migrar.
- **El cierre es manual, así que la finalización automática depende de él:** si el encargado no cierra la última aplicación, el contrato no finaliza y sus lotes siguen retenidos. Y si al cerrarla el contrato no está `vigente`, el oyente no hace nada y el encargado lo resuelve a mano.
- **Dos estados más en la máquina** (`pausada`, `cancelada`) que listados, filtros y etiquetas de la orden tienen que conocer.
- **Una aplicación cancelada por fuerza mayor y la que la rehace comparten número** dentro del contrato: por eso el índice único del número las excluye. El historial queda completo.
- **Las reglas de emisión y de finalización viven en código y en índices**, no solo en el esquema: la garantía depende de que toda emisión y toda transición pasen por los casos de uso y por `MaquinaEstadosOrden` / `MaquinaEstadosContrato` (invariante 7).
- Se revoca la decisión anterior de permitir emitir órdenes con el contrato en cualquier estado.
- `vencida` sigue sin disparador de negocio.

## Alternativas descartadas

**Mantener la selección de lotes por orden** (el enfoque de HU-92) — descartada por el dueño: cada aplicación es sobre todas las hectáreas del contrato, no sobre un subconjunto elegido por orden. Elegir lotes y hectáreas parciales permitía aplicaciones parciales, varias órdenes con el mismo número para una aplicación y obligaba a una guarda "una orden vigente por lote" que repetía, peor, lo que el ADR 0021 ya garantiza entre contratos.

**Derivar los lotes de la orden en cada lectura** (leer `com_contrato_lotes` en vivo en vez de copiarlos a `ope_orden_lotes`) — descartada: el conjunto de lotes de una orden ya emitida no debería cambiar porque cambie el contrato, y el catálogo de la app (`GET /api/sync/catalogo`) y el reparto por equipo de la Orden de Trabajo (HU-70) ya leen `ope_orden_lotes`. Conservar la tabla como copia automática evita cambiar su forma. El costo asumido está en las Consecuencias: un lote agregado después no entra en la aplicación abierta.

**Que la app de campo pueda cancelar** (o pausar o cerrar) una orden — descartada: la decisión es del operador junto con el dueño, leyendo lo que reporta el equipo, y depende de cosas que el campo no ve (el pago, p. ej.). La app registra lo que pasa —incidencias y pausas de sesión— y el panel decide.

**Que cancelar consuma siempre el número** — descartada: un dron caído (fuerza mayor) haría gastar una de las `aplicaciones_previstas` por algo ajeno a las partes, y la aplicación no llegó a hacerse. **Que cancelar nunca lo consuma** — descartada: si la aplicación se cancela por causa del cliente, no se rehace como si no hubiera pasado; la siguiente lleva el número que sigue. La causa (`cliente` | `fuerza_mayor`) es justamente lo que distingue los dos casos.

**Cierre automático por hectáreas validadas** — descartada: el dueño decidió que nada cierre la orden solo; el encargado cierra con el informe del equipo a la vista. Además acoplaría el cierre al ritmo de validación de las sesiones, que sigue su propia máquina, y a una tolerancia de solape todavía por definir (especificación §16).

**En vez de bloquear, cancelar en cascada las aplicaciones abiertas al cancelar el contrato** — descartada: cancelar una orden exige causa y motivo escritos, y una cascada decidiría por el operador el destino de la aplicación en curso sin ninguno de los dos. Con el bloqueo, primero se resuelve esa aplicación (se cierra o se cancela con su causa y su motivo) y recién después se cancela o finaliza el contrato.

**No tener estado `pausada`** — descartada: sin él, una detención (clima, entrega tardía de la calda, acceso, falta de insumos, pago…) solo podría representarse dejando la orden `vigente` sin marca, o cancelándola, lo que cierra la aplicación y, si la causa es del cliente, consume su número. `pausada` mantiene la aplicación abierta —sigue habiendo una sola por contrato, y no se puede emitir otra— hasta que se reanude o se cancele.

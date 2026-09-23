# ADR 0023 — El pago al personal de campo es del trabajo, no de la persona

**Fecha:** 2026-09-22 · **Estado:** aceptado · **Decide:** el dueño (Carlos), con el desarrollador.

## Contexto

Desde HU-16 el devengo de una sesión validada se calculaba como `hectáreas × per_personas.tarifa_ha`: la
tarifa vivía en la ficha de la persona. El 21/9/2026 el dueño lo señaló como error de modelo y el 22/9
lo cerró: **lo que cobra un piloto o un ayudante depende del trabajo, no de quién es**. Esta campaña se
paga por día; en lotes con obstáculos, o cuando hay que hacer voleo o fumigar con mapeo, la cuadrilla
negocia otra cosa, a veces por hectárea. Además hacía falta una configuración de pago base en Finanzas
("pago por defecto") para no negociar desde cero cada vez.

## Decisión

1. **Catálogo `fin_tarifas` (Financiero → Tarifas de pago).** Cada tarifa: nombre del tipo de trabajo
   (Fumigación manual, Fumigación con mapeo, Voleo…), modalidad (`por_dia` / `por_ha`), monto para el
   piloto y monto para el ayudante. A lo sumo una **predeterminada** (índice único parcial): es la que
   propone cada Orden de Trabajo. Editarla no toca nada ya armado.
2. **Condición de pago por equipo en la Orden de Trabajo** (`ope_orden_trabajo_equipos`, una fila por
   orden de trabajo y equipo). Se elige una tarifa y se **copian** modalidad y montos; si la cuadrilla no
   acepta, se marca «negociar» y se cargan modalidad, montos y motivo para ese trabajo puntual. Nivel
   equipo, no persona: la cuadrilla son dos o tres personas y el monto va por puesto. Los `ope_trabajos`
   del mismo equipo en la misma orden comparten la condición; al pasar un trabajo a otro equipo, ese
   equipo la hereda si no tiene una propia en esa orden.
3. **El devengo copia y congela la condición al validar la sesión** (`fin_devengos_personal.modalidad`,
   `tarifa`, `monto`, `trabajo_id`). Por hectárea: `hectáreas × tarifa`, una fila por sesión y persona.
   Por día (jornal): `monto = tarifa`, **una fila por persona y fecha** (índice único parcial); la segunda
   sesión del día no suma. La fecha es la de la sesión (`inicio`), no la de la validación.
4. **Día mixto — «solo el jornal»** (decisión del dueño): si una persona ese día tiene jornal, lo que
   haya por hectárea de esa fecha queda `absorbido_por_id` → el jornal, en cualquier orden en que lleguen
   las validaciones. La fila absorbida no se borra ni cambia su monto (invariante 6: sigue recalculable);
   solo deja de sumar (`DevengoPersonal::pagables()`), y el panel la muestra como «Cubierto por el jornal».
5. **Sin condición propia** (trabajo anterior a esta reforma, o creado fuera de una Orden de Trabajo) el
   devengo usa la tarifa predeterminada del catálogo; sin ninguna de las dos, `TrabajoSinCondicionDePago`
   revierte la validación entera, igual que antes hacía `PersonaSinTarifaHa`.
6. `per_personas.tarifa_ha` **se retira** (columna, formulario, listado, DTO). Los devengos históricos
   conservan su copia congelada.

## Consecuencias

- Finanzas define `ModalidadPago`, `CondicionPago` y `TarifaPago` en `Contratos/` porque Operaciones los
  guarda y los devuelve con la sesión validada (`DatosSesionValidada::$condicionPago`). Finanzas nunca lee
  `ope_orden_trabajo_equipos`; Operaciones nunca lee `fin_tarifas` salvo por `LecturaTarifasPago`.
  El uso de una tarifa en órdenes se lee por `LecturaUsoDeTarifa` (Operaciones → Finanzas).
- Zona de dinero: `GenerarDevengosSesion`, `CrearOrdenTrabajo::resolverCondicion()` y `ActualizarTrabajo::
  heredarCondicionDePago()` se revisan línea por línea (anotado en `runs/revision-pendiente.txt`).
- El INSERT del devengo va en una transacción anidada (SAVEPOINT): un duplicado atrapado no deja abortada
  la transacción de la validación en Postgres.
- La pantalla vieja de reparto (`RepartoCuadrillasController`) no pide condición: parte de la predeterminada.
- Queda abierto el **modelo del Trabajo** (equipo×lote vs. equipo + hectáreas con lotes informados desde el
  campo); ver `docs/negocio/observaciones_pago_por_trabajo_2026-09-22.md` §3.

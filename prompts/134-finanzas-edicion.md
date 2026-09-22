<!-- ciclo: critica=si turno-noche=1 rama=feature/finanzas-edicion etapas=3 descongela=tests -->

# Tarea 134 — casi nada de Finanzas se puede corregir después de creado

Queja del dueño (22/9/2026), recorriendo el módulo: "se crea y ahí queda en
el limbo" — Gastos, Combustible, Rendición, Planilla, Factura, ningún objeto
de Finanzas tiene una opción de editar. Confirmado en `routes/web.php`: hoy
`GastosController`/`CombustibleController` tienen `store`+`destroy` pero no
`update`; `PlanillasController` tiene `store`(genera)+`aprobar`, sin edición
ni baja; `RendicionesController` tiene transiciones (`asociarGasto`,
`presentar`, `aprobar`) pero no edita su propia cabecera; `FacturasController`
solo tiene `store` — y ahí el propio comentario de la ruta ya dice por qué:
"Sin edición ni baja: una factura emitida es un snapshot inmutable".

**Esta tarea es CRÍTICA** (toca el límite explícito de `CLAUDE.md` — "el
servicio de estados, los listeners que generan dinero (devengos, planilla)"
— y "cobranza" no existe todavía como pantalla propia, así que antes de tocar
nada hay que averiguar qué es lo que el dueño está mirando cuando la nombra).
**No es una tarea de "agregar un botón Editar en seis pantallas"**: cada
objeto tiene un riesgo de dinero distinto y la solución NO es la misma para
todos. No inventes la regla de negocio que falte — si no está escrita en un
ADR o en `especificacion_funcional_tecnica.md`, dejá esa entidad puntual
`BLOQUEADA` con la pregunta exacta en `runs/134.md` y seguí con las demás
(mismo criterio que la tarea 124 con "cierre manual del Trabajo").

Cargá los skills `dominio-backend`, `arquitectura`, `panel-design-ui` y
`redaccion-neutra`. Leé `PoliticaEdicionOrden` y
`PoliticaEdicionOrdenTrabajo` (tarea 127) enteras: son el patrón a replicar
donde aplique, no lo reinventes desde cero.

## Etapa 1 — clasificar antes de tocar código

Para cada entidad, contestá con código/ADR/migraciones a la vista (no de
memoria) y dejalo escrito en `runs/134.md` ANTES de escribir ningún cambio:

1. **¿Tiene máquina de estados propia?** (`Dominio/MaquinaEstados/*`,
   `Dominio/Estado*`).
2. **¿Alimenta un devengo, una planilla o un monto que ya se le pagó/cobró a
   alguien?** (invariante 2/6: lo ya validado/aprobado no se pisa).
3. **¿Es un documento con valor fiscal/legal?** (Factura ya está resuelto:
   NO se edita — confirmalo, no lo reabras).

Con eso, clasificá cada una en una de tres categorías y actuá distinto:

- **(A) Sin estado, sin dinero ya consumido por otro objeto → edición
  simple.** Candidatas: **Gastos**, **Combustible** — mientras el registro no
  esté ya asociado a una Rendición `presentada`/`aprobada` (comprobalo en el
  modelo antes de asumir que se puede). Patrón: igual que Bases/Cuadrillas
  (`_formulario` compartido create/edit, sin motivo obligatorio). Si ya está
  asociado a una rendición cerrada, "Editar" no aparece — igual que
  `PoliticaEdicionOrden::admiteEdicion`.
- **(B) Con máquina de estados, corrección con motivo, nunca UPDATE sobre lo
  aprobado → política dedicada.** Candidata: **Rendición** — el mismo patrón
  de `PoliticaEdicionOrdenTrabajo` (tarea 127): editable mientras esté
  `borrador`/equivalente pre-`presentada`; tras `presentada`/`aprobada`, no
  se edita (ahí ya activó una transición de dinero). Escribí
  `Dominio/PoliticaEdicionRendicion` con su test en `tests/Unit`.
- **(C) Inmutable por diseño, no le falta un "editar" — le puede faltar un
  "anular"/"corregir con un registro nuevo".** Candidatas: **Planilla**
  (generada desde devengos: si algo está mal, se corrige el devengo de
  origen y se regenera, no se edita la planilla — confirmá esto releyendo
  `MaquinaEstadosPlanilla` y `GenerarDevengosSesion`/lo que arma la
  planilla) y **Factura** (ya confirmado, no se edita). Para estas dos, NO
  agregues código de edición: si encontrás un caso real donde hace falta
  corregir una ya aprobada/emitida, es una decisión de negocio nueva
  (¿nota de crédito? ¿anular y reemitir?) — anotala en `runs/134.md` como
  hallazgo para el dueño, con el caso concreto, y no la implementes a
  ciegas.
- **"Cobranza":** buscá primero si existe como pantalla o si el dueño se
  refiere a algo dentro de Facturas (¿registrar un pago/cobro?). Si no
  existe ninguna pantalla con ese nombre ni ese verbo, anotalo en
  `runs/134.md` como "no encontrado, no hay nada que editar todavía" — no
  inventes el feature completo acá, esa sería una HU nueva.

## Etapa 2 — Gastos y Combustible (categoría A)

- `Aplicacion/ActualizarGasto`/`ActualizarCombustible` (o el nombre que siga
  la convención del módulo), `Request` compartido con el de alta (sacalo del
  actual, no lo dupliques).
- Rutas `GET .../{id}/editar` y `PUT .../{id}`, permiso existente de edición
  del módulo (revisá `gastos.editar`/equivalente en el seeder de permisos;
  si no existe, usá el mismo que ya gatea `destroy` — no crees uno nuevo sin
  falta).
- Vistas `edit.blade.php` (arquetipo Formulario, `_formulario.blade.php`
  compartido con `create` si no lo es ya).
- "Editar" en el listado como row-action (`warning-outline`), solo si la
  política lo permite (guardas explicadas arriba).

## Etapa 3 — Rendición (categoría B)

- `Dominio/PoliticaEdicionRendicion` + test.
- `Aplicacion/ActualizarRendicion`: transacción, guardas por la política,
  bitácora vía el observer de plataforma (invariante 9, no a mano).
- Ruta y vista `edit.blade.php` (arquetipo Formulario), "Editar" en la ficha
  y en el listado, solo si la política lo admite.
- `./bin/verify`, navegador, y anotación en `runs/revision-pendiente.txt`
  (toca dinero — "revisar sobre develop, ya integrado", con qué mirar, mismo
  formato que dejó la tarea 127).

## Qué NO hacer

- No le agregues edición a Planilla ni a Factura sin que el dueño lo pida
  explícitamente con el caso concreto (ver categoría C).
- No inventes un permiso nuevo si ya existe uno que sirve.
- No toques `MaquinaEstadosPlanilla`, `MaquinaEstadosRendicion` en su forma
  de transicionar (`aprobar`, `presentar`) — esto es editar la CABECERA
  antes de que la transición exista, no cambiar las transiciones.
- No uses `UPDATE` sobre un registro que ya generó dinero a otro (devengo,
  pago), aunque el formulario lo mande — la política lo tiene que rechazar
  primero.
- Sin `git stash`, sin `reset --hard`.

## Criterio de aceptación

- `./bin/verify` = 0, con `PoliticaEdicionRendicionTest` cubriendo caso
  positivo y negativo, y `ArquitecturaModulosTest` en verde.
- `runs/134.md` documenta la clasificación de las seis (Gastos, Combustible,
  Rendición, Planilla, Factura, Cobranza) con su porqué, código a la vista.
- Playwright (`runs/134-navegador.cjs`): Gasto y Combustible editables desde
  el listado (mientras no estén en una rendición cerrada); Rendición
  editable en borrador y NO editable tras `presentada`; Planilla y Factura
  siguen sin ningún botón de editar. Capturas en `runs/134-capturas/`
  (ruta absoluta), claro y oscuro.

## Cierre obligatorio de cada etapa

`runs/134.estado` (`PARCIAL`/`OK`/`BLOQUEADA` — puede ser `OK` con
sub-hallazgos `BLOQUEADA` anotados adentro, ver arriba), `runs/134.md`, y al
`OK` `runs/134.pr.md`. Commits agrupados por función, en español, imperativo,
sin `Co-Authored-By`.

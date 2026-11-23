# ADR 0035 — Retiro de la operación interna: finanzas, inventario y mantenimiento de flota

**Estado:** aceptada · **Fecha:** 2026-11-23 · **Decide:** el dueño (autonomía delegada)

## Contexto

Agrocom es un marketplace de fumigación con drones (`docs/especificacion/marketplace.md`). El ADR 0029 (punto 4) ya fija que no hay módulo de mantenimiento, inventario, planilla ni contabilidad interna: de todo eso solo se conserva el contador de **ciclos de batería** y la **ficha técnica** del dron. La plataforma no paga al personal, no lleva el costo de la flota ni el stock de repuestos; el pago entre cliente y proveedor y la comisión se resuelven según el ADR 0034.

## Decisión

1. **Se eliminan los módulos `Finanzas` e `Inventario`** con sus modelos, casos de uso, contratos, controladores, vistas, rutas, permisos, entradas de menú, lang, seeders, hojas de estilo y pruebas:
   - Finanzas: planilla, devengos, anticipos, rendiciones, gastos, combustibles, rubros y subrubros, tarifas de pago.
   - Inventario: repuestos, stock y movimientos.
2. **`Mantenimiento` queda reducido a lo que el marketplace usa**: el catálogo de baterías (`man_baterias`, con sus ciclos acumulados y la alerta de retiro) y la ficha técnica del dron (`man_drones`). Se eliminan vehículos, generadores, planes de mantenimiento y órdenes de mantenimiento. La ficha y los ciclos se asocian al dron por el identificador, como hasta ahora; la absorción en `flo_drones` (`ciclos_bateria`, ADR 0029) es parte del módulo de flota del marketplace y no de esta limpieza.
3. **Las cuadrillas llevan solo dron y baterías.** `RecursoTipoEquipo` pierde `vehiculo` y `generador`; el alta de cuadrilla, el resumen de la base y el tablero del jefe de campo dejan de ofrecerlos.
4. **Las órdenes de trabajo no llevan condición de pago.** Se elimina `ope_orden_trabajo_equipos` (guardaba tarifa, modalidad y montos por equipo, ADR 0023), la resolución de la condición de pago, el contrato `LecturaSesionValidada` y la sección de pago de los formularios y fichas. La sesión sigue anunciando `SesionValidada`; ya no hay un oyente que genere dinero.
5. **Las estadías en hacienda no registran vehículo.** Se elimina `ope_estadias_hacienda.vehiculo_id`. La sincronización sigue aceptando una clave `vehiculo_id` en `estadia_entrada` y la ignora, de modo que una app de campo anterior no ve rechazados sus lotes. El contrato OpenAPI deja de documentar el campo.
6. **El tablero y el menú pierden lo financiero y lo de stock**: secciones «Mi liquidación» y «Stock bajo mínimo», pestaña «Mis devengos», badges de stock y devengado, módulo de menú «Mantenimiento» y las siete entradas de «Financiero» que no son facturas. «Facturas y cobranzas» se queda en «Financiero» (módulo `Comercial`).
7. **El invariante 3 de `CLAUDE.md`** ya no habla del devengo: pasa a describir la validación idempotente de la sesión y el evento `SesionValidada`.

## Migración y respaldo

La migración `2026_11_23_100000_retira_operacion_interna` es destructiva a propósito, pero no borra datos:

- **Mueve** las 18 tablas retiradas al esquema `respaldo_operacion_interna` (`ALTER TABLE … SET SCHEMA`). Salen de `public`, que es lo que ve la aplicación; conservan filas, índices, restricciones y secuencias.
- **Quita** `ope_estadias_hacienda.vehiculo_id` y deja los pares `(id, vehiculo_id)` en `respaldo_operacion_interna.ope_estadias_vehiculo`.
- **Da de baja lógica** (invariante 8) las filas de `per_equipo_recursos` de tipo `vehiculo` y `generador`, los permisos retirados, sus asignaciones a roles y las entradas de menú; los ids o filas afectados quedan en tablas `*_retirados` del mismo esquema.
- **No toca** `plt_bitacoras`: el historial de auditoría de lo retirado sigue legible, por eso `lang/es/seguridad.php` conserva los nombres de esas entidades.
- `down()` devuelve las tablas a `public`, restaura `vehiculo_id`, las bajas lógicas y el catálogo de seguridad, y elimina el esquema de respaldo.
- Solo corre en PostgreSQL; las pruebas de `tests/Unit` no ejecutan esta migración.

### Retiro definitivo del respaldo

Mientras exista el esquema, las claves foráneas de las tablas archivadas hacia `per_personas`, `per_bases`, `per_equipos_trabajo` y `cpn_campanias` impiden borrar físicamente esas filas. Cuando el dueño confirme que no necesita los datos:

1. Volcar el respaldo y guardar el archivo fuera del servidor:
   `pg_dump --schema=respaldo_operacion_interna --format=custom --file=respaldo_operacion_interna.dump <base>`
2. Comprobar el volcado (`pg_restore --list respaldo_operacion_interna.dump`).
3. Eliminar el esquema en una migración propia: `DROP SCHEMA respaldo_operacion_interna CASCADE`.

Antes de aplicar la migración en un entorno con datos reales conviene hacer el mismo volcado de las 18 tablas, aunque la migración sea reversible.

## Alternativas descartadas

- **`DROP TABLE` directo**: pierde los datos de planilla y gastos sin vuelta atrás; el dueño puede necesitarlos para cierres contables ya presentados.
- **Dejar las tablas en `public` sin código**: las claves foráneas y los nombres siguen ensuciando el esquema que ve el equipo y los agentes.
- **Renombrar `man_baterias` y `man_drones` a `flo_*` en esta tarea**: mezcla una limpieza con el diseño del módulo de flota; se resuelve junto con `flo_drones`.

## Consecuencias

- El esquema de trabajo queda sin `fin_*`, `inv_*` ni las cuatro tablas de mantenimiento de flota; la consolidación del ADR 0024 sigue valiendo para lo que queda (los archivos `create_*` de lo retirado se conservan como historia de la base y la migración nueva los deja sin efecto).
- Las pruebas de `tests/Unit` que ejercitaban la condición de pago, la política de edición de gastos y rendiciones y los pasos de la orden de mantenimiento se eliminan con su código.
- Queda pendiente, fuera de esta decisión, el campo `ope_recargas.litros_combustible_generador`, que la app de campo sigue enviando como dato informativo.

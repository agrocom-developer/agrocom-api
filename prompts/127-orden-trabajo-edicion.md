<!-- ciclo: critica=no turno-noche=1 rama=feature/orden-trabajo-edicion etapas=3 descongela=tests -->

# Tarea 127 — la Orden de Trabajo se puede editar

Pedido del dueño (22/9/2026): «que la orden de trabajo también tenga la opción
de editar». Hoy `OrdenesTrabajoController` solo tiene `index`, `create`, `store`
y `show`; lo único editable es cada `Trabajo` por separado
(`/panel/trabajos/detalle/{trabajo}/editar`, `ActualizarTrabajo`).

Cargá los skills `verificacion`, `dominio-backend`, `panel-design-ui`,
`redaccion-neutra` y `modelo-datos`. Leé `CrearOrdenTrabajo`,
`CrearOrdenTrabajoRequest`, `ordenes-trabajo/create.blade.php` con sus parciales
`_calda` y `_equipo-bloque`, la ficha `ordenes-trabajo/show.blade.php`,
`Dominio/PoliticaEdicionOrden` (el patrón de «una orden publicada se corrige con
motivo», HU-100 punto 10) y el ADR 0023 (condición de pago por equipo).

## Alcance — qué se edita y qué no

La OT es una **tanda**: cabecera con las indicaciones compartidas y, por equipo,
una condición de pago; cada equipo×lote es un `Trabajo` con su propio estado.
La edición cubre:

1. **Indicaciones compartidas** de la cabecera: calda (`calda_productos`,
   `ph_agua`, `ph_calda`, `litros_ha`/`kilos_ha` según el tipo de insumo de la
   orden), límites climáticos y parámetros de vuelo. Mismas reglas de validación
   que el alta (sacalas del Request de alta a un lugar compartido; no las copies).
2. **Condición de pago por equipo** (`ope_orden_trabajo_equipos`): tarifa o
   valores negociados con motivo, con la misma resolución que
   `CrearOrdenTrabajo::resolverCondicion()` (extraela a algo reutilizable en
   `Aplicacion/`). Solo para un equipo cuyos trabajos estén **todos `abierto`**:
   un trabajo cerrado ya tiene sesiones que se van a devengar con la condición
   vigente al validar, y un validado ya generó dinero (invariante 2). Para los
   demás equipos la condición se muestra de solo lectura con el porqué.

**No** se edita acá el reparto (equipos, lotes, hectáreas, turno): eso sigue
siendo por trabajo, desde la ficha de la OT («Editar» de cada fila), y agregar o
quitar equipos a una tanda es una decisión abierta del dueño. Decilo en la
pantalla con un aviso corto que enlace a la ficha.

## Reglas

- `Dominio/PoliticaEdicionOrdenTrabajo` (pura, con test en `tests/Unit`):
  `admiteEdicion(estados de sus trabajos)` = ningún trabajo `validado`;
  `exigeMotivo(...)` = algún trabajo `cerrado`; `admiteCondicion(estados del
  equipo)` = todos `abierto`. Una OT con todos sus trabajos validados no se
  edita: «Editar» no aparece y `edit()`/`update()` redirigen a la ficha con el
  aviso.
- Migración **add** (ADR 0024, nunca se reconsolida acá):
  `ope_ordenes_trabajo.motivo_correccion` (text, null) y `corregida_at`
  (timestamptz, null), como tiene la orden de aplicación.
- `Aplicacion/ActualizarOrdenTrabajo`: transacción, guardas por la política,
  `TarifaNoDisponible` si la tarifa desapareció, `Brick\Math` para montos
  (invariante 6). La bitácora la escribe el observer de plataforma (invariante
  9): no la llames a mano; comprobá en `plt_bitacoras` que quedó el antes y el
  después.
- Ruta `GET /panel/trabajos/{ordenTrabajo}/editar` (`panel.trabajos.edit`) y
  `PUT /panel/trabajos/{ordenTrabajo}` (`panel.trabajos.update`), registradas
  **antes** del grupo `detalle/` como las demás, permiso `operaciones.trabajo.editar`
  (el mismo que edita un trabajo; sin permiso nuevo).
- Vista `ordenes-trabajo/edit.blade.php`: formulario del arquetipo (§6.3 de la
  guía): `form-layout`, secciones Orden (solo lectura), Calda, Clima, Vuelo,
  Equipos (condición de pago por equipo), `form-actions-bar`, y el aside con el
  resumen relacionado (trabajos por estado, orden, cuadrillas) — como la edición
  de la orden de aplicación. Las secciones de indicaciones se comparten con el
  alta por un parcial (`_indicaciones.blade.php`), **sin** renombrar `create`
  ni crear `_formulario`: `PanelHomogeneoTest` nombra
  `Operaciones/ordenes-trabajo/create.blade.php` (línea ~697); leé el test antes
  de mover nada. Tras guardar, vuelve a `edit()` con el aviso (§6.3.2).
- «Editar» aparece en la ficha de la OT (`page-header` → `actions`,
  `warning-outline`, junto a «Volver») y en el listado como segunda `row-action`
  después de «Ver», solo si la política lo admite.
- Copy en `lang/es/operaciones.php`, tuteo neutro.

## Cómo repartir las etapas

- Etapa 1: política + test, migración, `ActualizarOrdenTrabajo`, Request
  compartido, rutas, `update()`; verificado con un script en `storage/app`
  dentro de una transacción revertida (memoria «Verificar lógica en Postgres
  con rollback»).
- Etapa 2: `edit.blade.php`, parcial compartido, botones en ficha y listado.
- Etapa 3: navegador, `./bin/verify`, anotación en `runs/revision-pendiente.txt`
  (toca la condición de pago que alimenta los devengos: zona de dinero —
  «revisar sobre develop, ya integrado», con qué mirar).

## Datos para probar (compose)

OT `#2` (orden #2 vigente; EQ3 con un trabajo abierto y otro cerrado → condición
de solo lectura, exige motivo; EQ4 con trabajos abierto y cerrado), OT `#3`
(un trabajo cerrado). OT `#1` tiene trabajos cerrados con acta: no validados,
así que se edita con motivo. Si necesitás una OT con todos sus trabajos
abiertos, creala desde `/panel/trabajos/crear` sobre la orden #2 con lo que
quede sin repartir (queda como dato demo; no borres nada). Cuenta
`marcela.antelo` / `0000`.

## Qué NO hacer

- No tocar `MaquinaEstadosTrabajo`, `GenerarDevengosSesion` ni el sync.
- No permitir cambiar `orden_id` ni `nro_aplicacion`.
- No replicar la calda en `Mezclas`: las casillas viven en la cabecera.
- No `UPDATE` sobre un trabajo validado ni sobre la condición de un equipo con
  trabajos cerrados/validados, aunque el formulario lo mande.
- Sin `git stash`, sin `reset --hard`.

## Criterio de aceptación

- `./bin/verify` = 0, con `PoliticaEdicionOrdenTrabajoTest` cubriendo las tres
  reglas (cada una con su caso positivo y negativo) y `ArquitecturaModulosTest`
  en verde.
- Script de rollback en `storage/app/verifica-edicion-ot.php`: editar la OT #2
  cambia la cabecera y la condición de EQ4 solo si todos sus trabajos están
  abiertos; sin motivo, rebota; con un trabajo validado, `ActualizarOrdenTrabajo`
  lanza y no escribe nada; la bitácora registra el antes/después.
- Playwright (`runs/127-navegador.cjs`): la ficha de la OT #2 muestra «Editar»,
  el formulario carga con los valores actuales, guardar con motivo vuelve a la
  edición con el aviso, el listado muestra «Editar» tras «Ver»; capturas en
  `runs/127-capturas/` (absoluta), claro y oscuro, sin desborde.

## Cierre obligatorio de cada etapa

`runs/127.estado`, `runs/127.md`, y al `OK` `runs/127.pr.md`. Commits por
función, en español, imperativo, sin `Co-Authored-By`.

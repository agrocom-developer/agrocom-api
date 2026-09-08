<!-- ciclo: critica=si turno-noche=1 descongela=tests rama=feature/gastos-equipo etapas=3 -->

# Tarea 73 — HU-50: el gasto y el combustible se imputan al equipo de trabajo

## Por qué esta tarea

El dueño lo planteó como un problema concreto de imputación, no como una
mejora: *"el tema de los gastos que realiza cada equipo, como ser gastos en
combustible — como no sabemos en qué trabajo se cargan los gastos, ya el equipo
está asociado a equipos de inventario como ser vehículos, así sabemos qué
vehículo solicitó nuevo combustible, o en los generadores"*.

Lo que hay hoy no alcanza:

- `fin_gastos` imputa a `trabajo_id` o `base_id`, ambos nullable, "un gasto con
  las dos en NULL es general". Pero el gasto de campo típico **no pertenece a
  ningún trabajo** (no se sabe a qué lote cargarle la carga de combustible) y
  la base no distingue: varias cuadrillas comparten base.
- `fin_combustibles.destino` es un `string` con
  `CHECK (destino IN ('generador','vehiculo'))` y **no dice cuál**. Su propio
  docblock dejó escrito el porqué: *"no existe módulo Vehículo todavía — cuando
  exista, se agrega `vehiculo_id` por ALTER TABLE"*. Ya existe `man_vehiculos`,
  y con la tarea 72 existe también `man_generadores` y el equipamiento asignado
  al equipo.

**Es crítica**: toca dinero. Revisión posterior a la integración, anotada en
`runs/revision-pendiente.txt` — el PR no se retiene.

Depende de las tareas 69 (`campania_id` en `fin_gastos`) y 72 (equipos y su
equipamiento).

## Lo que ya existe

- `database/migrations/2026_09_03_100003_create_fin_gastos_table.php` — leé su
  docblock entero: explica por qué `monto` lo persiste el caso de uso con
  `Brick\Math\BigDecimal` (`cantidad × precio_unitario`) y no es columna
  calculada. Eso no cambia.
- `database/migrations/2026_09_03_100006_create_fin_combustibles_table.php` —
  `litros` y `monto` son ambos datos de entrada, no derivados. Eso tampoco
  cambia.
- `Finanzas/Aplicacion/{CrearGasto,CrearCombustible}.php` y sus controladores.
- `Personal/Contratos/LecturaEquipoTrabajo` (tarea 72) — de ahí sale qué
  recursos tiene asignado cada equipo.
- `bcmath` **no está instalado** ni en el Dockerfile ni en CI: toda cuenta de
  dinero va con `Brick\Math\BigDecimal`, que ya es dependencia de Laravel.

## Qué hacer

1. **`equipo_trabajo_id`** nullable en `fin_gastos`, por `ALTER TABLE`, FK real
   a `per_equipos_trabajo` + entero plano (ADR 0003 regla 3, mismo criterio que
   `base_id`). Nullable porque el gasto general sigue existiendo — pero el
   formulario lo ofrece primero, antes que base y trabajo.
2. **`fin_combustibles` pasa a imputar al equipo y al recurso concreto**:
   - `equipo_trabajo_id` (FK plana, **obligatoria** — el combustible siempre lo
     consume una cuadrilla) y `campania_id` (FK plana, **nullable**).
   - `campania_id` acá no es el período: es **en qué campaña se consumió**
     (ADR 0015 punto 6, 8/9/2026). Es **atribución de costo, no de cobro**: al
     cliente no se le factura combustible, paga por hectárea aplicada al precio
     de su contrato — *"de qué gana el cliente que le digamos que hoy pagamos la
     gasolina más cara que ayer (…) el cliente va a pagar solo por el servicio
     por hectárea"*. Sirve para comparar costo contra lo facturado.
     **La carga es la unidad y no se prorratea**: se atribuye entera a la
     campaña donde se cargó, y la sobra que se consume después no se recalcula.
     Vacío = consumo interno que no pertenece a ninguna campaña.
   - **No agregues `campo_id` ni `lote_id`** a estas tablas. El detalle por
     propiedad, campo y lote que pide el dueño sale de `trabajo_id` (lleva a
     lote → campo → cliente) y de las estadías del equipo (tarea 74).
   - `recurso_tipo` (`CHECK IN ('dron','vehiculo','generador')`) y `recurso_id`,
     ambos obligatorios, reemplazando a `destino`. Migrá el dato existente:
     `destino = 'generador'` → `recurso_tipo = 'generador'`,
     `destino = 'vehiculo'` → `recurso_tipo = 'vehiculo'`; si no hay a qué
     `recurso_id` apuntar, dejá la fila con el recurso que corresponda de la
     base y documentá la elección en el docblock. Recién después borrá
     `destino`.
   - `base_id` se conserva (hoy es obligatoria y el CA de HU-35 es literal
     "carga por base y fecha").
3. **El selector de recurso sale del equipo, no del catálogo entero.** Al
   elegir equipo, el formulario ofrece **solo** los recursos que ese equipo
   tenía asignados a la fecha de la carga (`LecturaEquipoTrabajo`). Un recurso
   que no le pertenecía ese día se rechaza en el caso de uso, no solo en la
   vista — es la regla que hace confiable la imputación.
4. **Guarda de campaña cerrada** en `CrearGasto` y `CrearCombustible`, igual
   que la que dejó la tarea 69 para el gasto.
5. **Agregado por equipo y por período**: en el listado de gastos, filtro por
   equipo, por rango de fechas y por campaña (opcional, dentro de un cliente),
   y un total por equipo. El corte del costo interno de Agrocom es la **fecha**,
   no la campaña — la campaña dice para qué cliente fue (ADR 0015 punto 6).
   Nada de esto se muestra en el portal del cliente. Sumas con
   `BigDecimal` en PHP, **nunca con `SUM()` de SQL** — en SQLite (motor de los
   tests) la agregación pasa por REAL/float y violaría la invariante 6. Ese es
   el mismo criterio que ya documenta `ObtenerAvanceComercial`.
6. **Traducciones** en `lang/es/finanzas.php`.

## Qué NO hacer

- No conviertas `monto` en columna calculada, ni en gastos ni en combustible.
- No borres `trabajo_id` ni `base_id` de `fin_gastos`: el gasto imputable a un
  trabajo concreto sigue siendo válido, solo deja de ser el camino principal.
- No uses `SUM()`/`AVG()` de SQL para dinero ni hectáreas.
- No toques devengos, anticipos ni planilla: es dinero de otro circuito y no fue
  parte del pedido.
- No agregues `equipo_trabajo_id` a `fin_rendiciones` sin dato que lo sostenga.

## Cómo repartir las etapas

- **Etapa 1**: `ALTER TABLE` de gastos, migración de `fin_combustibles` con su
  migración de datos, modelos, tests de esquema.
- **Etapa 2**: casos de uso con la validación "el recurso pertenece al equipo a
  esa fecha", guardas de campaña cerrada, tests unitarios y Feature.
- **Etapa 3**: formularios, filtros y totales por equipo, traducciones,
  snapshots, `bin/verify`.

## Criterio de aceptación

- `./bin/verify` = 0 (en este Mac, `./bin/verify --sin-assets`).
- Test: cargar combustible con un recurso que **no** estaba asignado a ese
  equipo en esa fecha se rechaza, con mensaje traducido.
- Test: el mismo recurso, con fecha dentro de su vigencia en el equipo, se
  acepta.
- Test: el total de gastos por equipo cuadra exacto contra la suma de sus
  `monto` (comparación de strings decimales, no de floats).
- Test de migración: las filas preexistentes de `fin_combustibles` conservan su
  destino como `recurso_tipo` y ninguna queda sin `equipo_trabajo_id`.
- Test: gasto o combustible contra campaña `cerrada` se rechaza.
- `grep -rn "destino" app/Dominios/Finanzas/` no devuelve la columna vieja.

## Puede tocar

`app/Dominios/Finanzas/**`, `database/migrations/**`, `database/seeders/Demo/**`,
`lang/es/finanzas.php`, `tests/**`, `tests/Visual/**` (snapshots de gastos y
combustible).

Fuera de alcance: `per_*` (solo se lee por contrato), devengos, anticipos,
planilla, `com_*`, `ope_*`.

## Cierre obligatorio de cada etapa

`runs/73.estado`, `runs/73.md`, y al `OK` `runs/73.pr.md`. Anotá la tarea en
`runs/revision-pendiente.txt` (es crítica). Commits agrupados por función, en
español, imperativo, sin `Co-Authored-By`.

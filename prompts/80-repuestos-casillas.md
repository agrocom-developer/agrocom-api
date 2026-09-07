<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/repuestos-casillas etapas=2 -->

# Tarea 80 — HU-57: elegir repuestos por casillas en la orden de mantenimiento

## Por qué esta tarea

Pedido del dueño el 7/9/2026, mirando `/panel/ordenes-mantenimiento/5/editar`:
*"lo ideal es tener otro modelo de selección por casillas y no tanto de select
en los repuestos"*.

Tiene razón y el marcado lo explica. Hoy consumir repuestos es: apretar "Agregar
repuesto" → aparece una fila (`_repuesto-linea.blade.php`) con **dos selects**
(repuesto y base) más una cantidad → repetir por cada repuesto. Para una orden
con seis repuestos son doce desplegables, cada uno recorriendo el catálogo
entero, y sin ver de un vistazo qué se lleva elegido. Es el mismo problema que
la tarea 76 resuelve en general, aplicado a la pantalla donde más duele.

Depende de la tarea 76: el `checkbox-group` con búsqueda ya está pensado para
este caso, así que **no construyas uno nuevo acá**.

## Lo que ya existe

- `app/Dominios/Mantenimiento/Infraestructura/Http/Views/pages/ordenes/_repuesto-linea.blade.php`
  — la fila repetible actual, con `repuestos[N][repuesto_id]`,
  `repuestos[N][base_id]` y `repuestos[N][cantidad]`.
- El caso de uso de cierre de orden, que descuenta stock y genera el gasto
  asociado **en una transacción** y no cierra sin stock disponible (HU-37).
  **Esa lógica no se toca**: cambia cómo se eligen los repuestos, no qué pasa
  al cerrarlos.
- `inv_stock` por repuesto y base — de ahí sale la disponibilidad.
- El átomo `checkbox-group` con búsqueda de la tarea 76.

## Qué hacer

1. **Selector por casillas**: una lista de repuestos con casilla, buscador por
   código y descripción, y filtro por base. Al marcar uno aparece su campo de
   cantidad al lado, con la disponibilidad de esa base a la vista. Desmarcar lo
   quita.
2. **Resumen de lo elegido** siempre visible (cuántos repuestos, y la lista con
   su cantidad), para no perder de vista qué lleva la orden mientras se busca en
   el catálogo.
3. **La base se elige una vez para la orden**, no una por fila: en la práctica
   los repuestos de una orden salen de la misma base, y repetir el select de
   base en cada línea es la mitad del ruido. Dejá la posibilidad de cambiar la
   base de una línea puntual, pero que no sea el camino normal.
4. **Aviso de stock insuficiente en el momento de marcar**, no recién al
   guardar: si la cantidad supera lo disponible en esa base, se ve ahí mismo.
   La validación dura sigue estando del lado del servidor.
5. **El nombre de los campos del formulario no cambia** (`repuestos[N][...]`):
   el request y el caso de uso siguen recibiendo lo mismo. Es un cambio de
   interfaz, no de contrato.

## Qué NO hacer

- **No construyas un componente de casillas propio de esta pantalla.** Usá el
  átomo de la tarea 76; si le falta algo, agregáselo ahí y que lo herede todo el
  panel.
- No toques la lógica de cierre de orden, el descuento de stock ni la generación
  del gasto asociado.
- No cambies el esquema de `man_ordenes_mantenimiento` ni de `inv_movimientos`.
- No elimines la validación del servidor por tener aviso en el cliente.

## Cómo repartir las etapas

- **Etapa 1**: el selector por casillas con búsqueda, cantidades,
  disponibilidad y resumen; tests Feature de que el formulario manda lo mismo
  que antes.
- **Etapa 2**: aviso de stock insuficiente, traducciones, snapshots,
  `bin/verify`.

## Criterio de aceptación

- `./bin/verify` = 0 (en este Mac, `./bin/verify --sin-assets`).
- Test: una orden guardada con tres repuestos desde la interfaz nueva produce
  **exactamente** el mismo payload y el mismo resultado que la de antes.
- Test: cerrar una orden sigue descontando stock y generando el gasto en la
  misma transacción (regresión de HU-37).
- Test: cantidad mayor al stock disponible se rechaza en el servidor aunque el
  cliente la haya dejado pasar.
- `grep -rn "<select" app/Dominios/Mantenimiento/Infraestructura/Http/Views/`
  no devuelve nada.

## Puede tocar

`app/Dominios/Mantenimiento/**` (vistas y controlador de órdenes),
`resources/views/components/**` (solo si hay que extender el átomo de casillas),
`resources/css/**`, `resources/js/**`, `lang/es/mantenimiento.php`, `tests/**`,
`tests/Visual/**`.

Fuera de alcance: `inv_*` y su lógica, el cierre de orden, `man_planes_mantenimiento`.

## Cierre obligatorio de cada etapa

`runs/80.estado`, `runs/80.md`, y al `OK` `runs/80.pr.md`. Commits agrupados por
función, en español, imperativo, sin `Co-Authored-By`.

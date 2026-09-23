<!-- ciclo: critica=no turno-noche=1 rama=feature/stock-movimientos-listado etapas=2 -->

# Tarea 133 — `/panel/stock` no deja ver los movimientos, solo el saldo

Hallazgo del dueño (22/9/2026): en `/panel/stock` "no se puede ni ver qué es
lo que se tiene" — hay un botón "Registrar movimiento" pero ninguna pantalla
para ver los movimientos ya registrados, solo el saldo agregado por
(repuesto, base).

**Ojo con lo que el dueño NO pidió, y que el propio código ya explica por
qué no existe:** editar o eliminar un movimiento. El docblock de
`stock/index.blade.php` (línea ~10) es explícito: `inv_movimientos` es un
asiento contable — "ni se edita ni se borra" — y `MovimientoStock` (ver su
docblock) es la mutación de negocio auditable en sí misma. Esto es correcto
y NO se toca (mismo criterio que un asiento de auditoría, invariante 2/6 de
`CLAUDE.md`). Lo que falta es la mitad de LECTURA: un listado de los
movimientos, de solo lectura, con su detalle — el dueño lo pidió como "ver
qué es lo que hay", no como "editar".

Cargá los skills `dominio-backend`, `panel-design-ui` y `redaccion-neutra`.
Leé `stock/index.blade.php`, `StockController`, `ListarStock` y
`MovimientoStock` (Eloquent) antes de escribir nada.

## Qué hacer

### Etapa 1 — lectura

- `Aplicacion/ListarMovimientosStock` (mismo módulo `Inventario`, mismo
  criterio que `ListarStock`): movimientos paginados, más recientes primero,
  con `repuesto` y `base`/`base_destino` precargados (sin N+1). Filtros por
  repuesto y por base (mismo patrón que `ListarStock`); sin filtro de texto
  libre si no hay una columna de texto natural para buscar (revisalo antes
  de copiar el `q` de otras pantallas por costumbre).
- Ruta `GET /panel/stock/movimientos` (`panel.stock.movimientos.index`),
  registrada **antes** de `/panel/stock/movimientos/crear` (mismo criterio
  que las demás rutas con prefijo compartido, ver `ordenes-trabajo`), mismo
  permiso `inventario.movimiento.ver` que el listado de saldo.
- Acceso: un link "Ver movimientos" en la cabecera de `stock/index.blade.php`
  (junto a "Registrar movimiento"), y opcionalmente una fila-acción "Ver" por
  repuesto/base que llega ya filtrada (mismo patrón que
  `ag-ordenes__crear-trabajo` de Órdenes, pero como link de solo navegación).

### Etapa 2 — vista

- `stock/movimientos/index.blade.php`, arquetipo Listado (§6.2 de la guía):
  `page-header` (con `boton-volver` a `/panel/stock`), KPI opcional si hay
  algo barato que resumir (cantidad de movimientos del período, por
  ejemplo — no inventes una cifra costosa), `filter-panel` con los filtros
  del punto anterior, `index-table` con: fecha, tipo (`compra`/`salida`/
  `ajuste`/`traslado`, badge por tipo — elegí tono del catálogo, no
  literal), repuesto, base (y base destino si es traslado), cantidad
  (+unidad, `FormatoCantidad`, nunca `float`), costo unitario si no es null,
  motivo. **Sin columna de acciones**: es de solo lectura, mismo criterio
  que el saldo — no le pongas una columna vacía ni un row-actions sin
  botones.
- Copy en `lang/es/inventario.php`, tuteo neutro.
- `resources/css/pages/stock.css`: lo que el catálogo no resuelva.

## Qué NO hacer

No agregues edición, eliminación ni anulación de un movimiento — es un
asiento, no se toca (ver arriba). No cambies `RegistrarMovimientoStock` ni
`EscrituraConsumoStock`. No le agregues una máquina de estados a
`inv_movimientos`: no la tiene y no la necesita.

## Criterio de aceptación

- `./bin/verify` = 0.
- `grep -c "row-actions" app/Dominios/Inventario/Infraestructura/Http/Views/pages/stock/movimientos/index.blade.php` = 0.
- Prueba en navegador: desde `/panel/stock`, "Ver movimientos" lista los
  asientos con su fecha, tipo, repuesto, base y cantidad; filtrar por
  repuesto/base funciona; ninguna fila ofrece editar ni eliminar. Capturas en
  `runs/133-capturas/` (ruta absoluta), claro y oscuro.

## Cierre obligatorio de cada etapa

`runs/133.estado`, `runs/133.md`, y al `OK` `runs/133.pr.md`. Commits por
función, en español, imperativo, sin `Co-Authored-By`.

# Observaciones del dueño — Mantenimiento (13/9/2026)

**Origen.** Cuarto documento de la misma ronda (`Mantenimiento.pdf`), corto:
una sola pantalla, "Orden de mantenimiento". Sin texto en rojo.

## 1. Decisión de alcance ya resuelta con el usuario

El PDF pedía "Ocultar" **Plan de Mantenimiento**, **Repuestos** y **Stock
Base** — que hoy son la maquinaria que calcula automáticamente el costo de
repuestos de una orden (§12 de la especificación, y ya implementadas:
`man_planes_mantenimiento` tarea 54, `inv_repuestos`/`inv_stock` tarea 52,
consumo de stock al cerrar una orden tarea 53/HU-37). Dos lecturas posibles,
con trabajo muy distinto: descope real de v1, o solo ocultar las 3 pantallas
de administración sin tocar la lógica.

**Confirmado: es solo ocultar las pantallas.** Repuestos y Stock siguen
funcionando y costeando automáticamente por debajo; lo único que cambia es
que el encargado no ve esas 3 pantallas en su menú del día a día. "Precio de
Mantenimiento Final" es el monto real ya generado (vía `gasto_id` →
`fin_gastos`, HU-37), mostrado como total en la orden — no un campo editable
nuevo que reemplace el cálculo.

## 2. Gaps reales → Sprint 18

| HU | Qué pide el dueño | Categoría |
|---|---|---|
| **HU-88** | Ocultar Plan de Mantenimiento/Repuestos/Stock Base del menú del encargado + mostrar "Precio de Mantenimiento Final" como el total real del gasto vinculado | GAP_REAL — resuelto arriba, es visibilidad de menú + un total en pantalla, no un cambio de modelo |
| **HU-89** | Descripción de Mantenimiento Final (al cerrar la orden), separada de la descripción de apertura | GAP_REAL, chico — `man_ordenes_mantenimiento` hoy solo tiene una `descripcion` (la de apertura, `database/migrations/2026_09_03_300004_create_man_ordenes_mantenimiento_table.php:58`) |
| **HU-90** | Tipo Equipo agrega "Chata" a Dron/Generador/Vehículo | GAP_REAL — **confirmado con el usuario: "Chata" es un valor más del catálogo interno de `man_vehiculos` (`tipo`), no una clase de equipo nueva.** No toca `equipo_tipo` de `man_ordenes_mantenimiento` (ese campo distingue en qué tabla vive el equipo — `dron`/`vehiculo` — no el tipo de vehículo en sí; una chata sigue siendo `equipo_tipo = 'vehiculo'`) |

Los demás campos de la orden ya están cubiertos: "Equipo" → `equipo_tipo` +
`equipo_id`; "Tipo de Orden" → `tipo` (`preventivo`/`correctivo`); "Descripción
de Falla o Mantenimiento" → `descripcion` (todos en la misma migración citada
arriba).

## 3. Hallazgo propio, no pedido por el dueño — deuda técnica, no HU

Al revisar la migración para ubicar HU-90 encontré que
`man_ordenes_mantenimiento.equipo_tipo` solo acepta `('dron', 'vehiculo')`
(`database/migrations/2026_09_03_300004_create_man_ordenes_mantenimiento_table.php:80-81`)
— **no incluye `'generador'`**. La tabla se creó el 3/9/2026 (tarea 53) antes
de que `man_generadores` existiera (tarea 72, 9/9/2026), así que hoy no se
puede abrir una orden de mantenimiento sobre un generador. No es parte de
esta ronda de observaciones — es deuda técnica que encontré de paso. Anotada
en la sección "Deuda técnica detectada" de `cola_tareas.md`, sin fila propia
en la cola: la decide el usuario, el ciclo no se la autoasigna.

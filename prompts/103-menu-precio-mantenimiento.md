<!-- ciclo: critica=no turno-noche=1 rama=feature/menu-precio-mantenimiento etapas=2 descongela=tests -->

# Tarea 103 — HU-88: menú del encargado sin administración diaria, precio final real

## Qué hacer

Dos cambios independientes sobre `Mantenimiento`, del mismo documento de
negocio (`docs/negocio/observaciones_mantenimiento_2026-09-13.md`):

1. El rol `encargado_operaciones` deja de ver en su menú "Plan de
   Mantenimiento", "Repuestos" y "Stock Base" — las usa poco y le ensucian la
   navegación del día a día. Las pantallas siguen existiendo y funcionando
   para quien sí tenga el permiso (el dueño, que recibe todos los permisos
   del catálogo sin excepción).
2. La orden de mantenimiento cerrada muestra el precio final **real** (el
   monto del gasto generado al cerrar), no solo el número de gasto.

Cargá el skill `verificacion` antes de tocar código, y `seguridad-roles`
antes de tocar permisos/menú.

### 1. Ocultar los 3 ítems

`database/seeders/Catalogo/SecMenuSeeder.php:261,270,271` ya gatea cada ítem
por un único `codigoPermiso` (`mantenimiento.plan.ver`,
`inventario.repuesto.ver`, `inventario.movimiento.ver`) — el menú de un rol
solo muestra lo que su rol activo tiene permitido. No hace falta tocar
`SecMenuSeeder`: alcanza con que `encargado_operaciones` deje de tener esos
tres códigos.

En `database/seeders/Catalogo/SeguridadSeeder.php`, `PERMISOS_ENCARGADO_OPERACIONES`
(líneas ~427-681) tiene hoy `mantenimiento.plan.ver` (línea 641),
`inventario.repuesto.ver` (línea 625) e `inventario.movimiento.ver`
(línea 629). Sacá esos tres códigos del array. **Antes de tocarlo**, leé
`RepuestosController`, `StockController` y `PlanesMantenimientoController`
(`app/Dominios/Inventario/Infraestructura/Http/Controllers/Web/`,
`app/Dominios/Mantenimiento/Infraestructura/Http/Controllers/Web/`) para
confirmar qué permiso exige cada acción (`index`/`create`/`edit`) — si
`.crear`/`.editar`/`.eliminar` de esos mismos catálogos siguen en la lista de
`encargado_operaciones` pero `.ver` no, decidí si eso deja un hueco raro
(puede crear pero no listar) y resolvelo del lado que menos alcance agregue:
lo mínimo que pide la HU es que el ítem desaparezca del menú, no rediseñar
el resto del permiso. Documentá la decisión en el commit.

**No toques** `PERMISOS_ENCARGADO_OPERACIONES` de `mantenimiento.orden.*`
(abrir/cerrar órdenes) — esa pantalla no es una de las tres que la HU pide
esconder.

### 2. Precio de Mantenimiento Final

`Mantenimiento` no lee el modelo `Gasto` de `Finanzas` directamente
(invariante 5 de ADR 0003, ver docblock de
`app/Dominios/Mantenimiento/Infraestructura/Eloquent/OrdenMantenimiento.php:19-22`).
Hoy `gasto_id` solo se muestra como número
(`app/Dominios/Mantenimiento/Infraestructura/Http/Views/pages/ordenes/edit.blade.php:131-136`,
`lang/es/mantenimiento.php` clave `detalle_gasto_valor` = `'Gasto #:id'`).

Sumá un contrato de LECTURA nuevo en `Finanzas`, mismo patrón que
`Finanzas\Contratos\EscrituraGastoMantenimiento.php` pero para leer en vez de
escribir: interfaz con un método tipo `montoDe(int $gastoId): ?string`
(`?string` porque invariante 6 de CLAUDE.md prohíbe `float`), implementación
Eloquent (`Finanzas\Infraestructura\...Eloquent.php`) y binding en
`FinanzasServiceProvider`. `OrdenesMantenimientoController@edit` lo inyecta y
pasa el monto a la vista cuando `$orden->gasto_id !== null`.

En la vista, reemplazá o sumá junto al `detalle_gasto_valor` actual una
etiqueta "Precio de Mantenimiento Final" con el monto (mismo formato liso
`Bs {{ $monto }}` que `recibo-planilla.blade.php:32-40` — el string decimal
ya viene formateado, sin `number_format`). Sin campo editable nuevo: es de
solo lectura, calculado siempre desde `fin_gastos.monto` (invariante 6: todo
monto derivado se recalcula desde el origen).

## Cómo repartir las etapas

- **Etapa 1**: `PERMISOS_ENCARGADO_OPERACIONES` (con la lectura previa de
  los 3 controladores), tests de menú/permiso.
- **Etapa 2**: contrato de lectura de `Finanzas`, controlador, vista,
  `lang/es/mantenimiento.php`, tests de la HTTP de la orden cerrada.

## Qué NO hacer

- No borres las rutas `panel.planes-mantenimiento.*`,
  `panel.repuestos.*`, `panel.stock.*` ni sus controladores — la HU pide
  ocultar del menú, no eliminar la función.
- No dejes que `Mantenimiento` haga `Gasto::query()` o cualquier acceso
  directo al modelo Eloquent de `Finanzas` — pasa por el contrato nuevo,
  mismo criterio que `EscrituraGastoMantenimiento`.
- No agregues un campo editable para el precio final — es derivado y de
  solo lectura (invariante 6).
- No toques `mantenimiento.orden.*` en `PERMISOS_ENCARGADO_OPERACIONES`.
- No toques `docs/decisiones/**` — no hay ADR que ampliar acá.

## Criterio de aceptación

`./bin/verify` = 0, con:
- Test de que el rol `encargado_operaciones` ya no ve "Plan de
  Mantenimiento"/"Repuestos"/"Stock Base" en su menú.
- Test de que un rol con el permiso (`dueno`) sigue accediendo a esas tres
  rutas por URL directa.
- Test de que el monto mostrado en la orden cerrada coincide con
  `fin_gastos.monto` del gasto generado al cerrar (regresión de HU-37,
  tarea 53).

## Cierre obligatorio de cada etapa

`runs/103.estado` con una sola palabra: `PARCIAL`/`OK`/`BLOQUEADA`.
`runs/103.md` con qué se hizo y qué falta, concreto. Al cerrar con `OK`,
`runs/103.pr.md` con título en la primera línea y cuerpo debajo.

## Commits

Agrupados por función (permisos+menú, contrato de lectura+vista+idioma,
tests si no entraron ya), español, imperativo, explicando el porqué. Sin
trailer `Co-Authored-By`.

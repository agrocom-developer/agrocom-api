<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/badges-menu-reales etapas=3 -->

# Tarea 60 — TE-14: reemplazar los badges de demostración del sidebar por contadores reales

## Por qué esta tarea

`plan_sprints.md` Sprint 12 (§255): "Los números del menú (`Órdenes 12`,
`Devengos 18.490`, `Sesiones 6`…) salen hoy de `DatosDemoPanel`: son
inventados. Cada badge consulta su módulo o desaparece; ningún número del
panel sin origen en la base." Es la última tarea de Sprint 12 — depende de
que las tareas 57 (reportes técnicos) y 58 (pausas) ya estén integradas,
porque dos de los ocho badges necesitan esas pantallas para tener un dato
real detrás.

No es crítica: son contadores de lectura, no mutan nada.

## Lo que ya existe

Los 8 badges hoy vienen de `DatosDemoPanel::badgesMenu()`
(`app/Dominios/Seguridad/Infraestructura/Http/Demo/DatosDemoPanel.php:46`),
consumidos por `CascaraPanel` (revisá cómo llega esa clave hasta la vista del
sidebar antes de tocar nada). Cada entrada es
`'menu.<modulo>.items.<item>' => ['numero' => ..., 'texto' => ...]`.

Para cada uno, esto es lo que ya existe en el código real (verificalo vos
mismo antes de armar el caso de uso — esta lista es un punto de partida, no
una verdad cerrada):

| Badge | Fuente real candidata | Estado |
|---|---|---|
| `operacion.items.programacion` | Ninguna — no hay concepto de "sesión programada para hoy" en el dominio (`ope_sesiones.estado` no tiene ese valor, es solo `abierto`/`cerrado`/etc.) | probablemente **desaparece** |
| `operacion.items.ordenes` | `Operaciones` — órdenes en estado `vigente` (HU-25, tarea 38, `OrdenesController`/`ListarOrdenes`) | real |
| `operacion.items.sesiones` | Cola de validación (HU-14, tarea 14, `ValidacionSesionesController`) — sesiones `cerrado` sin validar | real |
| `operacion.items.pausas` | Tarea 58 (`ope_pausas`) — revisá qué agregado quedó realmente disponible ahí; si esa tarea dejó la causa como obligatoria al registrar, "sin causa" puede no existir como estado posible — adaptá el texto del badge a lo que el dato real permite, no fuerces el texto de la maqueta | real, depende de la 58 |
| `comercial.items.reportes_cliente` | Portal del cliente (HU-41, tarea 55) — **sigue sin integrar** (PR #106 en borrador, rechazado dos veces, ver `runs/55.md`/`runs/55-veredicto.md`). Sin pantalla propia, este ítem no tiene de dónde sacar un número real | **desaparece** (no reintentes ni toques la 55 — quedó marcada para el usuario, ver `cola_tareas.md`) |
| `recursos.items.drones` | `ope_drones` no tiene columna de estado/taller (ver docblock de su migración: "deliberadamente mínima... nada de uso_acumulado, historial de mantenimiento") — no hay dato de "en taller" | **desaparece** |
| `mantenimiento.items.stock` | `Inventario` — `inv_stock` sí tiene `cantidad`/`stock_minimo` reales (tarea 52) | real |
| `financiero.items.devengos` | `fin_devengos_personal` del período (tarea 40/42, `DevengosController`) | real |

## Qué hacer

Cargá las skills `dominio-backend` y `verificacion`.

1. Por cada badge de la tabla, escribí (o reusá si ya existe) un método de
   conteo/suma en el módulo dueño — no un `Facade`/query suelto en
   `CascaraPanel`, que es de `Seguridad` y no debe tocar tablas de otros
   módulos directo (ADR 0003). El patrón correcto: un contrato de lectura
   chico por módulo (o un método del caso de uso de listado ya existente que
   ya sabe filtrar por el estado relevante — revisá si `ListarOrdenes` o el
   caso de uso de la cola de validación ya expone un `count()` antes de
   escribir uno nuevo).
2. Los badges que "desaparecen": sacá la clave de `DatosDemoPanel::badgesMenu()`
   — no, mejor: `DatosDemoPanel` desaparece del todo si estas eran sus
   únicas dos responsabilidades sin reemplazo (`badgesMenu()` completo
   reemplazado por datos reales, el resto de la clase — `chrome()`,
   `notificaciones()`, `sesiones()`, `distribucionSesiones()`, etc. — sigue
   siendo mock del dashboard y **no es parte de esta tarea**, no la toques
   más que en el método `badgesMenu()`). Si un ítem "desaparece", el
   componente que pinta el badge simplemente no recibe dato para esa clave
   — confirmá que la vista ya tolera la ausencia (probablemente sí, un
   `@isset`/`??` — si no, es el único cambio de vista que esta tarea
   necesita).
3. Componé el nuevo `badgesMenu()` (o el reemplazo equivalente) desde
   `CascaraPanel`/`DashboardController` inyectando los casos de uso reales de
   cada módulo, en vez del mock.

## Qué NO hacer

- No toques el resto de `DatosDemoPanel` (dashboard) — sigue siendo mock a
  propósito, documentado en su propio docblock; reemplazarlo es trabajo de
  HUs que no existen todavía en el plan.
- No reintentes ni "arregles" el portal del cliente (HU-41, tarea 55) para
  poder darle un número real a `reportes_cliente` — quedó rechazada dos
  veces y marcada para revisión humana (`runs/revision-pendiente.txt`); si
  la destrabás de rebote acá, la revisión que le corresponde se pierde.
- No le agregues una columna de estado a `ope_drones` solo para justificar
  el badge de "en taller" — es alcance de `Mantenimiento` (`man_baterias`/
  `man_vehiculos` ya tienen su propio estado), no de esta tarea.
- No inventes un concepto de "sesión programada" en `ope_sesiones` para
  salvar el badge de `programacion` — si no existe el dato, el badge
  desaparece, tal cual dice el CA.

## Cómo repartir las etapas

- **Etapa 1**: contratos de lectura/conteo reales por módulo (órdenes,
  sesiones, pausas, stock, devengos), tests unitarios de cada conteo.
- **Etapa 2**: composición en `CascaraPanel`/`DashboardController`, retiro de
  los tres badges sin dato real, ajuste de vista si hace falta.
- **Etapa 3**: tests Feature de punta a punta (el badge real cambia si cambia
  el dato de origen), verificación visual de que el sidebar no rompe layout
  con menos badges, `bin/verify` completo.

## Criterio de aceptación

- `./bin/verify` = 0, con Playwright.
- Test que, para cada badge que queda, cambia el dato de origen (crea una
  orden vigente más, valida una sesión, etc.) y confirma que el número del
  badge cambia en consecuencia — no un valor fijo.
- Test que confirma que los badges sin fuente real (`programacion`,
  `reportes_cliente`, `drones`) no aparecen.
- Spec visual del sidebar (si ya existe uno, actualizalo; si no, no hace
  falta crear uno nuevo solo para esto).

## Puede tocar

`app/Dominios/Seguridad/Infraestructura/Http/Demo/DatosDemoPanel.php` (solo
`badgesMenu()`), `app/Dominios/Seguridad/Infraestructura/Http/Presentacion/CascaraPanel.php`,
`app/Dominios/Seguridad/Infraestructura/Http/Controllers/Web/DashboardController.php`,
contratos de lectura nuevos en cada módulo dueño (`Operaciones/Contratos/**`,
`Inventario/Contratos/**`, `Finanzas/Contratos/**`) + su implementación,
vistas del sidebar si hace falta tolerar la ausencia de un badge, `tests/**`.

Fuera de alcance: el resto de `DatosDemoPanel`, `ope_drones`, la tarea 55.

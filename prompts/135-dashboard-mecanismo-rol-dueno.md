<!-- ciclo: critica=no turno-noche=1 rama=feature/dashboard-mecanismo-dueno etapas=3 -->

# Tarea 135 — el dashboard agrupa sus tabs por rol activo, empezando por el Dueño

Leé `docs/gestion/plan_dashboard_notificaciones_por_rol.md` completo (es
corto) antes de tocar nada — trae el diagnóstico ya hecho de dónde vive cada
pieza y por qué. Cargá los skills `panel-design-ui`, `dominio-backend` y
`verificacion`.

## El estado actual (ya diagnosticado, no lo reinvestigues)

`App\Dominios\Seguridad\Aplicacion\ArmarDashboard::ejecutar()` ya decide, por
rol activo, qué de las 14 `SeccionDashboard::cases()` es visible (por
permiso) y arma su contenido — no lo toques salvo que necesites una sección
nueva (ver más abajo). Lo único fijo hoy es el agrupamiento en tabs, en
`app/Dominios/Seguridad/Infraestructura/Http/Views/pages/dashboard.blade.php:20-29`:
un `$tabs` con 4 entradas (`resumen`/`mapa`/`lotes`/`multimedia`) iguales
para cualquier rol.

## Qué hacer

1. Reemplazá ese `$tabs` fijo por un agrupamiento que dependa del rol activo.
   Decisión de diseño a tu cargo, con un criterio: no es una vista por rol
   (seguiría el problema que la tarea 67 ya resolvió una vez — cuatro Blades
   que se desincronizan). Es una tabla `rol → array de tabs → array de claves
   de sección`, análoga en espíritu a como `SeccionDashboard::permiso()` es
   una tabla y no una constelación de `@if`. Un lugar razonable: un método
   nuevo en `ArmarDashboard` (p. ej. `tabsPara(string $rolClave): array`) o
   un enum/objeto de valor propio si el mapeo crece — vos decidís, dejalo
   documentado con el mismo criterio que ya tiene `SeccionDashboard`.
2. Implementá el agrupamiento del **Dueño** (rol `dueno`) en 3 tabs:
   - **Estado de cuentas**: de clientes y contratos. Investigá primero si
     `Finanzas` ya tiene un contrato de lectura de estado de cuenta por
     contrato (saldos, facturación, anticipos) antes de construir algo
     nuevo — `AvanceClientes` (`comercial.contrato.ver`) da avance de
     hectáreas, no plata; puede ser la mitad de esta tab, no toda.
   - **Resumen de trabajos actuales por equipo**: ninguna sección existente
     agrupa por equipo (`DistribucionSesiones` agrupa por estado). Mirá
     `LecturaEquipoTrabajo` (ya inyectado en `ArmarDashboard` para
     `diasEnHacienda()`) y el patrón de `misEquipos()` (hoy filtra por
     `persona_id`; acá necesitás la versión sin filtrar, para todos los
     equipos).
   - **Progreso en toda la campaña**: agregá `resumenPorLote()` a nivel
     campaña (hectáreas totales aplicadas vs. contratadas, con el mismo
     cálculo de porcentaje que ya usa `filaLote()` — no reinventes la
     fórmula) en vez de fila por fila.
3. Agregá el `SeccionDashboard::case` nuevo que necesites (con su `permiso()`
   — el mismo que ya gatea la pantalla completa del dato que muestra,
   invariante anti-fuga de la tarea 62) por cada pieza de contenido nueva.

## Qué NO hacer

No toques el agrupamiento de ningún otro rol (136 a 139 lo hacen cada uno
por su cuenta, sobre el mecanismo que dejás acá). No cambies
`ArmarDashboard::puedeVer()` ni el criterio de "sección sin contenido se
omite" (`ejecutar()`, comentario de la tarea 60). No agregues notificaciones
ni toques `notifications-menu.blade.php` (tarea 141, aparte).

## Criterio de aceptación

- `./bin/verify` = 0.
- Un usuario con rol activo `dueno` ve exactamente 3 tabs (cuando las 3
  tienen contenido) con los nombres de arriba, y ningún otro rol ve esas 3
  tabs iguales a como las veía antes de esta tarea (verificá con un usuario
  `piloto`/`jefe_campo` de que su vista no cambió).
- Con una sola tab visible, no aparece la barra de pestañas (regla ya
  probada de `dashboard.blade.php:51-53` — no la rompas).
- Playwright (`runs/135-navegador.cjs`) con el dueño demo, las 3 tabs y su
  contenido, claro y oscuro.

## Cierre obligatorio de cada etapa

`runs/135.estado`, `runs/135.md`, y al `OK` `runs/135.pr.md`. Commits por
función, en español, imperativo, sin `Co-Authored-By`.

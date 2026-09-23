<!-- ciclo: critica=no turno-noche=1 rama=feature/dashboard-piloto-ayudante etapas=2 -->

# Tarea 137 — dashboard del Piloto y del Ayudante (rol `auxiliar`)

Requiere la 135 integrada (mismo mecanismo, no lo reinventes). Leé
`docs/gestion/plan_dashboard_notificaciones_por_rol.md` §5. Cargá los
skills `panel-design-ui`, `dominio-backend`, `redaccion-neutra` y
`verificacion`.

## Contexto: ya hay bastante hecho, es reagrupar más que construir

`MisSesiones`, `MisEquipos` y `MiLiquidacion` ya existen como secciones
filtradas por `persona_id` del usuario (`requierePersona()` en
`SeccionDashboard`) — son casi exactamente "trabajos pendientes/realizados"
y "devengos" que pide el dueño. Confirmá que el rol `auxiliar` también
resuelve `persona_id` (tiene que, para cobrar jornal por persona+fecha, ADR
0023) y que `MiLiquidacion` le muestra lo mismo que al piloto.

## Qué hacer

1. Agrupá `piloto` y `auxiliar` en la misma forma de tabs (probablemente 2:
   una de devengos —`MiLiquidacion`— y otra de trabajos —`MisSesiones` +
   `MisEquipos`—, pero es tu criterio si conviene una sola tab con las tres
   secciones apiladas dado que ya no hay pestaña cuando hay una sola).
2. **La cuestión del label "Ayudante" vs. `auxiliar`**: el dueño lo nombra
   "ayudante" al hablar, la clave en `sec_roles` es `auxiliar`
   (`SeguridadSeeder::ROLES`). Antes de tocar nada, confirmá con
   `grep -rn "auxiliar" lang/es/` qué tan extendido está el label "Auxiliar"
   en la UI actual. Si es solo cuestión de qué texto se muestra (no de la
   clave del rol en la base ni en `PERMISOS`), cambiá el label a "Ayudante"
   en `lang/es/*.php` donde corresponda (nunca hardcodeado, invariante de
   `CLAUDE.md`). Si tocar solo el label deja inconsistencias raras (p. ej.
   el propio nombre del rol en el selector "Cambiar de rol"), documentalo en
   `runs/137.md` y dejalo así — **no renombres la clave `auxiliar` en
   `sec_roles`/`SeguridadSeeder`/permisos sin que el dueño lo confirme
   explícitamente**, es un cambio mucho más invasivo de lo que parece.

## Qué NO hacer

No toques el agrupamiento de ningún otro rol. No cambies qué permiso gatea
cada sección (`permiso()` de `SeccionDashboard`) — esta tarea agrupa, no
reabre quién ve qué.

## Criterio de aceptación

- `./bin/verify` = 0.
- Un piloto y un auxiliar demo ven su dashboard reagrupado, con sus propios
  devengos y trabajos, sin ver nada que no vieran antes.
- Playwright (`runs/137-navegador.cjs`) con ambos roles, claro y oscuro.

## Cierre obligatorio de cada etapa

`runs/137.estado`, `runs/137.md`, y al `OK` `runs/137.pr.md`. Commits por
función, en español, imperativo, sin `Co-Authored-By`.

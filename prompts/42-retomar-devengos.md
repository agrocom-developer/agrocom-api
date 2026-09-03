<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/devengos-panel etapas=2 -->

# Tarea 42 — HU-28: retomar y cerrar devengos por período (continuación de la tarea 40)

## Por qué esta tarea

La tarea 40 (mismo alcance: `prompts/40-devengos-panel.md`, no lo repitas,
léelo como referencia del criterio original) implementó HU-28 casi entera,
pero sus 3 sesiones de implementación se agotaron seguidas sin declarar
estado (`runs/40.estado` = `AGOTADA`) y **nunca hicieron un solo commit** —
quedó todo en el working tree de `develop`, sin commitear, bloqueando el
arranque de la tarea 41. `runs/40.log` muestra la causa exacta: la sesión
lanzó `bin/verify` en segundo plano y esperó su notificación con `Monitor`
en vez de commitear primero — la llamada a `Monitor` fue denegada por
permisos, y la sesión terminó su turno sin commitear ni declarar estado. Es
el mismo patrón que ya había costado tiempo (no commits) en la tarea 38,
documentado en `runs/38-plan.md`.

El trabajo se rescató a `git stash` antes de esta planificación (mismo
mecanismo ya usado con la tarea 32, ver `docs/gestion/cola_tareas.md` fila
32): `git stash list` muestra `stash@{0}: On develop: tarea 40 (devengos
HU-28) sin commitear...` (hay un `stash@{1}` más viejo, de la tarea 32 —
**no lo toques**, es otra historia sin resolver, del usuario).

**Investigación ya hecha, no la repitas**: leí el código completo antes de
escribir este prompt. Lo que hay en el stash parece bastante completo:

- `ListarDevengosPersona.php` — suma con `Brick\Math\BigDecimal`, filtra por
  mes calendario, resuelve período por defecto. Coincide con el criterio
  original.
- `DevengosController@index/show` — 404 de acceso cruzado dentro de `show()`
  comparando contra `AutorizacionPanelWeb::personaId()`, 403 vía
  `tienePermiso()`, `index()` redirige. Coincide con el criterio original.
- `tests/Feature/Finanzas/DevengosPersonalPanelTest.php` — 8 tests que ya
  cubren: total exacto sin error de redondeo, acceso cruzado → 404, filtro
  de período, 403 sin permiso, auxiliar con su propio permiso, redirect de
  `index`, 404 de cuenta sin persona asociada, ítem de menú gateado. Es el
  mismo criterio de aceptación del prompt original, ya escrito.
- `tests/Visual/devengos.spec.ts` + `tests/Visual/fixtures/devengos-demo.php`
  — spec claro/oscuro con fixture propio (reusa `camila.rojas`, no crea
  `SecUser` nuevo, para no romper `usuarios.spec.ts`).
- `lang/es/finanzas.php`, `resources/css/pages/devengos.css`,
  `SeguridadSeeder.php` (permiso `finanzas.devengo.ver`, `PERMISOS_PILOTO`,
  `PERMISOS_AUXILIAR` nuevo), `SecMenuSeeder.php` (activa el ítem
  "devengos"), `routes/web.php` (las dos rutas) — todo presente.
- `runs/40-verify.log` (queda en el repo, no se borra) muestra
  `bin/verify` corriendo: Pint y Larastan en verde, Pest completo en verde,
  Vite compilando, y Playwright corriendo — llegó a pasar los dos tests de
  `devengos.spec.ts` (`✓ 19 devengos › claro`, `✓ 20 devengos › oscuro`)
  antes de cortarse en el test 23 de 42 (no se sabe si el resto — las specs
  de páginas ya existentes — sigue en verde; no hay evidencia de que se
  haya roto, pero tampoco de que haya terminado).

**Nada de esto es una promesa de que está terminado.** No asumas que
alcanza con recuperar el stash y commitear: leé cada archivo del stash
contra el criterio de aceptación de abajo (igual al de la tarea 40) y
completá lo que falte. En particular, el stash también incluye cambios en
`tests/Visual/helpers.ts`, `resources/css/pages/index.css` y 4 capturas de
referencia de `personas`/`usuarios` que no tienen relación obvia con
devengos — entendé por qué existen (¿drift real de un cambio compartido?
¿efecto colateral de correr Playwright con `--update-snapshots`?) antes de
darlos por buenos; si son ruido, no los commitees.

## Qué hacer

Cargá las skills `dominio-backend`, `panel-design-ui` y `verificacion`.

1. En la rama `feature/devengos-panel` (el ciclo ya la crea desde `develop`
   antes de esta sesión), recuperá el stash: `git stash list` para
   confirmar cuál es el de la tarea 40 (**no** el de la tarea 32), `git
   stash pop` (o `apply` + `drop` si preferís revisar antes de dropear).
2. Revisá cada archivo contra el criterio de aceptación original (abajo,
   copiado de `prompts/40-devengos-panel.md`). Completá lo que falte,
   corregí lo que esté mal.
3. Corré `./bin/verify` **de forma síncrona, esperando el resultado en el
   mismo turno** — no lo lances en segundo plano a esperar con `Monitor`.
   Si de verdad necesitás lanzarlo en background por el tiempo que tarda,
   **commiteá primero** lo que ya esté completo y verificado a mano, así un
   agotamiento de turnos no vuelve a perder trabajo sin guardar.
4. Commiteá agrupado por función (esquema no aplica acá, no hay migración
   nueva): p. ej. un commit para `boot()` + `ListarDevengosPersona` +
   controller + rutas + permisos + menú, otro para la vista + copy, otro
   para tests Feature + spec visual. No hace falta que coincida con los
   commits que hubiera hecho la tarea 40 — son commits nuevos de esta
   sesión.
5. Checklist de `docs/diseno/guia_pantalla_panel.md` §8 sobre la vista
   nueva (`show.blade.php`): cero color/medida hardcodeada, cero texto
   literal fuera de `lang/es/finanzas.php`.

## Qué NO hacer

- No reescribas `ListarDevengosPersona` ni el controller desde cero si lo
  que hay en el stash ya cumple el criterio — revisalo y completalo, no lo
  tires.
- No toques el `stash@{1}` (tarea 32, `atoms/input` LSP) — es otra historia
  sin resolver, la decisión es del usuario, no de esta tarea.
- No implementes HU-29 (anticipos) ni toques `fin_devengos_personal` /
  `GenerarDevengosSesion` — esta tarea es solo cerrar HU-28.
- No sumes con floats — `Brick\Math\BigDecimal` en todo el camino
  (invariante 6).
- No lances `bin/verify` en background y termines el turno esperando su
  notificación sin haber commiteado nada antes — es la causa exacta por la
  que esta tarea existe.

## Cómo repartir las etapas

- **Etapa 1**: recuperar el stash, completar/corregir contra el criterio,
  commits de dominio + HTTP + rutas + permisos + menú + vista + copy.
- **Etapa 2**: `./bin/verify` en verde de punta a punta (incluyendo
  Playwright), checklist §8, commit final de tests si falta algo.

## Criterio de aceptación

(Igual al de la tarea 40 — no cambió.)

- `./bin/verify` = 0, con Playwright.
- Test Feature (`tests/Feature/Finanzas/DevengosPersonalPanelTest.php`)
  cubriendo: un piloto ve sus propios devengos del período con el total
  exacto; acceso cruzado (piloto A pide la persona de piloto B) → 404;
  filtro por período; 403 sin `finanzas.devengo.ver`; el auxiliar también
  accede (permiso propio); el ítem de menú "Devengos" gateado.
- Spec visual (`tests/Visual/devengos.spec.ts`), claro y oscuro.
- Checklist de `docs/diseno/guia_pantalla_panel.md` §8 pasa.

## Puede tocar

`app/Dominios/Finanzas/**`, `routes/web.php`,
`database/seeders/Catalogo/SeguridadSeeder.php`,
`database/seeders/Catalogo/SecMenuSeeder.php`, `lang/es/finanzas.php`,
`resources/css/pages/devengos.css`, `tests/**`.

Fuera de alcance: `fin_devengos_personal`, `GenerarDevengosSesion`,
`fin_anticipos` (tarea 41, siguiente en la cola — no arranca hasta que esta
esté integrada, tiene una dependencia real declarada en su propio prompt).

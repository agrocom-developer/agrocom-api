<!-- ciclo: critica=no turno-noche=0 -->
# Tarea 07 — Regresión visual del panel con Playwright

Sesión nueva y aislada. Todo lo que necesitás está acá o en el repo.

Cargá los skills `verificacion` y `panel-design-ui`. No leas `docs/` entero.

## Por qué esta tarea

`docs/gestion/automatizacion_desarrollo.md` ("Lo que falta", punto 2) y el
skill `verificacion` ("Qué NO cubre la cascada todavía") dicen lo mismo: no
hay regresión visual. `playwright` ya está en `devDependencies` desde hace
tiempo, pero no hay `playwright.config`, no hay suite, no hay capturas de
referencia. Mientras eso siga así, un cambio de CSS puede romper el tema
oscuro y `./bin/verify` queda igual de verde — y mientras no exista esta
suite, ningún cambio visual del panel puede cerrarse sin que una persona
mire la pantalla a mano (ver skill `panel-design-ui`, "Verificación visual").

Esta tarea es esa suite: el entregable **es** el test, igual que la tarea 04
(aduana de la invariante 7). Por eso corre con `turno-noche=0` — si algo del
gate queda mal, la sesión de corrección tiene que poder tocar `tests/Visual/`.

## Qué hacer

**1. El runner.** `playwright` (el paquete core) ya está instalado, pero el
comando `playwright test` lo provee `@playwright/test`, que todavía no está
en `package.json`. Agregalo (misma versión mayor que `playwright`, 1.62.x),
`npm install`, y `npx playwright install chromium` (con `--with-deps` si el
entorno lo permite; si no, sin esa bandera y documentá la limitación).

**2. `playwright.config.ts`** en la raíz. Un solo navegador (Chromium) alcanza
para esta primera pasada — no multipliques la matriz sin necesidad.
`testDir: 'tests/Visual'`, `baseURL: 'http://localhost:8000'`, y
`expect: { toHaveScreenshot: { animations: 'disabled' } }` (el panel tiene
`@view-transition` nativo — ver skill `panel-design-ui`; sin desactivar
animaciones las capturas van a ser inestables entre corridas).

**3. El servidor contra el que corre.** El panel vive en el contenedor `app`
(`docker-compose.yml`, puerto 8000) con Postgres poblado por
`DatabaseSeeder` → `CatalogoSeeder` + `Demo\DemoSeeder` (usuario
`camila.rojas` / `password`, tres roles — ver
`database/seeders/Demo/PanelDemoSeeder.php`). Cada corrida tiene que
garantizar ese estado de forma **idempotente**, sin derribar un compose que ya
esté arriba de otra sesión (`docker compose up -d` no reinicia lo que ya
corre) y sin `migrate:fresh` (bloqueado por
`.claude/hooks/guardarrail-bash.sh` — usá `migrate --seed --force`, que es
seguro de repetir porque los seeders de demo son `firstOrCreate`). Un
`webServer.command` de Playwright con `reuseExistingServer: true` **no**
sirve solo: si el contenedor ya está arriba pero sin seedear, Playwright no
corre el comando y las vistas salen vacías. Resolvé esto con un
`globalSetup` (o el mecanismo que prefieras) que siempre asegure
compose-arriba + migrate-seed antes de la primera prueba.

**4. Tres vistas, ya cerradas y estables — no más que estas tres:**
- `/login`
- `/panel/seleccionar-rol` (tras loguear con `camila.rojas`/`password`)
- `/panel/dashboard` (con un rol activo elegido, p. ej. `dueno`)

Todas en tema claro y oscuro — mismo criterio que usó la sesión del
28/8/2026 (skill `panel-design-ui`): alternar `data-bs-theme` o el toggle
real del header, tomar ambas capturas. Si alguna vista tiene una zona
genuinamente dinámica (fecha/hora actual, algo que `DatosDemoPanel` no fije),
enmascarala con la opción `mask` de `toHaveScreenshot` — no la excluyas de la
suite ni la reescribas para "arreglarla".

**5. Generá las capturas de referencia** (`--update-snapshots`), commiteálas,
y corré `npx playwright test` **dos veces seguidas** para confirmar que no
hay flakiness (fuente sin cargar, animación a mitad de camino, race del
`globalSetup`).

**6. Verificá el gate en el otro sentido**, como pide `verificacion` para
todo gate nuevo: cambiá algo visual real y de forma temporal (un token de
color, un spacing) en el CSS del panel, corré la suite, confirmá que
Playwright **falla** y muestra el diff, y revertí el cambio. Contá en
`runs/07.md` qué tocaste y qué reportó — sin esa evidencia la tarea no está
hecha (mismo estándar que `runs/04.md` y `runs/06.md`).

## Qué NO hacer

- No integres esto en `bin/verify` todavía. `bin/verify` corre PHP dentro del
  contenedor, que no tiene Node ni navegadores; Playwright corre en el host.
  Es un comando aparte a propósito — la integración, si hace falta, es
  decisión de otra tarea.
- No toques CSS/Blade del panel para que una vista "salga más estable" —
  si algo parpadea o cambia entre corridas, se resuelve en el test
  (`mask`, esperar la fuente, desactivar animaciones), nunca reescribiendo el
  componente.
- No uses `migrate:fresh`, `migrate:refresh` ni `db:wipe` — están bloqueados
  por el guardarraíl y además destruirían datos de otra sesión que comparta
  el mismo compose.
- No amplíes a más de estas tres vistas ni agregues más navegadores/viewports
  en esta pasada.
- No toques `CLAUDE.md`, los ADRs, `.claude/` ni
  `docs/gestion/automatizacion_desarrollo.md` — ese documento lo actualiza la
  próxima sesión de planificación, no esta.

## Criterio de aceptación

`npx playwright test` devuelve 0, dos corridas seguidas, con las capturas de
referencia versionadas en git. Más la evidencia del punto 6 (el gate falla
ante una regresión real inyectada, y se revierte). Además, `./bin/verify`
sigue devolviendo 0 — este cambio no debería tocar nada de PHP, pero
confirmalo.

## Máximo de intentos

3.

## Commits

Agrupados por función — no uno por archivo ni uno solo con todo. Por ejemplo:
runner + config, capturas de referencia por vista, evidencia del gate si deja
algún artefacto versionado. Mensajes en español, imperativo, explicando el
porqué — mirá `git log` para el tono.

## Cierre obligatorio

- `runs/07.estado` con una sola palabra: `OK` o `BLOQUEADA`.
- `runs/07.md`: cómo armaste el arranque del servidor para los tests, qué
  vistas cubriste, la evidencia de la inyección (punto 6), y qué quedó afuera.
- `runs/07.pr.md`: título del PR en la primera línea, cuerpo debajo. El ciclo
  lo usa tal cual para abrir el PR — no lo abras vos.

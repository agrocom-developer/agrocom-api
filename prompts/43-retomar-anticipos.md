<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/anticipos-personal etapas=3 -->

# Tarea 43 — HU-29: retomar y cerrar anticipos con tope validado (continuación de la tarea 41)

## Por qué esta tarea

La tarea 41 (mismo alcance: `prompts/41-anticipos-personal.md`, no lo
repitas, léelo como criterio original completo) implementó HU-29 casi
entera, pero sus 3 sesiones de implementación se agotaron seguidas sin
declarar estado (`runs/41.estado` = `AGOTADA`) y **nunca hicieron un solo
commit** — quedó todo en el working tree de `develop`, sin commitear,
bloqueando el arranque de la siguiente tarea. Es la tercera vez que pasa
exactamente este patrón (tareas 38, 40 y ahora 41, todas documentadas en
`runs/38-plan.md` y `runs/41-plan.md` — el anterior a este): una sesión
lanza `bin/verify` en segundo plano y termina su turno esperando la
notificación en vez de commitear primero lo que ya tiene verificado a mano.

El trabajo se rescató a `git stash` antes de esta planificación (mismo
mecanismo ya usado con las tareas 32 y 40): `git stash list` muestra
`stash@{0}: On develop: On develop: tarea 41 (anticipos HU-29) sin
commitear...` (hay un `stash@{1}` más viejo, de la tarea 32 — **no lo
toques**, es otra historia sin resolver, del usuario).

**Investigación ya hecha, no la repitas**: leí el código completo antes de
escribir este prompt, archivo por archivo, contra el criterio de aceptación
original de `prompts/41-anticipos-personal.md`. Lo que hay en el stash
parece completo:

- Migración `2026_09_02_200001_create_fin_anticipos_table.php` —
  `persona_id`, `monto DECIMAL(12,2)`, `fecha`, `motivo` nullable, auditoría,
  soft delete, `CHECK (monto > 0)` solo pgsql. Coincide con el criterio.
- `Aplicacion/CalcularDisponibleAnticipo.php` — reusa
  `ListarDevengosPersona` (tarea 40/42) para el devengado del mes, suma los
  `fin_anticipos` vivos del mismo mes con `Brick\Math\BigDecimal`, tope =
  `min(3000.00, devengado × 0.70 con RoundingMode::HalfDown)`, disponible =
  tope − anticipos, nunca negativo. Coincide con la fórmula del criterio
  original.
- `Aplicacion/RegistrarAnticipo.php` — lanza `AnticipoExcedeTope` con el
  disponible exacto en el mensaje ANTES de tocar la base si el monto lo
  supera. `Aplicacion/ListarAnticipos.php` (filtro por persona/período).
  `Aplicacion/EliminarAnticipo.php` (soft delete, `updated_by` a mano antes
  del `delete()`, mismo criterio que `EliminarBase`).
- `AnticiposController.php` — `index/create/store/destroy`, tres permisos de
  grano fino verificados contra el rol activo, captura
  `AnticipoExcedeTope` → `redirect()->back()->withErrors(['estado' => ...])`.
  `CrearAnticipoRequest.php` con las reglas de validación.
- Vistas `index.blade.php`/`create.blade.php`, `resources/css/pages/anticipos.css`.
- `SeguridadSeeder.php` (3 permisos nuevos en `PERMISOS_ENCARGADO_OPERACIONES`),
  `SecMenuSeeder.php` (ítem "anticipos" nuevo, orden 7), `routes/web.php`
  (las 4 rutas), `lang/es/finanzas.php` (bloque `anticipos`).
- `tests/Feature/Finanzas/AnticiposPanelTest.php` — 9 tests que cubren
  literalmente cada punto del criterio original: ambos topes por separado,
  acumulación de dos anticipos contra el remanente, baja que libera cupo,
  bitácora, 403, y el caso `3.33 × 12.35` de precisión decimal exacta (mismo
  espíritu que la tarea 16).
- `tests/Visual/anticipos.spec.ts` + `tests/Visual/fixtures/anticipos-demo.php`
  + las 4 capturas de referencia (`create`/`index` × claro/oscuro) ya
  generadas.
- Como efecto colateral de sembrar el ítem de menú nuevo, las capturas de
  `tests/Visual/devengos.spec.ts-snapshots/` cambiaron (el sidebar ahora
  incluye "Anticipos") — parece legítimo, pero confirmalo corriendo
  Playwright completo, no lo des por bueno sin mirar.

**Nada de esto es una promesa de que está terminado.** No asumas que
alcanza con recuperar el stash y commitear: releé cada archivo contra el
criterio de aceptación de abajo (igual al de la tarea 41) y completá lo que
falte o corrijas lo que esté mal.

## Qué hacer

Cargá las skills `dominio-backend`, `panel-design-ui` y `verificacion`.

1. En la rama `feature/anticipos-personal` (el ciclo la retoma, ya existe
   con 0 commits), recuperá el stash: `git stash list` para confirmar cuál
   es el de la tarea 41 (**no** el `stash@{1}` de la tarea 32), `git stash
   pop` (o `apply` + `drop` si preferís revisar antes de dropear).
2. Revisá cada archivo contra el criterio de aceptación de
   `prompts/41-anticipos-personal.md` (copiado abajo). Completá lo que
   falte, corregí lo que esté mal.
3. Corré `./bin/verify` **de forma síncrona, esperando el resultado en el
   mismo turno** — no lo lances en segundo plano a esperar con `Monitor`.
   Si de verdad necesitás lanzarlo en background por el tiempo que tarda,
   **commiteá primero** lo que ya esté completo y verificado a mano, así un
   agotamiento de turnos no vuelve a perder trabajo sin guardar. Esta es la
   causa exacta, tercera vez, por la que esta tarea existe — no la repitas.
4. Commiteá agrupado por función: p. ej. un commit para migración + modelo +
   casos de uso, otro para controller + request + rutas + permisos + menú,
   otro para vistas + copy + CSS, otro para tests Feature + spec visual +
   fixture. No hace falta que coincida con los commits que hubiera hecho la
   tarea 41 — son commits nuevos de esta sesión.
5. Checklist de `docs/diseno/guia_pantalla_panel.md` §8 sobre las dos vistas
   nuevas.

## Qué NO hacer

- No reescribas nada desde cero si lo que hay en el stash ya cumple el
  criterio — revisalo y completalo, no lo tires.
- No toques `stash@{1}` (tarea 32, `atoms/input` LSP) — es otra historia sin
  resolver, decisión del usuario.
- No implementes HU-30 (planilla) — es la tarea siguiente en la cola, con su
  propio prompt, y depende de que esta esté integrada primero.
- No calcules el tope con floats — `BigDecimal` en todo el camino.
- No lances `bin/verify` en background y termines el turno esperando su
  notificación sin haber commiteado nada antes.

## Cómo repartir las etapas

- **Etapa 1**: recuperar el stash, completar/corregir dominio + casos de uso
  + HTTP contra el criterio, commits agrupados.
- **Etapa 2**: vistas, copy, permisos, menú, rutas si falta algo; commit.
- **Etapa 3**: `./bin/verify` en verde de punta a punta (incluyendo
  Playwright, confirmando que el drift de las capturas de devengos es
  legítimo), checklist §8, commit final si falta algo.

## Criterio de aceptación

(Igual al de la tarea 41 — no cambió.)

- `./bin/verify` = 0, con Playwright.
- Test Feature (`tests/Feature/Finanzas/AnticiposPanelTest.php`) cubriendo:
  anticipo dentro de ambos topes; rechazo por tope absoluto de 3.000 Bs/mes
  con el disponible exacto en el mensaje; rechazo por 70 % del devengado con
  devengado bajo; dos anticipos del mismo mes donde el segundo se rechaza
  contra el remanente, no contra el tope total; baja por soft delete que
  libera cupo; bitácora en alta y baja; 403 sin el permiso; monto exacto sin
  error de redondeo flotante.
- Spec visual (`tests/Visual/anticipos.spec.ts`), claro y oscuro.
- Checklist de `docs/diseno/guia_pantalla_panel.md` §8 pasa.

## Puede tocar

`app/Dominios/Finanzas/**`, migración nueva, `routes/web.php`,
`database/seeders/Catalogo/SeguridadSeeder.php`,
`database/seeders/Catalogo/SecMenuSeeder.php`, `lang/es/finanzas.php`,
`resources/css/pages/anticipos.css`, `tests/**`.

Fuera de alcance: HU-30 (planilla, tarea 44, siguiente en la cola — no
arranca hasta que esta esté integrada), `fin_devengos_personal`.

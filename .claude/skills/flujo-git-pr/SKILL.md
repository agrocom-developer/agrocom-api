---
name: flujo-git-pr
description: Cómo se integra el trabajo en agrocom-api — ramas GitFlow simplificado, convención de mensajes de commit en español, PR contra develop y el auto-merge que mergea solo cuando CI está en verde. Usar antes de crear una rama, commitear o abrir un PR.
---

# Flujo de integración — agrocom-api

GitFlow simplificado (ADR 0006). La versión operativa está en `CONTRIBUTING.md`;
esto es lo que hay que tener presente al trabajar.

## Ramas

- **`master`**: estable. Solo recibe merges de `develop` cuando un conjunto de
  features está listo para desplegarse — nunca commits directos.
- **`develop`**: integración. Toda `feature/*` entra acá por PR.
- **`feature/<nombre-corto>`**: una por HU o TE del plan de sprints. Nace de
  `develop`, muere mergeada a `develop`.
- **`fix/<nombre-corto>`**: corrección puntual. Nace de `master`, vuelve a
  `master` y se reincorpora a `develop`.

**Nombres de rama de 2–3 palabras**: `feature/usuarios-multirol`,
`feature/dashboard-agro`, `feature/verify-hooks`. No `feature/implementacion-del-panel-de-usuarios-con-roles`.

## Un PR, varios commits

El PR es la unidad de revisión, no el commit. Mientras se trabaje sobre el mismo
objetivo, se agregan commits a la misma rama y al mismo PR — **nunca un PR por
commit**. Un PR nuevo se abre cuando cambia el objetivo.

## Mensajes de commit

En español, imperativo, sin punto final:

```
agrega validación de solape en sesiones
corrige cálculo de hectárea acumulada en relevo
documenta la novena vuelta en el catálogo de diseño del panel
```

**Sin trailer `Co-Authored-By`.** Decisión explícita del usuario desde el commit
`084b732`; los anteriores lo llevan y quedan así (no se reescribe historia ya
publicada). Esto vale también para los subagentes: ninguno commitea por su cuenta.

## El merge ya está automatizado

`.github/workflows/auto-merge.yml` mergea el PR con squash **en cuanto el check
`laravel-tests` queda verde** — sin gesto manual. No usa el auto-merge nativo de
GitHub (no disponible en repos privados del plan Free): espera los check-runs por
SHA con la API REST y mergea con `gh pr merge --squash`.

Consecuencias prácticas:

- Abrir un PR equivale a decidir que ese código entra a `develop`. Si no está
  listo, no se abre el PR todavía.
- El gate real es `ci.yml` → `laravel-tests` (Pint + Larastan + Pest sobre PHP 8.3
  con Postgres 16). Lo mismo que corre `bin/verify` localmente — por eso conviene
  que la cascada esté en verde **antes** de pushear, no después.
- **Un PR en draft NO se integra.** El job `merge-when-green` se saltea los
  borradores (`if: github.event.pull_request.draft == false`); al marcarlo
  "Ready for review" salta el evento `ready_for_review` y ahí sí corre. Un draft
  es, por definición, trabajo que espera un gesto humano — así que **una tarea
  automatizada abre PR normal, no draft**, o el turno se detiene esperando a
  alguien.
- El gesto manual de aprobación (comentario `/merge`) fue evaluado y descartado
  por el usuario: el gate de CI en verde ya cumple lo que necesita.
- No hay branch protection formal en GitHub (requiere permisos de admin que la
  cuenta `gh` de trabajo no tiene). El auto-merge funciona igual sin ella.

## Guardarraíles activos

`.claude/hooks/guardarrail-bash.sh` bloquea, incluso en modos permisivos:
push directo a `master`/`develop`, `push --force`, `reset --hard`, `clean -fdx`,
commits sobre `master` (y pide confirmación sobre `develop`). No son sugerencias:
son denegaciones.

## Antes de pushear

`./bin/verify` en verde — ver el skill [verificacion].

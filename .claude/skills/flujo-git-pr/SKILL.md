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

**Nombres de rama de 2–3 palabras, de la función del proyecto**:
`feature/usuarios-multirol`, `feature/sync-idempotente`, `feature/ordenes-offline`.
Nunca de la actividad ni del número de tarea (`feature/implementar-tests`,
`feature/tarea-09`), ni largos como
`feature/implementacion-del-panel-de-usuarios-con-roles`.

## Un PR = una HU, con varios commits adentro

La unidad de entrega es la **historia de usuario o tarea técnica completa** de
`docs/gestion/plan_sprints.md`, con todos sus criterios de aceptación cubiertos.
No el commit, y no el pedazo que entró en una sesión de trabajo. Mientras se
trabaje sobre el mismo objetivo se agregan commits a la misma rama y al mismo PR
— **nunca un PR por commit**. Un PR nuevo se abre cuando cambia el objetivo.

Si la historia no entra en una sesión, se sigue en otra **sobre la misma rama**:
en el ciclo automático eso son las `etapas=` del prompt (ver
`docs/gestion/automatizacion_desarrollo.md` §5), y a mano es simplemente volver a
la rama y seguir commiteando. Partir la HU en tareas para que quepa en una sesión
es lo que llenó el historial de PRs de un commit, y ya no se hace.

Vale abrir el PR a mitad de camino, **en borrador**: el auto-merge se saltea los
borradores, así que no se integra hasta marcarlo *Ready for review*.

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

Desde el 1/9/2026 no depende de que la sesión se acuerde: `.claude/settings.json`
declara `"includeCoAuthoredBy": false`, así que el harness no lo agrega. Se
sostuvo hasta acá por disciplina y se filtraba igual — el squash de GitHub arrastra
el trailer de cualquier commit que lo traiga.

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
  alguien. El ciclo abre borrador solo en los dos casos en que detenerse es
  justamente lo que corresponde: una tarea `critica=si`, y una HU que se quedó
  sin etapas antes de cerrarse.
- El gesto manual de aprobación (comentario `/merge`) fue evaluado y descartado
  por el usuario: el gate de CI en verde ya cumple lo que necesita.
- No hay branch protection formal en GitHub (requiere permisos de admin que la
  cuenta `gh` de trabajo no tiene). El auto-merge funciona igual sin ella.

## Guardarraíles activos

`.claude/hooks/guardarrail-bash.sh` bloquea, incluso en modos permisivos:
push directo a `master`/`develop`, `push --force`, `reset --hard`, `clean -fdx`,
commits sobre `master` (y pide confirmación sobre `develop`). No son sugerencias:
son denegaciones.

## Limpieza de ramas

Con squash, una rama integrada no queda como ancestro de `develop`, así que
`git branch -d` la rechaza y la lista crece hasta volverse ilegible.
`bin/limpiar-ramas` decide por evidencia (PR mergeado, ancestro de una rama
mergeada, o contenido idéntico) y sin `--ejecutar` solo informa.

**Se corre a mano, cuando lo decide el usuario** — típicamente tras un tramo
largo de avance. Ninguna sesión ni el ciclo lo invocan por su cuenta.

## Antes de pushear

`./bin/verify` en verde — ver el skill [verificacion].

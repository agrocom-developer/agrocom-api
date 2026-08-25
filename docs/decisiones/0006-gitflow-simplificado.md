# ADR 0006 — Branching: GitFlow simplificado

**Estado:** Aceptada · **Reemplaza:** el trunk-based (`main` + ramas cortas `feat/...`/`fix/...`) descrito en `docs/legacy/definicion_tecnica_repos_modulos_stack.md`.

## Contexto

El proyecto lo desarrolla una sola persona con agentes de IA como implementadores. El GitFlow clásico (main + develop + feature/\* + release/\* + hotfix/\*) está pensado para coordinar equipos con ciclos de release formales; para un solo desarrollador, `release/*` y `hotfix/*` agregan ceremonia sin nadie a quien coordinar. Al mismo tiempo, trunk-based puro pierde la separación entre "lo que ya está integrado" y "lo que ya está en producción", que sigue siendo útil incluso trabajando solo, porque las apps de campo (`agrocom-field`) dependen del contrato de API estable en producción, no de lo que esté a medio integrar.

## Decisión

**GitFlow simplificado**, en ambos repositorios (`agrocom-api` y `agrocom-field`):

- **`master`**: rama estable. En `agrocom-api`, cada merge a `master` dispara el deploy continuo (staging → producción), tal como ya definía `definicion_tecnica_repos_modulos_stack.md`. En `agrocom-field`, cada merge a `master` puede recibir un tag SemVer + `versionCode` Android (`1.4.0+17`) cuando corresponde publicar una versión candidata.
- **`develop`**: rama de integración. Todas las `feature/*` se mergean acá primero.
- **`feature/*`**: una por historia de usuario o tarea técnica (ver `docs/gestion/plan_sprints.md`), nace de `develop`, muere mergeada a `develop` por PR.
- **`fix/*`**: para correcciones puntuales; nace de `master`, se mergea a `master` y se reincorpora a `develop` para que no se pierda en la siguiente integración.
- **Sin `release/*` ni `hotfix/*` formales** — no hay equipo esperando una rama de release; una corrección urgente es directamente una `fix/*` corta.
- **Todo por Pull Request**, aunque el desarrollo sea en solitario: el PR es el punto en el que el desarrollador y el agente de IA revisan el diff antes de integrar — no es una formalidad de equipo, es el mecanismo de control de calidad.
- **Commits en español, imperativo**: `agrega validación de solape en sesiones` (heredado de la convención ya definida en los documentos legacy).

## Alternativas descartadas

- **Trunk-based puro** (la propuesta original): sin `develop`, pierde la frontera entre integrado y desplegado, que sigue teniendo valor porque `agrocom-field` depende del contrato de API en producción.
- **GitFlow completo** (con `release/*` y `hotfix/*`): ceremonia de coordinación de equipo que no aporta valor con un solo desarrollador.

## Consecuencias

- `docs/gestion/plan_sprints.md` (TE-01, Sprint 1) crea `develop` desde `master` en ambos repositorios como parte del esqueleto inicial.
- El primer commit de cada repo (esqueleto Laravel/Flutter, CI) se integra en `develop` y se mergea a `master` recién cuando la suite está en verde y el entorno de staging responde.

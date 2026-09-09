# Flujo de trabajo — GitFlow simplificado

Ver el porqué en `docs/decisiones/0006-gitflow-simplificado.md`. Esta es la versión operativa.

## Ramas

- **`master`**: rama estable. Cada merge dispara el deploy continuo (staging → producción).
- **`develop`**: rama de integración. Toda `feature/*` se mergea acá primero, por PR.
- **`feature/<nombre-corto>`**: una por historia de usuario o tarea técnica del plan de sprints (`docs/gestion/plan_sprints.md`). Nace de `develop`, muere mergeada a `develop`. El nombre son 2–3 palabras de **la función del proyecto** que construye (`feature/sync-idempotente`, `feature/ordenes-offline`), nunca de la actividad ni del número de tarea.
- **`fix/<nombre-corto>`**: corrección puntual. Nace de `master`, se mergea a `master` y se reincorpora a `develop`.

No hay `release/*` ni `hotfix/*` — un solo desarrollador no necesita esa ceremonia.

## Flujo de una historia de usuario

```
git checkout develop
git pull
git checkout -b feature/nombre-de-la-hu
# implementar, con tests del criterio de aceptación primero
git push -u origin feature/nombre-de-la-hu
# abrir PR contra develop
```

## Antes de pushear

```
./bin/verify
```

Es la misma cascada que corre CI (Pint + Larastan + Pest en el contenedor con PHP
8.3, más la compilación de assets en el host) y devuelve 0 solo si todo pasa.
Detalle en `docs/gestion/automatizacion_desarrollo.md`.

El PR es el punto de revisión — propio y del agente de IA — antes de integrar, aunque el desarrollo sea en solitario. Se mergea cuando: la suite está en verde, el criterio de aceptación de la HU/TE tiene test, y (si toca sync, estados o dinero) se revisó línea por línea.

El merge no es manual: `.github/workflows/auto-merge.yml` integra el PR con
squash en cuanto el check `laravel-tests` queda verde. Un PR en **draft** queda
excluido a propósito (el job se saltea los borradores) — es la forma de decir
"esto espera revisión". Al marcarlo *Ready for review* el auto-merge corre.

`develop` se mergea a `master` cuando un conjunto de features está listo para desplegarse — no automáticamente en cada PR a develop.

## Un PR = una HU

La unidad de entrega es la historia de usuario o la tarea técnica completa, con
todos sus criterios de aceptación cubiertos — no el commit, y no el pedazo que
entró en una sesión de trabajo. Mientras se avance sobre el mismo objetivo se
suman commits a la misma rama y al mismo PR. Un PR nuevo se abre cuando cambia
el objetivo.

Vale abrir el PR a mitad de camino si sirve para ver el avance, siempre que sea
**en borrador**: el auto-merge se saltea los borradores, así que no se integra
hasta marcarlo *Ready for review*.

## Commits

En español, imperativo: `agrega validación de solape en sesiones`, `corrige cálculo de hectárea acumulada en relevo`.

**Sin trailer `Co-Authored-By`.** Decisión explícita del usuario; desde el
1/9/2026 está aplicada mecánicamente con `includeCoAuthoredBy: false` en
`.claude/settings.json`, para que no dependa de que cada sesión se acuerde.

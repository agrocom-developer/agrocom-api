# Flujo de trabajo — GitFlow simplificado

Ver el porqué en `docs/decisiones/0006-gitflow-simplificado.md`. Esta es la versión operativa.

## Ramas

- **`master`**: rama estable. Cada merge dispara el deploy continuo (staging → producción).
- **`develop`**: rama de integración. Toda `feature/*` se mergea acá primero, por PR.
- **`feature/<nombre-corto>`**: una por historia de usuario o tarea técnica del plan de sprints (`docs/gestion/plan_sprints.md`). Nace de `develop`, muere mergeada a `develop`.
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

`develop` se mergea a `master` cuando un conjunto de features está listo para desplegarse — no automáticamente en cada PR a develop.

## Commits

En español, imperativo: `agrega validación de solape en sesiones`, `corrige cálculo de hectárea acumulada en relevo`.

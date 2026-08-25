# Estado del proyecto (snapshot vivo)

**Última actualización: 2026-08-25.** Este documento no es la especificación (que es estable): es "dónde estamos parados hoy". Lo mantiene el agente `memoria-contexto` (`.claude/agents/memoria-contexto.md`) al cierre de cada sesión de trabajo relevante. Si algo acá contradice `docs/especificacion/` o un ADR, releer el documento oficial primero — este archivo puede quedar desactualizado si no se lo actualizó a tiempo.

## Fase actual

**Fundación de documentación y tooling — todavía sin código.** No existe `composer.json` ni esqueleto Laravel/Flutter. El plan de sprints (`docs/gestion/plan_sprints.md`) arranca formalmente en Sprint 1, TE-01: esqueleto de `agrocom-api` y `agrocom-field` con CI en verde.

## Qué ya existe

- Documentación oficial consolidada (`docs/especificacion/`, `docs/decisiones/` con 9 ADRs, `docs/negocio/`, `docs/gestion/`, `docs/glosario.md`) — reemplaza a `docs/legacy/` (7 documentos originales, congelados).
- GitFlow simplificado adoptado: `master` (estable, deploy continuo) + `develop` (integración) + `feature/*` + `fix/*`.
- CI/CD en GitHub Actions: `.github/workflows/ci.yml` (guard de `docs/legacy/` ya activo; job de tests Laravel condicionado a que exista `composer.json`) y `.github/workflows/auto-merge.yml` (arma auto-merge por PR, gate = CI en verde, sin exigir aprobación humana).
- Diez subagentes especializados en `.claude/agents/` (arquitectura, backend, frontend, design-ui, modelo-datos, estandares-programacion, distribucion, modulos-roles, negocio, memoria-contexto).

## Ramas activas (a la fecha de esta actualización)

- `master`: sin tocar desde el commit inicial.
- `develop`: al día con la consolidación de documentación (mergeada desde `feature/consolidacion-documentacion-oficial`).
- `feature/ci-cd-agentes-estandares`: en curso — CI/CD, ADRs 0008/0009, glosario, este archivo, y los subagentes.

## Próximo paso inmediato

Sprint 1 del plan de sprints: esqueleto Laravel (`composer.json`, migraciones del núcleo comercial, modelo `sec_*` ajustado) + esqueleto Flutter (`agrocom-field`) + spike de hardware en el RC real. Cuando eso arranque, el job `laravel-tests` de `ci.yml` deja de estar condicionado y corre de verdad.

## Pendiente de decidir o confirmar

- Respuestas del banco de preguntas por rol (`docs/gestion/banco_preguntas_por_rol.md`) — a medida que lleguen, el agente `negocio` las clasifica en CONFIRMADO/CORREGIDO/DESCUBIERTO y propone ajustes a la especificación.
- Supuestos abiertos de `docs/especificacion/especificacion_funcional_tecnica.md` §16 (tolerancia de solape, ±5% de mezcla, formato de firma).
- Verificar que la configuración remota de GitHub (auto-merge habilitado, branch protection en `develop`/`master`) haya quedado aplicada — ver el resumen de la sesión donde se intentó.

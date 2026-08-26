# Estado del proyecto (snapshot vivo)

**Última actualización: 2026-08-25 (tarde).** Este documento no es la especificación (que es estable): es "dónde estamos parados hoy". Lo mantiene el agente `memoria-contexto` (`.claude/agents/memoria-contexto.md`) al cierre de cada sesión de trabajo relevante. Si algo acá contradice `docs/especificacion/` o un ADR, releer el documento oficial primero — este archivo puede quedar desactualizado si no se lo actualizó a tiempo.

## Fase actual

**Fundación de documentación y tooling — todavía sin código.** No existe `composer.json` ni esqueleto Laravel/Flutter. El plan de sprints (`docs/gestion/plan_sprints.md`) arranca formalmente en Sprint 1, TE-01: esqueleto de `agrocom-api` y `agrocom-field` con CI en verde.

## Qué ya existe

- Documentación oficial consolidada (`docs/especificacion/`, `docs/decisiones/` con 9 ADRs, `docs/negocio/`, `docs/gestion/`, `docs/glosario.md`) — reemplaza a `docs/legacy/` (7 documentos originales, congelados).
- GitFlow simplificado adoptado: `master` (estable, deploy continuo) + `develop` (integración) + `feature/*` + `fix/*`.
- CI/CD en GitHub Actions: `.github/workflows/ci.yml` (guard de `docs/legacy/` ya activo; job de tests Laravel condicionado a que exista `composer.json`) y `.github/workflows/auto-merge.yml` (arma auto-merge por PR, gate = CI en verde, sin exigir aprobación humana).
- Diez subagentes especializados en `.claude/agents/` (arquitectura, backend, frontend, design-ui, modelo-datos, estandares-programacion, distribucion, modulos-roles, negocio, memoria-contexto).

## Ramas activas (a la fecha de esta actualización)

- `master`: sin tocar desde el commit inicial. Solo existe en el remoto de GitHub (todo lo demás es local).
- `develop`: al día — incluye la consolidación de documentación y el bloque de CI/CD, agentes y ADRs 0008/0009 (ambas features ya mergeadas, fast-forward). Todavía no está pusheada al remoto.
- `feature/entorno-docker-local`: en curso — Docker Compose para desarrollo local (`docker-compose.yml`, `Dockerfile`, ADR 0010), nacida de `develop`.

## Próximo paso inmediato

Sprint 1 del plan de sprints: esqueleto Laravel (`composer.json`, migraciones del núcleo comercial, modelo `sec_*` ajustado) + esqueleto Flutter (`agrocom-field`) + spike de hardware en el RC real. Cuando eso arranque, el job `laravel-tests` de `ci.yml` deja de estar condicionado y corre de verdad.

## Pendiente de decidir o confirmar

- **Prefijo de tabla por módulo**: en discusión. El usuario pidió separar "Personal" (personas operativas) de "Identidad" como módulo propio, y un módulo de "Recursos"/"Inventario" para los activos físicos (drones, baterías, vehículos, bases, generadores) — falta cerrar el mapeo final módulo↔prefijo↔tablas antes de reescribir `docs/especificacion/especificacion_funcional_tecnica.md` sección 4 y crear el ADR correspondiente.
- **Configuración remota de GitHub**: la cuenta de `gh` usada en esta sesión (`Angello-27`) no tiene rol admin sobre `agrocom-developer/agrocom-api` (404 al intentar `allow_auto_merge`). El usuario tiene la cuenta admin y confirmó ver el checkbox "Allow auto-merge" deshabilitado en Settings → Pull Requests — probablemente por una política a nivel de la organización `agrocom-developer` (member privileges). Pendiente que el usuario revise el link "Why is this option disabled?" de GitHub y, si aplica, lo habilite en `github.com/organizations/agrocom-developer/settings/member_privileges`.
- **Push al remoto**: confirmado explícitamente que todavía NO se debe pushear `develop` ni las features — todo el trabajo vive solo en la copia local hasta que el usuario lo pida.
- Respuestas del banco de preguntas por rol (`docs/gestion/banco_preguntas_por_rol.md`) — a medida que lleguen, el agente `negocio` las clasifica en CONFIRMADO/CORREGIDO/DESCUBIERTO y propone ajustes a la especificación.
- Supuestos abiertos de `docs/especificacion/especificacion_funcional_tecnica.md` §16 (tolerancia de solape, ±5% de mezcla, formato de firma).
- Gaps señalados y aún no resueltos: no existe un documento oficial de riesgos (el legacy tenía uno en `enfoque_desarrollo_sistema_fumigacion.md` §6 que nunca migró); no hay diagramas ER/arquitectura (Mermaid) en la documentación oficial.

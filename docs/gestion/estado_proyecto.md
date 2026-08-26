# Estado y continuidad del proyecto

**Última actualización: 2026-08-25 (noche).** Este documento no es la especificación (que es estable) ni el plan de sprints (que es la estrategia global con HU y fases): es la bitácora de continuidad entre iteraciones — qué se avanzó, qué falta, y qué leer primero para no releer todo `docs/` de cero en cada sesión nueva. Lo mantiene el agente `memoria-contexto` (`.claude/agents/memoria-contexto.md`) al cierre de cada sesión de trabajo relevante.

## Cómo usar este documento

Al empezar una iteración nueva: leé este documento completo primero (es corto). Después, andá **solo** a los documentos que tu tarea puntual necesita — la tabla de abajo te dice cuáles, para no cargar contexto que no aplica (p. ej. no hace falta leer el ADR del panel web si vas a escribir una migración).

## Mapa de lectura mínima por tipo de tarea

| Si vas a trabajar en... | Leé primero | No hace falta leer |
|---|---|---|
| Migraciones / modelo de datos | `docs/especificacion/especificacion_funcional_tecnica.md` §4, ADR 0001, ADR 0007 | ADR 0002 (panel), ADR 0005 (Flutter) |
| Panel web / componentes Livewire | ADR 0002, ADR 0008 | ADR 0001, ADR 0005, negocio |
| Seguridad / permisos / roles | ADR 0004 | ADR 0002 en detalle visual |
| App Flutter (`agrocom-field`) | ADR 0005, especificación §2 (protocolo de sync) | ADR 0002, ADR 0004 en detalle |
| CI/CD / despliegue / entornos | `.github/workflows/`, `docs/gestion/entornos.md`, ADR 0006, ADR 0010 | especificación funcional completa |
| Negocio / respuestas de campo | `docs/negocio/`, `docs/gestion/banco_preguntas_por_rol.md` | ADRs técnicos |
| Nueva decisión de arquitectura | `CLAUDE.md` + índice de `docs/decisiones/` (no cada ADR entero, solo los que tocan el tema) | — |
| Retomar el hilo general | Este documento, completo | todo lo demás, hasta que haga falta |

## Fase actual

**Fundación de documentación y tooling — todavía sin código de aplicación.** No existe `composer.json` ni esqueleto Laravel/Flutter. El plan de sprints (`docs/gestion/plan_sprints.md`) arranca formalmente en Sprint 1, TE-01.

## Avanzado hasta ahora

- **Documentación oficial** consolidada: `docs/especificacion/`, `docs/decisiones/` (10 ADRs), `docs/negocio/`, `docs/gestion/`, `docs/glosario.md` — reemplaza a `docs/legacy/` (7 documentos originales, congelados, nunca se editan).
- **GitFlow simplificado** adoptado y en uso real: `master` + `develop` + `feature/*` + `fix/*`, todo por PR.
- **CI/CD funcionando de punta a punta y probado en vivo**: `.github/workflows/ci.yml` (`docs-legacy-guard` activo; `laravel-tests` condicionado a que exista `composer.json`) + `.github/workflows/auto-merge.yml` (mergea solo cuando los checks requeridos están en verde, sin depender del auto-merge nativo de GitHub — no disponible en repos privados de plan Free). Validado con el PR #1: se auto-fusionó a `develop` sin intervención manual.
- **Entorno de desarrollo local con Docker** (ADR 0010): `docker-compose.yml` + `Dockerfile` + `.dockerignore`, reemplaza MAMP.
- **Doce subagentes especializados** en `.claude/agents/`: los diez por capa (arquitectura, backend, frontend, design-ui, modelo-datos, estandares-programacion, distribucion, modulos-roles, negocio, memoria-contexto) más `orquestador` y `validador`. Cada uno con el modelo asignado según si su trabajo es de ejecución/instrucciones (modelo económico) o de juicio/coordinación (modelo capaz) — ver `.claude/agents/README.md`.
- **Convención de commits sin coautoría de IA**: desde el commit `084b732` en adelante, los mensajes de commit no llevan el trailer `Co-Authored-By: Claude...`. Los commits anteriores a esa fecha sí lo llevan y quedan así — decisión explícita del usuario de no reescribir historia ya pusheada.

## Ramas y remoto (estado real, no solo local)

- `master`: en GitHub, sin cambios desde el commit inicial.
- `develop`: en GitHub, al día — incluye todo lo anterior más el merge del PR #1.
- Local: parada actual en `feature/continuidad-iteraciones-y-modelos-agente`, nacida de `develop`.
- `gh` autenticado como `Angello-27` (permisos `push`/`pull`/`triage`, sin `admin`) — suficiente para todo el flujo de PRs y Actions; **no** suficiente para branch protection ni settings del repo.

## Próximo paso inmediato

Sprint 1 del plan de sprints: esqueleto Laravel (`composer.json`, migraciones del núcleo comercial) + esqueleto Flutter (`agrocom-field`) + spike de hardware en el RC real. Ahí el job `laravel-tests` deja de estar condicionado.

## Decisiones diferidas explícitamente (no reabrir sin que el usuario lo pida)

- **Mapeo de prefijos de tabla por módulo** (ej. `per_` para un módulo Personal nuevo, `rec_`/`inv_` para activos físicos): el usuario lo dejó como algo a definir más adelante, es "una forma de identificar tablas", no bloquea nada ahora. No tocar `docs/especificacion/` sección 4 ni crear el ADR hasta que se retome explícitamente.
- **Procesamiento del banco de preguntas por rol**: las respuestas ya llegaron en CSV, pero clasificarlas (CONFIRMADO/CORREGIDO/DESCUBIERTO) y ajustar la especificación es una iteración dedicada aparte, de lógica de negocio — no mezclarla con trabajo de infraestructura/tooling.
- **Gesto manual de aprobación para el merge** (comentario `/merge` o etiqueta, además del gate de CI): evaluado y descartado por el usuario — el gate de solo CI en verde ya cumple lo que necesita.
- **Branch protection formal en GitHub** (status checks requeridos vía Settings → Branches): sigue sin aplicarse — requiere permisos de admin que la cuenta `gh` de esta sesión no tiene. No es bloqueante: el auto-merge ya funciona sin ella.

## Otros gaps señalados, aún sin resolver

- No existe un documento oficial de riesgos (el legacy tenía uno en `enfoque_desarrollo_sistema_fumigacion.md` §6 que nunca migró a `docs/gestion/`).
- No hay diagramas ER/arquitectura (Mermaid) en la documentación oficial — solo texto y tablas.

# Estado y continuidad del proyecto

**Última actualización: 2026-08-26.** Este documento no es la especificación (que es estable) ni el plan de sprints (que es la estrategia global con HU y fases): es la bitácora de continuidad entre iteraciones — qué se avanzó, qué falta, y qué leer primero para no releer todo `docs/` de cero en cada sesión nueva. Lo mantiene el agente `memoria-contexto` (`.claude/agents/memoria-contexto.md`) al cierre de cada sesión de trabajo relevante.

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
| Negocio / respuestas de campo | `docs/gestion/respuestas_campo/analisis_clasificacion.md`, `docs/negocio/politicas/` (por rol), `docs/negocio/` | ADRs técnicos |
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
- **Respuestas de campo procesadas (26/8/2026)**: los 5 CSV del banco de preguntas clasificados en CONFIRMADO/CORREGIDO/DESCUBIERTO con matriz de límites (`docs/gestion/respuestas_campo/analisis_clasificacion.md`), 7 documentos de políticas por rol (`docs/negocio/politicas/` — incluye dueño y cliente, derivados), flujo base y excepciones, automatización/sistematización, alcance y objetivos, requerimientos de sistema (RF/RNF) e insumos para el modelo de datos. Hallazgos mayores: la mezcla la prepara hoy el cliente (CR-01), las pausas atribuibles nunca se registran (DS-01), el actor "encargado de la propiedad" (DS-02), límites de clima como parámetros por contrato, y T30 en la flota real. Supuestos §16 cerrados: ±5% aceptado, firma en cualquier formato, vuelo nocturno confirmado.

## Ramas y remoto (estado real, no solo local)

- `master`: en GitHub, sin cambios desde el commit inicial.
- `develop`: en GitHub, al día — incluye los merges de los PR #1 a #3.
- Local: parada actual en `feature/analisis-respuestas-campo`, nacida de `develop` (procesamiento de las respuestas de campo).
- `gh` autenticado como `Angello-27` (permisos `push`/`pull`/`triage`, sin `admin`) — suficiente para todo el flujo de PRs y Actions; **no** suficiente para branch protection ni settings del repo.

## Próximo paso inmediato

1. **Capturas del RC** (las consigue el usuario) → consolidar el modelo de datos en la especificación §4 con `docs/especificacion/insumos_modelo_datos.md`.
2. **Reunión de cierre** con la agenda de `analisis_clasificacion.md` §7 (mezcla, clima, acta, montos, lotes feos, EPP, boleo) → actualizar la especificación (§3, §4, §5, §7, §9, §10, §16) en una iteración dedicada.
3. Después: Sprint 1 del plan de sprints — esqueleto Laravel + Flutter + spike de hardware en el RC real. Ahí el job `laravel-tests` deja de estar condicionado.

## Decisiones diferidas explícitamente (no reabrir sin que el usuario lo pida)

- **Mapeo de prefijos de tabla por módulo** (ej. `per_` para un módulo Personal nuevo, `rec_`/`inv_` para activos físicos): el usuario lo dejó como algo a definir más adelante, es "una forma de identificar tablas", no bloquea nada ahora. No tocar `docs/especificacion/` sección 4 ni crear el ADR hasta que se retome explícitamente.
- **Actualización de la especificación con lo corregido/descubierto**: los hallazgos están clasificados y documentados, pero la especificación NO se toca hasta después de la reunión de cierre y de las capturas del RC — ver `analisis_clasificacion.md` §8.
- **Gesto manual de aprobación para el merge** (comentario `/merge` o etiqueta, además del gate de CI): evaluado y descartado por el usuario — el gate de solo CI en verde ya cumple lo que necesita.
- **Branch protection formal en GitHub** (status checks requeridos vía Settings → Branches): sigue sin aplicarse — requiere permisos de admin que la cuenta `gh` de esta sesión no tiene. No es bloqueante: el auto-merge ya funciona sin ella.

## Otros gaps señalados, aún sin resolver

- No existe un documento oficial de riesgos (el legacy tenía uno en `enfoque_desarrollo_sistema_fumigacion.md` §6 que nunca migró a `docs/gestion/`).
- No hay diagrama ER ni de arquitectura en la documentación oficial (el primer Mermaid es el flujo operativo en `docs/negocio/flujo_base_y_excepciones.md`; ER y arquitectura siguen pendientes).

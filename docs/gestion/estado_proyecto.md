# Estado y continuidad del proyecto

**Última actualización: 2026-08-27.** Este documento no es la especificación (que es estable) ni el plan de sprints (que es la estrategia global con HU y fases): es la bitácora de continuidad entre iteraciones — qué se avanzó, qué falta, y qué leer primero para no releer todo `docs/` de cero en cada sesión nueva. Lo mantiene el agente `memoria-contexto` (`.claude/agents/memoria-contexto.md`) al cierre de cada sesión de trabajo relevante.

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

**Sprint 1 en curso (27/8/2026).** TE-01 parcial: el esqueleto Laravel de `agrocom-api` existe y la suite (Pint + Larastan + Pest) corre en CI; de TE-01 faltan el repo `agrocom-field` (Flutter) y el entorno staging (diferido — ADR 0010, el servidor no está definido). TE-03 (migraciones del núcleo comercial: clientes, contratos, campos, lotes, órdenes) **hecho** — PR #9, commit `02c6927`. TE-02 (spike de hardware en el RC real) sigue pendiente. HU-01 (usuarios multi-rol) tiene su backend **hecho** en `feature/usuarios-multirol` (sin PR todavía): módulos `Personal`/`Seguridad`, caso de uso `AsignarRolesUsuario`, seeder de catálogo, `App\Models\User` reemplazado por `SecUser` — ver punto 4 de "Próximo paso inmediato". Falta el panel (HU-02) y los endpoints/tokens (HU-03).

## Avanzado hasta ahora

- **Documentación oficial** consolidada: `docs/especificacion/`, `docs/decisiones/` (10 ADRs), `docs/negocio/`, `docs/gestion/`, `docs/glosario.md`. `docs/legacy/` (los 7 documentos originales) se eliminó el 26/8/2026 — queda en el historial de git (`git log --oneline -- docs/legacy/`).
- **GitFlow simplificado** adoptado y en uso real: `master` + `develop` + `feature/*` + `fix/*`, todo por PR.
- **CI/CD funcionando de punta a punta y probado en vivo**: `.github/workflows/ci.yml` (`laravel-tests`: Pint + Larastan + Pest sobre PHP 8.3 con Postgres 16 de servicio; `docs-legacy-guard` retirado junto con `docs/legacy/`) + `.github/workflows/auto-merge.yml` (mergea solo cuando `laravel-tests` está en verde, sin depender del auto-merge nativo de GitHub — no disponible en repos privados de plan Free). Validado con el PR #1: se auto-fusionó a `develop` sin intervención manual.
- **Esqueleto Laravel (26/8/2026)**: Laravel 13 sobre PHP 8.3 (`composer.lock` resuelto con `config.platform.php = 8.3` para cuadrar con el Dockerfile y CI), Pest 4 + Pint + Larastan nivel 6 (`phpstan.neon`), `.env.example` completo con las decisiones vigentes (PostgreSQL, colas/sesión/caché en BD, disco `r2` en `config/filesystems.php`, locale `es`), descripciones de tests en español. Suite verificada dentro del contenedor Docker y migraciones corridas contra el Postgres 16 del compose.
- **Entorno de desarrollo local con Docker** (ADR 0010): `docker-compose.yml` + `Dockerfile` + `.dockerignore`, reemplaza MAMP.
- **Doce subagentes especializados** en `.claude/agents/`: los diez por capa (arquitectura, backend, frontend, design-ui, modelo-datos, estandares-programacion, distribucion, modulos-roles, negocio, memoria-contexto) más `orquestador` y `validador`. Cada uno con el modelo asignado según si su trabajo es de ejecución/instrucciones (modelo económico) o de juicio/coordinación (modelo capaz) — ver `.claude/agents/README.md`.
- **Convención de commits sin coautoría de IA**: desde el commit `084b732` en adelante, los mensajes de commit no llevan el trailer `Co-Authored-By: Claude...`. Los commits anteriores a esa fecha sí lo llevan y quedan así — decisión explícita del usuario de no reescribir historia ya pusheada.
- **Respuestas de campo procesadas (26/8/2026)**: los 5 CSV del banco de preguntas clasificados en CONFIRMADO/CORREGIDO/DESCUBIERTO con matriz de límites (`docs/gestion/respuestas_campo/analisis_clasificacion.md`), 7 documentos de políticas por rol (`docs/negocio/politicas/` — incluye dueño y cliente, derivados), flujo base y excepciones, automatización/sistematización, alcance y objetivos, requerimientos de sistema (RF/RNF) e insumos para el modelo de datos. Hallazgos mayores: la mezcla la prepara hoy el cliente (CR-01), las pausas atribuibles nunca se registran (DS-01), el actor "encargado de la propiedad" (DS-02), límites de clima como parámetros por contrato, y T30 en la flota real. Supuestos §16 cerrados: ±5% aceptado, firma en cualquier formato, vuelo nocturno confirmado.

## Ramas y remoto (estado real, no solo local)

- `master`: en GitHub, sin cambios desde el commit inicial.
- `develop`: en GitHub, al día — incluye los merges de los PR #1 a #9 (el #8 corrige `.env.example` para Docker, el #9 es TE-03: núcleo comercial).
- Local: parada actual en `feature/usuarios-multirol`, nacida de `develop` (HU-01: usuarios con uno o más roles y login único). Convención de nombres de rama cortos (2–3 palabras).
- `gh` autenticado como `Angello-27` (permisos `push`/`pull`/`triage`, sin `admin`) — suficiente para todo el flujo de PRs y Actions; **no** suficiente para branch protection ni settings del repo.

## Próximo paso inmediato

1. ~~Capturas del RC~~ **hecho (26/8/2026)**: 21 capturas analizadas en `docs/especificacion/analisis_capturas_rc.md` — campos DJI exactos, validación aritmética de áreas, columnas nuevas para el cierre de sesión.
2. **Reunión de cierre** con la agenda de `analisis_clasificacion.md` §7 (mezcla, clima, acta, montos, lotes feos, EPP, boleo) más las 5 preguntas de semántica del RC (`analisis_capturas_rc.md` §5) → actualizar la especificación (§3, §4, §5, §7, §9, §10, §16) en una iteración dedicada.
3. ~~Esqueleto Laravel~~ **hecho (26/8/2026)**: Laravel 13 + Pest/Pint/Larastan, CI real en verde. ~~TE-03~~ **hecho (26/8/2026, PR #9)**: migraciones del núcleo comercial (clientes, contratos, campos, lotes, órdenes), modelos y API de órdenes con Swagger. Siguen de Sprint 1: esqueleto Flutter (`agrocom-field`) y spike de hardware en el RC real (TE-02).
4. **HU-01 (27/8/2026, backend hecho, rama `feature/usuarios-multirol`, sin PR todavía)**: ADR 0011 extendido — `personas`/`bases` viven en módulo nuevo `Personal` (`per_personas`, `per_bases`), `sec_*` vive en módulo nuevo `Seguridad` (reconcilia el nombre "Identidad" de la lista original de ADR 0003, ya en desuso). `sec_user.persona_id` referencia `per_personas.id` por FK de base de datos + atributo entero plano, sin `belongsTo` Eloquent cross-módulo. Migraciones + modelos Eloquent (`PerBase`, `PerPersona`, `SecRole`, `SecPermission`, `SecUser`, `SecUserRole`, `SecRolePermission`, con `SecUsuarioInterno`/`SecUsuarioCliente` para el scoping de guard) + caso de uso `AsignarRolesUsuario` (alta y edición con la misma guarda de permisos, traduce violaciones de unicidad a excepción de dominio) + seeder `Database\Seeders\Catalogo\SeguridadSeeder` (5 roles, 6 permisos) + suite Pest dedicada, todo en verde (Pint + Larastan nivel 6 + Pest). `App\Models\User`/`users`/`password_reset_tokens` se eliminaron (coexistían con `sec_user`, dos sistemas de auth en paralelo); `config/auth.php` define los guards `interno`/`cliente` sobre `SecUser`. Los pivotes (`SecUserRole`/`SecRolePermission`) usan el trait `AsPivot` sobre `ModeloDominio` en vez de la clase `Pivot` de Laravel (para conservar soft delete + auditoría) y se navegan por `hasMany`, no por `belongsToMany()->using()` — el stub de tipos de Larastan para `using()` exige un pivote subtipo de `Pivot`, incompatible con el requisito de auditoría. Falta: panel (HU-02) y endpoints/tokens (HU-03). **Pendiente para más adelante, no ahora**: agregar `tarifa_ha` y `sueldo_mensual` a `per_personas` (columnas de dinero, atadas a la lógica de devengos/planilla que todavía no tiene ADR de máquina de estados — no implementar hasta ese diseño). Detalle completo en `docs/decisiones/0011-convencion-prefijos-tabla.md`, sección "Extensión (26/8/2026)".

## Decisiones diferidas explícitamente (no reabrir sin que el usuario lo pida)

- ~~**Mapeo de prefijos de tabla por módulo**~~ **resuelto (26/8/2026)**: ADR 0011 (extendido) ya define `per_` para `Personal` (`per_personas`, `per_bases`) y confirma `sec_` para `Seguridad`. Sigue diferido tocar `docs/especificacion/` sección 4 — eso espera la reunión de cierre (punto siguiente).
- **Actualización de la especificación con lo corregido/descubierto**: los hallazgos están clasificados y documentados, pero la especificación NO se toca hasta después de la reunión de cierre y de las capturas del RC — ver `analisis_clasificacion.md` §8.
- **Gesto manual de aprobación para el merge** (comentario `/merge` o etiqueta, además del gate de CI): evaluado y descartado por el usuario — el gate de solo CI en verde ya cumple lo que necesita.
- **Branch protection formal en GitHub** (status checks requeridos vía Settings → Branches): sigue sin aplicarse — requiere permisos de admin que la cuenta `gh` de esta sesión no tiene. No es bloqueante: el auto-merge ya funciona sin ella.

## Otros gaps señalados, aún sin resolver

- No existe un documento oficial de riesgos (el legacy tenía uno en `enfoque_desarrollo_sistema_fumigacion.md` §6 que nunca migró a `docs/gestion/`).
- No hay diagrama ER ni de arquitectura en la documentación oficial (el primer Mermaid es el flujo operativo en `docs/negocio/flujo_base_y_excepciones.md`; ER y arquitectura siguen pendientes).
- **FK real de `created_by`/`updated_by` a `sec_user.id`** (HU-01, diseño `modulos-roles` §6): ahora que `sec_user` existe, las columnas `created_by`/`updated_by` de `com_clientes`, `com_contratos` y el resto de tablas de TE-03 (y de `per_bases`/`per_personas`/`sec_*` de HU-01 mismo) siguen siendo `unsignedBigInteger` sin FK. Retrofit deliberadamente fuera de alcance de HU-01 (toca migraciones de otro módulo ya mergeado) — resolver en un solo pase futuro que agregue la FK a todas las tablas de una vez, no módulo por módulo.

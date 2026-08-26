# agrocom-api

Backend y panel administrativo del Sistema de Gestión de Operaciones de Fumigación de **Agrocom SRL**: API REST para las apps de campo, panel web multi-rol (jefe de campo, encargado de operaciones, dueño) y portal de solo lectura para el cliente (agrónomo/dueño del campo).

Repositorio hermano: **[`agrocom-field`](https://github.com/agrocom-developer/agrocom-field)** — app Flutter para el remote control (RC) de los drones DJI (piloto) y celular Android (auxiliar). El contrato entre ambos vive en `docs/api/` de este repo.

## Stack

- **Backend**: PHP 8.3 + Laravel 12 + PostgreSQL 16
- **Panel web**: AdminLTE + Blade + Livewire, componentes propios en Atomic Design (Bootstrap + Material Design), theming configurable por usuario
- **Autenticación**: Sanctum (apps, token por dispositivo) + sesión web (paneles), permisos vía modelo `sec_*` (permiso abstracto + multi-rol)
- **Arquitectura**: monolito modular, Clean Architecture pragmática por módulo de dominio

Detalle completo y el porqué de cada decisión en `docs/`.

## Documentación

- **`docs/especificacion/especificacion_funcional_tecnica.md`** — qué hace el sistema y cómo se modela (fuente de verdad funcional/técnica).
- **`docs/decisiones/`** — ADRs: por qué se eligió cada pieza del stack (9 decisiones, de la base de datos al modelo de seguridad).
- **`docs/glosario.md`** — diccionario de términos de negocio y técnicos del proyecto.
- **`docs/negocio/ventana_al_negocio.md`** — el negocio de punta a punta.
- **`docs/gestion/`** — plan de sprints, captura de procesos de campo, entornos, y el estado vivo del proyecto (`estado_proyecto.md`).
- **`docs/legacy/`** — documentos históricos de la fase de propuesta, congelados (ver `docs/README.md` para el mapa de qué reemplaza a qué).
- **`CLAUDE.md`** — invariantes no negociables para cualquier agente de IA que implemente en este repo.
- **`CONTRIBUTING.md`** — flujo de GitFlow simplificado.

## CI/CD

GitHub Actions (`.github/workflows/`):

- **`ci.yml`** — `docs-legacy-guard` (siempre activo: ningún PR puede modificar `docs/legacy/`) + `laravel-tests` (Pest, Larastan, Pint; se activa solo cuando exista `composer.json`).
- **`auto-merge.yml`** — arma el auto-merge de cada PR contra `develop`/`master`. Con un solo desarrollador, el gate es **CI en verde**, sin exigir aprobación humana (ver `docs/decisiones/0006-gitflow-simplificado.md`).

## Entornos

Ver `docs/gestion/entornos.md` y `.env.example`. Ningún `.env` real se versiona; cada entorno (local, CI, staging, producción) tiene el suyo.

### Desarrollo local con Docker

Ver `docs/decisiones/0010-entorno-local-docker-compose.md`. Reemplaza MAMP + una base de datos instalada aparte:

```
docker compose up -d
```

Levanta PHP 8.3 (`app`, a la espera hasta que exista el esqueleto Laravel), PostgreSQL 16 (`db`, puerto 5432) y Mailpit (`mail`, UI en `localhost:8025`).

## Agentes especializados

Subagentes de Claude Code en `.claude/agents/`, uno por capa del proyecto — cada uno ancla su trabajo a los documentos oficiales que le corresponden:

| Agente | Cuándo invocarlo |
|---|---|
| `arquitectura` | Dónde encaja código nuevo, o cuando haga falta un ADR |
| `backend` | Casos de uso, modelos Eloquent, endpoints de API |
| `frontend` | Pantallas del panel, componentes Livewire |
| `design-ui` | Tokens, theming, catálogo de componentes Atomic Design |
| `modelo-datos` | Migraciones, índices, integridad del esquema |
| `estandares-programacion` | Revisión de estilo, naming, tests, antes de un PR |
| `distribucion` | CI/CD, despliegue, versionado con `agrocom-field` |
| `modulos-roles` | Permisos `sec_*`, roles múltiples, policies |
| `negocio` | Reglas de negocio, respuestas del banco de preguntas por rol |
| `memoria-contexto` | Recuperar o actualizar el estado del proyecto al abrir/cerrar una sesión |

## Desarrollo

Un solo desarrollador con agentes de IA como implementadores principales. Ver `CONTRIBUTING.md` para el flujo de ramas y `docs/gestion/plan_sprints.md` para el plan de sprints vigente.

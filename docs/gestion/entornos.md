# Entornos y gestión de configuración

**Agrocom SRL · Documento oficial vigente**

## Entornos

| Entorno | Dónde vive | Propósito |
|---|---|---|
| **local** | Máquina del desarrollador | Desarrollo día a día, `.env` propio con credenciales de prueba |
| **CI** | GitHub Actions (`.github/workflows/ci.yml`) | Tests y análisis estático sobre un Postgres efímero del runner; sin secretos reales — ver variables inline del job `laravel-tests` |
| **producción beta** | Contenedor LXC 119 en Proxmox (`docs/decisiones/0017-despliegue-proxmox-lxc-self-hosted-runner.md`) | Primer ambiente productivo; acceso por IP pública + puerto expuesto, sin dominio ni HTTPS todavía. Runner self-hosted de GitHub Actions dentro del contenedor dispara el deploy al pushear un tag `v*.*.*` |

## Regla única

**Ningún `.env` real se versiona.** `.env.example` (raíz del repo) es la plantilla — variables agrupadas por bloque, sin valores. Cada entorno tiene su propio `.env` fuera del repo:

- **local**: copia de `.env.example` con credenciales de desarrollo, en el filesystem del desarrollador.
- **producción beta**: `.env` vive en el servidor (contenedor 119, fuera del repo), gestionado manualmente o vía el pipeline de deploy (`.github/workflows/deploy.yml`) — nunca se transmite por PR ni se imprime en logs de CI.
- **CI**: no usa `.env` — las variables necesarias para correr Pest se pasan como `env:` inline del job (ver `.github/workflows/ci.yml`), con un Postgres de servicio efímero (`postgres:16`) que se destruye al terminar el job.

## Qué cambia entre entornos

- `APP_ENV` / `APP_DEBUG`: `local`/`true` en desarrollo, `false` en producción beta (no hay staging hoy — ver "Nota" abajo).
- `DB_*`: instancia PostgreSQL propia por entorno — cada ambiente (local, CI, beta) su propia base.
- `FILESYSTEM_DISK` (R2): buckets separados por entorno (`agrocom-evidencias-local`, `agrocom-evidencias-beta`), para evitar que pruebas locales mezclen evidencias reales.
- `SENTRY_LARAVEL_DSN`: proyecto Sentry específico para beta, si existe monitoreo; vacío en local.
- `APP_URL` / `SANCTUM_STATEFUL_DOMAINS`: IP pública del contenedor 119 + puerto en producción beta (ej. `http://<IP>:80`), `localhost:8000` en desarrollo.

**Nota:** Hoy existe un único ambiente productivo (contenedor 119 en Proxmox, "producción beta"). Un ambiente de staging separado es una decisión futura — ver ADR 0017, sección "Decisión" §3.

## Secretos

Ninguna credencial (DB, R2, Sentry) se pega en un PR, un commit, o un comentario. Los secretos de CI (si el workflow de Laravel llegara a necesitar alguno real, hoy no lo necesita) van en **GitHub Actions secrets** del repositorio, nunca en el YAML del workflow.

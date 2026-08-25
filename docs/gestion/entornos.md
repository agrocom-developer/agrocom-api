# Entornos y gestión de configuración

**Agrocom SRL · Documento oficial vigente**

## Entornos

| Entorno | Dónde vive | Propósito |
|---|---|---|
| **local** | Máquina del desarrollador | Desarrollo día a día, `.env` propio con credenciales de prueba |
| **CI** | GitHub Actions (`.github/workflows/ci.yml`) | Tests y análisis estático sobre un Postgres efímero del runner; sin secretos reales — ver variables inline del job `laravel-tests` |
| **staging** | VPS, segundo sitio (`docs/legacy/definicion_tecnica_repos_modulos_stack.md`, sección 4) | Probar APK candidatos y cambios de backend antes de producción |
| **producción** | VPS | Campaña real |

## Regla única

**Ningún `.env` real se versiona.** `.env.example` (raíz del repo) es la plantilla — variables agrupadas por bloque, sin valores. Cada entorno tiene su propio `.env` fuera del repo:

- **local**: copia de `.env.example` con credenciales de desarrollo, en el filesystem del desarrollador.
- **staging/producción**: `.env` vive en el servidor (fuera del repo), gestionado manualmente o vía el pipeline de deploy — nunca se transmite por PR ni se imprime en logs de CI.
- **CI**: no usa `.env` — las variables necesarias para correr Pest se pasan como `env:` inline del job (ver `.github/workflows/ci.yml`), con un Postgres de servicio efímero (`postgres:16`) que se destruye al terminar el job.

## Qué cambia entre entornos

- `APP_ENV` / `APP_DEBUG`: `local`/`true` en desarrollo, `false` en staging y producción.
- `DB_*`: instancia PostgreSQL propia por entorno — nunca se comparte la base de producción con staging.
- `FILESYSTEM_DISK` (R2): buckets separados por entorno (`agrocom-evidencias-staging` vs. `agrocom-evidencias-produccion`), para que una prueba en staging no mezcle evidencias reales de campaña.
- `SENTRY_LARAVEL_DSN`: proyectos Sentry separados (o el mismo proyecto con `environment` etiquetado) para no confundir errores de staging con errores reales.
- `APP_URL` / `SANCTUM_STATEFUL_DOMAINS`: dominio real en staging/producción, `localhost` en desarrollo.

## Secretos

Ninguna credencial (DB, R2, Sentry) se pega en un PR, un commit, o un comentario. Los secretos de CI (si el workflow de Laravel llegara a necesitar alguno real, hoy no lo necesita) van en **GitHub Actions secrets** del repositorio, nunca en el YAML del workflow.

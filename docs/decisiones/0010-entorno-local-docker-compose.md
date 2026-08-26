# ADR 0010 — Entorno de desarrollo local con Docker Compose

**Estado:** Aceptada. **Alcance: solo entorno local** — la decisión de cómo se despliega en el servidor (Proxmox u otro) queda abierta para cuando ese entorno esté más definido; no se reabre este ADR para eso, se crea uno nuevo cuando corresponda.

## Contexto

El desarrollo local usaba MAMP para PHP y no tenía PostgreSQL instalado (la base de datos decidida en ADR 0001) — el desarrollador terminaba mezclando `php artisan serve` suelto con una base de datos que no coincidía con la de producción. Docker ya está instalado en la máquina de desarrollo (macOS), así que el costo de adopción es bajo.

## Decisión

`docker-compose.yml` con tres servicios:

- **`app`**: PHP 8.3 (`Dockerfile` propio, extensiones `pdo_pgsql`/`zip`, Composer). Mientras no exista el esqueleto Laravel (Sprint 1, TE-01), el contenedor queda a la espera sin fallar — se activa solo cuando aparezca `artisan`.
- **`db`**: PostgreSQL 16 — la misma versión mayor que se usará en staging/producción (ADR 0001), con healthcheck antes de que `app` dependa de él.
- **`mail`**: Mailpit (sucesor mantenido de Mailhog) para capturar correo saliente en desarrollo sin enviarlo de verdad.

`docker compose up -d` reemplaza a MAMP para este proyecto. El volumen `agrocom-db-data` persiste la base entre reinicios.

## Alternativas descartadas

- **Seguir con MAMP + Postgres.app instalado aparte**: exige mantener dos herramientas de infraestructura distintas en la misma máquina para un solo proyecto, y no reproduce el entorno de staging/producción.
- **Definir ya la imagen de producción para Proxmox**: prematuro — el servidor todavía se está configurando; mezclar esa decisión acá arriesga tener que deshacer trabajo. Se aborda en un ADR aparte cuando el entorno de servidor esté definido.

## Consecuencias

- `.env` local apunta a `DB_HOST=db` (nombre del servicio) en vez de `127.0.0.1`, cuando se trabaja con `docker compose exec app ...` en vez de correr PHP directo en el host. Si se prefiere seguir corriendo PHP fuera del contenedor (`php artisan serve` en el host) y solo usar Docker para `db`/`mail`, `DB_HOST=127.0.0.1` sigue funcionando porque el puerto 5432 está expuesto al host.
- CI (`.github/workflows/ci.yml`) no usa este `docker-compose.yml` — levanta su propio servicio `postgres:16` nativo de GitHub Actions, más simple para ese contexto.

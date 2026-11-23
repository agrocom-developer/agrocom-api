# CI/CD

## Permisos de los workflows

`ci.yml` y `deploy.yml` declaran `permissions: contents: read` a nivel de workflow. `auto-merge.yml` declara el conjunto mínimo que necesita (`pull-requests`, `contents`, `checks`, `actions`). Ningún workflow usa `pull_request_target`, de modo que el código de un fork nunca corre con permisos de escritura ni con secretos.

El `actions/checkout` de CI y despliegue usa `persist-credentials: false`: el token no queda guardado en el `.git` del workspace.

## Acciones fijadas por SHA

Toda acción externa se referencia por el SHA del commit de una versión concreta, con la versión en un comentario:

```yaml
- uses: actions/checkout@fbc6f3992d24b796d5a048ff273f7fcc4a7b6c09 # v5.1.0
```

Un tag móvil puede apuntar mañana a otro código; un SHA no. Dependabot (ecosistema `github-actions`) propone las actualizaciones y mantiene el comentario de versión.

## Job `seguridad`

Corre en cada PR, en paralelo a `laravel-tests`:

- `composer audit --locked`: falla si hay un advisory de severidad alta o crítica; los de severidad menor se imprimen en el log.
- `npm audit --omit=dev --audit-level=high`: solo dependencias de producción.

No es un check requerido del auto-merge (que espera únicamente `laravel-tests`), así que un advisory nuevo sobre una dependencia no bloquea un PR ajeno. Queda en rojo y visible, y Dependabot abre el PR de actualización.

## Dependabot

`.github/dependabot.yml` revisa `composer`, `npm` y `github-actions` cada semana (lunes), apunta a `develop`, agrupa las actualizaciones menores y de parche, y usa el prefijo de commit `actualiza`.

## CODEOWNERS

`.github/CODEOWNERS` asigna al dueño `.github/`, `.claude/`, `config/`, `database/migrations/`, los archivos Docker, el módulo `Seguridad`, `routes/` y los archivos de dependencias. La revisión obligatoria por code owner se activa en la web cuando haya un segundo colaborador (ver [`rulesets.md`](rulesets.md)).

## Runner self-hosted

El runner del contenedor 119 solo ejecuta `deploy.yml`:

- El disparador es el push de un tag `v*.*.*`; un fork no puede pushear tags a este repositorio.
- El job tiene la guarda `if: github.repository == 'agrocom-developer/agrocom-api'`.
- El único workflow disparado por `pull_request` (`ci.yml`) y el de auto-merge corren en `ubuntu-latest`.
- Ningún workflow usa `pull_request_target`.
- Los valores de contexto controlables por quien crea el tag (`github.ref_name`) entran a los scripts por variable de entorno, nunca interpolados en el cuerpo del script.

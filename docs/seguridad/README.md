# Seguridad del repositorio y de la API

Este directorio describe los controles de seguridad vigentes. La política de reporte está en [`SECURITY.md`](../../SECURITY.md).

| Documento | Contenido |
|---|---|
| [`rulesets.md`](rulesets.md) | Reglas de rama y del environment que el dueño activa en la web de GitHub |
| [`ci-cd.md`](ci-cd.md) | Permisos de los workflows, acciones fijadas por SHA, auditoría de dependencias, Dependabot, CODEOWNERS |
| [`despliegue.md`](despliegue.md) | Respaldo previo a migrar, verificación y procedimiento de rollback |
| [`autenticacion.md`](autenticacion.md) | Límite de intentos del login de campo y vigencia de los tokens |

## Hechos de contexto

- El repositorio es público en GitHub. Nada que viva en él es secreto: las credenciales viven en `~/agrocom-api.env` del runner y en los secretos del environment.
- El dueño es una cuenta personal, por lo que el auto-merge usa `GITHUB_TOKEN` y no hay revisores obligatorios.
- El runner self-hosted solo ejecuta el workflow de despliegue, disparado por tag.

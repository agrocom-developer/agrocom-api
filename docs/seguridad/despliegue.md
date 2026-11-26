# Despliegue a producción beta

El workflow `.github/workflows/deploy.yml` se dispara al pushear un tag `vX.Y.Z` y corre en el runner self-hosted del contenedor 119, bajo el environment `produccion-beta`, que exige aprobación manual (ver [`rulesets.md`](rulesets.md)). Solo corre un despliegue a la vez (`concurrency`).

## Secuencia

1. Aprobación del dueño en el environment.
2. Checkout del tag, sin persistir credenciales.
3. Copia de `~/agrocom-api.env` a `.env`.
4. `docker compose up -d --build`.
5. **Respaldo**: `pg_dump --format=custom` desde el contenedor `db` hacia `~/agrocom-backups/pre-<tag>-<fecha>.dump`, fuera del checkout, con permisos `600` en un directorio `700`. Se verifica que el archivo no esté vacío y que `pg_restore --list` lo lea. Si falla, el job se detiene antes de migrar. Se conservan los 14 respaldos más recientes y ninguno de más de 30 días (`respaldos.retencion_dias`; ver «Respaldos y cuentas eliminadas»).
6. `php artisan migrate --force`, seguido de una verificación de que no quedan migraciones pendientes.
7. Espera del endpoint `/up`.

## Rollback

1. **Código**: volver al tag anterior, ya sea re-ejecutando el workflow sobre ese tag o con `git checkout <tag-anterior>` y `docker compose -f docker-compose.prod.yml up -d --build` en el runner.
2. **Datos**, solo si la migración dejó el esquema o los datos incompatibles con el tag anterior:

   ```bash
   docker compose -f docker-compose.prod.yml exec -T db sh -c \
     'pg_restore -U "$POSTGRES_USER" -d "$POSTGRES_DB" --clean --if-exists' \
     < ~/agrocom-backups/<archivo>.dump
   ```

   Restaurar el respaldo descarta lo escrito en la base después de ese momento. Los dispositivos de campo reenvían su outbox por `uuid_cliente`, de modo que el reenvío es idempotente.
3. Confirmar `/up` y una consulta autenticada de `GET /api/auth/sesion`.

## Prueba periódica

Restaurar un respaldo en una base descartable una vez por mes y comprobar que `migrate:status` y una consulta de conteo coinciden con producción.

## Respaldos y cuentas eliminadas

Un respaldo contiene los datos de las cuentas que existían cuando se hizo, también los de una cuenta que la persona eliminó después. Para que la eliminación sea real (ADR 0047):

- **Rotación.** Ningún respaldo de la base ni de los archivos privados vive más de **30 días** (`respaldos.retencion_dias`); la política de privacidad dice esa cifra. El respaldo previo al despliegue se borra con el mismo plazo aunque no se llegue a 14.
- **Registro de eliminaciones fuera de la base.** Cada día que `EjecutarEliminacionesDeCuenta` ejecuta una eliminación, agrega una línea (id de cuenta y fecha, sin datos personales) a un archivo del servidor que no forma parte del respaldo ni se restaura con él (`~/agrocom-backups/eliminaciones-ejecutadas.log`, permisos `600`).
- **Restaurar reaplica las eliminaciones.** Después de `pg_restore`, y antes de abrir el servicio, se corre `bin/reaplicar-eliminaciones`, que vuelve a ejecutar la anonimización de cada cuenta del registro que reaparezca con datos. Sin ese paso, una restauración revive a una cuenta eliminada.
- **Archivos.** El almacenamiento de objetos de las evidencias no conserva versiones de lo borrado más allá de los 30 días (sin versionado, o con una regla de ciclo de vida que elimine las versiones antiguas).
- **Prueba periódica.** La restauración mensual de esta página comprueba también que una cuenta eliminada después del respaldo queda anonimizada tras `bin/reaplicar-eliminaciones`.

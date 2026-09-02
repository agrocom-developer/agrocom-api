<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/fk-autoria-usuarios etapas=2 -->

# Tarea 30 — FK real de `created_by`/`updated_by` a `sec_user.id`

## Por qué esta tarea

Gap documentado en `docs/gestion/estado_proyecto.md`, sección "Otros gaps
señalados, aún sin resolver": desde que `HU-01` creó `sec_user`, ninguna tabla
de dominio tiene FK real en sus columnas de auditoría — son
`$table->unsignedBigInteger('created_by')->nullable()` sueltas, sin
`->constrained()`. El retrofit se dejó deliberadamente fuera de HU-01 (tocaba
migraciones de otros módulos ya mergeados) para resolverse "en un solo pase
futuro que agregue la FK a todas las tablas de una vez, no módulo por módulo".
Ese pase es esta tarea.

No es una HU de `plan_sprints.md` — es integridad de esquema (invariante
implícita: toda referencia debería tener su constraint, como ya tienen
`evidencia_foto_id`, `imagen_campo_evidencia_id`, etc.). Calidad de dato, no
crítica: no toca sync, estados, dinero, ni portal del cliente.

## Alcance: qué tablas

Confirmado con `grep -rl created_by database/migrations/*.php` al momento de
escribir este prompt — verificalo de nuevo al empezar, puede haber cambiado:

```
com_clientes, com_cliente_contactos, com_contratos, com_contrato_ventanas,
com_campos, com_lotes, ope_ordenes_aplicacion, per_bases, per_personas,
sec_role, sec_permission, sec_user, sec_user_role, sec_role_permission,
sec_user_preferencia, sec_menu, sec_token_dispositivo, plt_bitacoras,
dis_versiones_apk, ope_trabajos, ope_sesiones, ope_sesion_rechazos,
ope_condiciones, ope_recepciones_caldo, ope_evidencias, ope_drones,
ope_incidencias, ope_recargas, fin_devengos_personal, ope_actas, ope_alertas,
ope_reportes_tecnicos
```

`sec_user` misma es autorreferencial (`created_by` de un usuario puede apuntar
a otro usuario, o a sí mismo en el primer seed) — es un caso válido, no lo
excluyas.

## Qué hacer

Cargar skill `verificacion` y `modelo-datos` antes de tocar nada.

1. **Una sola migración nueva** (`database/migrations/2026_09_0X_...`) que
   itere sobre la lista de tablas y agregue, para cada una,
   `$table->foreign('created_by')->references('id')->on('sec_user')->nullOnDelete();`
   y lo mismo para `updated_by` — `nullOnDelete()` porque la columna ya es
   `nullable()` y borrar un `sec_user` no debería arrastrar ni bloquear el
   borrado de todo lo que auditó (soft delete es la norma en el dominio, pero
   `sec_user` no está exento de un borrado físico eventual de cuenta).
2. **Verificá datos huérfanos antes de aplicar la constraint**, aunque en este
   proyecto no debería haber (no hay datos de producción; `RegistraAutoria`
   —`app/Dominios/Compartido/Infraestructura/Eloquent/RegistraAutoria.php`—
   solo completa `created_by`/`updated_by` cuando hay `Auth::id()` real, si no
   deja `NULL`, nunca un id inventado). Si algún seeder asigna un valor fijo
   sospechoso, corregilo antes de migrar, no lo escondas con
   `->nullOnDelete()` mal puesto.
3. **Excepción, si aplica**: si alguna tabla resulta ser de plataforma pura
   sin owner real (revisalo, no lo asumas) y el `created_by` ahí no tiene
   sentido apuntando a `sec_user`, dejala afuera y anotá el porqué en
   `runs/30.md` en vez de forzarla.
4. No hace falta tocar los modelos Eloquent (no hay `belongsTo` que agregar
   para esto — la FK es de integridad de esquema, no de una relación que el
   código use activamente hoy).

## Cómo repartir las etapas

- **Etapa 1**: la migración con las ~30 tablas, corrida contra el Postgres
  real del compose (no solo SQLite) para confirmar que no hay huérfanos y que
  las 60 constraints (dos por tabla) se crean sin error.
- **Etapa 2**: los tests y el cierre.

## Qué NO hacer

- No cambies el tipo de las columnas (`unsignedBigInteger` está bien, no hace
  falta `foreignId`) — el propósito es agregar la constraint, no reescribir la
  columna.
- No toques `RegistraAutoria` ni el trait de bitácora — su comportamiento no
  cambia, solo se le pone una constraint de integridad a lo que ya escribe.
- No es la tarea para revisar por qué `sec_user` no tiene su propia auditoría
  completa ni para tocar el modelo de permisos — alcance es solo la FK.

## Criterio de aceptación

`./bin/verify` = 0, con un test (SQLite ya aplica `foreign_key_constraints`
por defecto — `config/database.php:40` — así que no hace falta verificación
aparte contra Postgres para ESTO, a diferencia de constraints `DB::statement`
crudas de tareas anteriores) que confirma, sobre al menos dos o tres tablas
representativas de distintos módulos (p. ej. una de `Comercial`, una de
`Operaciones`, una de `Seguridad`), que insertar un `created_by` con un id de
`sec_user` inexistente lanza `QueryException` — mismo patrón que
`tests/Feature/EsquemaOperacionesTest.php:110,155`.

## Cierre de la etapa

`runs/30.estado` con una palabra. `runs/30.md` con la lista final de tablas
migradas (y cuáles, si alguna, quedaron afuera con su porqué). Al llegar a
`OK`, `runs/30.pr.md`.

## Commits

Uno para la migración, uno para los tests (o juntos si el diff total es
chico — tu criterio). Español, imperativo. Sin `Co-Authored-By`.

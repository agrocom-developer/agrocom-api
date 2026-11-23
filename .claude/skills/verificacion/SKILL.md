---
name: verificacion
description: La compuerta de calidad de agrocom-api — cómo correr la cascada (bin/verify), qué significa cada etapa que falla y cómo se corrige, y qué NO se toca para hacerla pasar. Usar antes de dar por cerrado cualquier cambio de código, y siempre antes de commitear.
---

# Verificación — la compuerta de agrocom-api

Un cambio está terminado cuando `bin/verify` devuelve 0. No cuando "se ve bien",
no cuando el test que escribiste pasa: cuando la cascada pasa.

## Cómo se corre

```
./bin/verify                # lo afectado por tu diff contra origin/develop (meta: < 3 min)
./bin/verify --todo         # TODO: Pint completo + Larastan + suite entera + assets
./bin/verify --sin-assets   # no compila assets, aunque el diff los toque
```

**Por defecto se verifica solo lo afectado.** Nadie —persona ni agente— corre la suite
entera para iterar ni para cerrar una tarea: ni `php artisan test`, ni `pest`, ni
`composer verify` sin filtro. El selector (`bin/tests-afectados`) elige las pruebas que
pueden verse afectadas por el diff y siempre suma el humo mínimo (arquitectura de módulos
y contrato de API); ante la duda elige más, nunca menos, y si el diff toca algo que no puede
acotar (lockfiles, `config/`, migraciones, `.github/`, `bin/`, pruebas compartidas…) la
cascada pasa sola a `TODO` e imprime el motivo. `--todo` se usa cuando el selector ya lo
manda, para un release o para reproducir una falla nocturna. Detalle completo en
`docs/gestion/ci-por-impacto.md`.

`bin/tests-afectados --explicar` muestra qué pruebas elige y por qué. Una prueba nueva no
se registra en ningún lado: el selector la descubre por su contenido.

El grueso corre **dentro del contenedor `app`** (PHP 8.3, igual que CI). El PHP del
host puede ser otra versión y dar resultados distintos a los del CI — por eso el
script prefiere el contenedor y solo cae al host si Docker no está disponible.
Los assets se compilan en el host porque la imagen no trae Node.

La cascada se detiene en la primera etapa que falla. En modo afectado Pint revisa solo los
`.php` tocados; Larastan es siempre completo (usa su caché y el análisis de un archivo depende
de todo lo que usa).

En CI el mismo criterio reparte el trabajo: un PR a `develop` corre lo afectado; el PR de
release a `master`, el disparo manual, la etiqueta `full-ci` y la corrida nocturna corren la
suite completa en fragmentos. El check requerido sigue llamándose `laravel-tests` (es el job
agregador).

## Las etapas, en orden, y qué significa que fallen

| Etapa | Herramienta | Qué mide | Cómo se corrige |
|---|---|---|---|
| Estilo | `vendor/bin/pint --test` (solo lo tocado en modo afectado) | Formato PSR-12 + preset Laravel | `vendor/bin/pint` (sin `--test`) lo arregla solo |
| Análisis estático | Larastan **nivel 6** (`phpstan.neon`) | Tipos, propiedades inexistentes, retornos | Se corrige tipando de verdad — no con `@phpstan-ignore` |
| Tests | Pest 4 (las afectadas + humo; todas con `--todo`) | `tests/Unit`: arquitectura de módulos, máquinas de estado, bitácora y reglas de dominio puras — sin base de datos | Ver abajo |
| Assets | `npm run build` (Vite) | Que el CSS/JS compile | Suele ser un import o un token CSS inexistente |

Nivel 6 es exigente: exige tipos en parámetros y retornos, y detecta accesos a
propiedades que Eloquent resuelve dinámicamente. La salida se corrige leyéndola,
no silenciándola.

## Los tests no tocan la base de desarrollo

`tests/Unit` es Pest puro, **sin base de datos**: Pest solo extiende `TestCase`
para `tests/Feature`, que ya no existe. Correr la suite **no** afecta al Postgres
del compose ni al seed demo del panel. Por eso nunca hace falta `migrate:fresh`
para "limpiar antes de probar" — y por eso ese comando está bloqueado por el
guardarraíl (`.claude/hooks/guardarrail-bash.sh`).

Consecuencia a tener presente: como ningún test toca una base, la cascada **no
prueba migraciones ni SQL de Postgres**, y las migraciones tampoco corren en
SQLite (un `drop column` con índice en `cpn_campanias`, por ejemplo, falla ahí).
Los `ALTER TABLE ... ADD CONSTRAINT` siguen yendo dentro de la guarda
`DB::getDriverName() === 'pgsql'`. Para ver el comportamiento contra Postgres
sin dejar rastro: un script temporal en `storage/app/` (git-ignorado), ejecutado
dentro del contenedor (`docker exec agrocom-api-app-1 php storage/app/<script>.php`),
envuelto en `DB::beginTransaction()` y `try { ... } finally { DB::rollBack(); }`.
Postgres revierte también el DDL, así que incluso una migración nueva se puede
aplicar adentro (`Artisan::call('migrate')`) y validar sin que quede nada; al
final se comprueba que la base quedó intacta y se borra el script (detalle en el
skill [modelo-datos]).

## Los datos demo de la base del compose no se borran

Todo lo que se cargue en el Postgres del compose para probar —seeders, un
usuario, un lote, una orden de ejemplo— **se deja ahí**. El usuario los revisa
por su cuenta después, así que borrarlos "para dejar limpio" destruye
justamente lo que quería mirar.

Concretamente: nada de `migrate:fresh`, `migrate:refresh`, `db:wipe`,
`docker compose down -v` ni `TRUNCATE` sobre esa base (el guardarraíl de
`.claude/hooks/guardarrail-bash.sh` los deniega, y por esto). Tampoco un
`delete()` de limpieza al final de un script de prueba.

No hace falta limpiar para probar: la suite no toca esa base, y lo que se quiera
comprobar contra Postgres se hace dentro de una transacción que se revierte (ver
arriba). Si un dato demo nuevo estorba, se agrega uno distinto, no se borra el
anterior.

## Lo que no se hace para que la cascada pase

- **No se edita un test para que deje de fallar.** El test es el criterio de
  aceptación; si falla, o el código está mal, o el criterio cambió — y cambiar un
  criterio es una decisión del usuario, no un paso de la implementación. Si el test
  te parece equivocado, decilo y pará; no lo reescribas.
- **No se agregan `@phpstan-ignore` ni baselines** para saltear Larastan.
- **No se marca un test como `skip`** para desbloquear un commit.

Estas tres son exactamente las salidas que un agente encuentra "razonables" bajo
presión, y las tres vacían la compuerta de contenido.

## Qué NO cubre la cascada todavía

Saberlo importa para no confiar de más en un verde:

- **No hay regresión visual automática — ni gate ni herramienta versionada.**
  Existió como `tests/Visual/` (Playwright) entre las tareas 07 y 14/9/2026,
  pero nunca corrió en `ci.yml` (no tenía paso de Playwright) y localmente se
  salteaba en cualquier plataforma sin capturas `-darwin`/`-linux` propias
  (las versionadas eran `-win32`) — así que "✓ Cascada completa en verde"
  terminaba significando casi siempre "no se corrió nada visual", sin decirlo
  con claridad suficiente. Se sacó de `bin/verify` y el directorio se borró
  el mismo día (ver memoria `regresion-visual-fuera-de-bin-verify`): un `⊘`
  que nadie lee no es mejor que no tener la etapa, y una suite que nadie corre
  no vale mantener viva "por si". La verificación visual de un cambio es
  manual — ver el skill [panel-design-ui], "Verificación visual", para el
  procedimiento de captura ad-hoc (claro/oscuro) que ya se usa para revisión
  humana.
- **Invariantes 2 y 3 de CLAUDE.md no tienen gate automático todavía** (no
  sobrescribir un registro validado, sesión validada una sola vez):
  vigilan tablas que aún no existen (`sesion`, la máquina de
  estados operativos), así que su gate se escribe recién cuando ese dominio
  se implemente — antes, una aduana sobre algo inexistente pasaría siempre y
  simularía una cobertura que no hay.
  Las invariantes 7 (transiciones por el servicio de estados) y 9 (bitácora
  antes/después) **sí tienen gate** desde las tareas 04 y 06:
  `tests/Unit/TransicionesEstadoTest.php` y el gate de bitácora en
  `app/Dominios/Compartido/` (trait/observer, ADR 0007). También hay gate
  para la 8 (soft delete, vía `ModeloDominio`) y para las fronteras modulares
  (`tests/Unit/ArquitecturaModulosTest.php`).

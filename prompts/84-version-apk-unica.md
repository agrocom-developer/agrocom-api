<!-- ciclo: critica=no turno-noche=1 rama=feature/version-apk-unica etapas=1 descongela=tests -->

# Tarea 84 — `VersionApkFactory` no garantiza `version` única

## Por qué esta tarea

No es una HU ni una TE de `plan_sprints.md`: es deuda técnica concreta, con
criterio de aceptación ejecutable — mismo tipo de fila que la tarea 61 (otro
flake documentado por una tarea que no era la suya, con criterio propio para
corregirlo).

`database/migrations/2026_09_01_100001_create_dis_versiones_apk_table.php`
pone un índice único parcial sobre `version` (además del que ya tiene
`version_code`):

```sql
CREATE UNIQUE INDEX dis_versiones_apk_version_unico
ON dis_versiones_apk (version) WHERE deleted_at IS NULL
```

`database/factories/VersionApkFactory.php` genera `version_code` con
`fake()->unique()->numberBetween(1, 100000)` (correcto, sin colisión posible
en cualquier corrida realista) pero `version` con
`sprintf('1.%d.%d', fake()->numberBetween(0, 20), fake()->numberBetween(0, 20))`
— **sin** `unique()`. Con 21×21 = 441 combinaciones posibles, cualquier test o
seeder que cree más de un puñado de instancias por factory sin fijar
`version` a mano tiene una probabilidad real de colisión contra el índice
único, con `QueryException` como resultado.

La tarea 71 (HU-48, ajena a este módulo) lo encontró de pasada corriendo la
suite completa (`runs/71.md`): un fallo intermitente en
`tests/Feature/Distribucion/MaquinaEstadosVersionApkTest.php`, no reproducido
en corridas aisladas — el patrón clásico de un flake por colisión de datos
aleatorios entre tests que corren en paralelo. Documentado y dejado fuera de
alcance en su momento, correctamente: `Distribucion` no era parte de esa
tarea.

## Qué hacer

Cargá el skill `verificacion` antes de empezar.

1. **Corregí `VersionApkFactory::definition()`** para que `version` no pueda
   colisionar. La forma más robusta —no exhaure el pool de `fake()->unique()`
   en una corrida que cree muchas instancias— es derivarla del propio
   `$versionCode`, que ya es único por construcción: por ejemplo
   `sprintf('1.%d.%d', intdiv($versionCode, 1000) % 21, $versionCode % 1000)`
   o cualquier descomposición equivalente que garantice `version` distinta
   para todo `$versionCode` distinto, sin depender de una segunda llamada a
   `fake()->unique()`. Evaluá esa opción contra usar `fake()->unique()` dos
   veces (más simple, pero con un pool de solo 21 valores por segmento —
   se agota rápido si un test crea más de ~20 instancias) y elegí la que
   sostenga sin fallar una corrida de cientos de instancias en un solo test.
2. **Escribí un test de regresión** en `tests/Feature/Distribucion/` (nuevo
   archivo o sumado a `MaquinaEstadosVersionApkTest.php`, tu criterio) que
   cree 200 o más `VersionApk::factory()->create()` en un solo test, sin fijar
   `version` a mano, y confirme que las 200 quedan persistidas sin
   `QueryException` y con `version` distinta entre todas. Corré ese test 5
   veces seguidas para confirmar que no es él mismo un nuevo flake.
3. `./bin/verify` completo.

## Qué NO hacer

- No toques la migración ni el índice único — es correcto, es la invariante 1
  (idempotencia/unicidad) aplicada a esta tabla, el bug está en el factory,
  no en el esquema.
- No toques `VersionApk` (el modelo), los casos de uso reales de
  `Distribucion` (autorización, endpoint `GET /api/version`) ni ninguna
  pantalla del panel — el alcance es exclusivamente el factory y su test de
  regresión.
- No cambies el formato `"1.x.y"` de `version` de forma que deje de ser
  legible como versionado semántico — solo hace falta que sea determinística
  y sin colisión, no un formato distinto.
- No investigues ni "arregles" el resto de flakes ajenos que la tarea 71
  documentó (`SeedDemoCompletaTest`, `PlanillaPanelTest`) — son de otro
  origen (carreras de `Storage::fake` entre workers paralelos), no de esta
  factory, y no son parte de esta tarea.

## Criterio de aceptación

`./bin/verify` = 0, con un test nuevo que crea 200+ `VersionApk::factory()` en
un solo test sin colisión de `version`, corrido 5 veces seguidas sin fallar.

## Cierre obligatorio

`runs/84.estado` = `OK` (una sola etapa; si algo la trabara, `BLOQUEADA` con
el motivo). `runs/84.md` con la solución elegida para garantizar unicidad y
el resultado de las 5 corridas. `runs/84.pr.md` con título y cuerpo del PR.

## Commits

Uno solo, salvo que el diagnóstico revele algo que amerite separarlo: "corrige
`VersionApkFactory` para que `version` no pueda colisionar", en español,
imperativo, explicando el porqué (la colisión real, no "agrega unique").
Sin trailer `Co-Authored-By`.

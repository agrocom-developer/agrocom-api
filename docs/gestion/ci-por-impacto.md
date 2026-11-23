# CI por impacto

**Última actualización: 23/11/2026.** Un cambio corre las pruebas que pueden verse
afectadas por él, no la suite entera. La suite completa corre en el release a
`master`, de noche y a pedido.

## Qué corre y cuándo

| Situación | Qué corre | Meta de tiempo |
|---|---|---|
| `bin/verify` (local) | Pint sobre los `.php` tocados, Larastan, pruebas afectadas + humo, assets solo si el diff los toca | menos de 3 min |
| PR hacia `develop` | Pint sobre lo tocado, Larastan, pruebas afectadas + humo | menos de 10 min |
| PR `develop` → `master` (release) | Suite completa en 3 fragmentos paralelos, sin filtros | menos de 15 min |
| `workflow_dispatch` o etiqueta `full-ci` en el PR | Suite completa, igual que el release | menos de 15 min |
| Nocturno (07:00 UTC) sobre `develop` | Suite completa; si falla abre o actualiza un issue `ci-nocturno` | — |
| `bin/verify --todo` | Pint completo, Larastan, suite completa, assets | — |

El **humo mínimo** corre siempre, sin importar el diff:

- `tests/Unit/ArquitecturaModulosTest.php`: fronteras entre módulos y reglas de importación (ADR 0003).
- `tests/Unit/ContratoOpenApiTest.php`: `docs/api/openapi.yaml` se lee y cada operación tiene `operationId` único.

## El selector: `bin/tests-afectados`

```
bin/tests-afectados                         # diff contra origin/develop (+ cambios locales)
bin/tests-afectados --explicar              # agrega por qué se eligió cada prueba
bin/tests-afectados --json                  # todo, motivo, pruebas, razones, archivos cambiados
bin/tests-afectados --archivos=a.php,b.php  # sin git: un diff dado
```

La salida es una prueba por línea; si el diff obliga a correr todo imprime `TODO <motivo>`.
La lógica vive en `bin/lib/SelectorImpacto.php`.

**Falla siempre hacia más pruebas, nunca hacia menos.** Un archivo que no reconoce corre
la suite entera; una prueba cuyas dependencias no se pueden inferir corre ante cualquier
cambio de código.

### Corre TODO si el diff toca

`composer.json`/`composer.lock`, `package.json`/`package-lock.json`, `config/`, `bootstrap/`,
`app/Providers/`, `database/migrations/` y el resto de `database/` salvo `factories/` y
`seeders/`, `.github/`, `bin/`, `phpunit*.xml`, `phpstan*`, cualquier archivo de `tests/` que
no sea una prueba (`Pest.php`, `TestCase.php`, `bootstrap.php`, ayudantes), el entorno de
ejecución (`artisan`, `Dockerfile*`, `docker/`, `docker-compose*.yml`, `.env*`) y cualquier
archivo que el selector no reconozca.

No tienen efecto sobre ninguna prueba: los `*.md` de la raíz, `.claude/`, `.gitignore`,
`.gitattributes`, `.editorconfig`, `LICENSE` y `CODEOWNERS`.

### Cómo mapea el resto

El análisis es estático, sobre el texto de cada prueba y de cada clase:

- **Grafo de clases.** Cada clase de `app/` y de `database/factories`/`database/seeders` apunta a
  las clases que nombra (nombre completo, `use` agrupados y clases hermanas del mismo namespace;
  los comentarios no cuentan). Si cambia una clase, se calculan las clases que la usan.
  Dentro de un módulo la propagación es transitiva. Entre módulos cruza un solo paso: los
  consumidores directos de lo que cambió. El grafo entre módulos es tan denso que seguir
  cruzando haría que cualquier cambio alcanzara a casi toda la suite; la integración entre módulos
  la cubren las pruebas que levantan la app (corren ante cualquier cambio de `app/`) y la suite
  completa del release y de la noche.
- **Pruebas que nombran clases.** Una prueba depende de los nombres `App\…`/`Database\…` que
  escribe; un nombre que es solo un namespace cubre todo lo que cuelga de él.
- **Pruebas que leen archivos.** Los literales de ruta (`/lang/es/…`, `database_path('migrations')`,
  `/app/Dominios/Comercial`) marcan qué carpetas escanea una prueba; corre cuando cambia algo adentro.
- **Pruebas que levantan la app** (`TestCase`, `tests/Feature/`) corren ante cambios de `app/`, rutas,
  vistas, idiomas y datos semilla.
- **Pruebas opacas** (sin referencias ni rutas detectables) corren ante cualquier cambio de código.
- **Una prueba modificada** corre siempre; una borrada no se pide.

El mapeo sale del contenido, no de los nombres de archivo ni de carpetas: no hace falta
mantener etiquetas ni mover pruebas, y una prueba nueva queda cubierta sin editar el selector.

### La prueba del selector

`tests/Unit/Ci/SelectorImpactoTest.php` arma un proyecto mínimo en un directorio temporal y
verifica diff de ejemplo → conjunto esperado de pruebas, incluidos todos los casos de "corre todo".
Cambiar el selector corre toda la suite (`bin/` es disparador de todo), así que esa prueba se
ejecuta siempre que el selector cambia.

## Estructura de `ci.yml`

| Job | Qué hace |
|---|---|
| `detect-laravel` | Existe el proyecto Laravel |
| `detectar` | Decide el modo (`afectados` o `completo`) y calcula las pruebas; publica la selección y sus razones en el resumen |
| `laravel-calidad` | Larastan, Pint (solo lo tocado en modo afectados) y assets si el diff los toca |
| `laravel-tests-afectados` | Solo en modo `afectados`: `bin/correr-pruebas --pruebas "<lista>"` |
| `laravel-tests-completo` | Solo en modo `completo`: matriz de 3 fragmentos, `bin/correr-pruebas --shard i/3` |
| `laravel-tests` | **Agregador, el check requerido por `auto-merge.yml`.** Falla si cualquier parte falla, o si una parte que debía correr quedó `skipped`/`cancelled` |
| `issue-nocturno` | Solo en la corrida nocturna: abre o actualiza el issue `ci-nocturno` si algo falló |
| `seguridad` | Auditoría de dependencias (no es check requerido) |

El nombre `laravel-tests` no cambia: `auto-merge.yml` espera ese check sobre el SHA de cabecera del PR.

Pasa a modo `completo` también un PR a `develop` cuyo diff el selector no puede acotar.
Para forzarlo a mano se agrega la etiqueta `full-ci` al PR.

## Medición y presupuesto

Cada corrida de pruebas publica en el resumen del job (`bin/resumen-ci`): tiempo total de reloj,
suma de tiempos de prueba, tiempo por carpeta de `tests/` y las 10 pruebas más lentas. Si la corrida
supera el presupuesto, el resumen lo avisa (no falla el job: es un aviso, no una compuerta).

Los presupuestos se configuran con variables del repositorio (Settings → Variables):

- `CI_PRESUPUESTO_AFECTADAS_SEG` (por defecto 600) para el modo afectados.
- `CI_PRESUPUESTO_COMPLETO_SEG` (por defecto 900) para cada fragmento del modo completo.

Local: `CI_PRESUPUESTO_SEG=180 bin/verify` avisa si la corrida de pruebas supera 3 minutos.

## Reglas para quien edita código (personas y agentes)

- Para iterar y para cerrar una tarea: `./bin/verify`. No se corre `php artisan test`, `pest` ni
  `composer verify` sobre la suite entera por defecto.
- La suite completa es `./bin/verify --todo`, y se reserva para cambios que el selector ya manda a
  `TODO`, para un release o para reproducir una falla nocturna.
- Una prueba nueva no se registra en ningún lado: el selector la descubre por su contenido. Si una
  prueba escanea archivos con rutas armadas dinámicamente (sin literales), el selector la trata como
  opaca y corre ante cualquier cambio de código; para afinarla basta nombrar la carpeta que lee con
  un literal (`'/app/Dominios/Comercial'`).

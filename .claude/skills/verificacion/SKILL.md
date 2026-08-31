---
name: verificacion
description: La compuerta de calidad de agrocom-api — cómo correr la cascada (bin/verify), qué significa cada etapa que falla y cómo se corrige, y qué NO se toca para hacerla pasar. Usar antes de dar por cerrado cualquier cambio de código, y siempre antes de commitear.
---

# Verificación — la compuerta de agrocom-api

Un cambio está terminado cuando `bin/verify` devuelve 0. No cuando "se ve bien",
no cuando el test que escribiste pasa: cuando la cascada completa pasa.

## Cómo se corre

```
./bin/verify                # cascada completa (PHP en el contenedor + assets en el host)
./bin/verify --sin-assets   # solo PHP, para iterar rápido
```

El grueso corre **dentro del contenedor `app`** (PHP 8.3, igual que CI). El PHP del
host puede ser otra versión y dar resultados distintos a los del CI — por eso el
script prefiere el contenedor y solo cae al host si Docker no está disponible.
Los assets se compilan en el host porque la imagen no trae Node.

Dentro del contenedor, `composer verify` encadena las tres etapas de PHP y se
detiene en la primera que falla.

## Las etapas, en orden, y qué significa que fallen

| Etapa | Herramienta | Qué mide | Cómo se corrige |
|---|---|---|---|
| Estilo | `vendor/bin/pint --test` | Formato PSR-12 + preset Laravel | `vendor/bin/pint` (sin `--test`) lo arregla solo |
| Análisis estático | Larastan **nivel 6** (`phpstan.neon`) | Tipos, propiedades inexistentes, retornos | Se corrige tipando de verdad — no con `@phpstan-ignore` |
| Tests | Pest 4 | 159 tests, SQLite en memoria | Ver abajo |
| Assets | `npm run build` (Vite) | Que el CSS/JS compile | Suele ser un import o un token CSS inexistente |

Nivel 6 es exigente: exige tipos en parámetros y retornos, y detecta accesos a
propiedades que Eloquent resuelve dinámicamente. La salida se corrige leyéndola,
no silenciándola.

## Los tests no tocan la base de desarrollo

`phpunit.xml` fuerza `DB_CONNECTION=sqlite` y `DB_DATABASE=:memory:`. Correr la
suite **no** afecta al Postgres del compose ni al seed demo del panel. Por eso
nunca hace falta `migrate:fresh` para "limpiar antes de probar" — y por eso ese
comando está bloqueado por el guardarraíl (`.claude/hooks/guardarrail-bash.sh`).

Consecuencia a tener presente: un test que pasa en SQLite puede fallar en CI
contra Postgres 16. Las migraciones ya contemplan esto (los `ALTER TABLE ... ADD
CONSTRAINT` van dentro de `if (DB::getDriverName() === 'pgsql')`). Si tu cambio
depende de una función específica de Postgres, el test tiene que saltarse en
SQLite explícitamente, no asumir el motor.

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

- **No hay tests de navegador.** Playwright está en `devDependencies` pero no hay
  `playwright.config` ni suite E2E. La verificación visual del panel sigue siendo
  manual — ver el skill [panel-design-ui].
- **No hay regresión visual.** Un cambio de CSS puede romper el tema oscuro y
  dejar la cascada en verde.
- **Invariantes 2, 3, 7 y 9 de CLAUDE.md no tienen gate automático** (no
  sobrescribir validados, devengo solo al validar, transiciones por servicio de
  estados, bitácora antes/después). Se cumplen por revisión, no por exit code.
  Sí hay gate para la 8 (soft delete, vía `ModeloDominio`) y para las fronteras
  modulares (`tests/Unit/ArquitecturaModulosTest.php`).

# ADR 0024 — Migraciones consolidadas: una `create` por tabla, con el estado final

**Fecha:** 2026-09-22 · **Estado:** aceptado · **Decide:** el dueño, con el desarrollador.

## Contexto

`database/migrations` acumulaba 153 archivos: 3 de Laravel y 150 propios, de los cuales la mitad eran
`add_*`, `drop_*`, `amplia_*`, `mueve_*`, `elimina_*` sobre tablas creadas antes, y varias tablas ya no
existían (`com_campos`, `com_contrato_alcances`, `com_contrato_ventanas`). El enfoque del proyecto cambió
varias veces desde agosto y el historial de esquema era ruido: no se podía saber cuántas tablas hay ni
cuál es el esquema vigente sin recorrer toda la carpeta. El dueño pidió (22/9/2026) archivos limpios donde
cada tabla esté definida una sola vez con su estado actual, y que lo legado deje de estorbar.

## Decisión

1. **Una migración `create_<tabla>_table` por tabla, con el estado final** (columnas, claves foráneas,
   índices, índices parciales, CHECK). 76 tablas → 76 archivos, numerados `2026_09_23_0000NN` en orden
   de dependencia de claves foráneas, más `000000_create_extension_unaccent`. **Contar tablas = contar
   archivos `create`.** Las tres migraciones propias de Laravel (`0001_01_01_*`) quedan como están.
2. Las claves foráneas de autoría (`created_by`/`updated_by` → `sec_user`) van en UNA migración final
   (`agrega_claves_foraneas_de_autoria`), como en la legada `add_fk_autoria_a_tablas_dominio`: cierran el
   ciclo `sec_user → per_personas → sec_user` y el retrofit a las tablas que aún no la tienen sigue
   pendiente en un solo pase (skill modelo-datos).
3. **Compatibilidad con bases ya migradas** (compose, staging, producción): cada `create` empieza con
   `if (Schema::hasTable(...)) return;`, la de autoría saltea cada restricción que ya exista y la extensión
   es `IF NOT EXISTS`. En una base legada, `migrate` registra las 79 nuevas sin tocar el esquema, y la
   última (`retira_migraciones_legadas`) borra de la tabla `migrations` toda fila cuyo archivo ya no
   existe. Verificado el 22/9/2026: 157 filas → 82, esquema idéntico antes y después.
4. **Los archivos los genera `bin/consolidar-migraciones`** leyendo el catálogo de Postgres (no se
   escriben a mano): columnas por `information_schema`, restricciones por `pg_constraint`, índices por
   `pg_index`. Cada archivo lista en su docblock las migraciones legadas que reemplaza. Los CHECK se
   emiten en la forma que escribió el desarrollador (`estado IN (...)`, `x >= 0`), que Postgres normaliza
   igual que el original.
5. **Verificación = comparación de esquemas**, no lectura: se migra una base vacía con los archivos nuevos
   (`docker exec -e DB_DATABASE=agrocom_verifN app php artisan migrate --force`) y se compara con la
   base legada usando un volcado normalizado (columnas, restricciones e índices por tabla). El 22/9/2026
   la diferencia fue cero líneas.

## De ahora en más

- Una tabla nueva es un `create_<tabla>_table` nuevo. Un cambio a una tabla existente sigue siendo una
  migración `add_*`/`drop_*` normal: **no se edita el `create`** de una tabla que ya está en alguna base.
- Cuando vuelvan a acumularse alteraciones (regla práctica: más `add_*`/`drop_*` que `create_*`), se
  repite la consolidación con el mismo generador y la misma verificación, en un PR propio.
- Los docblocks del código que citan migraciones legadas por nombre (`2026_09_01_200002_...`) son
  historia: no se reescriben; el archivo que las reemplaza las nombra.

## Consecuencias

- `tests/Unit/BitacoraAuditoriaTest` sigue leyendo `Schema::create('tabla'` de las migraciones para
  decidir qué tablas auditar: por eso los archivos usan `Schema::create` literal y no un helper.
- `migrate:rollback` de la tanda consolidada en una base legada haría `dropIfExists` de tablas con
  datos: no se hace rollback de esta tanda; se avanza con migraciones nuevas.
- Un entorno que estuviera a mitad de las migraciones legadas (ninguno conocido) tendría que
  completarlas con el commit anterior antes de tomar este.

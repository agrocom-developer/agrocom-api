---
name: modelo-datos
description: Convenciones de esquema PostgreSQL de agrocom-api — nombres y prefijos de tabla, columnas obligatorias de auditoría y soft delete, DECIMAL para dinero y hectáreas, índices parciales, y qué hacer con la diferencia Postgres/SQLite. Usar antes de escribir o modificar cualquier migración.
---

# Modelo de datos — agrocom-api

PostgreSQL 16 (ADR 0001). Las migraciones son la única forma de cambiar el
esquema: no hay DDL suelto, y el guardarraíl de Bash bloquea `DROP`/`TRUNCATE`
fuera de una migración.

## Nombre de tabla

`<prefijo_de_módulo>_<entidad_en_plural>`: `com_lotes`, `ope_ordenes_aplicacion`,
`per_personas`, `sec_user_role`. La tabla de prefijos vigente está en el ADR 0011
y en el skill [dominio-backend]. Una migración de un módulo que toca una tabla con
prefijo ajeno es una violación de frontera visible a simple vista en el diff.

`sec_*` conserva los nombres definidos en el ADR 0004, algunos en singular
(`sec_user`, `sec_role`) — no se "corrigen" por consistencia.

## Columnas que lleva toda tabla de dominio

```php
$table->unsignedBigInteger('created_by')->nullable();
$table->unsignedBigInteger('updated_by')->nullable();
$table->timestamps();
$table->softDeletes();
```

Sin excepción (ADR 0007, invariante 8 de CLAUDE.md). Hoy `created_by`/`updated_by`
son enteros planos **sin FK** a `sec_user.id`: el retrofit está pendiente y se
resolverá en un solo pase para todas las tablas, no módulo por módulo.

## Tipos que no se negocian

- **Dinero y hectáreas en `DECIMAL`, jamás `float`** (invariante 6). Vigente:
  `$table->decimal('hectareas', 10, 2)`. Todo monto derivado debe poder
  recalcularse desde el origen y cuadrar exacto.
- **`uuid_cliente`** en todo registro que nace en la app de campo, con
  `UNIQUE (uuid_cliente)` — es lo que hace la sincronización idempotente
  (invariante 1). Todavía no hay tablas de sync, pero la convención ya está fijada.
- **Geometría en `jsonb` como GeoJSON**, no PostGIS en v1: se guarda y se dibuja,
  no se consulta espacialmente.

## Unicidad con soft delete: índice parcial

Un `unique()` normal choca con el borrado lógico (una fila borrada sigue ocupando
el valor). El patrón vigente en `com_lotes`:

```php
$prefijo = DB::getTablePrefix();

DB::statement(<<<SQL
    CREATE UNIQUE INDEX {$prefijo}com_lotes_codigo_unico
    ON {$prefijo}com_lotes (campo_id, codigo)
    WHERE deleted_at IS NULL
SQL);
```

`DB::getTablePrefix()` se interpola siempre en SQL crudo: el prefijo global de
Laravel se aplica solo en Eloquent y el schema builder, no dentro de un
`DB::statement` (ADR 0011).

## Postgres sí, SQLite no

Los tests corren en SQLite en memoria (ver [verificacion]) y SQLite no soporta
`ALTER TABLE ... ADD CONSTRAINT`. Los CHECK van dentro de una guarda:

```php
if (DB::getDriverName() === 'pgsql') {
    DB::statement(<<<SQL
        ALTER TABLE {$prefijo}com_lotes
        ADD CONSTRAINT {$prefijo}com_lotes_hectareas_chk
        CHECK (hectareas > 0)
    SQL);
}
```

Consecuencia: **un CHECK así no está cubierto por la suite local**. Si la regla es
importante, además del CHECK va validada en el caso de uso, que sí se testea.

## Cada migración lleva su test de esquema

El patrón ya existe: `tests/Feature/EsquemaNucleoComercialTest.php`,
`EsquemaSeguridadPersonalTest.php`, `Modelos/BorradoLogicoTest.php`. Verifican que
las columnas existen, que el soft delete está, y que no se puede borrar físico.
Una migración nueva sin su test no está terminada.

## Lecturas complejas

No se arman con SQL crudo disperso: van por vistas `vw_*` (ADR 0012).

## Lo que no se hace

`migrate:fresh`, `migrate:refresh`, `migrate:reset` y `db:wipe` están bloqueados
por el guardarraíl: vacían la base del compose, que tiene el seed demo del panel.
Para probar una migración desde cero, la suite ya la corre entera en SQLite.

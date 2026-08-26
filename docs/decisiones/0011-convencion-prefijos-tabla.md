# ADR 0011 — Convención de prefijos de tabla: prefijo de módulo + prefijo global opcional

**Estado:** Aceptada · **Resuelve:** la decisión diferida "mapeo de prefijos de tabla por módulo" registrada en `docs/gestion/estado_proyecto.md` (retomada el 26/8/2026).

## Contexto

El nombrado físico de tablas estaba explícitamente diferido: el esquema `sec_*` ya usa prefijo de módulo (ADR 0004), pero el resto de las tablas de la especificación (`docs/especificacion/especificacion_funcional_tecnica.md`, sección 4) aparece sin prefijo (`clientes`, `ordenes_aplicacion`, `trabajos`, `sesiones`). Con las migraciones del núcleo comercial por arrancar (TE-03), la convención tiene que quedar cerrada antes de crear la primera tabla — renombrar después es caro y ruidoso.

Además, la regla 1 del ADR 0003 ("un módulo solo escribe sus propias tablas") hoy solo es visible en el árbol de código; en el esquema de la base no hay nada que diga a qué módulo pertenece cada tabla, ni que haga evidente en una migración que un módulo está creando o alterando una tabla ajena.

## Decisión

Doble nivel, estilo WordPress:

1. **Prefijo de módulo corto en el nombre físico de cada tabla.** El resto del nombre sigue la convención de dominio en español de la especificación (sección 4): `com_clientes`, `ope_ordenes_aplicacion`. Mapeo:

   | Módulo | Prefijo |
   |---|---|
   | Seguridad | `sec_` (ya en uso, ADR 0004 — conserva sus nombres tal como están definidos ahí) |
   | Sync | `syn_` |
   | Comercial | `com_` |
   | Operaciones | `ope_` |
   | Mezclas | `mez_` |
   | Personal | `per_` |
   | Finanzas | `fin_` |
   | Inventario | `inv_` |
   | Mantenimiento | `man_` |
   | Vistas de solo lectura | `vw_` (ver ADR 0012) |

   Reportes y Portal no tienen prefijo propio porque no escriben tablas: son módulos de solo lectura (`docs/especificacion/insumos_modelo_datos.md`, sección 1) y sus lecturas van por vistas `vw_*`.

2. **Prefijo global opcional a nivel de conexión**, como el `$table_prefix` de WordPress: `'prefix' => env('DB_TABLE_PREFIX', '')` en la conexión `pgsql` de `config/database.php`. Vacío por defecto; si algún día la base se comparte con otro sistema, se configura `agrocom_` en `.env` sin tocar código ni migraciones.

La base de datos es dedicada al sistema, así que el prefijo global no aporta hoy (solo alargaría joins y consultas manuales) — pero queda disponible sin costo. El prefijo de módulo, en cambio, hace visible en el propio esquema la frontera modular del ADR 0003.

## Alternativas descartadas

- **Solo prefijo global `agrocom_`**: identifica al sistema pero no dice nada del módulo dueño de cada tabla — que es justo la información que la regla 1 del ADR 0003 necesita hacer visible.
- **Prefijo compuesto en el nombre físico (`agro_sec_user`)**: verboso y redundante en una base dedicada; y fija en el DDL algo que el nivel de conexión resuelve por configuración.

## Consecuencias

- Toda migración que cree una tabla usa el prefijo del módulo que la escribe. Una migración de un módulo tocando tablas con prefijo ajeno es una violación de la regla 1 del ADR 0003, detectable a simple vista en el diff del PR (y verificable con tests de arquitectura).
- `config/database.php` cambia `'prefix' => ''` por `'prefix' => env('DB_TABLE_PREFIX', '')` en la conexión `pgsql`, y `.env.example` documenta `DB_TABLE_PREFIX=` vacío — es la única pieza de código de este ADR (la aplica el agente `backend` en la rama en curso).
- El prefijo global de Laravel se aplica automáticamente en Eloquent, query builder y schema builder, pero **no** dentro de SQL crudo: las migraciones de vistas por `DB::statement` (ADR 0012) deben interpolar `DB::getTablePrefix()` para no romper si algún día `DB_TABLE_PREFIX` deja de estar vacío.
- Los nombres físicos con prefijo se incorporan a la sección 4 de la especificación en su consolidación (pendiente de la reunión de cierre) — este ADR no modifica `docs/especificacion/`.
- Queda abierto el prefijo de las tablas transversales de `Compartido/` (p. ej. la bitácora de auditoría del ADR 0007): se define al implementarlas, ampliando la tabla de este ADR. Todo módulo nuevo registra aquí su prefijo antes de su primera migración.

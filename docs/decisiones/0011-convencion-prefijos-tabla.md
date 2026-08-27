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

### Extensión (26/8/2026) — dueño de `personas`/`bases` y ubicación de `Seguridad`, para HU-01

**Contexto de la extensión:** ADR 0004 define `sec_user.persona_id` (FK nullable a la persona operativa) pero ninguna TE anterior a HU-01 crea `personas` ni `bases` (§4.2 de la especificación). HU-01 ("crear usuarios con uno o más roles... y enlace a persona operativa") necesita que ese FK tenga un destino real. Esta sección resuelve el mapeo módulo→tablas que faltaba y dos preguntas de frontera que el ADR 0003 original dejó implícitas.

3. **`Personal` (`per_`) es el dueño de `personas` y `bases`.** La especificación agrupa `drones`, `baterías`, `vehículos`, `bases` y `personas` bajo un mismo epígrafe documental ("4.2 Recursos"), pero esa agrupación no es una frontera de módulo — igual que "4.3 Operación" ya se partió entre `Operaciones` (`ope_ordenes_aplicacion`) y `Mezclas` (`mez_*`) en TE-03. `personas` y `bases` van en `app/Dominios/Personal/` (prefijo `per_`, ya reservado en la tabla de arriba: `per_personas`, `per_bases`). `drones`, `baterías`, `vehículos` y `generadores` quedan para cuando existan los módulos `Mantenimiento`/`Inventario` (ninguno de los dos existe todavía bajo `app/Dominios/`, y HU-01 no los necesita).

   `bases` se descartó como tabla transversal de `Compartido/` — ver alternativas descartadas.

4. **El nombre de carpeta vigente para `sec_*` es `app/Dominios/Seguridad/`, no `Identidad`.** El ADR 0003 original listó los módulos previstos como `Identidad, Comercial, Mezclas, Operaciones, Sync, Finanzas, Mantenimiento, Portal, Reportes`, pero el ADR 0004 —que es el que efectivamente diseñó este módulo— lo nombra "seguridad" en su título y en todo su texto, la especificación titula su §4.6 "Seguridad (`sec_*`)", y este mismo ADR 0011 ya lo había mapeado como `Seguridad → sec_` en la tabla de arriba. Ningún documento vigente usa "Identidad" salvo esa lista de ADR 0003, que quedó desactualizada y no fue una decisión de renombrar deliberada — es deriva de nomenclatura entre ADRs, no una contradicción activa que alguien haya introducido a propósito. Se deja registrado para que nadie cree `app/Dominios/Identidad/` siguiendo la lista vieja al pie de la letra: **la tabla de este ADR 0011, no la lista prosa del ADR 0003, es la referencia canónica de nombres de módulo** — incluye además `Personal` e `Inventario`, que tampoco estaban en la lista original de ADR 0003. No se reescribe ADR 0003 (es un registro histórico); esta nota es la que reconcilia.

   Hoy no existe ningún módulo bajo `app/Dominios/` para `sec_*` (TE-03 solo tocó `Comercial`, `Compartido` y `Operaciones`); HU-01 es quien crea `app/Dominios/Seguridad/` por primera vez.

5. **`sec_user.persona_id → per_personas.id`: FK de base de datos, nunca relación Eloquent cruzada.** Es una referencia por ID (regla 3 del ADR 0003), no lógica cruzada:
   - La migración de `sec_user` lleva FK real a `per_personas.id` (integridad referencial es responsabilidad de la base).
   - El modelo Eloquent `SecUser` (`Seguridad/Infraestructura/Eloquent/`) expone `persona_id` como atributo entero plano — **no** declara `belongsTo(Persona::class)` importando el modelo Eloquent de `Personal`. En cuanto exista la carpeta `Seguridad/`, `tests/Unit/ArquitecturaModulosTest.php` lo bloquea solo (descubre módulos por carpeta y prohíbe que uno importe `Infraestructura\Eloquent` de otro), sin que haga falta tocar el test.
   - Si más adelante el panel necesita mostrar datos de la persona junto al usuario (nombre, rol operativo) y no solo el id, eso se resuelve con un contrato de lectura en `Personal/Contratos/` (interfaz + DTO primitivo, ADR 0003 regla 2) que `Seguridad/Aplicacion/` consume por inyección de dependencias — nunca uniendo tablas vía Eloquent cross-módulo. No hace falta para HU-01 si alcanza con el id; queda como paso natural si HU-02 (menú/listado de usuarios) pide mostrar el nombre.
   - El mismo patrón (FK + atributo plano, sin `belongsTo` cross-módulo) aplica también a `sec_user.contrato_id → com_contratos.id` — no es exclusivo de esta decisión, es el patrón general para toda FK saliente de `sec_user`.

6. **Alcance mínimo de `per_personas`/`per_bases` para HU-01**, sin adelantar el módulo `Personal` completo:
   - `per_bases`: `id`, `nombre`, `ubicacion` — igual que la especificación §4.2; una tabla de dos columnas de negocio no tiene nada que recortar.
   - `per_personas`: `id`, `nombre`, `rol` (clasificación operativa de la persona — piloto/auxiliar/jefe_campo/encargado_operaciones/dueño; **no confundir con `sec_role`**, que gobierna permisos y admite varios roles por usuario vía `sec_user_role` — `personas.rol` es una clasificación única de la persona, no autorización), `base_id` (FK a `per_bases`), `activo`.
   - **Diferido, no en esta migración**: `tarifa_ha` y `sueldo_mensual`. Son columnas de dinero (`DECIMAL`, invariante 6 de `CLAUDE.md`) que solo tienen sentido junto con la lógica de devengos/planilla — que `CLAUDE.md` marca explícitamente como algo que "no se delega sin revisión línea por línea" y que ningún ADR de máquina de estados cubre todavía. Agregarlas ahora sin esa lógica detrás es peso muerto y una invitación a que se usen antes de que el cálculo esté diseñado y revisado. Se agregan por `ALTER TABLE` cuando se implemente `Personal` en profundidad (tracked en `docs/gestion/estado_proyecto.md`).
   - Ambas tablas extienden `ModeloDominio` (soft delete + bitácora de autoría, ADR 0007) como todo modelo de dominio — no es específico de esta decisión, es la regla general.

## Alternativas descartadas

- **Solo prefijo global `agrocom_`**: identifica al sistema pero no dice nada del módulo dueño de cada tabla — que es justo la información que la regla 1 del ADR 0003 necesita hacer visible.
- **Prefijo compuesto en el nombre físico (`agro_sec_user`)**: verboso y redundante en una base dedicada; y fija en el DDL algo que el nivel de conexión resuelve por configuración.
- **`bases` como tabla transversal de `Compartido/`** (en vez de `per_bases` en `Personal`): se descarta porque `Compartido/` hoy es exclusivamente plataforma técnica sin tablas propias (`ModeloDominio`, `RegistraAutoria`, excepciones — ver `app/Dominios/Compartido/`), y `bases` es un concepto de negocio (dónde opera personal y equipos), no un mecanismo transversal. Que `Personal`, y a futuro `Mantenimiento`/`Inventario`/`Finanzas`, vayan a tener una columna `base_id` no es evidencia de transversalidad: es la regla 3 del ADR 0003 en acción ("las referencias cruzadas por ID están permitidas"), la misma forma que ya tiene `com_campos.cliente_id` apuntando a `com_clientes`. Meter `bases` en `Compartido/` sentaría el precedente equivocado — la siguiente tabla referenciada por tres módulos "merecería" lo mismo, y `Compartido/` dejaría de ser plataforma para ser un cajón de catálogos compartidos, lo que diluye la garantía de "Compartido no depende de nadie" (regla que solo tiene sentido si `Compartido/` se mantiene libre de conceptos de negocio).

## Consecuencias

- Toda migración que cree una tabla usa el prefijo del módulo que la escribe. Una migración de un módulo tocando tablas con prefijo ajeno es una violación de la regla 1 del ADR 0003, detectable a simple vista en el diff del PR (y verificable con tests de arquitectura).
- `config/database.php` cambia `'prefix' => ''` por `'prefix' => env('DB_TABLE_PREFIX', '')` en la conexión `pgsql`, y `.env.example` documenta `DB_TABLE_PREFIX=` vacío — es la única pieza de código de este ADR (la aplica el agente `backend` en la rama en curso).
- El prefijo global de Laravel se aplica automáticamente en Eloquent, query builder y schema builder, pero **no** dentro de SQL crudo: las migraciones de vistas por `DB::statement` (ADR 0012) deben interpolar `DB::getTablePrefix()` para no romper si algún día `DB_TABLE_PREFIX` deja de estar vacío.
- Los nombres físicos con prefijo se incorporan a la sección 4 de la especificación en su consolidación (pendiente de la reunión de cierre) — este ADR no modifica `docs/especificacion/`.
- Queda abierto el prefijo de las tablas transversales de `Compartido/` (p. ej. la bitácora de auditoría del ADR 0007): se define al implementarlas, ampliando la tabla de este ADR. Todo módulo nuevo registra aquí su prefijo antes de su primera migración.
- HU-01 crea `app/Dominios/Personal/` (`per_personas`, `per_bases`) y `app/Dominios/Seguridad/` (`sec_*`, ADR 0004) como primeros módulos nuevos desde ADR 0003; ambos quedan automáticamente cubiertos por `tests/Unit/ArquitecturaModulosTest.php` sin editar el test (descubrimiento por carpeta).
- `tarifa_ha` y `sueldo_mensual` de `personas` quedan fuera de la migración de HU-01 — anotado como pendiente en `docs/gestion/estado_proyecto.md` para que no se pierda antes de implementar devengos/planilla.

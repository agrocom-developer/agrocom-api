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
   | Compartido (tablas transversales de plataforma) | `plt_` (asignado en la extensión 31/8/2026, tarea 06 — ver `plt_bitacoras`, ADR 0007) |
   | Distribucion | `dis_` (asignado en la extensión 1/9/2026, tarea 10 — ver `dis_versiones_apk`, HU-20) |

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

### Extensión (27/8/2026) — tabla de preferencia de usuario (tema/idioma) para HU-02, corrige el destino apuntado por ADR 0002 y ADR 0013

**Contexto de la extensión:** HU-02 necesita persistir, por usuario, la preferencia de tema de color (ADR 0002, punto 4) y de idioma (ADR 0013, punto 2, con español como único valor habilitado en v1). Ambos ADR dicen textualmente que esa preferencia "vive en el modelo de usuario del módulo `Identidad`, no en `sec_user`". Pero la extensión de este mismo ADR (26/8/2026, punto 4) ya había establecido que ningún documento vigente debe usar "Identidad" como nombre de módulo — es un residuo de la lista prosa de ADR 0003 que nunca se implementó, y el nombre canónico de todo lo `sec_*` es `Seguridad`. Esa reconciliación solo revisó ADR 0003 en su momento y no llegó a notar que ADR 0002 y ADR 0013 arrastran el mismo nombre para una decisión concreta que ahora, con HU-02, deja de ser hipotética. A esto se suma una segunda inconsistencia: ADR 0004 (aceptado, nunca corregido en este punto) lista `language`, `profile_pic_url` e `initial_path` como columnas propias de `sec_user` en su bloque de decisión — exactamente lo que ADR 0002/0013 dicen que no debe pasar ("ADR 0004 mantiene `sec_*` como RBAC puro"), una caracterización de ADR 0004 que su propio texto no sostiene. Esta extensión resuelve ambas cosas para que `backend` tenga un destino único, sin contradicción, antes de escribir la migración de HU-02.

7. **No se crea el módulo `Identidad`.** Persistir tema/idioma de un usuario no es un dominio de negocio con reglas, estados o ciclo de vida propio — el criterio real que el ADR 0003 exige para justificar un módulo —, es un atributo de personalización acoplado 1:1 a la cuenta que ya administra `Seguridad`. Es el espejo de la alternativa descartada "`bases` en `Compartido/`" de la extensión anterior: ahí se rechazó meter un concepto de negocio en una carpeta puramente técnica; acá se rechaza levantar una carpeta de dominio de negocio (`Contratos/`, `Aplicacion/`, `Dominio/`, `Infraestructura/`) para dos columnas sin lógica propia — pagar el impuesto de Clean Architecture ortodoxa que el Contexto del ADR 0003 explícitamente rechaza para un equipo de una persona.

8. **Nueva tabla `sec_user_preferencia` dentro de `Seguridad` — no columnas nuevas en `sec_user`.** Se descarta seguir el esquema literal de ADR 0004 (agregar `language`, `profile_pic_url`, `initial_path` directo en `sec_user`) porque mezclaría atributos de identidad/autorización (login, password, roles, `persona_id`/`contrato_id`) con atributos de personalización de panel que van a seguir creciendo. El espíritu de "`sec_*` como RBAC puro" que ADR 0002/0013 invocan —aunque erraron el nombre del módulo destino— es válido y se preserva con una tabla satélite del mismo módulo, mismo patrón ya usado para `sec_user_role`:
   - Prefijo: `sec_` (ya asignado a `Seguridad` en la tabla de este ADR).
   - Tabla: `sec_user_preferencia` — `id`, `user_id` (FK a `sec_user.id`, índice único: una preferencia por usuario), `tema` (`claro`/`oscuro`, CHECK en pgsql igual que `sec_user.type`, default `claro`), `idioma` (string corto, default `es` — único valor válido en v1 por ADR 0013; sin CHECK todavía para no migrar de nuevo cuando se habilite el segundo idioma), columnas de auditoría/soft delete de `ModeloDominio` (ADR 0007) como todo modelo de dominio.
   - Carpeta: `app/Dominios/Seguridad/Infraestructura/Eloquent/SecUserPreferencia.php`, migración junto a las demás de `sec_*`. `SecUser::preferencia(): HasOne` es una relación **dentro del mismo módulo** — no aplica la restricción de `belongsTo` cross-módulo del punto 5 de la extensión 26/8/2026 (esa restricción rige relaciones hacia modelos de *otro* módulo, no dentro de `Seguridad`).
   - Si hace falta un caso de uso para leer/escribir la preferencia, va en `Seguridad/Aplicacion/` (p. ej. `ActualizarPreferenciaUsuario`), invocado desde un controlador/Livewire del panel con `Auth::user()`. No hace falta contrato de lectura hacia otro módulo: el panel ya consume `Auth::user()` (una instancia de `SecUser`) directamente, igual que ya lo hace para el rol activo (ADR 0004, extensión 27/8/2026).

9. **ADR 0002 (punto 4) y ADR 0013 (punto 2) deben leerse con "`Seguridad`" y "`sec_user_preferencia`" donde dicen "`Identidad`".** No se reescribe su texto original —la decisión de fondo (theming por tokens, idioma con español único en v1) sigue vigente sin cambios—, pero ninguna migración debe crear `app/Dominios/Identidad/` a partir de esa lectura literal. Ambos ADR llevan una nota corta apuntando acá.

10. **ADR 0004 sigue vigente en todo lo demás de su esquema de `sec_user`.** Su listado de columnas queda desactualizado únicamente en **`language`**, que se resuelve vía `sec_user_preferencia.idioma` en lugar de una columna de `sec_user` (punto 8). `profile_pic_url` e `initial_path` quedan fuera del alcance de HU-02 (que es tema + idioma) y sin resolver por esta extensión — es una decisión pendiente y explícita para cuando alguna historia los implemente, no una inclusión tácita en `sec_user_preferencia` ni una confirmación de que siguen en `sec_user`.

### Extensión (31/8/2026) — prefijo de las tablas transversales de `Compartido/`, para la tarea 06 (bitácora de auditoría)

**Contexto de la extensión:** este mismo ADR dejaba abierto, en sus "Consecuencias", el prefijo de una futura tabla transversal de `Compartido/` — nombraba como ejemplo, sin resolverlo todavía, "la bitácora de auditoría del ADR 0007". La tarea 06 (`docs/gestion/cola_tareas.md`) implementa esa bitácora y necesita cerrar el punto antes de escribir su primera migración.

11. **Prefijo `plt_` ("plataforma") para las tablas transversales de `Compartido/`.** Fila agregada a la tabla de prefijos del punto 1 de este ADR. Primera (y hoy única) tabla: `plt_bitacoras` (ADR 0007, invariante 9 de CLAUDE.md). Se descarta `cmp_` por parecerse demasiado, a simple vista, a `com_` (Comercial) — justo el tipo de ambigüedad que la regla 1 del ADR 0003 quiere evitar en el esquema; y se descarta un prefijo específico de la bitácora (p. ej. `aud_`) porque `Compartido/` es plataforma en general, no solo auditoría, y una segunda tabla transversal futura (p. ej. un log de trabajos en cola propio, si alguna vez hiciera falta uno que no sea la tabla `jobs` de Laravel) debe caer bajo el mismo prefijo sin que este ADR necesite una fila nueva por cada pieza de plataforma.
12. **`plt_bitacoras` no sigue el molde de `ModeloDominio`.** No es una tabla de dominio de ningún módulo de negocio: es un libro de solo-inserción, sin `deleted_at` ni `created_by`/`updated_by` propios — el detalle completo y su porqué están en el docblock de su migración (`database/migrations/2026_08_31_100002_create_plt_bitacoras_table.php`) y en el ADR 0007 (nota del 31/8/2026). No se generaliza esta excepción a otras tablas: sigue siendo la regla, no la excepción, que toda tabla de dominio lleve soft delete y auditoría por fila (ADR 0007, invariante 8 de CLAUDE.md).

## Alternativas descartadas

- **Solo prefijo global `agrocom_`**: identifica al sistema pero no dice nada del módulo dueño de cada tabla — que es justo la información que la regla 1 del ADR 0003 necesita hacer visible.
- **Prefijo compuesto en el nombre físico (`agro_sec_user`)**: verboso y redundante en una base dedicada; y fija en el DDL algo que el nivel de conexión resuelve por configuración.
- **`bases` como tabla transversal de `Compartido/`** (en vez de `per_bases` en `Personal`): se descarta porque `Compartido/` hoy es exclusivamente plataforma técnica sin tablas propias (`ModeloDominio`, `RegistraAutoria`, excepciones — ver `app/Dominios/Compartido/`), y `bases` es un concepto de negocio (dónde opera personal y equipos), no un mecanismo transversal. Que `Personal`, y a futuro `Mantenimiento`/`Inventario`/`Finanzas`, vayan a tener una columna `base_id` no es evidencia de transversalidad: es la regla 3 del ADR 0003 en acción ("las referencias cruzadas por ID están permitidas"), la misma forma que ya tiene `com_campos.cliente_id` apuntando a `com_clientes`. Meter `bases` en `Compartido/` sentaría el precedente equivocado — la siguiente tabla referenciada por tres módulos "merecería" lo mismo, y `Compartido/` dejaría de ser plataforma para ser un cajón de catálogos compartidos, lo que diluye la garantía de "Compartido no depende de nadie" (regla que solo tiene sentido si `Compartido/` se mantiene libre de conceptos de negocio).
- **Crear `app/Dominios/Identidad/`** siguiendo el texto literal de ADR 0002/0013 (extensión 27/8/2026): contradice la reconciliación de nombres ya asentada en este mismo ADR (26/8/2026, punto 4) y no supera el criterio de "dominio de negocio real" del ADR 0003 — ver punto 7 de la extensión 27/8/2026.
- **Agregar `tema`/`idioma` como columnas de `sec_user`** siguiendo el esquema literal de ADR 0004 (extensión 27/8/2026): descartada porque diluye la frontera "RBAC puro" que ADR 0002/0013 ya querían proteger (con el módulo equivocado) y ata cada preferencia de UI futura a un `ALTER TABLE` sobre la tabla más sensible de `Seguridad`.

## Consecuencias

- Toda migración que cree una tabla usa el prefijo del módulo que la escribe. Una migración de un módulo tocando tablas con prefijo ajeno es una violación de la regla 1 del ADR 0003, detectable a simple vista en el diff del PR (y verificable con tests de arquitectura).
- `config/database.php` cambia `'prefix' => ''` por `'prefix' => env('DB_TABLE_PREFIX', '')` en la conexión `pgsql`, y `.env.example` documenta `DB_TABLE_PREFIX=` vacío — es la única pieza de código de este ADR (la aplica el agente `backend` en la rama en curso).
- El prefijo global de Laravel se aplica automáticamente en Eloquent, query builder y schema builder, pero **no** dentro de SQL crudo: las migraciones de vistas por `DB::statement` (ADR 0012) deben interpolar `DB::getTablePrefix()` para no romper si algún día `DB_TABLE_PREFIX` deja de estar vacío.
- Los nombres físicos con prefijo se incorporan a la sección 4 de la especificación en su consolidación (pendiente de la reunión de cierre) — este ADR no modifica `docs/especificacion/`.
- Queda abierto el prefijo de las tablas transversales de `Compartido/` (p. ej. la bitácora de auditoría del ADR 0007): se define al implementarlas, ampliando la tabla de este ADR. Todo módulo nuevo registra aquí su prefijo antes de su primera migración. **Resuelto (31/8/2026)**: el prefijo es `plt_` — ver la extensión de esa fecha y la fila agregada en el punto 1.
- HU-01 crea `app/Dominios/Personal/` (`per_personas`, `per_bases`) y `app/Dominios/Seguridad/` (`sec_*`, ADR 0004) como primeros módulos nuevos desde ADR 0003; ambos quedan automáticamente cubiertos por `tests/Unit/ArquitecturaModulosTest.php` sin editar el test (descubrimiento por carpeta).
- `tarifa_ha` y `sueldo_mensual` de `personas` quedan fuera de la migración de HU-01 — anotado como pendiente en `docs/gestion/estado_proyecto.md` para que no se pierda antes de implementar devengos/planilla.
- HU-02 crea `sec_user_preferencia` (`tema`, `idioma`) dentro de `app/Dominios/Seguridad/` — no un módulo `Identidad` nuevo (extensión 27/8/2026). Queda pendiente, sin resolver por esta extensión, el destino de `profile_pic_url` e `initial_path` (columnas que ADR 0004 también listaba en `sec_user`) para cuando alguna historia los implemente.

### Extensión (1/9/2026) — prefijo `dis_` para el módulo `Distribucion`, para HU-20

**Contexto de la extensión:** HU-20 ("autorizar versiones del APK desde el panel") no encaja en ningún módulo existente — no es identidad/permisos (`Seguridad`), no es negocio de fumigación (`Comercial`/`Operaciones`). Ninguna tarea anterior había reservado prefijo para esto. Detalle completo del análisis en `runs/10-diseno.md`.

13. **Prefijo `dis_` para el módulo `Distribucion`.** Fila agregada a la tabla del punto 1. Primera (y hoy única) tabla: `dis_versiones_apk` (versión SemVer, `version_code` de Android, ruta del `.apk` en el disco `r2`, estado `pendiente`/`autorizada`/`rechazada` — única fila `autorizada` a la vez). Extiende `ModeloDominio` (soft delete + auditoría, ADR 0007) como todo modelo de dominio.

### Extensión (3/9/2026) — reparto `man_`/`inv_` entre `Mantenimiento` e `Inventario`, para HU-40

**Contexto de la extensión:** este mismo ADR reservó `man_` (Mantenimiento) e `inv_` (Inventario) desde su versión original (punto 1), y su extensión del 26/8/2026 (punto 3) ya adelantó que `drones`, `baterías`, `vehículos` y `generadores` quedaban "para cuando existan los módulos `Mantenimiento`/`Inventario`". HU-40 ("administrar los vehículos con su asignación a base") es la primera tarea que crea uno de los dos módulos (`Mantenimiento`) — hace falta cerrar el reparto entre ambos antes de la primera migración `man_*`, en vez de decidirlo de nuevo en cada HU siguiente (HU-36 para Inventario, HU-37/HU-38 para planes/órdenes de mantenimiento).

14. **`Mantenimiento` (`man_`) es dueño de los equipos con desgaste que disparan alerta por umbral, y de sus planes/órdenes de mantenimiento.** Alcance: vehículos (`man_vehiculos`, esta tarea), baterías (siguiente en la cola), generadores, y más adelante los planes y órdenes de mantenimiento de HU-37/HU-38. `Dron` **no** se mueve a este módulo: sigue en `Operaciones` (`ope_drones`) porque ese precedente ya está integrado (tarea 20/36) y moverlo no tiene motivo de negocio — es deuda de nomenclatura, no una corrección de frontera.
15. **`Inventario` (`inv_`) es dueño de repuestos, stock y sus movimientos.** Exclusivo de HU-36, todavía sin ninguna tabla creada. No se reparte con `Mantenimiento`: un repuesto en stock no es un equipo con ciclo de desgaste propio, es la contraparte de inventario que una orden de mantenimiento consume.

Ambos módulos, cuando escriban su primera tabla, siguen la regla general de este ADR: prefijo de módulo en el nombre físico, `ModeloDominio` (soft delete + auditoría, ADR 0007) salvo excepción justificada, y FK plana sin `belongsTo` cross-módulo para toda referencia hacia otro módulo (p. ej. `man_vehiculos.base_id → per_bases.id`, mismo criterio que `fin_gastos.base_id`).

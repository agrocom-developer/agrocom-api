# ADR 0018 — Propiedad como nivel de terreno entre Cliente y Campo, alcance mixto de contrato, y Cliente persona física/jurídica

**Estado:** Reemplazada por [ADR 0020](0020-propiedad-lote-directo.md) (15/9/2026) · **Fecha:** 10/9/2026 · **Origen:** hallazgo de campo del dueño (caso "Gamelera", investigado en `docs/gestion/pendiente_delimitacion_campo_lotes.md`) + pedido de rediseño del modelo comercial (mensaje del 10/9/2026).

## Contexto

`docs/gestion/pendiente_delimitacion_campo_lotes.md` (10/9/2026) dejó registrado, contra el modelo de datos real, que **"Propiedad" y "Campo" son la misma cosa en todo el sistema hoy — 1:1, sin excepción**: `com_campos` tiene `cliente_id` directo, la especificación dice "un cliente tiene varios campos; cada campo tiene varios lotes" (dos niveles), y el propio dueño había pedido tres días antes renombrar el ítem de menú de "Campos y lotes" a "Propiedades" sin tocar el modelo.

El caso real que rompe ese supuesto: la propiedad "Gamelera" del cliente Bruno Macedo está dividida en **dos campos físicos** de 1500 ha cada uno, separados por una carretera — uno se compró primero y el segundo se unificó después bajo el mismo dueño. Cada campo tiene sus propios lotes, y en campañas divididas cada campo se fumiga como campaña separada. Hoy no hay ninguna columna que exprese "estos dos campos son la misma propiedad física": ni agrupador, ni `propiedad_id`, y el índice único `com_campos_nombre_unico (cliente_id, nombre)` obliga a inventarles nombres distintos ("Norte"/"Sur") sin que el sistema sepa que están emparentados.

Ese documento dejaba la decisión de modelado (¿entidad `Propiedad` nueva, o agrupador liviano sobre `Campo`?) como paso previo obligatorio antes de tocar migraciones o el editor de mapa. Este ADR la resuelve.

En la misma conversación, el dueño pidió además:

1. Que el **contrato** pueda pactarse por propiedad completa, por campo específico, o mixto (varias propiedades) hasta cubrir las hectáreas contratadas. Hoy `com_contratos` no tiene ningún vínculo a terreno — solo un `hectareas_contratadas` suelto, sin relación a qué tierra corresponde.
2. Que `Cliente` distinga persona física (unipersonal) de persona jurídica (sociedad, ej. "Frigosis"), pudiendo registrar los datos del dueño cuando el cliente es una sociedad.
3. Una limpieza de `database/migrations/`: varias tablas crecieron con migraciones `add_*` sucesivas después de su `create_*` original; se unifican en un único archivo limpio por tabla, aceptando el costo de un `migrate:fresh` (ver "Consecuencias").

## Decisión

### 1. `Propiedad` es una entidad nueva, no un agrupador liviano sobre `Campo`

`com_propiedades`: `id`, `cliente_id` (FK → `com_clientes`), `nombre`, `ubicacion` (se muda acá desde `com_campos`: la dirección/zona es de la propiedad, no de cada campo dentro de ella). Único parcial `(cliente_id, nombre)` entre activos, mismo patrón que ya tenía `com_campos`.

`com_propiedades.ubicacion` es la localidad física del predio — departamento, provincia, municipio o pueblo (ej. Cuatro Cañadas, Roboré, San Matías) —, texto libre igual que hoy en `com_campos.ubicacion`: no hay pedido de reporte por zona que justifique partirla en columnas o un catálogo, así que no se infiere esa estructura.

**Adenda del 14/9/2026 (HU-76, tarea 92):** el pedido explícito que faltaba para partir la ubicación en columnas ya existe — ronda de negocio del 13/9/2026 (`docs/negocio/observaciones_operaciones_comercial_2026-09-13.md`, fila HU-76): "Propiedad: Departamento/Municipio/Localidad/Coordenada", para ubicar la propiedad en el mapa y filtrar por zona. `com_propiedades` gana `departamento`, `municipio`, `localidad` (texto libre, mismo criterio que `ubicacion` — sigue sin haber pedido de catálogo cerrado de Bolivia) y `latitud`/`longitud` (`DECIMAL(9,6)`, nunca una sin la otra). Estas son COLUMNAS NUEVAS junto a `ubicacion`, que sigue viva sin reemplazo: `ubicacion` queda como referencia libre/histórica, y las columnas nuevas son las que se usan para filtrar por zona y ubicar en el mapa.

**Adenda del 16/9/2026 (rediseño de listado/formulario de Propiedades):** el pedido de catálogo cerrado que la adenda anterior daba por descartado ("sigue sin haber pedido de catálogo cerrado de Bolivia") llegó — el dueño tiene el dump real de división político-administrativa de Bolivia de otro sistema propio (departamentos, provincias, municipios) y pidió cerrarlo. `departamento`/`municipio` (texto libre) se REEMPLAZAN por FK a un catálogo cerrado nuevo, encadenado: `com_departamentos` → `com_provincias` → `com_municipios` (9 → 112 → 365 filas, sembradas desde ese dump por `GeografiaBoliviaSeeder`, IDs conservados 1:1 contra la fuente). `ubicacion` (texto libre histórico del punto 1 original) se elimina sin reemplazo: quedó completamente cubierta por la ubicación estructurada. `localidad` NO gana catálogo — sigue siendo texto libre, dentro del municipio elegido, porque no hay datos de esa granularidad (pueblo/cantón) en el dump.

Decisión de encaje (validada con el agente `arquitectura`): el catálogo vive en `Comercial` (`com_`), no en un módulo nuevo — es dato de referencia sin lógica de negocio ni ciclo de vida propio, mismo criterio que `per_bases` (ADR 0011), no el de `Campania` (ADR 0015): que otro módulo pueda querer `municipio_id` a futuro no es evidencia de transversalidad, es una FK cruzada más (ADR 0003 regla 3). Si con el uso real esto empieza a doler, se saca a un módulo `Geografia` propio con su propio ADR — hoy no hay evidencia de eso, solo plausibilidad.

De paso, `com_propiedades` gana dos columnas más, pedidas en la misma ronda:
- `color`: paleta curada (no hex libre, a propósito — un conjunto fijo evita que dos propiedades del mismo cliente terminen con tonos casi idénticos), para distinguir propiedades de distintos clientes en listados y mapas; sus lotes heredan el mismo color visualmente (leen `propiedad->color`, nunca duplicado en `com_lotes`). Valores en `App\Dominios\Comercial\Dominio\ColorPropiedad`, CHECK de Postgres espejado a esa misma lista.
- `hectareas`: superficie total DECLARADA de la hacienda completa (puede incluir terreno que nunca se fumiga, p. ej. ganadería) — casi nunca coincide con la suma de `lotes.hectareas` (eso es lo que se factura, invariante 6). Dato a mano, igual que `Lote::$hectareas`, nunca calculado del polígono dibujado en `geometria`: un trazo impreciso no puede hacerle decir al portal del cliente una superficie distinta a la de su título.

Latitud/longitud/`geometria` (agregadas en la adenda del 14/9) se mudan de dónde se editan: salen del formulario principal y pasan a una pantalla de mapa aparte (`/panel/propiedades/{propiedad}/mapa`), tal como este mismo ADR ya había señalado para el editor de perímetro del lote y como ADR 0020 dejó explícitamente pendiente ("el editor de mapa multi-polígono se construye en un feature aparte"). Esa pantalla también dibuja, como capas de referencia sin interacción, el límite de la propiedad y los lotes ya cargados en el editor de mapa de un Lote nuevo — para no dibujar un lote a ciegas ni superpuesto con uno existente.

`com_campos` pasa a colgar de `Propiedad`, no de `Cliente`: pierde `cliente_id`, gana `propiedad_id` (FK → `com_propiedades`) y `geometria` JSONB nullable — el perímetro del campo, mismo formato GeoJSON que ya usa `com_lotes.geometria`. Esto resuelve de paso el segundo pedido de `pendiente_delimitacion_campo_lotes.md`: delimitar el campo primero, y que el editor de mapa del lote muestre ese perímetro como capa de referencia visual (sin PostGIS, sigue siendo una capa de referencia, no una restricción validada en base — misma limitación ya documentada ahí).

Se descartó el agrupador liviano (`propiedad_id` autorreferenciado en `Campo`, o un `grupo` de texto libre) porque no resuelve dos necesidades reales: una dirección/ubicación propia del predio físico completo, y un nivel donde el contrato pueda pactar hectáreas sin tener que enumerar cada campo. Con un agrupador liviano, "toda la propiedad Gamelera" no es una fila referenciable — hay que enumerar sus campos uno por uno en cada lugar que hoy pensamos "propiedad".

**Vocabulario:** el código ya le decía "Propiedad" a `Campo` en varios lugares (`LotesController::propiedadesActivas()`, labels `campo_propiedad`, docblock de `Campo.php`) porque hasta ahora eran la misma cosa. Con la entidad nueva esas referencias quedan incorrectas y se renombran de vuelta a "Campo" — "Propiedad" se reserva para el nivel nuevo, consistente con cómo el dueño ya usaba las dos palabras por separado (ADR 0015 punto 6: *"por lote, campo y propiedad"*).

### 2. El contrato tiene alcance de terreno, y puede ser mixto

`com_contrato_alcances`: `id`, `contrato_id`, `propiedad_id`, `campo_id` (nullable: `NULL` = cubre toda la propiedad, con valor = cubre solo ese campo), `hectareas`. N filas por contrato — así se modela tanto "toda la propiedad X" como "el campo Norte de X más 200 ha de la propiedad Y", sin forzar una sola columna `campo_id`/`propiedad_id` en `com_contratos` que no podría representar la mezcla.

Guardas en el caso de uso (mismo patrón que `CampaniaDeOtroCliente` de ADR 0015, no CHECK cruzado): la propiedad debe ser del cliente del contrato; si hay `campo_id`, debe pertenecer a esa propiedad; la suma de hectáreas de los alcances no puede superar `hectareas_contratadas`.

### 3. `Cliente.tipo_persona`, y el dueño ya tiene dónde vivir

`com_clientes` gana `tipo_persona` (`fisica` | `juridica`). El dato del dueño de una sociedad **no necesita tabla nueva**: `com_cliente_contactos` ya tiene `tipo = 'dueno'` (`TipoContactoCliente::Dueno`, existente desde el diseño original de Comercial) con nombre/teléfono/email/observaciones — es exactamente "los datos del dueño cuando el cliente es una sociedad". Se descartó modelar al dueño como su propio `Cliente` vinculado: hoy no hay ningún caso de uso que necesite que el dueño se loguee o contrate por separado de la sociedad: sería resolver un problema que todavía no existe.

### 4. Migraciones: squash completo, con `migrate:fresh` como costo aceptado

Cada tabla que hoy tiene un `create_*` seguido de uno o más `add_*`/`agrega_*` se reescribe como un único `create_*` con la forma final, y los `add_*` intermedios se borran — no solo en las tablas de este rediseño, en toda la carpeta `database/migrations/`. Esto rompe la tabla `migrations` de cualquier entorno que ya corrió las versiones viejas, así que exige `migrate:fresh` en el Postgres del compose.

Es una excepción puntual, explícitamente autorizada por el usuario el 10/9/2026, a la regla general de que los datos cargados en esa base para pruebas no se borran: se pierden los datos demo actuales a cambio de arrancar con un historial de migraciones limpio, y se recargan los seeders de demo después.

## Consecuencias

**A favor**

- El caso Gamelera (y cualquier propiedad con más de un campo físico) queda representable de verdad, con geometría propia por campo.
- El contrato puede reflejar cómo se pacta en la realidad: una propiedad entera, un campo suelto, o una mezcla de varias propiedades.
- `Cliente` distingue persona física de jurídica sin inventar una segunda entidad para el dueño.
- La carpeta de migraciones queda con un archivo por tabla, legible de punta a punta.

**En contra, y asumido**

- Se pierden los datos demo del compose (excepción puntual a la memoria de no borrarlos).
- Radio de impacto mecánico pero real en Comercial: ~50 fixtures de test y 3 seeders demo que hoy crean `Campo` con `cliente_id` directo pasan a crear primero una `Propiedad`; media docena de lecturas/`whereHas` que navegaban `campo.cliente_id` en un solo salto pasan a navegar `campo.propiedad.cliente_id`.
- El formulario de Lote pasa de una cascada de 2 niveles (cliente → campo) a una de 3 (cliente → propiedad → campo) — más clics para cargar un lote nuevo, pero refleja la jerarquía real.

**Confirmado, sin impacto:** el protocolo de sincronización con la app de campo (`SyncController`, `CatalogoController`, `LecturaLotesEloquent`) no expone `cliente_id` de campo en ningún payload — solo `campo_id`/`lote_id` como enteros opacos. Este rediseño no toca el motor de sync.

## Alternativas descartadas

**Agrupador liviano sobre `Campo`** (`propiedad_id` autorreferenciado o `grupo` de texto libre) — ver punto 1: no da un nivel referenciable para ubicación propia ni para alcance de contrato "toda la propiedad" sin enumerar campos.

**`campo_id`/`lote_id` directos en `com_contratos`** en vez de tabla de alcance — no puede representar un contrato mixto (varias propiedades) ni parcial (una parte de las hectáreas de una propiedad), que es justamente el caso que el dueño pidió cubrir.

**Dueño como `Cliente` propio, vinculado a la sociedad** — se resuelve un problema que hoy no existe (nadie pidió que el dueño opere el sistema por su cuenta); `com_cliente_contactos` con `tipo = 'dueno'` ya cubre el dato que se pidió registrar.

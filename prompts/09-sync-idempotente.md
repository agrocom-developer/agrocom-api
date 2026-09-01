<!-- ciclo: critica=si turno-noche=1 descongela=tests rama=feature/sync-idempotente etapas=5 -->

# Tarea 09 — TE-05: `POST /api/sync` idempotente

## Qué hacer

Implementar el endpoint que recibe el lote de escritura offline (espec §2.1)
con idempotencia real en la base: `trabajo` y `sesión`, procesados registro a
registro, con `UNIQUE (uuid_cliente)` como mecanismo de deduplicación —
nunca un chequeo previo en código, que tiene condición de carrera.

Es el ítem de la lista "qué no delegar sin revisión línea por línea" de
`CLAUDE.md`. Se implementa igual, pero el PR queda en borrador
(`critica=si`, ya en la cabecera).

Cargá los skills `verificacion`, `dominio-backend`, `modelo-datos` y
`flujo-git-pr` antes de tocar código. Releé también
`tests/Unit/TransicionesEstadoTest.php` completo (no solo el nombre): ya fija
la convención de dónde vive la máquina de estados
(`Aplicacion/MaquinaEstados/<Entidad>`) y qué formas de escritura vigila
(`create`, `insert`, `update`, `fill`, `forceFill`, `upsert`, `updateOrCreate`,
...). Esta tarea es la primera que la cruza de verdad — hasta ahora ningún
módulo escribía un estado.

### Recorte de alcance (con su porqué, igual que hizo la 08 con Mezclas)

La espec (§4.3) describe `sesion` con `dron_id` y `captura_rc_id`. Ninguna de
las dos tiene destino hoy:

- `dron_id` → tabla `drones`, dueña de `Mantenimiento`/`Inventario`
  (prefijos `man_`/`inv_` reservados en ADR 0011, carpetas inexistentes). El
  ADR 0011 punto 3 ya decidió explícitamente posponerlo — no es una decisión
  nueva de esta tarea, es aplicar la que ya está tomada.
- `captura_rc_id` → tabla `evidencias`, que llega con TE-07 (Sprint 3).

`ope_sesiones` se crea **sin esas dos columnas**. No las agregues nullable
"por si acaso": el precedente del propio ADR 0011 (`tarifa_ha`/`sueldo_mensual`
diferidas hasta que la lógica que las usa exista) es explícito — una columna
sin lector real es peso muerto. Se agregan por `ALTER TABLE` el día que
`Mantenimiento`/`Inventario` y la cola de evidencias existan.

Tampoco entran en esta tarea (todas tienen su propia HU/TE más adelante,
ninguna bloquea el motor de sync en sí):

- `mezcla` y todo lo que cuelga de `Mezclas` — el módulo no existe (mismo
  motivo que 08).
- `condiciones`, `recargas`, `incidencias`, `actas`, `evidencias` — Sprint 3
  (HU-06 a HU-09, TE-07).
- Validación de sesión, `anula_a_id`, devengo — HU-14 (Sprint 4) y HU-16
  (Sprint 5). `ope_sesiones` no lleva `motivo_cierre`, `validado_por` ni
  `fecha_validacion` todavía: sin el flujo de validación esas columnas no
  tienen quién las escriba. **Esto también corrige la nota "Condicionadas"
  de `cola_tareas.md`**: decía que las invariantes 2 y 3 se enganchan
  "cuando el Sprint 2 traiga las tablas" — esta tarea trae `sesion`, pero
  sin el concepto de "validado" no hay nada que guardar todavía. El gate
  real sigue esperando a HU-14/HU-16; dejá la nota corregida (ver más
  abajo, "Actualizá la cola").
- `hectareas_validadas` y `motivo_observacion` de `trabajo` — dependen de la
  validación también.
- TE-04 (outbox del lado cliente) — vive en `agrocom-field`, otro repo.

Lo que SÍ entra, y por qué alcanza para el criterio de la tarea: `trabajo`
(`orden_id`, `lote_id`, `nro_aplicacion`, `hectareas_declaradas`, `estado`,
`inicio`/`fin`) y `sesion` (`trabajo_id`, `secuencia`, `piloto_id`,
`auxiliar_id` — ambos contra `per_personas`, que ya existe —,
`hectareas_declaradas`, `estado`, `inicio`/`fin`). Con esas dos entidades y su
relación alcanza para demostrar los tres criterios de TE-05: proceso registro
a registro, `ON CONFLICT` por `uuid_cliente`, y resolución de referencia por
UUID (`sesion` llega con el `uuid_cliente` de su `trabajo`, no con su id de
servidor — puede no tenerlo si el trabajo se creó en el mismo lote).

### Diseño que evita el problema difícil

El punto que puede inflar esta tarea es "qué pasa si `sesion` llega antes que
su `trabajo` en el arreglo" (la prueba pide `en desorden`). No hace falta
lógica de reintento entre lotes para resolverlo: el servidor no necesita
confiar en el orden de llegada. Agrupá el lote por tipo de entidad y aplicá
siempre en el mismo orden causal fijo (`trabajo` antes que `sesion`), sin
importar en qué orden vino el arreglo del cliente. Con eso, "en desorden" dejar
de ser un caso especial — es el mismo camino que "en orden", una vez agrupado.
Si una `sesion` referencia un `trabajo` que no está ni en la base ni en este
mismo lote, ese registro se responde `rechazado {motivo}` sin frenar el resto
— el cliente lo reintenta en el próximo push, cuando el trabajo ya exista.

`trabajo` y `sesion` son las dos tablas de un mismo módulo (`Operaciones`), así
que la resolución de la referencia por `uuid_cliente` es una consulta interna
del propio módulo — no cruza el límite de `Contratos/` (ADR 0003 regla 1: un
módulo lee y escribe sus propias tablas sin restricción entre sí).

### Piezas a construir

1. **Máquina de estados de `Operaciones`** para `trabajo` y `sesion`
   (`Dominio/EstadoTrabajo.php`, `Dominio/EstadoSesion.php`, tabla de
   transiciones y guardas en `Dominio/MaquinaEstados/`,
   `Aplicacion/MaquinaEstados/MaquinaEstadosTrabajo.php` y
   `...Sesion.php` — es la ÚNICA clase que puede crear/mutar el `estado`).
   Estados mínimos para esta tarea: `abierto`/`cerrado` (o el nombre que
   la sesión de implementación prefiera) — no hace falta más que eso, la
   apertura la trae el sync, el cierre real con hectáreas llega con HU-05.
2. **Migraciones** `ope_trabajos`, `ope_sesiones` — índice único parcial por
   `uuid_cliente` (`WHERE deleted_at IS NULL`, mismo patrón que
   `com_lotes`/`ope_ordenes_aplicacion`), auditoría + soft delete
   obligatorios (invariantes 8 y 9), `hectareas_declaradas` en `DECIMAL`
   (invariante 6). CHECK de rango solo bajo `DB::getDriverName() === 'pgsql'`.
   Con su test de esquema (patrón `EsquemaNucleoComercialTest`).
3. **Contrato de escritura** en `Operaciones/Contratos/` (síncrono, ADR 0003
   regla 2) que `Sincronizacion` invoca para aplicar un `trabajo` o una
   `sesion` — recibe un DTO primitivo, devuelve un resultado con los tres
   casos de la espec (`aplicado` / `duplicado` / `rechazado {motivo}`).
   Implementación Eloquent + `ServiceProvider` — mismo patrón que TE-06.
4. **Módulo `Sincronizacion`**: el caso de uso que agrupa el lote por tipo,
   procesa cada registro en su propia transacción (nunca el lote entero en
   una), arma la respuesta por registro. Controller + Request delgados,
   documentados con atributos OpenAPI (ADR 0014).
5. **Ruta** `POST /api/sync` dentro del mismo grupo `auth:sanctum` de
   `routes/api.php` (junto a `/ordenes` y `/sync/catalogo`).
6. `docs/api/openapi.yaml` regenerado (`composer openapi`).

## Cómo repartir las etapas

- **Etapa 1**: migraciones + máquina de estados de `Operaciones` (con sus
  tests de transición y de esquema), sin endpoint todavía.
- **Etapa 2**: contrato de escritura + implementación Eloquent +
  `ServiceProvider`, con resolución de referencia `sesion → trabajo` por
  `uuid_cliente` ya probada a nivel de caso de uso (sin pasar por HTTP).
- **Etapa 3**: módulo `Sincronizacion` (caso de uso + controller + request),
  ruta, openapi.
- **Etapa 4**: tests de comportamiento vía HTTP — lote nuevo aplicado,
  reintento exacto → `duplicado` sin fila nueva ni error, un registro
  inválido no bloquea el resto, `sesion` resuelve su `trabajo` aunque venga
  antes en el arreglo, `trabajo` inexistente → `rechazado` sin romper el
  lote.
- **Etapa 5**: el test de replay explícito de la espec — mismo lote 10 veces,
  en orden y en desorden parcial, estado final idéntico (mismas filas,
  mismos valores, ninguna hectárea duplicada) — y cierre de la cascada
  completa.

Es sugerencia, no contrato: si una etapa rinde más o menos, seguí el criterio
de la HU entera, no el de la lista.

## Qué NO hacer

- No crear `drones`, `Mantenimiento` ni `Inventario`. No crear `evidencias`
  ni tocar TE-07. Ver "Recorte de alcance".
- No implementar validación de sesión, `anula_a_id` ni devengo (HU-14/HU-16).
  No agregues `validado_por`/`fecha_validacion`/`motivo_cierre` a `sesiones`
  todavía.
- No tocar `Mezclas`, `receta`, `producto` (igual que la 08).
- No escribir un `estado` fuera de `Aplicacion/MaquinaEstados/` — el gate de
  invariante 7 ya está activo y corre en la cascada; si hace falta crear la
  primera fila con estado inicial, esa creación también pasa por el
  servicio de máquina de estados, no por un `Model::create()` suelto en el
  caso de uso de sync.
- No resolver "duplicado" con un `SELECT` previo al `INSERT` — condición de
  carrera. Apoyate en la restricción `UNIQUE` de la base y capturá la
  violación.
- No construyas lógica de reintento entre pushes para resolver referencias
  fuera de orden: agrupar por tipo y aplicar en orden causal fijo alcanza
  (ver "Diseño que evita el problema difícil").
- No reabras ADR 0003 ni ADR 0011 — ninguno de los dos necesita ampliarse
  para esta tarea (el patrón de contrato síncrono y los prefijos de tabla ya
  cubren lo que hace falta).

## Criterio de aceptación

`./bin/verify` (con y sin `--sin-assets`) → exit code 0. Más, explícito y
propio de esta tarea: un test que aplica el mismo lote de sync (al menos un
`trabajo` y una `sesion` que lo referencia por `uuid_cliente`) **10 veces, en
orden y en desorden parcial**, y verifica que el estado final de la base es
idéntico en todos los casos — mismas filas, mismos valores, ninguna
`hectareas_declaradas` duplicada ni sumada de más.

## Cierre obligatorio de cada etapa

`runs/09.estado` con una sola palabra: `PARCIAL` si avanzó y commiteó pero la
tarea sigue abierta, `OK` recién cuando las seis piezas de arriba están
completas y el test de replay pasa, `BLOQUEADA` si aparece una decisión que no
le corresponde a esta sesión. `runs/09.md` con qué se hizo y qué falta,
concreto. Al cerrar con `OK`, `runs/09.pr.md` con el título en la primera
línea y el cuerpo debajo.

## Commits

Agrupados por función, español, imperativo, el porqué antes que el qué, sin
`Co-Authored-By`: por ejemplo, un commit para la máquina de estados de
`Operaciones`, otro para las migraciones, otro para el contrato de escritura,
otro para el módulo `Sincronizacion` y su ruta, otro para los tests de
comportamiento, otro para el test de replay. Varios commits por PR es lo
esperado.

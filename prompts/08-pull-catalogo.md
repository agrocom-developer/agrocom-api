<!-- ciclo: critica=no turno-noche=1 descongela=tests -->
# Tarea 08 — TE-06: pull de catálogo con cursor (órdenes, lotes, personas)

Sesión nueva y aislada. Cargá los skills `verificacion`, `dominio-backend` y
`modelo-datos`.

## La historia

TE-06 (`docs/gestion/plan_sprints.md`, sprint 2): "Pull de catálogo con cursor
(órdenes, recetas, productos, lotes, personas) al abrir la app y al recuperar
señal". Criterio de negocio: "el piloto sale al lote con órdenes ya en el
dispositivo" — es lo que habilita HU-04 (ver órdenes offline).

La especificación (`docs/especificacion/especificacion_funcional_tecnica.md`,
§2.1, línea 59) fija el contrato: `GET /api/sync/catalogo?desde={cursor}` baja
catálogo con cursor por `updated_at`.

## Recorte de alcance respecto a TE-06 tal como está escrita

**Esta tarea cubre solo órdenes, lotes y personas.** Recetas y productos
quedan afuera: no existe ninguna migración ni modelo para `receta` ni
`producto`, y su módulo dueño (`Mezclas`, prefijo `mez_` — ya reservado en el
ADR 0011, punto 1, pero la carpeta nunca se creó) no existe todavía. Crear ese
módulo es trabajo de modelo de datos completo (tablas, tipos de dosis,
`receta_items` ordenados) que le toca a Mezclas cuando llegue su turno
(sprint 4, HU-10 en adelante) — no es una decisión pendiente, es que Mezclas
simplemente no está construido aún. No lo adelantes ni inventes un módulo
"provisorio" para recetas/productos.

No hace falta ADR para este recorte: el ADR 0011 ya resolvió el prefijo. Lo
único que corresponde es dejarlo anotado (ya está en `cola_tareas.md`) para
que la próxima vez que se aborde Mezclas se sepa que el pull de catálogo
necesita extenderse.

## Antes de escribir código

Este endpoint es la primera vez que el código materializa el patrón de
"contrato de lectura entre módulos" que el ADR 0003 (regla 2) y el ADR 0011
(punto 5) solo habían descrito en prosa, nunca implementado. Escribí en
`runs/08-diseno.md`, en no más de una carilla:

- Dónde vive cada pieza: el módulo nuevo `app/Dominios/Sincronizacion/`
  (prefijo `syn_`, ya reservado en el ADR 0011) para el endpoint HTTP y el
  caso de uso que arma la respuesta combinada; y, en cada módulo dueño
  (`Operaciones` para órdenes, `Comercial` para lotes, `Personal` para
  personas), un contrato de lectura en su propia carpeta `Contratos/` —
  interfaz + forma de dato primitiva (no el modelo Eloquent) — que
  `Sincronizacion` consume por inyección de dependencias.
- Por qué así: `tests/Unit/ArquitecturaModulosTest.php` ya prohíbe, por
  descubrimiento automático de carpetas, que `Sincronizacion` importe
  `Infraestructura\Eloquent` de otro módulo — el diseño con contratos no es
  una opción entre varias, es lo único que pasa ese test.
- El formato del cursor: qué campo(s) codifica, cómo evita repetir o saltear
  registros con el mismo `updated_at`, y qué pasa si `desde` viene vacío
  (primera sincronización).
- Qué significa "vigente" para cada entidad: para órdenes ya existe un
  `estado` (`emitida/vigente/consumida/vencida` —
  `app/Dominios/Operaciones/Infraestructura/Eloquent/OrdenAplicacion.php`);
  para lotes y personas, "vigente" es simplemente no borrado (soft delete).
  No inventes un estado nuevo.

Si algo de esto no te cierra (por ejemplo, si el patrón de contratos choca con
algo que no anticipaste), **PARÁ**: escribí `runs/08.estado` con `BLOQUEADA` y
la pregunta concreta.

## Qué hacer

1. Un contrato de lectura por módulo dueño (Operaciones, Comercial, Personal):
   algo como `listarModificadosDesde(cursor, limite)`, que devuelve los
   registros vigentes con `updated_at` mayor al cursor, ordenados de forma
   determinística (`updated_at`, y un desempate estable — el `id` alcanza) y
   paginados con un límite razonable (documentá el número que elijas).
2. El módulo `Sincronizacion` (`Aplicacion/` + `Infraestructura/Http/Controllers/Api/`):
   un caso de uso que llama a los tres contratos y arma la respuesta de
   `GET /api/sync/catalogo`, con un cursor de salida por sección (o uno global,
   documentá la elección) para que el siguiente pull sepa desde dónde seguir.
3. Ruta en `routes/api.php`, dentro del grupo `auth:sanctum` (mismo guard que
   ya usa `/api/ordenes`, HU-03).
4. Tests de comportamiento, no solo de forma:
   - Dos pulls sucesivos, el segundo con el cursor que devolvió el primero, no
     repiten ningún registro ya entregado.
   - Un registro modificado después del cursor aparece en el siguiente pull.
   - Un registro borrado (soft delete) o una orden no vigente no aparece.
   - `desde` vacío trae todo lo vigente (primera sincronización).

## Qué NO hacer

- No crees el módulo `Mezclas` ni tablas de `producto`/`receta` — ver recorte
  de alcance arriba.
- No implementes `POST /api/sync` ni nada de outbox/idempotencia de escritura
  — eso es la tarea 09 (TE-05), en su propia sesión, marcada crítica.
- No importes modelos Eloquent de un módulo desde otro — ni directamente ni
  "solo para leer". El contrato con forma de dato primitiva es la frontera.
- No toques `CLAUDE.md`, ADRs existentes (más allá de lo que ya dice el ADR
  0011 sobre este prefijo, que no hace falta tocar) ni `.claude/`.

## Criterio de aceptación

`./bin/verify` devuelve 0, con los tests del punto 4 del "Qué hacer" pasando
de verdad (no triviales) y `tests/Unit/ArquitecturaModulosTest.php` en verde
sin haber sido editado.

## Máximo de intentos

3.

## Commits

Agrupados por función: los contratos de lectura (uno por módulo dueño, o
todos juntos si son el mismo tipo de cambio); el módulo `Sincronizacion` y su
endpoint; la ruta; los tests. Español, imperativo, explicando el porqué.

## Cierre obligatorio

- `runs/08.estado`: `OK` o `BLOQUEADA`.
- `runs/08.md`: qué se implementó, el formato de cursor elegido y por qué,
  y que recetas/productos quedaron afuera (ya documentado arriba, pero
  confirmalo).
- `runs/08.pr.md`: título en la primera línea, cuerpo debajo.

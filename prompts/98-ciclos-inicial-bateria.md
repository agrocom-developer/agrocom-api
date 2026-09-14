<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/ciclos-inicial-bateria etapas=2 -->

# Tarea 98 — HU-83: ciclo inicial de batería y estado "en mantenimiento"

## Por qué esta tarea

`man_baterias.ciclos_acumulados` es hoy el único contador: mezcla "con
cuántos ciclos entró la batería al catálogo" con "cuántos lleva hoy", y
`ActualizarBateria` deja pisar ese valor libremente. El encargado necesita el
punto de partida separado del total corriente para llevar el historial real
— y un tercer estado (`mantenimiento`) para sacar una batería de servicio sin
darla de baja (retirada = definitivo, mantenimiento = temporal). No es
crítica: `EstadoBateria` es descriptivo libre, sin máquina de estados de
negocio (ver su propio docblock) — agregar un caso no gobierna ninguna
transición. Independiente de 85/94/95/96/97. La tarea 102 (HU-87, ciclo que
se incrementa solo al cerrar una recarga) depende de que ESTA tarea esté
integrada — no la adelantes ni la mezcles acá.

## Lo que ya existe

- `man_baterias` (`database/migrations/2026_09_03_200002_create_man_baterias_table.php`):
  `identificador`, `ciclos_acumulados` (unsigned int, default 0, `CHECK >=
  0`), `estado` (string 20, default `activa`, `CHECK IN ('activa',
  'retirada')` solo en pgsql), `base_id` nullable.
- `EstadoBateria` (`app/Dominios/Mantenimiento/Dominio/EstadoBateria.php`):
  enum de dos casos, `Activa`/`Retirada`. Su docblock aclara explícitamente
  que NO es una máquina de estados de negocio — cualquier valor pasa a
  cualquier otro sin guarda.
- `CrearBateria`/`ActualizarBateria` (`Aplicacion/`): ambas reciben
  `int $ciclosAcumulados` y lo asignan directo al modelo — hoy no existe
  distinción entre "inicial" y "acumulado".
- `CrearBateriaRequest`/`ActualizarBateriaRequest`: `ciclos_acumulados` es
  `required|integer|min:0` en los dos; `estado` valida
  `Rule::enum(EstadoBateria::class)`.
- `lang/es/mantenimiento.php`: bloque `estado_bateria` (línea ~24, namespace
  propio para las etiquetas del enum) y bloque `baterias` (campos del
  formulario).
- Vista `baterias/_formulario.blade.php`: campo numérico
  `ciclos_acumulados` (arranca en 0 en alta, editable), select de `estado`
  poblado desde `$estados` (`EstadoBateria::cases()`, ver
  `BateriasController`).

## Qué hacer

Cargá los skills `verificacion` y `modelo-datos`.

1. **Migración `ALTER man_baterias`**: `ciclos_inicial` (unsigned int,
   default 0, `after('identificador')` o donde tenga sentido), con `CHECK
   ciclos_inicial >= 0` (solo pgsql, mismo patrón condicional que el resto de
   las migraciones de este repo). Actualizá el `CHECK` de `estado` para sumar
   `'mantenimiento'` (dropear y recrear el constraint, mismo patrón que
   `2026_09_13_100001_add_pausado_a_com_contratos_estado_chk.php` para el
   `CHECK` de `com_contratos.estado`).
2. **`EstadoBateria`**: sumá el caso `Mantenimiento = 'mantenimiento'`.
   Actualizá su docblock si hace falta — sigue sin ser máquina de estados.
3. **`CrearBateria`**: sumá el parámetro `int $ciclosInicial` (independiente
   de `ciclosAcumulados` — una batería puede entrar con uso previo en ambos:
   `ciclos_inicial` es el punto de partida, `ciclos_acumulados` puede
   arrancar igual o distinto si ya se usó desde que se detectó).
4. **`ActualizarBateria`**: **NO** agregues `ciclos_inicial` a su firma — es
   inmutable después del alta, ese es el criterio de aceptación central de
   esta HU. Si el Request de actualización llegara a incluir el campo
   igual (por reusar el mismo formulario), ignoralo en el caso de uso, no lo
   asignes al modelo.
5. **Los dos Request**: `CrearBateriaRequest` suma `'ciclos_inicial' =>
   ['required', 'integer', 'min:0']`. `ActualizarBateriaRequest` NO lo pide
   — si el formulario de edición lo muestra de solo lectura, no hace falta
   que viaje en el payload.
6. **`Bateria.php`** (Eloquent): sumá `ciclos_inicial` a `$fillable` y al
   docblock `@property`.
7. **Vista `_formulario.blade.php`**: en alta, un campo numérico
   `ciclos_inicial` junto a `ciclos_acumulados` (mismo estilo, arranca en 0).
   En edición, mostralo de solo lectura (sin `<input name="ciclos_inicial">`
   editable, o `disabled` sin `name` para que no viaje) — el valor ya
   quedó fijado al alta.
8. **`lang/es/mantenimiento.php`**: `estado_bateria.mantenimiento` = algo
   como "En mantenimiento"; campo/ayuda de `ciclos_inicial` en el bloque
   `baterias`.

## Qué NO hacer

- No conviertas `EstadoBateria` en una máquina de estados con guardas — el
  propio docblock del enum dice que no aplica CLAUDE.md invariante 7 acá, y
  esta tarea no cambia ese criterio.
- No toques `ciclos_acumulados` ni su semántica actual (sigue editable a
  mano en `ActualizarBateria`) — eso es exactamente lo que corrige la tarea
  102 (HU-87), que depende de que esta tarea esté integrada primero. No la
  adelantes.
- No hagas `ciclos_inicial` editable desde `ActualizarBateria` bajo ningún
  camino (ni Request, ni caso de uso, ni vista con `name` activo).

## Cómo repartir las etapas

- **Etapa 1**: migración (`ALTER` + `CHECK` de `estado` actualizado),
  `EstadoBateria`, `CrearBateria`/`Bateria.php`, `CrearBateriaRequest`, tests
  de caso de uso y de request.
- **Etapa 2**: vistas (alta editable, edición de solo lectura),
  `lang/es/mantenimiento.php`, test de integración de que
  `ActualizarBateria` nunca pisa `ciclos_inicial`, `bin/verify`.

## Criterio de aceptación

- `./bin/verify` = 0.
- Test: dar de alta una batería con `ciclos_inicial` y `ciclos_acumulados`
  distintos guarda ambos valores correctos.
- Test: `ActualizarBateria::ejecutar()` con cualquier `ciclos_acumulados`
  nuevo NO modifica `ciclos_inicial` de la batería (leela de nuevo después
  del `update` y comparalo contra el valor original).
- Test: transición al estado `mantenimiento` aceptada por el `CHECK`
  actualizado (alta o edición con `estado = mantenimiento` no rechaza a nivel
  de base de datos).
- Test de regresión: los dos estados existentes (`activa`/`retirada`) siguen
  aceptándose igual que antes.

## Puede tocar

`app/Dominios/Mantenimiento/**`, migración `ALTER man_baterias` +
`CHECK` nueva, `lang/es/mantenimiento.php`, `tests/**`.

## Cierre obligatorio de cada etapa

`runs/98.estado`, `runs/98.md`, y al `OK` `runs/98.pr.md`. Commits agrupados
por función, español, imperativo, sin `Co-Authored-By`.

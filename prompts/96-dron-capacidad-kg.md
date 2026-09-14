<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/dron-capacidad-kg etapas=1 -->

# Tarea 96 — HU-81: capacidad de un dron en kilos

## Por qué esta tarea

HU-79 (tarea 95, tipo sólido/líquido en la orden) va a necesitar saber cuánto
puede llevar un dron por vuelo cuando la orden es sólida — hoy `ope_drones`
solo tiene `capacidad_l` (litros, HU-27). Sin fila propia hasta esta tarea, un
dron que aplica sólido no tiene dónde guardar su capacidad. No es crítica: es
un ALTER puramente aditivo sobre un catálogo ya existente, sin tocar ninguna
guarda de negocio ni el motor de sync. No depende de la tarea 95 ni de la
85/94 (no toca `ope_ordenes_aplicacion`) — corre indistintamente de cómo estén
esas ramas.

## Lo que ya existe

- `ope_drones` (`identificador`, `modelo` nullable, `capacidad_l` nullable
  `decimal(5,2)` con `CHECK IN (30,50,60)` solo en pgsql — ver
  `database/migrations/2026_09_02_100004_add_modelo_capacidad_a_ope_drones_table.php`).
- `Dron` (`app/Dominios/Operaciones/Infraestructura/Eloquent/Dron.php`):
  `fillable` + cast `capacidad_l => decimal:2`.
- `CrearDron`/`ActualizarDron` (`Aplicacion/`): reciben `capacidadL` como
  string y lo pasan directo al modelo.
- `CrearDronRequest`/`ActualizarDronRequest`: `capacidad_l` valida
  `Rule::in([30, 50, 60])` — ese catálogo cerrado es específico de litros, NO
  lo repitas para kilos.
- `DronesController::store()`/`update()`: arman los parámetros posicionales
  con `cadenaONull($datos['capacidad_l'] ?? null)`.
- Vista `drones/_formulario.blade.php`: tres campos planos (`identificador`,
  `modelo`, `capacidad_l` con `min="30" max="60" step="1"`), sección con
  `campos_contador` en 3.
- Vista `drones/index.blade.php`: columna única `col_capacidad` que muestra
  `capacidad_l` en litros o `sin_capacidad`.
- `lang/es/operaciones.php` (bloque `drones`, línea ~307 en adelante):
  `campo_capacidad`/`campo_capacidad_ayuda`/`col_capacidad`/`capacidad_valor`/`sin_capacidad`.

## Qué hacer

Cargá los skills `verificacion` y `modelo-datos`.

1. **Migración `ALTER ope_drones`**: `capacidad_kg` `decimal(6,2)` nullable,
   `after('capacidad_l')`. **Sin `CHECK` de valores fijos** — a diferencia de
   `capacidad_l`, la espec (`REcursos.docx`, Sprint 17) dice explícitamente
   que no hay un catálogo cerrado de kilos conocido todavía. La validación de
   "positivo" va en el Request (`numeric`, `min:0.01`), no en la base.
2. **`Dron.php`**: sumá `capacidad_kg` a `$fillable`, al cast
   (`decimal:2`) y al docblock de `@property`.
3. **`CrearDronRequest`/`ActualizarDronRequest`**: `'capacidad_kg' =>
   ['nullable', 'numeric', 'min:0.01']`.
4. **`CrearDron`/`ActualizarDron`**: sumá el parámetro `?string $capacidadKg`
   (mismo criterio que `$capacidadL`, string sin castear a decimal en la capa
   de aplicación) y pasalo al modelo.
5. **`DronesController::store()`/`update()`**: sumá
   `$this->cadenaONull($datos['capacidad_kg'] ?? null)` al llamado de
   `ejecutar()`, en el mismo orden que agregaste el parámetro.
6. **Vista `_formulario.blade.php`**: un cuarto campo numérico
   `capacidad_kg`, sin `min`/`max` fijos (`min="0"`, `step="0.01"` alcanza),
   actualizá `campos_contador` de 3 a 4.
7. **Vista `index.blade.php`**: el dron puede tener litros, kilos, ambos o
   ninguno — no reemplaces la columna de litros, sumá cómo se ve el kilaje
   (columna nueva o mismo `<span>` con las dos cifras, a tu criterio de
   legibilidad).
8. **`lang/es/operaciones.php`**: claves nuevas para el campo, la ayuda, la
   columna del listado y el valor formateado (mismo patrón que
   `capacidad_valor`/`sin_capacidad`, pero para kilos).

## Qué NO hacer

- No le pongas `Rule::in()` ni `CHECK` de valores fijos a `capacidad_kg` — es
  la diferencia explícita de esta HU contra `capacidad_l`.
- No toques `capacidad_l` ni su `CHECK` existente.
- No conviertas esto en una decisión de "sólido vs. líquido" a nivel de
  dron — esa clasificación es de la orden (HU-79, tarea 95, todavía
  bloqueada); acá solo agregás el dato numérico.

## Cómo repartir las etapas

Una sola etapa: migración, los dos Request, los dos casos de uso, el
controlador, las dos vistas, `lang`, tests. Es medio día de trabajo real
(HU-81 en `plan_sprints.md` la estima en 0,5 d).

## Criterio de aceptación

- `./bin/verify` = 0.
- Test: guardar y leer `capacidad_kg` desde el alta y desde la edición de un
  dron.
- Test de regresión: un dron sin `capacidad_kg` (`NULL`) sigue operando
  líquido sin cambios — alta/edición con solo `capacidad_l` sigue
  funcionando igual que antes de esta tarea.
- Test: `capacidad_kg` negativo o cero se rechaza (422) en alta y edición.

## Puede tocar

`app/Dominios/Operaciones/**`, migración `ALTER` nueva sobre `ope_drones`,
`lang/es/operaciones.php`, `tests/**`.

## Cierre obligatorio de cada etapa

`runs/96.estado`, `runs/96.md`, y al `OK` `runs/96.pr.md`. Commits agrupados
por función, español, imperativo, sin `Co-Authored-By`.

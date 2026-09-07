<!-- ciclo: critica=si turno-noche=1 descongela=tests rama=feature/campania-activa etapas=4 -->

# Tarea 69 — HU-46: la campaña como eje del sistema, con campaña activa por sesión

## Por qué esta tarea

El sistema nunca tuvo corte temporal. Tres lugares del código lo dicen solo:

- `ObtenerAvanceComercial` (HU-32) se escribió con el enunciado "avance por
  cliente, contrato **y campaña**" y entregó las dos primeras.
- `fin_gastos` y `fin_combustibles` tienen docblocks que dicen literalmente
  "para que la campaña tenga costo real" y "para imputarlo a la campaña" —
  imputan a algo que no existe como tabla.
- La tarea 67 retiró el chip del header con el comentario: *"el dominio no
  tiene el concepto de campaña en ninguna tabla"*.

El dueño lo pidió el 7/9/2026: *"Campaña 2025-2026 similar a la gestión. Todo
se basa en una campaña"*. El diseño completo, con las alternativas
descartadas, está en **ADR 0015** — leelo entero antes de escribir código, es
la fuente del alcance de esta tarea junto con `plan_sprints.md` HU-46.

**Es crítica**: agrega una columna obligatoria a `com_contratos` y `fin_gastos`
(tablas de dinero) con migración de datos, y crea una máquina de estados nueva.
Revisión posterior a la integración, anotada en `runs/revision-pendiente.txt` —
el PR **no** se retiene (`CLAUDE.md`, "qué no delegar").

## Lo que ya existe

- **El molde exacto del rol activo**, que es lo que hay que espejar:
  `Seguridad/Aplicacion/ElegirRolActivo.php`,
  `Seguridad/Infraestructura/Http/Middleware/ResolverRolActivo.php`,
  `Seguridad/Infraestructura/Http/Controllers/Web/RolActivoController.php`.
  Leelos: la campaña activa se resuelve, se guarda y se cambia igual.
- **El hueco del chip**: `CascaraPanel::para()` devuelve `'campana' => null`
  con el comentario de la tarea 67. `resources/views/components/organisms/topbar.blade.php`
  (línea ~136) ya tolera `null` y pinta el chip cuando llega texto.
- **Máquinas de estado de referencia**: `Comercial/Aplicacion/MaquinaEstados/MaquinaEstadosContrato.php`
  (tabla de transiciones + guardas de datos) y `Comercial/Dominio/MaquinaEstados/TransicionesContrato.php`.
  Copiá esa estructura, no inventes otra.
- `ModeloDominio` (`Compartido/Infraestructura/Eloquent/`) da soft delete +
  autoría + bitácora a todo modelo de dominio (invariantes 8 y 9).

## Qué hacer

1. **Módulo `app/Dominios/Campania/`** con las cuatro capas (`Contratos/`,
   `Aplicacion/`, `Dominio/`, `Infraestructura/`) y su `ServiceProvider`
   registrado, con `View::addNamespace('campania', ...)` como el resto.
2. **Migración `cpn_campanias`**: `codigo` (string 20), `nombre` (nullable),
   `fecha_inicio`, `fecha_fin`, `estado` (default `planificada`), + auditoría y
   soft delete. Índice único **parcial** sobre `codigo` entre filas activas
   (`WHERE deleted_at IS NULL`), como `com_campos_nombre_unico`. `CHECK` de
   `estado IN ('planificada','abierta','cerrada')` y de `fecha_fin >=
   fecha_inicio`, solo en pgsql (SQLite no soporta `ADD CONSTRAINT`).
   **El prefijo es `cpn_`, no `cmp_`** — el ADR 0011 descartó `cmp_` por
   parecerse a `com_`.
3. **Máquina de estados** `planificada → abierta → cerrada`, sin vuelta atrás
   desde `cerrada`, en un servicio de dominio propio (invariante 7). Guarda de
   datos al abrir: no puede haber otra campaña `abierta` cuyo rango de fechas
   se solape — reusá el molde de `ValidadorSolapamientoVentanas`.
4. **ABM de campañas** en el panel (`/panel/campanias`), con su permiso
   `campania.campania.{ver,crear,editar,cambiar_estado}` sembrado en
   `SeguridadSeeder` y su ítem en `SecMenuSeeder`. Solo el dueño cierra una
   campaña.
5. **Campaña activa por sesión**: middleware `ResolverCampaniaActiva`, caso de
   uso `ElegirCampaniaActiva`, controlador para cambiarla, y el chip del header
   con la campaña real. Default al iniciar sesión: la campaña `abierta` cuyo
   rango contiene hoy; si no hay ninguna, la última `abierta` por
   `fecha_inicio`; si tampoco, `null` y el chip no se pinta.
   **La campaña activa filtra, no autoriza** — no toca permisos.
6. **`campania_id` en `com_contratos` y `fin_gastos`**, por `ALTER TABLE`, FK
   real + entero plano (ADR 0003 regla 3, sin `belongsTo` cross-módulo).
   Migración de datos en la misma migración: crear la campaña `2025-2026`
   (`abierta`, 1/7/2025 a 30/6/2026) y asignarle **todas** las filas
   existentes; recién después poner la columna `NOT NULL`.
7. **Guarda de campaña cerrada**: `CrearContrato` y `CrearGasto` rechazan si la
   campaña destino está `cerrada`, con excepción de dominio propia y mensaje
   traducido.
8. **Renombre de vocabulario** (ADR 0015 punto 2): la prop `campana` del chrome
   pasa a `campaniaActiva`, y la constante `ALERTAS_CAMPANA` de `CascaraPanel`
   a `ALERTAS_NOTIFICACION`. `campana` queda solo para el ícono de
   notificaciones. Actualizá `topbar.blade.php`, `mobile-topbar.blade.php` y
   todas las vistas que pasan `:campana=`.
9. **Filtro por defecto**: los listados de contratos y de gastos filtran por la
   campaña activa, con la opción de ver todas. No inventes filtro donde la
   pantalla no lo tenga hoy.
10. **Seeder** de la campaña `2025-2026` para que `migrate --seed` deje el
    panel usable, idempotente.

## Qué NO hacer

- **No agregues `campania_id`** a `ope_ordenes_aplicacion`, `ope_trabajos`,
  `ope_sesiones`, `ope_actas` ni `com_facturas`. Cuelgan de un contrato que ya
  la tiene, y duplicarla hace representable "trabajo de la campaña A en un
  contrato de la campaña B" (ADR 0015 punto 1).
- No toques `fin_combustibles`, `per_*` ni el motor de sync: son las tareas 72,
  73 y 74.
- No hagas que la campaña activa cambie permisos ni menús.
- No agregues un booleano `es_actual` a `cpn_campanias`: la campaña activa es
  de sesión, no de tabla.

## Cómo repartir las etapas

- **Etapa 1**: módulo, migración, modelo, máquina de estados y sus tests
  unitarios (incluida la transición prohibida `cerrada → abierta`).
- **Etapa 2**: ABM + permisos + menú + vistas, con tests Feature.
- **Etapa 3**: campaña activa (middleware, cambio sin re-login, chip),
  renombre `campana → campaniaActiva`, tests.
- **Etapa 4**: `campania_id` en contratos y gastos con migración de datos,
  guardas de campaña cerrada, filtros por defecto, seeder, `bin/verify`.

## Criterio de aceptación

- `./bin/verify` = 0. En este Mac se corre `./bin/verify --sin-assets`
  (Playwright siempre falla acá: las capturas son win32).
- Test: `cerrada → abierta` lanza la excepción de transición no permitida.
- Test: abrir una campaña cuyo rango se solapa con otra `abierta` se rechaza.
- Test: cambiar la campaña activa cambia el listado de contratos en el request
  siguiente, **sin** volver a loguearse y **sin** cambiar los permisos
  efectivos del rol activo.
- Test: crear un gasto contra una campaña `cerrada` se rechaza.
- Test de migración: con contratos y gastos preexistentes, `migrate` deja a
  todos con la campaña `2025-2026` y ninguna fila con `campania_id` nulo.
- `grep -rn "'campana'" app/ resources/` solo devuelve el ícono de
  notificaciones, nunca el chip de campaña.

## Puede tocar

`app/Dominios/Campania/**` (nuevo), `app/Dominios/Seguridad/**` (chrome,
middleware, seeders de permisos y menú), `app/Dominios/Comercial/**`
(contrato: columna, guarda, filtro), `app/Dominios/Finanzas/**` (gasto: ídem),
`database/migrations/**`, `database/seeders/**`, `resources/views/components/organisms/**`
(topbar), `lang/es/**`, `routes/web.php`, `tests/**`.

Fuera de alcance: `fin_combustibles`, `per_*`, `ope_*`, el motor de sync.

## Cierre obligatorio de cada etapa

`runs/69.estado`, `runs/69.md`, y al `OK` `runs/69.pr.md`. Anotá la tarea en
`runs/revision-pendiente.txt` (es crítica). Commits agrupados por función, en
español, imperativo, sin `Co-Authored-By`.

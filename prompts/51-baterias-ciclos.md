<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/baterias-ciclos etapas=4 -->

# Tarea 51 — HU-39: baterías con ciclos y estado

## Por qué esta tarea

`plan_sprints.md` Sprint 11 (§237): "Como encargado, quiero seguir las
baterías con sus ciclos y estado, para retirarlas antes de que fallen en
vuelo." CA esencial: ABM con ciclos acumulados; alerta por ciclos o por
temperatura registrada en recargas. Sigue a la tarea 50 (HU-40,
vehículos), que ya creó el módulo `Mantenimiento` — esta tarea **no**
crea el módulo, lo extiende.

**No es crítica**: ABM con una lectura cross-módulo de solo consulta, sin
dinero ni transición de estado gobernada.

**Confirmá primero que `app/Dominios/Mantenimiento/` ya existe** (lo crea
la tarea 50). Si no existe todavía, es `BLOQUEADA` — no lo crees vos ni
adivines su forma; el orden de `runs/cola.txt` garantiza que la 50 corrió
antes.

## El cruce con `ope_recargas` — leé antes de decidir el modelo

`ope_recargas` (tarea 23, HU-13) ya guarda, por cada recarga puntual
durante una sesión de vuelo: `bateria_saliente_id` (**`string` de texto
libre, sin FK** — su propio docblock dice explícitamente que no hay
catálogo de baterías en el esquema, porque en ese momento no lo había),
`temperatura_bateria_c` y `alerta_temperatura` (booleano, calculado una
sola vez al insertar si la temperatura superó 50 °C —
`RegistroRecarga::TEMPERATURA_MAX_C`).

Ahora sí hay catálogo (`man_baterias`, esta tarea). Aun así:

- **No conviertas `ope_recargas.bateria_saliente_id` en FK real** contra
  `man_baterias.id`. Es una migración de datos sobre una tabla que ya
  tiene filas reales (incluida la demo, que no se borra) escritas por el
  motor de sync con texto libre — arriesgar esa columna por esta HU no
  es necesario para su CA esencial. Dejala como está.
- **Correlacioná por texto**: `man_baterias.identificador` (string) se
  compara contra `ope_recargas.bateria_saliente_id` para calcular la
  alerta de temperatura. Documentá en el docblock que es un matching por
  igualdad de texto, no una FK, y por qué.
- El acceso a `ope_recargas` desde `Mantenimiento` va por un **contrato
  de lectura nuevo** en `Operaciones/Contratos/` (ADR 0003 regla 2, nunca
  `belongsTo` cruzado) — mirá
  `app/Dominios/Personal/Contratos/LecturaTarifaPersona.php` como
  plantilla exacta de forma (interfaz chica, un método, doc explicando
  la frontera). Implementación en
  `Operaciones/Infraestructura/` (Eloquent), bindeada en
  `OperacionesServiceProvider` (mismo patrón que
  `LecturaTarifaPersona`/`LecturaTarifaPersonaEloquent` en
  `PersonalServiceProvider`), consumida desde `Mantenimiento` por la
  interfaz, nunca por el modelo Eloquent de `Operaciones`.

## No reuses `ope_alertas` (tarea 26)

Ya existe una alerta operativa de "batería caliente" en `ope_alertas`
(`GenerarAlertaExcepcion`, tarea 26) — es una excepción táctica por
sesión de vuelo, de otro dominio (`Operaciones`), y no rastrea baterías
como activo. La alerta de esta HU es de gestión de activo ("retirar la
batería"), calculada sobre `man_baterias` — **no** la persistas en
`ope_alertas` ni cruces a ella. Calculala al leer (en el listado/detalle
de baterías), sin tabla de alertas propia — mismo criterio que
`ope_drones` no necesitó una tabla de alertas para ser mínimo.

## Qué hacer

Cargá las skills `dominio-backend`, `modelo-datos`, `panel-design-ui` y
`verificacion`.

### 1. Modelo de datos

`man_baterias`: `id, identificador` (string(40), índice único **parcial**
`WHERE deleted_at IS NULL` — mismo patrón que `ope_drones.identificador`
y `man_vehiculos.identificador`), `ciclos_acumulados` (`integer`, default
`0`, `CHECK >= 0`), `estado` (`string(20)`, `CHECK` — p. ej. `activa` /
`retirada`; documentá tu elección), `base_id` (FK `per_bases`, nullable,
entero plano), auditoría, soft delete.

Umbral de alerta por ciclos: fijá una constante razonable (documentada,
con el mismo criterio que `RegistroRecarga::TEMPERATURA_MAX_C = 50.0`) —
no hace falta que sea configurable por lote/modelo para el CA esencial.

### 2. Caso de uso y ABM

`Mantenimiento/Aplicacion/`: `CrearBateria`, `ActualizarBateria`
(incluye editar `ciclos_acumulados` — a diferencia de `fin_gastos`, acá
sí se espera actualizar el contador con el uso), `EliminarBateria` (soft
delete), `ListarBaterias` (paginado, filtro por base/estado, con la
alerta calculada por fila: `ciclos_acumulados >= umbral` **o**
`LecturaAlertasTemperaturaBateria` devuelve `true` para ese
`identificador`).

### 3. HTTP, permisos, menú

`BateriasController@index/create/store/edit/update/destroy`. Permisos
`mantenimiento.bateria.ver`/`.crear`/`.editar`/`.eliminar` en
`PERMISOS_ENCARGADO_OPERACIONES`. Activá el ítem `baterias` ya sembrado
como "botón sin link" en el grupo `recursos` de `SecMenuSeeder.php` — no
crees uno nuevo.

## Qué NO hacer

- No alteres `ope_recargas.bateria_saliente_id` a FK real.
- No escribas ni cruces contra `ope_alertas`.
- No construyas una máquina de estados para el campo `estado`.
- No recrees el `ServiceProvider` ni las carpetas base de `Mantenimiento`
  (ya existen desde la tarea 50) — solo agregá.

## Cómo repartir las etapas

- **Etapa 1**: migración (`man_baterias`), modelo Eloquent, contrato
  `LecturaAlertasTemperaturaBateria` + su implementación en `Operaciones`.
- **Etapa 2**: casos de uso (incluida la alerta calculada en
  `ListarBaterias`).
- **Etapa 3**: controller, rutas, permisos, menú, vistas + copy.
- **Etapa 4**: tests Feature + spec visual + checklist §8.

## Criterio de aceptación

- `./bin/verify` = 0, con Playwright.
- Test Feature (`tests/Feature/Mantenimiento/GestionBateriasPanelTest.php`)
  cubriendo: alta y edición de ciclos válidas; `identificador` duplicado
  como 422; alerta activada cuando `ciclos_acumulados` cruza el umbral;
  alerta activada cuando existe una recarga con `alerta_temperatura=true`
  y `bateria_saliente_id` igual al `identificador` de la batería (sin
  alerta si no hay ninguna); bitácora completa; soft delete real; 403 sin
  permiso; ítem de menú publicado y gateado.
- Spec visual (`tests/Visual/baterias.spec.ts`), claro y oscuro.
- Checklist de `docs/diseno/guia_pantalla_panel.md` §8 pasa.
- `tests/Unit/ArquitecturaModulosTest.php` sigue en verde (el contrato de
  lectura nuevo no rompe el aislamiento entre módulos).

## Puede tocar

`app/Dominios/Mantenimiento/**`, `app/Dominios/Operaciones/Contratos/**`
(el nuevo contrato de lectura), `app/Dominios/Operaciones/Infraestructura/**`
(su implementación y el binding en `OperacionesServiceProvider`),
migración nueva, `routes/web.php`,
`database/seeders/Catalogo/SeguridadSeeder.php`,
`database/seeders/Catalogo/SecMenuSeeder.php`, `lang/es/mantenimiento.php`,
`tests/**`.

Fuera de alcance: `ope_recargas` (ninguna migración sobre esa tabla),
`ope_alertas`, `Inventario`, planes/órdenes de mantenimiento (HU-37/38,
sin prompt todavía).

---

**Nota de la planificación**: esta tarea asume que la 50 (HU-40,
vehículos) ya está integrada en `develop` cuando arranque — el orden de
`runs/cola.txt` lo garantiza. Si por algo excepcional `Mantenimiento` no
existe todavía, es `BLOQUEADA`, no crees el módulo por tu cuenta.

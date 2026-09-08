<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/equipos-trabajo etapas=4 -->

# Tarea 72 — HU-49: equipos de trabajo, con su gente y su equipamiento

## Por qué esta tarea

El dueño nombró una unidad que el modelo no tiene: *"equipo de trabajo son el
piloto y su auxiliar, no dónde están trabajando"*. Y agregó el porqué operativo:
*"como no sabemos en qué trabajo se cargan los gastos, ya el equipo está
asociado a equipos de inventario como ser vehículos, así sabemos qué vehículo
solicitó nuevo combustible, o en los generadores"*.

Hoy la idea existe **derivada**: `LecturaPanelOperaciones::equiposDePersonaDelMes()`
reconstruye "con quién trabajé" desde las sesiones ya voladas. Sirve para
mostrar en el tablero, no para imputar: no se le carga un gasto a una
derivación, no tiene equipamiento, y no existe antes de la primera sesión —
justo cuando se arma el equipo.

Diseño completo en **ADR 0015 punto 3**. Habilita la 73 (gastos) y la 74
(estadías). **No depende de la 69**: el equipo es de Agrocom y no lleva
`campania_id` (corrección del 8/9/2026).

## Lo que ya existe

- `per_personas` (`nombre`, `rol` con `CHECK` de piloto/auxiliar/jefe_campo/
  encargado_operaciones/dueno, `base_id`, `activo`) y `per_bases`.
- `ope_drones` (`identificador`, `modelo`, `capacidad_l`) y `man_vehiculos`
  (`identificador`, `base_id`, `estado`). **No existe tabla de generadores.**
- `Comercial/Dominio/ValidadorSolapamientoVentanas.php` — el molde exacto para
  validar solapamiento de rangos; el de integrantes es el mismo problema con
  fechas en vez de horas.
- `Personal/**` con su `ServiceProvider` y namespace de vistas `personal`.

## Qué hacer

1. **`man_generadores`** (`identificador`, `modelo` nullable, `base_id`
   nullable, `estado` default `activo`, `horas_uso` nullable), con su ABM
   mínimo y permisos. **No es HU propia y no debe crecer**: es una tabla de
   catálogo cuyo único consumidor hoy es la asignación al equipo, y sin ella no
   hay generador que asignar (así está justificado en el ADR 0015).
2. **`per_equipos_trabajo`**: `codigo` (string 20), `nombre` (nullable),
   `base_id` (FK a `per_bases`), `estado` (`activo`/`inactivo`, default
   `activo`), `desde`, `hasta` (nullable = vigente), + auditoría y soft delete.
   Índice único parcial sobre `codigo` entre filas activas.
   **Sin `campania_id`** (ADR 0015 punto 3, corregido el 8/9/2026): la campaña
   es del cliente y el equipo trabaja para varios en la misma semana. Atarlo a
   una campaña obligaría a duplicar la cuadrilla por cliente, y el gasto de la
   camioneta no sabría a cuál de esas copias imputarse.
   **`per_equipos_trabajo`, no `per_equipos`**: la especificación §4.5 reserva
   `equipos` para la vista unificada de maquinaria.
3. **`per_equipo_integrantes`**: `equipo_trabajo_id`, `persona_id` (FK a
   `per_personas`), `rol_equipo` (`CHECK IN ('piloto','auxiliar')`), `desde`
   (date), `hasta` (date nullable = vigente), + auditoría y soft delete.
   `CHECK (hasta IS NULL OR hasta >= desde)`.
4. **`per_equipo_recursos`**: `equipo_trabajo_id`, `recurso_tipo`
   (`CHECK IN ('dron','vehiculo','generador')`), `recurso_id` (entero),
   `desde`, `hasta` (nullable), + auditoría y soft delete.
   **Sin FK**, a propósito: el destino depende del tipo (ADR 0015 punto 3, es
   la única excepción declarada a "FK real"). La integridad la sostiene el caso
   de uso al asignar —verificar que el recurso existe y está activo— y un test
   la cubre. Escribí ese porqué en el docblock de la migración.
5. **Aviso de solapamiento, NO bloqueo** (`Personal/Dominio/`, molde de
   `ValidadorSolapamientoVentanas` pero devolviendo advertencia, no excepción).
   Corrección del dueño del 7/9/2026: *"ese personal puede realizar diferentes
   trabajos, ya sea en propiedades diferentes, campañas diferentes, equipos
   diferentes, lotes ajenos y todo, porque en caso de que no hubiera personal se
   acoplará el que se tiene disponible"*. **La pertenencia a un equipo no es
   exclusiva**: la operación real presta gente entre cuadrillas cuando falta.
   - Persona ya vigente en otro equipo esa fecha → se muestra el aviso con los
     equipos en cuestión y **se guarda igual**.
   - Recurso ya asignado a otro equipo esa fecha → mismo criterio, aviso y se
     guarda. Un dron prestado entre cuadrillas es normal.
   - Lo único que sí se rechaza es duplicar **la misma** persona o **el mismo**
     recurso dentro **del mismo** equipo con vigencias que se pisan: eso no es
     flexibilidad, es la misma fila dos veces.
6. **ABM de equipos** en el panel: listado con filtro por estado y por base, alta, edición,
   y una ficha que muestre integrantes y recursos **vigentes a una fecha**
   (selector de fecha, default hoy) — la ficha tiene que poder responder
   "quiénes lo integraban el 14 de marzo". Permisos
   `personal.equipo_trabajo.{ver,crear,editar,eliminar}` + ítem de menú.
7. **Contrato de lectura** `Personal/Contratos/LecturaEquipoTrabajo` (interfaz +
   DTO primitivo) con al menos: equipos vigentes de una campaña, integrantes a
   una fecha, y recursos asignados a un equipo. Lo consumen las tareas 73 y 74
   sin tocar los Eloquent de `Personal` (ADR 0003 regla 2).
8. **Seeder demo** de dos equipos de la campaña `2025-2026` con piloto,
   auxiliar y equipamiento, idempotente.

## Qué NO hacer

- **No agregues `equipo_trabajo_id` a `ope_sesiones`.** Tocarla significa tocar
  el motor de sync y el contrato de la app de campo, y no hace falta: el gasto,
  el combustible y la estadía llevan el equipo **explícito**, escrito por quien
  los registra — ninguna imputación depende de deducirlo (ADR 0015 punto 3).
- **No impidas que una persona esté en dos equipos.** Es el caso real, no un
  error de carga.
- No borres ni reemplaces `equiposDePersonaDelMes()` del dashboard: es otra
  pregunta ("con quién volé"), sigue siendo válida y no es alcance de esta HU.
- No modeles el equipo con dos columnas fijas `piloto_id`/`auxiliar_id`: un
  gasto de marzo tiene que quedar atribuido a la formación de marzo.
- No toques `fin_gastos` ni `fin_combustibles`: es la tarea 73.

## Cómo repartir las etapas

- **Etapa 1**: `man_generadores` + ABM mínimo, con tests.
- **Etapa 2**: las tres tablas de equipo, modelos, aviso de solapamiento (no
  bloqueo), tests unitarios.
- **Etapa 3**: casos de uso, contrato de lectura, ABM y ficha con selector de
  fecha, permisos y menú, tests Feature.
- **Etapa 4**: seeder demo, traducciones, snapshots, `bin/verify`.

## Criterio de aceptación

- `./bin/verify` = 0 (en este Mac, `./bin/verify --sin-assets`).
- Test: asignar una persona a un segundo equipo con vigencia solapada **se
  guarda** y devuelve el aviso; duplicarla dentro del mismo equipo se rechaza.
- Test: la ficha del equipo consultada al 14/3 devuelve al auxiliar que estaba
  ese día, **no** al que está hoy, después de haber reemplazado al primero.
- Test: asignar un `recurso_tipo = 'vehiculo'` con un `recurso_id` que no
  existe en `man_vehiculos` se rechaza en el caso de uso.
- Test: un equipo de una campaña `cerrada` no acepta integrantes nuevos.
- `migrate --seed` dos veces deja la misma cantidad de equipos e integrantes.

## Puede tocar

`app/Dominios/Personal/**`, `app/Dominios/Mantenimiento/**` (solo
`man_generadores`), `app/Dominios/Seguridad/**` (solo `SeguridadSeeder` y
`SecMenuSeeder`), `database/migrations/**`, `database/seeders/**`, `lang/es/**`,
`routes/web.php`, `tests/**`.

Fuera de alcance: `ope_sesiones`, el motor de sync, `fin_*`, `com_*`.

## Cierre obligatorio de cada etapa

`runs/72.estado`, `runs/72.md`, y al `OK` `runs/72.pr.md`. Commits agrupados por
función, en español, imperativo, sin `Co-Authored-By`.

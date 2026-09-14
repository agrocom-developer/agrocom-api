# Observaciones del dueño — Catálogo de Recursos (13/9/2026)

**Origen.** Tercer documento de la misma ronda que
`docs/negocio/observaciones_operaciones_comercial_2026-09-13.md` — un Word
separado (`REcursos.docx`) sobre el catálogo de Drones, Baterías, Vehículos,
Base, Personal y Generador. A diferencia de los otros dos, **no tiene ningún
texto marcado en rojo**: todo son pedidos en positivo, sin urgencia
distinguida.

Mismas categorías que el documento hermano: `YA_CUBIERTO`, `GAP_REAL`,
`CAMBIO_TERMINOLOGIA`, `CONTRADICE_ARQUITECTURA`, `AMBIGUO`.

Se convierte en el **Sprint 17** de `plan_sprints.md` (HU-81 a HU-86) y las
filas 96–101 de `cola_tareas.md`.

## 1. Drones (`ope_drones`, módulo Operaciones)

Ya existen `identificador`, `modelo`, `capacidad_l` (30/50/60 L). El pedido
agrega dos tipos de dato bien distintos:

- **`capacidad_kg`** (capacidad en sólidos): es dato **operativo** — lo
  necesita HU-79 (Sprint 16) para saber cuánto puede cargar un dron en una
  orden sólida, igual que `capacidad_l` para líquida. Va en `ope_drones`, la
  misma tabla. → **HU-81**.
- **Serie, chasis, versión de software, región, serie del control,
  accesorios** (cargador de control/módem/maletín): son datos de
  **inventario/activo**, no de vuelo. La propia migración de `ope_drones`
  dice explícitamente que la tabla es *"deliberadamente mínima"* y que datos
  así le corresponden a Mantenimiento/Inventario — mismo criterio ya aplicado
  a batería, vehículo y generador (todos viven en `Mantenimiento`, con
  `ope_drones`/`ope_recargas` correlacionando por identificador de texto, sin
  FK real). Se resuelve con una ficha nueva en `Mantenimiento` (`man_drones`
  o equivalente), no ampliando `ope_drones`. → **HU-82**.

## 2. Baterías (`man_baterias`)

- `identificador`, `ciclos_acumulados`, `base_id` ya existen. **GAP_REAL,
  chico**: falta `ciclos_inicial` (valor de arranque, separado del
  acumulado — "como el odómetro de la batería", nota textual del dueño) y el
  estado `mantenimiento` (el enum real solo tiene `Activa`/`Retirada`, sin
  ese tercer valor). → **HU-83**.

## 3. Vehículos (`man_vehiculos`)

Solo existen `identificador`, `base_id` y `estado` (`activo/taller/de_baja`).
**GAP_REAL, el más grande de este documento**: faltan `marca`, `modelo`,
`año`, `combustible` (gasolina/diésel), `4x4` (sí/no), el estado `pausa`
(cuarto valor, no existe), `kilometraje_inicial` y `kilometraje_actual`. →
**HU-84**.

## 4. Base (`per_bases`)

Ya tiene `nombre` y `ubicacion` (texto libre). **GAP_REAL, chico**: faltan
coordenadas estructuradas (`latitud`/`longitud`) — mismo patrón que HU-76
(Propiedad) del Sprint 16. → **HU-85**.

## 5. Personal (`per_personas`)

**YA_CUBIERTO por completo.** `nombre`, `rol`, `base_id`, `tarifa_ha` y
`activo` ya existen los cinco. No genera tarea.

## 6. Generador (`man_generadores`)

Tiene `identificador`, `modelo`, `base_id`, `estado`, pero un único
`horas_uso` cargado a mano (la migración aclara que un generador no vuela,
así que no hay de dónde derivarlo como con las horas de vuelo del dron).
**GAP_REAL, chico**: separar en `horas_inicial`/`horas_actual`, mismo patrón
que kilometraje de vehículo y ciclos de batería. → **HU-86**.

## Nada ambiguo ni contradictorio en este documento

A diferencia del Word de Operaciones/Comercial, acá no hay ningún pedido que
choque con un ADR o invariante, ni redacción ambigua — son todas columnas
nuevas sobre catálogos que ya existen, sin cambio de cardinalidad ni de
responsabilidad de negocio.

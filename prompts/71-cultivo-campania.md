<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/cultivo-campania etapas=3 -->

# Tarea 71 — HU-48: qué cultivo se sembró en cada lote, en cada campaña

## Por qué esta tarea

El sistema no sabe qué se siembra. Sabe hectáreas, lotes y aplicaciones, pero
no si esas hectáreas son de soya o de maíz — y el negocio agrupa por cultivo:
el número de aplicaciones por campaña **varía por cultivo** (*"algunos cultivos
se aplican más de 5 por temas de enfermedades y plagas"*, Abraham,
`rol_agronomo.md` G-15), y el informe de avance que pidió el dueño (tarea 75)
selecciona por cultivo como entrada obligatoria.

El cultivo **no es atributo del lote**: el lote no "es" de soya, se siembra de
soya *esta* campaña y de maíz la siguiente. Ponerlo como columna de `com_lotes`
obliga a pisar el dato cada campaña y borra la historia — la razón está en el
**ADR 0015 punto 4**, leelo antes de empezar.

**Hecho confirmado por el usuario (8/9/2026): la campaña es por todo el campo,
no por cultivo.** Cubre la propiedad entera —todos sus lotes, con lo que se
haya sembrado en cada uno— y nunca se abre una campaña "de soya" y otra "de
maíz" sobre el mismo campo. Ratifica el punto 4 del ADR: el cultivo es una
dimensión del lote *dentro* de la campaña, jamás el criterio que la parte. Las
dos campañas del mismo año agronómico que menciona el ADR son **por temporada**
("campaña de verano 2025-2026"), que es como el negocio ya las nombra. Si al
implementar aparece el caso de dos cultivos en el mismo lote y la misma
campaña, no lo resuelvas inventando esquema: dejalo anotado en `runs/71.md`.

Depende de la tarea 69 (necesita `cpn_campanias`).

## Lo que ya existe

- `com_campos` → `com_lotes` (`campo_id`, `codigo`, `hectareas`, `geometria`,
  `restricciones`), con índice único parcial `com_lotes_codigo_unico`.
- El alta de campo con sus lotes en una transacción:
  `Comercial/Aplicacion/CrearCampo.php` y
  `Views/pages/campos/_formulario.blade.php` + `_lote-fila.blade.php` — es el
  molde de "N filas hijas en un formulario", reusalo.
- `Comercial/Aplicacion/{Crear,Actualizar,Eliminar}Campo.php` y su controlador.
- `cpn_campanias` con su `cliente_id` (tarea 69). **No hay campaña activa de
  sesión**: la campaña se elige dentro del cliente (ADR 0015 punto 1,
  corregido el 8/9/2026).

## Qué hacer

1. **`com_cultivos`**: `nombre` (string 80), `activo` (bool, default true), +
   auditoría y soft delete. Índice único parcial sobre `nombre` entre filas
   activas. Seeder con los cultivos reales de la zona: soya, maíz, girasol,
   trigo, sorgo, chía, frejol — idempotente.
2. **ABM de cultivos** en el panel, con permisos
   `comercial.cultivo.{ver,crear,editar,eliminar}` y su ítem de menú. Es un
   catálogo simple: listado, alta, edición, baja lógica.
3. **`com_lote_campania`**: `lote_id`, `campania_id`, `cultivo_id`,
   `hectareas_sembradas` `DECIMAL(10,2)`, `fecha_siembra` (nullable),
   `fecha_cosecha_estimada` (nullable), + auditoría y soft delete.
   - `UNIQUE (lote_id, campania_id)` **parcial** (`WHERE deleted_at IS NULL`):
     un cultivo por lote y campaña. El caso de dos ciclos en el mismo año
     agronómico se modela como **dos campañas del mismo cliente**, abiertas a la
     vez, no como dos cultivos (ADR 0015 punto 4) — no lo resuelvas acá.
   - Guarda de consistencia: el lote tiene que pertenecer a un campo del
     **mismo cliente** que la campaña. Con la campaña colgando del cliente, esto
     es representable y hay que impedirlo.
   - `CHECK (hectareas_sembradas > 0)` y `CHECK (fecha_cosecha_estimada IS NULL
     OR fecha_siembra IS NULL OR fecha_cosecha_estimada >= fecha_siembra)`,
     solo en pgsql.
   - Guarda de aplicación: `hectareas_sembradas` no puede superar las
     `hectareas` del lote. Va en el caso de uso, con `Brick\Math\BigDecimal`
     (invariante 6, y `bcmath` no está instalado en este entorno).
4. **La siembra se carga desde la ficha del campo**, con un selector de
   campaña arriba: las campañas del **cliente dueño de ese campo**, no todas.
   Por cada lote, cultivo + hectáreas sembradas + fechas. Cambiar de campaña en
   el selector muestra la siembra de esa campaña y **no pisa** la de la
   anterior. Si el cliente no tiene ninguna campaña, la ficha lo dice y ofrece
   crearla, en vez de mostrar un formulario que no puede guardar.
5. **Contrato de lectura** `Comercial/Contratos/LecturaCultivoLote` (interfaz +
   DTO primitivo, ADR 0003 regla 2) para que la tarea 75 pueda agrupar por
   cultivo sin tocar los modelos Eloquent de `Comercial`.
6. **Traducciones** en `lang/es/comercial.php`.

## Qué NO hacer

- No agregues `cultivo_id` a `com_lotes` — es exactamente lo que este ADR
  descarta.
- No permitas dos cultivos para el mismo lote en la misma campaña.
- No toques `com_contratos` ni el informe de avance: eso es la tarea 75.
- No inventes rendimiento, kilos ni precio de grano: Agrocom vende servicio de
  aplicación, no compra grano (ADR 0015, alternativas descartadas).

## Cómo repartir las etapas

- **Etapa 1**: `com_cultivos` + seeder + ABM + permisos + menú, con tests.
- **Etapa 2**: `com_lote_campania`, caso de uso con la guarda de hectáreas,
  contrato de lectura, tests unitarios y Feature.
- **Etapa 3**: pantalla de siembra en la ficha del campo, traducciones,
  snapshots, `bin/verify`.

## Criterio de aceptación

- `./bin/verify` = 0 (en este Mac, `./bin/verify --sin-assets`).
- Test: sembrar el mismo lote dos veces en la misma campaña se rechaza por el
  índice único, y el error llega traducido, no como `QueryException` cruda.
- Test: el mismo lote con soya en la campaña A y maíz en la campaña B convive
  sin conflicto, y consultar A no devuelve el cultivo de B.
- Test: `hectareas_sembradas` mayor a las hectáreas del lote se rechaza.
- `migrate --seed` dos veces deja la misma cantidad de cultivos.

## Puede tocar

`app/Dominios/Comercial/**`, `app/Dominios/Seguridad/**` (solo
`SeguridadSeeder` y `SecMenuSeeder`), `database/migrations/**`,
`database/seeders/**`, `lang/es/**`, `routes/web.php`, `tests/**`.

Fuera de alcance: `cpn_campanias` (ya existe), `per_*`, `fin_*`, `ope_*`.

## Cierre obligatorio de cada etapa

`runs/71.estado`, `runs/71.md`, y al `OK` `runs/71.pr.md`. Commits agrupados por
función, en español, imperativo, sin `Co-Authored-By`.

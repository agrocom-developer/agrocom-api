<!-- ciclo: critica=no turno-noche=1 descongela=tests,decisiones rama=feature/ubicacion-propiedad etapas=2 -->

# Tarea 92 — HU-76: Departamento/Municipio/Localidad/Coordenada de la propiedad

## Por qué esta tarea

El encargado necesita ubicar cada propiedad en el mapa y filtrar por zona.
`com_propiedades.ubicacion` (ADR 0018) es texto libre a propósito: cuando se
escribió ese ADR, "no hay pedido de reporte por zona que justifique partirla
en columnas... así que no se infiere esa estructura". Ese pedido explícito
llegó después (ronda de negocio del 13/9/2026,
`docs/negocio/observaciones_operaciones_comercial_2026-09-13.md`, fila HU-76):
"amplía ADR 0018 punto 1 (que decía 'no se infiere esa estructura' a falta de
un pedido explícito; ahora existe el pedido)". Por eso esta tarea, además de
la migración, deja una adenda fechada en el ADR — no lo reescribe, agrega lo
que ahora sí está pedido. No es crítica: ALTER aditivo, sin tocar el motor de
sync (confirmado en el propio ADR 0018: "no expone `cliente_id`... este
rediseño no toca el motor de sync", y esta tarea no agrega ninguna tabla
nueva al catálogo de sincronización).

## Lo que ya existe

- `com_propiedades` (`2026_08_26_100005_create_com_propiedades_table.php`):
  `cliente_id`, `nombre`, `ubicacion` (texto libre, nullable) — sin
  `departamento`/`municipio`/`localidad`/`latitud`/`longitud`.
- `Propiedad` (Eloquent), `CrearPropiedadRequest`/`ActualizarPropiedadRequest`,
  `CrearPropiedad`/`ActualizarPropiedad` (`Aplicacion/`) — las cuatro clases
  reciben/persisten columna por columna, sin arrays genéricos: vas a sumar
  parámetros a las firmas de `ejecutar()`, no a adivinar un shape.
  `propiedades/_formulario.blade.php` es el único formulario (compartido por
  `create`/`edit`).
- No hay ningún precedente de lat/long en el repo todavía — esta tarea fija
  el patrón que la tarea de HU-85 (Sprint 17, coordenada de una base) va a
  copiar después. Usá el mismo criterio que ya existe en el repo para "dos
  campos que se completan juntos o ninguno" (`ventanas.*.hora_inicio`/`hora_fin`
  en `CrearContratoRequest`, `required_with` mutuo): `latitud` y `longitud`
  nunca uno sin el otro.

## Qué hacer

Cargá los skills `verificacion` y `modelo-datos`.

1. **Migración `ALTER com_propiedades`**: `departamento` (`string(100)`
   nullable), `municipio` (`string(100)` nullable), `localidad` (`string(150)`
   nullable), `latitud` (`decimal(9,6)` nullable), `longitud` (`decimal(9,6)`
   nullable). `CHECK` condicional a `pgsql` (mismo patrón que el resto del
   repo): `latitud IS NULL OR (latitud >= -90 AND latitud <= 90)`,
   `longitud IS NULL OR (longitud >= -180 AND longitud <= 180)`.
2. **`Propiedad` (Eloquent)**: sumá las 5 columnas a `$fillable`; en
   `casts()` (agregala si no existe todavía en este modelo) `latitud`/
   `longitud` como `'decimal:6'` (invariante 6: nunca `float`).
3. **`CrearPropiedadRequest`/`ActualizarPropiedadRequest`**: `departamento`,
   `municipio` => `['nullable', 'string', 'max:100']`; `localidad` =>
   `['nullable', 'string', 'max:150']`; `latitud` => `['nullable',
   'required_with:longitud', 'numeric', 'between:-90,90']`; `longitud` =>
   `['nullable', 'required_with:latitud', 'numeric', 'between:-180,180']`.
4. **`CrearPropiedad`/`ActualizarPropiedad`**: sumá los 5 parámetros a
   `ejecutar()` (nullable todos) y asignalos igual que `ubicacion`.
5. **`PropiedadesController`**: sumá la lectura/normalización de los 5 campos
   (mismo `cadenaONull()` ya existente para los de texto; los numéricos
   viajan como string, igual que `hectareas_contratadas` en
   `ContratosController`).
6. **Vista `propiedades/_formulario.blade.php`**: tres `x-atoms.input`
   (`departamento`/`municipio`/`localidad`) y dos `x-atoms.input
   type="number"` (`latitud` `step="0.000001"` `min="-90"` `max="90"`,
   `longitud` `step="0.000001"` `min="-180"` `max="180"`). Ajustá
   `campos_contador`.
7. **`lang/es/comercial.php`**, bloque `propiedades`: etiquetas de los 5
   campos nuevos.
8. **Adenda en `docs/decisiones/0018-propiedad-nivel-terreno-y-alcance-contrato.md`**:
   al final de la sección "1. `Propiedad` es una entidad nueva...", agregá un
   párrafo fechado (14/9/2026) que registre que el pedido explícito que
   faltaba para partir la ubicación en columnas ya existe (HU-76,
   `observaciones_operaciones_comercial_2026-09-13.md`), y que `departamento`/
   `municipio`/`localidad`/`latitud`/`longitud` se agregan COMO COLUMNAS
   NUEVAS junto a `ubicacion` (que sigue viva, sin reemplazo: `ubicacion`
   queda como referencia libre/histórica, las columnas nuevas son las que se
   usan para filtrar por zona y ubicar en el mapa). No reescribas nada del
   texto original del ADR.

## Qué NO hacer

- No borres ni reemplaces `com_propiedades.ubicacion`: coexiste con las
  columnas nuevas, ninguna reemplaza a la otra.
- No repitas la matriz de "no se infiere esa estructura" en `Campo` ni en
  ninguna otra tabla — el pedido de HU-76 es específico de `Propiedad`.
- No inventes un catálogo cerrado de departamentos/municipios de Bolivia: son
  texto libre, igual que `ubicacion` — no hay pedido de ese nivel de
  estructura.
- No toques el editor de mapa (HU-56, `resources/js/**`) ni el proveedor de
  geocodificación: esto es un dato cargado a mano, no un pin derivado de un
  mapa.

## Cómo repartir las etapas

- **Etapa 1**: migración, modelo, los dos Request, las dos clases de
  `Aplicacion/`, controlador, tests de request/caso de uso (rango de
  latitud/longitud rechazado, uno sin el otro rechazado, guardado válido).
- **Etapa 2**: vista, `lang/es/comercial.php`, la adenda del ADR 0018, tests
  Feature/Playwright si aplica, `bin/verify`.

## Criterio de aceptación

- `./bin/verify` = 0.
- Test: guardar y leer los 5 campos nuevos (alta y edición).
- Test: `latitud`/`longitud` fuera de rango (±90/±180) se rechaza.
- Test: `latitud` sin `longitud` (o viceversa) se rechaza.
- `grep -q "HU-76" docs/decisiones/0018-propiedad-nivel-terreno-y-alcance-contrato.md`
  (o el texto exacto que elijas para la adenda, mientras sea localizable con
  un `grep` simple).

## Puede tocar

`app/Dominios/Comercial/**`, migración `ALTER` nueva,
`docs/decisiones/0018-propiedad-nivel-terreno-y-alcance-contrato.md` (solo la
adenda, no reescribir nada existente), `lang/es/comercial.php`, `tests/**`.

## Cierre obligatorio de cada etapa

`runs/92.estado`, `runs/92.md`, y al `OK` `runs/92.pr.md`. Commits agrupados
por función, español, imperativo, sin `Co-Authored-By`.

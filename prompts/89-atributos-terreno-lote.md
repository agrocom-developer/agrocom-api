<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/atributos-terreno-lote etapas=2 -->

# Tarea 89 — HU-73: desnivel y limpieza del lote

## Por qué esta tarea

El jefe de campo necesita saber si un lote tiene desniveles o está libre de
obstáculos ANTES de asignar el equipo (HU-70, ya integrada) — hoy esa
información no existe en ningún catálogo cerrado. `com_lotes.restricciones`
es texto libre para riesgos externos (cables, viviendas, colmenas, vecinos
sensibles), no sirve para filtrar ni planificar de forma sistemática. No es
crítica: ALTER puramente aditivo, sin tocar ninguna guarda de negocio ni el
motor de sync.

## Lo que ya existe

- `com_lotes` (migración `2026_08_26_100007_create_com_lotes_table.php`):
  `codigo`, `hectareas`, `geometria` (jsonb nullable), `restricciones` (text
  nullable) — sin `desnivel` ni `limpieza`.
- Catálogos cerrados de una sola columna, sin máquina de estados, ya siguen
  un molde simple en el proyecto: `ope_ordenes_aplicacion.tipo_aplicacion`
  (string + `CHECK IN (...)` solo en pgsql + `Rule::in()` en el Request, sin
  enum PHP dedicado) — mismo criterio a seguir acá, no hace falta inventar un
  enum si el resto de columnas de este tipo tampoco lo usa.
- El lote se edita por DOS caminos que comparten el mismo dato: dentro de la
  ficha de campo (`CrearCampoRequest`/`ActualizarCampoRequest`, array
  `lotes[]`, vía `CamposController`) y por su ficha propia
  (`CrearLoteRequest`/`ActualizarLoteRequest`, vía `LotesController`). Los
  cuatro Request ya validan `restricciones`; los cuatro tienen que sumar
  `desnivel`/`limpieza` — no alcanza con tocar uno solo. Los dos caminos
  convergen en `GuardadoLote::guardar()` para persistir.

## Qué hacer

Cargá los skills `verificacion` y `modelo-datos`.

1. **Migración `ALTER com_lotes`**: `desnivel` (string nullable) y
   `limpieza` (string nullable), sin default — son datos nuevos, no le
   asignes un valor a los lotes ya cargados. `CHECK` condicional a
   `DB::getDriverName() === 'pgsql'` (SQLite no soporta `ADD CONSTRAINT`,
   mismo patrón que el resto de las migraciones de este repo):
   `desnivel IN ('ninguno','algunos','varios','empinado')`,
   `limpieza IN ('limpio','algunos_obstaculos','muchos_obstaculos')`.
2. **Los cuatro Request**: `CrearCampoRequest`/`ActualizarCampoRequest`
   suman `lotes.*.desnivel`/`lotes.*.limpieza`; `CrearLoteRequest`/`ActualizarLoteRequest`
   suman `lote.desnivel`/`lote.limpieza`. Los cuatro `nullable` +
   `Rule::in([...])` con los mismos valores del `CHECK`.
3. **`GuardadoLote::guardar()`**: el array `$datos` de su docblock gana las
   dos claves nuevas — sin lógica adicional, viajan por `fill()` igual que
   `restricciones`.
4. **`CamposController`/`LotesController`**: sumá `desnivel`/`limpieza` a
   `normalizarLoteNuevo()`/`normalizarLoteExistente()` (revisá el nombre
   exacto de los métodos equivalentes en `LotesController`) con el mismo
   criterio `cadenaONull()` ya usado para `restricciones`.
5. **Vistas**: dos `<select>` nuevos junto al campo `restricciones` existente
   en los formularios de campo y de lote (confirmá los nombres reales de
   archivo bajo `pages/campos/` y `pages/lotes/` contra el árbol — no
   asumas los de este prompt si cambiaron). Usá el átomo `select` si ya está
   migrado (`resources/views/components/atoms/select.blade.php`, HU-53).
6. **`lang/es/comercial.php`**: etiquetas de los dos catálogos y de cada una
   de sus opciones.

## Qué NO hacer

- No confundas `desnivel`/`limpieza` con `restricciones`: son catálogos
  cerrados distintos, coexisten sin reemplazar el texto libre existente.
- No los hagas `required`: son datos nuevos sobre lotes que ya existen sin
  ellos — un lote viejo sin cargar tiene que poder seguir guardándose
  (`nullable` de punta a punta, formulario incluido).
- No toques `com_lotes.hectareas`, `geometria` ni ningún otro campo — el
  ALTER es puramente aditivo.
- Si encontrás más de los cuatro puntos de entrada listados acá validando
  datos de lote, es señal de que el árbol cambió desde que se escribió este
  prompt — confirmá contra el código real antes de asumir la lista cerrada.

## Cómo repartir las etapas

- **Etapa 1**: migración `ALTER`, los cuatro Request, `GuardadoLote`,
  normalización en los dos controladores, tests de request/caso de uso (valor
  fuera de catálogo rechazado en los cuatro puntos de entrada).
- **Etapa 2**: los `<select>` en las cuatro vistas, `lang/es/comercial.php`,
  tests Feature/Playwright si aplica, `bin/verify`.

## Criterio de aceptación

- `./bin/verify` = 0.
- Test: un valor fuera de catálogo en `desnivel` se rechaza (422) desde el
  alta de campo Y desde la ficha de lote — los dos caminos, no uno solo.
- Test: mismo caso para `limpieza`.
- Test de regresión: un lote existente sin `desnivel`/`limpieza` (`NULL`) se
  sigue editando sin que la validación los fuerce.

## Puede tocar

`app/Dominios/Comercial/**`, migración `ALTER` nueva, `lang/es/comercial.php`,
`tests/**`.

## Cierre obligatorio de cada etapa

`runs/89.estado`, `runs/89.md`, y al `OK` `runs/89.pr.md`. Commits agrupados
por función, español, imperativo, sin `Co-Authored-By`.

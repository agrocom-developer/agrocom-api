<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/alta-masiva-lotes etapas=3 -->

# Tarea 88 — HU-72: alta masiva de lotes

## Por qué esta tarea

Cargar lotes uno por uno cuando una propiedad trae decenas no tiene sentido
— el dueño pidió indicar cuántos y qué siembran de una sola vez, y
renombrar/dibujar el polígono de cada uno después. `CrearCampo` ya acepta
`lotes[]` en una sola transacción (tarea 35); falta el generador por
cantidad + el cultivo por defecto resuelto contra `com_lote_campania`,
reusando `GuardarSiembraCampania` (tarea 71) en vez de reimplementar el alta
de siembra. No es crítica: no toca el motor de sync ni una guarda con dinero
de por medio.

## Lo que ya existe

- `Comercial/Aplicacion/CrearCampo::ejecutar(propiedadId, nombre, lotes[])`:
  transacción que crea el campo y llama `GuardadoLote::guardar()` por cada
  lote (`codigo`/`hectareas`/`geometria`/`restricciones`).
- `Comercial/Aplicacion/Lote/GuardadoLote`: guarda un lote y traduce el
  índice único parcial `com_lotes_codigo_unico` a `LoteDuplicado`.
- `Comercial/Aplicacion/GuardarSiembraCampania::ejecutar(Campo, campaniaId,
  filas[])`: crea/actualiza/da de baja filas de `com_lote_campania`, valida
  que la campaña sea del mismo cliente que el campo (lanza
  `CampaniaDeOtroCliente`), vía `Campania\Contratos\LecturaCampania` (ADR
  0003 regla 2 — nunca el modelo Eloquent `Campania` directo).
- `Comercial/Infraestructura/Http/Controllers/Web/SiembraController::campaniasDelCliente()`:
  lee `cpn_campanias` con `DB::table` directo (ADR 0003 regla 3) para poblar
  un selector — mismo mecanismo a reusar acá.
- **Ojo**: una campaña "activa" no es única — `cpn_campanias` no tiene guarda
  de solape (tarea 69) y puede haber varias en estado `abierta` para el
  mismo cliente a la vez. El formulario tiene que ofrecer un selector
  explícito de campaña (mismo patrón que `SiembraController`), nunca resolver
  "la" activa con una consulta que asuma una sola fila.
- `com_lotes.hectareas` y `com_lote_campania.hectareas_sembradas` son
  `DECIMAL NOT NULL CHECK (> 0)`: un lote generado sin superficie dibujada
  todavía necesita un valor numérico real, no un placeholder vacío.

## Qué hacer

Cargá los skills `verificacion` y `dominio-backend`.

1. **Formulario de alta de campo** (`pages/campos/create.blade.php`): un
   bloque "Generar lotes" con cantidad `N`, hectáreas por lote (mismo valor
   aplicado a los N, editable lote por lote después desde la ficha ya
   existente), cultivo por defecto (select, cultivos activos) y campaña
   (select, `campaniasDelCliente()` de la propiedad elegida). Al confirmar,
   genera `N` filas en el array `lotes[]` que el formulario ya envía —
   código provisorio tipo "Lote 1".."Lote N" (no colisiona: el índice único
   es por `campo_id`, y el campo todavía no existe). Es la vía más simple:
   reusa el contrato de `CrearCampoRequest` tal cual, sin partir el alta en
   dos pasos.
2. **`CrearCampoRequest`**: sumá `cultivo_id` (nullable,
   `exists:com_cultivos,id`) y `campania_id` (nullable,
   `required_with:cultivo_id`, `exists:cpn_campanias,id`) — validan la
   intención de sembrar, no reemplazan la validación de `lotes[]` ya
   existente.
3. **`CrearCampo`, o un caso de uso nuevo que lo envuelva** (a tu criterio —
   documentá cuál elegiste y por qué en `runs/88.md`): después de crear el
   campo y sus lotes en la misma transacción, si vino `cultivo_id`, armá las
   filas de `GuardarSiembraCampania` (una por lote recién creado,
   `hectareas_sembradas` = las mismas hectáreas del lote) y ejecutalo con la
   `campania_id` recibida.
4. **`CamposController::store`**: pasá `cultivo_id`/`campania_id` al caso de
   uso; capturá `CampaniaDeOtroCliente` igual que ya hace
   `SiembraController::guardar()` (redirect con error en `withErrors`, no un
   500).

## Qué NO hacer

- No implementes el editor de polígono ni el renombrado individual: ya
  existen (`campos/edit.blade.php`, `LotesController`) — "dibujar/renombrar
  después" es ese camino ya construido, no algo nuevo de esta tarea.
- No asumas una campaña activa única ni la resuelvas con
  `orderByDesc()->first()` en el backend sin que el usuario la haya elegido:
  el selector es obligatorio si se quiere generar la siembra.
- No cambies el contrato de `lotes[]` de `CrearCampoRequest`/`CrearCampo`
  para altas manuales de un lote por vez — el generador solo rellena ese
  mismo array antes de enviar, no crea un endpoint paralelo.
- No toques `ActualizarCampo`/`campos/edit.blade.php`: la generación masiva
  es solo de alta, no de edición.
- No agregues `cultivo_id`/`campania_id` como columnas de `com_lotes` — la
  relación va por `com_lote_campania`, como ya está modelado (ADR 0015
  punto 4).

## Cómo repartir las etapas

- **Etapa 1**: `CrearCampoRequest` (campos nuevos), extensión de
  `CrearCampo` (o caso de uso envolvente) con `GuardarSiembraCampania`,
  `CamposController::store`, tests de caso de uso (N lotes + N siembras, sin
  `cultivo_id` no crea siembra, campaña de otro cliente rechazada).
- **Etapa 2**: JS del formulario (generador de N filas), selects de
  cultivo/campaña, estilos mínimos.
- **Etapa 3**: tests Feature end to end (`POST /panel/campos` con
  generación masiva) y `bin/verify` completo.

## Criterio de aceptación

- `./bin/verify` = 0.
- Test: pedir `N` lotes genera `N` filas de `com_lotes` + `N` filas de
  `com_lote_campania` con el cultivo elegido y la campaña elegida.
- Test: renombrar (cambiar `codigo`) o dibujar (cargar `geometria`) un lote
  generado, después, por la ficha existente, no le borra la fila de siembra
  ni el cultivo.
- Test: campaña de un cliente distinto al de la propiedad se rechaza
  (`CampaniaDeOtroCliente`), igual que ya hace `GuardarSiembraCampania`.
- Test de regresión: un alta de campo sin `cultivo_id`/`campania_id` (el
  camino manual de siempre) sigue funcionando idéntico, sin crear ninguna
  fila de `com_lote_campania`.

## Puede tocar

`app/Dominios/Comercial/Aplicacion/**`,
`app/Dominios/Comercial/Infraestructura/Http/Controllers/Web/CamposController.php`,
`app/Dominios/Comercial/Infraestructura/Http/Requests/CrearCampoRequest.php`,
`resources/views/components/**` (solo si extraés algo reusable del
generador), `pages/campos/create.blade.php`, `lang/es/comercial.php`,
`tests/**`.

## Cierre obligatorio de cada etapa

`runs/88.estado`, `runs/88.md`, y al `OK` `runs/88.pr.md`. Commits agrupados
por función, español, imperativo, sin `Co-Authored-By`.

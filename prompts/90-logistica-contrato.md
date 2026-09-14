<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/logistica-contrato etapas=2 -->

# Tarea 90 — HU-74: acomodaciones logísticas del contrato

## Por qué esta tarea

El encargado necesita saber, por contrato, qué logística cubre Agrocom
(alimentación/hospedaje/combustible del equipo) sin tener que preguntarlo cada
vez que arma una estadía. Hoy `com_contratos` no tiene ningún dato de esto. No
es crítica: ALTER puramente aditivo, sin tocar el motor de sync ni ninguna
máquina de estados. El impacto en el costeo de Finanzas queda para una tarea
futura — acá solo se registra el dato, no se lo usa todavía en ningún cálculo.

## Lo que ya existe

- `com_contratos` (migración `2026_08_26_100003_create_com_contratos_table.php`,
  más los `ALTER` de campaña/pausado): sin ninguna columna de logística.
- `CrearContratoRequest`/`ActualizarContratoRequest`: validan el resto de
  columnas del contrato una a una; `ContratosController::normalizarDatosContrato()`
  arma el array que reciben `Aplicacion/CrearContrato`/`ActualizarContrato`.
  Ninguna de las dos clases de `Aplicacion/` necesita cambios de lógica — solo
  reciben columnas más en `$datosContrato` (`CrearContrato`/`ActualizarContrato`
  hacen `$this->maquinaEstados->crear($datosContrato)` / `$contrato->fill($datosContrato)`
  sin listar columnas explícitas).
- Precedente de booleano de formulario en este mismo módulo:
  `contratos/_formulario.blade.php` ya tiene un `x-atoms.switch` (`dia_completo`,
  que NO se persiste — no es el patrón a copiar para persistencia). Para un
  booleano que SÍ se guarda, el patrón real del repo es
  `contactos.*.tipo`-style validación (`['boolean']`) + lectura con
  `$request->boolean('campo')` en el controlador — ver `HU-22`/`activo` en
  `CrearPersonaRequest`/`ActualizarPersonaRequest` y
  `OrganizacionController::actualizarEmpresa()` (`$request->boolean('logo_eliminar')`):
  un checkbox sin marcar NO llega en `$request->validated()`, así que hay que
  leerlo con `$request->boolean()` ANTES de armar el array, nunca confiar en
  que esté presente.

## Qué hacer

Cargá los skills `verificacion` y `modelo-datos`.

1. **Migración `ALTER com_contratos`**: `brinda_alimentacion`,
   `brinda_hospedaje`, `brinda_combustible` (`boolean`, `default(false)` —
   a diferencia de `desnivel`/`limpieza` de la tarea 89, acá SÍ tiene sentido
   un default: "no cubre" es el valor real para todo contrato existente, no un
   "sin cargar") y `observaciones_logistica` (`text`, nullable). Sin `CHECK`:
   no son catálogo cerrado.
2. **`Contrato` (Eloquent)**: sumá las 4 columnas a `$fillable`; en `casts()`
   agregá los tres booleanos como `'boolean'`.
3. **`CrearContratoRequest`/`ActualizarContratoRequest`**: `brinda_alimentacion`,
   `brinda_hospedaje`, `brinda_combustible` => `['boolean']`;
   `observaciones_logistica` => `['nullable', 'string']`.
4. **`ContratosController`**: en `store()` y `update()`, ANTES de llamar
   `normalizarDatosContrato()`, sumá al array validado los tres booleanos
   leídos con `$request->boolean('brinda_alimentacion')` (y los otros dos) —
   mismo criterio que `actualizarEmpresa()` con `logo_eliminar`, para que un
   checkbox sin marcar guarde `false`, no lo deje afuera del array. Sumá esas
   4 claves también dentro de `normalizarDatosContrato()` (los tres booleanos
   como `(bool)`, `observaciones_logistica` con `cadenaONull()` ya existente).
5. **Vista `contratos/_formulario.blade.php`**: nueva subsección o campos
   agregados a `seccion_datos` (ajustá `campos_contador`): tres
   `x-atoms.switch` (mismo componente que `dia_completo`, pero estos SÍ tienen
   `name` que viaja al servidor y `:checked` desde `old()`/el modelo) más un
   `x-atoms.textarea` para `observaciones_logistica` en
   `ag-form-section__field--full`.
6. **`lang/es/comercial.php`**, bloque `contratos`: etiquetas de los tres
   switches y del campo de observaciones.

## Qué NO hacer

- No toques `monto_total` ni ningún cálculo de `Aplicacion/CrearContrato`.
- No conectes esto con `Finanzas` ni con `fin_gastos`/`fin_combustibles`: el
  costeo real de la logística es alcance de una tarea futura, no de esta.
- No le pongas `CHECK` a los tres booleanos — no son un catálogo cerrado.
- No confundas con el switch `dia_completo` de ventanas: ese es puramente de
  presentación y nunca se envía; estos tres SÍ se persisten.

## Cómo repartir las etapas

- **Etapa 1**: migración, modelo, los dos Request, el controlador (ambas
  rutas, store y update), tests de request/caso de uso (guardado y lectura de
  los tres booleanos y las observaciones, en alta y en edición; checkbox sin
  marcar persiste `false`, no `null`).
- **Etapa 2**: vista, `lang/es/comercial.php`, test Feature/Playwright si
  aplica, `bin/verify`.

## Criterio de aceptación

- `./bin/verify` = 0.
- Test: alta de un contrato con los tres booleanos en `true` y observaciones
  cargadas persiste exacto los cuatro valores.
- Test: editar un contrato sin marcar ninguno de los tres checkboxes lo deja
  en `false` (no en su valor anterior sin tocar, ni en `null`).
- Test: los cuatro campos son opcionales — un contrato sin logística sigue
  creándose igual.

## Puede tocar

`app/Dominios/Comercial/**`, migración `ALTER` nueva, `lang/es/comercial.php`,
`tests/**`.

## Cierre obligatorio de cada etapa

`runs/90.estado`, `runs/90.md`, y al `OK` `runs/90.pr.md`. Commits agrupados
por función, español, imperativo, sin `Co-Authored-By`.

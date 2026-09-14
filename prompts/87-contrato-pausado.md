<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/contrato-pausado etapas=2 -->

# Tarea 87 — HU-71: contrato pausado, con el vocabulario del dueño

## Por qué esta tarea

El Word pide ver el contrato como En Ejecución / En Aprobación / Ejecutado /
Pausado. Tres de esos cuatro ya existen como estados reales
(`borrador`→"En Aprobación", `vigente`→"En Ejecución",
`finalizado`→"Ejecutado"); falta el estado `pausado` en sí — una
interrupción del contrato vigente, no una cancelación — y traducir las
etiquetas del panel a ese vocabulario sin tocar lo que se guarda en base.
Independiente de todo lo demás del Sprint 16; no es crítica: no toca el motor
de sync ni una guarda de negocio con dinero de por medio.

## Lo que ya existe

- `Comercial/Dominio/EstadoContrato` (enum): `Borrador`, `Vigente`,
  `Finalizado`, `Cancelado`.
- `Comercial/Dominio/MaquinaEstados/TransicionesContrato::PERMITIDAS`: tabla
  pura (`borrador → [vigente, cancelado]`, `vigente → [finalizado,
  cancelado]`, los dos terminales sin salida).
- `Comercial/Aplicacion/MaquinaEstados/MaquinaEstadosContrato`: única clase
  que muta `estado` (invariante 7). `cambiarA()` es el punto de entrada desde
  HTTP (`Aplicacion/CambiarEstadoContrato`) — resuelve a qué método
  corresponde cada destino vía `match`.
- CHECK en base: `com_contratos_estado_chk CHECK (estado IN ('borrador',
  'vigente', 'finalizado', 'cancelado'))` (migración
  `2026_08_26_100003_create_com_contratos_table.php`).
- `lang/es/comercial.php` → `'contrato' => ['estado' => [...]]` con las
  cuatro etiquetas actuales; el listado de contratos ya tiene acciones de
  cambio de estado (`accion_activar`, `accion_finalizar`, `accion_cancelar`)
  con sus confirmaciones.

## Qué hacer

Cargá los skills `verificacion` y `dominio-backend`.

1. **Enum**: agregá `case Pausado = 'pausado';` a `EstadoContrato`.
2. **`TransicionesContrato::PERMITIDAS`**: agregá `'vigente' => ['finalizado',
   'cancelado', 'pausado']` y `'pausado' => ['vigente']`. `pausado` no admite
   `cancelado` directo salvo que el dueño lo pida explícito — no lo agregues
   sin confirmarlo; el CA de la HU solo pide `vigente ↔ pausado`.
3. **Migración `ALTER`**: solo Postgres (`DB::getDriverName() === 'pgsql'`),
   `DROP CONSTRAINT` + `ADD CONSTRAINT` del mismo `CHECK` sumando `'pausado'`
   — no reescribas la tabla ni migres datos, es agregar un valor al enum de
   la base.
4. **`MaquinaEstadosContrato`**: sumá `pausar()`/`reanudar()` (o los nombres
   que sigan el criterio de `activar()`/`finalizar()`/`cancelar()` ya
   existente) y el caso nuevo en el `match` de `cambiarA()`. Sin guarda de
   datos adicional — pausar/reanudar es una decisión del encargado, no
   depende de fechas como `activar()`.
5. **Etiquetas** (`lang/es/comercial.php`): agregá `pausado` a
   `contrato.estado`, y traducí las cuatro etiquetas del panel al vocabulario
   del dueño (En Ejecución/En Aprobación/Ejecutado/Pausado) **sin** cambiar
   las claves que usa `EstadoContrato` ni lo que se guarda en base — es
   relabeling de presentación, `CAMBIO_TERMINOLOGIA` según el propio
   documento de observaciones, no un cambio de modelo.
6. **Panel**: acción "Pausar"/"Reanudar" en el listado de contratos, mismo
   patrón que `accion_activar`/`accion_finalizar` (confirmación +
   `CambiarEstadoContratoRequest`). `CambiarEstadoContratoRequest` ya valida
   contra el enum — confirmá que acepta el valor nuevo sin tocar su lógica.

## Qué NO hacer

- No agregues un campo booleano de "pausado" aparte del `estado`: es un
  estado más de la misma máquina, invariante 7.
- No permitas `pausado → cancelado` ni `pausado → finalizado` — el CA pide
  solo el ida y vuelta con `vigente`. Si te parece razonable ampliarlo, no lo
  hagas: no es tu decisión, escribilo como pendiente si querés dejarlo
  anotado.
- No reescribas `EstadoContrato`/`TransicionesContrato` más allá de sumar el
  caso nuevo — no es la tarea para revisar el resto de la máquina.
- No toques `com_contrato_ventanas` ni ningún otro campo del contrato — es
  solo el estado y su etiqueta.

## Cómo repartir las etapas

- **Etapa 1**: enum, tabla de transiciones, migración `ALTER` del `CHECK`,
  `MaquinaEstadosContrato`, tests unitarios de transición válida/inválida.
- **Etapa 2**: etiquetas, panel (acción + confirmación), tests Feature end
  to end, `bin/verify`.

## Criterio de aceptación

- `./bin/verify` = 0.
- Test: un contrato `vigente` pasa a `pausado` y de vuelta a `vigente`.
- Test: `borrador → pausado` se rechaza (`TransicionContratoNoPermitida`).
- Test: `pausado → cancelado` se rechaza (no está en la tabla de
  transiciones).
- Test de regresión: el enum guardado en base para un contrato `vigente`
  existente no cambia de valor por esta migración.

## Puede tocar

`app/Dominios/Comercial/Dominio/**`, `app/Dominios/Comercial/Aplicacion/MaquinaEstados/**`,
`app/Dominios/Comercial/Infraestructura/Http/**` (vistas de contratos),
migración `ALTER` nueva, `lang/es/comercial.php`, `tests/**`.

## Cierre obligatorio de cada etapa

`runs/87.estado`, `runs/87.md`, y al `OK` `runs/87.pr.md`. Commits agrupados
por función, español, imperativo, sin `Co-Authored-By`.

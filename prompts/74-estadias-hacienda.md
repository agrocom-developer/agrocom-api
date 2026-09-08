<!-- ciclo: critica=si turno-noche=1 descongela=tests rama=feature/estadias-hacienda etapas=3 -->

# Tarea 74 — HU-51: entrada y salida del equipo en cada hacienda

## Por qué esta tarea

El dueño lo pidió el 7/9/2026 junto con los equipos: *"los registros de entrada
y salida de las haciendas"*. Es el dato que falta para cerrar el circuito que
abrió la tarea 73: si el gasto se imputa al equipo, hay que poder decir **dónde
y cuántos días** estuvo ese equipo, o el costo por hectárea de una propiedad no
se puede sostener con nada.

Lo carga el equipo desde la app de campo, al llegar y al irse — no la oficina.
Eso lo convierte en una entidad que **nace en el dispositivo** y entra por
`POST /api/sync`, con todo lo que eso implica: `uuid_cliente`, idempotencia en
la base, `duplicado` ante reintento (invariante 1 de `CLAUDE.md`).

**Es crítica: toca el motor de sync.** Revisión posterior a la integración,
anotada en `runs/revision-pendiente.txt` — el PR **no** se retiene en borrador.
La razón está escrita en `CLAUDE.md`: retener el PR #46 bloqueó doce HU.

Depende de la tarea 72 (equipos). **No de la 69**: la estadía no lleva
`campania_id` (corrección del 8/9/2026).

## Lo que ya existe

- **El motor**: `Sincronizacion/Aplicacion/SincronizarLote.php`. Mirá
  `ORDEN_CAUSAL` (hoy `['trabajo','recepcion_caldo','sesion','condiciones',
  'incidencia','recarga','cierre_trabajo','cierre_sesion']`), el `match` de
  `aplicar()`, y cómo cada registro se procesa **en su propia transacción** —
  nunca el lote entero en una.
- **El molde de idempotencia**: `ope_trabajos` y `ope_sesiones` tienen índice
  único **parcial** sobre `uuid_cliente` (`WHERE deleted_at IS NULL`) y el
  duplicado se atrapa por `ON CONFLICT`, **nunca con un `SELECT` previo en el
  caso de uso**. Leé el docblock de la migración de `ope_trabajos`.
- **El contrato de escritura**: `Operaciones/Contratos/EscrituraSincronizacion`
  y su implementación Eloquent — el sync no toca modelos de otros módulos
  directamente.
- `Personal/Contratos/LecturaEquipoTrabajo` (tarea 72).

## Qué hacer

1. **`ope_estadias_hacienda`**: `uuid_cliente` (string 36),
   `equipo_trabajo_id` (FK plana a `per_equipos_trabajo`), `campo_id`
   (FK a `com_campos`), `entrada` (dateTime), `salida` (dateTime nullable),
   `vehiculo_id` (FK plana nullable a `man_vehiculos`), `observacion` (text
   nullable), + auditoría y soft delete.
   - Índice único parcial sobre `uuid_cliente` (`WHERE deleted_at IS NULL`) —
     **es el mecanismo real de idempotencia**, no un chequeo en código.
   - Índice único parcial sobre `equipo_trabajo_id`
     `WHERE salida IS NULL AND deleted_at IS NULL`: un equipo no puede tener dos
     estadías abiertas a la vez.
   - `CHECK (salida IS NULL OR salida > entrada)`, solo en pgsql.
   - **Sin columna de estado**: `salida IS NULL` significa "en curso". Una
     columna aparte solo podría desincronizarse de la fecha real, y además
     obligaría a una máquina de estados por la invariante 7 para algo que no es
     una transición de dominio (mismo criterio que `tiene_comprobante` en
     `fin_gastos`, descartado por la misma razón).
2. **Dos tipos nuevos en el sync**: `estadia_entrada` (abre) y `estadia_salida`
   (cierra, referenciando la estadía por su `uuid_cliente`, no por id de
   servidor — espec §2.1 punto 5). Van al final de `ORDEN_CAUSAL`: no son
   prerrequisito causal de nada.
3. **Reglas de aplicación**, en el contrato de escritura de `Operaciones`:
   - `estadia_entrada` con un equipo que ya tiene estadía abierta → `rechazado`
     con motivo, no excepción. Un rechazo **no frena el resto del lote**.
   - `estadia_salida` sobre una estadía inexistente o ya cerrada → `rechazado`
     con motivo.
   - Reintento del mismo `uuid_cliente` → `duplicado`, que el cliente trata
     como éxito.
   - **Sin campaña.** La estadía no la lleva: el campo dice de qué cliente es y
     la fecha de entrada ubica el ciclo. El piloto no elige campañas desde el
     celular, y con varias abiertas por cliente (ADR 0015 punto 1) deducir una
     sola sería inventar un dato. No agregues la columna ni la deduzcas al
     sincronizar.
4. **Pantalla de consulta** en el panel: estadías por rango de fechas, con
   filtro por equipo y por campo, y el total de días efectivos por equipo y por
   propiedad. Solo lectura — la estadía se carga desde la app, no desde el
   panel. Permiso `operaciones.estadia.ver` + ítem de menú.
5. **Documentación OpenAPI** del payload nuevo, code-first como el resto
   (ADR 0014), y actualización de la especificación §2.1 si hiciera falta
   listar los tipos.

## Qué NO hacer

- **No proceses el lote entero en una transacción.** Registro por registro, en
  transacciones individuales, con estado por registro.
- No hagas un `SELECT` previo para detectar duplicados: la idempotencia es del
  índice único, se atrapa por `QueryException`.
- No agregues `estado` a la estadía.
- No permitas crear ni cerrar estadías desde el panel en esta HU: si aparece la
  necesidad de corregir una estadía mal cargada, es un registro nuevo que anula
  al anterior (invariante 2), y eso es otra HU.
- No toques `ope_sesiones` ni el contrato de la app para sesiones.

## Cómo repartir las etapas

- **Etapa 1**: migración + modelo + contrato de escritura, con tests unitarios
  de los índices y los `CHECK`.
- **Etapa 2**: los dos tipos en el motor de sync, con **el test de replay**: el
  mismo lote aplicado 10 veces, en orden y en desorden, deja la base idéntica.
  Ese es el criterio del motor desde TE-05 y acá vuelve a aplicar.
- **Etapa 3**: pantalla de consulta, permisos, menú, OpenAPI, traducciones,
  `bin/verify`.

## Criterio de aceptación

- `./bin/verify` = 0 (en este Mac, `./bin/verify --sin-assets`).
- **Test de replay**: el mismo lote con `estadia_entrada` + `estadia_salida`
  aplicado 10 veces, en orden y en desorden, deja exactamente una fila y los
  mismos valores.
- Test: segundo `estadia_entrada` para un equipo con estadía abierta →
  `rechazado`, y **los registros siguientes del mismo lote se aplican igual**.
- Test: `estadia_salida` con `salida` anterior a `entrada` → `rechazado`.
- Test: reintento del mismo `uuid_cliente` → `duplicado`, no `rechazado`.
- Test: la pantalla suma correctamente los días efectivos por equipo (una
  estadía de 3 días cuenta 3, no 1).

## Puede tocar

`app/Dominios/Operaciones/**`, `app/Dominios/Sincronizacion/**`,
`app/Dominios/Seguridad/**` (solo `SeguridadSeeder` y `SecMenuSeeder`),
`database/migrations/**`, `database/seeders/Demo/**`, `lang/es/**`,
`routes/api.php`, `routes/web.php`, `tests/**`.

Fuera de alcance: `fin_*`, `com_contratos`, `per_*` (solo se lee por contrato),
la app Flutter (`agrocom-field`, otro repo).

## Cierre obligatorio de cada etapa

`runs/74.estado`, `runs/74.md`, y al `OK` `runs/74.pr.md`. Anotá la tarea en
`runs/revision-pendiente.txt` (es crítica: motor de sync). Commits agrupados por
función, en español, imperativo, sin `Co-Authored-By`.

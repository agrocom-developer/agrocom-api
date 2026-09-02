<!-- ciclo: critica=si turno-noche=1 descongela=tests rama=feature/recargas-dron etapas=4 -->

# Tarea 23 — HU-13: recargas del dron (batería, temperatura, combustible)

`plan_sprints.md`, Sprint 4, fila HU-13: "Como auxiliar, quiero registrar
cada recarga del dron con batería, temperatura y litros cargados, y dejar
constancia si el vuelo se retrasó o se rechazó por la calidad del caldo,
para deslindar responsabilidad". CA (columna de `plan_sprints.md`, la que
manda): "Alerta local si temperatura > 50 °C; recarga vincula sesión ↔
litros; combustible del generador; motivo y hora del retraso por caldo".

Escrita asumiendo que la tarea 22 (HU-08, incidencias) ya está integrada —
es el orden de la cola el que lo garantiza. No hay dependencia real de
código entre ambas (tablas distintas, sin FK cruzada), así que si por algún
motivo la 22 no llegó a integrarse, esta tarea NO queda bloqueada por eso —
seguí igual.

**Es crítica**: agrega un tipo de registro nuevo al motor de sync
(`Contratos/EscrituraSincronizacion.php`, `SincronizarLote::ORDEN_CAUSAL`,
`EscrituraSincronizacionEloquent`). El PR se abre en borrador.

## Decisión de alcance — leé esto antes que nada

`docs/especificacion/especificacion_funcional_tecnica.md` línea 136 (§4.3,
tabla `recargas`, ESCRITA ANTES DE CR-01) lista `mezcla_id` y
`problema_caldo` como columnas. **`mezcla_id` no aplica**: CR-01 (1/9/2026,
ver `plan_sprints.md` líneas 88-94) cerró que Agrocom no prepara la mezcla —
no existe módulo `Mezclas` ni concepto de mezcla propia, y la CA VIGENTE de
esta HU (la de `plan_sprints.md`, no la tabla §4.3 vieja) no menciona
`mezcla_id` en ningún lado. Guiate por la CA de `plan_sprints.md`, no por la
tabla §4.3 tal cual — es el mismo criterio que ya aplicó la tarea 18
(recepción de caldo) para dejar fuera la dosificación.

`problema_caldo` (enum `ninguno/filtro_tapado/grumos/decantacion/espuma/
color_olor_anormal`) sí es aprovechable, pero reencuadralo como "motivo del
retraso/rechazo por caldo" (lo que pide la CA), nullable — solo se completa
si hubo retraso o rechazo, no en cada recarga.

## Qué hacer

Cargá el skill `verificacion` y `dominio-backend`. Leé antes de tocar nada:

- `docs/especificacion/especificacion_funcional_tecnica.md` línea 136 (columnas
  de referencia, con el descarte de `mezcla_id` de arriba), línea 7.4 (cargas
  habituales por dron: T50 30L, T70 50L, T100 60L — informativo, NO es una
  validación de negocio pedida por la CA, no la impongas).
- `app/Dominios/Operaciones/Contratos/RegistroCondiciones.php` +
  `EscrituraSincronizacionEloquent::registrarCondiciones()` — mismo patrón a
  copiar: registro nuevo que referencia una `sesion` por `uuid_cliente`, sin
  `$operarioPersonaId` (la espec no define dueño individual — el auxiliar
  registra, pero no hay una noción de "pertenencia" del tipo
  `piloto_id`/operario del token a verificar, mismo criterio que
  `condiciones`/`recepcion_caldo`).
- `app/Dominios/Operaciones/Dominio/EstadoCoberturaTrabajo.php` y su
  docblock — ejemplo de cómo este repo representa un valor DERIVADO/de
  alerta sin inventar una máquina de estados nueva para algo que la espec no
  la pide. `alerta_temperatura` de esta tarea es ese mismo tipo de campo:
  una bandera calculada, no algo que bloquee el registro.

1. **`Dominio/RegistroRecarga.php` (Contratos)**: `uuid_cliente`,
   `sesion_uuid_cliente`, `secuencia` (entero, orden de la recarga dentro de
   la sesión), `litros_caldo`, `bateria_saliente_id` (identificador simple,
   texto libre — no existe catálogo de baterías en el esquema, no lo
   inventes acá), `temperatura_bateria_c`, `motivo_retraso_caldo` (opcional,
   del enum reencuadrado arriba), `hora_retraso` (opcional), `litros_combustible_generador`
   (opcional), `hora`.
2. **Alerta de temperatura, NO bloqueo**: si `temperatura_bateria_c > 50`, el
   registro se persiste igual con `alerta_temperatura = true` — la CA dice
   "alerta", no "rechaza". No repliques el patrón de `registrarCondiciones()`
   (que sí rechaza fuera de rango sin observación firmada) — son guardas
   distintas, esta HU no tiene esa condición de observación.
3. **Migración `ope_recargas`**: `sesion_id` FK real (`restrictOnDelete`),
   `secuencia`, `litros_caldo` decimal, `bateria_saliente_id` string,
   `temperatura_bateria_c` decimal, `alerta_temperatura` boolean (calculado
   al insertar, no se recalcula después), `motivo_retraso_caldo` nullable con
   `CHECK` en Postgres (mismo patrón que `ope_condiciones.momento`),
   `hora_retraso` nullable, `litros_combustible_generador` nullable, `hora`,
   auditoría + soft delete, índice único parcial sobre `uuid_cliente`.
4. **`EscrituraSincronizacion::registrarRecarga()`** + implementación
   Eloquent: resuelve la sesión por `uuid_cliente` (rechaza si no existe),
   calcula `alerta_temperatura`, crea la fila en una transacción,
   `duplicado` vía la violación del `UNIQUE` (nunca un `SELECT` previo).
5. **`SincronizarLote`**: agregá `'recarga'` a `ORDEN_CAUSAL`, después de
   `'sesion'` (junto a `'condiciones'`/`'incidencia'` si ya están — el orden
   entre esos tres no importa, ninguno depende de los otros dos). Agregá
   `aplicarRecarga()`.
6. **`composer openapi`** al final; confirmá que el diff no borra nada ya
   commiteado.

### Tests de integración (`tests/Feature/Api/RecargaSincronizacionTest.php`, nuevo)

1. Sin `sesion_uuid_cliente` válida (sesión inexistente) → `rechazado`.
2. `temperatura_bateria_c` ≤ 50 → `aplicado`, `alerta_temperatura = false`.
3. `temperatura_bateria_c` > 50 → `aplicado` igual (no rechaza),
   `alerta_temperatura = true`.
4. Recarga con `motivo_retraso_caldo` y `hora_retraso` → persistidos
   correctamente.
5. Recarga sin motivo de retraso (caso normal) → ambos campos `null`.
6. Reintento idempotente del mismo `uuid_cliente` → `duplicado`, sin duplicar
   la fila.

### Test unitario del DTO

`RegistroRecarga::intentarDesdeArreglo()` devuelve `null` ante datos
incompletos o mal tipados — agregalo a
`tests/Feature/EscrituraSincronizacionTest.php` (congelado,
`descongela=tests` ya declarado te habilita a tocarlo).

## Cómo repartir las etapas

1. DTO + migración + `registrarRecarga()` + `ORDEN_CAUSAL`.
2. Los seis casos de integración + el test unitario del DTO.
3. `composer openapi`, pulido.
4. Cascada verde, revisión final del alcance (sin `mezcla_id`, sin gasto
   real de combustible — ver "Qué NO hacer").

## Qué NO hacer

- No agregues `mezcla_id` ni ninguna referencia a un módulo `Mezclas` — ver
  "Decisión de alcance" arriba, CR-01 lo descarta explícitamente.
- No conviertas "combustible del generador" en un gasto real
  (`gastos`/`movimientos_stock`/`gasto_id`). Esas tablas son de Fase 3
  (sprint 9 en adelante, ver `plan_sprints.md` "Después de la v1.0") y no
  existen todavía. `litros_combustible_generador` acá es un dato informativo
  simple, sin costeo ni vínculo contable.
- No bloquees el registro por temperatura alta — persistí la alerta, no
  rechaces la recarga.
- No valides `litros_caldo` contra la capacidad del dron (T50/T70/T100,
  espec §7.4) — es informativo, ninguna validación de negocio lo pide.
- No toques `ope_condiciones` ni `ope_incidencias` (tarea 22) — tabla nueva,
  sin relación con esas dos más que compartir `sesion_id` como FK.

## Criterio de aceptación

`./bin/verify` = 0, con los tests de arriba en verde.

## Cierre obligatorio de cada etapa

`runs/23.estado` (`PARCIAL`/`OK`/`BLOQUEADA`). `runs/23.md` con qué se hizo y
qué falta. Al llegar a `OK`, `runs/23.pr.md`.

## Commits

Agrupados: DTO + migración + motor de sync; tests. Español, imperativo, el
porqué antes que el qué. Sin trailer `Co-Authored-By`.

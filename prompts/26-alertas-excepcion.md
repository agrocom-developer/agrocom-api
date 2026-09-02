<!-- ciclo: critica=no turno-noche=1 descongela=tests rama=feature/alertas-excepcion etapas=4 -->

# Tarea 26 — HU-19: bandeja de alertas por excepción

`plan_sprints.md`, Sprint 5, fila HU-19: "Como encargado, quiero recibir
solo alertas por excepción (sin evidencia, suma excedida, sin orden, batería
caliente, desvío de mezcla ±5%, lote parado 48h), para no revisar todo". CA:
"Bandeja de alertas en panel con estado atendida/pendiente".

Escrita asumiendo que las tareas 20 (relevo de piloto/tolerancia), 17
(condiciones de vuelo) y 23 (recargas del dron) ya están integradas — son la
fuente de datos de las alertas que esta tarea sí implementa. Sin dependencia
real de la 24/25 (acta/reporte): no hay FK ni caso de uso compartido.

No es crítica: no toca el motor de sync, no muta la máquina de estados de
`Trabajo`/`Sesion` (solo lee), no genera dinero.

## El recorte real: de 13 alertas de la espec, esta tarea implementa 4

La espec (§10) lista 13 condiciones de alerta. La mitad no tiene datos reales
detrás en este momento — implementarlas sería simular una cobertura que no
existe. Antes de escribir código, confirmá este análisis contra el estado
actual del código (puede haber cambiado si algo se integró distinto de lo
esperado) y documentá cualquier diferencia en `runs/26.md`.

**Sí entran (datos ya persistidos por tareas cerradas):**

1. **Batería caliente** — `ope_recargas.alerta_temperatura = true` (tarea
   23, temperatura > 50 °C). Ya calculado y persistido; esta tarea solo lo
   convierte en alerta de bandeja.
2. **Dron sospechoso** — mismo `dron_id` (vía `sesion_id` → `ope_sesiones.dron_id`,
   tarea 20) con 3 o más recargas de `alerta_temperatura = true`. Requiere un
   `JOIN` `ope_recargas` → `ope_sesiones`, no hay FK directa
   dron↔recarga.
3. **Condiciones forzadas** — `ope_condiciones.autorizado_con_observacion = true`
   (tarea 17): el trabajo arrancó fuera de rango con observación firmada del
   agrónomo.
4. **Suma excedida** — `EstadoCoberturaTrabajo::Observado` (tarea 20,
   `app/Dominios/Operaciones/Dominio/EstadoCoberturaTrabajo.php`): sesiones
   que superan hectáreas del lote + tolerancia.

**No entran, con su porqué (no las implementes, ni como placeholder):**

- **Hectáreas incoherentes** y **desvío de mezcla** (ambas ±%): comparan
  contra una dosis/receta ordenada que no existe — CR-01 sacó `Mezclas` de
  alcance. No hay con qué comparar.
- **Mezcla fuera de orden**: depende del mismo módulo `Mezclas` inexistente.
- **Consumo anómalo** (combustible/hectárea vs. promedio del dron): el
  "promedio del dron" no está definido en ningún lado del sistema (¿de qué
  período? ¿de qué ventana móvil?) — es una decisión de negocio sin tomar,
  no una tarea técnica. Anotalo como pregunta abierta si querés, pero no la
  inventes.
- **Sin evidencia** (sesión cerrada sin captura de RC, o lote conformado sin
  imagen del campo): la segunda mitad ya es estructuralmente imposible —
  `cerrarTrabajo()` (tarea 21) rechaza el cierre sin `imagen_campo_evidencia_id`,
  así que un trabajo cerrado siempre la tiene. La primera mitad
  (`sesiones.captura_rc_id`) no existe como columna — es el hallazgo
  informativo que dejó la tarea 21 (ver `docs/gestion/cola_tareas.md`, fila
  21). Agregar esa columna es un cambio de alcance de HU-09, no de esta
  tarea — no lo hagas acá.
- **Lote parado** (trabajo `parcial` sin sesión nueva en 48h): es detectable
  con los datos que ya existen, pero necesita un barrido periódico (no hay
  ningún evento de dominio que dispare "pasaron 48 horas"). Este proyecto no
  tiene un `Schedule` de Laravel configurado todavía (`routes/console.php`
  solo trae el comando de ejemplo) y wiring de cron real es tarea de TE-10
  (producción), que depende de infraestructura que no existe (ADR 0010). No
  la implementes con un cronjob del sistema operativo ni con un
  `sleep`/polling — si querés, dejá el query de detección escrito y
  documentado como pendiente de enganchar a un scheduler, pero SIN
  registrarlo en `routes/console.php` como si ya corriera solo.
- **Sin orden**: hoy es un rechazo de `POST /api/sync` (`EscrituraSincronizacionEloquent::abrirTrabajo()`,
  hallazgo de la tarea 12), no un registro persistido — el piloto/app lo ve
  al instante en la respuesta del sync, no hace falta una alerta para el
  encargado sobre algo que nunca llegó a existir en la base.
- **Anticipo al límite** y **rendición pendiente**: dependen de `anticipos`/
  `rendiciones`, módulos que no existen (Fase 4, muy posterior a este
  sprint). Mismo criterio que la sección "Condicionadas" de
  `docs/gestion/cola_tareas.md`: el gate se escribe cuando el dominio exista.

## Qué hacer

Cargá los skills `verificacion`, `dominio-backend` y `seguridad-roles`. Leé
antes de tocar nada:

- `docs/especificacion/especificacion_funcional_tecnica.md` línea 350-368
  (tabla completa de alertas — referencia, no todas aplican, ver arriba).
- `app/Dominios/Operaciones/Infraestructura/Eloquent/Recarga.php`,
  `Condiciones.php`, `Sesion.php` (columna `dron_id`), y
  `Dominio/EstadoCoberturaTrabajo.php` + `Aplicacion/CalcularCoberturaTrabajo.php`
  — son las cuatro fuentes de datos de esta tarea, ya construidas.
- `app/Dominios/Operaciones/Aplicacion/MaquinaEstados/MaquinaEstadosTrabajo.php`
  y el listener de `SesionValidada` → devengo (`Finanzas`, tarea 16) — patrón
  de referencia para enganchar generación de alertas a un evento de dominio
  existente, si decidís esa vía en lugar de un caso de uso que las genere al
  vuelo (ver diseño, punto 2).

### Diseño

1. **Migración `ope_alertas`**: `tipo` (`bateria_caliente` / `dron_sospechoso`
   / `condiciones_forzadas` / `suma_excedida`, `CHECK` en Postgres), una
   referencia polimórfica mínima al origen (`trabajo_id`/`sesion_id`/
   `recarga_id`/`condiciones_id`, nullable según el tipo — documentá el
   criterio elegido, no hace falta una tabla morph completa de Eloquent si
   con columnas nullable alcanza), `mensaje` (texto armado al generar, no
   recalculado en cada lectura), `estado` (`pendiente`/`atendida`),
   `atendida_por`/`atendida_en` nullable, auditoría + soft delete. Índice
   único parcial sobre `(tipo, sesion_id)` o el que corresponda por tipo,
   para no duplicar la misma alerta si el evento que la genera se reintenta
   (idempotencia, mismo criterio que el resto del dominio).
2. **Generación**: enganchá cada una de las 4 alertas al punto del código
   donde el dato que la dispara ya se persiste — `registrarRecarga()` (tarea
   23) para batería caliente y dron sospechoso, `registrarCondiciones()`
   (tarea 17) para condiciones forzadas, y la transición a `Observado` de la
   cobertura para suma excedida (esta última puede necesitar un listener
   sobre el evento de cierre de sesión/trabajo si la cobertura se calcula
   solo en lectura — documentá cómo la enganchaste, es la decisión de diseño
   más real de esta tarea). Nunca genera una alerta duplicada para el mismo
   hecho (reintento idempotente del sync no debe crear una segunda fila).
3. **`GET /api/alertas?estado=&tipo=`**: bandeja filtrable, mismo patrón de
   filtros que el tablero de trabajos (tarea 15).
4. **`POST /api/alertas/{id}/atender`**: transiciona `pendiente → atendida`,
   registra quién y cuándo. Reintento sobre una ya `atendida` → idempotente,
   no error.
5. **Permisos**: una acción `sec_action` para ver la bandeja y otra para
   atender, asignadas al encargado de operaciones (y dueño, según la fila de
   la espec línea 88 — "Ver reportes técnicos" no aplica acá, buscá si hay
   una fila específica de alertas; si no la hay, asignalo por el mismo
   criterio que el resto de las pantallas de supervisión).
6. **Panel**: pantalla nueva mínima (bandeja con filtro por estado/tipo y
   acción de atender) — a diferencia de las tareas 24/25, acá SÍ hace falta
   una pantalla propia porque no hay una existente que la contenga.
7. **`composer openapi`** al final.

### Tests de integración (`tests/Feature/Api/AlertaExcepcionTest.php`, nuevo)

1. Registrar una recarga con `temperatura_bateria_c > 50` → genera alerta
   `bateria_caliente`, estado `pendiente`.
2. Registrar una recarga con temperatura normal → NO genera alerta.
3. Tres recargas con `alerta_temperatura = true` sobre el mismo dron (vía
   sesión) → genera además `dron_sospechoso`; la segunda y tercera batería
   caliente sobre el mismo dron NO duplican la alerta de dron sospechoso.
4. Condiciones con `autorizado_con_observacion = true` → genera alerta
   `condiciones_forzadas`.
5. Sesiones cuya suma supera hectáreas + tolerancia (cobertura `Observado`,
   mismo escenario que el test de la tarea 20) → genera `suma_excedida`.
6. Reintento del mismo evento (recarga/condiciones/cierre) vía sync → no
   duplica la alerta ya generada.
7. `POST /api/alertas/{id}/atender` → estado `atendida`, `atendida_por`/
   `atendida_en` correctos.
8. Reintento de atender una alerta ya `atendida` → idempotente.
9. `GET /api/alertas?estado=pendiente` → filtra correctamente.

## Cómo repartir las etapas

1. Migración `ope_alertas` + generación de las dos alertas más simples
   (batería caliente, condiciones forzadas — enganchadas directo en el caso
   de uso que ya persiste el dato disparador).
2. Dron sospechoso (join) + suma excedida (la más compleja, cobertura de
   lectura) + su enganche.
3. Endpoints (`GET /api/alertas`, `POST /atender`) + permisos + pantalla
   mínima del panel.
4. Los nueve casos de test de arriba + `composer openapi` + pulido.

## Qué NO hacer

- No implementes las 9 alertas fuera de alcance listadas arriba, ni como
  placeholder ni como TODO en código — quedan documentadas en el prompt y en
  `cola_tareas.md`, no hace falta repetirlas en el código.
- No agregues `sesiones.captura_rc_id` — es un cambio de alcance de HU-09,
  ver arriba.
- No configures un cronjob del sistema operativo ni un scheduler de Laravel
  para "lote parado" — dejalo fuera, documentado.
- No inventes una definición de "promedio del dron" para consumo anómalo —
  es una decisión de negocio sin tomar.
- No toques `EstadoCoberturaTrabajo` ni `CalcularCoberturaTrabajo` — son
  proyecciones de lectura ya cerradas por la tarea 20, esta tarea solo las
  consume.

## Criterio de aceptación

`./bin/verify` = 0, con los nueve tests de arriba en verde.

## Cierre obligatorio de cada etapa

`runs/26.estado` (`PARCIAL`/`OK`/`BLOQUEADA`). `runs/26.md` con qué se hizo,
cómo enganchaste la alerta de suma excedida (el punto de diseño más real de
la tarea), y qué falta. Al llegar a `OK`, `runs/26.pr.md`.

## Commits

Agrupados: migración + generación de alertas simples (batería/condiciones);
dron sospechoso + suma excedida; endpoints + permisos + pantalla; tests.
Español, imperativo, el porqué antes que el qué. Sin trailer
`Co-Authored-By`.

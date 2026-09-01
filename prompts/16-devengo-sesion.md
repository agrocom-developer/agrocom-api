<!-- ciclo: critica=si turno-noche=1 descongela=tests rama=feature/devengo-sesion etapas=4 -->

# Tarea 16 — HU-16: devengo automático al validar una sesión

Con la tarea 14 (HU-14) integrada, `MaquinaEstadosSesion::validar()` ya
dispara `App\Dominios\Operaciones\Dominio\Eventos\SesionValidada` — sin
oyente real (`app/Dominios/Operaciones/Dominio/Eventos/SesionValidada.php`
lo documenta explícito: "sin oyente real todavía, HU-16"). Esta tarea escribe
ese oyente: el devengo del piloto y su auxiliar, generado **solo** al
validar, nunca al cerrar (invariante 3 de `CLAUDE.md`).

**Es crítica**: es un listener que genera dinero — exactamente lo que
`CLAUDE.md` no delega sin revisión línea por línea. El PR se abre en
borrador.

## Qué hacer

Cargá el skill `verificacion`, y `dominio-backend` para la arquitectura
modular. Leé antes de tocar nada:

- `app/Dominios/Operaciones/Dominio/Eventos/SesionValidada.php` y
  `app/Dominios/Operaciones/Aplicacion/MaquinaEstados/MaquinaEstadosSesion.php`
  (dónde se dispara el evento, y su garantía de idempotencia por disparo).
- `docs/especificacion/especificacion_funcional_tecnica.md` §4.4 (tabla
  `devengos_personal`: "Se calcula por sesión, no por lote... Se genera
  automáticamente al validar un trabajo, nunca al cerrarlo") y §4.2
  (`personas` — `tarifa_ha` diferido, ver abajo).
- `docs/decisiones/0011-convencion-prefijos-tabla.md` — `Finanzas` es
  `fin_`; `tarifa_ha`/`sueldo_mensual` de `personas` quedaron **fuera** de la
  migración de HU-01 a propósito, "se agregan por `ALTER TABLE` cuando se
  implemente devengos/planilla" — esta tarea es esa.
- `app/Dominios/Personal/Infraestructura/Eloquent/PerPersona.php` y sus
  `Contratos/` (`LecturaPersonas`, `PersonaCatalogo`) — el patrón de DTO
  primitivo que `Finanzas` debe copiar para leer tarifa, nunca importando
  `PerPersona` directo (ADR 0003, regla 2).
- `app/Dominios/Operaciones/Contratos/` (`CierreSesion`, `EscrituraSincronizacion`)
  — mismo patrón para que `Finanzas` lea `piloto_id`/`auxiliar_id`/
  `hectareas_declaradas` de una `Sesion` sin importar su modelo Eloquent.

El módulo `Finanzas` (`app/Dominios/Finanzas/`) no existe todavía — la creás
en esta tarea, con la misma forma que los demás (`Contratos/`, `Aplicacion/`,
`Dominio/`, `Infraestructura/`). `ArquitecturaModulosTest` la descubre sola
(`glob` por carpeta), no hace falta tocarla.

1. **Migraciones.**
   - `ALTER TABLE per_personas ADD tarifa_ha DECIMAL(...)` nullable (los
     `per_personas` de hoy no la tienen — no inventes un default de
     negocio). Documentá en `runs/16.md` qué pasa si una persona sin
     `tarifa_ha` termina siendo `piloto_id`/`auxiliar_id` de una sesión que
     se valida: es una decisión real, no la esquives ni la dejes generar un
     devengo con monto `0`/`null` en silencio.
   - `CREATE TABLE fin_devengos_personal` (espec §4.4: `persona_id`,
     `sesion_id`, `hectareas`, `tarifa_ha`, `monto`, `fecha`, más las
     columnas de auditoría/soft-delete de todo modelo de dominio, ADR 0007).
     `UNIQUE (sesion_id, persona_id)` — la idempotencia real, invariante 3
     literal.
   - Contrato nuevo en `Personal/Contratos/` para leer `tarifa_ha` por
     persona (extender `LecturaPersonas` o uno nuevo — tu criterio, pero DTO
     primitivo, nunca Eloquent cruzado).
   - Contrato nuevo en `Operaciones/Contratos/` para que `Finanzas` lea los
     datos de una sesión validada (`piloto_id`, `auxiliar_id`,
     `hectareas_declaradas`) sin importar `Sesion` Eloquent.
2. **Caso de uso + listener.** `Finanzas/Aplicacion/` calcula el devengo:
   `monto = hectareas_declaradas × tarifa_ha`, con **`bcmath`** (`bcmul`,
   escala 2) — nunca multiplicación de `float`. Invariante 6 de `CLAUDE.md`
   ("todo monto derivado debe poder recalcularse... y cuadrar exacto") es
   literal acá: un float arrastra error de redondeo que un test con
   decimales "difíciles" (p. ej. `12.35 × 3.33`) detecta. Un
   `FinanzasServiceProvider::boot()` registra `Event::listen()` sobre
   `SesionValidada` — mismo patrón que
   `SeguridadServiceProvider::boot()` con `TokenAuthenticated`. El listener
   genera un devengo para `piloto_id` siempre, y para `auxiliar_id` solo si
   no es `null` (espec §5: "Genera el devengo de ese piloto **y su
   auxiliar**").
3. **Gate de invariante 3 + exactitud.** Un test que prueba que
   `MaquinaEstadosSesion::cerrar()` (sin pasar por `validar()`) nunca crea
   una fila en `fin_devengos_personal` — la aduana que
   `docs/gestion/cola_tareas.md` (sección "Condicionadas") deja pendiente
   para esta tarea. Más el test de exactitud decimal.
4. **Integración de punta a punta.** Validar una sesión con piloto+auxiliar
   → dos devengos; sin auxiliar → uno; reintentar `validar()` sobre la misma
   sesión → no duplica (el `UNIQUE` sostiene lo que la tarea 14 ya garantizó
   a nivel de disparo del evento, como segunda capa).

## Cómo repartir las etapas

1. Migraciones (`tarifa_ha`, `fin_devengos_personal`) + contratos de lectura
   + esqueleto del módulo `Finanzas` + modelo `DevengoPersonal`.
2. Caso de uso de cálculo (`bcmath`) + listener registrado sobre
   `SesionValidada`.
3. Gate de invariante 3 + tests de exactitud decimal + decisión documentada
   sobre persona sin `tarifa_ha`.
4. Tests de integración completos (piloto+auxiliar, sin auxiliar,
   reintento) + pulido, cascada verde.

## Qué NO hacer

- Sin pantalla de panel — el criterio de la HU no pide una, y HU-19
  (bandeja de alertas, sprint 5) es donde el devengo se vuelve visible para
  alguien. No la anticipes.
- Sin `sueldo_mensual` (jefe/encargado) — eso no es un devengo por hectárea,
  queda fuera de esta tarea.
- Sin `anticipos`/`liquidaciones`/`planillas` — sprint 5/7-8, no esta tarea.
- No reabras el mecanismo de rechazo ni la cola de validación de la tarea 14.
- Nada de `agrocom-field`/Flutter.

## Criterio de aceptación

`./bin/verify` = 0, con tests nuevos:

1. Validar una sesión con `piloto_id` y `auxiliar_id` genera dos filas en
   `fin_devengos_personal`, cada una con su `tarifa_ha` y `monto` exacto.
2. Validar una sesión sin auxiliar genera una sola fila.
3. Reintentar `validar()` sobre la misma sesión no duplica devengos.
4. `cerrar()` una sesión (sin validarla) no crea ninguna fila en
   `fin_devengos_personal` — gate de invariante 3.
5. Un caso con decimales que un `float` redondearía mal (p. ej.
   `hectareas = 3.33`, `tarifa_ha = 12.35`) da el monto exacto esperado.

## Cierre obligatorio de cada etapa

`runs/16.estado` (`PARCIAL`/`OK`/`BLOQUEADA`). `runs/16.md` con lo hecho, la
decisión sobre persona sin `tarifa_ha`, y qué falta (planilla/liquidaciones
quedan para sprints posteriores — decilo explícito para que no se
redescubra). Al llegar a `OK`, `runs/16.pr.md`.

## Commits

Agrupados: migraciones y contratos, caso de uso y listener, gate e
exactitud, tests de integración. Español, imperativo, el porqué antes que
el qué. Sin trailer `Co-Authored-By`.

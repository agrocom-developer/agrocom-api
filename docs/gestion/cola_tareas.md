# Cola de tareas automatizables

**Última actualización: 7/9/2026 (Sprint 13 planificado — tareas 69 a 75).**
El dueño trajo un lote de ajustes de negocio el 7/9/2026 (mensajes de WhatsApp
y la referencia al informe de contratos de producción de synagro). Se
consolidaron en el **ADR 0015**, en la especificación (§4.0, §4.1, §4.2, §4.3,
§4.4, §5, §9.1) y en el **Sprint 13** de `plan_sprints.md`, y de ahí salen las
siete filas nuevas (69 a 75).

Ese mismo día llegó una **segunda tanda**, esta mirando el panel andando:
inputs de fecha y desplegables obsoletos, propiedades y lotes mezclados en un
solo menú, "Personas" que debía decir "Personal", repuestos por casillas,
la pestaña de Facturación vacía, el mapa sin pantalla completa, y una
configuración del sistema donde poner llaves y tokens. Son las filas 76 a 80,
en el **Sprint 14**. El orden de ejecución cruza las dos tandas — ver "Por qué
ese orden" al final.

Con esto la cola vuelve a tener trabajo escrito: la planificación anterior había
llegado a "detrás de la 61 no queda ninguna fila más" y el ciclo se detuvo solo
— ver `runs/DETENER`.

Dos de los ajustes ya estaban resueltos en el modelo y **no generaron tarea**:
"un cliente varios campos" y "un campo puede tener varios lotes" ya son
`com_clientes → com_campos → com_lotes`, y el alta de campo pide `lotes[]` con
mínimo uno. Se verificó antes de escribir nada.

Este es el backlog que el ciclo de
`bin/ciclo` consume solo — el punto 3 de
[automatizacion_desarrollo.md](automatizacion_desarrollo.md). No reemplaza a
`plan_sprints.md`: ahí están las HU y TE con su alcance de negocio; acá está lo
que además tiene **criterio de aceptación ejecutable**, que es lo único que
puede avanzar sin nadie mirando la pantalla.

La cola en ejecución es `runs/cola.txt` (ids, uno por línea, en orden). Esta
tabla es su versión legible, con el porqué de cada fila.

**Punto de partida limpio (1/9/2026).** Los prompts de las tareas 01 a 08 se
borraron del árbol —quedan en el historial de git— y `runs/cola.txt` arrancó
vacía. La numeración **sigue desde 09**, no vuelve a 01: `runs/` conserva los
estados de las tareas viejas y un id repetido se leería como ya cerrado, así que
la tarea nueva se saltearía sola. `runs/` no se versiona; es la bitácora local de
lo que ya corrió.

**Una fila = una HU o TE entera del plan de sprints = un PR.** No media
historia, no "la primera parte de". Si no entra en una sesión, el ciclo le da
varias etapas sobre la misma rama — ver `automatizacion_desarrollo.md` §5. El
único recorte válido es el que tiene una razón de dominio y queda escrito con su
porqué en el prompt (como el de la 08, que dejó recetas y productos para cuando
exista el módulo `Mezclas`).

## Cómo se lee una fila

| Campo | Qué significa |
|---|---|
| **Id** | Prefijo del prompt: `prompts/NN-slug.md` |
| **Criterio** | El comando que la acepta. Si no se puede escribir un comando, la tarea no entra en la cola |
| **Puede tocar** | El alcance de archivos. Lo de afuera es un hallazgo del verificador |
| **Crítica** | `sí` = está en la lista de "qué no delegar sin revisión línea por línea" de `CLAUDE.md`. Se implementa igual, pero el PR se abre en borrador y lo revisa una persona |
| **Etapas** | Sesiones que el ciclo puede encadenar sobre la misma rama para cerrar la HU. Se dimensiona por el tamaño de la historia, no por miedo |

## Cola

| Id | Tarea | Criterio | Puede tocar | Crítica | Etapas | Estado |
|---|---|---|---|---|---|---|
| 03 | HU-03 — token Sanctum por dispositivo | `./bin/verify` = 0 | `app/Dominios/Seguridad/**`, migraciones, seeders de catálogo, rutas, `tests/Feature/Seguridad/**` | no | 3 | **hecha** |
| 04 | Aduana de la invariante 7: ninguna asignación de estado fuera del servicio de estados | `./bin/verify` = 0, y el gate falla al inyectarle una asignación suelta | `tests/Unit/**`, `app/Dominios/Compartido/**` | no | 3 | **hecha** |
| 05 | Rescate del dashboard: integrar `feature/dashboard-agro`, cuyo PR se cerró sin mergear | `./bin/verify` = 0 y el PR abierto fuera de borrador | la rama `feature/dashboard-agro`, `docs/gestion/plan_dashboard_rediseno.md` | no | 3 | **hecha** |
| 06 | Bitácora de auditoría transversal (invariante 9, ADR 0007) y su gate | `./bin/verify` = 0, y el gate falla ante un modelo de dominio sin bitácora | `app/Dominios/Compartido/**`, migraciones, `tests/**`, la fila de prefijo del ADR 0011 | no | 3 | **hecha** |
| 07 | Regresión visual del panel con Playwright | `npx playwright test` = 0 con capturas de referencia versionadas | `playwright.config.*`, `tests/Visual/**`, `package.json` | no | 3 | **hecha** |
| 08 | TE-06 (parcial) — pull de catálogo con cursor: órdenes, lotes y personas | `./bin/verify` = 0 | `app/Dominios/Sincronizacion/**`, `Contratos/` de Operaciones/Comercial/Personal, rutas de API, tests de feature | no | 3 | **hecha** (PR #40) |
| 09 | TE-05 — `POST /api/sync` idempotente | `./bin/verify` = 0, con test de replay (mismo lote 10 veces, en orden y en desorden → base idéntica) | `app/Dominios/Sincronizacion/**`, migraciones, tests | **sí** | 5 | **hecha** (PR #46, mergeado 1/9/2026 después de la revisión línea por línea; dejó dos hallazgos abiertos que cierra la tarea 12) |
| 10 | HU-20 — `GET /api/version` y autorización de versiones del APK, sin hospedar el binario (vive en `agrocom-field`, ver regla de exclusión abajo) | `./bin/verify` = 0 | módulo nuevo `Distribucion` (`dis_`), `sec_action`/seed de permisos, rutas de API, pantalla del panel, tests | no | 3 | **hecha** (tarea 11 corrigió el alcance: la columna pasó de `ruta_apk`/disco `r2` a `url_apk`, la URL del release en `agrocom-field`; PR #47, mergeado 1/9/2026) |
| 11 | Corrección de alcance de la 10: el binario del APK no se hospeda en este repo | `./bin/verify` = 0 | `Distribucion`, migración, pantalla del panel | no | 2 | **hecha** (PR #47) |
| 12 | Los dos hallazgos de la revisión del motor de sync: `hectareas_declaradas` sin validar y `POST /api/sync` sin scoping por el operario del token | `./bin/verify` = 0, con un test de `hectareas_declaradas` no numérica y otro de `lote_id`/`piloto_id` ajenos al token | `Operaciones/Contratos/**`, `EscrituraSincronizacionEloquent`, `Sincronizacion/Aplicacion/**`, tests | **sí** | 2 | **hecha** (PR #49, mergeado 1/9/2026) |
| 13 | HU-05 — cierre real de trabajo y sesión (`abierto → cerrado`), motor de sync extendido, visible en una pantalla mínima del panel | `./bin/verify` = 0, con test de cierre idempotente y de rechazo de transición inválida | `Operaciones/**`, `Sincronizacion/Aplicacion/**`, migraciones, `Seguridad/**` (pantalla panel), tests | **sí** | 4 | **hecha** (PR #50, mergeado 1/9/2026) |
| 14 | HU-14 — cola de validación de sesiones: transición `cerrado → validado` con evento `SesionValidada`, policy validador≠piloto, mecanismo de corrección para el rechazo (primera implementación de la invariante 2) | `./bin/verify` = 0, con test de policy y de que el rechazo nunca hace `UPDATE` sobre la fila original | `Operaciones/**`, migraciones, `Seguridad/**`, tests | **sí** | 4 | **hecha** (PR #51, mergeado 1/9/2026) |
| 15 | HU-15 — tablero de trabajos por estado con filtros y detalle con sesiones (y evidencias, hoy vacío) | `./bin/verify` = 0, con test de filtro por estado y de detalle | `Operaciones/**` (pantalla panel), tests | no | 3 | **hecha** (PR #52, mergeado 1/9/2026) |
| 16 | HU-16 — devengo automático del piloto y su auxiliar al validar una sesión (`SesionValidada` → listener real, módulo `Finanzas` nuevo) | `./bin/verify` = 0, con test de idempotencia (`UNIQUE sesion_id+persona_id`), de exactitud decimal y de que `cerrar()` nunca genera devengo | módulo nuevo `Finanzas` (`fin_`), `ALTER per_personas` (`tarifa_ha`), `Personal/Contratos/**`, `Operaciones/Contratos/**`, tests | **sí** | 4 | **hecha** (PR #53, mergeado 1/9/2026) |
| 17 | HU-06 — condiciones al iniciar sesión (viento/temperatura/humedad), autoriza / autoriza con observación / rechaza, vía el motor de sync | `./bin/verify` = 0, con test de cada rango y de rechazo sin observación | `Operaciones/**`, `Sincronizacion/Aplicacion/**`, migración `ope_condiciones`, tests | **sí** | 4 | **hecha** (PR #54, mergeado 1/9/2026) |
| 18 | HU-10 (redefinida por CR-01) — recepción de caldo: litros recibidos por trabajo, consumidos por sesión, sobrante al cierre, cuadre recalculable | `./bin/verify` = 0, con test de cuadre (recibido = consumido + sobrante) e idempotencia | `Operaciones/**` o módulo nuevo `Mezclas` (decisión de la propia tarea), `Sincronizacion/Aplicacion/**`, tests | **sí** | 4 | **hecha** (PR #55, mergeado 1/9/2026) |
| 19 | TE-07 (parte servidor) — endpoint de recepción de evidencias (`ope_evidencias`), idempotente, con hash SHA-256. Requisito previo de HU-08 y HU-09, que la referencian | `./bin/verify` = 0, con test de idempotencia y de tipo/hash inválido | `Operaciones/**` (decisión de módulo a cargo de la tarea), migración, tests | **sí** | 4 | **hecha** (PR #56, mergeado 1/9/2026) |
| 20 | HU-07 — relevo de piloto y cambio de dron: `hectarea_inicial_acumulada`, catálogo mínimo de `dron`, trabajo `parcial`/`observado`, tolerancia de solape configurable | `./bin/verify` = 0, con test de suma de sesiones dentro y fuera de tolerancia | `Operaciones/**`, migraciones, `Sincronizacion/Aplicacion/**`, tests | **sí** | 5 | **hecha** (PR #57, mergeado 1/9/2026) |
| 21 | HU-09 — cierre de lote: imagen del campo obligatoria para cerrar un trabajo, reutiliza `observado` de la tarea 20 | `./bin/verify` = 0, con test de rechazo sin evidencia y de cierre válido | `Operaciones/Contratos/CierreTrabajo.php`, `EscrituraSincronizacionEloquent`, tests | **sí** | 3 | **hecha** (PR #58, mergeado 2/9/2026; hallazgo informativo sin acción: la HU-09 completa —"captura del RC e imagen del campo"— queda cerrada solo en su mitad de imagen del campo, falta `sesiones.captura_rc_id` si se decide ampliar) |
| 22 | HU-08 — incidencias con foto (caldo/ESC/batería/mecánica/clima), ligadas a la sesión, nuevo tipo de registro del motor de sync | `./bin/verify` = 0, con test de rechazo sin evidencia y de registro válido | `Operaciones/Contratos/**`, `Operaciones/Dominio/**`, `EscrituraSincronizacionEloquent`, `Sincronizacion/Aplicacion/**`, migración `ope_incidencias`, tests | **sí** | 3 | **hecha** (PR #59, mergeado 2/9/2026 por la tarea 27 — quedó atrás del resto de `develop` y necesitó reconciliación con conflictos reales en el motor de sync) |
| 23 | HU-13 — recargas del dron: batería, temperatura (alerta > 50 °C), litros de caldo por sesión, combustible del generador, motivo/hora de retraso por caldo | `./bin/verify` = 0, con test de alerta de temperatura y de recarga válida | `Operaciones/Contratos/**`, `EscrituraSincronizacionEloquent`, `Sincronizacion/Aplicacion/**`, migración `ope_recargas`, tests | **sí** | 4 | **hecha** (PR #61, mergeado 1/9/2026; su prompt tuvo que recuperarse a mano del historial de git a mitad de tarea — ver "El bug de la 24" abajo) |
| 24 | HU-17 — acta por lote: PDF con hectáreas conformadas, firma del agrónomo referenciada como evidencia (`firma_acta`), máquina de estados `pendiente → firmada` | `./bin/verify` = 0, con test de guarda (no genera sobre trabajo abierto) y de firma válida | `Operaciones/**`, migración `ope_actas`, `sec_action`/permisos, pantalla mínima del panel, tests | **sí** | 5 | **hecha** (PR #62, mergeado 2/9/2026; quedó en borrador por la política vieja del documento, corregida en la tarea de la 65/66) |
| 25 | HU-18 — reporte técnico por lote: PDF automático al firmar el acta de la tarea 24 (imagen del campo, horas de inicio/fin, condiciones, litros de caldo/ha, incidencias con evidencia, detalle de sesiones con relevo/cambio de dron), sin contenido de mezcla/dosis (CR-01: no existe ese dato) | `./bin/verify` = 0, con test de generación automática al firmar el acta y de rechazo si el trabajo todavía no está conformado | `Operaciones/**`, migración `ope_reportes_tecnicos`, `sec_action`/permisos, pantalla mínima del panel, tests | no | 4 | **hecha** (PR #67, mergeado 2/9/2026; retomada tras destrabarse el PR #62/HU-17. Quedó con las incidencias vacías a propósito —HU-08 todavía no estaba integrada— ver `runs/25.md`; cerrado por la tarea 28) |
| 26 | HU-19 — bandeja de alertas por excepción (batería caliente, dron sospechoso, condiciones forzadas, suma excedida/`observado`), recortada a lo que ya tiene datos reales — el resto de la lista de la espec depende de mezcla/anticipos/rendiciones, módulos que todavía no existen | `./bin/verify` = 0, con test de generación de cada alerta cubierta y de la transición `pendiente → atendida` | `Operaciones/**`, migración `ope_alertas`, `sec_action`/permisos, pantalla mínima del panel, tests | no | 4 | **hecha y mergeada** (PR #64, mergeado 2/9/2026 a `develop`; no crítica, se revisó por diff y test en el PR). Bandeja en `/panel/alertas`, no `/api/alertas` como nombraba el prompt: `routes/api.php` es exclusivo de las apps de campo por ADR 0008 — ver `runs/26.md` |
| 27 | Reconciliar `feature/incidencias-sesion` (HU-08, PR #59) con `develop` — quedó atrás tras el rescate de los PR #62/#64, con conflictos reales en el motor de sync (8 archivos) | `./bin/verify` = 0 sobre la rama ya mergeada, con test de convivencia de los 8 tipos de registro del motor de sync | motor de sync (`Sincronizacion/**`, `Operaciones/Contratos/**`), `docs/api/openapi.yaml`, tests | **sí** | 4 | **hecha** (PR #59 integrado 2/9/2026, `runs/27-veredicto.md` sin hallazgos) |
| 28 | Cerrar el hueco de incidencias en el reporte técnico: `ArmarContenidoReporteTecnico` quedó con `'incidencias' => []` fijo (tarea 25) porque HU-08 no estaba integrada a `develop` todavía; ya lo está desde la tarea 27 | `./bin/verify` = 0, con test de que una incidencia real (con su evidencia) aparece en el contenido armado del reporte | `Operaciones/Infraestructura/Eloquent/{Sesion,Incidencia}.php`, `Operaciones/Aplicacion/ArmarContenidoReporteTecnico.php`, vista PDF del reporte técnico, tests | no | 2 | **hecha** (PR #69, mergeado 2/9/2026) |
| 29 | Bug de timezone en `MaquinaEstadosTrabajo`/`MaquinaEstadosSesion` (`abrir()`/`cerrar()`): `CarbonImmutable::parse()` sin `->utc()` sobre columnas `dateTime` sin tz corre el instante real por el offset del cliente — mismo patrón ya corregido una vez en `MaquinaEstadosActa::firmar()`. Señalado dos veces sin corregirse (`runs/24.md`, `runs/25.md`, ambas pidiendo tarea propia) | `./bin/verify` = 0, con test de round-trip (guardar `inicio`/`fin` con offset no-UTC vía `/api/sync`, releer, instante correcto contra UTC) | `Operaciones/Aplicacion/MaquinaEstados/{MaquinaEstadosTrabajo,MaquinaEstadosSesion}.php`, tests | **sí** | 3 | **hecha** (PR #71, mergeado 2/9/2026) |
| 30 | FK real de `created_by`/`updated_by` a `sec_user.id` en las ~30 tablas de dominio que hoy son `unsignedBigInteger` sueltas sin `->constrained()` — retrofit que HU-01 dejó explícitamente para "un solo pase futuro que agregue la FK a todas las tablas de una vez" (`docs/gestion/estado_proyecto.md`, "Otros gaps señalados") | `./bin/verify` = 0, con test de que insertar un `created_by` con id de `sec_user` inexistente lanza `QueryException` | una migración nueva de `alter table` (sin tocar el tipo de columna existente), tests | no | 2 | **hecha** (PR #75, mergeado 2/9/2026; 31 tablas + fix de reconstrucción de índices parciales en SQLite, ver `runs/30.md`) |
| 31 | TE — el arquetipo formulario del panel (`form-section` evolucionado, `page-header`, `tabs`, `form-actions-bar`, `summary-card`, `progress-meter`, `file-field`) y la compuerta visual: `bin/verify` no corría `tests/Visual/` desde que existe (tarea 07) | `./bin/verify` = 0 con la etapa de Playwright adentro y en verde; diff vacío del checklist de `guia_pantalla_panel.md` §8 sobre el catálogo | `resources/views/components/**`, `resources/css/components/**`, `resources/css/pages/organizacion.css`, `Seguridad/Infraestructura/Http/Views/pages/organizacion/**`, `lang/es/seguridad.php`, `docs/diseno/sistema_diseno_panel.md`, `tests/Visual/**`, `bin/verify` | no | 4 | **hecha** (PR #74, mergeado 2/9/2026) — `/panel/organizacion` reconstruida como caso de prueba, `tests/Visual/organizacion.spec.ts` nuevo, `npx playwright test` sumado a `bin/verify` (fuera de `.github/workflows/`, snapshots `-darwin`, ver el spec) |
| 32 | `atoms/input` no fusiona `$attributes` en su `<div>` raíz — solo en el `<input>` interno (`resources/views/components/atoms/input.blade.php:48,71`). Rompe LSP: cualquier composición que necesite una clase/atributo en el contenedor (p. ej. `grid-column: 1 / -1` de un campo ancho) no puede pasarla al componente y necesita un `<div>` envolvente puntual en la página, como quedó en `organizacion/index.blade.php` (hallazgo de la tarea 31, documentado en `sistema_diseno_panel.md` §14, no corregido a propósito por blast radius: lo consume todo el panel) | `./bin/verify` = 0, con las páginas que hoy envuelven `atoms/input` a mano (`organizacion/index.blade.php`) usando la clase directo en el componente | `resources/views/components/atoms/input.blade.php`, páginas que lo consumen | no | 2 | **incompleta** — se agotaron las 2 etapas sin cerrar la HU (PR #76 en borrador, `runs/32.estado` = `INCOMPLETA`). El fix quedó escrito pero sin commitear, mezclado en el working tree de `develop` (bloqueaba el arranque de la tarea 33); se rescató a `git stash` sin verificar —`bin/verify` no llegó a terminar— al planificar la 35. Recuperarlo: `git stash list` en el working tree compartido. Decisión del usuario: retomarla desde ahí o cerrar el PR |
| 33 | HU-22 — clientes: ABM sobre `com_clientes` y `com_cliente_contactos`, el primer ABM real del panel (mismo patrón para el resto de Sprint 7) | `./bin/verify` = 0, con test de bitácora en alta/edición/baja, soft delete y NIT único entre clientes activos | módulo nuevo `Comercial/Aplicacion/` e `Infraestructura/Http/`, `routes/web.php`, `SeguridadSeeder`, `SecMenuSeeder`, `lang/es/comercial.php`, tests | no | 4 | **hecha** (PR #77, mergeado 2/9/2026) |
| 34 | HU-23 — contratos: ABM sobre `com_contratos` y `com_contrato_ventanas`, con máquina de estados propia (`borrador/vigente/finalizado/cancelado`, invariante 7) y `monto_total` recalculado, nunca editable a mano (invariante 6) | `./bin/verify` = 0, con test de transición de estados válida/inválida, de ventana solapada rechazada y de `monto_total` exacto | `Comercial/Aplicacion/`, `Comercial/Infraestructura/Http/`, `routes/web.php`, `SeguridadSeeder`, `SecMenuSeeder`, `lang/es/comercial.php`, tests | no | 5 | **hecha** (PR #78/#79, mergeados 2/9/2026) |
| 35 | HU-24 — campos y lotes: ABM sobre `com_campos` y `com_lotes`, con geometría opcional del lote (GeoJSON en `jsonb`, sin PostGIS — ADR 0001) | `./bin/verify` = 0, con test de `hectareas > 0`, de geometría inválida rechazada, y de que un lote con órdenes/trabajos asociados no se puede eliminar sin avisar | `Comercial/Aplicacion/`, `Comercial/Infraestructura/Http/`, `routes/web.php`, `SeguridadSeeder`, `SecMenuSeeder`, `lang/es/comercial.php`, tests | no | 4 | **hecha** (PR #80/#81, mergeados 2/9/2026) |
| 36 | HU-27 — drones: ABM sobre `ope_drones`, con `modelo`/`capacidad_l` nuevos por ALTER (30/50/60 L) — la migración de la tarea 23 los dejó fuera a propósito | `./bin/verify` = 0, con test de `capacidad_l` fuera de {30,50,60} rechazada y de `identificador` duplicado como error de validación | `Operaciones/Aplicacion/`, `Operaciones/Infraestructura/Http/`, migración ALTER de `ope_drones`, `routes/web.php`, `SeguridadSeeder`, `SecMenuSeeder`, `lang/es/operaciones.php`, tests | no | 3 | **hecha** (PR #82, mergeado 2/9/2026) |
| 37 | HU-26 — personas y bases: ABM sobre `per_personas` y `per_bases`, dos pantallas independientes; módulo `Personal` sin `Aplicacion/`/`Http/` todavía, se arma desde cero | `./bin/verify` = 0, con test de que editar `tarifa_ha` de una persona no altera un `DevengoPersonal` ya generado (el devengo ya congela su propia copia, confirmado en `Finanzas/Aplicacion/GenerarDevengosSesion.php`) | `Personal/**`, `routes/web.php`, `SeguridadSeeder`, `SecMenuSeeder`, `lang/es/personal.php` (nuevo), tests | no | 4 | **hecha** (PR #83/#84, mergeados 2/9/2026) |
| 38 | HU-25 — órdenes de aplicación: ABM sobre `ope_ordenes_aplicacion` con su primera máquina de estados real (`emitida → vigente`, `TransicionesOrden`/`MaquinaEstadosOrden` nuevos, mismo patrón que `MaquinaEstadosContrato`); una orden `vigente` ya aparece sola en `GET /api/sync/catalogo`, sin tocar el motor de sync | `./bin/verify` = 0, con test de la transición válida/inválida y de que dos órdenes `vigente` para el mismo lote chocan contra el índice parcial como error de validación | `Operaciones/Dominio/MaquinaEstados/`, `Operaciones/Aplicacion/MaquinaEstados/`, `Operaciones/Aplicacion/` (casos de uso de órdenes), `Operaciones/Infraestructura/Http/`, `routes/web.php`, `SeguridadSeeder`, `SecMenuSeeder`, `lang/es/operaciones.php`, tests | no | 5 | **hecha** (PR #85, mergeado 2/9/2026; el ciclo la marcó `AGOTADA` en `runs/38.estado` porque las 3 sesiones seguidas de implementación se quedaron sin turnos esperando la notificación de un `bin/verify` en segundo plano — pero el merge ya se había completado segundos antes de que se declarara el agotamiento, confirmado con `gh pr view 85` y el diff vacío entre la rama y `develop`. Cierra Sprint 7 salvo HU-45, que esta vuelta agrega como tarea 39) |
| 39 | HU-45 — usuarios: alta, edición y baja de `sec_user`/`sec_user_role` desde el panel, cierra la parte de HU-01 que quedó sin hacer. Reusa `AsignarRolesUsuario` (ya implementado en HU-01, con la guarda anti-rol-dueño); falta solo la capa HTTP, la baja y el toggle de bloqueo | `./bin/verify` = 0, con test de la guarda de rol dueño vía HTTP, de `username` duplicado, y de que cambiar roles no invalida la sesión activa | `Seguridad/Aplicacion/EliminarUsuario.php` (nuevo), `Seguridad/Infraestructura/Http/**`, `routes/web.php`, tests | no | 3 | **hecha** (PR #86/#87, mergeados 3/9/2026) |
| 40 | HU-28 — devengos por período: pantalla de solo lectura sobre `fin_devengos_personal`, primera del módulo `Finanzas`; primer acceso al panel para piloto/auxiliar (hoy sin ningún permiso de panel) | `./bin/verify` = 0, con test de acceso cruzado → 404 y de filtro por período | `Finanzas/**`, `routes/web.php`, `SeguridadSeeder`, `SecMenuSeeder`, `lang/es/finanzas.php` (nuevo), tests | no | 3 | **agotada** — 3 sesiones seguidas sin declarar estado (`runs/40.estado` = `AGOTADA`), nunca commitearon: `runs/40.log` muestra que esperaron la notificación de un `bin/verify` en segundo plano con `Monitor` (denegado) en vez de commitear primero, mismo patrón de la tarea 38. El trabajo (controller, caso de uso, 8 tests, spec visual, fixture) quedó completo en apariencia pero sin commitear, mezclado en el working tree de `develop` (bloqueaba el arranque de la 41); rescatado a `git stash` sin verificar de punta a punta al planificar la 42. Recuperarlo: `git stash list` (`stash@{0}`, no confundir con el `stash@{1}` de la tarea 32) — la tarea 42 lo retoma |
| 41 | HU-29 — anticipos: tabla nueva `fin_anticipos`, tope de 3.000 Bs/mes y 70 % del devengado calculado con `BigDecimal`, rechazo con el disponible exacto | `./bin/verify` = 0, con test de cada tope por separado y de acumulación de dos anticipos en el mismo mes | `Finanzas/**`, migración nueva, `routes/web.php`, `SeguridadSeeder`, `SecMenuSeeder`, `lang/es/finanzas.php`, tests | no | 4 | **agotada** — mismo patrón que la 40: 3 sesiones seguidas sin declarar estado (`runs/41.estado` = `AGOTADA`), nunca commitearon: lanzaron `bin/verify` en segundo plano y esperaron su notificación en vez de commitear primero. El trabajo (migración, modelo, 4 casos de uso, controller, 2 vistas, 9 tests, spec visual con sus 4 capturas) quedó completo en apariencia pero sin commitear, mezclado en el working tree de `develop`; rescatado a `git stash` al planificar la siguiente tanda. Recuperarlo: `git stash list` (`stash@{0}`, no confundir con el `stash@{1}` de la tarea 32) — la tarea 43 lo retoma |
| 42 | Retomar y cerrar HU-28 (continuación de la 40): recuperar el `git stash` con el trabajo casi completo, completar lo que falte contra el mismo criterio de aceptación, commitear y verificar de punta a punta | `./bin/verify` = 0, con test de acceso cruzado → 404 y de filtro por período (igual que la 40) | `Finanzas/**`, `routes/web.php`, `SeguridadSeeder`, `SecMenuSeeder`, `lang/es/finanzas.php`, `resources/css/pages/devengos.css`, tests | no | 2 | **hecha** (PR #88, mergeado 3/9/2026) |

| 43 | Retomar y cerrar HU-29 (continuación de la 41): recuperar el `git stash` con el trabajo casi completo, completar lo que falte contra el mismo criterio de aceptación, commitear y verificar de punta a punta | `./bin/verify` = 0, con test de cada tope por separado y de acumulación de dos anticipos en el mismo mes (igual que la 41) | `Finanzas/**`, migración nueva, `routes/web.php`, `SeguridadSeeder`, `SecMenuSeeder`, `lang/es/finanzas.php`, `resources/css/pages/anticipos.css`, tests | no | 3 | **hecha** (PR #89, mergeado 3/9/2026) |
| 44 | HU-30 — planilla del período: dos tablas nuevas (`fin_planillas`, `fin_planilla_detalles`), máquina de estados `borrador → aprobada` (solo `dueno`), recibo individual en PDF, total exacto contra los devengos de origen | `./bin/verify` = 0, con test de cuadre exacto contra `fin_devengos_personal`, de idempotencia por período y de que solo `dueno` aprueba | `Finanzas/**`, migraciones nuevas, `routes/web.php`, `SeguridadSeeder`, `SecMenuSeeder`, `lang/es/finanzas.php`, tests | **sí** | 5 | **hecha** (PR #90, mergeado 3/9/2026) |
| 45 | HU-31 — facturar un trabajo desde su acta conformada: nueva tabla `com_facturas` en `Comercial`, cruza a `Operaciones` (acta/trabajo/orden/contrato) por un contrato de lectura nuevo (`LecturaActaConformada`, mismo patrón que `LecturaSesionValidada`), monto = hectáreas conformadas × precio_ha del contrato | `./bin/verify` = 0, con test de rechazo desde acta sin firmar, de que no se factura dos veces la misma acta, y de monto exacto | `Comercial/**`, `Operaciones/Contratos/**` (contrato de lectura nuevo), `Operaciones/Infraestructura/` (su implementación), migración nueva, `routes/web.php`, `SeguridadSeeder`, `SecMenuSeeder`, `lang/es/comercial.php`, tests | no | 4 | **hecha** (PR #91, mergeado 3/9/2026). Queda pendiente, sin acción de esta cola: pegar a mano la sección "Revisión" redactada por el agente `arquitectura` al final de `docs/decisiones/0003-arquitectura-modular-clean-por-feature.md` — ver `runs/45.md`, requiere `descongela=decisiones` o el gesto manual del usuario |
| 46 | HU-32 — reporte comercial de avance: hectáreas contratadas vs. aplicadas vs. facturadas por contrato, agregando `LecturaActaConformada::listarFirmadas()` (ya existe desde la 45) contra `Contrato` y `Factura`, propios de `Comercial`; exportable a CSV. Cierra Sprint 9 | `./bin/verify` = 0, con test de agregación exacta por contrato y de exportación CSV con el filtro aplicado | `Comercial/**`, `routes/web.php`, `SeguridadSeeder`, `SecMenuSeeder`, `lang/es/comercial.php`, tests | no | 3 | **hecha** (PR #92, mergeado 3/9/2026) |
| 47 | HU-33 — gastos: módulo `Finanzas` nuevo con `fin_rubros`/`fin_subrubros` (catálogo semillado) y `fin_gastos` (imputable a trabajo, base o general), primera subida de comprobante humana desde el panel (`Storage::disk('r2')`, hash SHA-256, sin reusar `ope_evidencias`). Abre Sprint 10 | `./bin/verify` = 0, con test de `monto` exacto (`cantidad × precio_unitario`), de los tres casos de imputación, y de comprobante con hash | `Finanzas/**`, migraciones nuevas, `routes/web.php`, `SeguridadSeeder`, `SecMenuSeeder`, `lang/es/finanzas.php`, tests | no | 5 | **hecha** (PR #94, mergeado 3/9/2026) |
| 48 | HU-34 — rendiciones: `fin_rendiciones` + `ALTER fin_gastos` (agrega `rendicion_id`), máquina de estados propia (`abierta → presentada → aprobada`), guarda "el aprobador nunca es quien rinde" a nivel de persona (invariante 4, mismo patrón que `PoliticaValidacionSesion`). Depende de que la 47 esté integrada | `./bin/verify` = 0, con test de la guarda de persona (aprobador ≠ `jefe_campo_id`), de transición inválida rechazada, y de `monto` exacto = suma de gastos asociados | `Finanzas/**` (incluida la migración `ALTER` de `fin_gastos`), migraciones nuevas, `routes/web.php`, `SeguridadSeeder`, `SecMenuSeeder`, `lang/es/finanzas.php`, tests | no | 4 | **hecha** (PR #95, mergeado 3/9/2026) |
| 49 | HU-35 — combustible del generador y vehículos: `fin_combustibles` nueva, carga por base y fecha, litros y monto en `DECIMAL`, sin depender de `ope_recargas` (informativo, sin costeo) ni de un módulo `Vehiculo` que todavía no existe. Cierra Sprint 10 | `./bin/verify` = 0, con test de `destino` fuera de enum rechazado y de filtro por período | `Finanzas/**`, migración nueva, `routes/web.php`, `SeguridadSeeder`, `SecMenuSeeder`, `lang/es/finanzas.php`, tests | no | 3 | **hecha** (PR #96, mergeado 3/9/2026) |
| 50 | HU-40 — vehículos de la flota: ABM con asignación a base y estado, primera tarea en crear el módulo nuevo `Mantenimiento` (`man_`, reparto fijado con el agente `arquitectura` y transcrito como extensión del ADR 0011). Abre Sprint 11 | `./bin/verify` = 0, con test de `identificador` duplicado como 422 | módulo nuevo `Mantenimiento`, `routes/web.php`, `SeguridadSeeder`, `SecMenuSeeder`, `lang/es/mantenimiento.php`, `docs/decisiones/0011-convencion-prefijos-tabla.md` (solo el punto nuevo del mapeo), tests | no | 3 | **hecha** (PR #97, mergeado 3/9/2026) |
| 51 | HU-39 — baterías con ciclos y estado: ABM sobre `man_baterias` con alerta por ciclos acumulados o por temperatura ya registrada en `ope_recargas` (correlación por texto contra `bateria_saliente_id`, vía contrato de lectura nuevo — sin convertirlo a FK real). Depende de que la 50 haya creado `Mantenimiento` | `./bin/verify` = 0, con test de alerta por ciclos y de alerta por temperatura de una recarga real | `Mantenimiento/**`, `Operaciones/Contratos/**` y su implementación (el contrato de lectura nuevo), migración nueva, `routes/web.php`, `SeguridadSeeder`, `SecMenuSeeder`, `lang/es/mantenimiento.php`, tests | no | 4 | **hecha** (PR #98, mergeado 3/9/2026) |
| 52 | HU-36 — inventario de repuestos: módulo nuevo `Inventario` (`inv_`) con `inv_repuestos`/`inv_stock`/`inv_movimientos` (compra/salida/ajuste/traslado), guarda de stock nunca negativo y alerta por punto de reposición por base | `./bin/verify` = 0, con test de cada tipo de movimiento y de salida/traslado rechazado cuando dejaría el stock negativo | módulo nuevo `Inventario`, migraciones nuevas, `routes/web.php`, `SeguridadSeeder`, `SecMenuSeeder`, `lang/es/inventario.php`, tests | no | 4 | **hecha** (PR #99, mergeado 3/9/2026) |
| 53 | HU-37 — órdenes de mantenimiento: `man_ordenes_mantenimiento` con máquina de estados real (`abierta → cerrada`, guarda de repuestos disponibles), primer contrato de escritura cross-módulo del proyecto hacia `Inventario` (consumir stock) y hacia `Finanzas` (generar el gasto, reusando `CrearGasto`), todo en una transacción. Depende de que la 52 esté integrada | `./bin/verify` = 0, con test de cierre exitoso (stock y gasto exactos) y de cierre sin stock suficiente rechazado sin dejar nada a medias | `Mantenimiento/**`, `Inventario/Contratos/**`+`Infraestructura/**`, `Finanzas/Contratos/**`+`Infraestructura/**`, migración nueva, `routes/web.php`, `SeguridadSeeder`, `SecMenuSeeder`, `lang/es/mantenimiento.php`, tests | no | 5 | **hecha** (PR #101, mergeado 3/9/2026) |
| 54 | HU-38 — planes de mantenimiento preventivo por horas de vuelo: `man_planes_mantenimiento` por modelo de dron (correlación por texto, sin FK), horas acumuladas derivadas de `ope_sesiones` (suma `fin - inicio` en PHP) vía contrato de lectura nuevo, alerta calculada al leer | `./bin/verify` = 0, con test de alerta activada al cruzar el umbral y de plan sin drones de ese modelo sin alerta | `Mantenimiento/**`, `Operaciones/Contratos/**`+`Infraestructura/**`, migración nueva, `routes/web.php`, `SeguridadSeeder`, `SecMenuSeeder`, `lang/es/mantenimiento.php`, tests | no | 4 | **hecha** (PR #104, mergeado 3/9/2026) |
| 55 | HU-41 — portal del cliente: entra y ve solo sus reportes/actas/avance, todo consultado desde el `contrato` del usuario autenticado (invariante 5); test obligatorio de cliente A pidiendo recurso de cliente B → 404. Abre Sprint 12 | `./bin/verify` = 0, con el test de scoping cruzado (404) | módulo nuevo o extensión de `Comercial`/`Seguridad` para el rol cliente, `routes/web.php` o `routes/portal.php`, `SeguridadSeeder`, `SecMenuSeeder`, tests | **sí** | 4 | **integrada a mano el 4/9/2026** — el ciclo la rechazó dos veces (PR #106 en borrador) por el único hallazgo bloqueante de `runs/55-veredicto.md`: 17 capturas de Playwright ajenas a la HU (drift de fecha y orden de fixtures), resueltas de raíz en el PR #113. El scoping, los 404 cruzados y el login del guard `cliente` estaban aprobados. Se mergeó `develop` en la rama (unificando `LecturaReporteTecnico` con la tarea 57: `listarTodos()` + `listarPorContrato()`), se regeneraron las capturas afectadas por el fixture del portal y se marcó el PR listo |
| 56 | HU-42 — galería de evidencias de un trabajo: pantalla sobre `ope_evidencias` agrupada por trabajo y sesión, con miniaturas y descarga | `./bin/verify` = 0, con Playwright | `Operaciones/**` (pantalla panel), `routes/web.php`, tests | no | 2 | **hecha** (PR #107, mergeado 4/9/2026) |
| 57 | HU-43 — listado de reportes técnicos: pantalla sobre `ope_reportes_tecnicos` con filtro por cliente y período, reusando la descarga individual ya existente | `./bin/verify` = 0, con Playwright | `Operaciones/**` (contrato de lectura, caso de uso, controlador, vista), `Comercial/Contratos/**` (contrato de lectura inverso hacia el nombre del cliente), `routes/web.php`, `SecMenuSeeder` (solo activar el placeholder), tests | no | 3 | **hecha** (PR #108, mergeado 4/9/2026) |
| 58 | HU-44 — pausas con causa atribuible (DS-01): pausa ligada a sesión con causa de catálogo, agregado por causa en un tablero propio | `./bin/verify` = 0, con test de agregación exacta por causa y de causa fuera de catálogo rechazada | `Operaciones/**` (migración `ope_pausas`, caso de uso, controlador, vista), `SeguridadSeeder`, `SecMenuSeeder` (solo activar el placeholder `pausas`), tests | no | 4 | **hecha** (PR #109, mergeado 4/9/2026) |
| 59 | TE-13 — quitar del menú el ítem `Operación › Mezclas`: no existe más por CR-01 | `./bin/verify` = 0, con test de que `sec_menu` no tiene ninguna fila `menu.operacion.items.mezclas` | `SecMenuSeeder.php`, `lang/es/menu.php`, tests | no | 2 | **hecha** (PR #110, mergeado 4/9/2026) |
| 60 | TE-14 — reemplazar los badges de demostración del sidebar (`DatosDemoPanel::badgesMenu()`) por contadores reales o quitarlos si no hay fuente real; depende de que la 57 y la 58 estén integradas | `./bin/verify` = 0, con test de que cada badge que queda cambia con su dato de origen y de que los que no tienen fuente real no aparecen | `Seguridad/Infraestructura/Http/Demo/DatosDemoPanel.php` (solo `badgesMenu()`), `CascaraPanel.php`, `DashboardController.php`, contratos de lectura nuevos en `Operaciones`/`Inventario`/`Finanzas`, tests | no | 3 | **hecha** (PR #111, mergeado 4/9/2026) — cierra Sprint 12 y el plan de doce sprints completo (salvo la 55) |
| 61 | Deuda técnica (no es fila de `plan_sprints.md`, mismo criterio que 28-30): eliminar el no determinismo de `rendiciones.spec.ts → show` (claro u oscuro, alterna cuál falla) — el único rojo de Playwright que sigue sin explicación tras la 60, que regeneró los ~100 snapshots que tapaban el resto del drift preexistente | `npx playwright test tests/Visual/rendiciones.spec.ts --grep show --repeat-each=5` = 0, y `./bin/verify` = 0 sin ningún otro snapshot movido | `tests/Visual/rendiciones.spec.ts`, `resources/css/components/{topbar,panel-layout}.css` (si la causa es de layout compartido), `playwright.config.ts` (si hace falta, sin agregar `retries`) | no | 2 | escrita |
| 62 | Seguridad (auditoría del 4/9/2026, hallazgos P1): cerrar las tres fugas del modelo de permisos por rol activo — `AsignarRolesUsuario` evalúa por unión de roles en vez del rol activo (invariante 10), dashboard y organización sin permiso, ítems de `sec_menu` sin ruta ni permiso que abren Operación/Comercial/Seguridad a todo rol | `./bin/verify` = 0, con test de que un encargado con rol dueño asignado pero no activo recibe 403 al otorgar dueño, y de que `auxiliar` no ve dashboard ni organización y aterriza en un 200 | `app/Dominios/Seguridad/**`, `SeguridadSeeder`, `SecMenuSeeder`, `lang/`, engranaje del riel, `tests/**` | **sí** | 3 | escrita |
| 63 | Bitácora visible en el panel: pantalla `/panel/bitacora` (quién hizo qué y cuándo) con filtros y diff antes/después, instante guardado en UTC + `zona_horaria` IANA del actor, mostrado en la zona de quien mira con horario de verano/invierno (pedido del usuario el 4/9/2026, **repedido el 7/9/2026**; nunca corrió, ver actualización dentro del propio prompt) | `./bin/verify` = 0, con test de conversión IANA (Madrid julio +2 / enero +1) y test de que la bitácora del portal deja `user_id` del cliente | `app/Dominios/Compartido/**`, `app/Dominios/Seguridad/**`, preferencias del portal, migraciones nuevas, seeders de catálogo, `resources/`, `tests/**` | no | 3 | escrita |
| 64 | Roles y permisos administrables desde el panel: listado y ficha de rol con matriz de permisos editable, vista previa del menú por rol (mismo cálculo que el sidebar), alta/edición/bloqueo de roles, ficha del usuario multirol y tarjetas del selector de rol con los módulos habilitados; el seeder no pisa cambios manuales | `./bin/verify` = 0, con test de que quitar un permiso por la matriz da 403 en el request siguiente sin re-login, y de que nadie puede dejar sin `seguridad.rol.asignar_permisos` al último rol que lo tiene | `app/Dominios/Seguridad/**`, `SeguridadSeeder`, `SecMenuSeeder`, migraciones nuevas, `lang/`, `resources/`, `tests/**` | **sí** | 4 | escrita |
| 65 | Cuentas del portal desde el panel (tipo `cliente` con cliente → contrato, sin roles, permiso `seguridad.usuario.portal`) y `PortalDemoSeeder` con dos clientes (San Jorge y La Esperanza) con acta y reporte por el flujo real y un usuario cada uno, más guion de prueba manual de que B no ve lo de A (cierra lo que la 55 dejó sin datos) | `./bin/verify` = 0, con test de 404 cruzado entre las dos cuentas demo y `migrate --seed` idempotente | `app/Dominios/Seguridad/**` (usuarios), `database/seeders/Demo/**`, `SeguridadSeeder`, `lang/`, `resources/`, `docs/gestion/estado_proyecto.md`, `tests/**` | **sí** | 3 | escrita |
| 66 | Correo en la cuenta (`sec_user.email`, precargado desde persona/contacto del cliente), perfil propio con cambio de contraseña (panel y portal) y recuperación por correo con formulario real + CSRF, brokers por guard, `password_reset_tokens`, notificación en español y Mailpit habilitado en el compose; amplía el ADR 0004 | `./bin/verify` = 0, con test de 419 sin CSRF, respuesta idéntica exista o no el email, token de un guard inútil en el otro | `app/Dominios/Seguridad/**`, `app/Dominios/Portal/**`, `config/auth.php`, `.env.example`, `docker-compose.yml`, migraciones nuevas, seeders demo, `lang/`, `resources/`, `routes/web.php`, ADR 0004 (ampliar), `tests/**` | no | 5 | escrita |
| 67 | Dashboard sin maqueta: cada sección lee de la base por un contrato de lectura del módulo dueño, las secciones sin fuente real se retiran, las tres clases `DatosDemo*` desaparecen, `@puede` por sección, y `DashboardDemoSeeder` siembra por el flujo real lo necesario para verlo poblado | `./bin/verify` = 0, `grep -rn DatosDemo app/ resources/ tests/` vacío, y test de que validar una sesión cambia el dashboard en el request siguiente | `app/Dominios/Seguridad/**` (dashboard), `*/Contratos/**` + implementaciones, `database/seeders/Demo/**`, `resources/`, `lang/`, `tests/**` | no | 5 | escrita |
| 68 | HU-40 — avance del contrato en el portal del cliente (fila preexistente, sin cambios) | `./bin/verify` = 0 | `app/Dominios/Portal/**`, `lang/`, `resources/`, `tests/**` | no | 3 | escrita |
| 69 | HU-46 — la campaña **del cliente** como eje: módulo `Campania` + `cpn_campanias` con `cliente_id` y máquina de estados `planificada → abierta → cerrada`, único por `(cliente_id, código)` y sin guarda de solape, ABM en el panel, `campania_id` obligatorio en `com_contratos` (con guarda de mismo cliente y migración de datos por cliente) y nullable en `fin_gastos`, más el renombre `campana → campaniaActiva`. **Sin campaña activa de sesión ni chip** — corregido el 8/9/2026 | `./bin/verify` = 0, con test de campaña de otro cliente rechazada en el contrato, de solape permitido y de migración sin `campania_id` nulo | `app/Dominios/Campania/**`, `Comercial/**`, `Finanzas/**`, migraciones, seeders, `resources/views/components/organisms/**`, `lang/`, `routes/web.php`, `tests/**` | **sí** | 4 | escrita |
| 70 | HU-47 — altura de vuelo en el contrato, ventana horaria opcional (cero ventanas = todo el día, y cae la guarda de activación) y `tipo_aplicacion` (siembra / desarrollo / cosecha) en la orden | `./bin/verify` = 0, con test de que un contrato sin ventanas pasa a `vigente` y de que dos ventanas solapadas se siguen rechazando | `app/Dominios/Comercial/**`, `app/Dominios/Operaciones/**` (orden), migraciones, `lang/`, `tests/**` | no | 2 | escrita |
| 71 | HU-48 — cultivo por lote y campaña: catálogo `com_cultivos` y `com_lote_campania` con `UNIQUE (lote_id, campania_id)`, cargado desde la ficha del campo | `./bin/verify` = 0, con test de que el mismo lote lleva soya en una campaña y maíz en otra sin conflicto, y de que las hectáreas sembradas no superan las del lote | `app/Dominios/Comercial/**`, `Seguridad/**` (solo seeders de permiso y menú), migraciones, seeders, `lang/`, `tests/**` | no | 3 | escrita |
| 72 | HU-49 — equipos de trabajo con vigencia: `per_equipos_trabajo` + `per_equipo_integrantes` + `per_equipo_recursos`, más `man_generadores` (sin ella no hay generador que asignar), con validador de solapamiento y ficha que responde quién lo integraba a una fecha | `./bin/verify` = 0, con test de que una persona no entra en dos equipos con vigencias que se pisan, y de que la ficha al 14/3 devuelve la formación de ese día y no la de hoy | `app/Dominios/Personal/**`, `app/Dominios/Mantenimiento/**` (solo generadores), `Seguridad/**` (seeders), migraciones, seeders, `lang/`, `tests/**` | no | 4 | escrita |
| 73 | HU-50 — gasto y combustible imputados al equipo de trabajo y a la unidad que lo consumió: `equipo_trabajo_id` en `fin_gastos` y `fin_combustibles`, y el `destino` de texto reemplazado por recurso concreto elegible solo entre el equipamiento del equipo a esa fecha | `./bin/verify` = 0, con test de que un recurso ajeno al equipo se rechaza, test de que el total por equipo cuadra exacto (strings decimales, sin `SUM()` de SQL) y test de migración del `destino` viejo | `app/Dominios/Finanzas/**`, migraciones, seeders demo, `lang/finanzas.php`, `tests/**` | **sí** | 3 | escrita |
| 74 | HU-51 — entrada y salida del equipo en cada hacienda: `ope_estadias_hacienda` con `uuid_cliente`, dos tipos nuevos en el motor de sync (`estadia_entrada` / `estadia_salida`) y pantalla de consulta con días efectivos por equipo y propiedad | `./bin/verify` = 0, con test de replay (mismo lote 10 veces, en orden y en desorden → una sola fila), test de que un segundo ingreso con estadía abierta se rechaza sin frenar el resto del lote, y test de que el reintento devuelve `duplicado` | `app/Dominios/Operaciones/**`, `app/Dominios/Sincronizacion/**`, `Seguridad/**` (seeders), migraciones, seeders demo, `routes/`, `lang/`, `tests/**` | **sí** | 3 | escrita |
| 75 | HU-52 — informe de avance de contratos por cultivo y por cliente (molde del informe de synagro, con hectáreas donde ellos ponen kilos): selectores obligatorios, pantalla de filtros, chips removibles, dos pestañas, barra por cinco tramos con token propio cada uno y totalizador de "a aplicar" | `./bin/verify` = 0, con test de que el avance coincide exacto con `ObtenerAvanceComercial`, test de que sin cliente o sin cultivo no genera, y `grep` de color hardcodeado vacío en lo nuevo | `app/Dominios/Comercial/**`, `resources/views/components/**`, `resources/css/**`, `lang/comercial.php`, `routes/web.php`, `tests/**` | no | 4 | escrita |
| 76 | HU-53 — el sistema de inputs del panel: átomos `select` (con búsqueda y teclado), `date` (calendario propio en español), `checkbox`, `checkbox-group`, `radio-group` y `textarea`, con el contrato de props de `input`, y migradas las 88 apariciones crudas (70 `<select>`, 11 `type="date"`, 7 `type="checkbox"`) | `./bin/verify` = 0, `grep -rn "<select"` y `grep -rn 'type="date"'` sobre vistas sin resultados fuera de los propios átomos, test de que el `select` se opera solo con teclado, y `grep` de color hardcodeado vacío en los CSS nuevos | `resources/views/components/**`, `resources/css/**`, `resources/js/**`, `package.json`, `vite.config.js`, vistas de todos los módulos (solo el marcado), `docs/diseno/sistema_diseno_panel.md`, `tests/**` | no | 5 | escrita |
| 77 | HU-54 — Propiedades y Lotes como ítems de menú separados, con listado y ficha de lote propios (filtro por cliente y propiedad, búsqueda por código, perímetro), y `Personas` → `Personal` | `./bin/verify` = 0, con test de que el alta de propiedad con sus lotes sigue igual, test de 403 sin `comercial.lote.ver`, y test de que re-sembrar el menú no pisa permisos quitados a mano | `app/Dominios/Comercial/**`, `Personal/**` (rótulos y rutas), `Seguridad/**` (solo seeders), migraciones, `lang/`, `routes/web.php`, `resources/`, `tests/**` | no | 3 | escrita |
| 78 | HU-55 — configuración del sistema separada de la empresa: `/panel/configuracion` por sectores (mapas, correo, integraciones) con valores cifrados en reposo, nunca devueltos al navegador y excluidos del diff de la bitácora, resolución en cascada con `.env` de respaldo; más la pestaña de Facturación de la empresa que hoy dice "Próximamente" | `./bin/verify` = 0, con test de que el HTML no contiene el secreto en claro, test de que la bitácora registra el cambio sin el valor, test de que guardar vacío no borra lo configurado, y test de 403 para quien no es dueño | `app/Dominios/Compartido/**`, `Seguridad/**` (organización, permisos, menú), migraciones, seeders, `lang/`, `routes/web.php`, `resources/`, `tests/**` | **sí** | 4 | escrita |
| 79 | HU-56 — editor de perímetro a pantalla completa, con barra de acciones propia en Material Symbols, superficie en hectáreas mientras se dibuja, y proveedor de mapa configurable (Google Maps con llave, Leaflet + Esri sin ella) | `./bin/verify` = 0, con test de que sin llave el camino actual funciona intacto, test de que el GeoJSON guardado es idéntico con un proveedor o el otro, y test de que la llave no aparece en el HTML cuando el proveedor es Leaflet | `resources/js/**`, `resources/css/**`, `resources/views/components/**`, `Comercial/**` (vista del editor), `Compartido/Contratos/**`, `package.json`, `lang/`, `tests/**` | no | 3 | escrita |
| 80 | HU-57 — repuestos por casillas en la orden de mantenimiento: lista con búsqueda por código y descripción, cantidad y disponibilidad a la vista, resumen de lo elegido, base elegida una vez por orden; el payload no cambia | `./bin/verify` = 0, con test de que el formulario nuevo produce el mismo payload y el mismo resultado que el viejo, y regresión de HU-37 (cerrar descuenta stock y genera el gasto en una transacción) | `app/Dominios/Mantenimiento/**` (vistas y controlador), `resources/`, `lang/mantenimiento.php`, `tests/**` | no | 2 | escrita |
| 81 | HU-58 — ficha de desempeño de una persona: qué aplicó (lote, campo, cliente, campaña), cuándo, con qué dron y cuántas hectáreas, más sus sesiones rechazadas con motivo y sus incidencias, por contrato de lectura de `Operaciones` sin que `Personal` toque tablas `ope_*` | `./bin/verify` = 0, con test de que una sesión rechazada no suma hectáreas y de que la campaña mostrada es la del contrato de esa orden | `Operaciones/**` (contrato de lectura), `Personal/**` (pantalla), `Comercial/**` (solo extender lectura), `Seguridad/**` (permiso), `lang/`, `routes/web.php`, `tests/**` | no | 3 | escrita |

### El bug de la 24 — ya pasó dos veces, sigue sin arreglarse

`bin/ciclo` (`preparar_rama()`) commitea el prompt de la tarea que arranca con
`git add -- "${PLANIFICACION[@]}"`, un glob (`prompts/[0-9][0-9]-*.md`) que
agarra **todo** lo que esté sin commitear en `prompts/`, no solo el archivo de
la tarea que arranca. Cada vez que una planificación escribe varios prompts de
una sola vez (la regla de "escribí de a varias") sin commitearlos (la regla de
"no commitees" — viajan como primer commit de la rama que los va a usar), el
glob los barre juntos en el primer commit de la PRIMERA rama que arranca de
las varias — no solo el prompt de esa tarea.

**Primera vez:** la planificación tras la tarea 21 escribió los prompts 22, 23
y 24 sin commitear. El glob los barrió juntos en el commit `f2db767` de
`feature/incidencias-sesion` (tarea 22, crítica). Su PR (#59) quedó en
borrador sin mergear, y con él, los prompts 23 y 24 dejaron de estar
disponibles en `develop`. La tarea 23 lo notó sola (etapa 1 falló en seco,
`SIN-ESTADO`) y se rescató con `git show f2db767:prompts/23-recargas-dron.md`.
Nadie rescató la 24 —no era su trabajo— así que el ciclo, al no encontrar su
prompt, disparó una planificación nueva en vez de fallar en seco.

**Segunda vez, inmediatamente después:** esa misma planificación (tras
recuperar la 24) escribió los prompts 24 (recuperado), 25 y 26 sin commitear,
sabiendo que iba a volver a pasar (quedó anotado en la versión anterior de
esta sección). Pasó exactamente así: el glob los barrió juntos en el commit
`99ffd2e` de `feature/acta-conformidad` (tarea 24, también crítica). Su PR
(#62) también quedó en borrador sin mergear. La tarea 25 rescató su propio
prompt (`git show 99ffd2e:prompts/25-reporte-tecnico.md`, commiteado como
`3d036aa`) pero quedó `BLOQUEADA` de todos modos —no por falta de prompt, sino
porque su contenido depende de que exista `Acta`, y `Acta` sigue sin llegar a
`develop`—. El prompt 26 quedó atrapado en el mismo commit `99ffd2e` sin que
nadie lo rescatara, hasta la planificación que siguió a la tarea 21.

**El patrón que emerge**: el bug no solo pierde prompts (eso ya se sabe
rescatar, `git log --all --oneline -- prompts/NN-slug.md` encuentra el commit
aunque esté en una rama sin mergear); combinado con que las tareas 22 y 24
—las dos primeras ramas que arrancaron después de escribir varios prompts a
la vez— son ambas críticas y ninguna de sus dos PRs se mergeó de entrada, el
bug **encadena bloqueos**: cada prompt perdido depende de que una rama crítica
en borrador se mergee para volver a `develop`. La corrección de raíz —que
`preparar_rama()` solo agregue `prompts/${id}-*.md`, no el glob completo— es
un cambio de una línea en `bin/ciclo`. Sigue sin aplicarse porque no es una HU
del plan de sprints y ninguna sesión de planificación implementa código.
Alguien con acceso directo al repo debería aplicarlo — no es una tarea para
encolar en el ciclo automático: modificar el propio script que orquesta el
ciclo, desde una sesión que corre dentro de ese mismo ciclo, es el tipo de
cambio que conviene hacer a mano y una sola vez, no delegarlo.

### La política de PRs críticos en borrador — resuelta

`CLAUDE.md` dice, explícitamente, que la revisión de lo crítico **es
posterior a la integración, no previa**: el PR se mergea a `develop` y la
revisión línea por línea se anota en `runs/revision-pendiente.txt` para
hacerse después. `automatizacion_desarrollo.md` §5 decía lo contrario hasta el
2/9/2026 — se corrigió ese mismo día (PR #65) para que ambos documentos digan
lo mismo. Los dos PR que habían quedado retenidos por la versión vieja (#59 de
la tarea 22/HU-08, #62 de la tarea 24/HU-17) ya están integrados: el #62 se
reconcilió directo, el #59 necesitó la tarea 27 porque sus conflictos tocaban
el motor de sync en 8 archivos.

### Fuera del ciclo automático

Estas HU y TE del plan de sprints **no califican** y la sesión de planificación
las saltea sin detenerse — están anotadas acá para que no las redescubra en cada
vuelta. Saltearlas no las cancela: siguen en `plan_sprints.md` y las hace una
persona cuando corresponda.

**La app no entra al ciclo automático.** `agrocom-field`
(`https://github.com/agrocom-developer/agrocom-field.git`, creado el
25/8/2026) **ya existe** y es un proyecto **Flutter**. Todo lo que sea de la
app queda fuera de este ciclo, sin excepción y sin importar en qué sprint
aparezca: el build del APK, su binario y su publicación, el bloqueo por
versión mínima del lado cliente, la UI del piloto, el outbox offline (TE-04)
y cualquier código Dart/Flutter. `agrocom-api` aporta solo el lado servidor:
endpoints, panel web y permisos. Si una HU mezcla ambas cosas, entra al ciclo
**únicamente** su parte de servidor, y el prompt debe decir explícitamente
qué queda del lado de la app. Es regla permanente, no acotada a una tarea:
la tarea 10 (HU-20) la incumplió al asumir que `agrocom-field` "no existe
todavía" y terminó hospedando el binario del `.apk` en este repo; la tarea 11
lo corrigió.

| Id | Por qué no califica |
|---|---|
| TE-01 (resto) | Staging sigue diferido (ADR 0010, el servidor no está definido). El repo `agrocom-field` ya existe pero es Flutter — fuera de este ciclo por la regla permanente de arriba, no porque no exista |
| TE-02 | Spike de hardware: necesita el RC Agras en mano. Su entregable es un informe, no un exit code |
| TE-04 | Base local drift + outbox: vive en `agrocom-field`, otro repo |
| HU-04 (parte app) | "Lista y detalle offline" es UI de `agrocom-field`, fuera de este ciclo. Su parte de servidor ("sin orden vigente no se puede abrir trabajo") **ya está cubierta**: fue uno de los hallazgos de la tarea 12 (`EscrituraSincronizacionEloquent::abrirTrabajo()` rechaza si la orden no existe, no está vigente, o el `lote_id` no coincide) — no necesita tarea propia |
| HU-11, HU-12 | **Fuera de alcance desde el 1/9/2026** (CR-01 cerrada): Agrocom no prepara la mezcla ni dosifica. No están pendientes — están eliminadas del plan |
| TE-07 (parte app) | Compresión y cola de subida viven en `agrocom-field`; el endpoint que recibe la evidencia (la parte que sí califica) es la tarea 19 |
| TE-08 | Endurecimiento del sync con datos de las betas: no hay datos de beta real todavía, depende de que exista staging con tráfico |
| TE-09, HU-21 | Ensayo general en campo con operarios reales y sus correcciones de adopción |
| TE-10, TE-11 | Producción (VPS, HTTPS, respaldos, Sentry) y carga de datos maestros reales: dependen de infraestructura y de datos que no están |
| TE-12 | Cierre de ruta crítica (matriz de permisos, seeds de producción, tag v1.0): prematuro mientras el grueso del plan siga sin hacer |
| Reunión de cierre de la especificación | Es de negocio. `analisis_clasificacion.md` §7 tiene la agenda |

**Sprint 1 a 5 están enteros en lo que es automatizable de este repo.** El
plan de sprints tiene seis originalmente (ahora doce, ver más abajo); los
cinco primeros ya no tienen ninguna HU/TE pendiente que califique — lo único
que queda de ellos es TE-02 (spike de hardware) y TE-08 (necesita datos de
beta), ambas en la tabla de arriba. Sprint 6 entero tampoco califica (ensayo
de campo, producción, datos maestros reales, cierre prematuro). Las tareas 28
a 30 no son filas nuevas de `plan_sprints.md` — son deuda técnica concreta,
documentada explícitamente por tareas anteriores, con criterio de aceptación
ejecutable. Ver "Por qué ese orden" abajo.

### Sprint 7 — cerrado

`plan_sprints.md` se amplió a doce sprints (PR #70, 2/9/2026): el plan ahora
cubre el sistema entero, no solo la ruta crítica de los primeros seis. Sprint
7 ("Catálogos: el panel se vuelve operable") es el primero de esa ampliación
que calificó para el ciclo automático — sus siete historias (HU-22 a HU-27,
HU-45) son ABM sobre tablas que ya existen, migradas y auditadas desde TE-03 y
los sprints 2-5: sin modelo de datos nuevo, es la capa de pantalla que
faltaba.

Con el arquetipo formulario en `develop` (tarea 31), Sprint 7 fue mecánico:
HU-22 (clientes, tarea 33), HU-23 (contratos, tarea 34), HU-24 (campos y
lotes, tarea 35), HU-27 (drones, tarea 36), HU-26 (personas y bases, tarea
37) y HU-25 (órdenes, tarea 38) ya están integradas — todas con su PR y su
spec visual propio en `tests/Visual/`. HU-45 (usuarios, tarea 39) cerró el
sprint (PR #86/#87, 3/9/2026): a diferencia de las otras seis, toca
`sec_user`/`sec_user_role` (seguridad de acceso) en vez de un catálogo de
negocio puro, y su lógica de dominio pesada ya existía desde HU-01
(`AsignarRolesUsuario`) — faltaba solo la capa HTTP encima.

**El bug de LSP de `atoms/input` (tarea 32) sigue sin resolverse** — se
agotaron sus etapas sin cerrar la HU y quedó incompleta (ver la fila 32
arriba). Ninguna tarea de Sprint 7 en adelante asumió que estaba resuelta:
cada una revisó en el momento si `atoms/input` fusiona `$attributes` y usó el
envoltorio manual si no. Sigue siendo así para toda tarea nueva que use
campos anchos.

### Sprint 8 — cerrado

Con Sprint 7 cerrado, la cola pasó a Sprint 8 ("la gente cobra a fin de
mes"): HU-28 (devengos, tareas 40/42), HU-29 (anticipos, tareas 41/43) y
HU-30 (planilla, tarea 44) — las tres integradas al 3/9/2026. Las tareas 40 y
41 se agotaron sin commitear (mismo patrón: 3 sesiones seguidas lanzando
`bin/verify` en segundo plano sin commitear antes) y sus tareas de retoma
(42, 43) rescataron el trabajo de `git stash` y lo cerraron.

### Sprint 9 — cerrado

HU-31 (facturas, tarea 45, PR #91) y HU-32 (reporte comercial de avance,
tarea 46) cierran Sprint 9 ("cobrarle al cliente"). La 46 reusa
`LecturaActaConformada` tal cual la dejó la 45, sin tocarla.

### Condicionadas — todavía no tienen sobre qué correr

Las invariantes 2 (nunca se sobrescribe un registro validado) y 3 (el devengo
se genera solo al validar) vigilan tablas que aún no existían al escribirlas;
ambas ya tienen su gate (tareas 14 y 16, ver "Por qué ese orden" en versiones
anteriores de este documento).

Las alertas de la tarea 26 que dependen de `mezcla` (desvío ±5%, hectáreas
incoherentes por dosis) o de `anticipos`/`rendiciones` no tienen sobre qué
correr todavía para la parte de `mezcla` (fuera de alcance por CR-01). Las
partes de `anticipos` (tarea 43) y `rendiciones` (tarea 48, PR #95) ya están
integradas. Ninguna tarea de esta tanda escribe ese gate de alertas — queda
para cuando el negocio lo pida explícitamente, no es parte del CA esencial de
HU-33/HU-34/HU-35.

## Por qué ese orden

**05 lo antes posible, aunque no sea deuda de automatización.** El trabajo del
dashboard existe, pasa la cascada y no está en `develop` porque su PR quedó en
borrador y se cerró. Cada merge a `develop` lo encarece. Va detrás de la 04
solo porque la 04 es corta.

**04 y 06 antes que todo lo demás.** Hoy las invariantes 7 y 9 se cumplen por
disciplina: nada impide un `estado = ...` suelto en un controlador, ni un
modelo de dominio que mute sin dejar rastro, y la cascada quedaría igual de
verde. Mientras eso siga así, el verde de `bin/verify` no significa lo que el
ciclo asume que significa — y el ciclo mergea solo con ese verde. Es deuda que
hay que saldar **antes** de tocar devengos, planilla o máquina de estados, no
después.

**09 al final y con revisión posterior.** El motor de sync es lo primero de la
lista de `CLAUDE.md` que no se delega sin revisión línea por línea. Se integra
como cualquier otra tarea crítica, y queda anotado en
`runs/revision-pendiente.txt` para que una persona lo revise sobre `develop`
ya integrado — ver "La política de PRs críticos en borrador" arriba.

**22 → 23 → 24 → 25 → 26 cerraron lo que quedaba de los sprints 3, 4 y 5.**
Detalle completo del orden y las dependencias reales en el historial de git de
este documento (`git log -p -- docs/gestion/cola_tareas.md`).

**27 reconcilió lo que el "bug de la 24" dejó atrás.** El PR #59 (HU-08,
tarea 22) quedó en una rama vieja mientras `develop` avanzaba con HU-13,
HU-17, HU-18 y HU-19 por encima — reconciliarlo a mano, con test de
convivencia de los 8 tipos de registro, era más seguro que confiar en un merge
automático sobre el motor de sync.

**28 → 29 → 30, en ese orden, por urgencia real, no por tamaño.**

- **28** es la continuación más directa de lo que la 27 acaba de cerrar: HU-18
  (tarea 25) dejó las incidencias del reporte técnico vacías a propósito,
  documentando que HU-08 todavía no estaba integrada. Ya lo está. Dejar ese
  hueco abierto un ciclo más, con la dependencia ya resuelta, no tiene motivo.
- **29** es un bug real, no cosmético: `MaquinaEstadosTrabajo`/`MaquinaEstadosSesion`
  guardan `inicio`/`fin` mal en cualquier país con offset distinto de UTC —
  Bolivia es UTC-4, así que probablemente todo cierre real en producción lo
  dispara. Dos tareas distintas (24 y 25) lo encontraron y pidieron
  explícitamente que se convirtiera en tarea propia; cuanto más tarde se
  corrija, más código nuevo puede heredar el mismo patrón sin `->utc()`. Va
  crítica porque toca el servicio de estados.
- **30** es la de menos urgencia de las tres: integridad de esquema sin
  impacto funcional hoy (nada depende de que la FK exista todavía), documentada
  como gap desde HU-01. No hay apuro, pero tiene criterio de aceptación
  ejecutable y no depende de nada más — es razonable sacarla de la lista de
  gaps sueltos.

**32 → 33 → 34 → 35 se pensó en ese orden, pero la 32 se cayó en la
práctica — queda igual, sin reordenar.** La idea original: `atoms/input` (32)
tenía que fusionar `$attributes` antes de que las siete pantallas de Sprint 7
empezaran a repetir el envoltorio manual que documentó la tarea 31 — chica (2
etapas), sin dependencias, así que iba primera. En la ejecución real se agotó
sin cerrar (ver la fila 32). No se la reordena ni se reintenta desde acá —es
la que quedó marcada para el usuario, no una decisión de esta planificación—,
y las tareas 33 en adelante ya no dependen de que esté resuelta: cada una
revisa en el momento si `atoms/input` fusiona `$attributes` y usa el
envoltorio manual si no. Clientes (33) sigue antes que contratos (34) porque
`com_contratos.cliente_id` es una FK real a `com_clientes`: aunque los tests
de 34 puedan crear su propio cliente por factory sin que exista la pantalla de
alta, tiene sentido que el patrón de ABM completo (el primero real del panel)
se establezca una vez en la HU más simple de las dos, y que contratos —con
máquina de estados, 13 `CHECK` y montos derivados— lo reutilice en vez de
inventarlo en paralelo. Campos y lotes (35) sigue a contratos por el mismo
criterio de progresión de complejidad, no por una dependencia real de datos:
`com_campos.cliente_id` es FK a `com_clientes` (33), no a `com_contratos`
(34), así que 35 podría en principio ir antes que 34 — pero repite el patrón
de ABM simple una vez más (sin máquina de estados) antes de las HU con más
lógica propia (HU-25, con máquina de estados sobre órdenes; HU-26, con la
guarda de que cambiar tarifa no altere devengos ya generados), que conviene
dejar para cuando el patrón esté más asentado.

**36 → 37 → 38 siguieron exactamente el mismo criterio de progresión que 33 →
34 → 35.** Drones (36) no tenía ninguna dependencia de datos con
personas/bases (37) ni con órdenes (38) — el orden fue de complejidad, no de
FK: drones es un ABM de una sola tabla sin máquina de estados; personas y
bases armaron el módulo `Personal` desde cero (hasta entonces solo tenía
lecturas) pero seguían sin máquina de estados; órdenes fue la primera HU de
Sprint 7 con una máquina de estados propia (`emitida → vigente`), así que
convino que fuera la última de esa tanda, con el patrón ABM ya asentado tres
veces por 33-35 y sin máquina de estados dos veces más por 36-37.

**39 cierra Sprint 7 antes de abrir Sprint 8, sin reordenar contra HU-45.**
Quedó deliberadamente sin fila en la vuelta anterior (no por dependencia de
dato: `sec_user.persona_id` ya podía apuntar a cualquier persona sembrada por
seeder) sino porque su alcance de decisión —qué permisos exactos separar,
cómo dar la contraseña inicial sin infraestructura de correo— se prefería
planificar con el patrón ABM de 33-38 ya asentado, no en la misma tanda que
esas seis. Con las seis integradas, esa vuelta pudo investigar el código
real (`AsignarRolesUsuario` ya resuelve la parte más delicada desde HU-01) y
escribir un prompt que no reinventa esa lógica.

**40 → 41 abren Sprint 8 en el mismo orden del plan, sin corte de dominio.**
HU-28 (devengos, 40) antes que HU-29 (anticipos, 41) por una dependencia
real, no solo de lectura del plan: el tope de HU-29 se calcula sobre "el
devengado del período" de una persona, la misma suma que HU-28 necesita para
listar. Hacer 41 sin que 40 esté integrada hubiera significado escribir esa
suma dos veces en ramas paralelas con alto riesgo de que difieran. HU-30
(planilla), la tercera de Sprint 8, no se escribió hasta que `fin_anticipos`
estuvo integrada.

**42 se intercala entre 40 y 41, no se agrega al final.** La tarea 40 se
agotó sin commitear (ver fila 40): el trabajo quedó rescatado en `git
stash`, no integrado. La dependencia real de HU-29 sobre "el devengado del
período" (ver el párrafo de arriba) seguía sin resolverse mientras HU-28 no
estuviera en `develop` — dejar `41` como la próxima tarea de la cola tal cual
estaba habría hecho que el ciclo intentara anticipos sin que devengos
exista. `42` retomó exactamente el trabajo rescatado (no lo reescribió desde
cero) y fue antes de `41` en `runs/cola.txt`, aunque su número sea más alto:
el archivo ya no era estrictamente numérico (compará con `31` antes que
`30`), así que insertarla ahí no rompió nada. `41` no se tocó — su prompt
siguió siendo válido una vez que `42` cerró HU-28.

**43 repitió exactamente el mecanismo de la 42, sobre la 41.** Misma causa
(3 sesiones seguidas lanzando `bin/verify` en segundo plano sin commitear
antes, ver `runs/41-plan.md`), mismo remedio: `git stash` del trabajo casi
completo, tarea nueva que lo retomó con la advertencia explícita de commitear
antes de esperar un `bin/verify` largo.

**44 (planilla) cerró Sprint 8, siguió el orden literal del plan y dependía
de datos reales, no solo de orden de lectura.** HU-30 resta anticipos del
devengado del período — sin `fin_anticipos` integrada (tarea 43) no había
qué restar. Fue `critica=si` porque `CLAUDE.md` nombra literal "los listeners
que generan dinero (devengos, planilla)" en la lista de lo que no se delega
sin revisión línea por línea.

**45 (facturas) abrió Sprint 9 en cuanto Sprint 8 quedó completo.** No
dependía de dato de las tareas 43/44 — factura contra el acta conformada
(`ope_actas`, ya integrada desde la tarea 24/27) y el contrato
(`com_contratos`, tarea 34), ninguna de las dos tocada por anticipos ni
planilla. Se ordenó después de todos modos porque Sprint 8 (dinero que sale)
estaba a mitad de camino cuando se escribió esa tanda y convenía cerrarlo
antes de abrir el siguiente sprint.

**46 (reporte comercial) cierra Sprint 9 apenas la 45 está integrada.**
Depende exclusivamente de que `LecturaActaConformada` y `com_facturas` (tarea
45, PR #91) existan en `develop` — ambas ya están. No hay ninguna otra
dependencia de dato: `Contrato.hectareas_contratadas` es una columna que
existe desde HU-23 (tarea 34). Se escribe recién ahora, no antes, porque
diseñar su agregación "a ciegas" (sin `com_facturas` real para probar contra
ella) hubiera sido adivinar la forma del dato en vez de leerla.

**47 (gastos) abre Sprint 10 apenas Sprint 9 cierra, sin depender de nada de
Sprint 9.** HU-33 no toca `Comercial` ni `Operaciones` más que por una FK
plana a `ope_trabajos.id` — podría en principio haberse escrito en paralelo a
la 45/46. Se ordenó después de todos modos por el mismo criterio de
progresión ya usado entre sprints anteriores: cerrar el sprint en curso antes
de abrir el siguiente. Es la primera tarea que crea un catálogo semillado
(`fin_rubros`/`fin_subrubros`) y la primera subida de archivo humana desde el
panel (a diferencia de `ope_evidencias`, que es del motor de sync) — por eso
va con 5 etapas, una más que el resto de los ABM de Finanzas.

**48 (rendiciones) sigue a gastos por una dependencia de dato real, no de
orden de lectura.** El CA esencial de HU-34 ("rendición con detalle e
ítems") necesita que existan gastos reales para asociarles una rendición, y
la migración agrega `rendicion_id` a `fin_gastos` por `ALTER` — no puede
escribirse antes de que la tabla exista. HU-35 (combustible), la tercera de
Sprint 10, queda sin escribir todavía: es la más chica de las tres (1,0 d) y
su forma concreta (carga por base y fecha, sin depender de gastos ni
rendiciones) conviene decidirla en la próxima vuelta de planificación, no
adivinada ahora junto con las otras dos.

**49 (combustible) cierra Sprint 10 sin depender de la 47 ni de la 48.**
`fin_combustibles` no lee ni escribe `fin_gastos`/`fin_rendiciones` — es
independiente, se ordenó al final del sprint solo para cerrarlo antes de
abrir el siguiente, mismo criterio ya usado en 45→46 y 47→48. Se investigó
el código real de `ope_recargas` antes de escribir el prompt para confirmar
que su columna de combustible del generador es "informativa, sin costeo" y
no se cruza con esta HU — evita el error de intentar reusarla o de
inventar un vínculo que la especificación sugiere (`cargas_combustible.
gasto_id`) pero que el CA esencial de `plan_sprints.md` no pide.

**50 → 51 abren Sprint 11 con las dos HU más chicas y sin dependencia de
`HU-36`/`HU-37`, que quedan para la próxima vuelta.** Sprint 11 tiene 5
historias con una decisión de arquitectura real sin resolver: dónde viven
`Bateria` y `Vehiculo` (`docs/decisiones/0011-convencion-prefijos-tabla.md`
punto 3 los reserva para módulos `Mantenimiento`/`Inventario` que no
existen). Se consultó al agente `arquitectura` antes de escribir los
prompts en vez de adivinar: la recomendación fue crear **dos** módulos
nuevos, no cinco tablas sueltas — `Mantenimiento` (`man_`) para vehículos,
baterías, generadores y los planes/órdenes de mantenimiento (equipos con
desgaste que disparan alerta por umbral); `Inventario` (`inv_`) exclusivo
de HU-36 (repuestos, stock, movimientos). `Dron` no se mueve de
`Operaciones` — ese precedente ya está integrado. Vehículos (50) va primero
por ser la más simple de las cinco (1,0 d, sin cruce con otro módulo) y es
la que deja escrita la extensión al ADR 0011 que las siguientes necesitan.
Baterías (51) sigue porque su alerta por temperatura lee `ope_recargas`
(tarea 23) por un contrato de lectura nuevo, cruce que conviene escribir
una sola vez con el módulo `Mantenimiento` ya creado. HU-36 (repuestos,
2,5 d), HU-37 (mantenimiento que consume stock, depende de HU-36) y HU-38
(planes preventivos, cuya forma depende de si `ope_drones` necesita sumar
horas de vuelo acumuladas — dato que hoy no existe) quedan sin prompt: son
el "bloque más caro" del sprint, con dependencias reales entre sí, y
conviene decidirlas con el módulo `Mantenimiento` ya en `develop` para
probar contra código real en vez de a ciegas — mismo criterio que HU-32
esperó a que `com_facturas` estuviera integrada.

**52 → 53 → 54 cierran Sprint 11, ya con `Mantenimiento` en `develop` para
investigar contra código real en vez de a ciegas.** Repuestos (52) crea el
módulo `Inventario` (reservado desde la extensión del ADR 0011 escrita por
la tarea 50, sin volver a consultar arquitectura: el reparto ya estaba
decidido) y va primero porque órdenes de mantenimiento (53) tiene una
dependencia de dato real sobre él — no hay stock que consumir sin que
`inv_stock`/`inv_movimientos` existan. Órdenes (53) resultó ser la más
grande de las tres (5 etapas, contra 3-4 del resto de Sprint 11): es la
primera vez que un módulo escribe en `fin_gastos` desde afuera de
`Finanzas` (contrato de escritura nuevo, no solo de lectura como todos los
anteriores) y la primera vez que una sola transacción de negocio cruza
tres módulos (`Mantenimiento` → `Inventario` → `Finanzas`). Se investigó
`CrearGasto` y `FinanzasRubrosSeeder` antes de escribir el prompt para
confirmar que el rubro "Mantenimiento de equipos"/"Repuestos" ya está
sembrado desde la tarea 47 — la tarea 53 lo reusa, no lo reinventa. Planes
preventivos (54) cierra el sprint sin depender de la 53 (no necesita que
exista una orden para calcular la alerta): la pregunta que
`cola_tareas.md` dejaba abierta ("¿de dónde salen las horas de vuelo
acumuladas?") ya tiene respuesta con el código real de `ope_sesiones` a
la vista — `dron_id`/`inicio`/`fin` existen desde las tareas 09 y 20, así
que las horas de vuelo se derivan sumando sesiones cerradas por dron, sin
agregar una columna nueva que se desincronizaría del dato real. Queda
después de la 53 solo por seguir el orden literal de `plan_sprints.md`,
no por una dependencia real.

**55 → 56 → 57 → 58 → 59 → 60 abren y cierran Sprint 12, el último del
plan.** El orden literal del plan (HU-41, HU-42, HU-43, HU-44, TE-13, TE-14)
no tenía ninguna dependencia de dato entre sí — cada pantalla lee un módulo
distinto (`Comercial`/portal, `ope_evidencias`, `ope_reportes_tecnicos`,
`ope_pausas` nueva) — así que se respetó tal cual. La 55 (portal del
cliente) es la única `critica=si` de todo Sprint 12 (toca el scoping del
portal, invariante 5, de la lista de `CLAUDE.md`) y la única que no cerró:
quedó **rechazada dos veces** y su PR (#106) sigue en borrador — ver
`runs/55.md`, `runs/55-veredicto.md`. No bloqueó lo que vino detrás porque
ninguna otra HU de Sprint 12 depende de sus datos, pero sí se llevó puesto el
prompt de la 57 (escrito en la misma tanda de planificación, viajando sin
commitear en la rama de la 55 — el mismo mecanismo de "El bug de la 24" de
más arriba, aplicado esta vez a una rama que no era crítica por sí misma
pero terminó estancada por el rechazo). La 56 (galería de evidencias) sí se
integró (PR #107) porque arrancó en su propia rama después de que la 55 ya
había quedado resuelta (rechazada y en borrador), no antes.

**57 se restaura, no se reescribe.** Su contenido ya estaba pensado con
cuidado (nombra el contrato de lectura inverso `Comercial → Operaciones` que
hace falta, cita el ítem de menú placeholder correcto) — perderlo por un bug
de infraestructura del ciclo no es motivo para redecidir su alcance.
`git show 3463b52:prompts/57-listado-reportes-tecnicos.md` lo recuperó
íntegro.

**58 → 59 → 60 completan Sprint 12 sin dependencias de dato entre sí, salvo
la 60.** HU-44 (pausas, 58) y TE-13 (quitar Mezclas del menú, 59) son
independientes — se ordenaron en el orden literal del plan, no por
necesidad. TE-14 (badges reales, 60) sí depende de datos reales: dos de sus
ocho badges (`pausas`, y transitivamente el criterio "cada badge consulta su
módulo o desaparece") necesitan que la 58 exista, así que va última. De los
ocho badges de `DatosDemoPanel::badgesMenu()`, solo cinco tienen hoy una
fuente real identificada (órdenes vigentes, sesiones sin validar, pausas de
la 58, stock bajo mínimo, devengos del período); los otros tres
(`programacion`, `reportes_cliente`, `drones` en taller) no tienen dato real
detrás y su prompt instruye explícitamente a quitarlos en vez de inventar
una columna nueva — `reportes_cliente` en particular no se resuelve
integrando la 55: esa tarea quedó marcada para revisión humana y no se
reintenta desde acá.

**Con la 60, Sprint 12 cierra el plan de doce sprints completo** (salvo la
55, marcada para el usuario). No queda ninguna HU/TE adicional en
`plan_sprints.md` después de Sprint 12 — la próxima planificación, al no
encontrar más filas que agregar de un sprint nuevo, debe revisar primero si
alguna de las líneas de "Fuera del ciclo automático" cambió de condición
(por ejemplo, si ya hay datos de beta para TE-08) antes de considerar
`runs/DETENER`.

**61 se escribe porque ninguna línea de "Fuera del ciclo automático" cambió
de condición, pero apareció una deuda técnica nueva con criterio ejecutable
propio.** Con Sprint 12 cerrado (60), se revisó la tabla de arriba entera
antes de considerar `runs/DETENER`: TE-01 (resto) y TE-08 siguen sin
staging real (ADR 0010 sigue con el alcance de servidor abierto), TE-02
sigue necesitando el RC en mano, TE-04/TE-07 (parte app) siguen en
`agrocom-field`, TE-09/HU-21/TE-10/TE-11/TE-12 siguen atados a ensayo de
campo y producción real, y la reunión de cierre sigue siendo de negocio —
nada de eso calificó. Se evaluó también si TE-12 (matriz de permisos con
test por celda) podía recortarse como se hizo con TE-14/HU-09/HU-20 en
tareas anteriores, y se descartó: la matriz de `especificacion_funcional_
tecnica.md` §3 todavía tiene filas de mezcla (fuera de alcance por CR-01,
sin reconciliar porque la especificación no se toca hasta la reunión de
cierre) y una fila de portal del cliente que depende de la 55, rechazada y
marcada para no reintentarse — escribir esa tarea hubiera significado
inventar un recorte de alcance no pedido por nadie, no ejecutar una
decisión ya tomada.

Revisando en cambio `runs/*.md` de las últimas diez tareas apareció un
patrón real: diez sesiones seguidas (32, 53 a 60) documentaron "rojo
preexistente" de Playwright sin tocarlo, siempre con el mismo argumento
correcto (el gate real de CI no corre Playwright, así que no bloqueaba
ningún merge) pero sin que nadie lo arreglara — igual que las invariantes 7
y 9 antes de las tareas 04 y 06, o el gap de FK antes de la 30. La tarea 60
regeneró de un saque los ~100 snapshots que tapaban la mayor parte de ese
rojo (drift de fuentes/antialiasing del entorno). Queda un solo caso que
**no** es drift de fuentes — `rendiciones show`, no determinístico,
reproducido con y sin los cambios de la 60 — y con eso aislado, tiene
criterio de aceptación ejecutable propio (determinismo en corridas
repetidas) sin depender de ninguna decisión pendiente. Mismo criterio que
28-30: no es fila de `plan_sprints.md`, pero es deuda concreta, documentada
por varias tareas, con exit code verificable.

### Por qué ese orden en las tareas 69 a 80 (7/9/2026)

**El orden de ejecución no es el orden de los números**, porque las dos tandas
del 7/9 se cruzan. Es este:

```
69 → 76 → 77 → 70 → 71 → 72 → 73 → 74 → 78 → 63 → 79 → 80 → 75
```

La **63** (bitácora visible en el panel) ya estaba escrita desde el 4/9 y nunca
corrió — `runs/63.estado` no existe. El motor de bitácora sí está hecho (PR #34
y #35); lo que falta es la pantalla. El dueño la pidió de nuevo el 7/9, así que
vuelve a la cola en vez de escribirse un prompt duplicado, con una actualización
que cubre lo que cambió desde entonces.

- **69 (campaña) primero y sola**: la 70, la 71 y la 75 le cuelgan por
  `campania_id`, y su migración de datos toca `com_contratos` — cuanto más tarde
  entre, más filas hay que migrar. *(Corregido el 8/9/2026: la campaña es del
  cliente, así que la 72 (equipos) y la 74 (estadías) ya **no** dependen de
  ella — el equipo es de Agrocom y la estadía no lleva campaña. El orden no
  cambia igual: la 72 sigue antes que la 73 y la 74 porque no se le imputa un
  gasto ni se le registra una estadía a un equipo que no existe.)*
- **76 (sistema de inputs) segunda, antes que cualquier pantalla nueva.** Las
  tareas 70 a 75 construyen formularios; si nacen con los `<select>` crudos y el
  `type="date"` nativo, hay que migrarlos después uno por uno. Es la diferencia
  entre hacerlo una vez y hacerlo dos.
- **77 (propiedades y lotes) antes que 71**: el cultivo se carga por lote y
  campaña, y sin pantalla propia de lote ese dato solo se toca entrando por la
  propiedad.
- **72 (equipos) antes que 73 y 74**: no se le imputa un gasto ni se le registra
  una estadía a un equipo que no existe.
- **78 (configuración) antes que 79**: sin dónde guardar la llave, el mapa no
  puede cambiar de proveedor.
- **63 (bitácora en el panel) después de 78**: la 78 deja los secretos excluidos
  del diff antes/después, y la 63 es la pantalla donde esa exclusión se ve o se
  filtra. Al revés, la pantalla nacería mostrando llaves en claro.
- **80 (repuestos por casillas) después de 76**: consume el `checkbox-group` del
  catálogo, no construye uno propio.
- **75 (informe) al final**: necesita campaña (69) y cultivo (71).

**Hecho de negocio confirmado por el usuario el 8/9/2026: la campaña es por
todo el campo, no por cultivo.** Cubre la propiedad entera, con todos sus lotes
y lo que se haya sembrado en cada uno; el cultivo es una dimensión del lote
dentro de la campaña (tarea 71) y el informe de la 75 agrupa por cultivo, pero
ninguna de las dos abre campañas por cultivo. Ratifica el punto 4 del ADR 0015.

Cuatro son críticas por la lista de `CLAUDE.md`: la 69 y la 73 tocan dinero, la
74 toca el motor de sync, y la 78 guarda secretos. Las cuatro se integran igual
y se revisan después, anotadas en `runs/revision-pendiente.txt` — no se retiene
el PR.

**La nota de la planificación anterior quedó saldada.** Si se integra sin
sorpresas, la próxima planificación vuelve a estar en la misma situación
que esta (Sprint 12 cerrado, nada nuevo calificable) y debe repetir la
misma revisión de "Fuera del ciclo automático" antes de escribir
`runs/DETENER` — no se prepararon tareas 62/63 a ciegas porque, a
diferencia de una HU con corte de dominio conocido, no hay una segunda
pieza de deuda técnica identificada todavía con la misma certeza que la 61.

### La 81, agregada el 8/9/2026

Sale de una pregunta del dueño al leer la corrección del ADR 0015: *"si el
equipo de trabajo y las estadías ya no llevan campaña, ¿cómo sabemos qué equipo
está trabajando dónde?"*, con el caso de uso escrito — *"en el caso de que la
aplicación no funcionara y queremos buscar culpables, para tomar decisiones a
futuro de no volver a contratarlo"*.

La respuesta al modelo es que **sí se sabe, y por FKs reales**: sesión → trabajo
→ orden → contrato → campaña, y sesión → trabajo → lote → campo → cliente. No
hacía falta agregar ninguna columna. Lo que faltaba era la **pantalla**: hoy el
nombre del piloto sólo aparece en el reporte técnico de un lote.

Va **al final de la cola** y no antes: no bloquea a nadie, y necesita que la 69
esté integrada para poder mostrar la campaña de cada aplicación.

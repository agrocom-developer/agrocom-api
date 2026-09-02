# Cola de tareas automatizables

**Última actualización: 2/9/2026 (tarea 31).** Este es el backlog que el ciclo de
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
| 28 | Cerrar el hueco de incidencias en el reporte técnico: `ArmarContenidoReporteTecnico` quedó con `'incidencias' => []` fijo (tarea 25) porque HU-08 no estaba integrada a `develop` todavía; ya lo está desde la tarea 27 | `./bin/verify` = 0, con test de que una incidencia real (con su evidencia) aparece en el contenido armado del reporte | `Operaciones/Infraestructura/Eloquent/{Sesion,Incidencia}.php`, `Operaciones/Aplicacion/ArmarContenidoReporteTecnico.php`, vista PDF del reporte técnico, tests | no | 2 | pendiente |
| 29 | Bug de timezone en `MaquinaEstadosTrabajo`/`MaquinaEstadosSesion` (`abrir()`/`cerrar()`): `CarbonImmutable::parse()` sin `->utc()` sobre columnas `dateTime` sin tz corre el instante real por el offset del cliente — mismo patrón ya corregido una vez en `MaquinaEstadosActa::firmar()`. Señalado dos veces sin corregirse (`runs/24.md`, `runs/25.md`, ambas pidiendo tarea propia) | `./bin/verify` = 0, con test de round-trip (guardar `inicio`/`fin` con offset no-UTC vía `/api/sync`, releer, instante correcto contra UTC) | `Operaciones/Aplicacion/MaquinaEstados/{MaquinaEstadosTrabajo,MaquinaEstadosSesion}.php`, tests | **sí** | 3 | pendiente |
| 30 | FK real de `created_by`/`updated_by` a `sec_user.id` en las ~30 tablas de dominio que hoy son `unsignedBigInteger` sueltas sin `->constrained()` — retrofit que HU-01 dejó explícitamente para "un solo pase futuro que agregue la FK a todas las tablas de una vez" (`docs/gestion/estado_proyecto.md`, "Otros gaps señalados") | `./bin/verify` = 0, con test de que insertar un `created_by` con id de `sec_user` inexistente lanza `QueryException` | una migración nueva de `alter table` (sin tocar el tipo de columna existente), tests | no | 2 | pendiente |
| 31 | TE — el arquetipo formulario del panel (`form-section` evolucionado, `page-header`, `tabs`, `form-actions-bar`, `summary-card`, `progress-meter`, `file-field`) y la compuerta visual: `bin/verify` no corría `tests/Visual/` desde que existe (tarea 07) | `./bin/verify` = 0 con la etapa de Playwright adentro y en verde; diff vacío del checklist de `guia_pantalla_panel.md` §8 sobre el catálogo | `resources/views/components/**`, `resources/css/components/**`, `resources/css/pages/organizacion.css`, `Seguridad/Infraestructura/Http/Views/pages/organizacion/**`, `lang/es/seguridad.php`, `docs/diseno/sistema_diseno_panel.md`, `tests/Visual/**`, `bin/verify` | no | 4 | **hecha** — `/panel/organizacion` reconstruida como caso de prueba, `tests/Visual/organizacion.spec.ts` nuevo, `npx playwright test` sumado a `bin/verify` (fuera de `.github/workflows/`, snapshots `-darwin`, ver el spec) |
| 32 | `atoms/input` no fusiona `$attributes` en su `<div>` raíz — solo en el `<input>` interno (`resources/views/components/atoms/input.blade.php:48,71`). Rompe LSP: cualquier composición que necesite una clase/atributo en el contenedor (p. ej. `grid-column: 1 / -1` de un campo ancho) no puede pasarla al componente y necesita un `<div>` envolvente puntual en la página, como quedó en `organizacion/index.blade.php` (hallazgo de la tarea 31, documentado en `sistema_diseno_panel.md` §14, no corregido a propósito por blast radius: lo consume todo el panel) | `./bin/verify` = 0, con las páginas que hoy envuelven `atoms/input` a mano (`organizacion/index.blade.php`) usando la clase directo en el componente | `resources/views/components/atoms/input.blade.php`, páginas que lo consumen | no | 2 | pendiente |

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
plan de sprints tiene seis; los cinco primeros ya no tienen ninguna HU/TE
pendiente que califique — lo único que queda de ellos es TE-02 (spike de
hardware) y TE-08 (necesita datos de beta), ambas en la tabla de arriba.
Sprint 6 entero tampoco califica (ensayo de campo, producción, datos
maestros reales, cierre prematuro). Las tareas 28 a 30 no son filas nuevas de
`plan_sprints.md` — son deuda técnica concreta, documentada explícitamente por
tareas anteriores, con criterio de aceptación ejecutable. Ver "Por qué ese
orden" abajo.

### La 31 destraba Sprint 7 — mecánico, no un descubrimiento por hacer

Con el arquetipo formulario en `develop` (tarea 31: `page-header`, `tabs`,
`form-section` evolucionado, `progress-meter`, `summary-card`, `file-field`,
`form-actions-bar`, todos documentados en `sistema_diseno_panel.md` §3/§14 y
`guia_pantalla_panel.md` §6.3), Sprint 7 entero puede entrar al ciclo
automático: HU-22 (clientes), HU-23 (contratos), HU-24 (campos y lotes),
HU-25 (órdenes), HU-26 (personas y bases), HU-27 (drones) y HU-45 (usuarios)
son la misma pantalla ABM con otros campos, compuesta sobre las siete piezas
del catálogo — sin diseño nuevo que inventar. Cada una suma, además del
criterio de negocio de su HU, un spec visual propio en `tests/Visual/` (mismo
patrón que `organizacion.spec.ts`) para que la pantalla número 7 se vea como
la número 1 sin revisión a ojo. Todavía no están como filas de esta tabla —
falta que la próxima sesión de planificación les escriba el criterio de
aceptación ejecutable de cada una (qué campos, qué validación, qué permiso de
`sec_action`) a partir de `plan_sprints.md` y la especificación funcional.

### Condicionadas — todavía no tienen sobre qué correr

Las invariantes 2 (nunca se sobrescribe un registro validado) y 3 (el devengo
se genera solo al validar) vigilan tablas que aún no existían al escribirlas;
ambas ya tienen su gate (tareas 14 y 16, ver "Por qué ese orden" en versiones
anteriores de este documento).

Las alertas de la tarea 26 que dependen de `mezcla` (desvío ±5%, hectáreas
incoherentes por dosis) o de `anticipos`/`rendiciones` (fuera de alcance por
CR-01 las primeras, todavía sin construir las segundas) no tienen sobre qué
correr — su gate se escribe cuando esos dominios existan, no antes.

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

Ninguna de las tres agota Sprint 6 ni resuelve TE-02/TE-08 — la próxima
planificación, al cerrar la 30, va a tener que volver a repasar si algo nuevo
quedó documentado como pendiente en el camino, y si no, cerrar con
`runs/DETENER` explicando que Sprint 6 sigue sin nada automatizable.

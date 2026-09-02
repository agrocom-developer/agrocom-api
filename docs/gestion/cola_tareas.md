# Cola de tareas automatizables

**Última actualización: 2/9/2026.** Este es el backlog que el ciclo de
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
| 22 | HU-08 — incidencias con foto (caldo/ESC/batería/mecánica/clima), ligadas a la sesión, nuevo tipo de registro del motor de sync | `./bin/verify` = 0, con test de rechazo sin evidencia y de registro válido | `Operaciones/Contratos/**`, `Operaciones/Dominio/**`, `EscrituraSincronizacionEloquent`, `Sincronizacion/Aplicacion/**`, migración `ope_incidencias`, tests | **sí** | 3 | **verificada y APROBADA** (implementación y revisión crítica interna en verde, `runs/22.veredicto`); **PR #59 sigue en borrador esperando revisión humana** — ver `runs/revision-pendiente.txt` y "El bug de la 24" más abajo |
| 23 | HU-13 — recargas del dron: batería, temperatura (alerta > 50 °C), litros de caldo por sesión, combustible del generador, motivo/hora de retraso por caldo | `./bin/verify` = 0, con test de alerta de temperatura y de recarga válida | `Operaciones/Contratos/**`, `EscrituraSincronizacionEloquent`, `Sincronizacion/Aplicacion/**`, migración `ope_recargas`, tests | **sí** | 4 | **hecha** (PR #61, mergeado 1/9/2026; su prompt tuvo que recuperarse a mano del historial de git a mitad de tarea — ver "El bug de la 24" abajo) |
| 24 | HU-17 — acta por lote: PDF con hectáreas conformadas, firma del agrónomo referenciada como evidencia (`firma_acta`), máquina de estados `pendiente → firmada` | `./bin/verify` = 0, con test de guarda (no genera sobre trabajo abierto) y de firma válida | `Operaciones/**`, migración `ope_actas`, `sec_action`/permisos, pantalla mínima del panel, tests | **sí** | 5 | **verificada y APROBADA** (implementación y revisión crítica interna en verde, `runs/24.veredicto`, "APROBADO CON OBSERVACIONES"); **PR #62 sigue en borrador esperando revisión humana** — misma situación que la 22. Bloquea a la 25 (ver fila siguiente) |
| 25 | HU-18 — reporte técnico por lote: PDF automático al firmar el acta de la tarea 24 (imagen del campo, horas de inicio/fin, condiciones, litros de caldo/ha, incidencias con evidencia, detalle de sesiones con relevo/cambio de dron), sin contenido de mezcla/dosis (CR-01: no existe ese dato) | `./bin/verify` = 0, con test de generación automática al firmar el acta y de rechazo si el trabajo todavía no está conformado | `Operaciones/**`, migración `ope_reportes_tecnicos`, `sec_action`/permisos, pantalla mínima del panel, tests | no | 4 | **BLOQUEADA** (`runs/25.md`) — no hay `Acta` en `develop` (el PR #62 de la tarea 24 sigue en borrador), y sin acta firmada no hay "conformado" sobre qué generar el reporte. Pregunta para el usuario: **¿corresponde sacar el PR #62 de borrador y mergearlo?** Ver la nota sobre la política de PRs críticos, abajo |
| 26 | HU-19 — bandeja de alertas por excepción (batería caliente, dron sospechoso, condiciones forzadas, suma excedida/`observado`), recortada a lo que ya tiene datos reales — el resto de la lista de la espec depende de mezcla/anticipos/rendiciones, módulos que todavía no existen | `./bin/verify` = 0, con test de generación de cada alerta cubierta y de la transición `pendiente → atendida` | `Operaciones/**`, migración `ope_alertas`, `sec_action`/permisos, pantalla mínima del panel, tests | no | 4 | **hecha** (PR #63, abierto 2/9/2026 contra `develop`, auto-merge pendiente de CI en verde; no crítica, se revisa por diff y test en el PR, no antes de integrarse). Bandeja en `/panel/alertas`, no `/api/alertas` como nombraba el prompt: `routes/api.php` es exclusivo de las apps de campo por ADR 0008 — ver `runs/26.md` |

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
nadie lo rescatara, hasta esta planificación.

**El patrón que emerge**: el bug no solo pierde prompts (eso ya se sabe
rescatar, `git log --all --oneline -- prompts/NN-slug.md` encuentra el commit
aunque esté en una rama sin mergear); combinado con que las tareas 22 y 24
—las dos primeras ramas que arrancaron después de escribir varios prompts a
la vez— son ambas críticas y ninguna de sus dos PRs se mergeó, el bug
**encadena bloqueos**: cada prompt perdido depende de que una rama crítica en
borrador se mergee para volver a `develop`. La corrección de raíz —que
`preparar_rama()` solo agregue `prompts/${id}-*.md`, no el glob completo— es
un cambio de una línea en `bin/ciclo`. Ya lleva dos vueltas sin aplicarse
porque no es una HU del plan de sprints y ninguna sesión de planificación
implementa código. Alguien con acceso directo al repo debería aplicarlo antes
de la próxima vez que la cola escriba 3 prompts seguidos — la próxima
ocurrencia ya no tiene por qué ser inofensiva si la rama que se lleva los
prompts ajenos termina `RECHAZADA` o se descarta.

### La política de PRs críticos en borrador — contradicción sin resolver

`CLAUDE.md` dice, explícitamente, que la revisión de lo crítico **es
posterior a la integración, no previa**: el PR se mergea a `develop` y la
revisión línea por línea se anota en `runs/revision-pendiente.txt` para
hacerse después — y da como razón un incidente ya vivido (el PR #46 quedó en
borrador y bloqueó doce HU hasta que el ciclo se quedó sin trabajo).
`automatizacion_desarrollo.md` §5 documenta lo contrario como comportamiento
querido: "las tareas críticas se implementan pero no se integran solas...
su PR se abre en borrador, que es precisamente el caso que `auto-merge.yml`
deja pasar de largo".

En la práctica, de las once tareas críticas cerradas hasta ahora, nueve
mergearon solas (09, 12, 13, 14, 16, 17, 18, 19, 20, 21 — el `runs/
revision-pendiente.txt` las lista igual, revisión pendiente pero ya
integradas) y dos quedaron trabadas en borrador sin mergear (22, 24) — ambas,
no por casualidad, las dos primeras ramas que arrancaron justo después de que
una planificación escribiera varios prompts de una sola vez (ver "El bug de
la 24" arriba). No investigué por qué el mecanismo de auto-merge trató estas
dos distinto de las otras nueve — puede ser simplemente que a estas dos
todavía no las miró una persona para sacarlas de borrador a mano, mientras que
las nueve anteriores sí. Sea cual sea la causa mecánica, hay dos documentos
que dicen cosas opuestas sobre qué **debería** pasar, y eso es lo que hay que
resolver — no algo que esta planificación pueda decidir sola. Quedó como la
pregunta bloqueante de la tarea 25 (`runs/25.md`): si la respuesta es "sí,
sacalo de borrador y mergealo" (que es lo que dice `CLAUDE.md`), probablemente
haya que revisar también el PR #59 (tarea 22) con el mismo criterio, y ajustar
`automatizacion_desarrollo.md` §5 para que deje de documentar lo contrario.

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

**Los dos bloqueos que detuvieron el ciclo el 1/9/2026 están levantados.** El
PR #46 se integró a `develop`: `ope_trabajos`/`ope_sesiones` existen y HU-04 a
HU-19 vuelven a calificar todas. Y CR-01 se cerró: HU-10 y HU-13 quedan
redefinidas sobre volumen de caldo, HU-11 y HU-12 desaparecen. El orden
seguido desde entonces: **12** (hallazgos del sync) → **HU-05** (esqueleto
vertical del lado servidor, tarea 13) → **HU-14** y **HU-15** (las dos
pantallas de panel, tareas 14/15) → **HU-16** (devengo, tarea 16) → **HU-06**
(condiciones de vuelo, tarea 17) → **HU-10** (recepción de caldo, tarea 18)
→ **TE-07 parte servidor** (evidencias, tarea 19) → **HU-07** (relevo de
piloto, tarea 20) → **HU-09** (cierre de lote con evidencia, tarea 21) → **HU-08**
(incidencias, tarea 22) → **HU-13** (recargas del dron, tarea 23) → **HU-17**
(acta con firma, tarea 24) — las quince ya implementadas y verificadas
(algunas mergeadas, otras en borrador esperando revisión, ver la tabla
arriba). Sigue: **HU-18** (reporte técnico, tarea 25, **BLOQUEADA**) y
**HU-19** (alertas por excepción, tarea 26, sin bloqueo).

La regla que decide es la del punto 1 de
[automatizacion_desarrollo.md](automatizacion_desarrollo.md): una tarea avanza
sin supervisión solo si su criterio de aceptación es un comando que devuelve 0 o
1. Lo de otro repo no lo toca este ciclo, y punto.

Muchas de estas tienen **una parte que sí califica**: de HU-04, los endpoints y
sus tests; de HU-05, el lado servidor del flujo. Cuando sea el caso, la tarea se
escribe sobre esa parte y se dice explícitamente qué queda del lado de la app.

### Condicionadas — todavía no tienen sobre qué correr

Las invariantes 2 (nunca se sobrescribe un registro validado) y 3 (el devengo
se genera solo al validar) vigilan tablas que aún no existen: `sesion`,
`devengo`, la máquina de estados operativos. Su gate se escribe **en la misma
tarea que cree ese dominio**, no antes: un test de aduana sobre un dominio
inexistente pasa siempre y da una sensación de cobertura que no existe.

La invariante 2 ya tiene su gate: la tarea 14 lo escribió junto con el
mecanismo de corrección del rechazo (`RechazoSesionTest.php`, compara
columna por columna que solo `anulada_en` cambia). La invariante 3 sigue
esperando — su gate (que `cerrar()` nunca genere un devengo) se escribe en
la tarea 16, junto con el dominio de `Finanzas` que hace que haya algo que
guardar.

Del mismo tipo: las alertas de la tarea 26 que dependen de `mezcla`
(desvío ±5%, hectáreas incoherentes por dosis) o de `anticipos`/`rendiciones`
(fuera de alcance por CR-01 las primeras, todavía sin construir las segundas)
no tienen sobre qué correr — su gate se escribe cuando esos dominios existan,
no antes.

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

La 04 es una aduana estática, del mismo tipo que `ArquitecturaModulosTest` y
`TokensColorTest`: puesta antes que el código, falla la primera vez que alguien
la cruce; puesta después, ya hay que salir a buscar qué se coló. La 06 sí es
implementación: el ADR 0007 decidió la bitácora como trait/observer de
plataforma y todavía no existe — solo está `RegistraAutoria`, que cubre autoría
por fila pero no el antes/después de cada mutación.

**07 antes que cualquier tarea de panel.** Sin capturas de referencia, ningún
cambio visual puede cerrarse sin que una persona mire la pantalla — y eso saca
del turno desatendido a todo el frontend.

**09 al final y en borrador.** El motor de sync es lo primero de la lista de
`CLAUDE.md` que no se delega sin revisión línea por línea. Que el ciclo lo
implemente y lo deje en un PR en borrador con su test de replay en verde es
útil: el trabajo mecánico queda hecho y la revisión humana empieza sobre algo
que ya pasa la cascada. Que se mergee solo, no. (Este PR en particular sí
terminó mergeándose después de la revisión humana — ver fila 09. La
contradicción entre este párrafo y lo que pasó con las tareas 22/24 está
anotada en "La política de PRs críticos en borrador" más arriba.)

**08 recorta el alcance de TE-06.** `plan_sprints.md` describe TE-06 como el
pull de cinco catálogos (órdenes, recetas, productos, lotes, personas), pero
`receta` y `producto` no tienen migración ni módulo dueño: `Mezclas`
(prefijo `mez_`) está reservado en el ADR 0011 desde que se fijaron los
prefijos, pero la carpeta nunca se creó — no le toca el turno hasta el
sprint 4 (HU-10 en adelante). Meter la creación de `Mezclas` en la misma
tarea que el endpoint de pull duplicaría el tamaño de la sesión y mezclaría
modelo de datos nuevo con un endpoint de sync. La 08 cubre las tres entidades
que sí existen (órdenes, lotes, personas) — suficiente para lo que HU-04
necesita — y deja anotado que el pull se extiende cuando `Mezclas` exista.

**10 salta a Sprint 5 porque casi todo Sprint 2/3/4 quedó bloqueado a la vez.**
No es un cambio de estrategia: es que el PR #46 (crítico, en borrador) y CR-01
(decisión de negocio sin tomar) bloquean, entre los dos, prácticamente todo lo
que sigue en orden — ver la tabla "Fuera del ciclo automático" arriba, filas
HU-04 a HU-19. HU-20 no depende de ninguno de los dos. En cuanto el PR #46 se
mergee (revisión humana) o CR-01 se resuelva, la próxima planificación vuelve
al orden normal del plan — no hace falta reordenar nada a mano, las filas
bloqueadas se reevalúan solas en la próxima vuelta.

**Detenida el 1/9/2026 tras la tarea 11, y retomada el mismo día.** HU-20
(10/11) era la última fila que no dependía de ninguno de los dos bloqueos —
ver `runs/DETENER` y `runs/11-plan.md`. El PR #46 se mergeó horas después
(tarea 09) y CR-01 se cerró el mismo día: los seis sprints volvieron a
calificar, y el ciclo retomó con la 12.

**16 → 17 → 18 siguieron el orden literal de "acá en adelante" fijado por la
tarea 12**, saltando por delante HU-04/07/08/09/TE-07 (Sprint 2/3, todavía sin
cerrar) sin dejarlo anotado explícitamente en su momento — un hueco real del
trazado, no una exclusión deliberada. La planificación tras la tarea 18 lo
reconcilió: **HU-04 (parte servidor) ya estaba cubierta** desde la tarea 12,
así que no hacía falta tarea propia. Lo que sí seguía genuinamente sin hacer —
HU-07, HU-08, HU-09, TE-07 (parte servidor) — se retomó en el orden que impone
la dependencia real, no el orden de fila de `plan_sprints.md`: **TE-07
primero** (tarea 19) porque HU-07 y HU-09 necesitan poder referenciar una
evidencia ya subida (`captura_rc`/`imagen_campo`) antes de poder exigirla;
**HU-07 después** (tarea 20) porque es donde vive de forma más natural la
validación de suma de hectáreas contra tolerancia (espec §5, línea 206) que
**HU-09 reutiliza** (tarea 21) en vez de reimplementar.

**22 → 23 → 24 cierran lo que quedó pendiente de los sprints 3, 4 y 5, en ese
orden.** **HU-08** (tarea 22) es lo único que le faltaba al sprint 3: depende
solo de la tarea 19 (evidencias), ya integrada, y reusa el tipo `foto_incidencia`
que esa tarea dejó listo sin usar — no hay motivo para seguir postergándola.
**HU-13** (tarea 23) es lo único que le falta al sprint 4 (HU-11/12
desaparecieron por CR-01, el resto ya está hecho): sin dependencia real de la
22, pero sigue el orden del plan porque no hay ninguna razón para adelantarla.
**HU-17** (tarea 24) abre el sprint 5: de sus filas, HU-16 y HU-20 ya están
hechas y TE-08 sigue sin datos de beta para calificar, así que HU-17 es la
primera pendiente.

**25 → 26 siguen encadenadas a la 24 por dato, no por código.** **HU-18**
(tarea 25) es lo que la propia espec (§9) ata a la firma del acta: "PDF
automático al conformar el lote" — necesita que la tarea 24 exista para tener
qué disparar, y arma el resto del contenido leyendo lo que las tareas 17/18/20/
21/22/23 ya dejaron persistido (condiciones, recepción de caldo, sesiones con
relevo, incidencias, recargas) — sin nada de mezcla/dosis, fuera de alcance por
CR-01. **HU-19** (tarea 26) no depende de código de ninguna de las dos, pero se
recorta a los cuatro tipos de alerta de la espec §10 que ya tienen datos reales
detrás (batería caliente y dron sospechoso de la tarea 23, condiciones forzadas
de la tarea 17, suma excedida/`observado` de la tarea 20): el resto de la lista
—hectáreas incoherentes y desvío de mezcla (CR-01, no hay dosis que comparar),
anticipo al límite y rendición pendiente (`anticipos`/`rendiciones` no
existen)— queda para cuando esos dominios existan, mismo criterio que la
sección "Condicionadas" de arriba.

**Confirmado en esta vuelta: la 25 quedó `BLOQUEADA`, la 26 no.** Tal como
anticipó la nota de arriba ("si una queda BLOQUEADA o INCOMPLETA no debería
frenar a la siguiente"), la 25 se trabó exactamente por lo previsto (sin
`Acta` en `develop`, no hay "conformado" sobre qué generar el reporte) y la 26
no depende de ese mismo bloqueo — su prompt, perdido por la segunda vuelta del
"bug de la 24", se recuperó sin cambios y sigue siendo la siguiente tarea
válida. Esta planificación no escribió tareas nuevas más allá de la 26: su
prompt ya estaba completo y bien pensado (escrito por la planificación
anterior), así que corresponde usarlo tal cual en vez de reescribirlo —
la próxima planificación (al cerrar la 26) retoma la cola desde cero, y ahí
sí corresponde escribir 3 tareas nuevas de una sola vez. Con la 25 bloqueada y
la 26 siendo la última fila de Sprint 5 que no depende de TE-08 (sigue sin
datos de beta), esa próxima planificación va a tener que decidir entre volver
sobre la 25 (si el usuario ya resolvió la pregunta bloqueante) o abrir Sprint 6
— cuyas filas, adelanto, probablemente no califiquen ninguna todavía (ensayo
en campo, producción, datos maestros reales), así que es razonable que esa
vuelta termine cerca de `runs/DETENER` en vez de con tareas nuevas.

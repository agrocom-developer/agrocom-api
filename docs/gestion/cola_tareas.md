# Cola de tareas automatizables

**Última actualización: 1/9/2026.** Este es el backlog que el ciclo de
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
| 09 | TE-05 — `POST /api/sync` idempotente | `./bin/verify` = 0, con test de replay (mismo lote 10 veces, en orden y en desorden → base idéntica) | `app/Dominios/Sincronizacion/**`, migraciones, tests | **sí** | 5 | **implementada, sin mergear** — `runs/09.estado`=OK, veredicto APROBADO, pero el PR #46 sigue **en borrador** esperando revisión humana línea por línea (`CLAUDE.md`, "qué no delegar"). Mientras no se mergee, ninguna tarea nueva puede usar `ope_trabajos`/`ope_sesiones`: `bin/ciclo` crea toda rama desde `develop` al día, y esas tablas solo existen en `feature/sync-idempotente` |
| 10 | HU-20 — `GET /api/version` y autorización de versiones del APK | `./bin/verify` = 0 | módulo nuevo `Distribucion` (`dis_`), `sec_action`/seed de permisos, rutas de API, pantalla del panel, tests | no | 3 | encolada |

### Fuera del ciclo automático

Estas HU y TE del plan de sprints **no califican** y la sesión de planificación
las saltea sin detenerse — están anotadas acá para que no las redescubra en cada
vuelta. Saltearlas no las cancela: siguen en `plan_sprints.md` y las hace una
persona cuando corresponda.

| Id | Por qué no califica |
|---|---|
| TE-01 (resto) | El repo `agrocom-field` no existe y staging está diferido (ADR 0010, el servidor no está definido) |
| TE-02 | Spike de hardware: necesita el RC Agras en mano. Su entregable es un informe, no un exit code |
| TE-04 | Base local drift + outbox: vive en `agrocom-field`, otro repo |
| HU-04, HU-05 | La parte de API depende de `ope_trabajos`/`ope_sesiones` (TE-05), que solo existen en `feature/sync-idempotente` — el PR #46 sigue en borrador sin mergear. El resto del criterio se demuestra en la app y en el RC. Vuelve a calificar en cuanto el PR #46 se integre a `develop` |
| HU-06, HU-07, HU-08, HU-09, TE-07 | Sprint 3 entero opera sobre `ope_sesiones` (condiciones, cierre, incidencias, evidencia) — mismo bloqueo que HU-04/05: la tabla no está en `develop` todavía |
| HU-10, HU-11, HU-12, HU-13 | Dependen de CR-01 (`analisis_clasificacion.md:36,122`): si Agrocom prepara la mezcla o la prepara el cliente — decisión de negocio no tomada, "define el módulo entero" |
| HU-14, HU-15 | Dependen de `ope_sesiones`/`ope_trabajos` (cola de validación, tablero de trabajos) — mismo bloqueo que HU-04/05 |
| HU-16, HU-17, HU-18, HU-19 | Sprint 5 restante: devengos, actas, reporte técnico y alertas dependen todos de sesión/trabajo validados — mismo bloqueo |
| TE-08 | Endurecimiento del sync con datos de las betas: no hay datos de beta real todavía, depende de que exista staging con tráfico |
| TE-09, HU-21 | Ensayo general en campo con operarios reales y sus correcciones de adopción |
| TE-10, TE-11 | Producción (VPS, HTTPS, respaldos, Sentry) y carga de datos maestros reales: dependen de infraestructura y de datos que no están |
| TE-12 | Cierre de ruta crítica (matriz de permisos, seeds de producción, tag v1.0): prematuro mientras el grueso del plan siga bloqueado por lo de arriba |
| Reunión de cierre de la especificación | Es de negocio. `analisis_clasificacion.md` §7 tiene la agenda |

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
inexistente pasa siempre y da una sensación de cobertura que no existe. Quedan
anotadas acá para que la sesión de planificación las enganche cuando el Sprint
2 traiga las tablas — que ya trajo TE-05 (`sesion`/`trabajo` existen desde el
PR #46), pero sin el concepto de "validado" todavía no hay nada que guardar:
el gate real sigue esperando a HU-14/HU-16.

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
que ya pasa la cascada. Que se mergee solo, no.

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

# Cola de tareas automatizables

**Última actualización: 31/8/2026.** Este es el backlog que el ciclo de
`bin/ciclo` consume solo — el punto 3 de
[automatizacion_desarrollo.md](automatizacion_desarrollo.md). No reemplaza a
`plan_sprints.md`: ahí están las HU y TE con su alcance de negocio; acá está lo
que además tiene **criterio de aceptación ejecutable**, que es lo único que
puede avanzar sin nadie mirando la pantalla.

La cola en ejecución es `runs/cola.txt` (ids, uno por línea, en orden). Esta
tabla es su versión legible, con el porqué de cada fila.

## Cómo se lee una fila

| Campo | Qué significa |
|---|---|
| **Id** | Prefijo del prompt: `prompts/NN-slug.md` |
| **Criterio** | El comando que la acepta. Si no se puede escribir un comando, la tarea no entra en la cola |
| **Puede tocar** | El alcance de archivos. Lo de afuera es un hallazgo del verificador |
| **Crítica** | `sí` = está en la lista de "qué no delegar sin revisión línea por línea" de `CLAUDE.md`. Se implementa igual, pero el PR se abre en borrador y lo revisa una persona |
| **Intentos** | Sesiones antes de rendirse y dejarla marcada para el usuario |

## Cola

| Id | Tarea | Criterio | Puede tocar | Crítica | Intentos | Estado |
|---|---|---|---|---|---|---|
| 03 | HU-03 — token Sanctum por dispositivo | `./bin/verify` = 0 | `app/Dominios/Seguridad/**`, migraciones, seeders de catálogo, rutas, `tests/Feature/Seguridad/**` | no | 3 | **hecha** |
| 04 | Aduana de la invariante 7: ninguna asignación de estado fuera del servicio de estados | `./bin/verify` = 0, y el gate falla al inyectarle una asignación suelta | `tests/Unit/**`, `app/Dominios/Compartido/**` | no | 3 | **hecha** |
| 05 | Rescate del dashboard: integrar `feature/dashboard-agro`, cuyo PR se cerró sin mergear | `./bin/verify` = 0 y el PR abierto fuera de borrador | la rama `feature/dashboard-agro`, `docs/gestion/plan_dashboard_rediseno.md` | no | 3 | **hecha** |
| 06 | Bitácora de auditoría transversal (invariante 9, ADR 0007) y su gate | `./bin/verify` = 0, y el gate falla ante un modelo de dominio sin bitácora | `app/Dominios/Compartido/**`, migraciones, `tests/**`, la fila de prefijo del ADR 0011 | no | 3 | **hecha** |
| 07 | Regresión visual del panel con Playwright | `npx playwright test` = 0 con capturas de referencia versionadas | `playwright.config.*`, `tests/Visual/**`, `package.json` | no | 3 | **hecha** |
| 08 | TE-06 (parcial) — pull de catálogo con cursor: órdenes, lotes y personas | `./bin/verify` = 0 | `app/Dominios/Sincronizacion/**`, `Contratos/` de Operaciones/Comercial/Personal, rutas de API, tests de feature | no | 3 | siguiente |
| 09 | TE-05 — `POST /api/sync` idempotente | `./bin/verify` = 0, con test de replay (mismo lote 10 veces, en orden y en desorden → base idéntica) | `app/Dominios/Sincronizacion/**`, migraciones, tests | **sí** | 3 | encolada |

### Condicionadas — todavía no tienen sobre qué correr

Las invariantes 2 (nunca se sobrescribe un registro validado) y 3 (el devengo
se genera solo al validar) vigilan tablas que aún no existen: `sesion`,
`devengo`, la máquina de estados operativos. Su gate se escribe **en la misma
tarea que cree ese dominio**, no antes: un test de aduana sobre un dominio
inexistente pasa siempre y da una sensación de cobertura que no existe. Quedan
anotadas acá para que la sesión de planificación las enganche cuando el Sprint
2 traiga las tablas.

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

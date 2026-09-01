<!-- ciclo: critica=no turno-noche=1 descongela=tests,decisiones rama=feature/version-apk etapas=3 -->

# Tarea 10 — HU-20: autorizar versiones del APK, `GET /api/version`

## Contexto (por qué esta y no la que sigue en el plan)

El resto de Sprint 2 y todo Sprint 3 (HU-04 a HU-09, TE-07, HU-14, HU-15)
operan sobre `trabajo`/`sesion` — tablas que TE-05 creó, pero cuyo PR (#46)
sigue **en borrador esperando revisión humana** (`critica=si`, motor de
sync). `bin/ciclo` crea toda rama nueva desde `develop` al día
(`bin/ciclo:200-207`), y esas tablas no están ahí todavía. Cualquier tarea
que las necesite fallaría desde el primer commit.

Mezclas (HU-10 a HU-13) depende de CR-01
(`docs/gestion/respuestas_campo/analisis_clasificacion.md:36,122`): si
Agrocom prepara la mezcla o la prepara el cliente. Decisión de negocio no
tomada — "define el módulo entero". No corresponde adivinarla acá.

HU-20 no depende de ninguna de las dos cosas: usa `sec_action` (ya existe,
Seguridad) y crea su propio módulo nuevo. Es la primera candidata libre.
Cuando el PR #46 se mergee, la próxima planificación retoma el orden normal
del plan desde ahí.

## Qué hacer

"Como **dueño**, quiero autorizar las versiones del APK desde el panel, para
que ningún RC se actualice sin mi visto bueno" (`plan_sprints.md`, HU-20,
Sprint 5).

CA esenciales del plan: `GET /api/version`; distribución autohospedada.
El tercer CA del plan ("app bloquea bajo versión mínima") es lógica de
`agrocom-field` — ese repo no existe todavía. Acá entra solo lo que el
servidor tiene que ofrecer para que la app, el día que exista, pueda
implementarlo: la versión mínima y la vigente, servidas por el endpoint.

Cargá los skills `verificacion`, `dominio-backend`, `modelo-datos`,
`seguridad-roles` y `flujo-git-pr` antes de tocar código.

### Antes de escribir código

Escribí `runs/10-diseno.md` (no más de una carilla): a qué módulo pertenece
esto. No hay ninguno existente que encaje — no es `Seguridad` (no es
identidad ni permisos), no es `Comercial`/`Operaciones` (no es negocio de
fumigación). ADR 0011 no reserva prefijo para esto. Proponé un módulo nuevo
(`Distribucion`, prefijo `dis_`, es la lectura natural del listado de
endpoints §8 y coincide con el agente `distribucion` ya definido en
`.claude/agents/`) y agregá esa fila a la tabla de ADR 0011 — es ampliar lo
que el propio ADR ya deja abierto ("queda disponible sin costo"), no revisar
una decisión tomada; por eso esta tarea abre `descongela=decisiones`, solo
para esa fila.

Resolvé también dónde vive el binario del `.apk`. ADR 0009 cubre evidencias,
reportes y assets de marca — un binario de instalación no es ninguna de las
tres. La opción consistente con la decisión ya tomada ahí es el mismo patrón
(disco `r2`, URL firmada con expiración, nunca pública) en una ruta propia,
p. ej. `distribucion/apk/{version}.apk` — mismo mecanismo, carpeta nueva, sin
reabrir el ADR. Si al escribirlo ves que no cierra, `runs/10.estado` con
`BLOQUEADA` y la pregunta concreta — no lo fuerces.

### Piezas a construir

1. **Migración** de la tabla de versiones (`dis_versiones_apk` o el nombre
   que salga del diseño): `version` (SemVer), `version_code` (entero,
   Android), `ruta_apk` o `url_apk`, `estado` (`pendiente`/`autorizada`/
   `rechazada` — mínimo necesario para saber cuál es la vigente), soft
   delete + auditoría (invariantes 8/9, obligatorias como en toda tabla de
   dominio). Solo puede haber **una versión `autorizada` a la vez** — es una
   invariante de negocio real (análoga a "un rol activo por sesión"), no
   opcional.
2. **Transición de estado por servicio de dominio** (invariante 7): autorizar
   una versión nueva desautoriza la anterior, en la misma transacción — nunca
   un `estado = ...` suelto. Mismo patrón que `MaquinaEstadosTrabajo` de
   TE-05, pero podés mirar directamente `tests/Unit/TransicionesEstadoTest.php`
   para la convención exacta (`Aplicacion/MaquinaEstados/<Entidad>`), sin
   necesidad de leer el módulo `Operaciones` (que no está en esta rama).
3. **Endpoint `GET /api/version`**: versión mínima y versión vigente
   autorizada, con su URL de descarga firmada. Es lo que la futura app va a
   consultar antes de operar — no tiene sentido exigirle un token de
   dispositivo todavía inexistente para eso, así que va **sin auth**, con
   throttle (mismo criterio que `POST /auth/token` en `routes/api.php`, 6
   intentos por minuto y por IP alcanza). Documentado con atributos OpenAPI
   (ADR 0014); `docs/api/openapi.yaml` regenerado (`composer openapi`).
4. **Panel**: pantalla para que el dueño suba una versión y la autorice.
   Permiso `autorizar_version` — ya nombrado en `sec_action` según ADR 0004
   (`docs/decisiones/0004-modelo-seguridad-sec-multirol.md:96`) pero nunca
   seedeado; agregalo al seeder de acciones y asignalo al rol dueño. Sin
   ese permiso, autorizar responde 403 (test obligatorio). Componentes
   Blade en Atomic Design (ADR 0002), sin colores hardcodeados (invariante
   11) — cargá `panel-design-ui` si tocás CSS nuevo.

## Cómo repartir las etapas

- **Etapa 1**: `runs/10-diseno.md`, migración + máquina de estados +
  seed del permiso `autorizar_version`, con sus tests (esquema, transición,
  "solo una autorizada a la vez").
- **Etapa 2**: subida del `.apk` al disco `r2` (patrón ADR 0009) + endpoint
  `GET /api/version` + `openapi.yaml` regenerado, con tests de
  comportamiento HTTP.
- **Etapa 3**: pantalla del panel (listar, subir, autorizar) con su test de
  permiso (403 sin `autorizar_version`) y cierre de la cascada completa.

Sugerencia, no contrato — si una etapa rinde distinto, seguí el criterio de
la HU entera.

## Qué NO hacer

- No toques `app/Dominios/Operaciones/`, `Sincronizacion/`, ni nada que
  dependa de `trabajo`/`sesion` — esas tablas no existen en esta rama
  (nacen de `develop`, y TE-05 sigue sin mergear). Si te encontrás
  necesitándolas, pará: es señal de que te saliste del alcance de HU-20.
- No implementes Mezclas, receta ni nada de CR-01.
- No decidas lógica de "bloqueo por versión mínima" del lado cliente — eso
  es `agrocom-field`. Acá el servidor solo informa.
- No reabras ADR 0009 más allá de agregar la ruta nueva del `.apk`; no
  reescribas su decisión de evidencias/reportes.
- No subas el `.apk` real al repo ni a `public/` — sigue el patrón `r2` como
  evidencias y reportes, nunca `disco public` (ADR 0009 lo reserva a assets
  de marca).

## Criterio de aceptación

`./bin/verify` → exit code 0, con test para cada CA: `GET /api/version`
devuelve la vigente y la mínima; autorizar sin el permiso → 403; autorizar
una versión nueva desautoriza la anterior (una sola `autorizada` a la vez);
`ArquitecturaModulosTest` y `TransicionesEstadoTest` en verde sin editarlos.

## Cierre obligatorio de cada etapa

`runs/10.estado` con una sola palabra: `PARCIAL` si avanzó y commiteó pero la
HU sigue abierta, `OK` recién cuando está entera, `BLOQUEADA` si el diseño
del módulo/bucket no cierra. `runs/10.md` con qué se hizo y qué falta,
concreto. Al cerrar con `OK`, `runs/10.pr.md` con el título del PR en la
primera línea y el cuerpo debajo.

## Commits

Agrupados por función — un commit por pieza coherente (migración + máquina
de estados, seed de permiso, endpoint, pantalla del panel, tests), español,
imperativo, el porqué antes que el qué. Sin trailer `Co-Authored-By`.

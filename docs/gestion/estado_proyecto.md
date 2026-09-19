# Estado y continuidad del proyecto

**Última actualización: 2026-09-19.** Este documento no es la especificación (que es estable) ni el plan de sprints (que es la estrategia global con HU y fases): es la bitácora de continuidad entre iteraciones — qué se avanzó, qué falta, y qué leer primero para no releer todo `docs/` de cero en cada sesión nueva. Lo mantiene el agente `memoria-contexto` (`.claude/agents/memoria-contexto.md`) al cierre de cada sesión de trabajo relevante.

## Cómo usar este documento

Al empezar una iteración nueva: leé este documento completo primero (es corto). Después, andá **solo** a los documentos que tu tarea puntual necesita — la tabla de abajo te dice cuáles, para no cargar contexto que no aplica (p. ej. no hace falta leer el ADR del panel web si vas a escribir una migración).

## Mapa de lectura mínima por tipo de tarea

| Si vas a trabajar en... | Leé primero | No hace falta leer |
|---|---|---|
| Migraciones / modelo de datos | `docs/especificacion/especificacion_funcional_tecnica.md` §4, ADR 0001, ADR 0007 | ADR 0002 (panel), ADR 0005 (Flutter) |
| Panel web / componentes Livewire | ADR 0002, ADR 0008 | ADR 0001, ADR 0005, negocio |
| Seguridad / permisos / roles | ADR 0004 | ADR 0002 en detalle visual |
| App Flutter (`agrocom-field`) | ADR 0005, especificación §2 (protocolo de sync) | ADR 0002, ADR 0004 en detalle |
| CI/CD / despliegue / entornos | `.github/workflows/`, `docs/gestion/entornos.md`, ADR 0006, ADR 0010 | especificación funcional completa |
| Negocio / respuestas de campo | `docs/gestion/respuestas_campo/analisis_clasificacion.md`, `docs/negocio/politicas/` (por rol), `docs/negocio/` | ADRs técnicos |
| Nueva decisión de arquitectura | `CLAUDE.md` + índice de `docs/decisiones/` (no cada ADR entero, solo los que tocan el tema) | — |
| Retomar el hilo general | Este documento, completo | todo lo demás, hasta que haga falta |

## Fase actual

> **Nota del 7/9/2026 — el resto de este documento está desfasado.** La tabla de
> abajo quedó en el sprint 2; el estado real es Sprint 12 cerrado (PRs hasta el
> #122) y **Sprint 13 recién planificado**: el dueño trajo un lote de ajustes de
> negocio (campaña como eje, equipos de trabajo con su equipamiento, cultivo por
> lote y campaña, altura de vuelo en el contrato, ventana "todo el día",
> aplicación de siembra o cosecha, entrada y salida de haciendas, e informe de
> avance por cultivo y cliente). Consolidados en el **ADR 0015**, en la
> especificación (§4.0 a §4.4, §5 y §9.1) y en el **Sprint 13**, tareas 69 a 75.
>
> Ese mismo día llegó una segunda tanda, mirando el panel andando: inputs de
> fecha y desplegables obsoletos, propiedades y lotes mezclados en un menú,
> "Personas" que debía decir "Personal", repuestos por casillas, la pestaña de
> Facturación vacía, mapa sin pantalla completa, y una configuración del sistema
> para llaves y tokens. Consolidada en el **ADR 0016**, la extensión del **ADR
> 0002** y el **Sprint 14**, tareas 76 a 80.
>
> El orden de ejecución cruza las dos tandas y está en
> `docs/gestion/cola_tareas.md` ("Por qué ese orden"), no en el número de
> sprint. Para retomar el hilo, leé eso, no la tabla de abajo.

> **Nota del 19/9/2026 — la corrección del dueño del 18/9 sobre contratos,
> lotes y órdenes de aplicación, implementada en dos ramas y sin integrar.**
> Mirando el panel andando, el dueño desechó dos enfoques y dejó decidido el
> reemplazo (`docs/negocio/observaciones_operaciones_comercial_2026-09-18.md`).
> Quedó en dos ramas locales, ambas **sin push ni PR todavía**:
>
> - **`feature/contrato-conflicto` — exclusividad de lotes entre contratos**
>   (HU-96, Sprint 19, **ADR 0021**): un contrato `vigente` o `pausado` retiene
>   sus lotes para toda la campaña, y los `borrador` que compartían lote pasan
>   solos a `conflicto` ("En conflicto").
> - **`feature/orden-correlativa` — órdenes de aplicación** (HU-97, Sprint 20,
>   **ADR 0022**): cada orden es una aplicación completa del contrato, con número
>   correlativo y una sola abierta por contrato; estados nuevos `pausada` y
>   `cancelada` (solo desde el panel); cerrar la última aplicación finaliza el
>   contrato y libera sus lotes. Nace de `feature/contrato-conflicto`.
>
> **Pendiente:** la prueba manual del dueño, tras `migrate:fresh --seed` en el
> compose (las migraciones de `feature/orden-correlativa` frenan con un mensaje
> si la base ya trae órdenes con número repetido o varias abiertas por
> contrato); la revisión línea por línea, posterior a la integración, de las
> máquinas de estados de la orden y del contrato; y las limitaciones conocidas de
> HU-97 (la app de campo no se entera de una orden pausada, cancelada o cerrada;
> las sesiones abiertas no se frenan; un lote agregado al contrato no entra en la
> aplicación abierta), que **no están resueltas**.


**Sprint 1 cerrado en lo que es de este repo; sprint 2 en curso (1/9/2026).**

| Id | Qué | Estado |
|---|---|---|
| TE-01 | Esqueleto, CI, GitFlow, `CLAUDE.md` | **hecho** en `agrocom-api`. En `agrocom-field`: **hecho** (repo creado, `flutter create` corrido, flavors `piloto`/`auxiliar`, tema Material 3 del logo, PR #1 mergeado a `develop` de ese repo, 10/9/2026). Falta solo staging (diferido, ADR 0010: el servidor no está definido) |
| TE-02 | Spike de hardware en el RC real | **pendiente**, sin dependencias — necesita el RC en mano |
| TE-03 | Migraciones del núcleo comercial | **hecho** (PR #9) |
| HU-01 | Usuarios multi-rol con un solo login | **hecho** (PR #10) |
| HU-02 | Panel: login, rol activo, menú dinámico, tema | **hecho** (PR #14, más las vueltas de diseño #15 a #20 y #31) |
| HU-03 | Token Sanctum por dispositivo | **hecho** en `agrocom-api` (PR #26). Consumo del token del lado `agrocom-field` (guardado seguro + interceptor de `Authorization: Bearer`): **en curso**, PR propio en ese repo |
| TE-04 | Base local drift + outbox | **en curso en `agrocom-field`** (PR #2 en ese repo, 10/9/2026): tabla `ColaSync` (outbox mínimo, `uuid_cliente` único, `secuencia`, estados `pendiente/enviado/confirmado/rechazado`) ya escrita; faltan las tablas espejo de negocio |
| TE-05 | `POST /api/sync` idempotente | **pendiente**, próxima en la cola (tarea 09, crítica) |
| TE-06 | Pull de catálogo con cursor | **parcial** (PR #40): órdenes, lotes y personas. Recetas y productos esperan al módulo `Mezclas`, que no existe |
| HU-04 | Órdenes vigentes offline | **pendiente** — depende de TE-06 (ya cubierto) y del lado app |
| HU-05 | Esqueleto vertical | **pendiente** |

Además, fuera del plan de sprints, se construyó la automatización del
desarrollo: `bin/verify`, `auto-merge.yml`, los guardarraíles de `.claude/hooks/`,
las skills por área y el ciclo continuo `bin/ciclo`. Está descrita en
[automatizacion_desarrollo.md](automatizacion_desarrollo.md) y las invariantes 7
y 9 ya tienen gate propio (`tests/Unit/TransicionesEstadoTest.php`,
`tests/Unit/BitacoraAuditoriaTest.php`).

## Avanzado hasta ahora

- **Documentación oficial** consolidada: `docs/especificacion/`, `docs/decisiones/` (10 ADRs), `docs/negocio/`, `docs/gestion/`, `docs/glosario.md`. `docs/legacy/` (los 7 documentos originales) se eliminó el 26/8/2026 — queda en el historial de git (`git log --oneline -- docs/legacy/`).
- **GitFlow simplificado** adoptado y en uso real: `master` + `develop` + `feature/*` + `fix/*`, todo por PR.
- **CI/CD funcionando de punta a punta y probado en vivo**: `.github/workflows/ci.yml` (`laravel-tests`: Pint + Larastan + Pest sobre PHP 8.3 con Postgres 16 de servicio; `docs-legacy-guard` retirado junto con `docs/legacy/`) + `.github/workflows/auto-merge.yml` (mergea solo cuando `laravel-tests` está en verde, sin depender del auto-merge nativo de GitHub — no disponible en repos privados de plan Free). Validado con el PR #1: se auto-fusionó a `develop` sin intervención manual.
- **Esqueleto Laravel (26/8/2026)**: Laravel 13 sobre PHP 8.3 (`composer.lock` resuelto con `config.platform.php = 8.3` para cuadrar con el Dockerfile y CI), Pest 4 + Pint + Larastan nivel 6 (`phpstan.neon`), `.env.example` completo con las decisiones vigentes (PostgreSQL, colas/sesión/caché en BD, disco `r2` en `config/filesystems.php`, locale `es`), descripciones de tests en español. Suite verificada dentro del contenedor Docker y migraciones corridas contra el Postgres 16 del compose.
- **Entorno de desarrollo local con Docker** (ADR 0010): `docker-compose.yml` + `Dockerfile` + `.dockerignore`, reemplaza MAMP.
- **Doce subagentes especializados** en `.claude/agents/`: los diez por capa (arquitectura, backend, frontend, design-ui, modelo-datos, estandares-programacion, distribucion, modulos-roles, negocio, memoria-contexto) más `orquestador` y `validador`. Cada uno con el modelo asignado según si su trabajo es de ejecución/instrucciones (modelo económico) o de juicio/coordinación (modelo capaz) — ver `.claude/agents/README.md`.
- **Convención de commits sin coautoría de IA**: desde el commit `084b732` en adelante, los mensajes de commit no llevan el trailer `Co-Authored-By: Claude...`. Los commits anteriores a esa fecha sí lo llevan y quedan así — decisión explícita del usuario de no reescribir historia ya pusheada. **Desde el 1/9/2026 la regla es mecánica**: `.claude/settings.json` declara `"includeCoAuthoredBy": false`. Hasta entonces dependía de que cada sesión se acordara, y se filtraba igual — el squash de GitHub arrastra el trailer de cualquier commit que lo traiga, así que varios merges a `develop` lo llevan puesto.
- **Respuestas de campo procesadas (26/8/2026)**: los 5 CSV del banco de preguntas clasificados en CONFIRMADO/CORREGIDO/DESCUBIERTO con matriz de límites (`docs/gestion/respuestas_campo/analisis_clasificacion.md`), 7 documentos de políticas por rol (`docs/negocio/politicas/` — incluye dueño y cliente, derivados), flujo base y excepciones, automatización/sistematización, alcance y objetivos, requerimientos de sistema (RF/RNF) e insumos para el modelo de datos. Hallazgos mayores: la mezcla la prepara hoy el cliente (CR-01), las pausas atribuibles nunca se registran (DS-01), el actor "encargado de la propiedad" (DS-02), límites de clima como parámetros por contrato, y T30 en la flota real. Supuestos §16 cerrados: ±5% aceptado, firma en cualquier formato, vuelo nocturno confirmado.
- **Auditoría SOLID/Clean Code de HU-01 y TE-03 (27/8/2026)**, hecha por `estandares-programacion` a pedido explícito del usuario (quedó sin argumentar al tomar las decisiones de código, y quería la constancia antes de seguir a HU-02): **cumple**, con evidencia archivo:línea para los 5 principios SOLID y para las convenciones de CLAUDE.md — casos de uso con responsabilidad única (`AsignarRolesUsuario`, `ListarOrdenesAplicacion`), controladores delgados (`OrdenAplicacionController`, comentario explícito "ninguna regla de negocio vive acá"), subtipos sin romper el contrato del padre (`SecUsuarioInterno`/`SecUsuarioCliente` sobre `SecUser`), inyección de dependencias y cero relaciones Eloquent cruzando módulos (solo FK por ID). Dos huecos reales pero no causados por mal diseño, sino por alcance aún no llegado — ver gaps abajo. No bloquean HU-02 (login/menú/tema no tocan estados de contrato/orden ni mutan dinero/hectáreas).

## Cuenta de arranque (tarea 100)

Decisión directa del usuario: la familia `database/seeders/Demo/` se retiró
completa (11 seeders), junto con `tests/Feature/` (157 tests que dependían
de sus datos) — ya no se confía en que la suite automática refleje que el
sistema hace lo que se pide, y se prueba todo a mano, en vivo, contra el
compose real. Esta sección reemplaza a la vieja "Datos demo (cuentas para
probar a mano)", que documentaba `carlos.ferrufino`, `cliente.sanjorge` y el
resto de la cuadrilla/cartera de ejemplo.

Una instalación nueva de `local`/`staging` arranca con menú, roles y
permisos (`CatalogoSeeder`) más una única cuenta, sembrada por
`AdminPlataformaSeeder` (gateado igual que corría `Demo/` antes — nunca en
producción):

| Usuario | Password | Rol | Persona |
|---|---|---|---|
| `miguelo` | `0000` | `admin_plataforma` | — (`persona_id` null: cuenta técnica, no gente de campo) |

`admin_plataforma` recibe el catálogo de permisos completo, sin excepción
— mismo criterio que `dueno` (`SeguridadSeeder`): acceso total sobre
cualquier instalación, incluida la gestión de dueños. Es un rol técnico de
plataforma, no del negocio del cliente, y su seeder es dato de catálogo
puro que corre en todos los entornos (un rol sin usuarios asignados no daña
nada en producción).

El resto de los datos —clientes, contratos, personas, órdenes, sesiones—
los carga el usuario a mano desde el panel, con esta cuenta.

## Ramas y remoto (estado real, no solo local)

- `master`: en GitHub, sin cambios desde el commit inicial. `develop` nunca se
  mergeó a `master` todavía — no hay despliegue, así que no hubo motivo.
- `develop`: al día, con los PR #1 a #41 integrados.
- **Todas las `feature/*` y `fix/*` anteriores están integradas.** Se verificó
  una por una con `bin/limpiar-ramas`: PR mergeado, o ancestro de una rama cuyo
  PR entró (el caso de `feature/panel-admin`, cuyo trabajo viajó dentro del
  PR #18 porque `feature/layout-panel` nace de ella), o contenido idéntico al de
  `develop`. Ninguna tiene trabajo que se pierda al borrarla. La limpieza en sí
  la ejecuta el usuario cuando lo decide, no el ciclo.
- `gh` autenticado como `Angello-27` (permisos `push`/`pull`/`triage`, sin
  `admin`) — suficiente para todo el flujo de PRs y Actions; **no** suficiente
  para branch protection ni settings del repo.

## Próximo paso inmediato

1. **TE-05 — `POST /api/sync` idempotente** (tarea 09 de la cola, `critica=si`).
   Es la apuesta más riesgosa del proyecto y la que habilita todo el sprint 2.
   Su criterio es el test de replay: el mismo lote aplicado 10 veces, en orden y
   en desorden, deja la base idéntica. El PR se abre en borrador y lo revisa una
   persona línea por línea (`CLAUDE.md`, "qué no delegar").
2. **HU-04 y HU-05** detrás de ella: órdenes vigentes offline y el esqueleto
   vertical, que es lo que se demuestra en la Beta interna A.
3. **Reunión de cierre** con la agenda de `analisis_clasificacion.md` §7 (mezcla,
   clima, acta, montos, lotes feos, EPP, boleo) más las 5 preguntas de semántica
   del RC (`analisis_capturas_rc.md` §5) → actualizar la especificación (§3, §4,
   §5, §7, §9, §10, §16) en una iteración dedicada. **Sigue pendiente y es de
   negocio: no lo puede resolver el ciclo automático.**
4. **TE-02, spike de hardware en el RC real**: pendiente, sin dependencias
   técnicas — necesita el equipo en mano.
5. **`agrocom-field`** (actualizado 10/9/2026): el repo ya existe y tiene su
   workstation + esqueleto real (TE-01, PR #1 mergeado). TE-04 (drift +
   outbox) está en curso ahí (PR #2) — vive en ese repo, no en este.

## Decisiones diferidas explícitamente (no reabrir sin que el usuario lo pida)

- ~~**Mapeo de prefijos de tabla por módulo**~~ **resuelto (26/8/2026)**: ADR 0011 (extendido) ya define `per_` para `Personal` (`per_personas`, `per_bases`) y confirma `sec_` para `Seguridad`. Sigue diferido tocar `docs/especificacion/` sección 4 — eso espera la reunión de cierre (punto siguiente).
- **Actualización de la especificación con lo corregido/descubierto**: los hallazgos están clasificados y documentados, pero la especificación NO se toca hasta después de la reunión de cierre y de las capturas del RC — ver `analisis_clasificacion.md` §8.
- **Gesto manual de aprobación para el merge** (comentario `/merge` o etiqueta, además del gate de CI): evaluado y descartado por el usuario — el gate de solo CI en verde ya cumple lo que necesita.
- **Branch protection formal en GitHub** (status checks requeridos vía Settings → Branches): sigue sin aplicarse — requiere permisos de admin que la cuenta `gh` de esta sesión no tiene. No es bloqueante: el auto-merge ya funciona sin ella.

## Otros gaps señalados, aún sin resolver

- No existe un documento oficial de riesgos (el legacy tenía uno en `enfoque_desarrollo_sistema_fumigacion.md` §6 que nunca migró a `docs/gestion/`).
- No hay diagrama ER ni de arquitectura en la documentación oficial (el primer Mermaid es el flujo operativo en `docs/negocio/flujo_base_y_excepciones.md`; ER y arquitectura siguen pendientes).
- **FK real de `created_by`/`updated_by` a `sec_user.id`** (HU-01, diseño `modulos-roles` §6): ahora que `sec_user` existe, las columnas `created_by`/`updated_by` de `com_clientes`, `com_contratos` y el resto de tablas de TE-03 (y de `per_bases`/`per_personas`/`sec_*` de HU-01 mismo) siguen siendo `unsignedBigInteger` sin FK. Retrofit deliberadamente fuera de alcance de HU-01 (toca migraciones de otro módulo ya mergeado) — resolver en un solo pase futuro que agregue la FK a todas las tablas de una vez, no módulo por módulo.
- ~~**Invariante 7 (máquina de estados) sin gate**~~ **resuelto (PR #29)**: `tests/Unit/TransicionesEstadoTest.php` es la aduana — ninguna asignación de estado fuera del servicio de dominio pasa la cascada.
- ~~**Invariante 9 (bitácora de auditoría) incompleta**~~ **resuelto (PR #35)**: bitácora transversal con valores antes/después (ADR 0007) más su gate en `tests/Unit/BitacoraAuditoriaTest.php`, que falla ante un modelo de dominio sin bitácora.
- ~~**Tests de arquitectura ausentes**~~ **resuelto**: `tests/Unit/ArquitecturaModulosTest.php` prohíbe, por descubrimiento automático de carpetas, que un módulo importe `Infraestructura\Eloquent` de otro. Es lo que forzó el patrón de contratos de lectura de TE-06.
- **Invariantes 2 y 3 sin gate todavía** (no sobrescribir un registro validado, devengo solo al validar): vigilan tablas que aún no existen (`sesion`, `devengo`). Su aduana se escribe **en la misma tarea que cree ese dominio** — un test sobre un dominio inexistente pasa siempre y simula una cobertura que no hay.
- **Regresión visual fuera de `bin/verify`** (PR #37): Playwright existe con capturas de referencia, pero corre en el host y no dentro de la cascada (la imagen no trae Node ni navegadores). Un verde de `bin/verify` **no** implica haber corrido la regresión visual.

# ADR 0015 — Campaña como eje transversal, y el equipo de trabajo como unidad de imputación

**Estado:** Aceptada · **Fecha:** 7/9/2026 · **Origen:** ajustes de negocio del dueño (mensajes del 7/9/2026), con las cuatro decisiones de alcance resueltas en la misma sesión.

## Contexto

El sistema se construyó sobre un eje implícito: el **contrato**. Todo cuelga de ahí — órdenes, trabajos, actas, facturas — y las magnitudes de negocio se acumulan sin ningún corte temporal explícito. Eso funcionó mientras solo existía un ciclo productivo en la base, pero rompe en tres lugares concretos:

1. **No hay con qué cerrar un período.** `ObtenerAvanceComercial` (HU-32) se escribió con el enunciado "avance por cliente, contrato **y campaña**" y entregó las dos primeras: la tercera no tenía tabla detrás. Lo mismo `fin_gastos` y `fin_combustibles`, cuyos docblocks dicen literalmente "para que la campaña tenga costo real" y "para imputarlo a la campaña" — imputan a una campaña que no existe como entidad.
2. **El chip del header quedó apagado.** La tarea 67 retiró el chip "Campaña 2026-B" del `CascaraPanel` con el comentario `el dominio no tiene el concepto de campaña en ninguna tabla`. La maqueta original lo tenía porque el negocio lo tiene; el modelo no.
3. **Hay gasto que no se puede imputar a nada.** El combustible de la camioneta y del generador no pertenece a un trabajo (no se sabe a qué lote cargarlo) ni alcanza con la base (varias cuadrillas comparten base). Hoy `fin_combustibles.destino` es un `string` con `CHECK (destino IN ('generador','vehiculo'))` y **no dice cuál** de los dos: no hay FK, porque cuando se escribió no existía `man_vehiculos` ni existe todavía una tabla de generadores.

En paralelo, el negocio nombró una unidad que el modelo no tenía: el **equipo de trabajo** — el piloto y su auxiliar, *"no dónde están trabajando"*. Hoy esa idea solo existe derivada: `LecturaPanelOperaciones::equiposDePersonaDelMes()` la reconstruye desde las sesiones ya voladas (quién compartió sesión con quién, sobre qué dron). Sirve para mostrar en el tablero, pero no sirve para **imputar**: no se le puede cargar un gasto a una derivación de sesiones pasadas, y no responde "¿quién integraba el equipo 1 el 14 de marzo?" ni "¿qué camioneta tenía asignada?".

## Decisión

### 1. La campaña es un eje transversal de primera clase, con campaña activa por sesión

Módulo nuevo `app/Dominios/Campania/`, prefijo de tabla `cpn_` (extiende la tabla del ADR 0011 — **no `cmp_`**, que ese ADR ya había descartado en su punto 11 por parecerse a `com_` a simple vista). Tabla `cpn_campanias`: `codigo` (`2025-2026`), `nombre`, `fecha_inicio`, `fecha_fin`, `estado`.

**Máquina de estados** (invariante 7 de `CLAUDE.md`, servicio de dominio propio): `planificada → abierta → cerrada`. Sin vuelta atrás desde `cerrada` — reabrir una campaña cerrada es exactamente el agujero por el que se cuelan gastos e imputaciones retroactivas que descuadran un cierre ya presentado.

**Guarda de imputación:** ninguna escritura nueva puede imputarse a una campaña `cerrada`. Vale para contratos, órdenes, gastos, combustible, estadías y equipos. Lo verifica el módulo dueño de cada tabla al crear, no un trigger.

**Campaña activa por sesión, espejo exacto del rol activo** (invariante 10): se resuelve por middleware (`ResolverCampaniaActiva`, hermano de `ResolverRolActivo`), se guarda en la sesión, se muestra en el chip del header — el que la tarea 67 dejó apagado — y se cambia sin volver a loguearse. El default al iniciar sesión es la campaña `abierta` que contiene la fecha de hoy; si no hay ninguna, la última `abierta` por `fecha_inicio`.

**La campaña activa filtra, no autoriza.** Es un filtro por defecto de lo que se lista, no una regla de acceso: cambiar de campaña activa nunca da acceso a algo que el rol activo no permitía. Los permisos siguen siendo los del rol activo, y nada más.

**Dónde va `campania_id`.** Solo donde se **imputa** algo que hay que poder cerrar por período: `com_contratos`, `fin_gastos`, `fin_combustibles`, `per_equipos_trabajo`, `com_lote_campania`, `ope_estadias_hacienda`. **No** se agrega a `ope_ordenes_aplicacion`, `ope_trabajos`, `ope_sesiones`, `ope_actas` ni `com_facturas`: todas cuelgan de un contrato que ya la tiene, y duplicar la columna crea el estado imposible "trabajo de la campaña A dentro de un contrato de la campaña B". Las lecturas por campaña de esas tablas viajan por el contrato (`join`), que es la única fuente.

La referencia es siempre **FK real + entero plano**, nunca `belongsTo` cruzando módulos (ADR 0003 regla 3, mismo patrón que `sec_user.persona_id`).

### 2. `campaña` se escribe `campania` en código; `campana` sigue siendo la campana de notificaciones

Ya hay colisión y ya confunde: en `CascaraPanel` conviven la constante `ALERTAS_CAMPANA` (las alertas de la **campana**) y la prop `campana` (el chip de la **campaña**), a una letra de distancia y significando cosas distintas. Con una tabla `cpn_campanias` real eso se vuelve una trampa permanente.

- `campania` / `Campania` / `campania_id` → el ciclo productivo. Siempre con `i`.
- `campana` → el ícono de notificaciones, y nada más.
- La prop del chrome del panel pasa de `campana` a `campaniaActiva`, y la constante de alertas de `ALERTAS_CAMPANA` a `ALERTAS_NOTIFICACION`.

Es renombrado mecánico y barato hoy; dentro de tres módulos, no.

### 3. El equipo de trabajo es una entidad con vigencia, y agrupa gente **y** equipamiento

Tres tablas en `Personal` (`per_`), que ya es el dueño de `per_personas` y `per_bases` (ADR 0011, extensión del 26/8/2026):

- `per_equipos_trabajo` — `campania_id`, `codigo` (`E1`), `nombre`, `base_id`, `estado`.
- `per_equipo_integrantes` — `equipo_trabajo_id`, `persona_id`, `rol_equipo` (`piloto` / `auxiliar`), `desde`, `hasta` (nullable = vigente).
- `per_equipo_recursos` — `equipo_trabajo_id`, `recurso_tipo` (`dron` / `vehiculo` / `generador`), `recurso_id`, `desde`, `hasta`.

**Por qué con vigencia y no con dos columnas fijas.** Un gasto de marzo tiene que quedar atribuido a quienes integraban el equipo *en marzo*, no a la formación de hoy. Si el auxiliar cambia en abril y el equipo se edita en su lugar, todo el gasto histórico se reatribuye solo y en silencio — que es la misma clase de error que la invariante 2 prohíbe para los registros validados.

**Por qué el equipamiento va en la misma entidad.** Es lo que cierra el problema del combustible: *"como no sabemos en qué trabajo se cargan los gastos, ya el equipo está asociado a equipos de inventario como ser vehículos, así sabemos qué vehículo solicitó nuevo combustible, o en los generadores"*. Sin el equipamiento asignado, imputar al equipo dice **quién** gastó pero no **en qué**; con él, el combustible imputado al equipo se resuelve hasta la unidad concreta.

`recurso_tipo` + `recurso_id` es polimórfico a propósito, con el mismo molde que la `equipos` "vista unificada de drones, vehículos y generadores" de la especificación §4.5 (`tipo` + `referencia_id`). No lleva FK de base porque el destino depende del tipo; la integridad la sostiene el caso de uso al asignar, y un test la cubre. Es la única excepción a "FK real" de este ADR, y es deliberada.

**`per_equipos_trabajo`, no `per_equipos`.** La especificación §4.5 ya reserva la palabra `equipos` para la vista unificada de maquinaria. El negocio dice "equipo de trabajo" para la cuadrilla, así que el dominio conserva esa palabra (convención de `CLAUDE.md`: el vocabulario del negocio no se traduce) y el nombre físico la desambigua.

**El personal es móvil, y el modelo no puede pelearse con eso.** Corrección del 7/9/2026, del propio dueño: *"ese personal puede realizar diferentes trabajos, ya sea en propiedades diferentes, campañas diferentes, equipos diferentes, lotes ajenos y todo, porque en caso de que no hubiera personal se acoplará el que se tiene disponible"*. La pertenencia a un equipo **no es exclusiva**: una persona puede integrar varios equipos, y en la misma fecha, porque la operación real presta gente entre cuadrillas cuando falta.

Por eso `per_equipo_integrantes` **no lleva un bloqueo duro de solapamiento**. Se avisa —dos equipos activos para la misma persona en la misma fecha es información útil al armar la cuadrilla— pero no se rechaza: un sistema que impide registrar lo que en el campo ya pasó obliga a mentirle, y a partir de ahí ningún dato sirve.

**La sesión de vuelo no cambia igual.** `ope_sesiones` sigue con `piloto_id` y `auxiliar_id`; no se le agrega `equipo_trabajo_id`. Tocarla significa tocar el motor de sync y el contrato de la app de campo, y **no hace falta**: nada de lo que se imputa depende de deducir el equipo de una sesión. El gasto, el combustible y la estadía llevan `equipo_trabajo_id` **explícito**, escrito por quien los registra.

Lo que sí cae es el argumento de "el equipo de la sesión es deducible": con pertenencia múltiple deja de serlo sin ambigüedad. Queda dicho para que nadie construya una lectura encima de esa deducción creyéndola exacta. `equiposDePersonaDelMes()` del tablero sigue siendo válida porque responde otra pregunta —"con quién volé"—, no "de qué equipo formalmente dependo".

### 4. El cultivo entra como dimensión del lote **en una campaña**, no como atributo del lote

`com_cultivos` (catálogo) y `com_lote_campania` (`lote_id`, `campania_id`, `cultivo_id`, `hectareas_sembradas`, `fecha_siembra`, `fecha_cosecha_estimada`), con `UNIQUE (lote_id, campania_id)` entre filas activas.

Un lote no "es" de soya: se siembra de soya *esta* campaña y de maíz la siguiente. Ponerlo como columna de `com_lotes` obliga a pisar el dato cada campaña y borra la historia — la misma razón por la que el equipo lleva vigencia.

**Un cultivo por lote por campaña.** El caso real de dos ciclos en el mismo año agronómico (soya de verano, maíz de invierno) se modela como **dos campañas**, que es como el negocio ya lo nombra ("campaña de verano 2025-2026"), no como dos cultivos dentro de una. Así la hectárea nunca se cuenta dos veces en un mismo cierre.

### 5. Sin ventanas horarias cargadas significa "todo el día"

`com_contrato_ventanas` deja de ser obligatoria: se cae la guarda `ActivacionContratoNoDisponible::porFaltaDeVentanas()` y la regla `required|min:1` del request. **Cero ventanas = aplica a cualquier hora**, y así lo dice la pantalla.

Se descartó agregar un booleano `ventana_todo_el_dia`: convive con las filas de ventanas y hace representable un estado contradictorio (booleano en `true` *y* dos ventanas cargadas), que ningún `CHECK` puede impedir porque cruza dos tablas. La ausencia de filas no puede contradecirse a sí misma.

## Consecuencias

**A favor**

- El cierre de campaña pasa a ser posible: gasto, combustible, avance y equipos tienen todos el mismo corte temporal.
- El combustible deja de ser un `string` sin destino y se resuelve hasta la unidad que lo consumió.
- El chip del header vuelve, con dato real detrás.
- La pregunta "¿quién y con qué estaba el equipo 1 el 14 de marzo?" se responde con una consulta, no con arqueología sobre sesiones.

**En contra, y asumido**

- Una migración de datos obligatoria: nace la campaña `2025-2026` y todo lo existente se le asigna. No hay estado intermedio válido con `campania_id` nulo en las tablas que la llevan.
- El polimorfismo de `per_equipo_recursos` no tiene FK: es integridad sostenida por código y test, no por la base. Es la excepción, no el patrón.
- `man_generadores` no existe y hay que crearla para poder asignar un generador a un equipo. Entra con la HU de equipos, no como HU propia: es una tabla de catálogo de tres columnas cuyo único consumidor hoy es la asignación.

## Alternativas descartadas

**Campaña dentro de `Comercial`, colgando del contrato.** Es de donde nace la idea, pero dejaría a `Finanzas` pidiéndole a `Comercial` un concepto que no es comercial: el gasto de combustible de una cuadrilla se imputa a la campaña sin pasar por ningún contrato. Un eje que atraviesa cuatro módulos no puede vivir dentro de uno de ellos.

**Campaña en `Compartido` (`plt_`).** Rechazada por la misma razón que `bases` en su momento (ADR 0011, extensión del 26/8/2026): `Compartido` es infraestructura de plataforma (bitácora, modelo base), y la campaña es un concepto de negocio con estados, guardas y ciclo de vida propios.

**Campaña como catálogo sin campaña activa.** Menos trabajo, pero deja al usuario eligiendo campaña en cada pantalla y cada filtro, y garantiza que tarde o temprano alguien cargue un gasto en la campaña equivocada. El negocio ya piensa en "la gestión" como contexto ambiente, igual que el rol activo.

**Equipo derivado de las sesiones, sin tabla.** Es lo que hay hoy y es lo que falla: no se le puede imputar un gasto a una derivación, no tiene equipamiento, y no existe antes de la primera sesión volada — justo cuando se arma el equipo.

**Modelar contratos de producción en kilos**, como el sistema de referencia (`synagroweb.com/manual/contrato-de-produccion/`). Descartado: Agrocom vende servicio de aplicación, no compra grano. De ese manual se toma **la forma del informe** (selectores obligatorios de cliente y cultivo, pantalla de filtros con chips, pestañas "Por cultivo" / "Por cliente", barra de avance por tramos de color, totalizador de lo que falta), con hectáreas donde ellos ponen kilos.

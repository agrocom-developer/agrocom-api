# ADR 0015 — La campaña del cliente como eje transversal, y el equipo de trabajo como unidad de imputación

**Estado:** Aceptada · **Fecha:** 7/9/2026 · **Corregida:** 8/9/2026 (punto 1 rehecho: la campaña es del **cliente**, no de Agrocom — ver "Corrección del 8/9/2026" al final) · **Origen:** ajustes de negocio del dueño (mensajes del 7 y 8/9/2026).

## Contexto

El sistema se construyó sobre un eje implícito: el **contrato**. Todo cuelga de ahí — órdenes, trabajos, actas, facturas — y las magnitudes de negocio se acumulan sin ningún corte temporal explícito. Eso funcionó mientras solo existía un ciclo productivo en la base, pero rompe en tres lugares concretos:

1. **No hay con qué cerrar un período.** `ObtenerAvanceComercial` (HU-32) se escribió con el enunciado "avance por cliente, contrato **y campaña**" y entregó las dos primeras: la tercera no tenía tabla detrás. Lo mismo `fin_gastos` y `fin_combustibles`, cuyos docblocks dicen literalmente "para que la campaña tenga costo real" y "para imputarlo a la campaña" — imputan a una campaña que no existe como entidad.
2. **El chip del header quedó apagado.** La tarea 67 retiró el chip "Campaña 2026-B" del `CascaraPanel` con el comentario `el dominio no tiene el concepto de campaña en ninguna tabla`. La maqueta original lo tenía porque el negocio lo tiene; el modelo no.
3. **Hay gasto que no se puede imputar a nada.** El combustible de la camioneta y del generador no pertenece a un trabajo (no se sabe a qué lote cargarlo) ni alcanza con la base (varias cuadrillas comparten base). Hoy `fin_combustibles.destino` es un `string` con `CHECK (destino IN ('generador','vehiculo'))` y **no dice cuál** de los dos: no hay FK, porque cuando se escribió no existía `man_vehiculos` ni existe todavía una tabla de generadores.

En paralelo, el negocio nombró una unidad que el modelo no tenía: el **equipo de trabajo** — el piloto y su auxiliar, *"no dónde están trabajando"*. Hoy esa idea solo existe derivada: `LecturaPanelOperaciones::equiposDePersonaDelMes()` la reconstruye desde las sesiones ya voladas (quién compartió sesión con quién, sobre qué dron). Sirve para mostrar en el tablero, pero no sirve para **imputar**: no se le puede cargar un gasto a una derivación de sesiones pasadas, y no responde "¿quién integraba el equipo 1 el 14 de marzo?" ni "¿qué camioneta tenía asignada?".

## Decisión

### 1. La campaña es del **cliente**, y es un eje transversal de primera clase (corregido el 8/9/2026)

Módulo nuevo `app/Dominios/Campania/`, prefijo de tabla `cpn_` (extiende la tabla del ADR 0011 — **no `cmp_`**, que ese ADR ya había descartado en su punto 11 por parecerse a `com_` a simple vista). Tabla `cpn_campanias`: **`cliente_id`**, `codigo` (`2025-2026`), `nombre`, `fecha_inicio`, `fecha_fin`, `estado`.

**La campaña la corre el cliente, no Agrocom.** El dueño lo dijo así el 8/9/2026: *"cada cliente maneja sus campañas, nosotros solo vamos a fumigar (…) nosotros no hacemos campañas, solo fumigamos cuando el cliente está en campaña"*. Agrocom presta un servicio dentro de la campaña ajena; no tiene una campaña propia a la cual imputar todo.

**Único por cliente, no global:** índice único parcial sobre `(cliente_id, codigo)` entre filas activas. Dos clientes pueden tener cada uno su `2025-2026` y son campañas distintas.

**Máquina de estados** (invariante 7 de `CLAUDE.md`, servicio de dominio propio): `planificada → abierta → cerrada`. Sin vuelta atrás desde `cerrada` — reabrir una campaña cerrada es exactamente el agujero por el que se cuelan gastos e imputaciones retroactivas que descuadran un cierre ya presentado.

**Sin guarda de solapamiento, ni entre clientes ni dentro de uno.** Es la consecuencia directa de que la campaña sea ajena: hay tantas abiertas como clientes en campaña, y sus rangos se pisan por definición — uno cosechando mientras otro siembra. Dentro de un mismo cliente también se permiten varias abiertas (soya de verano y maíz de invierno): el dueño lo dejó abierto — *"sería varias campañas en un año (…) pero hasta que me confirmen, que sea flexible"*— y un bloqueo duro es lo único que después no se puede aflojar sin migrar datos.

**Guarda de imputación:** ninguna escritura nueva puede imputarse a una campaña `cerrada`. Lo verifica el módulo dueño de cada tabla al crear, no un trigger.

**No hay campaña activa por sesión.** Se descartó el 8/9/2026, junto con su middleware y el chip del header: con decenas de campañas abiertas a la vez, "la campaña de la sesión" no significa nada — el operador no trabaja en una campaña, trabaja en varias por día. La campaña se elige **dentro del cliente o del contrato**, y en los informes es un filtro más. El chip del header queda como estaba tras la tarea 67: apagado.

**Dónde va `campania_id`.** Solo donde alguien la **elige explícitamente**:

- `com_contratos` — **obligatorio**. El contrato es con un cliente y para una campaña suya; guarda de consistencia: `campania.cliente_id` debe ser el `cliente_id` del contrato. Es la que sostiene todas las lecturas por campaña de operaciones, porque órdenes, trabajos, sesiones, actas y facturas cuelgan del contrato.
- `com_lote_campania` — **obligatorio**, por definición de la tabla (qué se sembró en ese lote en esa campaña).
- `fin_gastos` y `fin_combustibles` — **nullable**, y con otro significado: no es el período contable, es **a qué campaña de cliente se le repercute** ese gasto. Ver el punto 6.

**No** lleva `campania_id`: `ope_ordenes_aplicacion`, `ope_trabajos`, `ope_sesiones`, `ope_actas`, `com_facturas` (cuelgan de un contrato que ya la tiene, y duplicarla hace representable "trabajo de la campaña A en un contrato de la campaña B"); `per_equipos_trabajo` (el equipo es de Agrocom y trabaja para varias campañas — ver punto 3); ni `ope_estadias_hacienda` (la estadía es de un equipo en una hacienda: el cliente sale del campo y la fecha ubica la campaña, y nadie la elige a mano porque la registra el piloto desde la app).

La referencia es siempre **FK real + entero plano**, nunca `belongsTo` cruzando módulos (ADR 0003 regla 3, mismo patrón que `sec_user.persona_id`).

### 2. `campaña` se escribe `campania` en código; `campana` sigue siendo la campana de notificaciones

Ya hay colisión y ya confunde: en `CascaraPanel` conviven la constante `ALERTAS_CAMPANA` (las alertas de la **campana**) y la prop `campana` (el chip de la **campaña**), a una letra de distancia y significando cosas distintas. Con una tabla `cpn_campanias` real eso se vuelve una trampa permanente.

- `campania` / `Campania` / `campania_id` → el ciclo productivo. Siempre con `i`.
- `campana` → el ícono de notificaciones, y nada más.
- La prop del chrome del panel pasa de `campana` a `campaniaActiva`, y la constante de alertas de `ALERTAS_CAMPANA` a `ALERTAS_NOTIFICACION`.

Es renombrado mecánico y barato hoy; dentro de tres módulos, no.

### 3. El equipo de trabajo es una entidad con vigencia, y agrupa gente **y** equipamiento

Tres tablas en `Personal` (`per_`), que ya es el dueño de `per_personas` y `per_bases` (ADR 0011, extensión del 26/8/2026):

- `per_equipos_trabajo` — `codigo` (`E1`), `nombre`, `base_id`, `estado`, `desde`, `hasta`. **Sin `campania_id`** (corregido el 8/9/2026): el equipo es de Agrocom y en la misma semana trabaja para las campañas de varios clientes. Atarlo a una campaña ajena obligaría a duplicar la cuadrilla por cliente, y el gasto de la camioneta no sabría a cuál de esas copias imputarse.
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

**Un cultivo por lote por campaña.** El caso real de dos ciclos en el mismo año agronómico (soya de verano, maíz de invierno) se modela como **dos campañas del mismo cliente**, abiertas a la vez si hace falta (punto 1), que es como el negocio ya lo nombra ("campaña de verano 2025-2026"), no como dos cultivos dentro de una. Así la hectárea nunca se cuenta dos veces en un mismo cierre.

### 5. Sin ventanas horarias cargadas significa "todo el día"

`com_contrato_ventanas` deja de ser obligatoria: se cae la guarda `ActivacionContratoNoDisponible::porFaltaDeVentanas()` y la regla `required|min:1` del request. **Cero ventanas = aplica a cualquier hora**, y así lo dice la pantalla.

Se descartó agregar un booleano `ventana_todo_el_dia`: convive con las filas de ventanas y hace representable un estado contradictorio (booleano en `true` *y* dos ventanas cargadas), que ningún `CHECK` puede impedir porque cruza dos tablas. La ausencia de filas no puede contradecirse a sí misma.

### 6. El gasto se registra contra el equipo, y se repercute a la campaña del cliente donde se consumió (8/9/2026)

`fin_gastos` y `fin_combustibles` se imputan **al equipo de trabajo** — eso no cambió, es el punto 3 y es lo que resuelve el gasto que no pertenece a ningún trabajo. Lo que se agrega es el otro lado: a quién se le **cobra**.

El dueño lo puso en términos de negocio: *"al equipo se le asigna el gasto y se le factura a todos los clientes donde trabaje; si se carga 2 veces gasolina pero en la última cargada sobra y se va a otro cliente, al cliente se le cobra como nueva cargada a pesar de que sea sobras — es un negocio, hay que ser rentable"*.

De ahí salen dos reglas:

- **La carga es la unidad, y no se prorratea.** Una carga de combustible se repercute entera a una sola campaña: la del cliente donde se cargó. La sobra que después se consume en otro cliente se le cobra a ese otro como carga nueva. No hay reparto proporcional entre clientes, ni cálculo de remanente — modelar eso sería inventar una contabilidad que el negocio no lleva, y encima una que le haría perder plata.
- **`campania_id` es nullable en las dos tablas.** El gasto interno (mantenimiento de la camioneta en el taller, un repuesto de galpón) no se le repercute a nadie y queda sin campaña. Obligarlo forzaría a elegir un cliente cualquiera, que es exactamente cómo se ensucia un dato.

**Consecuencia sobre el cierre de Agrocom:** como el gasto ya no cuelga de una campaña propia, el corte para mirar los costos de la empresa es **la fecha**, no la campaña. La campaña sirve para el cierre *del cliente* (qué se le aplicó, qué se le cobró) y para el informe de avance por cultivo; el costo interno se lee por período y por equipo.

## Consecuencias

**A favor**

- El cierre de la campaña **del cliente** pasa a ser posible: contratos, avance por cultivo y lo que se le repercutió tienen todos el mismo corte.
- El combustible deja de ser un `string` sin destino y se resuelve hasta la unidad que lo consumió.
- El gasto de cuadrilla deja de necesitar un cliente para poder registrarse: se carga contra el equipo, y la repercusión al cliente es una decisión aparte y opcional.
- La pregunta "¿quién y con qué estaba el equipo 1 el 14 de marzo?" se responde con una consulta, no con arqueología sobre sesiones.

**En contra, y asumido**

- Una migración de datos obligatoria y **por cliente**: nace una campaña `2025-2026` para cada cliente que ya tiene contratos, y cada contrato se asigna a la de su propio cliente. Recién después `com_contratos.campania_id` pasa a `NOT NULL`. En `fin_gastos` y `fin_combustibles` no hay migración: la columna nace nullable y vacía.
- **Agrocom pierde el corte temporal propio** que el punto 1 original le daba. Mirar el costo de la empresa por período pasa a ser una lectura por fecha y por equipo. Es el precio de que la campaña sea del cliente, y es lo que el negocio dice que es.
- El polimorfismo de `per_equipo_recursos` no tiene FK: es integridad sostenida por código y test, no por la base. Es la excepción, no el patrón.
- `man_generadores` no existe y hay que crearla para poder asignar un generador a un equipo. Entra con la HU de equipos, no como HU propia: es una tabla de catálogo de tres columnas cuyo único consumidor hoy es la asignación.

## Alternativas descartadas

**Campaña dentro de `Comercial`, colgando del contrato.** Es de donde nace la idea, pero dejaría a `Finanzas` pidiéndole a `Comercial` un concepto que no es comercial: el gasto de combustible de una cuadrilla se imputa a la campaña sin pasar por ningún contrato. Un eje que atraviesa cuatro módulos no puede vivir dentro de uno de ellos.

**Campaña en `Compartido` (`plt_`).** Rechazada por la misma razón que `bases` en su momento (ADR 0011, extensión del 26/8/2026): `Compartido` es infraestructura de plataforma (bitácora, modelo base), y la campaña es un concepto de negocio con estados, guardas y ciclo de vida propios.

**Campaña activa por sesión, espejo del rol activo.** Fue la decisión original del 7/9 y se dio vuelta el 8/9: con la campaña en manos del cliente hay decenas abiertas al mismo tiempo, y un contexto ambiente que hay que cambiar varias veces por día no es un contexto, es fricción. El argumento a favor —que evita cargar un gasto en la campaña equivocada— se cayó solo: el gasto ya no se carga contra una campaña, se carga contra el equipo.

**Campaña propia de Agrocom conviviendo con las de los clientes.** Daría el corte temporal interno que ahora se pierde, pero son dos calendarios que hay que mantener sincronizados a mano, y el primer cierre en que no coincidan deja los dos números sin poder explicarse. Para mirar el costo interno alcanza con la fecha.

**Equipo derivado de las sesiones, sin tabla.** Es lo que hay hoy y es lo que falla: no se le puede imputar un gasto a una derivación, no tiene equipamiento, y no existe antes de la primera sesión volada — justo cuando se arma el equipo.

**Modelar contratos de producción en kilos**, como el sistema de referencia (`synagroweb.com/manual/contrato-de-produccion/`). Descartado: Agrocom vende servicio de aplicación, no compra grano. De ese manual se toma **la forma del informe** (selectores obligatorios de cliente y cultivo, pantalla de filtros con chips, pestañas "Por cultivo" / "Por cliente", barra de avance por tramos de color, totalizador de lo que falta), con hectáreas donde ellos ponen kilos.

## Corrección del 8/9/2026 — de quién es la campaña

El ADR se escribió el 7/9 con la campaña como **eje de Agrocom**: una `2025-2026` de la empresa, activa por sesión como el rol activo, con todo lo imputable colgando de ella. Al día siguiente, con la tarea 69 ya implementándolo, el dueño corrigió el supuesto de base: *"cada cliente maneja sus campañas, nosotros solo vamos a fumigar"*.

No es un matiz. Cambia tres cosas:

1. `cpn_campanias` gana `cliente_id` y su unicidad pasa a ser por cliente.
2. Se cae la campaña activa por sesión, su middleware y el chip del header.
3. `campania_id` sale de `per_equipos_trabajo` y pasa a ser nullable en `fin_gastos` / `fin_combustibles`, donde ahora significa "a quién se le repercute" y no "de qué período es".

Lo que **no** cambió, y por eso el resto del ADR sigue en pie: el equipo de trabajo con vigencia como unidad de imputación (punto 3), el cultivo por lote y campaña (punto 4), la escritura `campania` vs. `campana` (punto 2) y las ventanas horarias opcionales (punto 5).

La tarea 69 se cortó en la etapa 2 al llegar la corrección. Lo hecho hasta ahí —el módulo, la máquina de estados, el ABM y el renombrado de las 82 vistas— queda en `feature/campania-cliente` (la rama se renombró: ya no hay campaña activa) y se retoma sobre este modelo; lo que se descarta es la campaña activa que ya estaba empezada.

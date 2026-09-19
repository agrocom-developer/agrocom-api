# Plan de sprints, historias de usuario y betas — Ruta crítica (Fases 1–2)

**Agrocom SRL · Sistema de Gestión de Operaciones de Fumigación · Documento oficial vigente**

*Actualizado desde el histórico `plan_sprints_hu_betas.md` (hoy solo en el historial de git): las referencias de branching pasan de trunk-based a GitFlow simplificado (ADR 0006). El resto del plan no cambia — ya estaba pensado para un desarrollador con agentes de IA a dedicación completa.*

Marco: **6 sprints de 2 semanas** (12 semanas), un desarrollador con agentes de IA a dedicación completa. Capacidad estimada por sprint: **8–9 días ideales** (los días ideales ya descuentan interrupciones). Las estimaciones asumen las decisiones vigentes: PostgreSQL 16 (ADR 0001), AdminLTE + Blade/Livewire con Atomic Design (ADR 0002), arquitectura modular Clean por feature (ADR 0003), modelo `sec_*` ajustado — permiso abstracto + multi-rol con un único login (ADR 0004), Flutter con BLoC feature-first (ADR 0005), GitFlow simplificado (ADR 0006), soft delete + bitácora de auditoría transversal (ADR 0007).

Convención: **HU** = historia de usuario (valor visible); **TE** = tarea técnica habilitante (sin ella las HU no existen). Cada HU lista sus criterios de aceptación (CA) esenciales — el detalle fino vive en `docs/especificacion/especificacion_funcional_tecnica.md`, que es el contrato.

---

## Calendario de betas

| Hito | Cuándo | Qué se presenta y a quién |
|---|---|---|
| **Beta API v0.1** | Fin sprint 1 | Auth multi-rol + catálogo por API (demo técnica, para vos) |
| **Beta interna A — esqueleto** | Fin sprint 2 | Flujo punta a punta con campos mínimos: orden → app sin señal → sesión → cierre → sync → panel. Demo al dueño/socios |
| **Beta App Piloto 0.9** | Fin sprint 3 | App completa del piloto instalada en el RC real. Demo con 1–2 pilotos → su feedback entra al sprint 4 |
| **Beta App Auxiliar 0.9 + Web Admin 0.9** | Fin sprint 4 | Mezcla con checklist en celular real + panel operando el día completo. Demo con auxiliar y jefe de campo |
| **Release Candidate integrada** | Fin sprint 5 | Ciclo completo con actas, PDFs y validación. Demo al agrónomo del cliente (el reporte técnico es SU entregable) |
| **v1.0 ruta crítica** | Fin sprint 6 | Ensayo general en campo superado; producción y respaldos listos |

Mostrar la Beta App Piloto a los pilotos al fin del sprint 3 —y no al final— es deliberado: su feedback de pantalla y flujo llega cuando todavía hay 3 sprints para absorberlo.

---

## Sprint 1 — Fundaciones, seguridad y spike de hardware

*Objetivo: los dos repos viven con GitFlow, el RC ejecuta un APK propio, y la seguridad multi-rol funciona.*

| ID | Historia / tarea | CA esenciales | Est. |
|---|---|---|---|
| TE-01 | Crear `agrocom-api` y `agrocom-field`: esqueletos, rama `develop` desde `master` en ambos repos (ADR 0006), CI (Pest/analizador; flutter test), `CLAUDE.md` con invariantes, entorno staging | Suites en verde en CI; deploy automático a staging desde `master`; primera `feature/*` mergeada a `develop` por PR | 1,0 d |
| TE-02 | **Spike RC Agras**: instalar APK propio, leer screenshot de DJI desde galería, confirmar versión Android/minSdk, permisos | Informe corto con lo que anda y lo que no; decisión app-en-RC vs app-en-celular | 1,5 d |
| TE-03 | Migraciones del núcleo comercial (clientes, contratos, campos, lotes, órdenes) con soft delete y columnas de auditoría (ADR 0007) + seeds de datos reales del contrato | Órdenes consultables por API con filtros; ningún modelo permite `DELETE` físico | 1,5 d |
| HU-01 | Como **encargado**, quiero crear usuarios con uno o más roles y un único login, base y enlace a persona operativa, para que cada quien entre con lo suyo | `sec_*` ajustado (permission abstracto, `sec_user_role`, `persona_id`); el encargado no puede crear usuarios dueño; un usuario nunca tiene dos logins | 2,0 d |

> **HU-01 está a medias, y se venía contando como cerrada.** La auditoría del
> panel en navegador del 2/9/2026 lo dejó a la vista: `UsuariosController` solo
> expone `index` y `/panel/usuarios` no tiene un solo formulario. El modelo de
> datos (`sec_user`, `sec_user_role`, `persona_id`) y el listado están; **el
> alta, la edición y la asignación de roles desde el panel, no**. Hoy un usuario
> nuevo solo se crea por seeder. Se cierra en HU-45 (Sprint 7), estimada en 1,5 d
> de los 2,0 originales.
| HU-02 | Como **usuario del panel**, quiero iniciar sesión eligiendo con qué rol entro (si tengo más de uno) y ver el menú AdminLTE armado según los permisos de ese rol activo, con mi tema de color preferido, y poder cambiar de rol activo sin volver a loguearme | Menú renderizado desde `sec_menu`/`sec_permission` según el **rol activo** (no unión de roles, CLAUDE.md invariante 10); selector de rol al login si el usuario tiene más de uno asignado; cambio de rol activo en caliente, misma sesión; botones ocultos sin permiso; preferencia de tema persistida por usuario, ningún color hardcodeado (ADR 0002) | 1,5 d |
| HU-03 | Como **piloto o auxiliar**, quiero iniciar sesión en la app con token propio del dispositivo, para operar sin volver a loguearme | Token Sanctum por dispositivo, revocable desde el panel; sesión persistente offline | 1,0 d |

**Total: 8,5 d · Entrega: Beta API v0.1**

---

## Sprint 2 — Motor de sync y esqueleto vertical

*Objetivo: la apuesta más riesgosa del proyecto, resuelta y demostrada de punta a punta.*

| ID | Historia / tarea | CA esenciales | Est. |
|---|---|---|---|
| TE-04 | Base local drift + **outbox**: toda escritura local encola con `uuid_cliente`, orden causal y estado (`pendiente/enviado/confirmado/rechazado`) | Escritura local + encolado en una transacción; UI lee solo de drift | 2,0 d |
| TE-05 | `POST /api/sync` idempotente: proceso registro a registro, `ON CONFLICT` por `uuid_cliente`, resolución de referencias por UUID, respuesta por registro | **Test de replay**: el mismo lote aplicado 10 veces, en orden y en desorden → base idéntica | 3,0 d |
| TE-06 | Pull de catálogo con cursor (`órdenes, recetas, productos, lotes, personas`) al abrir la app y al recuperar señal | El piloto sale al lote con órdenes ya en el dispositivo | 1,0 d |
| HU-04 | Como **piloto**, quiero ver las órdenes vigentes de mis lotes sin señal, para saber qué aplicar | Lista y detalle offline; sin orden vigente no se puede abrir trabajo | 1,0 d |
| HU-05 | **Esqueleto vertical**: como piloto abro trabajo y sesión, la cierro con hectáreas, y al volver a cobertura el jefe la ve en el panel | Flujo mínimo completo demostrable con avión-modo en el RC | 2,0 d |

**Total: 9,0 d · Entrega: Beta interna A**

---

## Sprint 3 — App del piloto completa

*Objetivo: todos los flujos del piloto, robustos y probados en el RC.*

| ID | Historia / tarea | CA esenciales | Est. |
|---|---|---|---|
| HU-06 | Como **piloto**, quiero registrar condiciones al iniciar (viento, temp., humedad) y que el sistema me autorice o bloquee, para cubrirme si se aplica al límite | Rangos 30 °C / 17 km/h / 90%; fuera de rango exige observación firmada del agrónomo (`autorizado_con_observacion`) | 1,5 d |
| HU-07 | Como **piloto**, quiero cerrar mi sesión con motivo (completado/relevo/falla/clima/jornada) y que el que entra registre la hectárea acumulada de partida, para que cada uno cobre lo suyo | Ha de sesión = diferencia contra acumulada; trabajo queda `parcial` con pendiente visible; suma ≤ lote + tolerancia | 2,0 d |
| HU-08 | Como **piloto**, quiero registrar incidencias con foto (caldo/ESC/batería/mecánica/clima) para respaldar el reporte | Incidencia offline con evidencia comprimida, ligada a la sesión | 1,0 d |
| HU-09 | Como **piloto**, quiero cerrar el lote adjuntando captura del RC e imagen del campo, para que el trabajo quede completo y demostrable | Sin captura no cierra; validación de suma dispara `observado` si excede tolerancia | 2,0 d |
| TE-07 | Cola de evidencias: compresión <300 KB, hash SHA-256, subida en segundo plano con reintentos | Registros sincronizan primero; evidencia pendiente visible; validación exige evidencia subida | 1,5 d |

**Total: 8,0 d · Entrega: Beta App Piloto 0.9 — demo con pilotos reales**

---

## Sprint 4 — App del auxiliar y panel operativo

*Objetivo: la mezcla trazable tanque por tanque, y el panel con el que el jefe opera su día.*

| ID | Historia / tarea | CA esenciales | Est. |
|---|---|---|---|
| HU-10 | Como **auxiliar**, quiero registrar el caldo que el cliente me entrega (litros, hora, quién lo entregó) y el sobrante que le devuelvo, para demostrar qué recibí y qué apliqué | Litros recibidos por trabajo; consumido por sesión; sobrante al cierre; el total cuadra (recibido = aplicado + sobrante) | 1,0 d |
| HU-11 | ~~Cálculo de cantidades por tanque~~ — **fuera de alcance (CR-01, 1/9/2026)**: la dosificación es del agrónomo del cliente | — | — |
| HU-12 | ~~Checklist secuencial de incorporación~~ — **fuera de alcance (CR-01, 1/9/2026)**: Agrocom no prepara la mezcla | — | — |
| HU-13 | Como **auxiliar**, quiero registrar cada recarga del dron con batería, temperatura y litros cargados, y dejar constancia si el vuelo se retrasó o se rechazó por la calidad del caldo, para deslindar responsabilidad | Alerta local si temperatura > 50 °C; recarga vincula sesión ↔ litros; combustible del generador; motivo y hora del retraso por caldo | 1,5 d |

> **CR-01 cerrada el 1/9/2026 — la mezcla no es de Agrocom.** El caldo lo
> formula y lo prepara el cliente con su propio ingeniero agrónomo; Agrocom
> recibe litros ya hechos y los rocía. Es un deslinde de responsabilidad: quien
> elige producto y dosis responde por el resultado agronómico (efectividad,
> daño al cultivo, germinación). HU-11 y HU-12 quedan fuera de alcance, y HU-10
> y HU-13 pasan a registrar volumen y trazabilidad, nunca composición. Detalle
> en la §7 de la especificación funcional.

| HU-14 | Como **jefe de campo**, quiero una cola de validación de sesiones en el panel, para aprobar lo volado sin revisar todo a mano | Validador ≠ piloto de la sesión (policy a nivel persona); rechazo con motivo genera corrección, nunca edición | 1,5 d |
| HU-15 | Como **encargado**, quiero ver trabajos y avance por lote/aplicación en el panel, para seguir la campaña desde la ciudad | Tablero de trabajos por estado con filtros; detalle con sesiones y evidencias | 1,0 d |

**Total: 9,5 d** *(si aprieta, HU-15 baja al sprint 5 sin tocar la beta)* · **Entrega: Beta App Auxiliar 0.9 + Web Admin 0.9**

---

## Sprint 5 — Validación con consecuencias, actas y reportes

*Objetivo: del dato validado al documento cobrable.*

| ID | Historia / tarea | CA esenciales | Est. |
|---|---|---|---|
| HU-16 | Como **dueño**, quiero que al validar una sesión se generen los devengos del piloto y auxiliar automáticamente, para que la planilla futura salga sola | Evento `SesionValidada` → devengos idempotentes (`UNIQUE sesion+persona`); recálculo cuadra exacto | 1,0 d |
| HU-17 | Como **piloto/jefe**, quiero generar el acta por lote apenas conformado y capturar la firma del agrónomo en pantalla, para cobrar sin pelea | Acta PDF con hectáreas conformadas; firma en pantalla o foto del acta física como evidencia | 2,0 d |
| HU-18 | Como **agrónomo (cliente)**, quiero recibir el reporte técnico por lote (imagen del campo, horas, condiciones, mezcla ejecutada, sesiones), para verificar la aplicación | PDF automático al conformar; incluye dosis ordenada vs. incorporada y superficie no aplicada con motivo | 2,0 d |
| HU-19 | Como **encargado**, quiero recibir solo alertas por excepción (sin evidencia, suma excedida, sin orden, batería caliente, desvío de mezcla ±5%, lote parado 48 h), para no revisar todo | Bandeja de alertas en panel con estado atendida/pendiente | 1,5 d |
| HU-20 | Como **dueño**, quiero autorizar las versiones del APK desde el panel, para que ningún RC se actualice sin mi visto bueno | `GET /api/version`; app bloquea bajo versión mínima; distribución autohospedada | 1,0 d |
| TE-08 | Endurecimiento del sync con datos de las betas: casos de borde, relojes, reintentos | Sin registros `rechazado` sin causa conocida en staging | 1,0 d |

**Total: 8,5 d · Entrega: Release Candidate integrada — demo al agrónomo**

---

## Sprint 6 — Ensayo general y salida a producción

*Objetivo: que los operarios completen el ciclo sin ayuda, y que producción no dependa de la suerte.*

| ID | Historia / tarea | CA esenciales | Est. |
|---|---|---|---|
| TE-09 | **Ensayo general en campo**: 1 dron, 1 lote, piloto y auxiliar reales, día completo con el sistema | Los operarios completan el ciclo sin asistencia; lista priorizada de fricciones | 2,0 d |
| HU-21 | Correcciones de adopción del ensayo (botones, textos, orden de pantallas, lo que salga) | Las 5 fricciones más graves resueltas y reverificadas con los mismos operarios | 3,0 d |
| TE-10 | Producción: VPS final, HTTPS, respaldo diario a bucket + **restauración probada**, monitoreo Sentry | Simulacro de restauración documentado; alertas de error llegando | 1,0 d |
| TE-11 | Carga de datos maestros reales: productos con formulación, lotes con geometría y restricciones, personal, tarifas | Catálogos completos ANTES de la primera aplicación | 1,5 d |
| TE-12 | Cierre: auditoría de permisos por rol contra la matriz de la especificación §3, seeds de producción, tag v1.0 en `master` | Cada celda de la matriz de permisos tiene un test | 1,0 d |

**Total: 8,5 d · Entrega: v1.0 ruta crítica en producción**

---

## Después de la v1.0 (con la campaña andando)

Hasta el 2/9/2026 estas fases vivían en un solo párrafo, sin historias, sin
criterios de aceptación y sin estimación. Eso tuvo dos consecuencias: el ciclo
automatizado no podía tomarlas —una tarea solo entra si su criterio es un
comando que devuelve 0 o 1— y, sobre todo, **el avance del proyecto se venía
midiendo contra un plan que cubría poco más de la mitad del sistema**. El panel
tiene 33 ítems de menú y solo 8 llevan a una pantalla; los otros 25 no estaban
escritos en ninguna parte como trabajo pendiente.

Quedan desglosadas abajo. El orden es el que ya estaba decidido —por urgencia
de plata— con una sola corrección: el Sprint 7 pasa a ser el de catálogos,
porque **TE-11 (carga de datos maestros reales, Sprint 6) no se puede hacer sin
pantallas donde cargarlos**. Hoy esa carga solo es posible por seeder o SQL a
mano, que no es algo que se le pida a un encargado.

Durante campaña la capacidad real por sprint baja (soporte + campo): planificar
5–6 días ideales, no 8–9.

---

## Sprint 7 — Catálogos: el panel se vuelve operable

*Objetivo: que un encargado pueda cargar y mantener los datos maestros sin tocar la base. Es el prerrequisito real de TE-11.*

Todas estas historias son ABM sobre tablas **que ya existen, migradas y auditadas** desde TE-03 y los sprints 2–5. No hay modelo de datos nuevo: es la capa de pantalla que faltó.

| ID | Historia / tarea | CA esenciales | Est. |
|---|---|---|---|
| HU-22 | Como **encargado**, quiero dar de alta y mantener clientes con sus contactos, para no depender de que alguien toque la base | ABM sobre `com_clientes` y `com_cliente_contactos`; soft delete; test de bitácora en alta, edición y baja | 1,0 d |
| HU-23 | Como **encargado**, quiero administrar contratos con sus ventanas de aplicación y tarifa, para que las órdenes cuelguen de un contrato vigente | ABM sobre `com_contratos` y `com_contrato_ventanas`; no permite ventana fuera del rango del contrato; tarifa en `DECIMAL` | 1,5 d |
| HU-24 | Como **encargado**, quiero administrar campos y sus lotes con superficie y geometría, para que el piloto vea el lote correcto | ABM sobre `com_campos` y `com_lotes`; superficie en `DECIMAL`; geometría opcional validada | 1,5 d |
| HU-25 | Como **encargado**, quiero crear y seguir las órdenes de aplicación desde el panel, para que el piloto las reciba en el pull de catálogo | ABM sobre `ope_ordenes_aplicacion` con su máquina de estados; una orden nueva aparece en `GET /api/sync/catalogo` | 1,5 d |
| HU-26 | Como **encargado**, quiero administrar personas y bases, con su rol operativo y tarifa, para que los devengos salgan con el dato correcto | ABM sobre `per_personas` y `per_bases`; tarifa en `DECIMAL`; test de que cambiar la tarifa no altera devengos ya generados | 1,5 d |
| HU-27 | Como **encargado**, quiero administrar la flota de drones con su modelo y volumen de carga, para planificar recargas | ABM sobre `ope_drones`; volumen real por modelo (30/50/60 L) | 1,0 d |
| HU-45 | Como **encargado**, quiero dar de alta usuarios y asignarles roles desde el panel, para no depender de un seeder — **cierra la parte de HU-01 que quedó sin hacer** | Alta, edición y baja sobre `sec_user` + `sec_user_role`; el encargado no puede crear un usuario dueño; un usuario nunca tiene dos logins; cambiar roles no invalida la sesión activa | 1,5 d |

**Total: 9,5 d · 7 pantallas nuevas + gestión de usuarios operable · Entrega: el panel deja de necesitar SQL a mano**

---

## Sprint 8 — Fase 4a: la gente cobra a fin de mes

*Objetivo: que la planilla salga del sistema y no de una hoja de cálculo.*

| ID | Historia / tarea | CA esenciales | Est. |
|---|---|---|---|
| HU-28 | Como **piloto o auxiliar**, quiero ver mis devengos por período, para saber qué voy a cobrar antes de que cierre el mes | Pantalla sobre `fin_devengos_personal` filtrada por persona y período; un operario ve solo lo suyo (test de acceso cruzado → 404) | 1,0 d |
| HU-29 | Como **encargado**, quiero registrar anticipos validando el tope, para no adelantar más de lo devengado | Tope 3.000 Bs/mes y 70 % del devengado; el rechazo dice cuánto es el máximo disponible | 1,5 d |
| HU-30 | Como **dueño**, quiero generar la planilla del período desde los devengos y aprobarla, para pagar con un respaldo que cuadre | Planilla en borrador desde devengos + anticipos; solo el dueño aprueba; recibo individual en PDF; el total cuadra exacto contra los devengos de origen | 3,0 d |

**Total: 5,5 d · 2 pantallas · Entrega: primer pago con planilla del sistema**

---

## Sprint 9 — Fase 4b: cobrarle al cliente

*Objetivo: cerrar el circuito del dinero que entra, no solo el que sale.*

| ID | Historia / tarea | CA esenciales | Est. |
|---|---|---|---|
| HU-31 | Como **encargado**, quiero emitir la factura de un trabajo desde su acta conformada, para cobrar sobre hectáreas ya firmadas | Factura solo desde acta en estado `firmada`; monto = hectáreas conformadas × tarifa del contrato; no permite facturar dos veces el mismo trabajo | 2,0 d |
| HU-32 | Como **dueño**, quiero un reporte comercial de avance por cliente, contrato y campaña, para saber cuánto queda por aplicar y por cobrar | Hectáreas contratadas vs. aplicadas vs. facturadas por contrato; exportable | 1,5 d |

**Total: 3,5 d · 2 pantallas**

---

## Sprint 10 — Fase 3: gastos y rendiciones

*Objetivo: que el costo real de una campaña sea consultable, no reconstruible.*

| ID | Historia / tarea | CA esenciales | Est. |
|---|---|---|---|
| HU-33 | Como **encargado**, quiero cargar gastos con su categoría y comprobante, para que la campaña tenga costo real | ABM de gastos con evidencia adjunta; categorías de catálogo; imputable a trabajo, base o general | 1,5 d |
| HU-34 | Como **jefe de campo**, quiero rendir lo que gasté en campo y que el encargado lo apruebe, para reponer el fondo | Rendición con detalle e ítems; estados `abierta → presentada → aprobada`; el aprobador nunca es quien rinde | 2,0 d |
| HU-35 | Como **encargado**, quiero registrar el combustible del generador y de los vehículos, para imputarlo a la campaña | Carga por base y fecha; litros y monto en `DECIMAL`; consultable por período | 1,0 d |

**Total: 4,5 d · 3 pantallas**

---

## Sprint 11 — Fases 5–6: inventario y mantenimiento

*Objetivo: que el equipo no se rompa por sorpresa y que los repuestos no falten en plena campaña.*

Es el bloque más caro: módulos nuevos (`man_*`, `inv_*`) con su propio modelo de datos, no pantallas sobre tablas existentes.

| ID | Historia / tarea | CA esenciales | Est. |
|---|---|---|---|
| HU-36 | Como **encargado**, quiero llevar stock de repuestos por base con alerta de mínimo, para reponer antes de quedarme sin | Movimientos de compra, salida, ajuste y traslado; el stock nunca queda negativo; alerta al cruzar el mínimo | 2,5 d |
| HU-37 | Como **encargado**, quiero abrir órdenes de mantenimiento y cerrarlas consumiendo repuestos, para que el costo quede imputado | Cierre descuenta stock y genera el gasto asociado en una transacción; no cierra sin repuestos disponibles | 2,5 d |
| HU-38 | Como **encargado**, quiero planes de mantenimiento preventivo por horas de vuelo, para que el sistema me avise antes de la falla | Plan por modelo de dron; alerta cuando el acumulado de horas cruza el umbral | 2,0 d |
| HU-39 | Como **encargado**, quiero seguir las baterías con sus ciclos y estado, para retirarlas antes de que fallen en vuelo | ABM con ciclos acumulados; alerta por ciclos o por temperatura registrada en recargas | 1,5 d |
| HU-40 | Como **encargado**, quiero administrar los vehículos con su asignación a base | ABM con asignación y estado | 1,0 d |

**Total: 9,5 d · 6 pantallas**

---

## Sprint 12 — Fases 7–8: lo que ve el cliente y lo que falta ver

*Objetivo: cerrar la visibilidad, adentro y afuera.*

| ID | Historia / tarea | CA esenciales | Est. |
|---|---|---|---|
| HU-41 | Como **cliente**, quiero entrar al portal y ver solo mis reportes, actas y avance, para verificar sin llamar a nadie | Todo consultado desde el `contrato` del usuario autenticado (invariante 5); test obligatorio: cliente A pide recurso de cliente B → 404 | 2,5 d |
| HU-42 | Como **jefe de campo**, quiero ver la galería de evidencias de un trabajo, para revisar sin abrir la base | Pantalla sobre `ope_evidencias` agrupada por trabajo y sesión; miniaturas y descarga | 1,0 d |
| HU-43 | Como **encargado**, quiero listar y descargar los reportes técnicos generados, para reenviarlos al agrónomo | Pantalla sobre `ope_reportes_tecnicos` con filtro por cliente y período | 1,0 d |
| HU-44 | Como **jefe de campo**, quiero registrar las pausas con su causa atribuible (DS-01), para saber qué tiempo se pierde y por qué | Pausa ligada a sesión con causa de catálogo; agregado por causa en el tablero | 1,5 d |
| TE-13 | Quitar del menú el ítem `Operación › Mezclas`: no existe más por CR-01 | El ítem desaparece de `SecMenuSeeder` y del árbol sembrado; ningún test lo referencia | 0,5 d |
| TE-14 | Reemplazar los badges de demostración del sidebar por contadores reales | Los números del menú (`Órdenes 12`, `Devengos 18.490`, `Sesiones 6`…) salen hoy de `DatosDemoPanel`: son inventados. Cada badge consulta su módulo o desaparece; ningún número del panel sin origen en la base | 1,0 d |

**Total: 7,5 d · 4 pantallas**

---

## Sprint 13 — La campaña, los equipos y el cultivo

*Objetivo: darle al sistema el eje temporal y la unidad de imputación que el negocio siempre tuvo y el modelo no. Nace de los ajustes del dueño del 7/9/2026; el porqué de cada decisión está en el ADR 0015.*

| ID | Historia / tarea | CA esenciales | Est. |
|---|---|---|---|
| HU-46 | Como **dueño**, quiero registrar la campaña de cada cliente (`2025-2026`) y que los contratos cuelguen de ella, para cerrar el ciclo de ese cliente y compararlo con el siguiente | Módulo `Campania` + `cpn_campanias` con `cliente_id` y máquina de estados `planificada → abierta → cerrada`; único por `(cliente_id, código)`, sin guarda de solape; ABM en el panel; `campania_id` obligatorio en contratos (misma cliente) con migración por cliente y nullable en gastos; renombre `campana → campaniaActiva`. **Sin campaña activa de sesión** (corregido el 8/9/2026, ADR 0015) | ADR 0015 |
| HU-47 | Como **encargado**, quiero fijar la altura de vuelo en el contrato, dejar la ventana horaria en "todo el día" y decir si la aplicación es de siembra o de cosecha, para que la orden salga con lo que el cliente acordó | `altura_vuelo_m` en `com_contratos`; ventanas dejan de ser obligatorias (cero ventanas = todo el día, sin booleano) y se cae la guarda de activación por falta de ventanas; `tipo_aplicacion` (siembra / desarrollo / cosecha) en la orden | 1,5 d |
| HU-48 | Como **encargado**, quiero registrar qué cultivo se sembró en cada lote en cada campaña, para agrupar el avance por cultivo | Catálogo `com_cultivos` + `com_lote_campania` con `UNIQUE (lote_id, campania_id)`; carga desde la ficha del campo; cambiar de campaña no pisa el cultivo de la anterior | 2,0 d |
| HU-49 | Como **encargado**, quiero armar los equipos de trabajo con su piloto, su auxiliar y el equipamiento asignado, para saber quién y con qué opera cada cuadrilla | `per_equipos_trabajo` + `per_equipo_integrantes` + `per_equipo_recursos` con vigencia (`desde`/`hasta`); `man_generadores` (sin ella no hay generador que asignar); una persona no puede estar en dos equipos el mismo día; la ficha responde "quiénes lo integraban el 14 de marzo" | 3,0 d |
| HU-50 | Como **encargado**, quiero imputar el gasto y el combustible al equipo de trabajo y a la unidad que lo consumió, para tener costo real sin inventar a qué trabajo cargarlo | `equipo_trabajo_id` en `fin_gastos` y `fin_combustibles`; el combustible pasa de `destino` (texto) a recurso concreto, elegible solo entre el equipamiento asignado a ese equipo; agregado de gasto por equipo y por campaña | 2,0 d |
| HU-51 | Como **piloto o auxiliar**, quiero registrar la entrada y la salida del equipo en cada hacienda, para que quede cuántos días estuvimos en cada propiedad | `ope_estadias_hacienda` con `uuid_cliente`, entrando por `POST /api/sync` (idempotente, invariante 1); un equipo no puede tener dos estadías abiertas; pantalla de consulta por campaña y equipo | 2,5 d |
| HU-52 | Como **dueño**, quiero un informe de avance de contratos por cultivo y por cliente, para ver de un vistazo cuánto falta aplicar | Selectores obligatorios de cliente y cultivo; pantalla de filtros con campaña (por defecto la activa), chips removibles, pestañas "Por cultivo"/"Por cliente", barra por tramos de color con token propio cada uno (invariante 11), totalizador de "a aplicar", estado vacío | 3,0 d |

**Total: 17,0 d · 5 pantallas nuevas**

**Orden y dependencias.** HU-46 va primera y sola: las otras seis le cuelgan (`campania_id`). Después HU-47 (independiente, la más barata) y HU-48 en paralelo lógico; HU-49 antes que HU-50 (no se imputa a un equipo que no existe) y antes que HU-51 (la estadía es de un equipo); HU-52 al final, porque necesita campaña (46) y cultivo (48).

**HU-51 toca el motor de sync**, así que entra en la lista de "qué no delegar sin revisión línea por línea" de `CLAUDE.md` — con la revisión posterior a la integración, anotada en `runs/revision-pendiente.txt`, no reteniendo el PR.

---

## Sprint 14 — El panel se ve y se usa como debe

*Objetivo: la interfaz deja de arrastrar controles nativos y marcado copiado. Nace de la segunda tanda de ajustes del dueño del 7/9/2026, mirando el panel andando.*

| ID | Historia / tarea | CA esenciales | Est. |
|---|---|---|---|
| HU-53 | Como **usuario del panel**, quiero campos de fecha, desplegables y casillas que se vean y se usen como el resto del sistema, para no pelearme con controles del navegador | Átomos `select` (con búsqueda y teclado), `date` (calendario propio en español), `checkbox`, `checkbox-group`, `radio-group` y `textarea`, con el contrato de props de `input`; migradas las 88 apariciones crudas del panel (70 `<select>`, 11 `type="date"`, 7 `type="checkbox"`); ningún color hardcodeado, claro y oscuro | 4,0 d |
| HU-54 | Como **encargado**, quiero Propiedades y Lotes como pantallas separadas, y que el menú diga "Personal", para encontrar un lote sin abrir la propiedad entera | Dos ítems de menú donde había "Campos y lotes"; listado y ficha de lote con filtro y búsqueda; el alta de propiedad con sus lotes sigue funcionando; `Personas` → `Personal` con ruta, permiso y rótulos | 2,5 d |
| HU-55 | Como **dueño**, quiero una configuración del sistema separada de los datos de la empresa, para cargar las llaves de mapas, correo y otros tokens; y quiero la pestaña de Facturación que hoy dice "Próximamente" | `/panel/configuracion` por sectores, solo para el dueño, con valores cifrados en reposo, nunca devueltos al navegador, y excluidos del diff de la bitácora; resolución en cascada con `.env` como respaldo; datos fiscales de la empresa en `/panel/organizacion` | 3,0 d |
| HU-56 | Como **encargado**, quiero dibujar el perímetro del lote a pantalla completa y con acciones claras, para marcar los puntos con precisión | Pantalla completa con salida por `Escape` conservando el trabajo; barra de acciones propia con Material Symbols en español; superficie en hectáreas mientras se dibuja; proveedor configurable (Google Maps con llave, Leaflet + Esri sin ella) sin cambiar el GeoJSON guardado | 3,0 d |
| HU-57 | Como **encargado**, quiero elegir los repuestos de una orden por casillas y no por desplegables, para cargar seis repuestos sin abrir doce selects | Lista con casillas, búsqueda por código y descripción, cantidad y disponibilidad a la vista, resumen de lo elegido, base elegida una vez por orden; el payload y el cierre de orden no cambian | 1,5 d |
| HU-58 | Como **jefe de campo**, quiero ver qué aplicó cada persona —dónde, cuándo, para qué cliente y campaña, con qué dron— junto con sus sesiones rechazadas e incidencias, para poder revisar una aplicación que salió mal y decidir a quién vuelvo a convocar | Contrato de lectura `LecturaDesempenioPersona` en `Operaciones` (la cadena sesión → trabajo → orden → contrato → campaña ya existe con FKs reales); pestaña de desempeño en la ficha de persona, con filtros por fecha, cliente y campaña, y permiso propio. Sin puntajes ni rankings: hechos y sus fuentes | ADR 0003, ADR 0015 |

**Total: 14,0 d · 3 pantallas nuevas + el catálogo de inputs**

**HU-55 es crítica**: guarda secretos. Una llave en un log, en la bitácora, en un snapshot o en el HTML es un incidente, no un bug de interfaz.

**HU-53 va temprano, antes que el grueso del Sprint 13**: todas las pantallas de campaña, cultivo, equipos y gastos construyen formularios, y no tiene sentido que nazcan con los inputs viejos para migrarlos después. El orden real de ejecución está en `docs/gestion/cola_tareas.md`, no en el número de sprint.

---

## Sprint 15 — App de campo: ciclo automatizado, UI simple y funciones de seguridad (`agrocom-field`)

**Movido a `docs/gestion/plan_sprints.md` de `agrocom-field` (10/9/2026)** — para que ajustar la planificación de la app no requiera abrir rama/PR en este repo cada vez (mismo criterio que ya evitaba mezclar el código de los dos repos). Ver ese documento para el detalle completo: TE-18 (ciclo automatizado propio), TE-15/16/17 (sistema de diseño, preferencias, i18n español/portugués), HU-59, HU-69 (selector de rol, ADR 0005 de `agrocom-field`) y HU-60 a HU-68.

Este repo sigue siendo la fuente de la especificación funcional/técnica y de las decisiones de negocio que ese plan referencia (`docs/especificacion/`, `docs/negocio/`, ADR 0004 de este repo para lo de roles/token de dispositivo).

---

## Sprint 16 — Ajustes de negocio de Operaciones y Comercial (ronda del dueño, 13/9/2026)

*Objetivo: cerrar los gaps de negocio reales que aparecieron en la ronda de
observaciones del dueño (Word "MODULO OPERACIONES - COMERCIAL" + 2 audios),
documentados en
`docs/negocio/observaciones_operaciones_comercial_2026-09-13.md`. La
numeración de HU sigue desde 70 (no desde 59) para no chocar con la de
`agrocom-field`, que ya usa HU-59 a HU-69 en su propio `plan_sprints.md`
(Sprint 15, movido el 10/9/2026).*

| ID | Historia / tarea | CA esenciales | Est. |
|---|---|---|---|
| HU-70 | Como **jefe de campo**, quiero asignar uno o más equipos de trabajo (con sus lotes y hectáreas) a una orden de aplicación vigente, para que el sistema genere el `Trabajo` de cada equipo automáticamente y el piloto sepa qué le toca sin que se lo mande por WhatsApp | Una orden admite N asignaciones (`equipo_trabajo_id` + `lote_id` + hectáreas), con `SUM(hectáreas asignadas) ≤ hectáreas del lote`; confirmar la asignación crea un `Trabajo` por equipo con su lote y hectáreas ya resueltos; el trabajo generado sale en `GET /api/sync/catalogo` con su equipo, para que la app lo muestre al iniciar sesión | 4,0 d |
| HU-71 | Como **encargado**, quiero pausar un contrato vigente y reanudarlo, y ver sus estados con el vocabulario del negocio (En Ejecución/En Aprobación/Ejecutado/Pausado), para reflejar una interrupción sin cancelarlo | Nuevo estado `pausado` en `TransicionesContrato` (`vigente ↔ pausado`, transición inválida rechazada); las etiquetas del panel traducen `borrador/vigente/finalizado/cancelado/pausado` sin tocar los valores guardados | 2,0 d |
| HU-72 | Como **encargado**, quiero crear varios lotes de una propiedad de una sola vez indicando cuántos y su tipo de siembra, para no cargar uno por uno y poder renombrarlos y dibujar el polígono después | Extiende `CrearCampo` (ya acepta `lotes[]`) con una cantidad `N` + cultivo por defecto; genera `N` lotes con nombre provisorio y su fila en `com_lote_campania` para la campaña activa del cliente; cada lote se puede renombrar/dibujar después sin perder el cultivo | 2,0 d |
| HU-73 | Como **encargado**, quiero registrar si un lote tiene desniveles y si está limpio de obstáculos, para planificar el vuelo antes de asignar el equipo | `com_lotes` gana `desnivel` (`ninguno/algunos/varios/empinado`) y `limpieza` (`limpio/algunos_obstaculos/muchos_obstaculos`), catálogos cerrados distintos de `restricciones` (texto libre existente) | 1,0 d |
| HU-74 | Como **encargado**, quiero registrar si el cliente brinda alimentación, hospedaje y combustible al equipo, con observaciones, para saber qué logística cubre Agrocom en cada contrato | `com_contratos` gana `brinda_alimentacion`/`brinda_hospedaje`/`brinda_combustible` (booleanos) y `observaciones_logistica` (texto) | 1,0 d |
| HU-75 | Como **encargado**, quiero registrar la ubicación de la oficina central y el logo del cliente, y clasificar sus contactos también como Gerente General, Finanzas o Secretario, para tener el directorio completo | `com_clientes` gana `ubicacion_oficina`/`logo_path`; `TipoContactoCliente` suma `gerente_general\|finanzas\|secretario` sin quitar los valores existentes (`dueno/agronomo/encargado_propiedad/otro`) | 1,0 d |
| HU-76 | Como **encargado**, quiero cargar Departamento/Municipio/Localidad y una coordenada de la propiedad, para ubicarla en el mapa y filtrar por zona | `com_propiedades` gana `departamento`/`municipio`/`localidad` (texto) y `latitud`/`longitud` (DECIMAL); amplía ADR 0018 punto 1 con una adenda fechada (no lo reescribe: el pedido explícito que faltaba ya existe) | 1,5 d |
| HU-77 | Como **encargado**, quiero elegir si la campaña es de invierno o verano y que el nombre se arme solo (`Estación/AñoInicio/AñoFin`), para no tipear un nombre cada vez | `cpn_campanias` gana `estacion` (`invierno/verano`); `nombre` se autogenera si no se especifica; el panel puede *mostrar* "Activa"/"Inactiva" como etiqueta de `planificada+abierta`/`cerrada`, pero la máquina sigue siendo irreversible desde `cerrada` (ADR 0015) — sin excepción nueva | 1,0 d |
| HU-78 | Como **piloto**, quiero registrar qué productos y en qué cantidad se cargaron en el caldo (p. ej. Glifosato, 24D, litros de agua, Urea) al crear una aplicación, para que quede trazado qué se aplicó realmente | **Revierte CR-01** (nota fechada ya en `especificacion_funcional_tecnica.md` §7): módulo `Mezclas` nuevo (`ope_mezclas`/`ope_mezcla_items`, producto + cantidad + unidad) ligado por `uuid_cliente` al motor de sync; el reporte técnico deja de imprimir la nota fija de "fuera de alcance" y lista los productos cargados; la sección §7 de la especificación se reescribe con el alcance nuevo (qué transcribe el piloto vs. qué sigue sin validar Agrocom) | 4,0 d |
| HU-79 | Como **encargado**, quiero indicar si una orden es de producto sólido (kilos por vuelo: fertilizante, semilla de pasto) o líquido (litros por hectárea: insecticida/herbicida/fungicida/fertilizante líquido/coadyuvante/antiespumante), para que la orden pida los datos correctos según el insumo | `tipo_insumo` (`solido/liquido`) en `ope_ordenes_aplicacion`, catálogo de productos por tipo (reusa `Mezclas` de HU-78); depende de HU-78 (mismo catálogo) y HU-70 (misma tabla, para no iterarla dos veces) | 3,0 d |
| HU-80 | Como **jefe de campo**, quiero que el reporte incluya la cantidad de ciclos de batería, el ciclo actual, las horas de vuelo del dron y fotos de control/balanceo/limpieza, junto con la fecha y hora de emisión, para no preguntar por WhatsApp el estado del equipo | Contrato de lectura nuevo de `Operaciones` hacia `Mantenimiento` (mismo patrón que `LecturaAlertasTemperaturaBateria`) trae `ciclos_acumulados` real; nuevos campos de evidencia (`horas_vuelo_dron`, `foto_control`, `foto_ciclo_bateria_balanceo`, `foto_dron_limpio`) ligados por `uuid_cliente`; el PDF imprime `generado_en` (ya existe en la base, solo falta el blade) junto con batería/ciclos/horas de vuelo | 3,0 d |
| HU-91 | Como **encargado**, quiero que el contrato deje de pedir un adelanto en porcentaje y el bloque completo de "Parámetros de vuelo" (clima, velocidad máxima, umbral de reporte y altura de vuelo), porque esos siete campos no van en el contrato — quedan solo en la Orden o heredan del sistema | Se elimina `adelanto_pct` de `com_contratos` (columna, validación, formulario, listado); `adelanto_monto` se relabelea a "Adelanto Solicitado" (mismo criterio de adopción de label que "Monto Estimado", sin migración de por medio); se elimina la sección completa "Parámetros de vuelo" (`viento_max_kmh`/`temperatura_max_c`/`humedad_min_pct`/`humedad_max_pct`/`velocidad_max_kmh`/`umbral_reporte_avance_ha`/`altura_vuelo_m`) del formulario y del modelo de contrato — el contrato deja de tener límites propios, todo hereda de la Orden o del valor por defecto del sistema (RF-60). `com_contrato_ventanas` **no se toca**: confirmado con el dueño (14/9/2026) que "VENTANA DE APLICACION (ORDEN DE APLICACION)" del Word era la misma instrucción de sacar parámetros de vuelo, leída en el contexto de esa pantalla — no una reubicación de la ventana; ADR 0015 punto 5 sigue vigente | 1,5 d |
| HU-92 | Como **jefe de campo**, quiero que una Orden de Aplicación pueda cubrir varios lotes de la propiedad y repartir esos lotes entre uno o más equipos con sus hectáreas, indicando cuántos equipos hacen falta, para no limitarme a un lote por orden | **Amplía HU-70** (ya integrada, PR #189): `ope_ordenes_aplicacion` deja de tener un `lote_id` único y pasa a N lotes vía una tabla de detalle nueva (`orden_lotes`: `orden_id`, `lote_id`, `hectareas_solicitadas`), con el índice único "una orden vigente por lote" migrado a esa tabla; en `/panel/ordenes/crear` se agrega "Cantidad de Equipos Necesarios" (default 1) — con 1 equipo se asigna a un dron/equipo específico que ejecuta el total de hectáreas de todos los lotes de la orden; con 2 o más, cada equipo elige un subconjunto de esos lotes (selección múltiple) y sus hectáreas en `/panel/asignacion-equipos`, y confirmar genera un `Trabajo` por cada par equipo↔lote (mismo criterio de generación automática que ya usa `AsignarEquiposOrden`); el campo hoy llamado "Nro. Aplicación" pasa a "Número de aplicaciones" (cambio de label, mismo `nro_aplicacion`) | 5,0 d |
| HU-93 | Como **encargado**, quiero que el listado de Trabajos muestre a qué Orden de Trabajo y equipo pertenece cada uno, y poder editarlo o eliminarlo antes de validarlo, para no navegar a otra pantalla ni perder un trabajo cargado mal | El listado de `/panel/trabajos` suma las columnas "Nro. Trabajo", "Orden de Trabajo" (`nro_aplicacion` de la orden) y "equipo asignado", junto a "Hectáreas"/"Estado" ya existentes; gana acciones de editar/eliminar solo mientras el trabajo no esté `validado` (invariante 8: soft delete; invariante 2: un trabajo validado nunca se sobrescribe — no se ofrece ni editar ni eliminar sobre uno validado, criterio por defecto ya escrito en `docs/negocio/observaciones_operaciones_comercial_2026-09-13.md` §4.4) | 2,0 d |
| HU-94 | Como **encargado**, quiero delimitar el perímetro del Campo en el mapa al crearlo, y recién después dividirlo en Lotes viendo ese límite como referencia, para no cargar un lote sin saber dónde termina el campo | `com_campos.geometria` ya existe en la base y en el modelo (ADR 0018) pero ningún formulario la expone — `CrearCampoRequest` lo dejó documentado como pendiente desde tarea 35/68. Se suma a `/panel/campos/crear` y `.../edit` el mismo editor de mapa que ya usan los lotes, para el perímetro propio del campo; la fila de lote con editor de mapa se saca de la pantalla de ALTA del campo (el generador de HU-72 sigue creando lotes provisorios sin geometría ahí) — dibujar el polígono de un lote pasa a requerir siempre un campo ya guardado (edición del campo, o `/panel/lotes/crear` con el campo elegido); el editor de mapa del lote pinta el perímetro del campo elegido como capa de referencia de solo lectura | 3,0 d |

**Total: 34,0 d · 0 pantallas nuevas de menú (todas amplían pantallas existentes, salvo la asignación de HU-70)**

**Orden y dependencias.** HU-70 va primera: resuelve el reclamo activo del
dueño (audio 1) y varias observaciones del Word cuelgan de la misma
cardinalidad orden↔trabajo. HU-80 segunda, por ser el otro reclamo por audio y
no depender de nada. HU-71 a HU-77 son independientes entre sí y de bajo
riesgo — se intercalan según convenga. HU-78 va antes que HU-79 porque
comparten el catálogo de insumos; HU-79 además espera a HU-70 integrada para
no iterar dos veces sobre `ope_ordenes_aplicacion`. HU-92 también reescribe
`ope_ordenes_aplicacion` (pasa de 1 a N lotes) — conviene resolverla junto con
o inmediatamente antes de HU-79, para no iterar la misma tabla tres veces.
HU-91, HU-93 y HU-94 son independientes del resto. HU-94 conviene resolverla
antes que cualquier otra que toque el formulario de campos/lotes (ninguna de
este sprint lo hace), y no depende de HU-72 (Sprint 16, ya integrada) más que
en no romper su generador de alta masiva.

**HU-70, HU-78, HU-79, HU-80 y HU-92 son críticas** (tocan el motor de sync o
una guarda de negocio ya existente) — igual se implementan y se integran; la
revisión línea por línea es posterior, anotada en `runs/revision-pendiente.txt`
(regla de `CLAUDE.md` y `automatizacion_desarrollo.md` §5). HU-91, HU-93 y
HU-94 no son críticas: son columnas que se sacan de un formulario, un
listado con soft delete ya cubierto por la plataforma, y un editor de mapa
que ya existe para lotes y se reutiliza para el campo — ninguna toca el
motor de sync ni una máquina de estados.

**Ambigüedades de la sección 4 del documento de observaciones — resueltas el
14/9/2026** (ver
`docs/negocio/observaciones_operaciones_comercial_2026-09-14.md`): el dueño
compartió el texto completo del Word original y confirmó en el momento el
recorte de parámetros de vuelo del contrato (HU-91) y que la ventana de
aplicación no se reubica. También surgió, leyendo el documento completo, que
la Orden de Aplicación debe cubrir varios lotes (no estaba en la lectura
parcial del 13/9) — HU-92. El alcance de "editar/eliminar trabajo" no cambió:
sigue el criterio por defecto ya escrito (§4.4), ahora con columnas de
listado confirmadas — HU-93.

---

## Sprint 17 — Catálogo de recursos ampliado (ronda del dueño, `REcursos.docx`, 13/9/2026)

*Objetivo: completar las fichas de Drones, Baterías, Vehículos, Base y
Generador con los campos de inventario/mantenimiento que el dueño pidió en un
tercer documento de la misma ronda, sin texto marcado en rojo — ninguno choca
con arquitectura. Detalle en
`docs/negocio/observaciones_recursos_2026-09-13.md`. Personal (nombre, rol,
base, tarifa por hectárea, activo) ya estaba completo y no generó HU.*

| ID | Historia / tarea | CA esenciales | Est. |
|---|---|---|---|
| HU-81 | Como **encargado**, quiero cargar la capacidad de un dron en kilos además de en litros, para que una orden de aplicación sólida sepa cuánto puede llevar cada vuelo | `capacidad_kg` en `ope_drones`, mismo patrón que `capacidad_l`; sin catálogo cerrado de valores (a diferencia de los litros, no hay 3 capacidades fijas conocidas todavía) | 0,5 d |
| HU-82 | Como **encargado**, quiero una ficha de inventario del dron con número de serie, chasis, versión de software, región y sus accesorios (cargador de control, módem, maletín), para llevar el activo completo sin mezclarlo con el dato operativo | Ficha nueva en `Mantenimiento` (`man_drones` o equivalente), correlación por identificador de texto con `ope_drones` (mismo patrón sin FK real que batería/recarga, justificado por el propio docblock de `ope_drones`: "deliberadamente mínima") | 2,0 d |
| HU-83 | Como **encargado**, quiero registrar el ciclo inicial de una batería además del acumulado, y poder marcarla en mantenimiento, para llevar su historial completo | `ciclos_inicial` nuevo en `man_baterias` (separado de `ciclos_acumulados`, que sigue siendo el total corriente); `EstadoBateria` suma el caso `Mantenimiento` (hoy solo `Activa`/`Retirada`), con su `CHECK` actualizado | 1,0 d |
| HU-84 | Como **encargado**, quiero la ficha completa del vehículo (marca, modelo, año, combustible, 4x4, kilometraje inicial y actual) y poder pausarlo, para llevar la flota igual que los drones | `man_vehiculos` gana `marca`/`modelo`/`anio`/`combustible` (gasolina/diesel)/`es_4x4`/`kilometraje_inicial`/`kilometraje_actual`; `EstadoVehiculo` suma el caso `Pausa` (hoy `activo`/`taller`/`de_baja`) | 2,0 d |
| HU-85 | Como **encargado**, quiero cargar la coordenada de una base además de su ubicación en texto, para ubicarla en el mapa | `latitud`/`longitud` (DECIMAL) en `per_bases`, mismo patrón de validación de rango que HU-76 (Sprint 16) | 0,5 d |
| HU-86 | Como **encargado**, quiero registrar las horas inicial y actual de un generador en vez de un solo valor cargado a mano, para saber cuánto acumuló desde que entró en la flota | `man_generadores` reemplaza `horas_uso` único por `horas_inicial`/`horas_actual` (ambos siguen siendo carga manual — un generador no vuela, no hay de dónde derivarlo); migración de datos existentes: `horas_inicial = horas_actual = horas_uso` | 1,0 d |
| HU-87 | Como **dueño**, quiero que el ciclo acumulado de una batería se incremente solo al cerrarse cada recarga que la usó, y que nunca se pueda bajar a mano sin dejar rastro, para que el dato del reporte (HU-80) sea confiable — "como el odómetro de un auto" (nota textual del dueño) | Al cerrarse una `Recarga` correlacionada por identificador con una `man_baterias`, el ciclo se incrementa automáticamente (evento de dominio desde Operaciones, consumido por Mantenimiento); `ActualizarBateria` deja de aceptar un valor menor al actual salvo como corrección explícita y auditada; test de dos recargas de la misma batería incrementando el contador dos veces, y test de que bajar el valor a mano sin ese mecanismo se rechaza. Depende de HU-83 (`ciclos_inicial` ya integrado) | 2,5 d |

**Total: 9,5 d · sin pantallas nuevas de menú (todas amplían fichas existentes)**

**Orden y dependencias.** HU-81 conviene antes que HU-79 (Sprint 16) si ambas
quedan en la misma vuelta del ciclo, porque HU-79 puede aprovechar
`capacidad_kg` para validar el máximo por vuelo — no es bloqueante, HU-79 ya
tiene su propio criterio sin ese dato. HU-87 depende de HU-83 (mismo campo).
El resto es independiente entre sí. **HU-87 es crítica** (toca el motor de
sync/eventos de dominio entre Operaciones y Mantenimiento, y es la garantía
de auditabilidad de un dato que va a un reporte); el resto de este sprint no
lo es: son columnas nuevas sobre catálogos que ya existen.

---

## Sprint 18 — Orden de mantenimiento (ronda del dueño, `Mantenimiento.pdf`, 13/9/2026)

*Objetivo: ajustar la pantalla de orden de mantenimiento a lo que el
encargado necesita ver en el día a día, sin tocar el costeo automático de
repuestos que ya funciona por debajo. Detalle en
`docs/negocio/observaciones_mantenimiento_2026-09-13.md`.*

| ID | Historia / tarea | CA esenciales | Est. |
|---|---|---|---|
| HU-88 | Como **encargado**, quiero no ver en mi menú del día a día las pantallas de Plan de Mantenimiento, Repuestos y Stock Base, y ver el precio final real de una orden cerrada, para no navegar pantallas de administración que no uso seguido | Los 3 ítems dejan de verse en el menú del rol `encargado` (sin eliminar rutas ni lógica — siguen accesibles a quien sí tenga el permiso); la orden cerrada muestra "Precio de Mantenimiento Final" = el monto real del `fin_gastos` vinculado por `gasto_id` (HU-37), sin campo editable nuevo que lo reemplace | 1,5 d |
| HU-89 | Como **encargado**, quiero registrar una descripción de mantenimiento final al cerrar la orden, separada de la descripción de apertura, para dejar constancia de qué se hizo realmente | `man_ordenes_mantenimiento` gana `descripcion_final`; `MaquinaEstadosOrdenMantenimiento::cerrar()` la exige antes de transicionar a `cerrada` | 1,0 d |
| HU-90 | Como **encargado**, quiero clasificar un vehículo por tipo (incluida "chata"), para diferenciar la flota | `man_vehiculos` gana `tipo` (catálogo cerrado con al menos `chata`); no toca `equipo_tipo` de `man_ordenes_mantenimiento` (esa columna distingue tabla de origen — `dron`/`vehiculo` —, no el tipo de vehículo). Complementa HU-84 (Sprint 17), misma tabla, sin bloquearla | 1,0 d |

**Total: 3,5 d · sin pantallas nuevas de menú**

**Ninguna es crítica**: son ajustes de visibilidad de menú y columnas nuevas
sobre una máquina de estados y un flujo de costeo que ya existen y no se
tocan en su lógica.

---

## Sprint 19 — Exclusividad de lotes entre contratos (ronda del dueño, 18/9/2026)

*Objetivo: que un lote no quede comprometido dos veces en la misma campaña. El
contrato elige lotes de las propiedades del cliente, y al aprobarse los
bloquea para los demás contratos de esa campaña; los que competían por el
mismo lote pasan solos a "En conflicto". Reemplaza la guarda anterior por
propiedad completa. Detalle de la corrección del dueño en
`docs/negocio/observaciones_operaciones_comercial_2026-09-18.md`; el porqué de
la decisión, en el ADR 0021. La numeración sigue desde HU-96: HU-95 (resumen
económico de campaña, especificación §9.2) ya está integrada.*

| ID | Historia / tarea | CA esenciales | Est. |
|---|---|---|---|
| HU-96 | Como **encargado**, quiero que al aprobar un contrato sus lotes queden bloqueados para otros contratos de la campaña y que los que compartían lote pasen a "En conflicto", para no comprometer dos veces la misma superficie | **1) Retención:** un contrato `vigente` ("En Ejecución") o `pausado` retiene sus lotes y los bloquea para cualquier otro contrato de la **misma campaña**, hasta que pase a `finalizado` ("Ejecutado") o `cancelado`; pausar **no** libera los lotes. **2) Borrador libre:** mientras un contrato está en `borrador` ("En Aprobación") el mismo lote se puede repetir en varios contratos. **3) Conflicto:** al aprobarse un contrato, los otros `borrador` de la misma campaña que comparten al menos un lote con él pasan automáticamente al estado nuevo `conflicto` ("En conflicto"); un contrato en `conflicto` no se puede aprobar. **4) Salida:** vuelve solo a `borrador` cuando ya no comparte ningún lote con un contrato que retenga lotes (se quitó el lote editando, o el otro contrato se canceló o finalizó); también puede cancelarse (`conflicto → cancelado`); nadie puede pedir `conflicto` a mano desde el panel. **5) Guardado:** registrar o editar un contrato agregándole un lote ya retenido por otro contrato (`vigente` o `pausado`) de la misma campaña se rechaza en el servidor con un mensaje que nombra el lote y el contrato que lo tiene; en edición solo se validan los lotes nuevos agregados (un contrato en `conflicto` puede guardarse arrastrando lotes ya ocupados). **6) Reemplaza** la guarda por propiedad completa ("propiedad agotada", `LotesDePropiedadAgotados`, PR #233): la regla pasa a ser por **lote**. **7) Máquina (invariante 7):** tabla `borrador → vigente \| cancelado \| conflicto`, `conflicto → borrador \| cancelado`, `vigente → finalizado \| cancelado \| pausado`, `pausado → vigente`, `finalizado` y `cancelado` sin salida; `borrador ↔ conflicto` solo los dispara la reconciliación de conflictos por campaña dentro de `MaquinaEstadosContrato`, nunca un usuario; la tabla queda cubierta en `tests/Unit/MaquinaEstadosComercialTest.php`. **8) Datos:** migración que agrega `conflicto` al `CHECK` `com_contratos_estado_chk` (solo Postgres). **9) UI:** la lectura para el panel (`LecturaOcupacionLotesPorCampania`) usa los mismos estados que retienen lotes que la guarda del servidor (`vigente` y `pausado`). **10)** `finalizar` sigue siendo manual en este tramo | 3,0 d |

**Total: 3,0 d · 0 pantallas nuevas de menú (amplía Contratos)**

**Es crítica**: toca el servicio de la máquina de estados del contrato y
reemplaza una guarda de negocio ya existente — se implementa y se integra
igual; la revisión línea por línea es posterior, anotada en
`runs/revision-pendiente.txt` (regla de `CLAUDE.md` y
`automatizacion_desarrollo.md` §5).

**Relación con HUs existentes.** HU-71 (estados del contrato) se **extiende**,
no se reemplaza: la máquina gana `conflicto`, y `pausado` conserva sus lotes.

**Tramo siguiente, sin HU todavía (pendiente de implementar).** El cierre
automático del contrato al cerrarse su última aplicación, y la reforma de la
Orden de Aplicación que el dueño dejó decidida el mismo día —la orden pasa a
ser siempre por toda la aplicación del contrato, sin elegir lotes ni
hectáreas parciales por lote— llegan en un tramo posterior, que se numerará
cuando se planifique. Esa reforma supera parcialmente a HU-92 (lo de "orden
con N lotes y hectáreas solicitadas por lote") y no cambia el reparto por
equipo y lote de la Orden de Trabajo (HU-70). Está registrada en
`docs/negocio/observaciones_operaciones_comercial_2026-09-18.md`, no como ya
implementada.

---

## Alcance total del sistema

| Bloque | Días | Pantallas de menú | Estado |
|---|---|---|---|
| Sprints 1–6 (ruta crítica) | 47,5 d | 8 | 32,7 d hechos · 13,5 d pendientes, ninguno de software · 1,3 d de HU-01 sin hacer |
| Sprints 7–12 (resto del panel) | 40,0 d | 24 | sin empezar |
| **Proyecto completo** | **87,5 d** | **32** | **32,7 d hechos = 37 %** |

### Tres métricas, tres preguntas distintas

Medido el 2/9/2026 con una auditoría del panel en navegador (Playwright, login
real como Dueño), no contando archivos:

| Métrica | Valor | Qué responde |
|---|---|---|
| **Esfuerzo** | **37 %** — 32,7 de 87,5 días-hombre | Cuánto trabajo se hizo |
| **Superficie del panel** | **25 %** — 8 de 32 ítems del menú llevan a una pantalla | Qué ve quien abre el sistema |
| **Panel operable** | **9 %** — 3 de 32 pantallas permiten hacer algo | Qué puede *usar* quien abre el sistema |

Esa tercera fila es la incómoda y hay que decirla: de las 8 pantallas que
existen, solo tres aceptan escritura — validación de sesiones (validar y
rechazar), organización (guardar) y versiones del APK (subir y autorizar). Las
otras cinco —dashboard, trabajos, usuarios, dispositivos, alertas— son de
lectura. **La gestión de usuarios no existe** (ver la nota de HU-01, Sprint 1).

La brecha entre esfuerzo y superficie no es un error de medición: la ruta
crítica era mayormente backend —sync offline idempotente, máquina de estados,
devengos, auditoría, seguridad multirol— y entregó 8 pantallas. Los sprints 7
a 12 invierten la proporción: 40 días de trabajo que producen las 24 pantallas
restantes y vuelven operables las que ya están.

**El Sprint 7 es el de mejor retorno visible: con 9,5 días, el panel pasa de 8
a 15 pantallas y la gestión de usuarios empieza a funcionar — de 25 % a 47 % de
superficie.** Por eso va primero, por delante incluso del bloque de dinero.

## Reglas del marco (versión para un equipo de una persona)

- **Planificación (1 h, lunes de inicio)**: elegir HUs hasta llenar capacidad; lo que no entra NO entra — el alcance se recorta, la fecha del ensayo no.
- **Demo (viernes de cierre)**: grabada en video de 5 minutos aunque no haya audiencia — es el registro de avance para el dueño-que-también-sos-vos y el material para pilotos y cliente.
- **Retro (15 min)**: una sola pregunta — ¿qué estimación falló y por qué? Ajustar la velocidad del sprint siguiente con ese dato, no con optimismo.
- **Definition of Done** de toda HU: criterios de aceptación con test automatizado, suite en verde en CI, desplegado en staging, código integrado a `develop` por PR (nunca directo) — y para HUs de app, probado en el RC o celular real, no solo en emulador.
- **Con los agentes de IA**: cada HU es una conversación en su propia rama `feature/*`; la HU con sus CA es el prompt de partida; el test del CA se escribe primero. Las TE del sync (TE-04/05) y todo lo que genera dinero (HU-16) se revisan línea por línea; el resto, por diff y test en el PR.

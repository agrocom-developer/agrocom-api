# Plan de sprints, historias de usuario y betas — Ruta crítica (Fases 1–2)

**Agrocom SRL · Sistema de Gestión de Operaciones de Fumigación**

Marco: **6 sprints de 2 semanas** (12 semanas), un desarrollador con agentes de IA a dedicación completa. Capacidad estimada por sprint: **8–9 días ideales** (los días ideales ya descuentan interrupciones). Las estimaciones asumen las decisiones tomadas: PostgreSQL, AdminLTE + Blade/Livewire en el panel, Flutter con BLoC, modelo `sec_*` ajustado (permiso abstracto + multi-rol).

Convención: **HU** = historia de usuario (valor visible); **TE** = tarea técnica habilitante (sin ella las HU no existen). Cada HU lista sus criterios de aceptación (CA) esenciales — el detalle fino vive en la spec técnica v1.0, que es el contrato.

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

*Objetivo: los dos repos viven, el RC ejecuta un APK propio, y la seguridad multi-rol funciona.*

| ID | Historia / tarea | CA esenciales | Est. |
|---|---|---|---|
| TE-01 | Crear `agrocom-api` y `agrocom-field`: esqueletos, CI (Pest/analizador; flutter test), CLAUDE.md con invariantes, entorno staging | Suites en verde en CI; deploy automático a staging | 1,0 d |
| TE-02 | **Spike RC Agras**: instalar APK propio, leer screenshot de DJI desde galería, confirmar versión Android/minSdk, permisos | Informe corto con lo que anda y lo que no; decisión app-en-RC vs app-en-celular | 1,5 d |
| TE-03 | Migraciones del núcleo comercial (clientes, contratos, campos, lotes, órdenes) + seeds de datos reales del contrato | Órdenes consultables por API con filtros | 1,5 d |
| HU-01 | Como **encargado**, quiero crear usuarios con uno o más roles, base y enlace a persona operativa, para que cada quien entre con lo suyo | `sec_*` ajustado (permission abstracto, `sec_user_role`, `persona_id`); el encargado no puede crear usuarios dueño | 2,0 d |
| HU-02 | Como **usuario del panel**, quiero iniciar sesión y ver el menú AdminLTE armado según mis permisos | Menú renderizado desde `sec_menu`/`sec_permission`; unión de roles; botones ocultos sin permiso | 1,5 d |
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
| HU-10 | Como **agrónomo/encargado**, quiero cargar la receta de la orden con su secuencia de incorporación, para que el auxiliar la ejecute tal cual | Receta con ítems ordenados, dosis y unidad (ml/ha, g/ha, ml/100L, %v/v), pre-disolución | 1,5 d |
| HU-11 | Como **auxiliar**, quiero que la app calcule las cantidades por tanque según volumen del dron y L/ha de la orden, para no hacer regla de tres en el campo | Cálculo por unidad de dosis; volúmenes reales por modelo (30/50/60 L) | 1,0 d |
| HU-12 | Como **auxiliar**, quiero un checklist secuencial bloqueante donde confirmo cada producto con la **cantidad real**, para que la mezcla quede demostrada paso a paso | No avanza sin confirmar el anterior; desvío real vs. calculado se registra; EPP al inicio; foto de evidencia | 2,5 d |
| HU-13 | Como **auxiliar**, quiero registrar sobrantes (volumen, destino, triple lavado) y recargas con batería y temperatura, para cerrar el circuito del caldo | Alerta local si temperatura > 50 °C; recarga vincula mezcla ↔ sesión; litros de combustible del generador | 2,0 d |
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
| TE-12 | Cierre: auditoría de permisos por rol contra la matriz de la spec §3, seeds de producción, tag v1.0 | Cada celda de la matriz de permisos tiene un test | 1,0 d |

**Total: 8,5 d · Entrega: v1.0 ruta crítica en producción**

---

## Después de la v1.0 (con la campaña andando)

El mismo marco continúa con sprints de mantenimiento + una fase por sprint, en orden de urgencia de plata: **Sprint 7–8: Fase 4** (devengos→anticipos→planilla→facturación — la gente cobra a fin de mes); **Sprint 9: Fase 3** (gastos, rendiciones, combustible — se migra de la planilla transitoria); **Sprint 10–11: Fases 5–6** (inventario y mantenimiento); **Sprint 12: Fases 7–8** (portal del cliente, dashboard de ganancia y alertas completas). Durante campaña la capacidad real por sprint baja (soporte + campo): planificar 5–6 días ideales, no 8–9.

## Reglas del marco (versión para un equipo de una persona)

- **Planificación (1 h, lunes de inicio)**: elegir HUs hasta llenar capacidad; lo que no entra NO entra — el alcance se recorta, la fecha del ensayo no.
- **Demo (viernes de cierre)**: grabada en video de 5 minutos aunque no haya audiencia — es el registro de avance para el dueño-que-también-sos-vos y el material para pilotos y cliente.
- **Retro (15 min)**: una sola pregunta — ¿qué estimación falló y por qué? Ajustar la velocidad del sprint siguiente con ese dato, no con optimismo.
- **Definition of Done** de toda HU: criterios de aceptación con test automatizado, suite en verde en CI, desplegado en staging, y — para HUs de app — probado en el RC o celular real, no solo en emulador.
- **Con los agentes de IA**: cada HU es una conversación; la HU con sus CA es el prompt de partida; el test del CA se escribe primero. Las TE del sync (TE-04/05) y todo lo que genera dinero (HU-16) se revisan línea por línea; el resto, por diff y test.

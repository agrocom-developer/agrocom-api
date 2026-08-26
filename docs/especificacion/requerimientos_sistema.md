# Requerimientos y requisitos de sistema

**Agrocom SRL · Documento oficial vigente · Derivado de las respuestas de campo del 25/8/2026**

Traduce las políticas de negocio capturadas en campo (`docs/negocio/politicas/`, `docs/negocio/flujo_base_y_excepciones.md`) a requerimientos verificables. Complementa a la especificación (`especificacion_funcional_tecnica.md`) — no la reemplaza: donde un RF ajuste el modelo, el cambio definitivo se hará en la especificación en la iteración de consolidación del modelo de datos (pendiente de las capturas del RC).

Prioridad: **[C]** crítico para la primera campaña con sistema (fases 1–2 de la espec §15) · **[A]** alto (fases 3–4) · **[M]** medio (fases 5–8).

---

## 1. Requerimientos funcionales

### Operación de campo (app RC del piloto)

- **RF-01 [C]** Mostrar al piloto las órdenes vigentes y los lotes del día con sus hectáreas pendientes ("con qué lote podés continuar al siguiente día").
- **RF-02 [C]** Abrir sesión registrando dron, pareja, condiciones (viento, temperatura, humedad) y `hectarea_inicial_acumulada` cuando se retoma una misión.
- **RF-03 [C]** Cerrar sesión/lote con: hectáreas, captura del RC adjunta, motivo de cierre, superficie no aplicada con motivo. Sin captura no hay validación.
- **RF-04 [C]** Registrar pausas con causa atribuible (`clima`, `imprevisto_del_cliente`, `falla_equipo`, `logistica`) y duración — la información que hoy "falta siempre".
- **RF-05 [C]** Registrar incidencias tipificadas con foto (ESC, motor, batería, caída, caldo) en momentos de dron en tierra.
- **RF-06 [C]** Si las condiciones están fuera del rango de la orden, bloquear la apertura salvo autorización del agrónomo, que queda firmada (`autorizado_con_observacion`).
- **RF-07 [A]** Presentar el acta para firma (en pantalla o foto del papel), por lote. La firma agrupada al cierre de la aplicación es una flexibilización en disputa (CR-06 de `analisis_clasificacion.md`), pendiente de decisión del dueño.
- **RF-08 [C]** Toda interacción de captura debe poder completarse con el dron en tierra en menos de un cambio de batería (~5 min); ninguna pantalla exige entrada durante el vuelo.

### Mezcla y recargas (app celular del auxiliar)

- **RF-10 [C]** Registrar toda mezcla con su **origen**: preparada por el cliente (escenario dominante) o por Agrocom. Si prepara Agrocom: checklist secuencial bloqueante con cantidades calculadas vs. reales (espec §7). Si prepara el cliente: registro de recepción, quién la preparó y problema detectado.
- **RF-11 [C]** Calcular automáticamente cantidades por tanque y orden de mezcla a partir de la orden (dosis por ha o por 100 L, volumen del tanque de mezcla o del tanque del dron según el caso, tipo de siembra).
- **RF-12 [C]** Registrar recargas: litros de caldo, batería saliente con temperatura, hora — en el tiempo muerto del ciclo (~5 min).
- **RF-13 [A]** Registrar cargas de combustible del generador (litros; el precio lo carga el encargado después).
- **RF-14 [A]** Registrar sobrante del último tanque y destino (incluida la aplicación en cortinas) y disposición de envases.
- **RF-15 [M]** Confirmación de EPP al iniciar mezcla propia — condicionada a la política de provisión de EPP que el negocio debe decidir (hoy no se provee).

### Validación, actas y devengo (panel web)

- **RF-20 [C]** Validar sesiones contra la captura del RC; el validador nunca es el piloto de la sesión, a nivel de persona (crítico: el dueño también vuela).
- **RF-21 [C]** Generar el devengo automáticamente al validar — nunca al cerrar (espec, invariante 3).
- **RF-22 [A]** Anticipos: tope 3.000 Bs/mes y 70% del devengado; la aprobación es del dueño y queda registrada; entrega por QR o efectivo con evidencia.
- **RF-23 [A]** Planilla mensual por sesión validada, recalculable desde el origen; aprueba solo el dueño.

### Planificación y seguimiento (panel web)

- **RF-30 [C]** Plan del día del jefe de campo: lotes, parejas piloto/auxiliar, orden, turnos día/noche — comparable contra lo ejecutado.
- **RF-31 [A]** Vista diaria consolidada por fecha y lote de todo lo que reportó el campo (reemplaza el hilo de WhatsApp).
- **RF-32 [M]** Mapa de avance de la aplicación: lotes aplicados, en curso y pendientes con hectáreas.
- **RF-33 [A]** Historial de asignación por piloto (lotes fáciles/difíciles) para sostener la equidad con datos.

### Gastos, repuestos y compras (panel web)

- **RF-40 [A]** Gastos con categoría, evidencia y marca de comprobante; rendiciones con respaldo fotográfico (gasolina de reventa sin factura) y capturas de QR.
- **RF-41 [A]** Escalera de autorización de gastos por monto, parametrizable (referencia actual: jefe ~500 Bs, encargado ~1.000 Bs, dueño el resto).
- **RF-42 [A]** Pedido de repuestos con estados (`solicitado → cotizado → comprado → enviado → recibido`) y tiempos medidos; proveedor y canal de envío.
- **RF-43 [M]** Stock por base con alerta de mínimo en repuestos críticos (hélices, motores, bombas, baterías).

### Reportes y portal

- **RF-50 [A]** Reporte técnico PDF por lote generado automáticamente al conformar (captura RC, parámetros, fotos, condiciones, mezcla ejecutada).
- **RF-51 [A]** Reporte comercial por aplicación; además, reporte de avance por umbral configurable de hectáreas (práctica actual: ~500 ha) o a pedido.
- **RF-52 [M]** Portal del cliente: reportes, actas e historial, siempre desde el contrato del usuario autenticado (invariante 5, test A→B → 404).
- **RF-53 [M]** Dashboard del dueño con estado de resultados básico y costo Bs/ha.

### Parámetros de negocio (configuración, no código)

- **RF-60 [C]** Límites de condiciones (viento, temperatura, humedad), ventanas horarias y velocidad máxima **por contrato y por orden** — los clientes los modulan (caso real: velocidad ≤15 km/h impuesta).
- **RF-61 [A]** Tarifas por rol, topes de anticipo, umbral de reporte, montos de autorización, tolerancia de solape y desvío de mezcla (±5% aceptado en campo): todos parámetros.

## 2. Requerimientos no funcionales

- **RNF-01 [C] Offline-first absoluto.** Starlink está en la base, no en el lote ("si tenés señal, belleza; si no, esperar"). Toda escritura va a SQLite local y sincroniza al volver a cobertura, idempotente por `uuid_cliente` (espec §2.1).
- **RNF-02 [C] Idempotencia verificable**: reprocesar el mismo lote de sync N veces produce el mismo estado final; test obligatorio antes de la primera pantalla.
- **RNF-03 [C] Usabilidad de campo**: pantallas operables con apuro y sol directo, tipografía grande, mínimo tipeo (selecciones y fotos antes que texto), en español. Los usuarios reales escriben con ortografía libre: ningún campo crítico depende de texto libre.
- **RNF-04 [C] Nada de captura en vuelo**: la app del RC no exige interacción mientras el dron está en el aire.
- **RNF-05 [A] Evidencias comprimidas** en el dispositivo (<300 KB por imagen) con subida en cola separada de los registros.
- **RNF-06 [C] Integridad monetaria**: dinero y hectáreas en `DECIMAL`; todo monto derivado recalculable exacto desde el origen.
- **RNF-07 [C] Inmutabilidad de lo validado**: correcciones como registros nuevos con `anula_a_id`; soft delete y bitácora de auditoría transversales (ADR 0007).
- **RNF-08 [A] Autonomía prolongada**: jornadas de 5:00 a 22:00 y turnos nocturnos; la app no puede drenar el dispositivo (sin GPS continuo ni polling agresivo).
- **RNF-09 [A] Multi-rol real**: una persona con todos los roles (el dueño hoy es piloto+jefe+encargado+agrónomo asesor) opera con un solo login; las reglas por persona (validador ≠ piloto) no se rompen por acumulación de roles.
- **RNF-10 [M] Degradación tolerante**: la pérdida de un dispositivo (dron caído, celular roto) no pierde datos ya sincronizados ni bloquea a los demás.

## 3. Requisitos de plataforma

| Componente | Requisito | Fuente |
|---|---|---|
| App piloto | Android en el RC del DJI Agras (pantalla propia del control); Flutter flavor `piloto` | espec §2, ADR 0005 |
| App auxiliar | Android en celular personal; Flutter flavor `auxiliar` | espec §2 |
| Conectividad | Starlink en base; lote sin señal; sync al volver | campo + espec §2 |
| Backend | Laravel + PostgreSQL 16, API REST | ADR 0001 |
| Panel/portal | AdminLTE + Blade/Livewire, guards separados | ADR 0002 |
| Distribución app | APK con `GET /api/version` (sin store, sin FCM) | espec §16 |
| Flota a soportar | T30, T50 (operados hoy); T70, T100 (previstos) — volúmenes de carga por modelo parametrizados | campo 25/8/2026 |

## 4. Trazabilidad de origen

Cada RF nace de una respuesta de campo o de la especificación; el mapa hallazgo → clasificación → impacto vive en `docs/gestion/respuestas_campo/analisis_clasificacion.md`. Los RF marcados como ajustes al modelo de datos se consolidarán en la especificación §4 cuando lleguen las capturas del RC (pantallas reales del flujo DJI).

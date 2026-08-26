# Análisis consolidado de las respuestas de campo

**Agrocom SRL · Documento de trabajo vigente · Respuestas del 25/8/2026, procesadas el 26/8/2026**

Ejecuta el "circuito de cierre" del banco de preguntas (`banco_preguntas_por_rol.md`): clasifica cada hallazgo en **CONFIRMADO / CORREGIDO / DESCUBIERTO**, completa la matriz de límites y deja la agenda de la reunión de cierre. El detalle por rol vive en `docs/negocio/politicas/`; los documentos transversales derivados en `docs/negocio/` y `docs/especificacion/`.

**Encuestados** (14 respuestas en los 5 cuestionarios, 6 personas): Josue Haenke (piloto, 3–4 campañas), Miguelito Justiniano Dorado (piloto/auxiliar/jefe, 1), Abraham Gutiérrez Contreras (auxiliar/jefe/"agrónomo", 4), David Omar Ríos Lino (auxiliar/jefe, 2), Jorge Richard Scheidel Dorado (encargado, 1), y **Carlos Ferrufino** ("carlos"/"carlos f": piloto, jefe, encargado y agrónomo — es el dueño; sus respuestas llevan la doble gorra). **Nadie del lado del cliente real respondió**: el rol agrónomo y el rol cliente quedan derivados, no validados.

---

## 1. CONFIRMADO — la especificación ya lo modela bien

| # | Hallazgo | Espec |
|---|---|---|
| CF-01 | La captura del RC como evidencia de cierre es hábito instalado; zanja disputas ("ninguna discusión, porque se sacan capturas") | §4.3, §5 |
| CF-02 | Validación por tercero contra el RC: capturas + inicio de sesión; "los pilotos no pueden mentir, todo queda en el control" | §5 |
| CF-03 | Relevo/lote a medias: se resuelve con reemplazo y las hectáreas de cada uno constan — respalda `hectarea_inicial_acumulada` | §5 |
| CF-04 | Ciclo de vuelo 10–12 min por batería/tanque; ~5 tanques/hora (cierra el [validar] de ventana §4.2) | §7.1 |
| CF-05 | 3 baterías por dron en rotación etiquetada alcanzan; carga rápida 8 min < vuelo | ventana §4.2 |
| CF-06 | División piloto (vuela) / auxiliar (batería, generador, caldo) es clara y aceptada | §3 |
| CF-07 | Responsabilidad del caldo = de quien lo preparó (unánime) | §7.4 |
| CF-08 | Sobrante a las cortinas; envases devueltos/ordenados al cliente | §4.3 `sobrantes` |
| CF-09 | 10 L/ha estándar; más litros ⇒ recargo (regla comercial explícita) | §4.3 |
| CF-10 | Asignación de lotes por experiencia: el más experimentado al más difícil (unánime — cierra el [validar] de ventana §4.1) | ventana §4.1 |
| CF-11 | Ciclo de daños de batería (pines → placa → demás baterías) respalda la alerta "dron sospechoso" | §10 |
| CF-12 | Repuestos: circuito ciudad→campo 24–48 h típico; críticos: hélices, motores, bombas, baterías | §12 |
| CF-13 | Rendición sin comprobante con respaldo fotográfico (gasolina de reventa); litros separados del precio | §4.4 |
| CF-14 | Anticipos: los aprueba el dueño "mediante lo trabajado" ("él aprueba y yo doy") | §3, §11 |
| CF-15 | Multi-rol con login único no es teórico: el dueño hoy cumple 4–5 roles; validador ≠ piloto por persona es imprescindible | §3, ADR 0004 |
| CF-16 | Órdenes llegan verbales/WhatsApp/papel — justifica formalizarlas con evidencia del original | §4.3 |

## 2. CORREGIDO — la especificación dice X, el campo hace Y

| # | La espec dice | El campo hace | Impacto |
|---|---|---|---|
| CR-01 | La mezcla la prepara el auxiliar con checklist en su app (§7) | **La prepara el cliente** (su agrónomo/personal) en el escenario dominante; Agrocom a veces solo la carga y sugiere qué no mezclar | **ALTO** — mezcla con `origen` (cliente/Agrocom); checklist solo en origen Agrocom; flujo de recepción en el otro. Decisión de negocio previa: ¿Agrocom toma la preparación en modalidad residente? |
| CR-02 | Temperatura límite ≤30 °C (ventana §4.1) | El campo opera con <40 °C; lo que manda son las **ventanas horarias** (6–10 y 16–20: la gota se pulveriza con sol fuerte) | Límites como **parámetros por contrato/orden**; posible doble umbral (operativo vs. agronómico) |
| CR-03 | Viento ≤17 km/h constante | Ráfagas ~20 km/h como umbral práctico; un cliente exigió además velocidad de vuelo ≤15 km/h | Parámetro por contrato/orden, no constante global |
| CR-04 | Quién reanuda tras pausa: jefe (ventana §7) | Cuatro versiones distintas (agrónomo / cliente / piloto con anemómetro / piloto+cliente); consenso solo en que **el piloto siempre puede parar por seguridad del dron** | Regla a cerrar en reunión; el veto de seguridad del piloto sí es firme |
| CR-05 | Flota T50/T70/T100 (§4.2) | Se opera **T30** y se menciona T70P; T100 aún no operado por este equipo. Hubo dos caídas relatadas: el T30 y el T50 de noche | Catálogo de modelos + volúmenes por modelo parametrizados |
| CR-06 | Acta por lote inmediata (§9, ventana §2.2) | El agrónomo (Carlos) acepta firmar "ese día **o al terminar la aplicación completa** para aprovechar la ventana" | Firma agrupable; tensiona el racional comercial del acta inmediata — decisión del dueño |
| CR-07 | Rendimiento de flota ~600–800 ha/día (ventana §4.2) | T50: 120 (dos fuentes) a 160–180 ha/día (una); "buen día" desde 60 ha/dron; día malo 10–30 ha | Recalibrar proformas con los números conservadores hasta medir con sesiones reales |
| CR-08 | Reporte al conformar el lote / cerrar aplicación (§9) | Se reporta al superar ~500 ha o a pedido, PDF de la nube DJI | Sumar reporte de avance por umbral configurable |
| CR-09 | Encargado registra cobranza/factura (§3) | "Lo maneja el dueño con trato directo"; el encargado ni conoce el dato | Mantener permiso, registrar práctica solo-dueño |
| CR-10 | EPP confirmado al iniciar mezcla (§7.3) | "Normalmente no te dan equipos de protección" | Decidir política de provisión antes de exigir la confirmación (si no, registro falso) |
| CR-11 | Temperatura de batería medida con termómetro IR (ventana §4.2) | Se detecta **al tacto**; el RC avisa tarde o no avisa | Dotar termómetro o admitir registro cualitativo en v1 |

## 3. DESCUBIERTO — no estaba escrito

| # | Hallazgo | Decisión v1 propuesta |
|---|---|---|
| DS-01 | **Pausas y demoras nunca se registran** — clima, agua/químicos del cliente que no llegan, cambios de lote ordenados; es "la información que falta siempre" (encargado) y donde "se pierden horas" | **Entra en v1**: entidad `pausas` con causa atribuible (incluye `imprevisto_del_cliente`) |
| DS-02 | **Encargado de la propiedad**: actor del cliente que indica lotes, prepara caldo, ordena pausas, recoge envases | Entra en v1 como contacto operativo del cliente |
| DS-03 | Ventanas horarias 6–10 / 16–20 moduladas por cliente; vuelo nocturno real con turnos día/noche; sereno como límite | Entra en v1 como parámetros por contrato |
| DS-04 | Mapeo: solo con luz de día; los obstáculos marcados son la constancia actual de superficie no aplicada | Entra al flujo (precondición de vuelo nocturno); campos exactos esperan las capturas del RC |
| DS-05 | Caso "caldo lodo": cliente ordena cambio de lote, el caldo espera 3–4 días y se pierde; se defendió con "reporte con pruebas" ad hoc | Cubierto por DS-01 + estado `suspendido_por_cliente` del trabajo |
| DS-06 | El piloto a veces **cobra directo** al cliente en trabajos spot; existen "aplicaciones de emergencia" para otros clientes con precio negociado | v1 registra el cobro; la gestión comercial multi-cliente completa queda anotada (espec §16 ya admite el modelo) |
| DS-07 | Limpieza diaria del dron (bombas, centrífugas, pines, boquillas, lavado) como rutina que hoy depende de cada auxiliar | Checklist de cierre de jornada (módulo Mantenimiento) |
| DS-08 | Caída de RPM de bombas/centrífugas como detector de grumos | Nueva señal/subtipo de incidencia; candidata a alerta §10 |
| DS-09 | Salud del personal: intoxicaciones y enfermedades en campaña; deseo explícito de que se cuide la salud | Tipo de incidencia `salud_personal`; política de EPP pendiente (CR-10) |
| DS-10 | Escalera de autorización de gastos: jefe ~500 Bs → encargado ~1.000 Bs → dueño | Parámetro por rol en v1 |
| DS-11 | Pago al personal por QR; rendiciones con capturas de QR | Medio de pago + evidencia `captura_qr` |
| DS-12 | Pedido de repuestos con tramo de envío propio (trufi/encomienda, 4–48 h); proveedores: Agropix, Agrosolución, NP Agro | Máquina `pedidos_repuesto` (fase 5) |
| DS-13 | El dueño pide un **estado de resultados básico**; hoy lento de armar | Dashboard fase 8 (ya previsto, ahora con nombre) |
| DS-14 | Jefe pide avance "gráfico en un mapa" (el GeoJSON de lotes ya existe) | Vista mapa fase 8 |
| DS-15 | Batería mínima de vuelo ~5% usada como límite práctico | Parámetro operativo; alerta candidata |
| DS-16 | **Boleo** (esparcido de sólidos) con T30 como línea de servicio | Anotado — decidir si v1 lo modela como tipo de aplicación |
| DS-17 | Parámetros de vuelo (altura 2–5 m, velocidad 20–25 km/h, ancho 4–8 m) se acuerdan piloto+agrónomo y no quedan en la orden | Campos en la orden de aplicación |
| DS-18 | Lote abandonado (1 ha) por ausencia de contraparte del cliente en el lugar | Motivo de no aplicación nuevo |
| DS-19 | Deseos del agrónomo: papel hidrosensible como evidencia, verificación a las 24 h | Evidencia adjunta admitida; flujo formal fuera de v1 |

## 4. Matriz de límites completada (Ejecuta / Decide / Registra / Valida)

⚠ = celda en conflicto → reunión de cierre.

| Proceso | Ejecuta | Decide | Registra | Valida |
|---|---|---|---|---|
| Emisión de orden | Agrónomo cliente | Agrónomo cliente | Hoy nadie (verbal/WhatsApp) → sistema | — |
| Planificación del día | Jefe de campo | Jefe **con agrónomo del cliente** ⚠ (¿o encargado?) | Hoy nadie → panel | — |
| Preparación de mezcla | **Cliente** (dominante) / auxiliar ⚠ | Agrónomo cliente (receta) | Hoy nadie → app auxiliar | Quien preparó responde |
| Autorización por clima | Piloto pausa | ⚠ 4 versiones; consenso: piloto siempre puede parar por seguridad | Anemómetro, sin constancia → sistema | Agrónomo firma si se fuerza |
| Apertura/cierre de sesión | Piloto | Piloto | RC + capturas → app RC | Jefe |
| Recarga y batería | Auxiliar | Auxiliar | Hoy nada ("todo queda en el dron") → app auxiliar | — |
| Superficie no aplicada | Piloto (marca obstáculos) | Piloto + agrónomo | Queda en el mapeo del RC → declarar al cierre | — |
| Cierre de lote y hectáreas | Piloto | — | Captura RC | Jefe/encargado |
| Validación de hectáreas | Jefe | Jefe (≠ piloto, por persona) | Capturas + inicio de sesión | — |
| Acta de conformidad | Piloto presenta | Agrónomo firma; momento ⚠ (lote vs. aplicación) | Sistema | — |
| Gastos de campo (caja chica) | Jefe | Jefe ≤ ~500 Bs ⚠ (montos difieren) | Excel/fotos/QR → sistema | Encargado |
| Compra y envío de repuestos | Encargado | Encargado ≤ ~1.000 Bs; dueño arriba ⚠ | Excel → sistema | — |
| Anticipos | Encargado entrega | **Dueño** | Hoy memoria → sistema | Dueño |
| Reporte al cliente | Piloto (capturas) / Encargado (PDF nube) | Umbral ~500 ha o a pedido | WhatsApp/nube → sistema | — |
| Facturas y cobros | Dueño | Dueño | ⚠ espec dice encargado | — |

## 5. Números en conflicto (a cerrar con datos, no a discutir)

| Dato | Versiones | Uso propuesto |
|---|---|---|
| Rendimiento T50/día | 120 (Carlos, Miguelito) · 160–180 (Abraham) | Proformas con el conservador; el sistema lo medirá por sesión |
| Rendimiento T100/día | 170 · 200 (8 h) · 300 (solo lotes enormes) | Ídem |
| Día malo | 10–20 ha · 30 ha | Rango |
| Viento límite | 17 · ráfagas 20 · cliente impone 15 | Parámetro por contrato |
| Temperatura límite | ≤30 °C (espec) · <40 °C (campo) | Doble umbral a definir |
| Caja chica jefe | 500 Bs (única fuente) | Confirmar y parametrizar |
| Gasolina reventa | 10 Bs/L · 15 Bs/L | Rango real; se registra por compra |
| Consumo generador | 20 L/4 h · 30 L/día · 50 L/día (jornada 5–22) | Depende de jornada; medir |
| Repuesto en llegar | 4 h · 24 h · 48 h | Depende de distancia; medir por pedido |

## 6. Estado de los supuestos de la especificación §16

| Supuesto | Estado |
|---|---|
| Desvío de mezcla ±5% | **CERRADO: aceptado** |
| Formato de firma (pantalla o papel fotografiado) | **CERRADO: cualquiera de las dos** |
| Tolerancia de solape | Semicerrado: franja ~9 m, "pocas veces se solapa" → tolerancia baja; valor numérico pendiente de las capturas |
| App en el RC Android | Sin objeción; pendiente spike de hardware |
| Un cliente contratante en v1 | Matizado: las aplicaciones de emergencia multi-cliente existen ya (DS-06) |
| Vuelo nocturno | **CERRADO: existe**, con turnos; límite = sereno; mapeo previo de día |
| Temperatura batería >50 °C | Sigue abierto: hoy se mide al tacto (CR-11) |

## 7. Agenda de la reunión de cierre (solo conflictos y vacíos)

1. **Mezcla** (CR-01): ¿Agrocom asume la preparación en modalidad residente o se modela el escenario cliente como primario? Define el módulo entero.
2. **Clima** (CR-02/03/04): cerrar la regla de parar/reanudar/forzar y los umbrales por defecto; ratificar el veto de seguridad del piloto.
3. **Acta** (CR-06): ¿por lote inmediata o agrupable? Recordar el racional comercial de ventana §2.2 antes de ceder.
4. **Escalera de montos** (DS-10) y naturaleza caja chica vs. viáticos.
5. **Reparto de lotes feos**: cuatro posturas (experiencia siempre / turnarse / ambos juntos / bono ~10% de Carlos) — decidir con los pilotos (ventana §6.3).
6. **EPP y salud** (CR-10/DS-09): política de provisión.
7. **Boleo** (DS-16): ¿entra en v1?
8. Pedir a Abraham el orden general de mezcla que "tiene guardado".
9. Rendimientos: aceptar que se medirán con el sistema; fijar los números de proforma mientras tanto.
10. **Cliente sin agrónomo** (trabajos spot / clientes chicos): ¿quién firma el acta? ¿El encargado de la propiedad tiene usuario propio en el portal (guard `cliente`)? — roza ADR 0004, decidir antes de la fase 2.
11. Formalizar en bitácora las aprobaciones hoy verbales del dueño (anticipos, gastos mayores) — hoy no dejan rastro y los cobros viven solo en su cabeza.

## 8. Pendientes que bloquean la consolidación del modelo de datos

- ~~**Capturas del RC**~~ **RESUELTO (26/8/2026)**: 21 capturas recibidas y analizadas en `docs/especificacion/analisis_capturas_rc.md` (crudas en `capturas_rc/`). Quedan 5 preguntas de semántica fina para la reunión de cierre (tasa 100% vs. pendiente, alcance de tiempo/litros, colores de pasadas, marca "M", carta corta vs. larga).
- **Voz real del cliente**: ni el agrónomo ni el dueño del campo respondieron; validar con ellos lo derivado en `politicas/rol_agronomo.md` y `politicas/rol_cliente.md`.
- La actualización de `especificacion_funcional_tecnica.md` (§3, §4, §5, §7, §9, §10, §16) se hace **después** de la reunión de cierre, en una iteración dedicada.

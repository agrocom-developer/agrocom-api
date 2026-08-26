# Políticas y lógica de negocio — Rol: Dueño de Agrocom

**Agrocom SRL · Documento derivado · Insumo para actualizar `docs/especificacion/especificacion_funcional_tecnica.md`**

Este rol **no tuvo cuestionario propio**. Se deriva de dos fuentes: (1) las respuestas de **Carlos Ferrufino** ("carlos" / "carlos f"), dueño de Agrocom, que respondió los cuestionarios de **piloto, jefe de campo, encargado de operaciones y agrónomo** (25/8/2026) porque en la operación real cumple todos esos roles — sus respuestas son la voz directa del dueño; y (2) las menciones al dueño en las respuestas de los demás encuestados (Jorge Richard Scheidel Dorado — encargado de operaciones; Abraham Gutiérrez Contreras; David Omar Ríos Lino; Miguelito Justiniano Dorado; Josue Haenke), contrastadas con `docs/negocio/ventana_al_negocio.md` (§1, §2, §6, §7) y la especificación (§1, §3, §9, §11, §13, §16).

**Estado**: insumo de trabajo. Cada política lleva su fuente; lo que no tiene fuente de campo ni especificación queda marcado como **pregunta abierta** — no se asume.

---

## 1. Misión del rol y límites

**Misión**: el dueño es el punto donde convergen la plata y el riesgo. Todo lo que mueve dinero por encima de un umbral (anticipos, gastos mayores, planilla, facturas, cobros, precio de trabajos nuevos) pasa por él; y todo conflicto que compromete la relación con el cliente o la integridad de la flota escala a él. Le importa el margen por hectárea, la salud de la flota, la caja de la campaña y que la trazabilidad lo defienda en los reclamos (`ventana_al_negocio.md` §2.3).

| Dimensión | Contenido |
|---|---|
| **EJECUTA** | El pago final al personal (QR bancario o efectivo con factura); el trato directo con el cliente en facturas y cobros; la negociación de contratos y precios; y — en la operación actual — suplencias en cualquier rol operativo: vuela, planifica, compra, asesora (multi-rol real). |
| **DECIDE** | Todo anticipo (otorgar/negar y monto); todo gasto por encima del umbral del encargado (~1.000 Bs); la aprobación de la planilla; el precio de trabajos nuevos y aplicaciones de emergencia; cancelar o continuar una aplicación tras una falla grave (dron caído); junto con el agrónomo, si se aplica en condiciones al límite; la regla de compensación por lote difícil (decisión pendiente, `ventana` §6.3). |
| **REGISTRA** | Hoy: casi nada — facturas, cobros y criterios de anticipo viven en su cabeza, WhatsApp y Excel (el encargado declara no tener esos datos). En el sistema: cobranzas, facturas, aprobaciones (de anticipo, gasto y planilla) con su bitácora. |
| **VALIDA** | La planilla (solo el dueño aprueba — espec §11). **No valida trabajos/sesiones como dueño** (espec §3: la validación es de jefe de campo y encargado); si valida, lo hace vía su rol de jefe de campo — y nunca sus propias sesiones cuando además voló (regla a nivel de persona, ADR 0004). |

**Celdas en conflicto para la reunión presencial** (matriz Ejecuta/Decide/Registra/Valida del banco de preguntas):

- *Autorización por condiciones (clima)*: Carlos como piloto dice "el agrónomo y el dueño de los drones"; Miguelito (piloto) dice "la última palabra la tiene el cliente"; Josue dice que el piloto para por seguridad del dron aunque el cliente pida seguir. Tres respuestas, tres decisores.
- *Anticipos*: sin conflicto — todos coinciden en que decide el dueño (Jorge, Carlos F.). Lo abierto es si el tope (3.000 Bs / 70% del devengado, `ventana` §6.1) es regla dura o alerta, porque hoy la decisión es discrecional del dueño.

---

## 2. Flujo base: cómo participa en el ciclo

1. **Contrato**: negocia precio, hectáreas, aplicaciones y reparto de aportes con el dueño del campo; recibe el adelanto del 35% (capital de trabajo, no ganancia — `ventana` §2.2). En la operación actual también negocia **trabajos puntuales y aplicaciones de emergencia de otros clientes**, precio por trabajo ("esperar nuevas solicitudes para las aplicaciones de emergencia de otros clientes, negociar precio" — Carlos F., cuestionario encargado).
2. **Orden de aplicación**: no interviene como dueño; interviene como **agrónomo asesor** cuando el cliente no tiene uno diario ("se define en coordinación con el ingeniero Carlos Ferrufino" — Miguelito, piloto) o como jefe de campo planificando la noche anterior.
3. **Aplicación**: recibe los escalamientos — falla grave ("directamente con el dueño en este caso, si es una falla grave" — Jorge), gastos por encima del umbral, decisiones económicas ("todo lo económico es con el de operaciones" y de ahí al dueño — Carlos F., jefe de campo), condiciones al límite.
4. **Acta**: no la firma él — la firma el agrónomo del cliente. Al dueño el acta le importa como sostén de la factura (`ventana` §2.2).
5. **Factura y cobro**: **trato directo dueño ↔ cliente**. El encargado no conoce ni cuántas facturas hubo ni cuánto tardó el cliente en pagar ("no tengo ese dato, ya que lo maneja el dueño con trato directo con el cliente" — Jorge). El cobro llega a veces de inmediato, a veces a 30 días (Carlos F., encargado).
6. **Planilla y pago**: aprueba la planilla y ejecuta el pago — por QR bancario o en efectivo contra factura/recibo ("el que realiza el pago final es el dueño, aprueba o desaprueba lo solicitado" — Jorge; "se paga directo al beneficiario por QR" — Carlos F.; "carga el QR para cobro de los empleados" — Miguelito, auxiliar).
7. **Cierre / lectura del negocio**: pide "un estado de resultados básico", que hoy es el reporte más difícil y lento de armar (Carlos F., cuestionario encargado, pregunta "¿qué reporte te pide el dueño?").

---

## 3. Políticas y reglas de negocio

- **D-01 — Todo anticipo lo aprueba el dueño.** El encargado puede gestionar o solicitar, pero quien aprueba o desaprueba y paga es el dueño. *Fuente: Jorge ("pago y anticipos directamente con el dueño"); Carlos F., encargado ("se la solicitan al dueño y él aprueba y yo doy"). Coincide con espec §3 y `ventana` §6.1.*
- **D-02 — El criterio de anticipo es doble: lo trabajado y el proyecto en curso.** El dueño decide "mediante lo trabajado y mediante el proyecto que lleva realizando" (Jorge) — es decir, no solo el devengado acumulado sino también el trabajo futuro comprometido de esa persona. *La espec solo modela tope y % del devengado; este segundo criterio es discrecional del dueño.*
- **D-03 — Anticipo mayor a lo ganado: decide el dueño, caso por caso.** *Fuente: Jorge ("no tengo ese dato preciso... él decide"); Carlos F., encargado ("el dueño decide"). La espec §10 lo trata como alerta ("Anticipo al límite: acumulado > 70% del devengado") — pregunta abierta: ¿tope duro o alerta que el dueño puede sobrepasar dejando rastro?*
- **D-04 — Escalera de aprobación de gastos: ~500 Bs (jefe de campo) → ~1.000 Bs (encargado) → dueño.** El jefe de campo gasta de caja chica hasta 500 Bs sin autorización (Carlos F., jefe de campo); el encargado aprueba "gastos menores a 1.000 Bs, después se consulta con el dueño" (Carlos F., encargado); las "cosas particulares como piezas de dron o algún gasto ostentoso" van al dueño (Jorge). *La espec no parametriza estos umbrales — ver §6.*
- **D-05 — Facturación y cobranza son trato directo dueño ↔ cliente.** Nadie más en la empresa tiene hoy ese dato. *Fuente: Jorge. Coincide con espec §3 (registrar cobranza/facturar: encargado y dueño) — pero en la práctica actual ni el encargado lo ve.*
- **D-06 — El dueño aprueba la planilla y ejecuta el pago, por QR o efectivo con comprobante.** Las rendiciones y pagos se respaldan con capturas de QR, y con facturas cuando el pago es en efectivo. *Fuente: Jorge; Miguelito, jefe de campo ("se rinde mediante capturas de QR y en caso de que solo se pague por efectivo, por facturas"); Carlos F., encargado. Espec §11: "solo el dueño aprueba" — CONFIRMADO; el medio de pago no está modelado — ver §6.*
- **D-07 — El dueño negocia precio y toma trabajos de emergencia de otros clientes.** La semana del encargado (que es el propio Carlos) incluye "esperar nuevas solicitudes para las aplicaciones de emergencia de otros clientes, negociar precio". *Fuente: Carlos F., encargado.*
- **D-08 — Las emergencias graves escalan directo al dueño.** *Fuente: Jorge ("directamente con el dueño en este caso, si es una falla grave") — caso del T50 caído de noche, §4.*
- **D-09 — Los reclamos de terceros (deriva) se resuelven entre dueños.** "Resolver sería entre los dueños [del terreno afectado] y el dueño del dron, tratar de llegar a un acuerdo" — con riesgo de denuncia por daños y perjuicios. *Fuente: Abraham, cuestionario agrónomo. Coincide con `ventana` §7 (deriva: la resuelve dueño/jefe, con el cliente; la defensa es preventiva — restricciones declaradas antes y viento registrado al aplicar).*
- **D-10 — El dueño lee el negocio por un estado de resultados básico** — hoy lento y difícil de armar desde Excel. *Fuente: Carlos F., encargado. Corresponde a la función 8 del alcance (contabilidad básica) + dashboard (fase 8).*
- **D-11 — Multi-rol real: el dueño ejerce todos los roles según necesidad.** Carlos respondió como piloto, jefe de campo, encargado y agrónomo porque de hecho lo es; además suple pilotos que faltan ("lo suplo hasta que llegue otro piloto" — Carlos F., jefe de campo). *El modelo de un usuario / un login / múltiples roles (espec §3, ADR 0004) existe exactamente para esto.*
- **D-12 — Cuando el dueño vuela, no valida sus propias sesiones.** La regla "validador ≠ piloto de esa sesión, a nivel de persona — no de rol" (espec §3 nota ¹, ADR 0004, invariante 4 de `CLAUDE.md`) se vuelve **crítica** por D-11: si Carlos vuela y Carlos es también el jefe/encargado, sus sesiones las tiene que validar otra persona (p. ej. Jorge). *Fuente: derivación directa de D-11 + espec.*
- **D-13 — En condiciones al límite deciden el agrónomo y el dueño de los drones.** *Fuente: Carlos F., piloto ("¿quién lo decidió? el agrónomo y el dueño de los drones"). En conflicto con otras respuestas — ver matriz en §1.*
- **D-14 — Solo el dueño ve la línea de ganancia.** El encargado ve costos y márgenes pero "sin acceso a la línea de ganancia". *Fuente: espec §3 nota ². Consistente con D-05: hoy la información comercial fina vive solo en el dueño.*
- **D-15 — Nadie salvo el dueño crea o modifica usuarios con rol dueño.** *Fuente: espec §3 nota ⁵.*
- **D-16 — La aversión al riesgo sobre la flota es del dueño y baja como cultura.** "Todas [las decisiones] se consultan, el dron es lo más caro" (Carlos F., piloto). Ante falla grave el dueño decide entre enviar repuesto o "cancelar la aplicación y traer el dron y el equipo dañado si es una caída" (Carlos F., encargado).

---

## 4. Excepciones y casos reales relatados

- **El T50 caído de noche** (Jorge): fumigando de noche con Miguelito se cayó el T50; lo buscaron 4 horas en la oscuridad sin encontrarlo — estaba en un maizal de 1,75 m; apareció al día siguiente. Total: 1 día y 4 horas para volver a la ciudad y llevar **otro dron** para terminar el trabajo. La decisión de reemplazar el dron y absorber el costo fue con el dueño (falla grave → D-08, D-16).
- **La suplencia del dueño**: cuando un piloto se enferma o renuncia, "lo suplo hasta que llegue otro piloto" (Carlos F., jefe de campo). El dueño volando es el caso límite del modelo de seguridad (D-12).
- **Gasolina revendida**: escasez cubierta con reventa a 10 Bs/l (Carlos F.) y hasta 15 Bs/l (Jorge) contra ~7 oficial; sin recibo ni factura — la rendición se respalda con **foto de los bidones comprados** (Jorge). El sobreprecio lo absorbe la empresa y hoy queda registrado solo como foto + Excel.
- **Anticipos sin regla escrita**: el encargado no puede responder qué pasa cuando alguien pide más de lo que ganó — "el anticipo es directamente con el dueño, no tengo ese dato preciso" (Jorge). La regla existe solo en la cabeza del dueño (D-02/D-03).
- **El estado de resultados que no sale**: gastos en Excel por descripción/categoría/monto, ingresos por hectárea en otro Excel, cobros en la cabeza del dueño → armar el estado de resultados que el dueño pide es hoy el reporte más difícil (Carlos F., encargado).

---

## 5. Números de calibración

| Concepto | Valor | Fuente |
|---|---|---|
| Caja chica del jefe de campo sin autorización | 500 Bs | Carlos F., jefe de campo |
| Umbral de aprobación del encargado | ~1.000 Bs (arriba: dueño) | Carlos F., encargado |
| Tope de anticipo | 3.000 Bs/mes y 70% del devengado | `ventana` §6.1 (sin confirmación de campo del número; la práctica es discrecional — D-02/D-03) |
| Plazo de cobro del cliente | inmediato o 30 días | Carlos F., encargado |
| Gasolina | oficial ~7 Bs/l; reventa 10 Bs/l (Carlos F.) a 15 Bs/l (Jorge) | encargados |
| Repuesto pedido → campo | típico 24 h; peor caso 48 h (Carlos F. y Miguelito coinciden en 24–48 h); 4 h si el campo está cerca (Cuatro Cañadas / Tres Cruces — Jorge) | encargados, jefe de campo |
| Repuestos más comprados | bombas, hélices, baterías; mayor desgaste: hélices y motores | Carlos F., Jorge |
| Días volables por mes | 15–16 (Carlos F. y Miguelito, jefes de campo); "de 4 días operativos, 1 al menos es de lluvia" (Carlos F., piloto) | jefes de campo |
| Rendimiento diario | T50: 120–180 ha; T100: 170–300 ha (170 Carlos F., 200 Miguelito, "hasta 300 en terrenos muy grandes" Abraham) | jefes de campo |
| Tarifas del personal variable | piloto 7 Bs/ha validada; auxiliar 4,25 Bs/ha validada | espec §11, `ventana` §6.1 |
| Economía de campaña (escenario base) | 65 Bs/ha; adelanto 35%; margen vivo 11,75 Bs/ha; día de flota parada ≈ 7.000–9.400 Bs de margen no generado | `ventana` §1 |
| Umbral de reporte al cliente | al superar ~500 ha o a pedido | Jorge |

---

## 6. Clasificación contra la especificación

| Hallazgo | Clasificación | Sección espec | Impacto |
|---|---|---|---|
| Solo el dueño aprueba anticipos, planilla y pago final | **CONFIRMADO** | §3, §11 | Ninguno — la espec ya lo modela. |
| Multi-rol real (dueño = jefe + encargado + piloto + agrónomo asesor) con login único | **CONFIRMADO** | §3, ADR 0004 | Ninguno en modelo; **sube la prioridad de probar** la unión de roles y el caso extremo "una persona con 5 roles". |
| Validador ≠ piloto a nivel de persona, crítico cuando el dueño vuela | **CONFIRMADO** | §3 nota ¹, ADR 0004 | Test obligatorio: usuario dueño+jefe+piloto no puede validar sus propias sesiones por ninguno de sus roles. |
| El dueño necesita un estado de resultados básico (hoy imposible de armar rápido) | **CONFIRMADO** | §1 (función 8), §15 (fase 8) | Priorizar en el dashboard el estado de resultados: ingresos − gastos por rubro, por campaña/aplicación. |
| Umbrales de aprobación de gastos (500 Bs jefe / ~1.000 Bs encargado / dueño) | **CORREGIDO** | §3 (permisos de gasto) | La espec da permisos binarios; el campo opera con **umbrales por rol**. Agregar parámetros de monto máximo por rol al flujo de gastos, con escalamiento al dueño por encima. |
| El criterio de anticipo incluye "el proyecto que lleva realizando", no solo el devengado | **CORREGIDO** | §10 (alerta), `ventana` §6.1 | La guarda del sistema debe ser **informativa** (mostrar devengado, anticipos acumulados y trabajo asignado vigente) y la decisión final del dueño, con motivo cuando sobrepasa el 70%. Definir en reunión: ¿tope duro o alerta? |
| Medio de pago QR / efectivo con factura, y captura de QR como comprobante | **DESCUBIERTO** | §11 | Agregar a la planilla y a las rendiciones: `medio_pago` (QR/efectivo) y evidencia adjunta (captura QR o foto de factura). Sin esto, la conciliación del pago sigue fuera del sistema. |
| Trabajos puntuales y aplicaciones de emergencia de otros clientes, con precio negociado por trabajo | **DESCUBIERTO** | §16 (supuesto "un solo cliente contratante en v1") | El supuesto queda corto frente a la práctica: la operación real es multi-cliente con trabajos spot además de la campaña residente. El modelo ya admite varios contratos (§16) — hace falta que el flujo comercial (precio por trabajo, cobro inmediato) no asuma campaña. |
| Cobros y facturas viven solo en el dueño; el encargado no tiene visibilidad | **DESCUBIERTO** | §1 (función 6), §3 | El módulo de ingresos debe hacer visible el contratado → aplicado → facturado → cobrado, hoy inexistente fuera de la cabeza del dueño. Riesgo actual: bus factor = 1. |
| Aprobación de gasto mayor: hoy verbal (WhatsApp/llamada), sin rastro | **DESCUBIERTO** | §14.1 | La aprobación del dueño debe quedar en bitácora (quién aprobó, cuándo, qué monto) — hoy no queda en ningún lado. |

*Nota de circuito: ninguno de estos ajustes toca una regla con ADR ya emitido, salvo la confirmación del modelo multi-rol (ADR 0004), que no cambia — se refuerza. Si al implementarse D-12 se detectara necesidad de cambiar ADR 0004, avisar a arquitectura antes de tocar la espec.*

---

## 7. Oportunidades de automatización / sistematización

1. **Estado de resultados en un clic** (la petición explícita del dueño): ingresos por contrato/trabajo − gastos por rubro = margen, por campaña y por aplicación. Es la función 8 del alcance; las respuestas le dan nombre y dolor concreto.
2. **Pantalla de aprobación de anticipos con contexto**: al pedir un anticipo, el dueño ve devengado acumulado, anticipos ya otorgados, % consumido y trabajo asignado vigente — sistematiza D-02 sin quitarle la decisión.
3. **Flujo de aprobación de gastos por umbral**: gasto > umbral del rol → solicitud al dueño → aprobación con bitácora. Reemplaza el WhatsApp/llamada actual.
4. **Registro de cobranzas y facturas** con estado por contrato (facturado/cobrado/pendiente, días de mora) — saca del bus factor la información más sensible del negocio.
5. **Planilla con medio de pago y comprobante**: aprobar → pagar por QR → adjuntar captura → conciliado. Cierra el ciclo que hoy termina en un chat.
6. **Alertas por excepción** (§10 espec): el dueño no revisa todo — le llega el anticipo al límite, el gasto fuera de umbral, la rendición vieja, el dron sospechoso.
7. **Registro de pausas y causas imputables** (pedido de Jorge, ver rol cliente C-08): sin eso, el reporte comercial no puede demostrar que la demora fue del cliente.

---

## 8. Qué usa este rol y en qué superficie

**Panel web** (espec §2: jefe de campo, encargado y dueño usan el panel):

- Aprobaciones: anticipos, gastos sobre umbral, planilla (única persona con "Aprobar planilla" — §3).
- Dashboard / estado de resultados: costos, márgenes y **línea de ganancia** (exclusiva del dueño — §3 nota ²).
- Ingresos: facturación y cobranzas (comparte permiso con el encargado, pero hoy lo ejerce solo él).
- Planilla de pagos: generar, aprobar, registrar pago (QR/efectivo + comprobante).
- Gestión de usuarios: único que administra usuarios con rol dueño (§3 nota ⁵).
- Bitácora de auditoría: consulta de quién hizo qué (ADR 0007).

**App piloto (RC)**: cuando vuela — con su mismo login y su rol piloto (D-11). Sus sesiones entran al circuito normal: cierre con captura, validación por **otra persona**, devengo si corresponde.

---

## 9. Vacíos que solo una conversación directa puede cerrar

Aunque Carlos respondió cuatro cuestionarios, ninguno le preguntó *como dueño*. Pendientes para conversación directa:

1. El umbral de 1.000 Bs del encargado: ¿es regla que quiere fijar en el sistema o costumbre negociable? ¿Y los 500 Bs del jefe?
2. Tope de anticipos (3.000 Bs/mes, 70% devengado): ¿regla dura que el sistema bloquea, o alerta que él puede sobrepasar con motivo registrado?
3. El "estado de resultados básico": ¿qué líneas exactas quiere ver (por campaña, por aplicación, por cliente, por dron)? ¿Con qué frecuencia?
4. ¿Cuando el dueño vuela y el encargado no está disponible, quién valida sus sesiones? ¿Acepta que queden pendientes sin devengar hasta que alguien más valide?
5. Precio de trabajos puntuales: ¿hay un piso por hectárea, un cargo por distancia/movilización ("recorrer 300 km por 100 ha es mal negocio" — `ventana` §2.1)? ¿Quiere que el sistema lo sugiera?
6. ¿Se cobra los trabajos chicos en campo (el piloto cobra al cierre — ver rol cliente C-09) o quiere centralizar el cobro? ¿Cómo entra ese dinero al registro?
7. Compensación por lote difícil: las tres opciones de `ventana` §6.3 siguen abiertas; su propio jefe de campo (él mismo) propuso "un extra de pago por lote, tal vez un 10% adicional según el lote". ¿Se cierra con los pilotos?
8. ¿Qué visibilidad quiere darle al encargado sobre facturas y cobros (hoy: ninguna)?
9. Pago al personal: ¿todo por QR con comprobante en el sistema, o el efectivo seguirá existiendo? ¿Quién carga la captura?
10. ¿La planilla incluye a la gente del cliente cuando Agrocom hace tareas que eran del cliente (echar la calda — ver rol cliente)? ¿Se factura aparte?

---

## 10. Términos candidatos al glosario

| Término | Definición propuesta | Fuente |
|---|---|---|
| **QR (pago por QR)** | Transferencia por código QR bancario, medio de pago habitual al personal y de rendición (la captura del QR es el comprobante) | Jorge; Miguelito; Carlos F. |
| **Estado de resultados básico** | Reporte ingresos − gastos por rubro que el dueño pide por campaña/aplicación; hoy se arma a mano desde Excel | Carlos F., encargado |
| **Trabajo puntual / aplicación de emergencia** | Servicio spot para un cliente fuera del contrato de campaña, con precio negociado por trabajo y cobro usualmente inmediato | Carlos F., encargado |
| **Reventa (gasolina revendida)** | Compra de combustible fuera del canal oficial en escasez (10–15 Bs/l vs ~7 oficial), sin comprobante fiscal — se respalda con foto de bidones | Jorge; Carlos F. |
| **Línea de ganancia** | La utilidad del negocio, visible solo para el dueño (el encargado ve costos y márgenes sin esa línea) | espec §3 nota ² |
| **Trufi / encomienda** | Transporte interurbano usado para enviar repuestos de la ciudad al campo (~4 h a Cuatro Cañadas / Tres Cruces) | Jorge |

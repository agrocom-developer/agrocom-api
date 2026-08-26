# Políticas y lógica de negocio — Rol: Encargado de operaciones

**Agrocom SRL · Documento derivado de respuestas de campo del 25/8/2026 · Insumo para actualizar `docs/especificacion/especificacion_funcional_tecnica.md`**

Fuentes primarias:

- `docs/gestion/respuestas_campo/Cuestionario Encargado de operaciones (Respuestas) - Respuestas de formulario 1.csv` — respondieron **Jorge Richard Scheidel Dorado** (1 campaña) y **Carlos Ferrufino** ("carlos f", 3 campañas — dueño de Agrocom respondiendo desde su experiencia haciendo también de encargado; su respuesta trae la visión del dueño, hay que leerla con ese doble sombrero).
- Instrumento: `docs/gestion/respuestas_campo/banco_preguntas_por_rol.md`, sección "ROL: ENCARGADO DE OPERACIONES".
- Contraste: `docs/especificacion/especificacion_funcional_tecnica.md` (§3, §4.4, §4.5, §9, §10, §11, §12) y `docs/negocio/ventana_al_negocio.md` (§2, §7, §8).

Las citas textuales conservan la ortografía original de las respuestas. Ninguna política de este documento modifica por sí sola la especificación: lo CORREGIDO y DESCUBIERTO se propone en la sección 6 y se cierra en la reunión de la sección 9.

---

## 1. Misión del rol y límites

El encargado de operaciones es **el eslabón de ciudad de la operación**: la base de campo produce hectáreas; el encargado hace que esa producción no se detenga (repuestos, gasolina, dinero) y que quede convertida en información ordenada (gastos, reportes, cobros). Es el destinatario natural de las alertas por excepción de la especificación (§10): no revisa todo, persigue lo anómalo.

Límites según las respuestas:

| Dimensión | Qué hace el encargado | Fuente |
|---|---|---|
| **EJECUTA** | Cotiza, compra, recoge y envía repuestos al campo; consigue gasolina y proveedor de combustible; arma y envía reportes al cliente; entrega anticipos ya aprobados; paga gastos menores (frecuentemente por QR directo al beneficiario) | Ambos |
| **DECIDE** | Gastos menores hasta ~1.000 Bs ("Gasto menores a 1000bs, depues se consutla con el dueno" — Carlos); gasolina, refrigerios y "cosas por menores" (Jorge); elección de proveedor de repuestos y de combustible; **negocia el precio de aplicaciones de emergencia de otros clientes** (Carlos — a confirmar si esa negociación es del rol o del dueño, ver §9) | Ambos |
| **REGISTRA** | Gastos y ventas en Excel (descripción, categoría, monto; precio por ha, cantidad, total a cobrar); evidencia fotográfica de compras sin comprobante | Ambos |
| **VALIDA** | Nada financiero por sí solo: pagos, anticipos, gastos grandes y facturación pasan por el dueño. En la especificación (§3) tiene además permiso de validar trabajos de campo — las respuestas no lo mencionan (vacío para la reunión, §9) | Ambos |

Reglas de límite explícitas:

- **Umbral de gasto propio: ~1.000 Bs.** Por debajo decide y ejecuta; por encima consulta al dueño. Jorge no pone monto pero coincide en el criterio: gasolina y menores los resuelve él, "cosas particulares como piezas de dron o algún gasto ostentoso sería con el dueño". *Conflicto de escalera de montos con el jefe de campo (500 Bs de caja chica) — ver §9.*
- **Anticipos: los aprueba el dueño; el encargado gestiona y entrega.** Carlos, en una frase que vale como política: *"se la solicitan al dueno y el aprueba y yo doy"*. Jorge idéntico: "yo puedo gestionar o solicitar un anticipo o viático, pero el que realiza el pago final es el dueño, aprueba o desaprueba lo solicitado". Anticipo mayor a lo devengado: *"el dueno decide"* — "mediante lo trabajado y mediante el proyecto que lleva realizando" (Jorge).
- **Emergencias graves escalan directo al dueño** (Jorge: "directamente con el dueño en este caso, si es una falla grave").

---

## 2. Flujo base — la semana desde la ciudad

Reconstrucción del caso base combinando ambas respuestas:

1. **Revisar lo aplicado.** Llegar a la oficina y "revisar los lotes aplicados" (Carlos) / "revisar el trabajo que se realizará durante todo el día" (Jorge), con dos insumos: los mensajes de WhatsApp de pilotos y auxiliares, y el avance subido a la **nube de DJI** desde el control remoto del dron.
2. **Esperar órdenes.** "Esperar órdenes de aplicación" del contrato vigente y "esperar nuevas solicitudes para aplicaciones de emergencia de otros clientes, negociar precio" (Carlos) — la operación de ciudad también capta trabajo spot.
3. **Sostener la logística.** Verificar cantidad de gasolina y buscar proveedor de combustible (Carlos); atender pedidos de repuestos del campo: cotizar, comprar, recoger, enviar (ver E-02).
4. **Registrar.** Todo en Excel: por el lado del ingreso, precio por hectárea, cantidad de hectáreas y total a cobrar; por el lado del gasto, descripción, categoría y monto (Carlos).
5. **Reportar.** Armar reportes al cliente con "las capturas del dron y un resumen de excel" (Carlos), descargando el PDF de la nube DJI cuando el avance lo amerita o el cliente lo pide (Jorge).

---

## 3. Políticas y reglas de negocio

**E-01 — Umbral de aprobación de gasto del encargado: ~1.000 Bs.** Gastos menores (gasolina, refrigerios, menudencias) los decide y paga el encargado; por encima de ~1.000 Bs, y en particular piezas de dron o gastos grandes, se consulta al dueño. *Fuente: Carlos (monto), Jorge (criterio sin monto). Estado del monto: en conflicto con la escalera completa — ver §9.*

**E-02 — Circuito de repuestos.** Paso a paso confirmado por ambos: (1) el campo avisa la falla de una pieza del dron; (2) se **verifica si tiene solución en campo** (Carlos — este filtro previo evita compras innecesarias); (3) si no la tiene, se **cotiza** en los proveedores habituales: **Agropix, Agrosolución o NP Agro** (Jorge); (4) se compra y se recoge; (5) se **envía por trufi o encomienda** hasta donde esté el equipo. *Fuente: ambos.*

**E-03 — Tiempos de repuesto.** Típico: 1 día (Jorge) / 48 horas (Carlos). Peor caso: 2 días. Mejor caso: **~4 horas si el equipo está en Cuatro Cañadas o cerca de Tres Cruces** (Jorge) — la distancia de la base a la ciudad es la variable dominante del tiempo de resolución. *Fuente: ambos.*

**E-04 — Anticipos: el dueño aprueba, el encargado gestiona y entrega.** El personal solicita; el encargado canaliza la solicitud; el dueño aprueba o rechaza; el encargado entrega el dinero. Si el anticipo supera lo devengado, decide el dueño caso por caso según lo trabajado y el proyecto en curso. *Fuente: ambos. Coincide con `anticipos.autorizado_por` de la especificación (§4.4) y con el tope del 70% de `ventana_al_negocio.md` §6.1 — aunque ninguna respuesta menciona el tope numérico: a validar.*

**E-05 — Reporte al cliente: al superar ~500 ha o a pedido.** "Mayormente piden cuando se agarra más de 500 hect o si el cliente lo pide" (Jorge). Se arma con el control de hectáreas fumigadas subido a la nube DJI, "se lo descarga y se lo puede mandar por PDF", más un resumen de Excel (Carlos). Mejora pedida desde el propio rol: *"sería bueno agregar imágenes y fotos del trabajo realizado en cada campo"* — exactamente el contenido del reporte técnico de la especificación §9. *Fuente: ambos.*

**E-06 — Rendiciones sin comprobante: se respaldan con fotos.** Cuando la compra no da recibo ni factura (el caso arquetípico: gasolina de reventa), la rendición se respalda con "fotos que están comprando esos utensilios o ese producto — en el caso de la gasolina, mandar foto de los bidones comprados" (Jorge). *Fuente: Jorge. Compatible con `gastos.tiene_comprobante` + `evidencia_id` de la especificación §4.4.*

**E-07 — Pago directo al beneficiario por QR.** La práctica que minimiza el efectivo sin respaldo: "se gasta poco y se paga directo al beneficiario por QR" (Carlos). El pago bancario por QR deja rastro propio aun sin factura — es el complemento urbano de E-06. *Fuente: Carlos.*

**E-08 — Gasolina de reventa: 10–15 Bs/L contra ~7 Bs/L oficial.** En escasez se compra revendida: Carlos pagó 10 Bs/L; Jorge reporta "mayormente revendida a 15 Bs el litro" — "depende de la situación en que nos encontremos en ese momento". La reventa no entrega recibo ni factura (por eso E-06). El sobreprecio hoy **no queda registrado como tal** en ningún lado: se pierde dentro del gasto. *Fuente: ambos. Refuerza la separación litros/precio de `cargas_combustible` (§4.4) y el conflicto "Gasolina" de `ventana_al_negocio.md` §7.*

**E-09 — Cobros del cliente: a veces inmediato, a veces 30 días.** "A veces paga de inmediato, otras veces 30 días" (Carlos). La facturación y el cobro son trato directo del dueño con el cliente: Jorge, como encargado contratado, **no tiene visibilidad** de ese dato ("no tengo ese dato, ya que lo maneja el dueño con trato directo con el cliente"). *Fuente: ambos. Ver conflicto de la celda "Registra cobranza" en §9.*

**E-10 — El avance del campo llega por WhatsApp + nube DJI, y siempre falta lo mismo: las pausas.** El reporte diario lo mandan piloto o auxiliar por WhatsApp, y el avance objetivo se ve en la nube con el reporte del control del dron. Lo que falta siempre, en palabras de Jorge: *"el tema de las pausas realizadas durante la fumigación, ya sea por tema de clima u otros imprevistos de parte de la empresa que nos contrata"*. Las pausas — y en particular las causadas por el cliente — son la información que hoy no existe y que defiende la ventana cuando se estira. *Fuente: ambos. Ver hallazgo D-01 en §6.*

**E-11 — Qué información pide, y a quién.** Al **jefe de campo**: el mapeo y la correcta aplicación a los lotes, cuántas hectáreas hicieron, fotos aplicando el producto y el archivo subido a la nube del control del dron; reporte de avances, capturas y fotografías del dron (Carlos). A **pilotos y auxiliares**: estado del dron dentro y fuera de la fumigación, baterías listas y cargadas, generador y gasolina, y colaborar con el recaudo de información del jefe de campo. *Fuente: ambos.*

**E-12 — Emergencia por falla: repuesto o repliegue.** "Depende del tipo de falla: normalmente se envía el repuesto, o se cancela la aplicación y se trae el dron y el equipo dañado si es una caída" (Carlos). El tiempo de resolución depende del campo: "hay trabajos que están a pocas horas de salir a la ciudad, o están muy lejos y podés tardar horas o incluso días" (Jorge). Falla grave → directo al dueño. *Fuente: ambos.*

**E-13 — Aplicaciones de emergencia de otros clientes: se negocia precio.** Parte de la semana normal según Carlos: esperar "nuevas solicitudes para aplicaciones de emergencia de otros clientes, negociar precio". Ojo: Carlos responde con doble sombrero dueño-encargado; queda abierto si un encargado contratado tendría delegada la negociación de precio (§9). *Fuente: Carlos.*

**E-14 — Registro actual en Excel.** Ingresos: precio por hectárea, cantidad de hectáreas, total a cobrar. Gastos: descripción, categoría, monto. Es el mapa exacto del dato existente para la migración inicial al sistema. *Fuente: ambos (Carlos con la estructura; Jorge confirma "los registros lo llevamos en Excel").*

---

## 4. Excepciones y casos reales

**La caída del T50 de noche (el caso de referencia).** Relato de Jorge: fumigando **de noche** con Miguelito Justiniano, se cayó el dron T50. Lo buscaron **casi 4 horas en la noche** sin encontrarlo; al día siguiente siguieron buscando — estaba dentro de un maíz de **1,75 m de altura**. Total: *"tardamos 1 día y 4 horas"*, volvieron a la ciudad **y se llevaron otro dron para acabar el trabajo**. Lecciones para el sistema: (a) el vuelo nocturno existe (supuesto a validar de `ventana_al_negocio.md` §9.3, aquí confirmado de facto); (b) una caída no es solo una incidencia — es búsqueda, traslado, dron de reemplazo y una aplicación que sigue con otra sesión y otro equipo, tal como modela `sesiones.motivo_cierre = falla_equipo`; (c) el costo real del evento (horas-persona, movilización, ventana) hoy no queda registrado en ninguna parte.

**Escasez de gasolina.** Se resuelve comprando de reventa (E-08), a precio variable según la situación, en bidones, sin comprobante — respaldo por foto de los bidones (E-06). El encargado consigue el proveedor; el sobreprecio queda invisible dentro del gasto.

**Falla grave lejos de la ciudad.** El tiempo de resolución no lo define la falla sino la geografía y el clima: de horas (bases tipo Cuatro Cañadas / Tres Cruces) a días (campos sin acceso rápido). Decisión binaria de Carlos: enviar repuesto o cancelar la aplicación y replegar el equipo dañado.

---

## 5. Números de calibración

| Parámetro | Valor de campo | Fuente |
|---|---|---|
| Repuesto pedido → campo, caso típico | 1 día (Jorge) / 48 h (Carlos) | Ambos |
| Repuesto, peor caso | 2 días | Jorge |
| Repuesto, mejor caso | ~4 horas (equipo en Cuatro Cañadas o cerca de Tres Cruces) | Jorge |
| Desgaste mayor / más comprado | Hélices y motores (Jorge); bombas, hélices y baterías del dron (Carlos) | Ambos |
| Gasolina de reventa | 10 Bs/L (Carlos) — 15 Bs/L (Jorge); "depende de la situación" | Ambos |
| Gasolina precio oficial (referencia) | ~7 Bs/L (`ventana_al_negocio.md` §7) | Doc. negocio |
| Umbral de gasto del encargado | ~1.000 Bs (Carlos); Jorge sin monto ("gasto ostentoso → dueño") | Ambos |
| Umbral de reporte al cliente | ~500 ha acumuladas, o a pedido | Jorge |
| Plazo de cobro del cliente | De inmediato a 30 días | Carlos |
| Recuperación de dron caído (caso T50 nocturno) | 4 h de búsqueda nocturna + 1 día más; total 1 día y 4 h hasta reponer con otro dron | Jorge |

Uso sugerido: los tiempos de repuesto calibran la alerta de "dron parado" y el stock mínimo de críticos (hélices, motores, bombas, baterías — coincide con la lista de `ventana_al_negocio.md` §8, sumando baterías); el rango de gasolina calibra la alerta de "consumo anómalo" separando precio de litros; el umbral de 500 ha calibra el disparador del reporte de avance.

---

## 6. Clasificación contra la especificación

| # | Hallazgo | Clasificación | Sección de la especificación | Impacto |
|---|---|---|---|---|
| C-01 | El encargado gestiona compras, entradas de stock y envíos de repuestos; el jefe de campo solo salidas | **CONFIRMADO** | §3 (matriz, nota ⁴), §12 | Ninguno — el circuito real (E-02) calza con el modelo |
| C-02 | Anticipos: aprueba el dueño, gestiona/entrega el encargado; exceso sobre devengado lo decide el dueño | **CONFIRMADO** | §4.4 (`anticipos.autorizado_por`), §10 (alerta 70%) | Ninguno; falta validar el tope 3.000 Bs/mes y 70% con el dueño (nadie los citó) |
| C-03 | Rendiciones sin comprobante con respaldo fotográfico | **CONFIRMADO** | §4.4 (`gastos.tiene_comprobante`, `evidencia_id`) | Ninguno — el modelo ya lo contempla |
| C-04 | Repuestos críticos que dejan dron en tierra: hélices, motores, bombas, baterías | **CONFIRMADO** | §12, `ventana_al_negocio.md` §8 | Sumar baterías a la lista de críticos con punto de reposición |
| C-05 | Gasolina: litros y precio como datos separados; reventa sin factura a sobreprecio | **CONFIRMADO** | §4.4 (`cargas_combustible`) | El diseño ya distingue desvío de precio de desvío de consumo — el campo lo justifica con datos (10–15 vs 7) |
| C-06 | Cobros con plazos variables (inmediato a 30 días) | **CONFIRMADO** | §4.4 (`facturas`, `cobranzas`) | Ninguno; el dato calibra proyección de caja |
| R-01 | Disparador del reporte al cliente: la especificación dice PDF automático al conformar lote (técnico) y al cerrar aplicación (comercial); el campo lo hace **al superar ~500 ha o a pedido del cliente**, descargado de la nube DJI | **CORREGIDO** | §9 | Agregar un **reporte de avance** intermedio con disparador por umbral de hectáreas configurable (~500 ha) y generación a demanda; los otros dos disparadores siguen vigentes |
| R-02 | Registro y visibilidad de facturación/cobranza: la especificación da al encargado "Registrar cobranza / facturar ✔"; en la práctica es trato directo del dueño y el encargado contratado ni ve el dato | **CORREGIDO** (o celda en conflicto) | §3 | Decidir en reunión si el permiso del encargado se mantiene (deseable para descargar al dueño) o se restringe; hoy la práctica es solo-dueño |
| D-01 | **Las pausas nunca se registran** — clima e "imprevistos de parte de la empresa que nos contrata"; es la información que el encargado dice que falta SIEMPRE | **DESCUBIERTO** | §4.3 (`sesiones.motivo_cierre`), §9 | La app de campo debe capturar pausas con causal; agregar la causal **imprevisto_del_cliente** (hoy el enum tiene clima/falla pero no responsabilidad del cliente) — es la defensa de la ventana cuando se estira por causa ajena. Es exactamente lo que la app de campo existe para capturar |
| D-02 | Registro actual en Excel: ingresos (precio/ha, cantidad, total a cobrar) y gastos (descripción, categoría, monto) | **DESCUBIERTO** | §4.4, §15 | Insumo directo para la migración de datos y para que las pantallas de carga del panel calquen las columnas que el encargado ya usa |
| D-03 | El dueño pide "un estado de resultado basico" que hoy es lento/difícil de armar | **DESCUBIERTO** | §3 ("Ver costos y márgenes"), §9 | Nuevo entregable: estado de resultados básico por campaña/aplicación (ingresos por ha validada − gastos por rubro); hoy no existe como reporte nombrado en la especificación |
| D-04 | La plata se pierde "en gasolina, gastos de movilizacion y repuestos" (Carlos) / "en la movilización y logística" (Jorge) | **DESCUBIERTO** | §4.4 (rubros), §10 | Prioriza qué rubros necesitan control automático primero: combustible, movilización, repuestos — orientar las primeras alertas de desvío a esos tres |
| D-05 | Pedido explícito: que el sistema **ordene la información que mandan pilotos y auxiliares por fecha, día y lote** (hoy llega suelta por WhatsApp) | **DESCUBIERTO** | §2.1, §9 | Es la consolidación automática que la app de campo + sync ya produce como efecto secundario; convertirla en la vista diaria del encargado (avance por fecha/lote/persona) |
| D-06 | Umbral de gasto del encargado ~1.000 Bs; escalera de autorización por monto entre roles | **DESCUBIERTO** | §3, §4.4 | La especificación no modela límites de autorización por monto; definir escalera formal (ver §9) y modelarla como parámetro |
| D-07 | Aplicaciones de emergencia de otros clientes, con negociación de precio | **DESCUBIERTO** | §1, §16 (supuesto "un solo cliente contratante en v1") | El trabajo spot existe; el modelo de datos ya admite varios contratos — falta definir el flujo comercial corto (solicitud → precio → orden) y quién negocia |
| D-08 | Envío de repuestos por trufi/encomienda como tramo logístico con tiempo propio | **DESCUBIERTO** | §4.5, §12 | El movimiento de stock tipo `traslado` existe, pero no el estado "en tránsito" ni el tiempo de envío; evaluar si v1 lo necesita o basta el registro del movimiento con fecha de envío/recepción |
| D-09 | Vuelo nocturno confirmado de facto (caída del T50 "fumigando de noche") | **DESCUBIERTO** | §16, `ventana_al_negocio.md` §9.3 | Cierra parcialmente el punto a validar "vuelo nocturno: si se hace" — se hace; falta capturar cuándo y qué cambia (preguntar a pilotos/jefe) |

Nota: R-01 y D-01 tocan el reporte al cliente y la máquina de estados de sesión respectivamente; si su ajuste roza una regla con ADR emitido, avisar a `arquitectura` antes de tocar la especificación.

---

## 7. Oportunidades de automatización y sistematización

Pedidas textualmente por el rol o derivadas directas de sus respuestas:

1. **Control automático de gastos** — respuesta de Carlos a "¿qué control te gustaría que el sistema haga automático?": *"gastos"*. Carga estructurada (categoría/descripción/monto ya existen en su Excel), medio de pago QR con rastro, y alertas de desvío por rubro priorizando gasolina, movilización y repuestos (D-04).
2. **Consolidación automática de los reportes de campo** — el pedido de Jorge: tener la información de pilotos y auxiliares "de una manera ordenada y por fecha o días realizados, lotes". La app de campo + sincronización lo produce solo; falta la vista del encargado (avance por fecha/lote/persona) que reemplace el hilo de WhatsApp.
3. **Alertas de repuestos** — punto de reposición por base sobre los críticos confirmados (hélices, motores, bombas, baterías), calibrado con los tiempos de E-03: si el repuesto tarda 1–2 días, la alerta debe saltar antes de que el dron quede en tierra, no después.
4. **Reporte de avance automático al cliente** — generar el PDF (con capturas e imágenes del campo, como pide Jorge) al cruzar el umbral de ~500 ha o a pedido, sin descargar a mano de la nube DJI (R-01).
5. **Estado de resultados básico** — el reporte que el dueño pide y hoy es difícil de armar (D-03): ingresos por hectárea validada contra gastos por rubro, por aplicación y por campaña.
6. **Registro del sobreprecio de gasolina** — al separar litros (auxiliar) de precio (encargado), el sistema puede mostrar el sobreprecio de reventa como dato visible, hoy invisible dentro del gasto (E-08).

---

## 8. Qué registra este rol y en qué superficie

El encargado trabaja desde la ciudad, con conectividad: su superficie es el **panel web** — no usa la app de campo.

| Registro | Detalle | Respaldo en especificación |
|---|---|---|
| Gastos contables | Categoría, descripción, monto, medio de pago (QR/efectivo), con o sin comprobante, evidencia fotográfica | §3 "Cargar gasto contable", §4.4 `gastos` |
| Precio de combustible | El precio de cada compra de gasolina (los litros los registra el campo) | §4.4 `cargas_combustible` |
| Compras de repuestos y entradas de stock | Cotización elegida (Agropix / Agrosolución / NP Agro), compra, entrada a stock, envío al campo | §3 nota ⁴, §4.5 `movimientos_stock` |
| Procesamiento de rendiciones del campo | Rendiciones del jefe de campo, con o sin comprobante, respaldo por fotos | §4.4 `rendiciones` |
| Reportes al cliente | Generación y envío del reporte de avance/técnico/comercial | §9 |
| Cobranzas y facturación | Registro de facturas y cobros — **celda en conflicto con la práctica actual (solo dueño), ver §9** | §3, §4.4 |
| Entrega de anticipos aprobados | Registro de la entrega del anticipo que el dueño aprobó | §4.4 `anticipos` |
| Mantenimiento e inventario | Órdenes de mantenimiento, movimientos de stock | §3, §12 |

---

## 9. Contradicciones y vacíos para la reunión de cierre

**Contradicciones (celdas en conflicto de la matriz de límites):**

1. **Escalera de montos de autorización.** Jorge: anticipos, piezas de dron y gastos grandes "directamente con el dueño", sin monto. Carlos: umbral de **1.000 Bs** para el encargado. El jefe de campo, en su cuestionario, declaró **500 Bs** de caja chica. Falta la escalera formal: ¿jefe de campo hasta 500 → encargado hasta 1.000 → dueño arriba? ¿Las piezas de dron van siempre al dueño sin importar el monto (versión Jorge) o entran en el umbral (versión Carlos)? Definir y modelar como parámetro.
2. **Registro de cobranza/facturación.** La especificación (§3) da el permiso al encargado; la práctica es trato directo del dueño, al punto de que Jorge no conoce plazos ni montos de cobro. ¿Se delega al encargado en el sistema o queda solo-dueño?
3. **Precio de la gasolina de reventa.** 10 Bs/L (Carlos) vs 15 Bs/L (Jorge). Probablemente ambos ciertos según el momento ("depende de la situación") — pero el parámetro de alerta de consumo/precio necesita un rango de referencia acordado.
4. **Tiempo típico de repuesto.** 1 día (Jorge) vs 48 h (Carlos). Rango coherente; acordar el valor para calibrar la alerta de dron parado y el stock mínimo.

**Vacíos (no respondido o respondido con doble sombrero):**

5. **Validación de trabajos.** La especificación da al encargado permiso de validar sesiones (§3); ninguna respuesta lo menciona. ¿Valida hoy? ¿Validaría en el sistema? Es la segunda pata del principio "se paga por hectárea validada".
6. **Aplicaciones de emergencia de otros clientes (E-13/D-07).** ¿La negociación de precio es del rol encargado o Carlos la describía como dueño? ¿Con qué margen de precio puede cerrar un encargado contratado sin consultar?
7. **Topes de anticipos.** Nadie citó el tope de 3.000 Bs/mes ni el 70% del devengado que asume `ventana_al_negocio.md` §6.1 — el dueño decide "caso por caso". Confirmar si los topes del sistema son regla dura o guía para la decisión del dueño.
8. **El umbral de ~500 ha del reporte** — ¿es acumulado por aplicación, por campaña o por cliente? ¿Lo dispara Agrocom o lo pide el cliente en la práctica?
9. **Causales exactas de pausa (D-01)** — cerrar con jefe de campo y pilotos la lista: clima / falla / imprevisto del cliente / otro, y quién la registra en el momento.
10. **Facturas por campaña.** La pregunta 15 del banco (cuántas facturas/cobros por campaña) quedó sin número — solo el rango de plazos. Pedir el dato al dueño para dimensionar el módulo de cobranzas.

---

## 10. Términos candidatos al glosario

| Término | Significado en la operación |
|---|---|
| **Nube DJI** ("la nuve") | Plataforma en línea de DJI donde el control remoto sube los registros de vuelo; fuente objetiva del avance en hectáreas y de los PDF que hoy se descargan para el reporte al cliente |
| **Captura del RC / del control** | Pantallazo del control remoto con las hectáreas voladas; evidencia primaria del cierre de sesión |
| **Trufi** | Transporte interurbano de pasajeros usado como servicio de encomienda: el canal habitual para enviar repuestos de la ciudad al campo |
| **Encomienda** | Envío de paquetería al campo (por trufi u otro transporte) |
| **QR** | Pago por código QR bancario; medio de pago preferido para pagar directo al beneficiario y dejar rastro sin factura |
| **Gasolina de reventa** | Combustible comprado fuera del canal oficial en épocas de escasez, en bidones, a 10–15 Bs/L, sin recibo ni factura |
| **Bidón** | Envase en que se compra y traslada la gasolina de reventa; su foto es el respaldo de la rendición |
| **Aplicación de emergencia** | Trabajo spot solicitado por un cliente fuera del contrato de campaña, con precio negociado ad hoc |
| **Viático** | Adelanto de dinero para gastos de traslado/estadía del personal; lo gestiona el encargado, lo aprueba el dueño |
| **Caja chica** | Fondo de gastos menores del campo a cargo del jefe de campo (~500 Bs según su cuestionario) |
| **Agropix / Agrosolución / NP Agro** | Proveedores habituales de repuestos de drones agrícolas en Santa Cruz, donde se cotiza toda pieza |
| **Estado de resultados básico** | El reporte que el dueño pide: ingresos contra gastos de la campaña/aplicación, hoy difícil de armar desde el Excel |
| **Repuesto crítico** | El que deja un dron en tierra: hélices, motores, bombas, baterías (y ESC según `ventana_al_negocio.md` §8) |

# Políticas y lógica de negocio — Rol: Jefe de campo

**Agrocom SRL · Documento derivado de respuestas de campo del 25/8/2026 · Insumo para actualizar la especificación**

Fuentes:

- Primaria: `docs/gestion/respuestas_campo/Cuestionario Jefe de campo (Respuestas) - Respuestas de formulario 1.csv`. Encuestados: **carlos** (3 campañas — es Carlos Ferrufino, dueño de Agrocom, que cumple múltiples roles; su respuesta trae además la visión del dueño), **Abraham Gutiérrez Contreras** (4 campañas, con perfil fuerte de mecánica de dron), **David Omar Ríos Lino** (2 campañas, incluye experiencia en boleo con T30), **Miguelito Justiniano** (1 campaña).
- Instrumento: `docs/gestion/respuestas_campo/banco_preguntas_por_rol.md`, sección ROL: JEFE DE CAMPO.
- Contraste: `docs/especificacion/especificacion_funcional_tecnica.md` §3, §5, §10, §11, §16 y `docs/negocio/ventana_al_negocio.md` §4.1, §6, §7, §8.

Convención de lectura de citas: se transcriben cortas y textuales (ortografía original) solo cuando la formulación vale oro. "Calda" = caldo/mezcla; "ha" = hectáreas; "bs" = bolivianos; "ración seca" = víveres de emergencia; "inicio de sesión" = dato de la cuenta DJI en el control remoto que identifica quién voló.

---

## 1. Misión del rol y límites

El jefe de campo es el dueño de la jornada: convierte las órdenes vigentes y el pronóstico en un plan de lotes y parejas, sostiene la logística para que el dron no pare, verifica contra el RC lo que cada piloto declara, y reporta el avance hacia arriba. Es la bisagra entre el campo y la ciudad — y entre Agrocom y el agrónomo del cliente, con una frontera nítida: **negocia lotes y orden de trabajo, jamás lo económico**.

**EJECUTA**
- La coordinación logística diaria con el personal de la propiedad (agua, calda, comedor, hora de inicio) y con el encargado (gasolina, repuestos).
- La suplencia de vuelo cuando falta un piloto: *"Lo suplo hasta que llegue otro piloto"* (carlos). Caso multirol real — cuando vuela, es piloto a nivel de persona.
- El reporte de avance: capturas del RC + resumen en Excel, por celular.

**DECIDE (sin consultar)**
- Plan del día: qué lotes, qué pareja piloto/auxiliar, en qué orden, y turnos día/noche cuando aplica.
- Asignación por experiencia: el piloto con más experiencia al lote con más obstáculos.
- Intervención ante bajo rendimiento: *"cuando el piloto no avanza y pierde la ventana de aplicación por negligencia"* (carlos).
- Ajustes operativos: mover al lote más pequeño cuando una batería falla, la cantidad de caldo por vuelo, la asignación de combustible por aplicación y su control (carlos).
- Soluciones de campo: fallas del dron resolubles en el momento sin ir a taller, y reemplazo de personal ante un accidente (Miguelito).
- Gastos de caja chica hasta ~500 Bs (carlos; los demás no dan monto — ver §9).

**REGISTRA**
- Avance por lote (hoy: capturas + Excel), rendiciones de gastos (extractos bancarios, capturas de QR, facturas cuando el pago es en efectivo).

**VALIDA**
- Lo que cada piloto declara haber volado, contra la captura de pantalla y el inicio de sesión del RC. Coincide con la espec §3 (Validar trabajo ✔, nunca sesiones propias — ADR 0004).

**ESCALA (al encargado de operaciones o al dueño)**
- Todo lo económico con el cliente: *"solo se negocia con el tema de que lotes aplicare todo lo economico es con el de operaciones"* (carlos).
- La disconformidad tras un reclamo del agrónomo que no se resuelve en campo.
- Repuestos que no están en base (pedido a ciudad vía encargado).
- Gastos por encima de la caja chica; Abraham: salirse de los rubros de viático *"ya son problemas con el jefe"*.

**NEGOCIA con el agrónomo del cliente (solo operación, nunca plata)**
- Qué lotes se aplican y en qué orden (p. ej. empezar por el más cercano al agua — Abraham); que el cliente aporte una persona para echar el líquido (David — avisando antes al jefe). Abraham advierte por qué la frontera importa: *"los propios dueños de los terrenos te ponen trampas"* — asumir negociaciones sin consultar es regalar argumentos para no pagar. Miguelito, más tajante: "no se negocia".

---

## 2. Flujo base

1. **Noche anterior — planificación.** Se define lotes, parejas y orden **en coordinación con el agrónomo de la empresa cliente** (carlos) o con el encargado, que marca urgencias (David). Criterios: experiencia del piloto, capacidad del dron, obstáculos y tipo de terreno; si hay varios lotes, primero el cercano al agua (Abraham). Si el clima cambia, se reorganiza todo de nuevo. Con mucho volumen, se divide el terreno por parejas y se arman turnos de día y de noche con cambio de turno (Miguelito).
2. **Asignación por experiencia.** El más experimentado va al terreno con más obstáculos, pendientes o desmonte reciente; el junior, al terreno limpio. Es el criterio dominante (carlos, Abraham, Miguelito; David asigna según pedidos de los pilotos — ver §9).
3. **Logística.** Agua y comida: normalmente las pone el cliente y son exigibles — *"se tiene que exigir porque es su responsabilidad"* (Abraham). Gasolina: se coordina con el encargado de operaciones dónde comprar; en la práctica el equipo sale con gasolina medida o de sobra. Traslados: con el agrónomo. Respaldo: **ración seca y agua propias** por si la alimentación del cliente falla (carlos).
4. **Durante el día.** El jefe monitorea avance y condiciones, resuelve fallas menores en el lote y equilibra la asignación cuando alguien queda parado.
5. **Verificación.** Contra el RC: captura de pantalla con todos los parámetros (porcentaje del mapeo, tiempo, caudal en L/ha, altura, velocidad) + inicio de sesión de la cuenta DJI para saber quién voló. *"los pilotos no pueden mentir porque todas las hectáreas que realizan al dia queda guardado en el control remoto"* (Abraham). Todo se sube además a la nube DJI y queda en el chip del equipo (Miguelito).
6. **Reporte.** Capturas del RC + resumen en Excel hacia el encargado/dueño, cuando hay señal; sin señal, el avance se muestra cuando el dueño del terreno llega al lote (Abraham).

---

## 3. Políticas y reglas de negocio

- **J-01 — Planificación la noche anterior, coordinada con el cliente.** El plan del día (lotes, parejas, orden) se cierra la noche previa, en coordinación con el agrónomo del cliente (carlos) y/o con el encargado que prioriza urgencias (David); se rehace si el clima cambia (Abraham). *Fuente: Q1 de los cuatro.*
- **J-02 — Asignación por experiencia.** Más experiencia → lote con más obstáculos/pendiente/desmonte; menos experiencia → terreno menos peligroso. También pondera capacidad del dron y tipo de terreno. *Fuente: carlos, Abraham, Miguelito (Q2 y Q21); David difiere — ver §9.*
- **J-03 — Verificación siempre contra el RC.** Ninguna hectárea se acepta por palabra: captura de pantalla con parámetros + inicio de sesión (identifica al piloto); respaldo en nube DJI y chip. *Fuente: los cuatro, Q3.*
- **J-04 — Lo económico nunca se toca en campo.** Con el agrónomo se negocia solo qué lotes y en qué orden; precios, pagos y descuentos son del encargado de operaciones/dueño. *Fuente: carlos, Abraham, Miguelito, Q8.*
- **J-05 — Caja chica ~500 Bs sin autorización.** Por encima, se consulta. Rendición con extractos, capturas de QR y facturas cuando el pago es en efectivo. Gastos típicos: comida, coca, gasolina, energizantes, remedios, llantas, alojamiento. *Fuente: carlos (monto), David y Miguelito (medios), los cuatro (rubros).*
- **J-06 — Agua y comida del cliente son exigibles; ración seca de respaldo.** La provisión de agua (y comida/calda según acuerdo) es responsabilidad del cliente en su propiedad; Agrocom lleva ración seca y agua por si falla. Cuando el cliente no cubre, corre por viáticos. *Fuente: carlos, Abraham, Miguelito, Q4/Q7.*
- **J-07 — Reporte de avance con evidencia del RC + resumen Excel.** La captura trae los parámetros completos (porcentaje, tiempo, caudal, altura, velocidad); el Excel consolida. La frecuencia la condiciona la señal. *Fuente: carlos, David, Miguelito, Q5.*
- **J-08 — El jefe interviene solo ante negligencia o falla.** Sin consultar: reasignar al piloto que pierde la ventana por negligencia, mover a lote pequeño si falla una batería, fijar caldo por vuelo, asignar y controlar combustible por aplicación, resolver fallas de dron solucionables en el lote, reemplazar personal accidentado. *Fuente: carlos, Miguelito, Q6.*
- **J-09 — Reanudar tras pausa por clima: regla EN DISPUTA.** Las cuatro respuestas difieren y ninguna dice "el jefe": agrónomo del cliente (carlos); piloto o dueño del terreno según sea sol o lluvia, con vespertino después de las 17:00 para que la mezcla penetre (Abraham); lo determina el anemómetro (David); entre el piloto y el cliente, viendo si no lloverá de nuevo (Miguelito). Contradice `ventana_al_negocio.md` §7 ("jefe decide reanudar") — a cerrar en reunión, no asumir.
- **J-10 — Suplencia con validación cruzada.** Si un piloto se enferma o renuncia, el jefe lo suple hasta que llegue otro (carlos). Cuando el jefe vuela, sus sesiones las valida otra persona (espec §3, ADR 0004 — regla a nivel de persona, ya modelada).
- **J-11 — Dron caído: se reemplaza el equipo, el lote sigue.** *"se lo reemplaza por otro"* (Miguelito). El repuesto se pide a ciudad vía encargado y tarda 24–48 h en llegar a la ciudad más cercana (carlos, Miguelito, David, Abraham). La redundancia de flota es la política: la aplicación no espera al repuesto.
- **J-12 — Turnos día/noche cuando el volumen lo exige.** Se divide el terreno por parejas y se trabaja con cambio de turno. *Fuente: Miguelito, Q1.*
- **J-13 — Reclamo del agrónomo en pleno trabajo: parar → verificar → evaluar → seguir.** Se verifica con instrumento (anemómetro a la vista del agrónomo, si es deriva) y se continúa; si no hay conformidad, se para y se deriva al encargado de operaciones. La dosis no se discute en campo: la define el cliente en la receta; si el problema es de preparación, es interno. *Fuente: carlos, David, Abraham, Miguelito, Q15.*
- **J-14 — Conflictos por hectáreas: el jefe equilibra.** *"todos quieren fumigar mas, yo lo que hago es hacer un equilibrio"* (carlos); se coordina y se divide campo, también por cansancio (Miguelito); si no hay acuerdo, decide el jefe y punto: *"fueron a trabajar no a pelear"* (Abraham). *Fuente: Q14.*
- **J-15 — (Propuesta del dueño, NO vigente) Bono por lote difícil.** carlos propone un extra de pago *"tal vez un 10% mas adicional dependiendo del lote"*. Es exactamente la opción 2 de `ventana_al_negocio.md` §6.3 (tarifa diferenciada) — insumo para la decisión del dueño, no regla activa.

---

## 4. Excepciones y casos reales

- **Caída del dron en plena campaña.** Miguelito la reporta como su peor jornada (*"se me cayo el dron"*); trabajaba con turnos nocturnos. Según el contexto cruzado con los cuestionarios de piloto, correspondería a la caída de un T50 fumigando de noche con búsqueda del equipo en maíz de 1,75 m — detalle a consolidar con las políticas del rol piloto, no consta en este CSV. Resolución: se reemplaza el dron por otro y se sigue; el repuesto tarda 24–48 h.
- **Dron destruido por falta de mantenimiento.** *"se subió a 100 metros y de allá se lanzó quedando en nada"* (Abraham). Su conclusión: la empresa necesita técnico de dron propio — que cambie la pieza o supervise por llamada/video al piloto en campo, incluso sin señal (manuales y videos descargados).
- **Caldo hecho "lodo".** *"Habrá afectado el tema de la preparación por qué se hizo lodo mismo"* (David). La respuesta lo atribuye a la preparación; la posible relación con un cambio de lote ordenado por el cliente (mezcla preparada para una orden que cambió) surge del contexto de otros cuestionarios — causa a confirmar en la reunión de cierre antes de modelarla.
- **Jornada de cadena rota.** Viento + mezcla que tapó la bomba + batería recalentada que se dañó, el mismo día (carlos). Ilustra la cadena de dependencias de `ventana_al_negocio.md` §8: cada eslabón caído frena hectáreas.
- **Intoxicaciones y enfermedades en campaña.** *"se intoxican por el veneno lo cual pueden renunciar"*; las enfermedades *"atacan más en campo por los mosquitos"* (Abraham). David derivó a su auxiliar enfermo a una farmacia, previo reporte al jefe. Hoy no hay registro sistematizado de estos eventos.
- **Piloto que pierde la ventana por negligencia.** El jefe interviene sin consultar (carlos): la ventana de aplicación es el activo comercial y no se sacrifica por bajo rendimiento individual.
- **Clima que deja plantado al equipo.** Época de lluvia: terreno inestable, fallas de camioneta, avance cero (Abraham). El reverso: un mes casi completo volable sin viento — la variabilidad es total.

---

## 5. Números de calibración

| Dato | carlos (dueño) | Abraham | David | Miguelito | Referencia previa |
|---|---|---|---|---|---|
| Días volables/mes | 16 | (anécdota: un mes casi completo sin viento) | n/a (campaña de 1 mes y 1 semana) | 15–16 (mermados por desplazamiento) | sin número escrito |
| T50 ha/día (día bueno) | 120 | 160–180 | — | 120 (en 8 h óptimas) | ventana §4.2: T50 debajo de 150–200 |
| T100 ha/día (día bueno) | 170 | hasta 300 (solo en terrenos muy grandes) | — | 200 (en 8 h óptimas) | ventana §4.2: T100 encima de 150–200 |
| T30 boleo/fumigación | — | — | 80–90 ha/día | — | modelo no contemplado en espec §4.2 |
| Repuesto (pedido → campo) | 48 h a ciudad más cercana | "de un día a más" | 1 día | 24–48 h | espec §12 sin tiempo calibrado |
| Caja chica sin autorización | ~500 Bs | sin monto (viáticos variables) | sin monto ("varía según días") | "en el campo no hay gastos" | sin parámetro en espec |
| Gastos comunes de emergencia | comida, coca, gasolina | alojamiento, comida, agua, energizantes, coca | comida (cuando el cliente no da) | energizantes, agua, remedios, cambio de llantas | espec §4.4 rubros genéricos |

Lectura: los rendimientos **no cuadran entre encuestados** (T50: 120 vs. 160–180; T100: 170 vs. 200 vs. 300). Abraham parece describir el techo en lote grande y limpio; carlos y Miguelito, el promedio realista. Hasta calibrar con datos de sesiones reales, usar los números conservadores (T50 ~120, T100 ~170–200) para promesas comerciales, y los de Abraham como techo teórico. Los 15–16 días volables/mes son consistentes entre los dos que respondieron con número.

---

## 6. Clasificación contra la especificación

| Hallazgo | Clasificación | Sección | Impacto |
|---|---|---|---|
| El jefe planifica el día la noche anterior y asigna parejas | CONFIRMADO | ventana §4.1 | Matriz: Planificación del día → Decide: jefe |
| La planificación se hace CON el agrónomo del cliente (carlos) y/o con el encargado (David) — no es puramente interna | CORREGIDO | ventana §4.1 | La orden del día es co-decidida con el cliente; la pantalla de planificación debería reflejar lotes acordados/prioridades del cliente |
| Criterio de asignación: experiencia → dificultad (cierra el "[validar en campo]" de ventana §4.1) | DESCUBIERTO | ventana §4.1 | Regla escrita; dato "experiencia/categoría" por piloto y "dificultad" por lote habilitaría sugerencia de asignación |
| Verificación de hectáreas contra captura del RC | CONFIRMADO | espec §5, §6 | `captura_rc_id` obligatoria al cerrar sesión — ya modelado |
| "Inicio de sesión" DJI + nube + chip como prueba de QUIÉN voló | DESCUBIERTO | espec §6, §16 | Evidencia de identidad complementaria a la captura; refuerza sesión-por-persona sin API DJI |
| Reanudar tras pausa por clima NO lo decide el jefe (4 respuestas distintas, ninguna coincide con ventana §7) | CORREGIDO (en disputa) | ventana §7, espec §5 (→ autorizado) | Celda "Autorización por condiciones — Decide" en conflicto → reunión de cierre |
| Turnos día/noche existen (cierra el "[validar en campo]" de vuelo nocturno) | DESCUBIERTO | ventana §4.1; espec §4.3 | Sesiones nocturnas: posible `motivo_cierre` cambio_turno; logística e iluminación |
| Agua/comida del cliente exigibles; viáticos cuando no; ración seca de respaldo | DESCUBIERTO | ventana §4.1; espec §4.4 | Condiciones de servicio a explicitar en contrato/proforma; rubro viáticos y ración seca en gastos |
| Caja chica ~500 Bs sin autorización; rendición por extracto/QR/factura | DESCUBIERTO | espec §4.4 (`fondos_caja`, `rendiciones`) | Parámetro de monto autorizado por responsable; tipos de comprobante digitales |
| Lo económico se escala al encargado; con el agrónomo solo lotes/orden | CONFIRMADO | espec §3; ventana §2.3 | Frontera de roles intacta — sostiene el modelo de permisos |
| El jefe suple al piloto (multirol real, con la visión del dueño incluida) | CONFIRMADO | espec §3; ADR 0004 | La validación a nivel de PERSONA no es teórica: pasa en cada campaña. No tocar sin avisar a arquitectura (ADR vigente) |
| Dron caído → se reemplaza por otro; repuesto 24–48 h | CONFIRMADO | ventana §8; espec §5 (cambio_dron), §12 | Calibra la alerta "lote parado >48 h" (espec §10): coincide con el ciclo real de repuesto |
| Reparto de lotes feos: tres criterios en conflicto + propuesta de bono ~10% del dueño | DESCUBIERTO | ventana §6.3 | Decisión de regla pendiente del dueño; la captura por sesión/lote ya soporta cualquiera de las opciones |
| El jefe pide reporte de avance "gráfico en un mapa" (aplicado/pendiente por lote y distancias) | DESCUBIERTO | espec §9, §10 | Backlog panel: capa de mapa sobre `lotes.geometría` (GeoJSON ya existe en el modelo) |
| El cliente aporta una persona para "echar el líquido" | DESCUBIERTO | espec §7 (`mezclas.preparada_por`) | Personal del cliente en la carga: definir cómo queda la responsabilidad/registro de esa participación |
| Intoxicaciones y enfermedades del personal en campaña | DESCUBIERTO | espec §4.3 (`incidencias`) | El enum de incidencias no cubre salud del personal; hoy se resuelve informal (farmacia + aviso al jefe) |
| Aplicación vespertina después de las 17:00 por eficacia de la mezcla | DESCUBIERTO | espec §4.3 (`condiciones`) | La ventana de aplicación no es solo clima: hay franjas horarias por eficacia — confirmar con el agrónomo |
| Servicio de boleo con T30 (David: "hizo boleros y fumigación") | DESCUBIERTO | espec §1 (alcance) | Línea de servicio no modelada — pregunta abierta: ¿entra en v1 o queda fuera de alcance? |
| Modelos T30 y T70P operados/mencionados | CORREGIDO | espec §4.2 (`drones.modelo`) | Ampliar catálogo de modelos más allá de T50/T70/T100 (CR-05 del análisis consolidado) |
| Rendimientos por modelo (con dispersión fuerte entre encuestados) | CONFIRMADO parcial — a calibrar | ventana §4.2 | El rango 120–300 abraza el estimado 150–200; usar conservador hasta tener sesiones reales |
| Se paga por trabajo/avance, no por horario (Abraham: "te pagan por trabajo si más avanzamos mejor para ambos") | CONFIRMADO | ventana §6.1 | Refuerza el principio rector: hectárea validada como unidad de pago |
| Técnico de dron como figura (presencial o remota por llamada/video, con material offline) | DESCUBIERTO | espec §12 | Rol/función no modelada; guías de reparación descargadas en la app de campo como mejora barata |

Ninguno de los hallazgos CORREGIDOS toca reglas con ADR propio; el único punto que roza un ADR (validación a nivel de persona, ADR 0004) queda CONFIRMADO sin cambios. Los ajustes propuestos a la especificación se aplican tras la reunión de cierre, no antes — en particular J-09, que sigue en disputa.

---

## 7. Oportunidades de automatización / sistematización

1. **Mapa de avance por lote.** El pedido más concreto y viene del dueño: *"reporte de lo aplicado y cuanto falta por aplicar y que lotes, mejor si es gráfico en un mapa para ver las distancias y planificar de mejor manera"* (carlos). El modelo ya tiene la geometría de lotes; falta la vista.
2. **Ordenar lo que mandan pilotos y auxiliares.** Hoy el avance llega como fotos y capturas sueltas por celular que el jefe consolida a mano en Excel; David resume su incendio diario en tres palabras: "registro de hectáreas fumigadas". El cierre de sesión con captura adjunta, ordenado por fecha y lote, elimina ese trabajo.
3. **El sistema como quien apaga incendios.** carlos lo dice sin rodeos: su día *"normalmente es para apagar incendios y la automatización vendría en el sistema"*.
4. **Trasladar carga administrativa al encargado, con datos.** Miguelito pide que operaciones vea repuestos, combustible y reporte de avance sin que el campo tenga que perseguirlo — es exactamente el modelo de alertas por excepción de la espec §10.
5. **Clima confiable cada mañana.** Tres de cuatro piden mejor información climática en campo (Abraham: *"no siempre tenés la información exacta del clima y peor en campo"*). Integración simple de pronóstico en el panel/app, cacheada en base.
6. **Estado de la calda y la logística antes de salir.** David quiere saber cada mañana "si la calda ya está lista" — checklist de arranque de jornada.

---

## 8. Qué registra este rol y en qué superficie

Superficie principal: **panel web** (el jefe cobra sueldo fijo, hoy ya trabaja con Excel y celular; la base tiene Starlink). En el lote, sin señal, opera como los pilotos: app de campo con sincronización al volver a base.

| Actividad | Superficie | Hoy | Con el sistema |
|---|---|---|---|
| Plan del día (lotes, parejas, orden, turnos) | Panel web (noche anterior, en base) | Verbal/WhatsApp con agrónomo y encargado | Planificación sobre órdenes vigentes + mapa |
| Validación de sesiones de pilotos | Panel web (o app en campo) | Mirar captura y RC a mano | Cola de sesiones cerradas con evidencia adjunta |
| Reporte de avance al encargado/dueño | Panel web | Capturas + Excel por celular | Automático al validar; el Excel muere |
| Rendición de caja chica | App/panel | Extractos, QR, facturas juntadas a mano | `rendiciones` con comprobante fotografiado |
| Incidencias (falla de dron, personal, clima) | App de campo | Llamada/WhatsApp | Registro con evidencia y escalamiento |
| Pedido de repuestos al encargado | App/panel | Llamada | Solicitud vinculada a stock de base |

**Qué necesita ver cada mañana** (síntesis de Q19 de los cuatro): pronóstico del día localizado; avance aplicado vs. pendiente por lote, en mapa, con distancias; órdenes vigentes y prioridades del cliente; estado de la calda/logística (lista o no); disponibilidad de drones y repuestos críticos en base.

---

## 9. Contradicciones y vacíos para la reunión de cierre

1. **Rendimientos que no cuadran.** T50: 120 (carlos, Miguelito) vs. 160–180 (Abraham). T100: 170 (carlos) vs. 200 en 8 h (Miguelito) vs. 300 (Abraham). ¿Promedio real vs. techo en lote ideal? Definir el número que respalda proformas — hoy la promesa comercial descansa en 150–200 ha/día/dron.
2. **Quién decide reanudar tras pausa por clima.** Cuatro respuestas, cuatro actores (agrónomo del cliente / piloto o dueño del terreno / anemómetro / piloto+cliente) y ninguna coincide con lo escrito ("el jefe decide" — ventana §7). Celda Decide de "Autorización por condiciones" en conflicto abierto.
3. **Criterio de reparto de lotes feos.** Por experiencia siempre (Abraham, Miguelito); turnándose entre pilotos experimentados — *"un terreno malo vos, el próximo yo"* (Abraham); ambos pilotos juntos al mismo lote feo — *"así no hay líos"* (David); tarifa base + bono ~10% por lote difícil (carlos, como dueño). Conecta directo con las tres opciones de ventana §6.3 — es LA decisión de regla de negocio pendiente del dueño, y David agrega una cuarta variante no listada.
4. **Con quién se planifica el día.** Con el agrónomo del cliente (carlos) vs. con el encargado (David) vs. "te dan los terrenos" (Abraham). ¿Quién emite la prioridad de lotes: cliente, encargado o jefe?
5. **Montos de caja chica.** Solo carlos da un número (500 Bs); Abraham y David describen viáticos variables sin tope; Miguelito dice que en campo no hay gastos. Falta definir: monto del fondo, tope sin autorización por responsable, y si viáticos y caja chica son el mismo fondo o dos.
6. **Comida/alojamiento: cliente o viáticos.** A veces la cubre el cliente (comedor, acuerdo previo), a veces sale de viáticos. ¿Se pacta en el contrato? Impacta costo por hectárea y la proforma.
7. **Ambigüedad en la respuesta de Miguelito a "piloto se enferma/renuncia".** Su *"se lo reemplaza por otro"* responde en el contexto de su dron caído — confirmar si la política de reemplazo inmediato aplica igual a personas y equipos.
8. **Causa del caldo "lodo".** David lo atribuye a la preparación; confirmar si hubo cambio de lote/orden del cliente de por medio (versión de otros cuestionarios) — cambia a quién protege el registro.
9. **Boleo con T30.** ¿Es línea de servicio de Agrocom que el sistema debe cubrir en v1, o queda fuera de alcance? Hoy la espec solo modela fumigación.
10. **Días volables.** 15–16/mes es consistente, pero Miguelito los atribuye en parte a desplazamiento, no a clima — ¿cuánto día volable se pierde por logística evitable? Dato que el sistema podría separar (motivo de día no volado).

---

## 10. Términos candidatos al glosario

| Término | Definición propuesta | Fuente |
|---|---|---|
| **Calda** | Forma de campo para el caldo/mezcla lista para cargar al dron | David, Miguelito |
| **Ración seca** | Víveres y agua de emergencia que lleva el equipo por si el cliente no provee alimentación | carlos |
| **Inicio de sesión (RC)** | Registro de la cuenta DJI en el control remoto; identifica qué piloto voló — evidencia de identidad complementaria a la captura | carlos |
| **Boleo / bolero** | Aplicación de sólidos (semilla/fertilizante) al voleo con dron (p. ej. T30); línea de servicio distinta de la fumigación | David |
| **Lote feo** | Lote con obstáculos, pendientes, bordes irregulares o desmonte reciente: menos ha/hora a igual tarifa (ver ventana §6.3) | los cuatro |
| **Encargado de fumigación** | Nombre de campo para la figura del jefe de campo que asigna terrenos por experiencia | Abraham |
| **Viático** | Gasto de campaña fuera de base (alojamiento, comida, agua, energizantes, coca) cubierto por la empresa dentro de rubros acordados | Abraham |
| **Cambio de turno** | Relevo entre parejas cuando se trabaja día y noche sobre el mismo terreno | Miguelito |
| **Chip (del RC)** | Tarjeta de memoria del control remoto donde queda respaldo local de los vuelos, además de la nube DJI | Miguelito |
| **Anemómetro** | Instrumento de medición de viento; en campo funciona como árbitro de pausas y reanudaciones y como evidencia ante el agrónomo | David |
| **T30 / T70P** | Modelos de dron Agras operados o referidos por el equipo, hoy fuera del catálogo de la espec (T50/T70/T100) | David, Abraham |
| **Trufi** | Transporte público interurbano usado como encomienda para envío de repuestos al campo (término del contexto de campo; no aparece en este CSV — confirmar en cuestionario del encargado) | contexto de captura |

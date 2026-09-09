# Políticas y lógica de negocio — Rol: Cliente (dueño del campo)

**Agrocom SRL · Documento derivado · Insumo para actualizar `docs/especificacion/especificacion_funcional_tecnica.md`**

Este rol **no tuvo cuestionario propio y nunca fue encuestado**. Se deriva de las menciones al "cliente", "dueño del terreno", "dueño de la propiedad", "encargado de la propiedad" y "encargado de la fumigación" presentes en **los cinco cuestionarios de campo** (25/8/2026 — Josue Haenke y Miguelito Justiniano Dorado como pilotos; Abraham Gutiérrez Contreras, David Omar Ríos Lino y Miguelito como auxiliares; Carlos Ferrufino, Abraham, David y Miguelito como jefes de campo; Jorge Richard Scheidel Dorado y Carlos F. como encargados; Carlos F. y Abraham en el cuestionario de agrónomo), contrastadas con `docs/negocio/ventana_al_negocio.md` (§1, §2, §5, §7) y la especificación (§1, §3, §9, §13, §16).

**Hallazgo central de esta derivación**: del lado del cliente hay **dos personas distintas** que la especificación hoy no separa:

1. **El dueño del campo** — contrata, paga, da permisos, pone condiciones. Firma el contrato y recibe la factura.
2. **El encargado de la propiedad** (también llamado "encargado de la fumigación" o "personal de la propiedad") — no firma nada pero **opera mucho**: indica los lotes, prepara y a veces echa la calda, ordena pausas y cambios de lote, recibe reportes, recoge envases. La espec (§3, §13) solo modela dueño del campo + agrónomo; este actor es **DESCUBIERTO**.

**Estado**: insumo de trabajo. Cada política lleva su fuente; el cliente real no fue encuestado, así que la sección 9 lista lo que solo él puede confirmar.

---

## 1. Misión del rol y límites

**Misión** (desde el negocio): el cliente compra hectáreas bien aplicadas y bien documentadas — que su cultivo reciba la dosis que su agrónomo ordenó, en la ventana que la plaga exige, con prueba de que ocurrió (`ventana` §1). Le importa el costo por hectárea, la ventana cumplida y no tener que pensar en la aplicación (`ventana` §2.3). Pero las respuestas de campo muestran a un cliente mucho más presente en el día a día de lo que el modelo asumía.

| Dimensión | Dueño del campo | Encargado de la propiedad (actor descubierto) |
|---|---|---|
| **EJECUTA** | Provee agua, productos fitosanitarios, a veces comida/comedor; a veces la persona que prepara y echa la calda; retira los envases vacíos | Prepara la calda (solo o con su agrónomo); a veces echa el químico al dron; lleva agua y productos al chaco; recoge envases; muestra físicamente los lotes |
| **DECIDE** | Contratar; precio y aportes; permisos de horario de vuelo (todo el día vs. parar por viento/sol/sereno); restricciones operativas (p. ej. velocidad máxima); prioridad y cambios de lote; qué hacer con una calda mala; si autoriza coadyuvantes; cuándo paga (inmediato o 30 días) | Prioridad de lotes en el día; parar o cambiar de lote en plena aplicación; disputar la pausa por clima con el piloto |
| **REGISTRA** | Hoy: nada. Las órdenes llegan verbales o por WhatsApp (mapas, croquis); sus demoras y cambios no quedan registrados en ningún lado — y eso es exactamente lo que le falta al encargado de operaciones ("la información que faltaría sería el tema de las pausas" — Jorge) | Nada — sus órdenes son verbales; solo el caso del caldo-lodo se documentó "con pruebas" por iniciativa de Agrocom |
| **VALIDA** | Verifica el avance (viene al chaco a ver si no hay señal); confirma conformidad al recibir el reporte; en el modelo objetivo, su **agrónomo** firma el acta por lote (espec §3: "Firmar acta — agrónomo") | Da conformidad informal en el lugar; no firma nada |

**Celdas en conflicto para la reunión presencial**:

- *Autorización por condiciones (clima)*: "el que tiene la última palabra la tiene el cliente" (Miguelito, piloto — caso del cliente que prohibió volar a más de 15 km/h) vs. "hay veces que el cliente quiere que sigamos y nosotros paramos por la seguridad del dron" (Josue) vs. "última palabra el agrónomo" (Carlos F., piloto). La espec y `ventana` §5 resuelven con autorización firmada del agrónomo cuando se fuerza — pero el campo hoy no lo practica.
- *Reanudar tras pausa*: "agrónomo del cliente" (Carlos F., jefe) vs. "el piloto o directamente el dueño del terreno" (Abraham) vs. "entre el piloto y el cliente, y si no lloverá de nuevo" (Miguelito, jefe) vs. anemómetro en mano (David).
- *Preparación de mezcla*: la espec la asigna al auxiliar de Agrocom (§3, §7); el campo dice que la hace mayormente el lado cliente — ver C-02.

---

## 2. Flujo base: cómo participa en el ciclo

1. **Contrato**: el dueño del campo pacta precio por hectárea, superficie y aportes. Reparto confirmado por el campo: el cliente pone **agua, productos y a veces comida/comedor y la persona de la calda**; Agrocom pone equipos, personal y gasolina ("en la propiedad la logística se encarga con el cliente (calda, agua) y el piloto se encarga de la gasolina" — Miguelito, jefe; "se negocia el tema que nos tienen que proporcionar una persona para echar el líquido" — David, jefe). El `[validar en campo]` de `ventana` §2.2 queda confirmado: el detalle fino se negocia caso por caso.
2. **Orden**: en el modelo objetivo la emite su agrónomo. En la práctica actual llega **verbal o por WhatsApp con mapas o croquis**, del "encargado de la fumigación o cliente" (Miguelito, piloto), y los lotes se muestran en persona: "se va con el cliente o su encargado, y nos muestra los lotes que se tienen que fumigar" (Josue).
3. **Aplicación**: el cliente provee agua y calda al pie del dron; da o niega permisos de horario; su encargado marca prioridades y puede ordenar cambios de lote en caliente. Sus demoras (agua o químicos recién llegando, calda no lista o cuajada) cuestan horas: "aquí se pueden perder horas" (Josue) — y hoy no quedan registradas.
4. **Acta**: la firma su agrónomo, por lote, inmediatamente (espec §3, §9; `ventana` §2.2 la llama "la decisión comercial más importante de todo el diseño"). Carlos F. como agrónomo aceptó firmar por lote "ese día o al terminar la aplicación completa de los lotes, para aprovechar la ventana"; formato de firma: indistinto (pantalla o papel fotografiado). **Vacío**: en clientes chicos sin agrónomo diario, ¿quién firma?
5. **Factura**: trato directo con el dueño de Agrocom (ver `rol_dueno.md` D-05).
6. **Cobro**: "a veces paga de inmediato, otras veces 30 días" (Carlos F., encargado). En trabajos chicos el piloto cobra en el campo al terminar: "les mando las fotos que saqué de cada lote que se fumigó y le hago el cobro" (Josue); "pregunto si se retira y si el cliente ya pagó" (Miguelito, piloto).
7. **Cierre**: el cliente retira los envases vacíos ("los envases vacíos son dejados en el lote para que después el personal de la propiedad los recoja" — David; "se recogen y se entregan al dueño" — Abraham); el sobrante del último tanque queda para el cliente o se repasa en las cortinas (Miguelito, auxiliar; Abraham; David).
8. **Reporte**: hoy lo recibe **cuando el trabajo supera ~500 ha o cuando lo pide**, armado desde la nube DJI a PDF (Jorge). El portal del cliente (espec §13) apunta a reemplazar el pedido manual.

---

## 3. Políticas y reglas de negocio

- **C-01 — El agua y los productos son responsabilidad del cliente.** "Ellos son los responsables de ponerte agua y producto" (Abraham, auxiliar); "agua se tiene que exigir porque es su responsabilidad" (Abraham, jefe); "si te falta agua y producto es culpa del cliente" (Miguelito, auxiliar). *Coincide con `ventana` §2.2 — CONFIRMADO.*
- **C-02 — La calda la prepara el lado cliente (su agrónomo o su personal); Agrocom sugiere qué no mezclar, pero no la modifica.** "El cliente se encarga de hacer la mezcla... nosotros le hacemos una sugerencia de qué químicos no mezclar" (Josue); "el ing. agrónomo de la propiedad es quien se encarga... la preparación la hace personal de la propiedad" (David); "eso se encarga el cliente" (Miguelito, auxiliar). Dos escenarios (Josue): (a) el cliente prepara **y deja una persona que echa el químico al dron**; (b) el cliente prepara y Agrocom lo echa. *El principio "la receta es del cliente" coincide con `ventana` §5; que además la **ejecute** el cliente contradice la asignación de la espec (§3: preparar mezcla = auxiliar/jefe) — CORREGIDO, ver §6.*
- **C-03 — La responsabilidad por el caldo sigue a quien lo preparó.** "Esa es la responsabilidad del cliente o de su encargado, ya que ellos son quienes se encargan de hacer la calda" (Josue); "la responsabilidad cae en quien preparó la calda" (Miguelito, piloto); "corresponde a quien hizo la preparación" (David). Y quien decide qué hacer con la calda mala (recuperarla o no) es el encargado del cliente (Miguelito, piloto). *CONFIRMADO como principio (`ventana` §5); exige registrar **quién preparó** cada mezcla.*
- **C-04 — Cada cliente define permisos y restricciones de vuelo propios.** "Hay clientes que dan permiso para fumigar todo el día sin parar, como también hay clientes que quieren que paremos por el viento, sol y el sereno" (Josue). Caso extremo: un cliente exigió no volar a más de **15 km/h** y "él decidió al final" (Miguelito, piloto). *DESCUBIERTO: restricciones operativas por cliente como atributo del contrato/orden.*
- **C-05 — La pausa por clima es una negociación de tres (piloto/cliente/agrónomo) sin regla clara.** El piloto para por seguridad del dron aunque el cliente pida seguir (Josue), pero otros ceden la última palabra al cliente (Miguelito, piloto) o al agrónomo (Carlos F.). *Celda en conflicto — la regla de la espec (autorización firmada si se fuerza, `ventana` §5) está bien diseñada pero no adoptada; hay que cerrarla en reunión y con el cliente.*
- **C-06 — El cliente (o su encargado) indica los lotes, su prioridad, y puede reordenar en plena aplicación.** "Él me dice qué lotes es más urgente e ir en orden" (David, jefe); "el encargado dijo que paremos y que vayamos a otro lote que era más urgente" (David, auxiliar). *DESCUBIERTO como flujo: el cambio de lote ordenado por el cliente es un evento del negocio, con costo.*
- **C-07 — Las órdenes del cliente que causan pérdida se documentan con evidencia.** Tras el caso del caldo-lodo (ver §4), "hicimos el reporte correspondiente con pruebas de que el encargado de la propiedad nos pidió dejar ese lote y movernos a otro" (David). *CONFIRMADO el principio rector: el registro existe para que la evidencia le dé la razón a quien corresponda.*
- **C-08 — Las demoras del cliente cuestan horas y hoy no quedan registradas.** "Cuando llegamos al chaco recién están comenzando a traer el agua, o los químicos, o recién lo están preparando... aquí se pueden perder horas" (Josue). Y es exactamente el dato que la oficina no tiene: "la información que faltaría sería el tema de las pausas realizadas durante la fumigación, ya sea por tema de clima u otros imprevistos de parte de la empresa que nos contrata" (Jorge). *DESCUBIERTO: registro de pausas con causa imputable (cliente / clima / Agrocom).*
- **C-09 — El pago no tiene un plazo único: inmediato o a 30 días; en trabajos chicos se cobra en el campo.** (Carlos F., encargado; Josue; Miguelito, piloto — ver flujo §2 puntos 6). *CORREGIDO/matiz: la espec modela cobranza registrada por encargado/dueño contra actas; el cobro en campo por el piloto no está modelado.*
- **C-10 — Hay clientes que buscan no pagar, y la trazabilidad es la defensa.** "Ya asumir otra negociación sin consultar al jefe es para problemas, porque los propios dueños de los terrenos te ponen trampas o cualquier cosa para evitar no pagarles" (Abraham, jefe). Corolario operativo: **toda negociación con el cliente pasa por el jefe/dueño**; el personal solo negocia el orden de los campos. *CONFIRMADO `ventana` §7 (conflictos cliente-eficacia y cliente-superficie: los gana la evidencia).*
- **C-11 — El cliente retira los envases vacíos; el sobrante queda para él o se repasa en las cortinas.** (David; Abraham; Miguelito, auxiliar). *DESCUBIERTO menor: constancia de entrega de envases — hoy es informal ("se lo dejamos ordenado").*
- **C-12 — Si el cliente pide más litros por hectárea que lo acordado, se cobra adicional.** "Si el cliente quiere que le echemos más litros por hectárea se le echa, pero se le cobra más, ya no lo acordado, por motivo de que serían más horas de trabajo" (Josue). *DESCUBIERTO: cambio de alcance con reprecio, hoy verbal.*
- **C-13 — Los sectores que no se aplican los definen el agrónomo o el dueño de la propiedad; lo peligroso para el dron lo decide el piloto.** (Carlos F., agrónomo; Abraham: zonas geo, viviendas, franjas de seguridad). *CONFIRMADO: superficie no aplicada con motivo (espec §9, `ventana` §4.3).*
- **C-14 — El cliente puede negar insumos correctivos.** "Normalmente el dueño te niega colocar un coadyuvante para combatir el grumo" (Abraham, auxiliar). *La negativa debería quedar registrada como decisión del cliente — hoy no queda.*
- **C-15 — El reporte se dispara por umbral (~500 ha) o a pedido.** Hoy: nube DJI → PDF → WhatsApp, "sería bueno agregar imágenes y fotos del trabajo realizado en cada campo" (Jorge). *CORREGIDO/matiz frente a espec §9 (PDF automático por lote y por aplicación): mantener lo automático y contemplar además el corte de avance por umbral.*
- **C-16 — El cliente debe tener a alguien a cargo en el campo; su ausencia degrada el trabajo.** Caso: "no había ni una persona que nos indique el lugar, y no había nadie a cargo; se dejó un lote de una hectárea donde no se fumigó" (Miguelito, piloto). Y antes de empezar "se les indica que deben tener las mezclas listas y que debe haber una persona a cargo de la preparación y llenado... aunque pocas veces nos han otorgado una" (Miguelito, piloto). *DESCUBIERTO: el contacto en campo del cliente es un requisito operativo del contrato.*
- **C-17 — El encargado de la propiedad es el interlocutor diario real** — indica lotes, prepara/echa la calda, ordena pausas y cambios, recibe reportes, recoge envases — **pero no firma nada**. *DESCUBIERTO (el hallazgo mayor — ver §6). Corrige el matiz de `ventana` §2.3 ("la relación diaria es Agrocom ↔ agrónomo"): en los clientes actuales, chicos y sin agrónomo residente, la relación diaria es Agrocom ↔ dueño/encargado de la propiedad.*
- **C-18 — Sin señal en el campo, el cliente va a ver el avance en persona.** "Si tenés señal, belleza; pero si no, esperar que el dueño venga a ver el avance" (Abraham, jefe). *El portal (espec §13) y el reporte automático apuntan a reemplazar esta visita.*
- **C-19 — La comida y el comedor son aporte variable del cliente.** "Comida: ellos normalmente tienen un comedor" (Carlos F., jefe — que además lleva ración seca por si la alimentación falla); "depende si quedaron con el dueño que les daría comida" (Abraham). *CONFIRMADO `ventana` §2.2, caso por caso.*
- **C-20 — Mantenimiento del terreno: hay tareas del cliente que condicionan la seguridad del vuelo.** "Lo que realmente deberían hacer los dueños de campo... siempre cortar los árboles secos o ramas secas que sobresalen de las cortinas" (Abraham, jefe). *Candidato a checklist de condiciones del lote previas.*

---

## 4. Excepciones y casos reales relatados

- **El caldo que se hizo lodo** (David, auxiliar): en plena fumigación, el encargado de la propiedad ordenó parar y pasar a otro lote "más urgente". Volvieron a los 3–4 días: el caldo preparado "que tenía que estar líquido estaba muy espeso, como lodo". Agrocom documentó con pruebas que el cambio lo ordenó el encargado del cliente. Es el caso testigo de C-06 + C-07: la orden del cliente tiene costo, y sin evidencia ese costo se le atribuye a Agrocom.
- **El cliente de los 15 km/h** (Miguelito, piloto): un cliente exigió no volar a más de 15 km/h; hubo desacuerdo y "él decidió al final". Restricción operativa por cliente que hoy no queda escrita en ningún contrato ni orden (C-04, C-05).
- **Las 180 ha prometidas que no eran** (Miguelito, piloto): "nos prometió 180 ha, si no, nos dieron poca hectárea y lotes distantes una de otras, donde no había ni una persona que nos indique el lugar, y no había nadie a cargo. Se dejó un lote de una hectárea donde no se fumigó". Superficie prometida vs. real + ausencia de contacto en campo (C-16).
- **Las horas perdidas al llegar al chaco** (Josue): agua y químicos recién llegando o recién preparándose, o una mala mezcla que "se les cuaje" — horas muertas imputables al cliente que hoy nadie registra (C-08).
- **Las trampas para no pagar** (Abraham, jefe): la advertencia explícita de que hay dueños de terreno que arman pretextos para descontar o no pagar — la razón de fondo por la que el acta por lote, la captura del RC y la evidencia fotográfica existen (C-10; `ventana` §2.2 y §7).
- **El cliente que quiere seguir volando** (Josue): "hay veces que el cliente quiere que sigamos y nosotros paramos por la seguridad del dron, como hay veces que el cliente quiere que paremos y paramos" — la doble dirección del conflicto de clima (C-05).

---

## 5. Números de calibración

| Concepto | Valor | Fuente |
|---|---|---|
| Plazo de pago | inmediato o 30 días | Carlos F., encargado |
| Umbral de reporte | al superar ~500 ha, o a pedido | Jorge |
| Dosis estándar | 10 L/ha ("nivel estándar"; más litros = cargo adicional) | Josue; Miguelito, piloto; Carlos F., agrónomo |
| Restricción de cliente (caso real) | velocidad máx. 15 km/h | Miguelito, piloto |
| Horario típico permitido de vuelo | 6:00–10:00 y 16:00–20:00 (fuera de eso: sol pulveriza la gota / sereno la hace resbalar); algunos clientes permiten todo el día | Josue |
| Demora por cliente al llegar al chaco | horas (sin registro hoy) | Josue; Jorge |
| Caldo esperando por cambio de lote | 3–4 días → se hizo lodo | David |
| Condiciones límite (lado Agrocom/agrónomo) | viento <17 km/h (Carlos F.) / <18–20 km/h (Abraham, Josue); temp. <40 °C; humedad 80–95% | cuestionario agrónomo y piloto |
| Desvío de mezcla aceptable | ±5% (aceptado por Carlos F. como agrónomo) | cuestionario agrónomo — cierra el supuesto de espec §16 |
| Firma del acta | por lote, ese día o al cierre de la aplicación completa; pantalla o papel fotografiado, indistinto | Carlos F., agrónomo — cierra parcialmente espec §16 |
| Solape | franja de 9 m; "pocas veces se solapa con el DJI Agras, es un dato muy fino" | Carlos F., agrónomo |

---

## 6. Clasificación contra la especificación

| Hallazgo | Clasificación | Sección espec | Impacto |
|---|---|---|---|
| **Actor "encargado de la propiedad"**: interlocutor diario del lado cliente que indica lotes, prepara/echa la calda, ordena pausas y cambios de lote, recibe reportes y recoge envases — y no firma nada | **DESCUBIERTO (mayor)** | §3 (roles), §13 (portal), §14 (usuarios) | La espec solo modela dueño del campo + agrónomo del lado cliente. Decidir: (a) si el encargado de la propiedad tiene usuario de portal (¿solo lectura? ¿puede recibir reportes?); (b) que las decisiones del cliente registradas en campo (cambio de lote, pausa, negativa de coadyuvante) se atribuyan a una **persona identificable** del lado cliente, no a "el cliente" genérico. Sin firma no hay acta — pero sin registro de sus órdenes no hay defensa. |
| Cliente aporta agua, productos, a veces comida y persona para la calda; el detalle se negocia caso por caso | **CONFIRMADO** | `ventana` §2.2 (cierra su `[validar en campo]`) | Modelar los aportes del cliente como atributos del contrato (qué pone cada parte). |
| La preparación de mezcla la ejecuta mayormente el lado cliente (su agrónomo o su personal); Agrocom solo sugiere incompatibilidades | **CORREGIDO** | §3 (preparar mezcla = auxiliar/jefe), §7 | Agregar a la mezcla el campo **`preparada_por`** (Agrocom / cliente) — define la responsabilidad cuando el caldo falla (C-03). Las pantallas de preparación del auxiliar aplican solo cuando prepara Agrocom; cuando prepara el cliente, Agrocom registra recepción y observaciones. |
| La relación diaria no es siempre con el agrónomo: en clientes chicos es con el dueño o su encargado; puede no haber agrónomo en el día a día | **CORREGIDO** | `ventana` §2.3; espec §3 (firma del acta = agrónomo) | Definir quién firma el acta cuando no hay agrónomo (¿el dueño del campo? ¿el encargado con delegación escrita?). Hoy la conformidad en trabajos chicos es el pago en el campo. |
| Pausas y demoras imputables al cliente no se registran — y es el dato que la oficina declara que siempre falta | **DESCUBIERTO** | §4 (modelo de sesión/incidencia), §9 (reportes), §10 (alertas) | Agregar el registro de **pausa con causa imputable** (cliente / clima / Agrocom) a la sesión. Alimenta el reporte comercial (demoras no atribuibles a Agrocom) y protege la ventana contractual. |
| Restricciones operativas por cliente (horarios permitidos, velocidad máxima, parar por sereno) | **DESCUBIERTO** | §4 (contrato/orden) | Campo de restricciones del cliente en contrato u orden, visible en la app del piloto antes de volar — hoy son verbales y se descubren en el lote. |
| Cambio de lote ordenado por el cliente en plena aplicación, con costo (caldo perdido, horas) | **DESCUBIERTO** | §4, §5 (estados de trabajo) | Motivo de suspensión de trabajo "orden del cliente" con autor (persona del cliente) y evidencia — el caso del lodo se defendió porque se documentó a mano. |
| Cobro en campo al cierre en trabajos chicos (el piloto cobra) | **DESCUBIERTO** | §1 (función 6), §11 | El flujo de ingresos asume facturación/cobranza desde oficina; el trabajo puntual se cobra al pie del dron. Registrar el cobro en campo (monto, medio, quién cobró) para que ingrese al ciclo contratado → cobrado. |
| Más L/ha a pedido del cliente = cargo adicional fuera de lo acordado | **DESCUBIERTO** | §4 (orden), §9 (reporte comercial) | El cambio de dosis pedido por el cliente debe generar delta de precio trazable, no un acuerdo verbal. |
| Reporte por umbral (~500 ha) o a pedido, hoy desde la nube DJI | **CORREGIDO (matiz)** | §9 | Mantener la generación automática por lote/aplicación y agregar el corte de avance por umbral configurable. |
| Entrega de envases vacíos al cliente | **DESCUBIERTO (menor)** | §9 | Constancia simple (foto/nota en el cierre del lote) — hoy es informal y es tema sensible de buenas prácticas fitosanitarias. |
| Portal de solo lectura con reportes, actas e historial responde a una necesidad real | **CONFIRMADO** | §13 | "Resultados de tus aplicaciones y puntualidad, lo que todos quisieran" (Abraham, agrónomo); reportes a pedido con fotos (Jorge). Sostener el aislamiento por contrato (invariante 5). |
| Desvío de mezcla ±5%, firma por lote (mismo día o al cierre de la aplicación), formato de firma indistinto, solape "dato muy fino" | **CONFIRMADO** | §16 (supuestos) | Cierra tres supuestos de la §16 con la voz del agrónomo (Carlos F.). El solape casi nulo del Agras sugiere tolerancia de solape chica por defecto. |

*Nota de circuito: el actor descubierto y el aislamiento del portal rozan ADR 0004 (modelo de seguridad `sec_*`, guard del portal). Antes de agregar un tipo de usuario cliente nuevo ("encargado de la propiedad") a la espec, avisar a arquitectura — puede resolverse como un rol más dentro del guard `cliente` sin cambiar el ADR, pero la decisión es de ellos.*

---

## 7. Oportunidades de automatización / sistematización

1. **Registro de pausas con causa imputable** — la pieza que falta según la propia oficina (Jorge). Convierte "perdimos horas por culpa del cliente" de anécdota en dato del reporte comercial.
2. **Ficha de cliente con restricciones operativas** (horarios permitidos, velocidad máxima, sensibilidad al sereno, vecinos/colmenas): visible en la app del piloto antes del primer despegue — hoy se descubre discutiendo en el lote.
3. **Atribución de decisiones del cliente a personas**: cambio de lote, negativa de coadyuvante, orden de seguir/parar → registradas con nombre del decisor del lado cliente y evidencia. El caso del lodo demostró el valor; hoy depende de la iniciativa del equipo.
4. **Reporte automático al cliente** (por lote y por umbral de avance) con fotos e imágenes del campo — reemplaza el armado manual desde la nube DJI y la visita del dueño al chaco para "venir a ver el avance".
5. **Portal del cliente** (§13): historial, actas, hectáreas aplicadas vs. contratadas — con acceso también para su agrónomo, y a decidir para su encargado de la propiedad.
6. **Registro de mezcla con `preparada_por`**: cuando prepara el cliente, Agrocom registra recepción del caldo y observaciones; cuando prepara Agrocom (a pedido, C-02 escenario b), aplica el módulo completo de mezcla — y la frontera de responsabilidad queda escrita tanque por tanque.
7. **Cobro en campo registrado**: el pago QR/efectivo del trabajo chico, cargado desde la app al cierre, entra al ciclo de ingresos en vez de viajar en el bolsillo del piloto.
8. **Constancia de envases y sobrante**: una foto al cierre del lote documenta la entrega — barato de capturar, valioso ante cualquier reclamo ambiental o de buenas prácticas.

---

## 8. Qué usa este rol y en qué superficie

**Portal del cliente** (espec §2 y §13 — solo lectura, guard separado, siempre desde el `contrato` del usuario autenticado):

- **Dueño del campo**: reporte comercial por aplicación (hectáreas aplicadas vs. contratadas, ventana, monto, saldo del adelanto), actas firmadas, historial de campaña. No ve costos, márgenes, personal ni datos de otros clientes.
- **Agrónomo del cliente**: reportes técnicos por lote (imagen del campo, condiciones, mezcla ejecutada, tanques), actas a firmar. Deseos expresados: "resultados de tus aplicaciones y puntualidad" (Abraham); papel hidrosensible como muestra por lote — sugerencia de Carlos F. como agrónomo, fuera de alcance v1 pero anotada.
- **Encargado de la propiedad**: **sin superficie definida hoy** — es parte del hallazgo. Candidato natural a recibir el reporte por lote (hoy le llega por WhatsApp) y a figurar como autor de las decisiones del cliente registradas en campo.

**Fuera del sistema (interacción en campo)**: el cliente no opera ninguna app de Agrocom; sus órdenes y decisiones las **registra el personal de Agrocom** en la app de campo, atribuidas a la persona del cliente que las dio. El diseño debe asumir cliente sin conectividad y sin obligación de usar nada.

---

## 9. Vacíos que solo una conversación directa puede cerrar

El cliente **nunca fue encuestado** — todo lo anterior es su reflejo en los ojos de Agrocom. Preguntas para hacerle directamente (al dueño del campo real, y por separado a su encargado):

1. ¿Quién es tu encargado en la propiedad y qué puede decidir en tu nombre? Si él ordena un cambio de lote o una pausa, ¿te compromete a vos (costos incluidos)?
2. ¿Aceptás firmar la conformidad por lote inmediatamente después de aplicado — vos, tu agrónomo o tu encargado? Cuando no hay agrónomo, ¿quién firma?
3. ¿Qué necesitás ver para firmar tranquilo: la captura del RC alcanza, o querés la imagen del campo, condiciones y mezcla?
4. ¿Cómo preferís pagar: contra acta por aplicación, al cierre del trabajo, a 30 días? ¿Qué te haría pagar más rápido?
5. Tus reglas de vuelo (horarios, velocidad máxima, sereno): ¿cuáles son y por qué? ¿Aceptás dejarlas escritas en el contrato para que el equipo las conozca antes de llegar?
6. Si una demora es tuya (agua o químicos tarde, calda no lista), ¿aceptás que quede registrada y visible en el reporte, igual que quedan las de Agrocom?
7. ¿Quién prepara la calda en tu operación y aceptás que el registro diga quién la preparó (para que la responsabilidad por un caldo malo sea de quien corresponde)?
8. Si pedís más litros por hectárea o un lote extra, ¿aceptás que el adicional se cotice y registre en el momento en vez de discutirse en la factura?
9. ¿Usarías un portal web con tu historial, actas y avance en tiempo casi real? ¿Qué te haría entrar? ¿Le darías acceso a tu encargado?
10. ¿Qué esperás que pase con los envases vacíos y el sobrante del último tanque? ¿Querés constancia de entrega?
11. Cuando una aplicación "no funcionó", ¿qué evidencia te convencería de que la aplicación no fue el problema (producto, dosis o aplicación — `ventana` §5)?
12. ¿Qué te haría elegir el dron frente a la avioneta u otra empresa? (Las hipótesis internas: puntualidad y calidad de aplicación — Abraham.)

---

## 10. Términos candidatos al glosario

| Término | Definición propuesta | Fuente |
|---|---|---|
| **Chaco** | El campo/terreno del cliente donde se fumiga (uso regional, Santa Cruz) | Josue; uso general en cuestionarios |
| **Calda** | El caldo/mezcla de aplicación (agua + productos); en los cuestionarios se usa "calda" y "caldo" indistintamente | todos los cuestionarios |
| **Encargado de la propiedad** (o **encargado de la fumigación**) | Persona del cliente que opera el lado cliente en el día a día: indica lotes, prepara/echa la calda, ordena pausas y cambios, recoge envases; no firma contrato ni factura | Josue; Miguelito; David; Abraham |
| **Cortinas** | Las orillas/bordes arbolados del terreno; donde se repasa el sobrante y viven los obstáculos de borde | Abraham; Miguelito, auxiliar |
| **Sereno** | Humedad/rocío nocturno que hace resbalar la gota de la planta; motivo de pausa nocturna y de restricción de algunos clientes | Josue |
| **La nube** | La nube de DJI donde el control remoto sube los registros de vuelo; fuente actual de los reportes al cliente | Jorge; Miguelito, jefe |
| **Boleo** | Aplicación/esparcido de sólidos con dron (variante de servicio distinta de la fumigación líquida) | David ("hizo boleos y fumigación") |
| **Mapeo** | Delimitación del lote y sus obstáculos en la app de DJI antes de fumigar; debe hacerse con luz de día; los obstáculos marcados quedan registrados en él | Josue; Miguelito |

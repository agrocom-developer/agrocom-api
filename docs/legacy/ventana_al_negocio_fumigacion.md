# Ventana al negocio — Operación de fumigación con drones

**Agrocom SRL · Documento de trabajo · Acompaña a la Especificación Técnica v1.0**

Este documento cuenta el negocio de punta a punta: desde que sale la proforma hasta que se cobra la última acta, pasando por lo que pasa de verdad en el lote — la coordinación, los conflictos, la plata de cada uno y los equipos. El objetivo es doble: **tener el mapa completo del proceso antes de hablar con pilotos y operarios**, y llegar a esas conversaciones con las preguntas correctas, en el idioma de ellos, para capturar sus procedimientos rápido.

Convención: lo que sale de la propuesta y la especificación de Agrocom va afirmado. Lo que es práctica habitual del rubro pero conviene confirmar con la gente de campo va marcado **[validar en campo]**. Esas marcas son, precisamente, la agenda de la conversación con el equipo.

---

## 1. El negocio en una frase

**Agrocom vende hectáreas bien aplicadas y bien documentadas; el margen vive en los días volables aprovechados.**

Todo lo demás cuelga de ahí. El cliente no compra horas de dron ni litros de caldo: compra que su cultivo reciba la dosis que su agrónomo ordenó, en la ventana que la plaga exige, con prueba de que ocurrió. Y del lado de Agrocom, el costo es mayormente fijo por campaña (equipo instalado, personal en campo, campamento montado), así que cada día que no se vuela — por clima, por falla, por logística — cuesta casi lo mismo que un día volado y no genera nada. Por eso la operación entera está diseñada alrededor de una pregunta: **¿cómo convierto cada ventana de clima volable en hectáreas aplicadas y validadas?**

Los números del escenario base lo dimensionan: 4.000 ha × 7 aplicaciones = 28.000 ha a 65 Bs/ha = **Bs 1.820.000 por campaña**, con adelanto del 35% (Bs 637.000). De cada 65 Bs, unos 53 son costo operativo y depreciación; el margen vivo es 11,75 Bs/ha. Un día de flota parada con 4 drones es un día en que no se generan las ~600–800 ha que la flota rinde — es decir, **Bs 7.000–9.400 de margen que no ocurrió**, con casi todos los costos corriendo igual.

---

## 2. La cadena comercial: de la proforma al adelanto

### 2.1 La proforma

La propuesta se arma por **capacidad de flota contra superficie y ventana**. El cliente tiene ~4.000 ha (típicamente soya en el este cruceño) que necesitan 6–8 aplicaciones por campaña (fungicidas, insecticidas, y foliares según el momento). La proforma ofrece opciones donde más flota = ventana más corta y mejor precio por volumen:

| Opción | Flota | Capacidad por aplicación | Tiempo por aplicación | Precio |
|---|---|---|---|---|
| 1 | 1 T100 + 1 T50 | 2.000 ha | 3–6 días | 70 Bs/ha |
| 2 | 1 T100 + 2 T50 | 3.000 ha | 4–7 días | 67 Bs/ha |
| 3 | 1 T100 + 3 T50 | 4.000 ha | 5–7 días | 65 Bs/ha |

La lógica del descuento: el costo de movilizar e instalar es casi el mismo, y más hectáreas comprometidas diluyen los fijos. Y la lógica de la ventana es agronómica, no logística: **una aplicación fuera de tiempo pierde eficacia** — el agrónomo dispara la orden cuando la presión de plaga o el estado del cultivo lo exige, y a partir de ahí cada día cuenta. Ese es el argumento comercial de la opción 3: con 4.000 ha por pasada, toda la propiedad recibe el producto dentro de la misma ventana.

**Modalidad: dron residente.** El equipo se instala en la propiedad del cliente toda la campaña (~5 meses). Esto elimina la movilización repetida (el criterio interno: recorrer 300 km por 100 ha es mal negocio) y compra velocidad de respuesta: cuando el agrónomo emite la orden, la flota ya está ahí.

### 2.2 La negociación y el contrato

Lo que se pacta, además del precio: hectáreas contratadas y aplicaciones previstas; el **adelanto del 35%**, que no es ganancia anticipada sino capital de trabajo (financia traslado e instalación, stock inicial de repuestos, gasolina, y los anticipos quincenales del personal durante la campaña); la forma de pago del saldo — **por aplicación, contra actas firmadas**; y qué aporta cada parte. Reparto típico de esta operación: el cliente pone los productos fitosanitarios (los define y provee su agrónomo), el agua en campo y la alimentación del personal; Agrocom pone equipos, personal, gasolina, campamento y conectividad Starlink. **[validar en campo: el detalle fino de qué aporta el cliente — agua, bidones, lugar de campamento, seguridad — suele negociarse caso por caso]**

**Por qué el acta es por lote y al momento, no al final:** si la conformidad se deja para el cierre de la aplicación completa, el cliente revisa todo junto con la factura en la mano — y ahí aparecen los motivos de descuento y las demoras de pago. El acta firmada por el agrónomo lote por lote, con la aplicación fresca, convierte la factura en una suma de conformidades ya dadas. Es la decisión comercial más importante de todo el diseño.

### 2.3 Los actores y qué le importa a cada uno

| Actor | Lado | Qué le importa de verdad |
|---|---|---|
| Dueño del campo | Cliente | Costo por ha, ventana cumplida, no tener que pensar en la aplicación |
| Agrónomo | Cliente | Eficacia: que SU receta se ejecute fiel (producto, dosis, orden de mezcla, condiciones); evidencia para responder él ante el dueño |
| Dueño de Agrocom | Agrocom | Margen por ha, salud de la flota, caja de la campaña, que la trazabilidad lo defienda en reclamos |
| Jefe de campo | Agrocom | Que el día rinda: asignación de lotes, resolver lo que se rompe, la caja chica de campo |
| Encargado de operaciones | Agrocom (ciudad) | Repuestos que lleguen a tiempo, gastos ordenados, reportes al día, ser el respaldo de emergencias |
| Piloto | Agrocom | Hectáreas voladas = plata; equipo confiable; lotes "buenos"; que le validen rápido lo que voló |
| Auxiliar | Agrocom | Ritmo de recarga sin caos, mezcla bien hecha (es su responsabilidad si el caldo falla), su tarifa por ha |
| Fiscalizador (en evaluación) | Agrocom | Buenas prácticas en sitio, logística interna, que los pilotos solo vuelen |

La figura clave del lado del cliente es el **agrónomo**: es quien ordena cada aplicación, quien firma las actas y quien reclama si la plaga sobrevive. La relación diaria del negocio es Agrocom ↔ agrónomo; el dueño del campo aparece en la firma del contrato y en la factura.

---

## 3. El ciclo de campaña

Una campaña de ~5 meses se estructura así:

1. **Instalación de base**: campamento en la propiedad, generador, Starlink, camioneta, drones, rotación de baterías, stock crítico de repuestos, bidones de gasolina. La "base" de la spec es esto: un punto físico con conectividad desde donde se despliega al lote (donde NO hay señal).
2. **Aplicaciones (6–8)**: cada una es un sprint de 5–7 días sobre las 4.000 ha, disparado por la orden del agrónomo según el estado del cultivo y la presión de plaga o enfermedad. Entre el disparo y el inicio hay horas, no días.
3. **Entre aplicaciones (1–3 semanas)**: mantenimiento preventivo de drones y generador, reposición de stock y gasolina, descanso del personal, cobranza de la aplicación cerrada. **[validar en campo: qué hace el personal por-hectárea en los días entre aplicaciones — es un punto sensible de la relación laboral, porque sin volar no ganan]**
4. **Cierre de campaña**: desinstalación, liquidación final de personal, conciliación con el cliente, estado de flota (ciclos de batería consumidos, horas de dron) — que es el insumo para decidir la flota del año siguiente.

El calendario real lo dicta el cultivo: siembra de verano en Santa Cruz entre noviembre y diciembre, aplicaciones desde el desarrollo vegetativo hasta cerca de cosecha. **Las órdenes no se conocen por adelantado** — se sabe que habrá ~7, no cuándo exactamente. Por eso la operación se diseña para estar siempre lista, y por eso los días muertos son el enemigo del margen.

---

## 4. El ciclo diario: la coreografía del lote

Este es el corazón del negocio y el terreno donde pilotos y auxiliares son los expertos. El mapa general, para conversar con propiedad:

### 4.1 El día

- **Antes de salir**: el jefe de campo define el plan del día — qué lotes, en qué orden, qué dron y qué pareja piloto/auxiliar a cada uno. Mira el pronóstico y las órdenes vigentes. Criterios de asignación de lotes: cercanía entre sí, tamaño, dificultad, y equidad entre pilotos **[validar en campo: cómo lo hace HOY el jefe de campo — este criterio es una de las reglas de negocio más importantes y menos escritas]**.
- **Ventanas de vuelo**: mandan el viento, la temperatura y la humedad (límites de Agrocom: ≤30 °C, ≤17 km/h, humedad <90%). El patrón típico del día es volable temprano en la mañana, viento que sube hacia el mediodía, y otra ventana a la tarde; con calor extremo se evalúa vuelo nocturno **[validar en campo: si se vuela de noche, con qué condiciones y cómo cambia la logística]**.
- **En el lote**: se monta la posición — camioneta, generador encendido cargando la rotación de baterías, estación de mezcla del auxiliar, agua. El piloto planifica o retoma la misión en la app de DJI; el auxiliar prepara el primer caldo según la receta de la orden.

### 4.2 El ciclo de vuelo (el ritmo que hace la plata)

El dron vuela tandas cortas: un tanque de caldo y una batería duran del orden de 10–15 minutos **[validar en campo: minutos reales por batería según modelo y carga]**. El ciclo continuo es:

> dron aterriza → auxiliar cambia batería (mide temperatura de la saliente con termómetro infrarrojo, anota en qué dron estaba) → recarga caldo del tanque de mezcla → dron despega → mientras vuela, el auxiliar prepara el siguiente caldo y el generador carga las baterías descargadas

La productividad del día es el resultado de que ese ciclo **no se corte**: una rotación de baterías corta, un generador parado, un caldo con grumos o un tanque de mezcla vacío frenan el dron — y el dron parado es el único recurso que no se puede recuperar después. La regla de dimensionamiento: por dron se necesitan suficientes baterías en rotación para que siempre haya una fría y cargada **[validar en campo: cuántas baterías por dron usan hoy y si el generador da abasto con la flota completa]**.

Los volúmenes de carga reales (no nominales): T50 ~30 L, T70 ~50 L, T100 ~60 L. El T100 admite más, pero cargarlo al máximo devuelve baterías muy descargadas y calientes — se sacrifica capacidad por salud de batería. Con una orden de 10 L/ha, un tanque de T50 son 3 ha; la aritmética del día es tanques × ha/tanque, y por eso cada recarga registrada reconstruye el avance.

Con la capacidad declarada en la proforma (4.000 ha en 5–7 días con 4 drones), la flota rinde **~600–800 ha/día**, es decir un promedio de ~150–200 ha/día por dron en condiciones razonables — con el T100 por encima de ese promedio y los T50 por debajo **[validar en campo: rendimiento real por modelo en un día bueno y en un día malo — este número calibra todas las promesas comerciales]**.

### 4.3 El cierre del lote

Al completar el lote: el piloto captura la pantalla del RC (hectáreas de la misión DJI), la adjunta al cierre, sube la imagen del campo capturada por el dron, y se genera el acta para la firma del agrónomo. La superficie NO aplicada se declara con motivo — cabeceras, cables, sectores anegados — porque una hectárea declarada como no-aplicable es una decisión técnica; una hectárea que simplemente falta es un incumplimiento. Esa distinción protege la factura.

---

## 5. La información que fluye con el cliente

El intercambio con el agrónomo es el sistema nervioso del negocio:

**Del agrónomo hacia Agrocom (antes de aplicar):**
- La **orden de aplicación**: qué producto, qué dosis, cuántos L/ha de caldo, humedad mínima, observaciones y restricciones del lote. Sin orden vigente no se vuela — es la frontera de responsabilidad.
- La **receta de mezcla** con su orden de incorporación: qué se disuelve primero, qué se pre-disuelve aparte (sólidos WG/WP), pH objetivo, antiespumante, antideriva. La receta es del agrónomo; Agrocom la ejecuta y la documenta, **no la modifica**. Si el caldo se corta o la eficacia falla por la receta, la responsabilidad es de quien la definió — pero solo si Agrocom puede demostrar ejecución fiel (cantidad real vs. calculada, orden cumplido, tanque por tanque).

**Del campo hacia el agrónomo (durante y al cierre):**
- Condiciones al iniciar (viento, temperatura, humedad) — y si están fuera de rango y el agrónomo igual quiere aplicar, su autorización queda **firmada como observación**: la decisión vuelve a quien la tomó.
- Avance por lote, incidencias con evidencia, superficie no aplicada con motivo.
- Al cierre: **acta de conformidad por lote** (firma del agrónomo) y **reporte técnico** (imagen del campo, horas de inicio y fin, mezcla ejecutada, condiciones, tanques).

**Hacia el dueño del campo (por aplicación):**
- **Reporte comercial**: hectáreas aplicadas vs. contratadas, cumplimiento de la ventana, monto del período, saldo del adelanto, actas firmadas. Es el documento que acompaña la factura y le da al dueño la foto sin tecnicismos.

El principio detrás de todo el flujo: **cuando la plaga sobrevive, hay tres sospechosos — el producto, la dosis o la aplicación.** El negocio de Agrocom depende de poder demostrar que la aplicación no fue: que se aplicó lo ordenado, en la cantidad ordenada, en el orden ordenado, en condiciones autorizadas. La trazabilidad no es burocracia: es la defensa comercial ante el reclamo de eficacia y el sostén de la factura ante el pedido de descuento.

---

## 6. La economía del piloto y el trabajo compartido

Este es el tema más delicado para conversar con la gente, porque es su plata. El mapa:

### 6.1 Cómo gana cada uno

- **Piloto: 7 Bs/ha validada. Auxiliar: 4,25 Bs/ha validada.** Sin sueldo básico. Días de lluvia o viento fuerte no generan nada. Jefe de campo y encargado: sueldo fijo mensual.
- La cuenta del piloto: a ~150 ha/día son ~Bs 1.050 por día volado. Sobre la campaña de 28.000 ha repartida entre 3–4 pilotos, cada uno vuela del orden de 7.000–9.000 ha → **Bs 49.000–65.000 en ~5 meses**, concentrados en los días de aplicación. Muy buen ingreso — pero irregular, y esa irregularidad explica los anticipos.
- **Anticipos**: tope de 3.000 Bs/mes por persona y hasta 70% del devengado acumulado, financiados con el adelanto del cliente. El riesgo administrado: que alguien cobre anticipos por encima de lo que voló y se vaya a mitad de campaña — de ahí el tope, el porcentaje y la cuenta corriente por persona.
- **Se paga por hectárea VALIDADA, no declarada**: alguien distinto del piloto (jefe de campo o encargado) revisa el cierre contra la captura del RC antes de que devengue. La validación no es desconfianza administrativa: es lo que hace defendible el pago — y la factura.

### 6.2 El trabajo compartido (lote a medias)

Un lote queda a medias por tres razones: **relevo de piloto** (fatiga, fin de jornada), **falla del dron**, o **logística**. La regla del negocio es una sola y hay que sostenerla con claridad ante el equipo:

> **Cada uno cobra exactamente las hectáreas que voló.** El que sale cierra su tramo con sus hectáreas y su captura; el que entra arranca registrando desde dónde parte. Nadie cobra por hectáreas del otro, nadie pierde las suyas por entregar el lote.

El detalle técnico que evita la pelea: cuando la misión de DJI se retoma, la pantalla del segundo piloto muestra el **acumulado** del lote, no lo suyo. Si ambos declaran "lo que dice la pantalla", el segundo cobra hectáreas del primero. Por eso el tramo del que entra se calcula como diferencia contra la hectárea acumulada de partida. Explicado así — "esto existe para que nadie te cobre tus hectáreas" — el registro deja de ser control y pasa a ser garantía. **Es el mejor argumento de adopción del sistema que existe.**

### 6.3 Lotes que pagan distinto (el conflicto de fondo)

La tarifa es plana: 7 Bs/ha en cualquier lote. Pero los lotes no son iguales:

- Un lote **grande, limpio y cuadrado** se vuela a máxima velocidad: más ha/hora, más plata/hora.
- Un lote **con bordes irregulares, cables, colmenas cerca, viviendas, cortinas de árboles o sectores anegados** exige vuelo lento, más maniobra, más pasadas de borde: menos ha/hora, misma tarifa → **menos plata por hora de trabajo, y más riesgo de estrellar el dron**.

Consecuencia natural: los pilotos prefieren los lotes fáciles, y la asignación de lotes se vuelve un tema de equidad que hoy administra el jefe de campo a criterio **[validar en campo: cómo se reparte hoy, si hay reclamos, y si existe alguna compensación informal por lote difícil]**. Opciones de regla de negocio, a decidir como dueño (no lo decide el sistema):

1. **Tarifa plana + rotación equitativa de lotes difíciles**, administrada y visible (el sistema registra qué voló cada uno, así que la equidad se puede mostrar con datos).
2. **Tarifa diferenciada por categoría de lote** (p. ej. lote estándar 7, lote difícil 8) — más justa por hora, pero abre la discusión de qué lote es "difícil" y complica la planilla.
3. **Plana con bono por cumplimiento de aplicación** — paga la velocidad colectiva, no el lote individual.

La recomendación de método: **no definir esto antes de hablar con los pilotos** — es exactamente el tipo de regla donde imponerla sin escucharlos cuesta adopción. Llevar las tres opciones y preguntar. Lo que sí conviene decidir de antemano: sea cual sea la regla, el sistema registra por sesión y por lote, así que cualquiera de las tres es implementable después sin cambiar la captura de datos.

### 6.4 Tiempos estimados y presión de ventana

El contrato promete la aplicación en 5–7 días. Si el clima come días, los que quedan concentran presión: jornadas más largas, evaluación de vuelo nocturno, y la tentación peligrosa de volar en condiciones al límite. Ahí es donde el registro de condiciones y la autorización firmada del agrónomo protegen a todos: si se fuerza la ventana, que quede claro quién lo decidió. Y si la demora es por falla de Agrocom (dron en tierra sin repuesto), el costo es doble: margen no generado y ventana comprometida ante el cliente. **[validar en campo: qué pasó en campañas anteriores cuando la ventana se estiró — cómo lo tomó el cliente y qué se negoció]**

---

## 7. Los conflictos típicos (y quién los resuelve)

| Conflicto | Se manifiesta como | Lo resuelve | Lo que el negocio necesita registrado |
|---|---|---|---|
| **Clima** | Viento >17 km/h, lluvia, calor >30 °C | Piloto pausa; jefe decide reanudar; agrónomo puede autorizar fuera de rango | Condiciones al inicio; autorización firmada si se forzó |
| **Falla de dron** | Error de ESC/motor, dron en tierra a mitad de lote | Cambio de dron (sesión nueva), repuesto crítico de stock, o lote queda parcial | Motivo de cierre, incidencia con evidencia, hectárea acumulada de partida |
| **Batería** | Temperatura >50 °C al salir, ciclos agotados | Auxiliar rota/retira; 3+ sobrecalentadas en el mismo dron → revisar el dron, no las baterías | Temperatura por cambio, en qué dron estaba, código de batería |
| **Caldo** | Grumos, espuma, decantación, filtro tapado | Auxiliar; a veces se pierde el tanque y el tiempo | Problema en la recarga, mezcla vinculada, cantidad real vs. calculada |
| **Terreno** | Cables, colmenas, viviendas, vecinos sensibles, sectores anegados | Piloto no aplica el sector; se declara con motivo | Superficie no aplicada + motivo (es la diferencia entre decisión técnica e incumplimiento) |
| **Deriva a vecinos** | Reclamo de un tercero (colmenas muertas, cultivo vecino tocado) | Dueño/jefe, con el cliente | Restricciones del lote declaradas ANTES, condiciones de viento al aplicar — la defensa es preventiva |
| **Personal** | Piloto que renuncia a mitad de campaña, disputa por lotes, anticipo que supera lo volado | Jefe de campo / dueño | Cuenta corriente por persona, hectáreas por sesión (la disputa se resuelve con datos, no con memoria) |
| **Cliente — eficacia** | "La plaga sigue viva, ustedes aplicaron mal" | Dueño, con la trazabilidad en la mano | La cadena completa: orden → mezcla real → condiciones → sesiones → evidencia |
| **Cliente — superficie** | "No aplicaron todo lo que facturan" | Dueño | Captura RC + imagen del campo + acta firmada por SU agrónomo en su momento |
| **Gasolina** | Escasez, precio de reventa (oficial ~7 Bs/L, reventa 10+) | Encargado (ciudad) consigue; jefe administra bidones | Litros (auxiliar) separados del precio (encargado): distingue desvío de precio de desvío de consumo |

El patrón general: **casi todos los conflictos del negocio se ganan o se pierden según lo que se registró cuando nadie estaba peleando.** Esa es la venta interna del sistema a los operarios: no es control para desconfiar de ellos, es la evidencia que les da la razón cuando el reclamo llega — y el respaldo de su propio pago.

---

## 8. Los equipos como sistema (la cadena que sostiene el vuelo)

Los equipos no son una lista de activos: son una **cadena de dependencias** donde el eslabón más débil define cuántas hectáreas salen del día:

> **gasolina → generador → baterías → dron → hectáreas → plata**

- **Drones (T50 / T70 / T100)**: capacidades reales de carga 30/50/60 L. Flota mixta: el T100 hace el volumen en lotes grandes; los T50 dan flexibilidad y redundancia — si el T100 cae, la aplicación sigue, más lenta. Cada dron acumula horas y hectáreas → dispara su mantenimiento preventivo.
- **Baterías**: el consumible crítico y uno de los rubros más caros (10 Bs/ha presupuestados, igual que el dron). Viven en ciclos; el calor las mata. Por eso: código propio grande y legible (T50-A-01), temperatura medida en cada cambio, y la regla de no cargar el T100 al máximo. Una batería que sale caliente una vez es dato; tres veces en el mismo dron es un dron con problema de motores/ESC.
- **Generador**: el corazón silencioso. Sin generador cargando la rotación, la flota se queda sin baterías en una hora y el día se muere. Sus horas de uso disparan su propio mantenimiento, y su gasolina es parte del costo por hectárea.
- **Camioneta**: mueve todo — equipo, bidones, agua, gente — y sus km disparan mantenimiento. El rubro camioneta (8 Bs/ha) es de los grandes.
- **Repuestos**: los de Agras son caros; el inventario es plata inmovilizada. La distinción operativa clave es el **repuesto crítico**: el que deja un dron en tierra (hélices, motores, ESC, bombas). De esos siempre hay stock mínimo en base, porque el costo de no tenerlos no es el repuesto — es la flota parada en plena ventana.
- **Starlink**: la conectividad vive en la base, no en el lote. Todo lo que pasa en el lote se registra sin señal y se sincroniza al volver — así está diseñada la app de campo.

---

## 9. Guía de conversación con pilotos y operarios

El objetivo de las conversaciones no es explicarles el sistema: es **capturar el proceso real** — que nunca coincide del todo con el proceso declarado — y validar las reglas marcadas en este documento. Método sugerido:

### 9.1 Cómo plantearlo

- Abrir con el día, no con el sistema: *"Llevame por tu día completo, desde que te levantás hasta que se guarda el dron."* La gente describe procesos caminándolos, no enumerándolos.
- Ante cada regla que cuenten, la repregunta de oro: *"¿Y cuando eso no se puede, qué hacen?"* — ahí vive el proceso real (el atajo, la excepción, el arreglo informal). Los atajos no se juzgan: se anotan, porque el sistema que no los contempla será esquivado.
- Cerrar cada tema con números: *"¿cuántas veces por día?", "¿cuántos minutos?", "¿cuántas veces pasó esta campaña?"* — los números convierten anécdota en requisito.
- Y la venta interna, dicha temprano: *"esto existe para que cada uno cobre exacto lo que voló, y para que cuando el cliente reclame, la evidencia les dé la razón a ustedes."*

### 9.2 Preguntas por rol

**Al piloto:**
1. ¿Cómo decidís que ya no se vuela — qué mirás para el viento, y quién tiene la última palabra si vos decís no y el jefe dice sí?
2. Cuando entregás un lote a medias (cansancio, falla, fin de jornada), ¿qué le decís al que entra? ¿Cómo saben hoy cuántas ha hizo cada uno?
3. Cuando el RC retoma una misión, ¿qué muestra la pantalla? ¿Alguna vez hubo lío por hectáreas de uno anotadas al otro?
4. ¿Qué lote es un "buen lote" y cuál es un "mal lote"? ¿Cuánto cambia tu día entre uno y otro, en hectáreas? ¿Cómo se reparten hoy?
5. ¿Qué hacés distinto cerca de cables, colmenas o casas? ¿Cuánto te frena?
6. ¿Qué es lo primero que se rompe en el dron y qué hacés cuando pasa en medio del lote?
7. Del registro: ¿qué estarías dispuesto a anotar al cerrar el lote, y qué te parecería una molestia en pleno trabajo?

**Al auxiliar:**
1. Contame cómo preparás un caldo, paso por paso, desde el agua. ¿En qué orden echás los productos y quién te dijo ese orden?
2. ¿Cómo calculás cuánto producto por tanque? (regla de tres actual, mental o anotada)
3. ¿Qué caldos dan problema — cuáles hacen grumos, cuáles espuma — y cómo te das cuenta ANTES de cargarlo al dron?
4. ¿Qué hacés con el sobrante del último tanque? ¿Y con los envases?
5. En el cambio de batería: ¿qué mirás, qué tocás, cuánto tarda? ¿Cuándo decidís que una batería "está muy caliente"?
6. ¿Cuántas baterías por dron necesitás para que el dron nunca espere? ¿El generador da abasto?
7. ¿Cada cuánto cargás gasolina al generador y cómo la anotan hoy?

**Al jefe de campo:**
1. ¿Cómo armás el plan del día — qué lote primero, quién a cuál? ¿Qué reclamos te genera el reparto?
2. ¿Cómo validás hoy lo que un piloto dice que voló? ¿Alguna vez encontraste diferencia?
3. ¿Qué emergencias resolviste esta campaña con plata de la caja chica, y cómo rendiste eso?
4. ¿Qué se rompió que dejó un dron parado, y cuánto tardó el repuesto en llegar de la ciudad?
5. Cuando el agrónomo quiere aplicar con viento al límite, ¿cómo se maneja hoy y quién queda responsable?

### 9.3 Qué validar puntualmente (checklist de la spec y de este documento)

- Rendimiento real por modelo de dron: ha/día en día bueno y en día malo, y minutos de vuelo por batería.
- Cuántas baterías por dron en rotación, y capacidad real del generador con la flota completa.
- El flujo del relevo hoy: cómo se anota, qué conflictos hubo, si la regla "cada uno cobra lo suyo" se entiende y se acepta.
- Asignación de lotes: criterio actual, reclamos, y reacción a las tres opciones de la sección 6.3.
- Orden de mezcla real vs. la plantilla de 10 pasos de la spec (agua → corrector pH → antiespumante → sólidos pre-disueltos → SC → SL → EC/EW/OD → aceites/coadyuvantes → antideriva → completar agua).
- Tolerancia de solape entre sesiones (el solape real existe: ¿de cuánto es?).
- Vuelo nocturno: si se hace, cuándo, y qué cambia.
- Qué hace el personal variable los días sin vuelo, y cómo viven la irregularidad del ingreso (contexto para anticipos).
- La superficie no aplicada: cómo se declara hoy y cómo la toma el agrónomo.

### 9.4 Cómo capturar sin demorar

Tres conversaciones de ~1 hora (piloto + auxiliar juntos rinde bien: se corrigen entre ellos; el jefe de campo aparte), grabadas con permiso. De cada una salen tres listas: **confirmado** (la spec ya lo modela bien), **corregido** (la spec dice X, el campo hace Y — se ajusta la spec), y **descubierto** (proceso que no estaba escrito — se decide si entra en v1 o se anota). Con eso, la captura de procesos que podría tomar semanas de idas y vueltas se cierra en una semana con evidencia.

---

## 10. Resumen: las 10 verdades del negocio

1. Se venden hectáreas documentadas; el margen vive en los días volables aprovechados.
2. El acta por lote, inmediata, es lo que hace que la factura se pague sin pelea.
3. La orden del agrónomo es la frontera de responsabilidad: Agrocom ejecuta fiel y lo demuestra; la receta es de quien la firmó.
4. El ciclo dron–batería–caldo–generador no se puede cortar: el dron parado es el único costo irrecuperable.
5. Cada uno cobra exacto lo que voló, por sesión validada — y el registro existe para garantizarlo, no para vigilar.
6. Los lotes no son iguales y la tarifa sí: la asignación de lotes es una regla de negocio abierta, a cerrar CON los pilotos.
7. La superficie no aplicada declarada con motivo es decisión técnica; la que falta sin declarar es incumplimiento.
8. Casi todo conflicto se gana con lo que se registró cuando nadie peleaba.
9. La cadena gasolina → generador → batería → dron define el día; el repuesto crítico sin stock cuesta la ventana, no el repuesto.
10. El proceso real vive en las excepciones: la pregunta que lo revela es "¿y cuando eso no se puede, qué hacen?".

# ADR 0048 — Modelo de negocio: suscripción y periodo de gratuidad

**Estado:** Propuesta exploratoria, sin decidir · **Fecha:** 2026-11-26 (fuentes consultadas ese día) · **Decide:** el dueño, cuando haya investigado cómo ofrecer el servicio · **Relacionados:** ADR 0034 (precio acordado y pago fuera de la app), ADR 0045 (mercado), ADR 0046 (cuentas, verificación, estado de la cuenta y compuerta de ofertar), `docs/politicas/precio_y_pago.md` y `docs/politicas/tiendas-y-privacidad.md`.

> **Este documento no decide nada y no es asesoría legal, tributaria ni de las tiendas.** Ordena las opciones, deja un criterio técnico como propuesta y lista lo que hay que confirmar. Ningún texto de este ADR cambia hoy una política, un esquema o una pantalla. Lo que no pude verificar está marcado **[NO VERIFICADO]** y se resume en la sección 11. El número 0047 está reservado por el ADR de las exigencias de las tiendas (rama `docs/tiendas-decisiones-aplicadas`), por eso este lleva el 0048.

## 1. Contexto

Agrocom es el nexo entre quien necesita una fumigación con drones y quien la presta, y el registro del estado y del resultado. Hoy el sistema no cobra nada: el precio se negocia entre cliente y proveedor, el pago del servicio ocurre fuera de la app y la plataforma no calcula comisión (ADR 0034; invariante 14). La política dice que «el modelo de ingresos de Agrocom se define fuera de la aplicación; cualquier cambio que lo traiga al sistema se decide en un ADR nuevo». Este es ese ADR, en su fase de exploración.

Punto de partida del dueño:

- Una **suscripción**, sin saber todavía quién paga: el proveedor, el cliente o ambos.
- Antes de cobrar, un **periodo de gratuidad**.
- La **comisión por trabajo no conviene**: el pago no pasa por la app y no se puede auditar, y el trabajo de Agrocom es dar el contacto al cliente y emparejarlo con un trabajo en colaboración, no intermediar el dinero.
- El dueño todavía debe investigar cómo ofrecer el servicio. Por eso este ADR documenta opciones y deja el criterio como propuesta; las decisiones están en la sección 12.

Lo que el sistema ya tiene y condiciona el diseño: cuentas separadas por app con estado de aprobación (ADR 0046), contacto del cliente visible solo tras adjudicar (invariante 13), proveedores propios, independientes, dueños de drones y empresas con pilotos vinculados (ADR 0028, 0046 puntos 34 a 36), motor de notificaciones con campana y correo (ADR 0025), parámetros en `plt_configuraciones` (ADR 0016) y un tablero de métricas de solo lectura (`panel/metricas`, ADR 0039).

## 2. Restricciones

1. **El pago del servicio ocurre fuera de la app y se queda fuera.** Lo que se estudia es otra cosa: cobrar por el **acceso a la plataforma** (poder ofertar, publicar, aceptar), no por el trabajo. La sección 9 lista las políticas que hoy confunden las dos.
2. **Dos tiendas.** Las apps se publican en Google Play y en la App Store. Ambas regulan cómo se cobra lo que se desbloquea dentro de una app (sección 4). Una suscripción mal planteada puede costar entre 15 % y 30 % de lo cobrado o el rechazo de la app.
3. **Bolivia.** La moneda es el boliviano; el pago más extendido es el QR interoperable del sistema bancario y el efectivo (sección 6); los procesadores internacionales más conocidos no operan en el país. La facturación se rige por el Servicio de Impuestos Nacionales (sección 7).
4. **Dos lados con poca masa.** Al principio hay pocos proveedores y pocos clientes. Cobrar antes de que el mercado funcione puede impedir que funcione.
5. **Invariantes que no se tocan:** dinero en decimal exacto, soft delete, bitácora, transiciones por máquina de estados, lo confirmado no se reescribe (se corrige con un registro nuevo), y el contacto del cliente solo tras adjudicar.
6. **Un solo operador del panel hoy.** Todo cobro manual lo registra una persona; el diseño debe ser pequeño y auditable.

## 3. Análisis de opciones

### 3.1 La dinámica de un marketplace de dos lados

- **Se cobra primero al lado que obtiene el ingreso.** El proveedor recibe dinero por cada trabajo; el cliente recibe un servicio que ya paga al proveedor. Cobrarle al cliente además de lo que paga al proveedor encarece el servicio y lo empuja a contratar por fuera.
- **El lado de la demanda se mantiene sin costo para ganar liquidez.** Un proveedor se suscribe solo si ve solicitudes; un cliente publica solo si espera ofertas. En la etapa inicial lo escaso es la **liquidez** (solicitudes con ofertas y ofertas con trabajo), y cobrar a quien trae las solicitudes la frena.
- **Matices del contexto de Agrocom:**
  - El cliente es quien tiene el terreno; el proveedor es quien tiene el equipo (dron), la licencia y los seguros. Como **hipótesis a comprobar** (no es un dato), quien ya invirtió en un dron podría estar más dispuesto a pagar por acceso a trabajo que quien solo necesita un servicio puntual.
  - El cliente puede ser también proveedor con otra cuenta (ADR 0046: una persona, a lo sumo una cuenta de cada tipo). Cobrar a ambos lados le cobra dos veces a la misma persona.
  - **No sé de qué lado es más escasa la oferta en Santa Cruz**: puede que falten pilotos o que falten clientes. Los datos de la fase gratuita lo dicen (sección 10).
- **Desintermediación.** Con comisión, cada trabajo cerrado por fuera le resta ingresos a la plataforma, y el sistema entero incentiva ocultarlos. Con suscripción, el proveedor paga por estar, no por cada trabajo; que dos partes se contacten por fuera después del primer trabajo cuesta menos a la plataforma. Es una ventaja de la suscripción y de los modelos que cobran **acceso**.

### 3.2 (a) Suscripción del proveedor

**Qué es.** El proveedor paga mensual o anual para poder ofertar, contraofertar y aceptar. Planes tentativos: **independiente** (una persona, uno o pocos drones); **empresa** con un tope de pilotos vinculados y de drones (o por tramos: hasta N pilotos, hasta M drones); los **pilotos propios de Agrocom no pagan** (tipo `propio`).

**Ventajas.** Cobra al lado que obtiene el ingreso. Es predecible para Agrocom y para el proveedor. No depende del monto de cada trabajo ni de que el pago del servicio se audite. Encaja con «damos el contacto y el emparejamiento»: el proveedor paga por tener acceso al flujo de solicitudes. No incentiva ocultar trabajos. Un plan por empresa refleja que una empresa con varios drones usa más el servicio.

**Riesgos.**
- Un proveedor que paga y no recibe trabajos se va y habla mal del servicio: hay que medir antes de cobrar y tener un criterio de «dejar de cobrar» si no hay demanda.
- Una cuota fija es la misma para quien hace un trabajo al mes que para quien hace veinte: el pequeño se siente castigado, el grande se siente bien tratado. Se compensa con planes por uso o por tramos.
- Es un cobro dentro de lo que las tiendas llaman «funcionalidad de la app» (sección 4): el diseño del cobro tiene la parte más delicada.
- Cobro manual en la fase inicial: trabajo operativo, riesgo de olvidos y de disputas sobre quién pagó.
- Una empresa puede repartir sus pilotos en cuentas independientes para pagar menos; el vínculo del ADR 0046 (punto 35) y el límite por plan lo vigilan.

**Cuándo conviene.** Cuando haya suficientes solicitudes abiertas por proveedor activo como para que la cuota se justifique y los proveedores ya tengan trabajos adjudicados (ver «gratuidad hasta masa crítica», 3.5). Es la opción que más se parece a lo que el dueño describe.

### 3.3 (b) Suscripción del cliente

**Qué es.** El cliente paga por publicar o por servicios premium (más ofertas visibles, prioridad en oleadas, historial).

**Ventajas.** Si los clientes son grandes (empresas agrícolas con muchas hectáreas), pueden pagar y esperar un servicio mejor.

**Riesgos.**
- Va contra la regla de ganar liquidez: encarece el lado que trae la demanda y que ya paga el servicio al proveedor.
- El cliente nuevo desconfía de pagar antes de ver ofertas.
- Si el cliente es también proveedor con otra cuenta, el cobro lo alcanza por partida doble.
- Un cliente que paga espera resultados garantizados que Agrocom no controla (hay proveedores que no responden): crea una expectativa que el producto aún no cumple (nadie puede asegurar ofertas en un mercado chico).
- Las tiendas lo tratan igual que cualquier acceso a funciones dentro de la app (sección 4).

**Cuándo conviene.** Más adelante, para un plan **empresa del cliente** (varios lotes, varios encargados, reportes) cuando haya demanda de esas funciones y el mercado ya tenga liquidez. Antes, no.

### 3.4 (c) Ambos lados

**Qué es.** Proveedor y cliente pagan, con planes distintos.

**Ventajas.** Más ingresos potenciales; filtra solicitudes y ofertas poco serias.

**Riesgos.** Suma los riesgos de (a) y (b) y duplica el trabajo de planes, avisos y cobros. Con un solo operador, es mucho para sostener. Pone fricción en los dos lados cuando el mercado todavía no tiene masa.

**Cuándo conviene.** No al inicio. Se reconsidera cuando el mercado tenga liquidez estable y la suscripción del proveedor ya funcione.

### 3.5 (d) Periodo de gratuidad

Las tres formas se pueden combinar. Todas se expresan como **parámetros** (sección 8.2), no como código.

| Forma | Cómo funciona | Ventajas | Riesgos |
|---|---|---|---|
| **Duración fija por cuenta** | Cada cuenta tiene N meses gratis contados desde su **aprobación** (no desde el registro, para que la demora de la verificación no la consuma) | Simple y justa; cada quien prueba el servicio el mismo tiempo | Una cuenta que se registra tarde llega a un mercado ya maduro y paga igual que las demás; el reloj corre aunque no haya solicitudes; quien no usa la app gasta su gratuidad |
| **Gratuidad de lanzamiento hasta una masa crítica** | Todos son gratis hasta una fecha o hasta que se cumplan umbrales: por ejemplo, un mínimo de proveedores aprobados y de adjudicaciones por mes en un departamento. Se avisa con anticipación cuándo termina | Sirve a la liquidez; no castiga a los primeros que llegan; es la que más se parece al caso de un mercado que aún no existe | El umbral se discute («¿ya llegamos?»); si nunca se cumple, nunca se cobra; los proveedores se acostumbran a lo gratis y protestan al cobrar |
| **Primeros N trabajos adjudicados** | La cuenta es gratis hasta que gana N adjudicaciones | Cobra cuando el proveedor ya obtuvo valor; alinea el pago con el éxito | Es un contador de trabajos: roza la comisión («paga cuando gana»), exige medir adjudicaciones por cuenta y se presta a que el proveedor las cierre por fuera para no gastarlas |

**Cómo se mide.** Por **persona** y no por cuenta, para que quien se registra de nuevo no reinicie la gratuidad (el ADR 0046 ya garantiza una persona, a lo sumo una cuenta de cada tipo). La gratuidad parte de `aprobado_en` de la cuenta; un reloj diario (`negociacion:procesar` ya corre cada minuto, el ADR 0038; uno de suscripciones correría una vez al día) calcula los vencimientos.

**Qué pasa al terminar.**
1. **Avisos** antes (campana y correo): por ejemplo 30, 7 y 1 días antes (parámetros).
2. **Gracia**: unos días después del vencimiento sin bloquear nada, para absorber olvidos y el tiempo del cobro manual (parámetro).
3. **Suspensión de ofertar y aceptar, no de ver**: el proveedor sigue viendo el mapa, sus solicitudes y su historial, conversa por los trabajos en curso, califica, y puede eliminar su cuenta (sección 8.5). Lo ya adjudicado no se interrumpe (coherente con el ADR 0046, punto 12).
4. **Reactivación inmediata** al registrar el pago.

**Propuesta de criterio, a decidir.** Gratuidad de lanzamiento **con fecha de término anunciada** y un tope en el tiempo (por ejemplo, «hasta el fin del primer semestre o hasta que se cumplan los umbrales, lo que ocurra primero»), más una **gratuidad por cuenta** posterior de unos meses desde la aprobación para quienes lleguen después. Los umbrales y las duraciones se fijan con los datos de la sección 10.

### 3.6 (e) Pago por contacto liberado o por paquetes

**Qué es.** Cobrar cuando se desbloquean los datos de contacto tras adjudicar, o vender paquetes de contactos o de ofertas (créditos).

**Lo que se sabe del sistema.** El contacto se libera **solo tras adjudicar** (invariante 13) y es automático: la adjudicación es un acuerdo entre dos partes que el servidor resuelve de forma atómica. Cobrar por ese desbloqueo equivale, en la práctica, a una **comisión por adjudicación** de monto fijo.

**Ventajas.** Se alinea literalmente con «nuestro trabajo es dar el contacto»: se paga por el emparejamiento y no por el monto del servicio; no depende de auditar el pago.

**Riesgos.**
- **Se parece a la comisión que ya se descartó**, solo que de monto fijo: el proveedor paga por cada adjudicación, así que tiene incentivo de cerrar por fuera antes de adjudicar (pasar un teléfono por el chat) o de pedir al cliente que no adjudique en la app. El filtro del chat (T7 del documento de tiendas) y la regla de contacto solo tras adjudicar ayudan, pero no eliminan la elusión.
- Introduce un cobro **por evento** en un momento delicado (el acuerdo ya está hecho): si el proveedor no puede pagar en ese instante, o hay disputa, el sistema tendría que decidir si retiene el contacto de un trato ya acordado. Rompe la regla de atomicidad de la adjudicación o la complica.
- Convierte la adjudicación, que hoy es una sola operación atómica entre dos partes (ADR 0038), en una operación que además depende de un pago.
- Para las tiendas, vender paquetes o créditos de contactos es una compra de funcionalidad digital (sección 4), con IAP casi segura.

**Cuándo conviene.** Si en la fase gratuita se ve que los proveedores con menos trabajos rechazan una cuota fija pero aceptarían pagar «solo cuando gano». Aun así, mejor evaluarlo como paquete de créditos con vigencia que como cobro automático al adjudicar. No es la primera opción.

### 3.7 (f) Destacar o promocionar

**Qué es.** El proveedor paga por aparecer primero en la lista de ofertas del cliente o por recibir avisos antes en las oleadas.

**Riesgos.** El ADR 0045 ya fijó que **no hay prioridad de proveedores** salvo reglas objetivas (propios y mejor calificados). Un pago por prioridad cambia la neutralidad del mercado y erosiona la confianza del cliente. Para las tiendas es una compra digital (Apple lo ejemplifica con los «boosts», 3.1.3(g)).

**Cuándo conviene.** Tarde: cuando el mercado ya esté lleno y la prioridad sea un bien escaso real. Una variante sin conflicto con la neutralidad es la **insignia** o la verificación ampliada (por ejemplo, empresa con documentación revisada), que informa y no ordena.

### 3.8 (g) Comisión por trabajo

**Por qué se descarta hoy.**
1. **No se puede auditar:** el pago ocurre fuera de la app; Agrocom no ve el monto real ni si hubo pago (el proveedor solo «informa» que cobró, ADR 0020 de field). El ADR 0034 ya lo dice.
2. **Condiciona la negociación:** una parte del precio acordado iría a un tercero y el proveedor lo descontaría o lo evitaría.
3. **Incentiva ocultar el trabajo:** las dos partes ganan si cierran por fuera.
4. **No describe lo que Agrocom hace:** da el contacto y el emparejamiento, no maneja el dinero.
5. Para cobrarla bien haría falta retener el pago (pasarela, cuentas en custodia), lo que el ADR 0034 descartó por cumplimiento y tiempo; en Bolivia el camino no está claro (sección 6).

**En qué condición se reconsideraría.** Si algún día el pago pasa por la plataforma (pago retenido hasta el cierre, con proveedor de pagos y conciliación) y el mercado exige esa garantía, la comisión se vuelve auditable y aparece el argumento de la protección al cliente y al proveedor. Eso es un producto distinto y un ADR nuevo.

### 3.9 Comparación rápida

| Opción | Cobra a | Auditable sin ver el pago del servicio | Frena la liquidez | Fricción con las tiendas | Esfuerzo operativo | Elusión |
|---|---|---|---|---|---|---|
| (a) Suscripción del proveedor | Quien obtiene el ingreso | Sí | Media | Alta (sección 4) | Medio | Baja |
| (b) Suscripción del cliente | Quien trae la demanda | Sí | Alta | Alta | Medio | Baja |
| (c) Ambos | Los dos | Sí | Muy alta | Alta | Alto | Baja |
| (d) Periodo de gratuidad | Nadie, por un tiempo | No aplica | Ninguna | Ninguna mientras no se venda nada | Bajo | No aplica |
| (e) Contacto liberado o paquetes | Proveedor por adjudicación | Sí (es un evento propio) | Media | Alta | Medio | **Alta** |
| (f) Destacar | Proveedor | Sí | Baja | Alta | Medio | Baja |
| (g) Comisión | Proveedor (o ambos) | **No** | Media | Alta si se cobra en app | Alto | **Alta** |

### 3.10 Propuesta de criterio técnico (a decidir más adelante)

Es una propuesta de trabajo, no una decisión:

1. Empezar con **gratuidad de lanzamiento** para todos, con fecha de término anunciada y umbrales medibles (sección 10).
2. Pasar a una **suscripción del proveedor** (a) con planes por tipo (independiente y empresa por tramos de pilotos y drones), **pilotos propios sin costo**, y **cliente sin costo** para sostener la liquidez.
3. Cobrar **a mano y fuera de la app** en las primeras etapas (sección 5), registrando cada pago en el panel.
4. Dejar (b), (c), (e) y (f) como ideas para revisar con datos, y la comisión descartada.
5. Diseñar el sistema para que el **origen** del pago (manual, Google Play, App Store, procesador) sea un dato de la suscripción, de modo que cambiar de canal no obligue a rehacer el modelo (sección 8.2).

## 4. Reglas de las tiendas para cobrar

**Fuentes, consultadas el 2026-11-26.** Las reglas cambian con frecuencia (hay litigios abiertos en EE. UU.). Todo lo de esta sección debe confirmarse con las tiendas antes de publicar un cobro (sección 11).

### 4.1 Google Play

Fuente: [Política de pagos de Google Play](https://support.google.com/googleplay/android-developer/answer/10281818).

- **Regla general:** «Google Play's billing system is required for developers offering in-app purchases of digital goods and services». Cuenta como digital: ítems, **suscripciones**, funcionalidad o contenido de la app, y software o servicios en la nube.
- **Lo que queda fuera:** compras de bienes físicos o servicios físicos; pagos entre personas; servicios en línea uno a uno no reproducibles; servicios financieros y clínicos regulados; **apps de solo consumo** (donde nada se puede comprar dentro de la app); y los casos en que la facturación de Google no existe en el país.
- **Dirigir a otro medio de pago:** «Within an app, developers may not lead users to a payment method other than Google Play's billing system» salvo excepciones de la propia política; los enlaces directos a páginas que lleven a un pago alternativo también están restringidos. Las apps de solo consumo pueden **mencionar** que se compra fuera, sin enlace directo (ejemplo del texto: «Ve a nuestro sitio para mejorar tu suscripción»). Comunicar ofertas por correo y otros canales externos está permitido.
- **Regiones:** la política enumera programas para India y Corea del Sur (facturación alternativa), el Espacio Económico Europeo (Ley de Mercados Digitales), EE. UU. (orden judicial; ofertas externas) y un programa piloto de elección de facturación. **Bolivia no figura.**
- **Comisión.** Para los mercados donde la nueva estructura aún no rige, la página de tarifas dice que la suscripción de renovación automática paga 15 % «sin importar los ingresos anuales» ([tarifas de servicio](https://support.google.com/googleplay/android-developer/answer/112622)). El 24 de junio de 2026 Google anunció una estructura separada (10 % de servicio y 5 % de facturación) desde el 30 de junio de 2026 en EE. UU., el EEE y el Reino Unido ([blog de Android Developers](https://android-developers.googleblog.com/2026/06/play-expanded-billing.html)), y la tabla de la página de tarifas más bajas ([ayuda](https://support.google.com/googleplay/android-developer/answer/16954621)) parece fijar el **«resto del mundo» al 30 de septiembre de 2027** (lectura de una tabla mal extraída: **[NO VERIFICADO]**). Para Bolivia hoy se lee 15 % en suscripciones.
- **Bolivia en Google Play:** el registro de desarrollador y el de comerciante están admitidos, con pagos en dólares ([ubicaciones admitidas](https://support.google.com/googleplay/android-developer/table/3539140)). Que **los compradores bolivianos** puedan pagar con medios locales no quedó claro: **[NO VERIFICADO]**.

### 4.2 App Store

Fuente: [Guías de revisión de la App Store, sección 3.1](https://developer.apple.com/app-store/review/guidelines/).

- **3.1.1:** si quieres desbloquear características o funcionalidad dentro de la app (el texto da como ejemplo las **suscripciones**), debes usar compras dentro de la app. Las apps «no pueden usar sus propios mecanismos para desbloquear contenido o funcionalidad, como claves de licencia, códigos QR, criptomonedas…».
- **3.1.1(a) y 3.1.3:** fuera de los países y plataformas con derechos específicos, las apps y sus metadatos no pueden tener «botones, enlaces externos u otros llamados a la acción» que dirijan a un medio de compra distinto. Quienes están en 3.1.3 «no pueden, dentro de la app, animar a los usuarios a usar otro método», aunque sí pueden enviar comunicaciones **fuera de la app** a su base de usuarios.
- **3.1.3 (excepciones que permiten otro medio de pago):**
  - (a) **Apps «lectoras»** (revistas, periódicos, libros, audio, música, video): no aplica.
  - (b) **Servicios multiplataforma:** se puede acceder en la app a lo adquirido en otra plataforma o en la web, **siempre que eso también esté disponible como compra dentro de la app**. Es decir, no exime de ofrecer la compra dentro.
  - (c) **Servicios empresariales:** si la app se vende **directamente a organizaciones o grupos para sus empleados o estudiantes**, esos usuarios pueden acceder a lo comprado antes; las ventas a consumidores, a un solo usuario o a familias deben usar compra dentro de la app. Un proveedor independiente persona natural no es una organización.
  - (d) **Servicios entre dos personas en tiempo real** (clases, consultas): no describe un acceso a una plataforma de oferta.
  - (e) **Bienes y servicios físicos consumidos fuera de la app:** deben cobrarse **sin** compra dentro de la app. Es el caso del **servicio de fumigación** que paga el cliente al proveedor (y por eso la app no usa compras dentro de la app para eso), pero **no** cubre una suscripción de acceso a la plataforma.
- **EE. UU.:** el texto dice que en la tienda de EE. UU. no hace falta autorización para incluir botones, enlaces y llamados a la acción externos. La comisión que Apple puede cobrar por esas compras sigue en litigio: en diciembre de 2025 el Noveno Circuito mantuvo gran parte de la sanción pero permitió una comisión limitada a los costos «necesarios y razonables» del traspaso, y en abril de 2026 la causa volvía al juzgado de primera instancia con Apple pidiendo la revisión de la Corte Suprema ([MacRumors, 2026-04-29](https://www.macrumors.com/2026/04/29/epic-games-wins-reversal-app-store-fee-battle/)). No encontré noticias posteriores: **[NO VERIFICADO]**. No es relevante para una app boliviana salvo que se publique en la tienda de EE. UU.
- **Otras regiones:** los enlaces externos con derecho (`StoreKit External Purchase Link`) están limitados a regiones concretas (la documentación menciona la Unión Europea, Japón y Brasil; la lista completa **[NO VERIFICADO]**). **Bolivia no aparece** en lo que encontré.
- **Comisión.** En el primer año de un suscriptor Apple deja el 70 % del precio, y el 85 % después de un año de servicio pagado; con el **Programa de Pequeños Negocios** (ingresos de hasta un millón de dólares al año), el 85 % desde el primer ciclo ([suscripciones](https://developer.apple.com/app-store/subscriptions/); [Programa de Pequeños Negocios](https://developer.apple.com/app-store/small-business-program/)). Agrocom entraría en ese programa si se inscribe.
- **Bolivia en la App Store:** hay tienda de Bolivia y la tabla de precios de suscripciones de Apple la lista con dólares ([umbrales de precio](https://developer.apple.com/help/app-store-connect/reference/in-app-purchases-and-subscriptions/auto-renewable-subscription-price-increase-thresholds/)); si el comprador boliviano paga en dólares o en bolivianos y con qué tarjeta o saldo, **[NO VERIFICADO]**. La práctica de que muchos proveedores no tienen un medio de pago aceptado por Apple es un riesgo operativo que no pude medir.

### 4.3 Qué dicen las reglas sobre una suscripción de acceso a ofertar

Lectura prudente, **que debe confirmar el dueño con las tiendas**:

1. Una suscripción que habilita **ofertar, contraofertar o aceptar dentro de la app** es una **funcionalidad digital de la app**. Apple la cubre con 3.1.1 («suscripciones» para desbloquear funcionalidad) y Google la cubre como «funcionalidad de la app». Si el cobro es dentro de la app, la regla es usar sus sistemas de compra y pagar su comisión (15 % en Google para suscripciones en Bolivia, según la tabla actual; 15 % o 30 % en Apple, según el programa y la antigüedad del suscriptor).
2. La excepción de los **servicios físicos** (3.1.3(e) de Apple; «physical services» de Google) cubre el pago del trabajo de fumigación, que Agrocom no cobra. No cubre la cuota de acceso a la plataforma.
3. La excepción **empresarial** de Apple (3.1.3(c)) solo cabe para un plan que se venda **directamente a organizaciones** para sus empleados. Un plan «empresa con N pilotos» podría encajar si el contrato se hace con la empresa fuera de la app; un plan para independientes, no.
4. **Pagar fuera de la app y no vender nada dentro:** Google permite comunicar fuera de la app y la política habla de apps de solo consumo; Apple permite comunicar fuera de la app a quienes están en las excepciones de 3.1.3, y exige que lo multiplataforma ofrezca también la compra dentro (3.1.3(b)). Que una cuenta se habilite **sin** que la app venda, muestre precios ni dirija a pagar, y por un acto del administrador tras un pago externo, **no tiene una regla que lo declare permitido ni prohibido** en lo que leí; un revisor podría tomarlo como un «mecanismo propio de desbloqueo» (3.1.1). **Riesgo no cuantificado.** La confirmación debe pedirse a Apple (contacto con App Review) y a Google (soporte de Play Console) antes de cobrar, con el flujo exacto por escrito.

### 4.4 Cómo varía por región (resumen)

| Región | Google Play | App Store |
|---|---|---|
| **Bolivia** (no figura en ningún programa regional de los que encontré) | Sin enlaces ni medios de pago alternativos dentro de la app; suscripciones por Play Billing con 15 % | Sin enlaces ni medios de pago alternativos; suscripciones por compra dentro de la app |
| **EE. UU.** | Orden judicial: ofertas externas y facturación alternativa con programas | Sin autorización previa para enlaces; la comisión sigue en litigio |
| **Unión Europea** | Programas por la Ley de Mercados Digitales | Autorización de enlaces con condiciones propias |
| **Japón, Brasil, Corea del Sur, India** | Corea y la India: facturación alternativa con 4 puntos menos de tarifa | Japón y Brasil aparecen con enlaces autorizados; el resto, **[NO VERIFICADO]** |

## 5. Camino de implementación seguro ante las tiendas (propuesta)

Cada fase se decide por separado; ninguna se implementa con este ADR.

| Fase | Qué ocurre | Qué ve la app | Riesgo con las tiendas |
|---|---|---|---|
| **0. Gratuidad** | Todos gratis. Se registra la suscripción gratuita, sus fechas y sus avisos; se mide (sección 10) | El estado de la suscripción en el perfil («Gratuita hasta el 30 de junio») | Ninguno: no se vende ni se muestra precio |
| **1. Cobro manual fuera de la app** | Agrocom informa el vencimiento y cobra **fuera de la app** (transferencia o QR bancario); un administrador registra el pago en el panel con comprobante y la suscripción se extiende | Solo el estado y la fecha. **Ningún precio, ningún enlace de pago, ninguna palabra como «pagar», «comprar» o «suscribirte»**; los avisos de vencimiento hablan del estado, no de cómo pagar | **Zona gris** descrita en 4.3(4): confirmar con las tiendas; el aviso de cómo pagar sale por correo o WhatsApp, fuera de la app |
| **2. Evaluar un procesador** | Pasarela con QR o débito recurrente, sin pasar por las tiendas (sección 6), solo si las tiendas confirman que el modelo cabe; o **compras dentro de la app** (Play Billing y StoreKit) con la comisión que corresponda | Con IAP: pantalla de planes con precios, restauración de compras y gestión de la suscripción | Con IAP: cumple las reglas, cuesta comisión y trabajo de integración. Con pasarela: depende de la respuesta de las tiendas |

**Opción de respaldo seguro:** si las tiendas no aceptan el cobro fuera de la app, la salida que **sí cumple** las reglas es usar las compras de las propias tiendas para la suscripción del proveedor (con 15 % en la mayoría de los casos), o vender el plan **empresa** por contrato directo con la organización (3.1.3(c)) y dejar los independientes con IAP. Es una decisión futura (sección 12).

**Qué no hacer** (porque cruza reglas que sí están escritas): mostrar precios o botones de pago externos dentro de la app; abrir una página web de pago desde la app (Bolivia no tiene derecho a enlaces externos); vender créditos o paquetes con un código o QR que la app valide (Apple 3.1.1); mencionar en la ficha de la tienda cómo pagar fuera.

## 6. Medios de pago locales y procesadores en Bolivia

**Medios locales** que el dueño podría usar en el cobro manual (fase 1):

- **QR interoperable:** estándar obligatorio para todo el sistema financiero boliviano, impulsado por el Banco Central de Bolivia (BCB); según el BCB y la Asociación de Bancos Privados (Asoban), el QR simple arrancó en 2019 y movió en 2025 más de Bs 357.747 millones ([ASFI](https://asfi.gob.bo/sites/default/files/2025-07/El%20uso%20del%20QR%20ha%20dinamizado%20el%20sistema%20de%20pagos%20en%20Bolivia.pdf); [Los Tiempos, 2026-06-14](https://www.lostiempos.com/actualidad/economia/20260614/banco-central-bolivia-resalta-uso-del-qr-28-veces-segundo-pais)). El QR es el medio con mejor encaje para una cuota pequeña y recurrente: no exige tarjeta.
- **Transferencia bancaria** entre cuentas: simple, requiere conciliar a mano.
- **Billeteras móviles** (por ejemplo Tigo Money): **no encontré datos fiables ni verifiqué cómo operan hoy ni si reciben pagos de comercios**: **[NO VERIFICADO]**.
- **Efectivo** en oficina: posible, difícil de auditar.

**Procesadores:**
- **Stripe:** **no opera en Bolivia**: Bolivia no está en la lista de países admitidos ([stripe.com/global](https://stripe.com/global)). La alternativa de constituir una empresa en otro país (Stripe Atlas) tiene consecuencias tributarias y legales que no analicé: **[NO VERIFICADO]**.
- **PayPal:** las fuentes se contradicen sobre si una cuenta boliviana puede recibir pagos (la mayoría apunta a que solo puede pagar); no es una base segura: **[NO VERIFICADO]** en la fuente oficial.
- **dLocal:** su documentación lista Bolivia con **QR**, transferencia bancaria, tarjetas y efectivo (Multipago, Wabi), en bolivianos, y un método **«Domicíliate»** de débito recurrente por QR interoperable que admite suscripciones; las tarjetas figuran sin recurrencia ([documentación de dLocal](https://docs.dlocal.com/docs/bolivia), actualizada el 2026-11-24). Condiciones comerciales, requisitos de la empresa y tarifas: **[NO VERIFICADO]**.
- Otros agregadores locales o regionales: no investigados.

Conclusión provisional: un procesador internacional «de consumo» (Stripe) no es una opción; hay al menos un proveedor regional con QR y débito recurrente que valdría la pena evaluar en la fase 2; hasta entonces, el cobro es manual.

## 7. Lo tributario y legal: puntos a confirmar con un contador y un abogado

**No es asesoría.** Es una lista de preguntas. **Nada de esto lo verifiqué contra el texto oficial** salvo donde se indica; los datos tributarios salen de un resultado de búsqueda con fuentes secundarias.

Tributario (contador):
1. **Qué impuestos gravan una suscripción** de acceso a una plataforma digital en Bolivia (IVA, Impuesto a las Transacciones) y si el servicio queda gravado o exento. **[NO VERIFICADO]**
2. **Facturación:** el SIN exige emitir factura con el sistema de facturación vigente (en línea o electrónica según el régimen y el calendario); la base citada por las fuentes secundarias es la RND 102100000011 y sus ajustes de plazos ([texto compilado en el sitio del SIN](https://www.impuestos.gob.bo/wp-content/uploads/2025/10/RND11.pdf), que **no leí completo**). Qué modalidad corresponde a Agrocom SRL y desde cuándo. **[NO VERIFICADO]**
3. **Cuándo nace la obligación:** al cobrar, al emitir la suscripción o durante la gratuidad (¿una gratuidad es un servicio sin precio que no se factura o hay que registrarla?). **[NO VERIFICADO]**
4. **Factura a quién:** a personas naturales sin NIT, a empresas con NIT; cómo se maneja el «sin razón social» o el CI.
5. **Cobro por QR o transferencia a la cuenta de la empresa:** conciliación, retención y comprobantes; si hay que informar los ingresos de una billetera.
6. **Cobro por las tiendas (si se llega a IAP):** cómo se factura lo que paga una tienda a Agrocom y el tratamiento de la comisión; impuestos sobre pagos del exterior. **[NO VERIFICADO]**
7. **Reembolsos, notas de crédito y anulaciones** de pagos ya registrados y facturados.

Legal (abogado):
1. **Contrato de suscripción y términos:** objeto, precio, vigencia, renovación, suspensión y reactivación, reembolso o no reembolso, cambio de precio con aviso, jurisdicción. Debe quedar reflejado en la política de términos y en el registro de aceptaciones (`sec_aceptaciones`, ADR 0046).
2. **Derechos del consumidor:** si el proveedor persona natural cuenta como consumidor y qué reglas bolivianas aplican a un cobro recurrente y a la gratuidad. **[NO VERIFICADO]**
3. **Plazos y avisos** para terminar la gratuidad y empezar a cobrar sin que se lea como un cambio unilateral.
4. **Suspensión por falta de pago:** qué se puede bloquear y qué no; el ADR 0046 ya dice que lo ya adjudicado no se interrumpe.
5. **Datos personales** de los pagos y comprobantes (retención, acceso), en línea con `tiendas-y-privacidad.md`.
6. **Quién responde si Agrocom cobra y el proveedor no recibe trabajos:** redacción de que no se garantizan solicitudes ni adjudicaciones.
7. **Los pilotos propios de Agrocom:** la exención se documenta como una condición del tipo de proveedor, no un favor.

## 8. Cómo encaja en el sistema (sin implementarlo)

### 8.1 La suscripción es una dimensión distinta del estado de la cuenta

El ADR 0046 (punto 12) define qué puede hacer una cuenta por su estado de **aprobación**. La suscripción añade una **segunda dimensión**: «aprobada» no significa «al día». Propuesta:

| Aprobación | Suscripción | Entra y ve | Proveedor: ofertar, contraofertar, aceptar |
|---|---|---|---|
| `aprobado` | `gratuita`, `vigente` o `en_gracia` | Sí | Sí |
| `aprobado` | `vencida` o `suspendida` | Sí | **No** |
| `pendiente`, `rechazado`, `suspendido`, `baja` | cualquiera | Según el ADR 0046 | No |

- El servidor decide y responde `403` con `codigo: suscripcion_no_vigente` (distinto de `cuenta_no_aprobada`), con el estado y la fecha, **sin precio ni enlace de pago**. La app lo muestra como estado, no como error.
- La compuerta es solo del proveedor al principio. Si más adelante el cliente tiene plan, la misma lógica aplicaría a publicar y aceptar.
- Pilotos propios: tipo `propio`, suscripción `exenta` (origen `propio`), sin cobro y sin vencimiento.

### 8.2 Catálogo de planes y gratuidad como parámetros, y modelo de datos tentativo

Los planes, precios, periodos, días de aviso, días de gracia y reglas de gratuidad son **datos editables desde el panel y parámetros de configuración** (`plt_configuraciones`, ADR 0016), nunca constantes de código. Tablas tentativas (prefijo `sus_`, módulo `Suscripciones`; la convención de prefijos, ADR 0011, se confirmaría al decidir):

- **`sus_planes`**: `codigo`, `nombre`, `destinatario` (`proveedor` o `cliente`), `perfil` (`independiente`, `tercero`), `periodicidad` (`mensual`, `anual`), `precio` y `moneda` (decimal exacto), `max_pilotos` y `max_drones` (para planes de empresa), `activo`, vigencia del plan y autoría.
- **`sus_gratuidades`**: reglas de gratuidad: `tipo` (`lanzamiento`, `por_cuenta`, `primeros_trabajos`), duración, umbrales de término, `vigente_desde` y `vigente_hasta`, y autoría.
- **`sus_suscripciones`**: `sujeto` (el proveedor titular, o el cliente si algún día hay plan), `plan_id`, `estado` (`gratuita`, `vigente`, `en_gracia`, `vencida`, `suspendida`, `cancelada`, `exenta`), **`origen`** (`gratuidad_lanzamiento`, `gratuidad_cuenta`, `pago_manual`, `cortesia`, `propio` y, en el futuro, `google_play`, `app_store` o `procesador`), `inicia_en`, `vence_en`, `gracia_hasta`, `persona_id` para medir por persona, motivo y autoría. Con historial de estados con autor y motivo, como el proveedor (ADR 0036).
- **`sus_pagos`**: `suscripcion_id`, `monto` (decimal), `moneda`, `metodo` (`qr`, `transferencia`, `efectivo`, otro), `referencia`, `fecha_pago`, `periodo_desde` y `periodo_hasta`, `comprobante_ref` (archivo en el disco privado con ruta por objeto, ADR 0026), `registrado_por` y `registrado_en`, y `anula_a_id` con motivo. **Lo registrado no se edita:** una corrección o anulación es un registro nuevo (invariante 2), y cada pago deja bitácora.

Reglas que se mantienen: dinero en `DECIMAL`, soft delete, transiciones de estado por la máquina de estados del dominio (invariante 7) y no por `UPDATE` suelto, adjudicaciones y negociaciones ya vigentes sin tocar, y la idempotencia al registrar un pago (clave por referencia y suscripción, para que un doble clic no duplique).

La **medición de la gratuidad por persona** (no por cuenta) evita reiniciarla registrándose otra vez; la cuenta de cliente que se vuelve proveedor con otra cuenta recibe la gratuidad de proveedor aparte, porque es otra cuenta de otro tipo.

**Planes de empresa y pilotos vinculados.** La suscripción cuelga del titular de la empresa (proveedor `tercero`); los pilotos con vínculo `activo` (ADR 0046, punto 35) no pagan por separado. El tope de pilotos y drones se valida contra los vínculos activos y los drones de sus equipos; un piloto que se libera y pasa a independiente (punto 36) abre su propia suscripción (con la gratuidad que corresponda a esa persona).

### 8.3 Avisos de vencimiento

Por el motor de notificaciones (ADR 0025), campana y correo, con clave idempotente por suscripción y umbral (como las oleadas del ADR 0045): gratuidad por terminar (30, 7 y 1 días, parámetros), suscripción por vencer, entrada en gracia, suspendida, pago registrado y pago anulado. Un comando diario (`suscripciones:procesar`) calcula vencimientos y pasos. Son categorías **obligatorias** (no se pueden apagar), porque afectan lo que la cuenta puede hacer. El contenido no incluye precio ni enlace de pago; el cómo pagar sale en la comunicación de la oficina por fuera de la app.

### 8.4 Panel (pantallas futuras, sin construirlas)

- **Planes:** catálogo de planes, precios y topes.
- **Suscripciones:** lista por estado y vencimiento, ficha con historial.
- **Registrar pago:** monto, medio, fecha, periodo cubierto y comprobante; extiende la suscripción.
- **Gratuidades:** reglas de lanzamiento y por cuenta, con su fecha de término y los umbrales.
- Permisos propios (`suscripciones.*`), por defecto del administrador; el operador solo si el administrador se los asigna en la matriz (ADR 0046). Registrar un pago es una acción con autor y bitácora.

### 8.5 Qué ve cada app

- **Proveedor (app `piloto`):** en el perfil, el estado de la suscripción y su fecha («Gratuita hasta…», «Al día hasta…», «Vencida»). **Sin precios, sin enlaces de pago, sin botón de pagar** hasta decidir (sección 5). Al intentar ofertar con la suscripción vencida, el motivo en español y sin presionar. El contacto de soporte ya existe en la app (T6 del documento de tiendas).
- **Cliente (app `cliente`):** nada, mientras no haya plan de cliente.

### 8.6 Cuenta suspendida por falta de pago: qué se bloquea y qué no

**Se bloquea:** ofertar, contraofertar y aceptar (y, si algún día hay plan de cliente, publicar y aceptar). Los avisos de nuevas solicitudes dejan de llegar mientras dure (la regla `aprobado` del ADR 0045 sumaría «suscripción al día»; decisión futura).

**No se bloquea:** ver el mapa y las solicitudes abiertas; ver y conversar por los trabajos adjudicados y en ejecución; ejecutar y cerrar un trabajo en curso; calificar y ser calificado; ver su historial; reclamar; **eliminar la cuenta** (las tiendas lo exigen; ADR 0047, en revisión); los datos de los trabajos en curso (contacto del cliente ya entregado tras adjudicar). La cuenta suspendida por falta de pago **nunca** pierde acceso a lo ya adjudicado.

**A decidir:** qué pasa con las ofertas vigentes de un proveedor que se suspende (¿se retiran con aviso o corren hasta vencer?). Una oferta vigente que el cliente acepta después de la suspensión no debería dejar al cliente sin proveedor: es una decisión futura (sección 12).

## 9. Políticas del repo que hoy dicen «sin cobro» y la precisión que habría que hacer

La distinción que habría que escribir: **lo que no se cobra en la app es el servicio** (el pago del trabajo se hace entre cliente y proveedor, fuera de la app, sin comisión). **Una suscripción de acceso a la plataforma es otra cosa**, que hoy no existe. **No se cambia ninguna de estas políticas todavía**: es la lista de puntos.

`agrocom-api`:
- `CLAUDE.md`, invariante 14: «La plataforma no cobra ni calcula comisión». Precisar: no cobra el servicio ni calcula comisión por trabajo; una suscripción de acceso, si se decide, es un cobro aparte y no automático.
- `README.md` (raíz), línea 15: «La app no cobra: el pago se hace fuera de ella».
- `docs/especificacion/marketplace.md`: principio 9 («Sin cobro en la aplicación», línea 19), §8.2 Precio y pago (línea 240) y el alcance de la primera versión (línea 420).
- `docs/politicas/precio_y_pago.md`: sección «Pago» («La aplicación no procesa cobros») y sección «Comisión» («El modelo de ingresos… se decide en un ADR nuevo», que apunta a este).
- `docs/decisiones/0034-precio-acordado-y-pago-fuera-de-la-app.md`: puntos 1 y 5 («la aplicación no cobra», «no hay bloqueo por deuda en el sistema»: la suspensión por falta de pago del acceso lo cambiaría); la alternativa descartada «Retención automática con pasarela».
- `docs/decisiones/0033-cancelacion-sin-penalidad-automatica-y-disputas.md` y `docs/politicas/cancelacion.md` (línea 8: «La plataforma no cobra»): se refieren al servicio y seguirían valiendo con la aclaración.
- `docs/especificacion/negociacion-contrato.md`, línea 270 («sin comisión»).
- `docs/politicas/tiendas-y-privacidad.md`: la fila «Cuenta y datos sin compra» (línea 208), la nota para el revisor («no hay cobro dentro de ella», línea 195), las declaraciones de datos de las tiendas («Lo que Agrocom no recoge: pagos», sección 1.3) y la sección 4.
- `docs/glosario.md`: no tiene entradas de pago ni comisión; habría que agregar «suscripción» y «periodo de gratuidad» al decidir.

`agrocom-field`:
- `CLAUDE.md`: «Sin cobro en la app. No se procesan pagos ni se calcula comisión…».
- `docs/vision.md`: «Qué no hace la app» («No procesa cobros ni calcula comisión», línea 111), la tabla de decisiones («Sin cobro ni comisión calculada en la app», línea 132) y la descripción de Agrocom en «Qué es Agrocom» («La app no cobra», línea 12).
- `README.md`, `DESIGN.md` (línea 8, «Sin cobro dentro de la app: el pago es directo entre cliente y piloto») y `.claude/skills/marketplace-politicas/SKILL.md` (línea 53); `.claude/agents/producto-marketplace.md`.
- `docs/gestion/cola_tareas.md` (línea 47, «Cobro dentro de la app y modelo de comisión») y `docs/gestion/plan_sprints.md` (líneas 69 y 75).
- Los ADR 0013, 0017 y 0020 de field hablan del precio final y del pago confirmado del **servicio** y no cambiarían.

## 10. Medir durante la gratuidad

**Ya existe** (`panel/metricas` y las vistas `vw_metricas_*`, ADR 0039; solo lectura, 7, 30 o 90 días):
- Mercado: solicitudes publicadas y por estado, adjudicadas y tasa de adjudicación, expiradas, hectáreas solicitadas y servidas, **ofertas por solicitud**, rondas promedio, **tiempo hasta la aceptación** (promedio y mediana), diferencia entre precio propuesto y acordado.
- Oferta: proveedores aprobados y pendientes, drones aprobados, ofertas respondidas, tasa de aceptación, cancelaciones del proveedor y su tasa.
- Calidad: calificación promedio, calificaciones por moderar, disputas y su tasa, reembolsos.
- Ingreso: servicios completados, volumen bruto y ingreso por hectárea.
- Tabla de proveedores con más servicios, su calificación, tasa de aceptación y tiempo de respuesta.

Para fijar el precio con datos se necesita medir también (cada punto, con su propósito):
1. **Proveedores activos** (con una oferta o una apertura de la app en el periodo, no solo aprobados): para el denominador de «solicitudes por proveedor» y la cuota que soportan. Hoy solo se cuenta «aprobados»; el `last_used_at` del token de dispositivo es un indicio, no una métrica.
2. **Ofertas por solicitud y por proveedor**, y qué parte de los avisos se convierte en oferta: dice cuánto trabajo le llega y cuánto responde cada proveedor.
3. **Adjudicaciones por proveedor y por mes** (distribución, no solo promedio): para decidir planes por uso o por tramos.
4. **Tiempo hasta la primera oferta y hasta la primera aceptación** por solicitud y por departamento: la salud de la oferta del mercado.
5. **Hectáreas contratadas por proveedor** y su distribución: para dimensionar el plan de empresa y el valor del servicio por proveedor.
6. **Retención:** proveedores y clientes que siguen operando a 30, 60 y 90 días de su aprobación; solicitudes repetidas del mismo cliente: dice cuánto vale quedarse.
7. **Embudo de alta:** registros, verificaciones aprobadas, tiempo hasta la aprobación y cuántos ofertan alguna vez: dónde se pierde la gente antes del cobro.
8. **Valor del trabajo adjudicado por proveedor** (monto final, no comisión): sirve para ver qué cuota es una fracción razonable de lo que gana, sin cobrarla.
9. **Solicitudes sin ninguna oferta** («Esperando piloto») por departamento y por mes: demanda sin atender; dice si falta oferta.
10. **Disposición a pagar:** una encuesta breve a proveedores durante la gratuidad (fuera de la app o en la oficina); no es una métrica del sistema, pero es el dato que más pesa.
11. **Elusión sospechada:** proporción de solicitudes adjudicadas que no se finalizan en la app o se cancelan tras adjudicar: mide si el contacto se usa para cerrar por fuera.

## 11. Lo que no pude verificar y debe confirmar el dueño

- **La pregunta principal:** si una suscripción de acceso a ofertar, **cobrada fuera de la app y habilitada por un administrador**, sin precios ni enlaces dentro de la app, cumple las reglas de Google Play y de la App Store. No hay una regla que lo diga; hay que preguntarlo a las tiendas con el flujo exacto (sección 4.3). **[NO VERIFICADO]**
- Si un plan **empresa** vendido por contrato cabe en la excepción empresarial de Apple (3.1.3(c)) y cómo lo ve Google.
- Que el «resto del mundo» (donde está Bolivia) entre a la nueva estructura de Google el **30 de septiembre de 2027** (lectura de una tabla mal extraída) y qué tarifa de facturación se anunciará para ese grupo.
- Si los compradores bolivianos pueden pagar en Google Play y en la App Store, en qué moneda y con qué medios de pago.
- La lista completa de regiones con enlaces externos autorizados en la App Store y el estado actual del litigio en EE. UU. (la última noticia que encontré es de abril de 2026).
- Cómo operan hoy las billeteras móviles de Bolivia para recibir pagos de una empresa.
- Si PayPal permite recibir pagos en Bolivia; las condiciones, requisitos y tarifas de dLocal (u otro agregador) en Bolivia; si hay otros procesadores.
- **Todo lo tributario y legal** de la sección 7: no leí la normativa; lo citado es una referencia de búsqueda.
- Cómo se trataría una gratuidad como figura contractual y si exige alguna formalidad.

## 12. Decisiones futuras

Lo que habrá que decidir cuando el dueño haya investigado:

1. **Quién paga:** proveedor, cliente o ambos, y en qué orden.
2. **La forma de la gratuidad** (por cuenta, de lanzamiento, por trabajos) y sus valores: duración, umbrales de masa crítica, fecha tope, gracia y avisos.
3. **Cuándo y cómo termina la gratuidad:** el anuncio y el plazo de aviso a los proveedores.
4. **Los planes y los precios:** independiente, empresa y sus tramos; periodicidad (mensual o anual); descuentos; si hay plan para dron adicional o piloto adicional.
5. **Los proveedores propios:** confirmar que no pagan y con qué marca en el sistema.
6. **El canal de cobro de cada fase:** cobro manual (QR, transferencia), un procesador regional, o las compras de las tiendas; y el cronograma entre fases.
7. **La respuesta de las tiendas** y, según ella, si se usa el cobro externo, las compras de las tiendas, o un contrato directo con empresas.
8. **Qué se bloquea al vencer** y qué ocurre con las ofertas vigentes y con los avisos de nuevas solicitudes.
9. **La política de reembolsos, cambios de plan y cancelación** de la suscripción.
10. **La facturación:** régimen, modalidad, quién factura y a qué datos (con el contador).
11. **El contrato y los términos** de la suscripción y su versión (con el abogado) y su aceptación registrada.
12. **Si algún día la comisión se reconsidera** y bajo qué condición (pago por la plataforma).
13. **Cuándo se reescriben las políticas** de la sección 9 y con qué texto.
14. **Qué se mide** de la sección 10 y a partir de cuándo, y qué cifra dispara el paso de la gratuidad al cobro.
15. **El plan del cliente** y el contacto liberado o la promoción, si se retoman (3.3, 3.6 y 3.7).

## Consecuencias

- Hoy no cambia nada en el sistema, las apps ni las políticas: es un documento de trabajo.
- La suscripción queda como **otra dimensión** del estado de la cuenta (no un nuevo estado de aprobación) y como una pieza con **origen** de pago intercambiable, para no rehacer el modelo al cambiar de canal.
- Cuando el dueño decida, este ADR pasa a «aceptada» (o se reemplaza), se ajustan las políticas de la sección 9 y se abren las tareas de la cola.
- Revisable en cualquier momento: las tiendas cambian sus reglas y hay litigios abiertos.

# Tiendas de aplicaciones y privacidad de datos personales

**Agrocom SRL · Vigente con las decisiones del dueño del 27/11/2026, pendiente de revisión legal** · Consulta de fuentes: **2026-11-26** · Decisión de fondo: ADR 0047 (eliminación de cuenta, retención, reportar y bloquear, ubicación de emergencia), ADR 0046 (cuentas, verificación y retención), ADR 0040 (ubicación y contacto) · Política hermana de `eliminacion_de_cuenta.md`, `seguimiento_y_privacidad.md`, `verificacion_proveedores.md`, `seguridad.md` y de `legal.md` (términos y privacidad públicos de DronAgro, versión 2026-2). **Antes de enviar a las tiendas: sección 6.**

> **Esto no es asesoría legal.** Reúne lo que las tiendas piden hoy y lo que las leyes de la región suelen exigir, para que el dueño llegue a la reunión con un abogado boliviano con las preguntas hechas. Las citas de leyes salen de búsquedas y de textos oficiales leídos en la fecha indicada; donde no pude verificar algo, está marcado **[NO VERIFICADO]**. La fuente de verdad de este tema vive en `agrocom-api`; `agrocom-field` solo lo enlaza. El dueño decidió ajustarse a lo que las tiendas exigen («si son restricción de las tiendas, nos tenemos que ajustar a ellas»): lo que este documento marca como decidido rige como política y se implementa por el plan de tareas de la sección 4.

## 0. Lo que se decidió

La investigación encontró nueve puntos donde el diseño no cumplía a las tiendas. El dueño decidió aplicarlos todos, y esto es lo vigente:

1. **«Eliminar mi cuenta»** dentro de las dos apps (Perfil, Privacidad y datos, con confirmación) y en la página pública `/eliminar-cuenta`. La eliminación voluntaria **anonimiza los datos personales en el lugar**; ya no es un congelamiento. Servicios, ofertas y calificaciones se conservan sin datos identificables. Qué se anonimiza y qué se conserva, plazos y auditoría: `eliminacion_de_cuenta.md` y ADR 0047 (1.5 y 1.6).
2. **Retención de la selfie y el documento, opción B:** 12 meses solo si hubo antecedentes (suspensión, rechazo por fraude, incidencia grave o reclamo) o una baja administrativa; en una eliminación voluntaria sin antecedentes, 90 días; después solo queda un registro mínimo antifraude sin imágenes ni nombre (1.5).
3. **El soft delete (invariante 8) y los esquemas `respaldo_*` (invariante 15)** no sirven para datos personales: reciben una excepción redactada en `CLAUDE.md` (ADR 0047, punto 13), y los respaldos rotan en 30 días.
4. **Chat con «Reportar» y «Bloquear».** El reporte es un reclamo de tipo conducta y abre a soporte solo lo reportado (1.9).
5. **La ubicación de un SOS o una incidencia se guarda** solo si la persona pulsa el botón o reporta, con finalidad de seguridad, 90 días después de cerrar la incidencia, y está declarada. La frase absoluta «la ubicación no se guarda» se reemplazó por la formulación exacta (1.3 y 1.4).
6. **Dominio y HTTPS** son trabajo de infraestructura del dueño y condición para publicar: lista de verificación en la sección 6.
7. **El mapa** con teselas públicas de OpenStreetMap no es base para una app publicada; la alternativa es una decisión del dueño con tres opciones (1.12).
8. **Sign in with Apple no aplica** (1.11), la edad mínima es de **18 años** y las declaraciones de Data safety y App Privacy están alineadas con lo anterior (1.4 y 1.8).
9. **Quién ofrece en una empresa** quedó resuelto (el encargado, con delegación opcional) y no es un tema de las tiendas.
10. **Textos legales** (términos y privacidad), edad mínima, responsable, terceros, plazos, derechos y responsabilidades: publicados sin aviso de borrador en la versión 2026-2 (T3). Los datos de contacto y de la empresa que faltan son configuración (`config/legal.php`). Los ajustes de código que esos textos suponen están en la sección 7.

## 1. Lo que cada tienda exige y cómo se aplica a Agrocom

Cada fila dice qué pide la tienda, la fuente oficial y la fecha de consulta, y cómo queda Agrocom. «Estado» usa: **cumple** (el diseño ya lo cubre), **falta** (hay tarea) y **decidir** (requiere decisión del dueño).

### 1.1 Cuenta de desarrollador

| Requisito | Fuente | Aplicación | Estado |
|---|---|---|---|
| Google: la cuenta de organización exige un número D-U-N-S; el nombre legal y la dirección deben coincidir con el perfil de pagos; puede tardar hasta 30 días | [Help: información para crear una cuenta](https://support.google.com/googleplay/android-developer/answer/13628312) (resumen por búsqueda, 2026-11-26) | Se abre como **organización a nombre de Agrocom SRL** | falta (tramitar D-U-N-S) |
| Google: las cuentas **personales** nuevas deben hacer una prueba cerrada con 12 testers durante 14 días antes de producción | [Requisitos de prueba](https://support.google.com/googleplay/android-developer/answer/14151465) (2026-11-26). La página no dice nada sobre cuentas de organización | No aplica si la cuenta es de organización **[NO VERIFICADO que una organización esté exenta]** | verificar |
| Apple 5.1.1(ix): las apps que «requieren información sensible del usuario» deben enviarse por una **entidad legal**, no por un individuo | [App Review Guidelines](https://developer.apple.com/app-store/review/guidelines/) (2026-11-26) | Se inscribe como organización (Apple pide D-U-N-S para eso: **[NO VERIFICADO en esta consulta]**) | falta |
| Apple: la información de contacto del desarrollador debe ser fácil de encontrar (1.5) | Mismas pautas | Correo y teléfono de soporte en la app, en la ficha y en el Support URL | falta |

### 1.2 Política de privacidad pública

| Requisito | Fuente | Aplicación |
|---|---|---|
| Google: enlace en Play Console **y dentro de la app**; URL pública, activa, no PDF; debe cubrir contacto del desarrollador, qué datos se acceden, recogen, usan y comparten y con quién, manejo seguro, y la **política de retención y eliminación** | [User Data](https://support.google.com/googleplay/android-developer/answer/10144311) y [App content](https://support.google.com/googleplay/android-developer/answer/9859455) (2026-11-26) | `GET /privacidad` ya existe como borrador. Debe completarse (sección 4, T3) y enlazarse en la ficha y dentro de las dos apps |
| Apple 5.1.1(i): enlace en App Store Connect **y dentro de la app**, que identifique qué datos se recogen, cómo y para qué; confirme que los terceros dan igual protección; explique retención y eliminación y cómo **revocar el consentimiento y pedir la eliminación** | [App Review Guidelines](https://developer.apple.com/app-store/review/guidelines/) 5.1.1(i) (2026-11-26) | Igual. La lista de terceros (hosting, correo, push, mapas) debe estar escrita |
| Apple: «Privacy Policy URL» obligatoria; «Privacy Choices URL» opcional | [App Privacy Details](https://developer.apple.com/app-store/app-privacy-details/) (2026-11-26) | La página de eliminación (1.6) sirve de Privacy Choices URL |
| Las dos: la política debe coincidir con lo declarado en Data safety y App Privacy | Mismas fuentes | Un solo mapa de datos (1.3) alimenta política y declaraciones |

La política de privacidad **no reemplaza** la explicación previa en la app (Google «prominent disclosure»; Apple 5.1.1(ii) y 5.1.5).

### 1.3 Mapa de datos: dato, para qué, si se comparte, si es obligatorio y cuánto se conserva

Es la base de la política, de Data safety y de App Privacy. «Comparte» incluye lo que ve la otra parte del servicio y los terceros (proveedores de infraestructura). Las retenciones salen del ADR 0047, el ADR 0046 §33, el ADR 0040 y las políticas. «Eliminación» es lo que pasa cuando la persona elimina su cuenta (`eliminacion_de_cuenta.md`).

| Dato | Para qué | ¿Se comparte? | ¿Obligatorio? | Retención y eliminación |
|---|---|---|---|---|
| Nombre y apellidos | Saber quién es cada persona y que cada oferta sea real | El cliente ve al proveedor en las ofertas (nombre comercial, logo, calificación). El proveedor ve el nombre del cliente solo tras adjudicar | Sí | Mientras exista la cuenta. Eliminación: la cuenta pasa a «Cuenta eliminada»; la persona (`sec_personas`) se anonimiza a los 90 días sin antecedentes o a los 12 meses con ellos, si no tiene otra cuenta viva |
| Teléfono | Referencia para entrar y contacto | Cliente y proveedor se ven el teléfono solo tras adjudicar. Nadie más | Sí | Mientras exista la cuenta. Eliminación: se borra y queda libre |
| Correo de respaldo | Recuperar el acceso con un código; avisos | No | Sí | Igual |
| Documento (tipo, número) y fecha de nacimiento (proveedor) | Una persona, una cuenta por tipo; mayoría de edad de quien vuela | No. Las otras partes ven la insignia «Verificado» | Sí, para operar | Igual que el nombre; después, solo una huella con clave del servidor (registro antifraude, 12 o 36 meses) |
| Imagen del documento | Que quien revisa compruebe que la persona existe | No. Solo quien tiene `seguridad.verificacion.ver` | Sí, para operar (no para entrar y mirar) | Mientras exista la cuenta. Después: **90 días** si eliminó su cuenta sin antecedentes; **12 meses** con antecedentes o baja administrativa; rechazadas o reemplazadas, 90 días |
| Selfie (cámara, en el momento) | Comparar con el documento | No. Igual | Sí, para operar | Igual |
| Foto de perfil, logo | Reconocer al proveedor en la oferta | Sí, a la otra parte | No | Con la cuenta; se borra al eliminarla |
| Nombre comercial, razón social, NIT, dirección | Datos de empresa | Logo y nombre del proveedor, al cliente en las ofertas; los del cliente, al proveedor tras adjudicar | No | Con la cuenta; se borran al eliminarla |
| Documentos del proveedor (licencia, matrículas, seguros) y fotos del dron | Aprobar al proveedor | No. Solo quien revisa | Sí, para ofertar | Con la cuenta; se borran al eliminarla |
| Terreno y lotes (contorno, ubicación, hectáreas, cultivo, accesos, colindantes) y fotos del terreno | Que el proveedor decida su precio | Proveedores con la solicitud visible, antes de adjudicar y **sin la identidad del cliente** | Sí, para publicar | Con la cuenta. Al eliminarla, se borran el contorno, las fotos, los accesos y las observaciones; la contraparte conserva hectáreas, cultivo, departamento, provincia, fechas y precio |
| Ubicación del piloto (última posición) | Que el cliente vea que el servicio avanza | Cliente del servicio, solo durante `en_ejecucion` | **No**: el piloto puede desactivarla | Se elimina al finalizar o cancelar; nunca en logs, bitácora ni historial |
| **Ubicación de emergencia (SOS e incidencias)** | Avisar a soporte donde ocurre la emergencia y atenderla (seguridad) | Soporte; no la contraparte | No: solo si la persona pulsa el botón o reporta | **90 días después de cerrar la incidencia**; nunca de forma automática ni en segundo plano; no entra a logs ni analíticas. En el piloto es la posición del dispositivo; en el cliente, la del terreno del servicio |
| Fotos de avance | Probar el avance y cerrar el servicio | Cliente del servicio | Sí, para cerrar | Hasta 12 meses después de que cierra el plazo de reclamo y de calificación (`retencion.servicio_meses`) |
| Mensajes del chat | Coordinar el servicio | La otra parte. Soporte solo lee lo reportado, con consentimiento de quien reporta, o con un reclamo abierto | No | Igual que las fotos. Al eliminar la cuenta, se borra el texto de los mensajes que escribió |
| Reportes y bloqueos | Moderar el chat y proteger a las personas | Soporte; el reportado no ve quién reportó | No | El reporte, con el servicio; el bloqueo, hasta que la persona lo deshaga o elimine su cuenta |
| Ofertas, precio acordado, avance, cierre, calificaciones, reclamos | Ser el registro del resultado del servicio | La contraparte | Sí | Se conservan; tras la eliminación muestran «Cuenta eliminada» y de la calificación se conservan el puntaje y las etiquetas, no el comentario |
| Token de sesión, `uuid_dispositivo`, token de push | Sesión, un dispositivo por token y avisos | Apple o Google si se usa su push **[ver T12]** | Sí (sesión) | Se revocan al cerrar sesión, dar de baja o eliminar la cuenta |
| Aceptación de términos y privacidad (versión, fecha, app) | Prueba de que la persona aceptó | No | Sí | 36 meses (`retencion.aceptaciones_meses`), sin el identificador del dispositivo tras eliminar la cuenta |
| Bitácora de auditoría y accesos a verificaciones | Seguridad y responsabilidad | No | Sí | Se conserva quién, cuándo y qué; los valores personales se redactan al eliminar la cuenta. **[POR DEFINIR]** el plazo de la bitácora misma |
| IP y registros del servidor (límites de frecuencia, errores) | Seguridad | No | Sí | **[POR DEFINIR]** (por ejemplo 30 días) |
| Registro mínimo antifraude (huella del documento, motivo, fecha) | Frenar la suplantación y el fraude reiterado | No | Sí, tras una eliminación | 12 meses sin antecedentes; 36 con ellos |
| Respaldos | Recuperar el servicio ante un fallo | No | Sí | Rotan en 30 días; una restauración reaplica las eliminaciones ejecutadas |
| IP y zona visible del mapa hacia el proveedor de teselas | Dibujar el mapa | **Sí, al proveedor de mapas (hoy la Fundación OpenStreetMap)** | Sí, para ver mapas | Según la política de ese tercero; decisión en 1.12 |

Lo que Agrocom **no** recoge: pagos, contactos del teléfono, galería completa (solo las fotos que la persona elige), micrófono, ubicación en segundo plano, identificador de publicidad, analíticas ni reportes de fallos de terceros. Si algún día se agrega un SDK de analíticas o de fallos, hay que declararlo antes (Apple pide declarar también lo que recogen los SDK; Google igual).

**La ubicación, con la formulación exacta.** La ubicación del piloto durante la ejecución se guarda solo como última posición y se elimina al finalizar; la única otra ubicación que se guarda es la de una emergencia o incidencia, solo si la persona pulsa el SOS o reporta, y 90 días después de cerrar la incidencia. La app no guarda ninguna ubicación en `drift`, logs, reportes de error ni analíticas, salvo la que viaja dentro del registro de la incidencia mientras espera sincronizarse. Esta es la frase que reemplaza a «la ubicación no se guarda».

**Uso único.** Los datos se usan para garantizar que cada persona es real y que cada oferta es genuina, y para operar el servicio. Apple 5.1.2(ii) prohíbe reutilizar un dato para otro fin sin nuevo consentimiento, y 5.1.2(vi) prohíbe usar datos de las APIs de cámara y fotos para publicidad o minería de datos. La política de privacidad lo dice expresamente: **la selfie y el documento no se usan para nada más que verificar y prevenir fraude, no se venden ni se comparten** (Google también prohíbe vender datos personales y sensibles).

**Biometría.** El diseño compara la selfie con el documento por una **persona** (quien revisa); el sistema no extrae plantillas faciales ni hace reconocimiento automático. Esa decisión es lo que hoy sostiene que la selfie es una foto y no un dato biométrico tratado. Si algún día se automatiza la comparación, cambia la clasificación en las tiendas y en varias leyes (LGPD, Ley 1581, Ley 21.719) y se exigen más garantías. Debe quedar escrito en la política y confirmarlo un abogado.

### 1.4 Declaraciones: Data safety (Google) y App Privacy (Apple)

Reglas generales leídas el 2026-11-26. **Google:** [Data safety](https://support.google.com/googleplay/android-developer/answer/10787469). Un formulario por paquete, así que hay **dos** (`...cliente` y `...piloto`); «recoger» es sacar datos del dispositivo, incluidos los que envían librerías; «compartir» es darlos a un tercero (los proveedores que tratan datos por cuenta propia, las transferencias legales y las acciones iniciadas por el usuario quedan fuera); hay que decir si todo viaja cifrado, si la persona puede pedir la eliminación, y la declaración debe seguir exacta siempre. **Apple:** [App Privacy Details](https://developer.apple.com/app-store/app-privacy-details/). Hay que declarar todo lo recogido aunque no sea para analíticas ni publicidad, vinculado o no a la persona, y si se usa para rastreo (no se usa). «Fraude» no es un propósito aparte: va dentro de «App Functionality». Se puede cambiar sin una versión nueva.

**Versión alineada con las decisiones del 27/11/2026** (sustituye al borrador). Se completa en Play Console y App Store Connect al enviar (T9); cada fila vale para las dos apps salvo lo que dice la columna «Solo en».

| Dato | Google Data safety | Apple App Privacy | Propósito | Vinculado | Opcional | Solo en |
|---|---|---|---|---|---|---|
| Nombre, teléfono, correo, ID de usuario | Información personal: nombre, correo, ID de usuario, número de teléfono | Contact Info (nombre, correo, teléfono) e Identifiers (User ID) | Funcionamiento de la app, gestión de cuenta, prevención de fraude | Sí | No | |
| Documento (número), fecha de nacimiento | Información personal: «otra información» | **[NO VERIFICADO]** No encontré una categoría de Apple para documentos de identidad; la salida prudente es «Other Data Types» | Funcionamiento, prevención de fraude | Sí | No | Fecha de nacimiento: piloto |
| Selfie, imagen del documento, foto de perfil, fotos del terreno y de avance, fotos del dron | Fotos y videos | Photos or Videos | Funcionamiento, prevención de fraude | Sí | La de perfil sí | Avance y dron: piloto; terreno: cliente |
| Ubicación del piloto durante la ejecución | Ubicación precisa | Precise Location | Funcionamiento de la app | Sí | **Sí** (puede desactivarla) | Piloto |
| **Ubicación de emergencia (SOS e incidencias)** | Ubicación precisa; recogida solo al pulsar el SOS o reportar; propósito de seguridad; conservada 90 días | Precise Location; Funcionamiento de la app | Seguridad (dentro de «App Functionality» en Apple) | Sí | **Sí** | Las dos (en el cliente es la del terreno del servicio, ya registrada) |
| Contorno y ubicación del terreno | Ubicación precisa (lo ingresa la persona) | Precise Location | Funcionamiento de la app | Sí | No | Cliente |
| Mensajes, reportes de conducta | Mensajes: «otros mensajes en la app» | Other User Content | Funcionamiento de la app | Sí | No | |
| Ofertas, calificaciones, avance | Actividad de la app: «otro contenido generado por el usuario» | Other User Content | Funcionamiento de la app | Sí | No | |
| Token de dispositivo y de push | Dispositivo u otros ID | Device ID | Funcionamiento de la app | Sí | No | |
| Cifrado en tránsito | Sí (HTTPS; **requiere T4**) | n/a | | | | |
| ¿Se puede pedir la eliminación? | **Sí**: dentro de la app y en la URL `/eliminar-cuenta`; se eliminan los datos personales y se conserva, declarado, lo de la retención B (selfie y documento 90 días o 12 meses, registro antifraude, servicios anonimizados) | n/a (la eliminación va dentro de la app; la web es la «Privacy Choices URL») | | | | |
| Datos que se comparten con terceros | **No** como venta ni publicidad; los proveedores que tratan datos por cuenta de Agrocom (alojamiento, correo, mapas, push) se nombran en la política | Igual | | | | |
| Rastreo / publicidad | No hay | No hay (no se usa App Tracking Transparency) | | | | |

La selfie y el documento que **solo ve quien revisa** siguen siendo datos «recogidos»: Apple define recoger como enviarlos fuera del dispositivo para conservarlos más de lo necesario para atender la petición, y Google como transmitirlos fuera del dispositivo.

**Lo que no pude confirmar de la clasificación:** si Apple trata la selfie de verificación como «Photos or Videos» o como «Sensitive Info» (que incluye datos biométricos). Su página no lo dice. Como no hay procesamiento biométrico, se declara como fotos; conviene confirmarlo en la reunión de App Store Connect o con soporte de Apple antes del primer envío.

**Compartir.** Lo que una parte ve de la otra es parte del funcionamiento y lo inicia la persona al ofertar o adjudicar; Google no lo cuenta como compartir con terceros cuando es una acción iniciada o esperada por el usuario, pero la guía no habla del caso de dos usuarios de un marketplace **[NO VERIFICADO]**. Se explica igual en la política. Los terceros reales hoy son: alojamiento (servidor propio en Proxmox, hasta nuevo aviso), el servidor de correo configurado en `plt_configuraciones`, el servicio de push que se elija (T12) y el proveedor de teselas del mapa (T11, decisión en 1.12). Los proveedores que tratan datos por cuenta de Agrocom no cuentan como «compartir» en Google, pero sí deben estar nombrados en la política.

### 1.5 Eliminación de cuenta y retención de la selfie y el documento

**Qué piden las tiendas** (consultas del 2026-11-26):

| Requisito | Google | Apple |
|---|---|---|
| Dentro de la app | Opción visible para iniciar la eliminación de la cuenta y sus datos ([Account deletion](https://support.google.com/googleplay/android-developer/answer/13327111), [User Data](https://support.google.com/googleplay/android-developer/answer/10144311)) | Obligatorio si la app permite crear cuenta ([5.1.1(v)](https://developer.apple.com/app-store/review/guidelines/) y [Offering account deletion](https://developer.apple.com/support/offering-account-deletion-in-your-app/)); se espera en los ajustes de la cuenta |
| Fuera de la app | Un enlace web donde pedirla sin reinstalar, con el nombre de la app o del desarrollador como aparece en Play, fácil de encontrar | Si hay que terminar en una web, enlace directo a esa página; no obliga a tener una |
| Qué cuenta como eliminar | Borrar los datos asociados. «Temporary account deactivation, disabling, or "freezing" does not qualify» | Borrar el registro de la cuenta y los datos personales asociados. Desactivar «es insuficiente». El contenido de usuario se borra con la cuenta |
| Qué se puede conservar | «Security, fraud prevention or regulatory compliance», declarándolo claramente (la política de privacidad es un lugar adecuado) | Lo que la ley obliga a conservar; hay que decir qué sí y qué no se conserva |
| Pasos y plazo | Se pueden explicar pasos previos; completar «within a reasonably quick timeframe»; revisar leyes locales | Se puede pedir un código al correo o teléfono de la cuenta y confirmar la intención. No exigir llamada, correo ni chat de soporte. Decir cuánto tarda y confirmar al terminar. No bloquear con trabas innecesarias |
| Alcance regional | Todas las personas | «Ofrecerla solo por CCPA o GDPR no es suficiente» |
| Dónde se declara | Formulario Data safety, preguntas de eliminación | App Review |

**Decisión del dueño (27/11/2026): se aplica.** Hay tres cosas distintas, que antes el ADR 0046 mezclaba en «baja»:

1. **Baja administrativa** (suspender o dar de baja una cuenta): decisión de un administrador, la cuenta queda cerrada. Sigue como está y cuenta como antecedente.
2. **Eliminación pedida por la persona:** anonimiza los datos personales **en el lugar**; no es un congelamiento. Es lo que las tiendas exigen.
3. **Conservación mínima:** lo único que sobrevive a la eliminación, declarado y con plazo.

**Cómo funciona** (ADR 0047, puntos 1 a 6, y `eliminacion_de_cuenta.md`): se pide en la app con la contraseña o un código (WhatsApp, SMS o correo), o en la web con el código; no se puede con un servicio adjudicado o en ejecución, un reclamo abierto, una incidencia grave abierta o pilotos vinculados (la app explica el motivo y la salida: son pasos previos que las dos tiendas admiten); al pedirla se cancelan las solicitudes abiertas y se retiran las ofertas; hay 7 días de arrepentimiento (la persona cancela entrando a la app); a los 30 días como máximo está ejecutada, y se avisa por correo al pedirla y al terminar. Los plazos son parámetros.

**Qué se conserva y por qué** (debe estar escrito en la política y en la pantalla de eliminación):

| Lo conservado | Motivo | Por cuánto | Quién lo ve |
|---|---|---|---|
| Selfie e imagen del documento (y la persona que las acompaña), **sin antecedentes** | Defender a Agrocom y a la contraparte en un reclamo | **90 días** desde la eliminación | Solo quien tenga el permiso sensible, por un reclamo; cada acceso queda registrado |
| Lo mismo, **con antecedentes** (suspensión, rechazo por fraude, incidencia grave, reclamo con responsabilidad) o tras una baja administrativa | Prevenir fraude y suplantación (volver a registrarse con otra identidad) y defender reclamos | **12 meses** | Igual |
| Registro mínimo antifraude: huella del documento con clave del servidor, motivo y fecha; sin imágenes ni nombre | Frenar a quien vuelve a registrarse tras un fraude; la huella no permite recuperar el documento | 12 meses sin antecedentes; 36 con ellos | Administradores, solo como marca «documento con antecedentes»; nada es automático |
| Ofertas, precio acordado, avance, cierre, hectáreas y reclamos cerrados | La plataforma es el registro del resultado; la contraparte los necesita para sus propios reclamos y su historial | Mientras sea posible un reclamo; mensajes y fotos, 12 meses más | La contraparte, con la persona mostrada como «Cuenta eliminada» |
| Calificaciones (puntaje y etiquetas, sin comentario ni autor identificable) | No falsear la reputación ni el historial de las otras partes | Con el registro del servicio | La contraparte y el panel |
| Aceptaciones, bitácora de auditoría | Probar qué se aceptó y qué se hizo; la bitácora conserva quién, cuándo y qué, sin los valores personales | Aceptaciones 36 meses; bitácora **[POR DEFINIR]** | Administradores |

**Por qué conservar sigue siendo defendible, y su riesgo.** Apple escribe «datos que el desarrollador **no está legalmente obligado** a conservar». No se encontró ninguna norma boliviana que obligue a guardar la imagen de un documento o una selfie; lo que existe es un interés legítimo (antifraude y defensa en reclamos) que las leyes de la región admiten bajo ciertas condiciones, y Google admite expresamente. Por eso la retención B (la opción recomendada por la investigación) limita los 12 meses a quien dio motivo, reduce a 90 días lo de quien se va sin antecedentes y lo declara; la selfie y el documento nunca se usan para otra cosa (5.1.2(ii)). Lo confirma un abogado (sección 3). Las opciones que se descartaron: **A** (12 meses para todas las cuentas, por «temas legales»: riesgo de objeción de Apple y de conservar de más la cara y el documento de quien no dio motivo) y **C** (no conservar imágenes nunca: la más fácil de defender, pero sin pruebas para un reclamo).

**Texto de la pantalla de eliminación** (se repite en la política): «Eliminamos tu cuenta y tus datos personales. Por seguridad y para prevenir fraude, conservamos tu selfie y la imagen de tu documento durante 90 días (12 meses si tu cuenta tuvo suspensiones, reclamos o incidencias graves); solo las ve personal autorizado de Agrocom y luego se destruyen. Tus servicios y calificaciones se conservan sin tu nombre».

**Respaldos.** Los datos eliminados siguen en los respaldos hasta que rotan: **30 días** (`respaldos.retencion_dias`, se dice en la política), y el procedimiento de restauración vuelve a aplicar las eliminaciones ejecutadas desde un registro que vive fuera de la base (`docs/seguridad/despliegue.md`). La excepción a los invariantes 8 y 15 que lo sostiene está en `CLAUDE.md` (ADR 0047, punto 13) y se marca como decisión revisable.

### 1.6 Página web de solicitud de eliminación

Google la exige: «un enlace web donde las personas pidan la eliminación sin reinstalar la app», que nombre la app o el desarrollador como aparecen en Play, y fácil de encontrar. Apple no la pide, pero la acepta como complemento y como «Privacy Choices URL». **Se construye** (T2).

- Ruta pública `GET /eliminar-cuenta` (sin sesión, sin PDF), enlazada desde `/privacidad`, `/terminos`, la ficha de Play (campo de eliminación de datos), el campo «Privacy Choices URL» de Apple y las apps.
- Contenido: nombre «Agrocom», para cuál app aplica (cliente o piloto, porque el mismo teléfono puede estar en las dos), qué se elimina, qué se conserva y por cuánto (la tabla de 1.5), plazo y cómo se confirma.
- Flujo: la persona escribe su teléfono, usuario o correo y elige la app; la página responde siempre igual; se envía un código al **correo de respaldo** (el mismo mecanismo de recuperación del ADR 0046 §27, con el propósito `eliminar_cuenta` y sus límites) y, al confirmarlo, se muestran el resumen y los impedimentos (si hay un servicio en curso, etc.) y queda registrada la solicitud. Sin código no se acepta: es la única forma de que un tercero no elimine la cuenta de otro, y por eso el estado de la cuenta se muestra solo después del código.
- Para quien perdió el acceso al correo: un formulario de contacto que abre una solicitud revisada por un administrador comparando selfie y documento (la vía que ya describe el ADR 0046); el administrador registra la solicitud con `origen = oficina`.
- La solicitud entra a una cola del panel con estado, plazo y autor; la persona recibe un correo al pedirla y otro al terminar (se envía antes de borrar el correo de la cuenta).

### 1.7 Permisos y textos de propósito

Fuentes: [User Data](https://support.google.com/googleplay/android-developer/answer/10144311) y [Permisos de ubicación](https://support.google.com/googleplay/android-developer/answer/9799150) (Google); 5.1.1(ii), 5.1.1(iii), 5.1.1(iv), 5.1.2(i) y 5.1.5 de las [pautas de Apple](https://developer.apple.com/app-store/review/guidelines/) (2026-11-26). Apple pide textos de propósito claros, completos y que no condicionen el uso de la app a activar ubicación o notificaciones. Su guía de interfaz sobre permisos no cargó en la consulta: lo que sigue sobre pedir en contexto y explicar antes del aviso del sistema es **[NO VERIFICADO contra la página oficial]**.

| Permiso | Dónde se usa | Qué dice hoy | Texto propuesto (es) | Notas |
|---|---|---|---|---|
| Cámara | Selfie de verificación (obligatoria con cámara, sin galería), fotos de avance del piloto, foto del terreno, documento, incidencias | iOS: «Agrocom usa la cámara para fotografiar el lote, las incidencias y los documentos» | «Agrocom usa la cámara para tomarte una selfie y fotografiar tu documento, y para tomar fotos del terreno y del avance del servicio. Solo las ve quien tiene que verificarlas o quien participa del servicio.» | Falta la selfie. Pedirla justo antes del paso de la selfie, con una pantalla que explique para qué sirve y que no se usa para nada más |
| Fotos | Elegir foto de perfil, logo, documento y fotos del terreno de la galería (no la selfie) | iOS: «necesita acceder a tus fotos para adjuntar evidencias y documentos» | «Agrocom accede solo a las fotos que eliges para tu perfil, el logo de tu empresa, tus documentos y el terreno. No lee el resto de tu galería.» | Apple 5.1.1(iii) recomienda el selector del sistema en lugar de acceso completo |
| Ubicación (en uso) | Solo la app del piloto, durante un servicio en ejecución | iOS: «Agrocom comparte tu última ubicación con el cliente solo mientras tienes un servicio en ejecución» | Mantener el texto y agregar: «También la usamos, solo si pulsas el botón de auxilio o reportas una incidencia, para avisar a soporte dónde estás». Añadir antes del permiso una pantalla dentro de la app que diga qué se comparte, con quién, cuándo y que puede desactivarse | Android declara hoy `ACCESS_COARSE_LOCATION` y `ACCESS_FINE_LOCATION` en el manifiesto común: **retirarlos del flavor cliente** (su SOS usa la ubicación del terreno del servicio y no pide ubicación del dispositivo; ADR 0047, punto 14). No hay ubicación en segundo plano ni servicio en primer plano; mantener así evita el formulario de ubicación en segundo plano de Google y su video. Apple 5.1.2(i): la app del piloto debe funcionar si se rechaza; hoy puede desactivarla |
| Notificaciones | Avisos de ofertas, adjudicación, mensajes y nuevas solicitudes | Android: `POST_NOTIFICATIONS`; iOS pide en ejecución | Pedir en contexto, con una pantalla previa: «Te avisamos cuando haya ofertas, mensajes o una solicitud nueva» | Apple 5.1.2(i): no exigirlas para usar la app. Hoy el aviso se ve en la campana mientras no haya push |

### 1.8 Clasificación por edad y público objetivo

| Tienda | Qué piden | Aplicación (decidido el 27/11/2026) |
|---|---|---|
| Google | Declarar el público objetivo; si incluye niños rige la política de Familias. Cuestionario IARC en la sección de contenido; la inexactitud puede llevar a retirar la app ([Content ratings](https://support.google.com/googleplay/android-developer/answer/9859655), [App content](https://support.google.com/googleplay/android-developer/answer/9859455), 2026-11-26) | Público objetivo: **adultos, 18 años o más**. La **edad mínima de 18 años** va en los términos y el registro de las dos apps pide declararla («Soy mayor de 18 años», junto a la aceptación de términos); el piloto además da su fecha de nacimiento. Respuestas del cuestionario: sin contenido sexual, violento ni de juego; sí hay mensajes entre usuarios, contenido de usuarios (con reporte y bloqueo, 1.9) y ubicación compartida |
| Apple | Cuestionario de edad con 4+, 9+, 13+, 16+ y 18+; pregunta por contenido de usuarios, mensajes y chat, ubicación compartida y control de edad ([Age ratings](https://developer.apple.com/help/app-store-connect/reference/app-information/age-ratings-values-and-definitions), 2026-11-26) | Se responde con los mismos datos. Mensajes y contenido de usuarios no suben por sí solos la calificación; lo que la sube es una red social con difusión a muchos. Agrocom es 1:1 entre partes adjudicadas. **[NO VERIFICADO el resultado exacto]**: se conoce al completar el cuestionario, y si Apple asigna 18+ se acepta |
| Apple, Kids | No aplica: la app no está dirigida a menores | Evitar términos y capturas que sugieran público infantil |
| Brasil | Google usa ClassInd (L, 10, 12, 14, 16, 18) por la IARC | Se obtiene sola del cuestionario |

### 1.9 Contenido de usuarios: el chat, las fotos y las calificaciones

| Requisito | Fuente | Estado hoy | Aplicación |
|---|---|---|---|
| Google: aceptar los términos antes de crear contenido; los términos definen lo objetable; moderación «robusta, efectiva y continua»; **bloqueo en la app** para funciones 1:1; reporte de contenido y usuarios cuando la app es solo de usuarios registrados | [UGC](https://support.google.com/googleplay/android-developer/answer/9876937) (2026-11-26) | Términos con la aceptación del ADR 0046 §31; el borrador prohíbe insultos, amenazas y datos de terceros. **Decidido:** reportar y bloquear en el chat (T7) | ADR 0047, puntos 15 a 19 |
| Apple 1.2: filtro de material objetable, mecanismo de **reporte con respuesta a tiempo**, **bloqueo** de usuarios abusivos y **contacto publicado** | [Guidelines 1.2](https://developer.apple.com/app-store/review/guidelines/) (2026-11-26) | Hoy solo hay `POST /api/calificaciones/{id}/reportar` para calificaciones | Igual |

**Decisión aplicada** (política en `seguimiento_y_privacidad.md`, `reclamos.md` y `negociacion.md`):

- **Reportar.** En cada mensaje, en la conversación y en cada foto de avance, con un motivo (acoso o insultos, amenazas, contenido sexual o violento, datos de terceros, estafa o pago por fuera, suplantación, otro) y un detalle opcional. El reporte queda como un **reclamo de tipo conducta** en el panel. Al reportar, la persona acepta que soporte lea **solo lo reportado**: el mensaje y los 10 anteriores y posteriores, o los últimos 50 si reporta la conversación; esa lectura se cierra con el reporte y deja constancia de cada acceso. Así se concilia con la política «soporte lee el chat solo con un reclamo»: el reporte **es** el reclamo y abre un acceso acotado, no el chat completo. Un reclamo de servicio abierto sigue abriendo el chat de esa solicitud. Respuesta en 24 horas hábiles; resultados: sin lugar, advertencia, contenido ocultado o cuenta suspendida, con motivo, autor y apelación; sin sanciones automáticas.
- **Bloquear.** Cierra el chat de esa solicitud para las dos partes y la negociación en curso (libera la casilla del cupo del cliente por el camino normal), y deja de mostrar a cada uno las solicitudes del otro en el mapa, en los avisos y en las ofertas. **El servicio ya adjudicado sigue**: la adjudicación es un acuerdo y el reclamo es la vía formal. No se avisa al bloqueado. Se deshace desde «Cuentas bloqueadas».
- **Filtro básico** de palabras y enlaces que un administrador configura, una foto indebida la oculta un administrador, y el **contacto de soporte** está visible en las apps y en las fichas de las tiendas.
- Las calificaciones ya se pueden reportar y un administrador las modera (`calificacion.md`).

### 1.10 Cuentas de prueba para los revisores

Las dos tiendas necesitan entrar a todas las funciones. Agrocom exige aprobación manual de un administrador, así que el revisor, sin cuenta aprobada, vería una app casi vacía.

| Requisito | Fuente |
|---|---|
| Google: credenciales disponibles siempre, reutilizables, sin vencer, en inglés, que funcionen desde cualquier ubicación; si hay verificación en dos pasos o código, dar credenciales que no la pidan; hasta cinco juegos de instrucciones en «Acceso a la app». La página no cubre aprobación manual | [Sign-in details](https://support.google.com/googleplay/android-developer/answer/15748846) (2026-11-26) |
| Apple 2.1(a): enviar versión final con **cuenta de demostración activa** y los servicios del backend encendidos; solo con permiso previo se acepta un modo demo | [Guidelines 2.1](https://developer.apple.com/app-store/review/guidelines/) (2026-11-26) |

Cómo se da el acceso:

1. **Una cuenta de cliente y una de proveedor (piloto) de revisión por tienda**, creadas por el administrador desde el panel (ADR 0044) y **aprobadas**; quedan con la insignia «Verificada por la oficina» y el motivo «cuenta de revisión de tienda» (ADR 0046 §19). No usan documentos ni selfies de personas reales: el nombre es claro (por ejemplo «Revisor Tiendas») y el documento, un valor reservado de pruebas.
2. **Datos para recorrer todo**: un terreno con lotes, una solicitud abierta, una negociación con ofertas, un servicio adjudicado y uno en ejecución con fotos, chat y una ubicación, y una calificación pendiente. El sembrador de demostración ya existe y se corre contra producción **solo para estas cuentas** (aisladas con una marca para que no reciban avisos reales, no cuenten en métricas, no entren a los avisos por oleadas y no se purguen).
3. **Sin obstáculos**: el login no pide código (solo la recuperación lo usa, por correo); el límite de 6 intentos por minuto por usuario e IP no estorba a un revisor; la contraseña no vence; el servidor está encendido durante la revisión y con HTTPS.
4. **Notas para el revisor, en inglés**: qué es la app, que no hay cobro dentro de ella, por qué se pide selfie y documento (confianza entre dueños de terreno, pilotos y drones, ver 5.1.1(ix)), que el registro de una cuenta nueva queda «pendiente» hasta que un administrador la apruebe y por eso se entregan cuentas ya aprobadas, dónde están la eliminación de cuenta y el reporte de contenido, y el contacto directo de soporte.
5. **Eliminar cuenta**: Apple prueba la eliminación dentro de la app. Se entrega, además, el camino para crear una cuenta propia y eliminarla (las nuevas, `pendiente`, se eliminan sin condiciones), para no destruir la cuenta de revisión.
6. La cámara y la ubicación deben poder probarse en el dispositivo del revisor: la selfie se puede enviar y quedará «en revisión» sin que cambie la cuenta de revisión ya aprobada.

### 1.11 Otros puntos que las tiendas revisan

| Tema | Resultado |
|---|---|
| **Sign in with Apple** | **No aplica** (anotado el 27/11/2026, ADR 0047, punto 22). Apple 4.8 solo exige otra opción de inicio de sesión cuando la app usa un inicio de sesión de terceros (Google, Facebook, etc.); Agrocom usa solo su propio sistema de cuentas, que está en la lista de excepciones ([4.8](https://developer.apple.com/app-store/review/guidelines/), 2026-11-26). Si algún día se agrega «Entrar con Google», sí aplica |
| **Privacy manifest de iOS** | Apple exige un `PrivacyInfo.xcprivacy` con las APIs de «motivo requerido» de la app y de los SDK (desde el 1 de mayo de 2024). La página oficial no cargó en la consulta, así que esto sale de conocimiento general **[NO VERIFICADO]**. Revisar `shared_preferences`, `flutter_secure_storage`, `drift`, `path_provider` y `package_info_plus` (T10) |
| **Seguridad del transporte** | Google exige manejar los datos personales de forma segura (HTTPS). Apple bloquea HTTP por defecto. Requiere T4 |
| **Dato sensible en el dispositivo** | Cumple: selfie, documento y códigos no se guardan en `drift`, preferencias ni logs (ADR 0024 §7); el token vive en Keychain/Keystore |
| **Publicidad y seguimiento** | No hay. Declarar «sin anuncios» en Google y sin rastreo en Apple |
| **Cuenta y datos sin compra** | Sin cobros en la app: no hay compras dentro de la app |
| **Datos personales pedidos** | Apple 5.1.1(v): solo se piden datos personales cuando son esenciales para la función principal o la ley. La verificación se justifica por el núcleo del marketplace y se explica en las notas de revisión |

### 1.12 Mapa: las teselas públicas de OpenStreetMap son un tercero

Las apps dibujan el mapa con `flutter_map` y teselas de `tile.openstreetmap.org`. Su política de uso es «best effort», puede bloquear sin aviso, desaconseja depender de ellas en un producto comercial y exige un `User-Agent` identificable y la atribución; además ese servicio recibe la IP y la zona que mira cada persona. En una app publicada eso es un tercero que se declara y un riesgo de continuidad. **Es una decisión del dueño**: aquí van las opciones, sin elegir por él. Los precios son aproximados y de memoria; **[NO VERIFICADO]**: hay que confirmarlos en el sitio de cada proveedor antes de decidir.

| Opción | Qué es | Costo aproximado | A favor | En contra |
|---|---|---|---|---|
| **A. Proveedor gestionado con plan comercial** (por ejemplo MapTiler, Stadia Maps, Thunderforest o Geoapify) | Teselas por una clave de API; estilos y, según el proveedor, satélite | Planes de entrada de unos US$20 a 30 al mes; los planes gratuitos suelen ser solo para uso no comercial o desarrollo | No hay nada que operar; términos y acuerdo de nivel de servicio claros; permite satélite, útil para ver lotes | Un costo mensual; la clave viaja en la app (se restringe por identificador de la app) y se declara al proveedor como tercero |
| **B. Mapbox** | Teselas raster (y vectoriales) por token, con capa gratuita mensual y pago por uso | Cero en el volumen de un lanzamiento; después del cupo, del orden de US$0,5 por mil teselas | Escala sin trabajo; satélite y estilos propios | El costo crece con el uso y es difícil de predecir; sus términos exigen su atribución y limitan guardar teselas sin conexión; tercero a declarar |
| **C. Teselas propias** | Extracto de OpenStreetMap de Bolivia (Geofabrik) servido como PMTiles o MBTiles con Nginx en la infraestructura Proxmox del dueño | Sin licencia (datos ODbL con atribución); costo en horas de montaje y actualización, y en ancho de banda y CPU del servidor | Control total y privacidad: la IP no sale a un tercero y no hay clave; costo fijo | Hay que montar, actualizar y vigilar el servicio; sin satélite (se necesitaría un proveedor aparte); si se usan teselas vectoriales, una dependencia nueva en la app con su motivo en el PR |

Sea cual sea la opción: la **atribución** «© contribuyentes de OpenStreetMap» (y la del proveedor si lo pide) visible en el mapa; el **`User-Agent`** fijo; la clave de API, si hay, entra por `--dart-define-from-file` y nunca al repo; y se **declara en la política de privacidad** quién recibe la IP y la zona visible, y en Data safety y App Privacy si el proveedor las recoge para sí. Mientras no se decida, la beta del equipo sigue con las teselas actuales y no se publica en las tiendas con ellas.

## 2. Panorama de privacidad de datos personales en Latinoamérica

**Resumen.** Bolivia no tiene una ley general de protección de datos personales ni una autoridad de control; su protección es constitucional (acción de protección de privacidad) más normas sectoriales. La mayoría de sus vecinos sí la tiene, con principios casi iguales. Las leyes de otros países pueden alcanzar a Agrocom si algún día atiende a personas de allí. Mientras tanto, las tiendas fijan el piso práctico, porque sus reglas rigen en todos los países.

### 2.1 Bolivia

| Pieza | Qué dice | Fuente y estado |
|---|---|---|
| Constitución, art. 21 | Reconoce el derecho a la privacidad | [XMS Latam](https://xmslatam.com/bo/proteccion-datos-personales-bolivia/) (2026-11-26); el texto constitucional no lo leí directamente **[NO VERIFICADO]** |
| Constitución, arts. 130 y 131 | **Acción de protección de privacidad** (el equivalente boliviano del hábeas data): saber qué datos hay, y oponerse, eliminar o rectificar en bases públicas o privadas; se tramita como el amparo y termina en el Tribunal Constitucional | Idem |
| Ley 164 (2011) | Confidencialidad de las comunicaciones y de los datos de usuarios de telecomunicaciones | Idem |
| Código Penal, arts. 363 bis y 363 ter | Manipulación informática y acceso indebido a datos | Idem |
| Ley general de datos | **No existe.** Es un recurso judicial individual, sin autoridad que supervise ni sanciones de una ley general | [Opinión](https://www.opinion.com.bo/tendencias/quien-queda-tus-datos-bolivia-todavia-tiene-ley-que-obligue-responder/20260916111314996213.html) (16 sep 2026) |
| Proyecto PL-538/2025-2026 | «Protección de Datos Personales», **en tratamiento** en la Cámara de Diputados | [CiberLATAM](https://ciberlatam.com/noticias/bolivia-mantiene-tramite-pl-538) (la fuente cita noticias del 18 y 20 de noviembre de 2026; no vi su contenido ni fecha de votación) |
| Anteproyecto de AGETIC | Alcance fuera del país para quienes ofrecen servicios a personas en Bolivia, deber de demostrar cumplimiento, bases de tratamiento más allá del consentimiento y una autoridad especializada. No es ley | XMS Latam |

**Alcance de esta revisión:** no leí la Gaceta Oficial ni el sitio de la Asamblea, y las fuentes sobre el proyecto son medios y estudios jurídicos. **Un abogado debe confirmar que no hay otra norma vigente aplicable** (por ejemplo sobre firma y ciudadanía digital, defensa del consumidor o telecomunicaciones **[NO VERIFICADO]**) y el estado del PL-538.

### 2.2 Referencias comparativas

| País | Norma | Estado verificado (2026-11-26) | Autoridad | Lo relevante para Agrocom |
|---|---|---|---|---|
| Brasil | Lei 13.709/2018 (LGPD) | Vigente. Texto leído en [Planalto](https://www.planalto.gov.br/ccivil_03/_ato2015-2018/2018/lei/l13709.htm): art. 5 II incluye el dato biométrico vinculado a una persona como sensible; art. 7 bases (derechos en procesos VI, interés legítimo IX); **art. 11 II «g»** admite tratar datos sensibles para prevenir fraude y garantizar la seguridad del titular en la identificación y autenticación de registros electrónicos; art. 16 excepciones para conservar; art. 18 derechos; art. 33 transferencias internacionales (nueve casos); art. 41 encargado, con identidad y contacto públicos | ANPD | Es la ley con base más clara para conservar la selfie y el documento por prevención de fraude. Exige designar un encargado y publicar su contacto |
| Argentina | Ley 25.326 (2000) | Vigente. La reforma sigue sin aprobarse: se presentó otro proyecto el 16 de julio de 2026 y existe el 1751-D-2026 ([IAPP](https://iapp.org/news/a/se-impulsa-un-nuevo-proyecto-de-reforma-del-r-gimen-de-protecci-n-de-datos-en-argentina), [AAIP](https://www.argentina.gob.ar/aaip/datospersonales/proyecto-ley-datos-personales)) | AAIP | Consentimiento, finalidad, acceso, rectificación y supresión; restricción de transferir a países sin protección adecuada **[de memoria, NO VERIFICADO el artículo]** |
| Chile | Ley 21.719 (13 dic 2024) | Publicada; su vigencia era el **1 de diciembre de 2026**, y hay un **proyecto que la posterga** (un año según el Ejecutivo; el Senado aprobó el 28 oct 2026 el 15 de octubre de 2027) **sin confirmar si se publicó** ([BCN](https://www.bcn.cl/balance-legislativo/detalle/ficha_LEY_21719_2024-12-13), [Garrigues](https://www.garrigues.com/es_ES/noticia/chile-ejecutivo-refuerza-futura-agencia-proteccion-datos-concede-ano-adicional-adaptacion.md)) | Agencia de Protección de Datos Personales | Modelo moderno tipo europeo: bases de licitud, derechos ARCO y portabilidad, datos sensibles incluidos los biométricos **[verificar texto]** |
| Colombia | Ley 1581 de 2012, Decreto 1377 de 2013 | Vigente. El art. 5 define los datos sensibles e incluye los biométricos; el art. 6 los prohíbe salvo autorización explícita; la SIC resume que su recolección requiere autorización escrita y que no puede condicionarse ([Función Pública](https://www.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=49981), búsqueda del 2026-11-26) | SIC | Autorización previa e informada, aviso de que el dato sensible es opcional, registro de bases de datos y reglas para transferencias (art. 26) **[detalle del registro NO VERIFICADO]** |
| Perú | Ley 29733 (2011); Reglamento DS 016-2024-JUS | Reglamento publicado el 30 de noviembre de 2024 y vigente a los 120 días (30 o 31 de marzo de 2025, según la fuente) ([MINJUS](https://www.gob.pe/institucion/minjus/noticias/1067368-ejecutivo-aprueba-nuevo-reglamento-de-la-ley-de-proteccion-de-datos-personales)) | Autoridad Nacional de Protección de Datos (MINJUS) | Consentimiento, banco de datos personales, evaluación de impacto como mecanismo preventivo |
| México | Nueva Ley Federal de Protección de Datos Personales en Posesión de los Particulares | Publicada el 20 de marzo de 2025 y vigente desde el 21; el INAI desaparece y la competencia pasa a la Secretaría Anticorrupción y Buen Gobierno (texto en la [Cámara de Diputados](https://www.diputados.gob.mx/LeyesBiblio/ref/lfpdppp.htm); fechas según despachos jurídicos consultados por búsqueda) | Secretaría Anticorrupción y Buen Gobierno | Aviso de privacidad, derechos ARCO, datos sensibles |
| Ecuador, Uruguay, Paraguay, Panamá, Costa Rica y otros | Leyes propias | **[NO VERIFICADO]**: no los revisé | | Si Agrocom opera allí, revisar cada ley |

### 2.3 Principios comunes y qué implican para Agrocom

| Principio | Qué significa | Cómo lo cubre o lo cubriría Agrocom |
|---|---|---|
| Consentimiento informado y libre | Decir qué se recoge y para qué antes de recoger; para datos sensibles, consentimiento expreso y que no se condicione lo no esencial | Aceptación versionada de términos y privacidad (ADR 0046 §31); explicación previa a cámara y ubicación; la selfie y el documento son esenciales para operar, no para entrar |
| Finalidad | No usar los datos para algo distinto | Una sola finalidad declarada: autenticidad de las personas y de las ofertas |
| Minimización y calidad | Pedir solo lo necesario, exacto y actualizado | El cliente no ve datos del proveedor ni el proveedor los del cliente antes de adjudicar; ubicación solo en ejecución; sin analíticas |
| Seguridad | Medidas técnicas y organizativas; aviso de incidentes (LGPD, Ley 1581 y otras) | Disco privado, URL firmada, permiso sensible, bitácora de accesos, HTTPS; **falta** el procedimiento de aviso de incidentes |
| Derechos de acceso, rectificación, cancelación y oposición (ARCO), y portabilidad en algunas leyes | La persona consulta, corrige, elimina y se opone | Eliminación en la app y la web (decidido, T1 y T2); corrección en el perfil; **falta** el canal y plazo de acceso y una exportación (T15) |
| Conservación limitada | Guardar solo el tiempo necesario | 90 días o 12 meses de la selfie y el documento según los antecedentes (retención B); lo demás, en 1.3 y en `eliminacion_de_cuenta.md` (T13) |
| Transferencias internacionales | Enviar datos al extranjero con garantías | Hoy hospedaje propio; si se usa nube, push (Google o Apple) o correo de terceros, hay que revisarlo (sección 3) |
| Responsable y contacto | Identificar a quien trata los datos y cómo contactarlo (LGPD pide un encargado) | Falta el dato de Agrocom SRL y el contacto en la política |
| Datos de menores | Protección reforzada | App para mayores de edad, edad mínima en los términos |

**Qué implica para Agrocom.** No hay una obligación local equivalente a la LGPD hoy, pero (1) las tiendas imponen un piso con eliminación, transparencia y consentimiento en todos los países; (2) construir sobre los principios comunes deja a Agrocom listo si Bolivia aprueba su ley (el anteproyecto de AGETIC los sigue) y si entra a otro país; (3) un registro con documento y rostro es justo el tipo de dato que esas leyes tratan con más cuidado, así que la minimización y el aviso valen más que en un marketplace común.

## 3. Puntos que debe confirmar un abogado local

1. **La retención B:** si en Bolivia hay una norma que obligue o permita conservar la selfie y el documento 90 días o 12 meses según los antecedentes, y con qué base (interés legítimo, defensa de reclamos, deber legal); y si los plazos de 90 días y 12 meses son prudentes.
2. **La naturaleza de la selfie**: si, sin comparación automática, se considera un dato biométrico o sensible, y qué consentimiento adicional se necesita (LGPD, Ley 1581 y otras lo tratan así cuando hay procesamiento biométrico).
3. **Transferencia a proveedores fuera del país**: correo, push (Google o Apple), mapas, copias de seguridad o una futura nube. Qué cláusulas y qué aviso corresponden, y si alguna ley extranjera aplica si Agrocom sirve a personas de otro país.
4. **Datos de terceros que el cliente carga** (contactos de referencia de empresa, integrantes del equipo): base legal para tratarlos, porque esas personas no aceptaron nada.
5. **El plazo y el contenido de los términos y de la privacidad**: edad mínima de 18 años, ley aplicable y jurisdicción, datos y representación de Agrocom SRL como responsable, aviso de incidentes y canal de derechos.
6. **El alcance de lo que se conserva del registro del servicio** (mensajes, fotos, calificaciones, bitácora) y su plazo de 12 meses, y que el puntaje y las etiquetas de una calificación se conserven sin autor.
7. **Ubicación en SOS e incidencias:** la conservación de 90 días tras cerrar la incidencia y la declaración como ubicación precisa.
8. **Si el PL-538 u otra norma estará vigente cuando se publique la app**, y qué cambiaría (inscripción, responsable de protección, autoridad).
9. **Cualquier obligación sectorial**: uso de drones agrícolas y datos de terrenos; no es el tema de este documento y no lo revisé.
10. **El registro mínimo antifraude:** si una huella del documento con clave del servidor se considera un dato personal, con qué base se conserva 12 o 36 meses y cómo se informa.
11. **El consentimiento al reportar:** que bastan el aviso y la casilla de la pantalla de reporte para que soporte lea lo reportado, y que el reportado no deba ser informado del contenido del reporte.

## 4. Tareas derivadas

Prioridad: **P0** bloquea el envío a las tiendas; **P1** antes de abrir al público; **P2** poco después. Los números de ADR son el 0047 en api (decisión) y el 0025 en field (las apps). **Estado:** «decidido» quiere decir que el dueño lo resolvió el 27/11/2026 y falta implementarlo; el plan por fases, archivos y pruebas afectadas está en `plan_tiendas_v1.md`.

| ID | Tarea | Repo | Prio | Estado | Documento o ADR que se actualiza |
|---|---|---|---|---|---|
| T1 | **Eliminar mi cuenta**: `POST /api/cuenta/eliminacion` con código al correo y contraseña, estados (`solicitada`, `pospuesta`, `cancelada`, `ejecutada`), gracia de 7 días, anonimización en el lugar, cancelación de ofertas y solicitudes, bloqueo con servicio o reclamo, correos y parámetros. Pantalla en las dos apps (Perfil, Privacidad y datos) con los cinco estados y goldens | api + field | P0 | decidido (ADR 0047 y 0025) | `CLAUDE.md` invariantes 8, 9, 13 y 15; ADR 0046 §33; `eliminacion_de_cuenta.md`; `openapi.yaml`; `DESIGN.md` |
| T2 | **Página web `/eliminar-cuenta`** (sección 1.6) y cola de solicitudes en el panel (estado, plazo, autor, caso sin acceso al correo) | api | P0 | decidido | ADR 0047; `legal.md`; glosario |
| T3 | **Completar los textos legales**: quitar los `[POR DEFINIR]`, incluir la eliminación, la retención B, reportar y bloquear, la ubicación de emergencia, la edad mínima de 18 años y el tercero del mapa, y subir la versión | api | P0 | hecho en la versión 2026-2; faltan los datos de contacto y de la empresa (configuración) | `legal.md`, `lang/es/legal.php`, `config/legal.php`; revisión de abogado (sección 3) |
| T4 | **Dominio y HTTPS en producción**, antes de la URL de privacidad y de la eliminación (sección 6) | api (infraestructura del dueño) | P0 | por hacer por el dueño | ADR 0017; `docs/gestion/entornos.md` |
| T5 | **Textos de propósito y permisos por app**: Info.plist y manifiestos por flavor (retirar la ubicación del cliente), selfie en el texto de cámara, pantallas de explicación previa | field | P0 | decidido | ADR 0024 y 0025; `DESIGN.md`; `docs/diseno/` |
| T6 | **Enlaces a términos y privacidad** en el registro y dentro de la app (ajustes de cuenta), contacto de soporte visible y declaración «Soy mayor de 18 años» | field | P0 | decidido | ADR 0024 §14 y 0025 |
| T7 | **Reportar y bloquear** en el chat: reclamo de conducta, lectura acotada, bloqueos y su efecto en cupo, mapa y avisos, filtro básico, panel y apps | api + field | P0 | decidido (ADR 0047 y 0025) | `seguimiento_y_privacidad.md`; `reclamos.md`; `negociacion.md`; `openapi.yaml`; ADR 0017 y 0018 de field |
| T8 | **Cuentas de revisión de las tiendas** (sección 1.10) con datos de demostración aislados y las **notas para el revisor en inglés** | api | P0 | por hacer | Nuevo `docs/gestion/revision-tiendas.md`; `demo-local.md`; ADR 0044 |
| T9 | **Declaraciones**: Play Console (Data safety de las dos apps, URL de eliminación, contenido, público objetivo, acceso a la app, privacidad) y App Store Connect (App Privacy de las dos apps, edad, URL de privacidad y de soporte, notas de revisión), con los valores de 1.3 y 1.4 | field | P0 | borrador alineado (1.4) | Nuevo `docs/gestion/publicacion-tiendas.md` en field |
| T10 | **`PrivacyInfo.xcprivacy`** de las apps y verificación de los manifiestos de los plugins | field | P1 | por hacer | `docs/gestion/ci-por-impacto.md` si cambia la compuerta; ADR 0019 de field |
| T11 | **Mapa**: elegir entre las opciones de 1.12 e implementarla | field + api | P1 | **decisión del dueño** | el ADR que fija las teselas abiertas; política de privacidad |
| T12 | **Push**: decidir el servicio (FCM/APNs), enviar sin datos personales en el mensaje, declarar el ID de dispositivo | api + field | P1 | por decidir | ADR 0041 y 0045; ADR 0020 de field; Data safety |
| T13 | **Retención**: purga con la opción B (`PurgarVerificaciones`), `PurgarRetencionDeServicios`, parámetros `retencion.*` y respaldos de 30 días con `bin/reaplicar-eliminaciones` | api | P1 | decidido | ADR 0046 §33; ADR 0047; `docs/seguridad/despliegue.md` |
| T14 | **Ubicación en SOS e incidencias**: 90 días tras el cierre (`PurgarUbicacionDeIncidencias`), SOS del cliente con la ubicación del terreno, y la formulación exacta en la política y en `CLAUDE.md` de field | api + field | P1 | decidido | ADR 0040 y 0047; `seguridad.md`; `seguimiento_y_privacidad.md` |
| T15 | **Derechos de acceso y rectificación**: canal, plazo de respuesta y exportación de los datos propios; cola en el panel | api | P2 | por hacer | ADR 0047; `legal.md` |
| T16 | **Registro de aceptación** (`sec_aceptaciones`, `GET /api/legal/vigentes`, `legal_pendiente`): confirmar que está implementado antes del envío | api + field | P1 | por confirmar | ADR 0046 §31; ADR 0024 §14 de field |
| T17 | **Edad mínima y cuestionarios de edad** con las respuestas de 1.8 | api + field | P1 | decidido (18 años) | Términos; ADR 0046 |
| T18 | **Terceros y encargados del tratamiento**: lista pública de proveedores, contratos con ellos y revisión de transferencias | api | P1 | por hacer | `legal.md`; `docs/seguridad/` |
| T19 | **Aviso de incidentes de seguridad**: procedimiento y texto | api | P2 | por hacer | `docs/seguridad/`; `legal.md` |
| T20 | **Abogado local**: revisar las preguntas de la sección 3 y los textos de T3 | dueño | P0 | por hacer | Este documento |
| T21 | **Cuentas de desarrollador de organización** (Google con D-U-N-S, Apple como entidad) | dueño | P0 | por hacer | `docs/gestion/publicacion-tiendas.md` |

## 5. Lo que no pude verificar en esta revisión

- Que una cuenta de **organización** de Google Play esté exenta de la prueba cerrada de 12 testers (la página solo habla de cuentas personales).
- Que Apple pida D-U-N-S para inscribir una organización y el procedimiento de inscripción.
- La **clasificación de la selfie y del documento** en App Privacy (¿fotos, información sensible u otros datos?) y en Data safety («otra información» para el documento).
- La guía de interfaz de Apple sobre permisos, y el manifiesto de privacidad de iOS: las páginas oficiales no cargaron y lo anotado sale de conocimiento general.
- El **resultado exacto** del cuestionario de edad de Apple (se conoce al responderlo).
- Si Google cuenta como «compartir» lo que un usuario muestra a otro en un marketplace.
- El texto de la Constitución y de las normas bolivianas citadas (leí resúmenes), la existencia de otras normas bolivianas aplicables y el contenido del PL-538.
- El estado de **Chile** (si la postergación a 2027 ya es ley), el art. 12 de la Ley argentina 25.326 y el detalle del registro de bases de datos en Colombia.
- Ecuador, Uruguay, Paraguay y otros países.
- La versión mínima de Android (`targetSdk`) que Google exige hoy para publicar.

## 6. Antes de publicar en las tiendas

Lista de verificación para el dueño. Lo marcado como «dueño» es infraestructura y trámites que no se resuelven con código; lo demás lo implementa el plan de tareas (sección 4). **Nada de esto se da por hecho:** cada casilla se tilda cuando se comprueba.

**Dominio, HTTPS y páginas públicas (dueño)**

- [ ] **Dominio propio** registrado a nombre de Agrocom SRL, con DNS administrable. Una dirección para la web y las páginas legales y otra para la API (por ejemplo `agrocom.<dominio>` y `api.<dominio>`; el nombre lo elige el dueño).
- [ ] **Certificado HTTPS** válido y renovación automática (Nginx Proxy Manager del Proxmox es una vía), redirección de HTTP a HTTPS y HSTS. La API y las apps usan solo HTTPS (`API_BASE_URL` de las apps en `https://`; sin excepciones de certificado en release).
- [ ] **`/terminos`** abre sin sesión, activa, sin PDF, con la versión vigente.
- [ ] **`/privacidad`** abre sin sesión, activa, sin PDF, con contacto del desarrollador, qué datos se recogen y con quién se comparten, la retención y la eliminación (esta política).
- [ ] **`/eliminar-cuenta`** abre sin sesión y funciona de punta a punta (código al correo, resumen, solicitud); es la URL de eliminación de Play y la «Privacy Choices URL» de Apple.
- [ ] Las tres páginas se abren desde fuera de la red del equipo y desde cualquier país.
- [ ] **Correo de contacto y soporte** con buzón real (por ejemplo `soporte@<dominio>`), también publicado en las apps, en las dos fichas y en el campo «Support URL» de Apple; un teléfono de soporte si se ofrece.
- [ ] **Correo del sistema** con remitente propio del dominio, SPF, DKIM y DMARC configurados para que los códigos de recuperación y de eliminación lleguen, y un servidor SMTP real en `plt_configuraciones` (no Mailpit).
- [ ] Certificado, dominio y servidor encendidos mientras dure la revisión de las tiendas (Apple prueba con el backend activo).

**Dependencias de código y textos**

- [ ] **Datos de contacto y de la empresa** cargados en el entorno de producción (`LEGAL_CONTACTO_CORREO`, `LEGAL_CONTACTO_TELEFONO`, `LEGAL_RAZON_SOCIAL`, `LEGAL_NIT`, `LEGAL_REPRESENTANTE`, `LEGAL_JURISDICCION` y `LEGAL_RED_*`); las páginas legales los muestran solo si existen.
- [ ] **Textos legales revisados por un abogado** (T20): un ajuste entra como versión nueva.
- [ ] Los ajustes P0 de la sección 7 implementados, porque los términos y la política los describen en presente.
- [ ] `GET /api/legal/vigentes` devuelve las direcciones https y las versiones (`legal.terminos_url`, `legal.privacidad_url`).
- [ ] T1, T2, T7 y T14 implementados y probados en un entorno con HTTPS.

**Cuentas, declaraciones y revisión**

- [ ] Cuenta de desarrollador de organización: Google (D-U-N-S) y Apple (entidad legal) (T21).
- [ ] Decisión del mapa tomada e implementada (1.12) y declarada en la política.
- [ ] Data safety (las dos apps) y App Privacy (las dos apps) cargados con la tabla de 1.4; edad y público objetivo (1.8).
- [ ] Cuentas de revisión creadas, aprobadas y con datos de demostración (1.10) y notas para el revisor en inglés, con la ruta de eliminar cuenta y de reportar.
- [ ] `PrivacyInfo.xcprivacy` y manifiestos de permisos por flavor revisados (T5, T10).

## 7. Términos → ajuste de código

Los términos y la política de privacidad (versión 2026-2) describen el sistema que las tiendas exigen. Esta tabla lista lo que el texto afirma y que el código o el proceso todavía no hacen (o no están verificados); cada fila es una tarea para otra rama y no se implementa con los textos. Estado al 2026-11-27, contra `develop`.

Cómo se alinean las responsabilidades con las tiendas: Agrocom es el nexo y el registro (no presta el servicio operativo, no garantiza resultados ni disponibilidad, no procesa pagos: Apple 3.1 y Google tratan los servicios del mundo real pagados fuera de la app como no sujetos a compra dentro de la app); cada parte responde por lo que controla (proveedor: licencias, seguros, producto, operación; cliente: veracidad del terreno y pago); el contenido de usuarios se rige por reglas, reporte con respuesta a tiempo, bloqueo y moderación (Apple 1.2 y la política de contenido generado por usuarios de Google); y la eliminación se ofrece dentro de la app y por un canal web (Apple 5.1.1(v) y la política de eliminación de Google). Fuentes y fechas de consulta: secciones 1.2 a 1.9 de este documento (2026-11-26).

| Lo que dice el texto | Ajuste de código o de proceso | Repo | Prio | Tarea |
|---|---|---|---|---|
| Se puede pedir la eliminación sin entrar a la app (dato de contacto) y en una página web | Página pública `GET /eliminar-cuenta` con código al correo, cola de solicitudes en el panel y caso de oficina; después, mencionar la URL en la sección «Cómo eliminar tu cuenta y tus datos» y subir la versión | api | P0 | T2 |
| «Eliminar mi cuenta» en Perfil, Privacidad y datos, con confirmación y los cinco estados | Pantalla en las dos apps sobre `/api/cuenta/eliminacion` (la API existe) | field | P0 | T1 |
| Reportar un mensaje, una conversación o una foto; bloquear a la otra parte; respuesta en 24 horas hábiles; filtro de palabras; soporte lee solo lo reportado | Endpoints y casos de uso de reporte (reclamo de conducta con lectura acotada y constancia de acceso), bloqueo (cierre del chat, mapa, avisos y ofertas) y filtro; pantallas en las apps y cola en el panel. Hoy solo existe `POST /api/calificaciones/{id}/reportar` | api + field | P0 | T7 |
| Se guarda la versión y la fecha de lo aceptado y se pide aceptar de nuevo si el cambio es de fondo | `sec_aceptaciones`, `GET /api/legal/vigentes` (versiones y URL, con `legal.terminos_url` y `legal.privacidad_url`) y `legal_pendiente` en la sesión; la versión 2026-2 obliga a las cuentas existentes a aceptar. No aparecen en las rutas de `develop` | api + field | P1 | T16 |
| «Soy mayor de 18 años» al registrarse | Casilla en el registro de las dos apps, guardada con la aceptación | api + field | P1 | T17 |
| Enlaces a términos y privacidad en el registro y en la app, y contacto de soporte visible | Enlaces en registro y ajustes; leer el contacto desde `config/legal.php` (agregarlo a `GET /api/legal/vigentes`) | api + field | P0 | T6 |
| La ubicación se pide en contexto, con explicación previa; el piloto la puede desactivar; el cliente no pide ubicación del dispositivo | Pantalla previa al permiso, retiro de `ACCESS_COARSE_LOCATION` y `ACCESS_FINE_LOCATION` del flavor cliente y textos de propósito por flavor | field | P0 | T5 |
| La ubicación de un SOS o una incidencia se elimina 90 días después de cerrar la incidencia | `PurgarUbicacionDeIncidencias` y SOS del cliente con la ubicación del terreno | api + field | P1 | T14 |
| Selfie y documento: 90 días sin antecedentes, 12 meses con ellos; registro antifraude de 12 o 36 meses; mensajes y fotos de un servicio, 12 meses tras el plazo de reclamo; aceptaciones, 36 meses | Tareas de purga (`PurgarVerificaciones`, `PurgarRetencionDeServicios`) y los parámetros `retencion.*`; no existen en `develop` | api | P1 | T13 |
| Los respaldos rotan en 30 días y una restauración reaplica las eliminaciones | Rotación de respaldos y `bin/reaplicar-eliminaciones` (descritos en `docs/seguridad/despliegue.md`); verificar que corren en producción | api (infraestructura) | P1 | T13 |
| Acceso a los datos y copia en un formato de uso común; respuesta en 30 días | Exportación de los datos propios, canal y cola en el panel con seguimiento del plazo | api | P2 | T15 |
| «Si un incidente de seguridad afecta tus datos, te avisaremos» | Procedimiento de aviso de incidentes y plantilla de mensaje | api (proceso) | P2 | T19 |
| Los registros técnicos del servidor y la bitácora se conservan un tiempo limitado | Definir y aplicar un plazo para los registros del servidor y para la bitácora, y luego citarlo en la política | api | P2 | T13 |
| Un proveedor de mapas recibe la IP y la zona que miras (hoy OpenStreetMap) | Elegir proveedor (1.12), con atribución y `User-Agent`; si cambia, actualizar el texto de «Con qué terceros compartimos datos» | field + api | P1 | T11 |
| WhatsApp Business y SMS para enviar códigos, «cuando se activan» | Adaptadores de `EnviadorDeCodigo` configurados en producción; mientras no existan, el canal responde `503 canal_no_disponible` | api | P1 | T12 |
| El almacenamiento privado de archivos en producción es Cloudflare R2 | Confirmar el proveedor real del entorno de producción y ajustar el texto si es otro | api (infraestructura) | P0 | T18 |
| Las apps y la API usan HTTPS | Dominio y certificado en producción, sin excepciones de certificado en release | api (infraestructura) | P0 | T4 |
| No hay notificaciones push todavía; las notificaciones del texto son las de la campana y el correo | Si se agrega push (FCM o APNs), declararlo en la política y en Data safety antes de lanzarlo | api + field | P1 | T12 |
| Cuentas de revisión de las tiendas y notas para el revisor | Cuentas aprobadas con datos de demostración y notas en inglés | api | P0 | T8 |

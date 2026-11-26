# Tiendas de aplicaciones y privacidad de datos personales

**Agrocom SRL · Borrador de trabajo, pendiente de revisión legal** · Consulta de fuentes: **2026-11-26** · Decisión de fondo: ADR 0046 (cuentas, verificación y retención), ADR 0040 (ubicación y contacto) · Política hermana de `seguimiento_y_privacidad.md`, `verificacion_proveedores.md`, `seguridad.md` y de `legal.md` (términos y privacidad públicos; ese documento vive en la rama `feature/paginas-legales` hasta que se integre en `develop`).

> **Esto no es asesoría legal.** Reúne lo que las tiendas piden hoy y lo que las leyes de la región suelen exigir, para que el dueño llegue a la reunión con un abogado boliviano con las preguntas hechas. Las citas de leyes salen de búsquedas y de textos oficiales leídos en la fecha indicada; donde no pude verificar algo, está marcado **[NO VERIFICADO]**. La fuente de verdad de este tema vive en `agrocom-api`; `agrocom-field` solo lo enlaza.

## 0. Lo que obliga a cambiar el diseño

El diseño actual (ADR 0046 y 0024 de field) cumple casi todo lo que piden las tiendas, con estas excepciones, que se resuelven con las tareas de la sección 4:

1. **Falta «Eliminar mi cuenta», dentro de las dos apps y en una página web.** Hoy la «baja» es un estado que pone un administrador y deja la cuenta congelada con soft delete. Google dice que congelar o desactivar «no califica como eliminación de cuenta» y Apple, que desactivar «es insuficiente». Hace falta una eliminación iniciada por la persona, que borre o anonimice sus datos de verdad (T1 y T2).
2. **El soft delete (invariante 8) y el retiro a esquemas `respaldo_*` (invariante 15) no sirven para los datos personales de una cuenta eliminada.** Una fila con nombre y teléfono marcada como borrada sigue siendo el dato. La eliminación debe anonimizar en el lugar y los respaldos deben tener una rotación declarada (T1, T13).
3. **Conservar la selfie y el documento 12 meses «por temas legales» es defendible pero frágil.** Google permite retener por seguridad, prevención de fraude o cumplimiento legal si se declara en la política de privacidad. Apple solo menciona lo que la ley obliga a conservar. Bolivia no tiene ley general de datos y no encontré una norma que obligue a guardar la imagen del documento. El motivo real es prevenir fraude y poder defenderse en un reclamo, y así debe decirse. Hay dos formas de reducir el riesgo (sección 1.5).
4. **El chat no tiene «reportar» ni «bloquear».** Google (1:1 con bloqueo obligatorio) y Apple 1.2 (filtro, reporte con respuesta, bloqueo y contacto publicado) lo exigen para contenido de usuarios. La política actual dice que soporte lee una conversación solo si hay un reclamo abierto, y eso choca con moderar un reporte (T7).
5. **La ubicación del SOS y de las incidencias no está dentro de la regla «la ubicación no se guarda».** `seg_incidencias.ubicacion` guarda una posición. Hay que declararla y fijar su retención (T14).
6. **La beta no tiene dominio ni HTTPS (ADR 0017).** Las dos tiendas piden una URL de privacidad pública y Apple bloquea HTTP por defecto. Producción necesita dominio y certificado antes de enviar nada (T4).
7. **Mapas con `tile.openstreetmap.org`.** El servicio comunitario es «best effort», puede bloquear sin aviso y su política desaconseja depender de él en un producto comercial. Además recibe la IP y la zona que mira cada usuario: es un tercero que hay que declarar (T11).
8. **El manifiesto de Android declara permisos de ubicación para las dos apps**, y solo el piloto usa `geolocator`. El cliente declararía un permiso que no usa (T5).
9. **Edad mínima, responsable de los datos, contacto, terceros y plazos siguen en `[POR DEFINIR]`** en el borrador de privacidad. Las tiendas exigen una política completa.

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

Es la base de la política, de Data safety y de App Privacy. «Comparte» incluye lo que ve la otra parte del servicio y los terceros (proveedores de infraestructura). Las retenciones salen de ADR 0046 §33, ADR 0040 y las políticas; **«[POR DEFINIR]»** marca lo que ningún documento fija hoy.

| Dato | Para qué | ¿Se comparte? | ¿Obligatorio? | Retención |
|---|---|---|---|---|
| Nombre y apellidos | Saber quién es cada persona y que cada oferta sea real | El cliente ve al proveedor en las ofertas (nombre comercial, logo, calificación). El proveedor ve el nombre del cliente solo tras adjudicar | Sí | Mientras exista la cuenta; se anonimiza a los 12 meses de la baja si no hay otra cuenta viva ni reclamo (ADR 0046 §33) |
| Teléfono | Referencia para entrar y contacto | Cliente y proveedor se ven el teléfono solo tras adjudicar. Nadie más | Sí | Igual; al anonimizar se libera |
| Correo de respaldo | Recuperar el acceso con un código; avisos | No | Sí | Igual |
| Documento (tipo, número) y fecha de nacimiento (proveedor) | Una persona, una cuenta por tipo; mayoría de edad de quien vuela | No. Las otras partes ven la insignia «Verificado» | Sí, para operar | Igual que el nombre |
| Imagen del documento | Que quien revisa compruebe que la persona existe | No. Solo quien tiene `seguridad.verificacion.ver` | Sí, para operar (no para entrar y mirar) | Cuenta + 12 meses tras la baja; rechazadas o reemplazadas, 90 días |
| Selfie (cámara, en el momento) | Comparar con el documento | No. Igual | Sí, para operar | Igual |
| Foto de perfil | Reconocer al proveedor en la oferta | Sí, a la otra parte | No | Con la cuenta |
| Logo, nombre comercial, razón social, NIT | Datos de empresa | Logo y nombre del proveedor, al cliente en las ofertas; los del cliente, al proveedor tras adjudicar | No | Con la cuenta |
| Documentos del proveedor (licencia, matrículas, seguros) y fotos del dron | Aprobar al proveedor | No. Solo quien revisa | Sí, para ofertar | Con la cuenta **[POR DEFINIR tras la baja]** |
| Terreno y lotes (contorno, ubicación, hectáreas, cultivo, accesos, colindantes) y fotos del terreno | Que el proveedor decida su precio | Proveedores con la solicitud visible, antes de adjudicar y **sin la identidad del cliente** | Sí, para publicar | Con la cuenta; el registro del servicio sigue con la contraparte |
| Ubicación del piloto (última posición) | Que el cliente vea que el servicio avanza | Cliente del servicio, solo durante `en_ejecucion` | **No**: el piloto puede desactivarla | Se elimina al finalizar o cancelar; nunca en logs, bitácora ni historial |
| Ubicación en SOS e incidencias | Avisar a soporte donde ocurre la emergencia | Soporte | No (la persona decide reportar) | **[POR DEFINIR]**: hoy se guarda en `seg_incidencias.ubicacion` |
| Fotos de avance | Probar el avance y cerrar el servicio | Cliente del servicio | Sí, para cerrar | Mientras sea posible un reclamo y luego **[POR DEFINIR]** |
| Mensajes del chat | Coordinar el servicio | La otra parte. Soporte solo con un reclamo abierto | No | **[POR DEFINIR]** |
| Ofertas, precio acordado, avance, cierre, calificaciones, reclamos | Ser el registro del resultado del servicio | La contraparte | Sí | Se conservan; tras la baja muestran «Cuenta eliminada» (ADR 0046 §33) |
| Token de sesión, `uuid_dispositivo`, token de push | Sesión, un dispositivo por token y avisos | Apple o Google si se usa su push **[ver T12]** | Sí (sesión) | Se revocan al cerrar sesión o dar de baja |
| Aceptación de términos y privacidad (versión, fecha, app, dispositivo) | Prueba de que la persona aceptó | No | Sí | **[POR DEFINIR]** (prueba de aceptación) |
| Bitácora de auditoría y accesos a verificaciones | Seguridad y responsabilidad | No | Sí | **[POR DEFINIR]** |
| IP y registros del servidor (límites de frecuencia, errores) | Seguridad | No | Sí | **[POR DEFINIR]** |
| IP y zona visible del mapa hacia `tile.openstreetmap.org` | Dibujar el mapa | **Sí, a la Fundación OpenStreetMap** | Sí, para ver mapas | Según la política de ese tercero |

Lo que Agrocom **no** recoge: pagos, contactos del teléfono, galería completa (solo las fotos que la persona elige), micrófono, ubicación en segundo plano, identificador de publicidad, analíticas ni reportes de fallos de terceros. Si algún día se agrega un SDK de analíticas o de fallos, hay que declararlo antes (Apple pide declarar también lo que recogen los SDK; Google igual).

**Uso único.** Los datos se usan para garantizar que cada persona es real y que cada oferta es genuina, y para operar el servicio. Apple 5.1.2(ii) prohíbe reutilizar un dato para otro fin sin nuevo consentimiento, y 5.1.2(vi) prohíbe usar datos de las APIs de cámara y fotos para publicidad o minería de datos. La política de privacidad lo dice expresamente: **la selfie y el documento no se usan para nada más que verificar y prevenir fraude, no se venden ni se comparten** (Google también prohíbe vender datos personales y sensibles).

**Biometría.** El diseño compara la selfie con el documento por una **persona** (quien revisa); el sistema no extrae plantillas faciales ni hace reconocimiento automático. Esa decisión es lo que hoy sostiene que la selfie es una foto y no un dato biométrico tratado. Si algún día se automatiza la comparación, cambia la clasificación en las tiendas y en varias leyes (LGPD, Ley 1581, Ley 21.719) y se exigen más garantías. Debe quedar escrito en la política y confirmarlo un abogado.

### 1.4 Declaraciones: Data safety (Google) y App Privacy (Apple)

Reglas generales leídas el 2026-11-26. **Google:** [Data safety](https://support.google.com/googleplay/android-developer/answer/10787469). Un formulario por paquete, así que hay **dos** (`...cliente` y `...piloto`); «recoger» es sacar datos del dispositivo, incluidos los que envían librerías; «compartir» es darlos a un tercero (los proveedores que tratan datos por cuenta propia, las transferencias legales y las acciones iniciadas por el usuario quedan fuera); hay que decir si todo viaja cifrado, si la persona puede pedir la eliminación, y la declaración debe seguir exacta siempre. **Apple:** [App Privacy Details](https://developer.apple.com/app-store/app-privacy-details/). Hay que declarar todo lo recogido aunque no sea para analíticas ni publicidad, vinculado o no a la persona, y si se usa para rastreo (no se usa). «Fraude» no es un propósito aparte: va dentro de «App Functionality». Se puede cambiar sin una versión nueva.

| Dato | Google Data safety | Apple App Privacy | Propósito | Vinculado al usuario | Opcional |
|---|---|---|---|---|---|
| Nombre, teléfono, correo, ID de usuario | Información personal: nombre, correo, ID de usuario, número de teléfono | Contact Info (nombre, correo, teléfono) e Identifiers (User ID) | Funcionamiento de la app, gestión de cuenta, prevención de fraude | Sí | No |
| Documento (número), fecha de nacimiento | Información personal: «otra información» | **[NO VERIFICADO]** No encontré una categoría de Apple para documentos de identidad; la salida prudente es «Other Data Types» | Funcionamiento, prevención de fraude | Sí | No |
| Selfie, imagen del documento, foto de perfil, fotos del terreno y de avance, fotos del dron | Fotos y videos | Photos or Videos | Funcionamiento, prevención de fraude | Sí | No (la de perfil sí) |
| Ubicación del piloto | Ubicación precisa | Precise Location | Funcionamiento de la app | Sí | **Sí** (puede desactivarla) |
| Contorno y ubicación del terreno | Ubicación precisa (lo ingresa la persona) | Precise Location | Funcionamiento de la app | Sí | No |
| Mensajes | Mensajes: «otros mensajes en la app» | Other User Content | Funcionamiento de la app | Sí | No |
| Ofertas, calificaciones, avance | Actividad de la app: «otro contenido generado por el usuario» | Other User Content | Funcionamiento de la app | Sí | No |
| Token de dispositivo y de push | Dispositivo u otros ID | Device ID | Funcionamiento de la app | Sí | No |
| Cifrado en tránsito | Sí (HTTPS; **requiere T4**) | n/a | | | |
| ¿Pide eliminar los datos? | Sí, con la URL de eliminación | n/a (la eliminación va dentro de la app) | | | |
| Rastreo / publicidad | No hay | No hay (no se usa App Tracking Transparency) | | | |

La selfie y el documento que **solo ve quien revisa** siguen siendo datos «recogidos»: Apple define recoger como enviarlos fuera del dispositivo para conservarlos más de lo necesario para atender la petición, y Google como transmitirlos fuera del dispositivo.

**Lo que no pude confirmar de la clasificación:** si Apple trata la selfie de verificación como «Photos or Videos» o como «Sensitive Info» (que incluye datos biométricos). Su página no lo dice. Como no hay procesamiento biométrico, se declara como fotos; conviene confirmarlo en la reunión de App Store Connect o con soporte de Apple antes del primer envío.

**Compartir.** Lo que una parte ve de la otra es parte del funcionamiento y lo inicia la persona al ofertar o adjudicar; Google no lo cuenta como compartir con terceros cuando es una acción iniciada o esperada por el usuario, pero la guía no habla del caso de dos usuarios de un marketplace **[NO VERIFICADO]**. Se explica igual en la política. Los terceros reales hoy son: alojamiento (servidor propio en Proxmox, hasta nuevo aviso), el servidor de correo configurado en `plt_configuraciones`, el servicio de push que se elija (T12) y OpenStreetMap (T11). Los proveedores que tratan datos por cuenta de Agrocom no cuentan como «compartir» en Google, pero sí deben estar nombrados en la política.

### 1.5 Eliminación de cuenta y la retención de 12 meses

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

**Cómo se concilia con la retención de la selfie y el documento.** Hay que separar tres cosas que hoy el ADR 0046 mezcla en «baja»:

1. **Baja administrativa** (suspender o dar de baja una cuenta): decisión de un administrador, la cuenta queda cerrada. Sigue como está.
2. **Eliminación pedida por la persona**: la persona ya no quiere la cuenta. Es lo que las tiendas exigen y no puede ser un congelamiento.
3. **Conservación mínima para prevenir fraude y defender reclamos**: lo único que sobrevive a la eliminación, declarado y limitado en el tiempo.

**Qué se elimina cuando la persona lo pide** (propuesta, dentro de los 30 días de la solicitud, plazo en un parámetro): perfil, foto de perfil, logo, correo, teléfono, contactos de referencia, documentos del proveedor, fotos del dron, equipos e integrantes, terrenos sin servicio vigente, borradores, tokens de sesión y de push; las solicitudes abiertas se cancelan y las ofertas vigentes se retiran. Nombre, documento y fecha de nacimiento se anonimizan **en el lugar** (no basta el soft delete). Si hay un servicio adjudicado o en ejecución, o un reclamo abierto, la eliminación se programa para cuando terminen y la app lo explica con el motivo y el camino (las dos tiendas aceptan explicar pasos previos, siempre que no sea una traba innecesaria).

**Qué se conserva y por qué, y debe estar escrito en la política y en la pantalla de eliminación:**

| Lo conservado | Motivo | Por cuánto | Quién lo ve |
|---|---|---|---|
| Selfie e imagen del documento, con nombre, documento y fecha de nacimiento | Prevenir fraude y suplantación (volver a registrarse con otra identidad) y defender a Agrocom y a la contraparte en un reclamo | 12 meses tras la baja o eliminación (ADR 0046 §33) | Solo quien tenga el permiso sensible; cada acceso queda registrado |
| Ofertas, precio acordado, avance, cierre, fotos del servicio, mensajes y calificaciones del servicio | La plataforma es el registro del resultado; la contraparte los necesita para reclamos y calificaciones | Mientras sea posible un reclamo y luego **[POR DEFINIR]** | La contraparte, con la persona mostrada como «Cuenta eliminada» |
| Aceptaciones, bitácora de auditoría | Probar qué se aceptó y qué se hizo | **[POR DEFINIR]** | Administradores |

**Dónde está el riesgo y qué cambiaría.** Apple escribe «datos que el desarrollador **no está legalmente obligado** a conservar». Yo no encontré ninguna norma boliviana que obligue a guardar la imagen de un documento o una selfie; lo que existe es un interés legítimo (antifraude y defensa en reclamos) que las leyes de la región admiten bajo ciertas condiciones, y Google lo admite expresamente. Por eso:

- **Opción A (la actual).** Todas las cuentas conservan selfie y documento 12 meses. Es la más simple. Riesgo: un revisor de Apple puede objetar que «por temas legales» no está respaldado, y una persona sin ningún antecedente ve conservada su cara y su documento un año después de irse. Se mitiga con la declaración clara y el acceso restringido.
- **Opción B (recomendada, la decide el dueño).** El plazo de 12 meses se aplica cuando hubo suspensión, rechazo por fraude, incidencia grave o reclamo. Para una eliminación voluntaria sin antecedentes, las imágenes se eliminan al terminar el plazo de reclamos (por ejemplo, los 90 días que ya existen para verificaciones reemplazadas) y solo queda lo mínimo para frenar a quien volvió a registrarse tras un fraude (un código derivado del documento con clave del servidor, motivo y fecha, sin imágenes ni nombre). Reduce el riesgo en las tiendas y bajo la ley, y conserva el objetivo declarado.
- **Opción C.** No conservar imágenes en ningún caso y confiar solo en el registro mínimo de B. Es la más fácil de defender y la que menos pruebas deja en un reclamo.

Sea cual sea la opción, **se declara en la política y se repite en la pantalla de eliminación** («Eliminamos tu cuenta y tus datos. Por seguridad y para prevenir fraude, conservamos tu selfie y la imagen de tu documento durante N meses; solo las ve personal autorizado de Agrocom y luego se destruyen»). Y la regla de 5.1.2(ii): lo conservado no se usa para otra cosa.

**Respaldos.** Los datos eliminados siguen en los respaldos hasta que rotan. La política debe decir cuánto (**[POR DEFINIR]**, por ejemplo hasta 30 días) y el procedimiento de restauración debe volver a aplicar las eliminaciones pendientes. El invariante 15 (mover a `respaldo_*`) no aplica a datos personales de una eliminación.

### 1.6 Página web de solicitud de eliminación

Google la exige: «un enlace web donde las personas pidan la eliminación sin reinstalar la app», que nombre la app o el desarrollador como aparecen en Play, y fácil de encontrar. Apple no la pide, pero la acepta como complemento.

- Ruta pública `GET /eliminar-cuenta`, enlazada desde `/privacidad`, `/terminos`, la ficha de Play (campo de la sección de eliminación de datos), el campo «Privacy Choices URL» de Apple y las apps.
- Contenido: nombre «Agrocom», para cuál app aplica, qué se elimina, qué se conserva y por cuánto (la tabla de 1.5), plazo, cómo se confirma.
- Flujo: la persona escribe su teléfono, usuario o correo y la app responde siempre igual; se envía un código al **correo de respaldo** (el mismo mecanismo de recuperación del ADR 0046 §27, con sus límites) y, al confirmarlo, queda registrada la solicitud. Sin código no se acepta: es la única forma de que un tercero no elimine la cuenta de otro.
- Para quien perdió el acceso al correo: un formulario de contacto que abre una solicitud revisada por un administrador comparando selfie y documento (la vía que ya describe el ADR 0046).
- La solicitud entra a una cola del panel con estado, plazo y autor; la persona recibe un correo al comenzar y otro al terminar (se envía antes de borrar el correo de la cuenta).

### 1.7 Permisos y textos de propósito

Fuentes: [User Data](https://support.google.com/googleplay/android-developer/answer/10144311) y [Permisos de ubicación](https://support.google.com/googleplay/android-developer/answer/9799150) (Google); 5.1.1(ii), 5.1.1(iii), 5.1.1(iv), 5.1.2(i) y 5.1.5 de las [pautas de Apple](https://developer.apple.com/app-store/review/guidelines/) (2026-11-26). Apple pide textos de propósito claros, completos y que no condicionen el uso de la app a activar ubicación o notificaciones. Su guía de interfaz sobre permisos no cargó en la consulta: lo que sigue sobre pedir en contexto y explicar antes del aviso del sistema es **[NO VERIFICADO contra la página oficial]**.

| Permiso | Dónde se usa | Qué dice hoy | Texto propuesto (es) | Notas |
|---|---|---|---|---|
| Cámara | Selfie de verificación (obligatoria con cámara, sin galería), fotos de avance del piloto, foto del terreno, documento, incidencias | iOS: «Agrocom usa la cámara para fotografiar el lote, las incidencias y los documentos» | «Agrocom usa la cámara para tomarte una selfie y fotografiar tu documento, y para tomar fotos del terreno y del avance del servicio. Solo las ve quien tiene que verificarlas o quien participa del servicio.» | Falta la selfie. Pedirla justo antes del paso de la selfie, con una pantalla que explique para qué sirve y que no se usa para nada más |
| Fotos | Elegir foto de perfil, logo, documento y fotos del terreno de la galería (no la selfie) | iOS: «necesita acceder a tus fotos para adjuntar evidencias y documentos» | «Agrocom accede solo a las fotos que eliges para tu perfil, el logo de tu empresa, tus documentos y el terreno. No lee el resto de tu galería.» | Apple 5.1.1(iii) recomienda el selector del sistema en lugar de acceso completo |
| Ubicación (en uso) | Solo la app del piloto, durante un servicio en ejecución | iOS: «Agrocom comparte tu última ubicación con el cliente solo mientras tienes un servicio en ejecución» | Mantener el texto. Añadir antes del permiso una pantalla dentro de la app que diga qué se comparte, con quién, cuándo y que puede desactivarse | Android declara hoy `ACCESS_COARSE_LOCATION` y `ACCESS_FINE_LOCATION` en el manifiesto común: **retirarlos del flavor cliente**. No hay ubicación en segundo plano ni servicio en primer plano; mantener así evita el formulario de ubicación en segundo plano de Google y su video. Apple 5.1.2(i): la app del piloto debe funcionar si se rechaza; hoy puede desactivarla |
| Notificaciones | Avisos de ofertas, adjudicación, mensajes y nuevas solicitudes | Android: `POST_NOTIFICATIONS`; iOS pide en ejecución | Pedir en contexto, con una pantalla previa: «Te avisamos cuando haya ofertas, mensajes o una solicitud nueva» | Apple 5.1.2(i): no exigirlas para usar la app. Hoy el aviso se ve en la campana mientras no haya push |

### 1.8 Clasificación por edad y público objetivo

| Tienda | Qué piden | Aplicación |
|---|---|---|
| Google | Declarar el público objetivo; si incluye niños rige la política de Familias. Cuestionario IARC en la sección de contenido; la inexactitud puede llevar a retirar la app ([Content ratings](https://support.google.com/googleplay/android-developer/answer/9859655), [App content](https://support.google.com/googleplay/android-developer/answer/9859455), 2026-11-26) | Público objetivo: **adultos** (el piloto debe ser mayor de edad; el cliente tiene terreno). Fijar edad mínima en los términos (hoy `[POR DEFINIR]`). Respuestas: sin contenido sexual, violento ni de juego; sí hay mensajes entre usuarios, contenido de usuarios y ubicación compartida |
| Apple | Cuestionario de edad con 4+, 9+, 13+, 16+ y 18+; pregunta por contenido de usuarios, mensajes y chat, ubicación compartida y control de edad ([Age ratings](https://developer.apple.com/help/app-store-connect/reference/app-information/age-ratings-values-and-definitions), 2026-11-26) | Mensajes y contenido de usuarios no suben por sí solos la calificación; lo que la sube es una red social con difusión a muchos. Agrocom es 1:1 entre partes adjudicadas. **[NO VERIFICADO el resultado exacto]**: se conoce al completar el cuestionario |
| Apple, Kids | No aplica: la app no está dirigida a menores | Evitar términos y capturas que sugieran público infantil |
| Brasil | Google usa ClassInd (L, 10, 12, 14, 16, 18) por la IARC | Se obtiene sola del cuestionario |

### 1.9 Contenido de usuarios: el chat, las fotos y las calificaciones

| Requisito | Fuente | Estado hoy | Aplicación |
|---|---|---|---|
| Google: aceptar los términos antes de crear contenido; los términos definen lo objetable; moderación «robusta, efectiva y continua»; **bloqueo en la app** para funciones 1:1; reporte de contenido y usuarios cuando la app es solo de usuarios registrados | [UGC](https://support.google.com/googleplay/android-developer/answer/9876937) (2026-11-26) | Términos con la aceptación del ADR 0046 §31; el borrador prohíbe insultos, amenazas y datos de terceros. **No hay reportar ni bloquear en el chat** | T7 |
| Apple 1.2: filtro de material objetable, mecanismo de **reporte con respuesta a tiempo**, **bloqueo** de usuarios abusivos y **contacto publicado** | [Guidelines 1.2](https://developer.apple.com/app-store/review/guidelines/) (2026-11-26) | Solo hay `POST /api/calificaciones/{id}/reportar` para calificaciones. Soporte lee una conversación solo con un reclamo abierto | T7 |

Propuesta: en cada mensaje y en el chat, «Reportar» (con motivo; adjunta a soporte los últimos mensajes de esa conversación **con el consentimiento de quien reporta**, lo que se dice en la política) y «Bloquear» (la otra parte ya no puede escribir; **el servicio sigue**, porque la adjudicación es un acuerdo y el reclamo es la vía formal). Respuesta de soporte en el plazo que ya existe para reclamos no urgentes (24 horas hábiles). Filtro básico de enlaces, teléfonos y groserías, y un administrador puede ocultar una foto (ya existe). Contacto de soporte visible en la app y en la ficha.

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
| **Sign in with Apple** | **No aplica.** Apple 4.8 solo exige otra opción de inicio de sesión cuando la app usa un inicio de sesión de terceros (Google, Facebook, etc.); Agrocom usa solo su propio sistema de cuentas, que está en la lista de excepciones ([4.8](https://developer.apple.com/app-store/review/guidelines/), 2026-11-26). Si algún día se agrega «Entrar con Google», sí aplica |
| **Privacy manifest de iOS** | Apple exige un `PrivacyInfo.xcprivacy` con las APIs de «motivo requerido» de la app y de los SDK (desde el 1 de mayo de 2024). La página oficial no cargó en la consulta, así que esto sale de conocimiento general **[NO VERIFICADO]**. Revisar `shared_preferences`, `flutter_secure_storage`, `drift`, `path_provider` y `package_info_plus` (T10) |
| **Seguridad del transporte** | Google exige manejar los datos personales de forma segura (HTTPS). Apple bloquea HTTP por defecto. Requiere T4 |
| **Dato sensible en el dispositivo** | Cumple: selfie, documento y códigos no se guardan en `drift`, preferencias ni logs (ADR 0024 §7); el token vive en Keychain/Keystore |
| **Publicidad y seguimiento** | No hay. Declarar «sin anuncios» en Google y sin rastreo en Apple |
| **Cuenta y datos sin compra** | Sin cobros en la app: no hay compras dentro de la app |
| **Datos personales pedidos** | Apple 5.1.1(v): solo se piden datos personales cuando son esenciales para la función principal o la ley. La verificación se justifica por el núcleo del marketplace y se explica en las notas de revisión |

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
| Derechos de acceso, rectificación, cancelación y oposición (ARCO), y portabilidad en algunas leyes | La persona consulta, corrige, elimina y se opone | Corrección y eliminación en la app y la web (T1, T2); **falta** el canal y plazo de acceso y una exportación (T15) |
| Conservación limitada | Guardar solo el tiempo necesario | 12 meses de la selfie y el documento; resto **[POR DEFINIR]** (T13) |
| Transferencias internacionales | Enviar datos al extranjero con garantías | Hoy hospedaje propio; si se usa nube, push (Google o Apple) o correo de terceros, hay que revisarlo (sección 3) |
| Responsable y contacto | Identificar a quien trata los datos y cómo contactarlo (LGPD pide un encargado) | Falta el dato de Agrocom SRL y el contacto en la política |
| Datos de menores | Protección reforzada | App para mayores de edad, edad mínima en los términos |

**Qué implica para Agrocom.** No hay una obligación local equivalente a la LGPD hoy, pero (1) las tiendas imponen un piso con eliminación, transparencia y consentimiento en todos los países; (2) construir sobre los principios comunes deja a Agrocom listo si Bolivia aprueba su ley (el anteproyecto de AGETIC los sigue) y si entra a otro país; (3) un registro con documento y rostro es justo el tipo de dato que esas leyes tratan con más cuidado, así que la minimización y el aviso valen más que en un marketplace común.

## 3. Puntos que debe confirmar un abogado local

1. **Si en Bolivia hay una norma que obligue o permita conservar la selfie y el documento 12 meses** tras la baja, y con qué base (interés legítimo, defensa de reclamos, deber legal). Es el motivo que se escribe hoy como «por temas legales». Y si la Opción B o C (sección 1.5) es más prudente.
2. **La naturaleza de la selfie**: si, sin comparación automática, se considera un dato biométrico o sensible, y qué consentimiento adicional se necesita (LGPD, Ley 1581 y otras lo tratan así cuando hay procesamiento biométrico).
3. **Transferencia a proveedores fuera del país**: correo, push (Google o Apple), mapas, copias de seguridad o una futura nube. Qué cláusulas y qué aviso corresponden, y si alguna ley extranjera aplica si Agrocom sirve a personas de otro país.
4. **Datos de documentos de terceros que el cliente carga** (contactos de referencia de empresa, integrantes del equipo): base legal para tratarlos, porque esas personas no aceptaron nada.
5. **El plazo y el contenido de los términos y de la privacidad**: edad mínima, ley aplicable y jurisdicción, datos y representación de Agrocom SRL como responsable, aviso de incidentes y canal de derechos.
6. **El alcance de lo que se conserva del registro del servicio** (mensajes, fotos, calificaciones, bitácora) y su plazo.
7. **Ubicación en SOS e incidencias** y su conservación.
8. **Si el PL-538 u otra norma estará vigente cuando se publique la app**, y qué cambiaría (inscripción, responsable de protección, autoridad).
9. **Cualquier obligación sectorial**: uso de drones agrícolas y datos de terrenos; no es el tema de este documento y no lo revisé.

## 4. Tareas derivadas

Prioridad: **P0** bloquea el envío a las tiendas; **P1** antes de abrir al público; **P2** poco después. Los números de ADR nuevos son los siguientes libres (0047 en api y 0025 en field).

| ID | Tarea | Repo | Prio | Documento o ADR que se actualiza |
|---|---|---|---|---|
| T1 | **Eliminar mi cuenta**: caso de uso y endpoint (`POST /api/cuenta/eliminacion` con confirmación por código al correo y contraseña), estados (`solicitada`, `programada`, `ejecutada`), anonimización en el lugar, cancelación de ofertas y solicitudes, bloqueo mientras haya servicio activo o reclamo (con mensaje claro), correo de confirmación y plazo en un parámetro. Pantalla en las dos apps (Perfil, Privacidad y datos) con los cinco estados y goldens | api + field | P0 | Nuevo ADR 0047 (api) y ADR 0025 (field); `CLAUDE.md` invariantes 8 y 15 (excepción para datos personales); ADR 0046 §33; `verificacion_proveedores.md`; `seguimiento_y_privacidad.md`; `docs/api/openapi.yaml`; `docs/seguridad/autenticacion.md`; ADR 0024 de field; `DESIGN.md` |
| T2 | **Página web `/eliminar-cuenta`** (sección 1.6) y cola de solicitudes en el panel (estado, plazo, autor, caso sin acceso al correo) | api | P0 | ADR 0047; `legal.md`; política del panel; glosario |
| T3 | **Completar los textos legales**: quitar los `[POR DEFINIR]` (responsable, contacto, edad mínima, terceros, retención, derechos, incidentes, transferencias), incluir la tabla de 1.3 y 1.5, y subir la versión | api | P0 | `legal.md`, `lang/es/legal.php`, `config/legal.php`; revisión de abogado (sección 3) |
| T4 | **Dominio y HTTPS en producción**, antes de la URL de privacidad y de la eliminación | api | P0 | ADR 0017; `docs/gestion/entornos.md` |
| T5 | **Textos de propósito y permisos por app**: Info.plist y manifiestos por flavor (retirar la ubicación del cliente), selfie en el texto de cámara, pantallas de explicación previa para cámara-selfie, ubicación y notificaciones | field | P0 | ADR 0024 puntos 5 y 7 de field; `DESIGN.md`; `docs/diseno/` |
| T6 | **Enlaces a términos y privacidad** en el registro (la casilla del ADR 0024 §14 ya está decidida) y **dentro de la app** (ajustes de cuenta), más contacto de soporte visible | field | P0 | ADR 0024 §14; `DESIGN.md` |
| T7 | **Reportar y bloquear en el chat**, filtro básico, respuesta de soporte, contacto publicado; la política cambia para que soporte lea los mensajes reportados con consentimiento de quien reporta | api + field | P0 | Nuevo ADR (moderación del chat); `seguimiento_y_privacidad.md`; `seguridad.md`; ADR 0040; `openapi.yaml`; ADR 0017 y 0018 de field |
| T8 | **Cuentas de revisión de las tiendas** (sección 1.10) con datos de demostración aislados, marca para excluirlas de métricas, avisos y purgas, y las **notas para el revisor en inglés** | api | P0 | Nuevo `docs/gestion/revision-tiendas.md`; `demo-local.md`; ADR 0044 |
| T9 | **Declaraciones**: Play Console (Data safety de las dos apps, URL de eliminación, contenido, público objetivo, acceso a la app, privacidad) y App Store Connect (App Privacy de las dos apps, edad, URL de privacidad y de soporte, notas de revisión), con los valores de 1.3 y 1.4 | field | P0 | Nuevo `docs/gestion/publicacion-tiendas.md` en field |
| T10 | **`PrivacyInfo.xcprivacy`** de las apps y verificación de los manifiestos de los plugins | field | P1 | `docs/gestion/ci-por-impacto.md` si cambia la compuerta; ADR 0019 de field |
| T11 | **Mapa**: servidor de teselas propio o proveedor comercial, con `User-Agent` fijo y atribución (ya hay), y declarar al tercero | field + api | P1 | el ADR que fija las teselas abiertas (citado en `lib/nucleo/marketplace/ui/mapa_lote.dart` de field); política de privacidad |
| T12 | **Push**: decidir el servicio (FCM/APNs), enviar sin datos personales en el mensaje, declarar el ID de dispositivo | api + field | P1 | ADR 0041 y 0045; ADR 0020 de field; Data safety |
| T13 | **Retención**: parámetros y política para mensajes, fotos, bitácora, aceptaciones, registros del servidor y respaldos; elegir entre las Opciones A, B o C de 1.5 | api | P1 | ADR 0046 §33; `seguimiento_y_privacidad.md`; `CLAUDE.md` invariante 13; tarea `PurgarVerificaciones` |
| T14 | **Ubicación en SOS e incidencias**: fijar retención, decir en la política que existe y alinear el invariante 13 | api + field | P1 | ADR 0040; `seguridad.md`; `seguimiento_y_privacidad.md`; `CLAUDE.md` de field («ninguna ubicación se guarda en drift») |
| T15 | **Derechos de acceso y rectificación**: canal, plazo de respuesta y exportación de los datos propios; cola en el panel | api | P2 | ADR 0047; `legal.md` |
| T16 | **Registro de aceptación** (`sec_aceptaciones`, `GET /api/legal/vigentes`, `legal_pendiente`): confirmar que está implementado antes del envío | api + field | P1 | ADR 0046 §31; ADR 0024 §14 de field |
| T17 | **Edad mínima y cuestionarios de edad** con las respuestas de 1.8 | api + field | P1 | Términos; ADR 0046 |
| T18 | **Terceros y encargados del tratamiento**: lista pública de proveedores, contratos con ellos y revisión de transferencias | api | P1 | `legal.md`; `docs/seguridad/` |
| T19 | **Aviso de incidentes de seguridad**: procedimiento y texto | api | P2 | `docs/seguridad/`; `legal.md` |
| T20 | **Abogado local**: revisar las preguntas de la sección 3 y los textos de T3 | dueño | P0 | Este documento |
| T21 | **Cuentas de desarrollador de organización** (Google con D-U-N-S, Apple como entidad) | dueño | P0 | `docs/gestion/publicacion-tiendas.md` |

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

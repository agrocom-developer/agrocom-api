# Autenticación de las apps y del panel

## Tres tipos de cuenta

`sec_user.tipo_cuenta` es `panel`, `cliente` o `proveedor` (ADR 0046). El panel autentica solo cuentas `panel`; `POST /api/auth/token` emite token solo a cuentas `cliente` o `proveedor` y recibe `app` (`cliente` o `proveedor`), la app desde la que se ingresa. Si el tipo de la cuenta no es el de la app, responde `403 tipo_de_cuenta_incorrecto` con el tipo de la cuenta y no emite token. Una cuenta del otro mundo (panel desde la app, o app desde el panel) recibe la misma respuesta que una credencial inválida, para no revelar qué cuentas existen. El token identifica cuenta y dispositivo, no un rol: la respuesta de `POST /api/auth/token` y de `GET /api/auth/sesion` trae `cuenta` (con su `tipo`, su estado de aprobación y lo que falta para aprobarla), `persona` y `verificacion`. Qué puede hacer la cuenta lo decide el servidor en cada caso de uso según su tipo y su estado (`403 cuenta_no_aprobada`).

## Teléfono, correo de respaldo y una cuenta de cada tipo

Toda cuenta de app registra un **teléfono** (referencia principal de autenticación, en formato internacional) y un **correo de respaldo**. El login acepta el usuario o el teléfono. `sec_user` tiene índices únicos parciales `(telefono, tipo_cuenta)` y `(email, tipo_cuenta)`, y `sec_personas` uno por `(documento_tipo, documento_numero)` con `sec_user` por `(persona_id, tipo_cuenta)`: no hay dos cuentas de cliente con el mismo teléfono ni dos de proveedor; el mismo teléfono sí sirve a una cuenta de cada tipo. Registrarse otra vez en un tipo que ya tiene cuenta con ese teléfono o ese documento responde `409 cuenta_ya_registrada` con el tipo, sin crear nada ni devolver datos: la app dice «Ya tienes una cuenta, inicia sesión» y, si le falta completar algo (selfie, documentos), la guía el `falta` de la sesión.

### Contraseña

La contraseña de una cuenta de app pide **4 caracteres como mínimo** (100 como máximo) y ninguna regla de composición: sin mayúsculas, números ni símbolos obligatorios y sin lista de prohibidas. Lo compensan el límite de intentos y el bloqueo temporal del login (ver «Límite de intentos del login»), que responden igual exista o no la cuenta. El panel conserva sus propias reglas.

### Qué prueba el teléfono y qué no

Un teléfono único **no prueba que quien se registra sea su dueño**: un impostor podría registrar primero el de otra persona. La prueba de posesión es un **código enviado por WhatsApp** (WhatsApp Business) al número, con SMS y correo como alternativas del mismo código. Mientras la persona no lo confirma, `telefono_verificado_en` es nulo, el teléfono es dato de contacto y el control real de identidad es la aprobación de un administrador con selfie en cámara y documento; sin ella nadie opera en el mercado. Controles:

- **Verificar el teléfono**: `POST /api/cuenta/telefono/verificacion` (`canal`: `whatsapp` o `sms`) envía el código al teléfono de la cuenta y `POST /api/cuenta/telefono/verificacion/confirmar` (`codigo`) lo confirma y marca `telefono_verificado_en`. La sesión devuelve `telefono_verificado` para que la app lo pida mientras falte. `verificacion.telefono.exigido_para_aprobar` (falso por defecto) impide aprobar una cuenta con el teléfono sin verificar.
- **Frecuencia**: `registro-publico` (5 por minuto y 20 por hora por IP), un tope de `409` por IP (5 por hora, luego `429`) y límites por teléfono y documento consultados, también en el cambio de teléfono.
- **No revelar de más**: mensajes iguales y sin datos. **Riesgo aceptado y mitigado**: «ya tienes una cuenta» confirma que un teléfono o documento está registrado en Agrocom; es un dato de poco valor y está limitado por frecuencia.
- **Comprobación manual**: la ficha muestra «Teléfono sin verificar» y el administrador puede llamar o escribir al número antes de aprobar y marcarlo verificado (con su nombre y motivo).
- **Recurso de la víctima**: la oficina compara selfie y documento, elimina la cuenta falsa con motivo y libera el teléfono.

## Códigos de un solo uso

Un mismo mecanismo sirve a la verificación del teléfono, la recuperación del acceso, el cambio de contraseña, el cambio de teléfono y la eliminación de la cuenta. El propósito va en `sec_codigos_verificacion.proposito` (`verificar_telefono`, `recuperar_acceso`, `cambiar_contrasena`, `cambiar_telefono`, `eliminar_cuenta`) y el canal en `canal`:

| Canal | Destino | Cómo sale |
|---|---|---|
| `whatsapp` | teléfono de la cuenta | adaptador de `EnviadorDeCodigo` configurado en `config/verificacion.php` (`canales.whatsapp.adaptador`); es el canal principal |
| `sms` | teléfono de la cuenta | adaptador de `EnviadorDeCodigo` (`canales.sms.adaptador`) |
| `correo` | correo de respaldo | `Notification` con la configuración del panel (`plt_configuraciones`: `correo.host`, `correo.puerto`, `correo.usuario`, `correo.password`, `correo.remitente`); en local y en la demo es Mailpit (`http://localhost:8025`) |

`EnviadorDeCodigo` es la interfaz de dominio; el dominio no conoce a ningún proveedor. Un canal sin adaptador responde `503 canal_no_disponible` (sin tocar la cuenta) y la app ofrece otro. La persona elige el canal; la verificación del teléfono solo admite `whatsapp` y `sms`.

- **El código**: 6 dígitos, guardado con hash, vence a los 10 minutos (`verificacion.codigo.vigencia_minutos`), un solo uso, 5 intentos, un código vigente por cuenta y propósito (pedir otro anula el anterior), máximo 3 envíos por hora y 10 por día por cuenta, más el límite por IP. No se escribe en logs, bitácora ni respuestas; la fila guarda cuándo se pidió y cuándo se confirmó, y los cambios de la cuenta que provoca (contraseña, teléfono verificado) quedan en la bitácora sin valores.
- **Canal probado**: confirmar un código por WhatsApp o SMS marca `telefono_verificado_en`; por correo, `email_verificado_en`.
- **Modo de desarrollo**: `VERIFICACION_MODO_DESARROLLO=true` fija el código en `000000`, hace que `whatsapp` y `sms` no envíen nada, y solo se respeta con `APP_ENV` en `local` o `testing`. Sin correo configurado el envío por correo falla con `503`.

## Recuperar el acceso, cambiar la contraseña y cambiar el teléfono desde la app

No hay página web ni enlace de restablecimiento para las cuentas de app (el del ADR 0004 queda solo para el panel).

- **Recuperar** (`POST /api/auth/recuperar` y `/confirmar`): la persona da su usuario, teléfono o correo, la `app` (`cliente` o `proveedor`) y elige el `canal`. La respuesta es siempre `202` con el mismo mensaje exista o no la cuenta. Con el código fija una contraseña nueva y la app le muestra su usuario; cualquier fallo del código (cuenta inexistente, código errado, vencido, usado o con los intentos agotados) responde el mismo `422 codigo_invalido`. Al cambiarla se cierran las sesiones y se revocan los tokens de dispositivo.
- **Cambiar la contraseña** con la sesión iniciada (`POST /api/cuenta/contrasena/codigo` y `PUT /api/cuenta/contrasena`): código por el canal elegido y contraseña nueva; se revocan los demás dispositivos y se conserva el actual.
- **Cambiar el teléfono** (`POST /api/cuenta/telefono` y `/confirmar`): con la sesión iniciada y la contraseña, el teléfono nuevo debe cumplir la unicidad por tipo; el código va al teléfono nuevo (WhatsApp o SMS) o al correo de respaldo; si llegó al teléfono nuevo queda verificado, si llegó al correo vuelve a nulo; se avisa al correo.

## Eliminar mi cuenta

La persona elimina su cuenta desde la app: `GET /api/cuenta/eliminacion` informa el estado y los motivos que la impiden, `POST /api/cuenta/eliminacion/codigo` envía el código (`propósito = eliminar_cuenta`, por el canal elegido, mismos límites que la recuperación) y `POST /api/cuenta/eliminacion` la solicita con la **contraseña o el código** (basta uno). También puede hacerlo desde la página pública `GET /eliminar-cuenta` (teléfono, usuario o correo, la app y el canal; respuesta siempre igual; el resultado se muestra solo después del código). Responde `409 eliminacion_bloqueada` con los `motivos` si hay un servicio adjudicado o en ejecución, un reclamo o una incidencia grave abiertos, o pilotos vinculados activos. Al pedirla se revocan las sesiones y la cuenta queda `solicitada` durante 7 días: puede iniciar sesión, pero la sesión solo permite ver el estado, cancelar (`POST /api/cuenta/eliminacion/cancelar`) y cerrar sesión; lo demás responde `403 cuenta_en_eliminacion`. La tarea diaria `EjecutarEliminacionesDeCuenta` la ejecuta (anonimiza en el lugar, sin pasar por `respaldo_*`), y todo queda en la bitácora sin valores personales. Detalle en `docs/politicas/eliminacion_de_cuenta.md`.

## Aceptación de términos y privacidad

El registro exige la versión vigente de los términos y de la privacidad (`GET /api/legal/vigentes`); se guarda en `sec_aceptaciones` la versión y la fecha por cuenta, y cuando cambia la versión la sesión trae `legal_pendiente` y la app pide aceptar antes de operar en el mercado.

## Verificación de identidad

La selfie y el documento de identidad se suben por HTTPS a `PUT /api/verificacion/selfie` y `PUT /api/verificacion/documento`. Se guardan en el disco privado con ruta por objeto (ADR 0026), solo se sirven con URL firmada de corta vida a quien tiene el permiso `seguridad.verificacion.ver`, y su contenido no se escribe en logs ni en la bitácora (cada acceso sí queda registrado: quién, cuándo y de qué verificación). Se conservan mientras exista la cuenta; después, 12 meses si hubo antecedentes o una baja administrativa, 90 días si la persona eliminó su cuenta sin antecedentes y 90 días las rechazadas o reemplazadas (retención B, ADR 0047); una tarea diaria elimina entonces los archivos, deja un evento en la bitácora sin los datos y conserva solo un registro mínimo antifraude (huella del documento con la clave `ANTIFRAUDE_CLAVE`, motivo y fecha; sin imágenes ni nombre). Cada apertura de un archivo registra quién, cuándo y por qué motivo. El servidor no puede probar que una imagen salió de la cámara: guarda `selfie_capturada_en` y rechaza una captura anterior al plazo del parámetro (10 minutos por defecto); el control real es la revisión humana de la selfie contra el documento. La carga la hace la persona desde la app, o el administrador desde el panel con la marca «cargado por administrador».

## Límite de intentos del login y bloqueo temporal

`POST /api/auth/token` usa el limitador `login-campo` (definido en `SeguridadServiceProvider`) con dos cubos:

| Cubo | Clave | Límite |
|---|---|---|
| Usuario + IP | `username` normalizado (minúsculas, sin espacios) + IP | 6 por minuto |
| IP | IP | 60 por minuto |

Como la contraseña solo exige 4 caracteres, se suma un **bloqueo temporal por fallos**: cada credencial incorrecta cuenta contra el identificador normalizado (usuario o teléfono, exista o no la cuenta) y contra la IP; al llegar a **10 fallos en 15 minutos por identificador** o **30 por IP**, el login responde `429` con `Retry-After` y el mismo mensaje sin importar si la cuenta existe. Un login correcto limpia el contador del identificador. Los mismos topes protegen la confirmación de los códigos (5 intentos por código).

Los dispositivos del lote comparten la IP del enlace satelital: limitar solo por IP bloquearía a todo el equipo por los errores de una persona. El cubo por usuario aísla ese caso, y el cubo por IP, más amplio, frena el barrido de usuarios distintos desde un mismo origen. Superado el límite, la API responde `429` con `Retry-After`.

El panel web conserva su `throttle:6,1` por IP.

## Vigencia de los tokens

El token de dispositivo caduca a los `SANCTUM_EXPIRATION` minutos de su emisión (43200 por defecto, 30 días). Sanctum compara la fecha de emisión con ese plazo en cada request, y `sec_token_dispositivo.expires_at` guarda el vencimiento calculado al emitir para que el panel lo muestre.

- Valor vacío o no numérico en `SANCTUM_EXPIRATION`: el token no caduca por tiempo.
- Al vencer, la API responde `401`; la app pide el login en cuanto recupera conexión y lo capturado sin conexión sigue en el outbox hasta enviarse con el token nuevo.
- Bajar el plazo caduca de golpe los tokens más viejos que el nuevo valor.
- El cierre de sesión de la app, la revocación desde el panel y la baja de la cuenta siguen invalidando el token en el request siguiente. Un cambio del estado de aprobación no invalida el token: cambia lo que la cuenta puede hacer.

Las pruebas están en `tests/Feature/Seguridad/LoginCampoSeguridadTest.php`.

## Sesión por biometría

La huella o el rostro **nunca llegan al servidor**: la app los usa solo para desbloquear el almacenamiento seguro del dispositivo (Keychain/Keystore) donde guarda su token. Lo que el servidor garantiza para que eso funcione:

- **Validar**: `GET /api/auth/sesion` con el token guardado dice si sigue vigente (`401` si caducó, se revocó o la cuenta ya no puede entrar); la app la consulta tras el desbloqueo local cuando hay red y, sin red, opera con lo ya capturado.
- **Renovar**: `POST /api/auth/renovar` (con el token vigente) emite un token nuevo para el mismo dispositivo, con vigencia completa, y revoca el anterior; la app lo llama antes de que caduque (por ejemplo, cuando le quedan menos de 7 días: `dispositivo.expira_en`). Un token ya caducado no se renueva: la persona vuelve a entrar con su contraseña o con el código de recuperación.
- **Revocar**: `DELETE /api/auth/token` cierra el dispositivo actual; el panel revoca los demás (`/api/dispositivos` lista los propios). Cambiar la contraseña o eliminar la cuenta revoca todos los dispositivos (el que cambió la contraseña conserva el suyo). Si la persona desactiva la biometría en la app, la app borra el token y pide la contraseña.
- **Un solo token vivo por dispositivo** (`uuid_dispositivo`): iniciar sesión o renovar en el mismo dispositivo revoca el token anterior.

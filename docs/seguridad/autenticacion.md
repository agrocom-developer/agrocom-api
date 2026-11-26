# Autenticación de las apps y del panel

## Tres tipos de cuenta

`sec_user.tipo_cuenta` es `panel`, `cliente` o `proveedor` (ADR 0046). El panel autentica solo cuentas `panel`; `POST /api/auth/token` emite token solo a cuentas `cliente` o `proveedor` y recibe `app` (`cliente` o `proveedor`), la app desde la que se ingresa. Si el tipo de la cuenta no es el de la app, responde `403 tipo_de_cuenta_incorrecto` con el tipo de la cuenta y no emite token. Una cuenta del otro mundo (panel desde la app, o app desde el panel) recibe la misma respuesta que una credencial inválida, para no revelar qué cuentas existen. El token identifica cuenta y dispositivo, no un rol: la respuesta de `POST /api/auth/token` y de `GET /api/auth/sesion` trae `cuenta` (con su `tipo`, su estado de aprobación y lo que falta para aprobarla), `persona` y `verificacion`. Qué puede hacer la cuenta lo decide el servidor en cada caso de uso según su tipo y su estado (`403 cuenta_no_aprobada`).

## Teléfono, correo de respaldo y una cuenta de cada tipo

Toda cuenta de app registra un **teléfono** (referencia principal de autenticación, en formato internacional) y un **correo de respaldo**. El login acepta el usuario o el teléfono. `sec_user` tiene índices únicos parciales `(telefono, tipo_cuenta)` y `(email, tipo_cuenta)`, y `sec_personas` uno por `(documento_tipo, documento_numero)` con `sec_user` por `(persona_id, tipo_cuenta)`: no hay dos cuentas de cliente con el mismo teléfono ni dos de proveedor; el mismo teléfono sí sirve a una cuenta de cada tipo. Registrarse otra vez en un tipo que ya tiene cuenta con ese teléfono o ese documento responde `409 cuenta_ya_registrada` con el tipo, sin crear nada ni devolver datos: la app dice «Ya tienes una cuenta, inicia sesión» y, si le falta completar algo (selfie, documentos), la guía el `falta` de la sesión.

### Qué prueba el teléfono y qué no

Un teléfono único **no prueba que quien se registra sea su dueño**: un impostor podría registrar primero el de otra persona. La prueba de posesión es un código por SMS o WhatsApp, que requiere un proveedor (Firebase o Supabase, más adelante). Hasta entonces el teléfono es dato de contacto y de autenticación con `telefono_verificado_en` nulo, y el control real de identidad es la aprobación de un administrador con selfie en cámara y documento; sin ella nadie opera en el mercado. Controles sin proveedor:

- **Frecuencia**: `registro-publico` (5 por minuto y 20 por hora por IP), un tope de `409` por IP (5 por hora, luego `429`) y límites por teléfono y documento consultados, también en el cambio de teléfono.
- **No revelar de más**: mensajes iguales y sin datos. **Riesgo aceptado y mitigado**: «ya tienes una cuenta» confirma que un teléfono o documento está registrado en Agrocom; es un dato de poco valor y está limitado por frecuencia.
- **Comprobación manual**: la ficha muestra «Teléfono sin verificar» y el administrador puede llamar o escribir al número antes de aprobar y marcarlo verificado (con su nombre y motivo).
- **Recurso de la víctima**: la oficina compara selfie y documento, elimina la cuenta falsa con motivo y libera el teléfono.

El diseño ya prevé el código por SMS o WhatsApp: el envío es la interfaz `EnviadorDeCodigo` con el canal en `sec_codigos_verificacion`, los casos de uso de verificar el teléfono existen y responden `503 canal_no_disponible` sin adaptador, y los parámetros `verificacion.telefono.proveedor` y `verificacion.telefono.exigido_para_aprobar` lo activan.

## Recuperar el acceso y cambiar el teléfono desde la app

No hay página web ni enlace de restablecimiento para las cuentas de app (el del ADR 0004 queda solo para el panel).

- **Recuperar** (`POST /api/auth/recuperar` y `/confirmar`): la persona da su usuario, teléfono o correo; la respuesta es siempre la misma exista o no la cuenta; el código llega al correo de respaldo. Con el código fija una contraseña nueva y la app le muestra su usuario. Al cambiarla se cierran las sesiones y se revocan los tokens de dispositivo.
- **Cambiar el teléfono** (`POST /api/cuenta/telefono` y `/confirmar`): con la sesión iniciada y la contraseña, el teléfono nuevo debe cumplir la unicidad por tipo; el código va al correo de respaldo; al confirmarlo `telefono_verificado_en` vuelve a nulo y se avisa al correo.
- **El código**: 6 dígitos, guardado con hash, vence a los 10 minutos, un solo uso, 5 intentos, un código vigente por cuenta y propósito, máximo 3 envíos por hora y 10 por día por cuenta, más el límite por IP. No se escribe en logs, bitácora ni respuestas; la bitácora registra que se pidió y que se confirmó.
- **Correo**: sale por `Notification` con la configuración del panel (`plt_configuraciones`, `correo.host`, `correo.puerto`, `correo.usuario`, `correo.password`, `correo.remitente`). En local y en la demo es Mailpit (`http://localhost:8025`). **Modo de desarrollo**: `VERIFICACION_MODO_DESARROLLO=true` fija el código en `000000` y solo se respeta con `APP_ENV` en `local` o `testing`. Sin correo configurado el envío falla con `503`.

## Eliminar mi cuenta

La persona elimina su cuenta desde la app (`POST /api/cuenta/eliminacion`, con la contraseña y un código al correo de respaldo; `propósito = eliminar_cuenta`, mismos límites que la recuperación) o desde la página pública `GET /eliminar-cuenta` (teléfono, usuario o correo y la app; respuesta siempre igual; código al correo de respaldo; el resultado se muestra solo después del código). Responde `409 eliminacion_bloqueada` con los `motivos` si hay un servicio adjudicado o en ejecución, un reclamo o una incidencia grave abiertos, o pilotos vinculados activos. Al pedirla se revocan las sesiones y la cuenta queda `solicitada` durante 7 días: puede iniciar sesión, pero la sesión solo permite ver el estado, cancelar (`POST /api/cuenta/eliminacion/cancelar`) y cerrar sesión; lo demás responde `403 cuenta_en_eliminacion`. La tarea diaria `EjecutarEliminacionesDeCuenta` la ejecuta (anonimiza en el lugar, sin pasar por `respaldo_*`), y todo queda en la bitácora sin valores personales. Detalle en `docs/politicas/eliminacion_de_cuenta.md`.

## Aceptación de términos y privacidad

El registro exige la versión vigente de los términos y de la privacidad (`GET /api/legal/vigentes`); se guarda en `sec_aceptaciones` la versión y la fecha por cuenta, y cuando cambia la versión la sesión trae `legal_pendiente` y la app pide aceptar antes de operar en el mercado.

## Verificación de identidad

La selfie y el documento de identidad se suben por HTTPS a `PUT /api/verificacion/selfie` y `PUT /api/verificacion/documento`. Se guardan en el disco privado con ruta por objeto (ADR 0026), solo se sirven con URL firmada de corta vida a quien tiene el permiso `seguridad.verificacion.ver`, y su contenido no se escribe en logs ni en la bitácora (cada acceso sí queda registrado: quién, cuándo y de qué verificación). Se conservan mientras exista la cuenta; después, 12 meses si hubo antecedentes o una baja administrativa, 90 días si la persona eliminó su cuenta sin antecedentes y 90 días las rechazadas o reemplazadas (retención B, ADR 0047); una tarea diaria elimina entonces los archivos, deja un evento en la bitácora sin los datos y conserva solo un registro mínimo antifraude (huella del documento con la clave `ANTIFRAUDE_CLAVE`, motivo y fecha; sin imágenes ni nombre). Cada apertura de un archivo registra quién, cuándo y por qué motivo. El servidor no puede probar que una imagen salió de la cámara: guarda `selfie_capturada_en` y rechaza una captura anterior al plazo del parámetro (10 minutos por defecto); el control real es la revisión humana de la selfie contra el documento. La carga la hace la persona desde la app, o el administrador desde el panel con la marca «cargado por administrador».

## Límite de intentos del login

`POST /api/auth/token` usa el limitador `login-campo` (definido en `SeguridadServiceProvider`) con dos cubos:

| Cubo | Clave | Límite |
|---|---|---|
| Usuario + IP | `username` normalizado (minúsculas, sin espacios) + IP | 6 por minuto |
| IP | IP | 60 por minuto |

Los dispositivos del lote comparten la IP del enlace satelital: limitar solo por IP bloquearía a todo el equipo por los errores de una persona. El cubo por usuario aísla ese caso, y el cubo por IP, más amplio, frena el barrido de usuarios distintos desde un mismo origen. Superado el límite, la API responde `429` con `Retry-After`.

El panel web conserva su `throttle:6,1` por IP.

## Vigencia de los tokens

El token de dispositivo caduca a los `SANCTUM_EXPIRATION` minutos de su emisión (43200 por defecto, 30 días). Sanctum compara la fecha de emisión con ese plazo en cada request, y `sec_token_dispositivo.expires_at` guarda el vencimiento calculado al emitir para que el panel lo muestre.

- Valor vacío o no numérico en `SANCTUM_EXPIRATION`: el token no caduca por tiempo.
- Al vencer, la API responde `401`; la app pide el login en cuanto recupera conexión y lo capturado sin conexión sigue en el outbox hasta enviarse con el token nuevo.
- Bajar el plazo caduca de golpe los tokens más viejos que el nuevo valor.
- El cierre de sesión de la app, la revocación desde el panel y la baja de la cuenta siguen invalidando el token en el request siguiente. Un cambio del estado de aprobación no invalida el token: cambia lo que la cuenta puede hacer.

Las pruebas están en `tests/Feature/Seguridad/LoginCampoSeguridadTest.php`.

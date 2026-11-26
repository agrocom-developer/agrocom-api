# Autenticación de las apps y del panel

## Tres tipos de cuenta

`sec_user.tipo_cuenta` es `panel`, `cliente` o `proveedor` (ADR 0046). El panel autentica solo cuentas `panel`; `POST /api/auth/token` emite token solo a cuentas `cliente` o `proveedor` y recibe `app` (`cliente` o `proveedor`), la app desde la que se ingresa. Si el tipo de la cuenta no es el de la app, responde `403 tipo_de_cuenta_incorrecto` con el tipo de la cuenta y no emite token. Una cuenta del otro mundo (panel desde la app, o app desde el panel) recibe la misma respuesta que una credencial inválida, para no revelar qué cuentas existen. El token identifica cuenta y dispositivo, no un rol: la respuesta de `POST /api/auth/token` y de `GET /api/auth/sesion` trae `cuenta` (con su `tipo`, su estado de aprobación y lo que falta para aprobarla), `persona` y `verificacion`. Qué puede hacer la cuenta lo decide el servidor en cada caso de uso según su tipo y su estado (`403 cuenta_no_aprobada`).

## Una persona, una cuenta de cada tipo

`sec_personas` tiene un índice único por `(documento_tipo, documento_numero)` y `sec_user` uno por `(persona_id, tipo_cuenta)`. Registrarse de nuevo en el mismo tipo responde `409 persona_ya_registrada` con el tipo, sin devolver datos de la persona, bajo el limitador `registro-publico` (5 por minuto y 20 por hora por IP). El documento declarado no está verificado hasta la revisión: si alguien registró el de otra persona, un administrador compara la selfie con el documento y elimina la cuenta falsa con motivo, lo que libera el lugar.

## Verificación de identidad

La selfie y el documento de identidad se suben por HTTPS a `PUT /api/verificacion/selfie` y `PUT /api/verificacion/documento`. Se guardan en el disco privado con ruta por objeto (ADR 0026), solo se sirven con URL firmada de corta vida a quien tiene el permiso `seguridad.verificacion.ver`, y no se escriben en logs ni en la bitácora. El servidor no puede probar que una imagen salió de la cámara: guarda `selfie_capturada_en` y rechaza una captura anterior al plazo del parámetro (10 minutos por defecto); el control real es la revisión humana de la selfie contra el documento. La carga la hace la persona desde la app, o el administrador desde el panel con la marca «cargado por administrador».

## Límite de intentos del login

`POST /api/auth/token` usa el limitador `login-campo` (definido en `SeguridadServiceProvider`) con dos cubos:

| Cubo | Clave | Límite |
|---|---|---|
| Usuario + IP | `username` normalizado (minúsculas, sin espacios) + IP | 6 por minuto |
| IP | IP | 60 por minuto |

Los dispositivos del lote comparten la IP del enlace satelital: limitar solo por IP bloquearía a todo el equipo por los errores de una persona. El cubo por usuario aísla ese caso, y el cubo por IP, más amplio, frena el barrido de usuarios distintos desde un mismo origen. Superado el límite, la API responde `429` con `Retry-After`.

El panel web y `GET /api/version` conservan su `throttle:6,1` por IP.

## Vigencia de los tokens

El token de dispositivo caduca a los `SANCTUM_EXPIRATION` minutos de su emisión (43200 por defecto, 30 días). Sanctum compara la fecha de emisión con ese plazo en cada request, y `sec_token_dispositivo.expires_at` guarda el vencimiento calculado al emitir para que el panel lo muestre.

- Valor vacío o no numérico en `SANCTUM_EXPIRATION`: el token no caduca por tiempo.
- Al vencer, la API responde `401`; la app pide el login en cuanto recupera conexión y lo capturado sin conexión sigue en el outbox hasta enviarse con el token nuevo.
- Bajar el plazo caduca de golpe los tokens más viejos que el nuevo valor.
- La revocación desde el panel y la baja de la cuenta siguen invalidando el token en el request siguiente. Un cambio del estado de aprobación no invalida el token: cambia lo que la cuenta puede hacer.

Las pruebas están en `tests/Feature/Seguridad/LoginCampoSeguridadTest.php`.

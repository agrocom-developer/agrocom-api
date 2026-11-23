# Autenticación de la app de campo

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
- La revocación desde el panel, la baja de la cuenta y el retiro del rol siguen invalidando el token en el request siguiente.

Las pruebas están en `tests/Feature/Seguridad/LoginCampoSeguridadTest.php`.

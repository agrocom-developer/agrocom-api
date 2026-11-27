# Correo del sistema y recuperación del acceso del panel

## De dónde sale el correo

El sistema envía correo para tres cosas: los códigos de un solo uso de las cuentas de app (canal `correo`), el enlace para restablecer la contraseña del panel y el correo de prueba. Todas pasan por el mismo mailer, que se resuelve así:

1. **Servidor guardado en la base.** Si `/panel/configuracion`, pestaña **Correo**, tiene un servidor guardado (`correo.host`), ese SMTP pasa a ser el mailer por defecto (`ConfiguracionDeCorreo::aplicar()`, que se ejecuta una vez por proceso, al pedirse el gestor de correo).
2. **`.env` y `config/mail.php`.** Sin servidor en la base nada cambia: Mailpit en desarrollo (`MAIL_MAILER=smtp`, `MAIL_HOST=mail`), `array` en pruebas y `log` en un servidor sin correo. Lo que falte en la base (usuario, remitente) también cae al `.env`.

Un mailer `log` sin servidor en la base cuenta como **correo sin configurar**: el canal `correo` de los códigos responde `503 canal_no_disponible` y la recuperación del panel avisa que el correo todavía no está listo.

## Configurar el correo (panel)

Exclusivo del administrador (`seguridad.configuracion.ver` y `.editar`). Campos de la pestaña Correo:

| Campo | Clave | Notas |
|---|---|---|
| Proveedor | `correo.proveedor` | `gmail` (por defecto), `outlook` u `otro`. Al elegirlo la pantalla rellena servidor, puerto y cifrado. |
| Servidor SMTP | `correo.host` | Gmail `smtp.gmail.com`; Outlook o Microsoft 365 `smtp.office365.com`; `otro` es libre. |
| Puerto | `correo.puerto` | 587 con STARTTLS; 465 con SSL/TLS (Gmail también lo admite). |
| Cifrado | `correo.cifrado` | `tls` (STARTTLS, exigido), `ssl` (SMTPS) o `ninguno` (solo redes internas). |
| Usuario | `correo.usuario` | Normalmente la dirección completa. |
| Contraseña de aplicación | `correo.password` | Secreto. |
| Remitente | `correo.remitente` | En Gmail, la misma cuenta o un alias. |
| Nombre visible | `correo.remitente_nombre` | «DronAgro» por defecto. |

El catálogo vive en `config/configuracion.php` (grupo `correo`, presets en `proveedores_correo`); la validación (opciones, puerto 1 a 65535, servidor sin espacios, remitente con formato de correo) está en `ActualizarConfiguracionRequest`.

### Contraseña de aplicación de Gmail

Gmail no acepta la contraseña de la cuenta por SMTP. Hace falta activar la verificación en dos pasos, abrir `myaccount.google.com/apppasswords`, crear una con el nombre «DronAgro» y pegar las 16 letras (los espacios se ignoran en Gmail). Google la muestra una sola vez.

### Cómo se guarda y qué nunca se muestra

- Todo `valor` de `plt_configuraciones` va cifrado en reposo con `APP_KEY` (cast `encrypted`); el texto plano nunca llega a la columna.
- El campo de la contraseña llega siempre vacío a la pantalla, que solo dice «Guardada» o «Sin configurar» (ni siquiera los últimos caracteres). Dejarlo vacío conserva la guardada; la casilla «Eliminar» la borra.
- La bitácora registra que la configuración cambió, sin el valor (la columna `valor` es sensible y se excluye).
- Cambiar `APP_KEY` vuelve ilegibles los valores guardados: hay que volver a cargar la contraseña.

### Correo de prueba

El botón **Enviar correo de prueba** envía, con la configuración ya guardada, un correo al correo de la cuenta de quien lo pulsa (no a una dirección libre) y necesita el permiso de editar; límite de 5 por minuto. El resultado es éxito o una de estas causas, en lenguaje claro: usuario o contraseña rechazados, no se pudo conectar, tiempo agotado, cifrado que no coincide con el puerto, u otro error. El mensaje del servidor no se muestra ni se registra: el log solo guarda la categoría y la clase de la excepción.

## Recuperar el acceso del panel

Solo para cuentas de panel; las cuentas de app recuperan su acceso desde la app con un código (ver `autenticacion.md`).

- En el login, la pestaña «Recupera tu acceso» pide el correo y llama a `POST /recuperar`.
- La respuesta es siempre la misma (`seguridad.recuperar.estado_generico`), exista o no la cuenta, y también si el envío falla: el fallo queda en el log sin datos y no se le cuenta al visitante, porque solo ocurriría con cuentas que existen.
- El enlace (`/restablecer/{token}`) vence a los 60 minutos, sirve una sola vez y pedir otro invalida el anterior; una cuenta bloqueada no puede pedirlo ni usarlo.
- Límites: 6 solicitudes por minuto por IP (`429` con `Retry-After`) y una por minuto por correo (la limita el broker de contraseñas y responde igual).
- Si el correo todavía no está configurado, el login lo dice sin culpar a quien pregunta («Por ahora no podemos enviar correos desde este sistema…») y no se genera ningún enlace.
- Al restablecer se cierran las demás sesiones del usuario.

Los correos salen con la marca «DronAgro» (remitente, asunto y despedida).

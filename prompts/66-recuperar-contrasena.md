<!-- ciclo: critica=no turno-noche=1 descongela=tests,decisiones rama=feature/recuperar-contrasena etapas=5 -->

# Tarea 66 — correo en la cuenta, cambio de contraseña propio y recuperación por correo

## Por qué esta tarea

Hoy no existe ningún flujo de "olvidé mi contraseña", ni para el panel
(guard `interno`) ni para el portal (guard `cliente`). Es una decisión
explícita y vieja (`config/auth.php` lo dice en su cabecera: "sin broker de
reset por correo; login por username"), y quedó con dos consecuencias que el
usuario decidió revertir el 4/9/2026:

- La pestaña "Recuperar acceso" del login (`resources/views/components/organisms/login-form.blade.php`)
  es un `<div>` sin `<form>`: un input de email y un botón `type="button"`
  que no postea a nada. El usuario cree que pidió el reset y no pasa nada.
- Nadie puede cambiar su propia contraseña: solo un administrador desde
  `PUT /panel/usuarios/{u}`. No hay pantalla de perfil.

El usuario pidió tres cosas: que el formulario de recuperación **se envíe de
verdad, con validación CSRF**; que el correo salga de la **tabla vinculada al
usuario** (una cuenta interna cuelga de una persona, una de portal de un
contrato/cliente); y habilitar el correo para que el reset funcione.

No es crítica según la lista de `CLAUDE.md`, pero es autenticación: hacé el
trabajo como si lo fuera y dejalo anotado en `runs/66.md`.

## Lo que ya existe

- `sec_user`: `name`, `username`, `password`, `type`, `persona_id` (FK
  `per_personas`: `nombre`, `rol`, `base_id`, `activo` — **sin email ni
  teléfono**), `contrato_id` (FK `com_contratos` → `com_clientes` →
  `com_cliente_contactos`, que **sí tiene `email`** nullable por contacto).
  No hay tabla `password_reset_tokens` (la migración inicial la eliminó a
  propósito) y `config/auth.php` tiene `'passwords' => null`.
- Servicio `mail` (Mailpit) en `docker-compose.yml`: SMTP en `1025`, UI en
  `8025`. `.env.example` tiene `MAIL_MAILER=log` y `MAIL_HOST=127.0.0.1`;
  dentro del contenedor el host del SMTP es `mail`. No hay ninguna
  `Notification` ni `Mailable` en `app/`.
- `SesionController` (login interno) y `SesionPortalController` (portal)
  ya validan CSRF en el `POST` de login; el organism `login-form` lo usan
  ambos.
- El topbar (`resources/views/components/organisms/topbar.blade.php`) tiene
  el menú de usuario con "Cambiar de rol" y "Cerrar sesión": ahí va "Mi
  perfil".
- `sec_user_preferencia` guarda tema, idioma y (tras la tarea 63) zona
  horaria: es la tabla "del usuario", no de la persona.

## Decisión de modelo que esta tarea toma (y documenta)

El **correo de la cuenta vive en `sec_user.email`** (nullable, único entre
no borrados, índice parcial). Razón: el reset es de la cuenta, no de la
persona operativa ni del contacto comercial; una persona puede no tener
cuenta y un cliente tiene varios contactos con email. Las tablas vinculadas
se usan para **precargar**: al crear una cuenta interna desde el panel, si
la persona elegida tiene un contacto con correo en algún lado, se sugiere;
al crear una cuenta de portal, se sugiere el email del contacto `dueno` (o
el primero con email) de `com_cliente_contactos` del cliente del contrato.
El administrador siempre puede escribir otro. Documentá esto como
**ampliación** del ADR 0004 (sección nueva al final, con fecha), sin
reescribir lo que ya dice; por eso `descongela=decisiones`.

## Qué hacer

Cargá las skills `seguridad-roles`, `dominio-backend`, `modelo-datos`,
`panel-design-ui` y `verificacion`.

1. **Correo en la cuenta.** Migración: `sec_user.email` (string 150,
   nullable) + índice único parcial `WHERE deleted_at IS NULL` (skill
   `modelo-datos`; en SQLite el índice parcial también existe, verificá que
   la migración corra en los dos motores). `CrearUsuarioRequest` /
   `ActualizarUsuarioRequest` lo validan (`email`, único) y el formulario de
   usuarios lo muestra, con la sugerencia precargada según el punto
   anterior. Seeders demo: `camila.rojas` y `demo` reciben un email
   `@agrocom.example`; las cuentas de portal demo (tarea 65) reciben el
   email del contacto de su cliente.
2. **Perfil propio: `GET/PUT /panel/perfil`** (guard `interno`, cualquier rol
   activo, sin permiso especial: es el propio usuario) con nombre, email y
   cambio de contraseña (contraseña actual + nueva + confirmación; la
   actual se verifica con `Hash::check`; mínimo 8; al cambiarla se
   invalidan las otras sesiones con `Auth::logoutOtherDevices`). Lo mismo
   en `/portal/perfil` para el guard `cliente`. Ítem "Mi perfil" en el menú
   de usuario del topbar (panel y portal). La bitácora registra el cambio
   sin guardar hashes en `antes`/`despues` (verificá que el trait ya oculte
   `password`; si no, ocultalo).
3. **Recuperación por correo.**
   - Migración `password_reset_tokens` (email, token, created_at) estándar
     de Laravel — un solo broker por proveedor, dos brokers: `interno`
     (provider `usuarios_interno`) y `cliente` (`usuarios_cliente`), en
     `config/auth.php → passwords`, con expiración 60 min y throttle 60 s.
     Los modelos implementan `CanResetPassword`.
   - La pestaña "Recuperar acceso" pasa a ser un `<form method="POST">`
     con `@csrf` a `POST /recuperar` (panel) y `POST /portal/recuperar`
     (portal). Respuesta siempre igual ("si el correo existe, vas a recibir
     un enlace") para no revelar qué correos existen. Rate limit por IP y
     por email (`throttle`).
   - `Notification` propia en español (`RestablecerContrasena`), con el
     enlace a `GET /restablecer/{token}?email=` (y su par del portal), que
     muestra el formulario de nueva contraseña (también con `@csrf`) y
     `POST /restablecer` que valida token, cambia la contraseña, borra el
     token, cierra otras sesiones y redirige al login con mensaje.
   - Cuentas bloqueadas (`state = false`) o borradas no reciben correo ni
     pueden restablecer.
   - Textos en `lang/es/seguridad.php`; nada hardcodeado en la vista.
4. **Correo habilitado en el entorno local.** `.env.example`:
   `MAIL_MAILER=smtp`, `MAIL_HOST=mail`, `MAIL_PORT=1025`,
   `MAIL_FROM_ADDRESS=no-responder@agrocom.example`; `docker-compose.yml`
   pasa esas variables al servicio `app` si no las hereda ya del `.env`.
   Documentá en `docs/gestion/estado_proyecto.md` que los correos locales se
   leen en `http://localhost:8025`. En tests, `Notification::fake()`; en CI
   no se envía nada.

## Qué NO hacer

- No hagas el login por email: sigue siendo `username` + password. El email
  es solo para el reset y el contacto.
- No mandes el correo desde el controlador con `Mail::raw`: es una
  `Notification` con su Mailable/plantilla, para que el texto viva en un
  solo lugar y se pueda testear con `fake()`.
- No expongas en la respuesta si el email existe o no.
- No toques el mecanismo de sesión/rol activo ni el scoping del portal.
- No cambies lo que el ADR 0004 ya decidió: solo lo ampliás.

## Cómo repartir las etapas

- **Etapa 1**: migración `email` + índice parcial, validaciones, formulario
  de usuarios con precarga, seeders demo con email, tests.
- **Etapa 2**: perfil propio (panel y portal) con cambio de contraseña,
  ítem del topbar, tests (contraseña actual incorrecta → 422; cambio
  correcto → otras sesiones cerradas; bitácora sin hash).
- **Etapa 3**: brokers, tabla de tokens, formulario real de "Recuperar
  acceso" con CSRF, notificación, tests con `Notification::fake()` (se
  envía a un email existente; no se envía a uno inexistente y la respuesta
  es idéntica; bloqueado no recibe).
- **Etapa 4**: pantalla de restablecer, token vencido/inválido, cierre de
  otras sesiones, tests; lo mismo para el portal.
- **Etapa 5**: Mailpit en `.env.example`/compose, ampliación del ADR 0004,
  specs visuales (`login.spec.ts` con la pestaña "Recuperar acceso",
  `perfil.spec.ts`, `restablecer.spec.ts`, claro/oscuro), `bin/verify`.

## Criterio de aceptación

- `./bin/verify` = 0, con Playwright.
- Test: `POST /recuperar` sin token CSRF → 419; con CSRF y email existente
  → notificación enviada (fake) y 302 con el mismo mensaje que con un email
  inexistente.
- Test: el enlace del correo permite fijar una contraseña nueva, el token
  deja de servir después, y el login con la contraseña vieja falla.
- Test: `PUT /panel/perfil` con contraseña actual incorrecta → 422; correcta
  → login con la nueva funciona.
- Test: dos cuentas vivas no pueden compartir email (422); una borrada sí
  libera el email.
- Test (portal): `cliente.sanjorge` puede restablecer la suya; el flujo del
  portal no toca cuentas internas ni al revés (un token emitido para el
  guard `interno` no sirve en `/portal/restablecer`).
- `docker compose exec -T app php artisan tinker` no hace falta: con la
  base del compose, pedir reset para `camila.rojas` deja un correo visible
  en Mailpit (`http://localhost:8025`) — dejá ese paso escrito en
  `runs/66.md` como verificación manual, no lo automatices.

## Puede tocar

`app/Dominios/Seguridad/**`, `app/Dominios/Portal/**` (perfil y reset del
portal), `config/auth.php`, `config/mail.php` solo si hace falta,
`.env.example`, `docker-compose.yml` (variables de mail), migraciones
nuevas, `database/seeders/Demo/**`, `lang/es/**`, `resources/**`,
`routes/web.php`, `docs/decisiones/0004-modelo-seguridad-sec-multirol.md`
(solo ampliar), `docs/gestion/estado_proyecto.md`, `tests/**`.

## Cierre obligatorio de cada etapa

`runs/66.estado`, `runs/66.md`, y al `OK` `runs/66.pr.md`. Commits agrupados
por función, en español, imperativo, sin `Co-Authored-By`.

---
name: seguridad-roles
description: Modelo de seguridad sec_* de agrocom-api — un login por usuario, varios roles asignados, un único rol activo por sesión, permisos efectivos y menú dinámico. Usar antes de tocar autenticación, permisos, roles, menú del panel o cualquier pantalla que dependa de quién está mirando.
---

# Seguridad y roles — agrocom-api

Modelo `sec_*` del ADR 0004, más su extensión del 27/8/2026 (rol activo por
sesión). Este skill mapea lo que ya está implementado y las trampas conocidas.

## La regla que ordena todo

**Un usuario, un login, múltiples roles, un rol activo por sesión.**

Nunca se crean cuentas duplicadas por rol. Un usuario puede tener varios roles
vía `sec_user_role`, pero en cada sesión opera bajo **un único rol activo**, y los
permisos efectivos son los de ese rol — **jamás la unión de todos sus roles**.
Cambiar de rol activo no requiere volver a loguearse (invariante 10 de CLAUDE.md).

Corolario del negocio (invariante 4): validador ≠ piloto **a nivel de persona, no
de rol**. Un jefe de campo que además es piloto no valida sus propias sesiones.

## Autenticación

- Login por **`username` + password**, nunca por correo.
- Dos guards: `interno` (personal de Agrocom) y el de cliente —
  `SecUsuarioInterno` / `SecUsuarioCliente` son subtipos de `SecUser` que no
  rompen el contrato del padre.
- `App\Models\User` **no existe**: fue reemplazado íntegramente por `SecUser`.
- App de campo: token Sanctum por dispositivo, revocable desde el panel (HU-03,
  todavía pendiente).

## Dónde vive el rol activo

En la **sesión**, bajo la clave `sec_rol_activo_id`. Nunca en una columna de
`sec_user`: es estado de sesión, no atributo de la cuenta.

Un único escritor: `Aplicacion/ElegirRolActivo`. Lo llaman tres lugares (login con
rol único, cambio explícito de rol, y el middleware) y ninguno reimplementa la
validación. **Si necesitás fijar el rol activo, pasás por ahí.**

`Infraestructura/Http/Middleware/ResolverRolActivo` corre después de
`auth:interno` en cada request y revalida contra la base que el rol siga vivo
(`sec_user_role.deleted_at IS NULL` + `sec_role.state = true`). Es deliberado: una
revocación de rol a mitad de sesión tiene efecto en el request siguiente. Sin rol
activo resoluble no hay panel — redirige al selector, o responde 409 con los roles
disponibles si el request pide JSON.

## Menú y permisos

El menú se renderiza desde `sec_menu` según los permisos del **rol activo**
(`Aplicacion/ObtenerMenuPorRolActivo`), no desde una lista fija en Blade. Un botón
sin permiso no se muestra — y la pantalla detrás igual valida del lado del
servidor.

Permisos abstractos en `sec_permission`, ligados al rol por `sec_role_permission`.
El seeder `SeguridadSeeder` carga el catálogo base (5 roles, 6 permisos).

## Piezas ya implementadas

| Necesidad | Dónde |
|---|---|
| Login del panel | `Aplicacion/IniciarSesionPanel`, `Controllers/Web/SesionController` |
| Elegir / cambiar rol activo | `Aplicacion/ElegirRolActivo`, `Controllers/Web/RolActivoController` |
| Roles disponibles del usuario | `Aplicacion/ListarRolesDisponibles` |
| Menú por rol activo | `Aplicacion/ObtenerMenuPorRolActivo`, `SecMenu` |
| Asignar roles a un usuario | `Aplicacion/AsignarRolesUsuario` |
| Tema e idioma por usuario | `Aplicacion/ActualizarPreferenciaUsuario`, `SecUserPreferencia` |
| Ver como otro usuario (solo lectura) | `Aplicacion/IniciarVistaComo` / `TerminarVistaComo` / `ResolverVistaComo`, `Middleware/AplicarVistaComo`, `SecVistaComo` |
| Usuario demo multirol | `carlos.ferrufino` / `password` (seeder `Demo/PersonalDemoSeeder`) |

Excepciones de dominio disponibles: `RolNoAsignado`, `PermisoDenegado`,
`UsuarioDuplicado`, `IdiomaNoSoportado`.

## El portal del cliente

Todo endpoint de `/api/portal/*` consulta **desde el `contrato` del usuario
autenticado**, nunca desde la tabla global con un `where` agregado después
(invariante 5). Cada endpoint nuevo lleva su test: cliente A pidiendo un recurso
de cliente B → **404**, no 403.

Esto está en la lista de "no delegar sin revisión línea por línea" de CLAUDE.md.

## Ver como otro usuario (tarea 140)

El administrador de plataforma (`seguridad.usuario.ver_como`, solo `admin_plataforma`)
mira el panel o el portal tal como lo ve otra cuenta, **en solo lectura**. Detalle y
porqué: ADR 0004, extensión del 23/9/2026. Lo que hay que respetar al tocar seguridad:

- **No hay un login como el otro.** Una bandera de sesión (`vista_como`,
  `Dominio/VistaComoActiva`) más `AplicarVistaComo`, que pone la cuenta observada en el
  guard `interno` o `cliente` con `setUser()` y la devuelve en un `finally`. La sesión
  de autenticación del administrador no se toca; no existe un tercer guard.
- **El portal no cambia:** `AutorizacionPortalCliente::contratoId()` sigue leyendo
  `user('cliente')->contrato_id`. Nada de un `where` aparte para «el administrador
  mirando» (invariante 5).
- **Se rechaza, no se esconde:** con la bandera, todo método distinto de GET/HEAD/OPTIONS
  da 403 antes del controlador (salvo `POST /vista-como/salir`), y `ModoSoloLectura`
  impide que un modelo de dominio guarde, borre o restaure durante el request. Una ruta
  nueva con sesión queda cubierta sola por estar en el grupo `web`; una ruta que
  autentique `interno` o `cliente` **fuera** de ese grupo se saltaría la vista como.
- **El permiso se evalúa contra el rol activo** con el que el administrador entró
  (`tienePermisoEnRol`), nunca la unión de sus roles (invariante 10), y se revalida en
  cada request.
- **No se delega:** `ver_como` está en `Dominio/PermisosReservados`, y `AsignarPermisosRol`
  rechaza otorgarlo a todo rol que no sea `admin_plataforma` (guarda 5). Un permiso de
  plataforma nuevo se agrega a esa lista, no al seeder.
- **La bitácora sale del modelo:** `SecVistaComo` lleva `RegistraBitacora`; la salida
  corre con el guard `interno` siendo el administrador real para que el actor sea él.

## Antes de cerrar

Los tests de seguridad ya cubren login, rol activo, middleware, menú y permisos
(`tests/Feature/Seguridad/`). Un cambio acá sin test nuevo es un cambio
incompleto. Correr la cascada — ver [verificacion].

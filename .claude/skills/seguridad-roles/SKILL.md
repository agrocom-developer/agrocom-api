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
| Usuario demo multirol | `carlos.ferrufino` / `password` (seeder `Demo/PersonalDemoSeeder`) |

Excepciones de dominio disponibles: `RolNoAsignado`, `PermisoDenegado`,
`UsuarioDuplicado`, `IdiomaNoSoportado`.

## El portal del cliente

Todo endpoint de `/api/portal/*` consulta **desde el `contrato` del usuario
autenticado**, nunca desde la tabla global con un `where` agregado después
(invariante 5). Cada endpoint nuevo lleva su test: cliente A pidiendo un recurso
de cliente B → **404**, no 403.

Esto está en la lista de "no delegar sin revisión línea por línea" de CLAUDE.md.

## Antes de cerrar

Los tests de seguridad ya cubren login, rol activo, middleware, menú y permisos
(`tests/Feature/Seguridad/`). Un cambio acá sin test nuevo es un cambio
incompleto. Correr la cascada — ver [verificacion].

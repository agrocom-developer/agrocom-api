<!-- ciclo: critica=no turno-noche=1 rama=feature/dashboard-admin-plataforma etapas=2 -->

# Tarea 139 — dashboard del Administrador de plataforma (técnico, no operativo)

Requiere la 135 integrada. Leé `docs/gestion/plan_dashboard_notificaciones_por_rol.md`
§5. Cargá los skills `panel-design-ui`, `seguridad-roles` y `verificacion`.

## Contexto

El rol `admin_plataforma` es "técnico, no del negocio del cliente"
(`SeguridadSeeder::ROLES`, su propia descripción). El dueño no quiere que su
dashboard muestre nada operativo/financiero — quiere accesos y estado de lo
que administra el sistema en sí: usuarios, bitácora, configuración. Esta es
la más simple de las cuatro tareas de rol: casi todo mapea a permisos que ya
existen, no hay que cruzar módulos de negocio.

## Qué hacer

Agrupá el rol `admin_plataforma` en tabs (probablemente 2, a tu criterio):

1. **Usuarios y accesos**: conteo/resumen de usuarios activos por rol
   (`seguridad.usuario.ver`), dispositivos con sesión abierta
   (`seguridad.dispositivo.ver`), versiones de APK pendientes de autorizar
   (`distribucion.version.autorizar`).
2. **Bitácora y configuración**: últimas entradas de la bitácora de
   auditoría (`seguridad.bitacora.ver`) y accesos directos (links, no
   contenido embebido) a Configuración del sistema
   (`seguridad.configuracion.ver`) y Organización (`seguridad.organizacion.ver`).

Cada pieza nueva es un `SeccionDashboard::case` con su permiso — no dupliques
consultas que ya existen en las pantallas de Usuarios/Bitácora/Dispositivos,
llamalas desde ahí (son del mismo módulo `Seguridad`, no hace falta
Contrato).

## Qué NO hacer

No le muestres a este rol ninguna sección operativa/financiera existente
(`AvanceClientes`, `Stock`, etc.) aunque tenga el permiso por herencia de
otro rol — el dashboard de admin es solo lo técnico, aunque la cuenta tenga
más de un rol asignado (evaluás contra el rol activo `admin_plataforma`
únicamente, invariante 10).

## Criterio de aceptación

- `./bin/verify` = 0.
- Un usuario con rol activo `admin_plataforma` ve solo contenido técnico, sin
  ninguna sección operativa/financiera.
- Playwright (`runs/139-navegador.cjs`), claro y oscuro.

## Cierre obligatorio de cada etapa

`runs/139.estado`, `runs/139.md`, y al `OK` `runs/139.pr.md`. Commits por
función, en español, imperativo, sin `Co-Authored-By`.

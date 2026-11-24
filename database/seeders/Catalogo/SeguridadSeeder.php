<?php

namespace Database\Seeders\Catalogo;

use App\Dominios\Seguridad\Dominio\PermisosReservados;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecPermission;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRolePermission;
use Illuminate\Database\Seeder;

/**
 * Catálogo de roles y permisos (HU-01, diseño `modulos-roles` §2): corre en
 * todos los entornos, producción incluida — sin roles ni permisos no hay con
 * qué autorizar al primer usuario.
 *
 * Sin autor explícito (`created_by`/`updated_by` quedan NULL): a diferencia
 * del seeder Demo (que sí asigna un usuario autor porque simula un flujo de
 * negocio), esto es dato de catálogo — nadie "creó" el rol `dueno`, lo
 * sembró el sistema antes de que exista ningún usuario.
 *
 * Idempotente vía `firstOrCreate`: correrlo de nuevo no duplica filas ni
 * pisa `description`/`state` si ya fueron editados a mano.
 */
class SeguridadSeeder extends Seeder
{
    /** @var array<string, string> */
    private const ROLES = [
        'piloto' => 'Piloto de dron: ejecuta sesiones de vuelo en campo.',
        'auxiliar' => 'Auxiliar de campo: apoya la preparación y logística de la sesión.',
        'jefe_campo' => 'Jefe de campo: coordina la cuadrilla y valida sesiones ajenas.',
        'encargado_operaciones' => 'Encargado de operaciones: administra usuarios, órdenes y planificación.',
        'dueno' => 'Dueño de Agrocom SRL: acceso total, incluida la gestión de otros dueños.',
        // Marketplace (ADR 0036): cuentas que se registran por sí mismas desde
        // la app. No tienen ninguna pantalla del panel; operan por la API.
        'cliente' => 'Cliente del marketplace: solicita servicios de fumigación y califica al proveedor.',
        'proveedor' => 'Proveedor del marketplace: gestiona su personal, sus drones y sus equipos desde la app.',
        // Descripción recortada a propósito: `sec_role.description` es
        // `varchar(150)` (migración `2026_08_27_100003`) y el texto original
        // del pedido (168 caracteres) no entra — SQLite no lo detecta (sin
        // límite de longitud), Postgres sí lo rechazó en la corrida real
        // contra el compose (`value too long for type character
        // varying(150)`). Se preserva el sentido: acceso total incluida la
        // gestión de dueños, y que es un rol técnico, no del negocio.
        'admin_plataforma' => 'Administrador de la plataforma Agrocom: acceso total, incluida la gestión de dueños — rol técnico, no del negocio del cliente.',
    ];

    /** @var array<string, string> */
    private const PERMISOS = [
        'seguridad.usuario.ver' => 'Ver listado/detalle de usuarios',
        'seguridad.usuario.crear' => 'Crear usuario y asignarle roles (excepto dueño)',
        'seguridad.usuario.editar' => 'Editar datos y reasignar roles de un usuario (excepto dueño)',
        'seguridad.usuario.bloquear' => 'Bloquear/desbloquear (toggle de state, no es baja)',
        'seguridad.usuario.eliminar' => 'Baja lógica (soft delete)',
        'seguridad.usuario.asignar_rol_dueno' => 'Asignar o quitar el rol dueño a cualquier usuario',
        // Tarea 65 (HU-41): crear/editar cuentas de portal es más sensible
        // que administrar cuentas internas (define qué contrato ve un
        // cliente) — exige este permiso ADEMÁS de crear/editar, restringido
        // a dueño y encargado_operaciones (ver PERMISOS_ENCARGADO_OPERACIONES
        // más abajo; dueño lo recibe con el resto del catálogo).
        // Tarea 140: mirar el panel o el portal COMO otro usuario, en solo
        // lectura. Permiso propio y a propósito fuera de `dueno` (ver
        // PERMISOS_SOLO_ADMIN_PLATAFORMA más abajo): es una herramienta de
        // soporte de la plataforma, no del negocio del cliente.
        'seguridad.usuario.ver_como' => 'Ver el panel o el portal como otro usuario (solo lectura)',
        // Administración del catálogo de roles y de la matriz rol↔permiso.
        // Era lo último del modelo `sec_*` sin pantalla: roles, permisos y
        // sus asignaciones solo se tocaban editando este archivo. Los cinco
        // van SOLO a `dueno` (ver más abajo): quien puede editar la matriz
        // puede concederse cualquier permiso del sistema, así que no es una
        // responsabilidad delegable al encargado de operaciones — que sí
        // tiene el alta de usuarios. `asignar_permiso` es la LLAVE: las
        // guardas de AsignarPermisosRol impiden que el sistema se quede sin
        // ningún rol vivo que la tenga.
        'seguridad.rol.ver' => 'Ver el catálogo de roles y qué permisos tiene cada uno',
        'seguridad.rol.crear' => 'Crear un rol nuevo (nace sin permisos)',
        'seguridad.rol.editar' => 'Editar nombre, descripción y estado de un rol',
        'seguridad.rol.eliminar' => 'Dar de baja un rol sin usuarios asignados',
        'seguridad.rol.asignar_permiso' => 'Otorgar y quitar permisos a un rol',
        // Tarea 62 (fuga 2): dashboard y organización eran visibles para
        // cualquier rol activo sin ningún permiso que lo gatee — un
        // `auxiliar` (con un único permiso de panel) veía el tablero completo
        // y la ficha de la compañía. `dueno` los recibe igual que todo el catálogo (sin
        // excepción, diseño §2); `encargado_operaciones` y `jefe_campo` los
        // reciben explícitos abajo.
        //
        // `seguridad.dashboard.ver` volvió a piloto/auxiliar en la tarea 67,
        // y no contradice aquella corrección: lo que la fuga 2 castigaba era
        // que vieran el TABLERO COMPLETO sin permiso. Desde la 67 el
        // dashboard se compone por sección y cada una exige el permiso de su
        // propia pantalla, así que este permiso ya no abre la operación
        // entera — solo la puerta. `seguridad.organizacion.ver` sigue afuera.
        'seguridad.dashboard.ver' => 'Ver el tablero "Operación de hoy" (dashboard)',
        'seguridad.organizacion.ver' => 'Ver el registro de la compañía (Organización)',
        // Tarea 78 (HU-55): guardar la pestaña Facturación (datos fiscales:
        // razón social, NIT, domicilio, actividad económica, leyenda al pie).
        // Separado de `.ver` — mismo criterio grano fino que el resto del
        // catálogo — porque administrar los datos con que se factura es una
        // responsabilidad distinta de solo ver la ficha de la compañía.
        'seguridad.organizacion.editar' => 'Editar los datos fiscales de la empresa (pestaña Facturación)',
        // Tarea 78 (HU-55): pantalla `/panel/configuracion` — llaves y tokens
        // de infraestructura (mapas, correo, integraciones). Exclusivo del
        // dueño (pedido explícito): a diferencia de `seguridad.organizacion.*`,
        // que el encargado también administra, estos son secretos con los que
        // el sistema funciona, no datos operativos del día a día. Ninguna otra
        // lista PERMISOS_* de abajo los referencia, así que solo `dueno` los
        // recibe (asignación "todos los permisos, sin excepción" de más abajo).
        'seguridad.configuracion.ver' => 'Ver la configuración del sistema (llaves y tokens)',
        'seguridad.configuracion.editar' => 'Editar la configuración del sistema (llaves y tokens)',
        // Tarea 63 (invariante 9 de CLAUDE.md): pantalla de bitácora de
        // auditoría — quién hizo qué, cuándo y en qué zona horaria. Solo
        // `dueno` y `encargado_operaciones` (mismo criterio que
        // `seguridad.usuario.*`: administran la operación diaria); ningún
        // otro rol lo recibe. Sin `.editar`: es de solo lectura por
        // definición (ADR 0007) — no hay una acción de mutación que gatear.
        'seguridad.bitacora.ver' => 'Ver la bitácora de auditoría (quién hizo qué, cuándo y en qué zona horaria)',
        // HU-03: ver y revocar sesiones de la app de campo. Separados a
        // propósito — mirar quién tiene sesión abierta y dejar a alguien
        // afuera en medio de una jornada de vuelo no son la misma
        // responsabilidad.
        'seguridad.dispositivo.ver' => 'Ver los dispositivos con sesión abierta en la app de campo',
        'seguridad.dispositivo.revocar' => 'Revocar el acceso de un dispositivo de campo',
        // HU-20: autorizar una versión del APK para distribución (RC). Solo
        // el dueño — ningún RC se actualiza sin su visto bueno.
        'distribucion.version.autorizar' => 'Autorizar una versión del APK para distribución',
        // HU-05 (tarea 13): ver el listado de trabajos/sesiones del panel —
        // "hasta que el jefe lo vea en el panel" (prompt de la tarea). Un
        // único permiso de lectura: el detalle con evidencias y los filtros
        // llegan con HU-15.
        'operaciones.trabajo.ver' => 'Ver el listado de trabajos y sesiones en el panel',
        // HU-93 (tarea 108; ampliado por la reforma "Orden de Trabajo" del
        // 18/9/2026): crear una tanda (equipos + parámetros compartidos +
        // lotes por equipo) sobre una orden vigente, y editar/eliminar un
        // trabajo puntual mientras no esté `validado`. Grano fino, mismo
        // criterio que `operaciones.orden.crear/.editar/.eliminar`.
        'operaciones.trabajo.eliminar' => 'Dar de baja (lógica) un trabajo mientras no esté validado',
        // HU-14 (tarea 14): cola de validación — aprobar o rechazar una
        // sesión cerrada. Un único permiso gatea listar y decidir (mismo
        // criterio que `distribucion.version.autorizar`): la policy
        // validador≠piloto (invariante 4) rige la fila puntual, no la
        // visibilidad de la pantalla.
        'operaciones.sesion.validar' => 'Validar o rechazar sesiones cerradas desde el panel',
        // HU-17 (tarea 24): acta de conformidad por lote. La espec (§3) le da
        // "Generar y presentar acta" a piloto/jefe de campo y "Firmar acta"
        // al agrónomo — pero el agrónomo no tiene cuenta en `sec_*` (no hay
        // rol/portal para él todavía, ver runs/24.md): quien ejecuta el
        // registro de la firma en `agrocom-field` es el piloto o el jefe, en
        // presencia del agrónomo, así que ambos permisos se asignan a los
        // mismos dos roles — el FIRMANTE real queda como dato
        // (`ope_actas.firmante`), no como actor `sec_user`.
        'operaciones.acta.generar' => 'Generar el acta de conformidad de un trabajo cerrado y validado',
        'operaciones.acta.firmar' => 'Registrar la firma del agrónomo sobre un acta pendiente',
        // HU-18 (tarea 25): reporte técnico por lote. Espec §3, línea 89
        // ("Ver reportes técnicos"): jefe de campo, encargado y dueño desde
        // el panel interno; el agrónomo también figura en esa fila, pero
        // solo "desde el portal" — que todavía no existe (Sprint 12, ver
        // runs/25.md) — así que ningún rol de `sec_*` lo representa hoy.
        // Piloto/auxiliar quedan afuera: no están en esa fila de la espec.
        'operaciones.reporte.ver' => 'Ver y descargar el reporte técnico de un trabajo',
        // HU-19 (tarea 26): bandeja de alertas por excepción. Separados a
        // propósito, mismo criterio que dispositivo.ver/.revocar: mirar la
        // bandeja y marcar una alerta como resuelta no son la misma
        // responsabilidad.
        'operaciones.alerta.ver' => 'Ver la bandeja de alertas por excepción',
        'operaciones.alerta.atender' => 'Marcar una alerta por excepción como atendida',
        // HU-22 (tarea 33): alta y mantenimiento de clientes con sus
        // contactos. Grano fino (ver/crear/editar/eliminar separados, mismo
        // criterio que seguridad.usuario.*): permite que un rol futuro de
        // solo lectura exista sin tocar este catálogo.
        // ADR 0018: propiedades del cliente — nivel de terreno entre
        // `Cliente` y `Campo` (una propiedad puede tener más de un campo
        // físico delimitado, caso "Gamelera"). Grano fino, mismo criterio
        // que `comercial.cliente.*`.
        // HU-23 (tarea 34): administración de contratos con sus ventanas de
        // aplicación. `cambiar_estado` separado de `.editar`, mismo criterio
        // que `usuario.bloquear` separado de `usuario.editar`: pasar un
        // contrato de `vigente` a `cancelado` no es la misma responsabilidad
        // que corregir un dato.
        // ADR 0020 (15/9/2026): `Campo` se elimina como entidad — `Lote`
        // cuelga directo de `Propiedad`. Los cuatro permisos
        // `comercial.campo.*` que administraban "campos con sus lotes"
        // (HU-24, tarea 35) quedan huérfanos: ningún controlador los
        // verifica ya (`CamposController` no existe). Se retiran del
        // catálogo — ver `retirarPermiso()`, llamado desde `run()`, que
        // además soft-deletea las filas ya sembradas en una base existente
        // (sacar la línea de acá solo evita que una instalación NUEVA vuelva
        // a crearlas).
        // HU-54 (tarea 77): pantalla propia de lotes — listado con filtro
        // por cliente/propiedad y ficha con alta, edición y baja lógica de
        // un lote suelto. Grano fino, mismo criterio que `comercial.cliente.*`;
        // el alta de una propiedad con sus lotes en la misma transacción
        // sigue siendo un único caso de uso, llamado desde cualquiera de los
        // dos formularios.
        // HU-48 (tarea 71, ADR 0015 punto 4): catálogo de cultivos — el
        // cultivo no es columna de `com_lotes`, es un catálogo simple que se
        // vincula a un lote solo dentro de una campaña (etapa 2 de esta
        // tarea). Grano fino, mismo criterio que `comercial.campo.*`/
        // `comercial.lote.*`.
        // HU-27 (tarea 36): administración de la flota de drones con su
        // modelo y capacidad de carga. Grano fino, mismo criterio que
        // `comercial.campo.*`.
        'operaciones.dron.ver' => 'Ver el listado de drones',
        'operaciones.dron.crear' => 'Dar de alta un dron',
        'operaciones.dron.editar' => 'Editar los datos de un dron',
        'operaciones.dron.eliminar' => 'Dar de baja (lógica) un dron',
        // HU-25 (tarea 38): órdenes de aplicación con su propia máquina de
        // estados. `.activar` separado de `.editar`, mismo criterio que
        // `comercial.contrato.cambiar_estado` separado de `.editar`: pasar
        // una orden de `emitida` a `vigente` no es la misma responsabilidad
        // que corregir un dato.
        // ADR 0022 (19/9/2026): la aplicación se pausa, se cierra o se cancela
        // desde el panel — nunca desde la app de campo. Grano propio por
        // acción, mismo criterio que `.activar` arriba: son decisiones de
        // ejecución (del operador, junto con el dueño), no correcciones de un
        // dato.
        // HU-70 (tarea 85): "dónde asignarle el trabajo al piloto" — repartir
        // las hectáreas de una orden vigente entre equipos de trabajo. Grano
        // propio, no parte de `.editar`: no corrige la orden, reparte su
        // trabajo; una ficha propia (`panel.reparto-cuadrillas.*`), no la
        // ficha de la orden, así que no depende de `operaciones.orden.ver`.
        // HU-44 (tarea 58): pausas de sesión con causa atribuible (DS-01).
        // Grano fino (ver/registrar), mismo criterio que `operaciones.alerta.*`:
        // ver el tablero agregado y cargar una pausa no son la misma
        // responsabilidad.
        'operaciones.pausa.ver' => 'Ver el listado de pausas y su agregado por causa',
        'operaciones.pausa.registrar' => 'Registrar una pausa de sesión con su causa',
        // HU-51 (tarea 74): entrada y salida del equipo en cada hacienda.
        // Reforma 19/9/2026: la oficina también registra, edita, finaliza y
        // da de baja estadías desde el panel (antes el panel solo las leía,
        // cargadas desde la app de campo). `.editar` cubre también finalizar
        // (pasar a "finalizada" con su fecha de salida) — mismo criterio que
        // `personal.equipo_trabajo.editar`, que cubre asignar/finalizar
        // integrantes: no es un permiso aparte, es parte de mantener el dato.
        // HU-26 (tarea 37): administración de personas y bases, con su rol
        // operativo y tarifa. Dos recursos, cada uno con su grano fino
        // (ver/crear/editar/eliminar) — mismo criterio que
        // `comercial.campo.*`/`operaciones.dron.*`.
        'personal.base.ver' => 'Ver el listado de bases',
        'personal.base.crear' => 'Dar de alta una base',
        'personal.base.editar' => 'Editar los datos de una base',
        'personal.base.eliminar' => 'Dar de baja (lógica) una base',
        'personal.persona.ver' => 'Ver el listado de personas',
        'personal.persona.crear' => 'Dar de alta una persona operativa',
        'personal.persona.editar' => 'Editar los datos de una persona operativa',
        'personal.persona.eliminar' => 'Dar de baja (lógica) una persona operativa',
        // HU-58 (tarea 81): "¿qué hizo esta persona esta campaña?" — hechos
        // verificables (sesiones, rechazos, incidencias), nunca un puntaje.
        // Información sensible sobre una persona: permiso propio, separado
        // de `.ver` (que solo lista datos de alta, no desempeño operativo).
        'personal.persona.desempenio' => 'Ver la ficha de desempeño de una persona (sesiones, rechazos e incidencias)',
        // Tarea 72 (HU-49, ADR 0015 punto 3): equipos de trabajo — el piloto
        // y su auxiliar, con el equipamiento asignado. `.editar` cubre
        // también asignar/finalizar integrantes y recursos desde la ficha:
        // no es un permiso aparte, es parte de mantener el equipo.
        'personal.equipo_trabajo.ver' => 'Ver el listado y la ficha de equipos de trabajo',
        'personal.equipo_trabajo.crear' => 'Dar de alta un equipo de trabajo',
        'personal.equipo_trabajo.editar' => 'Editar un equipo de trabajo y asignar o finalizar sus integrantes y recursos',
        'personal.equipo_trabajo.eliminar' => 'Dar de baja (lógica) un equipo de trabajo',
        // HU-31 (tarea 45): "como encargado, quiero emitir la factura de un
        // trabajo desde su acta conformada, para cobrar sobre hectáreas ya
        // firmadas" — abre Sprint 9. Grano fino sin `.editar` ni
        // `.eliminar`: una factura, una vez emitida, es un snapshot
        // inmutable (ver `Comercial/Aplicacion/EmitirFactura`).
        // HU-32 (tarea 46): "como dueño, quiero un reporte comercial de
        // avance por cliente, contrato y campaña" — la HU lo dice literal.
        // NO entra en PERMISOS_ENCARGADO_OPERACIONES (ver más abajo):
        // exclusivo del dueño.
        // HU-39 (tarea 51): "como encargado, quiero seguir las baterías con
        // sus ciclos y estado, para retirarlas antes de que fallen en
        // vuelo". Grano fino, mismo criterio que `operaciones.dron.*`.
        'mantenimiento.bateria.ver' => 'Ver el listado de baterías',
        'mantenimiento.bateria.crear' => 'Dar de alta una batería',
        'mantenimiento.bateria.editar' => 'Editar los datos de una batería, incluidos sus ciclos acumulados',
        'mantenimiento.bateria.eliminar' => 'Dar de baja (lógica) una batería',
        // HU-82 (tarea 97): "como encargado, quiero llevar el activo completo
        // del dron (serie, chasis, versión de software, región, serie del
        // control, accesorios), para tener el inventario completo". ABM
        // nuevo sin máquina de estados. Grano fino, mismo criterio que
        // `mantenimiento.bateria.*`.
        'mantenimiento.ficha_dron.ver' => 'Ver el listado de fichas de inventario de dron',
        'mantenimiento.ficha_dron.crear' => 'Dar de alta una ficha de inventario de dron',
        'mantenimiento.ficha_dron.editar' => 'Editar los datos de una ficha de inventario de dron',
        'mantenimiento.ficha_dron.eliminar' => 'Dar de baja (lógica) una ficha de inventario de dron',
        // HU-46 (tarea 69, ADR 0015 punto 1): la campaña como eje transversal
        // del sistema. Grano fino, mismo criterio que `comercial.contrato.*`
        // — `.cambiar_estado` y `.eliminar` separados de `.editar`. A
        // diferencia de `comercial.contrato.cambiar_estado` (compartido con
        // `encargado_operaciones`), `.cambiar_estado` NO entra en
        // PERMISOS_ENCARGADO_OPERACIONES (ver más abajo): "solo el dueño
        // cierra una campaña" (pedido explícito del 7/9/2026) — con una
        // única apertura/cierre por campaña al año no hay costo operativo en
        // concentrarla en el dueño, a diferencia de `.ver`/`.crear`/`.editar`,
        // que sí comparte con el encargado (arma la campaña, el dueño decide
        // cuándo abrirla y cerrarla). `.eliminar` es soft delete sin guarda
        // de "tiene contratos asociados", mismo criterio que `comercial.cultivo.eliminar`.
        // Marketplace (ADR 0032 y 0036): aprobación de proveedores, pilotos,
        // auxiliares y drones en el panel. Grano por acción, mismo criterio
        // que `comercial.contrato.cambiar_estado`: aprobar (o rechazar) un
        // registro, suspenderlo y darlo de baja no son la misma
        // responsabilidad. `.aprobar` cubre aprobar, rechazar y rehabilitar.
        'proveedores.proveedor.ver' => 'Ver el listado y la ficha de proveedores',
        'proveedores.proveedor.aprobar' => 'Aprobar o rechazar a un proveedor y rehabilitar a uno suspendido',
        'proveedores.proveedor.suspender' => 'Suspender a un proveedor, con motivo',
        'proveedores.proveedor.dar_baja' => 'Dar de baja a un proveedor, con motivo',
        'proveedores.piloto.ver' => 'Ver el listado y la ficha de pilotos',
        'proveedores.piloto.aprobar' => 'Aprobar o rechazar a un piloto y rehabilitar a uno suspendido',
        'proveedores.piloto.suspender' => 'Suspender a un piloto, con motivo',
        'proveedores.piloto.dar_baja' => 'Dar de baja a un piloto, con motivo',
        'proveedores.auxiliar.ver' => 'Ver el listado y la ficha de auxiliares',
        'proveedores.auxiliar.aprobar' => 'Aprobar o rechazar a un auxiliar y rehabilitar a uno suspendido',
        'proveedores.auxiliar.suspender' => 'Suspender a un auxiliar, con motivo',
        'proveedores.auxiliar.dar_baja' => 'Dar de baja a un auxiliar, con motivo',
        'proveedores.documento.ver' => 'Ver la cola de documentos y descargar su archivo (acceso restringido)',
        'proveedores.documento.revisar' => 'Aprobar o rechazar un documento, con motivo en el rechazo',
        'flota.dron.ver' => 'Ver el listado y la ficha de drones del marketplace',
        'flota.dron.aprobar' => 'Aprobar o rechazar un dron y rehabilitar a uno suspendido',
        'flota.dron.suspender' => 'Suspender un dron, con motivo',
        'flota.dron.dar_baja' => 'Dar de baja un dron, con motivo',
        'flota.equipo.ver' => 'Ver los equipos (piloto, dron y auxiliar) de los proveedores',
        // Marketplace, lado app (token de dispositivo): lo que cada rol hace
        // sobre lo SUYO. El alcance (solo sus registros) lo hacen los casos de
        // uso; el permiso es la puerta.
        'proveedores.cuenta.gestionar' => 'Gestionar su propio proveedor desde la app: pilotos, drones, equipos y documentos',
        'proveedores.auxiliar.registrar' => 'Registrar a un auxiliar (queda pendiente de aprobación)',
        'proveedores.documento.cargar' => 'Cargar los documentos propios o de lo que uno registró',
        'solicitudes.cliente.gestionar' => 'Gestionar su propio perfil de cliente desde la app',
        'solicitudes.terreno.gestionar' => 'Registrar y consultar sus propios terrenos desde la app',
        'solicitudes.solicitud.gestionar' => 'Crear, consultar, publicar y cancelar sus propias solicitudes de servicio desde la app',
        // Marketplace, panel (ADR 0027): consulta de todas las solicitudes y
        // cancelación por soporte, con motivo.
        'solicitudes.solicitud.ver' => 'Ver el listado y la ficha de las solicitudes de servicio',
        'solicitudes.solicitud.cancelar' => 'Cancelar una solicitud de servicio desde el panel, con motivo',
        // Negociación oferta-demanda (`negociacion-contrato.md`), lado app: el proveedor ve el mapa de
        // solicitudes abiertas, ofrece su precio y registra el seguimiento; el cliente compara y elige;
        // ambos usan el chat y eligen sus avisos. El alcance lo hacen los casos de uso (un id ajeno es 404).
        'negociacion.mercado.ver' => 'Ver el mapa de solicitudes abiertas y su detalle antes de ofertar desde la app',
        'negociacion.oferta.proveedor' => 'Ofertar, contraofertar, aceptar, rechazar y retirar ofertas, y cancelar un servicio adjudicado, desde la app del proveedor',
        'negociacion.oferta.cliente' => 'Comparar las ofertas de sus solicitudes, contraofertar, aceptar y rechazar, y ver dónde está el piloto, desde la app del cliente',
        'negociacion.seguimiento.registrar' => 'Marcar el inicio, el avance y el cierre de un servicio adjudicado y enviar la ubicación del piloto desde la app',
        'negociacion.seguimiento.ver' => 'Ver el avance y las fotos de un servicio propio desde la app',
        'negociacion.evidencia.subir' => 'Subir fotos de avance, finales y de incidencia de un servicio adjudicado desde la app',
        'negociacion.mensaje.usar' => 'Leer y escribir mensajes con la otra parte de un servicio adjudicado desde la app',
        'negociacion.preferencias.gestionar' => 'Activar o desactivar los avisos de la cuenta, y las zonas de las nuevas solicitudes, desde la app',
        // Calificaciones (ADR 0031 y 0039), lado app: el cliente califica al proveedor,
        // al piloto y al auxiliar; el proveedor califica al cliente en privado, ve lo
        // que recibe y reporta lo abusivo. El alcance lo hacen los casos de uso.
        'calificaciones.calificacion.calificar_proveedor' => 'Calificar al proveedor, al piloto y al auxiliar de un servicio completado desde la app',
        'calificaciones.calificacion.calificar_cliente' => 'Calificar en privado al cliente de un servicio completado desde la app',
        'calificaciones.calificacion.ver_recibidas' => 'Ver las calificaciones que recibe su proveedor desde la app',
        'calificaciones.calificacion.reportar' => 'Reportar desde la app una calificación recibida que considera abusiva',
        // Calificaciones, panel: la cola de moderación y la moderación con causa y motivo.
        'calificaciones.calificacion.ver' => 'Ver las calificaciones y su cola de moderación',
        'calificaciones.calificacion.moderar' => 'Ocultar o restablecer una calificación, con causa y motivo',
        // Disputas (ADR 0033 y 0039), lado app: cada parte abre, argumenta, retira y apela
        // las suyas. El alcance (solo la disputa de su solicitud) lo hacen los casos de uso.
        'disputas.disputa.gestionar_cliente' => 'Abrir, argumentar, retirar y apelar una disputa de su servicio desde la app del cliente',
        'disputas.disputa.gestionar_proveedor' => 'Abrir, argumentar, retirar y apelar una disputa de su proveedor desde la app',
        // Disputas, panel: la cola, la mediación de soporte y la resolución.
        'disputas.disputa.ver' => 'Ver las disputas, sus mensajes y su resolución',
        'disputas.disputa.atender' => 'Tomar una disputa, pedir información a las partes y mensajear como soporte',
        'disputas.disputa.resolver' => 'Resolver una disputa o su apelación, con resultado, reembolso y motivo',
        // Métricas del mercado en el panel (especificación §14).
        'metricas.panel.ver' => 'Ver las métricas del mercado: solicitudes, aceptación, calificación, tiempos de asignación e ingreso',
    ];

    /**
     * Permisos que el catálogo entrega ÚNICAMENTE a `admin_plataforma`, no a
     * `dueno` (que por lo demás recibe "todo el catálogo, sin excepción"):
     * capacidades de soporte técnico de la plataforma (tarea 140). Tampoco se
     * pueden otorgar a otro rol desde la matriz de permisos: la lista es la de
     * `PermisosReservados`, que `AsignarPermisosRol` hace cumplir.
     *
     * @var list<string>
     */
    private const PERMISOS_SOLO_ADMIN_PLATAFORMA = PermisosReservados::SOLO_ADMIN_PLATAFORMA;

    /**
     * Administración de proveedores y flota en el panel (ADR 0032): el
     * encargado de operaciones revisa documentos y aprueba. El dueño recibe
     * todo el catálogo.
     *
     * @var list<string>
     */
    private const PERMISOS_ADMINISTRACION_PROVEEDORES = [
        'proveedores.proveedor.ver',
        'proveedores.proveedor.aprobar',
        'proveedores.proveedor.suspender',
        'proveedores.proveedor.dar_baja',
        'proveedores.piloto.ver',
        'proveedores.piloto.aprobar',
        'proveedores.piloto.suspender',
        'proveedores.piloto.dar_baja',
        'proveedores.auxiliar.ver',
        'proveedores.auxiliar.aprobar',
        'proveedores.auxiliar.suspender',
        'proveedores.auxiliar.dar_baja',
        'proveedores.documento.ver',
        'proveedores.documento.revisar',
        'flota.dron.ver',
        'flota.dron.aprobar',
        'flota.dron.suspender',
        'flota.dron.dar_baja',
        'flota.equipo.ver',
    ];

    /** @var list<string> */
    private const PERMISOS_PROVEEDOR = [
        'proveedores.cuenta.gestionar',
        'proveedores.auxiliar.registrar',
        'proveedores.documento.cargar',
        'negociacion.mercado.ver',
        'negociacion.oferta.proveedor',
        'negociacion.seguimiento.registrar',
        'negociacion.seguimiento.ver',
        'negociacion.evidencia.subir',
        'negociacion.mensaje.usar',
        'negociacion.preferencias.gestionar',
        'calificaciones.calificacion.calificar_cliente',
        'calificaciones.calificacion.ver_recibidas',
        'calificaciones.calificacion.reportar',
        'disputas.disputa.gestionar_proveedor',
    ];

    /** @var list<string> */
    private const PERMISOS_CLIENTE = [
        'solicitudes.cliente.gestionar',
        'solicitudes.terreno.gestionar',
        'solicitudes.solicitud.gestionar',
        'negociacion.oferta.cliente',
        'negociacion.seguimiento.ver',
        'negociacion.mensaje.usar',
        'negociacion.preferencias.gestionar',
        'calificaciones.calificacion.calificar_proveedor',
        'disputas.disputa.gestionar_cliente',
    ];

    /**
     * Solicitudes de servicio en el panel (ADR 0027): el encargado de
     * operaciones las consulta y cancela por soporte. El dueño recibe todo el
     * catálogo.
     *
     * @var list<string>
     */
    private const PERMISOS_ADMINISTRACION_SOLICITUDES = [
        'solicitudes.solicitud.ver',
        'solicitudes.solicitud.cancelar',
    ];

    /**
     * Calificaciones, disputas y métricas en el panel (ADR 0031, 0033 y 0039): el
     * encargado de operaciones modera, media y resuelve, y mira el mercado. El dueño
     * recibe todo el catálogo.
     *
     * @var list<string>
     */
    private const PERMISOS_ADMINISTRACION_POSTVENTA = [
        'calificaciones.calificacion.ver',
        'calificaciones.calificacion.moderar',
        'disputas.disputa.ver',
        'disputas.disputa.atender',
        'disputas.disputa.resolver',
        'metricas.panel.ver',
    ];

    /**
     * Piloto: lo que ejecuta desde `agrocom-field` (HU-17, tarea 24) más su
     * tablero.
     *
     * @var list<string>
     */
    private const PERMISOS_PILOTO = [
        // Marketplace: el piloto registra a su auxiliar y carga sus documentos.
        'proveedores.auxiliar.registrar',
        'proveedores.documento.cargar',
        // Negociación: el piloto de un proveedor ofrece, ejecuta y sube las fotos de su proveedor.
        'negociacion.mercado.ver',
        'negociacion.oferta.proveedor',
        'negociacion.seguimiento.registrar',
        'negociacion.seguimiento.ver',
        'negociacion.evidencia.subir',
        'negociacion.mensaje.usar',
        'negociacion.preferencias.gestionar',
        // Calificaciones y disputas (ADR 0031 y 0033): el piloto habla por su proveedor.
        'calificaciones.calificacion.calificar_cliente',
        'calificaciones.calificacion.ver_recibidas',
        'calificaciones.calificacion.reportar',
        'disputas.disputa.gestionar_proveedor',
        'operaciones.acta.generar',
        'operaciones.acta.firmar',
        // Tarea 67: el dashboard dejó de ser un tablero único de gerencia y
        // pasó a componerse por rol. Para el piloto son sus sesiones y sus
        // equipos, acotados a su `persona_id`, no a la operación entera. Sin este permiso aterrizaba
        // en la primera pantalla suelta que el menú le dejara ver.
        'seguridad.dashboard.ver',
    ];

    /**
     * Auxiliar: solo su tablero.
     *
     * @var list<string>
     */
    private const PERMISOS_AUXILIAR = [
        // Marketplace: el auxiliar carga sus propios documentos y sube fotos del servicio de su equipo.
        'proveedores.documento.cargar',
        'negociacion.evidencia.subir',
        'negociacion.seguimiento.ver',
        // Tarea 67, mismo motivo que el piloto: ve sus propias sesiones (como
        // auxiliar de ellas), nada de la operación global.
        'seguridad.dashboard.ver',
    ];

    /** @var list<string> Todo, salvo asignar_rol_dueno (diseño §2). */
    private const PERMISOS_ENCARGADO_OPERACIONES = [
        'seguridad.usuario.ver',
        'seguridad.usuario.crear',
        'seguridad.usuario.editar',
        'seguridad.usuario.bloquear',
        'seguridad.usuario.eliminar',
        // Tarea 65 (HU-41): el encargado de operaciones es quien prueba y
        // mantiene las cuentas de portal en el día a día.
        // Tarea 62 (fuga 2): administra la operación diaria, aterriza en el
        // dashboard tras elegir rol y necesita la ficha de la compañía.
        'seguridad.dashboard.ver',
        'seguridad.organizacion.ver',
        // Tarea 78 (HU-55): administra también los datos fiscales con que se
        // factura — mismo criterio que el resto de este rol (ve Y administra
        // la operación diaria, a diferencia de jefe_campo que solo ve).
        'seguridad.organizacion.editar',
        // Tarea 63: administra la operación diaria, así que también puede
        // auditar quién hizo qué — mismo criterio que `seguridad.usuario.*`.
        'seguridad.bitacora.ver',
        // Es quien administra la operación diaria: si un piloto pierde el
        // teléfono en campo, tiene que poder cortarle el acceso sin
        // escalar al dueño (HU-03).
        'seguridad.dispositivo.ver',
        'seguridad.dispositivo.revocar',
        // Administra órdenes y planificación (diseño §2): ve qué trabajos y
        // sesiones se cerraron en el panel, igual que el jefe de campo.
        'operaciones.trabajo.ver',
        // HU-93 (tarea 108; reforma "Orden de Trabajo" 18/9/2026): administra
        // también el alta de tandas y la corrección de un trabajo cargado
        // mal — mismo criterio que `operaciones.orden.crear/.editar/.eliminar`.
        'operaciones.trabajo.eliminar',
        // HU-14: administra la operación diaria, así que también puede
        // destrabar la cola de validación — mismo criterio que trabajo.ver.
        'operaciones.sesion.validar',
        // HU-19 (tarea 26): "Como encargado, quiero recibir solo alertas por
        // excepción" — la bandeja es suya. jefe_campo no la recibe: la
        // espec no le asigna esta responsabilidad (a diferencia de
        // trabajo.ver/sesion.validar, que sí comparte).
        'operaciones.alerta.ver',
        'operaciones.alerta.atender',
        // HU-18 (tarea 25): espec línea 89, "Ver reportes técnicos".
        'operaciones.reporte.ver',
        // HU-22 (tarea 33): "Como encargado, quiero dar de alta y mantener
        // clientes" — la HU lo dice literal, así que el rol encargado se
        // lleva el grano completo.
        // ADR 0018: administra también las propiedades del cliente — mismo
        // criterio que clientes arriba.
        // HU-23 (tarea 34): "Como encargado, quiero administrar contratos
        // con sus ventanas de aplicación" — la HU lo dice literal, mismo
        // criterio que clientes arriba.
        // HU-24 (tarea 35): "Como encargado, quiero administrar campos y sus
        // lotes" — la HU lo dice literal, mismo criterio que clientes y
        // contratos arriba. ADR 0020 retira `comercial.campo.*` (entidad
        // eliminada, ver el comentario en PERMISOS de arriba); el encargado
        // ya tiene `comercial.propiedad.*` asignado arriba, así que retirar
        // estos cuatro no le saca ninguna capacidad real.
        // HU-54 (tarea 77): administra también la entrada directa por
        // lote — mismo criterio que propiedades arriba.
        // HU-48 (tarea 71): administra también el catálogo de cultivos —
        // mismo criterio que campos y lotes arriba.
        // HU-27 (tarea 36): "Como encargado, quiero administrar la flota de
        // drones" — la HU lo dice literal, mismo criterio que clientes,
        // contratos y campos arriba.
        'operaciones.dron.ver',
        'operaciones.dron.crear',
        'operaciones.dron.editar',
        'operaciones.dron.eliminar',
        // HU-25 (tarea 38): "Como encargado, quiero crear y seguir las
        // órdenes de aplicación desde el panel" — la HU lo dice literal,
        // mismo criterio que clientes, contratos, campos y drones arriba.
        // ADR 0022: pausar/cerrar/cancelar una aplicación — el encargado
        // administra la ejecución diaria, mismo criterio que `.activar`.
        // HU-70 (tarea 85): administra también el reparto de equipos por
        // orden — mismo criterio que el resto de `operaciones.orden.*` arriba.
        // HU-44 (tarea 58): "jefe de campo, quiero registrar las pausas con
        // su causa atribuible" — el jefe de campo es dueño de la HU, pero el
        // encargado administra la operación diaria (mismo criterio que
        // trabajo.ver/sesion.validar arriba) así que comparte el grano.
        'operaciones.pausa.ver',
        'operaciones.pausa.registrar',
        // HU-51 (tarea 74): administra la operación diaria, así que también
        // puede ver dónde y cuántos días estuvo cada equipo — mismo criterio
        // que trabajo.ver/pausa.ver arriba. Reforma 19/9/2026: administra
        // también el alta, edición y baja de estadías — mismo criterio que
        // el resto de `operaciones.orden.*`/`operaciones.dron.*` arriba
        // (`.eliminar` incluido, a diferencia del jefe de campo).
        // HU-26 (tarea 37): "Como encargado, quiero administrar personas y
        // bases" — la HU lo dice literal, mismo criterio que clientes,
        // contratos, campos y drones arriba.
        'personal.base.ver',
        'personal.base.crear',
        'personal.base.editar',
        'personal.base.eliminar',
        'personal.persona.ver',
        'personal.persona.crear',
        'personal.persona.editar',
        'personal.persona.eliminar',
        // HU-58 (tarea 81): administra la operación diaria y decide a quién
        // volver a contratar — mismo criterio que el resto de este rol.
        'personal.persona.desempenio',
        // Tarea 72 (HU-49, ADR 0015 punto 3): equipos de trabajo — mismo
        // criterio que personas/bases arriba.
        'personal.equipo_trabajo.ver',
        'personal.equipo_trabajo.crear',
        'personal.equipo_trabajo.editar',
        'personal.equipo_trabajo.eliminar',
        // HU-31 (tarea 45): "como encargado, quiero emitir la factura de un
        // trabajo desde su acta conformada" — la HU lo dice literal, mismo
        // criterio que clientes, contratos, campos, drones, bases y personas
        // arriba.
        // HU-39 (tarea 51): "como encargado, quiero seguir las baterías con
        // sus ciclos y estado" — la HU lo dice literal, mismo criterio que
        // clientes, contratos, campos, drones, bases, personas y facturas
        // arriba.
        'mantenimiento.bateria.ver',
        'mantenimiento.bateria.crear',
        'mantenimiento.bateria.editar',
        'mantenimiento.bateria.eliminar',
        // HU-82 (tarea 97): "como encargado, quiero llevar el activo
        // completo del dron" — la HU lo dice literal, mismo criterio que el
        // resto de este rol arriba.
        'mantenimiento.ficha_dron.ver',
        'mantenimiento.ficha_dron.crear',
        'mantenimiento.ficha_dron.editar',
        'mantenimiento.ficha_dron.eliminar',
        // HU-46 (tarea 69, ADR 0015 punto 1): arma la campaña (código,
        // nombre, fechas) — sin `.cambiar_estado`, exclusivo del dueño (ver
        // el comentario en PERMISOS de arriba).
    ];

    /**
     * Coordina la cuadrilla y valida sesiones ajenas (diseño §2) — necesita
     * ver qué se cerró en el panel para poder coordinar la jornada siguiente,
     * y (HU-14) aprobar o rechazar sesiones cerradas.
     *
     * @var list<string>
     */
    private const PERMISOS_JEFE_CAMPO = [
        'operaciones.trabajo.ver',
        'operaciones.sesion.validar',
        // HU-70 (tarea 85): "dónde asignarle el trabajo al piloto" — el
        // reclamo del dueño (audio del 13/9/2026) es literalmente del jefe
        // de campo, que hoy avisa por WhatsApp. Ficha propia
        // (`panel.reparto-cuadrillas.*`), no la de la orden: no necesita
        // `operaciones.orden.ver` (CRUD completo de la orden) para repartir
        // equipos.
        // Reforma "Orden de Trabajo" (18/9/2026): mismo reclamo de HU-70,
        // ahora con tanda/turno — el jefe de campo arma la tanda igual que
        // antes repartía equipos, así que se lleva el permiso nuevo con el
        // mismo criterio que `operaciones.orden.asignar_equipos` arriba. Sin
        // `.editar`/`.eliminar`: corregir un trabajo cargado mal es del
        // encargado (`PERMISOS_ENCARGADO_OPERACIONES`), no del jefe de campo.
        // Tarea 62 (fuga 2): coordina la cuadrilla, aterriza en el dashboard
        // tras elegir rol y necesita la ficha de la compañía.
        'seguridad.dashboard.ver',
        'seguridad.organizacion.ver',
        // HU-17 (tarea 24): jefe de campo también genera y firma el acta —
        // mismo criterio que el piloto (ver PERMISOS, arriba).
        'operaciones.acta.generar',
        'operaciones.acta.firmar',
        // HU-18 (tarea 25): espec línea 89, "Ver reportes técnicos".
        'operaciones.reporte.ver',
        // HU-44 (tarea 58): "como jefe de campo, quiero registrar las
        // pausas con su causa atribuible" — la HU lo dice literal, dueño de
        // esta responsabilidad.
        'operaciones.pausa.ver',
        'operaciones.pausa.registrar',
        // HU-51 (tarea 74): coordina la cuadrilla, así que necesita ver
        // dónde y cuántos días estuvo el equipo — mismo criterio que
        // trabajo.ver/pausa.ver arriba. Reforma 19/9/2026: también registra y
        // edita las estadías de sus cuadrillas — mismo criterio que
        // `operaciones.trabajo.crear` arriba (sin `.eliminar`, exclusivo de
        // los roles de gestión, ver `PERMISOS_ENCARGADO_OPERACIONES`).
        // Tarea 138 (dashboard del jefe de campo): el dueño le pidió ver los
        // recursos que se ocupan o faltan, qué lleva cada cuadrilla y cómo van
        // todas las órdenes de aplicación. Solo lectura, un permiso por cada
        // pantalla que el tablero resume: `ArmarDashboard` gatea cada sección
        // con el permiso de su pantalla completa (tarea 62), así que sin estos
        // tres el jefe no vería esas pestañas. Sin `.crear`/`.editar` de ninguno.
        'personal.equipo_trabajo.ver',
    ];

    /**
     * Permisos retirados del catálogo. Una base ya sembrada conserva la fila y
     * sus asignaciones, así que `run()` las da de baja de forma explícita
     * antes de sembrar:
     *
     * - `comercial.campo.*`: `Campo` deja de ser una entidad (ADR 0020).
     * - `finanzas.*`, `inventario.*` y `mantenimiento.(vehiculo|generador|orden|plan).*`:
     *   el marketplace no lleva planilla, contabilidad interna, inventario ni
     *   mantenimiento de flota (ADR 0029, punto 4). Del mantenimiento solo
     *   quedan las baterías y la ficha técnica del dron.
     *
     * @var list<string>
     */
    private const PERMISOS_RETIRADOS = [
        'seguridad.usuario.portal',
        'operaciones.trabajo.crear',
        'operaciones.trabajo.editar',
        'comercial.cliente.ver',
        'comercial.cliente.crear',
        'comercial.cliente.editar',
        'comercial.cliente.eliminar',
        'comercial.propiedad.ver',
        'comercial.propiedad.crear',
        'comercial.propiedad.editar',
        'comercial.propiedad.eliminar',
        'comercial.contrato.ver',
        'comercial.contrato.crear',
        'comercial.contrato.editar',
        'comercial.contrato.cambiar_estado',
        'comercial.lote.ver',
        'comercial.lote.crear',
        'comercial.lote.editar',
        'comercial.lote.eliminar',
        'comercial.cultivo.ver',
        'comercial.cultivo.crear',
        'comercial.cultivo.editar',
        'comercial.cultivo.eliminar',
        'operaciones.orden.ver',
        'operaciones.orden.crear',
        'operaciones.orden.editar',
        'operaciones.orden.activar',
        'operaciones.orden.eliminar',
        'operaciones.orden.pausar',
        'operaciones.orden.cerrar',
        'operaciones.orden.cancelar',
        'operaciones.orden.asignar_equipos',
        'operaciones.estadia.ver',
        'operaciones.estadia.crear',
        'operaciones.estadia.editar',
        'operaciones.estadia.eliminar',
        'comercial.factura.ver',
        'comercial.factura.crear',
        'comercial.reporte.ver',
        'campania.campania.ver',
        'campania.campania.crear',
        'campania.campania.editar',
        'campania.campania.cambiar_estado',
        'campania.campania.eliminar',
        'comercial.campo.ver',
        'comercial.campo.crear',
        'comercial.campo.editar',
        'comercial.campo.eliminar',
        'finanzas.anticipo.crear',
        'finanzas.anticipo.eliminar',
        'finanzas.anticipo.ver',
        'finanzas.combustible.crear',
        'finanzas.combustible.eliminar',
        'finanzas.combustible.ver',
        'finanzas.devengo.ver',
        'finanzas.gasto.crear',
        'finanzas.gasto.eliminar',
        'finanzas.gasto.ver',
        'finanzas.planilla.aprobar',
        'finanzas.planilla.generar',
        'finanzas.planilla.ver',
        'finanzas.rendicion.aprobar',
        'finanzas.rendicion.crear',
        'finanzas.rendicion.presentar',
        'finanzas.rendicion.ver',
        'finanzas.tarifa.crear',
        'finanzas.tarifa.editar',
        'finanzas.tarifa.eliminar',
        'finanzas.tarifa.ver',
        'inventario.movimiento.crear',
        'inventario.movimiento.ver',
        'inventario.repuesto.crear',
        'inventario.repuesto.editar',
        'inventario.repuesto.eliminar',
        'inventario.repuesto.ver',
        'mantenimiento.generador.crear',
        'mantenimiento.generador.editar',
        'mantenimiento.generador.eliminar',
        'mantenimiento.generador.ver',
        'mantenimiento.orden.cerrar',
        'mantenimiento.orden.crear',
        'mantenimiento.orden.editar',
        'mantenimiento.orden.ver',
        'mantenimiento.plan.crear',
        'mantenimiento.plan.editar',
        'mantenimiento.plan.eliminar',
        'mantenimiento.plan.ver',
        'mantenimiento.vehiculo.crear',
        'mantenimiento.vehiculo.editar',
        'mantenimiento.vehiculo.eliminar',
        'mantenimiento.vehiculo.ver',
    ];

    public function run(): void
    {
        foreach (self::PERMISOS_RETIRADOS as $codigoRetirado) {
            $this->retirarPermiso($codigoRetirado);
        }

        $roles = collect(self::ROLES)->mapWithKeys(
            fn (string $description, string $name) => [$name => $this->rol($name, $description)],
        );

        $permisos = collect(self::PERMISOS)->mapWithKeys(
            fn (string $description, string $code) => [$code => $this->permiso($code, $description)],
        );

        // dueno: todos los permisos del catálogo (diseño §2), salvo los que
        // son solo de la plataforma (tarea 140).
        $this->asignar(
            $roles['dueno'],
            $permisos->except(self::PERMISOS_SOLO_ADMIN_PLATAFORMA)->values()->all(),
        );

        // admin_plataforma (tarea 100): TODO el catálogo, sin excepción — a
        // diferencia de dueno, recibe también PERMISOS_SOLO_ADMIN_PLATAFORMA
        // (tarea 140). Es dato de catálogo puro, corre en TODOS los entornos
        // (un rol sin usuarios asignados no daña nada en producción). Quien
        // recibe usuarios asignados a este rol es AdminPlataformaSeeder, que
        // sí está gateado a local/staging.
        $this->asignar($roles['admin_plataforma'], $permisos->values()->all());

        // encargado_operaciones: todo salvo asignar_rol_dueno.
        $this->asignar(
            $roles['encargado_operaciones'],
            $permisos->only([...self::PERMISOS_ENCARGADO_OPERACIONES, ...self::PERMISOS_ADMINISTRACION_PROVEEDORES, ...self::PERMISOS_ADMINISTRACION_SOLICITUDES, ...self::PERMISOS_ADMINISTRACION_POSTVENTA])->values()->all(),
        );

        // Marketplace (ADR 0036): proveedor y cliente operan por la API con lo suyo.
        $this->asignar($roles['proveedor'], $permisos->only(self::PERMISOS_PROVEEDOR)->values()->all());
        $this->asignar($roles['cliente'], $permisos->only(self::PERMISOS_CLIENTE)->values()->all());

        // piloto (HU-17, tarea 24): ejecuta desde `agrocom-field`; en el
        // panel solo ve su tablero.
        $this->asignar($roles['piloto'], $permisos->only(self::PERMISOS_PILOTO)->values()->all());

        // jefe_campo: solo lo suyo (diseño §2) — antes ninguno, ahora ver
        // trabajos/sesiones (HU-05, tarea 13).
        $this->asignar(
            $roles['jefe_campo'],
            $permisos->only(self::PERMISOS_JEFE_CAMPO)->values()->all(),
        );

        // auxiliar: a diferencia del piloto (arriba), no genera ni firma
        // actas. Solo ve su tablero, acotado a su persona.
        $this->asignar(
            $roles['auxiliar'],
            $permisos->only(self::PERMISOS_AUXILIAR)->values()->all(),
        );
    }

    private function rol(string $name, string $description): SecRole
    {
        return SecRole::query()->firstOrCreate(
            ['name' => $name],
            ['description' => $description, 'state' => true],
        );
    }

    private function permiso(string $code, string $description): SecPermission
    {
        return SecPermission::query()->firstOrCreate(
            ['code' => $code],
            ['description' => $description, 'state' => true],
        );
    }

    /**
     * `withTrashed()`, no `query()`: una fila soft-deleteada (un dueño quitó
     * el permiso desde el panel, vía `AsignarPermisosRol`) YA EXISTE para
     * este par rol/permiso. Contar solo las vivas volvería a insertar el
     * otorgamiento en cada corrida —el mismo hueco que la tarea 64 pidió
     * cerrar ("no pisa en cada corrida lo que un dueño cambió")— porque el
     * índice único es parcial (`WHERE deleted_at IS NULL`) y no impide una
     * fila nueva. Sembrar es "otorgar si nunca se otorgó", nunca "reponer lo
     * que alguien quitó a propósito".
     *
     * @param  list<SecPermission>  $permisos
     */
    private function asignar(SecRole $rol, array $permisos): void
    {
        foreach ($permisos as $permiso) {
            $yaExiste = SecRolePermission::withTrashed()
                ->where('id_role', $rol->id)
                ->where('id_permission', $permiso->id)
                ->exists();

            if ($yaExiste) {
                continue;
            }

            (new SecRolePermission([
                'id_role' => $rol->id,
                'id_permission' => $permiso->id,
            ]))->save();
        }
    }

    /**
     * Inversa de {@see asignar()}: ahí "sembrar es otorgar si nunca se
     * otorgó, nunca reponer lo que alguien quitó"; acá es "retirar lo que ya
     * no debería estar", así que no se reusa ese método. Sacar la línea del
     * array `PERMISOS` (con `firstOrCreate`, que solo agrega) evita que una
     * instalación NUEVA cree el permiso, pero una base YA sembrada conserva
     * la fila y sus asignaciones en `sec_role_permission` — hay que
     * retirarlas de manera explícita.
     *
     * Soft delete (invariante 8 de CLAUDE.md): la fila de `sec_permission` y
     * cada `sec_role_permission` que la referencia se borran una por una (no
     * un `update()` masivo), para que cada `delete()` pase por el observer
     * de bitácora. Idempotente: si el permiso ya está soft-deleteado (o
     * nunca existió, instalación nueva), `SecPermission::query()` no lo
     * encuentra y no hace nada.
     */
    private function retirarPermiso(string $code): void
    {
        $permiso = SecPermission::query()->where('code', $code)->first();

        if ($permiso === null) {
            return;
        }

        SecRolePermission::query()
            ->where('id_permission', $permiso->id)
            ->get()
            ->each(fn (SecRolePermission $asignacion) => $asignacion->delete());

        $permiso->delete();
    }
}

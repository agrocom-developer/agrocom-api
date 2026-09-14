<?php

namespace Database\Seeders\Catalogo;

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
        'seguridad.usuario.portal' => 'Crear/editar cuentas de portal del cliente (además de crear/editar)',
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
        // `auxiliar` (un único permiso en todo el catálogo,
        // `finanzas.devengo.ver`) veía el tablero completo y la ficha de la
        // compañía. `dueno` los recibe igual que todo el catálogo (sin
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
        'comercial.cliente.ver' => 'Ver el listado y detalle de clientes',
        'comercial.cliente.crear' => 'Dar de alta un cliente con sus contactos',
        'comercial.cliente.editar' => 'Editar los datos y contactos de un cliente',
        'comercial.cliente.eliminar' => 'Dar de baja (lógica) un cliente',
        // ADR 0018: propiedades del cliente — nivel de terreno entre
        // `Cliente` y `Campo` (una propiedad puede tener más de un campo
        // físico delimitado, caso "Gamelera"). Grano fino, mismo criterio
        // que `comercial.cliente.*`.
        'comercial.propiedad.ver' => 'Ver el listado y detalle de propiedades',
        'comercial.propiedad.crear' => 'Dar de alta una propiedad',
        'comercial.propiedad.editar' => 'Editar los datos de una propiedad',
        'comercial.propiedad.eliminar' => 'Dar de baja (lógica) una propiedad sin campos asociados',
        // HU-23 (tarea 34): administración de contratos con sus ventanas de
        // aplicación. `cambiar_estado` separado de `.editar`, mismo criterio
        // que `usuario.bloquear` separado de `usuario.editar`: pasar un
        // contrato de `vigente` a `cancelado` no es la misma responsabilidad
        // que corregir un dato.
        'comercial.contrato.ver' => 'Ver el listado y detalle de contratos',
        'comercial.contrato.crear' => 'Dar de alta un contrato con sus ventanas de aplicación',
        'comercial.contrato.editar' => 'Editar los datos y ventanas de un contrato',
        'comercial.contrato.cambiar_estado' => 'Cambiar el estado de un contrato (vigente, finalizado, cancelado)',
        // HU-24 (tarea 35): administración de campos con sus lotes. Grano
        // fino, mismo criterio que `comercial.cliente.*`. Hasta la tarea 77
        // los lotes no tenían permiso propio (solo se tocaban dentro del
        // formulario del campo); `comercial.lote.*` abajo abre la entrada
        // directa por lote, sin reemplazar esta.
        'comercial.campo.ver' => 'Ver el listado y detalle de campos con sus lotes',
        'comercial.campo.crear' => 'Dar de alta un campo con sus lotes',
        'comercial.campo.editar' => 'Editar los datos y lotes de un campo',
        'comercial.campo.eliminar' => 'Dar de baja (lógica) un campo',
        // HU-54 (tarea 77): pantalla propia de lotes — listado con filtro
        // por cliente/propiedad y ficha con alta, edición y baja lógica de
        // un lote suelto. Grano fino, mismo criterio que `comercial.campo.*`;
        // el alta de una propiedad con sus lotes en la misma transacción
        // sigue siendo un único caso de uso (`CrearCampo`), llamado desde
        // cualquiera de los dos formularios.
        'comercial.lote.ver' => 'Ver el listado y detalle de lotes',
        'comercial.lote.crear' => 'Dar de alta un lote suelto',
        'comercial.lote.editar' => 'Editar los datos y el perímetro de un lote',
        'comercial.lote.eliminar' => 'Dar de baja (lógica) un lote sin historial asociado',
        // HU-48 (tarea 71, ADR 0015 punto 4): catálogo de cultivos — el
        // cultivo no es columna de `com_lotes`, es un catálogo simple que se
        // vincula a un lote solo dentro de una campaña (etapa 2 de esta
        // tarea). Grano fino, mismo criterio que `comercial.campo.*`/
        // `comercial.lote.*`.
        'comercial.cultivo.ver' => 'Ver el catálogo de cultivos',
        'comercial.cultivo.crear' => 'Dar de alta un cultivo',
        'comercial.cultivo.editar' => 'Editar los datos de un cultivo',
        'comercial.cultivo.eliminar' => 'Dar de baja (lógica) un cultivo',
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
        'operaciones.orden.ver' => 'Ver el listado de órdenes de aplicación',
        'operaciones.orden.crear' => 'Dar de alta una orden de aplicación',
        'operaciones.orden.editar' => 'Editar los datos de una orden de aplicación',
        'operaciones.orden.activar' => 'Activar una orden de aplicación (emitida → vigente)',
        'operaciones.orden.eliminar' => 'Dar de baja (lógica) una orden de aplicación',
        // HU-70 (tarea 85): "dónde asignarle el trabajo al piloto" — repartir
        // las hectáreas de una orden vigente entre equipos de trabajo. Grano
        // propio, no parte de `.editar`: no corrige la orden, reparte su
        // trabajo; una ficha propia (`panel.asignacion-equipos.*`), no la
        // ficha de la orden, así que no depende de `operaciones.orden.ver`.
        'operaciones.orden.asignar_equipos' => 'Asignar equipos de trabajo (con sus hectáreas) a una orden vigente',
        // HU-44 (tarea 58): pausas de sesión con causa atribuible (DS-01).
        // Grano fino (ver/registrar), mismo criterio que `operaciones.alerta.*`:
        // ver el tablero agregado y cargar una pausa no son la misma
        // responsabilidad.
        'operaciones.pausa.ver' => 'Ver el listado de pausas y su agregado por causa',
        'operaciones.pausa.registrar' => 'Registrar una pausa de sesión con su causa',
        // HU-51 (tarea 74): entrada y salida del equipo en cada hacienda,
        // cargada desde la app de campo. Un único permiso de solo lectura —
        // el panel nunca abre ni cierra una estadía (ver "Qué NO hacer" del
        // prompt de la tarea).
        'operaciones.estadia.ver' => 'Ver el listado de estadías del equipo en cada hacienda',
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
        // HU-28 (tarea 40): "como piloto o auxiliar, quiero ver mis devengos
        // por período" — primer permiso de panel para piloto y auxiliar, que
        // hasta esta tarea no tenían ninguno (piloto: solo
        // PERMISOS_PILOTO/acta.*; auxiliar: ninguno). Un único permiso de
        // lectura, con el scoping por PERSONA (no por rol) resuelto dentro de
        // DevengosController.
        'finanzas.devengo.ver' => 'Ver los propios devengos por período',
        // HU-29 (tarea 41): "como encargado, quiero registrar anticipos
        // validando el tope, para no adelantar más de lo devengado". Grano
        // fino sin `.editar`: un anticipo, una vez creado, es inmutable
        // salvo baja (ver `Aplicacion/RegistrarAnticipo`).
        'finanzas.anticipo.ver' => 'Ver el listado de anticipos',
        'finanzas.anticipo.crear' => 'Registrar un anticipo, validado contra el tope del mes',
        'finanzas.anticipo.eliminar' => 'Dar de baja (lógica) un anticipo registrado por error',
        // HU-30 (tarea 44): "como dueño, quiero generar la planilla del
        // período desde los devengos y aprobarla, para pagar con un
        // respaldo que cuadre" — cierra el Sprint 8. `.aprobar` NO entra en
        // PERMISOS_ENCARGADO_OPERACIONES (ver más abajo): el dueño lo recibe
        // solo por ser "todos los permisos del catálogo, sin excepción"
        // (diseño §2), ningún otro rol lo tiene.
        'finanzas.planilla.ver' => 'Ver el listado y detalle de planillas del período',
        'finanzas.planilla.generar' => 'Generar la planilla de un período desde sus devengos y anticipos',
        'finanzas.planilla.aprobar' => 'Aprobar una planilla en borrador (exclusivo del dueño)',
        // HU-31 (tarea 45): "como encargado, quiero emitir la factura de un
        // trabajo desde su acta conformada, para cobrar sobre hectáreas ya
        // firmadas" — abre Sprint 9. Grano fino sin `.editar` ni
        // `.eliminar`: una factura, una vez emitida, es un snapshot
        // inmutable (ver `Comercial/Aplicacion/EmitirFactura`).
        'comercial.factura.ver' => 'Ver el listado de facturas emitidas',
        'comercial.factura.crear' => 'Emitir la factura de un trabajo desde su acta conformada',
        // HU-32 (tarea 46): "como dueño, quiero un reporte comercial de
        // avance por cliente, contrato y campaña" — la HU lo dice literal.
        // NO entra en PERMISOS_ENCARGADO_OPERACIONES (ver más abajo):
        // exclusivo del dueño, mismo criterio que `finanzas.planilla.aprobar`.
        'comercial.reporte.ver' => 'Ver el reporte comercial de avance por contrato (exclusivo del dueño)',
        // HU-33 (tarea 47): "como encargado, quiero cargar gastos con su
        // categoría y comprobante, para que la campaña tenga costo real" —
        // abre Sprint 10. Grano fino sin `.editar`: un gasto, una vez
        // cargado, es inmutable salvo baja (ver `Aplicacion/CrearGasto`).
        'finanzas.gasto.ver' => 'Ver el listado de gastos de campaña',
        'finanzas.gasto.crear' => 'Cargar un gasto con su categoría y comprobante',
        'finanzas.gasto.eliminar' => 'Dar de baja (lógica) un gasto registrado por error',
        // HU-34 (tarea 48): "como jefe de campo, quiero rendir los gastos que
        // hice en campo; el encargado los aprueba para reponer el fondo".
        // Grano fino con máquina de estados propia (abierta → presentada →
        // aprobada, a diferencia de gasto/anticipo arriba, sin ninguna).
        // `.aprobar` SÍ entra en PERMISOS_ENCARGADO_OPERACIONES (ver más
        // abajo) — a diferencia de `finanzas.planilla.aprobar`, que es
        // exclusivo del dueño: acá la guarda real de que el aprobador nunca
        // sea quien rindió ya la resuelve `PoliticaAprobacionRendicion`/la
        // máquina de estados por PERSONA, no el permiso.
        'finanzas.rendicion.ver' => 'Ver el listado y detalle de rendiciones de campo',
        'finanzas.rendicion.crear' => 'Crear una rendición de campo y asociarle gastos',
        'finanzas.rendicion.presentar' => 'Presentar una rendición de campo para su aprobación',
        'finanzas.rendicion.aprobar' => 'Aprobar una rendición de campo presentada, para reponer el fondo',
        // HU-35 (tarea 49): "como encargado, quiero registrar el
        // combustible del generador y de los vehículos, para imputarlo a la
        // campaña" — cierra Sprint 10. Grano fino sin `.editar`: una carga,
        // una vez cargada, es inmutable salvo baja (ver
        // `Aplicacion/CrearCombustible`), mismo criterio que gasto/anticipo.
        'finanzas.combustible.ver' => 'Ver el listado de cargas de combustible',
        'finanzas.combustible.crear' => 'Cargar combustible del generador o de un vehículo',
        'finanzas.combustible.eliminar' => 'Dar de baja (lógica) una carga de combustible registrada por error',
        // HU-40 (tarea 50): "como encargado, quiero administrar los
        // vehículos con su asignación a base" — abre Sprint 11 y el módulo
        // `Mantenimiento` (ADR 0011, extensión 3/9/2026). Grano fino, mismo
        // criterio que `operaciones.dron.*`.
        'mantenimiento.vehiculo.ver' => 'Ver el listado de vehículos',
        'mantenimiento.vehiculo.crear' => 'Dar de alta un vehículo',
        'mantenimiento.vehiculo.editar' => 'Editar los datos de un vehículo',
        'mantenimiento.vehiculo.eliminar' => 'Dar de baja (lógica) un vehículo',
        // Tarea 72 (HU-49, ADR 0015 punto 3): catálogo de generadores, ABM
        // mínimo — no es una HU propia, es la tabla que hace falta para
        // poder asignar un generador como equipamiento de un equipo de
        // trabajo. Grano fino, mismo criterio que `mantenimiento.vehiculo.*`.
        'mantenimiento.generador.ver' => 'Ver el listado de generadores',
        'mantenimiento.generador.crear' => 'Dar de alta un generador',
        'mantenimiento.generador.editar' => 'Editar los datos de un generador',
        'mantenimiento.generador.eliminar' => 'Dar de baja (lógica) un generador',
        // HU-39 (tarea 51): "como encargado, quiero seguir las baterías con
        // sus ciclos y estado, para retirarlas antes de que fallen en
        // vuelo". Grano fino, mismo criterio que `mantenimiento.vehiculo.*`.
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
        // HU-36 (tarea 52): "como encargado, quiero llevar stock de
        // repuestos por base con alerta de mínimo, para reponer antes de
        // quedarme sin" — cierra Sprint 11 y abre el módulo `Inventario`
        // (ADR 0011, extensión 3/9/2026, punto 15), separado de
        // `Mantenimiento`. Catálogo de repuestos: grano fino, mismo criterio
        // que `mantenimiento.bateria.*`.
        'inventario.repuesto.ver' => 'Ver el catálogo de repuestos',
        'inventario.repuesto.crear' => 'Dar de alta un repuesto',
        'inventario.repuesto.editar' => 'Editar los datos de un repuesto',
        'inventario.repuesto.eliminar' => 'Dar de baja (lógica) un repuesto',
        // Stock por base y sus movimientos (compra/salida/ajuste/traslado).
        // Sin `.editar`/`.eliminar`: un movimiento, una vez registrado, es un
        // asiento inmutable (ver `RegistrarMovimientoStock`), mismo criterio
        // que `finanzas.gasto.*` sin `.editar`.
        'inventario.movimiento.ver' => 'Ver el stock por base y sus movimientos',
        'inventario.movimiento.crear' => 'Registrar un movimiento de stock (compra, salida, ajuste o traslado)',
        // HU-37 (tarea 53): "como encargado, quiero abrir órdenes de
        // mantenimiento y cerrarlas consumiendo repuestos, para que el costo
        // quede imputado". Grano fino, mismo criterio que
        // `mantenimiento.vehiculo.*`; `.cerrar` aparte de `.editar` porque el
        // cierre no es una edición libre — dispara la máquina de estados que
        // consume stock y genera el gasto (invariante 7 de CLAUDE.md).
        'mantenimiento.orden.ver' => 'Ver el listado de órdenes de mantenimiento',
        'mantenimiento.orden.crear' => 'Abrir una orden de mantenimiento',
        'mantenimiento.orden.editar' => 'Editar los datos de una orden de mantenimiento',
        'mantenimiento.orden.cerrar' => 'Cerrar una orden de mantenimiento consumiendo repuestos',
        // HU-38 (tarea 54): "como encargado, quiero planes de mantenimiento
        // preventivo por horas de vuelo, para que el sistema me avise antes
        // de la falla" — cierra Sprint 11. Grano fino, mismo criterio que
        // `mantenimiento.vehiculo.*`/`mantenimiento.bateria.*`.
        'mantenimiento.plan.ver' => 'Ver el listado de planes de mantenimiento preventivo',
        'mantenimiento.plan.crear' => 'Dar de alta un plan de mantenimiento preventivo',
        'mantenimiento.plan.editar' => 'Editar los datos de un plan de mantenimiento preventivo',
        'mantenimiento.plan.eliminar' => 'Dar de baja (lógica) un plan de mantenimiento preventivo',
        // HU-46 (tarea 69, ADR 0015 punto 1): la campaña como eje transversal
        // del sistema. Grano fino, mismo criterio que `comercial.contrato.*`
        // — `.cambiar_estado` separado de `.editar`. A diferencia de
        // `comercial.contrato.cambiar_estado` (compartido con
        // `encargado_operaciones`), `.cambiar_estado` NO entra en
        // PERMISOS_ENCARGADO_OPERACIONES (ver más abajo): "solo el dueño
        // cierra una campaña" (pedido explícito del 7/9/2026) — con una
        // única apertura/cierre por campaña al año no hay costo operativo en
        // concentrarla en el dueño, a diferencia de `.ver`/`.crear`/`.editar`,
        // que sí comparte con el encargado (arma la campaña, el dueño decide
        // cuándo abrirla y cerrarla).
        'campania.campania.ver' => 'Ver el listado de campañas',
        'campania.campania.crear' => 'Dar de alta una campaña',
        'campania.campania.editar' => 'Editar los datos de una campaña',
        'campania.campania.cambiar_estado' => 'Cambiar el estado de una campaña (abrir, cerrar) — exclusivo del dueño',
    ];

    /**
     * Piloto: lo que ejecuta desde `agrocom-field` (HU-17, tarea 24) más su
     * primer permiso de panel (HU-28, tarea 40) — ver sus propios devengos.
     *
     * @var list<string>
     */
    private const PERMISOS_PILOTO = [
        'operaciones.acta.generar',
        'operaciones.acta.firmar',
        'finanzas.devengo.ver',
        // Tarea 67: el dashboard dejó de ser un tablero único de gerencia y
        // pasó a componerse por rol. Para el piloto son DOS secciones —sus
        // sesiones y su liquidación del mes—, ambas acotadas a su
        // `persona_id`, no a la operación entera. Sin este permiso aterrizaba
        // en la primera pantalla suelta que el menú le dejara ver.
        'seguridad.dashboard.ver',
    ];

    /**
     * Auxiliar: hasta HU-28 (tarea 40) no tenía ningún permiso de panel — ver
     * sus propios devengos es el primero.
     *
     * @var list<string>
     */
    private const PERMISOS_AUXILIAR = [
        'finanzas.devengo.ver',
        // Tarea 67, mismo motivo que el piloto: ve sus propias sesiones (como
        // auxiliar de ellas) y su liquidación, nada de la operación global.
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
        'seguridad.usuario.portal',
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
        'comercial.cliente.ver',
        'comercial.cliente.crear',
        'comercial.cliente.editar',
        'comercial.cliente.eliminar',
        // ADR 0018: administra también las propiedades del cliente — mismo
        // criterio que clientes arriba.
        'comercial.propiedad.ver',
        'comercial.propiedad.crear',
        'comercial.propiedad.editar',
        'comercial.propiedad.eliminar',
        // HU-23 (tarea 34): "Como encargado, quiero administrar contratos
        // con sus ventanas de aplicación" — la HU lo dice literal, mismo
        // criterio que clientes arriba.
        'comercial.contrato.ver',
        'comercial.contrato.crear',
        'comercial.contrato.editar',
        'comercial.contrato.cambiar_estado',
        // HU-24 (tarea 35): "Como encargado, quiero administrar campos y sus
        // lotes" — la HU lo dice literal, mismo criterio que clientes y
        // contratos arriba.
        'comercial.campo.ver',
        'comercial.campo.crear',
        'comercial.campo.editar',
        'comercial.campo.eliminar',
        // HU-54 (tarea 77): administra también la entrada directa por
        // lote — mismo criterio que campos arriba.
        'comercial.lote.ver',
        'comercial.lote.crear',
        'comercial.lote.editar',
        'comercial.lote.eliminar',
        // HU-48 (tarea 71): administra también el catálogo de cultivos —
        // mismo criterio que campos y lotes arriba.
        'comercial.cultivo.ver',
        'comercial.cultivo.crear',
        'comercial.cultivo.editar',
        'comercial.cultivo.eliminar',
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
        'operaciones.orden.ver',
        'operaciones.orden.crear',
        'operaciones.orden.editar',
        'operaciones.orden.activar',
        'operaciones.orden.eliminar',
        // HU-70 (tarea 85): administra también el reparto de equipos por
        // orden — mismo criterio que el resto de `operaciones.orden.*` arriba.
        'operaciones.orden.asignar_equipos',
        // HU-44 (tarea 58): "jefe de campo, quiero registrar las pausas con
        // su causa atribuible" — el jefe de campo es dueño de la HU, pero el
        // encargado administra la operación diaria (mismo criterio que
        // trabajo.ver/sesion.validar arriba) así que comparte el grano.
        'operaciones.pausa.ver',
        'operaciones.pausa.registrar',
        // HU-51 (tarea 74): administra la operación diaria, así que también
        // puede ver dónde y cuántos días estuvo cada equipo — mismo criterio
        // que trabajo.ver/pausa.ver arriba.
        'operaciones.estadia.ver',
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
        // HU-29 (tarea 41): "como encargado, quiero registrar anticipos
        // validando el tope" — la HU lo dice literal, mismo criterio que
        // clientes, contratos, campos, drones, bases y personas arriba.
        'finanzas.anticipo.ver',
        'finanzas.anticipo.crear',
        'finanzas.anticipo.eliminar',
        // HU-30 (tarea 44): "como dueño, quiero generar la planilla..." — el
        // encargado genera y consulta, pero NO aprueba: `.aprobar` queda
        // fuera de esta lista a propósito (exclusivo del rol `dueno`, ver
        // el comentario en PERMISOS de arriba).
        'finanzas.planilla.ver',
        'finanzas.planilla.generar',
        // HU-31 (tarea 45): "como encargado, quiero emitir la factura de un
        // trabajo desde su acta conformada" — la HU lo dice literal, mismo
        // criterio que clientes, contratos, campos, drones, bases, personas
        // y anticipos arriba.
        'comercial.factura.ver',
        'comercial.factura.crear',
        // HU-33 (tarea 47): "como encargado, quiero cargar gastos con su
        // categoría y comprobante" — la HU lo dice literal, mismo criterio
        // que clientes, contratos, campos, drones, bases, personas,
        // anticipos y facturas arriba.
        'finanzas.gasto.ver',
        'finanzas.gasto.crear',
        'finanzas.gasto.eliminar',
        // HU-34 (tarea 48): "como jefe de campo, quiero rendir los gastos que
        // hice en campo; el encargado los aprueba para reponer el fondo" —
        // la HU lo dice literal, incluido `.aprobar`: a diferencia de
        // `finanzas.planilla.aprobar` (exclusivo del dueño), acá el
        // encargado SÍ aprueba (ver el comentario en PERMISOS de arriba).
        'finanzas.rendicion.ver',
        'finanzas.rendicion.crear',
        'finanzas.rendicion.presentar',
        'finanzas.rendicion.aprobar',
        // HU-35 (tarea 49): "como encargado, quiero registrar el
        // combustible del generador y de los vehículos" — la HU lo dice
        // literal, mismo criterio que clientes, contratos, campos, drones,
        // bases, personas, anticipos, facturas y gastos arriba.
        'finanzas.combustible.ver',
        'finanzas.combustible.crear',
        'finanzas.combustible.eliminar',
        // HU-40 (tarea 50): "como encargado, quiero administrar los
        // vehículos con su asignación a base" — la HU lo dice literal, mismo
        // criterio que clientes, contratos, campos, drones, bases, personas,
        // anticipos, facturas, gastos y combustible arriba.
        'mantenimiento.vehiculo.ver',
        'mantenimiento.vehiculo.crear',
        'mantenimiento.vehiculo.editar',
        'mantenimiento.vehiculo.eliminar',
        // Tarea 72 (HU-49, ADR 0015 punto 3): catálogo de generadores — mismo
        // criterio que vehículos arriba.
        'mantenimiento.generador.ver',
        'mantenimiento.generador.crear',
        'mantenimiento.generador.editar',
        'mantenimiento.generador.eliminar',
        // HU-39 (tarea 51): "como encargado, quiero seguir las baterías con
        // sus ciclos y estado" — la HU lo dice literal, mismo criterio que
        // clientes, contratos, campos, drones, bases, personas, anticipos,
        // facturas, gastos, combustible y vehículos arriba.
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
        // HU-37 (tarea 53): "como encargado, quiero abrir órdenes de
        // mantenimiento y cerrarlas consumiendo repuestos" — la HU lo dice
        // literal, mismo criterio que el resto de este rol arriba.
        // (HU-36/HU-38 daban acceso completo a repuestos, stock y planes de
        // mantenimiento; HU-88, tarea 103, se lo saca: los usa poco y le
        // ensucian el menú del día a día. Se retira el catálogo entero de
        // cada uno —ver/crear/editar/eliminar— y no solo `.ver`: dejar
        // `.crear`/`.editar`/`.eliminar` sin `.ver` abría un hueco raro
        // —podría crear un repuesto o un plan sin poder listarlo después—
        // y agregar `.ver` de vuelta habría revertido el pedido de la HU de
        // sacarlo del menú. Sigue disponible para `dueno`, que recibe el
        // catálogo completo sin excepción.)
        'mantenimiento.orden.ver',
        'mantenimiento.orden.crear',
        'mantenimiento.orden.editar',
        'mantenimiento.orden.cerrar',
        // HU-46 (tarea 69, ADR 0015 punto 1): arma la campaña (código,
        // nombre, fechas) — sin `.cambiar_estado`, exclusivo del dueño (ver
        // el comentario en PERMISOS de arriba).
        'campania.campania.ver',
        'campania.campania.crear',
        'campania.campania.editar',
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
        // (`panel.asignacion-equipos.*`), no la de la orden: no necesita
        // `operaciones.orden.ver` (CRUD completo de la orden) para repartir
        // equipos.
        'operaciones.orden.asignar_equipos',
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
        // trabajo.ver/pausa.ver arriba.
        'operaciones.estadia.ver',
    ];

    public function run(): void
    {
        $roles = collect(self::ROLES)->mapWithKeys(
            fn (string $description, string $name) => [$name => $this->rol($name, $description)],
        );

        $permisos = collect(self::PERMISOS)->mapWithKeys(
            fn (string $description, string $code) => [$code => $this->permiso($code, $description)],
        );

        // dueno: todos los permisos del catálogo, sin excepción (diseño §2).
        $this->asignar($roles['dueno'], $permisos->values()->all());

        // encargado_operaciones: todo salvo asignar_rol_dueno.
        $this->asignar(
            $roles['encargado_operaciones'],
            $permisos->only(self::PERMISOS_ENCARGADO_OPERACIONES)->values()->all(),
        );

        // piloto (HU-17, tarea 24): ejecuta desde `agrocom-field`; en el
        // panel solo ve lo suyo (devengos y, desde la tarea 67, su tablero).
        $this->asignar($roles['piloto'], $permisos->only(self::PERMISOS_PILOTO)->values()->all());

        // jefe_campo: solo lo suyo (diseño §2) — antes ninguno, ahora ver
        // trabajos/sesiones (HU-05, tarea 13).
        $this->asignar(
            $roles['jefe_campo'],
            $permisos->only(self::PERMISOS_JEFE_CAMPO)->values()->all(),
        );

        // auxiliar: a diferencia del piloto (arriba), no genera ni firma
        // actas. HU-28 (tarea 40) le dio su primer permiso de panel —ver sus
        // propios devengos— y la tarea 67 su tablero, acotado a su persona.
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
}

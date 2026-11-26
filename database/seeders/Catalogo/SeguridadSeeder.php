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
        'piloto' => 'Piloto de dron: ofrece, ejecuta los servicios adjudicados y sube las fotos desde la app.',
        'auxiliar' => 'Auxiliar de campo: apoya al piloto en el lote y sube fotos de avance desde la app.',
        // `encargado_operaciones` es el rol del administrador del marketplace; el
        // nombre técnico se conserva porque lo usan los avisos y las cuentas ya creadas.
        'encargado_operaciones' => 'Administrador del marketplace: aprueba proveedores, pilotos, drones y documentos, modera calificaciones y atiende disputas.',
        'soporte' => 'Soporte: atiende solicitudes, disputas e incidencias, e interviene en una negociación con motivo.',
        // Rol de la operación interna anterior: lo usan los avisos de trabajo cerrado y las cuentas ya creadas.
        'jefe_campo' => 'Jefe de campo: rol sin pantallas en el panel, se conserva para las cuentas y los avisos que ya lo usan.',
        'dueno' => 'Dueño de Agrocom SRL: acceso total, incluida la gestión de otros dueños.',
        // Marketplace (ADR 0036): cuentas que se registran por sí mismas desde
        // la app. No tienen ninguna pantalla del panel; operan por la API.
        'cliente' => 'Cliente del marketplace: solicita servicios de fumigación y califica al proveedor.',
        'proveedor' => 'Proveedor del marketplace: gestiona su personal, sus drones y sus equipos desde la app.',
        // Descripción recortada a propósito: `sec_role.description` es
        // `varchar(150)` y el texto original no entra en Postgres.
        'admin_plataforma' => 'Administrador de la plataforma Agrocom: acceso total, incluida la gestión de dueños — rol técnico, no del negocio del cliente.',
    ];

    /** @var array<string, string> */
    private const PERMISOS = [
        // Seguridad: cuentas, roles, dispositivos, organización, configuración y bitácora.
        'seguridad.usuario.ver' => 'Ver listado/detalle de usuarios',
        'seguridad.usuario.crear' => 'Crear usuario y asignarle roles (excepto dueño)',
        'seguridad.usuario.editar' => 'Editar datos y reasignar roles de un usuario (excepto dueño)',
        'seguridad.usuario.bloquear' => 'Bloquear/desbloquear (toggle de state, no es baja)',
        'seguridad.usuario.eliminar' => 'Baja lógica (soft delete)',
        'seguridad.usuario.asignar_rol_dueno' => 'Asignar o quitar el rol dueño a cualquier usuario',
        'seguridad.usuario.ver_como' => 'Ver el panel o el portal como otro usuario (solo lectura)',
        'seguridad.rol.ver' => 'Ver el catálogo de roles y qué permisos tiene cada uno',
        'seguridad.rol.crear' => 'Crear un rol nuevo (nace sin permisos)',
        'seguridad.rol.editar' => 'Editar nombre, descripción y estado de un rol',
        'seguridad.rol.eliminar' => 'Dar de baja un rol sin usuarios asignados',
        'seguridad.rol.asignar_permiso' => 'Otorgar y quitar permisos a un rol',
        'seguridad.dashboard.ver' => 'Ver el tablero "Operación de hoy" (dashboard)',
        'seguridad.organizacion.ver' => 'Ver el registro de la compañía (Organización)',
        'seguridad.organizacion.editar' => 'Editar los datos fiscales de la empresa (pestaña Facturación)',
        'seguridad.configuracion.ver' => 'Ver la configuración del sistema (llaves y tokens)',
        'seguridad.configuracion.editar' => 'Editar la configuración del sistema (llaves y tokens)',
        'seguridad.bitacora.ver' => 'Ver la bitácora de auditoría (quién hizo qué, cuándo y en qué zona horaria)',
        'seguridad.dispositivo.ver' => 'Ver los dispositivos con sesión abierta en la app de campo',
        'seguridad.dispositivo.revocar' => 'Revocar el acceso de un dispositivo de campo',
        // Proveedores y flota en el panel (ADR 0032): aprobar, suspender y dar de baja.
        'proveedores.proveedor.crear' => 'Registrar a mano a un proveedor que llega a la oficina (queda pendiente; quien lo registra no lo aprueba)',
        'proveedores.piloto.crear' => 'Registrar a mano a un piloto de un proveedor tercero (queda pendiente)',
        'flota.dron.crear' => 'Registrar a mano el dron de un proveedor (queda pendiente)',
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
        'flota.dron.revisar_foto' => 'Aprobar o rechazar las fotos de un dron, con motivo en el rechazo',
        'flota.equipo.ver' => 'Ver los equipos (piloto, dron y auxiliar) de los proveedores',
        // Marketplace, lado app (token de dispositivo): lo que cada rol hace sobre lo SUYO. El alcance lo hacen los casos de uso; el permiso es la puerta.
        'proveedores.cuenta.gestionar' => 'Gestionar su propio proveedor desde la app: pilotos, drones, equipos y documentos',
        'proveedores.cuenta.ver' => 'Ver, solo lectura, el proveedor al que pertenece y los drones de sus equipos desde la app (piloto)',
        'proveedores.auxiliar.registrar' => 'Registrar a un auxiliar (queda pendiente de aprobación)',
        'proveedores.documento.cargar' => 'Cargar los documentos propios o de lo que uno registró',
        'solicitudes.cliente.gestionar' => 'Gestionar su propio perfil de cliente desde la app',
        'solicitudes.terreno.gestionar' => 'Registrar y consultar sus propios terrenos desde la app',
        'solicitudes.solicitud.gestionar' => 'Crear, consultar, publicar y cancelar sus propias solicitudes de servicio desde la app',
        // Solicitudes, clientes y zonas en el panel (ADR 0027): consulta, cancelación e intervención con motivo.
        'solicitudes.solicitud.ver' => 'Ver el listado y la ficha de las solicitudes de servicio',
        'solicitudes.solicitud.cancelar' => 'Cancelar una solicitud de servicio desde el panel, con motivo',
        'solicitudes.cliente.crear' => 'Registrar a mano a un cliente (persona o empresa con sus contactos) que llega a la oficina',
        'solicitudes.cliente.ver' => 'Ver el listado y la ficha de los clientes del marketplace',
        'solicitudes.cliente.suspender' => 'Suspender a un cliente o reactivarlo, con motivo',
        'solicitudes.zona.ver' => 'Ver el catálogo de zonas de Santa Cruz',
        'solicitudes.zona.gestionar' => 'Crear, editar y dar de baja zonas',
        'solicitudes.solicitud.ver_seguimiento' => 'Ver el chat, las fotos de avance y la ubicación del piloto de una solicitud',
        'notificaciones.aviso.ver' => 'Ver los avisos que el sistema envía a clientes, proveedores y administradores',
        'solicitudes.solicitud.intervenir' => 'Cerrar la negociación de un proveedor sobre una solicitud, con motivo',
        // Negociación oferta-demanda (`negociacion-contrato.md`), lado app. El alcance lo hacen los casos de uso (un id ajeno es 404).
        'negociacion.mercado.ver' => 'Ver el mapa de solicitudes abiertas y su detalle antes de ofertar desde la app',
        'negociacion.oferta.proveedor' => 'Ofertar, contraofertar, aceptar, rechazar y retirar ofertas, y cancelar un servicio adjudicado, desde la app del proveedor',
        'negociacion.oferta.cliente' => 'Comparar las ofertas de sus solicitudes, contraofertar, aceptar y rechazar, y ver dónde está el piloto, desde la app del cliente',
        'negociacion.seguimiento.registrar' => 'Marcar el inicio, el avance y el cierre de un servicio adjudicado y enviar la ubicación del piloto desde la app',
        'negociacion.seguimiento.ver' => 'Ver el avance y las fotos de un servicio propio desde la app',
        'negociacion.evidencia.subir' => 'Subir fotos de avance, finales y de incidencia de un servicio adjudicado desde la app',
        'negociacion.pago.confirmar' => 'Informar que el proveedor recibió el pago de un servicio finalizado, que ocurre fuera de la aplicación',
        'negociacion.precio_final.confirmar' => 'Confirmar como cliente el precio final de un servicio cerrado por el piloto, o abrir una disputa en su lugar',
        'negociacion.mensaje.usar' => 'Leer y escribir mensajes con la otra parte de un servicio adjudicado desde la app',
        'negociacion.preferencias.gestionar' => 'Activar o desactivar los avisos de la cuenta, y las zonas de las nuevas solicitudes, desde la app',
        // Calificaciones (ADR 0031 y 0039): lado app y cola de moderación del panel.
        'calificaciones.calificacion.calificar_proveedor' => 'Calificar al proveedor, al piloto y al auxiliar de un servicio completado desde la app',
        'calificaciones.calificacion.calificar_cliente' => 'Calificar en privado al cliente de un servicio completado desde la app',
        'calificaciones.calificacion.ver_recibidas' => 'Ver las calificaciones que recibe su proveedor desde la app',
        'calificaciones.calificacion.reportar' => 'Reportar desde la app una calificación recibida que considera abusiva',
        'calificaciones.calificacion.ver' => 'Ver las calificaciones y su cola de moderación',
        'calificaciones.calificacion.moderar' => 'Ocultar o restablecer una calificación, con causa y motivo',
        // Disputas (ADR 0033 y 0039): lado app, cola y resolución en el panel.
        'disputas.disputa.gestionar_cliente' => 'Abrir, argumentar, retirar y apelar una disputa de su servicio desde la app del cliente',
        'disputas.disputa.gestionar_proveedor' => 'Abrir, argumentar, retirar y apelar una disputa de su proveedor desde la app',
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
        'negociacion.pago.confirmar',
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
        'negociacion.precio_final.confirmar',
        'negociacion.mensaje.usar',
        'negociacion.preferencias.gestionar',
        'calificaciones.calificacion.calificar_proveedor',
        'disputas.disputa.gestionar_cliente',
    ];

    /**
     * Piloto: lo que hace desde `agrocom-field`. No tiene pantalla del panel.
     *
     * @var list<string>
     */
    private const PERMISOS_PILOTO = [
        'proveedores.cuenta.ver',
        'proveedores.auxiliar.registrar',
        'proveedores.documento.cargar',
        'negociacion.mercado.ver',
        'negociacion.oferta.proveedor',
        'negociacion.seguimiento.registrar',
        'negociacion.seguimiento.ver',
        'negociacion.evidencia.subir',
        'negociacion.pago.confirmar',
        'negociacion.mensaje.usar',
        'negociacion.preferencias.gestionar',
        'calificaciones.calificacion.calificar_cliente',
        'calificaciones.calificacion.ver_recibidas',
        'calificaciones.calificacion.reportar',
        'disputas.disputa.gestionar_proveedor',
    ];

    /**
     * Auxiliar: sus documentos y las fotos del servicio de su equipo. No tiene
     * pantalla del panel.
     *
     * @var list<string>
     */
    private const PERMISOS_AUXILIAR = [
        'proveedores.documento.cargar',
        'negociacion.evidencia.subir',
        'negociacion.seguimiento.ver',
    ];

    /**
     * Administrador del marketplace: aprueba, suspende y modera. Todo salvo
     * gestionar roles, configuración y dueños (esos son del dueño).
     *
     * @var list<string>
     */
    private const PERMISOS_ADMINISTRADOR = [
        'seguridad.dashboard.ver',
        'seguridad.usuario.ver',
        'seguridad.usuario.crear',
        'seguridad.usuario.editar',
        'seguridad.usuario.bloquear',
        'seguridad.usuario.eliminar',
        'seguridad.organizacion.ver',
        'seguridad.organizacion.editar',
        'seguridad.bitacora.ver',
        'seguridad.dispositivo.ver',
        'seguridad.dispositivo.revocar',
        'proveedores.proveedor.ver',
        'proveedores.proveedor.crear',
        'proveedores.proveedor.aprobar',
        'proveedores.proveedor.suspender',
        'proveedores.proveedor.dar_baja',
        'proveedores.piloto.ver',
        'proveedores.piloto.crear',
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
        'flota.dron.crear',
        'flota.dron.aprobar',
        'flota.dron.suspender',
        'flota.dron.dar_baja',
        'flota.dron.revisar_foto',
        'flota.equipo.ver',
        'solicitudes.solicitud.ver',
        'solicitudes.solicitud.cancelar',
        'solicitudes.solicitud.ver_seguimiento',
        'solicitudes.solicitud.intervenir',
        'solicitudes.cliente.ver',
        'solicitudes.cliente.crear',
        'solicitudes.cliente.suspender',
        'solicitudes.zona.ver',
        'solicitudes.zona.gestionar',
        'calificaciones.calificacion.ver',
        'calificaciones.calificacion.moderar',
        'disputas.disputa.ver',
        'disputas.disputa.atender',
        'disputas.disputa.resolver',
        'metricas.panel.ver',
        'notificaciones.aviso.ver',
    ];

    /**
     * Soporte: atiende solicitudes, clientes y disputas; no aprueba proveedores
     * ni drones.
     *
     * @var list<string>
     */
    private const PERMISOS_SOPORTE = [
        'seguridad.dashboard.ver',
        'proveedores.proveedor.ver',
        'proveedores.piloto.ver',
        'proveedores.auxiliar.ver',
        'flota.dron.ver',
        'flota.equipo.ver',
        'solicitudes.solicitud.ver',
        'solicitudes.solicitud.cancelar',
        'solicitudes.solicitud.ver_seguimiento',
        'solicitudes.solicitud.intervenir',
        'solicitudes.cliente.ver',
        'solicitudes.zona.ver',
        'calificaciones.calificacion.ver',
        'disputas.disputa.ver',
        'disputas.disputa.atender',
        'metricas.panel.ver',
    ];

    /**
     * Permisos retirados del catálogo. Una base ya sembrada conserva la fila y
     * sus asignaciones, así que `run()` las da de baja de forma explícita
     * antes de sembrar:
     *
     * - `comercial.campo.*`: `Campo` deja de ser una entidad (ADR 0020).
     * - `finanzas.*`, `inventario.*` y `mantenimiento.*`: el marketplace no lleva
     *   planilla, contabilidad interna, inventario ni mantenimiento de flota
     *   (ADR 0029 y 0035).
     * - `operaciones.(trabajo|sesion|alerta|dron|pausa).*` y `personal.*`: el
     *   panel ya no administra trabajos, sesiones, pausas, alertas, drones
     *   operativos, bases, personal ni cuadrillas; la negociación y el
     *   seguimiento del servicio los ve el administrador en las solicitudes.
     * - `distribucion.version.autorizar`: las apps salen por Play Store y App
     *   Store; el panel ya no sube ni autoriza versiones del APK.
     *
     * @var list<string>
     */
    private const PERMISOS_RETIRADOS = [
        'seguridad.usuario.portal',
        'distribucion.version.autorizar',
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
        'operaciones.trabajo.ver',
        'operaciones.trabajo.eliminar',
        'operaciones.sesion.validar',
        'operaciones.alerta.ver',
        'operaciones.alerta.atender',
        'operaciones.dron.ver',
        'operaciones.dron.crear',
        'operaciones.dron.editar',
        'operaciones.dron.eliminar',
        'operaciones.pausa.ver',
        'operaciones.pausa.registrar',
        'personal.base.ver',
        'personal.base.crear',
        'personal.base.editar',
        'personal.base.eliminar',
        'personal.persona.ver',
        'personal.persona.crear',
        'personal.persona.editar',
        'personal.persona.eliminar',
        'personal.persona.desempenio',
        'personal.equipo_trabajo.ver',
        'personal.equipo_trabajo.crear',
        'personal.equipo_trabajo.editar',
        'personal.equipo_trabajo.eliminar',
        'mantenimiento.bateria.ver',
        'mantenimiento.bateria.crear',
        'mantenimiento.bateria.editar',
        'mantenimiento.bateria.eliminar',
        'mantenimiento.ficha_dron.ver',
        'mantenimiento.ficha_dron.crear',
        'mantenimiento.ficha_dron.editar',
        'mantenimiento.ficha_dron.eliminar',
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

        // Administrador del marketplace y soporte: las pantallas de aprobación,
        // moderación y atención.
        $this->asignar($roles['encargado_operaciones'], $permisos->only(self::PERMISOS_ADMINISTRADOR)->values()->all());
        $this->asignar($roles['soporte'], $permisos->only(self::PERMISOS_SOPORTE)->values()->all());

        // Marketplace (ADR 0036): proveedor, cliente, piloto y auxiliar operan por la API con lo suyo.
        $this->asignar($roles['proveedor'], $permisos->only(self::PERMISOS_PROVEEDOR)->values()->all());
        $this->asignar($roles['cliente'], $permisos->only(self::PERMISOS_CLIENTE)->values()->all());
        $this->asignar($roles['piloto'], $permisos->only(self::PERMISOS_PILOTO)->values()->all());
        $this->asignar($roles['auxiliar'], $permisos->only(self::PERMISOS_AUXILIAR)->values()->all());

        // Piloto y auxiliar ya no entran al tablero del panel: una base ya
        // sembrada conserva ese otorgamiento y hay que quitarlo.
        foreach (['piloto', 'auxiliar'] as $rol) {
            $this->quitarAsignacion($roles[$rol], 'seguridad.dashboard.ver');
        }
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

    /** Quita un otorgamiento puntual (soft delete, por la bitácora de cada fila). */
    private function quitarAsignacion(SecRole $rol, string $code): void
    {
        $permisoId = SecPermission::query()->where('code', $code)->value('id');

        if ($permisoId === null) {
            return;
        }

        SecRolePermission::query()
            ->where('id_role', $rol->id)
            ->where('id_permission', $permisoId)
            ->get()
            ->each(fn (SecRolePermission $asignacion) => $asignacion->delete());
    }
}

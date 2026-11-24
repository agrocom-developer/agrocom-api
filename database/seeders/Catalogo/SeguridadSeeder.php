<?php

namespace Database\Seeders\Catalogo;

use App\Dominios\Seguridad\Dominio\PermisosReservados;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecPermission;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRolePermission;
use Illuminate\Database\Seeder;

/**
 * Catálogo de roles y permisos del marketplace: corre en todos los entornos,
 * producción incluida — sin roles ni permisos no hay con qué autorizar al
 * primer usuario. Sin autor explícito (`created_by`/`updated_by` quedan NULL):
 * es dato de catálogo, lo siembra el sistema.
 *
 * El panel es la torre de control del administrador de la plataforma:
 * `admin_plataforma` y `dueno` reciben todo el catálogo; `encargado_operaciones`
 * administra el día a día (usuarios, aprobaciones, solicitudes, moderación y
 * disputas); `soporte` atiende solicitudes, clientes y disputas sin aprobar ni
 * moderar. `cliente`, `proveedor`, `piloto` y `auxiliar` operan por la API con
 * lo suyo y no tienen pantallas en el panel.
 *
 * Idempotente vía `firstOrCreate`: correrlo de nuevo no duplica filas ni pisa
 * `description`/`state` si ya fueron editados a mano. Sembrar es «otorgar si
 * nunca se otorgó», nunca «reponer lo que alguien quitó a propósito».
 */
class SeguridadSeeder extends Seeder
{
    /** @var array<string, string> */
    private const ROLES = [
        'piloto' => 'Piloto de dron: ofrece, ejecuta los servicios y sube las fotos de avance desde la app.',
        'auxiliar' => 'Auxiliar de campo: apoya al piloto en el lote y puede subir fotos de avance desde la app.',
        'jefe_campo' => 'Jefe de campo: rol de cuentas existentes; el marketplace no le da pantallas en el panel.',
        'encargado_operaciones' => 'Administrador de operaciones: aprueba proveedores y drones, modera calificaciones y resuelve disputas.',
        'dueno' => 'Dueño de Agrocom SRL: acceso total, incluida la gestión de otros dueños.',
        'soporte' => 'Soporte: atiende solicitudes, clientes y disputas; no aprueba ni modera.',
        'cliente' => 'Cliente del marketplace: solicita servicios de fumigación y califica al proveedor.',
        'proveedor' => 'Proveedor del marketplace: gestiona su personal, sus drones y sus equipos desde la app.',
        // `sec_role.description` es `varchar(150)`: el texto cabe justo.
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
        'seguridad.usuario.ver_como' => 'Ver el panel o el portal como otro usuario (solo lectura)',
        'seguridad.rol.ver' => 'Ver el catálogo de roles y qué permisos tiene cada uno',
        'seguridad.rol.crear' => 'Crear un rol nuevo (nace sin permisos)',
        'seguridad.rol.editar' => 'Editar nombre, descripción y estado de un rol',
        'seguridad.rol.eliminar' => 'Dar de baja un rol sin usuarios asignados',
        'seguridad.rol.asignar_permiso' => 'Otorgar y quitar permisos a un rol',
        'seguridad.dashboard.ver' => 'Ver el tablero de la torre de control del marketplace',
        'seguridad.organizacion.ver' => 'Ver el registro de la compañía (Organización)',
        'seguridad.organizacion.editar' => 'Editar los datos fiscales de la empresa (pestaña Facturación)',
        'seguridad.configuracion.ver' => 'Ver la configuración del sistema (llaves y tokens)',
        'seguridad.configuracion.editar' => 'Editar la configuración del sistema (llaves y tokens)',
        'seguridad.bitacora.ver' => 'Ver la bitácora de auditoría (quién hizo qué, cuándo y en qué zona horaria)',
        'seguridad.dispositivo.ver' => 'Ver los dispositivos con sesión abierta en la app de campo',
        'seguridad.dispositivo.revocar' => 'Revocar el acceso de un dispositivo de campo',
        'distribucion.version.autorizar' => 'Autorizar una versión del APK para distribución',
        'operaciones.acta.generar' => 'Generar el acta de conformidad de un trabajo cerrado y validado',
        'operaciones.acta.firmar' => 'Registrar la firma del agrónomo sobre un acta pendiente',
        'operaciones.reporte.ver' => 'Descargar el reporte técnico de un servicio desde la app',
        'personal.persona.ver' => 'Ver el listado de personas',
        'personal.persona.editar' => 'Editar los datos de una persona operativa',
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
        'proveedores.cuenta.gestionar' => 'Gestionar su propio proveedor desde la app: pilotos, drones, equipos y documentos',
        'proveedores.auxiliar.registrar' => 'Registrar a un auxiliar (queda pendiente de aprobación)',
        'proveedores.documento.cargar' => 'Cargar los documentos propios o de lo que uno registró',
        'solicitudes.cliente.gestionar' => 'Gestionar su propio perfil de cliente desde la app',
        'solicitudes.terreno.gestionar' => 'Registrar y consultar sus propios terrenos desde la app',
        'solicitudes.solicitud.gestionar' => 'Crear, consultar, publicar y cancelar sus propias solicitudes de servicio desde la app',
        'solicitudes.solicitud.ver' => 'Ver el listado y la ficha de las solicitudes de servicio',
        'solicitudes.solicitud.cancelar' => 'Cancelar una solicitud de servicio desde el panel, con motivo',
        'solicitudes.solicitud.ver_seguimiento' => 'Ver el chat, las fotos de avance y la ubicación del piloto de una solicitud',
        'negociacion.mercado.ver' => 'Ver el mapa de solicitudes abiertas y su detalle antes de ofertar desde la app',
        'negociacion.oferta.proveedor' => 'Ofertar, contraofertar, aceptar, rechazar y retirar ofertas, y cancelar un servicio adjudicado, desde la app del proveedor',
        'negociacion.oferta.cliente' => 'Comparar las ofertas de sus solicitudes, contraofertar, aceptar y rechazar, y ver dónde está el piloto, desde la app del cliente',
        'negociacion.seguimiento.registrar' => 'Marcar el inicio, el avance y el cierre de un servicio adjudicado y enviar la ubicación del piloto desde la app',
        'negociacion.seguimiento.ver' => 'Ver el avance y las fotos de un servicio propio desde la app',
        'negociacion.evidencia.subir' => 'Subir fotos de avance, finales y de incidencia de un servicio adjudicado desde la app',
        'negociacion.mensaje.usar' => 'Leer y escribir mensajes con la otra parte de un servicio adjudicado desde la app',
        'negociacion.preferencias.gestionar' => 'Activar o desactivar los avisos de la cuenta, y las zonas de las nuevas solicitudes, desde la app',
        'calificaciones.calificacion.calificar_proveedor' => 'Calificar al proveedor, al piloto y al auxiliar de un servicio completado desde la app',
        'calificaciones.calificacion.calificar_cliente' => 'Calificar en privado al cliente de un servicio completado desde la app',
        'calificaciones.calificacion.ver_recibidas' => 'Ver las calificaciones que recibe su proveedor desde la app',
        'calificaciones.calificacion.reportar' => 'Reportar desde la app una calificación recibida que considera abusiva',
        'calificaciones.calificacion.ver' => 'Ver las calificaciones y su cola de moderación',
        'calificaciones.calificacion.moderar' => 'Ocultar o restablecer una calificación, con causa y motivo',
        'disputas.disputa.gestionar_cliente' => 'Abrir, argumentar, retirar y apelar una disputa de su servicio desde la app del cliente',
        'disputas.disputa.gestionar_proveedor' => 'Abrir, argumentar, retirar y apelar una disputa de su proveedor desde la app',
        'disputas.disputa.ver' => 'Ver las disputas, sus mensajes y su resolución',
        'disputas.disputa.atender' => 'Tomar una disputa, pedir información a las partes y mensajear como soporte',
        'disputas.disputa.resolver' => 'Resolver una disputa o su apelación, con resultado, reembolso y motivo',
        'metricas.panel.ver' => 'Ver las métricas del mercado: solicitudes, aceptación, calificación, tiempos de asignación e ingreso',
        'solicitudes.cliente.ver' => 'Ver el listado y la ficha de los clientes del marketplace',
        'solicitudes.cliente.suspender' => 'Suspender a un cliente o reactivarlo, con motivo',
        'solicitudes.zona.ver' => 'Ver las zonas y macrorregiones de Santa Cruz y su demanda',
        'solicitudes.zona.editar' => 'Editar el nombre y la macrorregión de una zona',
        'notificaciones.aviso.ver' => 'Ver los avisos que el sistema envía a clientes, proveedores y administradores',
    ];

    /**
     * Permisos que el catálogo entrega únicamente a `admin_plataforma`, no a
     * `dueno`. Tampoco se pueden otorgar a otro rol desde la matriz de
     * permisos: la lista es la de `PermisosReservados`.
     *
     * @var list<string>
     */
    private const PERMISOS_SOLO_ADMIN_PLATAFORMA = PermisosReservados::SOLO_ADMIN_PLATAFORMA;

    /** @var list<string> Proveedores, pilotos, auxiliares, documentos y flota. */
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

    /** @var list<string> Solicitudes, clientes y zonas. */
    private const PERMISOS_ADMINISTRACION_SOLICITUDES = [
        'solicitudes.solicitud.ver',
        'solicitudes.solicitud.cancelar',
        'solicitudes.solicitud.ver_seguimiento',
        'solicitudes.cliente.ver',
        'solicitudes.cliente.suspender',
        'solicitudes.zona.ver',
        'solicitudes.zona.editar',
    ];

    /** @var list<string> Calificaciones, disputas, métricas y avisos. */
    private const PERMISOS_ADMINISTRACION_POSTVENTA = [
        'calificaciones.calificacion.ver',
        'calificaciones.calificacion.moderar',
        'disputas.disputa.ver',
        'disputas.disputa.atender',
        'disputas.disputa.resolver',
        'metricas.panel.ver',
        'notificaciones.aviso.ver',
    ];

    /** @var list<string> Usuarios, organización, bitácora y dispositivos. */
    private const PERMISOS_ADMINISTRACION_CUENTAS = [
        'seguridad.usuario.ver',
        'seguridad.usuario.crear',
        'seguridad.usuario.editar',
        'seguridad.usuario.bloquear',
        'seguridad.usuario.eliminar',
        'seguridad.dashboard.ver',
        'seguridad.organizacion.ver',
        'seguridad.organizacion.editar',
        'seguridad.bitacora.ver',
        'seguridad.dispositivo.ver',
        'seguridad.dispositivo.revocar',
        'personal.persona.ver',
        'personal.persona.editar',
    ];

    /** @var list<string> */
    private const PERMISOS_SOPORTE = [
        'seguridad.dashboard.ver',
        'solicitudes.solicitud.ver',
        'solicitudes.solicitud.ver_seguimiento',
        'solicitudes.solicitud.cancelar',
        'solicitudes.cliente.ver',
        'solicitudes.zona.ver',
        'calificaciones.calificacion.ver',
        'disputas.disputa.ver',
        'disputas.disputa.atender',
        'metricas.panel.ver',
        'notificaciones.aviso.ver',
        'proveedores.proveedor.ver',
        'proveedores.piloto.ver',
        'proveedores.auxiliar.ver',
        'flota.dron.ver',
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

    /** @var list<string> */
    private const PERMISOS_PILOTO = [
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
        'operaciones.acta.generar',
        'operaciones.acta.firmar',
    ];

    /** @var list<string> */
    private const PERMISOS_AUXILIAR = [
        'proveedores.documento.cargar',
        'negociacion.evidencia.subir',
        'negociacion.seguimiento.ver',
    ];

    /** @var list<string> Lo que sigue usando la API del lote (acta y reporte). */
    private const PERMISOS_JEFE_CAMPO = [
        'operaciones.acta.generar',
        'operaciones.acta.firmar',
        'operaciones.reporte.ver',
    ];

    /**
     * Permisos que ya no existen en el catálogo. Una base ya sembrada conserva
     * la fila y sus asignaciones, así que `run()` las da de baja de forma
     * explícita antes de sembrar (soft delete, fila por fila para que cada baja
     * pase por la bitácora).
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
        'personal.persona.crear',
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

        // dueno: todo el catálogo, salvo lo que es solo de la plataforma.
        $this->asignar(
            $roles['dueno'],
            $permisos->except(self::PERMISOS_SOLO_ADMIN_PLATAFORMA)->values()->all(),
        );

        // admin_plataforma: todo el catálogo, sin excepción.
        $this->asignar($roles['admin_plataforma'], $permisos->values()->all());

        $this->asignar(
            $roles['encargado_operaciones'],
            $permisos->only([
                ...self::PERMISOS_ADMINISTRACION_CUENTAS,
                ...self::PERMISOS_ADMINISTRACION_PROVEEDORES,
                ...self::PERMISOS_ADMINISTRACION_SOLICITUDES,
                ...self::PERMISOS_ADMINISTRACION_POSTVENTA,
            ])->values()->all(),
        );

        $this->asignar($roles['soporte'], $permisos->only(self::PERMISOS_SOPORTE)->values()->all());
        $this->asignar($roles['proveedor'], $permisos->only(self::PERMISOS_PROVEEDOR)->values()->all());
        $this->asignar($roles['cliente'], $permisos->only(self::PERMISOS_CLIENTE)->values()->all());
        $this->asignar($roles['piloto'], $permisos->only(self::PERMISOS_PILOTO)->values()->all());
        $this->asignar($roles['auxiliar'], $permisos->only(self::PERMISOS_AUXILIAR)->values()->all());
        $this->asignar($roles['jefe_campo'], $permisos->only(self::PERMISOS_JEFE_CAMPO)->values()->all());
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
     * `withTrashed()`, no `query()`: una fila soft-deleteada (alguien quitó el
     * permiso desde el panel) YA EXISTE para este par rol/permiso. Contar solo
     * las vivas volvería a insertar el otorgamiento en cada corrida, porque el
     * índice único es parcial (`WHERE deleted_at IS NULL`) y no lo impide.
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
     * Da de baja un permiso retirado y cada asignación que lo referencia, una
     * por una (no un `update()` masivo) para que cada `delete()` pase por el
     * observer de bitácora. Idempotente: un permiso ya borrado, o que nunca
     * existió, no se encuentra y no hace nada.
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

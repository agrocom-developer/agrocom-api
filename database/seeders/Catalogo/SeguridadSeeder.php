<?php

namespace Database\Seeders\Catalogo;

use App\Dominios\Seguridad\Dominio\PermisosReservados;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecPermission;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRole;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecRolePermission;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserRole;
use Illuminate\Database\Seeder;

/**
 * Catálogo de roles y permisos (HU-01, diseño `modulos-roles` §2): corre en
 * todos los entornos, producción incluida — sin roles ni permisos no hay con
 * qué autorizar al primer usuario.
 *
 * Roles del panel (ADR 0046, punto 4): solo `administrador` (todos los
 * permisos del panel, siempre) y `operador` (su preset de solo lectura; el
 * resto lo marca el administrador en la matriz). Los roles `cliente`,
 * `proveedor` y `piloto` son de ámbito `app`: TRANSITORIOS hasta la fase A5,
 * porque el token del dispositivo aún resuelve permisos por ellos; sus
 * permisos salen de `config/permisos_app.php`.
 *
 * Sin autor explícito (`created_by`/`updated_by` quedan NULL): a diferencia
 * del seeder Demo (que sí asigna un usuario autor porque simula un flujo de
 * negocio), esto es dato de catálogo — nadie "creó" el rol `administrador`, lo
 * sembró el sistema antes de que exista ningún usuario.
 *
 * Idempotente vía `firstOrCreate`: correrlo de nuevo no duplica filas ni
 * pisa `description`/`state` si ya fueron editados a mano. La excepción es el
 * administrador, cuya matriz no se edita: cada corrida le devuelve todo el
 * catálogo del panel.
 */
class SeguridadSeeder extends Seeder
{
    /** @var array<string, string> */
    private const ROLES_DEL_PANEL = [
        'administrador' => 'Administrador del panel: ve y hace todo, incluidos usuarios, roles y configuración.',
        'operador' => 'Operador del panel: ve y hace lo que el administrador marca en la matriz de este rol.',
    ];

    /**
     * Roles de app: transitorios hasta la fase A5 (el token aún resuelve
     * permisos por rol). No tienen ninguna pantalla del panel.
     *
     * @var array<string, string>
     */
    private const ROLES_DE_APP = [
        'piloto' => 'Piloto de dron: ofrece, ejecuta los servicios adjudicados y sube las fotos desde la app.',
        'cliente' => 'Cliente del marketplace: solicita servicios de fumigación y califica al proveedor.',
        'proveedor' => 'Proveedor del marketplace: gestiona su personal, sus drones y sus equipos desde la app.',
    ];

    /** @var array<string, string> */
    private const PERMISOS = [
        // Seguridad: cuentas, roles, dispositivos, organización, configuración y bitácora.
        'seguridad.usuario.ver' => 'Ver el listado y la ficha de los usuarios',
        'seguridad.usuario.crear' => 'Crear una cuenta del panel y asignarle su rol (solo el administrador)',
        'seguridad.usuario.editar' => 'Editar los datos y el rol de una cuenta del panel (solo el administrador)',
        'seguridad.usuario.bloquear' => 'Bloquear o desbloquear el acceso de un usuario (no lo elimina)',
        'seguridad.usuario.eliminar' => 'Eliminar un usuario',
        'seguridad.usuario.asignar_rol_administrador' => 'Asignar o quitar el rol administrador a una cuenta del panel',
        'seguridad.usuario.ver_como' => 'Ver el panel como una cuenta de panel (solo lectura, solo el administrador)',
        'seguridad.rol.ver' => 'Ver el catálogo de roles y qué permisos tiene cada uno',
        'seguridad.rol.crear' => 'Crear un rol nuevo (nace sin permisos)',
        'seguridad.rol.editar' => 'Editar nombre, descripción y estado de un rol',
        'seguridad.rol.eliminar' => 'Eliminar un rol sin usuarios asignados',
        'seguridad.rol.asignar_permiso' => 'Otorgar y quitar permisos a un rol',
        'seguridad.dashboard.ver' => 'Ver el tablero del mercado',
        'seguridad.organizacion.ver' => 'Ver los datos de la empresa (Organización)',
        'seguridad.organizacion.editar' => 'Editar los datos fiscales de la empresa (pestaña Facturación)',
        'seguridad.configuracion.ver' => 'Ver la configuración del sistema (claves y tokens)',
        'seguridad.configuracion.editar' => 'Editar la configuración del sistema (claves y tokens)',
        'seguridad.bitacora.ver' => 'Ver la bitácora de auditoría (quién hizo qué, cuándo y en qué zona horaria)',
        // Proveedores y flota en el panel (ADR 0032): aprobar, suspender y dar de baja.
        'proveedores.proveedor.crear' => 'Registrar a mano a un proveedor que llega a la oficina (queda pendiente; quien lo registra no lo aprueba)',
        'proveedores.piloto.crear' => 'Registrar a mano a un piloto de un proveedor tercero (queda pendiente)',
        'flota.dron.crear' => 'Registrar a mano el dron de un proveedor (queda pendiente)',
        'proveedores.proveedor.ver' => 'Ver el listado y la ficha de proveedores',
        'proveedores.proveedor.aprobar' => 'Aprobar o rechazar a un proveedor y rehabilitar a uno suspendido',
        'proveedores.proveedor.suspender' => 'Suspender a un proveedor, con motivo',
        'proveedores.proveedor.dar_baja' => 'Eliminar a un proveedor, con motivo',
        'proveedores.piloto.ver' => 'Ver el listado y la ficha de pilotos',
        'proveedores.piloto.aprobar' => 'Aprobar o rechazar a un piloto y rehabilitar a uno suspendido',
        'proveedores.piloto.suspender' => 'Suspender a un piloto, con motivo',
        'proveedores.piloto.dar_baja' => 'Eliminar a un piloto, con motivo',
        'proveedores.documento.ver' => 'Ver los documentos por revisar y descargar su archivo (acceso restringido)',
        'proveedores.documento.revisar' => 'Aprobar o rechazar un documento, con motivo en el rechazo',
        'flota.dron.ver' => 'Ver el listado y la ficha de drones del marketplace',
        'flota.dron.aprobar' => 'Aprobar o rechazar un dron y rehabilitar a uno suspendido',
        'flota.dron.suspender' => 'Suspender un dron, con motivo',
        'flota.dron.dar_baja' => 'Eliminar un dron, con motivo',
        'flota.dron.revisar_foto' => 'Aprobar o rechazar las fotos de un dron, con motivo en el rechazo',
        // Marketplace, lado app (token de dispositivo): lo que cada rol hace sobre lo SUYO. El alcance lo hacen los casos de uso; el permiso es la puerta.
        'proveedores.cuenta.gestionar' => 'Gestionar su propio proveedor desde la app: pilotos, drones, equipos y documentos',
        'proveedores.cuenta.ver' => 'Ver, solo lectura, el proveedor al que pertenece y los drones de sus equipos desde la app (piloto)',
        'proveedores.documento.cargar' => 'Cargar los documentos propios o de lo que uno registró',
        'solicitudes.cliente.gestionar' => 'Gestionar su propio perfil de cliente desde la app',
        'solicitudes.terreno.gestionar' => 'Registrar y consultar sus propios terrenos desde la app',
        'solicitudes.solicitud.gestionar' => 'Crear, consultar, publicar y cancelar sus propias solicitudes de servicio desde la app',
        // Solicitudes y clientes en el panel (ADR 0027): consulta, cancelación e intervención con motivo.
        'solicitudes.solicitud.ver' => 'Ver el listado y la ficha de las solicitudes de servicio',
        'solicitudes.solicitud.cancelar' => 'Cancelar una solicitud de servicio desde el panel, con motivo',
        'solicitudes.cliente.crear' => 'Registrar a mano a un cliente (persona o empresa con sus contactos) que llega a la oficina',
        'solicitudes.cliente.ver' => 'Ver el listado y la ficha de los clientes del marketplace',
        'solicitudes.cliente.aprobar' => 'Aprobar o rechazar a un cliente pendiente, con motivo en el rechazo',
        'solicitudes.cliente.suspender' => 'Suspender a un cliente o reactivarlo, con motivo',
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
        'negociacion.precio_final.confirmar' => 'Confirmar como cliente el precio final de un servicio cerrado por el piloto, o abrir un reclamo en su lugar',
        'negociacion.mensaje.usar' => 'Leer y escribir mensajes con la otra parte de un servicio adjudicado desde la app',
        'negociacion.preferencias.gestionar' => 'Activar o desactivar los avisos de la cuenta, y las nuevas solicitudes, desde la app',
        // Calificaciones (ADR 0031 y 0039): lado app y cola de moderación del panel.
        'calificaciones.calificacion.calificar_proveedor' => 'Calificar al proveedor y al piloto de un servicio completado desde la app',
        'calificaciones.calificacion.calificar_cliente' => 'Calificar en privado al cliente de un servicio completado desde la app',
        'calificaciones.calificacion.ver_recibidas' => 'Ver las calificaciones que recibe su proveedor desde la app',
        'calificaciones.calificacion.reportar' => 'Reportar desde la app una calificación recibida que considera abusiva',
        'calificaciones.calificacion.ver' => 'Ver las calificaciones y las que esperan moderación',
        'calificaciones.calificacion.moderar' => 'Ocultar o restablecer una calificación, con causa y motivo',
        // Disputas (ADR 0033 y 0039): lado app, cola y resolución en el panel.
        'disputas.disputa.gestionar_cliente' => 'Abrir, argumentar, retirar y apelar un reclamo de su servicio desde la app del cliente',
        'disputas.disputa.gestionar_proveedor' => 'Abrir, argumentar, retirar y apelar un reclamo de su proveedor desde la app',
        'disputas.disputa.ver' => 'Ver los reclamos, sus mensajes y su resolución',
        'disputas.disputa.atender' => 'Atender un reclamo, pedir información a las partes y escribirles como soporte',
        'disputas.disputa.resolver' => 'Resolver un reclamo o su apelación, con resultado, reembolso y motivo',
        // Métricas del mercado en el panel (especificación §14).
        'metricas.panel.ver' => 'Ver las métricas del mercado: solicitudes, aceptación, calificación, tiempos de adjudicación e ingresos',
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
     * - `seguridad.dispositivo.*` y `flota.equipo.ver`: el panel ya no tiene las
     *   páginas Dispositivos ni Equipos; el token por dispositivo del login y el
     *   equipo (piloto + dron) de la solicitud siguen en la API.
     * - `proveedores.auxiliar.*`: el auxiliar ya no existe en el sistema.
     * - `seguridad.usuario.asignar_rol_dueno`: el rol `dueno` sale del panel;
     *   lo reemplaza `seguridad.usuario.asignar_rol_administrador` (ADR 0046).
     * - `solicitudes.zona.*`: el mercado no tiene zonas (ADR 0045, punto 1); la
     *   pantalla Zonas y el catálogo se retiraron.
     *
     * @var list<string>
     */
    private const PERMISOS_RETIRADOS = [
        'solicitudes.zona.ver',
        'solicitudes.zona.gestionar',
        'seguridad.usuario.asignar_rol_dueno',
        'seguridad.usuario.portal',
        'distribucion.version.autorizar',
        'seguridad.dispositivo.ver',
        'seguridad.dispositivo.revocar',
        'flota.equipo.ver',
        'proveedores.auxiliar.ver',
        'proveedores.auxiliar.aprobar',
        'proveedores.auxiliar.suspender',
        'proveedores.auxiliar.dar_baja',
        'proveedores.auxiliar.registrar',
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

    /**
     * Roles retirados del catálogo. Una base ya sembrada conserva la fila del
     * rol, sus permisos y sus asignaciones a usuarios, así que `run()` las da
     * de baja de forma explícita (soft delete, fila por fila):
     *
     * - `auxiliar`: el auxiliar ya no es un rol del sistema (ni usuario móvil ni
     *   cuenta del panel).
     * - `dueno`, `encargado_operaciones`, `jefe_campo`, `soporte`,
     *   `admin_plataforma` y `operario`: el panel tiene dos roles,
     *   `administrador` y `operador` (ADR 0046, punto 4). La migración
     *   `dos_roles_de_panel_y_ambito_de_roles_y_permisos` ya pasó a
     *   `administrador` a las cuentas que los tenían; esto cubre una base que
     *   los trae de nuevo (un restablecimiento, un seeder previo).
     *
     * @var list<string>
     */
    private const ROLES_RETIRADOS = [
        'auxiliar',
        'dueno',
        'encargado_operaciones',
        'jefe_campo',
        'soporte',
        'admin_plataforma',
        'operario',
    ];

    public function run(): void
    {
        foreach (self::PERMISOS_RETIRADOS as $codigoRetirado) {
            $this->retirarPermiso($codigoRetirado);
        }

        $permisos = collect(self::PERMISOS)->mapWithKeys(
            fn (string $description, string $code) => [$code => $this->permiso($code, $description)],
        );

        $roles = collect(self::ROLES_DEL_PANEL)->mapWithKeys(
            fn (string $description, string $name) => [$name => $this->rol($name, $description, 'panel')],
        )->merge(collect(self::ROLES_DE_APP)->mapWithKeys(
            fn (string $description, string $name) => [$name => $this->rol($name, $description, 'app')],
        ));

        // Los retirados se dan de baja con sus cuentas ya pasadas a administrador
        // (por la migración); si una base trae cuentas con un rol retirado y
        // todavía sin administrador, se las pasa antes de dar de baja el rol.
        foreach (self::ROLES_RETIRADOS as $nombreRetirado) {
            $this->retirarRol($nombreRetirado, $roles['administrador']);
        }

        // Administrador: todo el catálogo del panel, siempre. Su matriz no se
        // edita (AsignarPermisosRol lo rechaza), así que cada corrida repone lo que falte.
        $this->asignarTodo(
            $roles['administrador'],
            $permisos->filter(fn (SecPermission $permiso, string $codigo): bool => ! $this->esDeApp($codigo))->values()->all(),
        );

        // Operador: su preset de solo lectura, una sola vez; después manda la matriz.
        $this->asignar($roles['operador'], $permisos->only(PermisosReservados::PRESET_OPERADOR)->values()->all());

        // Roles de app (transitorios hasta A5): sus permisos salen de config/permisos_app.php.
        foreach (self::ROLES_DE_APP as $nombre => $_) {
            $this->asignar($roles[$nombre], $permisos->only(config("permisos_app.{$nombre}", []))->values()->all());
        }

        // El piloto no entra al tablero del panel: una base ya sembrada
        // conserva ese otorgamiento y hay que quitarlo.
        $this->quitarAsignacion($roles['piloto'], 'seguridad.dashboard.ver');
    }

    /** Si el código lo ejerce una cuenta de app (config/permisos_app.php). */
    private function esDeApp(string $codigo): bool
    {
        foreach ((array) config('permisos_app', []) as $codigos) {
            if (in_array($codigo, $codigos, true)) {
                return true;
            }
        }

        return false;
    }

    private function rol(string $name, string $description, string $ambito): SecRole
    {
        $rol = SecRole::query()->firstOrCreate(
            ['name' => $name],
            ['description' => $description, 'ambito' => $ambito, 'state' => true],
        );

        // El ámbito es del catálogo, no editable: se corrige en una base que ya traía el rol.
        if ($rol->ambito !== $ambito) {
            $rol->ambito = $ambito;
            $rol->save();
        }

        return $rol;
    }

    private function permiso(string $code, string $description): SecPermission
    {
        $ambito = $this->esDeApp($code) ? 'app' : 'panel';

        $permiso = SecPermission::query()->firstOrCreate(
            ['code' => $code],
            ['description' => $description, 'ambito' => $ambito, 'state' => true],
        );

        if ($permiso->ambito !== $ambito) {
            $permiso->ambito = $ambito;
            $permiso->save();
        }

        return $permiso;
    }

    /**
     * Otorga TODOS los permisos dados, también los que alguien quitó (fila
     * dada de baja): es el rol cuya matriz no se edita, así que "sembrar" es
     * "reponer".
     *
     * @param  list<SecPermission>  $permisos
     */
    private function asignarTodo(SecRole $rol, array $permisos): void
    {
        foreach ($permisos as $permiso) {
            $previo = SecRolePermission::withTrashed()
                ->where('id_role', $rol->id)
                ->where('id_permission', $permiso->id)
                ->first();

            if ($previo === null) {
                (new SecRolePermission(['id_role' => $rol->id, 'id_permission' => $permiso->id]))->save();

                continue;
            }

            if ($previo->trashed()) {
                $previo->restore();
            }
        }
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

    /**
     * Da de baja un rol y lo que cuelga de él: sus permisos asignados y sus
     * asignaciones a usuarios (soft delete, una por una para que cada
     * `delete()` pase por el observer de bitácora). Idempotente: si el rol ya
     * no existe, no hace nada.
     */
    private function retirarRol(string $name, SecRole $administrador): void
    {
        $rol = SecRole::query()->where('name', $name)->first();

        if ($rol === null) {
            return;
        }

        // Las cuentas del panel que lo tenían pasan a administrador antes de la baja del rol.
        if ($name !== 'auxiliar') {
            SecUserRole::query()
                ->where('id_role', $rol->id)
                ->pluck('id_user')
                ->each(function (int|string $idUsuario) use ($administrador): void {
                    $tiene = SecUserRole::withTrashed()
                        ->where('id_user', $idUsuario)
                        ->where('id_role', $administrador->id)
                        ->whereNull('deleted_at')
                        ->exists();

                    if (! $tiene) {
                        (new SecUserRole(['id_user' => (int) $idUsuario, 'id_role' => $administrador->id]))->save();
                    }
                });
        }

        SecRolePermission::query()
            ->where('id_role', $rol->id)
            ->get()
            ->each(fn (SecRolePermission $asignacion) => $asignacion->delete());

        SecUserRole::query()
            ->where('id_role', $rol->id)
            ->get()
            ->each(fn (SecUserRole $asignacion) => $asignacion->delete());

        $rol->delete();
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

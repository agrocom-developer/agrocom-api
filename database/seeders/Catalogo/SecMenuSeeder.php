<?php

namespace Database\Seeders\Catalogo;

use App\Dominios\Seguridad\Infraestructura\Eloquent\SecMenu;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecPermission;
use Illuminate\Database\Seeder;

/**
 * Catálogo del menú de tres niveles del panel: módulos raíz (riel de nivel 1)
 * con sus ítems hijos (sidebar de nivel 2). El panel es la torre de control
 * del administrador del marketplace (`docs/panel/torre-de-control.md`):
 *
 * - Torre de control: tablero, solicitudes, disputas, calificaciones y métricas.
 * - Proveedores: proveedores, pilotos, auxiliares, drones, equipos y documentos.
 * - Clientes y zonas: clientes, zonas de Santa Cruz y avisos del sistema.
 * - Seguridad: usuarios, roles, dispositivos, organización, versiones del APK,
 *   configuración y bitácora.
 *
 * Corre en todos los entornos, producción incluida, y depende de
 * `SeguridadSeeder` (necesita `sec_permission` sembrado). Sin autor explícito
 * (`created_by`/`updated_by` NULL): dato de catálogo.
 *
 * `label`/`descripcion` guardan CLAVES de `lang/es/menu.php` (ADR 0013) y `ruta`
 * guarda nombres de ruta Laravel. Cada ítem va gateado por el permiso de
 * LISTADO (`ver`); los permisos de acción rigen los botones dentro de cada
 * pantalla, no la visibilidad del ítem.
 *
 * Idempotente: `firstOrCreate` por (label, padre) y sincronización de ícono,
 * orden, ruta y permiso. Lo que ya no pertenece al menú se da de baja con
 * soft delete (invariante 8), una fila por vez para que pase por la bitácora.
 */
class SecMenuSeeder extends Seeder
{
    /** @var list<string> Entradas que ya no forman parte del menú del panel. */
    private const RETIRADAS = [
        'menu.operacion.items.orden_trabajo',
        'menu.operacion.items.cuadrillas',
        'menu.operacion.items.seguimiento_vuelos',
        'menu.operacion.items.programacion',
        'menu.operacion.items.trabajos',
        'menu.operacion.items.sesiones',
        'menu.operacion.items.pausas',
        'menu.recursos.items.drones',
        'menu.recursos.items.baterias',
        'menu.recursos.items.bases',
        'menu.recursos.items.personal',
        'menu.recursos.items.personas',
        'menu.recursos.items.fichas_dron',
        'menu.recursos.label',
        'menu.reportes.items.tecnicos',
        'menu.reportes.items.alertas',
        'menu.reportes.label',
        'menu.solicitudes.items.solicitudes',
        'menu.solicitudes.items.calificaciones',
        'menu.solicitudes.items.disputas',
        'menu.solicitudes.items.metricas',
        'menu.solicitudes.label',
    ];

    public function run(): void
    {
        $this->retirarEntradas(self::RETIRADAS);

        $torre = $this->modulo('operacion', 'flight_takeoff', 1);
        $proveedores = $this->modulo('proveedores', 'local_shipping', 2);
        $directorio = $this->modulo('directorio', 'groups', 3);
        $seguridad = $this->modulo('seguridad', 'admin_panel_settings', 4);

        // Torre de control
        $this->item($torre, 'operacion', 'tablero', 'dashboard', 1, ruta: 'panel.dashboard', codigoPermiso: 'seguridad.dashboard.ver');
        $this->item($torre, 'operacion', 'solicitudes', 'request_quote', 2, ruta: 'panel.solicitudes.index', codigoPermiso: 'solicitudes.solicitud.ver');
        $this->item($torre, 'operacion', 'disputas', 'report', 3, ruta: 'panel.disputas.index', codigoPermiso: 'disputas.disputa.ver');
        $this->item($torre, 'operacion', 'calificaciones', 'star', 4, ruta: 'panel.calificaciones.index', codigoPermiso: 'calificaciones.calificacion.ver');
        $this->item($torre, 'operacion', 'metricas', 'insights', 5, ruta: 'panel.metricas.index', codigoPermiso: 'metricas.panel.ver');

        // Proveedores: documentos va primero, es la cola que más se mira a diario.
        $this->item($proveedores, 'proveedores', 'documentos', 'folder_open', 1, ruta: 'panel.documentos.index', codigoPermiso: 'proveedores.documento.ver');
        $this->item($proveedores, 'proveedores', 'proveedores', 'local_shipping', 2, ruta: 'panel.proveedores.index', codigoPermiso: 'proveedores.proveedor.ver');
        $this->item($proveedores, 'proveedores', 'pilotos', 'sports_esports', 3, ruta: 'panel.pilotos.index', codigoPermiso: 'proveedores.piloto.ver');
        $this->item($proveedores, 'proveedores', 'auxiliares', 'support_agent', 4, ruta: 'panel.auxiliares.index', codigoPermiso: 'proveedores.auxiliar.ver');
        $this->item($proveedores, 'proveedores', 'drones_flota', 'airplanemode_active', 5, ruta: 'panel.drones-flota.index', codigoPermiso: 'flota.dron.ver');
        $this->item($proveedores, 'proveedores', 'equipos_flota', 'groups', 6, ruta: 'panel.equipos-flota.index', codigoPermiso: 'flota.equipo.ver');

        // Clientes y zonas
        $this->item($directorio, 'directorio', 'clientes', 'agriculture', 1, ruta: 'panel.clientes.index', codigoPermiso: 'solicitudes.cliente.ver');
        $this->item($directorio, 'directorio', 'zonas', 'map', 2, ruta: 'panel.zonas.index', codigoPermiso: 'solicitudes.zona.ver');
        $this->item($directorio, 'directorio', 'avisos', 'notifications_active', 3, ruta: 'panel.avisos.index', codigoPermiso: 'notificaciones.aviso.ver');

        // Seguridad
        $this->item($seguridad, 'seguridad', 'usuarios', 'group', 1, ruta: 'panel.usuarios.index', codigoPermiso: 'seguridad.usuario.ver');
        $this->item($seguridad, 'seguridad', 'roles', 'shield_person', 2, ruta: 'panel.roles.index', codigoPermiso: 'seguridad.rol.ver');
        $this->item($seguridad, 'seguridad', 'dispositivos', 'smartphone', 3, ruta: 'panel.dispositivos.index', codigoPermiso: 'seguridad.dispositivo.ver');
        $this->item($seguridad, 'seguridad', 'organizacion', 'apartment', 4, ruta: 'panel.organizacion.index', codigoPermiso: 'seguridad.organizacion.ver');
        $this->item($seguridad, 'seguridad', 'versiones_apk', 'system_update', 5, ruta: 'panel.versiones-apk.index', codigoPermiso: 'distribucion.version.autorizar');
        // Llaves y tokens de infraestructura: solo el dueño y la plataforma.
        $this->item($seguridad, 'seguridad', 'configuracion', 'tune', 7, ruta: 'panel.configuracion.index', codigoPermiso: 'seguridad.configuracion.ver');
        $this->item($seguridad, 'seguridad', 'bitacora', 'history', 8, ruta: 'panel.bitacora.index', codigoPermiso: 'seguridad.bitacora.ver');
    }

    /**
     * Da de baja (soft delete) las entradas del menú con esas claves de
     * `lang/es/menu.php`, una por una para que cada `delete()` pase por el
     * observer de bitácora.
     *
     * @param  list<string>  $labels
     */
    private function retirarEntradas(array $labels): void
    {
        SecMenu::query()
            ->whereIn('label', $labels)
            ->get()
            ->each(fn (SecMenu $entrada) => $entrada->delete());
    }

    private function modulo(string $clave, string $icono, int $orden): SecMenu
    {
        $fila = SecMenu::query()->firstOrCreate(
            ['label' => "menu.{$clave}.label", 'padre_id' => null],
            [
                'descripcion' => "menu.{$clave}.descripcion",
                'icono' => $icono,
                'ruta' => null,
                'orden' => $orden,
                'permission_id' => null,
            ],
        );

        if ($fila->orden !== $orden || $fila->icono !== $icono) {
            $fila->orden = $orden;
            $fila->icono = $icono;
            $fila->save();
        }

        return $fila;
    }

    /**
     * `firstOrCreate` para la fila y sincronización de ícono, orden, ruta y
     * permiso: este seeder es la única fuente del menú, así que pisar esos
     * campos no descarta ninguna edición de nadie.
     */
    private function item(
        SecMenu $padre,
        string $claveModulo,
        string $claveItem,
        string $icono,
        int $orden,
        ?string $ruta = null,
        ?string $codigoPermiso = null,
        bool $requierePersona = false,
    ): SecMenu {
        $permissionId = $codigoPermiso === null
            ? null
            : SecPermission::query()->where('code', $codigoPermiso)->value('id');

        $fila = SecMenu::query()->firstOrCreate(
            ['label' => "menu.{$claveModulo}.items.{$claveItem}", 'padre_id' => $padre->id],
            [
                'icono' => $icono,
                'ruta' => $ruta,
                'orden' => $orden,
                'permission_id' => $permissionId,
                'requiere_persona' => $requierePersona,
            ],
        );

        if (
            $fila->ruta !== $ruta
            || $fila->permission_id !== $permissionId
            || $fila->requiere_persona !== $requierePersona
            || $fila->orden !== $orden
            || $fila->icono !== $icono
        ) {
            $fila->ruta = $ruta;
            $fila->permission_id = $permissionId;
            $fila->requiere_persona = $requierePersona;
            $fila->orden = $orden;
            $fila->icono = $icono;
            $fila->save();
        }

        return $fila;
    }
}

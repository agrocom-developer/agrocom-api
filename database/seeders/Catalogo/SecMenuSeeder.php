<?php

namespace Database\Seeders\Catalogo;

use App\Dominios\Seguridad\Infraestructura\Eloquent\SecMenu;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecPermission;
use Illuminate\Database\Seeder;

/**
 * Catálogo del menú de tres niveles del panel (módulos raíz en el riel de nivel
 * 1, ítems hijos en el sidebar de nivel 2), según
 * `docs/especificacion/marketplace.md` §10.4: Inicio (acceso directo), Mercado, Proveedores,
 * Atención, Seguridad y Sistema. El mercado de fumigación es de negociación: no hay trabajos
 * internos, cuadrillas, bases, personal propio, baterías ni fichas de dron.
 *
 * Corre en todos los entornos, producción incluida — igual que
 * `SeguridadSeeder`, del que depende (necesita `sec_permission` sembrado).
 * Sin autor explícito (`created_by`/`updated_by` NULL): dato de catálogo.
 *
 * `label`/`descripcion` guardan CLAVES de `lang/es/menu.php` (ADR 0013).
 * `ruta` guarda nombres de ruta Laravel.
 *
 * Idempotente: `firstOrCreate` sobre `(label, padre_id)`. Una base ya sembrada
 * recibe el tablero movido a Mercado y las entradas retiradas dadas de baja
 * (soft delete, invariante 8): `Seeder::run()` nunca borra lo que ya no siembra.
 */
class SecMenuSeeder extends Seeder
{
    /** Entradas de módulos y pantallas que el marketplace no tiene (hojas primero, raíces después). */
    private const RETIRADAS = [
        // Operación interna: trabajos, sesiones, pausas, cuadrillas.
        'menu.operacion.items.orden_trabajo',
        'menu.operacion.items.trabajos',
        'menu.operacion.items.cuadrillas',
        'menu.operacion.items.seguimiento_vuelos',
        'menu.operacion.items.sesiones',
        'menu.operacion.items.pausas',
        'menu.operacion.items.mezclas',
        'menu.operacion.items.evidencias',
        'menu.operacion.items.ordenes',
        'menu.operacion.items.estadias',
        'menu.operacion.items.programacion',
        // Recursos propios: drones operativos, baterías, bases, personal, fichas.
        'menu.recursos.items.drones',
        'menu.recursos.items.baterias',
        'menu.recursos.items.bases',
        'menu.recursos.items.personal',
        'menu.recursos.items.personas',
        'menu.recursos.items.fichas_dron',
        'menu.recursos.items.vehiculos',
        'menu.recursos.items.generadores',
        'menu.recursos.items.equipos_trabajo',
        // Reportes técnicos y alertas operativas.
        'menu.reportes.items.tecnicos',
        'menu.reportes.items.alertas',
        'menu.reportes.items.comerciales',
        // Mantenimiento, finanzas y comercial retirados antes.
        'menu.mantenimiento.items.ordenes',
        'menu.mantenimiento.items.planes',
        'menu.mantenimiento.items.repuestos',
        'menu.mantenimiento.items.stock',
        'menu.financiero.items.gastos',
        'menu.financiero.items.combustible',
        'menu.financiero.items.rendiciones',
        'menu.financiero.items.devengos',
        'menu.financiero.items.planilla',
        'menu.financiero.items.anticipos',
        'menu.financiero.items.tarifas',
        'menu.financiero.items.facturas',
        'menu.comercial.items.campanias',
        'menu.comercial.items.clientes',
        'menu.comercial.items.contratos',
        'menu.comercial.items.propiedades',
        'menu.comercial.items.lotes',
        'menu.comercial.items.cultivos',
        'menu.comercial.items.reportes_cliente',
        // Retirados con el cambio de enfoque: el auxiliar ya no es usuario y el
        // equipo (piloto + dron) se ve en la ficha del piloto, no en una pantalla.
        'menu.solicitudes.items.tablero',
        'menu.operacion.items.tablero',
        'menu.proveedores.items.auxiliares',
        'menu.proveedores.items.equipos_flota',
        // Los módulos raíz, al final: ya sin hijos vivos.
        'menu.catalogos.label',
        'menu.operacion.label',
        'menu.recursos.label',
        'menu.reportes.label',
        'menu.mantenimiento.label',
        'menu.financiero.label',
        'menu.comercial.label',
    ];

    public function run(): void
    {
        // Inicio no es un módulo con ítems: es un acceso directo del riel al
        // tablero (un módulo sin hijos y con ruta propia).
        $this->acceso('inicio', 'home', 0, ruta: 'panel.dashboard', codigoPermiso: 'seguridad.dashboard.ver');
        $mercado = $this->modulo('solicitudes', 'storefront', 1);
        $proveedores = $this->modulo('proveedores', 'local_shipping', 2);
        $atencion = $this->modulo('atencion', 'support_agent', 3);
        $seguridad = $this->modulo('seguridad', 'admin_panel_settings', 4);
        $sistema = $this->modulo('sistema', 'tune', 5);

        // Las pantallas que cambian de módulo conservan su fila (id, permiso y
        // bitácora intactos). El tablero vivía dentro de Mercado y de
        // «Operación»: su fila vieja se da de baja y Inicio ocupa su lugar.
        $this->mover('menu.solicitudes.items.clientes', 'menu.atencion.items.clientes', $atencion, 1);
        $this->mover('menu.solicitudes.items.calificaciones', 'menu.atencion.items.calificaciones', $atencion, 2);
        $this->mover('menu.solicitudes.items.disputas', 'menu.atencion.items.reclamos', $atencion, 3);
        $this->mover('menu.catalogos.items.zonas', 'menu.sistema.items.zonas', $sistema, 1);
        $this->mover('menu.seguridad.items.organizacion', 'menu.sistema.items.organizacion', $sistema, 2);
        $this->mover('menu.seguridad.items.configuracion', 'menu.sistema.items.configuracion', $sistema, 3);
        $this->mover('menu.seguridad.items.versiones_apk', 'menu.sistema.items.versiones_apk', $sistema, 4);
        $this->mover('menu.solicitudes.items.avisos', 'menu.sistema.items.avisos', $sistema, 5);
        $this->retirarEntradas(self::RETIRADAS);

        // Mercado (ADR 0027, 0031, 0033, 0039): el ciclo de la solicitud y su
        // medición. Cada ítem va gateado por el permiso de LISTADO (`ver`);
        // cancelar rige el botón de cada pantalla, no la visibilidad del ítem.
        $this->item($mercado, 'solicitudes', 'solicitudes', 'request_quote', 1, ruta: 'panel.solicitudes.index', codigoPermiso: 'solicitudes.solicitud.ver');
        $this->item($mercado, 'solicitudes', 'metricas', 'insights', 2, ruta: 'panel.metricas.index', codigoPermiso: 'metricas.panel.ver');

        // Proveedores (ADR 0032): la cola de aprobación. Documentos va primero:
        // es la cola que más se mira a diario. Aprobar, suspender y eliminar
        // rigen los botones de la ficha, no la visibilidad del ítem. El equipo
        // de un piloto se ve en su ficha, no en un ítem propio.
        $this->item($proveedores, 'proveedores', 'documentos', 'folder_open', 1, ruta: 'panel.documentos.index', codigoPermiso: 'proveedores.documento.ver');
        $this->item($proveedores, 'proveedores', 'proveedores', 'local_shipping', 2, ruta: 'panel.proveedores.index', codigoPermiso: 'proveedores.proveedor.ver');
        $this->item($proveedores, 'proveedores', 'pilotos', 'sports_esports', 3, ruta: 'panel.pilotos.index', codigoPermiso: 'proveedores.piloto.ver');
        $this->item($proveedores, 'proveedores', 'drones_flota', 'airplanemode_active', 4, ruta: 'panel.drones-flota.index', codigoPermiso: 'flota.dron.ver');

        // Atención: la relación con quienes usan la plataforma. Los clientes, lo
        // que opinan de un servicio (calificaciones) y lo que reclaman
        // (reclamos). Reclamos va último: es lo que se atiende cuando llega.
        $this->item($atencion, 'atencion', 'clientes', 'agriculture', 1, ruta: 'panel.clientes.index', codigoPermiso: 'solicitudes.cliente.ver');
        $this->item($atencion, 'atencion', 'calificaciones', 'star', 2, ruta: 'panel.calificaciones.index', codigoPermiso: 'calificaciones.calificacion.ver');
        $this->item($atencion, 'atencion', 'reclamos', 'report', 3, ruta: 'panel.disputas.index', codigoPermiso: 'disputas.disputa.ver');

        // Seguridad: quién es (usuarios), qué puede (roles), con qué entra
        // (dispositivos) y qué hizo (bitácora).
        $this->item($seguridad, 'seguridad', 'usuarios', 'group', 1, ruta: 'panel.usuarios.index', codigoPermiso: 'seguridad.usuario.ver');
        $this->item($seguridad, 'seguridad', 'roles', 'shield_person', 2, ruta: 'panel.roles.index', codigoPermiso: 'seguridad.rol.ver');
        $this->item($seguridad, 'seguridad', 'dispositivos', 'smartphone', 3, ruta: 'panel.dispositivos.index', codigoPermiso: 'seguridad.dispositivo.ver');
        $this->item($seguridad, 'seguridad', 'bitacora', 'history', 4, ruta: 'panel.bitacora.index', codigoPermiso: 'seguridad.bitacora.ver');

        // Sistema: ajustes que se tocan poco y los avisos del sistema, que son
        // una herramienta de auditoría (ven los de todas las cuentas).
        $this->item($sistema, 'sistema', 'zonas', 'pin_drop', 1, ruta: 'panel.zonas.index', codigoPermiso: 'solicitudes.zona.ver');
        $this->item($sistema, 'sistema', 'organizacion', 'apartment', 2, ruta: 'panel.organizacion.index', codigoPermiso: 'seguridad.organizacion.ver');
        $this->item($sistema, 'sistema', 'configuracion', 'tune', 3, ruta: 'panel.configuracion.index', codigoPermiso: 'seguridad.configuracion.ver');
        $this->item($sistema, 'sistema', 'versiones_apk', 'system_update', 4, ruta: 'panel.versiones-apk.index', codigoPermiso: 'distribucion.version.autorizar');
        $this->item($sistema, 'sistema', 'avisos', 'notifications_active', 5, ruta: 'panel.avisos.index', codigoPermiso: 'notificaciones.aviso.ver');
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

    /**
     * Acceso directo del riel: un módulo raíz SIN hijos que enlaza a su propia
     * ruta (Inicio → tablero). Es visible si el rol activo tiene su permiso.
     */
    private function acceso(string $clave, string $icono, int $orden, string $ruta, string $codigoPermiso): SecMenu
    {
        $permissionId = SecPermission::query()->where('code', $codigoPermiso)->value('id');

        $fila = SecMenu::query()->firstOrCreate(
            ['label' => "menu.{$clave}.label", 'padre_id' => null],
            [
                'descripcion' => "menu.{$clave}.descripcion",
                'icono' => $icono,
                'ruta' => $ruta,
                'orden' => $orden,
                'permission_id' => $permissionId,
            ],
        );

        if ($fila->ruta !== $ruta || $fila->permission_id !== $permissionId || $fila->orden !== $orden || $fila->icono !== $icono) {
            $fila->ruta = $ruta;
            $fila->permission_id = $permissionId;
            $fila->orden = $orden;
            $fila->icono = $icono;
            $fila->save();
        }

        return $fila;
    }

    private function modulo(string $clave, string $icono, int $orden): SecMenu
    {
        return SecMenu::query()->firstOrCreate(
            ['label' => "menu.{$clave}.label", 'padre_id' => null],
            [
                'descripcion' => "menu.{$clave}.descripcion",
                'icono' => $icono,
                'ruta' => null,
                'orden' => $orden,
                'permission_id' => null,
            ],
        );
    }

    /**
     * Mueve un ítem YA COLGADO de un módulo a otro, conservando la fila —su
     * id, su ruta, su permiso y su bitácora— y renombrando la etiqueta al
     * namespace del módulo nuevo. Distinto de {@see migrar()}, que sube una
     * raíz suelta (`padre_id` nulo) del catálogo plano viejo: acá el ítem ya
     * cuelga de un módulo y lo que cambia es de cuál.
     *
     * Busca por el label viejo sin filtrar por padre y sale sin hacer nada si
     * no lo encuentra: en una base ya movida —o recién sembrada— el `item()`
     * que sigue la resuelve por el label nuevo, así que correr el seeder dos
     * veces no duplica el ítem ni deja el viejo colgando. Se guarda con
     * `save()`, no con un `update()` masivo, para que la mutación pase por el
     * observer de bitácora (invariante 9).
     */
    private function mover(string $labelViejo, string $labelNuevo, SecMenu $padre, int $orden): void
    {
        $fila = SecMenu::query()->where('label', $labelViejo)->first();

        if ($fila === null) {
            return;
        }

        $fila->label = $labelNuevo;
        $fila->padre_id = $padre->id;
        $fila->orden = $orden;
        $fila->save();
    }

    private function migrar(string $labelViejo, string $labelNuevo, SecMenu $padre, string $icono, int $orden): void
    {
        $fila = SecMenu::query()
            ->whereNull('padre_id')
            ->where('label', $labelViejo)
            ->first();

        if ($fila === null) {
            return;
        }

        $fila->label = $labelNuevo;
        $fila->padre_id = $padre->id;
        $fila->icono = $icono;
        $fila->orden = $orden;
        $fila->save();
    }

    /**
     * `firstOrCreate` para la fila; además, si ya existía como "botón sin
     * link" (pantalla todavía no construida cuando se sembró por primera
     * vez) y esta vuelta SÍ trae `ruta`/`codigoPermiso`, completa esos dos
     * campos — nunca pisa un valor que ya estaba seteado (ni por una vuelta
     * anterior del seeder ni por una edición manual). Necesario porque el
     * catálogo de ítems se siembra completo desde el principio (quinta
     * vuelta) con placeholders para las pantallas que todavía no existían;
     * activarlas más tarde no puede depender de truncar `sec_menu` — los
     * datos demo no se borran.
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

        $activaRuta = $fila->ruta === null && $ruta !== null;
        $activaPermiso = $fila->permission_id === null && $permissionId !== null;
        // A diferencia de `ruta`/`permission_id`, estas dos SÍ se sincronizan
        // siempre: no son placeholders que el catálogo va completando, son
        // propiedades de la pantalla que el seeder define — una base ya
        // sembrada tiene que recibir el cambio sin que haya que truncar
        // `sec_menu` (los datos demo no se borran).
        //
        // `orden` se sumó a esa regla al insertar "Roles y permisos" en medio
        // del grupo Seguridad: sin sincronizarlo, un ítem nuevo solo podía ir
        // al final de su grupo, porque los ya sembrados conservaban su número
        // para siempre. Este seeder es la única fuente del orden del menú (no
        // hay pantalla que lo reordene), así que pisarlo es correcto y no
        // descarta ninguna edición de nadie.
        //
        // `icono` se sumó el 17/9/2026 (cambio de "Orden de Trabajo" de
        // fact_check a work_history): sin sincronizarlo, cambiar el ícono acá
        // no se reflejaba en una base ya sembrada — mismo motivo que `orden`.
        $ajustaRequisito = $fila->requiere_persona !== $requierePersona;
        $ajustaOrden = $fila->orden !== $orden;
        $ajustaIcono = $fila->icono !== $icono;

        if ($activaRuta || $activaPermiso || $ajustaRequisito || $ajustaOrden || $ajustaIcono) {
            $fila->ruta ??= $ruta;
            $fila->permission_id ??= $permissionId;
            $fila->requiere_persona = $requierePersona;
            $fila->orden = $orden;
            $fila->icono = $icono;
            $fila->save();
        }

        return $fila;
    }
}

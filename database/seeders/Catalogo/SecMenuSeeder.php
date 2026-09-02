<?php

namespace Database\Seeders\Catalogo;

use App\Dominios\Seguridad\Infraestructura\Eloquent\SecMenu;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecPermission;
use Illuminate\Database\Seeder;

/**
 * Catálogo del menú de tres niveles del panel (quinta vuelta — el LAYOUT es
 * el de las maquetas aprobadas 4a/5a/5b/5c; los MÓDULOS y su vocabulario
 * salen de la especificación, no del mockup — pedido explícito del
 * 28/8/2026): 7 módulos raíz (riel de nivel 1) con sus ítems hijos (sidebar
 * de nivel 2), según §4 de
 * `docs/especificacion/especificacion_funcional_tecnica.md` — Comercial
 * (4.1), Recursos (4.2), Operación (4.3), Financiero (4.4), Mantenimiento e
 * inventario (4.5), Seguridad (4.6) — más Reportes (cap. 9/10).
 *
 * Corre en todos los entornos, producción incluida — igual que
 * `SeguridadSeeder`, del que depende (necesita `sec_permission` sembrado).
 * Sin autor explícito (`created_by`/`updated_by` NULL): dato de catálogo.
 *
 * `label`/`descripcion` guardan CLAVES de `lang/es/menu.php` (ADR 0013).
 * `ruta` guarda nombres de ruta Laravel; los ítems de pantallas todavía sin
 * construir la llevan NULL (menu-item los pinta como botón sin link). Las
 * tres pantallas que YA existen quedan en el árbol (pedido del 28/8/2026 —
 * nada se retira del menú):
 * - Operación › Programación → `panel.dashboard` (el viejo ítem "Inicio",
 *   migrado — es la misma pantalla, el "Operación de hoy").
 * - Seguridad › Usuarios → `panel.usuarios.index`, gateado por
 *   `seguridad.usuario.ver` (el permiso de listado — los de mutación rigen
 *   acciones DENTRO de la pantalla, no la visibilidad del ítem).
 * - Seguridad › Organización → `panel.organizacion.index` (sin permiso,
 *   igual que antes; el engranaje del riel también la abre como atajo).
 *
 * Idempotente: primero MIGRA las filas del catálogo plano anterior a su
 * nueva identidad (mismo id, label/padre nuevos — así ediciones manuales y
 * bitácora sobreviven), después `firstOrCreate` sobre `(label, padre_id)`.
 */
class SecMenuSeeder extends Seeder
{
    public function run(): void
    {
        $operacion = $this->modulo('operacion', 'flight_takeoff', 1);
        $comercial = $this->modulo('comercial', 'handshake', 2);
        $recursos = $this->modulo('recursos', 'precision_manufacturing', 3);
        $mantenimiento = $this->modulo('mantenimiento', 'inventory_2', 4);
        $financiero = $this->modulo('financiero', 'payments', 5);
        $reportes = $this->modulo('reportes', 'lab_profile', 6);
        $seguridad = $this->modulo('seguridad', 'admin_panel_settings', 7);

        // Migración del catálogo plano anterior (raíces `seguridad.menu.*`):
        // las tres pantallas existen y siguen en el menú — misma fila, nueva
        // identidad bajo su módulo.
        $this->migrar('seguridad.menu.inicio', 'menu.operacion.items.programacion', $operacion, 'event_available', 1);
        $this->migrar('seguridad.menu.usuarios', 'menu.seguridad.items.usuarios', $seguridad, 'group', 1);
        $this->migrar('seguridad.menu.organizacion', 'menu.seguridad.items.organizacion', $seguridad, 'apartment', 3);

        // Operación (§4.3)
        $this->item($operacion, 'operacion', 'programacion', 'event_available', 1, ruta: 'panel.dashboard');
        $this->item($operacion, 'operacion', 'ordenes', 'assignment', 2);
        // HU-05 (tarea 13): listado mínimo de trabajos/sesiones — el jefe ve
        // qué se cerró. Detalle con evidencias y filtros llegan con HU-15.
        $this->item($operacion, 'operacion', 'trabajos', 'fact_check', 3, ruta: 'panel.trabajos.index', codigoPermiso: 'operaciones.trabajo.ver');
        // HU-14 (tarea 14): cola de validación de sesiones cerradas.
        $this->item($operacion, 'operacion', 'sesiones', 'flight', 4, ruta: 'panel.sesiones.validacion.index', codigoPermiso: 'operaciones.sesion.validar');
        $this->item($operacion, 'operacion', 'pausas', 'pause_circle', 5);
        $this->item($operacion, 'operacion', 'mezclas', 'science', 6);
        $this->item($operacion, 'operacion', 'evidencias', 'photo_library', 7);

        // Comercial (§4.1 + cap. 9)
        $this->item($comercial, 'comercial', 'clientes', 'contact_page', 1);
        $this->item($comercial, 'comercial', 'contratos', 'description', 2);
        $this->item($comercial, 'comercial', 'campos', 'map', 3);
        $this->item($comercial, 'comercial', 'reportes_cliente', 'picture_as_pdf', 4);

        // Recursos (§4.2)
        $this->item($recursos, 'recursos', 'drones', 'airplanemode_active', 1);
        $this->item($recursos, 'recursos', 'baterias', 'battery_charging_full', 2);
        $this->item($recursos, 'recursos', 'vehiculos', 'local_shipping', 3);
        $this->item($recursos, 'recursos', 'bases', 'home_work', 4);
        $this->item($recursos, 'recursos', 'personas', 'badge', 5);

        // Mantenimiento e inventario (§4.5)
        $this->item($mantenimiento, 'mantenimiento', 'ordenes', 'build', 1);
        $this->item($mantenimiento, 'mantenimiento', 'planes', 'checklist', 2);
        $this->item($mantenimiento, 'mantenimiento', 'repuestos', 'construction', 3);
        $this->item($mantenimiento, 'mantenimiento', 'stock', 'warehouse', 4);

        // Financiero (§4.4 + cap. 11)
        $this->item($financiero, 'financiero', 'gastos', 'receipt_long', 1);
        $this->item($financiero, 'financiero', 'combustible', 'local_gas_station', 2);
        $this->item($financiero, 'financiero', 'rendiciones', 'fact_check', 3);
        $this->item($financiero, 'financiero', 'devengos', 'request_quote', 4);
        $this->item($financiero, 'financiero', 'planilla', 'event_note', 5);
        $this->item($financiero, 'financiero', 'facturas', 'receipt', 6);

        // Reportes (cap. 9 y 10)
        $this->item($reportes, 'reportes', 'tecnicos', 'summarize', 1);
        $this->item($reportes, 'reportes', 'comerciales', 'insert_chart', 2);
        // HU-19 (tarea 26): bandeja de alertas por excepción — activa el
        // ítem que ya estaba sembrado como "botón sin link" (ver docblock de
        // `item()`).
        $this->item($reportes, 'reportes', 'alertas', 'notifications_active', 3, ruta: 'panel.alertas.index', codigoPermiso: 'operaciones.alerta.ver');

        // Seguridad (§4.6 + cap. 14) — las dos pantallas existentes; si la
        // migración de arriba ya las convirtió, el firstOrCreate las
        // encuentra y no duplica.
        $this->item($seguridad, 'seguridad', 'usuarios', 'group', 1, ruta: 'panel.usuarios.index', codigoPermiso: 'seguridad.usuario.ver');
        // HU-03: revocación de sesiones de la app de campo. Gateado por el
        // permiso de LISTADO (`ver`); el de revocar rige el botón dentro de
        // la pantalla, no la visibilidad del ítem — mismo criterio que
        // Usuarios.
        $this->item($seguridad, 'seguridad', 'dispositivos', 'smartphone', 2, ruta: 'panel.dispositivos.index', codigoPermiso: 'seguridad.dispositivo.ver');
        $this->item($seguridad, 'seguridad', 'organizacion', 'apartment', 3, ruta: 'panel.organizacion.index');
        // HU-20: sin módulo raíz propio en la espec §4 (runs/10-diseno.md) —
        // entra bajo Seguridad, mismo criterio que Organización.
        $this->item($seguridad, 'seguridad', 'versiones_apk', 'system_update', 4, ruta: 'panel.versiones-apk.index', codigoPermiso: 'distribucion.version.autorizar');
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
     * Convierte una raíz del catálogo plano anterior en un ítem del árbol
     * nuevo — misma fila (id estable, bitácora intacta), identidad nueva.
     * `ruta`/`permission_id` no se tocan: ya apuntaban a la pantalla real.
     */
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
            ],
        );

        $activaRuta = $fila->ruta === null && $ruta !== null;
        $activaPermiso = $fila->permission_id === null && $permissionId !== null;

        if ($activaRuta || $activaPermiso) {
            $fila->ruta ??= $ruta;
            $fila->permission_id ??= $permissionId;
            $fila->save();
        }

        return $fila;
    }
}

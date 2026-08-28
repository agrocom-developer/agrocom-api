<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web;

use App\Dominios\Seguridad\Aplicacion\ListarRolesDisponibles;
use App\Dominios\Seguridad\Aplicacion\ObtenerMenuPorRolActivo;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Http\Middleware\ResolverRolActivo;
use Database\Seeders\Catalogo\SecMenuSeeder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * `GET /panel/dashboard` (`panel.dashboard`): página de aterrizaje del panel
 * tras el login, sin permiso propio — visible para cualquier usuario
 * autenticado con rol activo resuelto (`sec_menu` la siembra sin
 * `permission_id`, {@see SecMenuSeeder}).
 *
 * Middleware `auth:interno` + `rol.activo`: para cuando este controlador se
 * ejecuta, `session('sec_rol_activo_id')` ya es un rol vivo válido de este
 * usuario ({@see ResolverRolActivo} lo garantiza) — acá no se vuelve a
 * revalidar esa pertenencia.
 *
 * Adaptador delgado (ADR 0008): resuelve el árbol de menú del ROL ACTIVO vía
 * {@see ObtenerMenuPorRolActivo} (nunca la unión de todos los roles del
 * usuario, invariante 10 de `CLAUDE.md`) y los datos de cáscara que
 * `templates/panel-layout` espera (roles para el selector de cambio de rol,
 * nombre del rol activo, nombre de usuario) — el contenido real del
 * dashboard lo ensambla aquí con datos MOCK para mockup de alta fidelidad.
 *
 * MOCK (reemplazar cuando existan módulos reales):
 * - Métricas (sesiones, hectáreas, devengos) — sin cálculo de negocio real.
 * - Notificaciones — datos duros para demostración visual.
 * - Estados de órdenes — los 4 estados son el vocabulario REAL de
 *   {@see \App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion} (solo el
 *   CONTEO es mock), pero se listan acá como strings literales en vez de
 *   importar el enum: Operaciones todavía no tiene `Contratos/` (ADR 0003 —
 *   entre módulos se viaja por `Contratos/` o eventos de dominio, nunca
 *   alcanzando directo el `Dominio/` de otro módulo), así que este
 *   controlador de Seguridad no puede depender de
 *   `Operaciones\Dominio\EstadoOrdenAplicacion` sin crear ese acoplamiento
 *   prohibido. Si el valor de alguno de los 4 casos cambia alguna vez, este
 *   archivo queda desincronizado — riesgo aceptado explícitamente por ser
 *   presentación mock, no lógica de negocio.
 */
final class DashboardController
{
    public function index(
        Request $request,
        ObtenerMenuPorRolActivo $obtenerMenu,
        ListarRolesDisponibles $listarRolesDisponibles,
    ): View {
        /** @var SecUser $usuario */
        $usuario = $request->user('interno');
        $idRolActivo = (int) $request->session()->get('sec_rol_activo_id');

        $roles = $listarRolesDisponibles->ejecutar($usuario);
        $activeRoleLabel = $roles->firstWhere('id', $idRolActivo)?->name;

        // MOCK — reemplazar cuando exista módulo Operaciones real.
        $statCards = $this->generarStatCardsPorRol($activeRoleLabel);

        // MOCK — reemplazar cuando exista módulo de notificaciones real.
        $notificaciones = $this->generarNotificacionesMock();

        // MOCK (solo el conteo) — el conteo es mock, los 4 estados son
        // vocabulario real de Operaciones::EstadoOrdenAplicacion, ver
        // docblock de la clase.
        $ordenesPorEstado = $activeRoleLabel === 'encargado_operaciones' || $activeRoleLabel === 'dueno'
            ? $this->generarOrdenesPorEstadoMock()
            : [];

        return view('seguridad::pages.dashboard', [
            'menu' => $obtenerMenu->ejecutar($usuario, $idRolActivo),
            'roles' => $roles,
            'rolActivoId' => $idRolActivo,
            'activeRoleLabel' => $activeRoleLabel,
            'userName' => $usuario->name,
            'statCards' => $statCards,
            'notificaciones' => $notificaciones,
            'ordenesPorEstado' => $ordenesPorEstado,
        ]);
    }

    /**
     * MOCK — datos de stat-cards variados por rol activo.
     * Reemplazar cuando exista la lógica de negocio real de cada módulo.
     *
     * @return array<string, array{icon: string, value: string, label: string, variant: string, trend?: string, trendDirection?: string}>
     */
    private function generarStatCardsPorRol(?string $rolActivo): array
    {
        return match ($rolActivo) {
            'piloto', 'auxiliar' => [
                'sesiones' => [
                    'icon' => 'flight_takeoff',
                    'value' => '12',
                    'label' => __('seguridad.dashboard.mock.sesiones_personales'),
                    'variant' => 'success',
                    'trend' => '+3 este mes',
                    'trendDirection' => 'up',
                ],
                'hectareas' => [
                    'icon' => 'landscape',
                    'value' => '486 ha',
                    'label' => __('seguridad.dashboard.mock.hectareas_cubiertas'),
                    'variant' => 'info',
                ],
                'proxima' => [
                    'icon' => 'task_alt',
                    'value' => '15 ago',
                    'label' => __('seguridad.dashboard.mock.proxima_orden'),
                    'variant' => 'neutral',
                ],
            ],
            'jefe_campo' => [
                'sesiones' => [
                    'icon' => 'people_alt',
                    'value' => '24',
                    'label' => __('seguridad.dashboard.mock.sesiones_equipo'),
                    'variant' => 'success',
                    'trend' => '+8 este mes',
                    'trendDirection' => 'up',
                ],
                'hectareas' => [
                    'icon' => 'landscape',
                    'value' => '1,208 ha',
                    'label' => __('seguridad.dashboard.mock.hectareas_equipo'),
                    'variant' => 'info',
                ],
                'validacion' => [
                    'icon' => 'approval',
                    'value' => '7',
                    'label' => __('seguridad.dashboard.mock.pendientes_validar'),
                    'variant' => 'warning',
                    'trend' => '-2 desde ayer',
                    'trendDirection' => 'down',
                ],
            ],
            'encargado_operaciones', 'dueno' => [
                'usuarios' => [
                    'icon' => 'group',
                    'value' => '8',
                    'label' => __('seguridad.dashboard.mock.usuarios_activos'),
                    'variant' => 'neutral',
                ],
                'sesiones' => [
                    'icon' => 'flight_takeoff',
                    'value' => '156',
                    'label' => __('seguridad.dashboard.mock.sesiones_mes'),
                    'variant' => 'success',
                    'trend' => '+42%',
                    'trendDirection' => 'up',
                ],
                'hectareas' => [
                    'icon' => 'landscape',
                    'value' => '4,268 ha',
                    'label' => __('seguridad.dashboard.mock.hectareas_totales'),
                    'variant' => 'info',
                ],
                'devengos' => [
                    'icon' => 'payment',
                    'value' => 'Bs 18,490',
                    'label' => __('seguridad.dashboard.mock.devengos_pendientes'),
                    'variant' => 'accent',
                    'trend' => '↑ Bs 3,200',
                    'trendDirection' => 'up',
                ],
            ],
            default => [],
        };
    }

    /**
     * MOCK — notificaciones para topbar (solo dashboard, usuarios/organización vacío).
     * Reemplazar cuando exista módulo de notificaciones real.
     *
     * @return array<int, array{icon: string, title: string, time: string, unread: bool}>
     */
    private function generarNotificacionesMock(): array
    {
        return [
            [
                'icon' => 'task_alt',
                'title' => __('seguridad.dashboard.mock.notificacion_1_titulo'),
                'time' => __('seguridad.dashboard.mock.notificacion_1_hora'),
                'unread' => true,
            ],
            [
                'icon' => 'flight_takeoff',
                'title' => __('seguridad.dashboard.mock.notificacion_2_titulo'),
                'time' => __('seguridad.dashboard.mock.notificacion_2_hora'),
                'unread' => true,
            ],
            [
                'icon' => 'info',
                'title' => __('seguridad.dashboard.mock.notificacion_3_titulo'),
                'time' => __('seguridad.dashboard.mock.notificacion_3_hora'),
                'unread' => false,
            ],
        ];
    }

    /**
     * MOCK (solo el conteo) — los 4 estados listados son el vocabulario REAL
     * de Operaciones::EstadoOrdenAplicacion (emitida|vigente|consumida|
     * vencida), como strings literales por el límite de acoplamiento entre
     * módulos (ver docblock de la clase) — reemplazar el conteo cuando
     * exista el módulo real de órdenes.
     *
     * @return list<array{estado: string, count: int, variant: string, label: string}>
     */
    private function generarOrdenesPorEstadoMock(): array
    {
        return [
            ['estado' => 'emitida', 'count' => 8, 'variant' => 'info', 'label' => __('operaciones.estado.emitida')],
            ['estado' => 'vigente', 'count' => 12, 'variant' => 'success', 'label' => __('operaciones.estado.vigente')],
            ['estado' => 'consumida', 'count' => 3, 'variant' => 'warning', 'label' => __('operaciones.estado.consumida')],
            ['estado' => 'vencida', 'count' => 2, 'variant' => 'danger', 'label' => __('operaciones.estado.vencida')],
        ];
    }
}

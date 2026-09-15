<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Presentacion;

use App\Dominios\Finanzas\Contratos\LecturaContadoresPanel as LecturaContadoresPanelFinanzas;
use App\Dominios\Inventario\Contratos\LecturaContadoresPanel as LecturaContadoresPanelInventario;
use App\Dominios\Operaciones\Contratos\AlertaPanel;
use App\Dominios\Operaciones\Contratos\LecturaContadoresPanel as LecturaContadoresPanelOperaciones;
use App\Dominios\Operaciones\Contratos\LecturaPanelOperaciones;
use App\Dominios\Seguridad\Aplicacion\ListarRolesDisponibles;
use App\Dominios\Seguridad\Aplicacion\ObtenerMenuPorRolActivo;
use App\Dominios\Seguridad\Dominio\TemaPreferencia;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserPreferencia;
use Illuminate\Support\Carbon;

/**
 * Datos de cáscara que `templates/panel-layout` + `templates/panel-shell`
 * esperan, resueltos una sola vez para cualquier página del panel (dashboard,
 * usuarios, organización): árbol de menú del ROL ACTIVO (nunca la unión —
 * CLAUDE.md invariante 10), roles disponibles, rol activo legible, tema
 * persistido, el chrome del header y los badges del menú (TE-14, tarea 60 —
 * contadores reales de cada módulo dueño, ver {@see menuBadges()}).
 *
 * Desde la tarea 67 no queda nada de maqueta acá: las notificaciones de la
 * campana son las alertas por excepción reales (HU-19) y la versión del pie
 * sale de `config('app.version')`.
 *
 * El 9/9/2026 el header se quedó además sin contexto de negocio, mirando el
 * panel andando: cayeron el chip de campaña (ADR 0015 — la campaña es del
 * cliente, se elige dentro del cliente o del contrato, nunca es un contexto
 * ambiente de la sesión) y el selector de período, que mostraba el mes en
 * curso sin filtrar nada y se leía justamente como si fuera esa campaña.
 *
 * Existe para que cada controlador de página no re-arme (ni desincronice)
 * esta misma docena de props — los controladores siguen siendo adaptadores
 * delgados (ADR 0008) que agregan solo los datos propios de su pantalla.
 */
final class CascaraPanel
{
    private const ALERTAS_NOTIFICACION = 5;

    public function __construct(
        private readonly ObtenerMenuPorRolActivo $obtenerMenu,
        private readonly ListarRolesDisponibles $listarRolesDisponibles,
        private readonly LecturaContadoresPanelOperaciones $contadoresOperaciones,
        private readonly LecturaPanelOperaciones $panelOperaciones,
        private readonly LecturaContadoresPanelInventario $contadoresInventario,
        private readonly LecturaContadoresPanelFinanzas $contadoresFinanzas,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function para(SecUser $usuario, int $idRolActivo): array
    {
        $roles = $this->listarRolesDisponibles->ejecutar($usuario);
        $rolActivo = $roles->firstWhere('id', $idRolActivo);

        $preferencia = SecUserPreferencia::query()->where('user_id', $usuario->id)->first();
        $tema = ($preferencia->tema ?? TemaPreferencia::Claro)->atributoBootstrap();

        return [
            'menu' => $this->obtenerMenu->ejecutar($usuario, $idRolActivo),
            'roles' => $roles,
            'rolActivoId' => $idRolActivo,
            'activeRoleLabel' => $rolActivo !== null ? PresentadorRol::nombreLegible($rolActivo) : null,
            'userName' => $usuario->name,
            'tema' => $tema,
            // Tarea 63: zona horaria IANA que informó el navegador (en el
            // login o, si la sesión venía sin ella, apenas carga el panel).
            // Nunca se inventa un default acá: `null` es "todavía no la
            // fijó", y el badge del pie nace vacío hasta que el JS la
            // detecta y la persiste (molecules/timezone-badge).
            'zonaHoraria' => $preferencia->zona_horaria ?? null,
            'notifications' => $this->notificaciones($usuario, $idRolActivo),
            'menuBadges' => $this->menuBadges($usuario),
            'version' => config('app.version'),
        ];
    }

    /**
     * Contadores reales de pendientes de los ítems del menú (badge ámbar del
     * nivel 3), indexados por la clave `label` de `sec_menu` (TE-14, tarea
     * 60 — contadores reales, no maqueta). `numero` es lo
     * único que pinta el badge en el sidebar (compacto); `texto` es la frase
     * completa del tooltip.
     *
     * Dos ítems de la maqueta original quedan sin badge porque ningún
     * módulo tiene un dato real detrás (ver `docs/gestion/plan_sprints.md`
     * Sprint 12 §255): `operacion.items.programacion` (no existe el
     * concepto de sesión programada) y `recursos.items.drones` (`ope_drones`
     * no registra estado de taller). `comercial.items.reportes_cliente`
     * (el tercero de esa lista) se retiró del catálogo en la tarea 62 (fuga
     * 3, `SecMenuSeeder`) — nunca llegó a tener pantalla propia.
     * `panel-layout.blade.php` ya tolera la ausencia de una clave
     * (`$menuBadges[$label] ?? null`).
     *
     * @return array<string, array{numero: string, texto: string}>
     */
    private function menuBadges(SecUser $usuario): array
    {
        $badges = [];

        $ordenesVigentes = $this->contadoresOperaciones->ordenesVigentes();
        if ($ordenesVigentes > 0) {
            $badges['menu.operacion.items.ordenes'] = [
                'numero' => (string) $ordenesVigentes,
                'texto' => "{$ordenesVigentes} vigentes",
            ];
        }

        $sesionesPendientes = $this->contadoresOperaciones->sesionesPendientesValidacion();
        if ($sesionesPendientes > 0) {
            $badges['menu.operacion.items.sesiones'] = [
                'numero' => (string) $sesionesPendientes,
                'texto' => "{$sesionesPendientes} sin validar",
            ];
        }

        $pausas = $this->contadoresOperaciones->pausasDelMes();
        if ($pausas['cantidad'] > 0) {
            $badges['menu.operacion.items.pausas'] = [
                'numero' => (string) $pausas['cantidad'],
                'texto' => "{$pausas['cantidad']} este mes",
            ];
        }

        $stockBajoMinimo = $this->contadoresInventario->stockBajoMinimo();
        if ($stockBajoMinimo > 0) {
            $badges['menu.mantenimiento.items.stock'] = [
                'numero' => (string) $stockBajoMinimo,
                'texto' => "{$stockBajoMinimo} bajo mínimo",
            ];
        }

        if ($usuario->persona_id !== null) {
            $devengado = $this->contadoresFinanzas->devengadoDelMes($usuario->persona_id);
            if ((float) $devengado > 0) {
                $devengadoFormateado = number_format((float) $devengado, 0, ',', '.');
                $badges['menu.financiero.items.devengos'] = [
                    'numero' => $devengadoFormateado,
                    'texto' => "Bs {$devengadoFormateado}",
                ];
            }
        }

        return $badges;
    }

    /**
     * Campana del header: las alertas por excepción reales (HU-19), no los
     * tres avisos inventados que devolvía la maqueta. Gateadas
     * por `operaciones.alerta.ver` contra el ROL ACTIVO: un rol que no puede
     * entrar a `/panel/alertas` tampoco las lee por la campana.
     *
     * @return list<array{icon: string, title: string, time: string, unread: bool}>
     */
    private function notificaciones(SecUser $usuario, int $idRolActivo): array
    {
        if (! $usuario->tienePermisoEnRol('operaciones.alerta.ver', $idRolActivo)) {
            return [];
        }

        return array_map(fn (AlertaPanel $alerta) => [
            'icon' => 'warning',
            'title' => $alerta->mensaje,
            'time' => Carbon::parse($alerta->creadaEn)->diffForHumans(),
            'unread' => $alerta->pendiente,
        ], $this->panelOperaciones->alertasRecientes(self::ALERTAS_NOTIFICACION));
    }
}

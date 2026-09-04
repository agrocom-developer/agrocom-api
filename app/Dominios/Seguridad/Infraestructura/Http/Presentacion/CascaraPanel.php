<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Presentacion;

use App\Dominios\Finanzas\Contratos\LecturaContadoresPanel as LecturaContadoresPanelFinanzas;
use App\Dominios\Inventario\Contratos\LecturaContadoresPanel as LecturaContadoresPanelInventario;
use App\Dominios\Operaciones\Contratos\LecturaContadoresPanel as LecturaContadoresPanelOperaciones;
use App\Dominios\Seguridad\Aplicacion\ListarRolesDisponibles;
use App\Dominios\Seguridad\Aplicacion\ObtenerMenuPorRolActivo;
use App\Dominios\Seguridad\Dominio\TemaPreferencia;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUserPreferencia;
use App\Dominios\Seguridad\Infraestructura\Http\Demo\DatosDemoPanel;

/**
 * Datos de cáscara que `templates/panel-layout` + `templates/panel-shell`
 * esperan, resueltos una sola vez para cualquier página del panel (dashboard,
 * usuarios, organización): árbol de menú del ROL ACTIVO (nunca la unión —
 * CLAUDE.md invariante 10), roles disponibles, rol activo legible, tema
 * persistido, el chrome de demo (campaña, período, notificaciones — MOCK,
 * ver {@see DatosDemoPanel}) y los badges del menú (TE-14, tarea 60 —
 * contadores reales de cada módulo dueño, ver {@see menuBadges()}).
 *
 * Existe para que cada controlador de página no re-arme (ni desincronice)
 * esta misma docena de props — los controladores siguen siendo adaptadores
 * delgados (ADR 0008) que agregan solo los datos propios de su pantalla.
 */
final class CascaraPanel
{
    public function __construct(
        private readonly ObtenerMenuPorRolActivo $obtenerMenu,
        private readonly ListarRolesDisponibles $listarRolesDisponibles,
        private readonly DatosDemoPanel $demo,
        private readonly LecturaContadoresPanelOperaciones $contadoresOperaciones,
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

        $chrome = $this->demo->chrome();

        return [
            'menu' => $this->obtenerMenu->ejecutar($usuario, $idRolActivo),
            'roles' => $roles,
            'rolActivoId' => $idRolActivo,
            'activeRoleLabel' => $rolActivo !== null ? PresentadorRol::nombreLegible($rolActivo) : null,
            'userName' => $usuario->name,
            'tema' => $tema,
            'notifications' => $this->demo->notificaciones(),
            'menuBadges' => $this->menuBadges($usuario),
            'campana' => $chrome['campana'],
            'periodo' => $chrome['periodo'],
            'version' => $chrome['version'],
        ];
    }

    /**
     * Contadores reales de pendientes de los ítems del menú (badge ámbar del
     * nivel 3), indexados por la clave `label` de `sec_menu` (TE-14, tarea
     * 60 — reemplaza al mock `DatosDemoPanel::badgesMenu()`). `numero` es lo
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
        $badges['menu.operacion.items.ordenes'] = [
            'numero' => (string) $ordenesVigentes,
            'texto' => "{$ordenesVigentes} vigentes",
        ];

        $sesionesPendientes = $this->contadoresOperaciones->sesionesPendientesValidacion();
        $badges['menu.operacion.items.sesiones'] = [
            'numero' => (string) $sesionesPendientes,
            'texto' => "{$sesionesPendientes} sin validar",
        ];

        $pausas = $this->contadoresOperaciones->pausasDelMes();
        $badges['menu.operacion.items.pausas'] = [
            'numero' => (string) $pausas['cantidad'],
            'texto' => "{$pausas['cantidad']} este mes",
        ];

        $stockBajoMinimo = $this->contadoresInventario->stockBajoMinimo();
        $badges['menu.mantenimiento.items.stock'] = [
            'numero' => (string) $stockBajoMinimo,
            'texto' => "{$stockBajoMinimo} bajo mínimo",
        ];

        if ($usuario->persona_id !== null) {
            $devengado = $this->contadoresFinanzas->devengadoDelMes($usuario->persona_id);
            $devengadoFormateado = number_format((float) $devengado, 0, ',', '.');
            $badges['menu.financiero.items.devengos'] = [
                'numero' => $devengadoFormateado,
                'texto' => "Bs {$devengadoFormateado}",
            ];
        }

        return $badges;
    }
}

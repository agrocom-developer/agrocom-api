<?php

namespace App\Dominios\Seguridad\Infraestructura\Http;

use App\Dominios\Seguridad\Aplicacion\ItemMenu;
use App\Dominios\Seguridad\Aplicacion\ObtenerMenuPorRolActivo;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Http\Presentacion\CascaraPanel;
use Illuminate\Http\Request;

/**
 * Implementación Eloquent de {@see AutorizacionPanelWeb}, sobre la sesión de
 * panel (guard `interno`). Fail-closed: sin un `SecUser` autenticado, ningún
 * permiso se concede.
 */
final class AutorizacionPanelWebSesion implements AutorizacionPanelWeb
{
    public function __construct(
        private readonly CascaraPanel $cascaraPanel,
        private readonly ObtenerMenuPorRolActivo $obtenerMenu,
    ) {}

    public function tienePermiso(Request $request, string $codigoPermiso): bool
    {
        $usuario = $request->user('interno');

        if (! $usuario instanceof SecUser) {
            return false;
        }

        $idRolActivo = (int) $request->session()->get('sec_rol_activo_id');

        return $usuario->tienePermisoEnRol($codigoPermiso, $idRolActivo);
    }

    /** @return array<string, mixed> */
    public function cascara(Request $request): array
    {
        /** @var SecUser $usuario */
        $usuario = $request->user('interno');
        $idRolActivo = (int) $request->session()->get('sec_rol_activo_id');

        return $this->cascaraPanel->para($usuario, $idRolActivo);
    }

    public function personaId(Request $request): ?int
    {
        /** @var SecUser|null $usuario */
        $usuario = $request->user('interno');

        return $usuario?->persona_id;
    }

    public function primerDestinoVisible(Request $request): string
    {
        /** @var SecUser $usuario */
        $usuario = $request->user('interno');
        $idRolActivo = (int) $request->session()->get('sec_rol_activo_id');

        $arbol = $this->obtenerMenu->ejecutar($usuario, $idRolActivo);
        $ruta = $this->primeraRutaVisible($arbol);

        // No debería ocurrir con el catálogo actual (los 5 roles tienen al
        // menos un ítem visible), pero un rol activo sin ningún ítem no
        // puede quedar sin destino: mejor caer al dashboard (que a su vez
        // resuelve su propio 403 si corresponde) que un 500.
        return $ruta !== null ? route($ruta) : route('panel.dashboard');
    }

    /**
     * @param  list<ItemMenu>  $items
     */
    private function primeraRutaVisible(array $items): ?string
    {
        foreach ($items as $item) {
            if ($item->ruta !== null) {
                return $item->ruta;
            }

            $rutaHijo = $this->primeraRutaVisible($item->hijos);

            if ($rutaHijo !== null) {
                return $rutaHijo;
            }
        }

        return null;
    }
}

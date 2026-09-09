<?php

namespace App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web;

use App\Dominios\Compartido\Aplicacion\GuardarConfiguracion;
use App\Dominios\Compartido\Aplicacion\ListarConfiguracionPorGrupo;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use App\Dominios\Seguridad\Infraestructura\Eloquent\SecUser;
use App\Dominios\Seguridad\Infraestructura\Http\Presentacion\CascaraPanel;
use App\Dominios\Seguridad\Infraestructura\Http\Requests\ActualizarConfiguracionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * `GET /panel/configuracion`, `POST /panel/configuracion/{grupo}` (tarea 78,
 * HU-55): llaves y tokens con que EL SISTEMA se parametriza (mapas, correo,
 * integraciones) — separado a propósito de `/panel/organizacion` (los datos
 * de LA EMPRESA). Exclusivo del rol `dueno` (pedido explícito del 7/9/2026):
 * dos permisos propios, `seguridad.configuracion.ver`/`.editar`, que ninguna
 * otra lista `PERMISOS_*` de `SeguridadSeeder` referencia.
 *
 * El modelo (`Configuracion`) y sus casos de uso viven en `Compartido`
 * (`plt_configuraciones`, tarea 78: "es plataforma, no negocio") — este
 * controlador los consume directo, mismo criterio que ya usan todos los
 * modelos `sec_*` con `ModeloDominio`/`RegistraBitacora` de ese mismo módulo:
 * `Compartido` es la plataforma transversal, no una vertical de negocio con
 * la que haya que cruzar por `Contratos/` (ADR 0003, `tests/Unit/ArquitecturaModulosTest.php`
 * excluye a `Compartido` de esa regla a propósito).
 *
 * NUNCA pasa un valor secreto a la vista: {@see ListarConfiguracionPorGrupo}
 * ya entrega el estado ("configurada"/"sin configurar") y, como mucho, los
 * últimos 4 caracteres — el corazón de la tarea. Este controlador es un
 * adaptador delgado (ADR 0008) que no toca ese contrato.
 */
final class ConfiguracionController
{
    private const PERMISO_VER = 'seguridad.configuracion.ver';

    private const PERMISO_EDITAR = 'seguridad.configuracion.editar';

    public function index(
        Request $request,
        AutorizacionPanelWeb $autorizacion,
        CascaraPanel $cascara,
        ListarConfiguracionPorGrupo $listar,
    ): View {
        abort_unless($autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        /** @var SecUser $usuario */
        $usuario = $request->user('interno');
        $idRolActivo = (int) $request->session()->get('sec_rol_activo_id');
        $grupoActivo = $this->grupoActivo($request);

        return view('seguridad::pages.configuracion.index', array_merge(
            $cascara->para($usuario, $idRolActivo),
            [
                'grupos' => $this->grupos($grupoActivo),
                // Los TRES sectores se listan siempre, no solo el activo: las
                // pestañas alternan por clase CSS/Bootstrap en el cliente
                // (mismo mecanismo que `/panel/organizacion`), sin recarga —
                // así que el contenido de cada una ya tiene que estar en el
                // HTML desde este único GET.
                'filasPorGrupo' => collect((array) config('configuracion.grupos'))
                    ->mapWithKeys(fn (string $grupo): array => [$grupo => $listar->ejecutar($grupo)])
                    ->all(),
                'puedeEditar' => $autorizacion->tienePermiso($request, self::PERMISO_EDITAR),
            ],
        ));
    }

    public function actualizar(
        ActualizarConfiguracionRequest $request,
        string $grupo,
        AutorizacionPanelWeb $autorizacion,
        GuardarConfiguracion $guardar,
    ): RedirectResponse {
        abort_unless($autorizacion->tienePermiso($request, self::PERMISO_EDITAR), 403);
        abort_unless(in_array($grupo, config('configuracion.grupos'), true), 404);

        $datos = $request->validated();

        $guardar->ejecutar($grupo, $datos['valores'] ?? [], $datos['borrar'] ?? []);

        return redirect()
            ->route('panel.configuracion.index', ['grupo' => $grupo])
            ->with('estado', __('configuracion.guardada'));
    }

    private function grupoActivo(Request $request): string
    {
        $grupos = config('configuracion.grupos');
        $solicitado = $request->query('grupo');

        return in_array($solicitado, $grupos, true) ? $solicitado : $grupos[0];
    }

    /**
     * @return list<array{clave: string, label: string, active: bool}>
     */
    private function grupos(string $grupoActivo): array
    {
        return array_map(
            fn (string $clave): array => [
                'clave' => $clave,
                'label' => __("configuracion.grupo_{$clave}"),
                'active' => $clave === $grupoActivo,
            ],
            config('configuracion.grupos'),
        );
    }
}

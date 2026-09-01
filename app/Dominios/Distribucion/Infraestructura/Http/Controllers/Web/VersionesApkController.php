<?php

namespace App\Dominios\Distribucion\Infraestructura\Http\Controllers\Web;

use App\Dominios\Distribucion\Aplicacion\MaquinaEstados\MaquinaEstadosVersionApk;
use App\Dominios\Distribucion\Aplicacion\RegistrarVersionApk;
use App\Dominios\Distribucion\Infraestructura\Eloquent\VersionApk;
use App\Dominios\Distribucion\Infraestructura\Http\Requests\RegistrarVersionApkRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * `GET /panel/versiones-apk`, `POST /panel/versiones-apk`, `POST
 * /panel/versiones-apk/{version}/autorizar` (HU-20): la pantalla desde la
 * que el dueño registra y autoriza versiones del APK — "ningún RC se
 * actualiza sin su visto bueno". El binario vive en el release de
 * `agrocom-field`; acá solo se guarda su URL.
 *
 * Un único permiso gatea toda la pantalla (`distribucion.version.autorizar`):
 * a diferencia de dispositivos (ver/revocar separados), acá nadie más que
 * quien autoriza necesita siquiera ver el listado — la HU no pide una
 * granularidad que nadie va a usar.
 *
 * Depende de {@see AutorizacionPanelWeb} (contrato de Seguridad, ADR 0003
 * regla 2) en vez de `SecUser`/`CascaraPanel` directos: un módulo que no sea
 * Seguridad no importa su modelo Eloquent (`tests/Unit/ArquitecturaModulosTest.php`).
 */
final class VersionesApkController
{
    private const PERMISO = 'distribucion.version.autorizar';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO), 403);

        return view('distribucion::pages.versiones-apk.index', [
            ...$this->autorizacion->cascara($request),
            'versiones' => VersionApk::query()->orderByDesc('version_code')->get(),
        ]);
    }

    public function store(RegistrarVersionApkRequest $request, RegistrarVersionApk $registrarVersion): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO), 403);

        $datos = $request->validated();

        $registrarVersion->ejecutar(
            (string) $datos['version'],
            (int) $datos['version_code'],
            (string) $datos['url_apk'],
        );

        return redirect()
            ->route('panel.versiones-apk.index')
            ->with('estado', __('distribucion.versiones.subida'));
    }

    public function autorizar(
        Request $request,
        VersionApk $version,
        MaquinaEstadosVersionApk $maquinaEstados,
    ): RedirectResponse {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO), 403);

        $maquinaEstados->autorizar($version);

        return redirect()
            ->route('panel.versiones-apk.index')
            ->with('estado', __('distribucion.versiones.autorizada'));
    }
}

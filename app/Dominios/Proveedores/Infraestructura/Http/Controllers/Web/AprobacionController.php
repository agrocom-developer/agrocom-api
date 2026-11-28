<?php

namespace App\Dominios\Proveedores\Infraestructura\Http\Controllers\Web;

use App\Dominios\Compartido\Dominio\AccionAprobacion;
use App\Dominios\Compartido\Dominio\Excepciones\AprobacionPropia;
use App\Dominios\Compartido\Dominio\Excepciones\DocumentosIncompletos;
use App\Dominios\Compartido\Dominio\Excepciones\MotivoRequerido;
use App\Dominios\Compartido\Dominio\Excepciones\TransicionDeAprobacionInvalida;
use App\Dominios\Proveedores\Aplicacion\ResolverAprobacion;
use App\Dominios\Proveedores\Dominio\TipoSujeto;
use App\Dominios\Proveedores\Infraestructura\Http\Requests\ResolverAprobacionRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * `POST /panel/{proveedores|pilotos}/{id}/resolver`: aprobar,
 * rechazar, suspender, rehabilitar o dar de baja, con motivo donde corresponde
 * (ADR 0032). El permiso es por entidad y por acción y se verifica contra el
 * ROL ACTIVO; la transición la valida {@see ResolverAprobacion}, nunca esta
 * pantalla.
 */
final class AprobacionController
{
    use ArmaFichasDeAprobacion;

    private const PERMISO_POR_SUJETO = [
        'proveedor' => 'proveedores.proveedor',
        'piloto' => 'proveedores.piloto',
    ];

    private const RUTA_LISTADO_POR_SUJETO = [
        'proveedor' => 'panel.proveedores.index',
        'piloto' => 'panel.pilotos.index',
    ];

    private const RUTA_POR_SUJETO = [
        'proveedor' => 'panel.proveedores.show',
        'piloto' => 'panel.pilotos.show',
    ];

    public function __construct(
        private readonly AutorizacionPanelWeb $autorizacion,
        private readonly ResolverAprobacion $resolver,
    ) {}

    public function proveedor(ResolverAprobacionRequest $request, int $id): RedirectResponse
    {
        return $this->resolver($request, TipoSujeto::Proveedor, $id);
    }

    public function piloto(ResolverAprobacionRequest $request, int $id): RedirectResponse
    {
        return $this->resolver($request, TipoSujeto::Piloto, $id);
    }

    private function resolver(ResolverAprobacionRequest $request, TipoSujeto $tipo, int $id): RedirectResponse
    {
        $accion = AccionAprobacion::from($request->validated('accion'));
        $permiso = self::PERMISO_POR_SUJETO[$tipo->value].'.'.self::permisoDeAccion($accion);

        abort_unless($this->autorizacion->tienePermiso($request, $permiso), 403);

        // Desde una fila del listado se vuelve al listado, con sus filtros; desde la ficha, a la ficha.
        $volver = $request->input('desde') === 'listado'
            ? redirect()->to(url()->previous(route(self::RUTA_LISTADO_POR_SUJETO[$tipo->value])))
            : redirect()->route(self::RUTA_POR_SUJETO[$tipo->value], $id);

        try {
            $this->resolver->ejecutar($tipo, $id, $accion, (int) Auth::guard('interno')->id(), $request->validated('motivo'));
        } catch (TransicionDeAprobacionInvalida|MotivoRequerido|DocumentosIncompletos|AprobacionPropia $excepcion) {
            return $volver->withErrors(['aprobacion' => $excepcion->getMessage()]);
        }

        return $volver->with('estado', __('proveedores.aprobacion.resuelta.'.$accion->value));
    }
}

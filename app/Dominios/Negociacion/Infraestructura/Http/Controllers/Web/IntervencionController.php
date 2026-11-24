<?php

namespace App\Dominios\Negociacion\Infraestructura\Http\Controllers\Web;

use App\Dominios\Negociacion\Aplicacion\IntervenirNegociacion;
use App\Dominios\Negociacion\Dominio\Excepciones\NegociacionInvalida;
use App\Dominios\Negociacion\Infraestructura\Eloquent\Evidencia;
use App\Dominios\Negociacion\Infraestructura\Http\Requests\CerrarNegociacionRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Lo que el panel hace sobre la negociación de una solicitud (adaptador delgado,
 * ADR 0008): cerrar con motivo la negociación de un proveedor
 * (`solicitudes.solicitud.intervenir`) y abrir una foto del servicio
 * (`solicitudes.solicitud.ver`). El permiso se verifica contra el ROL ACTIVO
 * (invariante 10); la lectura de la negociación vive en la ficha de la solicitud.
 */
final class IntervencionController
{
    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    /** `POST /panel/solicitudes/{solicitud}/proveedores/{proveedor}/cerrar-negociacion`. */
    public function cerrar(CerrarNegociacionRequest $request, int $solicitud, int $proveedor, IntervenirNegociacion $intervenir): RedirectResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, 'solicitudes.solicitud.intervenir'), 403);

        $volver = redirect()->route('panel.solicitudes.show', $solicitud);

        try {
            $intervenir->cerrar($solicitud, $proveedor, (string) $request->validated('motivo'));
        } catch (NegociacionInvalida $excepcion) {
            return $volver->withErrors(['intervencion' => $excepcion->getMessage()]);
        }

        return $volver->with('estado', __('negociacion.panel.negociacion_cerrada'));
    }

    /** `GET /panel/evidencias/{evidencia}/archivo`: la foto, desde el disco privado (nunca una URL pública). */
    public function evidencia(Request $request, int $evidencia): BinaryFileResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, 'solicitudes.solicitud.ver_seguimiento'), 403);

        $foto = Evidencia::query()->findOrFail($evidencia);

        abort_unless(Storage::disk('local')->exists($foto->archivo), 404);

        return response()->file(Storage::disk('local')->path($foto->archivo), ['Cache-Control' => 'private, no-store']);
    }
}

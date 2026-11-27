<?php

namespace App\Dominios\Negociacion\Infraestructura\Http\Controllers\Web;

use App\Dominios\Compartido\Aplicacion\Medios\AlmacenDeMedios;
use App\Dominios\Compartido\Dominio\VarianteDeMedio;
use App\Dominios\Negociacion\Aplicacion\IntervenirNegociacion;
use App\Dominios\Negociacion\Dominio\Excepciones\NegociacionInvalida;
use App\Dominios\Negociacion\Infraestructura\Eloquent\Evidencia;
use App\Dominios\Negociacion\Infraestructura\Http\Requests\CerrarNegociacionRequest;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    /** `GET /panel/evidencias/{evidencia}/archivo`: la foto, desde el disco privado (nunca una URL pública); `?variante=miniatura|media` entrega el derivado. */
    public function evidencia(Request $request, int $evidencia, AlmacenDeMedios $almacen): StreamedResponse
    {
        abort_unless($this->autorizacion->tienePermiso($request, 'solicitudes.solicitud.ver_seguimiento'), 403);

        $foto = Evidencia::query()->findOrFail($evidencia);

        $respuesta = $almacen->respuesta($foto->archivo, VarianteDeMedio::desde($request->query('variante')));

        abort_if($respuesta === null, 404);

        return $respuesta;
    }
}

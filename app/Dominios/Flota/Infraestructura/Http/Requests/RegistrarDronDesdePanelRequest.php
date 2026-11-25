<?php

namespace App\Dominios\Flota\Infraestructura\Http\Requests;

use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cuerpo de `POST /panel/drones-flota`: la oficina registra a mano el dron de
 * un proveedor que ya existe. Las reglas del dron son las de la API
 * ({@see GuardarDronRequest::reglasDeRegistro()}).
 */
final class RegistrarDronDesdePanelRequest extends FormRequest
{
    public const PERMISO = 'flota.dron.crear';

    public function authorize(): bool
    {
        return app(AutorizacionPanelWeb::class)->tienePermiso($this, self::PERMISO);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'proveedor_id' => ['required', 'integer', Rule::exists('prv_proveedores', 'id')->whereNull('deleted_at')],
            ...GuardarDronRequest::reglasDeRegistro(),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('flota.campos');
    }
}

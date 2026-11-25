<?php

namespace App\Dominios\Proveedores\Infraestructura\Http\Requests;

use App\Dominios\Proveedores\Dominio\TipoProveedor;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cuerpo de `POST /panel/pilotos`: la oficina suma un piloto a un proveedor
 * tercero que ya existe. Las reglas del piloto son las del registro por la API
 * ({@see RegistrarPilotoRequest::reglasDeRegistro()}).
 */
final class RegistrarPilotoDesdePanelRequest extends FormRequest
{
    public const PERMISO = 'proveedores.piloto.crear';

    public function authorize(): bool
    {
        return app(AutorizacionPanelWeb::class)->tienePermiso($this, self::PERMISO);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'proveedor_id' => ['required', 'integer', Rule::exists('prv_proveedores', 'id')->where('tipo', TipoProveedor::Tercero->value)->whereNull('deleted_at')],
            ...RegistrarPilotoRequest::reglasDeRegistro(),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('proveedores.campos');
    }
}

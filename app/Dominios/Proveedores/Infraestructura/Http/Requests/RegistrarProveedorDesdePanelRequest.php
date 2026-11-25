<?php

namespace App\Dominios\Proveedores\Infraestructura\Http\Requests;

use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Cuerpo de `POST /panel/proveedores`: la oficina registra a mano a un
 * proveedor independiente o tercero. Las reglas son las del registro público
 * ({@see RegistrarProveedorRequest::reglasDeRegistro()}): el panel no agrega ni
 * quita ninguna.
 */
final class RegistrarProveedorDesdePanelRequest extends FormRequest
{
    public const PERMISO = 'proveedores.proveedor.crear';

    public function authorize(): bool
    {
        return app(AutorizacionPanelWeb::class)->tienePermiso($this, self::PERMISO);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return RegistrarProveedorRequest::reglasDeRegistro();
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('proveedores.campos');
    }
}

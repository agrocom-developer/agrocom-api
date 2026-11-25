<?php

namespace App\Dominios\Proveedores\Infraestructura\Http\Requests;

use App\Dominios\Proveedores\Dominio\TipoProveedor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Cuerpo de `POST /api/proveedores/registro` (registro público, ADR 0032). */
final class RegistrarProveedorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return self::reglasDeRegistro();
    }

    /**
     * Las reglas del registro, que el alta manual del panel reutiliza tal cual.
     *
     * @return array<string, mixed>
     */
    public static function reglasDeRegistro(): array
    {
        $tipos = array_map(static fn (TipoProveedor $tipo): string => $tipo->value, TipoProveedor::registrables());

        return [
            'tipo' => ['required', Rule::in($tipos)],
            ...ReglasDeCuenta::reglas(),
            'telefono' => ['required', 'string', 'max:30', 'regex:/^[0-9 +().-]{6,30}$/'],
            'razon_social' => ['required', 'string', 'max:150'],
            // Datos de la persona (el piloto o el titular): el administrador los revisa al aprobar.
            'nombres' => ['required', 'string', 'max:80'],
            'apellidos' => ['required', 'string', 'max:80'],
            'documento_identidad' => ['required', 'string', 'max:30'],
            'fecha_nacimiento' => ['required', 'date_format:Y-m-d', 'before:-18 years'],
            'documento_fiscal' => ['required', 'string', 'max:30'],
            'zona_base_id' => ['nullable', 'integer', Rule::exists('sol_zonas', 'id')->whereNull('deleted_at')],
            'radio_operacion_km' => ['nullable', 'numeric', 'between:1,500'],
            // El independiente es su propio piloto: sin licencia no hay proveedor.
            'licencia_numero' => ['required_if:tipo,independiente', 'nullable', 'string', 'max:40'],
            'licencia_vence' => ['required_if:tipo,independiente', 'nullable', 'date', 'after:today'],
            'categoria_habilitacion' => ['required_if:tipo,independiente', 'nullable', 'string', 'max:40'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('proveedores.campos');
    }
}

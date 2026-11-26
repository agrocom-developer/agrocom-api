<?php

namespace App\Dominios\Proveedores\Infraestructura\Http\Requests;

use App\Dominios\Seguridad\Contratos\ReglasDeContacto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Cuerpo de `POST /api/proveedores/pilotos`: el titular de un tercero registra a un piloto. */
final class RegistrarPilotoRequest extends FormRequest
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
        return [
            ...ReglasDeCuenta::reglas(),
            // El teléfono y el documento del piloto (ADR 0046). TRANSITORIO: opcionales hasta que la app del piloto
            // los envíe (fase F4 de field); si llegan, se normalizan y cuentan para la unicidad por tipo y por persona.
            'telefono' => ['nullable', 'string', 'max:30', ReglasDeContacto::reglaTelefono()],
            'documento_tipo' => ['nullable', 'string', Rule::in(ReglasDeContacto::tiposDeDocumento())],
            'documento_numero' => ['nullable', 'required_with:nombres,apellidos', 'string', 'max:40'],
            'nombres' => ['nullable', 'required_with:documento_numero', 'string', 'max:80'],
            'apellidos' => ['nullable', 'required_with:documento_numero', 'string', 'max:80'],
            'fecha_nacimiento' => ['nullable', 'date_format:Y-m-d', 'before:-18 years'],
            'licencia_numero' => ['required', 'string', 'max:40'],
            'licencia_vence' => ['required', 'date', 'after:today'],
            'categoria_habilitacion' => ['required', 'string', 'max:40'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('proveedores.campos');
    }
}

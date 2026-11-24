<?php

namespace App\Dominios\Proveedores\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Cuerpo de `POST /api/proveedores/auxiliares`: un piloto o el titular registra a un auxiliar. */
final class RegistrarAuxiliarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ReglasDeCuenta::reglas();
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('proveedores.campos');
    }
}

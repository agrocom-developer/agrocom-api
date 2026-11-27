<?php

namespace App\Dominios\Proveedores\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Cuerpo de la eliminación de un documento: el motivo es obligatorio. */
final class EliminarDocumentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['motivo' => ['required', 'string', 'max:500']];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['motivo' => __('proveedores.campos.motivo')];
    }
}

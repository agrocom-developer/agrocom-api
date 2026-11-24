<?php

namespace App\Dominios\Proveedores\Infraestructura\Http\Requests;

use App\Dominios\Compartido\Dominio\TipoDocumento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cuerpo (multipart) de `POST /api/proveedores/documentos` y del alta de un
 * documento de dron. La licencia, el seguro y la habilitación del dron exigen
 * fecha de vencimiento: sin ella el sistema nunca avisaría ni suspendería.
 */
class CargarDocumentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $extensiones = implode(',', (array) config('proveedores.documento_extensiones'));

        return [
            'tipo' => ['required', Rule::in(TipoDocumento::valores())],
            'archivo' => ['required', 'file', 'mimes:'.$extensiones, 'max:'.(int) config('proveedores.documento_max_kb')],
            'vence_en' => [
                'required_if:tipo,licencia_piloto,seguro_responsabilidad,habilitacion_dron',
                'nullable', 'date', 'after_or_equal:today',
            ],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('proveedores.campos');
    }
}

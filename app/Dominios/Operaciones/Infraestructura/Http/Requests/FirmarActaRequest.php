<?php

namespace App\Dominios\Operaciones\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /api/actas/{acta:uuid_cliente}/firmar` (HU-17, tarea 24). La firma en
 * pantalla (o la foto del acta física) ya llegó como evidencia subida por
 * `POST /api/evidencias` — acá solo se referencia por su `uuid_cliente`,
 * mismo patrón que `CierreTrabajo::$evidenciaImagenCampoUuidCliente`.
 */
final class FirmarActaRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La autorización real es el permiso `operaciones.acta.firmar`,
        // verificado en el controlador contra el token del dispositivo.
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'evidencia_firma_uuid_cliente' => ['required', 'string', 'max:36'],
            'firmante' => ['required', 'string', 'max:255'],
            'fecha_firma' => ['required', 'date'],
        ];
    }
}

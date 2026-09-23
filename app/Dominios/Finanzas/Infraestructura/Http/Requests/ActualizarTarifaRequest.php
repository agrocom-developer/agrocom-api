<?php

namespace App\Dominios\Finanzas\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `PUT /panel/tarifas/{tarifa}` (ADR 0023). Mismas reglas que
 * `CrearTarifaRequest`.
 */
final class ActualizarTarifaRequest extends FormRequest
{
    use ValidaDatosDeTarifa;
}

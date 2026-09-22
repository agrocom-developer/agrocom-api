<?php

namespace App\Dominios\Finanzas\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /panel/tarifas` (ADR 0023). La autorización (permiso
 * `finanzas.tarifa.crear`) se verifica en el controlador, contra el rol
 * activo — no acá, mismo criterio que el resto del panel.
 */
final class CrearTarifaRequest extends FormRequest
{
    use ValidaDatosDeTarifa;
}

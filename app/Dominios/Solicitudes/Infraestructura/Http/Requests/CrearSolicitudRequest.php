<?php

namespace App\Dominios\Solicitudes\Infraestructura\Http\Requests;

use App\Dominios\Solicitudes\Dominio\AportaProducto;
use App\Dominios\Solicitudes\Dominio\TipoAplicacionServicio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Cuerpo de `POST /api/solicitudes` (especificación §10.1). */
final class CrearSolicitudRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'uuid_cliente' => ['required', 'uuid'],
            'terreno_id' => ['required', 'integer'],
            'contacto_id' => ['nullable', 'integer'],
            'tipo_aplicacion' => ['required', Rule::enum(TipoAplicacionServicio::class)],
            'hectareas_solicitadas' => ['required', 'numeric', 'gt:0', 'max:99999999', 'decimal:0,4'],
            'cultivo' => ['nullable', 'string', 'max:80'],
            'producto_indicado' => ['required', 'string', 'max:500'],
            'aporta_producto' => ['nullable', Rule::enum(AportaProducto::class)],
            'ventana_desde' => ['required', 'date_format:Y-m-d'],
            'ventana_hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:ventana_desde'],
            'urgente' => ['nullable', 'boolean'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'precio_ha_propuesto' => ['nullable', 'numeric', 'gt:0', 'max:99999999', 'decimal:0,4'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('solicitudes.campos');
    }
}

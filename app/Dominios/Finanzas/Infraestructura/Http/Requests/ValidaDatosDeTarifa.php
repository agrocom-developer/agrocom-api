<?php

namespace App\Dominios\Finanzas\Infraestructura\Http\Requests;

use App\Dominios\Finanzas\Aplicacion\DatosTarifa;
use App\Dominios\Finanzas\Contratos\ModalidadPago;
use Illuminate\Validation\Rule;

/**
 * Concern compartido entre `CrearTarifaRequest` y `ActualizarTarifaRequest`
 * para normalizar datos de tarifa (modal, montos como string decimal).
 */
trait ValidaDatosDeTarifa
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:80'],
            'modalidad' => ['required', Rule::enum(ModalidadPago::class)],
            'monto_piloto' => ['required', 'numeric', 'min:0'],
            'monto_auxiliar' => ['required', 'numeric', 'min:0'],
            'predeterminada' => ['boolean'],
            'descripcion' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'nombre.required' => __('finanzas.tarifas.error_nombre_requerido'),
            'modalidad.required' => __('finanzas.tarifas.error_modalidad_requerida'),
            'modalidad.enum' => __('finanzas.tarifas.error_modalidad_requerida'),
            'monto_piloto.required' => __('finanzas.tarifas.error_monto_requerido'),
            'monto_piloto.numeric' => __('finanzas.tarifas.error_monto_invalido'),
            'monto_piloto.min' => __('finanzas.tarifas.error_monto_invalido'),
            'monto_auxiliar.required' => __('finanzas.tarifas.error_monto_requerido'),
            'monto_auxiliar.numeric' => __('finanzas.tarifas.error_monto_invalido'),
            'monto_auxiliar.min' => __('finanzas.tarifas.error_monto_invalido'),
        ];
    }

    public function datosTarifa(): DatosTarifa
    {
        $datos = $this->validated();

        return new DatosTarifa(
            nombre: (string) $datos['nombre'],
            modalidad: ModalidadPago::from((string) $datos['modalidad']),
            montoPiloto: (string) $datos['monto_piloto'],
            montoAuxiliar: (string) $datos['monto_auxiliar'],
            predeterminada: $this->boolean('predeterminada'),
            descripcion: isset($datos['descripcion']) ? (string) $datos['descripcion'] : null,
        );
    }
}

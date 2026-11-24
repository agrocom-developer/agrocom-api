<?php

namespace App\Dominios\Flota\Infraestructura\Http\Requests;

use App\Dominios\Compartido\Dominio\AccionAprobacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Cuerpo de las acciones de aprobación de un dron en el panel; el motivo es obligatorio al rechazar, suspender o dar de baja. */
final class ResolverAprobacionDronRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $acciones = array_map(
            static fn (AccionAprobacion $accion): string => $accion->value,
            array_filter(AccionAprobacion::cases(), static fn (AccionAprobacion $accion): bool => ! $accion->esDelSolicitante()),
        );

        $conMotivo = implode(',', array_map(
            static fn (AccionAprobacion $accion): string => $accion->value,
            array_filter(AccionAprobacion::cases(), static fn (AccionAprobacion $accion): bool => $accion->requiereMotivo()),
        ));

        return [
            'accion' => ['required', Rule::in($acciones)],
            'motivo' => ["required_if:accion,{$conMotivo}", 'nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['motivo' => __('proveedores.campos.motivo')];
    }
}

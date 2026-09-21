<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /panel/propiedades/{propiedad}/mapa` — punto de referencia
 * (latitud/longitud) y perímetro (`geometria`, GeoJSON `MultiPolygon`) de la
 * propiedad, movidos acá desde el formulario principal (adenda 16/9/2026 a
 * ADR 0018 punto 1 / ADR 0020). Mismas reglas que tenía `CrearPropiedadRequest`
 * para estos tres campos antes de moverlas. La autorización (permiso
 * `comercial.propiedad.editar`) se verifica en el controlador, no acá.
 */
final class GuardarUbicacionMapaPropiedadRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'latitud' => ['nullable', 'required_with:longitud', 'numeric', 'between:-90,90'],
            'longitud' => ['nullable', 'required_with:latitud', 'numeric', 'between:-180,180'],
            'geometria' => ['nullable', 'string', $this->reglaGeometriaValida()],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'latitud.required_with' => __('comercial.propiedades.error_coordenada_incompleta'),
            'longitud.required_with' => __('comercial.propiedades.error_coordenada_incompleta'),
        ];
    }

    private function reglaGeometriaValida(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $falla): void {
            if (! is_string($valor) || $valor === '') {
                return;
            }

            $decodificado = json_decode($valor, true);

            $esMultiPolygonMinimo = is_array($decodificado)
                && ($decodificado['type'] ?? null) === 'MultiPolygon'
                && is_array($decodificado['coordinates'] ?? null);

            if (! $esMultiPolygonMinimo) {
                $falla('comercial.propiedades.error_geometria_invalida')->translate();
            }
        };
    }
}

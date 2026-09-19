<?php

namespace App\Dominios\Comercial\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Lo que comparten crear y editar lotes en bloque (`GenerarLotesRequest`,
 * `EditarLotesBloqueRequest`): las reglas de las hectáreas por lote y de
 * `terreno.*` — UN solo juego de valores, que se aplica a todos los lotes del
 * bloque por igual (16/9/2026, pedido directo: no una fila por lote) — y su
 * traducción a los atributos que guarda el lote.
 *
 * `terreno.*` son las mismas reglas de terreno que `CrearLoteRequest` (switch
 * `limpio` + grado de obstáculos si no está marcado).
 */
abstract class LotesBloqueRequest extends FormRequest
{
    /** Límite de la tabla: `com_lotes.hectareas` es `DECIMAL(10,2)`. */
    protected const HECTAREAS_MAXIMAS = '99999999.99';

    /** @return list<mixed> */
    protected function reglasHectareas(bool $requeridas): array
    {
        return [$requeridas ? 'required' : 'nullable', 'numeric', 'gt:0', 'max:'.self::HECTAREAS_MAXIMAS];
    }

    /** @return array<string, mixed> */
    protected function reglasTerreno(): array
    {
        return [
            'terreno.desnivel' => ['nullable', Rule::in(['ninguno', 'algunos', 'varios', 'empinado'])],
            'terreno.limpio' => ['boolean'],
            'terreno.grado_obstaculos' => [
                Rule::requiredIf(fn () => ! $this->boolean('terreno.limpio')),
                'nullable',
                Rule::in(['pocos_obstaculos', 'algunos_obstaculos', 'muchos_obstaculos']),
            ],
            'terreno.restricciones' => ['nullable', 'string'],
        ];
    }

    /** @return array<string, string> */
    protected function mensajesBloque(): array
    {
        return [
            'hectareas.required' => __('comercial.validacion.lotes_bloque_hectareas_requeridas'),
            'hectareas.gt' => __('comercial.validacion.hectareas_mayor_a_cero'),
            'terreno.grado_obstaculos.required' => __('comercial.lotes.error_grado_obstaculos_requerido'),
        ];
    }

    /**
     * Los atributos de terreno tal como los guarda el lote: la columna
     * `limpieza` es un único string ('limpio' o un grado de obstáculos).
     *
     * @return array{desnivel: string|null, limpieza: string|null, restricciones: string|null}
     */
    public function atributosTerreno(): array
    {
        /** @var array<string, mixed> $terreno */
        $terreno = $this->validated('terreno') ?? [];

        return [
            'desnivel' => $this->cadenaONull($terreno['desnivel'] ?? null),
            'limpieza' => ! empty($terreno['limpio']) ? 'limpio' : $this->cadenaONull($terreno['grado_obstaculos'] ?? null),
            'restricciones' => $this->cadenaONull($terreno['restricciones'] ?? null),
        ];
    }

    private function cadenaONull(mixed $valor): ?string
    {
        return $valor === null || $valor === '' ? null : (string) $valor;
    }
}

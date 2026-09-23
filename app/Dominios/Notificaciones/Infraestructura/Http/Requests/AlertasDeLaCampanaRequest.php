<?php

namespace App\Dominios\Notificaciones\Infraestructura\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Las alertas técnicas que la campana mostraba al enviar «Marcar todas como
 * leídas» o «Limpiar» (`alertas[]`, ids de `ope_alertas`).
 *
 * Solo dice qué alertas tenía delante quien apretó: forma, no autorización.
 * Que pueda verlas (`operaciones.alerta.ver` en su rol activo) y que existan
 * lo revalida el servidor antes de escribir nada.
 */
final class AlertasDeLaCampanaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'alertas' => ['nullable', 'array', 'max:50'],
            'alertas.*' => ['integer', 'min:1', 'digits_between:1,18'],
        ];
    }

    /** @return list<int> */
    public function idsDeAlerta(): array
    {
        return array_values(array_unique(array_map(intval(...), $this->validated()['alertas'] ?? [])));
    }
}

<?php

namespace App\Dominios\Calificaciones\Aplicacion;

/**
 * Los parámetros de la política de calificación (ADR 0016 y 0039), leídos de
 * `config/calificaciones.php` cada vez que se piden: un valor cambiado en la
 * configuración rige desde la siguiente calificación, sin reiniciar nada.
 */
final class ParametrosDeCalificacion
{
    public function plazoDias(): int
    {
        return max(1, (int) config('calificaciones.plazo_dias', 7));
    }

    public function ventana(): int
    {
        return max(1, (int) config('calificaciones.ventana', 100));
    }

    /** @return array<string, string> peso por tipo de sujeto del lado del proveedor. */
    public function pesos(): array
    {
        return array_map('strval', (array) config('calificaciones.pesos', ['proveedor' => '0.50', 'piloto' => '0.35']));
    }

    public function comentarioMax(): int
    {
        return max(1, (int) config('calificaciones.comentario_max', 1000));
    }
}

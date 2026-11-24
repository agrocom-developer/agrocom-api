<?php

namespace App\Dominios\Solicitudes\Aplicacion;

/**
 * Parámetros de la política de calificación del cliente (ADR 0039). Leen `config/solicitudes.php`; nada de esto es un secreto.
 */
final class ParametrosDeServicio
{
    /** @return array{umbral: string, minimo_conteo: int, plazo_limitacion_dias: int} */
    public function calificacionDeCliente(): array
    {
        $politica = (array) config('solicitudes.calificacion_cliente', []);

        return [
            'umbral' => (string) ($politica['umbral'] ?? '3.5'),
            'minimo_conteo' => (int) ($politica['minimo_conteo'] ?? 10),
            'plazo_limitacion_dias' => (int) ($politica['plazo_limitacion_dias'] ?? 30),
        ];
    }
}

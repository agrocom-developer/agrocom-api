<?php

namespace App\Dominios\Solicitudes\Aplicacion;

/**
 * Parámetros del cierre del servicio (ADR 0016 y 0039): plazo de conformidad,
 * tolerancia de hectáreas, tasas de comisión y política de calificación del
 * cliente. Leen `config/solicitudes.php`; nada de esto es un secreto.
 */
final class ParametrosDeServicio
{
    public function conformidadHoras(): int
    {
        return max(1, (int) config('solicitudes.conformidad_horas', 72));
    }

    public function toleranciaHectareasPct(): string
    {
        return (string) config('solicitudes.tolerancia_hectareas_pct', '5');
    }

    /** @return array{propio: string, independiente: string, tercero: string, dron_vendido: string} */
    public function tasasDeComision(): array
    {
        $tasas = (array) config('solicitudes.comision', []);

        return [
            'propio' => (string) ($tasas['propio'] ?? '0'),
            'independiente' => (string) ($tasas['independiente'] ?? '15'),
            'tercero' => (string) ($tasas['tercero'] ?? '15'),
            'dron_vendido' => (string) ($tasas['dron_vendido'] ?? '10'),
        ];
    }

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

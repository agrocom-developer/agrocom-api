<?php

namespace App\Dominios\Metricas\Dominio;

/**
 * Los cuatro grupos en que el panel resume los estados de una solicitud: lo que está en el
 * mercado, lo que está en curso, lo que se cerró bien y lo que se cayó. El borrador no pertenece
 * a ninguno. Pura.
 */
final class GruposDeSolicitud
{
    public const ABIERTAS = 'abiertas';

    public const EN_CURSO = 'en_curso';

    public const CERRADAS = 'cerradas';

    public const CANCELADAS = 'canceladas';

    /** Los grupos, en el orden en que el panel los muestra. */
    public const TODOS = [self::ABIERTAS, self::EN_CURSO, self::CERRADAS, self::CANCELADAS];

    /** @return 'abiertas'|'en_curso'|'cerradas'|'canceladas'|null */
    public static function de(string $estado): ?string
    {
        return match ($estado) {
            'abierta', 'con_ofertas' => self::ABIERTAS,
            'adjudicada', 'en_ejecucion', 'disputada' => self::EN_CURSO,
            'finalizada', 'calificada' => self::CERRADAS,
            'cancelada', 'expirada' => self::CANCELADAS,
            default => null,
        };
    }
}

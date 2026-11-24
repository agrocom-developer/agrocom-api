<?php

namespace App\Dominios\Calificaciones\Aplicacion;

use App\Dominios\Calificaciones\Dominio\EstadoCalificacion;
use App\Dominios\Calificaciones\Dominio\SujetoCalificacion;
use App\Dominios\Calificaciones\Infraestructura\Eloquent\Calificacion;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * La cola de moderación del panel: las calificaciones reportadas primero y, tras
 * ellas, de la más nueva a la más vieja, con filtros por estado y por sujeto.
 */
final class ListarParaModeracion
{
    /** @return LengthAwarePaginator<int, Calificacion> */
    public function ejecutar(?string $estado, ?string $sujeto, int $porPagina = 15): LengthAwarePaginator
    {
        return Calificacion::query()
            ->with('moderaciones')
            ->when(EstadoCalificacion::tryFrom((string) $estado), fn ($consulta, EstadoCalificacion $e) => $consulta->where('estado', $e->value))
            ->when(SujetoCalificacion::tryFrom((string) $sujeto), fn ($consulta, SujetoCalificacion $s) => $consulta->where('sujeto_tipo', $s->value))
            ->orderByRaw("CASE estado WHEN 'en_moderacion' THEN 0 WHEN 'visible' THEN 1 ELSE 2 END")
            ->orderByDesc('id')
            ->paginate($porPagina)
            ->withQueryString();
    }
}

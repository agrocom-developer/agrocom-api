<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Campania\Contratos\DatosCampania;
use App\Dominios\Campania\Contratos\LecturaCampania;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cultivo;
use App\Dominios\Comercial\Infraestructura\Eloquent\LoteCampania;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * KPI del listado de Cultivos (pedido del dueño, 22/9/2026): cuántos cultivos
 * hay en el catálogo y cuántos están sembrados hoy —lotes y hectáreas— en las
 * campañas abiertas. Lo que crece con los datos (por cultivo, por propiedad)
 * es del tablero; acá solo la franja fija.
 */
final class ResumirSiembraDeCultivos
{
    public function __construct(private readonly LecturaCampania $lecturaCampania) {}

    /** @return array{cultivos: int, sembrados: int, lotes: int, hectareas: string, campanias: list<string>} */
    public function ejecutar(): array
    {
        $campanias = $this->lecturaCampania->abiertas();
        $cultivos = Cultivo::query()->count();

        if ($campanias === []) {
            return ['cultivos' => $cultivos, 'sembrados' => 0, 'lotes' => 0, 'hectareas' => '0.00', 'campanias' => []];
        }

        $siembras = LoteCampania::query()
            ->whereIn('campania_id', array_map(fn (DatosCampania $campania): int => $campania->id, $campanias))
            ->whereHas('lote')
            ->get(['cultivo_id', 'lote_id', 'hectareas_sembradas']);

        $hectareas = $siembras->reduce(
            fn (BigDecimal $acumulado, LoteCampania $siembra): BigDecimal => $acumulado->plus($siembra->hectareas_sembradas),
            BigDecimal::zero(),
        );

        return [
            'cultivos' => $cultivos,
            'sembrados' => $siembras->pluck('cultivo_id')->unique()->count(),
            'lotes' => $siembras->pluck('lote_id')->unique()->count(),
            'hectareas' => (string) $hectareas->toScale(2, RoundingMode::HalfUp),
            'campanias' => array_map(fn (DatosCampania $campania): string => $campania->codigo, $campanias),
        ];
    }
}

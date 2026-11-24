<?php

namespace App\Dominios\Metricas\Aplicacion;

use App\Dominios\Metricas\Aplicacion\Bloques\BloqueCalidad;
use App\Dominios\Metricas\Aplicacion\Bloques\BloqueIngreso;
use App\Dominios\Metricas\Aplicacion\Bloques\BloqueMercado;
use App\Dominios\Metricas\Aplicacion\Bloques\BloqueOferta;
use App\Dominios\Metricas\Aplicacion\Bloques\BloqueProveedores;
use App\Dominios\Metricas\Dominio\PeriodoDeMetricas;
use Illuminate\Support\Carbon;

/**
 * Las métricas del mercado del panel (especificación §14): mercado, calidad,
 * oferta, volumen y tabla por proveedor. Módulo de solo lectura (ADR 0012): cada
 * bloque lee de las vistas `vw_metricas_*` por un contrato de `Contratos/` y
 * aplica las razones de `Dominio/Razones` en decimal exacto (invariante 6);
 * sin divisor, `null` («sin datos»), nunca cero. Este caso de uso solo los reúne.
 *
 * No hay cobro en la aplicación (ADR 0034): el volumen es la suma del precio final y el
 * pago ocurre fuera de ella.
 */
final class CalcularMetricas
{
    public function __construct(
        private readonly BloqueMercado $mercado,
        private readonly BloqueCalidad $calidad,
        private readonly BloqueOferta $oferta,
        private readonly BloqueIngreso $ingreso,
        private readonly BloqueProveedores $proveedores,
    ) {}

    /**
     * @return array{
     *     periodo: array{dias: int, desde: string, hasta: string},
     *     mercado: array<string, mixed>,
     *     calidad: array<string, mixed>,
     *     oferta: array<string, mixed>,
     *     ingreso: array<string, mixed>,
     *     proveedores: list<array<string, mixed>>
     * }
     */
    public function ejecutar(PeriodoDeMetricas $periodo): array
    {
        return [
            'periodo' => [
                'dias' => $periodo->dias,
                'desde' => Carbon::instance($periodo->desde)->toDateString(),
                'hasta' => Carbon::instance($periodo->hasta)->toDateString(),
            ],
            'mercado' => $this->mercado->armar($periodo),
            'calidad' => $this->calidad->armar($periodo),
            'oferta' => $this->oferta->armar($periodo),
            'ingreso' => $this->ingreso->armar($periodo),
            'proveedores' => $this->proveedores->armar($periodo),
        ];
    }
}

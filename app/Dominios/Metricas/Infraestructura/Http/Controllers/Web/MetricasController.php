<?php

namespace App\Dominios\Metricas\Infraestructura\Http\Controllers\Web;

use App\Dominios\Compartido\Infraestructura\Http\TextoDeFiltro;
use App\Dominios\Metricas\Aplicacion\CalcularMetricas;
use App\Dominios\Metricas\Dominio\PeriodoDeMetricas;
use App\Dominios\Seguridad\Contratos\AutorizacionPanelWeb;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Métricas del mercado del panel (`GET /panel/metricas`, especificación §14):
 * solicitudes, asignación, tasa de aceptación, calificación, disputas, tiempo hasta
 * la aceptación e ingreso, de los últimos 7, 30 o 90 días. Solo lectura.
 *
 * Gateada por `metricas.panel.ver`, verificado DENTRO del controlador contra el
 * ROL ACTIVO (invariante 10).
 */
final class MetricasController
{
    private const PERMISO_VER = 'metricas.panel.ver';

    public function __construct(private readonly AutorizacionPanelWeb $autorizacion) {}

    public function index(Request $request, CalcularMetricas $calcular): View
    {
        abort_unless($this->autorizacion->tienePermiso($request, self::PERMISO_VER), 403);

        $periodo = PeriodoDeMetricas::de(TextoDeFiltro::de($request, 'dias'), new DateTimeImmutable);

        return view('metricas::pages.metricas.index', [
            ...$this->autorizacion->cascara($request),
            'metricas' => $calcular->ejecutar($periodo),
            'diasValidos' => PeriodoDeMetricas::DIAS_VALIDOS,
        ]);
    }
}

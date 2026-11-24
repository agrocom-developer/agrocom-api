<?php

namespace App\Dominios\Metricas\Infraestructura\Lectura;

use App\Dominios\Metricas\Contratos\LecturaMetricasOferta;
use App\Dominios\Metricas\Dominio\PeriodoDeMetricas;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Cifras de oferta leídas de `vw_metricas_catalogo`, `_ofertas_proveedor` y `_cancelaciones_proveedor`. */
final class LecturaMetricasOfertaVistas implements LecturaMetricasOferta
{
    public function proveedoresEn(string $estado): int
    {
        return DB::table('vw_metricas_catalogo')->where('clase', 'proveedor')->where('estado', $estado)->count();
    }

    public function dronesEn(string $estado): int
    {
        return DB::table('vw_metricas_catalogo')->where('clase', 'dron')->where('estado', $estado)->count();
    }

    public function ofertasAceptadas(PeriodoDeMetricas $periodo): int
    {
        return $this->ofertas($periodo)->whereIn('estado', ['aceptada', 'cerrada', 'reemplazada'])->whereNotNull('respondida_en')->count();
    }

    public function ofertasNoAceptadas(PeriodoDeMetricas $periodo): int
    {
        return $this->ofertas($periodo)->whereIn('estado', ['rechazada', 'vencida'])->count();
    }

    public function cancelacionesDelProveedor(PeriodoDeMetricas $periodo): int
    {
        return DB::table('vw_metricas_cancelaciones_proveedor')->whereBetween('cancelada_en', VentanaSql::de($periodo))->count();
    }

    private function ofertas(PeriodoDeMetricas $periodo): Builder
    {
        return DB::table('vw_metricas_ofertas_proveedor')->whereBetween('ofrecida_en', VentanaSql::de($periodo));
    }
}

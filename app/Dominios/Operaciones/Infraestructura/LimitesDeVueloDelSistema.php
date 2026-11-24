<?php

namespace App\Dominios\Operaciones\Infraestructura;

use App\Dominios\Operaciones\Contratos\LecturaLimitesDeVuelo;
use App\Dominios\Operaciones\Dominio\LimitesEfectivos;

/** Los límites por defecto del sistema ({@see LimitesEfectivos::porDefecto()}). */
final class LimitesDeVueloDelSistema implements LecturaLimitesDeVuelo
{
    public function efectivos(): array
    {
        $limites = LimitesEfectivos::porDefecto();

        return [
            'humedad_min_pct' => $limites->humedadMinPct,
            'viento_max_kmh' => $limites->vientoMaxKmh,
            'temperatura_max_c' => $limites->temperaturaMaxC,
            'humedad_max_pct' => $limites->humedadMaxPct,
            'altura_vuelo_m' => $limites->alturaVueloM,
            'velocidad_vuelo_kmh' => $limites->velocidadVueloKmh,
            'ancho_pasada_m' => $limites->anchoPasadaM,
        ];
    }
}

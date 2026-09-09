<?php

namespace App\Dominios\Comercial\Aplicacion;

/**
 * Resultado del informe de avance de contratos (HU-52, tarea 75, espec
 * §9.1). `porCultivo` alcanza para las dos pestañas de la pantalla — ver
 * {@see GrupoCultivoInforme}.
 */
final readonly class InformeAvanceContratos
{
    /** @param  list<GrupoCultivoInforme>  $porCultivo  ordenados por nombre de cultivo. */
    public function __construct(
        public array $porCultivo,
    ) {}
}

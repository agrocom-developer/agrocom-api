<?php

namespace App\Dominios\Comercial\Aplicacion;

use App\Dominios\Comercial\Dominio\TramoAvance;

/**
 * Cliente dentro de un grupo de cultivo, para la pestaña "Por cliente" del
 * informe (HU-52, tarea 75, espec §9.1). Los totales son la suma exacta
 * (`Brick\Math\BigDecimal`) de sus propios contratos — nunca una segunda
 * fórmula de avance.
 */
final readonly class GrupoClienteInforme
{
    /**
     * @param  list<FilaContratoInforme>  $contratos  ordenados por vencimiento más cercano.
     */
    public function __construct(
        public int $clienteId,
        public string $clienteNombre,
        public string $hectareasContratadas,
        public string $hectareasAplicadas,
        public string $hectareasAAplicar,
        public int $porcentaje,
        public TramoAvance $tramo,
        public array $contratos,
    ) {}
}

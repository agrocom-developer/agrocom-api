<?php

namespace App\Dominios\Personal\Contratos;

/**
 * Lo que Personal sabe de un recurso (dron, vehículo, generador o batería), en
 * datos primitivos, para el resumen relacionado de su ficha en otro módulo
 * (ADR 0003, regla 2): cuántas cuadrillas lo tienen hoy, cuántas lo tuvieron
 * alguna vez y los códigos de las vigentes.
 */
final readonly class DatosCuadrillasDeRecurso
{
    /** @param  list<string>  $codigosVigentes  código de cada cuadrilla que lo tiene a la fecha, sin repetir. */
    public function __construct(
        public int $vigentes,
        public int $historial,
        public array $codigosVigentes,
    ) {}
}

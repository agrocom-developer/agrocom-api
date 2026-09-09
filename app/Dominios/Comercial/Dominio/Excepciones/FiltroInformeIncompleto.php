<?php

namespace App\Dominios\Comercial\Dominio\Excepciones;

use DomainException;

/**
 * El informe de avance de contratos (HU-52, tarea 75, espec §9.1) exige al
 * menos un cliente y al menos un cultivo — la guarda vive también en el caso
 * de uso, no solo en el request del panel, para que nadie pueda invocar
 * `ObtenerInformeAvanceContratos` sin esa entrada obligatoria.
 */
final class FiltroInformeIncompleto extends DomainException
{
    public static function porFaltaDeCliente(): self
    {
        return new self('El informe de avance de contratos requiere al menos un cliente.');
    }

    public static function porFaltaDeCultivo(): self
    {
        return new self('El informe de avance de contratos requiere al menos un cultivo.');
    }
}

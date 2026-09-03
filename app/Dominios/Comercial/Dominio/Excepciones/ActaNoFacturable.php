<?php

namespace App\Dominios\Comercial\Dominio\Excepciones;

use DomainException;

/**
 * El acta pedida no está en condiciones de generar una factura (HU-31, tarea
 * 45). Tres causas, cada una con su mensaje específico — mismo criterio que
 * `Operaciones\Dominio\Excepciones\TrabajoNoListoParaActa`:
 *   - el acta no existe;
 *   - existe pero todavía no está `firmada` (criterio de aceptación: "factura
 *     solo desde acta en estado firmada");
 *   - ya tiene una factura viva (criterio de aceptación: "no permite facturar
 *     dos veces el mismo trabajo" — un acta es única por trabajo, así que
 *     única por acta implica única por trabajo).
 */
final class ActaNoFacturable extends DomainException
{
    public static function porNoExistir(int $actaId): self
    {
        return new self("El acta #{$actaId} no existe.");
    }

    public static function porNoEstarFirmada(int $actaId): self
    {
        return new self("El acta #{$actaId} debe estar firmada para poder facturarse.");
    }

    public static function porYaFacturada(int $actaId): self
    {
        return new self("El acta #{$actaId} ya tiene una factura emitida.");
    }
}

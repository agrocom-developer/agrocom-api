<?php

namespace App\Dominios\Comercial\Dominio\Excepciones;

use RuntimeException;

/**
 * ADR 0020: una propiedad con lotes activos asociados no se puede dar de
 * baja — `propiedad_id` no admite NULL en `com_lotes`
 * (`restrictOnDelete()` en la migración, que solo protege el DELETE físico
 * que esta capa nunca ejecuta), y un lote sin propiedad quedaría huérfano de
 * un padre que ya no aparece en ningún listado ni cascada del formulario de
 * lote. Se rechaza con un mensaje claro en vez de dejar la propiedad
 * inaccesible con lotes colgando.
 */
final class PropiedadConLotesAsociados extends RuntimeException
{
    public static function paraPropiedad(string $nombre): self
    {
        return new self("La propiedad '{$nombre}' tiene lotes asociados y no se puede eliminar.");
    }
}

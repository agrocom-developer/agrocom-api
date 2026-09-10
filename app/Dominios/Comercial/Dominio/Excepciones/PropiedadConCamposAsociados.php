<?php

namespace App\Dominios\Comercial\Dominio\Excepciones;

use RuntimeException;

/**
 * ADR 0018: una propiedad con campos físicos activos no se puede dar de baja
 * — a diferencia de `EliminarCliente`/`EliminarCampo` (que no propagan la
 * baja a sus hijos porque esos hijos siguen siendo alcanzables desde su
 * propia pantalla), un campo sin propiedad quedaría huérfano de un padre que
 * ya no aparece en ningún listado ni cascada del formulario de lote, y
 * `propiedad_id` no admite NULL (`restrictOnDelete()` en la migración, que
 * solo protege el DELETE físico que esta capa nunca ejecuta). Se rechaza con
 * un mensaje claro en vez de dejar la propiedad inaccesible con campos
 * colgando.
 */
final class PropiedadConCamposAsociados extends RuntimeException
{
    public static function paraPropiedad(string $nombre): self
    {
        return new self("La propiedad '{$nombre}' tiene campos asociados y no se puede eliminar.");
    }
}
